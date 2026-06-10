<?php

namespace AutoThreads\Services\AI;

class ThreadConfigCatalog
{
    /** @var list<string> */
    public const CATEGORIES = [
        'story', 'product_recommendation', 'comparison', 'productivity_tip',
        'viral_hook', 'opinion', 'list_post', 'wish_i_knew', 'general',
    ];

    /** @var list<string> */
    public const TONES = [
        'casual', 'professional', 'witty', 'inspirational',
        'controversial', 'educational', 'storytelling', 'urgent',
    ];

    /** @var list<string> */
    public const LENGTHS = ['short', 'medium', 'long'];

    public static function selectionPrompt(): string
    {
        return implode("\n", [
            'VALID OPTIONS (use exact IDs only):',
            '- category (pick 1): ' . implode(', ', self::CATEGORIES),
            '- tone (pick 1): ' . implode(', ', self::TONES),
            '- thread_length (pick 1): short = 4-5 replies, medium = 5-6 replies, long = 6-7 replies',
        ]);
    }

    /**
     * @param  array<string, mixed>  $raw
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
    public static function sanitizeSuggestion(array $raw, array $niches): array
    {
        $category = strtolower(trim((string) ($raw['category'] ?? 'general')));
        if (!in_array($category, self::CATEGORIES, true)) {
            $category = 'product_recommendation';
        }

        $tone = strtolower(trim((string) ($raw['tone'] ?? 'casual')));
        if (!in_array($tone, self::TONES, true)) {
            $tone = 'casual';
        }

        $length = strtolower(trim((string) ($raw['thread_length'] ?? $raw['length'] ?? 'medium')));
        if (!in_array($length, self::LENGTHS, true)) {
            $length = 'medium';
        }

        $nicheId = null;
        $rawNicheId = $raw['niche_id'] ?? null;
        if ($rawNicheId !== null && $rawNicheId !== '') {
            $nicheId = (int) $rawNicheId;
            $validIds = array_map(fn ($n) => (int) ($n['id'] ?? 0), $niches);
            if (!in_array($nicheId, $validIds, true)) {
                $nicheId = null;
            }
        }

        $suggestedNiche = trim((string) ($raw['suggested_niche'] ?? ''));

        if ($nicheId === null && $suggestedNiche !== '') {
            foreach ($niches as $niche) {
                $name = strtolower(trim((string) ($niche['name'] ?? '')));
                if ($name !== '' && (
                    $name === strtolower($suggestedNiche)
                    || str_contains($name, strtolower($suggestedNiche))
                    || str_contains(strtolower($suggestedNiche), $name)
                )) {
                    $nicheId = (int) $niche['id'];
                    break;
                }
            }
        }

        return [
            'niche_id' => $nicheId,
            'suggested_niche' => $suggestedNiche,
            'category' => $category,
            'tone' => $tone,
            'thread_length' => $length,
            'product_summary' => trim((string) ($raw['product_summary'] ?? '')),
            'suggested_product_name' => trim((string) ($raw['suggested_product_name'] ?? '')),
        ];
    }
}
