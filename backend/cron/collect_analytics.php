<?php

/**
 * Cron Job: Collect Analytics
 * 
 * Run every 6 hours:
 * 0 */6 * * * php /path/to/backend/cron/collect_analytics.php
 * 
 * Fetches engagement metrics from Threads API for recent posts
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use AutoThreads\Config\Bootstrap;
use AutoThreads\Models\ScheduledPost;
use AutoThreads\Models\Analytics;
use AutoThreads\Services\Threads\ThreadsClient;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$container = Bootstrap::init();
$logger = $container->get('logger');
$threadsClient = new ThreadsClient();

$logger->info('Cron: collect_analytics started');

// Get posts from last 7 days that have been published
$recentPosts = ScheduledPost::where('status', 'posted')
    ->where('posted_at', '>=', now()->subDays(7))
    ->whereNotNull('threads_post_id')
    ->with('threadsAccount')
    ->get();

foreach ($recentPosts as $post) {
    try {
        $insights = $threadsClient->getPostInsights($post->threadsAccount, $post->threads_post_id);

        Analytics::updateOrCreate(
            ['threads_post_id' => $post->threads_post_id],
            [
                'user_id' => $post->user_id,
                'scheduled_post_id' => $post->id,
                'generated_post_id' => $post->generated_post_id,
                'impressions' => $insights['views'] ?? 0,
                'likes' => $insights['likes'] ?? 0,
                'comments' => $insights['replies'] ?? 0,
                'reposts' => $insights['reposts'] ?? 0,
                'quotes' => $insights['quotes'] ?? 0,
                'collected_at' => now(),
            ]
        );

        $logger->info("Collected analytics for post {$post->threads_post_id}");
    } catch (\Exception $e) {
        $logger->warning("Failed to collect analytics for {$post->threads_post_id}: {$e->getMessage()}");
    }

    usleep(500000); // 0.5s delay between API calls
}

$logger->info('Cron: collect_analytics completed');
