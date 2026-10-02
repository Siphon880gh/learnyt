<?php
declare(strict_types=1);

/**
 * Shared helpers: escaping, JSON I/O, time formatting, deep links, CSRF-lite.
 */

function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_read(string $path, mixed $default = null): mixed
{
    if (!is_readable($path)) {
        return $default;
    }
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return $default;
    }
    $data = json_decode($raw, true);
    return json_last_error() === JSON_ERROR_NONE ? $data : $default;
}

function json_write(string $path, mixed $data): bool
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    return file_put_contents($path, $json . "\n", LOCK_EX) !== false;
}

function format_time(int|float $seconds): string
{
    $s = (int) max(0, floor($seconds));
    $h = intdiv($s, 3600);
    $m = intdiv($s % 3600, 60);
    $sec = $s % 60;
    if ($h > 0) {
        return sprintf('%d:%02d:%02d', $h, $m, $sec);
    }
    return sprintf('%d:%02d', $m, $sec);
}

function parse_time_param(?string $t): int
{
    if ($t === null || $t === '') {
        return 0;
    }
    if (ctype_digit($t)) {
        return (int) $t;
    }
    // Support 1h2m3s or 1:02:03
    if (preg_match('/^(\d+):(\d{2})(?::(\d{2}))?$/', $t, $m)) {
        if (isset($m[3])) {
            return ((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + (int) $m[3];
        }
        return ((int) $m[1]) * 60 + (int) $m[2];
    }
    $total = 0;
    if (preg_match('/(\d+)h/', $t, $m)) {
        $total += (int) $m[1] * 3600;
    }
    if (preg_match('/(\d+)m/', $t, $m)) {
        $total += (int) $m[1] * 60;
    }
    if (preg_match('/(\d+)s/', $t, $m)) {
        $total += (int) $m[1];
    }
    return $total;
}

function youtube_embed_url(string $videoId, int $start = 0): string
{
    $q = 'enablejsapi=1&rel=0&modestbranding=1';
    if ($start > 0) {
        $q .= '&start=' . $start;
    }
    return 'https://www.youtube.com/embed/' . rawurlencode($videoId) . '?' . $q;
}

function lesson_deep_link(string $videoId, array $params = []): string
{
    $q = array_merge(['v' => $videoId], $params);
    // Strip empties and secrets-like keys
    unset($q['key'], $q['token'], $q['api_key'], $q['secret']);
    $q = array_filter($q, static fn($v) => $v !== null && $v !== '');
    return 'lesson.php?' . http_build_query($q);
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ensure_same_origin(): void
{
    // Lightweight same-origin check for mutating APIs
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return;
    }
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $ok = false;
    if ($origin !== '') {
        $oh = parse_url($origin, PHP_URL_HOST);
        $op = parse_url($origin, PHP_URL_PORT);
        $originHost = $oh . ($op ? ':' . $op : '');
        $ok = strcasecmp((string) $originHost, $host) === 0;
    } elseif ($referer !== '') {
        $rh = parse_url($referer, PHP_URL_HOST);
        $rp = parse_url($referer, PHP_URL_PORT);
        $refHost = $rh . ($rp ? ':' . $rp : '');
        $ok = strcasecmp((string) $refHost, $host) === 0;
    } else {
        // Allow CLI / same-host tools without Origin
        $ok = true;
    }
    if (!$ok) {
        json_response(['error' => 'Forbidden: cross-origin request blocked'], 403);
    }
}

function uuid_v4(): string
{
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

function category_label(?string $cat): string
{
    $map = [
        'concept' => 'Core concepts',
        'definition' => 'Definitions',
        'prerequisite' => 'Prerequisites',
        'argument' => 'Arguments',
        'mechanism' => 'Mechanisms',
        'example' => 'Examples',
        'application' => 'Applications',
        'mistake' => 'Common mistakes',
        'takeaway' => 'Takeaways',
        'other' => 'Other',
    ];
    return $map[$cat ?? ''] ?? ($cat ? ucfirst($cat) : 'Topic');
}
