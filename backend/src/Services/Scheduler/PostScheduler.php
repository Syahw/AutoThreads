<?php

namespace AutoThreads\Services\Scheduler;

use AutoThreads\Models\GeneratedPost;
use AutoThreads\Models\ScheduledPost;
use AutoThreads\Models\ThreadsAccount;
use Carbon\Carbon;

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
        $this->earliestHour = (int) ($_ENV['SCHEDULER_EARLIEST_HOUR'] ?? 0);
        $this->latestHour = (int) ($_ENV['SCHEDULER_LATEST_HOUR'] ?? 23);
        $this->timezone = $_ENV['SCHEDULER_TIMEZONE'] ?? 'UTC';
    }

    /**
     * Schedule a single post at a specific or random time
     */
    public function schedulePost(int $userId, int $postId, int $accountId, ?string $scheduledAt = null): ScheduledPost
    {
        $post = GeneratedPost::where('id', $postId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $account = ThreadsAccount::where('id', $accountId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->firstOrFail();

        if ($post->status !== 'approved') {
            throw new \RuntimeException('Only approved posts can be scheduled. Approve the post first.');
        }

        // Determine scheduling time
        if ($scheduledAt) {
            $normalized = str_replace('T', ' ', trim($scheduledAt));
            $time = new \DateTime($normalized, new \DateTimeZone($this->timezone));
            $this->validateScheduledTime($time);
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
        $now = Carbon::now($this->timezone)->format('Y-m-d H:i:s');

        return ScheduledPost::where('status', 'queued')
            ->where('scheduled_at', '<=', $now)
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
            $post->scheduled_at = Carbon::now($this->timezone)->addMinutes(15)->format('Y-m-d H:i:s');
        }

        $post->save();
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        return [
            'timezone' => $this->timezone,
            'earliest_hour' => $this->earliestHour,
            'latest_hour' => $this->latestHour,
            'min_posts_per_day' => $this->minPostsPerDay,
            'max_posts_per_day' => $this->maxPostsPerDay,
        ];
    }

    private function validateScheduledTime(\DateTime $time): void
    {
        $now = new \DateTime('now', new \DateTimeZone($this->timezone));

        if ($time <= $now) {
            throw new \RuntimeException('Scheduled time must be in the future.');
        }

        $hour = (int) $time->format('G');

        if ($hour < $this->earliestHour || $hour > $this->latestHour) {
            throw new \RuntimeException(
                "Scheduled time must be between {$this->earliestHour}:00 and {$this->latestHour}:59 ({$this->timezone})."
            );
        }
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
