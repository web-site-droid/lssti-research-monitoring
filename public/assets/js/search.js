(function () {
  var apiUrl = 'index.php?route=search/api';
  var suggestUrl = 'index.php?route=search/suggest';
  var dashboardSearchActive = false;
  var searchRequest = 0;

  function debounce(fn, ms) {
    var t;
    return function () {
      var args = arguments;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(null, args); }, ms);
    };
  }

  function fieldValue(id) {
    var el = document.getElementById(id);
    return el ? el.value : '';
  }

  function gatherFilters() {
    var filters = {
      q: fieldValue('q'),
      full_text: '1',
      program_id: fieldValue('program_id'),
      school_year: fieldValue('school_year'),
      adviser_user_id: fieldValue('adviser_user_id'),
      study_type: fieldValue('study_type'),
      keyword: fieldValue('keyword'),
      date_field: fieldValue('date_field') || 'submission',
      date_from: fieldValue('date_from'),
      date_to: fieldValue('date_to'),
      sort: fieldValue('sort') || 'relevance'
    };
    var q = document.getElementById('q');
    var bar = q && q.closest('[data-find-bar]');
    if (bar) {
      function value(name) { var field = bar.querySelector('[data-find-' + name + ']'); return field ? field.value : ''; }
      filters.program_code = value('dept');
      filters.study_type = value('type');
      filters.school_year = value('year');
      filters.school_year_from = value('from');
      filters.school_year_to = value('to');
      filters.sort = value('sort') === 'newest' ? 'date' : value('sort');
    }
    return filters;
  }

  function starMarks(avg) {
    var n = Math.round(Number(avg) || 0);
    if (n < 0) n = 0;
    if (n > 5) n = 5;
    var marks = '';
    for (var i = 1; i <= 5; i++) {
      marks += i <= n ? '★' : '☆';
    }
    return marks;
  }

  function searchWords(query) {
    return (query || '').trim().split(/\s+/).filter(function (word) {
      return word.length > 0;
    });
  }

  function isTitleSearch(query, title) {
    var q = (query || '').trim().toLowerCase();
    var t = (title || '').trim().toLowerCase();
    return q.length >= 8 && t.indexOf(q) !== -1;
  }

  function highlight(text, query) {
    var raw = String(text || '');
    var words = searchWords(query).sort(function (a, b) { return b.length - a.length; });
    if (!words.length) return escapeHtml(raw);
    var pattern = words.map(function (word) { return word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }).join('|');
    var result = '', last = 0;
    raw.replace(new RegExp(pattern, 'ig'), function (match, offset) {
      result += escapeHtml(raw.slice(last, offset)) + '<mark class="search-term">' + escapeHtml(match) + '</mark>';
      last = offset + match.length;
      return match;
    });
    return result + escapeHtml(raw.slice(last));
  }

  function snippetText(text, query) {
    var clean = (text || '').replace(/\s+/g, ' ').trim();
    if (!clean) return '';
    var lower = clean.toLowerCase();
    var at = -1;
    searchWords(query).some(function (word) {
      at = lower.indexOf(word.toLowerCase());
      return at >= 0;
    });
    var start = at > 80 ? Math.max(0, at - 60) : 0;
    var slice = clean.slice(start, start + 280);
    if (start > 0) slice = '… ' + slice.replace(/^\S+\s/, '');
    if (start + 280 < clean.length) slice += '…';
    return slice;
  }

  function snippet(text, query) {
    return highlight(snippetText(text, query), query);
  }

  var catalog = {};

  function saveControl(item) {
    var logged = document.getElementById('results').getAttribute('data-logged') === '1';
    if (!logged) {
      return '<a class="scholar-save" href="index.php?route=login">☆ Star</a>';
    }
    return '<button type="button" class="scholar-save' + (item.saved ? ' is-saved' : '') + '" aria-pressed="' + (!!item.saved) + '" data-save="' + item.id + '">' +
      (item.saved ? '★ Starred' : '☆ Star') + '</button>';
  }

  function updateDashboardStar(id, saved) {
    var grid = document.getElementById('dept-paper-cards');
    if (!grid) return;
    grid.querySelectorAll('.paper-card').forEach(function (card) {
      if (card.getAttribute('data-study-id') !== String(id)) return;
      card.setAttribute('data-starred', saved ? '1' : '0');
      var button = card.querySelector('[data-star-study]');
      if (button) { button.textContent = saved ? '★ Starred' : '☆ Star'; button.setAttribute('aria-pressed', saved ? 'true' : 'false'); }
    });
    if (!dashboardSearchActive && grid._applyTableFilter) grid._applyTableFilter();
  }

  function openDialog(dialog) {
    if (!dialog) return;
    if (dialog.parentElement !== document.body) document.body.appendChild(dialog);
    if (!dialog.open) dialog.showModal();
  }

  function closeDialog(dialog) {
    if (dialog && dialog.open) dialog.close();
  }

  function fillCite(item) {
    var body = document.getElementById('cite-body');
    body.innerHTML = '';
    var citations = item.citations || {};
    Object.keys(citations).forEach(function (label) {
      var row = document.createElement('div');
      row.className = 'cite-row';
      var name = document.createElement('strong');
      name.textContent = label;
      var text = document.createElement('p');
      text.textContent = citations[label];
      var copy = document.createElement('button');
      copy.type = 'button';
      copy.className = 'btn btn-secondary btn-sm';
      copy.textContent = 'Copy';
      copy.addEventListener('click', function () {
        if (navigator.clipboard) {
          navigator.clipboard.writeText(citations[label]);
          copy.textContent = 'Copied';
        }
      });
      row.appendChild(name);
      row.appendChild(text);
      row.appendChild(copy);
      body.appendChild(row);
    });
  }

  function fillQuick(item) {
    var body = document.getElementById('quick-body');
    body.innerHTML = '';
    var title = document.createElement('h3');
    title.textContent = item.title || '';
    var byline = document.createElement('p');
    byline.className = 'scholar-meta';
    byline.textContent = [item.authors, item.program_code, item.school_year, item.study_type_label].filter(Boolean).join(' · ');
    var abstractLabel = document.createElement('h4');
    abstractLabel.textContent = 'Abstract';
    var abstract = document.createElement('p');
    abstract.textContent = item.abstract || 'No abstract is listed for this study.';
    body.appendChild(title);
    body.appendChild(byline);
    body.appendChild(abstractLabel);
    body.appendChild(abstract);
    if (item.objectives) {
      var heading = document.createElement('h4');
      heading.textContent = 'Research objectives';
      var objectives = document.createElement('p');
      objectives.textContent = item.objectives;
      body.appendChild(heading);
      body.appendChild(objectives);
    }
  }

  function bindScholar(box) {
    box.querySelectorAll('[data-cite]').forEach(function (button) {
      button.addEventListener('click', function () {
        var item = catalog[button.getAttribute('data-cite')];
        if (!item) return;
        fillCite(item);
        openDialog(document.getElementById('cite-modal'));
      });
    });
    box.querySelectorAll('[data-quick]').forEach(function (button) {
      button.addEventListener('click', function () {
        var item = catalog[button.getAttribute('data-quick')];
        if (!item) return;
        fillQuick(item);
        openDialog(document.getElementById('quick-modal'));
      });
    });
    box.querySelectorAll('[data-save]').forEach(function (button) {
      button.addEventListener('click', function () {
        if (button.disabled) return;
        button.disabled = true;
        var body = new FormData();
        body.append('_csrf', box.getAttribute('data-csrf') || '');
        body.append('study_id', button.getAttribute('data-save'));
        body.append('ajax', '1');
        fetch('index.php?route=search/bookmark', { method: 'POST', body: body, credentials: 'same-origin' })
          .then(function (r) { if (!r.ok) throw new Error('Star failed'); return r.json(); })
          .then(function (data) {
            if (!data || !data.ok) return;
            var item = catalog[button.getAttribute('data-save')];
            if (item) item.saved = !!data.saved;
            button.classList.toggle('is-saved', !!data.saved);
            button.setAttribute('aria-pressed', data.saved ? 'true' : 'false');
            button.textContent = data.saved ? '★ Starred' : '☆ Star';
            updateDashboardStar(button.getAttribute('data-save'), !!data.saved);
            if (box.getAttribute('data-can-rate') === '1') {
              renderResults(Object.keys(catalog).map(function (id) { return catalog[id]; }), false, (document.getElementById('q') || {}).value || '');
            }
          }).catch(function () { button.textContent = 'Try starring again'; }).finally(function () { button.disabled = false; });
      });
    });
  }

  function renderResults(items, featured, query) {
    var box = document.getElementById('results');
    if (box.getAttribute('data-can-rate') === '1') items.sort(function (a, b) { return Number(!!b.saved) - Number(!!a.saved); });
    var main = document.querySelector('.scholar-main');
    if (featured) {
      if (main) main.classList.remove('is-searching');
      box.innerHTML = '';
      return;
    }
    if (main) main.classList.add('is-searching');
    if (!items.length) {
      box.innerHTML = featured
        ? '<p>No published studies yet.</p>'
        : '<p class="scholar-count">No published studies match those words.</p>';
      return;
    }
    if (!featured) {
      var count = items.length === 1 ? '1 study' : items.length + ' studies';
      box.innerHTML = '<p class="scholar-count">' + count + '</p>' + items.map(function (item) {
        var text = item.search_text || item.abstract || item.objectives || '';
        var meta = [item.authors, item.program_code, item.school_year, item.study_type_label].filter(Boolean).join(' · ');
        return '<article class="scholar-hit" data-study="' + item.id + '">' +
          '<div><h3>' + (item.full_access ? '<a href="index.php?route=reader&id=' + item.id + '">' : '') + highlight(item.title, query) + (item.full_access ? '</a>' : '') + '</h3>' +
          '<p class="scholar-meta">' + highlight(meta, query) + '</p>' +
          '<p class="publication-timestamp">Published: ' + escapeHtml(item.published_at_label || 'Publication date unavailable') + '</p>' +
          '<p class="scholar-snippet">' + snippet(text, query) + '</p>' +
          '<p class="scholar-actions">' + saveControl(item) +
          '<button type="button" class="scholar-cite" data-cite="' + item.id + '">Cite</button></p></div>' +
          '<div class="scholar-tools"><span class="scholar-file-icons">' +
          (item.full_access && item.version_id
            ? '<a class="scholar-file" href="index.php?route=file/manuscript&study_id=' + item.id + '&version_id=' + item.version_id + '&inline=1" target="_blank" rel="noopener" aria-label="Open PDF" title="PDF">' +
              '<svg class="btn-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h2"/><path d="M8 17h8"/><path d="M8 9h1"/></svg></a>'
            : '<span class="scholar-file is-locked" title="' + escapeHtml(item.access_note || 'Abstract only') + '" aria-label="Abstract only">' +
              '<svg class="btn-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>') +
          '<button type="button" class="scholar-file scholar-quick" data-quick="' + item.id + '" aria-label="Quick read" title="Quick read">' +
          '<svg class="btn-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a1 1 0 0 1 0-5H20"/></svg></button></span>' +
          (!item.full_access && item.access_note ? '<p class="scholar-access-note">' + escapeHtml(item.access_note) + '</p>' : '') +
          '</div></article>';
      }).join('');
      catalog = {};
      items.forEach(function (item) { catalog[String(item.id)] = item; });
      bindScholar(box);
      return;
    }
    var canRate = box.getAttribute('data-can-rate') === '1';
    box.innerHTML = items.map(function (item) {
      var mine = Number(item.my_stars) || 0;
      var shown = mine > 0 ? mine : Math.round(Number(item.star_avg) || 0);
      var buttons = '';
      for (var n = 5; n >= 1; n--) {
        var on = n <= shown ? ' is-on' : '';
        if (canRate) {
          buttons += '<button type="button" class="star-btn' + on + '" data-study="' + item.id + '" data-stars="' + n + '" aria-label="' + n + ' star' + (n === 1 ? '' : 's') + '">★</button>';
        } else {
          buttons += '<span class="star-btn' + on + '">★</span>';
        }
      }
      var starLine = '<div class="result-stars"><p class="star-pick">' + buttons + '</p></div>';
      var obj = item.objectives ? '<p class="objectives-preview">' + escapeHtml(item.objectives.substring(0, 220)) + (item.objectives.length > 220 ? '…' : '') + '</p>' : '';
      return '<article class="result-item"><h3>' + (item.full_access ? '<a href="index.php?route=reader&id=' + item.id + '">' + escapeHtml(item.title) + '</a>' : escapeHtml(item.title)) + '</h3>' +
        starLine +
        '<p>' + escapeHtml(item.authors) + ' · ' + escapeHtml(item.program_code) + ' · ' + escapeHtml(item.school_year) + (item.study_type_label ? ' · ' + escapeHtml(item.study_type_label) : '') + '</p>' + obj + '</article>';
    }).join('');
    if (canRate) bindRating(box);
  }

  function bindRating(box) {
    var csrf = box.getAttribute('data-csrf') || '';
    box.querySelectorAll('.star-btn[data-study]').forEach(function (button) {
      button.addEventListener('click', function () {
        var body = new FormData();
        body.append('_csrf', csrf);
        body.append('study_id', button.getAttribute('data-study'));
        body.append('stars', button.getAttribute('data-stars'));
        body.append('ajax', '1');
        fetch('index.php?route=search/rate', { method: 'POST', body: body, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (data && data.ok) runSearch();
          });
      });
    });
  }

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function dashboardResults(active) {
    dashboardSearchActive = active;
    ['dept-paper-cards', 'dept-paper-pager', 'dept-paper-empty', 'dept-catalog-empty'].forEach(function (id) {
      var node = document.getElementById(id);
      if (node && active) node.hidden = true;
      else if (node && id === 'dept-paper-cards') node.hidden = false;
    });
    var q = document.getElementById('q');
    var bar = q && q.closest('[data-find-bar]');
    if (bar) { var hits = bar.querySelector('[data-find-hits]'); if (hits) hits.hidden = true; }
  }

  function clearDashboardSearch() {
    searchRequest++;
    dashboardResults(false);
    var box = document.getElementById('results');
    if (box) { box.innerHTML = ''; box.removeAttribute('aria-busy'); }
    var grid = document.getElementById('dept-paper-cards');
    if (grid && grid._applyTableFilter) grid._applyTableFilter();
    var catalogEmpty = document.getElementById('dept-catalog-empty');
    if (catalogEmpty) catalogEmpty.hidden = false;
  }

  function runSearch() {
    var filters = gatherFilters();
    var q = document.getElementById('q');
    var bar = q && q.closest('[data-find-bar]');
    if (bar && !filters.q.trim()) { clearDashboardSearch(); return; }
    var request = ++searchRequest;
    var box = document.getElementById('results');
    if (bar) dashboardResults(true);
    box.setAttribute('aria-busy', 'true');
    box.innerHTML = '<p class="scholar-count">Searching published studies…</p>';
    var params = new URLSearchParams(filters);
    fetch(apiUrl + '&' + params.toString(), { credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error('Search failed'); return r.json(); })
      .then(function (data) {
        if (request !== searchRequest) return;
        box.removeAttribute('aria-busy');
        var items = data.results || [];
        renderResults(items, !!data.featured, filters.q);
        if (bar) {
          dashboardResults(true);
          var count = bar.querySelector('[data-find-count]');
          var page = bar.querySelector('[data-find-page]');
          if (count) count.textContent = items.length + (items.length === 1 ? ' result' : ' results');
          if (page) page.textContent = 'Keyword search';
        }
      }).catch(function () {
        if (request !== searchRequest) return;
        box.removeAttribute('aria-busy');
        box.innerHTML = '<p class="scholar-count">Search could not load. Press Enter to try again.</p>';
      });
  }

  function runSuggest(term) {
    var list = document.getElementById('suggestions');
    if (!term) {
      list.innerHTML = '';
      return;
    }
    fetch(suggestUrl + '&term=' + encodeURIComponent(term), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (fieldValue('q').trim() !== term) return;
        list.innerHTML = (data.suggestions || []).filter(function (s) { return s.type === 'title'; }).map(function (s) {
          return '<li role="option" tabindex="0" data-value="' + escapeHtml(s.label) + '">' +
            escapeHtml(s.label) + ' <small>(' + escapeHtml(s.type) + ')</small></li>';
        }).join('');
        list.querySelectorAll('li').forEach(function (li) {
          li.addEventListener('click', function () {
            var field = document.getElementById('q');
            field.value = li.getAttribute('data-value');
            field.dispatchEvent(new Event('input', { bubbles: true }));
            list.innerHTML = '';
          });
        });
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var q = document.getElementById('q');
    if (!q) return;
    document.querySelectorAll('[data-star-study]').forEach(function (button) {
      button.addEventListener('click', function () {
        if (button.disabled) return;
        button.disabled = true;
        var body = new FormData();
        body.append('_csrf', document.getElementById('results').getAttribute('data-csrf') || '');
        body.append('study_id', button.getAttribute('data-star-study')); body.append('ajax', '1');
        fetch('index.php?route=search/bookmark', {method:'POST', body:body, credentials:'same-origin'})
          .then(function (r) { if (!r.ok) throw new Error('Star failed'); return r.json(); })
          .then(function (data) { if (data.ok) updateDashboardStar(button.getAttribute('data-star-study'), !!data.saved); })
          .catch(function () { button.textContent='Try starring again'; })
          .finally(function () { button.disabled=false; });
      });
    });
    var debouncedSuggest = debounce(function () {
      runSuggest(q.value.trim());
    }, 200);
    q.addEventListener('input', function () {
      if (q.closest('[data-find-bar]')) {
        if (dashboardSearchActive) clearDashboardSearch();
        var list = document.getElementById('suggestions');
        if (list) list.innerHTML = '';
        return;
      }
      debouncedSuggest();
      searchRequest++;
      var box = document.getElementById('results');
      if (box) box.innerHTML = '';
    });
    q.addEventListener('keydown', function (event) {
      if (event.key !== 'Enter') return;
      event.preventDefault();
      if (document.getElementById('suggestions')) {
        document.getElementById('suggestions').innerHTML = '';
      }
      runSearch();
    });
    var dashboardBar = q.closest('[data-find-bar]');
    if (dashboardBar) {
      dashboardBar.addEventListener('change', function () { if (dashboardSearchActive) runSearch(); });
      var apply = dashboardBar.querySelector('[data-find-apply]');
      var reset = dashboardBar.querySelector('[data-find-reset]');
      if (apply) apply.addEventListener('click', function () { if (dashboardSearchActive) runSearch(); });
      if (reset) reset.addEventListener('click', clearDashboardSearch);
    }
    var go = document.getElementById('search-go');
    if (go) {
      go.addEventListener('click', function () {
        if (document.getElementById('suggestions')) {
          document.getElementById('suggestions').innerHTML = '';
        }
        runSearch();
      });
    }
    document.querySelectorAll('.scholar-side select').forEach(function (el) {
      el.addEventListener('change', runSearch);
    });
    var params = new URLSearchParams(window.location.search);
    ['q', 'program_id', 'school_year', 'adviser_user_id', 'study_type'].forEach(function (key) {
      var field = document.getElementById(key);
      if (field && params.get(key)) field.value = params.get(key);
    });
    var hasQuery = ['q', 'program_id', 'school_year', 'adviser_user_id', 'study_type'].some(function (key) {
      return params.get(key);
    });
    var resultsBox = document.getElementById('results');
    var deptLocked = resultsBox && resultsBox.getAttribute('data-dept-locked') === '1';
    if ((hasQuery || deptLocked) && !q.hasAttribute('data-manual')) {
      runSearch();
    }
    ['cite-modal', 'quick-modal'].forEach(function (id) {
      var dialog = document.getElementById(id);
      var close = document.getElementById(id === 'cite-modal' ? 'cite-close' : 'quick-close');
      if (!dialog) return;
      if (close) close.addEventListener('click', function () { closeDialog(dialog); });
      dialog.addEventListener('click', function (event) {
        var rect = dialog.getBoundingClientRect();
        var inside = event.clientY >= rect.top && event.clientY <= rect.bottom
          && event.clientX >= rect.left && event.clientX <= rect.right;
        if (!inside) closeDialog(dialog);
      });
    });
    var timeMode = 'any';
    function paintFilters() {
      document.querySelectorAll('[data-time]').forEach(function (button) {
        button.classList.toggle('is-on', button.getAttribute('data-time') === timeMode);
      });
    }
    document.querySelectorAll('[data-time]').forEach(function (button) {
      button.addEventListener('click', function () {
        timeMode = button.getAttribute('data-time');
        var from = document.getElementById('date_from');
        var to = document.getElementById('date_to');
        var range = document.getElementById('scholar-range');
        document.getElementById('date_field').value = 'approval';
        if (timeMode === 'any') {
          from.value = '';
          to.value = '';
          range.hidden = true;
        } else if (timeMode === 'custom') {
          from.value = document.getElementById('range_from').value;
          to.value = document.getElementById('range_to').value;
          range.hidden = false;
        } else {
          from.value = timeMode + '-01-01';
          to.value = '';
          range.hidden = true;
        }
        paintFilters();
        if (timeMode !== 'custom' || from.value || to.value) runSearch();
      });
    });
    document.querySelectorAll('#range_from, #range_to').forEach(function (input) {
      input.addEventListener('change', function () {
        timeMode = 'custom';
        document.getElementById('date_from').value = document.getElementById('range_from').value;
        document.getElementById('date_to').value = document.getElementById('range_to').value;
        paintFilters();
        runSearch();
      });
    });
  });
})();
