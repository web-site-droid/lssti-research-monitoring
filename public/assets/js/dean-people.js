(function () {
  var dialog = document.getElementById('dean-account-dialog');
  if (!dialog) return;
  document.querySelectorAll('[data-dean-view]').forEach(function (button) {
    button.addEventListener('click', function () {
      dialog.querySelectorAll('[data-dean-detail]').forEach(function (detail) {
        detail.textContent = button.getAttribute('data-account-' + detail.getAttribute('data-dean-detail')) || 'Not specified';
      });
      dialog.showModal();
    });
  });
  dialog.querySelectorAll('[data-dean-view-close]').forEach(function (button) {
    button.addEventListener('click', function () { dialog.close(); });
  });
  dialog.addEventListener('click', function (event) {
    if (event.target !== dialog) return;
    var rect = dialog.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
  });
})();
