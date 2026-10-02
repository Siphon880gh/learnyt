# Skill: youtube-lesson

Generate a Learnyt lesson JSON from a YouTube URL (or video ID) for local study in this PHP app.

## When to use

Use this skill when the user:

- Pastes a YouTube URL / video ID in chat and wants a **learning module**
- Asks to “make a lesson”, “build a study module”, “extract TOC + transcript”, or “add to Learnyt”
- Wants dual TOCs (chronological + learning structure) saved under `data/lessons/`

Do **not** use this skill for unrelated YouTube tasks (download media for redistribution, comment scraping, etc.).

## Inputs

Accept any of:

- `https://www.youtube.com/watch?v=VIDEO_ID`
- `https://youtu.be/VIDEO_ID`
- `https://www.youtube.com/shorts/VIDEO_ID`
- Bare `VIDEO_ID` (11 chars: `A-Za-z0-9_-`)

Confirm the video ID before writing files.

## Outputs

Write:

```
data/lessons/{videoId}.json
```

Optionally seed:

- `data/highlights/{videoId}.json` — only if user asked to pre-highlight
- SRS items via the app UI (prefer not to invent SRS cards unless asked)

Match schema in `docs/DATA_SCHEMA.md`.

## Procedure

### 1. Identify video ID

Extract with regex:

- `(?:v=|/youtu\.be/|/embed/|/shorts/)([A-Za-z0-9_-]{11})`
- or validate bare 11-char id

### 2. Obtain transcript with timestamps

**Prefer (local):**

```bash
php api/transcript_helper.php VIDEO_ID
# or
php api/transcript_helper.php --url 'https://www.youtube.com/watch?v=VIDEO_ID'
```

This wraps `yt-dlp` (subtitles → VTT → segments). Requires `yt-dlp` on PATH (or `YT_DLP_PATH` in `.env`).

**Direct yt-dlp:**

```bash
yt-dlp --skip-download --write-auto-sub --write-sub --sub-lang en \
  --convert-subs vtt -o '/tmp/learnyt_%(id)s' -- 'https://www.youtube.com/watch?v=VIDEO_ID'
```

Parse VTT cues into `{id, start, end, text}` segments. Deduplicate overlapping auto-caption lines.

**Metadata (title, channel, duration, thumbnail):**

```bash
yt-dlp -j --skip-download -- 'https://www.youtube.com/watch?v=VIDEO_ID'
```

Map fields: `id`, `title`, `channel`/`uploader`, `duration`, `thumbnail`.

**Fallbacks (in order):**

1. Manual captions / auto-subs via yt-dlp (`--write-sub` then `--write-auto-sub`)
2. Other local transcript CLIs the environment already has (e.g. `youtube_transcript_api` via Python) — prefer tools already installed; do not invent API keys
3. If no captions exist: tell the user, offer to stop, or build a coarse TOC from chapters/`description` timestamps only (mark `meta.transcriptConfidence: "low"`)

**Never:**

- Put API keys in frontend, HTML, CSS, query params, or committed files
- Commit `.env`
- Call paid APIs unless the user explicitly configured server-side `.env` and asked you to

### 3. Segment & analyze

From the timestamped transcript:

1. **Merge** tiny cues into readable sentences/paragraphs (~8–25s each when possible).
2. **Detect chapters** from:
   - YouTube chapter markers in description / yt-dlp `chapters`
   - Explicit verbal section cues (“next…”, “part two…”, numbered lists)
   - Topic shifts (new subject, definition block, example block)
3. If no chapters: create **~5-minute** chronological fallbacks with descriptive titles from the first sentence of each window.
4. Label learning material:
   - prerequisites, definitions, core concepts, arguments, mechanisms, examples, applications, mistakes, takeaways

### 4. Build dual TOCs

**A. `chronologicalToc`** — video order:

```json
{ "id": "c1", "title": "…", "start": 0, "end": 120 }
```

**B. `learningToc`** — reorganized for study (not necessarily video order):

```json
{
  "id": "l1",
  "title": "…",
  "category": "definition",
  "sources": [{ "start": 45, "end": 62 }]
}
```

Categories: `prerequisite` | `definition` | `concept` | `argument` | `mechanism` | `example` | `application` | `mistake` | `takeaway` | `other`

Each learning item must link back to at least one source timestamp.

Also fill:

- `meta.summary` (2–4 sentences)
- `meta.learningObjectives` (3–6 bullets)
- `meta.createdAt` / `meta.updatedAt` (ISO-8601 UTC)
- `meta.generator`: `"youtube-lesson-skill"`

### 5. Write JSON

Path: `data/lessons/{videoId}.json`

Minimum shape:

```json
{
  "video": {
    "id": "VIDEO_ID",
    "title": "…",
    "channel": "…",
    "duration": 0,
    "thumbnail": "https://img.youtube.com/vi/VIDEO_ID/hqdefault.jpg",
    "url": "https://www.youtube.com/watch?v=VIDEO_ID"
  },
  "meta": {
    "createdAt": "…",
    "updatedAt": "…",
    "generator": "youtube-lesson-skill",
    "summary": "…",
    "learningObjectives": ["…"]
  },
  "transcript": [{ "id": "s1", "start": 0, "end": 10, "text": "…" }],
  "chronologicalToc": [{ "id": "c1", "title": "…", "start": 0, "end": 60 }],
  "learningToc": [{
    "id": "l1",
    "title": "…",
    "category": "concept",
    "sources": [{ "start": 0, "end": 60 }]
  }]
}
```

Use pretty-printed UTF-8 JSON. Validate:

- Every TOC `start` is within `[0, duration]`
- Segment ids unique; TOC ids unique
- `video.id` matches filename stem

### 6. Verify in the app

```bash
php -S localhost:8080
```

Open `http://localhost:8080/` — lesson should appear. Open `lesson.php?v=VIDEO_ID` and spot-check:

- Chronological | Learning Structure toggle
- Transcript timestamps seek the player
- Deep link `?v=&t=&toc=`

## Quality bar

- Titles are specific (not “Part 1”, “Section 2”) unless the speaker uses those labels
- Learning TOC covers definitions + mechanisms + examples + takeaways when present
- Transcript is readable (punctuation cleaned; no raw VTT tags)
- No secrets in the JSON

## Example user prompt

> Run the youtube-lesson skill on https://www.youtube.com/watch?v=XXXX and save the lesson JSON.

## Example agent checklist

- [ ] Parsed video id
- [ ] Fetched metadata
- [ ] Fetched / parsed transcript (or documented failure)
- [ ] Built chronological TOC
- [ ] Built learning TOC with categories + source timestamps
- [ ] Wrote `data/lessons/{id}.json`
- [ ] Confirmed file opens in Learnyt UI
