<?php

namespace AutoThreads\Services\AI;

/**
 * QualityScorer - Scores generated content for quality and engagement potential
 * 
 * Scoring dimensions:
 * - Hook strength (curiosity, emotion, specificity)
 * - Readability (sentence variety, length, flow)
 * - Humanization (absence of AI patterns)
 * - Engagement potential (question, CTA, relatability)
 * - Overall composite score
 */
class QualityScorer
{
    private array $aiGiveaways = [
        'game-changer', 'dive into', 'unlock', 'leverage',
        'in today\'s world', 'it\'s worth noting', 'furthermore',
        'additionally', 'moreover', 'in conclusion',
    ];

    private array $strongHookPatterns = [
        '/^(nobody|no one) talks about/i',
        '/^(hot take|unpopular opinion)/i',
        '/^(stop|quit) \w+ing/i',
        '/^the (secret|truth|reason)/i',
        '/^\d+ (things|reasons|ways)/i',
        '/^(why|how) (most|everyone)/i',
        '/\?$/',  // Questions as hooks
    ];

    /**
     * Score content across multiple dimensions
     */
    public function score(array $humanizedContent): array
    {
        $content = $humanizedContent['content'];
        $hook = $humanizedContent['hook'] ?? '';
        $cta = $humanizedContent['cta'] ?? '';

        $hookScore = $this->scoreHook($hook);
        $readabilityScore = $this->scoreReadability($content);
        $humanizationScore = $this->scoreHumanization($content);
        $engagementScore = $this->scoreEngagement($content, $cta);

        $overall = round(
            ($hookScore * 0.30) +
            ($readabilityScore * 0.20) +
            ($humanizationScore * 0.25) +
            ($engagementScore * 0.25),
            2
        );

        return [
            'hook' => $hookScore,
            'readability' => $readabilityScore,
            'humanization' => $humanizationScore,
            'engagement' => $engagementScore,
            'overall' => $overall,
        ];
    }

    private function scoreHook(string $hook): float
    {
        $score = 50.0; // Base score

        // Check against strong hook patterns
        foreach ($this->strongHookPatterns as $pattern) {
            if (preg_match($pattern, $hook)) {
                $score += 15;
                break;
            }
        }

        // Short hooks score higher (curiosity gap)
        $hookLength = mb_strlen($hook);
        if ($hookLength > 10 && $hookLength < 60) {
            $score += 10;
        }

        // Hooks with numbers score well
        if (preg_match('/\d/', $hook)) {
            $score += 5;
        }

        // Emotional words boost
        $emotionalWords = ['never', 'always', 'worst', 'best', 'secret', 'truth', 'mistake'];
        foreach ($emotionalWords as $word) {
            if (stripos($hook, $word) !== false) {
                $score += 5;
                break;
            }
        }

        return min(100, max(0, $score));
    }

    private function scoreReadability(string $content): float
    {
        $score = 60.0;
        $sentences = preg_split('/[.!?\n]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $sentenceCount = count($sentences);

        // Variety in sentence length
        if ($sentenceCount >= 2) {
            $lengths = array_map('mb_strlen', $sentences);
            $variance = $this->calculateVariance($lengths);
            if ($variance > 100) $score += 15; // Good variety
        }

        // Optimal length (150-400 chars for Threads)
        $length = mb_strlen($content);
        if ($length >= 150 && $length <= 400) {
            $score += 15;
        } elseif ($length < 80 || $length > 500) {
            $score -= 10;
        }

        // Line breaks (readability on mobile)
        $lineBreaks = substr_count($content, "\n");
        if ($lineBreaks >= 1 && $lineBreaks <= 4) {
            $score += 10;
        }

        return min(100, max(0, $score));
    }

    private function scoreHumanization(string $content): float
    {
        $score = 90.0; // Start high, deduct for AI patterns

        $lowerContent = strtolower($content);

        // Deduct for AI giveaway phrases
        foreach ($this->aiGiveaways as $phrase) {
            if (str_contains($lowerContent, strtolower($phrase))) {
                $score -= 15;
            }
        }

        // Deduct for overly formal structure
        if (preg_match('/^(First|Second|Third|Finally|In conclusion)/m', $content)) {
            $score -= 10;
        }

        // Bonus for casual markers
        $casualMarkers = ['tbh', 'ngl', 'lowkey', 'fr', '...', ' - ', 'lol'];
        foreach ($casualMarkers as $marker) {
            if (str_contains($lowerContent, $marker)) {
                $score += 3;
                break;
            }
        }

        return min(100, max(0, $score));
    }

    private function scoreEngagement(string $content, string $cta): float
    {
        $score = 50.0;

        // Questions drive engagement
        if (str_contains($content, '?')) {
            $score += 15;
        }

        // CTA presence
        if (!empty($cta) && mb_strlen($cta) > 5) {
            $score += 10;
        }

        // Relatability markers
        $relatablePatterns = ['/you (know|feel|get)/i', '/we all/i', '/same/i', '/right\?/i'];
        foreach ($relatablePatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $score += 5;
                break;
            }
        }

        // Controversy/opinion markers boost engagement
        if (preg_match('/(unpopular|hot take|controversial|disagree)/i', $content)) {
            $score += 10;
        }

        return min(100, max(0, $score));
    }

    private function calculateVariance(array $values): float
    {
        $count = count($values);
        if ($count < 2) return 0;

        $mean = array_sum($values) / $count;
        $squaredDiffs = array_map(fn($v) => pow($v - $mean, 2), $values);
        return array_sum($squaredDiffs) / $count;
    }
}
