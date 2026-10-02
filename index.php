<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$repo = new LessonRepository();
$serverLessons = $repo->listAll();
$seedIds = $repo->seedIds();
$srs = new SrsEngine();
$dueCount = count($srs->due());

layout_header('Home', array('nav' => 'home'));
?>
<div class="mx-auto max-w-3xl px-4 sm:px-6 pt-16 pb-8">
  <p class="text-sm font-medium tracking-wide uppercase text-slate-500 mb-4">Harness-first learning</p>
  <h1 class="text-4xl sm:text-5xl font-semibold tracking-tight text-slate-900 leading-[1.15] mb-6">
    Turn YouTube into interactive study modules
  </h1>
  <p class="text-lg text-slate-600 leading-relaxed mb-10 max-w-readable">
    You don’t paste URLs into a web form
    <span class="relative inline-flex align-middle ml-0.5">
      <button type="button"
        id="tokens-info-btn"
        class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-300 bg-white text-[11px] font-semibold italic text-slate-500 hover:border-slate-400 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-1"
        aria-expanded="false"
        aria-controls="tokens-info-panel"
        title="Why not paste a YouTube link here?">
        <span class="sr-only">Why you don’t paste a YouTube link in the app</span>
        i
      </button>
      <span id="tokens-info-panel"
        role="region"
        aria-labelledby="tokens-info-btn"
        hidden
        class="absolute left-0 top-full z-30 mt-2 w-72 sm:w-80 rounded-lg border border-slate-200 bg-white p-3 text-sm font-normal not-italic text-slate-600 leading-relaxed shadow-lg normal-case tracking-normal">
        <strong class="font-medium text-slate-800">No free tokens.</strong>
        This is a free service so you’ll have to use your own tokens. There are three ways:
        integrate your API key into the app, a prompt builder you copy into your own ChatGPT/Claude,
        or harness-first where you talk to the codebase to generate the artifacts you see in the rendered app.
        <strong class="font-medium text-slate-800">Decision: harness-first.</strong>
      </span>
    </span>.
    Open this project in an AI coding harness,
    drop a YouTube link in chat, and the local skill builds lesson JSON. Import that JSON
    into this browser (localStorage), study offline to the server, then optionally
    <strong class="font-medium text-slate-800">Sync to demo</strong> so any visitor of this app can open it.
  </p>

  <div class="flex flex-wrap gap-3 mb-10">
    <a href="#workflow" class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-5 py-3 text-sm font-medium text-white hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2">
      Open in AI harness → paste YouTube URL
    </a>
    <a href="review.php" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2">
      Review<?= $dueCount > 0 ? ' (' . (int) $dueCount . ' due)' : '' ?>
    </a>
    <button type="button" id="btn-sync-demo"
      class="inline-flex items-center justify-center rounded-lg border border-indigo-300 bg-indigo-50 px-5 py-3 text-sm font-medium text-indigo-900 hover:bg-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-700 focus-visible:ring-offset-2">
      Sync to demo
    </button>
  </div>
  <p id="sync-status" class="text-sm text-slate-500 mb-8 hidden" role="status" aria-live="polite"></p>
</div>

<section id="workflow" class="mx-auto max-w-3xl px-4 sm:px-6 pb-16">
  <details class="group rounded-xl border border-slate-200 bg-white open:shadow-sm">
    <summary class="cursor-pointer list-none flex items-center justify-between gap-3 px-5 py-4 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 rounded-xl">
      <span class="text-xl font-semibold text-slate-900">Six-step harness workflow</span>
      <span class="text-sm text-slate-500 group-open:hidden">Show</span>
      <span class="text-sm text-slate-500 hidden group-open:inline">Hide</span>
    </summary>
    <div class="px-5 pb-6 border-t border-slate-100 pt-5">
      <p class="text-sm text-slate-600 leading-relaxed mb-5">
        First download or clone the codebase locally from
        <a class="font-medium text-slate-900 underline underline-offset-2 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 rounded"
           href="https://github.com/Siphon880gh/youtube-learner"
           target="_blank"
           rel="noopener noreferrer">github.com/Siphon880gh/youtube-learner</a>
        — the harness needs a local project folder, not just this live page.
      </p>
      <ol class="space-y-5">
        <?php
        $steps = array(
          array('Open the project', 'Clone or download the github.com/Siphon880gh/youtube-learner repo first, then open that folder in Cursor, Claude Code, or another AI coding harness with local tool access.'),
          array('Paste a YouTube URL', 'In chat, give a video URL (or ID). Ask the agent to run the youtube-lesson skill.'),
          array('Skill builds lesson JSON', 'Transcript, dual TOCs, and diagrams → JSON matching docs/DATA_SCHEMA.md (may also write a file).'),
          array('Import in the browser', 'Paste or upload the lesson JSON below. It stays in localStorage until you sync.'),
          array('Study locally', 'Open the lesson, highlight, review SRS — local-only lessons use browser storage.'),
          array('Optional: Sync to demo', 'Enter the demo password to publish under gitignored data/demo/ so any visitor of this URL can open the lesson.'),
        );
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
    </div>
  </details>
</section>

<section id="import" class="mx-auto max-w-3xl px-4 sm:px-6 pb-12">
  <details class="group rounded-xl border border-slate-200 bg-white open:shadow-sm">
    <summary class="cursor-pointer list-none flex items-center justify-between gap-3 px-5 py-4 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 rounded-xl">
      <span class="text-xl font-semibold text-slate-900">Import lesson JSON</span>
      <span class="text-sm text-slate-500 group-open:hidden">Show</span>
      <span class="text-sm text-slate-500 hidden group-open:inline">Hide</span>
    </summary>
    <div class="px-5 pb-5 border-t border-slate-100 pt-4 space-y-4">
      <p class="text-sm text-slate-600">
        Secondary path (not a YouTube URL form). Paste JSON from the harness or upload a <code class="text-slate-800">.json</code> file.
        Stored in this browser until you Sync to demo. Cannot overwrite the seed id <code class="text-slate-800">sample-spaced-rep</code>.
      </p>
      <label class="block text-sm font-medium text-slate-700" for="import-file">Upload file</label>
      <input id="import-file" type="file" accept="application/json,.json" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-900 file:px-3 file:py-1.5 file:text-sm file:text-white">
      <label class="block text-sm font-medium text-slate-700" for="import-json">Or paste JSON</label>
      <textarea id="import-json" rows="6" placeholder='{ "video": { "id": "...", "title": "..." }, "transcript": [], ... }'
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900"></textarea>
      <div class="flex flex-wrap gap-2">
        <button type="button" id="btn-import-lesson" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900">
          Save to this browser
        </button>
        <span id="import-status" class="text-sm text-slate-500 self-center" role="status"></span>
      </div>
    </div>
  </details>
</section>

<section class="mx-auto max-w-3xl px-4 sm:px-6 pb-20">
  <div class="flex items-baseline justify-between gap-4 mb-6">
    <h2 class="text-xl font-semibold text-slate-900">Lessons</h2>
    <span class="text-sm text-slate-500" id="lesson-count-label"><?= count($serverLessons) ?> on server</span>
  </div>

  <ul class="space-y-3" id="lesson-list"
      data-server-lessons="<?= e(json_encode($serverLessons, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
      data-seed-ids="<?= e(json_encode($seedIds)) ?>">
    <!-- Filled by app.js: seed + localStorage + synced demo -->
    <li class="text-sm text-slate-500" id="lesson-list-placeholder">Loading lessons…</li>
  </ul>
</section>

<!-- Sync password dialog -->
<dialog id="sync-dialog" class="rounded-xl border border-slate-200 p-0 shadow-xl max-w-sm w-[calc(100%-2rem)] backdrop:bg-slate-900/40">
  <form id="sync-form" class="p-6 space-y-4" action="#">
    <h2 class="text-lg font-semibold text-slate-900">Sync to demo</h2>
    <p class="text-sm text-slate-600">Publishes browser-local lessons to gitignored <code class="text-xs">data/demo/</code> so any visitor of this app can open them. Password is checked on the server.</p>
    <label class="block text-sm font-medium text-slate-700" for="sync-password">Password</label>
    <input id="sync-password" name="password" type="password" autocomplete="current-password"
      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-700"
      required>
    <p id="sync-dialog-error" class="text-sm text-red-600 hidden" role="alert"></p>
    <div class="flex justify-end gap-2 pt-2">
      <button type="button" id="sync-cancel" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
      <button type="submit" id="sync-confirm" class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-800">Sync</button>
    </div>
  </form>
</dialog>

<div id="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 hidden rounded-lg bg-slate-900 text-white text-sm px-4 py-2 shadow-lg" role="status" aria-live="polite"></div>

<script>
  window.LEARNYT_HOME = {
    seedIds: <?= json_encode($seedIds) ?>,
    serverLessons: <?= json_encode($serverLessons, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
  };
  (function () {
    var btn = document.getElementById('tokens-info-btn');
    var panel = document.getElementById('tokens-info-panel');
    if (!btn || !panel) return;
    function setOpen(open) {
      if (open) {
        panel.removeAttribute('hidden');
        btn.setAttribute('aria-expanded', 'true');
      } else {
        panel.setAttribute('hidden', 'hidden');
        btn.setAttribute('aria-expanded', 'false');
      }
    }
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      setOpen(panel.hasAttribute('hidden'));
    });
    btn.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') setOpen(false);
    });
    document.addEventListener('click', function (e) {
      if (panel.hasAttribute('hidden')) return;
      if (btn.contains(e.target) || panel.contains(e.target)) return;
      setOpen(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') setOpen(false);
    });
  })();
</script>
<?php
layout_footer();
