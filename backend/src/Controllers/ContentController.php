<?php

namespace AutoThreads\Controllers;

use AutoThreads\Models\GeneratedPost;
use AutoThreads\Models\ThreadsAccount;
use AutoThreads\Services\AI\ContentGenerator;
use AutoThreads\Services\AI\Humanizer;
use AutoThreads\Services\Media\HookImageStorage;
use AutoThreads\Services\Threads\ThreadPublisher;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ContentController
{
    private ContentGenerator $generator;
    private HookImageStorage $hookImages;

    public function __construct()
    {
        $this->generator = new ContentGenerator();
        $this->hookImages = new HookImageStorage();
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
            'data' => $posts->map(fn (GeneratedPost $post) => $this->serializePost($post)),
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
            'niche_id' => !empty($data['niche_id']) ? $data['niche_id'] : null,
            'topic_id' => !empty($data['topic_id']) ? $data['topic_id'] : null,
            'affiliate_link_id' => !empty($data['affiliate_link_id']) ? $data['affiliate_link_id'] : null,
            'category' => $data['category'] ?? 'general',
            'tone' => !empty($data['tone']) ? $data['tone'] : null,
            'variations' => (int) ($data['variations'] ?? 1),
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
                'data' => $this->serializePost($post),
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

        $updates = [];

        if (isset($data['content'])) {
            $updates['content'] = $data['content'];
        }
        if (isset($data['hook'])) {
            $updates['hook'] = $data['hook'];
        }
        if (isset($data['cta'])) {
            $updates['cta'] = $data['cta'];
        }
        if (isset($data['hashtags'])) {
            $updates['hashtags'] = $data['hashtags'];
        }
        if (array_key_exists('affiliate_link_id', $data)) {
            $updates['affiliate_link_id'] = $data['affiliate_link_id'] ?: null;
        }

        if (!empty($updates['content'])) {
            $humanizer = new Humanizer();
            $parsed = $humanizer->parseThreadReplies($updates['content']);
            if (count($parsed) >= 5) {
                $metadata = $post->metadata ?? [];
                $metadata['replies'] = $parsed;
                $metadata['thread_format'] = true;
                $updates['metadata'] = $metadata;
                $updates['hook'] = $updates['hook'] ?? ($parsed[0] ?? $post->hook);
                $updates['cta'] = $updates['cta'] ?? ($parsed[array_key_last($parsed)] ?? $post->cta);
            }
        }

        $post->update($updates);

        return $this->json($response, ['message' => 'Post updated', 'data' => $this->serializePost($post->fresh())]);
    }

    public function uploadHookImage(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $post = GeneratedPost::where('id', $args['id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        if (!in_array($post->status, ['draft', 'approved'], true)) {
            return $this->json($response, [
                'error' => true,
                'message' => 'Hook image can only be added to draft or approved posts',
            ], 422);
        }

        $files = $request->getUploadedFiles();
        $file = $files['image'] ?? null;

        if ($file === null) {
            return $this->json($response, [
                'error' => true,
                'message' => 'Missing image file (field name: image)',
            ], 422);
        }

        try {
            $stored = $this->hookImages->store($userId, $file);
            $this->hookImages->attachToPost($post, $stored);

            return $this->json($response, [
                'message' => 'Hook image uploaded',
                'data' => $this->serializePost($post->fresh()),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, ['error' => true, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => true,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function deleteHookImage(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $post = GeneratedPost::where('id', $args['id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        $this->hookImages->deleteForPost($post);

        return $this->json($response, [
            'message' => 'Hook image removed',
            'data' => $this->serializePost($post->fresh()),
        ]);
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

    /**
     * Publish an approved post immediately as a Threads reply chain (no scheduler).
     */
    public function publish(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');
        $data = $request->getParsedBody() ?? [];

        $post = GeneratedPost::where('id', $args['id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        if ($post->status === 'posted') {
            return $this->json($response, [
                'error' => true,
                'message' => 'Post has already been published',
            ], 422);
        }

        if ($post->status !== 'approved') {
            return $this->json($response, [
                'error' => true,
                'message' => 'Only approved posts can be published. Approve the post first.',
            ], 422);
        }

        $accountQuery = ThreadsAccount::where('user_id', $userId)->where('is_active', true);

        if (!empty($data['account_id'])) {
            $accountQuery->where('id', (int) $data['account_id']);
        }

        $account = $accountQuery->orderBy('created_at', 'desc')->first();

        if (!$account) {
            return $this->json($response, [
                'error' => true,
                'message' => 'No connected Threads account. Connect one in Settings first.',
            ], 422);
        }

        if (!empty($data['affiliate_link_id'])) {
            $post->affiliate_link_id = (int) $data['affiliate_link_id'];
            $post->save();
        }

        $publisher = new ThreadPublisher();

        try {
            $result = $publisher->publish($post, $account);

            $metadata = $post->metadata ?? [];
            $metadata['threads_publish'] = [
                'root_post_id' => $result['root_post_id'],
                'post_ids' => $result['post_ids'],
                'published_count' => $result['published_count'],
                'account_id' => $account->id,
                'published_at' => date('c'),
            ];

            $post->status = 'posted';
            $post->metadata = $metadata;
            $post->save();

            return $this->json($response, [
                'message' => 'Thread published to Threads',
                'data' => $post,
                'threads' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => true,
                'message' => 'Publish failed: ' . $e->getMessage(),
            ], 500);
        }
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
        $post = GeneratedPost::where('id', $args['id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        $filename = $post->metadata['hook_image']['filename'] ?? null;
        $this->hookImages->removeStoredFile(is_string($filename) ? $filename : null);
        $post->delete();

        return $this->json($response, ['message' => 'Post deleted']);
    }

    private function serializePost(GeneratedPost $post): array
    {
        $data = $post->toArray();
        $data['hook_image_url'] = $this->hookImages->resolvePublicUrl($post);

        return $data;
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
