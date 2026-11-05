<?php
$pageTitle = 'Edit profile';
$initials = strtoupper(substr((string) ($user['first_name'] ?? 'U'), 0, 1) . substr((string) ($user['last_name'] ?? ''), 0, 1));
?>
<div class="profile-page">
    <?php $hasPhoto = !empty($user['photo_path']); ?>
    <dialog id="profile-modal" class="preview-modal profile-modal" data-back="<?= e(url('dashboard')) ?>" aria-labelledby="profile-heading">
    <form method="post" action="<?= e(url('profile/update')) ?>" class="profile-editor stack" id="profile-form" enctype="multipart/form-data">
        <?= \App\Helpers\Csrf::field() ?>
        <section class="profile-editor-body">
            <div class="preview-modal-head">
                <h1 id="profile-heading">Edit profile</h1>
                <div class="profile-head-actions">
                <label class="profile-avatar" for="photo">
                    <?php if ($hasPhoto): ?>
                        <img id="photo-preview" src="<?= e(url('profile/photo', ['v' => (string) time()])) ?>" alt="">
                    <?php else: ?>
                        <img id="photo-preview" alt="" hidden>
                        <span id="photo-initials"><?= e($initials) ?></span>
                    <?php endif; ?>
                    <span class="profile-avatar-cam" title="Upload picture"><?= icon('upload') ?></span>
                    <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
                </label>
                <button type="button" class="btn btn-secondary btn-icon-only" id="profile-modal-close" aria-label="Close"><?= icon('x') ?></button>
                </div>
            </div>
            <?php if (!empty($success)): ?><p class="alert alert-success"><?= e($success) ?></p><?php endif; ?>
            <?php if (!empty($error)): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
            <?php
            $programs = $programs ?? [];
            $departmentRequired = !empty($departmentRequired);
            $savedProgramId = (int) ($user['program_id'] ?? 0);
            $canChangeDepartment = (int) ($user['created_by'] ?? 0) > 0
                && (int) ($user['created_by'] ?? 0) === (int) ($user['id'] ?? 0);
            $departmentLocked = !$canChangeDepartment;
            $savedProgramLabel = trim((string) (($user['program_code'] ?? '') . ' — ' . ($user['program_name'] ?? '')), ' —');
            $roleLabel = \App\Helpers\Role::label((string) ($user['role_slug'] ?? ''), (string) ($user['role_name'] ?? ''));
            ?>
            <div class="profile-landscape">
            <div>
            <label for="school_id">ID no.</label>
            <input id="school_id" value="<?= e($user['school_id']) ?>" readonly>
            </div>
            <div>
            <label for="first_name">First name <span class="req">*</span></label>
            <input id="first_name" name="first_name" required value="<?= e($user['first_name']) ?>">
            </div>
            <div>
            <label for="middle_name">Middle name</label>
            <input id="middle_name" name="middle_name" value="<?= e($user['middle_name'] ?? '') ?>">
            </div>
            <div>
            <label for="last_name">Last name <span class="req">*</span></label>
            <input id="last_name" name="last_name" required value="<?= e($user['last_name']) ?>">
            </div>
            <div>
            <label for="role_label">Role</label>
            <input id="role_label" value="<?= e($roleLabel) ?>" readonly>
            </div>
            <div>
            <?php if ($departmentLocked): ?>
                <label>Department</label>
                <input value="<?= e($savedProgramLabel !== '' ? $savedProgramLabel : 'No department') ?>" readonly>
            <?php else: ?>
                <label for="program_id">Department<?= $departmentRequired ? ' <span class="req">*</span>' : '' ?></label>
                <select id="program_id" name="program_id"<?= $departmentRequired ? ' required' : '' ?>>
                    <option value=""><?= $departmentRequired ? 'Select department' : 'No department' ?></option>
                    <?php foreach ($programs as $program): ?>
                        <option value="<?= (int) $program['id'] ?>" <?= $savedProgramId === (int) $program['id'] ? 'selected' : '' ?>><?= e($program['code'] . ' — ' . $program['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            </div>
            <div>
            <label for="email">Email <span class="req">*</span></label>
            <div class="profile-input">
                <input id="email" name="email" type="email" required value="<?= e($user['email'] ?? '') ?>" data-check="email">
                <span class="field-check" aria-hidden="true">✓</span>
            </div>
            </div>
            <div>
            <label for="contact_number">Contact number <span class="req">*</span></label>
            <input id="contact_number" name="contact_number" required inputmode="numeric" pattern="[0-9]{11}" maxlength="11" minlength="11" placeholder="09XXXXXXXXX" value="<?= e($user['contact_number'] ?? '') ?>" title="Enter 11 digits">
            </div>
            <div>
            <label for="state">Province <span class="req">*</span></label>
                    <?php $savedState = (string) ($user['state'] ?? ''); ?>
                    <select id="state" name="state" required>
                        <option value="">Select province</option>
                        <?php foreach (array_keys(\App\Helpers\Locations::places()) as $province): ?>
                            <option value="<?= e($province) ?>" <?= $savedState === $province ? 'selected' : '' ?>><?= e($province) ?></option>
                        <?php endforeach; ?>
                        <?php if ($savedState !== '' && !isset(\App\Helpers\Locations::places()[$savedState])): ?>
                            <option value="<?= e($savedState) ?>" selected><?= e($savedState) ?></option>
                        <?php endif; ?>
                    </select>
            </div>
            <div>
            <label for="city">City <span class="req">*</span></label>
            <select id="city" name="city" required data-saved="<?= e((string) ($user['city'] ?? '')) ?>">
                <option value="">Select city</option>
            </select>
            </div>
            <div>
            <label for="address">Barangay <span class="req">*</span></label>
            <select id="address" name="address" required data-saved="<?= e((string) ($user['address'] ?? '')) ?>">
                <option value="">Select barangay</option>
            </select>
            </div>
            </div>

            <h2 class="section-title">Change password</h2>
            <p class="muted">Leave the new password blank to keep the current one.</p>
            <div class="profile-landscape">
            <div>
            <label for="current_password">Current password</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password" placeholder="Required only when changing password">
            </div>
            <div>
            <label for="password">New password</label>
            <div class="profile-input">
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" placeholder="At least 8 characters" data-check="password">
                <span class="field-check" aria-hidden="true">✓</span>
            </div>
            </div>
            <div>
            <label for="password_confirm">Confirm new password</label>
            <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" minlength="8" placeholder="Type the new password again">
            </div>
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary"><?= icon('save') ?> Save</button>
                <button type="button" class="btn btn-secondary" id="profile-modal-cancel">Cancel</button>
            </div>
        </section>
    </form>
    </dialog>
</div>
<?php if (($bookmarks ?? []) !== []): ?>
<section class="card card-elevated paper-group is-wide" id="bookmarks">
    <h2 class="section-title section-title-plain">Bookmarks</h2>
    <div class="table-wrap">
        <table class="data-table paper-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Department</th>
                    <th>School year</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookmarks as $bookmark): ?>
                    <tr>
                        <td><?php if (can_open_full_file($user, $bookmark)): ?><a href="<?= e(url('reader', ['id' => $bookmark['id']])) ?>"><?= e($bookmark['title']) ?></a><?php else: ?><?= e($bookmark['title']) ?><?php endif; ?></td>
                        <td><?= e($bookmark['program_code']) ?></td>
                        <td><?= e($bookmark['school_year']) ?></td>
                        <td>
                            <form method="post" action="<?= e(url('search/bookmark')) ?>" class="inline-form">
                                <?= \App\Helpers\Csrf::field() ?>
                                <input type="hidden" name="study_id" value="<?= (int) $bookmark['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-icon-only" aria-label="Remove" title="Remove"><?= icon('x') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
<?php if (($user['role_slug'] ?? '') === 'student'): ?>
<?php
$drafts = [];
foreach ($studies as $study) {
    if (in_array($study['status'], ['draft', 'revision_requested'], true)) {
        $drafts[] = $study;
    }
}
?>
<?php if ($drafts !== []): ?>
<div class="paper-tables" id="study-list">
    <?php if ($drafts !== []): ?>
    <section class="card card-elevated paper-group is-wide">
        <h2 class="section-title section-title-plain">Draft</h2>
        <label class="study-search" for="search-draft">Search
            <span class="study-search-field">
                <?= icon('search') ?>
                <input type="search" class="paper-search" id="search-draft" placeholder="Search draft" autocomplete="off">
            </span>
        </label>
        <p class="paper-none empty-state" hidden>No papers match this search.</p>
        <form method="post" action="<?= e(url('studies/delete')) ?>" id="draft-actions-form" data-confirm="Do you want to delete this study? You cannot undo this.">
            <?= \App\Helpers\Csrf::field() ?>
            <input type="hidden" name="id" value="">
            <div class="table-wrap">
                <table class="data-table paper-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($drafts as $study): ?>
                            <tr class="paper-row" data-title="<?= e(strtolower($study['short_title'])) ?>">
                                <td><a href="<?= e(url('studies/view', ['id' => $study['id']])) ?>"><?= e($study['short_title']) ?></a></td>
                                <td class="actions-cell">
                                    <?= action_menu(
                                        '<a class="btn btn-secondary btn-icon-only" href="' . e(url('studies/edit', ['id' => $study['id']])) . '" aria-label="Edit" title="Edit">' . icon('pencil') . '</a>'
                                        . '<button type="submit" class="btn btn-danger btn-icon-only" data-study-id="' . (int) $study['id'] . '" aria-label="Delete" title="Delete">' . icon('trash') . '</button>'
                                    ) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </section>
    <?php endif; ?>
</div>
<?php endif; ?>
<script>
(function () {
  document.querySelectorAll('.paper-group').forEach(function (group) {
    var input = group.querySelector('.paper-search');
    var empty = group.querySelector('.paper-none');
    if (!input) return;
    input.addEventListener('input', function () {
      var query = input.value.trim().toLowerCase();
      var shown = 0;
      group.querySelectorAll('.paper-row').forEach(function (row) {
        var match = !query || (row.getAttribute('data-title') || '').indexOf(query) !== -1;
        row.hidden = !match;
        if (match) shown += 1;
      });
      var blank = group.querySelector('.paper-empty');
      if (blank) blank.hidden = query !== '';
      if (empty) empty.hidden = query === '' || shown !== 0;
    });
  });
  var draftForm = document.getElementById('draft-actions-form');
  if (!draftForm) return;
  draftForm.querySelectorAll('[data-study-id]').forEach(function (button) {
    button.addEventListener('click', function () {
      draftForm.querySelector('input[name="id"]').value = button.getAttribute('data-study-id');
    });
  });
})();
</script>
<?php endif; ?>
<script>
(function () {
  function valid(input) {
    var value = input.value.trim();
    if (input.getAttribute('data-check') === 'email') return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    if (input.getAttribute('data-check') === 'password') return value.length >= 8;
    return value.length > 0;
  }
  var places = <?= json_encode(\App\Helpers\Locations::places(), JSON_UNESCAPED_UNICODE) ?>;
  var barangays = <?= json_encode(\App\Helpers\Locations::barangays(), JSON_UNESCAPED_UNICODE) ?>;
  var stateSelect = document.getElementById('state');
  var citySelect = document.getElementById('city');
  var barangaySelect = document.getElementById('address');
  function fillSelect(select, placeholder, items, saved) {
    select.innerHTML = '<option value="">' + placeholder + '</option>';
    items.forEach(function (name) {
      var option = document.createElement('option');
      option.value = name;
      option.textContent = name;
      if (name === saved) option.selected = true;
      select.appendChild(option);
    });
  }
  function fillCities() {
    var saved = citySelect.getAttribute('data-saved') || '';
    var province = stateSelect.value;
    var cities = places[province] ? places[province].slice() : [];
    if (saved && cities.indexOf(saved) === -1 && province) cities.unshift(saved);
    fillSelect(citySelect, 'Select city', cities, saved);
    fillBarangays();
  }
  function fillBarangays() {
    if (!barangaySelect) return;
    var saved = barangaySelect.getAttribute('data-saved') || '';
    var province = stateSelect.value;
    var city = citySelect.value;
    var items = barangays[province] && barangays[province][city] ? barangays[province][city].slice() : [];
    if (saved && items.indexOf(saved) === -1 && city) items.unshift(saved);
    fillSelect(barangaySelect, 'Select barangay', items, saved);
  }
  if (stateSelect && citySelect) {
    stateSelect.addEventListener('change', function () {
      citySelect.setAttribute('data-saved', '');
      if (barangaySelect) barangaySelect.setAttribute('data-saved', '');
      fillCities();
    });
    citySelect.addEventListener('change', function () {
      if (barangaySelect) barangaySelect.setAttribute('data-saved', '');
      fillBarangays();
    });
    fillCities();
  }
  document.querySelectorAll('#profile-form [data-check]').forEach(function (input) {
    var wrap = input.closest('.profile-input');
    function paint() { wrap.classList.toggle('is-valid', valid(input)); }
    input.addEventListener('input', paint);
    paint();
  });
  var photo = document.getElementById('photo');
  var preview = document.getElementById('photo-preview');
  if (photo && preview) {
    photo.addEventListener('change', function () {
      var file = photo.files && photo.files[0];
      if (!file) return;
      preview.src = URL.createObjectURL(file);
      preview.hidden = false;
      var initials = document.getElementById('photo-initials');
      if (initials) initials.hidden = true;
    });
  }
})();
</script>
