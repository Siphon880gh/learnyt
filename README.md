# Learnyt

Harness-first **YouTube → interactive learning modules**.

You do **not** submit URLs through a web form. Open this project in an AI coding harness (Cursor, Claude Code, etc.), paste a YouTube URL in chat, and the local skill under `.agents/skills/youtube-lesson/` fetches the transcript, builds dual TOCs, and writes lesson JSON. This PHP app renders lessons with highlights, deep links, and spaced repetition.

## Quick start

```bash
cd /path/to/learnyt
cp .env.example .env          # optional tweaks
php -S localhost:8080
```

Open http://localhost:8080 — a sample lesson is included so the UI is demoable immediately.

Requirements: **PHP 8+**. Optional for the skill: **yt-dlp** on PATH (Homebrew: `brew install yt-dlp`).

## Harness workflow (6 steps)

1. Open this folder in an AI coding harness with local tools.
2. Paste a YouTube URL in chat; ask to run the **youtube-lesson** skill.
3. Skill fetches timestamped transcript (yt-dlp / `php api/transcript_helper.php`).
4. Skill segments content and builds chronological + learning TOCs.
5. Skill writes `data/lessons/{videoId}.json`.
6. Refresh Lessons → study, highlight, deep-link, save to SRS.

Skill instructions: [`.agents/skills/youtube-lesson/SKILL.md`](.agents/skills/youtube-lesson/SKILL.md)  
Schema: [`docs/DATA_SCHEMA.md`](docs/DATA_SCHEMA.md)

## App pages

| Page | Role |
|------|------|
| `index.php` | Landing, workflow, lesson list |
| `lesson.php?v=` | Dual TOC, video, transcript, highlights |
| `review.php` | SRS queue (Again / Hard / Good / Easy) |
| `api/*.php` | Highlights, SRS, lessons JSON APIs |

## Dual TOC

- **Chronological** — video order with timestamps and deep links  
- **Learning Structure** — study order (concepts, definitions, examples, …) linking back to source times  

Toggle on the lesson page (`?toc=chrono|learn`).

## Highlights & SRS

- Highlights: `data/highlights/{videoId}.json` via selection toolbar  
- SRS: `data/srs.json`, SM-2-inspired intervals  
- Local only; highlight/SRS write files are gitignored  

## Secrets

- Use `.env` for any server-side config (see `.env.example`)  
- **Never** put API keys in frontend JS, HTML, CSS, query params, or committed files  
- `.env` is gitignored  

## Transcript helper (CLI)

```bash
php api/transcript_helper.php VIDEO_ID
php api/transcript_helper.php --url 'https://www.youtube.com/watch?v=VIDEO_ID'
```

Web access to this script is blocked (CLI only).

## Design notes

Plain PHP 8, Tailwind CDN, vanilla JS. Zero Composer deps by default. Neutral slate palette, Inter/system fonts, readable max width.

## License

Use freely for local learning tools. YouTube content remains subject to YouTube’s terms; this app stores lesson JSON you generate locally.
