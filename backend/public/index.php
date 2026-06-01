<?php

/**
 * AutoThreads API Entry Point
 * 
 * All requests are routed through this file.
 * Slim Framework handles routing, middleware, and DI.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';


require __DIR__ . '/../src/helpers.php';


use Slim\Factory\AppFactory;
use AutoThreads\Config\Bootstrap;

// Load environment variables (safeLoad won't fatal if .env is absent)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Bootstrap application (DB, Redis, Logger)
$container = Bootstrap::init();
AppFactory::setContainer($container);

$app = AppFactory::create();


$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = rtrim($scriptDir, '/');
if ($basePath !== '' && $basePath !== '/') {
    $app->setBasePath($basePath);
}

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

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
