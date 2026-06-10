<?php

namespace AutoThreads\Controllers;

use AutoThreads\Models\GeneratedPost;
use AutoThreads\Models\ScheduledPost;
use AutoThreads\Models\ThreadsAccount;
use AutoThreads\Services\Threads\ThreadsClient;
use AutoThreads\Services\AI\ContentGenerator;
use AutoThreads\Models\Niche;
use AutoThreads\Services\AI\HookImageAnalyzer;
use AutoThreads\Services\AI\Humanizer;
use AutoThreads\Services\AI\ThreadConfigImageAnalyzer;
use AutoThreads\Services\Media\HookImageStorage;
use AutoThreads\Services\Media\ImagePreprocessor;
use AutoThreads\Services\Threads\ThreadPublisher;
use AutoThreads\Config\Bootstrap;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;

class ContentController
{
    private ContentGenerator $generator;
    private HookImageStorage $hookImages;
    private ImagePreprocessor $imagePreprocessor;

    public function __construct()
    {
        $container = Bootstrap::init();
        $logger = $container->get('logger');
        $this->generator = new ContentGenerator($logger);
        $this->hookImages = new HookImageStorage();
        $this->imagePreprocessor = new ImagePreprocessor($this->generator->getImageConfig());
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

        $limit = (int) ($params['limit'] ?? 20);
        $offset = (int) ($params['offset'] ?? 0);
        $total = (clone $query)->count();

        $posts = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return $this->json($response, [
            'data' => $posts->map(fn (GeneratedPost $post) => $this->serializePost($post)),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function visionSettings(Request $request, Response $response): Response
    {
        return $this->json($response, [
            'data' => $this->generator->getImageConfig()->toPublicArray(),
        ]);
    }

    public function analyzeThreadImage(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $uploaded = $this->collectReferenceImages($request);

        if ($uploaded === []) {
            return $this->json($response, [
                'error' => true,
                'message' => 'reference_image is required',
            ], 422);
        }

        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $data = [];
        }

        $language = !empty($data['language']) ? strtolower(trim((string) $data['language'])) : 'bm';

        try {
            $processed = $this->imagePreprocessor->processUploads($uploaded, [
                'high_detail' => false,
            ]);

            $niches = Niche::where('user_id', $userId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'description'])
                ->map(fn (Niche $n) => [
                    'id' => $n->id,
                    'name' => $n->name,
                    'description' => $n->description,
                ])
                ->values()
                ->all();

            $analyzer = new ThreadConfigImageAnalyzer(Bootstrap::init()->get('logger'));
            $result = $analyzer->analyze($processed, $niches, $language);

            return $this->json($response, [
                'message' => 'Thread settings suggested',
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, ['error' => true, 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'rate limit') ? 429 : 502;
            return $this->json($response, ['error' => true, 'message' => $e->getMessage()], $status);
        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => true,
                'message' => 'Thread image analysis failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function analyzeHookImage(Request $request, Response $response): Response
    {
        $uploaded = $this->collectReferenceImages($request);

        if ($uploaded === []) {
            return $this->json($response, [
                'error' => true,
                'message' => 'reference_image is required',
            ], 422);
        }

        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $data = [];
        }

        $language = !empty($data['language']) ? strtolower(trim((string) $data['language'])) : 'bm';

        try {
            $processed = $this->imagePreprocessor->processUploads($uploaded, [
                'high_detail' => false,
            ]);

            $analyzer = new HookImageAnalyzer(Bootstrap::init()->get('logger'));
            $result = $analyzer->analyze($processed, $language);

            return $this->json($response, [
                'message' => 'Hook suggestions ready',
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, ['error' => true, 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'rate limit') ? 429 : 502;
            return $this->json($response, ['error' => true, 'message' => $e->getMessage()], $status);
        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => true,
                'message' => 'Hook image analysis failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function generate(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $uploaded = $this->collectReferenceImages($request);

        if ($uploaded !== []) {
            return $this->generateWithImages($request, $response, $userId, $uploaded);
        }

        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $data = [];
        }

        $config = $this->buildGenerationConfig($userId, $data);

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
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, ['error' => true, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => true,
                'message' => 'Generation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param  list<UploadedFileInterface>  $uploaded
     */
    private function generateWithImages(
        Request $request,
        Response $response,
        int $userId,
        array $uploaded
    ): Response {
        $data = $request->getParsedBody();
        if (!is_array($data)) {
            $data = [];
        }

        $highDetail = filter_var($data['high_detail'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $config = $this->buildGenerationConfig($userId, $data);
        $config['high_detail'] = $highDetail;

        if (($config['variations'] ?? 1) > 1) {
            return $this->json($response, [
                'error' => true,
                'message' => 'Variations are not supported with reference images. Use variations=1.',
            ], 422);
        }

        try {
            $processed = $this->imagePreprocessor->processUploads($uploaded, [
                'high_detail' => $highDetail,
            ]);

            $result = $this->generator->generateWithImages($config, $processed);

            return $this->json($response, [
                'message' => 'Content generated from image',
                'data' => $this->serializePost($result['post']),
                'image_analysis' => $result['image_analysis'],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, ['error' => true, 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            $status = str_contains(strtolower($e->getMessage()), 'rate limit') ? 429 : 502;
            return $this->json($response, ['error' => true, 'message' => $e->getMessage()], $status);
        } catch (\Exception $e) {
            return $this->json($response, [
                'error' => true,
                'message' => 'Image generation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /** @return array<string, mixed> */
    private function buildGenerationConfig(int $userId, array $data): array
    {
        return [
            'user_id' => $userId,
            'niche_id' => !empty($data['niche_id']) ? (int) $data['niche_id'] : null,
            'topic_id' => !empty($data['topic_id']) ? (int) $data['topic_id'] : null,
            'affiliate_link_id' => !empty($data['affiliate_link_id']) ? (int) $data['affiliate_link_id'] : null,
            'category' => $data['category'] ?? 'general',
            'tone' => !empty($data['tone']) ? $data['tone'] : null,
            'variations' => (int) ($data['variations'] ?? 1),
            'high_detail' => filter_var($data['high_detail'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'hook_instruction' => !empty($data['hook_instruction']) ? trim((string) $data['hook_instruction']) : null,
            'thread_length' => !empty($data['thread_length']) ? strtolower(trim((string) $data['thread_length'])) : null,
            'product_context' => !empty($data['product_context']) ? trim((string) $data['product_context']) : null,
            'language' => !empty($data['language']) ? strtolower(trim((string) $data['language'])) : 'bm',
        ];
    }

    /**
     * @return list<UploadedFileInterface>
     */
    private function collectReferenceImages(Request $request): array
    {
        $files = $request->getUploadedFiles();
        $collected = [];

        if (isset($files['reference_image']) && $files['reference_image'] instanceof UploadedFileInterface) {
            if ($files['reference_image']->getError() !== UPLOAD_ERR_NO_FILE) {
                $collected[] = $files['reference_image'];
            }
        }

        if (isset($files['reference_images']) && is_array($files['reference_images'])) {
            foreach ($files['reference_images'] as $file) {
                if ($file instanceof UploadedFileInterface && $file->getError() !== UPLOAD_ERR_NO_FILE) {
                    $collected[] = $file;
                }
            }
        }

        return $collected;
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

        if (isset($updates['content'])) {
            $humanizer = new Humanizer();
            $parsed = $humanizer->parseThreadReplies($updates['content']);
            $metadata = $post->metadata ?? [];

            if (count($parsed) > 0) {
                $metadata['replies'] = $parsed;
                $metadata['thread_format'] = count($parsed) > 1;
                $updates['metadata'] = $metadata;
                $updates['hook'] = $updates['hook'] ?? ($parsed[0] ?? $post->hook);
                $updates['cta'] = $updates['cta'] ?? ($parsed[array_key_last($parsed)] ?? $post->cta);
            } else {
                unset($metadata['replies']);
                $updates['metadata'] = $metadata;
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

        if (!in_array($post->status, ['draft', 'approved', 'scheduled'], true)) {
            return $this->json($response, [
                'error' => true,
                'message' => 'Hook image can only be added to draft, approved, or scheduled posts',
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

    /**
     * Revert an approved or scheduled post back to draft (cancels queued schedule).
     */
    public function unapprove(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $post = GeneratedPost::where('id', $args['id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        if (!in_array($post->status, ['approved', 'scheduled'], true)) {
            return $this->json($response, [
                'error' => true,
                'message' => 'Only approved or scheduled posts can be reverted to draft',
            ], 422);
        }

        $this->cancelQueuedScheduleForPost($post);
        $post->status = 'draft';
        $post->save();

        return $this->json($response, [
            'message' => 'Post reverted to draft',
            'data' => $this->serializePost($post->fresh()),
        ]);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $request->getAttribute('user_id');
        $body = $request->getParsedBody() ?? [];
        $query = $request->getQueryParams();
        $deleteFromThreads = filter_var(
            $body['delete_from_threads'] ?? $query['delete_from_threads'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $post = GeneratedPost::where('id', $args['id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        $threadsResult = null;

        if ($deleteFromThreads) {
            if ($post->status !== 'posted') {
                return $this->json($response, [
                    'error' => true,
                    'message' => 'Only published posts can be deleted from Threads',
                ], 422);
            }

            try {
                $threadsResult = $this->deletePostFromThreads($post, $userId);
            } catch (\Throwable $e) {
                return $this->json($response, [
                    'error' => true,
                    'message' => 'Threads deletion failed: ' . $e->getMessage(),
                ], 500);
            }

            if (!empty($threadsResult['errors'])) {
                return $this->json($response, [
                    'error' => true,
                    'message' => 'Some Threads posts could not be deleted',
                    'threads' => $threadsResult,
                ], 502);
            }
        }

        if (in_array($post->status, ['approved', 'scheduled'], true)) {
            $this->cancelQueuedScheduleForPost($post);
        }

        $filename = $post->metadata['hook_image']['filename'] ?? null;
        $this->hookImages->removeStoredFile(is_string($filename) ? $filename : null);
        $post->delete();

        return $this->json($response, [
            'message' => $deleteFromThreads
                ? 'Post deleted from AutoThreads and Threads'
                : 'Post deleted',
            'threads' => $threadsResult,
        ]);
    }

    private function cancelQueuedScheduleForPost(GeneratedPost $post): void
    {
        ScheduledPost::where('generated_post_id', $post->id)
            ->whereIn('status', ['queued', 'processing'])
            ->get()
            ->each(function (ScheduledPost $scheduled) {
                $scheduled->status = 'cancelled';
                $scheduled->save();
            });
    }

    /** @return array{deleted: list<string>, errors: list<array{id: string, error: string}>} */
    private function deletePostFromThreads(GeneratedPost $post, int $userId): array
    {
        $metadata = $post->metadata ?? [];
        $publish = $metadata['threads_publish'] ?? null;
        $postIds = $publish['post_ids'] ?? [];

        if (!is_array($postIds) || $postIds === []) {
            throw new \RuntimeException('No Threads post IDs found for this content');
        }

        $accountQuery = ThreadsAccount::where('user_id', $userId)->where('is_active', true);

        if (!empty($publish['account_id'])) {
            $accountQuery->where('id', (int) $publish['account_id']);
        }

        $account = $accountQuery->orderByDesc('created_at')->first();

        if (!$account) {
            throw new \RuntimeException('No connected Threads account found');
        }

        $client = new ThreadsClient();

        if (!$client->tokenHasScope($account->access_token, 'threads_delete')) {
            throw new \RuntimeException(
                'Your Threads token is missing threads_delete. Disconnect and connect again in Settings.'
            );
        }

        return $client->deleteThreadPosts($account, $postIds);
    }

    private function serializePost(GeneratedPost $post): array
    {
        $data = $post->toArray();
        $data['hook_image_url'] = $this->hookImages->resolvePublicUrl($post);

        $activeSchedule = ScheduledPost::where('generated_post_id', $post->id)
            ->whereIn('status', ['queued', 'processing'])
            ->orderByDesc('id')
            ->first();

        if ($activeSchedule) {
            $data['scheduled_post_id'] = $activeSchedule->id;
        }

        return $data;
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
