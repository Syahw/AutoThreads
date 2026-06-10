<?php

namespace AutoThreads\Services\AI;

use AutoThreads\Services\Media\ProcessedImage;
use Psr\Log\LoggerInterface;

/**
 * Analyzes a product/reference image and suggests hook-builder selections.
 */
class HookImageAnalyzer
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
     * @return array{topic: string, product_summary: string, selections: array<string, list<string>>}
     */
    public function analyze(array $images, string $language = 'bm'): array
    {
        if ($images === []) {
            throw new \InvalidArgumentException('At least one image is required');
        }

        $lang = strtolower(trim($language)) === 'en' ? 'en' : 'bm';
        $catalog = HookBuilderCatalog::selectionPrompt();

        $instructions = <<<'PROMPT'
You are a Threads hook strategist for affiliate and product content.
Analyze the uploaded image (product photo, screenshot, packaging, etc.) and choose the hook-builder options that would work best for Reply 1 of a thread about this product.

Rules:
- Pick options that match the product category, visual vibe, and likely audience.
- hook_style: pick exactly one — storytelling, fomo, urgency, problem_solution, or self_thought.
- topic: short subject line for the thread (product name or angle, max 12 words).
- product_summary: one sentence describing what you see and who it is for.

Respond with ONLY valid JSON, no markdown fences:
{"topic":"...","product_summary":"...","selections":{"hook_style":["id"]}}
PROMPT;

        $userText = "Language for topic text: {$lang}\n\n{$catalog}";

        $response = $this->visionClient->generateWithImages($instructions, $userText, $images, 'low');
        $parsed = $this->parseJsonResponse($response['content']);

        $this->logger?->info('Hook image analyzed', [
            'topic' => $parsed['topic'],
            'groups' => array_keys($parsed['selections']),
        ]);

        return $parsed;
    }

    /** @return array{topic: string, product_summary: string, selections: array<string, list<string>>} */
    private function parseJsonResponse(string $content): array
    {
        $content = trim($content);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $content, $matches)) {
            $content = trim($matches[1]);
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            if (preg_match('/\{[\s\S]*\}/', $content, $jsonMatch)) {
                $decoded = json_decode($jsonMatch[0], true);
            }
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException('Could not parse hook analysis from AI response');
        }

        return HookBuilderCatalog::sanitizeSuggestion($decoded);
    }
}
