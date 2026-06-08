<?php

namespace AutoThreads\Controllers;

use AutoThreads\Models\GeneratedPost;
use AutoThreads\Models\ScheduledPost;
use AutoThreads\Models\Analytics;
use AutoThreads\Models\User;
use AutoThreads\Services\Admin\SystemSettings;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DashboardController
{
    public function stats(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $user = User::find($userId);
        $plan = $user?->plan ?? 'free';

        $stats = [
            'total_posts' => GeneratedPost::where('user_id', $userId)->count(),
            'posts_today' => GeneratedPost::where('user_id', $userId)
                ->whereDate('created_at', today())->count(),
            'scheduled_pending' => ScheduledPost::where('user_id', $userId)
                ->where('status', 'queued')->count(),
            'posted_this_week' => ScheduledPost::where('user_id', $userId)
                ->where('status', 'posted')
                ->where('posted_at', '>=', now()->subDays(7))->count(),
            'avg_quality_score' => round(
                GeneratedPost::where('user_id', $userId)->avg('quality_score') ?? 0, 1
            ),
            'total_impressions' => Analytics::where('user_id', $userId)->sum('impressions'),
            'total_engagement' => Analytics::where('user_id', $userId)
                ->selectRaw('SUM(likes + comments + reposts) as total')
                ->value('total') ?? 0,
            'failed_posts' => ScheduledPost::where('user_id', $userId)
                ->where('status', 'failed')->count(),
            'announcements' => $this->announcementsForPlan($plan),
        ];

        return $this->json($response, ['data' => $stats]);
    }

    /** @return list<array<string, mixed>> */
    private function announcementsForPlan(string $plan): array
    {
        $items = (new SystemSettings())->all()['announcements'] ?? [];
        $isFree = $plan === 'free';
        $isPaid = in_array($plan, ['starter', 'pro', 'enterprise'], true);

        $filtered = array_filter($items, function (array $item) use ($isFree, $isPaid): bool {
            if (empty($item['active'])) {
                return false;
            }

            return match ($item['target'] ?? 'all') {
                'free' => $isFree,
                'paid' => $isPaid,
                default => true,
            };
        });

        usort($filtered, fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        return array_values(array_map(fn (array $item) => [
            'id' => $item['id'] ?? '',
            'title' => $item['title'] ?? '',
            'message' => $item['message'] ?? '',
            'target' => $item['target'] ?? 'all',
            'created_at' => $item['created_at'] ?? null,
        ], $filtered));
    }

    public function recentActivity(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $recent = ScheduledPost::where('user_id', $userId)
            ->with('generatedPost:id,hook,category,quality_score')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        return $this->json($response, ['data' => $recent]);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
