<?php

namespace AutoThreads\Services\AI;

/**
 * Humanizer - Post-processing to make AI content feel natural
 * 
 * Strategies:
 * - Remove common AI patterns
 * - Add natural imperfections
 * - Extract hook/CTA structure
 * - Ensure platform-native formatting
 */
class Humanizer
{
    private array $aiPhrases = [
        'game-changer', 'game changer', 'dive into', 'dive in',
        'unlock your', 'leverage', 'in today\'s world',
        'it\'s worth noting', 'at the end of the day',
        'without further ado', 'in this day and age',
        'needless to say', 'let\'s be honest',
        'here\'s the thing', 'the reality is',
        'I cannot stress enough', 'absolutely essential',
        'revolutionary', 'groundbreaking', 'cutting-edge',
        'seamlessly', 'effortlessly', 'streamline',
    ];

    private array $replacements = [
        'game-changer' => 'solid find',
        'game changer' => 'solid find',
        'dive into' => 'check out',
        'dive in' => 'get into',
        'unlock your' => 'improve your',
        'leverage' => 'use',
        'absolutely essential' => 'really helpful',
        'revolutionary' => 'different',
        'groundbreaking' => 'interesting',
        'cutting-edge' => 'new',
        'seamlessly' => 'smoothly',
        'effortlessly' => 'easily',
        'streamline' => 'simplify',
    ];

    /**
     * Process AI-generated content through humanization pipeline
     */
    public function process(string $rawContent): array
    {
        $content = $rawContent;

        // Step 1: Remove AI giveaway phrases
        $content = $this->removeAIPhrases($content);

        // Step 2: Fix over-formal punctuation
        $content = $this->casualizePunctuation($content);

        // Step 3: Ensure proper length for Threads
        $content = $this->enforceLength($content, 500);

        // Step 4: Extract structural components
        $hook = $this->extractHook($content);
        $cta = $this->extractCTA($content);
        $hashtags = $this->extractHashtags($content);

        // Step 5: Clean up hashtags from content body
        $content = $this->removeHashtagsFromBody($content);

        return [
            'content' => trim($content),
            'hook' => $hook,
            'cta' => $cta,
            'hashtags' => $hashtags,
        ];
    }

    private function removeAIPhrases(string $content): string
    {
        foreach ($this->replacements as $phrase => $replacement) {
            $content = str_ireplace($phrase, $replacement, $content);
        }

        // Remove remaining flagged phrases without replacement
        foreach ($this->aiPhrases as $phrase) {
            if (!isset($this->replacements[$phrase])) {
                $content = str_ireplace($phrase, '', $content);
            }
        }

        // Clean up double spaces
        $content = preg_replace('/\s{2,}/', ' ', $content);

        return $content;
    }

    private function casualizePunctuation(string $content): string
    {
        // Replace semicolons with periods or dashes (more casual)
        $content = str_replace(';', ' -', $content);

        // Remove excessive exclamation marks
        $content = preg_replace('/!{2,}/', '!', $content);

        // Limit emoji usage (max 2)
        $emojiPattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}]/u';
        preg_match_all($emojiPattern, $content, $matches);
        if (count($matches[0]) > 2) {
            $count = 0;
            $content = preg_replace_callback($emojiPattern, function ($match) use (&$count) {
                $count++;
                return $count <= 2 ? $match[0] : '';
            }, $content);
        }

        return $content;
    }

    private function enforceLength(string $content, int $maxChars): string
    {
        if (mb_strlen($content) <= $maxChars) {
            return $content;
        }

        // Trim to last complete sentence within limit
        $trimmed = mb_substr($content, 0, $maxChars);
        $lastPeriod = mb_strrpos($trimmed, '.');
        $lastNewline = mb_strrpos($trimmed, "\n");
        $cutPoint = max($lastPeriod, $lastNewline);

        if ($cutPoint > $maxChars * 0.6) {
            return mb_substr($content, 0, $cutPoint + 1);
        }

        return $trimmed;
    }

    private function extractHook(string $content): string
    {
        $lines = explode("\n", $content);
        return trim($lines[0] ?? '');
    }

    private function extractCTA(string $content): string
    {
        $lines = array_filter(explode("\n", trim($content)));
        return trim(end($lines) ?: '');
    }

    private function extractHashtags(string $content): array
    {
        preg_match_all('/#(\w+)/', $content, $matches);
        return array_slice($matches[1] ?? [], 0, 3);
    }

    private function removeHashtagsFromBody(string $content): string
    {
        // Keep hashtags only at the end
        $lines = explode("\n", $content);
        $lastLine = end($lines);

        if (preg_match('/^#/', trim($lastLine))) {
            // Last line is hashtags, keep it
            return $content;
        }

        // Remove inline hashtags but keep the words
        return preg_replace('/#(\w+)/', '$1', $content);
    }
}
