<?php

namespace AutoThreads\Services\Threads;

use GuzzleHttp\Client;
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
        $this->http = new Client(['timeout' => 30]);
    }

    /**
     * Get OAuth authorization URL
     */
    public function getAuthUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => $_ENV['THREADS_APP_ID'],
            'redirect_uri' => $_ENV['THREADS_REDIRECT_URI'],
            'scope' => 'threads_basic,threads_content_publish,threads_manage_insights',
            'response_type' => 'code',
            'state' => $state,
        ]);

        return "https://threads.net/oauth/authorize?{$params}";
    }

    /**
     * Exchange authorization code for access token
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
     * Publish a text post to Threads (two-step process)
     */
    public function publishPost(ThreadsAccount $account, string $text): array
    {
        // Step 1: Create media container
        $container = $this->createMediaContainer($account, $text);
        $containerId = $container['id'];

        // Wait for container to be ready (Threads requires this)
        sleep(2);

        // Step 2: Publish the container
        return $this->publishContainer($account, $containerId);
    }

    /**
     * Step 1: Create a media container
     */
    private function createMediaContainer(ThreadsAccount $account, string $text): array
    {
        $response = $this->http->post("{$this->baseUrl}/{$account->threads_user_id}/threads", [
            'form_params' => [
                'media_type' => 'TEXT',
                'text' => $text,
                'access_token' => $account->access_token,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Step 2: Publish the container
     */
    private function publishContainer(ThreadsAccount $account, string $containerId): array
    {
        $response = $this->http->post("{$this->baseUrl}/{$account->threads_user_id}/threads_publish", [
            'form_params' => [
                'creation_id' => $containerId,
                'access_token' => $account->access_token,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
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
