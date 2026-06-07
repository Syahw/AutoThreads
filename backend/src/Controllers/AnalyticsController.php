<?php

namespace AutoThreads\Controllers;

use AutoThreads\Models\Analytics;
use AutoThreads\Models\GeneratedPost;
use AutoThreads\Models\ScheduledPost;
use AutoThreads\Services\Analytics\AnalyticsCollector;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AnalyticsController
{
    public function overview(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $totalPosts = GeneratedPost::where('user_id', $userId)->count();
        $approvedPosts = GeneratedPost::where('user_id', $userId)->where('status', 'approved')->count();
        $publishedPosts = GeneratedPost::where('user_id', $userId)->where('status', 'posted')->count();
        $avgQuality = GeneratedPost::where('user_id', $userId)->avg('quality_score') ?? 0;

        $analyticsQuery = Analytics::where('user_id', $userId);

        return $this->json($response, [
            'data' => [
                'total_posts' => $totalPosts,
                'approved_posts' => $approvedPosts,
                'published_posts' => $publishedPosts,
                'avg_quality_score' => round($avgQuality, 2),
                'total_impressions' => (int) $analyticsQuery->sum('impressions'),
                'total_engagement' => (int) Analytics::where('user_id', $userId)
                    ->selectRaw('SUM(likes + comments + reposts) as total')
                    ->value('total'),
            ],
        ]);
    }

    public function engagementTrend(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $rows = Analytics::where('user_id', $userId)
            ->where('collected_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(collected_at) as date')
            ->selectRaw('SUM(impressions) as impressions')
            ->selectRaw('SUM(likes + comments + reposts) as engagement')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $this->json($response, ['data' => $rows]);
    }

    public function postPerformance(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $posts = GeneratedPost::query()
            ->from('generated_posts')
            ->where('generated_posts.user_id', $userId)
            ->where('generated_posts.status', 'posted')
            ->leftJoin('analytics', 'analytics.generated_post_id', '=', 'generated_posts.id')
            ->select([
                'generated_posts.id',
                'generated_posts.hook',
                'generated_posts.category',
                'generated_posts.tone',
                'generated_posts.quality_score',
                'generated_posts.created_at',
            ])
            ->selectRaw('COALESCE(analytics.impressions, 0) as impressions')
            ->selectRaw('COALESCE(analytics.likes + analytics.comments + analytics.reposts, 0) as engagement')
            ->orderByDesc('engagement')
            ->orderByDesc('generated_posts.quality_score')
            ->limit(10)
            ->get();

        return $this->json($response, ['data' => $posts]);
    }

    public function bestTimes(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $bestTimes = ScheduledPost::query()
            ->from('scheduled_posts')
            ->where('scheduled_posts.user_id', $userId)
            ->where('scheduled_posts.status', 'posted')
            ->whereNotNull('scheduled_posts.posted_at')
            ->join('analytics', 'analytics.scheduled_post_id', '=', 'scheduled_posts.id')
            ->selectRaw('HOUR(scheduled_posts.posted_at) as hour')
            ->selectRaw('DAYNAME(scheduled_posts.posted_at) as day')
            ->selectRaw('SUM(analytics.likes + analytics.comments + analytics.reposts) as engagement')
            ->groupBy('hour', 'day')
            ->orderByDesc('engagement')
            ->limit(8)
            ->get();

        return $this->json($response, ['data' => $bestTimes]);
    }

    public function bestHooks(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $posts = GeneratedPost::query()
            ->from('generated_posts')
            ->where('generated_posts.user_id', $userId)
            ->where('generated_posts.status', 'posted')
            ->whereNotNull('generated_posts.hook')
            ->leftJoin('analytics', 'analytics.generated_post_id', '=', 'generated_posts.id')
            ->select([
                'generated_posts.id',
                'generated_posts.hook',
                'generated_posts.quality_score',
                'generated_posts.category',
                'generated_posts.tone',
            ])
            ->selectRaw('COALESCE(analytics.likes + analytics.comments + analytics.reposts, 0) as engagement')
            ->orderByDesc('engagement')
            ->orderByDesc('generated_posts.quality_score')
            ->limit(10)
            ->get();

        return $this->json($response, ['data' => $posts]);
    }

    public function collect(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $collector = new AnalyticsCollector();

        try {
            $summary = $collector->collect($userId);

            return $this->json($response, [
                'message' => 'Analytics collection finished',
                'data' => $summary,
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, [
                'error' => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
