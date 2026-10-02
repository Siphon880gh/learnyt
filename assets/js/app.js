/**
 * Learnyt — vanilla JS interactions
 * No secrets; all API calls same-origin.
 */
(function () {
  'use strict';

  const toastEl = () => document.getElementById('toast');

  function toast(msg) {
    const el = toastEl();
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('hidden');
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.add('hidden'), 2200);
  }
  window.LearnytToast = toast;

  function useLocalStore() {
    const cfg = window.LEARNYT || {};
    return window.LearnytStorage && window.LearnytStorage.usesLocalPersistence(cfg);
  }

  async function api(url, opts = {}) {
    const res = await fetch(url, {
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      credentials: 'same-origin',
      ...opts,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || res.statusText || 'Request failed');
    return data;
  }

  function buildDeepLink(extra = {}) {
    const cfg = window.LEARNYT || {};
    const url = new URL(window.location.href);
    url.search = '';
    url.pathname = url.pathname.replace(/[^/]+$/, 'lesson.php');
    const params = {
      v: cfg.videoId || url.searchParams.get('v'),
      t: extra.t != null ? String(Math.floor(extra.t)) : undefined,
      seg: extra.seg,
      hl: extra.hl,
      dg: extra.dg,
      toc: extra.toc || cfg.toc || 'chrono',
    };
    Object.entries(params).forEach(([k, v]) => {
      if (v != null && v !== '') url.searchParams.set(k, v);
    });
    return url.toString();
  }

  // --- YouTube player ---
  let ytPlayer = null;
  window.onYouTubeIframeAPIReady = function () {
    const iframe = document.getElementById('yt-player');
    if (!iframe || typeof YT === 'undefined') return;
    ytPlayer = new YT.Player('yt-player', {
      events: {
        onReady: () => {
          const start = (window.LEARNYT && window.LEARNYT.start) || 0;
          if (start > 0 && ytPlayer && ytPlayer.seekTo) {
            try { ytPlayer.seekTo(start, true); } catch (_) {}
          }
        },
      },
    });
  };

  function seekTo(seconds) {
    const t = Math.max(0, Number(seconds) || 0);
    if (ytPlayer && typeof ytPlayer.seekTo === 'function') {
      try {
        ytPlayer.seekTo(t, true);
        if (typeof ytPlayer.playVideo === 'function') ytPlayer.playVideo();
        return;
      } catch (_) {}
    }
    // Fallback: reload iframe with start
    const iframe = document.getElementById('yt-player');
    const cfg = window.LEARNYT || {};
    const embed = cfg.embedId || cfg.videoId;
    if (iframe && embed && /^[A-Za-z0-9_-]{11}$/.test(embed)) {
      iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
      iframe.referrerPolicy = 'strict-origin-when-cross-origin';
      iframe.src =
        'https://www.youtube.com/embed/' +
        encodeURIComponent(embed) +
        '?enablejsapi=1&rel=0&modestbranding=1&start=' +
        Math.floor(t) +
        '&autoplay=1';
    }
  }

  // --- TOC toggle ---
  function initToc() {
    document.querySelectorAll('[data-toc-mode]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const mode = btn.getAttribute('data-toc-mode');
        document.querySelectorAll('[data-toc-mode]').forEach((b) => {
          const on = b.getAttribute('data-toc-mode') === mode;
          b.setAttribute('aria-selected', on ? 'true' : 'false');
          b.classList.toggle('bg-slate-900', on);
          b.classList.toggle('text-white', on);
          b.classList.toggle('text-slate-600', !on);
        });
        document.getElementById('toc-chrono')?.classList.toggle('hidden', mode !== 'chrono');
        document.getElementById('toc-learn')?.classList.toggle('hidden', mode !== 'learn');
        if (window.LEARNYT) window.LEARNYT.toc = mode;
        const u = new URL(window.location.href);
        u.searchParams.set('toc', mode);
        history.replaceState(null, '', u.toString());
      });
    });

    document.querySelectorAll('[data-seek]').forEach((el) => {
      el.addEventListener('click', (e) => {
        // Allow modifier-click to open deep link normally for <a>
        if (el.tagName === 'A' && (e.metaKey || e.ctrlKey || e.shiftKey)) return;
        if (el.tagName === 'A') e.preventDefault();
        const t = el.getAttribute('data-seek');
        seekTo(t);
        highlightSegAt(Number(t));
      });
    });
  }

  function highlightSegAt(t) {
    const segs = document.querySelectorAll('.transcript-seg');
    let best = null;
    segs.forEach((s) => {
      s.classList.remove('is-active');
      const start = Number(s.dataset.start);
      const end = Number(s.dataset.end);
      if (t >= start && t < end) best = s;
    });
    if (!best) {
      // nearest
      let min = Infinity;
      segs.forEach((s) => {
        const d = Math.abs(Number(s.dataset.start) - t);
        if (d < min) {
          min = d;
          best = s;
        }
      });
    }
    if (best) {
      best.classList.add('is-active');
      best.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  // --- Selection toolbar ---
  function initSelectionToolbar() {
    const toolbar = document.getElementById('selection-toolbar');
    const list = document.getElementById('transcript-list');
    if (!toolbar || !list) return;

    let lastRange = null;
    let lastSeg = null;

    function hide() {
      toolbar.classList.remove('is-visible');
    }

    function showAt(rect) {
      const parent = list.getBoundingClientRect();
      const top = rect.top - parent.top + list.scrollTop;
      const left = rect.left - parent.left + list.scrollLeft + rect.width / 2;
      toolbar.style.top = Math.max(0, top) + 'px';
      toolbar.style.left = Math.max(40, left) + 'px';
      toolbar.classList.add('is-visible');
    }

    document.addEventListener('selectionchange', () => {
      const sel = window.getSelection();
      if (!sel || sel.isCollapsed || sel.rangeCount === 0) {
        hide();
        return;
      }
      const range = sel.getRangeAt(0);
      if (!list.contains(range.commonAncestorContainer)) {
        hide();
        return;
      }
      lastRange = range.cloneRange();
      const node = range.startContainer.nodeType === 3 ? range.startContainer.parentElement : range.startContainer;
      lastSeg = node?.closest?.('.transcript-seg') || null;
      const rect = range.getBoundingClientRect();
      if (rect.width === 0 && rect.height === 0) {
        hide();
        return;
      }
      showAt(rect);
    });

    document.addEventListener('mousedown', (e) => {
      if (toolbar.contains(e.target)) return;
      // delay hide so button click fires
      setTimeout(() => {
        if (!toolbar.contains(document.activeElement)) hide();
      }, 150);
    });

    toolbar.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-action]');
      if (!btn) return;
      const action = btn.getAttribute('data-action');
      const sel = window.getSelection();
      const text = (sel && !sel.isCollapsed ? sel.toString() : lastRange?.toString() || '').trim();
      const cfg = window.LEARNYT || {};
      const start = lastSeg ? Number(lastSeg.dataset.start) : 0;
      const end = lastSeg ? Number(lastSeg.dataset.end) : start;
      const segmentId = lastSeg ? lastSeg.dataset.segId : null;

      try {
        if (action === 'copy') {
          await navigator.clipboard.writeText(text);
          toast('Copied');
        } else if (action === 'jump') {
          seekTo(start);
          toast('Jumped to ' + formatTime(start));
        } else if (action === 'deeplink') {
          const link = buildDeepLink({ t: start, seg: segmentId, toc: cfg.toc });
          await navigator.clipboard.writeText(link);
          toast('Deep link copied');
        } else if (action === 'highlight') {
          if (!text) return toast('Select text first');
          if (useLocalStore()) {
            const highlight = window.LearnytStorage.addHighlight(cfg.videoId, {
              text, start, end, segmentId,
            });
            appendHighlight(highlight);
            toast('Highlight saved (this browser)');
          } else {
            const data = await api('api/highlights.php', {
              method: 'POST',
              body: JSON.stringify({ videoId: cfg.videoId, text, start, end, segmentId }),
            });
            appendHighlight(data.highlight);
            toast('Highlight saved');
          }
        } else if (action === 'srs') {
          if (!text) return toast('Select text first');
          if (useLocalStore()) {
            window.LearnytStorage.addSrs({
              text, videoId: cfg.videoId, start, end, segmentId, sourceType: 'highlight',
            });
            toast('Saved to SRS (this browser)');
          } else {
            await api('api/srs.php', {
              method: 'POST',
              body: JSON.stringify({
                text,
                videoId: cfg.videoId,
                start,
                end,
                segmentId,
                sourceType: 'highlight',
              }),
            });
            toast('Saved to SRS');
          }
        }
      } catch (err) {
        toast(err.message || 'Error');
      }
      hide();
    });
  }

  function formatTime(s) {
    s = Math.floor(Number(s) || 0);
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return m + ':' + String(sec).padStart(2, '0');
  }

  function appendHighlight(h) {
    const list = document.getElementById('highlight-list');
    const empty = document.getElementById('highlights-empty');
    if (empty) empty.remove();
    if (!list || !h) return;
    const li = document.createElement('li');
    li.className = 'rounded-xl border border-slate-200 bg-amber-50/40 p-4';
    li.dataset.hlId = h.id;
    const cfg = window.LEARNYT || {};
    const link = buildDeepLink({ t: h.start, hl: h.id });
    li.innerHTML =
      '<p class="text-slate-800 leading-relaxed"></p>' +
      '<div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-500">' +
      '<a class="underline hover:text-slate-800" href=""></a>' +
      '<button type="button" class="underline hover:text-slate-800" data-srs-from-hl="">Save to SRS</button>' +
      '<button type="button" class="underline hover:text-red-700" data-delete-hl="">Delete</button>' +
      '</div>';
    li.querySelector('p').textContent = h.text;
    const a = li.querySelector('a');
    a.href = link;
    a.textContent = formatTime(h.start);
    li.querySelector('[data-srs-from-hl]').dataset.srsFromHl = h.id;
    li.querySelector('[data-delete-hl]').dataset.deleteHl = h.id;
    list.prepend(li);
    const count = document.getElementById('highlight-count');
    if (count) count.textContent = String(list.querySelectorAll('[data-hl-id]').length);
  }

  function initHighlights() {
    const cfg = window.LEARNYT || {};
    document.getElementById('highlight-list')?.addEventListener('click', async (e) => {
      const del = e.target.closest('[data-delete-hl]');
      const srsBtn = e.target.closest('[data-srs-from-hl]');
      try {
        if (del) {
          const id = del.getAttribute('data-delete-hl');
          if (useLocalStore()) {
            window.LearnytStorage.deleteHighlight(cfg.videoId, id);
          } else {
            await api('api/highlights.php', {
              method: 'DELETE',
              body: JSON.stringify({ videoId: cfg.videoId, id }),
            });
          }
          del.closest('[data-hl-id]')?.remove();
          toast('Highlight deleted');
        }
        if (srsBtn) {
          const card = srsBtn.closest('[data-hl-id]');
          const text = card?.querySelector('p')?.textContent?.trim() || '';
          if (useLocalStore()) {
            window.LearnytStorage.addSrs({ text, videoId: cfg.videoId, sourceType: 'highlight' });
            toast('Saved to SRS (this browser)');
          } else {
            await api('api/srs.php', {
              method: 'POST',
              body: JSON.stringify({
                text,
                videoId: cfg.videoId,
                sourceType: 'highlight',
              }),
            });
            toast('Saved to SRS');
          }
        }
      } catch (err) {
        toast(err.message || 'Error');
      }
    });

    // Deep-link highlight
    if (cfg.hl) {
      const el = document.querySelector('[data-hl-id="' + CSS.escape(cfg.hl) + '"]');
      el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      el?.classList.add('ring-2', 'ring-amber-400');
    }
    if (cfg.seg) {
      const seg = document.getElementById('seg-' + cfg.seg);
      seg?.classList.add('is-active');
      seg?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else if (cfg.start > 0) {
      highlightSegAt(cfg.start);
    }
  }

  function initCopyDeepLink() {
    document.querySelectorAll('[data-copy-deeplink]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(window.location.href);
          toast('Deep link copied');
        } catch (_) {
          toast('Could not copy');
        }
      });
    });
  }

  function initLessonNav() {
    const bar = document.getElementById('lesson-nav-bar');
    if (bar && 'IntersectionObserver' in window) {
      let sentinel = document.getElementById('lesson-nav-sentinel');
      if (!sentinel) {
        sentinel = document.createElement('div');
        sentinel.id = 'lesson-nav-sentinel';
        sentinel.setAttribute('aria-hidden', 'true');
        sentinel.style.cssText = 'height:1px;margin:0;padding:0;pointer-events:none;';
        bar.parentNode.insertBefore(sentinel, bar);
      }
      const io = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            bar.classList.toggle('is-stuck', !entry.isIntersecting);
          });
        },
        { rootMargin: '-56px 0px 0px 0px', threshold: 0 } // ~site header height
      );
      io.observe(sentinel);
    }

    document.querySelectorAll('.lesson-nav-link').forEach((a) => {
      a.addEventListener('click', (e) => {
        const section = a.getAttribute('data-section');
        if (section === 'review') return;
        e.preventDefault();
        const map = {
          overview: 'overview',
          chrono: 'chrono',
          learn: 'learn',
          transcript: 'transcript',
          diagrams: 'diagrams',
          highlights: 'highlights',
        };
        if (section === 'learn') {
          document.querySelector('[data-toc-mode="learn"]')?.click();
        }
        if (section === 'chrono') {
          document.querySelector('[data-toc-mode="chrono"]')?.click();
        }
        const id = map[section];
        document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  }

  // Learning TOC → quick SRS (double-click or context via long-press not needed; add button on focus)
  function initLearnSrs() {
    document.querySelectorAll('#toc-learn [data-srs-title]').forEach((a) => {
      a.addEventListener('contextmenu', async (e) => {
        e.preventDefault();
        const cfg = window.LEARNYT || {};
        try {
          const payload = {
            text: a.getAttribute('data-srs-title'),
            videoId: cfg.videoId,
            start: Number(a.getAttribute('data-seek') || 0),
            sourceType: 'learn',
            sourceTitle: a.getAttribute('data-srs-title'),
          };
          if (useLocalStore()) {
            window.LearnytStorage.addSrs(payload);
            toast('Learning topic saved to SRS (this browser)');
          } else {
            await api('api/srs.php', { method: 'POST', body: JSON.stringify(payload) });
            toast('Learning topic saved to SRS');
          }
        } catch (err) {
          toast(err.message || 'Error');
        }
      });
    });
  }

  // --- Review page ---
  function initReview() {
    const card = document.getElementById('review-card');
    if (!card) return;
    const itemId = card.dataset.itemId;
    document.getElementById('srs-reveal')?.addEventListener('click', () => {
      const ctx = document.getElementById('srs-context');
      if (ctx) ctx.hidden = !ctx.hidden;
    });
    document.getElementById('srs-ratings')?.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-rate]');
      if (!btn || !itemId) return;
      const rating = btn.getAttribute('data-rate');
      try {
        await api('api/srs.php', {
          method: 'POST',
          body: JSON.stringify({ action: 'rate', id: itemId, rating }),
        });
        toast('Rated: ' + rating);
        setTimeout(() => {
          window.location.href = 'review.php';
        }, 400);
      } catch (err) {
        toast(err.message || 'Error');
      }
    });
  }


  function initMermaid() {
    if (typeof mermaid === 'undefined') return;
    try {
      mermaid.initialize({
        startOnLoad: false,
        theme: 'neutral',
        securityLevel: 'strict',
        flowchart: { htmlLabels: false },
      });
      const nodes = document.querySelectorAll('pre.mermaid');
      if (nodes.length) {
        mermaid.run({ nodes: nodes });
      }
    } catch (err) {
      console.warn('Mermaid init failed', err);
    }
  }

  function initDiagramLightbox() {
    let root = document.getElementById('diagram-lightbox');
    if (!root) {
      root = document.createElement('div');
      root.id = 'diagram-lightbox';
      root.className = 'diagram-lightbox';
      root.hidden = true;
      root.setAttribute('role', 'dialog');
      root.setAttribute('aria-modal', 'true');
      root.setAttribute('aria-labelledby', 'diagram-lightbox-title');
      root.innerHTML =
        '<div class="diagram-lightbox-backdrop" data-lb-close></div>' +
        '<div class="diagram-lightbox-panel" role="document">' +
        '<div class="diagram-lightbox-toolbar">' +
        '<h2 id="diagram-lightbox-title" class="diagram-lightbox-title">Diagram</h2>' +
        '<div class="diagram-lightbox-actions">' +
        '<button type="button" class="diagram-lightbox-btn" data-lb-zoom="out" aria-label="Zoom out">−</button>' +
        '<button type="button" class="diagram-lightbox-btn" data-lb-zoom="reset" aria-label="Reset zoom">100%</button>' +
        '<button type="button" class="diagram-lightbox-btn" data-lb-zoom="in" aria-label="Zoom in">+</button>' +
        '<button type="button" class="diagram-lightbox-btn diagram-lightbox-close" data-lb-close aria-label="Close enlarged diagram">Close</button>' +
        '</div></div>' +
        '<div class="diagram-lightbox-stage-wrap"><div class="diagram-lightbox-stage" id="diagram-lightbox-stage"></div></div>' +
        '</div>';
      document.body.appendChild(root);
    }

    const stage = root.querySelector('#diagram-lightbox-stage');
    const titleEl = root.querySelector('#diagram-lightbox-title');
    const closeBtn = root.querySelector('.diagram-lightbox-close');
    let scale = 1.35;
    let lastFocus = null;

    function applyScale() {
      stage.style.transform = 'scale(' + scale + ')';
    }

    function openFrom(body) {
      const card = body.closest('.diagram-card') || body.closest('figure');
      const cap = card && card.querySelector('figcaption');
      titleEl.textContent = (cap && cap.textContent.trim()) || 'Diagram';
      stage.innerHTML = '';
      const clone = body.cloneNode(true);
      clone.removeAttribute('role');
      clone.removeAttribute('tabindex');
      clone.classList.add('diagram-lightbox-clone');
      stage.appendChild(clone);
      scale = 1.35;
      applyScale();
      lastFocus = document.activeElement;
      root.hidden = false;
      document.body.classList.add('diagram-lightbox-open');
      closeBtn.focus();
    }

    function close() {
      if (root.hidden) return;
      root.hidden = true;
      stage.innerHTML = '';
      document.body.classList.remove('diagram-lightbox-open');
      if (lastFocus && typeof lastFocus.focus === 'function') {
        lastFocus.focus();
      }
    }

    function markBodies() {
      document.querySelectorAll('.diagram-body').forEach((body) => {
        if (body.dataset.lbReady === '1') return;
        body.dataset.lbReady = '1';
        body.setAttribute('role', 'button');
        body.setAttribute('tabindex', '0');
        if (!body.getAttribute('aria-label')) {
          body.setAttribute('aria-label', 'Enlarge diagram');
        }
      });
    }

    markBodies();

    document.addEventListener('click', (e) => {
      const closeHit = e.target.closest('[data-lb-close]');
      if (closeHit && root.contains(closeHit)) {
        close();
        return;
      }
      const zoomBtn = e.target.closest('[data-lb-zoom]');
      if (zoomBtn && root.contains(zoomBtn) && !root.hidden) {
        const mode = zoomBtn.getAttribute('data-lb-zoom');
        if (mode === 'in') scale = Math.min(3, Math.round((scale + 0.25) * 100) / 100);
        else if (mode === 'out') scale = Math.max(0.5, Math.round((scale - 0.25) * 100) / 100);
        else scale = 1.35;
        applyScale();
        return;
      }
      if (e.target.closest('a, button')) return;
      const body = e.target.closest('.diagram-body');
      if (!body || root.contains(body)) return;
      e.preventDefault();
      openFrom(body);
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !root.hidden) {
        e.preventDefault();
        close();
        return;
      }
      const body = e.target.closest && e.target.closest('.diagram-body');
      if (!body || root.contains(body)) return;
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openFrom(body);
      }
    });

    // Hydrated diagrams may appear before this runs; mermaid may replace nodes after.
    setTimeout(markBodies, 400);
    setTimeout(markBodies, 1600);
  }

  function initDiagrams() {
    const cfg = window.LEARNYT || {};
    document.querySelectorAll('[data-diagram-jump]').forEach((a) => {
      a.addEventListener('click', (e) => {
        if (e.metaKey || e.ctrlKey || e.shiftKey) return;
        e.preventDefault();
        const t = Number(a.getAttribute('data-seek') || 0);
        const id = a.getAttribute('data-diagram-jump');
        seekTo(t);
        highlightSegAt(t);
        const el = id ? document.getElementById('dg-' + id) : null;
        // Prefer scrolling to the matching transcript segment when present
        const segId = el && el.getAttribute('data-seg-id');
        const seg = segId ? document.getElementById('seg-' + segId) : null;
        (seg || el)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el?.classList.add('ring-2', 'ring-indigo-400');
      });
    });

    if (cfg.dg) {
      const el = document.getElementById('dg-' + cfg.dg);
      if (el) {
        setTimeout(() => {
          el.scrollIntoView({ behavior: 'smooth', block: 'center' });
          el.classList.add('ring-2', 'ring-indigo-400');
          const t = Number(el.getAttribute('data-start') || cfg.start || 0);
          if (t > 0) highlightSegAt(t);
        }, 400);
      }
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (window.LearnytStorage && window.LEARNYT && window.LEARNYT.hydrateLocal) {
      window.LearnytStorage.hydrateLessonPage();
    }
    initToc();
    initSelectionToolbar();
    initHighlights();
    initCopyDeepLink();
    initLessonNav();
    initLearnSrs();
    initReview();
    initMermaid();
    initDiagrams();
    initDiagramLightbox();
    // If YT API already loaded
    if (typeof YT !== 'undefined' && YT.Player && !ytPlayer) {
      window.onYouTubeIframeAPIReady();
    }
  });
})();
