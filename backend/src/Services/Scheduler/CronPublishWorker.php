<?php

namespace AutoThreads\Services\Scheduler;

use AutoThreads\Models\PostingLog;
use AutoThreads\Models\ScheduledPost;
use AutoThreads\Services\Threads\ThreadPublisher;
use Carbon\Carbon;
use GuzzleHttp\Exception\ClientException;

/**
 * Processes due scheduled_posts (used by cron CLI and manual "Run now" in UI).
 */
class CronPublishWorker
{
    private PostScheduler $scheduler;
    private ThreadPublisher $publisher;

    public function __construct(?PostScheduler $scheduler = null, ?ThreadPublisher $publisher = null)
    {
        $this->scheduler = $scheduler ?? new PostScheduler();
        $this->publisher = $publisher ?? new ThreadPublisher();
    }

    /**
     * @return array{processed: int, published: int, failed: int, skipped: int, details: list<array>}
     */
    public function processDuePosts(?int $userId = null): array
    {
        $query = ScheduledPost::where('status', 'queued')
            ->where('scheduled_at', '<=', Carbon::now($this->scheduler->getTimezone())->format('Y-m-d H:i:s'))
            ->with(['generatedPost', 'threadsAccount'])
            ->orderBy('scheduled_at', 'asc')
            ->limit(10);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $duePosts = $query->get();

        $summary = [
            'processed' => 0,
            'published' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => [],
        ];

        foreach ($duePosts as $scheduledPost) {
            $summary['processed']++;
            $detail = $this->processOne($scheduledPost);
            $summary['details'][] = $detail;

            if ($detail['status'] === 'published') {
                $summary['published']++;
            } elseif ($detail['status'] === 'failed') {
                $summary['failed']++;
            } else {
                $summary['skipped']++;
            }

            sleep(3);
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function processOne(ScheduledPost $scheduledPost): array
    {
        $startTime = microtime(true);
        $detail = [
            'scheduled_post_id' => $scheduledPost->id,
            'scheduled_at' => $scheduledPost->getAttributes()['scheduled_at'] ?? null,
            'status' => 'failed',
            'message' => '',
        ];

        try {
            $scheduledPost->status = 'processing';
            $scheduledPost->save();

            $generatedPost = $scheduledPost->generatedPost;
            $account = $scheduledPost->threadsAccount;

            if (!$generatedPost || !$account) {
                throw new \RuntimeException('Missing generated post or Threads account');
            }

            $result = $this->publisher->publish($generatedPost, $account);
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            $scheduledPost->status = 'posted';
            $scheduledPost->posted_at = Carbon::now($this->scheduler->getTimezone())->format('Y-m-d H:i:s');
            $scheduledPost->threads_post_id = $result['root_post_id'] ?? null;
            $scheduledPost->save();

            $metadata = $generatedPost->metadata ?? [];
            $metadata['threads_publish'] = [
                'root_post_id' => $result['root_post_id'],
                'post_ids' => $result['post_ids'],
                'published_count' => $result['published_count'],
                'account_id' => $account->id,
                'published_at' => date('c'),
            ];
            $generatedPost->metadata = $metadata;
            $generatedPost->status = 'posted';
            $generatedPost->save();

            $this->safePostingLog([
                'user_id' => $scheduledPost->user_id,
                'scheduled_post_id' => $scheduledPost->id,
                'threads_account_id' => $account->id,
                'action' => 'post',
                'status' => 'success',
                'threads_post_id' => $result['root_post_id'] ?? null,
                'response_time_ms' => $responseTime,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $detail['status'] = 'published';
            $detail['message'] = 'Published to Threads';
            $detail['threads_post_id'] = $result['root_post_id'] ?? null;
        } catch (ClientException $e) {
            $errorBody = $e->getResponse()?->getBody()?->getContents() ?? $e->getMessage();
            $this->scheduler->markForRetry($scheduledPost, $errorBody);
            $this->safePostingLog([
                'user_id' => $scheduledPost->user_id,
                'scheduled_post_id' => $scheduledPost->id,
                'threads_account_id' => $scheduledPost->threads_account_id,
                'action' => $scheduledPost->retry_count > 0 ? 'retry' : 'post',
                'status' => $e->getResponse()?->getStatusCode() === 429 ? 'rate_limited' : 'failed',
                'error_message' => $errorBody,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $detail['message'] = $errorBody;
        } catch (\Throwable $e) {
            $this->scheduler->markForRetry($scheduledPost, $e->getMessage());
            $detail['message'] = $e->getMessage();
        }

        return $detail;
    }

    private function safePostingLog(array $data): void
    {
        try {
            PostingLog::create($data);
        } catch (\Throwable) {
            // Table may be missing on older installs — do not block publishing
        }
    }
}
