<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$srs = new SrsEngine();
$buckets = $srs->buckets();
$filterV = isset($_GET['v']) ? (string) $_GET['v'] : '';
$itemId = isset($_GET['item']) ? (string) $_GET['item'] : '';

$queue = array_merge($buckets['overdue'], $buckets['dueToday']);
if ($filterV !== '') {
    $queue = array_values(array_filter($queue, static function ($i) use ($filterV) {
        return ($i['videoId'] ?? '') === $filterV;
    }));
}

$current = null;
if ($itemId !== '') {
    $current = $srs->find($itemId);
}
if ($current === null && count($queue) > 0) {
    $current = $queue[0];
}

layout_header('Review', ['nav' => 'review']);
?>
<div class="mx-auto max-w-3xl px-4 sm:px-6 pt-12 pb-20">
  <h1 class="text-3xl font-semibold tracking-tight text-slate-900 mb-2">Spaced repetition</h1>
  <p class="text-slate-600 mb-10">SM-2 inspired review. Rate cards After / Hard / Good / Easy to schedule the next interval.</p>

  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-10">
    <?php
    $stats = [
      ['Overdue', count($buckets['overdue']), 'text-red-700 bg-red-50 border-red-100'],
      ['Due today', count($buckets['dueToday']), 'text-amber-800 bg-amber-50 border-amber-100'],
      ['Upcoming', count($buckets['upcoming']), 'text-slate-700 bg-slate-50 border-slate-200'],
      ['Total', count($buckets['all']), 'text-slate-700 bg-white border-slate-200'],
    ];
    foreach ($stats as [$label, $n, $cls]):
    ?>
    <div class="rounded-xl border px-4 py-3 <?= e($cls) ?>">
      <p class="text-xs font-medium uppercase tracking-wide opacity-80"><?= e($label) ?></p>
      <p class="text-2xl font-semibold mt-1 tabular-nums"><?= (int) $n ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <section class="mb-14" id="review-card" data-item-id="<?= e((string) ($current['id'] ?? '')) ?>">
    <h2 class="text-lg font-semibold text-slate-900 mb-4">Review now</h2>
    <?php if ($current === null): ?>
    <div class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center">
      <p class="font-medium text-slate-800 mb-2">Nothing due</p>
      <p class="text-sm text-slate-500">Save highlights or learning TOC items to SRS from a lesson page.</p>
    </div>
    <?php else: ?>
    <article class="rounded-xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm">
      <p class="text-xs uppercase tracking-wide text-slate-400 mb-3">
        <?= e((string) ($current['sourceType'] ?? 'item')) ?>
        <?php if (!empty($current['videoId'])): ?>
        · <a class="underline hover:text-slate-700" href="<?= e(lesson_deep_link((string) $current['videoId'], ['t' => (int) ($current['start'] ?? 0)])) ?>">source</a>
        <?php endif; ?>
      </p>
      <p class="text-xl text-slate-900 leading-relaxed font-medium" id="srs-prompt"><?= e((string) ($current['text'] ?? '')) ?></p>

      <button type="button" id="srs-reveal" class="mt-6 text-sm font-medium text-slate-900 underline underline-offset-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 rounded">
        Reveal context
      </button>

      <div class="srs-answer mt-4 rounded-lg bg-slate-50 border border-slate-100 p-4 text-sm text-slate-600" id="srs-context" hidden>
        <?php if (!empty($current['note'])): ?>
        <p class="mb-2"><span class="font-medium text-slate-800">Note:</span> <?= e((string) $current['note']) ?></p>
        <?php endif; ?>
        <?php if (!empty($current['sourceTitle'])): ?>
        <p class="mb-2"><span class="font-medium text-slate-800">Topic:</span> <?= e((string) $current['sourceTitle']) ?></p>
        <?php endif; ?>
        <p>
          Interval: <?= (int) ($current['interval'] ?? 0) ?>d ·
          Ease: <?= e((string) ($current['ease'] ?? '2.5')) ?> ·
          Reviews: <?= (int) ($current['reviewCount'] ?? 0) ?>
        </p>
        <?php if (!empty($current['videoId'])): ?>
        <p class="mt-3">
          <a class="inline-flex items-center rounded-md bg-slate-900 text-white px-3 py-1.5 text-xs font-medium hover:bg-slate-800"
             href="<?= e(lesson_deep_link((string) $current['videoId'], ['t' => (int) ($current['start'] ?? 0)])) ?>">
            Jump to source at <?= e(format_time((int) ($current['start'] ?? 0))) ?>
          </a>
        </p>
        <?php endif; ?>
      </div>

      <div class="mt-8 grid grid-cols-2 sm:grid-cols-4 gap-2" id="srs-ratings">
        <?php
        $ratings = [
          ['again', 'Again', 'border-red-200 text-red-800 hover:bg-red-50'],
          ['hard', 'Hard', 'border-orange-200 text-orange-900 hover:bg-orange-50'],
          ['good', 'Good', 'border-emerald-200 text-emerald-900 hover:bg-emerald-50'],
          ['easy', 'Easy', 'border-sky-200 text-sky-900 hover:bg-sky-50'],
        ];
        foreach ($ratings as [$val, $label, $cls]):
        ?>
        <button type="button" data-rate="<?= e($val) ?>"
          class="rounded-lg border bg-white px-3 py-3 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 <?= e($cls) ?>">
          <?= e($label) ?>
        </button>
        <?php endforeach; ?>
      </div>
    </article>
    <?php endif; ?>
  </section>

  <section class="mb-12">
    <h2 class="text-lg font-semibold text-slate-900 mb-4">Recently added</h2>
    <?php if (count($buckets['recent']) === 0): ?>
    <p class="text-sm text-slate-500">No recent cards.</p>
    <?php else: ?>
    <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white">
      <?php foreach ($buckets['recent'] as $item): ?>
      <li class="px-4 py-3 flex gap-3 items-start justify-between">
        <div class="min-w-0">
          <p class="text-sm text-slate-800 truncate"><?= e((string) ($item['text'] ?? '')) ?></p>
          <p class="text-xs text-slate-400 mt-1">Next: <?= e(isset($item['nextReview']) ? date('M j, g:ia', strtotime((string) $item['nextReview'])) : '—') ?> PT</p>
        </div>
        <a class="text-xs text-slate-600 underline shrink-0" href="review.php?item=<?= e(rawurlencode((string) ($item['id'] ?? ''))) ?>">Open</a>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>

  <section>
    <h2 class="text-lg font-semibold text-slate-900 mb-4">History</h2>
    <?php if (count($buckets['history']) === 0): ?>
    <p class="text-sm text-slate-500">No reviews yet.</p>
    <?php else: ?>
    <ul class="space-y-2 text-sm">
      <?php foreach ($buckets['history'] as $ev): ?>
      <li class="flex gap-3 text-slate-600">
        <span class="text-slate-400 tabular-nums shrink-0 w-36"><?= e(isset($ev['at']) ? date('M j, g:ia', strtotime((string) $ev['at'])) : '') ?></span>
        <span class="font-medium text-slate-800 uppercase text-xs tracking-wide w-14 shrink-0 pt-0.5"><?= e((string) ($ev['rating'] ?? '')) ?></span>
        <span class="truncate"><?= e((string) ($ev['text'] ?? '')) ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
</div>

<div id="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 hidden rounded-lg bg-slate-900 text-white text-sm px-4 py-2 shadow-lg" role="status" aria-live="polite"></div>
<?php
layout_footer();
