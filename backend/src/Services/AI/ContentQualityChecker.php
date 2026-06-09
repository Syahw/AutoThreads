<?php

namespace AutoThreads\Services\AI;

/**
 * ContentQualityChecker - Pre-publish automated quality gate.
 *
 * Checks processed content for patterns that indicate the output is
 * over-processed, repetitive, or still contains AI artefacts.
 *
 * Returns a list of flag strings. An empty flags array means the content
 * passed all checks. Callers may log flags, block publishing, or simply
 * store them as metadata — the choice is left to ContentGenerator.
 */
class ContentQualityChecker
{
    private array $aiBuzzwords = [
        'game-changer',
        'game changer',
        'dive into',
        'unlock',
        'leverage',
        'dalam dunia hari ini',
        'revolutionary',
        'groundbreaking',
        'seamlessly',
        'effortlessly',
        'cutting-edge',
        'tidak dapat dinafikan',
        'secara keseluruhannya',
        'kesimpulannya',
    ];

    /**
     * Run all quality checks on processed (humanized) content.
     *
     * @param  array{content: string, hook: string, cta: string, replies: array<string>} $humanizedContent
     * @return array{passed: bool, flags: array<string>}
     */
    public function check(array $humanizedContent): array
    {
        $content = $humanizedContent['content'] ?? '';
        $replies = $humanizedContent['replies'] ?? [];
        $flags   = [];

        if ($this->hasRepeatedOpenings($replies)) {
            $flags[] = 'repeated_openings';
        }

        if ($this->exceedsWordFrequency($content, 'aku', threshold: 20)) {
            $flags[] = 'excessive_aku';
        }

        if ($this->exceedsWordFrequency($content, 'tapi', threshold: 8)) {
            $flags[] = 'excessive_tapi';
        }

        if (substr_count($content, '!') > 2) {
            $flags[] = 'too_many_exclamations';
        }

        if (substr_count($content, '?') > 2) {
            $flags[] = 'too_many_questions';
        }

        $foundBuzzwords = $this->detectBuzzwords($content);
        if (!empty($foundBuzzwords)) {
            $flags[] = 'ai_buzzwords:' . implode(',', $foundBuzzwords);
        }

        if ($this->hasRepetitiveStructure($replies)) {
            $flags[] = 'repetitive_structure';
        }

        return [
            'passed' => empty($flags),
            'flags'  => $flags,
        ];
    }

    /**
     * Check whether three or more replies share the same first word.
     */
    private function hasRepeatedOpenings(array $replies): bool
    {
        if (count($replies) < 3) {
            return false;
        }

        $firstWords = array_map(function (string $reply): string {
            $words = preg_split('/\s+/', trim($reply), 2);
            return mb_strtolower($words[0] ?? '');
        }, $replies);

        $counts = array_count_values(array_filter($firstWords));

        return max($counts) >= 3;
    }

    /**
     * Check whether a word appears more than $threshold times (case-insensitive).
     */
    private function exceedsWordFrequency(string $content, string $word, int $threshold): bool
    {
        return substr_count(mb_strtolower($content), mb_strtolower($word)) > $threshold;
    }

    /**
     * Return any AI buzzwords still present in the content.
     *
     * @return array<string>
     */
    private function detectBuzzwords(string $content): array
    {
        $lower = mb_strtolower($content);
        $found = [];

        foreach ($this->aiBuzzwords as $buzzword) {
            if (str_contains($lower, mb_strtolower($buzzword))) {
                $found[] = $buzzword;
            }
        }

        return $found;
    }

    /**
     * Check whether more than half the replies start with the same two-word pattern.
     */
    private function hasRepetitiveStructure(array $replies): bool
    {
        if (count($replies) < 4) {
            return false;
        }

        $patterns = array_map(function (string $reply): string {
            $words = preg_split('/\s+/', trim($reply), 3);
            return mb_strtolower(implode(' ', array_slice($words, 0, 2)));
        }, $replies);

        $counts    = array_count_values(array_filter($patterns));
        $maxCount  = empty($counts) ? 0 : max($counts);
        $threshold = (int) ceil(count($replies) / 2);

        return $maxCount >= $threshold;
    }
}
