<?php

/**
 * AutoThreads API Entry Point
 * 
 * All requests are routed through this file.
 * Slim Framework handles routing, middleware, and DI.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use AutoThreads\Config\Bootstrap;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Bootstrap application (DB, Redis, Logger)
$container = Bootstrap::init();
AppFactory::setContainer($container);

$app = AppFactory::create();

// Global middleware
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// CORS middleware
$app->add(new AutoThreads\Middleware\CorsMiddleware());

// Error handling
$errorMiddleware = $app->addErrorMiddleware(
    (bool) ($_ENV['APP_DEBUG'] ?? false),
    true,
    true
);

// Register routes
require __DIR__ . '/../src/Routes/api.php';

$app->run();
