Build a web application using **PHP and Tailwind CSS** that transforms YouTube video content into interactive learning modules.

## Core Concept: Harness-First Architecture

This application must be designed around a **harness-first workflow**, rather than requiring users to submit YouTube URLs through a traditional web form.

Because Weng cannot provide or donate AI-token usage for this service, users must use an **AI coding/chat harness of their choice** against the application's codebase.

The expected workflow is:

1. The user opens the project inside an AI harness.
2. The user provides a YouTube URL directly in the AI chat.
3. The AI harness invokes a local project skill located under:

`/.agents/skills/*`

4. The skill processes the YouTube video and generates the required lesson data.
5. The web application displays the resulting interactive learning module.

Create the local skill and supporting documentation needed so an AI harness can reliably understand and execute this workflow.

The web application itself should therefore **not depend on a traditional YouTube URL input interface**. Instead, the landing page should clearly explain how users provide a URL through their AI harness and how the generated lesson subsequently appears in the application.

## Design

Use a **Minimalist aesthetic** with:

- High readability
- Generous whitespace
- Strong typography
- Clear visual hierarchy
- Intuitive navigation
- Minimal visual clutter
- Responsive layouts
- Fast interaction
- Accessible UI patterns

The learning content should remain the primary visual focus.

## Technology

Use:

- PHP
- Tailwind CSS
- A dedicated backend service or processing layer for transcript/video processing
- Local skills under `/.agents/skills/*`

Never expose API keys, secrets, credentials, or privileged service tokens in frontend JavaScript, HTML, URLs, or browser-accessible source code.

All API credentials and third-party service calls requiring authentication must be handled exclusively by the backend or by the user's chosen AI harness.

## 1. Landing Page

Create a landing page that clearly explains the AI-driven lesson creation workflow.

Explain that the user:

1. Opens the project with their preferred AI harness.
2. Gives the AI a YouTube URL.
3. The AI invokes the project's local YouTube-learning skill.
4. The skill obtains and analyzes the transcript.
5. The application generates an interactive learning module.
6. The user studies the video using the transcript, Tables of Contents, highlights, deep links, and spaced repetition.

Include a prominent call-to-action explaining how to begin through the user's AI harness.

Do not make a traditional YouTube URL submission form the primary workflow.

## 2. Local AI Skill

Create a reusable local skill under:

`/.agents/skills/`

The skill should accept a YouTube URL and orchestrate the lesson-generation process.

It should be capable of:

- Identifying the YouTube video
- Obtaining the transcript when available
- Preserving transcript timestamps
- Breaking the transcript into meaningful segments
- Identifying chapters, subjects, arguments, examples, explanations, and transitions
- Generating chronological navigation
- Reorganizing the material into a better learning sequence
- Producing structured lesson data that the PHP application can render
- Saving the generated module into the application's expected local data format

The skill should contain enough instructions that different compatible AI harnesses can use it consistently.

## 3. Dual Table of Contents

For every processed video, generate **two separate Tables of Contents**.

### A. Chronological Table of Contents

This version follows the video's actual timeline.

It should reflect the natural flow of the transcript and may contain:

- Existing YouTube chapters
- Automatically detected chapters
- Major discussion points
- Topic changes
- Important examples
- Logical transcript sections
- Time-based sections such as approximately five-minute intervals when meaningful semantic sections cannot be determined

Each entry should contain a timestamp and deep-link into the corresponding point in the transcript/video.

The goal is:

**"What happened in the video, and in what order?"**

### B. Learning-Focused Table of Contents

Generate a second TOC optimized for **understanding and studying the subject**, rather than merely reproducing the video's order.

The AI may reorganize related ideas into categories such as:

- Core concepts
- Definitions
- Prerequisites
- Key arguments
- Mechanisms
- Examples
- Evidence
- Comparisons
- Practical applications
- Common mistakes
- Exceptions
- Important takeaways
- Review topics

The structured TOC does not need to follow the original chronology.

Each learning section should still maintain links back to the original transcript timestamps so the learner can inspect the source material.

The goal is:

**"How should I organize this information if I want to learn it?"**

Allow users to switch easily between:

**Chronological** | **Learning Structure**

## 4. Interactive Transcript Viewer

Display the complete timestamped transcript in a highly readable interface.

The transcript should synchronize logically with the TOC and video timestamps.

Users must be able to select or highlight transcript text.

When text is highlighted, provide contextual actions for:

- Save for spaced repetition
- Copy to clipboard
- Create a shareable deep link
- Jump to the corresponding video timestamp

Avoid permanently covering the transcript with large controls. Actions should appear contextually when text is selected.

## 5. Deep Links

Allow users to generate URLs that point directly to specific lesson content.

Use URL query parameters or another human-readable deep-linking scheme.

A link should be capable of restoring relevant state such as:

- Video/module
- Timestamp
- Transcript segment
- Highlight
- TOC section

For example, a shared URL could open the lesson and automatically jump to the relevant timestamp and transcript passage.

Deep links should remain usable without exposing private credentials or backend secrets.

## 6. Highlighting

Users should be able to highlight important passages within the transcript.

Persist highlights locally or through the application's backend data model.

A highlight should retain:

- Highlighted text
- Video ID
- Start timestamp
- End timestamp if applicable
- Transcript segment
- Creation date
- Optional user note
- Spaced-repetition status

Highlights should remain connected to the original source location.

## 7. Spaced Repetition

Integrate an **Anki-style spaced repetition system**.

Users should be able to save learning items from:

- Transcript highlights
- Chronological TOC entries
- Learning-focused TOC entries
- AI-generated concepts or summaries

Create a review queue where saved items become due over time.

Each review item should retain enough source context to return the learner directly to the relevant part of the lesson.

During review, provide controls such as:

- Again
- Hard
- Good
- Easy

Use these responses to adjust the next review interval using a practical spaced-repetition algorithm inspired by Anki/SM-2 principles.

Track fields such as:

- Created date
- Last reviewed
- Next review
- Review interval
- Ease/difficulty
- Review count
- Source video
- Source timestamp
- Source transcript text

## 8. Review Queue

Create a dedicated **Review** section.

Show:

- Items due today
- Overdue items
- Upcoming reviews
- Recently added items
- Review history

The primary review experience should focus on one item at a time.

Users should be able to reveal additional context or jump directly back to the source transcript/video when needed.

## 9. Lesson Navigation

Each generated lesson should have clear navigation between:

- Overview
- Chronological TOC
- Learning TOC
- Transcript
- Highlights
- Review Queue

Keep the interface lightweight and avoid turning the application into a complex dashboard.

The application should feel like a focused reading and learning environment.

## 10. Data Architecture

Design the application so lesson data is separated from presentation logic.

A generated lesson should have structured data representing at least:

- Video metadata
- Transcript
- Timestamped transcript segments
- Chronological TOC
- Learning-focused TOC
- Highlights
- Notes
- Deep-link identifiers
- Spaced-repetition items

The local AI skill should generate or update this structured lesson data, while the PHP application primarily handles rendering, interaction, persistence, and review management.

## 11. Backend and Security

Use the backend for:

- Transcript processing services
- API integrations
- Authenticated external requests
- Data persistence
- Lesson storage
- Highlight storage
- Spaced-repetition scheduling

Never store API keys in:

- Frontend JavaScript
- HTML
- CSS
- Query parameters
- Public Git repositories
- Browser local storage

Use server-side environment configuration for secrets when backend credentials are required.

## Primary Product Principle

The application should not attempt to subsidize AI generation.

Instead, it provides the **learning interface, data structure, local AI skill, transcript tools, deep-link system, and spaced-repetition environment**, while users bring their own AI harness and associated model/token access.

The overall experience should be:

**YouTube URL in AI harness → local skill processes video → lesson generated → study with dual TOCs + interactive transcript → highlight important material → save to spaced repetition → review over time.**