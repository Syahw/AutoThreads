<?php

namespace AutoThreads\Controllers;

use AutoThreads\Models\AiUsageLog;
use AutoThreads\Models\GeneratedPost;
use AutoThreads\Models\PostingLog;
use AutoThreads\Models\ScheduledPost;
use AutoThreads\Models\ThreadsAccount;
use AutoThreads\Models\User;
use AutoThreads\Services\Admin\SystemSettings;
use AutoThreads\Services\Scheduler\CronLogger;
use AutoThreads\Services\Scheduler\CronPublishWorker;
use AutoThreads\Services\Scheduler\PostScheduler;
use AutoThreads\Services\Threads\ThreadPublisher;
use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AdminController
{
    private SystemSettings $settings;
    private PostScheduler $scheduler;

    public function __construct()
    {
        $this->settings = new SystemSettings();
        $this->scheduler = new PostScheduler();
    }

    public function dashboard(Request $request, Response $response): Response
    {
        $today = today()->toDateString();
        $weekAgo = now()->subDays(7)->toDateTimeString();

        $stats = [
            'users_total' => User::count(),
            'users_active' => User::where('is_active', true)->count(),
            'plans' => User::selectRaw('plan, COUNT(*) as count')->groupBy('plan')->pluck('count', 'plan'),
            'queue_pending' => ScheduledPost::where('status', 'queued')->count(),
            'queue_failed' => ScheduledPost::where('status', 'failed')->count(),
            'published_today' => ScheduledPost::where('status', 'posted')->whereDate('posted_at', $today)->count(),
            'published_week' => ScheduledPost::where('status', 'posted')->where('posted_at', '>=', $weekAgo)->count(),
            'posts_generated_today' => GeneratedPost::whereDate('created_at', $today)->count(),
            'ai_tokens_week' => (int) AiUsageLog::where('created_at', '>=', $weekAgo)->sum('total_tokens'),
            'ai_cost_week' => round((float) AiUsageLog::where('created_at', '>=', $weekAgo)->sum('cost'), 4),
            'worker' => $this->workerSummary(),
            'analytics' => $this->platformAnalytics(),
            'announcements' => $this->settings->all()['announcements'] ?? [],
        ];

        return $this->json($response, ['data' => $stats]);
    }

    public function users(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $query = User::query()->orderByDesc('created_at');

        if (!empty($params['search'])) {
            $s = '%' . $params['search'] . '%';
            $query->where(function ($q) use ($s) {
                $q->where('email', 'like', $s)->orWhere('name', 'like', $s);
            });
        }

        if (!empty($params['plan'])) {
            $query->where('plan', $params['plan']);
        }

        $users = $query->limit((int) ($params['limit'] ?? 50))->get()->map(fn (User $u) => $this->serializeUser($u));

        return $this->json($response, ['data' => $users]);
    }

    public function showUser(Request $request, Response $response, array $args): Response
    {
        $user = User::with(['threadsAccounts'])->findOrFail($args['id']);

        return $this->json($response, [
            'data' => array_merge($this->serializeUser($user, true), [
                'threads_accounts' => $user->threadsAccounts->map->toPublicArray(),
                'stats' => [
                    'generated_posts' => GeneratedPost::where('user_id', $user->id)->count(),
                    'scheduled_posts' => ScheduledPost::where('user_id', $user->id)->count(),
                    'published_posts' => ScheduledPost::where('user_id', $user->id)->where('status', 'posted')->count(),
                ],
            ]),
        ]);
    }

    public function updateUser(Request $request, Response $response, array $args): Response
    {
        $user = User::findOrFail($args['id']);
        $data = $request->getParsedBody() ?? [];

        if (isset($data['plan']) && in_array($data['plan'], ['free', 'starter', 'pro', 'enterprise'], true)) {
            $user->plan = $data['plan'];
        }

        if (isset($data['role']) && in_array($data['role'], ['user', 'moderator', 'admin'], true)) {
            $user->role = $data['role'];
        }

        if (array_key_exists('is_active', $data)) {
            $user->is_active = (bool) $data['is_active'];
        }

        $user->save();

        return $this->json($response, ['message' => 'User updated', 'data' => $this->serializeUser($user)]);
    }

    public function suspendUser(Request $request, Response $response, array $args): Response
    {
        $user = User::findOrFail($args['id']);
        $user->is_active = false;
        $settings = $user->settings ? json_decode($user->settings, true) : [];
        $settings['suspended_at'] = date('c');
        unset($settings['banned_at']);
        $user->settings = json_encode($settings);
        $user->save();

        return $this->json($response, ['message' => 'User suspended']);
    }

    public function banUser(Request $request, Response $response, array $args): Response
    {
        $user = User::findOrFail($args['id']);
        $user->is_active = false;
        $settings = $user->settings ? json_decode($user->settings, true) : [];
        $settings['banned_at'] = date('c');
        $user->settings = json_encode($settings);
        $user->save();

        return $this->json($response, ['message' => 'User banned']);
    }

    public function activateUser(Request $request, Response $response, array $args): Response
    {
        $user = User::findOrFail($args['id']);
        $user->is_active = true;
        $settings = $user->settings ? json_decode($user->settings, true) : [];
        unset($settings['suspended_at'], $settings['banned_at']);
        $user->settings = json_encode($settings);
        $user->save();

        return $this->json($response, [
            'message' => 'User activated',
            'data' => $this->serializeUser($user),
        ]);
    }

    public function resetQuota(Request $request, Response $response, array $args): Response
    {
        $user = User::findOrFail($args['id']);
        $settings = $user->settings ? json_decode($user->settings, true) : [];
        $settings['posts_this_month'] = 0;
        $settings['quota_reset_at'] = date('c');
        $user->settings = json_encode($settings);
        $user->save();

        return $this->json($response, ['message' => 'Quota reset']);
    }

    public function impersonate(Request $request, Response $response, array $args): Response
    {
        $adminId = $request->getAttribute('user_id');
        $target = User::findOrFail($args['id']);

        if ((int) $target->id === (int) $adminId) {
            return $this->json($response, ['error' => true, 'message' => 'Cannot impersonate yourself'], 422);
        }

        $token = $this->generateToken($target);

        return $this->json($response, [
            'message' => 'Impersonation token issued',
            'user' => $target->toPublicArray(),
            'token' => $token,
            'impersonated_by' => $adminId,
        ]);
    }

    public function subscriptions(Request $request, Response $response): Response
    {
        $users = User::orderByDesc('created_at')->limit(100)->get();
        $now = now();

        $rows = $users->map(function (User $user) use ($now) {
            $settings = $user->settings ? json_decode($user->settings, true) : [];
            $expires = $settings['subscription_expires_at'] ?? null;
            $trialEnds = $settings['trial_ends_at'] ?? null;
            $paymentStatus = $settings['payment_status'] ?? 'none';

            $status = 'active';
            if (!$user->is_active) {
                $status = isset($settings['banned_at']) ? 'banned' : 'suspended';
            } elseif ($trialEnds && $now->lt($trialEnds)) {
                $status = 'trial';
            } elseif ($expires && $now->gt($expires)) {
                $status = 'expired';
            } elseif ($paymentStatus === 'failed') {
                $status = 'payment_failed';
            }

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'plan' => $user->plan,
                'status' => $status,
                'subscription_expires_at' => $expires,
                'trial_ends_at' => $trialEnds,
                'bonus_credits' => (int) ($settings['bonus_credits'] ?? 0),
                'payment_status' => $paymentStatus,
            ];
        });

        return $this->json($response, [
            'data' => $rows,
            'summary' => [
                'active' => $rows->where('status', 'active')->count(),
                'trial' => $rows->where('status', 'trial')->count(),
                'expired' => $rows->where('status', 'expired')->count(),
                'payment_failed' => $rows->where('status', 'payment_failed')->count(),
            ],
        ]);
    }

    public function updateSubscription(Request $request, Response $response, array $args): Response
    {
        $user = User::findOrFail($args['id']);
        $data = $request->getParsedBody() ?? [];
        $settings = $user->settings ? json_decode($user->settings, true) : [];

        if (!empty($data['plan'])) {
            $user->plan = $data['plan'];
        }

        if (isset($data['bonus_credits'])) {
            $settings['bonus_credits'] = (int) $data['bonus_credits'];
        }

        if (!empty($data['extend_days'])) {
            $base = !empty($settings['subscription_expires_at'])
                ? new \DateTime($settings['subscription_expires_at'])
                : new \DateTime();
            $base->modify('+' . (int) $data['extend_days'] . ' days');
            $settings['subscription_expires_at'] = $base->format('c');
        }

        if (array_key_exists('payment_status', $data)) {
            $settings['payment_status'] = $data['payment_status'];
        }

        $user->settings = json_encode($settings);
        $user->save();

        return $this->json($response, ['message' => 'Subscription updated']);
    }

    public function queue(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $query = ScheduledPost::with(['user:id,name,email', 'threadsAccount:id,username', 'generatedPost:id,hook'])
            ->orderByDesc('scheduled_at');

        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        $posts = $query->limit((int) ($params['limit'] ?? 100))->get()->map(fn (ScheduledPost $p) => [
            'id' => $p->id,
            'user_id' => $p->user_id,
            'user_name' => $p->user?->name,
            'user_email' => $p->user?->email,
            'scheduled_at' => $p->getAttributes()['scheduled_at'] ?? null,
            'status' => $p->status,
            'display_status' => $this->mapQueueStatus($p),
            'retry_count' => $p->retry_count,
            'last_error' => $p->last_error,
            'threads_account' => $p->threadsAccount?->username,
            'hook' => $p->generatedPost?->hook,
        ]);

        return $this->json($response, ['data' => $posts]);
    }

    public function retryQueueItem(Request $request, Response $response, array $args): Response
    {
        $post = ScheduledPost::findOrFail($args['id']);
        $post->status = 'queued';
        $post->scheduled_at = now()->format('Y-m-d H:i:s');
        $post->save();

        return $this->json($response, ['message' => 'Post queued for retry']);
    }

    public function cancelQueueItem(Request $request, Response $response, array $args): Response
    {
        $post = ScheduledPost::where('id', $args['id'])->firstOrFail();
        $post->status = 'cancelled';
        $post->save();

        if ($post->generatedPost) {
            $post->generatedPost->status = 'approved';
            $post->generatedPost->save();
        }

        return $this->json($response, ['message' => 'Schedule cancelled']);
    }

    public function forcePublish(Request $request, Response $response, array $args): Response
    {
        $post = ScheduledPost::with(['generatedPost', 'threadsAccount'])->findOrFail($args['id']);
        $worker = new CronPublishWorker($this->scheduler);
        $result = $worker->processOne($post);

        return $this->json($response, ['message' => 'Force publish attempted', 'data' => $result]);
    }

    public function worker(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->workerSummary(true)]);
    }

    public function runWorker(Request $request, Response $response): Response
    {
        $worker = new CronPublishWorker($this->scheduler);
        $summary = $worker->processDuePosts(null);

        return $this->json($response, ['message' => 'Worker run completed', 'data' => $summary]);
    }

    public function aiLogs(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $logs = AiUsageLog::with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit((int) ($params['limit'] ?? 50))
            ->get();

        return $this->json($response, ['data' => $logs]);
    }

    public function systemLogs(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $lines = (int) ($params['lines'] ?? 100);
        $level = strtolower($params['level'] ?? 'all');

        $cron = new CronLogger();
        $appLog = dirname(__DIR__, 2) . '/storage/logs/app.log';

        $entries = [];

        foreach ($cron->tail($lines) as $line) {
            $entries[] = ['source' => 'worker', 'level' => 'info', 'message' => $line, 'timestamp' => null];
        }

        if (is_readable($appLog)) {
            $appLines = array_slice(array_filter(explode("\n", (string) file_get_contents($appLog))), -$lines);
            foreach ($appLines as $line) {
                $entries[] = [
                    'source' => 'app',
                    'level' => $this->detectLogLevel($line),
                    'message' => $line,
                    'timestamp' => null,
                ];
            }
        }

        if ($level !== 'all') {
            $entries = array_values(array_filter($entries, fn ($e) => $e['level'] === $level));
        }

        usort($entries, fn ($a, $b) => strcmp($b['message'], $a['message']));

        return $this->json($response, ['data' => array_slice($entries, 0, $lines)]);
    }

    public function getSettings(Request $request, Response $response): Response
    {
        return $this->json($response, ['data' => $this->settings->all()]);
    }

    public function updateSettings(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $updated = $this->settings->update($data);

        return $this->json($response, ['message' => 'Settings saved', 'data' => $updated]);
    }

    public function saveAnnouncement(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $title = trim((string) ($data['title'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));

        if ($title === '' || $message === '') {
            return $this->json($response, ['error' => true, 'message' => 'Title and message are required'], 422);
        }

        $target = in_array($data['target'] ?? '', ['all', 'free', 'paid'], true) ? $data['target'] : 'all';
        $all = $this->settings->all();
        $items = $all['announcements'] ?? [];
        $id = !empty($data['id']) ? (string) $data['id'] : bin2hex(random_bytes(8));
        $entry = [
            'id' => $id,
            'title' => $title,
            'message' => $message,
            'target' => $target,
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
            'created_at' => date('c'),
        ];

        $found = false;
        foreach ($items as $i => $item) {
            if (($item['id'] ?? '') === $id) {
                $entry['created_at'] = $item['created_at'] ?? $entry['created_at'];
                $items[$i] = $entry;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $items[] = $entry;
        }

        $this->settings->update(['announcements' => array_values($items)]);

        return $this->json($response, ['message' => $found ? 'Announcement updated' : 'Announcement created', 'data' => $entry]);
    }

    public function deleteAnnouncement(Request $request, Response $response, array $args): Response
    {
        $id = (string) ($args['id'] ?? '');
        $all = $this->settings->all();
        $items = array_values(array_filter(
            $all['announcements'] ?? [],
            fn ($item) => ($item['id'] ?? '') !== $id
        ));

        $this->settings->update(['announcements' => $items]);

        return $this->json($response, ['message' => 'Announcement deleted']);
    }

    public function threadsAccounts(Request $request, Response $response): Response
    {
        $accounts = ThreadsAccount::with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (ThreadsAccount $a) => array_merge($a->toPublicArray(), [
                'user_id' => $a->user_id,
                'user_name' => $a->user?->name,
                'user_email' => $a->user?->email,
            ]));

        return $this->json($response, ['data' => $accounts]);
    }

    public function disconnectThreadsAccount(Request $request, Response $response, array $args): Response
    {
        $account = ThreadsAccount::findOrFail($args['id']);
        $account->is_active = false;
        $account->save();

        return $this->json($response, ['message' => 'Threads account disconnected']);
    }

    /** @return array<string, mixed> */
    private function platformAnalytics(): array
    {
        $weekAgo = now()->subDays(6)->startOfDay();
        $postsPerDay = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $weekAgo->copy()->addDays($i);
            $date = $day->toDateString();
            $postsPerDay[] = [
                'date' => $date,
                'label' => $day->format('D'),
                'count' => ScheduledPost::where('status', 'posted')->whereDate('posted_at', $date)->count(),
            ];
        }

        $since = now()->subDays(7)->toDateTimeString();
        $published = ScheduledPost::where('status', 'posted')->where('posted_at', '>=', $since)->count();
        $failed = ScheduledPost::where('status', 'failed')->where('updated_at', '>=', $since)->count();
        $total = $published + $failed;
        $successRate = $total > 0 ? round(($published / $total) * 100, 1) : 0;
        $failureRate = $total > 0 ? round(($failed / $total) * 100, 1) : 0;

        $topUsers = User::query()
            ->select('users.id', 'users.name', 'users.email')
            ->selectRaw('COUNT(scheduled_posts.id) as post_count')
            ->leftJoin('scheduled_posts', function ($join) use ($since) {
                $join->on('scheduled_posts.user_id', '=', 'users.id')
                    ->where('scheduled_posts.status', '=', 'posted')
                    ->where('scheduled_posts.posted_at', '>=', $since);
            })
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('post_count')
            ->limit(5)
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'post_count' => (int) $u->post_count,
            ]);

        return [
            'posts_per_day' => $postsPerDay,
            'success_rate' => $successRate,
            'failure_rate' => $failureRate,
            'published_week' => $published,
            'failed_week' => $failed,
            'top_users' => $topUsers,
        ];
    }

    /** @return array<string, mixed> */
    private function workerSummary(bool $detailed = false): array
    {
        $cron = new CronLogger();
        $lastRun = $cron->getLastRunAt();
        $health = 'not_running';
        $healthLabel = 'Not running';

        if ($lastRun) {
            $lastTs = strtotime($lastRun);
            $ageMinutes = ($lastTs !== false) ? (time() - $lastTs) / 60 : 999;

            if ($ageMinutes <= 3) {
                $health = 'healthy';
                $healthLabel = 'Worker healthy';
            } elseif ($ageMinutes <= 15) {
                $health = 'delayed';
                $healthLabel = 'Delayed';
            }
        }

        $today = today()->toDateString();
        $publishedToday = ScheduledPost::where('status', 'posted')->whereDate('posted_at', $today)->count();
        $failedToday = ScheduledPost::where('status', 'failed')->whereDate('updated_at', $today)->count();

        $avgMs = (int) PostingLog::whereDate('created_at', $today)->avg('response_time_ms');

        $summary = [
            'last_run' => $lastRun,
            'health' => $health,
            'health_label' => $healthLabel,
            'published_today' => $publishedToday,
            'failed_today' => $failedToday,
            'avg_processing_ms' => $avgMs,
        ];

        if ($detailed) {
            $summary['log_lines'] = $cron->tail(30);
            $summary['log_path'] = $cron->getLogPath();
        }

        return $summary;
    }

    private function mapQueueStatus(ScheduledPost $post): string
    {
        return match ($post->status) {
            'queued' => $post->retry_count > 0 ? 'Retrying' : 'Pending',
            'processing' => 'Publishing',
            'posted' => 'Published',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            default => ucfirst($post->status),
        };
    }

    /** @return array<string, mixed> */
    private function serializeUser(User $user, bool $detailed = false): array
    {
        $settings = $user->settings ? json_decode($user->settings, true) : [];
        $status = 'active';

        if (!$user->is_active) {
            $status = isset($settings['banned_at']) ? 'banned' : 'suspended';
        }

        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'plan' => $user->plan,
            'role' => $user->role,
            'status' => $status,
            'is_active' => $user->is_active,
            'joined' => $user->created_at?->format('Y-m-d'),
            'last_login_at' => $user->last_login_at?->toISOString(),
        ];

        if ($detailed) {
            $data['settings'] = $settings;
        }

        return $data;
    }

    private function detectLogLevel(string $line): string
    {
        if (stripos($line, 'ERROR') !== false) {
            return 'error';
        }
        if (stripos($line, 'WARNING') !== false) {
            return 'warning';
        }

        return 'info';
    }

    /** @return array{access_token: string, refresh_token: string, expires_in: int} */
    private function generateToken(User $user): array
    {
        $now = time();
        $accessPayload = [
            'iss' => $_ENV['APP_URL'] ?? '',
            'sub' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'iat' => $now,
            'exp' => $now + (int) ($_ENV['JWT_EXPIRY'] ?? 3600),
        ];
        $refreshPayload = [
            'iss' => $_ENV['APP_URL'] ?? '',
            'sub' => $user->id,
            'iat' => $now,
            'exp' => $now + (int) ($_ENV['JWT_REFRESH_EXPIRY'] ?? 604800),
        ];

        return [
            'access_token' => JWT::encode($accessPayload, $_ENV['JWT_SECRET'], 'HS256'),
            'refresh_token' => JWT::encode($refreshPayload, $_ENV['JWT_SECRET'], 'HS256'),
            'expires_in' => (int) ($_ENV['JWT_EXPIRY'] ?? 3600),
        ];
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
