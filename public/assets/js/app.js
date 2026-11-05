document.addEventListener('DOMContentLoaded', function () {
  initConfirmDialogs();
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function () {
      var btn = form.querySelector('button[type="submit"]');
      if (btn && !form.classList.contains('inline-form') && !form.classList.contains('review-tools')) {
        btn.disabled = true;
      }
    });
  });

  document.querySelectorAll('table[data-sortable]').forEach(initSortableTable);
  markCurrentNav();
  initMenus();
  initMobileNav();
  initAlerts();
  initTableSearch();
  initDepartmentField();
  initNewDepartment();
  initAccountEdit();
  initAddAccount();
  initReviewQuick();
  initReviewNotes();
  initProfileModal();
  initStudyQuick();
  initAuthorsQuick();
  initDashFilter();
  initReviewFilter();
  initReportFilter();
  initSubmittersQuick();
  initPasswordToggle();
  initContactNumber();
  initSignOut();
  initNotifyModal();
  initGuideModal();
  initFindBars();
  initDeptPaperCards();
  initResponsiveTables();
  initMobilePageTitle();
});

function initDepartmentField() {
  document.querySelectorAll('[data-department-role]').forEach(function (role) {
    var field = document.getElementById(role.getAttribute('data-department-role'));
    if (!field) return;
    var select = field.querySelector('select');
    if (!select) return;
    var reqMark = field.querySelector('.req, #department-req');
    function isBound(slug) {
      return slug === 'student'
        || slug === 'research_adviser'
        || slug === 'adviser'
        || slug === 'dept_research_coordinator'
        || slug === 'dean';
    }
    function sync() {
      var option = role.options[role.selectedIndex];
      var slug = option ? option.getAttribute('data-slug') : '';
      var bound = isBound(slug);
      var assignAny = !!(role.closest('[data-assign-dept="1"]'));
      var show = assignAny || bound;
      field.hidden = !show;
      select.required = bound;
      if (reqMark) reqMark.hidden = !bound;
      if (!show) {
        select.value = '';
        select.dispatchEvent(new Event('change'));
      }
    }
    role.addEventListener('change', sync);
    sync();
  });
}

function initNewDepartment() {
  document.querySelectorAll('[data-new-department]').forEach(function (block) {
    var select = document.getElementById(block.getAttribute('data-new-department'));
    if (!select) return;
    var inputs = block.querySelectorAll('input');
    function sync() {
      var adding = select.value === 'new';
      block.hidden = !adding;
      inputs.forEach(function (input) {
        input.required = adding;
        if (!adding) input.value = '';
      });
    }
    select.addEventListener('change', sync);
    sync();
  });
}

function initAccountEdit() {
  var dialog = document.getElementById('edit-account');
  if (!dialog) return;
  var role = document.getElementById('role_id');
  var program = document.getElementById('program_id');
  function fill(button) {
    document.getElementById('edit-id').value = button.getAttribute('data-id') || '';
    document.getElementById('edit-reset-id').value = button.getAttribute('data-id') || '';
    document.getElementById('edit-school-id').value = button.getAttribute('data-school-id') || '';
    document.getElementById('edit-first-name').value = button.getAttribute('data-first') || '';
    document.getElementById('edit-middle-name').value = button.getAttribute('data-middle') || '';
    document.getElementById('edit-last-name').value = button.getAttribute('data-last') || '';
    if (role) role.value = button.getAttribute('data-role') || '';
    if (role) role.dispatchEvent(new Event('change'));
    var actorId = dialog.getAttribute('data-actor-id') || '0';
    var createdBy = button.getAttribute('data-created') || '0';
    var canChangeDepartment = dialog.getAttribute('data-assign-dept') === '1'
      || dialog.getAttribute('data-manage-dept') === '1'
      || (actorId !== '0' && createdBy === actorId);
    var locked = document.getElementById('program-locked');
    if (program) {
      program.disabled = !canChangeDepartment;
      program.hidden = !canChangeDepartment;
      program.value = button.getAttribute('data-program') || '';
      program.dispatchEvent(new Event('change'));
    }
    if (locked) {
      var label = button.getAttribute('data-program-label') || 'No department';
      locked.hidden = canChangeDepartment;
      locked.value = canChangeDepartment ? '' : label;
    }
    var deptLabel = document.getElementById('edit-department');
    if (deptLabel) deptLabel.value = button.getAttribute('data-program-label') || deptLabel.value;
    document.getElementById('edit-active').checked = button.getAttribute('data-active') === '1';
    if (typeof dialog.showModal === 'function') dialog.showModal();
  }
  document.querySelectorAll('[data-edit-account]').forEach(function (button) {
    button.addEventListener('click', function () { fill(button); });
  });
  dialog.querySelectorAll('[data-close-edit]').forEach(function (button) {
    button.addEventListener('click', function () { dialog.close(); });
  });
  dialog.addEventListener('click', function (event) {
    var rect = dialog.getBoundingClientRect();
    var inside = event.clientY >= rect.top && event.clientY <= rect.bottom
      && event.clientX >= rect.left && event.clientX <= rect.right;
    if (!inside) dialog.close();
  });
  var openId = dialog.getAttribute('data-open-edit');
  if (openId && openId !== '0') {
    var match = document.querySelector('[data-edit-account][data-id="' + openId + '"]');
    if (match) fill(match);
  }
}

function initAddAccount() {
  var dialog = document.getElementById('add-account');
  if (!dialog) return;
  function open() {
    if (typeof dialog.showModal === 'function') dialog.showModal();
  }
  document.querySelectorAll('button[data-open-add]').forEach(function (button) {
    button.addEventListener('click', open);
  });
  dialog.querySelectorAll('[data-close-add]').forEach(function (button) {
    button.addEventListener('click', function () { dialog.close(); });
  });
  dialog.addEventListener('click', function (event) {
    var rect = dialog.getBoundingClientRect();
    var inside = event.clientY >= rect.top && event.clientY <= rect.bottom
      && event.clientX >= rect.left && event.clientX <= rect.right;
    if (!inside) dialog.close();
  });
  if (dialog.getAttribute('data-open-add') === '1') open();
}

function initContactNumber() {
  var input = document.getElementById('contact_number');
  if (!input) return;
  function digitsOnly() {
    input.value = String(input.value || '').replace(/\D/g, '').slice(0, 11);
  }
  input.addEventListener('input', digitsOnly);
  input.addEventListener('paste', function () {
    setTimeout(digitsOnly, 0);
  });
}

function initPasswordToggle() {
  document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
    var wrap = button.closest('.password-wrap');
    var input = wrap ? wrap.querySelector('input') : null;
    var showIcon = button.querySelector('.is-show');
    var hideIcon = button.querySelector('.is-hide');
    if (!input) return;
    button.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.setAttribute('aria-pressed', show ? 'true' : 'false');
      button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      button.title = show ? 'Hide password' : 'Show password';
      if (showIcon) showIcon.hidden = show;
      if (hideIcon) hideIcon.hidden = !show;
    });
  });
}

function initDashFilter() {
  var filter = document.getElementById('program_id');
  var table = document.getElementById('dash-studies');
  if (!filter || !table || !table.closest('.dash-board') || filter.hasAttribute('data-table-dept')) return;
  var empty = table.querySelector('.dash-empty-row');
  function apply() {
    var program = filter.value;
    var shown = 0;
    table.querySelectorAll('tbody tr[data-program]').forEach(function (row) {
      var match = !program || row.getAttribute('data-program') === program;
      row.hidden = !match;
      if (match) shown += 1;
    });
    if (empty) empty.hidden = shown !== 0;
    var scroller = table.closest('.table-scroll');
    if (scroller) scroller.classList.toggle('is-scroll', shown > 10);
  }
  filter.addEventListener('change', apply);
}

function initReviewFilter() {
  var form = document.getElementById('review-tools');
  var table = document.getElementById('review-studies');
  var search = document.getElementById('review-search');
  if (!form || !table) return;
  var empty = table.querySelector('.review-empty-row');
  var decision = '';
  function apply() {
    var query = search ? search.value.trim().toLowerCase() : '';
    var shown = 0;
    table.querySelectorAll('tbody tr[data-program]').forEach(function (row) {
      var matchText = !query || row.textContent.toLowerCase().indexOf(query) !== -1;
      var matchDecision = !decision || row.getAttribute('data-decision') === decision;
      var match = matchText && matchDecision;
      row.hidden = !match;
      if (match) shown += 1;
    });
    if (empty) empty.hidden = shown !== 0;
    var scroller = table.closest('.table-scroll');
    if (scroller) scroller.classList.toggle('is-scroll', shown > 10);
  }
  form.addEventListener('submit', function (event) {
    event.preventDefault();
    apply();
  });
  if (search) search.addEventListener('input', apply);
  document.querySelectorAll('[data-review-decision]').forEach(function (button) {
    button.addEventListener('click', function () {
      decision = button.getAttribute('data-review-decision') || '';
      document.querySelectorAll('[data-review-decision]').forEach(function (other) {
        var on = other === button;
        other.classList.toggle('is-on', on);
        other.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      apply();
    });
  });
}

function filterProgramTable(table, program) {
  if (!table) return;
  var empty = table.querySelector('.report-empty-row');
  var shown = 0;
  table.querySelectorAll('tbody tr[data-program]').forEach(function (row) {
    var match = !program || row.getAttribute('data-program') === program;
    row.hidden = !match;
    if (match) shown += 1;
  });
  if (empty) empty.hidden = shown !== 0;
  var scroller = table.closest('.table-scroll');
  if (scroller) scroller.classList.toggle('is-scroll', shown > 10);
}

function initReportFilter() {
  var filter = document.getElementById('report-program');
  if (!filter) return;
  function apply() {
    var program = filter.value;
    filterProgramTable(document.getElementById('report-dept'), program);
  }
  filter.addEventListener('change', apply);
}

function bindDialogDismiss(dialog, closeId) {
  if (!dialog) return;
  var close = document.getElementById(closeId);
  if (close) close.addEventListener('click', function () { dialog.close(); });
  dialog.addEventListener('click', function (event) {
    var rect = dialog.getBoundingClientRect();
    var inside = event.clientY >= rect.top && event.clientY <= rect.bottom
      && event.clientX >= rect.left && event.clientX <= rect.right;
    if (!inside) dialog.close();
  });
}

function initSubmittersQuick() {
  var dialog = document.getElementById('submitters-quick');
  var heading = document.getElementById('submitters-quick-heading');
  var body = document.getElementById('submitters-quick-body');
  if (!dialog || !body) return;
  document.querySelectorAll('[data-submitters-open]').forEach(function (button) {
    button.addEventListener('click', function () {
      var program = button.getAttribute('data-program') || '';
      var rows = [];
      try {
        rows = JSON.parse(button.getAttribute('data-rows') || '[]');
      } catch (error) {
        rows = [];
      }
      if (heading) heading.textContent = program ? 'Who submitted · ' + program : 'Who submitted';
      body.textContent = '';
      if (!rows.length) {
        var empty = document.createElement('tr');
        empty.innerHTML = '<td>No submissions in this department.</td><td></td><td></td><td></td><td></td>';
        body.appendChild(empty);
      } else {
        rows.forEach(function (row) {
          var tr = document.createElement('tr');
          ['school_id', 'name', 'submitted', 'approved', 'last_update'].forEach(function (key) {
            var td = document.createElement('td');
            td.textContent = row[key] == null ? '' : String(row[key]);
            tr.appendChild(td);
          });
          body.appendChild(tr);
        });
      }
      if (typeof dialog.showModal === 'function') dialog.showModal();
    });
  });
  bindDialogDismiss(dialog, 'submitters-quick-close');
}

function initProfileModal() {
  var dialog = document.getElementById('profile-modal');
  if (!dialog || typeof dialog.showModal !== 'function') return;
  function showProfile() {
    if (dialog.parentElement !== document.body) document.body.appendChild(dialog);
    if (!dialog.open) dialog.showModal();
  }
  function leave() {
    var back = dialog.getAttribute('data-back');
    if (back) window.location.href = back;
  }
  var cancel = document.getElementById('profile-modal-cancel');
  if (cancel) cancel.addEventListener('click', function () { dialog.close(); });
  bindDialogDismiss(dialog, 'profile-modal-close');
  dialog.addEventListener('close', leave);
  showProfile();
}

function initReviewNotes() {
  var button = document.getElementById('review-notes-toggle');
  var notes = document.getElementById('review-notes');
  if (!button || !notes || typeof notes.showModal !== 'function') return;
  button.addEventListener('click', function () {
    if (notes.parentElement !== document.body) document.body.appendChild(notes);
    if (!notes.open) notes.showModal();
  });
  bindDialogDismiss(notes, 'review-notes-close');
}

function initReviewQuick() {
  var dialog = document.getElementById('review-quick');
  var open = document.getElementById('quick-view-open');
  if (dialog && open) {
    open.addEventListener('click', function () {
      if (typeof dialog.showModal === 'function') dialog.showModal();
    });
    bindDialogDismiss(dialog, 'quick-view-close');
  }
  openReturningDialog(document.getElementById('review-decision-modal'), 'review-decision-close');
  openReturningDialog(document.getElementById('library-review-modal'), 'library-review-close');
}

function openReturningDialog(dialog, closeId) {
  if (!dialog || typeof dialog.showModal !== 'function') return;
  function show() {
    if (dialog.parentElement !== document.body) document.body.appendChild(dialog);
    if (!dialog.open) dialog.showModal();
  }
  bindDialogDismiss(dialog, closeId);
  dialog.addEventListener('close', function () {
    var back = dialog.getAttribute('data-back');
    if (back) window.location.href = back;
  });
  show();
  var note = dialog.querySelector('[data-swal="note"]');
  if (!note || !swalReady()) return;
  var text = (note.textContent || '').trim();
  note.remove();
  if (!text) return;
  Swal.fire({
    target: dialog,
    icon: 'success',
    title: 'Note saved',
    text: text,
    confirmButtonText: 'OK',
    confirmButtonColor: '#c8102e'
  });
}

function renderPaperPager(pager, page, pages, show) {
  if (!pager) return;
  pager.hidden = !show;
  var current = pager.querySelector('[data-page-current]');
  if (current) current.textContent = String(page);
  var bar = pager.querySelector('[data-page-bar]');
  if (!bar) return;
  var html = '';
  html += '<button type="button" data-page="first" aria-label="First page"' + (page <= 1 ? ' disabled' : '') + '>&laquo;</button>';
  html += '<button type="button" data-page="prev" aria-label="Previous page"' + (page <= 1 ? ' disabled' : '') + '>&lsaquo;</button>';
  deptPageItems(page, pages).forEach(function (item) {
    if (item === '…') {
      html += '<span class="paper-pager-gap" aria-hidden="true">…</span>';
      return;
    }
    html += '<button type="button" data-page="' + item + '"' + (item === page ? ' class="is-on" aria-current="page"' : '') + '>' + item + '</button>';
  });
  html += '<button type="button" data-page="next" aria-label="Next page"' + (page >= pages ? ' disabled' : '') + '>&rsaquo;</button>';
  html += '<button type="button" data-page="last" aria-label="Last page"' + (page >= pages ? ' disabled' : '') + '>&raquo;</button>';
  bar.innerHTML = html;
}

function deptPageItems(current, total) {
  if (total <= 5) {
    var all = [];
    for (var i = 1; i <= total; i++) all.push(i);
    return all;
  }
  if (current <= 3) return [1, 2, 3, '…', total];
  if (current >= total - 2) return [1, '…', total - 2, total - 1, total];
  return [1, '…', current - 1, current, current + 1, '…', total];
}

function initFindBars() {
  document.querySelectorAll('[data-find-bar]').forEach(function (bar) {
    var tableSel = bar.getAttribute('data-find-table') || '';
    var table = tableSel ? document.querySelector(tableSel) : null;
    var cardsSel = bar.getAttribute('data-find-cards') || '';
    var cards = cardsSel ? document.querySelector(cardsSel) : null;
    var pagerSel = bar.getAttribute('data-find-pager') || '';
    var emptySel = bar.getAttribute('data-find-empty') || '';
    var pager = pagerSel ? document.querySelector(pagerSel) : null;
    var empty = emptySel ? document.querySelector(emptySel) : null;
    var statusAttr = bar.getAttribute('data-status-attr') || 'data-gate';
    var pageSize = parseInt(bar.getAttribute('data-page-size') || '0', 10);
    var delegate = bar.getAttribute('data-find-mode') === 'delegate';
    var page = 1;
    var pages = 1;
    var applied = { dept: '', type: '', year: '', from: '', to: '', status: '' };
    var countEl = bar.querySelector('[data-find-count]');
    var pageEl = bar.querySelector('[data-find-page]');
    var queryEl = bar.querySelector('[data-find-q]');
    var sortEl = bar.querySelector('[data-find-sort]');
    var panel = bar.querySelector('[data-find-panel]');
    var toggle = bar.querySelector('[data-find-toggle]');

    function field(name) {
      return bar.querySelector('[data-find-' + name + ']');
    }
    function readApplied() {
      applied.dept = field('dept') ? field('dept').value : '';
      applied.type = field('type') ? field('type').value : '';
      applied.year = field('year') ? field('year').value : '';
      applied.from = field('from') ? field('from').value : '';
      applied.to = field('to') ? field('to').value : '';
      applied.status = field('status') ? field('status').value : '';
    }
    function schoolYearOk(value) {
      if (!applied.from && !applied.to) return true;
      var start = parseInt(value, 10);
      if (!Number.isFinite(start)) return false;
      var from = applied.from ? parseInt(applied.from, 10) : null;
      var to = applied.to ? parseInt(applied.to, 10) : null;
      if (from !== null && to !== null && from > to) {
        var swap = from; from = to; to = swap;
      }
      if (from !== null && start < from) return false;
      if (to !== null && start > to) return false;
      return true;
    }
    function matches(node) {
      var query = queryEl ? queryEl.value.trim().toLowerCase() : '';
      var heading = node.querySelector('h3, td');
      var authors = node.querySelector('.paper-card-authors');
      var cells = node.querySelectorAll('td');
      var searchable = [node.getAttribute('data-search-title') || (heading ? heading.textContent : ''), node.getAttribute('data-search-authors') || (authors ? authors.textContent : (cells[1] ? cells[1].textContent : '')), node.getAttribute('data-program') || ''].join(' ').toLowerCase();
      var textOk = !query || query.split(/\s+/).every(function (word) { return searchable.indexOf(word) !== -1; });
      if (query.length === 1) {
        textOk = (node.getAttribute('data-search-title') || (heading ? heading.textContent : '')).trim().toLowerCase().indexOf(query) === 0;
      }
      var deptOk = !applied.dept || node.getAttribute('data-program') === applied.dept || node.getAttribute('data-all-depts') === '1';
      var typeOk = !applied.type || node.getAttribute('data-type') === applied.type;
      var yearOk = !applied.year || node.getAttribute('data-year') === applied.year;
      var statusOk = !applied.status || node.getAttribute(statusAttr) === applied.status;
      return textOk && deptOk && typeOk && yearOk && schoolYearOk(node.getAttribute('data-year') || '') && statusOk;
    }
    function showHits(pool) {
      var box = bar.querySelector('[data-find-hits]');
      if (!box) return;
      var query = queryEl ? queryEl.value.trim() : '';
      if (!query) {
        box.hidden = true;
        box.innerHTML = '';
        return;
      }
      var hits = pool.filter(matches).filter(function (node) {
        return suggestionRank(node, query) <= 1;
      }).sort(function (a, b) {
        return suggestionRank(a, query) - suggestionRank(b, query);
      }).slice(0, 6);
      box.innerHTML = '';
      if (!hits.length) {
        var emptyHit = document.createElement('p');
        emptyHit.className = 'find-hit is-empty';
        emptyHit.textContent = 'No titles start with "' + query + '".';
        box.appendChild(emptyHit);
        box.hidden = false;
        return;
      }
      hits.forEach(function (node) {
        var titleNode = node.querySelector('h3, td');
        var authorNode = node.querySelector('.paper-card-authors');
        var metaNode = node.querySelector('.paper-card-meta');
        var cells = node.querySelectorAll('td');
        var title = titleNode ? titleNode.textContent.trim() : '';
        var authors = authorNode ? authorNode.textContent.trim() : (cells[1] ? cells[1].textContent.trim() : '');
        var meta = metaNode ? metaNode.textContent.trim() : (cells[2] ? cells[2].textContent.trim() : '');
        var open = node.querySelector('a.paper-card-open, a[href*="route=reader"]');
        var card = document.createElement(open ? 'a' : 'button');
        card.className = 'find-hit';
        if (open) card.href = open.getAttribute('href');
        else card.type = 'button';
        var name = document.createElement('strong');
        name.textContent = title;
        card.appendChild(name);
        if (authors) {
          var by = document.createElement('span');
          by.textContent = authors;
          card.appendChild(by);
        }
        if (meta) {
          var line = document.createElement('span');
          line.textContent = meta;
          card.appendChild(line);
        }
        if (!open) {
          card.addEventListener('click', function () {
            if (queryEl) queryEl.value = title;
            box.hidden = true;
            page = 1;
            paint();
          });
        }
        box.appendChild(card);
      });
      box.hidden = false;
    }
    function itemTitle(node) {
      var heading = node.querySelector('h3, td');
      return (heading ? heading.textContent : node.textContent).trim().toLowerCase();
    }
    function suggestionRank(node, query) {
      var title = (node.getAttribute('data-search-title') || itemTitle(node)).toLowerCase();
      var term = query.trim().toLowerCase();
      if (title === term) return 0;
      if (title.indexOf(term) === 0) return 1;
      if (title.split(/\s+/).some(function (word) { return word.indexOf(term) === 0; })) return 2;
      if (title.indexOf(term) !== -1) return 3;
      return 4;
    }
    function sortNodes(list) {
      var mode = sortEl ? sortEl.value : 'newest';
      list.sort(function (a, b) {
        if (cardsSel === '#dept-paper-cards') {
          var starred = Number(b.getAttribute('data-starred') || 0) - Number(a.getAttribute('data-starred') || 0);
          if (starred) return starred;
        }
        if (mode === 'title' || mode === 'title-desc') {
          var cmp = itemTitle(a).localeCompare(itemTitle(b));
          return mode === 'title-desc' ? -cmp : cmp;
        }
        var av = a.getAttribute('data-when') || '';
        var bv = b.getAttribute('data-when') || '';
        if (av === bv) return itemTitle(a).localeCompare(itemTitle(b));
        if (mode === 'oldest') return av > bv ? 1 : -1;
        return av < bv ? 1 : -1;
      });
      return list;
    }
    function paint() {
      var rows = table ? Array.prototype.slice.call(table.querySelectorAll('tbody tr')).filter(function (row) {
        return !row.classList.contains('dash-empty-row') && row.id !== 'folder-none';
      }) : [];
      var cardNodes = cards ? Array.prototype.slice.call(cards.querySelectorAll('.paper-card')) : [];
      if (delegate) {
        var matched = rows.filter(matches);
        bar.dispatchEvent(new CustomEvent('find-change', { bubbles: true }));
        if (countEl) countEl.textContent = matched.length === 1 ? '1 result' : matched.length + ' results';
        if (pageEl) pageEl.textContent = 'Page 1 of 1';
        showHits(rows);
        return;
      }
      var matchedRows = sortNodes(rows.filter(matches));
      var matchedCards = sortNodes(cardNodes.filter(matches));
      if (table) {
        var body = table.querySelector('tbody');
        matchedRows.forEach(function (row) { if (body) body.appendChild(row); });
      }
      if (cards) {
        matchedCards.forEach(function (card) { cards.appendChild(card); });
      }
      var total = Math.max(matchedRows.length, matchedCards.length);
      pages = pageSize > 0 ? Math.max(1, Math.ceil(total / pageSize)) : 1;
      if (page > pages) page = 1;
      rows.forEach(function (row) { row.hidden = true; });
      cardNodes.forEach(function (card) { card.hidden = true; });
      matchedRows.forEach(function (row, index) {
        row.hidden = pageSize > 0 && Math.floor(index / pageSize) + 1 !== page;
      });
      matchedCards.forEach(function (card, index) {
        card.hidden = pageSize > 0 && Math.floor(index / pageSize) + 1 !== page;
      });
      if (table) {
        table.querySelectorAll('tbody tr.dash-empty-row').forEach(function (row) {
          row.hidden = matchedRows.length !== 0;
        });
      }
      if (empty) {
        empty.hidden = total !== 0;
        if (!empty.hidden) empty.textContent = empty.getAttribute('data-empty-text') || 'No papers match.';
      }
      if (countEl) countEl.textContent = total === 1 ? '1 result' : total + ' results';
      if (pageEl) pageEl.textContent = 'Page ' + page + ' of ' + pages;
      showHits(rows.length ? rows : cardNodes);
      if (pager) renderPaperPager(pager, page, pages, pageSize > 0 && total > pageSize);
      if (table) table._applyTableFilter = paint;
    }
    if (toggle && panel) {
      panel.hidden = true;
      toggle.classList.remove('is-on');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.addEventListener('click', function () {
        var open = panel.hasAttribute('hidden');
        panel.hidden = !open;
        toggle.classList.toggle('is-on', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }
    var applyBtn = bar.querySelector('[data-find-apply]');
    if (applyBtn) applyBtn.addEventListener('click', function () { readApplied(); page = 1; paint(); });
    var resetBtn = bar.querySelector('[data-find-reset]');
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        ['dept', 'type', 'year', 'from', 'to', 'status'].forEach(function (name) {
          var el = field(name);
          if (el) el.value = '';
        });
        if (queryEl) queryEl.value = '';
        if (sortEl) sortEl.value = 'newest';
        readApplied();
        page = 1;
        paint();
      });
    }
    if (queryEl) {
      queryEl.addEventListener('input', function () { page = 1; paint(); });
      queryEl.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        readApplied();
        page = 1;
        paint();
      });
    }
    if (sortEl) sortEl.addEventListener('change', function () { page = 1; paint(); });
    ['dept', 'type', 'year', 'from', 'to', 'status'].forEach(function (name) {
      var el = field(name);
      if (!el) return;
      el.addEventListener('change', function () { readApplied(); page = 1; paint(); });
    });
    if (pager && pager.getAttribute('data-find-bound') !== '1') {
      pager.setAttribute('data-find-bound', '1');
      pager.addEventListener('click', function (event) {
        var button = event.target.closest('[data-page]');
        if (!button || button.disabled) return;
        var dir = button.getAttribute('data-page');
        if (dir === 'first') page = 1;
        else if (dir === 'prev') page = Math.max(1, page - 1);
        else if (dir === 'next') page = Math.min(pages, page + 1);
        else if (dir === 'last') page = pages;
        else page = parseInt(dir, 10) || 1;
        paint();
      });
    }
    document.addEventListener('click', function (event) {
      if (bar.contains(event.target)) return;
      var box = bar.querySelector('[data-find-hits]');
      if (box) box.hidden = true;
    });
    readApplied();
    paint();
  });
}

function initDeptPaperCards() {
  if (document.querySelector('[data-find-cards="#dept-paper-cards"]')) return;
  var grid = document.getElementById('dept-paper-cards');
  if (!grid) return;
  var cards = Array.prototype.slice.call(grid.querySelectorAll('.paper-card'));
  var pager = document.getElementById('dept-paper-pager');
  var filter = document.getElementById('dept-paper-filter');
  var empty = document.getElementById('dept-paper-empty');
  var size = parseInt(grid.getAttribute('data-page-size') || '6', 10);
  if (!size || size < 1) size = 6;
  var page = 1;
  var pages = 1;
  function matching() {
    var code = filter ? filter.value : '';
    return cards.filter(function (card) {
      return code === '' || card.getAttribute('data-program') === code;
    });
  }
  function paint() {
    var shown = matching();
    pages = Math.max(1, Math.ceil(shown.length / size));
    if (page > pages) page = 1;
    cards.forEach(function (card) { card.hidden = true; });
    shown.forEach(function (card, index) {
      card.hidden = Math.floor(index / size) + 1 !== page;
    });
    if (empty) empty.hidden = shown.length > 0;
    renderPaperPager(pager, page, pages, shown.length > size);
  }
  if (pager && pager.getAttribute('data-bound') !== '1') {
    pager.setAttribute('data-bound', '1');
    pager.addEventListener('click', function (event) {
      var button = event.target.closest('[data-page]');
      if (!button || button.disabled) return;
      var dir = button.getAttribute('data-page');
      if (dir === 'first') page = 1;
      else if (dir === 'prev') page = Math.max(1, page - 1);
      else if (dir === 'next') page = Math.min(pages, page + 1);
      else if (dir === 'last') page = pages;
      else {
        var picked = parseInt(dir, 10);
        if (picked >= 1 && picked <= pages) page = picked;
      }
      paint();
    });
  }
  if (filter) {
    filter.addEventListener('change', function () {
      page = 1;
      paint();
    });
  }
  paint();
}

function initStudyQuick() {
  var dialog = document.getElementById('study-quick');
  if (!dialog) return;
  var titleEl = document.getElementById('study-quick-title');
  var metaEl = document.getElementById('study-quick-meta');
  var abstractEl = document.getElementById('study-quick-abstract');
  var objectivesWrap = document.getElementById('study-quick-objectives-wrap');
  var objectivesEl = document.getElementById('study-quick-objectives');
  document.querySelectorAll('[data-study-quick]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (titleEl) titleEl.textContent = btn.getAttribute('data-title') || '';
      if (metaEl) metaEl.textContent = btn.getAttribute('data-meta') || '';
      if (abstractEl) {
        var raw = btn.getAttribute('data-abstract') || '';
        var keywords = '';
        var marker = raw.match(/\bKeywords\s*:\s*([\s\S]*)$/i);
        if (marker) {
          keywords = marker[1].replace(/\s+/g, ' ').trim();
          raw = raw.slice(0, marker.index).trim();
        }
        abstractEl.textContent = raw || 'No abstract is listed for this study.';
        var kwWrap = document.getElementById('study-quick-keywords-wrap');
        if (!kwWrap) {
          kwWrap = document.createElement('div');
          kwWrap.id = 'study-quick-keywords-wrap';
          kwWrap.hidden = true;
          var kwLabel = document.createElement('h4');
          kwLabel.textContent = 'Keywords';
          var kwEl = document.createElement('p');
          kwEl.id = 'study-quick-keywords';
          kwWrap.appendChild(kwLabel);
          kwWrap.appendChild(kwEl);
          abstractEl.insertAdjacentElement('afterend', kwWrap);
        }
        var kwText = document.getElementById('study-quick-keywords');
        kwWrap.hidden = keywords === '';
        if (kwText) kwText.textContent = keywords;
      }
      var objectives = btn.getAttribute('data-objectives') || '';
      if (objectivesWrap) objectivesWrap.hidden = objectives === '';
      if (objectivesEl) objectivesEl.textContent = objectives;
      if (typeof closeMenus === 'function') closeMenus();
      if (typeof dialog.showModal === 'function') dialog.showModal();
    });
  });
  bindDialogDismiss(dialog, 'study-quick-close');
}

function initAuthorsQuick() {
  var dialog = document.getElementById('authors-quick');
  if (!dialog) return;
  var titleEl = document.getElementById('authors-quick-title');
  var listEl = document.getElementById('authors-quick-list');
  document.querySelectorAll('[data-authors-quick]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (titleEl) titleEl.textContent = btn.getAttribute('data-title') || '';
      var names = (btn.getAttribute('data-authors') || '').split(/\n/).filter(Boolean);
      if (listEl) {
        listEl.textContent = '';
        names.forEach(function (name) {
          var item = document.createElement('li');
          item.textContent = name;
          listEl.appendChild(item);
        });
      }
      if (typeof dialog.showModal === 'function') dialog.showModal();
    });
  });
  bindDialogDismiss(dialog, 'authors-quick-close');
}

function markCurrentNav() {
  var params = new URLSearchParams(window.location.search);
  var current = params.get('route') || '';
  var tab = params.get('tab') || '';
  document.querySelectorAll('.main-nav a[href], .nav-sheet-list a[href]').forEach(function (link) {
    if (link.classList.contains('nav-signout')) return;
    var route = '';
    try {
      route = new URL(link.href, window.location.href).searchParams.get('route') || '';
    } catch (error) {
      return;
    }
    var listed = (link.getAttribute('data-nav-match') || route).split(/\s+/).filter(Boolean);
    var active = listed.some(function (item) {
      if (item === 'home') return current === '';
      return item === current || current.indexOf(item + '/') === 0;
    });
    var navTab = link.getAttribute('data-track-nav');
    if (navTab) {
      var onTrack = current === 'track' || current === 'approval';
      if (navTab === 'waiting') {
        active = onTrack && (tab === 'waiting' || current === 'approval');
      } else {
        active = onTrack && tab !== 'waiting' && current !== 'approval';
      }
    }
    if (active) {
      link.classList.add('is-current');
      link.setAttribute('aria-current', 'page');
    }
  });
  document.querySelectorAll('.main-nav a:not(.nav-signout)').forEach(function (link) {
    link.addEventListener('click', function () {
      var nav = link.closest('.main-nav');
      if (!nav) return;
      nav.querySelectorAll('a').forEach(function (other) {
        other.classList.remove('is-current');
        other.removeAttribute('aria-current');
      });
      link.classList.add('is-current');
      link.setAttribute('aria-current', 'page');
    });
  });
}

function placeActionPanel(button, panel) {
  if (!panel.classList.contains('action-panel')) return;
  var rect = button.getBoundingClientRect();
  var gap = 8;
  panel.style.position = 'fixed';
  panel.style.top = (rect.bottom + gap) + 'px';
  panel.style.left = 'auto';
  panel.style.right = Math.max(12, window.innerWidth - rect.right) + 'px';
  panel.style.zIndex = '80';
}

function initMenus() {
  document.querySelectorAll('[data-menu]').forEach(function (menu) {
    var button = menu.querySelector('[data-menu-button]');
    var panel = menu.querySelector('.menu-panel');
    if (!button || !panel) return;
    button.addEventListener('click', function () {
      var open = panel.hasAttribute('hidden');
      closeMenus();
      if (open) {
        panel.removeAttribute('hidden');
        button.setAttribute('aria-expanded', 'true');
        menu.classList.add('is-open');
        placeActionPanel(button, panel);
      }
    });
  });
  document.addEventListener('click', function (event) {
    if (!event.target.closest('[data-menu]')) closeMenus();
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeMenus();
  });
  window.addEventListener('scroll', closeMenus, true);
  window.addEventListener('resize', closeMenus);
}

function closeMenus() {
  document.querySelectorAll('[data-menu]').forEach(function (menu) {
    var panel = menu.querySelector('.menu-panel');
    var button = menu.querySelector('[data-menu-button]');
    if (panel) {
      panel.setAttribute('hidden', '');
      panel.style.position = '';
      panel.style.top = '';
      panel.style.left = '';
      panel.style.right = '';
      panel.style.zIndex = '';
    }
    if (button) button.setAttribute('aria-expanded', 'false');
    menu.classList.remove('is-open');
  });
}

function initMobilePageTitle() {
  var topbar = document.querySelector('.topbar');
  if (!topbar || topbar.querySelector('.topbar-title')) return;
  var heading = document.querySelector('#main h1, .page-header h1');
  if (!heading) return;
  var title = document.createElement('span');
  title.className = 'topbar-title';
  title.textContent = heading.textContent.replace(/\s+/g, ' ').trim();
  var actions = topbar.querySelector('.topbar-actions');
  topbar.insertBefore(title, actions || null);
}

function initResponsiveTables() {
  var mq = window.matchMedia('(max-width: 720px)');
  function apply() {
    document.querySelectorAll('table.data-table, table.dash-table').forEach(function (table) {
      var colCount = table.querySelectorAll('thead th').length;
      if (table.classList.contains('paper-table') && colCount <= 3) {
        table.classList.remove('is-cards');
        return;
      }
      var heads = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) {
        return (th.textContent || '').replace(/\s+/g, ' ').trim();
      });
      table.querySelectorAll('tbody tr').forEach(function (row) {
        Array.prototype.forEach.call(row.children, function (cell, index) {
          if (heads[index]) cell.setAttribute('data-label', heads[index]);
        });
      });
      table.classList.toggle('is-cards', mq.matches);
    });
  }
  apply();
  if (mq.addEventListener) mq.addEventListener('change', apply);
  else mq.addListener(apply);
}

function initMobileNav() {
  var sheet = document.getElementById('nav-sheet');
  function close() {
    document.body.classList.remove('nav-open', 'header-open');
    if (sheet) sheet.hidden = true;
    document.querySelectorAll('[data-nav-toggle]').forEach(function (button) {
      button.setAttribute('aria-expanded', 'false');
    });
  }
  function openMenu(button) {
    document.body.classList.add('nav-open');
    if (sheet) sheet.hidden = false;
    if (button) button.setAttribute('aria-expanded', 'true');
  }
  document.querySelectorAll('[data-nav-toggle]').forEach(function (button) {
    button.addEventListener('click', function () {
      var open = button.getAttribute('aria-expanded') === 'true';
      if (open) close();
      else openMenu(button);
    });
  });
  document.querySelectorAll('[data-nav-close]').forEach(function (button) {
    button.addEventListener('click', close);
  });
  if (sheet) {
    sheet.addEventListener('click', function (event) {
      if (event.target === sheet) close();
    });
  }
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') close();
  });
  document.querySelectorAll('.nav-sheet-list a, .nav-sheet-list [data-open-guide]').forEach(function (link) {
    link.addEventListener('click', close);
  });
}

function swalReady() {
  return typeof Swal !== 'undefined';
}

function initConfirmDialogs() {
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form || !form.getAttribute) return;
    var message = form.getAttribute('data-confirm');
    if (!message || form.getAttribute('data-confirmed') === '1') return;
    event.preventDefault();
    event.stopPropagation();
    if (!swalReady()) {
      if (window.confirm(message)) {
        form.setAttribute('data-confirmed', '1');
        form.submit();
      }
      return;
    }
    Swal.fire({
      title: 'Please confirm',
      text: message,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes',
      cancelButtonText: 'No',
      confirmButtonColor: '#c8102e',
      cancelButtonColor: '#003da5',
      reverseButtons: true
    }).then(function (result) {
      if (!result.isConfirmed) return;
      form.setAttribute('data-confirmed', '1');
      form.submit();
    });
  }, true);
}

function initAlerts() {
  var nodes = Array.prototype.slice.call(document.querySelectorAll('.alert'));
  if (!swalReady()) {
    nodes.forEach(function (alert) {
      var close = document.createElement('button');
      close.type = 'button';
      close.className = 'alert-close';
      close.setAttribute('aria-label', 'Dismiss');
      close.textContent = '×';
      close.addEventListener('click', function () { alert.remove(); });
      alert.appendChild(close);
    });
    return;
  }
  var messages = [];
  nodes.forEach(function (alert) {
    if (alert.hasAttribute('data-swal')) return;
    var text = (alert.textContent || '').replace(/×/g, '').trim();
    if (!text) return;
    var icon = 'info';
    if (alert.classList.contains('alert-error')) icon = 'error';
    else if (alert.classList.contains('alert-success')) icon = 'success';
    else if (alert.classList.contains('alert-warn')) icon = 'warning';
    messages.push({ icon: icon, text: text });
    alert.remove();
  });
  if (!messages.length) return;
  function showNext() {
    var item = messages.shift();
    if (!item) return;
    if (item.icon === 'success') {
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: item.text,
        showConfirmButton: false,
        timer: 2800,
        timerProgressBar: true
      }).then(showNext);
      return;
    }
    Swal.fire({
      icon: item.icon,
      title: item.icon === 'error' ? 'Please check this' : 'Notice',
      text: item.text,
      confirmButtonText: 'OK',
      confirmButtonColor: item.icon === 'error' ? '#c8102e' : '#003da5'
    }).then(function () {
      if (item.icon === 'error') {
        var bad = document.querySelector('.field-invalid');
        if (bad) {
          bad.scrollIntoView({ behavior: 'smooth', block: 'center' });
          var focusable = bad.querySelector('input, textarea, select');
          if (focusable) focusable.focus();
        }
      }
      showNext();
    });
  }
  showNext();
}

function openNotifyDetail(button) {
  var title = button.getAttribute('data-title') || 'Alert';
  var body = button.getAttribute('data-body') || '';
  var time = button.getAttribute('data-time') || '';
  var href = button.getAttribute('data-href') || '';
  var text = [body, time].filter(Boolean).join('\n');
  if (!swalReady()) {
    window.alert(title + (text ? '\n' + text : ''));
    if (href) window.location.href = href;
    return;
  }
  Swal.fire({
    icon: 'info',
    title: title,
    text: text || 'No more details.',
    confirmButtonText: href ? 'Open' : 'OK',
    showCancelButton: true,
    cancelButtonText: 'Close',
    confirmButtonColor: '#003da5',
    cancelButtonColor: '#6b7280',
    reverseButtons: true
  }).then(function (result) {
    if (result.isConfirmed && href) window.location.href = href;
  });
}

function initGuideModal() {
  var dialog = document.getElementById('guide-modal');
  if (!dialog) return;
  bindDialogDismiss(dialog, 'guide-modal-close');
  document.querySelectorAll('[data-open-guide]').forEach(function (button) {
    button.addEventListener('click', function () {
      if (dialog.parentElement !== document.body) document.body.appendChild(dialog);
      if (typeof dialog.showModal === 'function') dialog.showModal();
    });
  });
}

function initNotifyModal() {
  var dialog = document.getElementById('notify-modal');
  var list = document.getElementById('notify-list');
  var detail = document.getElementById('notify-detail');
  var heading = document.getElementById('notify-modal-heading');
  var markAll = document.getElementById('notify-mark-all');

  function showNotifyModal() {
    if (!dialog || typeof dialog.showModal !== 'function') return false;
    if (dialog.parentElement !== document.body) document.body.appendChild(dialog);
    if (!dialog.open) dialog.showModal();
    return true;
  }

  function showNotifyList() {
    if (list) list.hidden = false;
    if (detail) detail.hidden = true;
    if (markAll) markAll.hidden = false;
    if (heading) heading.textContent = 'Alerts';
  }

  function showNotifyDetail(button) {
    if (!detail || !showNotifyModal()) {
      openNotifyDetail(button);
      return;
    }
    if (list) list.hidden = true;
    detail.hidden = false;
    if (markAll) markAll.hidden = true;
    if (heading) heading.textContent = 'Alert';
    var title = document.getElementById('notify-detail-title');
    var body = document.getElementById('notify-detail-body');
    var time = document.getElementById('notify-detail-time');
    var link = document.getElementById('notify-detail-link');
    if (title) title.textContent = button.getAttribute('data-title') || 'Alert';
    if (body) body.textContent = button.getAttribute('data-body') || 'No more details.';
    if (time) time.textContent = button.getAttribute('data-time') || '';
    var href = button.getAttribute('data-href') || '';
    if (link) {
      link.hidden = href === '';
      if (href) link.href = href;
    }
    markOneNotifyRead(button);
  }

  function markOneNotifyRead(button) {
    if (!button.classList.contains('is-unread')) return;
    button.classList.remove('is-unread');
    var state = button.querySelector('[data-notify-state]');
    if (state) state.textContent = 'Read';
    var row = button.closest('.notice-row');
    if (row) row.classList.remove('unread');
    document.querySelectorAll('.site-header .nav-badge, .topbar .nav-badge, .header-bell .nav-badge').forEach(function (badge) {
      var count = (parseInt(badge.textContent, 10) || 1) - 1;
      if (count <= 0) badge.remove();
      else badge.textContent = String(count);
    });
    var readUrl = button.getAttribute('data-read-url') || '';
    if (!readUrl) return;
    fetch(readUrl, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
  }

  document.querySelectorAll('[data-open-notify]').forEach(function (button) {
    button.addEventListener('click', function () {
      if (!showNotifyModal()) return;
      showNotifyList();
    });
  });
  document.querySelectorAll('[data-notify-open]').forEach(function (button) {
    button.addEventListener('click', function () {
      showNotifyDetail(button);
    });
  });
  if (markAll) {
    markAll.addEventListener('click', function (event) {
      event.preventDefault();
      var url = markAll.href + (markAll.href.indexOf('?') === -1 ? '?' : '&') + 'ajax=1';
      fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (!data || !data.ok) return;
          document.querySelectorAll('.notify-item.is-unread, .notice-row.unread').forEach(function (item) {
            item.classList.remove('is-unread', 'unread');
            var state = item.querySelector('[data-notify-state]');
            if (state) state.textContent = 'Read';
          });
          document.querySelectorAll('.site-header .nav-badge, .topbar .nav-badge').forEach(function (badge) {
            badge.remove();
          });
          showNotifyList();
          if (dialog && dialog.open) dialog.close();
          var done = function () {
            showNotifyModal();
          };
          if (!swalReady()) {
            window.alert('All alerts are marked as read.');
            done();
            return;
          }
          Swal.fire({
            icon: 'success',
            title: 'All alerts are marked as read.',
            confirmButtonText: 'OK',
            confirmButtonColor: '#003da5'
          }).then(done);
        });
    });
  }
  if (dialog) bindDialogDismiss(dialog, 'notify-modal-close');
}

function initSignOut() {
  document.querySelectorAll('a.nav-signout').forEach(function (link) {
    link.addEventListener('click', function (event) {
      if (!swalReady()) return;
      event.preventDefault();
      Swal.fire({
        title: 'Sign out?',
        text: 'You can sign in again anytime.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sign out',
        cancelButtonText: 'Stay',
        confirmButtonColor: '#003da5',
        cancelButtonColor: '#6b7280',
        reverseButtons: true
      }).then(function (result) {
        if (result.isConfirmed) window.location.href = link.href;
      });
    });
  });
}

function initTableSearch() {
  document.querySelectorAll('[data-table-filter]').forEach(function (input) {
    var selector = input.getAttribute('data-table-filter');
    var table = document.querySelector(selector);
    if (!table) return;
    var empty = document.getElementById(table.id + '-empty') || input.parentElement.nextElementSibling;
    var dept = document.querySelector('[data-table-dept="' + selector + '"]');
    var statusBox = document.querySelector('[data-table-status="' + selector + '"]');
    var pager = document.querySelector('[data-table-pager="' + selector + '"]');
    var pageSize = parseInt(table.getAttribute('data-page-size') || '0', 10);
    var status = '';
    var page = 1;
    var signature = null;
    function apply() {
      var query = input.value.trim().toLowerCase();
      var code = dept ? dept.value : '';
      var nextSignature = query + '\n' + code + '\n' + status;
      if (signature !== nextSignature) {
        signature = nextSignature;
        page = 1;
      }
      var matched = [];
      table.querySelectorAll('tbody tr').forEach(function (row) {
        if (row.classList.contains('dash-empty-row')) {
          return;
        }
        var matchText = !query || row.textContent.toLowerCase().indexOf(query) !== -1;
        var matchDept = !code || row.getAttribute('data-all-depts') === '1' || row.getAttribute('data-program') === code;
        var matchStatus = !status || row.getAttribute('data-gate') === status;
        if (!(matchText && matchDept && matchStatus)) {
          row.hidden = true;
          return;
        }
        matched.push(row);
      });
      var externalEmpty = empty && empty.classList && empty.classList.contains('empty-state');
      table.querySelectorAll('tbody tr.dash-empty-row').forEach(function (row) {
        row.hidden = externalEmpty ? true : matched.length !== 0;
      });
      if (pager && pageSize > 0) {
        var pages = Math.max(1, Math.ceil(matched.length / pageSize));
        if (page > pages) page = pages;
        matched.forEach(function (row, index) {
          row.hidden = Math.floor(index / pageSize) + 1 !== page;
        });
        renderPaperPager(pager, page, pages, matched.length > pageSize);
      } else {
        matched.forEach(function (row) { row.hidden = false; });
      }
      var shown = matched.length;
      if (empty && empty.classList && empty.classList.contains('empty-state')) {
        empty.hidden = shown !== 0;
        if (!empty.hidden) {
          var deptMsg = empty.getAttribute('data-empty-dept');
          var textMsg = empty.getAttribute('data-empty-text');
          if (deptMsg || textMsg) {
            empty.textContent = code && !query && !status ? (deptMsg || textMsg) : (textMsg || deptMsg);
          } else {
            empty.textContent = code && !query ? 'No accounts in that department.' : 'No accounts match.';
          }
        }
      }
    }
    table._applyTableFilter = apply;
    input.addEventListener('input', apply);
    if (dept) dept.addEventListener('change', apply);
    if (pager && pager.getAttribute('data-bound') !== '1') {
      pager.setAttribute('data-bound', '1');
      pager.addEventListener('click', function (event) {
        var button = event.target.closest('[data-page]');
        if (!button || button.disabled) return;
        var dir = button.getAttribute('data-page');
        var current = parseInt((pager.querySelector('[data-page-current]') || {}).textContent || '1', 10);
        if (dir === 'first') page = 1;
        else if (dir === 'prev') page = Math.max(1, current - 1);
        else if (dir === 'next') page = current + 1;
        else if (dir === 'last') page = 9999;
        else page = parseInt(dir, 10) || 1;
        apply();
      });
    }
    apply();
    if (statusBox) {
      statusBox.querySelectorAll('[data-status]').forEach(function (button) {
        button.addEventListener('click', function () {
          status = button.getAttribute('data-status') || '';
          statusBox.querySelectorAll('[data-status]').forEach(function (other) {
            var on = other === button;
            other.classList.toggle('is-on', on);
            other.setAttribute('aria-pressed', on ? 'true' : 'false');
          });
          apply();
        });
      });
    }
  });
}

function initSortableTable(table) {
  var headers = table.querySelectorAll('thead th');
  headers.forEach(function (th, index) {
    if (th.hasAttribute('data-nosort')) {
      return;
    }
    th.setAttribute('tabindex', '0');
    th.setAttribute('role', 'button');
    th.setAttribute('aria-sort', 'none');
    th.classList.add('sortable-th');
    th.title = 'Sort by ' + th.textContent.trim();

    function sort() {
      var tbody = table.querySelector('tbody');
      if (!tbody) {
        return;
      }
      var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
      var asc = th.getAttribute('aria-sort') !== 'ascending';
      headers.forEach(function (other) {
        if (other !== th) {
          other.setAttribute('aria-sort', 'none');
          other.classList.remove('is-sorted-asc', 'is-sorted-desc');
        }
      });
      th.setAttribute('aria-sort', asc ? 'ascending' : 'descending');
      th.classList.toggle('is-sorted-asc', asc);
      th.classList.toggle('is-sorted-desc', !asc);
      rows.sort(function (a, b) {
        var av = cellText(a, index);
        var bv = cellText(b, index);
        var an = parseFloat(av.replace(/,/g, ''));
        var bn = parseFloat(bv.replace(/,/g, ''));
        var cmp;
        if (av !== '' && bv !== '' && !isNaN(an) && !isNaN(bn) && String(an) === av.replace(/,/g, '') && String(bn) === bv.replace(/,/g, '')) {
          cmp = an - bn;
        } else {
          cmp = av.localeCompare(bv, undefined, { numeric: true, sensitivity: 'base' });
        }
        return asc ? cmp : -cmp;
      });
      rows.forEach(function (row) {
        tbody.appendChild(row);
      });
      if (typeof table._applyTableFilter === 'function') table._applyTableFilter();
    }

    th.addEventListener('click', sort);
    th.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        sort();
      }
    });
  });
}

function cellText(row, index) {
  var cell = row.children[index];
  return cell ? cell.textContent.trim() : '';
}

(function () {
  var active = null;
  var tip = document.createElement('div');
  tip.className = 'status-tooltip';
  tip.id = 'study-status-tooltip';
  tip.setAttribute('role', 'tooltip');
  tip.hidden = true;
  document.body.appendChild(tip);
  function hide() {
    if (active) active.removeAttribute('aria-describedby');
    active = null;
    tip.hidden = true;
  }
  function show(button) {
    if (active && active !== button) hide();
    active = button;
    tip.replaceChildren();
    ['current', 'next'].forEach(function (key) {
      var label = document.createElement('strong');
      label.textContent = key === 'current' ? 'Currently' : 'Next step';
      var text = document.createElement('p');
      text.textContent = button.getAttribute('data-status-' + key) || '';
      tip.appendChild(label);
      tip.appendChild(text);
    });
    button.setAttribute('aria-describedby', tip.id);
    tip.hidden = false;
    var rect = button.getBoundingClientRect();
    var width = tip.offsetWidth;
    var height = tip.offsetHeight;
    tip.style.left = Math.max(12, Math.min(rect.left, window.innerWidth - width - 12)) + 'px';
    var below = rect.bottom + 8;
    tip.style.top = Math.max(12, below + height < window.innerHeight - 12 ? below : rect.top - height - 8) + 'px';
  }
  document.addEventListener('pointerover', function (event) {
    var button = event.target.closest('.status-help');
    if (button) show(button);
  });
  document.addEventListener('pointerout', function (event) {
    if (!active) return;
    var next = event.relatedTarget;
    if (next && (active.contains(next) || tip.contains(next))) return;
    if (active.contains(event.target) || tip.contains(event.target)) hide();
  });
  document.addEventListener('focusin', function (event) {
    var button = event.target.closest('.status-help');
    if (button) show(button);
  });
  document.addEventListener('focusout', function (event) {
    if (active && active.contains(event.target)) hide();
  });
  document.addEventListener('click', function (event) {
    var button = event.target.closest('.status-help');
    if (button) show(button);
    else if (!tip.contains(event.target)) hide();
  });
  document.addEventListener('keydown', function (event) { if (event.key === 'Escape') hide(); });
  window.addEventListener('resize', hide);
  document.addEventListener('scroll', hide, true);
})();
