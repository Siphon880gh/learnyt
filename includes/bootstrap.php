<?php
declare(strict_types=1);

/**
 * Bootstrap: paths, env, autoload-style requires.
 */

define('LEARNYT_ROOT', dirname(__DIR__));
define('LEARNYT_DATA', LEARNYT_ROOT . '/data');
define('LEARNYT_LESSONS', LEARNYT_DATA . '/lessons');
define('LEARNYT_HIGHLIGHTS', LEARNYT_DATA . '/highlights');
define('LEARNYT_SRS_FILE', LEARNYT_DATA . '/srs.json');

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
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

function env(string $key, ?string $default = null): ?string
{
    $v = $_ENV[$key] ?? getenv($key);
    if ($v === false || $v === null || $v === '') {
        return $default;
    }
    return (string) $v;
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/LessonRepository.php';
require_once __DIR__ . '/HighlightRepository.php';
require_once __DIR__ . '/SrsEngine.php';

// Ensure data dirs exist
foreach ([LEARNYT_DATA, LEARNYT_LESSONS, LEARNYT_HIGHLIGHTS] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
