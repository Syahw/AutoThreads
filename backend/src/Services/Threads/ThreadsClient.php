<?php

namespace AutoThreads\Services\Threads;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use AutoThreads\Models\ThreadsAccount;

/**
 * ThreadsClient - Threads API integration
 * 
 * Handles:
 * - OAuth2 authentication flow
 * - Publishing text posts
 * - Media upload support
 * - Fetching post insights
 * - Rate limit management
 * 
 * Threads API uses a two-step publishing process:
 * 1. Create a media container
 * 2. Publish the container
 */
class ThreadsClient
{
    private Client $http;
    private string $apiVersion;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiVersion = $_ENV['THREADS_API_VERSION'] ?? 'v1.0';
        $this->baseUrl = "https://graph.threads.net/{$this->apiVersion}";

        $this->http = new Client([
            'timeout' => 30,
            'verify' => guzzle_ssl_verify(),
        ]);
    }

    /**
     * Get OAuth authorization URL
     */
    public function getAuthUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => $_ENV['THREADS_APP_ID'],
            'redirect_uri' => $_ENV['THREADS_REDIRECT_URI'],
            'scope' => 'threads_basic,threads_content_publish,threads_manage_replies,threads_manage_insights,threads_delete',
            'response_type' => 'code',
            'state' => $state,
        ]);

        return "https://threads.net/oauth/authorize?{$params}";
    }

    /**
     * Exchange authorization code for a short-lived access token
     */
    public function exchangeCode(string $code): array
    {
        $response = $this->http->post('https://graph.threads.net/oauth/access_token', [
            'form_params' => [
                'client_id' => $_ENV['THREADS_APP_ID'],
                'client_secret' => $_ENV['THREADS_APP_SECRET'],
                'grant_type' => 'authorization_code',
                'redirect_uri' => $_ENV['THREADS_REDIRECT_URI'],
                'code' => $code,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Exchange a short-lived token for a long-lived token (60 days)
     */
    public function exchangeLongLivedToken(string $shortLivedToken): array
    {
        $response = $this->http->get('https://graph.threads.net/access_token', [
            'query' => [
                'grant_type' => 'th_exchange_token',
                'client_secret' => $_ENV['THREADS_APP_SECRET'],
                'access_token' => $shortLivedToken,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Inspect token metadata and granted scopes (Threads debug_token endpoint).
     */
    public function debugToken(string $accessToken): array
    {
        $response = $this->http->get("{$this->baseUrl}/debug_token", [
            'query' => [
                'access_token' => $accessToken,
                'input_token' => $accessToken,
            ],
        ]);

        $payload = json_decode($response->getBody()->getContents(), true);

        return $payload['data'] ?? [];
    }

    /**
     * @return string[]
     */
    public function getTokenScopes(string $accessToken): array
    {
        $debug = $this->debugToken($accessToken);

        return $debug['scopes'] ?? [];
    }

    public function tokenHasScope(string $accessToken, string $scope): bool
    {
        return in_array($scope, $this->getTokenScopes($accessToken), true);
    }

    public function assertCanPublishReplyChain(ThreadsAccount $account, int $replyCount): void
    {
        if ($replyCount <= 1) {
            return;
        }

        if ($this->tokenHasScope($account->access_token, 'threads_manage_replies')) {
            return;
        }

        $scopes = $this->getTokenScopes($account->access_token);

        throw new \RuntimeException(
            'Your Threads token does not include threads_manage_replies (required for reply 2+). '
            . 'Granted scopes: ' . (empty($scopes) ? 'none detected' : implode(', ', $scopes)) . '. '
            . 'In Meta Developer → Use cases → Threads API, add threads_manage_replies, then '
            . 'Disconnect and Connect again in Settings.'
        );
    }

    /**
     * Fetch profile for a token before the account row exists
     */
    public function getProfileByToken(string $accessToken): array
    {
        $response = $this->http->get("{$this->baseUrl}/me", [
            'query' => [
                'fields' => 'id,username,threads_profile_picture_url,threads_biography',
                'access_token' => $accessToken,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Publish a single text post to Threads
     */
    public function publishPost(ThreadsAccount $account, string $text): array
    {
        $result = $this->publishThread($account, [$text]);

        return [
            'id' => $result['root_post_id'],
            'thread' => $result,
        ];
    }

    /**
     * Publish a thread: first post is root, each following item is a reply in the chain
     *
     * @param  string[]  $texts
     * @param  string|null  $hookImageUrl  Public HTTPS URL for reply 1 image (optional)
     */
    public function publishThread(ThreadsAccount $account, array $texts, ?string $hookImageUrl = null): array
    {
        $texts = array_values(array_filter(array_map('trim', $texts)));

        if ($texts === []) {
            throw new \InvalidArgumentException('No reply texts to publish');
        }

        $this->assertCanPublishReplyChain($account, count($texts));

        $delay = (int) ($_ENV['THREADS_PUBLISH_DELAY_SECONDS'] ?? 5);
        $afterPublishDelay = (int) ($_ENV['THREADS_AFTER_PUBLISH_DELAY_SECONDS'] ?? 3);
        $postIds = [];

        foreach ($texts as $index => $text) {
            $replyNum = $index + 1;

            try {
                $text = $this->truncateForThreads($text);
                $parentId = $postIds[$index - 1] ?? null;

                $container = ($index === 0 && $hookImageUrl !== null && $hookImageUrl !== '')
                    ? $this->createImageContainer($account, $text, $hookImageUrl, $parentId)
                    : $this->createTextContainer($account, $text, $parentId);

                if (empty($container['id'])) {
                    throw new \RuntimeException(
                        $container['error_message'] ?? 'Failed to create media container for reply ' . $replyNum
                    );
                }

                sleep($delay);

                $published = $this->publishContainer($account, $container['id']);

                if (empty($published['id'])) {
                    throw new \RuntimeException(
                        $published['error_message'] ?? 'Failed to publish reply ' . $replyNum
                    );
                }

                $postIds[] = $published['id'];

                if ($index < count($texts) - 1) {
                    sleep($afterPublishDelay);
                }
            } catch (\Exception $e) {
                if ($postIds !== []) {
                    throw new \RuntimeException(
                        sprintf(
                            'Published %d of %d replies, then failed on reply %d: %s',
                            count($postIds),
                            count($texts),
                            $replyNum,
                            $e->getMessage()
                        ),
                        0,
                        $e
                    );
                }

                throw $e;
            }
        }

        return [
            'root_post_id' => $postIds[0],
            'post_ids' => $postIds,
            'published_count' => count($postIds),
        ];
    }

    /**
     * Step 1: Create a text media container (optional reply_to_id chains the thread)
     */
    private function createTextContainer(ThreadsAccount $account, string $text, ?string $replyToId = null): array
    {
        $params = [
            'media_type' => 'TEXT',
            'text' => $text,
            'access_token' => $account->access_token,
        ];

        if ($replyToId !== null) {
            $params['reply_to_id'] = $replyToId;
        }

        // Meta docs use /me/threads for reply containers with the user's access token
        return $this->postForm("{$this->baseUrl}/me/threads", $params, $replyToId !== null);
    }

    /**
     * Image container for the root hook (caption in text).
     */
    private function createImageContainer(
        ThreadsAccount $account,
        string $text,
        string $imageUrl,
        ?string $replyToId = null
    ): array {
        $params = [
            'media_type' => 'IMAGE',
            'image_url' => $imageUrl,
            'text' => $this->truncateForThreads($text),
            'access_token' => $account->access_token,
        ];

        if ($replyToId !== null) {
            $params['reply_to_id'] = $replyToId;
        }

        return $this->postForm("{$this->baseUrl}/me/threads", $params, $replyToId !== null);
    }

    /**
     * @param  array<string, string>  $params
     */
    private function postForm(string $url, array $params, bool $isReply = false): array
    {
        try {
            $response = $this->http->post($url, ['form_params' => $params]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (ClientException $e) {
            throw new \RuntimeException($this->formatApiError($e, $isReply), 0, $e);
        }
    }

    private function formatApiError(ClientException $e, bool $isReply = false): string
    {
        $body = $e->getResponse()->getBody()->getContents();
        $data = json_decode($body, true);
        $message = $data['error']['message'] ?? $e->getMessage();
        $code = $data['error']['code'] ?? null;

        if ($isReply && (int) $code === 10) {
            $message .= ' Your token is missing threads_manage_replies. In Meta app settings, add that permission, '
                . 'then disconnect and reconnect Threads in AutoThreads Settings.';
        }

        return $message;
    }

    private function truncateForThreads(string $text): string
    {
        $max = (int) ($_ENV['THREADS_MAX_TEXT_LENGTH'] ?? 500);

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max - 1) . '…';
    }

    /**
     * Step 2: Publish the container
     */
    private function publishContainer(ThreadsAccount $account, string $containerId): array
    {
        return $this->postForm("{$this->baseUrl}/{$account->threads_user_id}/threads_publish", [
            'creation_id' => $containerId,
            'access_token' => $account->access_token,
        ]);
    }

    /**
     * Delete a single published Threads media object.
     *
     * @see https://developers.facebook.com/docs/threads/posts/delete-posts/
     */
    public function deletePost(ThreadsAccount $account, string $mediaId): array
    {
        try {
            $response = $this->http->delete("{$this->baseUrl}/{$mediaId}", [
                'query' => ['access_token' => $account->access_token],
            ]);

            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (ClientException $e) {
            throw new \RuntimeException($this->formatApiError($e), 0, $e);
        }
    }

    /**
     * Delete a published reply chain (replies first, then root).
     *
     * @param  list<string>  $postIds
     * @return array{deleted: list<string>, errors: list<array{id: string, error: string}>}
     */
    public function deleteThreadPosts(ThreadsAccount $account, array $postIds): array
    {
        $deleted = [];
        $errors = [];

        foreach (array_reverse(array_values($postIds)) as $mediaId) {
            if ($mediaId === '') {
                continue;
            }

            try {
                $this->deletePost($account, $mediaId);
                $deleted[] = $mediaId;
                usleep(500_000);
            } catch (\Throwable $e) {
                $errors[] = ['id' => $mediaId, 'error' => $e->getMessage()];
            }
        }

        return ['deleted' => $deleted, 'errors' => $errors];
    }

    /**
     * Get insights for a specific post
     */
    public function getPostInsights(ThreadsAccount $account, string $postId): array
    {
        $metrics = 'views,likes,replies,reposts,quotes';

        $response = $this->http->get("{$this->baseUrl}/{$postId}/insights", [
            'query' => [
                'metric' => $metrics,
                'access_token' => $account->access_token,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $this->normalizeInsights($data);
    }

    /**
     * Get user profile info
     */
    public function getUserProfile(ThreadsAccount $account): array
    {
        $response = $this->http->get("{$this->baseUrl}/me", [
            'query' => [
                'fields' => 'id,username,threads_profile_picture_url,threads_biography',
                'access_token' => $account->access_token,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Refresh a long-lived token
     */
    public function refreshToken(ThreadsAccount $account): array
    {
        $response = $this->http->get("{$this->baseUrl}/refresh_access_token", [
            'query' => [
                'grant_type' => 'th_refresh_token',
                'access_token' => $account->access_token,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    private function normalizeInsights(array $data): array
    {
        $insights = [];
        foreach ($data['data'] ?? [] as $metric) {
            $insights[$metric['name']] = $metric['values'][0]['value'] ?? 0;
        }
        return $insights;
    }
}
