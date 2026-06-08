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
        // Hype giveaways
        'best gila', 'memang best', 'beremosi gila', 'puas hati',
        'vibe unik', 'teruja gila', 'full adrenaline', 'adrenaline penuh',
        'komen bawah', 'Komen bawah',
        'just now' => 'tadi',
        'right now' => 'sekarang',
        'very' => 'gila',
        'really' => 'memang',
        'kind of' => 'macam',
        'sort of' => 'lebih kurang',
        'you know' => 'tau tak',
        'like this' => 'macam ni',
        'like that' => 'macam tu',
        'this one' => 'yang ni',
        'that one' => 'yang tu',
        'not bad' => 'okay la',
        'pretty good' => 'boleh tahan',
        'quite' => 'agak',
        'already' => 'dah',
        'still' => 'masih lagi',
        'again' => 'lagi',
        'too much' => 'terlebih',
        'too many' => 'terlalu banyak',
        'so much' => 'banyak gila',
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
        'in order to' => 'to',
        'to put it simply' => 'basically',
        'in simple terms' => 'simply put',
        'long story short' => 'short story',
        'to summarise' => 'so yeah',
        'to summarize' => 'so yeah',
        'overall, it can be said' => 'overall',
        'it is clear that' => 'clearly',
        'it becomes evident' => 'you can see',
        'a wide range of' => 'lots of',
        'a variety of' => 'many',
        'designed to help' => 'meant to help',
        'built to' => 'made to',
        'helps you to' => 'helps you',
        'makes it possible' => 'lets you',
        'allows you to' => 'lets you',
        'provides you with' => 'gives you',
        'ensures that' => 'so that',
        'it is recommended to' => 'better to',
        'keep in mind that' => 'just remember',
        'it is worth mentioning' => 'also',
        'this approach' => 'this way',
        'this method' => 'this way',
        'this solution' => 'this thing',
    
        // BM formal -> casual (keep correct spelling)
        'dalam dunia hari ini' => 'sekarang ni',
        'perlu diingatkan' => '',
        'tidak dapat dinafikan' => 'memang',
        'sesungguhnya' => '',
        'adalah penting untuk' => 'penting',
        'di samping itu' => 'lagi satu',
        'sehubungan dengan itu' => '',
        'tambahan pula' => 'lagi',
        'secara keseluruhannya' => 'overall',
        'pada hakikatnya' => 'sebenarnya',
        'walau bagaimanapun' => 'tapi',
        'kesimpulannya' => '',
        // Hype / robotic BM phrases → remove or soften
        'best gila' => 'okay je',
        'memang best' => 'okay',
        'beremosi gila' => 'rasa something',
        'puas hati' => 'okay',
        'vibe unik' => '',
        'seriously' => '',
        'legit' => '',
        'teruja' => '',
        'adrenaline' => '',
        'Komen bawah!' => '',
        'komen bawah!' => '',
        'Mana satu pilihan korang?' => '',
        'mana satu pilihan korang?' => '',
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
        'tentang' => 'pasal',
        'tentu' => 'mesti punya',
        'kedua-dua' => 'dua-dua',
        'kedua-dua-nya' => 'dua-dua-nya',
        'kedua-dua-lah' => 'dua-dua-lah',
        'kedua-dua-itu' => 'dua-dua-itu',
        'kedua-dua-pula' => 'dua-dua-pula',
        'kedua-dua-pula' => 'dua-dua-pula',

        // Natural spoken BM (lebih real, less formal)
        'sekarang ni' => 'sekarang ni',
        'sangat' => 'gila',
        'terlalu' => 'terlebih',
        'agak' => 'macam',
        'lebih kurang' => 'macam',
        'lagi satu' => 'oh ya lagi satu',
        'tapi' => 'tapi kan',
        'sebenarnya' => 'sebenarnya la',
        'overall' => 'overall la',
        'penting' => 'memang kena',
        'mesti' => 'kena',
        'nak' => 'nak la',
        'tak nak' => 'tak nak la',
        'tak boleh' => 'memang tak boleh',
        'tak tahu' => 'entah la',
        'tak apa' => 'its okay la / takpe je',
        'kena buat' => 'perlu buat',
        'perlu' => 'kena',
        'guna' => 'pakai',
        'buat' => 'buat je',
        'lihat' => 'tengok',
        'bagitau' => 'cakap',
        'macam mana' => 'macam mana eh',
        'kenapa' => 'why eh',
        'korang semua' => 'korang',
        'diorang semua' => 'diorang',
        'momen' => 'moment',
        'merubah' => 'mengubah',
        'berlegar' => 'yang bermain dalam fikiran',
        'automasi' => 'automation',
        'bantu' => 'tolong',
        'pengpakaian' => 'pengunaan',
        'pengpakai' => 'user',
        'landskap' => 'landscape',
        'bagi ramai' => 'untuk ramai',
        'bagi' => 'untuk',
        'berpakai' => 'boleh pakai',
        'kegemaran' => 'favourite aku',
        'pkorang' => 'korang',
        'ia' => 'dia',
        'dalam talian' => 'online',

    ];
   
    public function process(string $rawContent): array
    {
        // Parse thread replies
        $replies = $this->parseThreadReplies($rawContent);

        if (count($replies) >= 4) {
            // Process each reply individually
            $processedReplies = [];
            foreach ($replies as $i => $reply) {
                $processed = $this->removeAIPhrases($reply);
                $processed = $this->fixCommonTypos($processed);
                $processed = $this->casualizePunctuation($processed);
                $processed = $this->removeEmDashes($processed);
                $processed = $this->dedupeHype($processed);
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
        $content = $this->removeEmDashes($content);
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
            'pkorang' => 'korang',
            'pengunaan' => 'penggunaan',
            'pengpakaian' => 'penggunaan',
            'pengpakai' => 'pengguna',
            'memang kena' => 'memang perlu',
            'tapi kan' => 'tapi',
            'sebenarnya la' => 'sebenarnya',
            'overall la' => 'overall',
            'macam mana eh' => 'macam mana',
            'why eh' => 'kenapa',
            'entah la' => 'tak tahu',
            'its okay' => 'okay',
            'takpe je' => 'takpe',
            'nak la' => 'nak',
            'tak nak la' => 'tak nak',
            'buat je' => 'buat',
            'perlu buat' => 'kena buat',
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
        $content = str_replace(';', '.', $content);

        // Collapse multiple exclamation marks
        $content = preg_replace('/!{2,}/', '.', $content);

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

    /**
     * Strip hype filler and cap exclamation marks per reply.
     */
    private function dedupeHype(string $content): string
    {
        $hypePatterns = [
            '/\b(?:seriously|legit),?\s*/iu' => '',
            '/\b(?:best gila|memang best|beremosi gila|puas hati)\b/iu' => '',
            '/\b(?:vibe unik|teruja gila|penuh adrenaline|adrenaline penuh)\b/iu' => '',
            '/\b(?:komen bawah|Komen bawah)!?\s*/iu' => '',
            '/\bMana satu pilihan korang\?\s*/iu' => '',
        ];

        foreach ($hypePatterns as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        // Max one exclamation mark per reply — convert extras to periods
        $exclamations = 0;
        $content = preg_replace_callback('/!/', function () use (&$exclamations) {
            $exclamations++;
            return $exclamations <= 1 ? '!' : '.';
        }, $content);

        $content = preg_replace('/\s{2,}/', ' ', $content);

        return trim($content);
    }

    /**
     * Replace em/en dashes with comma — they read too AI-formal for casual BM threads.
     */
    private function removeEmDashes(string $content): string
    {
        $content = preg_replace('/\s*[—–]\s*/u', ', ', $content);
        $content = preg_replace('/,\s*,+/', ',', $content);
        $content = preg_replace('/,\s+\./', '.', $content);

        return trim($content);
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
