<?php

namespace AutoThreads\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ThreadsController
{
    public function connect(Request $request, Response $response): Response
    {
        // Threads OAuth - redirect to Meta authorization
        $appId = $_ENV['THREADS_APP_ID'] ?? '';
        $redirectUri = $_ENV['THREADS_REDIRECT_URI'] ?? '';

        if (empty($appId) || empty($redirectUri)) {
            return $this->json($response, [
                'error' => true,
                'message' => 'Threads API not configured. Please set THREADS_APP_ID and THREADS_REDIRECT_URI in .env',
            ], 400);
        }

        $authUrl = "https://threads.net/oauth/authorize?client_id={$appId}&redirect_uri={$redirectUri}&scope=threads_basic,threads_content_publish&response_type=code";

        return $this->json($response, ['data' => ['auth_url' => $authUrl]]);
    }

    public function callback(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $code = $params['code'] ?? null;

        if (!$code) {
            return $this->json($response, [
                'error' => true,
                'message' => 'Authorization code not provided',
            ], 400);
        }

        // Token exchange would happen here
        return $this->json($response, [
            'message' => 'Threads callback received',
            'data' => ['code' => $code],
        ]);
    }

    public function accounts(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        // Return connected accounts (placeholder until OAuth is configured)
        return $this->json($response, ['data' => []]);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
