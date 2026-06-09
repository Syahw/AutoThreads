<?php

namespace AutoThreads\Services\AI;

use AutoThreads\Models\GeneratedPost;
use AutoThreads\Models\AffiliateLink;
use AutoThreads\Models\Niche;
use AutoThreads\Models\AiUsageLog;
use AutoThreads\Services\AI\PromptBuilder;
use AutoThreads\Services\AI\Humanizer;
use AutoThreads\Services\AI\LightHumanizer;
use AutoThreads\Services\AI\AIContentReviewer;
use AutoThreads\Services\AI\ContentDiversityManager;
use AutoThreads\Services\AI\ContentQualityChecker;
use AutoThreads\Services\AI\QualityScorer;
use AutoThreads\Services\Media\ProcessedImage;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

/**
 * ContentGenerator - Core AI content generation pipeline
 *
 * Pipeline:
 *   DiversityManager → PromptBuilder → GPT-4o Mini (generate)
 *   → AIContentReviewer (polish pass) → LightHumanizer
 *   → ContentQualityChecker → QualityScorer → Store → DiversityManager.record()
 *
 * Design decisions:
 * - Model does the humanization work; PHP only strips what should never appear
 * - Review pass uses low temperature (0.25) to make targeted edits only
 * - Quality flags are non-blocking — logged and stored in metadata
 * - Diversity memory persists across requests via file-based JSON store
 */
class ContentGenerator
{
    private Client $httpClient;
    private PromptBuilder $promptBuilder;
    private Humanizer $humanizer;
    private LightHumanizer $lightHumanizer;
    private AIContentReviewer $reviewer;
    private ContentDiversityManager $diversityManager;
    private ContentQualityChecker $qualityChecker;
    private QualityScorer $scorer;
    private OpenAIResponsesClient $visionClient;
    private ImageAnalysisConfig $imageConfig;
    private ?LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        $this->imageConfig = new ImageAnalysisConfig();
        $this->httpClient = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'timeout' => 30,
            'verify' => guzzle_ssl_verify(),
        ]);
        $this->promptBuilder    = new PromptBuilder();
        $this->humanizer        = new Humanizer();
        $this->lightHumanizer   = new LightHumanizer();
        $this->reviewer         = new AIContentReviewer($logger);
        $this->diversityManager = new ContentDiversityManager();
        $this->qualityChecker   = new ContentQualityChecker();
        $this->scorer           = new QualityScorer();
        $this->visionClient     = new OpenAIResponsesClient($this->imageConfig, $logger);
    }

    public function getImageConfig(): ImageAnalysisConfig
    {
        return $this->imageConfig;
    }

    /**
     * Generate content for a given configuration.
     *
     * Pipeline:
     *   1. Diversity hint injection
     *   2. GPT-4o Mini generation
     *   3. GPT-4o Mini self-review / polish pass
     *   4. LightHumanizer (buzzword strip, em-dash removal, exclamation cap)
     *   5. ContentQualityChecker (flag logging)
     *   6. QualityScorer
     *   7. Persist to DB
     *   8. Record hook for future diversity checks
     */
    public function generate(array $config): GeneratedPost
    {
        $userId          = $config['user_id'];
        $nicheId         = $config['niche_id'] ?? null;
        $topicId         = $config['topic_id'] ?? null;
        $affiliateLinkId = $config['affiliate_link_id'] ?? null;
        $category        = $config['category'] ?? 'general';
        $tone            = $config['tone'] ?? null;
        $variations      = $config['variations'] ?? 1;

        $niche         = $nicheId ? Niche::find($nicheId) : null;
        $affiliateLink = $affiliateLinkId ? AffiliateLink::find($affiliateLinkId) : null;

        // 1. Build prompt with diversity hint
        $prompt = $this->promptBuilder->build([
            'niche'           => $niche,
            'category'        => $category,
            'tone'            => $tone,
            'affiliate'       => $affiliateLink,
            'target_audience' => $niche?->target_audience,
            'cta_style'       => $affiliateLink?->cta_style ?? 'soft',
            'diversity_hint'  => $this->diversityManager->buildDiversityHint(),
        ]);

        // 2. Generate
        $aiResponse  = $this->callOpenAI($prompt['system'], $prompt['user']);
        $totalTokens = $aiResponse['tokens_used'];

        // 3. Self-review / polish pass
        $reviewApplied = false;
        $contentToProcess = $aiResponse['content'];
        try {
            $reviewResult     = $this->reviewer->review($aiResponse['content']);
            $contentToProcess = $reviewResult['content'];
            $totalTokens     += $reviewResult['tokens_used'];
            $reviewApplied    = true;
        } catch (\Throwable $e) {
            $this->logger?->warning('Review pass failed, using raw generation', [
                'error' => $e->getMessage(),
            ]);
        }

        // 4. Light humanize
        $humanized = $this->lightHumanizer->process($contentToProcess);

        // 5. Quality check (non-blocking — flags stored in metadata)
        $qualityCheck = $this->qualityChecker->check($humanized);
        if (!$qualityCheck['passed']) {
            $this->logger?->info('Quality flags on generated content', [
                'user_id'  => $userId,
                'category' => $category,
                'flags'    => $qualityCheck['flags'],
            ]);
        }

        // 6. Score
        $scores = $this->scorer->score($humanized);

        // 7. Persist
        $post = GeneratedPost::create([
            'user_id'          => $userId,
            'niche_id'         => $niche?->id,
            'topic_id'         => $topicId,
            'affiliate_link_id' => $affiliateLinkId,
            'content'          => $humanized['content'],
            'hook'             => $humanized['hook'],
            'cta'              => $humanized['cta'],
            'hashtags'         => $humanized['hashtags'] ?? [],
            'category'         => $category,
            'tone'             => $tone ?? $prompt['tone_used'],
            'writing_style'    => $prompt['style_used'],
            'quality_score'    => $scores['overall'],
            'humanization_score' => $scores['humanization'],
            'status'           => 'draft',
            'ai_model'         => $aiResponse['model'],
            'tokens_used'      => $totalTokens,
            'generation_cost'  => $this->calculateCost($totalTokens),
            'variations_count' => $variations,
            'metadata'         => [
                'prompt_hash'       => md5($prompt['user']),
                'generation_time_ms' => $aiResponse['time_ms'],
                'replies'           => $humanized['replies'] ?? [],
                'thread_format'     => !empty($humanized['replies']),
                'review_applied'    => $reviewApplied,
                'quality_flags'     => $qualityCheck['flags'],
            ],
        ]);

        // 8. Record hook for diversity memory
        $this->diversityManager->record(
            $humanized['hook'],
            $niche?->name ?? $category
        );

        $this->logAiUsage((int) $userId, $aiResponse, 'generate');

        return $post;
    }

    /**
     * Generate thread content grounded in one or more reference images (GPT-4o Mini vision).
     *
     * @param  list<ProcessedImage>  $images
     * @return array{post: GeneratedPost, image_analysis: array<string, mixed>}
     */
    public function generateWithImages(array $config, array $images): array
    {
        if ($images === []) {
            throw new \InvalidArgumentException('At least one reference image is required');
        }

        $userId = $config['user_id'];
        $nicheId = $config['niche_id'] ?? null;
        $topicId = $config['topic_id'] ?? null;
        $affiliateLinkId = $config['affiliate_link_id'] ?? null;
        $category = $config['category'] ?? 'general';
        $tone = $config['tone'] ?? null;
        $variations = $config['variations'] ?? 1;
        $highDetail = (bool) ($config['high_detail'] ?? false);

        $niche = $nicheId ? Niche::find($nicheId) : null;
        $affiliateLink = $affiliateLinkId ? AffiliateLink::find($affiliateLinkId) : null;

        $prompt = $this->promptBuilder->build([
            'niche' => $niche,
            'category' => $category,
            'tone' => $tone,
            'affiliate' => $affiliateLink,
            'target_audience' => $niche?->target_audience,
            'cta_style' => $affiliateLink?->cta_style ?? 'soft',
        ]);

        $systemPrompt = trim($prompt['system'] . "\n\n" . $this->promptBuilder->buildVisionSystemAddendum());
        $visionUserPrompt = $this->promptBuilder->buildVisionUserPrompt(
            $prompt['user'],
            count($images),
            $niche?->name
        );

        $detail    = $highDetail ? 'high' : null;
        $aiResponse = $this->visionClient->generateWithImages($systemPrompt, $visionUserPrompt, $images, $detail);

        $threadContent = $this->stripExtractedTextBlock($aiResponse['content']);

        // Self-review pass
        $reviewApplied = false;
        $contentToProcess = $threadContent;
        try {
            $reviewResult     = $this->reviewer->review($threadContent);
            $contentToProcess = $reviewResult['content'];
            $reviewApplied    = true;
        } catch (\Throwable $e) {
            $this->logger?->warning('Vision review pass failed, using raw content', [
                'error' => $e->getMessage(),
            ]);
        }

        $humanized    = $this->lightHumanizer->process($contentToProcess);
        $qualityCheck = $this->qualityChecker->check($humanized);
        $scores       = $this->scorer->score($humanized);

        $imageMetadata = array_map(fn (ProcessedImage $img) => $img->toMetadata(), $images);
        $processingMeta = [
            'images' => $imageMetadata,
            'detail_requested' => $highDetail ? 'high' : $this->imageConfig->defaultDetail(),
            'estimated_image_tokens' => $aiResponse['estimated_image_tokens'],
            'usage' => $aiResponse['usage'],
            'model' => $aiResponse['model'],
            'response_id' => $aiResponse['response_id'],
            'generation_time_ms' => $aiResponse['time_ms'],
        ];

        $post = GeneratedPost::create([
            'user_id' => $userId,
            'niche_id' => $nicheId,
            'topic_id' => $topicId,
            'affiliate_link_id' => $affiliateLinkId,
            'content' => $humanized['content'],
            'hook' => $humanized['hook'],
            'cta' => $humanized['cta'],
            'hashtags' => $humanized['hashtags'] ?? [],
            'category' => $category,
            'tone' => $tone ?? $prompt['tone_used'],
            'writing_style' => $prompt['style_used'],
            'quality_score' => $scores['overall'],
            'humanization_score' => $scores['humanization'],
            'status' => 'draft',
            'ai_model' => $aiResponse['model'],
            'tokens_used' => $aiResponse['usage']['total_tokens'],
            'generation_cost' => $this->calculateCost($aiResponse['usage']['total_tokens']),
            'variations_count' => $variations,
            'metadata' => [
                'prompt_hash'        => md5($visionUserPrompt),
                'generation_time_ms' => $aiResponse['time_ms'],
                'replies'            => $humanized['replies'] ?? [],
                'thread_format'      => !empty($humanized['replies']),
                'review_applied'     => $reviewApplied,
                'quality_flags'      => $qualityCheck['flags'],
                'image_generation'   => $processingMeta,
                'extracted_text'     => $aiResponse['extracted_text'],
            ],
        ]);

        $this->diversityManager->record($humanized['hook'], $niche?->name ?? $category);

        if (!$qualityCheck['passed']) {
            $this->logger?->info('Quality flags on vision-generated content', [
                'user_id' => $userId,
                'flags'   => $qualityCheck['flags'],
            ]);
        }

        $this->logger?->info('Vision content generated', [
            'user_id'                => $userId,
            'post_id'                => $post->id,
            'images'                 => count($images),
            'estimated_image_tokens' => $aiResponse['estimated_image_tokens'],
            'total_tokens'           => $aiResponse['usage']['total_tokens'],
        ]);

        $this->logAiUsage((int) $userId, [
            'model' => $aiResponse['model'],
            'tokens_used' => $aiResponse['usage']['total_tokens'],
            'prompt_tokens' => $aiResponse['usage']['input_tokens'],
            'completion_tokens' => $aiResponse['usage']['output_tokens'],
            'time_ms' => $aiResponse['time_ms'],
        ], 'generate');

        return [
            'post' => $post,
            'image_analysis' => [
                'generated_content' => $humanized['content'],
                'extracted_text' => $aiResponse['extracted_text'],
                'estimated_image_tokens' => $aiResponse['estimated_image_tokens'],
                'usage' => $aiResponse['usage'],
                'processing' => $processingMeta,
            ],
        ];
    }

    /**
     * Generate multiple variations of content
     */
    public function generateVariations(array $config, int $count = 3): array
    {
        $posts = [];
        $parentId = null;

        for ($i = 0; $i < $count; $i++) {
            // Rotate tone for each variation
            $config['tone'] = $this->promptBuilder->getRotatedTone($i);
            $post = $this->generate($config);

            if ($parentId === null) {
                $parentId = $post->id;
            } else {
                $post->parent_post_id = $parentId;
                $post->save();
            }

            $posts[] = $post;
        }

        return $posts;
    }

    /**
     * Call OpenAI Chat Completions API (text-only generation).
     */
    private function callOpenAI(string $systemPrompt, string $userPrompt): array
    {
        $startTime = microtime(true);

        $response = $this->httpClient->post('chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $_ENV['OPENAI_API_KEY'],
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens' => (int) ($_ENV['OPENAI_MAX_TOKENS'] ?? 3000),
                'temperature' => (float) ($_ENV['OPENAI_TEMPERATURE'] ?? 0.75),
                'presence_penalty' => 0.6,
                'frequency_penalty' => 0.4,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $timeMs = (int) ((microtime(true) - $startTime) * 1000);
        $usage = $data['usage'] ?? [];

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'tokens_used' => $usage['total_tokens'] ?? 0,
            'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
            'completion_tokens' => $usage['completion_tokens'] ?? 0,
            'time_ms' => $timeMs,
            'model' => $data['model'] ?? ($_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini'),
        ];
    }

    private function stripExtractedTextBlock(string $content): string
    {
        return trim(preg_replace('/\[EXTRACTED_TEXT\].*?\[\/EXTRACTED_TEXT\]\s*/s', '', $content) ?? $content);
    }

    private function calculateCost(int $tokens): float
    {
        return round(($tokens / 1000) * 0.0003, 4);
    }

    /** @param array<string, mixed> $aiResponse */
    private function logAiUsage(int $userId, array $aiResponse, string $action): void
    {
        try {
            $total = (int) ($aiResponse['tokens_used'] ?? 0);
            AiUsageLog::create([
                'user_id' => $userId,
                'model' => (string) ($aiResponse['model'] ?? 'gpt-4o-mini'),
                'action' => $action,
                'prompt_tokens' => (int) ($aiResponse['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($aiResponse['completion_tokens'] ?? 0),
                'total_tokens' => $total,
                'cost' => $this->calculateCost($total),
                'response_time_ms' => (int) ($aiResponse['time_ms'] ?? 0),
                'success' => true,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Non-blocking if table missing
        }
    }
}
