<?php

namespace AutoThreads\Controllers;

use AutoThreads\Models\Niche;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Support\Str;

class NicheController
{
    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $niches = Niche::where('user_id', $userId)->orderBy('name')->get();
        return $this->json($response, ['data' => $niches]);
    }

    public function store(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            return $this->json($response, ['error' => true, 'message' => 'Name is required'], 422);
        }

        $niche = Niche::create([
            'user_id' => $userId,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'keywords' => $data['keywords'] ?? [],
            'target_audience' => $data['target_audience'] ?? null,
        ]);

        return $this->json($response, ['message' => 'Niche created', 'data' => $niche], 201);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $niche = Niche::where('id', $args['id'])->where('user_id', $userId)->firstOrFail();
        return $this->json($response, ['data' => $niche]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();
        $niche = Niche::where('id', $args['id'])->where('user_id', $userId)->firstOrFail();

        $niche->update(array_filter([
            'name' => $data['name'] ?? null,
            'slug' => isset($data['name']) ? Str::slug($data['name']) : null,
            'description' => $data['description'] ?? null,
            'keywords' => $data['keywords'] ?? null,
            'target_audience' => $data['target_audience'] ?? null,
            'is_active' => $data['is_active'] ?? null,
        ], fn($v) => $v !== null));

        return $this->json($response, ['message' => 'Niche updated', 'data' => $niche]);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        Niche::where('id', $args['id'])->where('user_id', $userId)->delete();
        return $this->json($response, ['message' => 'Niche deleted']);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
