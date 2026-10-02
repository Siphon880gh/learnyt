<?php
declare(strict_types=1);

/**
 * Bootstrap: paths, env, polyfills (PHP 7.4+), autoload-style requires.
 */

define('LEARNYT_ROOT', dirname(__DIR__));
define('LEARNYT_DATA', LEARNYT_ROOT . '/data');
define('LEARNYT_LESSONS', LEARNYT_DATA . '/lessons');
define('LEARNYT_HIGHLIGHTS', LEARNYT_DATA . '/highlights');
define('LEARNYT_SRS_FILE', LEARNYT_DATA . '/srs.json');
define('LEARNYT_DEMO', LEARNYT_DATA . '/demo');
define('LEARNYT_DEMO_LESSONS', LEARNYT_DEMO . '/lessons');
define('LEARNYT_DEMO_HIGHLIGHTS', LEARNYT_DEMO . '/highlights');
define('LEARNYT_DEMO_SRS_FILE', LEARNYT_DEMO . '/srs.json');
define('LEARNYT_SEED_IDS', 'sample-spaced-rep'); // comma-separated protected seed ids

// --- PHP 8.0 string helper polyfills (for PHP 7.4 / MAMP) ---
if (!function_exists('str_starts_with')) {
    /**
     * @param string $haystack
     * @param string $needle
     */
    function str_starts_with($haystack, $needle)
    {
        $haystack = (string) $haystack;
        $needle = (string) $needle;
        if ($needle === '') {
            return true;
        }
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    /**
     * @param string $haystack
     * @param string $needle
     */
    function str_ends_with($haystack, $needle)
    {
        $haystack = (string) $haystack;
        $needle = (string) $needle;
        if ($needle === '') {
            return true;
        }
        $len = strlen($needle);
        if ($len > strlen($haystack)) {
            return false;
        }
        return substr($haystack, -$len) === $needle;
    }
}
if (!function_exists('str_contains')) {
    /**
     * @param string $haystack
     * @param string $needle
     */
    function str_contains($haystack, $needle)
    {
        $haystack = (string) $haystack;
        $needle = (string) $needle;
        if ($needle === '') {
            return true;
        }
        return strpos($haystack, $needle) !== false;
    }
}

// Load .env if present (simple KEY=VALUE parser; no secrets to client)
$envFile = LEARNYT_ROOT . '/.env';
if (is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        $key = trim($parts[0]);
        $value = isset($parts[1]) ? trim($parts[1], " \t\"'") : '';
        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

/**
 * @param string $key
 * @param string|null $default
 * @return string|null
 */
function env($key, $default = null)
{
    $v = isset($_ENV[$key]) ? $_ENV[$key] : getenv($key);
    if ($v === false || $v === null || $v === '') {
        return $default;
    }
    return (string) $v;
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/LessonRepository.php';
require_once __DIR__ . '/HighlightRepository.php';
require_once __DIR__ . '/SrsEngine.php';
require_once __DIR__ . '/DemoSync.php';

// Ensure data dirs exist
foreach ([LEARNYT_DATA, LEARNYT_LESSONS, LEARNYT_HIGHLIGHTS, LEARNYT_DEMO, LEARNYT_DEMO_LESSONS, LEARNYT_DEMO_HIGHLIGHTS] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
