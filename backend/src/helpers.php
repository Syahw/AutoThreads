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
