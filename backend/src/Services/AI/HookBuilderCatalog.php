<?php

namespace AutoThreads\Services\AI;

/**
 * Valid hook-builder option IDs (mirrors frontend hookBuilder.js).
 */
class HookBuilderCatalog
{
    /** @var array<string, array{multi: bool, options: list<string>}> */
    private const GROUPS = [
        'hook_style' => [
            'multi' => false,
            'options' => [
                'storytelling', 'fomo', 'urgency', 'problem_solution', 'self_thought',
            ],
        ],
    ];

    /** @return array<string, array{multi: bool, options: list<string>}> */
    public static function groups(): array
    {
        return self::GROUPS;
    }

    public static function selectionPrompt(): string
    {
        $lines = ["HOOK BUILDER OPTIONS (use exact IDs only):\n"];

        foreach (self::GROUPS as $groupId => $meta) {
            $pick = $meta['multi'] ? 'pick 1-3' : 'pick exactly 1';
            $lines[] = "- {$groupId} ({$pick}): " . implode(', ', $meta['options']);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{topic: string, product_summary: string, selections: array<string, list<string>>}
     */
    public static function sanitizeSuggestion(array $raw): array
    {
        $topic = trim((string) ($raw['topic'] ?? ''));
        $productSummary = trim((string) ($raw['product_summary'] ?? ''));
        $rawSelections = is_array($raw['selections'] ?? null) ? $raw['selections'] : [];

        $selections = [];

        foreach (self::GROUPS as $groupId => $meta) {
            $valid = $meta['options'];
            $incoming = $rawSelections[$groupId] ?? [];
            if (!is_array($incoming)) {
                $incoming = [$incoming];
            }

            $picked = [];
            foreach ($incoming as $id) {
                $id = trim((string) $id);
                if ($id !== '' && in_array($id, $valid, true) && !in_array($id, $picked, true)) {
                    $picked[] = $id;
                }
            }

            if (!$meta['multi'] && count($picked) > 1) {
                $picked = [array_values($picked)[0]];
            }

            if ($picked !== []) {
                $selections[$groupId] = $picked;
            }
        }

        return [
            'topic' => $topic,
            'product_summary' => $productSummary,
            'selections' => $selections,
        ];
    }
}
