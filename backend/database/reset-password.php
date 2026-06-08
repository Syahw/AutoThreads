<?php

declare(strict_types=1);

/**
 * Re-hash a user password with an algorithm supported on this PHP build.
 * Use when moving from WAMP (Argon2) to XAMPP (bcrypt-only).
 *
 * Usage: php database/reset-password.php user@example.com newpassword
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers.php';

use AutoThreads\Config\Bootstrap;
use AutoThreads\Models\User;

$email = $argv[1] ?? '';
$password = $argv[2] ?? '';

if ($email === '' || $password === '') {
    fwrite(STDERR, "Usage: php database/reset-password.php user@example.com newpassword\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

Bootstrap::init()->get('db');

$user = User::where('email', $email)->first();

if (!$user) {
    fwrite(STDERR, "No user found for: {$email}\n");
    exit(1);
}

$user->password_hash = hash_password($password);
$user->save();

echo "Password updated for {$email} using " . (password_algo() === \PASSWORD_BCRYPT ? 'bcrypt' : 'argon2id') . ".\n";
