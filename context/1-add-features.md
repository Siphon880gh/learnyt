### **Task: Learnyt Lesson Viewer Enhancements**

#### **1. Diagram & Thumbnail Lightbox**
- **Viewport & Auto-Fit:** Lightbox opens full-viewport. Auto-fit logic scales up to **150%** for small images and scales down to fit viewport boundaries for larger images.
- **Controls:** Add a **"Zoom to fit"** button adjacent to standard zoom controls.
- **Pixel Initialization:** Explicitly size graphics in real pixels prior to rendering to eliminate blank white squares on open.

#### **2. Sticky Lesson Navigation Bar**
- **Layout & Mobile Alignment:**
  - Position controls on the far right on mobile viewports so dropdown menus are never clipped.
  - **Day/Night Switch:** Placed immediately to the left of the Sync control (persisted via local storage).
  - **Vertical Separator:** Placed between theme toggle and Sync control.
- **Sync Control Dropdown:**
  - Render with a downward chevron selector.
  - **Modes:**
    - `Off` (No scrolling)
    - `Ease Snap` (Smooth snaps per section)
    - `Continuous` (Smooth continuous auto-scroll)
  - **Sync Behavior:** Enabling Sync immediately jumps/snaps the view to the transcript segment matching the current video timestamp, then tracks active playback timestamps dynamically.

#### **3. Video Header Layout & Stacking**
- Adjust vertical layout offsets so the sticky embedded video container sits completely below the fixed header and lesson nav.
- Ensure the YouTube video title overlay remains unobstructed and visible.

#### **4. Transcript Extraction & Sample Regeneration**
- Update the lesson ingestion pipeline/skill to extract captions directly from the embedded video source.
- Regenerate the sample lesson content so text blocks, segment boundaries, and timestamps correlate directly with the embedded video captions.

#### **5. Delivery**
- Verify layout across desktop and mobile viewports.
- Commit all changes and push directly to `main`.