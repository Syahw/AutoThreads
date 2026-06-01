<?php

namespace AutoThreads\Controllers;

use AutoThreads\Models\GeneratedPost;
use AutoThreads\Services\AI\ContentGenerator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ContentController
{
    private ContentGenerator $generator;

    public function __construct()
    {
        $this->generator = new ContentGenerator();
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $params = $request->getQueryParams();

        $query = GeneratedPost::where('user_id', $userId);

        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }
        if (!empty($params['niche_id'])) {
            $query->where('niche_id', $params['niche_id']);
        }
        if (!empty($params['category'])) {
            $query->where('category', $params['category']);
        }

        $posts = $query->orderBy('created_at', 'desc')
            ->limit($params['limit'] ?? 20)
            ->offset($params['offset'] ?? 0)
            ->get();

        $total = $query->count();

        return $this->json($response, [
            'data' => $posts,
            'total' => $total,
            'limit' => (int) ($params['limit'] ?? 20),
            'offset' => (int) ($params['offset'] ?? 0),
        ]);
    }

    public function generate(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        $config = [
            'user_id' => $userId,
            'niche_id' => $data['niche_id'] ?? null,
            'topic_id' => $data['topic_id'] ?? null,
            'affiliate_link_id' => $data['affiliate_link_id'] ?? null,
            'category' => $data['category'] ?? 'general',
            'tone' => $data['tone'] ?? null,
            'variations' => $data['variations'] ?? 1,
        ];

        try {
            if (($config['variations'] ?? 1) > 1) {
                $posts = $this->generator->generateVariations($config, $config['variations']);
                return $this->json($response, [
                    'message' => 'Content variations generated',
                    'data' => $posts,
                ], 201);
            }

            $post = $this->generator->generate($config);
            return $this->json($response, [
                'message' => 'Content generated',
                'data' => $post,
            ], 201);
        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => true,
                'message' => 'Generation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function regenerate(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $post = GeneratedPost::where('id', $args['id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        $config = [
            'user_id' => $userId,
            'niche_id' => $post->niche_id,
            'topic_id' => $post->topic_id,
            'affiliate_link_id' => $post->affiliate_link_id,
            'category' => $post->category,
        ];

        try {
            $newPost = $this->generator->generate($config);
            $newPost->parent_post_id = $post->id;
            $newPost->save();

            return $this->json($response, [
                'message' => 'Content regenerated',
                'data' => $newPost,
            ], 201);
        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => true,
                'message' => 'Regeneration failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody();

        $post = GeneratedPost::where('id', $args['id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        $post->update([
            'content' => $data['content'] ?? $post->content,
            'hook' => $data['hook'] ?? $post->hook,
            'cta' => $data['cta'] ?? $post->cta,
            'hashtags' => $data['hashtags'] ?? $post->hashtags,
        ]);

        return $this->json($response, ['message' => 'Post updated', 'data' => $post]);
    }

    public function approve(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $post = GeneratedPost::where('id', $args['id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        $post->status = 'approved';
        $post->save();

        return $this->json($response, ['message' => 'Post approved', 'data' => $post]);
    }

    public function reject(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $post = GeneratedPost::where('id', $args['id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        $post->status = 'rejected';
        $post->save();

        return $this->json($response, ['message' => 'Post rejected']);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        GeneratedPost::where('id', $args['id'])
            ->where('user_id', $userId)
            ->delete();

        return $this->json($response, ['message' => 'Post deleted']);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
