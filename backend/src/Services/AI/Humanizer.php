<?php

namespace AutoThreads\Services\AI;

/**
 * Humanizer - Post-processing to make AI content feel natural
 * 
 * Strategies:
 * - Remove common AI patterns
 * - Casual tone without mangled spelling
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
        // BM AI giveaway phrases
        'dalam dunia hari ini', 'perlu diingatkan',
        'tidak dapat dinafikan', 'sesungguhnya',
        'adalah penting untuk', 'di samping itu',
        'sehubungan dengan itu', 'tambahan pula',
        'secara keseluruhannya', 'pada hakikatnya',
        'walau bagaimanapun', 'kesimpulannya',
    ];
    private array $replacements = [
        // Existing AI phrase replacements
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
    
        // BM formal -> casual (keep correct spelling)
        'dalam dunia hari ini' => 'sekarang ni',
        'perlu diingatkan' => '',
        'tidak dapat dinafikan' => 'memang',
        'sesungguhnya' => 'seriously',
        'adalah penting untuk' => 'penting',
        'di samping itu' => 'lagi satu',
        'sehubungan dengan itu' => '',
        'tambahan pula' => 'lagi',
        'secara keseluruhannya' => 'overall',
        'pada hakikatnya' => 'sebenarnya',
        'walau bagaimanapun' => 'tapi',
        'kesimpulannya' => '',
        'saya rasa' => 'aku rasa',
        'saya fikir' => 'aku rasa',
        'saya telah' => 'aku dah',
        'aku telah' => 'aku dah',
        'tidak boleh' => 'tak boleh',
        'tidak dapat' => 'tak dapat',
        'langsung tidak' => 'langsung tak',
        'bukan sahaja' => 'bukan je',
        'kadangkala' => 'kadang-kadang',
        'memberitahu' => 'bagitau',
        'menggunakan' => 'guna',
        'mencuba' => 'cuba',
        'kelihatan' => 'nampak',
        'melihat' => 'tengok',
        'bagaimana' => 'macam mana',
        'mengapa' => 'kenapa',
        'saya' => 'aku',
        'anda' => 'korang',
        'awak' => 'korang',
        'kamu' => 'korang',
        'mereka' => 'diorang',
        'tidak' => 'tak',
        'hendak' => 'nak',
        'mahu' => 'nak',
        'ingin' => 'nak',
        'sudah' => 'dah',
        'telah' => 'dah',
        'kepada' => 'ke',
        'sahaja' => 'je',
        'juga' => 'jugak',
        'sedikit' => 'sikit',
        'sebentar' => 'kejap',
        'perkara' => 'benda',
    ];
   
    public function process(string $rawContent): array
    {
        // Parse thread replies
        $replies = $this->parseThreadReplies($rawContent);

        if (count($replies) >= 5) {
            // Process each reply individually
            $processedReplies = [];
            foreach ($replies as $i => $reply) {
                $processed = $this->removeAIPhrases($reply);
                $processed = $this->fixCommonTypos($processed);
                $processed = $this->casualizePunctuation($processed);
                $processed = trim($processed);
                $processedReplies[$i] = $processed;
            }

            // Extract components from thread structure
            $hook = $processedReplies[0] ?? '';
            $cta = $processedReplies[array_key_last($processedReplies)] ?? '';

            // Rebuild full content with Reply markers
            $fullContent = '';
            foreach ($processedReplies as $i => $reply) {
                $replyNum = $i + 1;
                $fullContent .= "Reply {$replyNum}:\n{$reply}\n\n";
            }

            return [
                'content' => trim($fullContent),
                'hook' => $hook,
                'cta' => $cta,
                'hashtags' => [],
                'replies' => $processedReplies,
            ];
        }

        // Fallback: single post format (backward compatibility)
        $content = $rawContent;
        $content = $this->removeAIPhrases($content);
        $content = $this->fixCommonTypos($content);
        $content = $this->casualizePunctuation($content);
        $content = $this->enforceLength($content, 500);

        $hook = $this->extractHook($content);
        $cta = $this->extractCTA($content);
        $hashtags = $this->extractHashtags($content);
        $content = $this->removeHashtagsFromBody($content);

        return [
            'content' => trim($content),
            'hook' => $hook,
            'cta' => $cta,
            'hashtags' => $hashtags,
            'replies' => [],
        ];
    }

    /**
     * Parse thread replies from AI output
     * Splits content by "Reply X:" markers
     */
    public function parseThreadReplies(string $content): array
    {
        $replies = [];
        // Split by "Reply N:" pattern (case-insensitive)
        $parts = preg_split('/Reply\s*\d+\s*:/i', $content, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($parts as $part) {
            $trimmed = trim($part);
            if (!empty($trimmed)) {
                $replies[] = $trimmed;
            }
        }

        return $replies;
    }

    /**
     * Fix common AI/humanizer typos while keeping casual tone.
     */
    private function fixCommonTypos(string $content): string
    {
        $fixes = [
            'bdiasa' => 'biasa',
            'specdial' => 'special',
            'rdiang' => 'riang',
            ' ngan ' => ' dengan ',
            ' ngon ' => ' dengan ',
            'kecik' => 'kecil',
            'besaq' => 'besar',
            'benda kecik' => 'benda kecil',
            'terlampau' => 'terlalu',
            'lom ' => 'belum ',
            ' camtu' => ' macam tu',
        ];

        foreach ($fixes as $wrong => $right) {
            $content = str_ireplace($wrong, $right, $content);
        }

        return $content;
    }

    private function removeAIPhrases(string $content): string
    {
        // Longer phrases first to avoid partial replacements
        $replacements = $this->replacements;
        uksort($replacements, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($replacements as $phrase => $replacement) {
            if ($phrase === '') {
                continue;
            }
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
