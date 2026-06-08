<?php

/**
 * Global helper functions.
 *
 * This project uses illuminate/database (Eloquent) standalone, without the
 * full Laravel framework. Some Laravel helpers like now() live in the
 * Foundation package, which is not installed here. We polyfill the ones the
 * codebase relies on so they behave the same way (returning a Carbon instance).
 */

use Illuminate\Support\Carbon;

if (!function_exists('now')) {
    /**
     * Create a new Carbon instance for the current time.
     *
     * @param  \DateTimeZone|string|null  $tz
     * @return \Illuminate\Support\Carbon
     */
    function now($tz = null): Carbon
    {
        return Carbon::now($tz);
    }
}

if (!function_exists('today')) {
    /**
     * Create a new Carbon instance for the current date.
     *
     * @param  \DateTimeZone|string|null  $tz
     * @return \Illuminate\Support\Carbon
     */
    function today($tz = null): Carbon
    {
        return Carbon::today($tz);
    }
}

if (!function_exists('guzzle_ssl_verify')) {
    /**
     * CA bundle path for Guzzle on Windows/WAMP, or true if none configured.
     */
    function guzzle_ssl_verify(): bool|string
    {
        foreach (['SSL_CAFILE', 'CURL_CA_BUNDLE'] as $key) {
            $envPath = $_ENV[$key] ?? null;
            if (is_string($envPath) && $envPath !== '' && file_exists($envPath)) {
                return $envPath;
            }
        }

        $caBundle = dirname(__DIR__) . '/cacert.pem';

        return file_exists($caBundle) ? $caBundle : true;
    }
}

if (!function_exists('password_algo')) {
    /**
     * Best available password hashing algorithm for this PHP build.
     */
    function password_algo(): int|string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return \PASSWORD_ARGON2ID;
        }

        return \PASSWORD_BCRYPT;
    }
}

if (!function_exists('hash_password')) {
    function hash_password(string $password): string
    {
        return password_hash($password, password_algo());
    }
}

if (!function_exists('password_hash_supported')) {
    function password_hash_supported(string $hash): bool
    {
        $info = password_get_info($hash);

        return ($info['algoName'] ?? 'unknown') !== 'unknown';
    }
}

if (!function_exists('public_media_base_url')) {
    /**
     * HTTPS base URL where Meta can fetch uploaded media (ngrok in local dev).
     *
     * When ngrok tunnels Vite on port 3000, set PUBLIC_MEDIA_BASE_URL (or FRONTEND_URL)
     * to that HTTPS origin and proxy /media in vite.config.js to Apache.
     */
    function public_media_base_url(): string
    {
        if (!empty($_ENV['PUBLIC_MEDIA_BASE_URL'])) {
            return rtrim((string) $_ENV['PUBLIC_MEDIA_BASE_URL'], '/');
        }

        $frontend = $_ENV['FRONTEND_URL'] ?? '';
        if (is_string($frontend) && str_starts_with($frontend, 'https://')) {
            return rtrim($frontend, '/');
        }

        $redirect = $_ENV['THREADS_REDIRECT_URI'] ?? '';
        if (is_string($redirect) && $redirect !== '') {
            $base = preg_replace('#/api/v1/threads/callback$#', '', $redirect);

            if (is_string($base) && $base !== '') {
                return rtrim($base, '/');
            }
        }

        return rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
    }
}
