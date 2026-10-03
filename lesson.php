<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$repo = new LessonRepository();
$videoId = (string) ($_GET['v'] ?? '');
$lesson = $repo->find($videoId);

$isLocalShell = false;
if ($lesson === null) {
    // Not on disk (seed/demo). Render a shell; app.js hydrates from localStorage if present.
    $isLocalShell = true;
    $sanitized = $repo->sanitizeId($videoId);
    if ($sanitized === '') {
        http_response_code(404);
        layout_header('Lesson not found', array('nav' => 'home'));
        echo '<div class="mx-auto max-w-3xl px-4 sm:px-6 py-20 text-center">';
        echo '<h1 class="text-2xl font-semibold text-slate-900 mb-3">Invalid lesson id</h1>';
        echo '<a class="text-slate-900 underline" href="index.php">Back to lessons</a></div>';
        layout_footer();
        exit;
    }
    $videoId = $sanitized;
    $lesson = array(
        'video' => array('id' => $videoId, 'title' => 'Loading…', 'channel' => '', 'duration' => 0),
        'meta' => array('summary' => ''),
        'transcript' => array(),
        'chronologicalToc' => array(),
        'learningToc' => array(),
        'diagrams' => array(),
        '_source' => 'local',
    );
}

$video = $lesson['video'] ?? [];
$transcript = $lesson['transcript'] ?? [];
$chrono = $lesson['chronologicalToc'] ?? [];
$learn = $lesson['learningToc'] ?? [];
$diagrams = isset($lesson['diagrams']) && is_array($lesson['diagrams']) ? $lesson['diagrams'] : [];
$title = (string) ($video['title'] ?? $videoId);

$startT = parse_time_param(isset($_GET['t']) ? (string) $_GET['t'] : null);
$segParam = isset($_GET['seg']) ? (string) $_GET['seg'] : '';
$hlParam = isset($_GET['hl']) ? (string) $_GET['hl'] : '';
$dgParam = isset($_GET['dg']) ? (string) $_GET['dg'] : '';
$tocMode = (isset($_GET['toc']) && $_GET['toc'] === 'learn') ? 'learn' : 'chrono';
$navSection = isset($_GET['section']) ? (string) $_GET['section'] : 'overview';

$highlights = (new HighlightRepository())->listForVideo($videoId);
$embedStart = $startT > 0 ? $startT : 0;
$embedId = (string) ($video['embedId'] ?? $videoId);
// Prefer 11-char YouTube ids for iframe; sample lessons may set embedId separately
if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $embedId)) {
    $embedId = preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) ? $videoId : '';
}

$lessonSource = isset($lesson['_source']) ? (string) $lesson['_source'] : ($isLocalShell ? 'local' : (string) $repo->sourceOf($videoId));
if ($lessonSource === '') {
    $lessonSource = 'seed';
}

layout_header($title, array('nav' => 'home'));
?>
<div class="border-b border-slate-200 bg-white">
  <div class="mx-auto max-w-6xl px-4 sm:px-6 py-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div class="min-w-0">
        <p class="text-xs uppercase tracking-wide text-slate-500 mb-1">
          Lesson
          <?php if ($lessonSource === 'seed'): ?>
          <span class="ml-2 inline-flex items-center rounded-full bg-emerald-50 text-emerald-800 px-2 py-0.5 text-[10px] font-semibold tracking-wide normal-case">Sample</span>
          <?php elseif ($lessonSource === 'demo'): ?>
          <span class="ml-2 inline-flex items-center rounded-full bg-sky-50 text-sky-800 px-2 py-0.5 text-[10px] font-semibold tracking-wide normal-case">Synced demo</span>
          <?php elseif ($isLocalShell || $lessonSource === 'local'): ?>
          <span class="ml-2 inline-flex items-center rounded-full bg-amber-50 text-amber-900 px-2 py-0.5 text-[10px] font-semibold tracking-wide normal-case">On this browser</span>
          <?php endif; ?>
        </p>
        <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-900 truncate"><?= e($title) ?></h1>
        <p class="mt-1 text-sm text-slate-500">
          <?= e($video['channel'] ?? '') ?>
          <?php if (!empty($video['duration'])): ?> · <?= e(format_time((int) $video['duration'])) ?><?php endif; ?>
        </p>
      </div>
      <div class="flex flex-wrap gap-2 text-sm">
        <button type="button" data-copy-deeplink class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900">Copy deep link</button>
        <?php if (!empty($video['url'])): ?>
        <a href="<?= e($video['url']) ?>" target="_blank" rel="noopener" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900">Open on YouTube</a>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<!-- Sticks under site header (h-14) once scrolled past the title block -->
<div id="lesson-nav-bar" class="sticky top-14 z-30 border-b border-slate-200/80 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/90">
  <div class="mx-auto max-w-6xl px-4 sm:px-6">
    <nav class="flex flex-wrap gap-1 text-sm py-2" aria-label="Lesson sections" id="lesson-nav">
      <?php
      $navItems = array(
        'overview' => 'Overview',
        'chrono' => 'Chronological TOC',
        'learn' => 'Learning TOC',
        'transcript' => 'Transcript',
        'diagrams' => 'Diagrams',
        'highlights' => 'Highlights',
        'review' => 'Review',
      );
      foreach ($navItems as $key => $label):
        $href = $key === 'review' ? 'review.php?v=' . rawurlencode($videoId) : '#' . $key;
      ?>
      <a href="<?= e($href) ?>" data-section="<?= e($key) ?>"
         class="lesson-nav-link px-3 py-1.5 rounded-md text-slate-600 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</div>

<div class="mx-auto max-w-6xl px-4 sm:px-6 py-8 grid grid-cols-1 lg:grid-cols-12 gap-8"
     id="lesson-app" data-hydrate-local="<?= $isLocalShell ? '1' : '0' ?>"
     data-video-id="<?= e($videoId) ?>"
     data-start="<?= (int) $embedStart ?>"
     data-seg="<?= e($segParam) ?>"
     data-hl="<?= e($hlParam) ?>"
     data-dg="<?= e($dgParam) ?>"
     data-toc="<?= e($tocMode) ?>">

  <!-- Video + TOC column -->
  <aside class="lg:col-span-5 xl:col-span-4 space-y-6 lg:sticky lg:top-20 lg:self-start">
    <div class="video-frame w-full overflow-hidden rounded-xl bg-slate-900 shadow-sm relative" id="overview">
      <?php
        // Always render the iframe so localStorage hydrate / seek can set src later.
        $iframeSrc = $embedId !== '' ? youtube_embed_url($embedId, $embedStart) : 'about:blank';
      ?>
      <iframe
        id="yt-player"
        class="h-full w-full"
        src="<?= e($iframeSrc) ?>"
        data-embed-id="<?= e($embedId) ?>"
        title="YouTube video"
        referrerpolicy="strict-origin-when-cross-origin"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
        allowfullscreen
        loading="lazy"></iframe>
      <?php if ($embedId === ''): ?>
      <div id="yt-embed-placeholder" class="absolute inset-0 flex items-center justify-center text-slate-300 text-sm p-6 text-center bg-slate-900 pointer-events-none">
        No YouTube embed yet. It appears after the lesson loads (or set video.embedId / an 11-char video id).
      </div>
      <?php endif; ?>
      <button type="button"
        class="js-transcript-sync absolute top-2 right-2 z-10 rounded-md border border-white/25 bg-slate-900/85 px-2.5 py-1 text-xs font-medium text-white shadow backdrop-blur hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
        aria-pressed="false"
        title="When on, scroll the transcript to match the playing video">
        Sync
      </button>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4">
      <div class="flex items-center justify-between gap-2 mb-3">
        <h2 class="text-sm font-semibold text-slate-900">Table of contents</h2>
        <div class="inline-flex rounded-lg border border-slate-200 p-0.5 text-xs" role="tablist" aria-label="TOC mode">
          <button type="button" role="tab" data-toc-mode="chrono" aria-selected="<?= $tocMode === 'chrono' ? 'true' : 'false' ?>"
            class="toc-toggle px-2.5 py-1 rounded-md <?= $tocMode === 'chrono' ? 'bg-slate-900 text-white' : 'text-slate-600' ?>">Chronological</button>
          <button type="button" role="tab" data-toc-mode="learn" aria-selected="<?= $tocMode === 'learn' ? 'true' : 'false' ?>"
            class="toc-toggle px-2.5 py-1 rounded-md <?= $tocMode === 'learn' ? 'bg-slate-900 text-white' : 'text-slate-600' ?>">Learning Structure</button>
        </div>
      </div>

      <div id="toc-chrono" class="<?= $tocMode === 'chrono' ? '' : 'hidden' ?>" role="tabpanel">
        <ul class="space-y-1 max-h-[28rem] overflow-y-auto text-sm" id="chrono">
          <?php foreach ($chrono as $item): ?>
          <li>
            <a href="<?= e(lesson_deep_link($videoId, ['t' => (int) ($item['start'] ?? 0), 'toc' => 'chrono', 'seg' => $item['id'] ?? ''])) ?>"
               class="toc-link flex gap-2 rounded-md px-2 py-1.5 hover:bg-slate-50 text-slate-700"
               data-seek="<?= (float) ($item['start'] ?? 0) ?>"
               data-toc-id="<?= e((string) ($item['id'] ?? '')) ?>">
              <span class="tabular-nums text-slate-400 shrink-0 w-12"><?= e(format_time((int) ($item['start'] ?? 0))) ?></span>
              <span><?= e((string) ($item['title'] ?? 'Section')) ?></span>
            </a>
          </li>
          <?php endforeach; ?>
          <?php if (count($chrono) === 0): ?>
          <li class="text-slate-500 px-2 py-2">No chronological TOC.</li>
          <?php endif; ?>
        </ul>
      </div>

      <div id="toc-learn" class="<?= $tocMode === 'learn' ? '' : 'hidden' ?>" role="tabpanel">
        <ul class="space-y-3 max-h-[28rem] overflow-y-auto text-sm" id="learn">
          <?php
          $byCat = [];
          foreach ($learn as $item) {
              $cat = $item['category'] ?? 'other';
              $byCat[$cat][] = $item;
          }
          if (count($byCat) === 0) {
              echo '<li class="text-slate-500 px-2 py-2">No learning TOC.</li>';
          }
          foreach ($byCat as $cat => $items):
          ?>
          <li>
            <p class="px-2 text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1"><?= e(category_label((string) $cat)) ?></p>
            <ul class="space-y-1">
              <?php foreach ($items as $item):
                $src = $item['sources'][0] ?? [];
                $st = (float) ($src['start'] ?? 0);
              ?>
              <li>
                <a href="<?= e(lesson_deep_link($videoId, ['t' => (int) $st, 'toc' => 'learn', 'seg' => $item['id'] ?? ''])) ?>"
                   class="toc-link block rounded-md px-2 py-1.5 hover:bg-slate-50 text-slate-700"
                   data-seek="<?= $st ?>"
                   data-toc-id="<?= e((string) ($item['id'] ?? '')) ?>"
                   data-srs-title="<?= e((string) ($item['title'] ?? '')) ?>"
                   data-source-type="learn">
                  <?= e((string) ($item['title'] ?? 'Topic')) ?>
                  <span class="block text-xs text-slate-400 mt-0.5"><?= e(format_time((int) $st)) ?></span>
                </a>
              </li>
              <?php endforeach; ?>
            </ul>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </aside>

  <!-- Main reading column -->
  <div class="lg:col-span-7 xl:col-span-8 space-y-10 min-w-0">
    <?php if ($isLocalShell): ?>
    <div id="local-hydrate-status" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
      Looking for this lesson in browser storage…
    </div>
    <?php endif; ?>
    <section id="overview-panel" class="rounded-xl border border-slate-200 bg-white p-6 sm:p-8">
      <h2 class="text-lg font-semibold text-slate-900 mb-3">Overview</h2>
      <?php if (!empty($lesson['meta']['summary'])): ?>
      <p class="prose-lesson text-slate-700 leading-relaxed"><?= e((string) $lesson['meta']['summary']) ?></p>
      <?php else: ?>
      <p class="text-slate-600 leading-relaxed">
        Use the chronological TOC to follow the video, or switch to Learning Structure for study-oriented topics.
        Select any transcript passage for Save to SRS, Copy, Deep link, or Jump to video.
      </p>
      <?php endif; ?>
      <?php if (!empty($lesson['meta']['learningObjectives']) && is_array($lesson['meta']['learningObjectives'])): ?>
      <h3 class="mt-6 text-sm font-semibold text-slate-900">Learning objectives</h3>
      <ul class="mt-2 list-disc pl-5 space-y-1 text-slate-700">
        <?php foreach ($lesson['meta']['learningObjectives'] as $obj): ?>
        <li><?= e((string) $obj) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>

    <section id="transcript" class="relative">
      <div class="flex items-center justify-between gap-3 mb-4">
        <h2 class="text-lg font-semibold text-slate-900">Interactive transcript</h2>
        <div class="flex items-center gap-3">
          <span class="text-xs text-slate-400"><?= count($transcript) ?> segments</span>
          <button type="button"
            class="js-transcript-sync inline-flex items-center rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900"
            aria-pressed="false"
            title="When on, scroll the transcript to match the playing video">
            Sync
          </button>
        </div>
      </div>

      <!-- Selection toolbar (positioned by JS) -->
      <div id="selection-toolbar" class="items-center gap-1 rounded-lg border border-slate-200 bg-white px-1.5 py-1 shadow-lg text-xs" role="toolbar" aria-label="Selection actions">
        <button type="button" data-action="srs" class="rounded px-2 py-1.5 font-medium text-slate-800 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900">Save for SRS</button>
        <button type="button" data-action="highlight" class="rounded px-2 py-1.5 text-slate-700 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900">Highlight</button>
        <button type="button" data-action="copy" class="rounded px-2 py-1.5 text-slate-700 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900">Copy</button>
        <button type="button" data-action="deeplink" class="rounded px-2 py-1.5 text-slate-700 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900">Deep link</button>
        <button type="button" data-action="jump" class="rounded px-2 py-1.5 text-slate-700 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900">Jump to video</button>
      </div>

      <?php
        // Index diagrams by segmentId for inline transcript breaks
        $diagramsBySeg = [];
        $diagramsNoSeg = [];
        foreach ($diagrams as $dg) {
            $sid = isset($dg['segmentId']) ? (string) $dg['segmentId'] : '';
            if ($sid !== '') {
                if (!isset($diagramsBySeg[$sid])) {
                    $diagramsBySeg[$sid] = [];
                }
                $diagramsBySeg[$sid][] = $dg;
            } else {
                $diagramsNoSeg[] = $dg;
            }
        }
      ?>
      <div class="rounded-xl border border-slate-200 bg-white divide-y divide-slate-100" id="transcript-list">
        <?php foreach ($transcript as $seg):
          $sid = (string) ($seg['id'] ?? '');
          $st = (float) ($seg['start'] ?? 0);
          $en = (float) ($seg['end'] ?? $st);
        ?>
        <div class="transcript-seg px-4 py-3 sm:px-5"
             id="seg-<?= e($sid) ?>"
             data-seg-id="<?= e($sid) ?>"
             data-start="<?= $st ?>"
             data-end="<?= $en ?>">
          <button type="button" class="text-xs tabular-nums text-slate-400 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 rounded" data-seek="<?= $st ?>">
            <?= e(format_time((int) $st)) ?>
          </button>
          <p class="seg-text mt-1 text-slate-800 leading-relaxed select-text"><?= e((string) ($seg['text'] ?? '')) ?></p>
        </div>
        <?php
          if (!empty($diagramsBySeg[$sid])) {
              foreach ($diagramsBySeg[$sid] as $dg) {
                  echo '<div class="px-3 sm:px-4 py-2 bg-slate-50/80">';
                  render_diagram_card($dg, $videoId, ['context' => 'inline']);
                  echo '</div>';
              }
          }
        ?>
        <?php endforeach; ?>
        <?php if (count($transcript) === 0): ?>
        <p class="p-6 text-slate-500">No transcript segments in this lesson file.</p>
        <?php endif; ?>
      </div>
    </section>

    <section id="diagrams" class="scroll-mt-24">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-slate-900">Diagrams &amp; infographics</h2>
        <span class="text-xs text-slate-400"><?= count($diagrams) ?></span>
      </div>
      <?php if (count($diagrams) === 0): ?>
      <p class="text-sm text-slate-500 rounded-xl border border-dashed border-slate-300 bg-white p-6">
        No diagrams in this lesson. The youtube-lesson skill can add Mermaid/SVG visuals for processes, comparisons, and mechanisms.
      </p>
      <?php else: ?>
      <div class="space-y-4" id="diagram-gallery">
        <?php foreach ($diagrams as $dg): ?>
          <?php render_diagram_card($dg, $videoId, ['context' => 'gallery']); ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>

    <section id="highlights">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-slate-900">Highlights</h2>
        <span class="text-xs text-slate-400" id="highlight-count"><?= count($highlights) ?></span>
      </div>
      <ul class="space-y-3" id="highlight-list">
        <?php foreach ($highlights as $h): ?>
        <li class="rounded-xl border border-slate-200 bg-amber-50/40 p-4" data-hl-id="<?= e((string) ($h['id'] ?? '')) ?>">
          <p class="text-slate-800 leading-relaxed"><?= e((string) ($h['text'] ?? '')) ?></p>
          <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-500">
            <a class="underline hover:text-slate-800" href="<?= e(lesson_deep_link($videoId, ['t' => (int) ($h['start'] ?? 0), 'hl' => $h['id'] ?? ''])) ?>">
              <?= e(format_time((int) ($h['start'] ?? 0))) ?>
            </a>
            <button type="button" class="underline hover:text-slate-800" data-srs-from-hl="<?= e((string) ($h['id'] ?? '')) ?>">Save to SRS</button>
            <button type="button" class="underline hover:text-red-700" data-delete-hl="<?= e((string) ($h['id'] ?? '')) ?>">Delete</button>
          </div>
        </li>
        <?php endforeach; ?>
        <?php if (count($highlights) === 0): ?>
        <li class="text-slate-500 text-sm" id="highlights-empty">No highlights yet. Select transcript text and choose Highlight.</li>
        <?php endif; ?>
      </ul>
    </section>
  </div>
</div>

<!-- Toast -->
<div id="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 hidden rounded-lg bg-slate-900 text-white text-sm px-4 py-2 shadow-lg" role="status" aria-live="polite"></div>

<script>
  // Pass lesson bootstrap for app.js (no secrets)
  window.LEARNYT = {
    videoId: <?= json_encode($videoId, JSON_UNESCAPED_SLASHES) ?>,
    embedId: <?= json_encode($embedId, JSON_UNESCAPED_SLASHES) ?>,
    start: <?= (int) $embedStart ?>,
    seg: <?= json_encode($segParam) ?>,
    hl: <?= json_encode($hlParam) ?>,
    dg: <?= json_encode($dgParam) ?>,
    toc: <?= json_encode($tocMode) ?>,
    source: <?= json_encode($lessonSource) ?>,
    hydrateLocal: <?= $isLocalShell ? 'true' : 'false' ?>
  };
</script>
<!-- YouTube IFrame API -->
<script src="https://www.youtube.com/iframe_api"></script>
<!-- Mermaid for lesson diagrams (client-side only; no secrets) -->
<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
<?php
layout_footer();
