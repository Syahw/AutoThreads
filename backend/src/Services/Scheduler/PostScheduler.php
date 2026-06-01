<?php

namespace AutoThreads\Services\Scheduler;

use AutoThreads\Models\GeneratedPost;
use AutoThreads\Models\ScheduledPost;
use AutoThreads\Models\ThreadsAccount;

/**
 * PostScheduler - Manages post scheduling with randomized timing
 * 
 * Design decisions:
 * - Randomized posting times within user-defined windows
 * - Prevents duplicate posting (same content, same day)
 * - Respects daily post limits per account
 * - Supports retry logic for failed posts
 * - Queue-based for horizontal scaling
 */
class PostScheduler
{
    private int $minPostsPerDay;
    private int $maxPostsPerDay;
    private int $earliestHour;
    private int $latestHour;
    private string $timezone;

    public function __construct()
    {
        $this->minPostsPerDay = (int) ($_ENV['SCHEDULER_MIN_POSTS_PER_DAY'] ?? 1);
        $this->maxPostsPerDay = (int) ($_ENV['SCHEDULER_MAX_POSTS_PER_DAY'] ?? 5);
        $this->earliestHour = (int) ($_ENV['SCHEDULER_EARLIEST_HOUR'] ?? 8);
        $this->latestHour = (int) ($_ENV['SCHEDULER_LATEST_HOUR'] ?? 22);
        $this->timezone = $_ENV['SCHEDULER_TIMEZONE'] ?? 'UTC';
    }

    /**
     * Schedule a single post at a specific or random time
     */
    public function schedulePost(int $userId, int $postId, int $accountId, ?string $scheduledAt = null): ScheduledPost
    {
        $post = GeneratedPost::findOrFail($postId);
        $account = ThreadsAccount::findOrFail($accountId);

        // Determine scheduling time
        if ($scheduledAt) {
            $time = new \DateTime($scheduledAt, new \DateTimeZone($this->timezone));
        } else {
            $time = $this->generateRandomTime();
        }

        // Check for duplicate scheduling
        $exists = ScheduledPost::where('generated_post_id', $postId)
            ->whereIn('status', ['queued', 'processing'])
            ->exists();

        if ($exists) {
            throw new \RuntimeException('Post is already scheduled');
        }

        // Check daily limit
        $todayCount = ScheduledPost::where('user_id', $userId)
            ->where('threads_account_id', $accountId)
            ->whereDate('scheduled_at', $time->format('Y-m-d'))
            ->whereIn('status', ['queued', 'processing', 'posted'])
            ->count();

        if ($todayCount >= $this->maxPostsPerDay) {
            throw new \RuntimeException("Daily post limit ({$this->maxPostsPerDay}) reached for this account");
        }

        // Create scheduled post
        $scheduled = ScheduledPost::create([
            'user_id' => $userId,
            'generated_post_id' => $postId,
            'threads_account_id' => $accountId,
            'scheduled_at' => $time->format('Y-m-d H:i:s'),
            'status' => 'queued',
            'retry_count' => 0,
            'max_retries' => 3,
        ]);

        // Update generated post status
        $post->status = 'scheduled';
        $post->save();

        return $scheduled;
    }

    /**
     * Auto-schedule multiple posts for a day
     */
    public function autoScheduleDay(int $userId, int $accountId, ?string $date = null): array
    {
        $date = $date ?? date('Y-m-d');
        $postsToSchedule = rand($this->minPostsPerDay, $this->maxPostsPerDay);

        // Get approved posts that haven't been scheduled
        $availablePosts = GeneratedPost::where('user_id', $userId)
            ->where('status', 'approved')
            ->orderBy('quality_score', 'desc')
            ->limit($postsToSchedule)
            ->get();

        $scheduled = [];
        $times = $this->generateDistributedTimes($date, count($availablePosts));

        foreach ($availablePosts as $index => $post) {
            try {
                $scheduled[] = $this->schedulePost(
                    $userId,
                    $post->id,
                    $accountId,
                    $times[$index]
                );
            } catch (\RuntimeException $e) {
                // Skip if limit reached or duplicate
                continue;
            }
        }

        return $scheduled;
    }

    /**
     * Get posts that are due for publishing
     */
    public function getDuePosts(): \Illuminate\Database\Eloquent\Collection
    {
        return ScheduledPost::where('status', 'queued')
            ->where('scheduled_at', '<=', now())
            ->with(['generatedPost', 'threadsAccount'])
            ->orderBy('scheduled_at', 'asc')
            ->limit(10)
            ->get();
    }

    /**
     * Mark a post for retry after failure
     */
    public function markForRetry(ScheduledPost $post, string $error): void
    {
        $post->retry_count += 1;
        $post->last_error = $error;

        if ($post->retry_count >= $post->max_retries) {
            $post->status = 'failed';
            $post->generatedPost->status = 'failed';
            $post->generatedPost->save();
        } else {
            // Retry in 15 minutes
            $post->scheduled_at = now()->addMinutes(15);
        }

        $post->save();
    }

    /**
     * Generate a random posting time within the allowed window
     */
    private function generateRandomTime(?string $date = null): \DateTime
    {
        $date = $date ?? date('Y-m-d');
        $hour = rand($this->earliestHour, $this->latestHour);
        $minute = rand(0, 59);

        return new \DateTime(
            "{$date} {$hour}:{$minute}:00",
            new \DateTimeZone($this->timezone)
        );
    }

    /**
     * Generate evenly distributed times with randomization
     */
    private function generateDistributedTimes(string $date, int $count): array
    {
        if ($count === 0) return [];

        $windowHours = $this->latestHour - $this->earliestHour;
        $intervalMinutes = ($windowHours * 60) / max($count, 1);

        $times = [];
        for ($i = 0; $i < $count; $i++) {
            $baseMinutes = $this->earliestHour * 60 + ($i * $intervalMinutes);
            // Add randomness (±20 minutes)
            $jitter = rand(-20, 20);
            $actualMinutes = max($this->earliestHour * 60, min($this->latestHour * 60, $baseMinutes + $jitter));

            $hour = (int) ($actualMinutes / 60);
            $minute = (int) ($actualMinutes % 60);

            $times[] = "{$date} " . sprintf('%02d:%02d:00', $hour, $minute);
        }

        return $times;
    }
}
