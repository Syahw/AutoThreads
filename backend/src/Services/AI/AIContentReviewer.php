<?php

namespace AutoThreads\Services\AI;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

/**
 * AIContentReviewer - Second-pass polish using GPT-4o Mini.
 *
 * Reviews generated thread content for naturalness without rewriting it.
 * Focuses only on awkward Malaysian phrasing, AI-sounding sentences,
 * repetitive openings, and spelling mistakes.
 *
 * Temperature is kept low (0.25) to produce conservative, targeted edits.
 */
class AIContentReviewer
{
    private Client $httpClient;
    private ?LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        $this->httpClient = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'timeout' => 30,
            'verify' => guzzle_ssl_verify(),
        ]);
    }

    /**
     * Run a targeted polish pass on generated thread content.
     *
     * @return array{content: string, tokens_used: int, prompt_tokens: int, completion_tokens: int, time_ms: int}
     */
    public function review(string $threadContent): array
    {
        $startTime = microtime(true);

        $response = $this->httpClient->post('chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $_ENV['OPENAI_API_KEY'],
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'       => $_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini',
                'messages'    => [
                    ['role' => 'system', 'content' => $this->buildSystemPrompt()],
                    ['role' => 'user',   'content' => $this->buildUserPrompt($threadContent)],
                ],
                'max_tokens'  => (int) ($_ENV['OPENAI_MAX_TOKENS'] ?? 3000),
                'temperature' => 0.25,
            ],
        ]);

        $data   = json_decode($response->getBody()->getContents(), true);
        $timeMs = (int) ((microtime(true) - $startTime) * 1000);
        $usage  = $data['usage'] ?? [];

        $reviewed = trim($data['choices'][0]['message']['content'] ?? '');

        // Reject the review if the model returned something suspiciously short
        // (indicates a misfire), and fall back to the original content.
        if (mb_strlen($reviewed) < mb_strlen($threadContent) * 0.6) {
            $this->logger?->warning('Review pass returned unexpectedly short content, using original', [
                'original_len' => mb_strlen($threadContent),
                'reviewed_len' => mb_strlen($reviewed),
            ]);
            $reviewed = $threadContent;
        }

        $this->logger?->info('Content review pass completed', [
            'tokens'  => $usage['total_tokens'] ?? 0,
            'time_ms' => $timeMs,
        ]);

        return [
            'content'           => $reviewed,
            'tokens_used'       => $usage['total_tokens'] ?? 0,
            'prompt_tokens'     => $usage['prompt_tokens'] ?? 0,
            'completion_tokens' => $usage['completion_tokens'] ?? 0,
            'time_ms'           => $timeMs,
        ];
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
        Kau editor Bahasa Malaysia berpengalaman. Bukan copywriter, bukan influencer. Kau edit tulisan orang biasa supaya bunyi lebih natural, bukan untuk jadikan ia lebih "content creator" atau lebih viral.

        TUGAS KAU:
        Semak thread Threads dalam BM casual dan betulkan SAHAJA bahagian yang terasa janggal, tidak natural, atau berbunyi AI. Jangan tulis semula semuanya. Jangan ubah ayat yang dah natural.

        FOKUS SEMAKAN:
        1. Ejaan yang salah (typo atau ejaan tidak standard)
        2. Phrasing BM janggal - macam direct translate dari English
        3. Transisi yang robotic atau bunyi macam essay ("Jadi,", "Oleh itu,", "Kesimpulannya,")
        4. Ayat yang terlalu hype atau dipaksa teruja
        5. Ayat berturut-turut yang mula dengan perkataan sama (contoh: 3 ayat mula "Aku")
        6. Frasa AI yang masih ada ("game-changer", "dalam dunia hari ini", dll.)

        LARANGAN KETAT:
        - Jangan pendekkan thread secara ketara
        - Jangan tukar maksud, tone, atau aliran cerita
        - Jangan tambah emoji, hashtag, atau bahasa marketing
        - Jangan paksa slang atau partikel ("la", "kan", "je") kalau tidak sesuai dengan konteks
        - Preserve format "Reply 1:", "Reply 2:", dll. tepat seperti asal
        - Jangan introduce typo baru
        - Jangan tukar ayat yang dah natural dan ok

        Return ONLY the corrected thread. No explanation, no preamble, no summary.
        PROMPT;
    }

    private function buildUserPrompt(string $threadContent): string
    {
        return <<<PROMPT
        Semak dan poles thread Threads berikut. Betulkan sahaja bahagian yang janggal atau tidak natural. Jangan tulis semula keseluruhannya.

        ---
        {$threadContent}
        ---

        Return the improved thread only, preserving the exact "Reply N:" format.
        PROMPT;
    }
}
