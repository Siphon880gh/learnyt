# Learnyt data schema

All lesson data is local JSON under `data/`. No database required for MVP.

- **Seed (committed):** `data/lessons/{id}.json`
- **Browser drafts:** `localStorage` keys `learnyt_user_lessons_v1`, `learnyt_highlights_v1`, `learnyt_srs_v1`
- **Synced demo (gitignored, public on this server):** `data/demo/lessons/{id}.json` via Sync to demo (`api/sync_demo.php`, password in `.env`)

## Lesson file

**Path:** `data/lessons/{videoId}.json`

`videoId` is the YouTube id (`[A-Za-z0-9_-]{11}`) or a demo id such as `sample-spaced-rep` (`[A-Za-z0-9_-]{6,64}`).

```ts
type Lesson = {
  video: {
    id: string
    title: string
    channel?: string
    duration?: number          // seconds
    thumbnail?: string
    url?: string
    embedId?: string           // optional override for iframe when id is synthetic
    note?: string
  }
  meta?: {
    createdAt?: string         // ISO-8601
    updatedAt?: string
    generator?: string
    summary?: string
    learningObjectives?: string[]
    transcriptConfidence?: 'high' | 'medium' | 'low'
  }
  transcript: TranscriptSegment[]
  chronologicalToc: ChronoItem[]
  learningToc: LearnItem[]
  diagrams?: Diagram[]
}

type TranscriptSegment = {
  id: string
  start: number                // seconds
  end: number
  text: string
}

type ChronoItem = {
  id: string
  title: string
  start: number
  end?: number
}


type LearnItem = {
  id: string
  title: string
  category?: 'prerequisite' | 'definition' | 'concept' | 'argument'
    | 'mechanism' | 'example' | 'application' | 'mistake' | 'takeaway' | 'other'
  sources: { start: number; end?: number }[]
}
```

## Diagrams / infographics

Optional array on the lesson file. Prefer **Mermaid** (`kind: "mermaid"`) so the skill can emit text-only visuals with no binary assets. The PHP UI renders Mermaid client-side via CDN, shows diagrams as **inline transcript breaks**, and lists them in a **Diagrams** gallery that deep-links back to the segment/time.

```ts
type Diagram = {
  id: string                   // e.g. "d1"
  title: string
  caption?: string
  segmentId?: string           // transcript segment id to interrupt after
  start?: number               // seconds — video/transcript jump target
  end?: number
  kind: 'mermaid' | 'svg' | 'image'
  mermaid?: string             // when kind === 'mermaid'
  svg?: string                 // when kind === 'svg' (inline SVG markup)
  imageUrl?: string            // when kind === 'image' (https or local relative path)
}
```

Guidelines for the skill:

- Add a diagram when a process, comparison, hierarchy, timeline, or mechanism is easier to see than to read
- Keep Mermaid diagrams small (roughly ≤ 20 nodes)
- Always set `start` (and `segmentId` when possible) so the UI can jump to source
- Do not embed secrets or private URLs in `imageUrl`


## Highlights

**Path:** `data/highlights/{videoId}.json` (gitignored contents; folder kept via `.gitkeep`)

```ts
type HighlightStore = {
  videoId: string
  highlights: Highlight[]
}

type Highlight = {
  id: string                   // uuid
  videoId: string
  text: string
  start: number
  end: number
  segmentId?: string | null
  note?: string | null
  srsStatus?: 'none' | 'queued' | 'reviewing'
  created: string              // ISO-8601
  updated?: string
}
```

API: `api/highlights.php` — GET/POST/PATCH/DELETE (same-origin).

## Spaced repetition

**Path:** `data/srs.json` (gitignored)

```ts
type SrsStore = {
  updatedAt?: string
  items: SrsItem[]
}

type SrsItem = {
  id: string
  text: string
  videoId?: string | null
  start?: number | null
  end?: number | null
  segmentId?: string | null
  sourceType?: 'highlight' | 'chrono' | 'learn' | 'concept' | 'manual'
  sourceTitle?: string | null
  note?: string | null
  created: string
  lastReviewed?: string | null
  nextReview: string
  interval: number             // days
  ease: number                 // default 2.5, min 1.3
  reviewCount: number
  history: { rating: 'again'|'hard'|'good'|'easy'; at: string; interval: number; ease: number }[]
}
```

API: `api/srs.php` — list/due/buckets, create, rate (`again|hard|good|easy`), delete.

Ratings follow an SM-2-inspired schedule (Again resets; Hard/Good/Easy grow interval and adjust ease).

## Deep links

Human-readable query params on `lesson.php` (no secrets):

| Param | Meaning |
|-------|---------|
| `v` | video / lesson id |
| `t` | start seconds (or `1:23` / `1h2m3s`) |
| `seg` | transcript or TOC segment id |
| `hl` | highlight id |
| `toc` | `chrono` \| `learn` |
| `dg` | diagram id |

Example: `lesson.php?v=sample-spaced-rep&t=62&toc=learn&seg=l4&dg=d1`

## Lessons list API

`GET api/lessons.php` — summary list  
`GET api/lessons.php?v=VIDEO_ID` — full lesson JSON
