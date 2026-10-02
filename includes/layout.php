<?php
declare(strict_types=1);

/**
 * Shared HTML layout helpers.
 */

function layout_header(string $title, array $opts = []): void
{
    $app = env('APP_NAME', 'Learnyt');
    $full = $title === '' ? $app : $title . ' · ' . $app;
    $bodyClass = $opts['bodyClass'] ?? 'bg-slate-50 text-slate-800 antialiased';
    $active = $opts['nav'] ?? '';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="referrer" content="strict-origin-when-cross-origin">
  <title><?= e($full) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif'],
            prose: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
          },
          maxWidth: {
            readable: '42rem',
          }
        }
      }
    }
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="<?= e($bodyClass) ?>">
  <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:bg-white focus:px-3 focus:py-2 focus:ring-2 focus:ring-slate-900">Skip to content</a>
  <header class="border-b border-slate-200/80 bg-white/90 backdrop-blur sticky top-0 z-40">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 flex items-center justify-between h-14">
      <a href="index.php" class="font-semibold tracking-tight text-slate-900 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2 rounded">
        Learnyt
      </a>
      <nav class="flex items-center gap-1 text-sm" aria-label="Primary">
        <a href="index.php" class="px-3 py-1.5 rounded-md <?= $active === 'home' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?> focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900">Lessons</a>
        <a href="review.php" class="px-3 py-1.5 rounded-md <?= $active === 'review' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?> focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900">Review</a>
      </nav>
    </div>
  </header>
  <main id="main">
<?php
}

function layout_footer(array $opts = []): void
{
    $scripts = $opts['scripts'] ?? [];
    ?>
  </main>
  <footer class="mt-20 border-t border-slate-200 py-10">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 text-sm text-slate-500 flex flex-col sm:flex-row gap-2 sm:items-center sm:justify-between">
      <p>Harness-first YouTube → interactive learning. Local-only data.</p>
      <p class="flex flex-wrap gap-3">
        <a class="underline hover:text-slate-800" href="https://github.com/Siphon880gh/youtube-learner" target="_blank" rel="noopener noreferrer">GitHub</a>
        <a class="underline hover:text-slate-800" href="https://github.com/Siphon880gh/youtube-learner#readme" target="_blank" rel="noopener noreferrer">Docs</a>
      </p>
    </div>
  </footer>
  <script src="assets/js/storage.js" defer></script>
  <script src="assets/js/app.js" defer></script>
  <?php foreach ($scripts as $src): ?>
  <script src="<?= e($src) ?>" defer></script>
  <?php endforeach; ?>
</body>
</html>
<?php
}
