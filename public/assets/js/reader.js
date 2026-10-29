(function () {
  var root = document.getElementById('reader-content');
  if (!root) return;
  var fileUrl = root.getAttribute('data-file-url');
  var ext = root.getAttribute('data-ext');
  var pageNav = document.getElementById('page-nav');
  var status = document.getElementById('tts-status');
  var sections = [];
  var queue = [];
  var utterIndex = 0;
  var speaking = false;
  var paused = false;
  var currentUtterance = null;
  var synth = window.speechSynthesis;

  function setStatus(text) {
    if (status) status.textContent = text;
  }

  function chunks(text) {
    var clean = (text || '').replace(/\s+/g, ' ').trim();
    if (!clean) return [];
    var parts = clean.match(/[^.!?]+[.!?]?/g) || [clean];
    var out = [];
    var buf = '';
    parts.forEach(function (part) {
      if ((buf + ' ' + part).trim().length > 220) {
        if (buf.trim()) out.push(buf.trim());
        buf = part;
      } else {
        buf = (buf + ' ' + part).trim();
      }
    });
    if (buf.trim()) out.push(buf.trim());
    return out;
  }

  function rebuildQueue() {
    queue = [];
    sections.forEach(function (sec) {
      chunks(sec.text).forEach(function (text) {
        queue.push({ el: sec.el, text: text, label: sec.label });
      });
    });
  }

  function markReading(el) {
    document.querySelectorAll('.is-reading').forEach(function (node) {
      node.classList.remove('is-reading');
    });
    if (!el) return;
    var page = el.closest ? (el.closest('.pdf-page') || el) : el;
    if (page.classList) page.classList.add('is-reading');
    if (page.scrollIntoView) page.scrollIntoView({ behavior: 'smooth', block: 'center' });
    if (pageNav && page.id) {
      pageNav.querySelectorAll('a').forEach(function (link) {
        link.classList.toggle('is-current', link.getAttribute('href') === '#' + page.id);
      });
    }
  }

  function loadAbstract() {
    var block = document.getElementById('abstract-text');
    if (block) sections.push({ el: block, text: block.textContent || '', label: 'Abstract' });
    var keys = document.getElementById('keyword-text');
    if (keys && keys.textContent && keys.textContent.trim() !== 'None listed.') {
      sections.push({ el: keys, text: 'Keywords. ' + keys.textContent, label: 'Keywords' });
    }
    rebuildQueue();
  }

  function stripAutoLinks(container) {
    container.querySelectorAll('a').forEach(function (a) {
      var span = document.createElement('span');
      span.textContent = a.textContent;
      a.replaceWith(span);
    });
  }

  function addPageLink(id, label) {
    if (!pageNav) return;
    pageNav.hidden = false;
    var link = document.createElement('a');
    link.href = '#' + id;
    link.textContent = label;
    link.addEventListener('click', function () {
      var target = document.getElementById(id);
      if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    pageNav.appendChild(link);
  }

  function loadDocx() {
    fetch(fileUrl, { credentials: 'same-origin' })
      .then(function (r) { return r.arrayBuffer(); })
      .then(function (buf) { return mammoth.convertToHtml({ arrayBuffer: buf }); })
      .then(function (result) {
        var holder = document.createElement('div');
        holder.innerHTML = result.value;
        stripAutoLinks(holder);
        var preview = document.getElementById('docx-preview');
        if (!preview) return;
        preview.innerHTML = '';
        var blocks = Array.prototype.slice.call(holder.querySelectorAll('p, h1, h2, h3, li'));
        if (!blocks.length) {
          preview.textContent = 'Could not preview this file. Download it to read the study.';
          preview.hidden = false;
          return;
        }
        var perPage = 8;
        var pages = Math.max(1, Math.ceil(blocks.length / perPage));
        for (var page = 0; page < pages; page++) {
          var sheet = document.createElement('div');
          sheet.className = 'pdf-page docx-page';
          sheet.id = 'pdf-page-' + (page + 1);
          var slice = blocks.slice(page * perPage, (page + 1) * perPage);
          slice.forEach(function (block) {
            sheet.appendChild(block);
          });
          preview.appendChild(sheet);
          addPageLink(sheet.id, String(page + 1));
          sections.push({ el: sheet, text: sheet.textContent || '', label: 'Page ' + (page + 1) });
        }
        if (!speaking) rebuildQueue();
        preview.hidden = false;
        markPage(1);
      })
      .catch(function () {
        var preview = document.getElementById('docx-preview');
        if (preview) preview.textContent = 'Could not preview this file.';
      });
  }

  function pageText(page) {
    return page.getTextContent().then(function (content) {
      var lines = [];
      var line = '';
      var lastY = null;
      content.items.forEach(function (item) {
        var y = item.transform ? item.transform[5] : null;
        if (lastY !== null && y !== null && Math.abs(lastY - y) > 3) {
          lines.push(line.trim());
          line = '';
        }
        line += item.str;
        lastY = y;
      });
      if (line.trim()) lines.push(line.trim());
      return lines.join(' ').replace(/\s+/g, ' ').trim();
    });
  }

  function loadPdf() {
    var viewer = document.getElementById('pdf-viewer');
    if (!viewer || typeof pdfjsLib === 'undefined') return;
    var basePath = window.location.pathname.replace(/\/index\.php.*$/, '');
    pdfjsLib.GlobalWorkerOptions.workerSrc = basePath + '/assets/vendor/pdfjs/build/pdf.worker.js';
    fetch(fileUrl, { credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) throw new Error('file');
        return r.arrayBuffer();
      })
      .then(function (data) {
        return pdfjsLib.getDocument({ data: data }).promise;
      })
      .then(function (pdf) {
        var total = pdf.numPages;
        var chain = Promise.resolve();
        function renderPage(pageNumber) {
          return pdf.getPage(pageNumber).then(function (page) {
            var wrap = document.createElement('div');
            wrap.className = 'pdf-page';
            wrap.id = 'pdf-page-' + page.pageNumber;
            var canvas = document.createElement('canvas');
            var unscaled = page.getViewport({ scale: 1 });
            var maxWidth = Math.max(280, (viewer.clientWidth || 720) - 8);
            var scale = Math.min(1.35, maxWidth / unscaled.width);
            var viewport = page.getViewport({ scale: scale });
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            wrap.appendChild(canvas);
            viewer.appendChild(wrap);
            addPageLink(wrap.id, String(page.pageNumber));
            if (page.pageNumber === 1) markPage(1);
            return page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise
              .then(function () { return pageText(page); })
              .then(function (text) {
                sections.push({ el: wrap, text: text, label: 'Page ' + page.pageNumber });
                if (!speaking) rebuildQueue();
              });
          });
        }
        for (var p = 1; p <= total; p++) {
          chain = chain.then(renderPage.bind(null, p));
        }
        return chain;
      })
      .catch(function () {
        viewer.textContent = 'Could not preview this file. Download it to read the study.';
      });
  }

  function speakNext() {
    if (!speaking || paused || utterIndex >= queue.length) {
      if (speaking && utterIndex >= queue.length) {
        speaking = false;
        markReading(null);
        setStatus('Finished.');
      }
      return;
    }
    var item = queue[utterIndex];
    markReading(item.el);
    setStatus('Playing ' + item.label + '.');
    var u = new SpeechSynthesisUtterance(item.text);
    currentUtterance = u;
    var voices = synth.getVoices();
    var voiceSelect = document.getElementById('tts-voice');
    var vi = voiceSelect ? parseInt(voiceSelect.value, 10) : 0;
    if (voices[vi]) u.voice = voices[vi];
    u.rate = parseFloat(document.getElementById('tts-rate').value) || 1;
    u.onend = function () {
      if (!speaking || paused) return;
      utterIndex++;
      speakNext();
    };
    u.onerror = function () {
      if (!speaking) return;
      utterIndex++;
      speakNext();
    };
    synth.speak(u);
  }

  function loadVoices() {
    var voiceSelect = document.getElementById('tts-voice');
    if (!voiceSelect) return;
    var voices = synth.getVoices();
    var current = voiceSelect.value;
    voiceSelect.innerHTML = '';
    voices.forEach(function (v, i) {
      var opt = document.createElement('option');
      opt.value = String(i);
      opt.textContent = v.name + ' (' + v.lang + ')';
      voiceSelect.appendChild(opt);
    });
    if (current && voiceSelect.querySelector('option[value="' + current + '"]')) {
      voiceSelect.value = current;
    }
  }

  var rate = document.getElementById('tts-rate');
  var rateValue = document.getElementById('tts-rate-value');
  if (rate && rateValue) {
    rate.addEventListener('input', function () {
      rateValue.textContent = Number(rate.value).toFixed(1);
    });
  }

  document.getElementById('tts-play').addEventListener('click', function () {
    if (paused) {
      paused = false;
      speaking = true;
      synth.resume();
      setStatus('Playing.');
      if (!synth.speaking) speakNext();
      return;
    }
    if (speaking) return;
    rebuildQueue();
    if (!queue.length) {
      setStatus('Nothing to play yet.');
      return;
    }
    speaking = true;
    paused = false;
    if (utterIndex >= queue.length) utterIndex = 0;
    if (synth.speaking || synth.pending) {
      synth.cancel();
      setTimeout(speakNext, 80);
    } else {
      speakNext();
    }
  });

  document.getElementById('tts-pause').addEventListener('click', function () {
    if (!speaking) return;
    paused = true;
    synth.pause();
    setStatus('Paused.');
  });

  document.getElementById('tts-stop').addEventListener('click', function () {
    speaking = false;
    paused = false;
    utterIndex = 0;
    currentUtterance = null;
    synth.cancel();
    markReading(null);
    setStatus('Stopped.');
  });

  function markPage(number) {
    if (!pageNav) return;
    pageNav.querySelectorAll('a').forEach(function (link) {
      link.classList.toggle('is-current', link.getAttribute('href') === '#pdf-page-' + number);
    });
  }

  loadVoices();
  if (typeof synth.onvoiceschanged !== 'undefined') {
    synth.onvoiceschanged = loadVoices;
  }
  loadAbstract();
  window.requestAnimationFrame(function () {
    if (ext === 'docx') loadDocx();
    else if (ext === 'pdf') loadPdf();
  });
})();
