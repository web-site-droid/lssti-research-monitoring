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
})();
