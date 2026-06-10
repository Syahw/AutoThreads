<?php

namespace AutoThreads\Services\AI;

use AutoThreads\Services\Media\ProcessedImage;
use Psr\Log\LoggerInterface;

class ThreadConfigImageAnalyzer
{
    private OpenAIResponsesClient $visionClient;
    private ?LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $config = new ImageAnalysisConfig();
        $this->visionClient = new OpenAIResponsesClient($config, $logger);
        $this->logger = $logger;
    }

    /**
     * @param  list<ProcessedImage>  $images
     * @param  list<array{id: int|string, name: string, description?: string|null}>  $niches
     * @return array{
     *   niche_id: int|null,
     *   suggested_niche: string,
     *   category: string,
     *   tone: string,
     *   thread_length: string,
     *   product_summary: string,
     *   suggested_product_name: string
     * }
     */
    public function analyze(array $images, array $niches, string $language = 'bm'): array
    {
        if ($images === []) {
            throw new \InvalidArgumentException('At least one image is required');
        }

        $lang = strtolower(trim($language)) === 'en' ? 'en' : 'bm';
        $catalog = ThreadConfigCatalog::selectionPrompt();
        $nicheList = $this->formatNicheList($niches);

        $instructions = <<<'PROMPT'
You are a Threads content strategist for affiliate product threads.
Analyze the uploaded image and pick the best generation settings for a full multi-reply thread about this product.

Rules:
- Pick the niche_id from the user's niche list that best fits the product (or null if none fit).
- suggested_niche: name of the best-matching niche even if niche_id is null.
- category: best content category for this product image.
- tone: best tone for the audience.
- thread_length: short, medium, or long based on how much story depth the product deserves.
- product_summary: one sentence describing the product and ideal buyer.
- suggested_product_name: short product label from the image (for affiliate naming).

Respond with ONLY valid JSON, no markdown fences:
{"niche_id":1,"suggested_niche":"...","category":"product_recommendation","tone":"casual","thread_length":"medium","product_summary":"...","suggested_product_name":"..."}
PROMPT;

        $userText = "Language: {$lang}\n\nUSER NICHES:\n{$nicheList}\n\n{$catalog}";

        $response = $this->visionClient->generateWithImages($instructions, $userText, $images, 'low');
        $parsed = $this->parseJsonResponse($response['content'], $niches);

        $this->logger?->info('Thread config image analyzed', [
            'niche_id' => $parsed['niche_id'],
            'category' => $parsed['category'],
            'thread_length' => $parsed['thread_length'],
        ]);

        return $parsed;
    }

    /** @param  list<array{id: int|string, name: string, description?: string|null}>  $niches */
    private function formatNicheList(array $niches): string
    {
        if ($niches === []) {
            return '(user has no niches yet — set niche_id to null and suggest a niche name)';
        }

        $lines = [];
        foreach ($niches as $niche) {
            $id = (int) ($niche['id'] ?? 0);
            $name = trim((string) ($niche['name'] ?? ''));
            $desc = trim((string) ($niche['description'] ?? ''));
            $lines[] = $desc !== ''
                ? "- id={$id} name=\"{$name}\" — {$desc}"
                : "- id={$id} name=\"{$name}\"";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<array{id: int|string, name: string}>  $niches
     * @return array{
     *   niche_id: int|null,
     *   suggested_niche: string,
     *   category: string,
     *   tone: string,
     *   thread_length: string,
     *   product_summary: string,
     *   suggested_product_name: string
     * }
     */
    private function parseJsonResponse(string $content, array $niches): array
    {
        $content = trim($content);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $content, $matches)) {
            $content = trim($matches[1]);
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded) && preg_match('/\{[\s\S]*\}/', $content, $jsonMatch)) {
            $decoded = json_decode($jsonMatch[0], true);
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException('Could not parse thread config analysis from AI response');
        }

        return ThreadConfigCatalog::sanitizeSuggestion($decoded, $niches);
    }
}
