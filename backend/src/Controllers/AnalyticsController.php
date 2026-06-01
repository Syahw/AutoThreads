<?php

namespace AutoThreads\Controllers;

use AutoThreads\Models\GeneratedPost;
use AutoThreads\Models\ScheduledPost;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AnalyticsController
{
    public function overview(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $totalPosts = GeneratedPost::where('user_id', $userId)->count();
        $approvedPosts = GeneratedPost::where('user_id', $userId)->where('status', 'approved')->count();
        $publishedPosts = GeneratedPost::where('user_id', $userId)->where('status', 'published')->count();
        $avgQuality = GeneratedPost::where('user_id', $userId)->avg('quality_score') ?? 0;

        return $this->json($response, [
            'data' => [
                'total_posts' => $totalPosts,
                'approved_posts' => $approvedPosts,
                'published_posts' => $publishedPosts,
                'avg_quality_score' => round($avgQuality, 2),
            ],
        ]);
    }

    public function postPerformance(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $posts = GeneratedPost::where('user_id', $userId)
            ->where('status', 'published')
            ->orderBy('quality_score', 'desc')
            ->limit(20)
            ->get(['id', 'content', 'hook', 'category', 'tone', 'quality_score', 'created_at']);

        return $this->json($response, ['data' => $posts]);
    }

    public function bestTimes(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        // Return placeholder data for now - will be populated as posts get published
        $bestTimes = [
            ['hour' => 8, 'day' => 'Monday', 'engagement' => 0],
            ['hour' => 12, 'day' => 'Wednesday', 'engagement' => 0],
            ['hour' => 18, 'day' => 'Friday', 'engagement' => 0],
        ];

        return $this->json($response, ['data' => $bestTimes]);
    }

    public function bestHooks(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $posts = GeneratedPost::where('user_id', $userId)
            ->whereNotNull('hook')
            ->orderBy('quality_score', 'desc')
            ->limit(10)
            ->get(['id', 'hook', 'quality_score', 'category', 'tone']);

        return $this->json($response, ['data' => $posts]);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
