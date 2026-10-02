/**
 * Learnyt dual storage — browser localStorage drafts + helpers.
 * Keys: learnyt_user_lessons_v1, learnyt_highlights_v1, learnyt_srs_v1
 * No secrets. Seed id sample-spaced-rep cannot be overwritten.
 */
(function (global) {
  'use strict';

  var KEY_LESSONS = 'learnyt_user_lessons_v1';
  var KEY_HIGHLIGHTS = 'learnyt_highlights_v1';
  var KEY_SRS = 'learnyt_srs_v1';
  var SEED_BLOCKLIST = { 'sample-spaced-rep': true };

  function readJson(key, fallback) {
    try {
      var raw = localStorage.getItem(key);
      if (!raw) return fallback;
      var data = JSON.parse(raw);
      return data == null ? fallback : data;
    } catch (e) {
      return fallback;
    }
  }

  function writeJson(key, value) {
    localStorage.setItem(key, JSON.stringify(value));
  }

  function uuid() {
    if (global.crypto && crypto.randomUUID) return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = (Math.random() * 16) | 0;
      var v = c === 'x' ? r : (r & 0x3) | 0x8;
      return v.toString(16);
    });
  }

  function getLessonsMap() {
    var data = readJson(KEY_LESSONS, {});
    return data && typeof data === 'object' ? data : {};
  }

  function saveLessonsMap(map) {
    writeJson(KEY_LESSONS, map);
  }

  function listLocalLessons() {
    var map = getLessonsMap();
    var out = [];
    Object.keys(map).forEach(function (id) {
      var lesson = map[id];
      if (!lesson || !lesson.video) return;
      out.push({
        id: lesson.video.id || id,
        title: lesson.video.title || id,
        channel: lesson.video.channel || '',
        duration: lesson.video.duration || 0,
        thumbnail: lesson.video.thumbnail || '',
        url: lesson.video.url || '',
        segmentCount: (lesson.transcript || []).length,
        chronoCount: (lesson.chronologicalToc || []).length,
        learnCount: (lesson.learningToc || []).length,
        diagramCount: (lesson.diagrams || []).length,
        updatedAt: (lesson.meta && lesson.meta.updatedAt) || null,
        source: 'local',
        lesson: lesson,
      });
    });
    return out;
  }

  function getLocalLesson(id) {
    var map = getLessonsMap();
    return map[id] || null;
  }

  function importLesson(raw) {
    var lesson = typeof raw === 'string' ? JSON.parse(raw) : raw;
    if (!lesson || typeof lesson !== 'object') {
      throw new Error('Invalid JSON');
    }
    if (!lesson.video || !lesson.video.id || !lesson.video.title) {
      throw new Error('Lesson must include video.id and video.title');
    }
    var id = String(lesson.video.id).trim();
    if (!/^[A-Za-z0-9_-]{6,64}$/.test(id)) {
      throw new Error('Invalid video.id');
    }
    if (SEED_BLOCKLIST[id]) {
      throw new Error('Cannot overwrite the seed lesson "' + id + '"');
    }
    if (!Array.isArray(lesson.transcript)) lesson.transcript = [];
    if (!Array.isArray(lesson.chronologicalToc)) lesson.chronologicalToc = [];
    if (!Array.isArray(lesson.learningToc)) lesson.learningToc = [];
    if (!Array.isArray(lesson.diagrams)) lesson.diagrams = [];
    if (!lesson.meta || typeof lesson.meta !== 'object') lesson.meta = {};
    lesson.meta.updatedAt = new Date().toISOString();
    lesson.meta.importedAt = lesson.meta.importedAt || new Date().toISOString();
    var map = getLessonsMap();
    map[id] = lesson;
    saveLessonsMap(map);
    return lesson;
  }

  function getHighlightsStore() {
    return readJson(KEY_HIGHLIGHTS, {});
  }

  function listHighlights(videoId) {
    var store = getHighlightsStore();
    var arr = store[videoId];
    return Array.isArray(arr) ? arr : [];
  }

  function addHighlight(videoId, item) {
    var store = getHighlightsStore();
    if (!store[videoId]) store[videoId] = [];
    var row = {
      id: item.id || uuid(),
      videoId: videoId,
      text: item.text,
      start: item.start || 0,
      end: item.end != null ? item.end : item.start || 0,
      segmentId: item.segmentId || null,
      note: item.note || null,
      srsStatus: 'none',
      created: new Date().toISOString(),
    };
    store[videoId].unshift(row);
    writeJson(KEY_HIGHLIGHTS, store);
    return row;
  }

  function deleteHighlight(videoId, id) {
    var store = getHighlightsStore();
    var arr = store[videoId] || [];
    store[videoId] = arr.filter(function (h) {
      return h.id !== id;
    });
    writeJson(KEY_HIGHLIGHTS, store);
  }

  function listSrs() {
    var data = readJson(KEY_SRS, { items: [] });
    return Array.isArray(data.items) ? data.items : [];
  }

  function addSrs(item) {
    var data = readJson(KEY_SRS, { items: [] });
    if (!Array.isArray(data.items)) data.items = [];
    var row = {
      id: item.id || uuid(),
      text: item.text,
      videoId: item.videoId || null,
      start: item.start != null ? item.start : null,
      end: item.end != null ? item.end : null,
      segmentId: item.segmentId || null,
      sourceType: item.sourceType || 'highlight',
      sourceTitle: item.sourceTitle || null,
      note: item.note || null,
      created: new Date().toISOString(),
      lastReviewed: null,
      nextReview: new Date().toISOString(),
      interval: 0,
      ease: 2.5,
      reviewCount: 0,
      history: [],
    };
    data.items.push(row);
    writeJson(KEY_SRS, data);
    return row;
  }

  function syncPayload() {
    var lessons = [];
    var map = getLessonsMap();
    Object.keys(map).forEach(function (id) {
      if (SEED_BLOCKLIST[id]) return;
      lessons.push(map[id]);
    });
    var hlStore = getHighlightsStore();
    var highlightsByVideo = {};
    lessons.forEach(function (lesson) {
      var id = lesson.video && lesson.video.id;
      if (id && Array.isArray(hlStore[id])) {
        highlightsByVideo[id] = hlStore[id];
      }
    });
    var localIds = {};
    lessons.forEach(function (l) {
      if (l.video && l.video.id) localIds[l.video.id] = true;
    });
    var srsItems = listSrs().filter(function (item) {
      return item.videoId && localIds[item.videoId];
    });
    return { lessons: lessons, highlightsByVideo: highlightsByVideo, srsItems: srsItems };
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function formatTime(seconds) {
    var s = Math.max(0, Math.floor(Number(seconds) || 0));
    var m = Math.floor(s / 60);
    var sec = s % 60;
    return m + ':' + String(sec).padStart(2, '0');
  }

  function badgeHtml(source) {
    if (source === 'seed') {
      return '<span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-800 px-2 py-0.5 text-[10px] font-semibold">Sample</span>';
    }
    if (source === 'demo') {
      return '<span class="inline-flex items-center rounded-full bg-sky-50 text-sky-800 px-2 py-0.5 text-[10px] font-semibold">Synced demo</span>';
    }
    return '<span class="inline-flex items-center rounded-full bg-amber-50 text-amber-900 px-2 py-0.5 text-[10px] font-semibold">On this browser</span>';
  }

  function mergeLessonLists(serverLessons, seedIds) {
    var seedSet = {};
    (seedIds || []).forEach(function (id) {
      seedSet[id] = true;
    });
    var byId = {};
    (serverLessons || []).forEach(function (item) {
      var src = item.source || (seedSet[item.id] ? 'seed' : 'demo');
      byId[item.id] = Object.assign({}, item, { source: src });
    });
    listLocalLessons().forEach(function (item) {
      if (byId[item.id] && byId[item.id].source === 'seed') return;
      if (byId[item.id] && byId[item.id].source === 'demo') {
        // Keep demo as canonical for listing, but note local draft exists — still show demo badge
        return;
      }
      byId[item.id] = item;
    });
    return Object.keys(byId)
      .map(function (k) {
        return byId[k];
      })
      .sort(function (a, b) {
        return String(b.updatedAt || '').localeCompare(String(a.updatedAt || ''));
      });
  }

  function renderHomeList() {
    var root = document.getElementById('lesson-list');
    if (!root) return;
    var home = global.LEARNYT_HOME || {};
    var server = home.serverLessons || [];
    try {
      if (root.dataset.serverLessons) {
        server = JSON.parse(root.dataset.serverLessons);
      }
    } catch (e) {}
    var seedIds = home.seedIds || [];
    try {
      if (root.dataset.seedIds) seedIds = JSON.parse(root.dataset.seedIds);
    } catch (e2) {}

    var merged = mergeLessonLists(server, seedIds);
    var label = document.getElementById('lesson-count-label');
    if (label) {
      label.textContent = merged.length + ' module' + (merged.length === 1 ? '' : 's');
    }
    if (merged.length === 0) {
      root.innerHTML =
        '<li class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500 text-sm">No lessons yet. Import JSON below or open the sample after refresh.</li>';
      return;
    }
    root.innerHTML = merged
      .map(function (lesson) {
        var thumb = lesson.thumbnail
          ? '<img src="' +
            escapeHtml(lesson.thumbnail) +
            '" alt="" class="hidden sm:block h-16 w-28 rounded-md object-cover bg-slate-100 shrink-0" loading="lazy" width="112" height="64">'
          : '<div class="hidden sm:flex h-16 w-28 rounded-md bg-slate-100 items-center justify-center text-slate-400 text-xs shrink-0">No thumb</div>';
        var meta =
          escapeHtml(lesson.channel || 'Unknown channel') +
          (lesson.duration ? ' · ' + formatTime(lesson.duration) : '');
        var counts =
          (lesson.segmentCount || 0) +
          ' segments · ' +
          (lesson.chronoCount || 0) +
          ' chrono · ' +
          (lesson.learnCount || 0) +
          ' learn topics' +
          (lesson.diagramCount ? ' · ' + lesson.diagramCount + ' diagrams' : '');
        return (
          '<li><a href="lesson.php?v=' +
          encodeURIComponent(lesson.id) +
          '" class="group block rounded-xl border border-slate-200 bg-white p-5 hover:border-slate-300 hover:shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900">' +
          '<div class="flex gap-4">' +
          thumb +
          '<div class="min-w-0 flex-1">' +
          '<div class="flex flex-wrap items-center gap-2 mb-1">' +
          badgeHtml(lesson.source) +
          '</div>' +
          '<h3 class="font-medium text-slate-900 group-hover:text-slate-700 truncate">' +
          escapeHtml(lesson.title) +
          '</h3>' +
          '<p class="mt-1 text-sm text-slate-500">' +
          meta +
          '</p>' +
          '<p class="mt-2 text-xs text-slate-400">' +
          counts +
          '</p>' +
          '</div></div></a></li>'
        );
      })
      .join('');
  }

  function initImport() {
    var btn = document.getElementById('btn-import-lesson');
    if (!btn) return;
    var status = document.getElementById('import-status');
    var fileInput = document.getElementById('import-file');
    var area = document.getElementById('import-json');

    function setStatus(msg, ok) {
      if (!status) return;
      status.textContent = msg;
      status.className = 'text-sm self-center ' + (ok ? 'text-emerald-700' : 'text-red-600');
    }

    if (fileInput) {
      fileInput.addEventListener('change', function () {
        var f = fileInput.files && fileInput.files[0];
        if (!f) return;
        var reader = new FileReader();
        reader.onload = function () {
          if (area) area.value = String(reader.result || '');
        };
        reader.readAsText(f);
      });
    }

    btn.addEventListener('click', function () {
      try {
        var raw = area ? area.value.trim() : '';
        if (!raw) {
          setStatus('Paste or upload JSON first', false);
          return;
        }
        var lesson = importLesson(raw);
        setStatus('Saved “' + lesson.video.title + '” to this browser', true);
        renderHomeList();
        if (global.LearnytToast) global.LearnytToast('Lesson imported');
      } catch (err) {
        setStatus(err.message || 'Import failed', false);
      }
    });
  }

  function initSync() {
    var btn = document.getElementById('btn-sync-demo');
    var dialog = document.getElementById('sync-dialog');
    var form = document.getElementById('sync-form');
    if (!btn || !dialog || !form) return;

    var status = document.getElementById('sync-status');
    var errEl = document.getElementById('sync-dialog-error');
    var pwInput = document.getElementById('sync-password');
    var cancelBtn = document.getElementById('sync-cancel');

    function openDialog() {
      if (errEl) {
        errEl.classList.add('hidden');
        errEl.textContent = '';
      }
      if (pwInput) pwInput.value = '';
      if (typeof dialog.showModal === 'function') dialog.showModal();
      else dialog.setAttribute('open', 'open');
      if (pwInput) setTimeout(function () { pwInput.focus(); }, 50);
    }

    function closeDialog() {
      if (typeof dialog.close === 'function') dialog.close();
      else dialog.removeAttribute('open');
    }

    btn.addEventListener('click', openDialog);
    if (cancelBtn) cancelBtn.addEventListener('click', closeDialog);

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      doSync(pwInput ? pwInput.value : '');
    });

    async function doSync(password) {
      if (errEl) {
        errEl.classList.add('hidden');
        errEl.textContent = '';
      }
      var payload = syncPayload();
      if (!payload.lessons.length) {
        if (errEl) {
          errEl.textContent = 'No browser-local lessons to sync. Import a lesson first.';
          errEl.classList.remove('hidden');
        }
        return;
      }
      try {
        var res = await fetch('api/sync_demo.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({
            password: password,
            lessons: payload.lessons,
            highlightsByVideo: payload.highlightsByVideo,
            srsItems: payload.srsItems,
          }),
        });
        var data = await res.json().catch(function () {
          return {};
        });
        if (!res.ok) {
          if (errEl) {
            errEl.textContent = data.error || 'Sync failed';
            errEl.classList.remove('hidden');
          }
          return;
        }
        closeDialog();
        if (status) {
          status.classList.remove('hidden');
          status.className = 'text-sm text-emerald-700 mb-8';
          var n = (data.result && data.result.lessonCount) || payload.lessons.length;
          status.textContent =
            (data.message || 'Synced to demo.') +
            ' (' + n + ' lesson' + (n === 1 ? '' : 's') + '). Any visitor of this URL can open them.';
        }
        if (global.LearnytToast) global.LearnytToast('Synced to demo');
        try {
          var listRes = await fetch('api/lessons.php', { credentials: 'same-origin' });
          var listData = await listRes.json();
          if (listData.lessons) {
            global.LEARNYT_HOME = global.LEARNYT_HOME || {};
            global.LEARNYT_HOME.serverLessons = listData.lessons;
            if (listData.seedIds) global.LEARNYT_HOME.seedIds = listData.seedIds;
            var root = document.getElementById('lesson-list');
            if (root) {
              root.dataset.serverLessons = JSON.stringify(listData.lessons || []);
              if (listData.seedIds) root.dataset.seedIds = JSON.stringify(listData.seedIds);
            }
          }
        } catch (ignore) {}
        renderHomeList();
      } catch (err) {
        if (errEl) {
          errEl.textContent = err.message || 'Network error';
          errEl.classList.remove('hidden');
        }
      }
    }
  }

  /**
   * Client-side render for lessons that exist only in localStorage.
   */
  function hydrateLessonPage() {
    var cfg = global.LEARNYT || {};
    if (!cfg.hydrateLocal) return false;
    var status = document.getElementById('local-hydrate-status');
    var lesson = getLocalLesson(cfg.videoId);
    if (!lesson) {
      if (status) {
        status.className = 'rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900';
        status.innerHTML =
          'Not found on this server or in browser storage. <a class="underline" href="index.php">Back to lessons</a>';
      }
      return false;
    }
    cfg.source = 'local';
    cfg.storage = 'local';
    if (lesson.video && lesson.video.embedId) cfg.embedId = lesson.video.embedId;
    else if (lesson.video && /^[A-Za-z0-9_-]{11}$/.test(lesson.video.id)) cfg.embedId = lesson.video.id;

    var titleEl = document.querySelector('h1');
    if (titleEl && lesson.video.title) titleEl.textContent = lesson.video.title;
    document.title = (lesson.video.title || cfg.videoId) + ' · Learnyt';

    // Overview
    var overview = document.querySelector('#overview-panel p.prose-lesson, #overview-panel p.text-slate-600');
    var summary = (lesson.meta && lesson.meta.summary) || 'Local lesson loaded from this browser.';
    var ov = document.getElementById('overview-panel');
    if (ov) {
      var objectives = lesson.meta && Array.isArray(lesson.meta.learningObjectives) ? lesson.meta.learningObjectives : [];
      ov.innerHTML =
        '<h2 class="text-lg font-semibold text-slate-900 mb-3">Overview</h2>' +
        '<p class="prose-lesson text-slate-700 leading-relaxed">' +
        escapeHtml(summary) +
        '</p>' +
        (objectives.length
          ? '<h3 class="mt-6 text-sm font-semibold text-slate-900">Learning objectives</h3><ul class="mt-2 list-disc pl-5 space-y-1 text-slate-700">' +
            objectives
              .map(function (o) {
                return '<li>' + escapeHtml(o) + '</li>';
              })
              .join('') +
            '</ul>'
          : '');
    }

    // TOC chrono
    var chronoUl = document.querySelector('#toc-chrono ul');
    if (chronoUl) {
      chronoUl.innerHTML = (lesson.chronologicalToc || [])
        .map(function (item) {
          return (
            '<li><a href="lesson.php?v=' +
            encodeURIComponent(cfg.videoId) +
            '&t=' +
            Math.floor(item.start || 0) +
            '&toc=chrono&seg=' +
            encodeURIComponent(item.id || '') +
            '" class="toc-link flex gap-2 rounded-md px-2 py-1.5 hover:bg-slate-50 text-slate-700" data-seek="' +
            (item.start || 0) +
            '" data-toc-id="' +
            escapeHtml(item.id || '') +
            '"><span class="tabular-nums text-slate-400 shrink-0 w-12">' +
            formatTime(item.start || 0) +
            '</span><span>' +
            escapeHtml(item.title || 'Section') +
            '</span></a></li>'
          );
        })
        .join('') || '<li class="text-slate-500 px-2 py-2">No chronological TOC.</li>';
    }

    // TOC learn (flat)
    var learnUl = document.querySelector('#toc-learn ul');
    if (learnUl) {
      learnUl.innerHTML = (lesson.learningToc || [])
        .map(function (item) {
          var src = (item.sources && item.sources[0]) || {};
          var st = src.start || 0;
          return (
            '<li><a href="lesson.php?v=' +
            encodeURIComponent(cfg.videoId) +
            '&t=' +
            Math.floor(st) +
            '&toc=learn&seg=' +
            encodeURIComponent(item.id || '') +
            '" class="toc-link block rounded-md px-2 py-1.5 hover:bg-slate-50 text-slate-700" data-seek="' +
            st +
            '" data-toc-id="' +
            escapeHtml(item.id || '') +
            '" data-srs-title="' +
            escapeHtml(item.title || '') +
            '" data-source-type="learn">' +
            escapeHtml(item.title || 'Topic') +
            '<span class="block text-xs text-slate-400 mt-0.5">' +
            formatTime(st) +
            '</span></a></li>'
          );
        })
        .join('') || '<li class="text-slate-500 px-2 py-2">No learning TOC.</li>';
    }

    // Transcript + inline diagrams
    var list = document.getElementById('transcript-list');
    if (list) {
      var bySeg = {};
      (lesson.diagrams || []).forEach(function (dg) {
        var sid = dg.segmentId || '';
        if (!sid) return;
        if (!bySeg[sid]) bySeg[sid] = [];
        bySeg[sid].push(dg);
      });
      var html = '';
      (lesson.transcript || []).forEach(function (seg) {
        var sid = seg.id || '';
        html +=
          '<div class="transcript-seg px-4 py-3 sm:px-5" id="seg-' +
          escapeHtml(sid) +
          '" data-seg-id="' +
          escapeHtml(sid) +
          '" data-start="' +
          (seg.start || 0) +
          '" data-end="' +
          (seg.end || seg.start || 0) +
          '">' +
          '<button type="button" class="text-xs tabular-nums text-slate-400 hover:text-slate-700" data-seek="' +
          (seg.start || 0) +
          '">' +
          formatTime(seg.start || 0) +
          '</button>' +
          '<p class="seg-text mt-1 text-slate-800 leading-relaxed select-text">' +
          escapeHtml(seg.text || '') +
          '</p></div>';
        (bySeg[sid] || []).forEach(function (dg) {
          html += '<div class="px-3 sm:px-4 py-2 bg-slate-50/80">' + diagramCardHtml(dg, cfg.videoId, 'inline') + '</div>';
        });
      });
      list.innerHTML = html || '<p class="p-6 text-slate-500">No transcript segments.</p>';
    }

    // Diagram gallery
    var gallery = document.getElementById('diagram-gallery');
    var diagramsSection = document.getElementById('diagrams');
    if (diagramsSection) {
      var dgs = lesson.diagrams || [];
      var countEl = diagramsSection.querySelector('.text-xs.text-slate-400');
      if (countEl) countEl.textContent = String(dgs.length);
      if (!dgs.length) {
        diagramsSection.innerHTML =
          '<div class="flex items-center justify-between mb-4"><h2 class="text-lg font-semibold text-slate-900">Diagrams &amp; infographics</h2><span class="text-xs text-slate-400">0</span></div>' +
          '<p class="text-sm text-slate-500 rounded-xl border border-dashed border-slate-300 bg-white p-6">No diagrams in this lesson.</p>';
      } else {
        var ghtml = dgs
          .map(function (dg) {
            return diagramCardHtml(dg, cfg.videoId, 'gallery');
          })
          .join('');
        diagramsSection.innerHTML =
          '<div class="flex items-center justify-between mb-4"><h2 class="text-lg font-semibold text-slate-900">Diagrams &amp; infographics</h2><span class="text-xs text-slate-400">' +
          dgs.length +
          '</span></div><div class="space-y-4" id="diagram-gallery">' +
          ghtml +
          '</div>';
      }
    }

    // Highlights from local store
    var hlList = document.getElementById('highlight-list');
    if (hlList) {
      var hls = listHighlights(cfg.videoId);
      var hc = document.getElementById('highlight-count');
      if (hc) hc.textContent = String(hls.length);
      if (!hls.length) {
        hlList.innerHTML =
          '<li class="text-slate-500 text-sm" id="highlights-empty">No highlights yet. Select transcript text and choose Highlight.</li>';
      } else {
        hlList.innerHTML = hls
          .map(function (h) {
            return (
              '<li class="rounded-xl border border-slate-200 bg-amber-50/40 p-4" data-hl-id="' +
              escapeHtml(h.id) +
              '"><p class="text-slate-800 leading-relaxed">' +
              escapeHtml(h.text) +
              '</p><div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-500">' +
              '<a class="underline" href="lesson.php?v=' +
              encodeURIComponent(cfg.videoId) +
              '&t=' +
              Math.floor(h.start || 0) +
              '&hl=' +
              encodeURIComponent(h.id) +
              '">' +
              formatTime(h.start || 0) +
              '</a>' +
              '<button type="button" class="underline" data-srs-from-hl="' +
              escapeHtml(h.id) +
              '">Save to SRS</button>' +
              '<button type="button" class="underline hover:text-red-700" data-delete-hl="' +
              escapeHtml(h.id) +
              '">Delete</button></div></li>'
            );
          })
          .join('');
      }
    }

    // Video iframe (shell may start at about:blank until hydrate)
    var embed = cfg.embedId || '';
    if (lesson.video) {
      if (lesson.video.embedId && /^[A-Za-z0-9_-]{11}$/.test(String(lesson.video.embedId))) {
        embed = String(lesson.video.embedId);
        cfg.embedId = embed;
      } else if (/^[A-Za-z0-9_-]{11}$/.test(String(lesson.video.id || ''))) {
        embed = String(lesson.video.id);
        cfg.embedId = embed;
      }
    }
    var frameWrap = document.getElementById('overview');
    var iframe = document.getElementById('yt-player');
    if (!iframe && frameWrap) {
      iframe = document.createElement('iframe');
      iframe.id = 'yt-player';
      iframe.className = 'h-full w-full';
      iframe.title = 'YouTube video';
      iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
      iframe.setAttribute('allowfullscreen', 'allowfullscreen');
      frameWrap.insertBefore(iframe, frameWrap.firstChild);
    }
    var placeholder = document.getElementById('yt-embed-placeholder');
    if (iframe && embed && /^[A-Za-z0-9_-]{11}$/.test(embed)) {
      var start = cfg.start || 0;
      iframe.setAttribute('data-embed-id', embed);
      iframe.src =
        'https://www.youtube.com/embed/' +
        encodeURIComponent(embed) +
        '?enablejsapi=1&rel=0&modestbranding=1' +
        (start ? '&start=' + Math.floor(start) : '');
      if (placeholder && placeholder.parentNode) {
        placeholder.parentNode.removeChild(placeholder);
      }
      // Re-bind YT API player if available
      if (typeof window.onYouTubeIframeAPIReady === 'function' && typeof YT !== 'undefined' && YT.Player) {
        try {
          window.onYouTubeIframeAPIReady();
        } catch (ignore) {}
      }
    }

    if (status) {
      status.className = 'rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950';
      status.textContent =
        'Loaded from this browser (localStorage). Sync to demo to publish under data/demo/ for all visitors.';
    }
    return true;
  }

  function diagramCardHtml(dg, videoId, context) {
    var id = dg.id || '';
    var title = dg.title || 'Diagram';
    var caption = dg.caption || '';
    var start = dg.start || 0;
    var kind = dg.kind || 'mermaid';
    var wrap =
      context === 'inline'
        ? 'diagram-card diagram-inline my-4 rounded-xl border border-indigo-200 bg-indigo-50/40 p-4 sm:p-5'
        : 'diagram-card rounded-xl border border-slate-200 bg-white p-4 sm:p-5';
    var body = '';
    if (kind === 'mermaid' && dg.mermaid) {
      body = '<pre class="mermaid text-sm leading-normal">' + escapeHtml(dg.mermaid) + '</pre>';
    } else if (kind === 'svg' && dg.svg) {
      body = '<div class="diagram-svg max-w-full text-slate-800">' + dg.svg + '</div>';
    } else if (kind === 'image' && dg.imageUrl) {
      body = '<img src="' + escapeHtml(dg.imageUrl) + '" alt="' + escapeHtml(title) + '" class="max-w-full h-auto mx-auto" loading="lazy">';
    } else {
      body = '<p class="text-sm text-slate-500">No diagram payload.</p>';
    }
    return (
      '<figure id="dg-' +
      escapeHtml(id) +
      '" class="' +
      wrap +
      '" data-diagram-id="' +
      escapeHtml(id) +
      '" data-start="' +
      start +
      '" data-seg-id="' +
      escapeHtml(dg.segmentId || '') +
      '"><div class="flex flex-wrap items-start justify-between gap-2 mb-3"><div class="min-w-0">' +
      (context === 'inline'
        ? '<p class="text-xs font-semibold uppercase tracking-wide text-indigo-600 mb-1">Visual break</p>'
        : '') +
      '<figcaption class="font-medium text-slate-900">' +
      escapeHtml(title) +
      '</figcaption>' +
      (caption ? '<p class="mt-1 text-sm text-slate-600">' + escapeHtml(caption) + '</p>' : '') +
      '</div><a href="lesson.php?v=' +
      encodeURIComponent(videoId) +
      '&t=' +
      Math.floor(start) +
      '&dg=' +
      encodeURIComponent(id) +
      '" class="shrink-0 text-xs font-medium text-slate-600 underline" data-seek="' +
      start +
      '" data-diagram-jump="' +
      escapeHtml(id) +
      '">Jump to ' +
      formatTime(start) +
      '</a></div><div class="diagram-body overflow-x-auto rounded-lg bg-white border border-slate-100 p-3">' +
      body +
      '</div></figure>'
    );
  }

  function isLocalLesson(videoId) {
    return !!getLocalLesson(videoId);
  }

  function usesLocalPersistence(cfg) {
    if (!cfg) return false;
    if (cfg.storage === 'local' || cfg.hydrateLocal) return true;
    if (cfg.source === 'local') return true;
    // Local draft that hasn't been synced yet
    if (cfg.videoId && getLocalLesson(cfg.videoId) && cfg.source !== 'seed' && cfg.source !== 'demo') {
      return true;
    }
    return false;
  }

  global.LearnytStorage = {
    KEY_LESSONS: KEY_LESSONS,
    importLesson: importLesson,
    getLocalLesson: getLocalLesson,
    listLocalLessons: listLocalLessons,
    listHighlights: listHighlights,
    addHighlight: addHighlight,
    deleteHighlight: deleteHighlight,
    addSrs: addSrs,
    listSrs: listSrs,
    syncPayload: syncPayload,
    renderHomeList: renderHomeList,
    initImport: initImport,
    initSync: initSync,
    hydrateLessonPage: hydrateLessonPage,
    isLocalLesson: isLocalLesson,
    usesLocalPersistence: usesLocalPersistence,
    SEED_BLOCKLIST: SEED_BLOCKLIST,
  };

  document.addEventListener('DOMContentLoaded', function () {
    renderHomeList();
    initImport();
    initSync();
  });
})(window);
