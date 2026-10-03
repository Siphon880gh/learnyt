<?php
declare(strict_types=1);

/**
 * Shared helpers: escaping, JSON I/O, time formatting, deep links, CSRF-lite.
 * Typed for PHP 7.4+ (no union/mixed/never types).
 */

/**
 * @param string|null $s
 */
function e($s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @param string $path
 * @param mixed $default
 * @return mixed
 */
function json_read($path, $default = null)
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

/**
 * @param string $path
 * @param mixed $data
 */
function json_write($path, $data): bool
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

/**
 * @param int|float $seconds
 */
function format_time($seconds): string
{
    $s = (int) max(0, floor((float) $seconds));
    $h = intdiv($s, 3600);
    $m = intdiv($s % 3600, 60);
    $sec = $s % 60;
    if ($h > 0) {
        return sprintf('%d:%02d:%02d', $h, $m, $sec);
    }
    return sprintf('%d:%02d', $m, $sec);
}

/**
 * @param string|null $t
 */
function parse_time_param($t): int
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
    $q = array_filter($q, static function ($v) {
        return $v !== null && $v !== '';
    });
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

/**
 * Emit JSON and exit. (PHP 8.1 `never` avoided for 7.4.)
 *
 * @param mixed $data
 * @return void
 */
function json_response($data, int $status = 200)
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
    $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return;
    }
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
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

/**
 * @param string|null $cat
 */
function category_label($cat): string
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
    return isset($map[$cat ?? '']) ? $map[$cat ?? ''] : ($cat ? ucfirst($cat) : 'Topic');
}

/**
 * Render a lesson diagram card (gallery or inline transcript break).
 * PHP 7.4 compatible. Mermaid/SVG/image only — no secrets.
 *
 * @param array $diagram
 * @param string $videoId
 * @param array $opts  context: 'inline'|'gallery'
 */
function render_diagram_card(array $diagram, $videoId, array $opts = [])
{
    $context = isset($opts['context']) ? $opts['context'] : 'gallery';
    $id = (string) (isset($diagram['id']) ? $diagram['id'] : '');
    $title = (string) (isset($diagram['title']) ? $diagram['title'] : 'Diagram');
    $caption = isset($diagram['caption']) ? (string) $diagram['caption'] : '';
    $kind = (string) (isset($diagram['kind']) ? $diagram['kind'] : 'mermaid');
    $start = isset($diagram['start']) ? (float) $diagram['start'] : 0;
    $segmentId = isset($diagram['segmentId']) ? (string) $diagram['segmentId'] : '';
    $deep = lesson_deep_link($videoId, array(
        't' => (int) $start,
        'seg' => $segmentId,
        'dg' => $id,
    ));
    $wrapClass = $context === 'inline'
        ? 'diagram-card diagram-inline my-4 rounded-xl border border-indigo-200 bg-indigo-50/40 p-4 sm:p-5'
        : 'diagram-card rounded-xl border border-slate-200 bg-white p-4 sm:p-5';
    ?>
    <figure id="dg-<?= e($id) ?>" class="<?= e($wrapClass) ?>" data-diagram-id="<?= e($id) ?>" data-start="<?= e((string) $start) ?>" data-seg-id="<?= e($segmentId) ?>">
      <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
        <div class="min-w-0">
          <?php if ($context === 'inline'): ?>
          <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600 mb-1">Visual break</p>
          <?php endif; ?>
          <figcaption class="font-medium text-slate-900"><?= e($title) ?></figcaption>
          <?php if ($caption !== ''): ?>
          <p class="mt-1 text-sm text-slate-600"><?= e($caption) ?></p>
          <?php endif; ?>
        </div>
        <a href="<?= e($deep) ?>"
           class="shrink-0 text-xs font-medium text-slate-600 underline hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 rounded"
           data-seek="<?= e((string) $start) ?>"
           data-diagram-jump="<?= e($id) ?>">
          Jump to <?= e(format_time((int) $start)) ?>
        </a>
      </div>
      <div class="diagram-body overflow-x-auto rounded-lg bg-white border border-slate-100 p-3">
        <?php if ($kind === 'mermaid' && !empty($diagram['mermaid'])): ?>
        <pre class="mermaid text-sm leading-normal"><?= e((string) $diagram['mermaid']) ?></pre>
        <?php elseif ($kind === 'svg' && !empty($diagram['svg'])): ?>
        <div class="diagram-svg max-w-full text-slate-800"><?= sanitize_inline_svg((string) $diagram['svg']) ?></div>
        <?php elseif ($kind === 'image' && !empty($diagram['imageUrl'])): ?>
        <?php
          $src = (string) $diagram['imageUrl'];
          // Allow https URLs or local relative paths under assets/ or data/ only
          $ok = (strpos($src, 'https://') === 0)
            || (strpos($src, 'assets/') === 0)
            || (strpos($src, 'data/') === 0);
        ?>
        <?php if ($ok): ?>
        <img src="<?= e($src) ?>" alt="<?= e($title) ?>" class="max-w-full h-auto mx-auto" loading="lazy">
        <?php else: ?>
        <p class="text-sm text-slate-500">Image blocked (use https:// or local assets/ / data/ path).</p>
        <?php endif; ?>
        <?php else: ?>
        <p class="text-sm text-slate-500">No diagram payload for kind “<?= e($kind) ?>”.</p>
        <?php endif; ?>
      </div>
    </figure>
    <?php
}

/**
 * Allow a minimal inline SVG subset (no scripts/handlers).
 *
 * @param string $svg
 * @return string
 */
function sanitize_inline_svg($svg)
{
    $svg = (string) $svg;
    // Strip script tags and on* attributes
    $svg = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svg);
    $svg = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg);
    $svg = preg_replace('/javascript:/i', '', $svg);
    // Only keep if it looks like SVG
    if (stripos($svg, '<svg') === false) {
        return '<p class="text-sm text-slate-500">Invalid SVG.</p>';
    }
    return $svg;
}


/**
 * Compact Sync control: toggle + mode chevron menu.
 * Only the sticky lesson-nav instance is rendered.
 *
 * @param string $variant
 */
function render_transcript_sync_control($variant = 'sticky')
{
    $wrapExtra = ' transcript-sync-control--sticky';
    $btnExtra = 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50 focus-visible:ring-slate-900';
    $chevExtra = 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50 focus-visible:ring-slate-900';
    unset($variant);
    ?>
    <div class="transcript-sync-control<?= e($wrapExtra) ?>" data-sync-control>
      <button type="button"
        class="js-transcript-sync transcript-sync-toggle inline-flex items-center rounded-l-md border px-2.5 py-1 text-xs font-medium focus:outline-none focus-visible:ring-2 <?= e($btnExtra) ?>"
        aria-pressed="false"
        title="Follow the playing video in the transcript">
        Sync
      </button>
      <span class="transcript-sync-sep" aria-hidden="true"></span>
      <button type="button"
        class="js-transcript-sync-mode-btn transcript-sync-mode-btn inline-flex items-center justify-center rounded-r-md border border-l-0 px-1.5 py-1 focus:outline-none focus-visible:ring-2 <?= e($chevExtra) ?>"
        aria-haspopup="menu"
        aria-expanded="false"
        aria-label="Sync scroll mode"
        title="Scroll mode">
        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
      </button>
      <div class="transcript-sync-menu" role="menu" hidden>
        <button type="button" role="menuitemradio" class="transcript-sync-mode-option" data-sync-mode="none" aria-checked="false">No scrolling</button>
        <button type="button" role="menuitemradio" class="transcript-sync-mode-option" data-sync-mode="ease" aria-checked="true">Ease snap</button>
        <button type="button" role="menuitemradio" class="transcript-sync-mode-option" data-sync-mode="continuous" aria-checked="false">Continuous scrolling</button>
      </div>
    </div>
    <?php
}
