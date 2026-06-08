<?php

/**
 * AutoThreads API Routes
 * 
 * All API endpoints are prefixed with /api/v1
 * Protected routes require JWT authentication via AuthMiddleware
 */

use Slim\Routing\RouteCollectorProxy;
use AutoThreads\Middleware\AuthMiddleware;
use AutoThreads\Controllers\AuthController;
use AutoThreads\Controllers\DashboardController;
use AutoThreads\Controllers\NicheController;
use AutoThreads\Controllers\TopicController;
use AutoThreads\Controllers\ContentController;
use AutoThreads\Controllers\SchedulerController;
use AutoThreads\Controllers\AffiliateController;
use AutoThreads\Controllers\AdminController;
use AutoThreads\Controllers\AnalyticsController;
use AutoThreads\Middleware\AdminMiddleware;
use AutoThreads\Controllers\SettingsController;
use AutoThreads\Controllers\ThreadsController;
use AutoThreads\Controllers\MediaController;

// API root - friendly landing response so the base URL isn't a 404
$app->get('/', function ($request, $response) {
    $response->getBody()->write(json_encode([
        'name' => 'AutoThreads API',
        'version' => '1.0.0',
        'status' => 'running',
        'docs' => '/api/v1',
        'health' => '/health',
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Health check
$app->get('/health', function ($request, $response) {

    $response->getBody()->write(json_encode([
        'status' => 'ok',
        'version' => '1.0.0',
        'timestamp' => date('c'),
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Public hook images — Meta fetches these (no JWT). Must stay outside /api/v1.
$app->get('/media/{filename}', [MediaController::class, 'show']);

$app->group('/api/v1', function (RouteCollectorProxy $group) {

    // === PUBLIC ROUTES (No auth required) ===
    $group->post('/auth/register', [AuthController::class, 'register']);
    $group->post('/auth/login', [AuthController::class, 'login']);
    $group->post('/auth/refresh', [AuthController::class, 'refresh']);

    // Threads OAuth callback (Meta redirect — no JWT)
    $group->get('/threads/callback', [ThreadsController::class, 'callback']);

    // === PROTECTED ROUTES ===
    $group->group('', function (RouteCollectorProxy $protected) {

        // Auth
        $protected->post('/auth/logout', [AuthController::class, 'logout']);
        $protected->get('/auth/me', [AuthController::class, 'me']);

        // Dashboard
        $protected->get('/dashboard/stats', [DashboardController::class, 'stats']);
        $protected->get('/dashboard/recent', [DashboardController::class, 'recentActivity']);

        // Niches
        $protected->get('/niches', [NicheController::class, 'index']);
        $protected->post('/niches', [NicheController::class, 'store']);
        $protected->get('/niches/{id}', [NicheController::class, 'show']);
        $protected->put('/niches/{id}', [NicheController::class, 'update']);
        $protected->delete('/niches/{id}', [NicheController::class, 'destroy']);

        // Topics
        $protected->get('/topics', [TopicController::class, 'index']);
        $protected->post('/topics/generate', [TopicController::class, 'generate']);
        $protected->put('/topics/{id}/status', [TopicController::class, 'updateStatus']);
        $protected->delete('/topics/{id}', [TopicController::class, 'destroy']);

        // Content Generation
        $protected->get('/content', [ContentController::class, 'index']);
        $protected->get('/content/vision-settings', [ContentController::class, 'visionSettings']);
        $protected->post('/content/generate', [ContentController::class, 'generate']);
        $protected->post('/content/{id}/regenerate', [ContentController::class, 'regenerate']);
        $protected->put('/content/{id}', [ContentController::class, 'update']);
        $protected->post('/content/{id}/hook-image', [ContentController::class, 'uploadHookImage']);
        $protected->delete('/content/{id}/hook-image', [ContentController::class, 'deleteHookImage']);
        $protected->put('/content/{id}/approve', [ContentController::class, 'approve']);
        $protected->post('/content/{id}/publish', [ContentController::class, 'publish']);
        $protected->put('/content/{id}/reject', [ContentController::class, 'reject']);
        $protected->delete('/content/{id}', [ContentController::class, 'destroy']);

        // Scheduler
        $protected->get('/scheduler/settings', [SchedulerController::class, 'settings']);
        $protected->get('/scheduler/diagnostics', [SchedulerController::class, 'diagnostics']);
        $protected->get('/scheduler/worker-log', [SchedulerController::class, 'workerLog']);
        $protected->post('/scheduler/run-now', [SchedulerController::class, 'runNow']);
        $protected->get('/scheduler', [SchedulerController::class, 'index']);
        $protected->post('/scheduler', [SchedulerController::class, 'schedule']);
        $protected->put('/scheduler/{id}/cancel', [SchedulerController::class, 'cancel']);
        $protected->get('/scheduler/calendar', [SchedulerController::class, 'calendar']);

        // Affiliate Links
        $protected->get('/affiliates', [AffiliateController::class, 'index']);
        $protected->post('/affiliates', [AffiliateController::class, 'store']);
        $protected->put('/affiliates/{id}', [AffiliateController::class, 'update']);
        $protected->delete('/affiliates/{id}', [AffiliateController::class, 'destroy']);

        // Analytics
        $protected->get('/analytics/overview', [AnalyticsController::class, 'overview']);
        $protected->get('/analytics/trend', [AnalyticsController::class, 'engagementTrend']);
        $protected->get('/analytics/posts', [AnalyticsController::class, 'postPerformance']);
        $protected->get('/analytics/best-times', [AnalyticsController::class, 'bestTimes']);
        $protected->get('/analytics/best-hooks', [AnalyticsController::class, 'bestHooks']);
        $protected->post('/analytics/collect', [AnalyticsController::class, 'collect']);

        // Threads Integration
        $protected->get('/threads/connect', [ThreadsController::class, 'connect']);
        $protected->get('/threads/accounts', [ThreadsController::class, 'accounts']);
        $protected->delete('/threads/accounts/{id}', [ThreadsController::class, 'disconnect']);

        // Settings
        $protected->get('/settings', [SettingsController::class, 'index']);
        $protected->put('/settings', [SettingsController::class, 'update']);
        $protected->get('/settings/blacklist', [SettingsController::class, 'blacklist']);
        $protected->post('/settings/blacklist', [SettingsController::class, 'addBlacklistWord']);

        // Admin (requires role=admin)
        $protected->group('/admin', function (RouteCollectorProxy $admin) {
            $admin->get('/dashboard', [AdminController::class, 'dashboard']);
            $admin->get('/users', [AdminController::class, 'users']);
            $admin->get('/users/{id}', [AdminController::class, 'showUser']);
            $admin->put('/users/{id}', [AdminController::class, 'updateUser']);
            $admin->post('/users/{id}/suspend', [AdminController::class, 'suspendUser']);
            $admin->post('/users/{id}/ban', [AdminController::class, 'banUser']);
            $admin->post('/users/{id}/activate', [AdminController::class, 'activateUser']);
            $admin->post('/users/{id}/reset-quota', [AdminController::class, 'resetQuota']);
            $admin->post('/users/{id}/impersonate', [AdminController::class, 'impersonate']);
            $admin->get('/subscriptions', [AdminController::class, 'subscriptions']);
            $admin->put('/subscriptions/{id}', [AdminController::class, 'updateSubscription']);
            $admin->get('/queue', [AdminController::class, 'queue']);
            $admin->post('/queue/{id}/retry', [AdminController::class, 'retryQueueItem']);
            $admin->post('/queue/{id}/cancel', [AdminController::class, 'cancelQueueItem']);
            $admin->post('/queue/{id}/force-publish', [AdminController::class, 'forcePublish']);
            $admin->get('/worker', [AdminController::class, 'worker']);
            $admin->post('/worker/run', [AdminController::class, 'runWorker']);
            $admin->get('/ai-logs', [AdminController::class, 'aiLogs']);
            $admin->get('/system-logs', [AdminController::class, 'systemLogs']);
            $admin->get('/settings', [AdminController::class, 'getSettings']);
            $admin->put('/settings', [AdminController::class, 'updateSettings']);
            $admin->post('/announcements', [AdminController::class, 'saveAnnouncement']);
            $admin->delete('/announcements/{id}', [AdminController::class, 'deleteAnnouncement']);
            $admin->get('/threads-accounts', [AdminController::class, 'threadsAccounts']);
            $admin->post('/threads-accounts/{id}/disconnect', [AdminController::class, 'disconnectThreadsAccount']);
        })->add(new AdminMiddleware());

    })->add(new AuthMiddleware());
});
