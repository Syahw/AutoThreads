<?php

/**
 * Cron Job: Auto-Schedule Posts for Tomorrow
 * 
 * Run daily at midnight:
 * 0 0 * * * php /path/to/backend/cron/auto_schedule.php
 * 
 * Automatically schedules approved posts for the next day
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use AutoThreads\Config\Bootstrap;
use AutoThreads\Models\User;
use AutoThreads\Models\ThreadsAccount;
use AutoThreads\Services\Scheduler\PostScheduler;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$container = Bootstrap::init();
$logger = $container->get('logger');
$scheduler = new PostScheduler();

$logger->info('Cron: auto_schedule started');

$tomorrow = date('Y-m-d', strtotime('+1 day'));

// Get all active users with connected accounts
$users = User::where('is_active', true)->get();

foreach ($users as $user) {
    $settings = $user->settings ?? [];
    if (!($settings['auto_schedule'] ?? true)) {
        continue;
    }

    $accounts = ThreadsAccount::where('user_id', $user->id)
        ->where('is_active', true)
        ->get();

    foreach ($accounts as $account) {
        try {
            $scheduled = $scheduler->autoScheduleDay($user->id, $account->id, $tomorrow);
            $count = count($scheduled);
            $logger->info("Auto-scheduled {$count} posts for user #{$user->id} on {$tomorrow}");
        } catch (\Exception $e) {
            $logger->error("Auto-schedule failed for user #{$user->id}: {$e->getMessage()}");
        }
    }
}

$logger->info('Cron: auto_schedule completed');
