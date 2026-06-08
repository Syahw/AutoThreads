<?php

namespace AutoThreads\Services\AI;

use AutoThreads\Models\GeneratedPost;
use AutoThreads\Models\AffiliateLink;
use AutoThreads\Models\Niche;
use AutoThreads\Models\AiUsageLog;
use AutoThreads\Services\AI\PromptBuilder;
use AutoThreads\Services\AI\Humanizer;
use AutoThreads\Services\AI\QualityScorer;
use AutoThreads\Services\Media\ProcessedImage;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

/**
 * ContentGenerator - Core AI content generation pipeline
 * 
 * Pipeline: Topic → Prompt Build → AI Generate → Humanize → Score → Store
 * 
 * Design decisions:
 * - Modular pipeline allows swapping any step independently
 * - Each step is testable in isolation
 * - Supports multiple AI providers via adapter pattern
 * - Anti-repetition built into prompt construction
 */
class ContentGenerator
{
    private Client $httpClient;
    private PromptBuilder $promptBuilder;
    private Humanizer $humanizer;
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
        $this->promptBuilder = new PromptBuilder();
        $this->humanizer = new Humanizer();
        $this->scorer = new QualityScorer();
        $this->visionClient = new OpenAIResponsesClient($this->imageConfig, $logger);
    }

    public function getImageConfig(): ImageAnalysisConfig
    {
        return $this->imageConfig;
    }

    /**
     * Generate content for a given configuration
     */
    public function generate(array $config): GeneratedPost
    {
        $userId = $config['user_id'];
        $nicheId = $config['niche_id'] ?? null;
        $topicId = $config['topic_id'] ?? null;
        $affiliateLinkId = $config['affiliate_link_id'] ?? null;
        $category = $config['category'] ?? 'general';
        $tone = $config['tone'] ?? null;
        $variations = $config['variations'] ?? 1;

        // Load related data
        $niche = $nicheId ? Niche::find($nicheId) : null;
        $affiliateLink = $affiliateLinkId ? AffiliateLink::find($affiliateLinkId) : null;

        // Build the prompt
        $prompt = $this->promptBuilder->build([
            'niche' => $niche,
            'category' => $category,
            'tone' => $tone,
            'affiliate' => $affiliateLink,
            'target_audience' => $niche?->target_audience,
            'cta_style' => $affiliateLink?->cta_style ?? 'soft',
        ]);

        // Call OpenAI API
        $aiResponse = $this->callOpenAI($prompt['system'], $prompt['user']);

        $post = $this->storeGeneratedPost($config, $prompt, $aiResponse, $niche, $affiliateLinkId, $topicId, $variations);
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

        $detail = $highDetail ? 'high' : null;
        $aiResponse = $this->visionClient->generateWithImages($systemPrompt, $visionUserPrompt, $images, $detail);

        $threadContent = $this->stripExtractedTextBlock($aiResponse['content']);
        $humanized = $this->humanizer->process($threadContent);
        $scores = $this->scorer->score($humanized);

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
                'prompt_hash' => md5($visionUserPrompt),
                'generation_time_ms' => $aiResponse['time_ms'],
                'replies' => $humanized['replies'] ?? [],
                'thread_format' => !empty($humanized['replies']),
                'image_generation' => $processingMeta,
                'extracted_text' => $aiResponse['extracted_text'],
            ],
        ]);

        $this->logger?->info('Vision content generated', [
            'user_id' => $userId,
            'post_id' => $post->id,
            'images' => count($images),
            'estimated_image_tokens' => $aiResponse['estimated_image_tokens'],
            'total_tokens' => $aiResponse['usage']['total_tokens'],
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

    /** @param array<string, mixed> $prompt */
    private function storeGeneratedPost(
        array $config,
        array $prompt,
        array $aiResponse,
        ?Niche $niche,
        ?int $affiliateLinkId,
        ?int $topicId,
        int $variations
    ): GeneratedPost {
        $humanized = $this->humanizer->process($aiResponse['content']);
        $scores = $this->scorer->score($humanized);

        return GeneratedPost::create([
            'user_id' => $config['user_id'],
            'niche_id' => $niche?->id,
            'topic_id' => $topicId,
            'affiliate_link_id' => $affiliateLinkId,
            'content' => $humanized['content'],
            'hook' => $humanized['hook'],
            'cta' => $humanized['cta'],
            'hashtags' => $humanized['hashtags'] ?? [],
            'category' => $config['category'] ?? 'general',
            'tone' => $config['tone'] ?? $prompt['tone_used'],
            'writing_style' => $prompt['style_used'],
            'quality_score' => $scores['overall'],
            'humanization_score' => $scores['humanization'],
            'status' => 'draft',
            'ai_model' => $aiResponse['model'] ?? ($_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini'),
            'tokens_used' => $aiResponse['tokens_used'],
            'generation_cost' => $this->calculateCost($aiResponse['tokens_used']),
            'variations_count' => $variations,
            'metadata' => [
                'prompt_hash' => md5($prompt['user']),
                'generation_time_ms' => $aiResponse['time_ms'],
                'replies' => $humanized['replies'] ?? [],
                'thread_format' => !empty($humanized['replies']),
            ],
        ]);
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
