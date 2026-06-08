<?php

namespace AutoThreads\Services\Threads;

use AutoThreads\Models\AffiliateLink;
use AutoThreads\Models\GeneratedPost;
use AutoThreads\Models\ThreadsAccount;
use AutoThreads\Services\AI\Humanizer;
use AutoThreads\Services\Media\HookImageStorage;

class ThreadPublisher
{
    private ThreadsClient $threadsClient;
    private Humanizer $humanizer;
    private HookImageStorage $hookImages;

    public function __construct(
        ?ThreadsClient $threadsClient = null,
        ?Humanizer $humanizer = null,
        ?HookImageStorage $hookImages = null
    ) {
        $this->threadsClient = $threadsClient ?? new ThreadsClient();
        $this->humanizer = $humanizer ?? new Humanizer();
        $this->hookImages = $hookImages ?? new HookImageStorage();
    }

    /**
     * Publish an approved generated post as a chained Threads thread (one API post per reply).
     */
    public function publish(GeneratedPost $post, ThreadsAccount $account): array
    {
        $replies = $this->extractReplies($post);

        if ($replies === []) {
            throw new \RuntimeException('No thread replies found to publish');
        }

        $replies = $this->applyAffiliateLink($post, $replies);

        $hookImageUrl = $this->hookImages->resolvePublicUrl($post);
        $this->hookImages->validatePublicUrl($hookImageUrl);

        return $this->threadsClient->publishThread($account, $replies, $hookImageUrl);
    }

    /**
     * @return string[]
     */
    public function extractReplies(GeneratedPost $post): array
    {
        $metadata = $post->metadata ?? [];
        $stored = $metadata['replies'] ?? [];

        if (is_array($stored) && count($stored) > 0) {
            return array_values(array_filter(array_map(
                fn ($r) => trim((string) $r),
                $stored
            )));
        }

        $parsed = $this->humanizer->parseThreadReplies($post->content);

        return array_values(array_filter(array_map('trim', $parsed)));
    }

    /**
     * Replace [link] placeholder with the post's affiliate URL before publishing.
     *
     * @param string[] $replies
     * @return string[]
     */
    public function applyAffiliateLink(GeneratedPost $post, array $replies): array
    {
        $url = $this->resolveAffiliateUrl($post);

        if ($url === null) {
            return array_map(fn (string $reply) => $this->stripLinkPlaceholder($reply), $replies);
        }

        $lastIndex = count($replies) - 1;

        return array_map(function (string $reply, int $index) use ($url, $lastIndex) {
            if (stripos($reply, '[link]') !== false) {
                return str_ireplace('[link]', $url, $reply);
            }

            // Placeholder missing on last reply — append URL so CTA still works
            if ($index === $lastIndex) {
                return rtrim($reply) . "\n\n" . $url;
            }

            return $reply;
        }, $replies, array_keys($replies));
    }

    private function resolveAffiliateUrl(GeneratedPost $post): ?string
    {
        if (!$post->affiliate_link_id) {
            return null;
        }

        $affiliate = AffiliateLink::find($post->affiliate_link_id);

        if (!$affiliate || !$affiliate->is_active) {
            return null;
        }

        $url = trim($affiliate->short_url ?: $affiliate->url);

        return $url !== '' ? $url : null;
    }

    private function stripLinkPlaceholder(string $reply): string
    {
        $reply = str_ireplace('[link]', '', $reply);
        $reply = preg_replace('/\s{2,}/', ' ', $reply) ?? $reply;
        $reply = preg_replace('/\s+([,.!?])/', '$1', $reply) ?? $reply;

        return trim($reply);
    }
}
