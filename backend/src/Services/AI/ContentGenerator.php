<?php

namespace AutoThreads\Services\AI;

use AutoThreads\Models\GeneratedPost;
use AutoThreads\Models\AffiliateLink;
use AutoThreads\Models\Niche;
use AutoThreads\Services\AI\PromptBuilder;
use AutoThreads\Services\AI\Humanizer;
use AutoThreads\Services\AI\QualityScorer;
use GuzzleHttp\Client;

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

    public function __construct()
    {
        $this->httpClient = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'timeout' => 30,
            'verify' => guzzle_ssl_verify(),
        ]);
        $this->promptBuilder = new PromptBuilder();
        $this->humanizer = new Humanizer();
        $this->scorer = new QualityScorer();
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

        // Humanize the content
        $humanized = $this->humanizer->process($aiResponse['content']);

        // Score quality
        $scores = $this->scorer->score($humanized);

        // Store the generated post
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
            'ai_model' => $_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini',
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

        return $post;
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
     * Call OpenAI API
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
                'model' => $_ENV['OPENAI_MODEL'] ?? 'gpt-4',
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

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'tokens_used' => $data['usage']['total_tokens'] ?? 0,
            'time_ms' => $timeMs,
        ];
    }

    private function calculateCost(int $tokens): float
    {
        // GPT-4 pricing: ~$0.03/1K input, ~$0.06/1K output (approximate)
        return round(($tokens / 1000) * 0.045, 4);
    }
}
