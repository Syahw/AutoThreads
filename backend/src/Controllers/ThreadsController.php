<?php

namespace AutoThreads\Controllers;

use AutoThreads\Models\ThreadsAccount;
use AutoThreads\Services\Threads\ThreadsClient;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response as SlimResponse;

class ThreadsController
{
    private ThreadsClient $threadsClient;

    public function __construct()
    {
        $this->threadsClient = new ThreadsClient();
    }

    /**
     * Start OAuth — returns Meta authorize URL (user must be logged in to AutoThreads)
     */
    public function connect(Request $request, Response $response): Response
    {
        $appId = $_ENV['THREADS_APP_ID'] ?? '';
        $redirectUri = $_ENV['THREADS_REDIRECT_URI'] ?? '';

        if (empty($appId) || empty($redirectUri)) {
            return $this->json($response, [
                'error' => true,
                'message' => 'Threads API not configured. Set THREADS_APP_ID and THREADS_REDIRECT_URI in backend/.env',
            ], 400);
        }

        if (!str_starts_with($redirectUri, 'https://')) {
            return $this->json($response, [
                'error' => true,
                'message' => 'THREADS_REDIRECT_URI must use HTTPS (Meta blocks http://). '
                    . 'For local dev use ngrok (https://xxx.ngrok-free.app/.../threads/callback) or WAMP SSL with a custom host like https://autothreads.local/...',
                'error_code' => 1349187,
            ], 400);
        }

        $userId = $request->getAttribute('user_id');
        $state = $this->createOAuthState((int) $userId);
        $authUrl = $this->threadsClient->getAuthUrl($state);

        return $this->json($response, ['data' => ['auth_url' => $authUrl]]);
    }

    /**
     * Meta OAuth callback (public — no JWT; state carries AutoThreads user id)
     */
    public function callback(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $frontendUrl = rtrim($_ENV['FRONTEND_URL'] ?? 'http://localhost:3000', '/');
        $settingsPath = '/settings';

        if (!empty($params['error'])) {
            return $this->redirect(
                $response,
                $this->settingsUrl($frontendUrl, $settingsPath, [
                    'threads' => 'error',
                    'message' => $params['error_description'] ?? $params['error'],
                ])
            );
        }

        $code = $params['code'] ?? null;
        $state = $params['state'] ?? null;

        if (!$code || !$state) {
            return $this->redirect(
                $response,
                $this->settingsUrl($frontendUrl, $settingsPath, [
                    'threads' => 'error',
                    'message' => 'Missing authorization code or state',
                ])
            );
        }

        try {
            $userId = $this->verifyOAuthState($state);
        } catch (\Exception $e) {
            return $this->redirect(
                $response,
                $this->settingsUrl($frontendUrl, $settingsPath, [
                    'threads' => 'error',
                    'message' => 'Invalid or expired connect session. Click Connect again.',
                ])
            );
        }

        try {
            $shortLived = $this->threadsClient->exchangeCode($code);

            if (empty($shortLived['access_token'])) {
                throw new \RuntimeException($shortLived['error_message'] ?? 'Token exchange failed');
            }

            $longLived = $this->threadsClient->exchangeLongLivedToken($shortLived['access_token']);

            if (empty($longLived['access_token'])) {
                throw new \RuntimeException($longLived['error_message'] ?? 'Long-lived token exchange failed');
            }

            $accessToken = $longLived['access_token'];
            $profile = $this->threadsClient->getProfileByToken($accessToken);
            $threadsUserId = (string) ($profile['id'] ?? $shortLived['user_id'] ?? '');

            if ($threadsUserId === '') {
                throw new \RuntimeException('Could not resolve Threads user id');
            }

            $tokenDebug = $this->threadsClient->debugToken($accessToken);
            $tokenScopes = $tokenDebug['scopes'] ?? [];
            $hasManageReplies = in_array('threads_manage_replies', $tokenScopes, true);

            $expiresIn = (int) ($longLived['expires_in'] ?? 5184000);
            $tokenExpiresAt = (new \DateTimeImmutable())->modify("+{$expiresIn} seconds");

            ThreadsAccount::updateOrCreate(
                [
                    'user_id' => $userId,
                    'threads_user_id' => $threadsUserId,
                ],
                [
                    'username' => $profile['username'] ?? 'threads_user',
                    'access_token' => $accessToken,
                    'token_expires_at' => $tokenExpiresAt->format('Y-m-d H:i:s'),
                    'is_active' => true,
                    'metadata' => [
                        'profile_picture_url' => $profile['threads_profile_picture_url'] ?? null,
                        'token_scopes' => $tokenScopes,
                        'can_publish_reply_chain' => $hasManageReplies,
                    ],
                ]
            );

            $query = [
                'threads' => $hasManageReplies ? 'connected' : 'connected_missing_scopes',
                'username' => $profile['username'] ?? '',
            ];

            if (!$hasManageReplies) {
                $query['message'] = 'Connected, but threads_manage_replies was not granted. '
                    . 'Add it in Meta app settings, then disconnect and connect again.';
            }

            return $this->redirect(
                $response,
                $this->settingsUrl($frontendUrl, $settingsPath, $query)
            );
        } catch (\Exception $e) {
            return $this->redirect(
                $response,
                $this->settingsUrl($frontendUrl, $settingsPath, [
                    'threads' => 'error',
                    'message' => $e->getMessage(),
                ])
            );
        }
    }

    public function accounts(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        $accounts = ThreadsAccount::where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (ThreadsAccount $account) {
                $public = $account->toPublicArray();

                try {
                    $scopes = $this->threadsClient->getTokenScopes($account->access_token);
                    $public['token_scopes'] = $scopes;
                    $public['can_publish_reply_chain'] = in_array('threads_manage_replies', $scopes, true);

                    $metadata = $account->metadata ?? [];
                    $metadata['token_scopes'] = $scopes;
                    $metadata['can_publish_reply_chain'] = $public['can_publish_reply_chain'];
                    $account->metadata = $metadata;
                    $account->save();
                } catch (\Exception) {
                    $public['token_scopes'] = $account->metadata['token_scopes'] ?? [];
                    $public['can_publish_reply_chain'] = $account->metadata['can_publish_reply_chain'] ?? false;
                    $public['scope_check_error'] = 'Could not refresh token scopes';
                }

                return $public;
            });

        return $this->json($response, ['data' => $accounts]);
    }

    public function disconnect(Request $request, Response $response, array $args): Response
    {
        $userId = $request->getAttribute('user_id');

        $account = ThreadsAccount::where('id', $args['id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        $account->is_active = false;
        $account->save();

        return $this->json($response, ['message' => 'Threads account disconnected']);
    }

    private function createOAuthState(int $userId): string
    {
        $now = time();

        return JWT::encode([
            'purpose' => 'threads_oauth',
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + 900,
        ], $_ENV['JWT_SECRET'], 'HS256');
    }

    private function verifyOAuthState(string $state): int
    {
        $decoded = JWT::decode($state, new Key($_ENV['JWT_SECRET'], 'HS256'));

        if (($decoded->purpose ?? '') !== 'threads_oauth') {
            throw new \RuntimeException('Invalid OAuth state');
        }

        return (int) $decoded->sub;
    }

    private function settingsUrl(string $frontendUrl, string $path, array $query): string
    {
        return $frontendUrl . $path . '?' . http_build_query($query);
    }

    private function redirect(Response $response, string $url): Response
    {
        return $response
            ->withStatus(302)
            ->withHeader('Location', $url);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
