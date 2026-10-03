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

Prefer producing lesson JSON the user can **Import** on the Learnyt home page (browser localStorage). Optionally also write a file for convenience:

```
data/lessons/{videoId}.json   # only if asked; do not overwrite sample-spaced-rep unless fixing a transcript/video mismatch
```

Product path: harness JSON → user Import (localStorage) → optional **Sync to demo** (gitignored `data/demo/lessons/`, server password).

Still valid to write:

```
data/lessons/{videoId}.json
```
when the user wants a seed-style file in the repo — never overwrite `sample-spaced-rep`.

Optionally seed:

- `data/highlights/{videoId}.json` — only if user asked to pre-highlight
- SRS items via the app UI (prefer not to invent SRS cards unless asked)

Match schema in `docs/DATA_SCHEMA.md`.

## Procedure

### 1. Identify video ID

Extract with regex:

- `(?:v=|/youtu\.be/|/embed/|/shorts/)([A-Za-z0-9_-]{11})`
- or validate bare 11-char id

### 2. Obtain transcript with timestamps (must match the real video)

**Hard rule:** The transcript `text` and `start`/`end` timestamps **must come from the actual video’s captions** (manual or auto) for the same YouTube id that the lesson embeds (`video.id` when it is an 11-char YouTube id, otherwise `video.embedId`).

- **Do not invent** a script, paraphrase a different lecture, or reuse synthetic copy that does not match what is spoken.
- After parsing VTT, spot-check the first ~30s and one mid-video line against the player. If words and times do not match the audio, stop and re-fetch — do not ship the lesson.
- If `video.id` is a demo/seed id (e.g. `sample-spaced-rep`) but `embedId` points at a real video, the transcript **still must match `embedId`**.

**Prefer (local):**

```bash
php api/transcript_helper.php VIDEO_ID
# or
php api/transcript_helper.php --url 'https://www.youtube.com/watch?v=VIDEO_ID'
```

This wraps `yt-dlp` (subtitles → VTT → segments). Requires `yt-dlp` on PATH (or `YT_DLP_PATH` in `.env`). Use the **embedded** YouTube id (`embedId` if present).

**Direct yt-dlp:**

```bash
yt-dlp --skip-download --write-auto-sub --write-sub --sub-lang en \
  --convert-subs vtt -o '/tmp/learnyt_%(id)s' -- 'https://www.youtube.com/watch?v=VIDEO_ID'
```

Parse VTT cues into `{id, start, end, text}` segments. Deduplicate overlapping auto-caption lines. Merge tiny cues into readable segments (~8–25s) **without changing wording** beyond light cleanup (strip VTT tags, collapse whitespace).

**Metadata (title, channel, duration, thumbnail):**

```bash
yt-dlp -j --skip-download -- 'https://www.youtube.com/watch?v=VIDEO_ID'
```

Map fields: `id`, `title`, `channel`/`uploader`, `duration`, `thumbnail`.

**Fallbacks (in order):**

1. Manual captions / auto-subs via yt-dlp (`--write-sub` then `--write-auto-sub`)
2. Other local transcript CLIs the environment already has (e.g. `youtube_transcript_api` via Python) — prefer tools already installed; do not invent API keys
3. If no captions exist: tell the user and stop (or build TOC-only from chapters with an empty/`low` transcript). **Never fabricate spoken lines.**

**Never:**

- Invent a transcript that does not match the embedded video’s captions/audio
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
5. Plan **visual breaks**: for each concept that can be illustrated, note the transcript `segmentId` + `start` where a Mermaid diagram should interrupt the reading flow (see § Visual breaks / Diagrams)

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

### 5. Visual breaks — emit Mermaid diagrams (required when concepts can be illustrated)

**At visual breaks, generating Mermaid diagrams is useful and expected.** Whenever a process, mechanism, comparison, hierarchy, timeline, or causal chain appears in the lecture, add an entry to `diagrams[]` so the PHP UI inserts an **inline visual break** after the linked transcript segment (and lists it in the Diagrams gallery).

For each such concept:

1. Pick the transcript segment that finishes introducing the idea (`segmentId`, e.g. `"s5"`).
2. Set `start` (and optional `end`) to that segment’s timestamp in seconds.
3. Emit `kind: "mermaid"` with a compact Mermaid source string in `mermaid` (flowchart, sequenceDiagram, mindmap, or timeline-style flowchart).
4. Give a short `title` + `caption`. Keep ≤ ~20 nodes; no secrets.

Schema-matching example (inline break after segment `s5` at t=62):

```json
{
  "id": "d1",
  "title": "SM-2 rating flow",
  "caption": "How Again / Hard / Good / Easy change the next interval.",
  "segmentId": "s5",
  "start": 62,
  "end": 80,
  "kind": "mermaid",
  "mermaid": "flowchart TD\n  R[Review card] --> A{Rating}\n  A -->|Again| X[Reset / short delay]\n  A -->|Hard| H[Smaller growth]\n  A -->|Good| G[ease × interval]\n  A -->|Easy| E[Larger interval]\n  X --> N[Next review]\n  H --> N\n  G --> N\n  E --> N"
}
```

Another useful pattern — sequence for ordered steps:

```json
{
  "id": "d2",
  "title": "Retrieval practice loop",
  "caption": "Recall before re-reading.",
  "segmentId": "s6",
  "start": 80,
  "kind": "mermaid",
  "mermaid": "sequenceDiagram\n  participant L as Learner\n  participant M as Memory\n  L->>M: Attempt recall\n  M-->>L: Partial or full answer\n  L->>M: Restudy gaps\n  Note over L,M: Space the next review"
}
```

Do **not** skip this step when the video clearly teaches something visualizable. Prefer Mermaid over SVG/image so the skill stays text-only. See also **Diagrams / infographics** below.

### 6. Write JSON

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
  }],
  "diagrams": [{
    "id": "d1",
    "title": "…",
    "caption": "…",
    "segmentId": "s1",
    "start": 0,
    "kind": "mermaid",
    "mermaid": "flowchart LR\n  A --> B"
  }]
}
```

Use pretty-printed UTF-8 JSON. Validate:

- Every TOC `start` is within `[0, duration]`
- Segment ids unique; TOC ids unique
- `video.id` matches filename stem

### 7. Verify in the app

```bash
php -S localhost:8080
```

Open `http://localhost:8080/` — lesson should appear (Import or file). Open `lesson.php?v=VIDEO_ID` and spot-check:

- Chronological | Learning Structure toggle
- Transcript timestamps seek the player
- Mermaid **visual breaks** appear inline after the linked segments; Diagrams gallery lists them
- Deep link `?v=&t=&toc=&dg=`


## Diagrams / infographics

**Required when learnable ideas can be illustrated:** populate `diagrams[]` with Mermaid visuals attached to transcript segments as **visual breaks**. This is not optional decoration—the Learnyt UI is built to interrupt the transcript with these diagrams and to list them in a gallery.

Good candidates:

- Processes / algorithms (e.g. SM-2 rating flow)
- Comparisons / contrasts
- Hierarchies / taxonomies
- Timelines / causal chains
- Mechanisms / system diagrams

**Use Mermaid** (`kind: "mermaid"` + `mermaid` source string) by default—text only, no binary assets. The app renders Mermaid client-side (CDN).

Minimal schema-matching object:

```json
{
  "id": "d1",
  "title": "Short descriptive title",
  "caption": "One sentence of context",
  "segmentId": "s5",
  "start": 62,
  "end": 80,
  "kind": "mermaid",
  "mermaid": "flowchart TD\n  A[Start] --> B[Step]\n  B --> C[Result]"
}
```

Also supported (secondary):

- `kind: "svg"` + `svg`: `"<svg ...>...</svg>"` (no scripts / event handlers)
- `kind: "image"` + `imageUrl`: `https://...` or local `assets/...` / `data/...` path

Rules:

1. Always set `start` (seconds) so Jump-to-video / deep links work
2. **Always set `segmentId`** to the transcript segment the diagram should follow (that is what creates the inline visual break)
3. Keep Mermaid small (≤ ~20 nodes); prefer `flowchart`, `sequenceDiagram`, or `mindmap`
4. Typically 1–6 diagrams per lecture; skip purely decorative fluff, but do not skip clear visualizable concepts
5. Never put secrets or private URLs in diagram payloads
6. Diagram `id`s unique (`d1`, `d2`, …)

The PHP UI (`lesson.php` + Mermaid CDN):

- Renders `pre.mermaid` client-side
- Inserts diagrams **inline in the transcript** immediately after the linked `segmentId`
- Shows a **Diagrams** gallery; each card deep-links with `?dg=` + `?t=`

## Quality bar

- Titles are specific (not “Part 1”, “Section 2”) unless the speaker uses those labels
- Learning TOC covers definitions + mechanisms + examples + takeaways when present
- Transcript is readable (punctuation cleaned; no raw VTT tags) **and matches the embedded video’s real captions/timemarks**
- Visualizable concepts have Mermaid entries in `diagrams[]` with `segmentId` + `start` (inline visual breaks)
- No secrets in the JSON

## Example user prompt

> Run the youtube-lesson skill on https://www.youtube.com/watch?v=XXXX and save the lesson JSON.

## Example agent checklist

- [ ] Parsed video id
- [ ] Fetched metadata
- [ ] Fetched / parsed **real** transcript for the embedded video id (spot-checked vs player; no synthetic mismatch)
- [ ] Built chronological TOC
- [ ] Built learning TOC with categories + source timestamps
- [ ] Added Mermaid visual breaks (`diagrams[]` with `kind: "mermaid"`, `segmentId`, `start`) for illustratable concepts
- [ ] Wrote `data/lessons/{id}.json`
- [ ] Confirmed file opens in Learnyt UI (dual TOC + Diagrams gallery)
