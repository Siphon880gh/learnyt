<?php
declare(strict_types=1);

/**
 * Optional CLI-friendly transcript helper for the local skill.
 * Usage (from project root):
 *   php api/transcript_helper.php VIDEO_ID
 *   php api/transcript_helper.php --url 'https://www.youtube.com/watch?v=...'
 *
 * Prefers yt-dlp. Writes nothing by default; prints JSON to stdout.
 * Secrets (if any) stay in .env — never echoed to clients.
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    // Block web access — this is a local skill helper only
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "CLI only.\n";
    exit(1);
}

function extract_video_id(string $input): string
{
    $input = trim($input);
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) {
        return $input;
    }
    if (preg_match('/(?:v=|\/youtu\.be\/|\/embed\/|\/shorts\/)([A-Za-z0-9_-]{11})/', $input, $m)) {
        return $m[1];
    }
    return '';
}

$args = array_slice($argv, 1);
$urlOrId = '';
foreach ($args as $i => $a) {
    if ($a === '--url' && isset($args[$i + 1])) {
        $urlOrId = $args[$i + 1];
        break;
    }
    if (!str_starts_with($a, '-')) {
        $urlOrId = $a;
        break;
    }
}

$videoId = extract_video_id($urlOrId);
if ($videoId === '') {
    fwrite(STDERR, "Usage: php api/transcript_helper.php VIDEO_ID|URL\n");
    exit(1);
}

$ytDlp = env('YT_DLP_PATH', 'yt-dlp');
$tmp = sys_get_temp_dir() . '/learnyt_' . $videoId . '_' . getmypid();
@mkdir($tmp, 0755, true);

$cmd = escapeshellcmd($ytDlp)
    . ' --skip-download --write-auto-sub --write-sub --sub-lang en --sub-format vtt/srt/best'
    . ' --convert-subs vtt'
    . ' -o ' . escapeshellarg($tmp . '/%(id)s')
    . ' -- ' . escapeshellarg('https://www.youtube.com/watch?v=' . $videoId)
    . ' 2>&1';

exec($cmd, $out, $code);

$vttFiles = glob($tmp . '/*.vtt') ?: [];
$segments = [];
if (count($vttFiles) > 0) {
    $vtt = file_get_contents($vttFiles[0]) ?: '';
    $segments = parse_vtt_segments($vtt);
}

// Cleanup
foreach (glob($tmp . '/*') ?: [] as $f) {
    @unlink($f);
}
@rmdir($tmp);

$result = [
    'videoId' => $videoId,
    'url' => 'https://www.youtube.com/watch?v=' . $videoId,
    'ytDlpExit' => $code,
    'segmentCount' => count($segments),
    'transcript' => $segments,
    'log' => array_slice($out, -20),
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
exit(count($segments) > 0 ? 0 : 2);

function parse_vtt_segments(string $vtt): array
{
    $lines = preg_split('/\R/', $vtt) ?: [];
    $segments = [];
    $i = 0;
    $n = count($lines);
    $idx = 0;
    while ($i < $n) {
        $line = trim($lines[$i]);
        if (preg_match('/(\d{2}:\d{2}:\d{2}\.\d{3})\s-->\s(\d{2}:\d{2}:\d{2}\.\d{3})/', $line, $m)) {
            $start = vtt_ts_to_seconds($m[1]);
            $end = vtt_ts_to_seconds($m[2]);
            $i++;
            $textParts = [];
            while ($i < $n && trim($lines[$i]) !== '') {
                $t = trim(preg_replace('/<[^>]+>/', '', $lines[$i]) ?? '');
                if ($t !== '' && !preg_match('/^\d+$/', $t)) {
                    $textParts[] = $t;
                }
                $i++;
            }
            $text = trim(implode(' ', $textParts));
            // Deduplicate overlapping auto-caption fragments
            if ($text !== '' && (count($segments) === 0 || $segments[count($segments) - 1]['text'] !== $text)) {
                $idx++;
                $segments[] = [
                    'id' => 's' . $idx,
                    'start' => $start,
                    'end' => $end,
                    'text' => $text,
                ];
            }
        }
        $i++;
    }
    return $segments;
}

function vtt_ts_to_seconds(string $ts): float
{
    if (!preg_match('/(\d{2}):(\d{2}):(\d{2})\.(\d{3})/', $ts, $m)) {
        return 0.0;
    }
    return ((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + (int) $m[3] + ((int) $m[4]) / 1000;
}
