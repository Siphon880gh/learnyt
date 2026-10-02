# Learnyt (youtube-learner)

![Last Commit](https://img.shields.io/github/last-commit/Siphon880gh/youtube-learner/main)
<a target="_blank" href="https://github.com/Siphon880gh" rel="nofollow"><img src="https://img.shields.io/badge/GitHub--blue?style=social&logo=GitHub" alt="Github" data-canonical-src="https://img.shields.io/badge/GitHub--blue?style=social&logo=GitHub" style="max-width:8.5ch;"></a>
<a target="_blank" href="https://www.linkedin.com/in/weng-fung/" rel="nofollow"><img src="https://img.shields.io/badge/LinkedIn-blue?style=flat&logo=linkedin&labelColor=blue" alt="Linked-In" data-canonical-src="https://img.shields.io/badge/LinkedIn-blue?style=flat&amp;logo=linkedin&amp;labelColor=blue" style="max-width:10ch;"></a>
<a target="_blank" href="https://www.youtube.com/@WengTeachesCode/" rel="nofollow"><img src="https://img.shields.io/badge/Youtube-red?style=flat&logo=youtube&labelColor=red" alt="Youtube" data-canonical-src="https://img.shields.io/badge/Youtube-red?style=flat&amp;logo=youtube&amp;labelColor=red" style="max-width:10ch;"></a>

By Weng (Weng Fei Fung). Generate transcripts, lessons, spaced repetition, infographics/diagrams for any Youtube video where learning is important.

Harness-first **YouTube → interactive learning modules**.

You do **not** submit URLs through a web form. Open this project in an AI coding harness (Cursor, Claude Code, etc.), paste a YouTube URL in chat, and the local skill under `.agents/skills/youtube-lesson/` fetches the transcript, builds dual TOCs, diagrams/infographics, and writes lesson JSON. This PHP app renders lessons with highlights, deep links, visual breaks, and spaced repetition.

## Quick start

```bash
cd /path/to/learnyt
cp .env.example .env          # optional tweaks
php -S localhost:8080
```

Open http://localhost:8080 — a sample lesson is included so the UI is demoable immediately.

Requirements: **PHP 7.4+** (works with MAMP 7.4; polyfills for PHP 8 string helpers). Optional for the skill: **yt-dlp** on PATH (Homebrew: `brew install yt-dlp`).


## Dual storage (browser → Sync to demo)

1. **Seed** — `data/lessons/sample-spaced-rep.json` is committed and always listed (badge: Sample).
2. **Import** — paste/upload lesson JSON on the home page into **localStorage** (`learnyt_user_lessons_v1`). Badge: On this browser. Cannot overwrite the seed id.
3. **Sync to demo** — button prompts for a password. PHP validates `SYNC_DEMO_PASSWORD` from `.env` (local demo password: **`go`**). On success, writes gitignored `data/demo/lessons/{id}.json` (plus highlights/SRS under `data/demo/`). Badge: Synced demo. **Any visitor of this app URL can open synced lessons.** Wrong password: no files written.
4. Seed folder `data/lessons/` is never written by sync.

Set in `.env` (gitignored):

```
SYNC_DEMO_PASSWORD=go
```

## Harness workflow (6 steps)

1. Open this folder in an AI coding harness with local tools.
2. Paste a YouTube URL in chat; ask to run the **youtube-lesson** skill.
3. Skill fetches timestamped transcript (yt-dlp / `php api/transcript_helper.php`).
4. Skill segments content, builds chronological + learning TOCs, and adds Mermaid/SVG diagrams where visuals help.
5. Skill writes `data/lessons/{videoId}.json`.
6. Refresh Lessons → study, highlight, deep-link, browse diagrams, save to SRS.

Skill instructions: [`.agents/skills/youtube-lesson/SKILL.md`](.agents/skills/youtube-lesson/SKILL.md)  
Schema: [`docs/DATA_SCHEMA.md`](docs/DATA_SCHEMA.md)

## App pages

| Page | Role |
|------|------|
| `index.php` | Landing, workflow, lesson list |
| `lesson.php?v=` | Dual TOC, video, transcript, diagrams, highlights |
| `review.php` | SRS queue (Again / Hard / Good / Easy) |
| `api/*.php` | Highlights, SRS, lessons JSON APIs |

## Dual TOC

- **Chronological** — video order with timestamps and deep links  
- **Learning Structure** — study order (concepts, definitions, examples, …) linking back to source times  

Toggle on the lesson page (`?toc=chrono|learn`).

## Diagrams / infographics

Lessons may include a `diagrams` array (prefer **Mermaid** text so no binary assets are required; SVG and image URLs also work). In the UI:

- Diagrams appear as **inline visual breaks** in the transcript at the linked segment/time
- A **Diagrams** gallery lists all visuals; each jumps to the transcript + video timestamp
- Deep link: `lesson.php?v=VIDEO_ID&dg=d1&t=62`

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

Plain PHP 7.4+, Tailwind CDN, vanilla JS, Mermaid CDN for diagrams. Zero Composer deps by default. Neutral slate palette, Inter/system fonts, readable max width.

## License

Use freely for local learning tools. YouTube content remains subject to YouTube’s terms; this app stores lesson JSON you generate locally.
