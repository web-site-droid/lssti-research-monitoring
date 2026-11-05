(function () {
  var dialog = document.getElementById('account-admin-create');
  if (!dialog) return;
  document.querySelectorAll('[data-account-dialog-open]').forEach(function (button) {
    button.addEventListener('click', function () { dialog.showModal(); });
  });
  dialog.querySelectorAll('[data-account-dialog-close]').forEach(function (button) {
    button.addEventListener('click', function () { dialog.close(); });
  });
  dialog.addEventListener('close', function () { dialog.querySelector('[name="password"]').value = ''; });
  var edit = document.getElementById('account-admin-edit');
  if (!edit) return;
  document.querySelectorAll('[data-librarian-edit]').forEach(function (button) {
    button.addEventListener('click', function () {
      document.getElementById('librarian-edit-id').value = button.getAttribute('data-librarian-edit');
      ['identity', 'first', 'middle', 'last'].forEach(function (field) {
        document.getElementById('librarian-edit-' + field).value = button.getAttribute('data-' + field) || '';
      });
      document.getElementById('librarian-edit-active').checked = button.getAttribute('data-active') === '1';
      edit.showModal();
    });
  });
  edit.querySelectorAll('[data-librarian-edit-close]').forEach(function (button) {
    button.addEventListener('click', function () { edit.close(); });
  });
})();
