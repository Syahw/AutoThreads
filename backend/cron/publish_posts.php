<?php

/**
 * Cron Job: Publish Scheduled Posts
 *
 * Run every minute via Task Scheduler (Windows) or crontab (Linux):
 *   backend/cron/run_publish.bat
 *
 * Logs: backend/storage/logs/cron-publish.log
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use AutoThreads\Config\Bootstrap;
use AutoThreads\Services\Scheduler\CronLogger;
use AutoThreads\Services\Scheduler\CronPublishWorker;
use AutoThreads\Services\Scheduler\PostScheduler;
use Carbon\Carbon;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

Bootstrap::init();

$cronLog = new CronLogger();
$scheduler = new PostScheduler();
$worker = new CronPublishWorker($scheduler);

$cronLog->line('=== publish_posts started ===');
$cronLog->line('PHP: ' . PHP_VERSION . ' · SAPI: ' . PHP_SAPI);
$cronLog->line('Timezone: ' . date_default_timezone_get());
$cronLog->line('Now: ' . Carbon::now($scheduler->getTimezone())->format('Y-m-d H:i:s'));

try {
    $summary = $worker->processDuePosts();

    $cronLog->line(sprintf(
        'Done: processed=%d published=%d failed=%d',
        $summary['processed'],
        $summary['published'],
        $summary['failed']
    ));

    foreach ($summary['details'] as $detail) {
        $cronLog->line(sprintf(
            '  #%s @ %s → %s: %s',
            $detail['scheduled_post_id'],
            $detail['scheduled_at'] ?? '?',
            $detail['status'],
            mb_substr($detail['message'] ?? '', 0, 200)
        ));
    }

    if ($summary['processed'] === 0) {
        $cronLog->line('No posts were due (queued with scheduled_at <= now).');
    }
} catch (\Throwable $e) {
    $cronLog->line('FATAL: ' . $e->getMessage());
    $cronLog->line($e->getFile() . ':' . $e->getLine());
    exit(1);
}

$cronLog->line('=== publish_posts completed ===');
exit(0);
