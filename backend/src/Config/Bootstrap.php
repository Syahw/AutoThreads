<?php

namespace AutoThreads\Config;

use DI\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Predis\Client as Redis;

/**
 * Application Bootstrap
 * 
 * Initializes all core services: Database, Redis, Logger.
 * Returns a DI container for dependency injection throughout the app.
 */
class Bootstrap
{
    public static function init(): Container
    {
        $container = new Container();

        // Database (Eloquent ORM)
        $container->set('db', function () {
            $capsule = new Capsule();
            $capsule->addConnection([
                'driver'    => 'mysql',
                'host'      => $_ENV['DB_HOST'] ?? '127.0.0.1',
                'port'      => $_ENV['DB_PORT'] ?? '3306',
                'database'  => $_ENV['DB_DATABASE'] ?? 'autothreads',
                'username'  => $_ENV['DB_USERNAME'] ?? 'root',
                'password'  => $_ENV['DB_PASSWORD'] ?? '',
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix'    => '',
            ]);
            $capsule->setAsGlobal();
            $capsule->bootEloquent();
            return $capsule;
        });

        // Redis
        $container->set('redis', function () {
            return new Redis([
                'scheme' => 'tcp',
                'host'   => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
                'port'   => (int) ($_ENV['REDIS_PORT'] ?? 6379),
                'password' => $_ENV['REDIS_PASSWORD'] ?: null,
            ]);
        });

        // Logger
        $container->set('logger', function () {
            $logger = new Logger('autothreads');
            $logPath = __DIR__ . '/../../storage/logs/app.log';
            $logger->pushHandler(
                new RotatingFileHandler($logPath, 14, Logger::DEBUG)
            );
            return $logger;
        });

        // Boot database connection immediately
        $container->get('db');

        return $container;
    }
}
