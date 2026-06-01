<?php

namespace AutoThreads\Controllers;

use AutoThreads\Models\ScheduledPost;
use AutoThreads\Services\Scheduler\PostScheduler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SchedulerController
{
    private PostScheduler $scheduler;

    public function __construct()
    {
        $this->scheduler = new PostScheduler();
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $query = ScheduledPost::where('user_id', $userId)
            ->with('generatedPost');

        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        $posts = $query->orderBy('scheduled_at', 'asc')
            ->limit($params['limit'] ?? 50)
            ->get();

        return $this->json($response, ['data' => $posts]);
    }

    public function schedule(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        try {
            $scheduled = $this->scheduler->schedulePost(
                $userId,
                (int) $data['post_id'],
                (int) $data['account_id'],
                $data['scheduled_at'] ?? null
            );

            return $this->json($response, [
                'message' => 'Post scheduled',
                'data' => $scheduled,
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

        $post->generatedPost->status = 'approved';
        $post->generatedPost->save();

        return $this->json($response, ['message' => 'Schedule cancelled']);
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

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
