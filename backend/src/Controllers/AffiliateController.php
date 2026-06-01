<?php

namespace AutoThreads\Controllers;

use AutoThreads\Models\AffiliateLink;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AffiliateController
{
    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $links = AffiliateLink::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->json($response, ['data' => $links]);
    }

    public function store(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        $link = AffiliateLink::create([
            'user_id' => $userId,
            'product_name' => $data['product_name'] ?? '',
            'url' => $data['url'] ?? '',
            'short_url' => $data['short_url'] ?? null,
            'platform' => $data['platform'] ?? null,
            'commission_rate' => $data['commission_rate'] ?? null,
            'cta_style' => $data['cta_style'] ?? 'soft',
            'niche_id' => $data['niche_id'] ?? null,
            'is_active' => true,
        ]);

        return $this->json($response, ['message' => 'Affiliate link created', 'data' => $link], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        $link = AffiliateLink::where('id', $args['id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        $link->update(array_filter([
            'product_name' => $data['product_name'] ?? null,
            'url' => $data['url'] ?? null,
            'short_url' => $data['short_url'] ?? null,
            'platform' => $data['platform'] ?? null,
            'commission_rate' => $data['commission_rate'] ?? null,
            'cta_style' => $data['cta_style'] ?? null,
            'niche_id' => $data['niche_id'] ?? null,
            'is_active' => $data['is_active'] ?? null,
        ], fn($v) => $v !== null));

        return $this->json($response, ['message' => 'Affiliate link updated', 'data' => $link]);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        AffiliateLink::where('id', $args['id'])
            ->where('user_id', $userId)
            ->delete();

        return $this->json($response, ['message' => 'Affiliate link deleted']);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
