<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$repo = new LessonRepository();
$lessons = $repo->listAll();
$srs = new SrsEngine();
$dueCount = count($srs->due());

layout_header('Home', ['nav' => 'home']);
?>
<div class="mx-auto max-w-3xl px-4 sm:px-6 pt-16 pb-8">
  <p class="text-sm font-medium tracking-wide uppercase text-slate-500 mb-4">Harness-first learning</p>
  <h1 class="text-4xl sm:text-5xl font-semibold tracking-tight text-slate-900 leading-[1.15] mb-6">
    Turn YouTube into interactive study modules
  </h1>
  <p class="text-lg text-slate-600 leading-relaxed mb-10 max-w-readable">
    You don’t paste URLs into a web form. Open this project in an AI coding harness,
    drop a YouTube link in chat, and the local skill fetches the transcript, builds dual TOCs,
    and writes lesson JSON that this app renders—with highlights, deep links, and spaced repetition.
  </p>

  <div class="flex flex-wrap gap-3 mb-16">
    <a href="#workflow" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-5 py-3 text-sm font-medium text-white hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2">
      Open in AI harness → paste YouTube URL
    </a>
    <a href="review.php" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2">
      Review<?= $dueCount > 0 ? ' (' . (int) $dueCount . ' due)' : '' ?>
    </a>
  </div>
</div>

<section id="workflow" class="mx-auto max-w-3xl px-4 sm:px-6 pb-16">
  <h2 class="text-xl font-semibold text-slate-900 mb-6">Six-step harness workflow</h2>
  <ol class="space-y-5">
    <?php
    $steps = [
      ['Open the project', 'Open this folder in Cursor, Claude Code, or another AI coding harness with local tool access.'],
      ['Paste a YouTube URL', 'In chat, give a video URL (or ID). Ask the agent to run the youtube-lesson skill.'],
      ['Skill fetches transcript', 'The skill uses yt-dlp (or documented fallbacks) to get timestamped captions server-side / locally.'],
      ['Analyze & structure', 'It segments the talk, detects chapters/subjects/examples, and builds chronological + learning TOCs.'],
      ['Write lesson JSON', 'Output lands at data/lessons/{videoId}.json matching the app schema (see docs/DATA_SCHEMA.md).'],
      ['Study here', 'Refresh Lessons, open the module, highlight, deep-link, and save items to spaced repetition.'],
    ];
    foreach ($steps as $i => $step):
    ?>
    <li class="flex gap-4">
      <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-900 text-sm font-medium text-white"><?= $i + 1 ?></span>
      <div>
        <p class="font-medium text-slate-900"><?= e($step[0]) ?></p>
        <p class="text-slate-600 mt-1 leading-relaxed"><?= e($step[1]) ?></p>
      </div>
    </li>
    <?php endforeach; ?>
  </ol>
  <p class="mt-8 text-sm text-slate-500">
    Skill path: <code class="rounded bg-slate-100 px-1.5 py-0.5 text-slate-800">.agents/skills/youtube-lesson/SKILL.md</code>
    · Run app: <code class="rounded bg-slate-100 px-1.5 py-0.5 text-slate-800">php -S localhost:8080</code>
  </p>
</section>

<section class="mx-auto max-w-3xl px-4 sm:px-6 pb-20">
  <div class="flex items-baseline justify-between gap-4 mb-6">
    <h2 class="text-xl font-semibold text-slate-900">Lessons</h2>
    <span class="text-sm text-slate-500"><?= count($lessons) ?> module<?= count($lessons) === 1 ? '' : 's' ?></span>
  </div>

  <?php if (count($lessons) === 0): ?>
  <div class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center">
    <p class="text-slate-700 font-medium mb-2">No lessons yet</p>
    <p class="text-slate-500 text-sm max-w-md mx-auto">
      Generate one with the harness skill, or keep the included sample lesson under <code class="text-slate-700">data/lessons/</code>.
    </p>
  </div>
  <?php else: ?>
  <ul class="space-y-3">
    <?php foreach ($lessons as $lesson): ?>
    <li>
      <a href="<?= e(lesson_deep_link($lesson['id'])) ?>"
         class="group block rounded-xl border border-slate-200 bg-white p-5 hover:border-slate-300 hover:shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900">
        <div class="flex gap-4">
          <?php if (!empty($lesson['thumbnail'])): ?>
          <img src="<?= e($lesson['thumbnail']) ?>" alt="" class="hidden sm:block h-16 w-28 rounded-md object-cover bg-slate-100 shrink-0" loading="lazy" width="112" height="64">
          <?php else: ?>
          <div class="hidden sm:flex h-16 w-28 rounded-md bg-slate-100 items-center justify-center text-slate-400 text-xs shrink-0">No thumb</div>
          <?php endif; ?>
          <div class="min-w-0 flex-1">
            <h3 class="font-medium text-slate-900 group-hover:text-slate-700 truncate"><?= e($lesson['title']) ?></h3>
            <p class="mt-1 text-sm text-slate-500">
              <?= e($lesson['channel'] ?: 'Unknown channel') ?>
              <?php if (!empty($lesson['duration'])): ?>
              · <?= e(format_time((int) $lesson['duration'])) ?>
              <?php endif; ?>
            </p>
            <p class="mt-2 text-xs text-slate-400">
              <?= (int) $lesson['segmentCount'] ?> segments ·
              <?= (int) $lesson['chronoCount'] ?> chrono ·
              <?= (int) $lesson['learnCount'] ?> learn topics
            </p>
          </div>
        </div>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<?php
layout_footer();
