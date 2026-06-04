<?php

namespace AutoThreads\Controllers;

use AutoThreads\Models\PostingLog;
use AutoThreads\Models\ScheduledPost;
use AutoThreads\Services\Scheduler\CronLogger;
use AutoThreads\Services\Scheduler\CronPublishWorker;
use AutoThreads\Services\Scheduler\PostScheduler;
use Carbon\Carbon;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SchedulerController
{
    private PostScheduler $scheduler;

    public function __construct()
    {
        $this->scheduler = new PostScheduler();
    }

    public function settings(Request $request, Response $response): Response
    {
        return $this->json($response, [
            'data' => $this->scheduler->getSettings(),
        ]);
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $query = ScheduledPost::where('user_id', $userId)
            ->with(['generatedPost', 'threadsAccount:id,username']);

        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        $posts = $query->orderBy('scheduled_at', 'asc')
            ->limit($params['limit'] ?? 50)
            ->get()
            ->map(fn (ScheduledPost $post) => $this->serializeScheduledPost($post));

        return $this->json($response, ['data' => $posts->values()]);
    }

    public function schedule(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['post_id']) || empty($data['account_id'])) {
            return $this->json($response, [
                'error' => true,
                'message' => 'post_id and account_id are required',
            ], 422);
        }

        if (empty($data['scheduled_at'])) {
            return $this->json($response, [
                'error' => true,
                'message' => 'scheduled_at is required (date and time)',
            ], 422);
        }

        try {
            $scheduled = $this->scheduler->schedulePost(
                $userId,
                (int) $data['post_id'],
                (int) $data['account_id'],
                $data['scheduled_at']
            );

            return $this->json($response, [
                'message' => 'Post scheduled',
                'data' => $this->serializeScheduledPost($scheduled),
            ], 201);
        } catch (\RuntimeException $e) {
            return $this->json($response, [
                'error' => true,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function cancel(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $post = ScheduledPost::where('id', $args['id'])
            ->where('user_id', $userId)
            ->where('status', 'queued')
            ->firstOrFail();

        $post->status = 'cancelled';
        $post->save();

        if ($post->generatedPost) {
            $post->generatedPost->status = 'approved';
            $post->generatedPost->save();
        }

        return $this->json($response, ['message' => 'Schedule cancelled']);
    }

    /**
     * Debug: is cron running, what's due, recent failures.
     */
    public function diagnostics(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $tz = $this->scheduler->getTimezone();
        $now = Carbon::now($tz)->format('Y-m-d H:i:s');
        $cronLog = new CronLogger();

        $queued = ScheduledPost::where('user_id', $userId)->where('status', 'queued')->count();
        $due = ScheduledPost::where('user_id', $userId)
            ->where('status', 'queued')
            ->where('scheduled_at', '<=', $now)
            ->count();
        $processing = ScheduledPost::where('user_id', $userId)->where('status', 'processing')->count();
        $failed = ScheduledPost::where('user_id', $userId)->where('status', 'failed')->count();

        $nextDue = ScheduledPost::where('user_id', $userId)
            ->where('status', 'queued')
            ->orderBy('scheduled_at', 'asc')
            ->first();

        $recentLogs = [];
        try {
            $recentLogs = PostingLog::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->toArray();
        } catch (\Throwable) {
            $recentLogs = [];
        }

        $lastCronLine = $cronLog->tail(1);
        $cronRunningRecently = false;
        if ($lastCronLine !== []) {
            $lastRun = $cronLog->getLastRunAt();
            if ($lastRun) {
                try {
                    $lastDt = Carbon::parse($lastRun, $tz);
                    $cronRunningRecently = $lastDt->diffInMinutes(Carbon::now($tz)) <= 3;
                } catch (\Throwable) {
                    $cronRunningRecently = false;
                }
            }
        }

        return $this->json($response, [
            'data' => [
                'server_now' => $now,
                'timezone' => $tz,
                'cron_log_path' => $cronLog->getLogPath(),
                'cron_last_line' => $lastCronLine[0] ?? null,
                'cron_running_recently' => $cronRunningRecently,
                'queued_count' => $queued,
                'due_now_count' => $due,
                'processing_count' => $processing,
                'failed_count' => $failed,
                'next_due' => $nextDue ? $this->serializeScheduledPost($nextDue) : null,
                'recent_posting_logs' => $recentLogs,
            ],
        ]);
    }

    public function workerLog(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $lines = (int) ($params['lines'] ?? 80);
        $cronLog = new CronLogger();

        return $this->json($response, [
            'data' => [
                'path' => $cronLog->getLogPath(),
                'lines' => $cronLog->tail(min($lines, 200)),
            ],
        ]);
    }

    /**
     * Manually process due posts for the logged-in user (same logic as cron).
     */
    public function runNow(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $cronLog = new CronLogger();
        $worker = new CronPublishWorker($this->scheduler);

        $cronLog->line("=== runNow via API (user #{$userId}) ===");

        try {
            $summary = $worker->processDuePosts($userId);
            $cronLog->line(sprintf(
                'runNow: processed=%d published=%d failed=%d',
                $summary['processed'],
                $summary['published'],
                $summary['failed']
            ));

            return $this->json($response, [
                'message' => 'Publish worker finished',
                'data' => $summary,
            ]);
        } catch (\Throwable $e) {
            $cronLog->line('runNow FATAL: ' . $e->getMessage());

            return $this->json($response, [
                'error' => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function calendar(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $startDate = $params['start'] ?? date('Y-m-01');
        $endDate = $params['end'] ?? date('Y-m-t');

        $posts = ScheduledPost::where('user_id', $userId)
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->with('generatedPost:id,content,hook,category')
            ->get()
            ->groupBy(fn($p) => $p->scheduled_at->format('Y-m-d'));

        return $this->json($response, ['data' => $posts]);
    }

    /**
     * Expose scheduled_at as wall-clock in SCHEDULER_TIMEZONE (avoids UTC JSON shift in UI).
     */
    private function serializeScheduledPost(ScheduledPost $post): array
    {
        $data = $post->toArray();
        $raw = $post->getAttributes()['scheduled_at'] ?? null;

        if ($raw !== null) {
            $data['scheduled_at'] = is_string($raw)
                ? $raw
                : $post->scheduled_at->format('Y-m-d H:i:s');
        }

        $tz = $_ENV['SCHEDULER_TIMEZONE'] ?? 'UTC';
        $data['scheduled_at_timezone'] = $tz;

        if (!empty($data['scheduled_at'])) {
            $data['scheduled_at_display'] = $this->formatWallClock($data['scheduled_at'], $tz);
        }

        return $data;
    }

    private function formatWallClock(string $datetime, string $timezone): string
    {
        try {
            $dt = new \DateTime($datetime, new \DateTimeZone($timezone));

            return $dt->format('D, j M Y, g:i A');
        } catch (\Exception) {
            return $datetime;
        }
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
