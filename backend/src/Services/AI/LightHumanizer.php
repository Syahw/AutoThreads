<?php

namespace AutoThreads\Services\AI;

/**
 * LightHumanizer - Minimal, safe post-processing.
 *
 * Philosophy: the model does the humanization work. This class only strips
 * things that should never appear regardless of how the model writes:
 *   - Residual AI buzzwords / marketing clichés
 *   - Em/en dashes
 *   - Excess exclamation marks
 *   - Duplicate spaces
 *
 * It does NOT:
 *   - Rewrite grammar
 *   - Replace normal vocabulary with slang
 *   - Inject particles (la, kan, je) forcefully
 *   - Change sentence meaning
 */
class LightHumanizer
{
    /**
     * AI / marketing buzzwords to remove entirely (no replacement).
     * Sorted by length descending at runtime to avoid partial matches.
     */
    private array $removals = [
        // English AI clichés
        'game-changer',
        'game changer',
        'dive into',
        'dive in',
        'unlock your',
        'leverage',
        'in today\'s world',
        'it\'s worth noting',
        'at the end of the day',
        'without further ado',
        'in this day and age',
        'needless to say',
        'revolutionary',
        'groundbreaking',
        'cutting-edge',
        'seamlessly',
        'effortlessly',
        // BM AI / formal giveaways
        'dalam dunia hari ini',
        'tidak dapat dinafikan',
        'sesungguhnya',
        'adalah penting untuk',
        'sehubungan dengan itu',
        'secara keseluruhannya',
        'pada hakikatnya',
        'kesimpulannya',
        'perlu diingatkan',
        'tambahan pula',
        // Hype / engagement-bait
        'beremosi gila',
        'vibe unik',
        'teruja gila',
        'full adrenaline',
        'adrenaline penuh',
        'penuh adrenaline',
        'Komen bawah!',
        'komen bawah!',
        'Mana satu pilihan korang?',
        'mana satu pilihan korang?',
    ];

    /**
     * Safe formality-normalization substitutions.
     * Only replaces formal BM register with natural spoken equivalents.
     * Does NOT inject slang or alter sentence meaning.
     */
    private array $formalToNatural = [
        // Pronoun normalization
        'saya rasa'     => 'aku rasa',
        'saya fikir'    => 'aku fikir',
        'saya telah'    => 'aku dah',
        'aku telah'     => 'aku dah',
        'saya'          => 'aku',
        'anda'          => 'korang',
        'awak'          => 'korang',
        // Negation normalization
        'tidak boleh'   => 'tak boleh',
        'tidak dapat'   => 'tak dapat',
        'langsung tidak' => 'langsung tak',
        'bukan sahaja'  => 'bukan je',
        // Common formal → spoken equivalents
        'kadangkala'    => 'kadang-kadang',
        'walau bagaimanapun' => 'tapi',
        'sahaja'        => 'je',
        'dalam talian'  => 'online',
        'sudah'         => 'dah',
        'telah'         => 'dah',
        // Pronoun (must come after longer phrases)
        'tidak'         => 'tak',
        'hendak'        => 'nak',
        'mahu'          => 'nak',
        'ingin'         => 'nak',
        'momen'         => 'moment',
        'aplikasi'         => 'app',
        'automasi'         => 'automation',
        'landskap'         => 'landscape',
        'konten'         => 'content',
    ];

    public function process(string $rawContent): array
    {
        $replies = $this->parseThreadReplies($rawContent);

        if (count($replies) >= 4) {
            $processedReplies = [];
            foreach ($replies as $i => $reply) {
                $processed = $this->applyRemovals($reply);
                $processed = $this->applyFormalToNatural($processed);
                $processed = $this->removeEmDashes($processed);
                $processed = $this->limitExclamations($processed, maxAllowed: 1);
                $processed = $this->collapseSpaces($processed);
                $processedReplies[$i] = trim($processed);
            }

            $hook = $processedReplies[0] ?? '';
            $cta  = $processedReplies[array_key_last($processedReplies)] ?? '';

            $fullContent = '';
            foreach ($processedReplies as $i => $reply) {
                $replyNum    = $i + 1;
                $fullContent .= "Reply {$replyNum}:\n{$reply}\n\n";
            }

            return [
                'content'  => trim($fullContent),
                'hook'     => $hook,
                'cta'      => $cta,
                'hashtags' => [],
                'replies'  => $processedReplies,
            ];
        }

        // Fallback: single post
        $content = $this->applyRemovals($rawContent);
        $content = $this->applyFormalToNatural($content);
        $content = $this->removeEmDashes($content);
        $content = $this->limitExclamations($content, maxAllowed: 1);
        $content = $this->collapseSpaces($content);

        return [
            'content'  => trim($content),
            'hook'     => $this->extractHook($content),
            'cta'      => $this->extractCTA($content),
            'hashtags' => [],
            'replies'  => [],
        ];
    }

    public function parseThreadReplies(string $content): array
    {
        $replies = [];
        $parts   = preg_split('/Reply\s*\d+\s*:/i', $content, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($parts as $part) {
            $trimmed = trim($part);
            if ($trimmed !== '') {
                $replies[] = $trimmed;
            }
        }

        return $replies;
    }

    private function applyRemovals(string $content): string
    {
        $sorted = $this->removals;
        usort($sorted, fn($a, $b) => strlen($b) <=> strlen($a));

        foreach ($sorted as $phrase) {
            $content = str_ireplace($phrase, '', $content);
        }

        return $content;
    }

    private function applyFormalToNatural(string $content): string
    {
        // Sort by key length descending so longer phrases match before shorter substrings
        $map = $this->formalToNatural;
        uksort($map, fn($a, $b) => strlen($b) <=> strlen($a));

        foreach ($map as $formal => $natural) {
            $content = str_ireplace($formal, $natural, $content);
        }

        return $content;
    }

    private function removeEmDashes(string $content): string
    {
        $content = preg_replace('/\s*[—–]\s*/u', ', ', $content);
        $content = preg_replace('/,\s*,+/', ',', $content);
        $content = preg_replace('/,\s+\./', '.', $content);

        return trim($content);
    }

    private function limitExclamations(string $content, int $maxAllowed): string
    {
        $count = 0;

        return preg_replace_callback('/!/', function () use (&$count, $maxAllowed) {
            $count++;
            return $count <= $maxAllowed ? '!' : '.';
        }, $content);
    }

    private function collapseSpaces(string $content): string
    {
        return preg_replace('/\s{2,}/', ' ', $content);
    }

    private function extractHook(string $content): string
    {
        return trim(explode("\n", $content)[0] ?? '');
    }

    private function extractCTA(string $content): string
    {
        $lines = array_filter(explode("\n", trim($content)));
        return trim(end($lines) ?: '');
    }
}
