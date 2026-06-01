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
use AutoThreads\Controllers\AnalyticsController;
use AutoThreads\Controllers\SettingsController;
use AutoThreads\Controllers\ThreadsController;

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

$app->group('/api/v1', function (RouteCollectorProxy $group) {

    // === PUBLIC ROUTES (No auth required) ===
    $group->post('/auth/register', [AuthController::class, 'register']);
    $group->post('/auth/login', [AuthController::class, 'login']);
    $group->post('/auth/refresh', [AuthController::class, 'refresh']);

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
        $protected->post('/content/generate', [ContentController::class, 'generate']);
        $protected->post('/content/{id}/regenerate', [ContentController::class, 'regenerate']);
        $protected->put('/content/{id}', [ContentController::class, 'update']);
        $protected->put('/content/{id}/approve', [ContentController::class, 'approve']);
        $protected->put('/content/{id}/reject', [ContentController::class, 'reject']);
        $protected->delete('/content/{id}', [ContentController::class, 'destroy']);

        // Scheduler
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
        $protected->get('/analytics/posts', [AnalyticsController::class, 'postPerformance']);
        $protected->get('/analytics/best-times', [AnalyticsController::class, 'bestTimes']);
        $protected->get('/analytics/best-hooks', [AnalyticsController::class, 'bestHooks']);

        // Threads Integration
        $protected->get('/threads/connect', [ThreadsController::class, 'connect']);
        $protected->get('/threads/callback', [ThreadsController::class, 'callback']);
        $protected->get('/threads/accounts', [ThreadsController::class, 'accounts']);

        // Settings
        $protected->get('/settings', [SettingsController::class, 'index']);
        $protected->put('/settings', [SettingsController::class, 'update']);
        $protected->get('/settings/blacklist', [SettingsController::class, 'blacklist']);
        $protected->post('/settings/blacklist', [SettingsController::class, 'addBlacklistWord']);

    })->add(new AuthMiddleware());
});
