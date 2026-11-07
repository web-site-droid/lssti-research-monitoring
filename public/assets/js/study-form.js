function countWords(text) {
  text = text.trim().replace(/\s+/g, ' ');
  if (!text) return 0;
  return text.split(' ').length;
}

document.addEventListener('DOMContentLoaded', function () {
  var short = document.getElementById('short_title');
  var shortCount = document.getElementById('short-count');
  var abstract = document.getElementById('abstract');
  var wordCount = document.getElementById('word-count');
  var form = document.getElementById('study-form');
  var uploadDate = document.getElementById('upload_date');
  var useToday = document.getElementById('upload-date-today');
  if (uploadDate && useToday) {
    useToday.hidden = false;
    useToday.addEventListener('click', function () {
      uploadDate.value = uploadDate.max;
      uploadDate.dispatchEvent(new Event('input', { bubbles: true }));
      uploadDate.dispatchEvent(new Event('change', { bubbles: true }));
      uploadDate.focus();
    });
  }

  function updateShort() {
    if (short && shortCount) shortCount.textContent = String(short.value.length);
  }
  function updateWords() {
    if (abstract && wordCount) wordCount.textContent = String(countWords(abstract.value));
  }
  if (short) short.addEventListener('input', updateShort);
  if (abstract) abstract.addEventListener('input', updateWords);
  updateShort();
  updateWords();

  var intent = document.getElementById('intent');
  var saveDraft = document.getElementById('save-draft');
  var submitReview = document.getElementById('submit-review');
  if (saveDraft && intent) {
    saveDraft.addEventListener('click', function () {
      intent.value = 'draft';
    });
  }
  if (submitReview && intent) {
    submitReview.addEventListener('click', function () {
      intent.value = 'submit';
    });
  }

  var program = document.getElementById('program_id');
  var adviser = document.getElementById('adviser_user_id');
  function filterAdvisers() {
    if (!program || !adviser) return;
    var dept = program.value;
    var keep = adviser.value;
    Array.prototype.forEach.call(adviser.options, function (option) {
      if (!option.value) return;
      var show = option.getAttribute('data-program') === dept;
      option.hidden = !show;
      option.disabled = !show;
    });
    var current = adviser.querySelector('option[value="' + keep + '"]');
    if (!current || current.disabled) adviser.value = '';
  }
  if (program && adviser) {
    program.addEventListener('change', filterAdvisers);
    filterAdvisers();
  }

  var adviserField = document.getElementById('adviser-field');
  var submitReviewLabel = document.getElementById('submit-label');
  function syncPassChoice() {
    var selected = document.querySelector('input[name="pass_to"]:checked')
      || document.querySelector('input[name="pass_to"][type="hidden"]');
    var library = selected && selected.value === 'library';
    if (adviserField) adviserField.hidden = library;
    if (adviser) adviser.required = !library;
    if (submitReviewLabel && !library) submitReviewLabel.textContent = 'Pass to adviser';
    if (submitReviewLabel && library) submitReviewLabel.textContent = 'Submit';
  }
  document.querySelectorAll('input[name="pass_to"]').forEach(function (input) {
    input.addEventListener('change', syncPassChoice);
  });
  syncPassChoice();

  if (form) {
    form.addEventListener('submit', function (e) {
      if (abstract && countWords(abstract.value) > 350) {
        e.preventDefault();
        var message = 'Abstract must be at most 350 words.';
        if (typeof Swal !== 'undefined') {
          Swal.fire({ icon: 'error', title: message, confirmButtonColor: '#c8102e' });
        } else {
          alert(message);
        }
      }
    });
  }

  var list = document.getElementById('author-list');
  var addBtn = document.getElementById('add-author');
  function syncAuthorRemove() {
    if (!list) return;
    var rows = list.querySelectorAll('.author-row');
    var single = rows.length < 2;
    rows.forEach(function (row) {
      var btn = row.querySelector('.remove-author');
      if (btn) btn.hidden = single;
    });
  }
  if (list && addBtn) {
    syncAuthorRemove();
    addBtn.addEventListener('click', function () {
      var row = list.querySelector('.author-row');
      if (!row) return;
      var copy = row.cloneNode(true);
      copy.querySelectorAll('input').forEach(function (input) {
        input.value = '';
      });
      list.appendChild(copy);
      syncAuthorRemove();
    });
    list.addEventListener('click', function (event) {
      var btn = event.target.closest('.remove-author');
      if (!btn || btn.hidden) return;
      var rows = list.querySelectorAll('.author-row');
      if (rows.length < 2) return;
      btn.closest('.author-row').remove();
      syncAuthorRemove();
    });
  }
});
