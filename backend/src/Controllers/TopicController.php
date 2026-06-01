<?php

namespace AutoThreads\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class TopicController
{
    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $query = DB::table('topics')->where('user_id', $userId);

        if (!empty($params['niche_id'])) {
            $query->where('niche_id', $params['niche_id']);
        }
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        $topics = $query->orderBy('created_at', 'desc')
            ->limit($params['limit'] ?? 20)
            ->offset($params['offset'] ?? 0)
            ->get();

        return $this->json($response, ['data' => $topics]);
    }

    public function generate(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        // Placeholder - topic generation would use AI
        return $this->json($response, [
            'message' => 'Topic generation not yet implemented',
            'data' => [],
        ], 200);
    }

    public function updateStatus(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        DB::table('topics')
            ->where('id', $args['id'])
            ->where('user_id', $userId)
            ->update(['status' => $data['status'] ?? 'active']);

        return $this->json($response, ['message' => 'Topic status updated']);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        DB::table('topics')
            ->where('id', $args['id'])
            ->where('user_id', $userId)
            ->delete();

        return $this->json($response, ['message' => 'Topic deleted']);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
