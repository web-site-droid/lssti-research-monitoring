(function () {
  var CHUNK = 512 * 1024;

  function csrfValue(form) {
    var el = form.querySelector('input[name="_csrf"]');
    return el ? el.value : '';
  }

  function sendPart(form, file, token, index, total) {
    var start = index * CHUNK;
    var blob = file.slice(start, Math.min(file.size, start + CHUNK));
    var data = new FormData();
    data.append('_csrf', csrfValue(form));
    data.append('token', token);
    data.append('index', String(index));
    data.append('total', String(total));
    data.append('name', file.name);
    data.append('size', String(file.size));
    data.append('chunk', blob, file.name);
    return fetch(form.getAttribute('data-chunk-url'), {
      method: 'POST',
      body: data,
      credentials: 'same-origin'
    }).then(function (res) {
      return res.json().then(function (json) {
        if (!json || !json.ok) {
          throw new Error((json && json.error) || 'Manuscript: the upload failed. Try again.');
        }
        return json.token || token;
      });
    });
  }

  function uploadFile(form, file, onProgress) {
    var total = Math.max(1, Math.ceil(file.size / CHUNK));
    var token = '';
    var chain = Promise.resolve();
    var i;
    for (i = 0; i < total; i += 1) {
      (function (index) {
        chain = chain.then(function () {
          return sendPart(form, file, token, index, total).then(function (next) {
            token = next;
            if (onProgress) onProgress(index + 1, total);
            return token;
          });
        });
      })(i);
    }
    return chain;
  }

  function bindForm(form) {
    if (!form || form.getAttribute('data-chunk-bound') === '1') return;
    form.setAttribute('data-chunk-bound', '1');
    form.addEventListener('submit', function (event) {
      if (form.getAttribute('data-chunk-done') === '1') return;
      var input = form.querySelector('input[type="file"][name="manuscript"]');
      if (!input || !input.files || !input.files[0]) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      var file = input.files[0];
      var status = form.querySelector('.upload-progress');
      var buttons = form.querySelectorAll('button[type="submit"]');
      buttons.forEach(function (btn) { btn.disabled = true; });
      if (status) {
        status.hidden = false;
        status.textContent = 'Uploading manuscript…';
      }
      uploadFile(form, file, function (done, total) {
        if (status) status.textContent = 'Uploading manuscript… ' + done + ' / ' + total;
      }).then(function (token) {
        var hid = form.querySelector('input[name="manuscript_stored"]');
        if (!hid) {
          hid = document.createElement('input');
          hid.type = 'hidden';
          hid.name = 'manuscript_stored';
          form.appendChild(hid);
        }
        hid.value = token;
        var nameField = form.querySelector('input[name="manuscript_name"]');
        if (!nameField) {
          nameField = document.createElement('input');
          nameField.type = 'hidden';
          nameField.name = 'manuscript_name';
          form.appendChild(nameField);
        }
        nameField.value = file.name;
        input.removeAttribute('required');
        input.disabled = true;
        form.setAttribute('data-chunk-done', '1');
        if (status) status.textContent = 'Saving…';
        form.submit();
      }).catch(function (err) {
        buttons.forEach(function (btn) { btn.disabled = false; });
        if (status) status.hidden = true;
        var message = (err && err.message) ? err.message : 'Manuscript: the upload failed. Try again.';
        if (typeof Swal !== 'undefined') {
          Swal.fire({ icon: 'error', title: message, confirmButtonColor: '#c8102e' });
        } else {
          alert(message);
        }
      });
    }, true);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-chunk-url]').forEach(bindForm);
  });
})();
