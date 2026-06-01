<?php

/**
 * Cron Job: Publish Scheduled Posts
 * 
 * Run every minute via crontab:
 * * * * * * php /path/to/backend/cron/publish_posts.php >> /path/to/storage/logs/cron.log 2>&1
 * 
 * This script:
 * 1. Finds posts that are due for publishing
 * 2. Publishes them via Threads API
 * 3. Logs results and handles retries
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use AutoThreads\Config\Bootstrap;
use AutoThreads\Models\ScheduledPost;
use AutoThreads\Models\PostingLog;
use AutoThreads\Services\Threads\ThreadsClient;
use AutoThreads\Services\Scheduler\PostScheduler;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Bootstrap
$container = Bootstrap::init();
$logger = $container->get('logger');

$logger->info('Cron: publish_posts started');

$scheduler = new PostScheduler();
$threadsClient = new ThreadsClient();

// Get due posts
$duePosts = $scheduler->getDuePosts();

if ($duePosts->isEmpty()) {
    $logger->info('Cron: No posts due for publishing');
    exit(0);
}

$logger->info("Cron: Found {$duePosts->count()} posts to publish");

foreach ($duePosts as $scheduledPost) {
    $startTime = microtime(true);

    try {
        // Mark as processing
        $scheduledPost->status = 'processing';
        $scheduledPost->save();

        $content = $scheduledPost->generatedPost->content;
        $account = $scheduledPost->threadsAccount;

        // Publish to Threads
        $result = $threadsClient->publishPost($account, $content);
        $responseTime = (int) ((microtime(true) - $startTime) * 1000);

        // Success
        $scheduledPost->status = 'posted';
        $scheduledPost->posted_at = now();
        $scheduledPost->threads_post_id = $result['id'] ?? null;
        $scheduledPost->save();

        // Update generated post status
        $scheduledPost->generatedPost->status = 'posted';
        $scheduledPost->generatedPost->save();

        // Log success
        PostingLog::create([
            'user_id' => $scheduledPost->user_id,
            'scheduled_post_id' => $scheduledPost->id,
            'threads_account_id' => $account->id,
            'action' => 'post',
            'status' => 'success',
            'threads_post_id' => $result['id'] ?? null,
            'response_time_ms' => $responseTime,
        ]);

        $logger->info("Published post #{$scheduledPost->id} successfully");

    } catch (\GuzzleHttp\Exception\ClientException $e) {
        $responseTime = (int) ((microtime(true) - $startTime) * 1000);
        $statusCode = $e->getResponse()->getStatusCode();
        $errorBody = $e->getResponse()->getBody()->getContents();

        $status = $statusCode === 429 ? 'rate_limited' : 'failed';

        PostingLog::create([
            'user_id' => $scheduledPost->user_id,
            'scheduled_post_id' => $scheduledPost->id,
            'threads_account_id' => $scheduledPost->threads_account_id,
            'action' => $scheduledPost->retry_count > 0 ? 'retry' : 'post',
            'status' => $status,
            'error_message' => $errorBody,
            'response_time_ms' => $responseTime,
        ]);

        $scheduler->markForRetry($scheduledPost, $errorBody);
        $logger->warning("Failed to publish post #{$scheduledPost->id}: {$errorBody}");

    } catch (\Exception $e) {
        $scheduler->markForRetry($scheduledPost, $e->getMessage());
        $logger->error("Error publishing post #{$scheduledPost->id}: {$e->getMessage()}");
    }

    // Rate limit: wait between posts
    sleep(3);
}

$logger->info('Cron: publish_posts completed');
