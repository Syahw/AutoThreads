<?php

namespace AutoThreads\Services\Analytics;

use AutoThreads\Models\Analytics;
use AutoThreads\Models\ScheduledPost;
use AutoThreads\Services\Threads\ThreadsClient;

class AnalyticsCollector
{
    private ThreadsClient $threadsClient;

    public function __construct(?ThreadsClient $threadsClient = null)
    {
        $this->threadsClient = $threadsClient ?? new ThreadsClient();
    }

    /**
     * Fetch Threads insights for recent posted content and persist to analytics table.
     *
     * @return array{processed: int, collected: int, failed: int, errors: string[]}
     */
    public function collect(?int $userId = null, int $days = 7): array
    {
        $query = ScheduledPost::where('status', 'posted')
            ->where('posted_at', '>=', now()->subDays($days))
            ->whereNotNull('threads_post_id')
            ->with('threadsAccount');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $posts = $query->get();
        $collected = 0;
        $failed = 0;
        $errors = [];

        foreach ($posts as $post) {
            if (!$post->threadsAccount) {
                $failed++;
                $errors[] = "Post #{$post->id}: missing Threads account";
                continue;
            }

            try {
                $insights = $this->threadsClient->getPostInsights(
                    $post->threadsAccount,
                    $post->threads_post_id
                );

                $likes = (int) ($insights['likes'] ?? 0);
                $comments = (int) ($insights['replies'] ?? 0);
                $reposts = (int) ($insights['reposts'] ?? 0);
                $impressions = (int) ($insights['views'] ?? 0);
                $engagement = $likes + $comments + $reposts;

                Analytics::updateOrCreate(
                    ['threads_post_id' => $post->threads_post_id],
                    [
                        'user_id' => $post->user_id,
                        'scheduled_post_id' => $post->id,
                        'generated_post_id' => $post->generated_post_id,
                        'impressions' => $impressions,
                        'likes' => $likes,
                        'comments' => $comments,
                        'reposts' => $reposts,
                        'quotes' => (int) ($insights['quotes'] ?? 0),
                        'engagement_rate' => $impressions > 0
                            ? round(($engagement / $impressions) * 100, 2)
                            : 0,
                        'collected_at' => now(),
                    ]
                );

                $collected++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Post {$post->threads_post_id}: {$e->getMessage()}";
            }

            usleep(500000);
        }

        return [
            'processed' => $posts->count(),
            'collected' => $collected,
            'failed' => $failed,
            'errors' => array_slice($errors, 0, 5),
        ];
    }
}
