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
use AutoThreads\Services\Analytics\AnalyticsCollector;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$container = Bootstrap::init();
$logger = $container->get('logger');
$collector = new AnalyticsCollector();

$logger->info('Cron: collect_analytics started');

$summary = $collector->collect();

$logger->info(sprintf(
    'Cron: collect_analytics completed — processed=%d collected=%d failed=%d',
    $summary['processed'],
    $summary['collected'],
    $summary['failed']
));
