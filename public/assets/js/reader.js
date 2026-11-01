(function () {
  var speechRoot = document.getElementById('reader-speech');
  var fileUrl = speechRoot ? (speechRoot.getAttribute('data-file-url') || '') : '';
  var ext = ((speechRoot && speechRoot.getAttribute('data-ext')) || '').toLowerCase();
  var workerSrc = speechRoot ? (speechRoot.getAttribute('data-pdf-worker') || '') : '';
  var pageNav = document.getElementById('page-nav');
  var status = document.getElementById('tts-status');
  var playBtn = document.getElementById('tts-play');
  var sections = [];
  var queue = [];
  var utterIndex = 0;
  var speaking = false;
  var paused = false;
  var fileReady = !fileUrl;
  var wantsPlay = false;
  var currentUtterance = null;
  var keepAlive = null;
  var endWatch = null;
  var holdUntil = 0;
  var unlocked = false;
  var synth = window.speechSynthesis || null;

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
      var next = (buf + ' ' + part).trim();
      if (next.length > 160) {
        if (buf.trim()) out.push(buf.trim());
        buf = part;
      } else {
        buf = next;
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

  function addSection(el, text, label) {
    var clean = (text || '').replace(/\s+/g, ' ').trim();
    if (!el || !clean) return;
    sections.push({ el: el, text: clean, label: label });
  }

  function addFrontMatter() {
    var title = document.querySelector('.reader-head h1');
    if (title) addSection(title, title.textContent || '', 'Title');
    var block = document.getElementById('abstract-text');
    if (block) addSection(block, block.textContent || '', 'Abstract');
    var keys = document.getElementById('keyword-text');
    if (keys && keys.textContent && keys.textContent.trim() !== 'None listed.') {
      addSection(keys, 'Keywords. ' + keys.textContent, 'Keywords');
    }
    var obj = document.getElementById('objectives-text');
    if (obj) addSection(obj, 'Research objectives. ' + obj.textContent, 'Research objectives');
  }

  function loadAbstract() {
    sections = [];
    addFrontMatter();
    rebuildQueue();
    fileReady = true;
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

  function isPdfBuffer(buf) {
    var u8 = new Uint8Array(buf);
    return u8.length >= 4 && u8[0] === 0x25 && u8[1] === 0x50 && u8[2] === 0x44 && u8[3] === 0x46;
  }

  function onManuscriptReady() {
    fileReady = true;
    rebuildQueue();
    if (!queue.length) {
      setStatus('This file has no readable text. Download it to read the study.');
      return;
    }
    if (wantsPlay) {
      wantsPlay = false;
      beginSpeak();
      return;
    }
    setStatus('Press Play to hear the whole paper.');
  }

  function loadDocx(buf) {
    if (typeof mammoth === 'undefined') return Promise.reject(new Error('docx'));
    return mammoth.convertToHtml({ arrayBuffer: buf }).then(function (result) {
      var holder = document.createElement('div');
      holder.innerHTML = result.value;
      stripAutoLinks(holder);
      var preview = document.getElementById('docx-preview');
      if (!preview) throw new Error('preview');
      preview.innerHTML = '';
      var blocks = Array.prototype.slice.call(holder.querySelectorAll('p, h1, h2, h3, h4, li, td'));
      if (!blocks.length) {
        preview.textContent = (holder.textContent || '').trim() || 'Could not preview this file. Download it to read the study.';
        preview.hidden = false;
        var all = (holder.textContent || '').trim();
        if (all) sections.push({ el: preview, text: all, label: 'Manuscript' });
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
        var text = (sheet.textContent || '').replace(/\s+/g, ' ').trim();
        if (text) sections.push({ el: sheet, text: text, label: 'Page ' + (page + 1) });
      }
      preview.hidden = false;
      markPage(1);
    });
  }

  function pageText(page) {
    return page.getTextContent().then(function (content) {
      var lines = [];
      var line = '';
      var lastY = null;
      (content.items || []).forEach(function (item) {
        var str = item.str || '';
        var y = item.transform ? item.transform[5] : null;
        if (lastY !== null && y !== null && Math.abs(lastY - y) > 3) {
          if (line.trim()) lines.push(line.trim());
          line = '';
        }
        line += (line && !/\s$/.test(line) && str && !/^\s/.test(str) ? ' ' : '') + str;
        lastY = y;
      });
      if (line.trim()) lines.push(line.trim());
      return lines.join(' ').replace(/\s+/g, ' ').trim();
    });
  }

  function loadPdf(buf) {
    var viewer = document.getElementById('pdf-viewer');
    if (!viewer || typeof pdfjsLib === 'undefined') return Promise.reject(new Error('pdf'));
    var basePath = window.location.pathname.replace(/\/index\.php.*$/, '');
    pdfjsLib.GlobalWorkerOptions.workerSrc = workerSrc || (basePath + '/assets/vendor/pdfjs/build/pdf.worker.js');
    return pdfjsLib.getDocument({ data: buf }).promise.then(function (pdf) {
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
              if (text) sections.push({ el: wrap, text: text, label: 'Page ' + page.pageNumber + ' of ' + total });
            });
        });
      }
      for (var p = 1; p <= total; p++) {
        chain = chain.then(renderPage.bind(null, p));
      }
      return chain;
    });
  }

  function loadManuscript() {
    if (!fileUrl) {
      loadAbstract();
      return;
    }
    sections = [];
    addFrontMatter();
    setStatus('Loading the whole paper…');
    fetch(fileUrl, { credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) throw new Error('file');
        return r.arrayBuffer();
      })
      .then(function (buf) {
        var pdf = isPdfBuffer(buf) || ext === 'pdf';
        if (pdf) return loadPdf(buf);
        return loadDocx(buf).catch(function () { return loadPdf(buf); });
      })
      .then(onManuscriptReady)
      .catch(function () {
        var viewer = document.getElementById('pdf-viewer');
        if (viewer && !viewer.childElementCount) {
          viewer.textContent = 'Could not preview this file. Download it to read the study.';
        }
        rebuildQueue();
        fileReady = true;
        if (queue.length) {
          setStatus('Could not read the file. Press Play to hear the title and abstract.');
          if (wantsPlay) {
            wantsPlay = false;
            beginSpeak();
          }
          return;
        }
        setStatus('Could not read the file aloud. Download it to read the study.');
      });
  }

  function clearTimers() {
    if (keepAlive) {
      clearInterval(keepAlive);
      keepAlive = null;
    }
    if (endWatch) {
      clearTimeout(endWatch);
      endWatch = null;
    }
  }

  function startKeepAlive() {
    clearTimers();
    if (!synth) return;
    keepAlive = setInterval(function () {
      if (!speaking || paused || !synth.speaking) return;
      holdUntil = Date.now() + 500;
      try {
        synth.pause();
        synth.resume();
      } catch (err) {}
    }, 10000);
  }

  function chosenVoice() {
    if (!synth) return null;
    var voices = synth.getVoices() || [];
    var voiceSelect = document.getElementById('tts-voice');
    var vi = voiceSelect ? parseInt(voiceSelect.value, 10) : NaN;
    if (!isNaN(vi) && voices[vi]) return voices[vi];
    return preferredVoice(voices);
  }

  function preferredVoice(voices) {
    var i;
    var score = function (v) {
      var lang = (v.lang || '').toLowerCase();
      var name = (v.name || '').toLowerCase();
      var n = 0;
      if (lang.indexOf('en') === 0) n += 4;
      if (lang.indexOf('en-ph') === 0 || lang.indexOf('en-us') === 0) n += 2;
      if (name.indexOf('google') !== -1 || name.indexOf('natural') !== -1) n += 2;
      if (name.indexOf('microsoft') !== -1) n += 1;
      return n;
    };
    var best = null;
    var bestScore = -1;
    for (i = 0; i < voices.length; i++) {
      var n = score(voices[i]);
      if (n > bestScore) {
        best = voices[i];
        bestScore = n;
      }
    }
    return best || voices[0] || null;
  }

  function unlockSpeech() {
    if (unlocked || !synth) return;
    unlocked = true;
    try {
      var kick = new SpeechSynthesisUtterance(' ');
      kick.volume = 0;
      kick.rate = 1;
      synth.speak(kick);
      synth.cancel();
    } catch (err) {}
  }

  function finishSpeak() {
    speaking = false;
    paused = false;
    currentUtterance = null;
    clearTimers();
    markReading(null);
    setStatus('Finished the whole paper.');
  }

  function speakNext() {
    if (!synth) return;
    if (!speaking || paused) return;
    if (utterIndex >= queue.length) {
      finishSpeak();
      return;
    }
    var item = queue[utterIndex];
    markReading(item.el);
    setStatus('Playing ' + item.label + '.');
    var u = new SpeechSynthesisUtterance(item.text);
    currentUtterance = u;
    var voice = chosenVoice();
    if (voice) {
      u.voice = voice;
      u.lang = voice.lang || 'en-US';
    } else {
      u.lang = 'en-US';
    }
    u.rate = parseFloat((document.getElementById('tts-rate') || {}).value) || 1;
    function advance() {
      if (currentUtterance !== u) return;
      currentUtterance = null;
      if (endWatch) {
        clearTimeout(endWatch);
        endWatch = null;
      }
      if (!speaking || paused) return;
      utterIndex++;
      speakNext();
    }
    u.onend = function () {
      if (Date.now() < holdUntil) return;
      advance();
    };
    u.onerror = function () {
      if (Date.now() < holdUntil) return;
      advance();
    };
    if (endWatch) clearTimeout(endWatch);
    var waitMs = Math.max(4000, Math.round((item.text.length / Math.max(8, 12 * u.rate)) * 1000) + 2500);
    endWatch = setTimeout(function () {
      if (currentUtterance !== u || !speaking || paused) return;
      advance();
    }, waitMs);
    try {
      synth.speak(u);
    } catch (err) {
      utterIndex++;
      speakNext();
    }
  }

  function beginSpeak() {
    if (!synth) {
      setStatus('This browser cannot speak. Use Chrome or Edge.');
      return;
    }
    rebuildQueue();
    if (!queue.length) {
      setStatus(fileUrl && !fileReady ? 'Loading the whole paper…' : 'Nothing to play yet.');
      return;
    }
    speaking = true;
    paused = false;
    if (utterIndex >= queue.length) utterIndex = 0;
    startKeepAlive();
    if (synth.speaking || synth.pending || synth.paused) {
      try { synth.cancel(); } catch (err) {}
      currentUtterance = null;
      setTimeout(speakNext, 180);
    } else {
      speakNext();
    }
  }

  function loadVoices() {
    var voiceSelect = document.getElementById('tts-voice');
    if (!voiceSelect || !synth) return;
    var voices = synth.getVoices() || [];
    var current = voiceSelect.value;
    var preferred = preferredVoice(voices);
    voiceSelect.innerHTML = '';
    if (!voices.length) {
      var empty = document.createElement('option');
      empty.value = '';
      empty.textContent = 'Default voice';
      voiceSelect.appendChild(empty);
      return;
    }
    voices.forEach(function (v, i) {
      var opt = document.createElement('option');
      opt.value = String(i);
      opt.textContent = v.name + ' (' + v.lang + ')';
      voiceSelect.appendChild(opt);
    });
    if (current && voiceSelect.querySelector('option[value="' + current + '"]')) {
      voiceSelect.value = current;
    } else if (preferred) {
      voiceSelect.value = String(voices.indexOf(preferred));
    }
  }

  var rate = document.getElementById('tts-rate');
  var rateValue = document.getElementById('tts-rate-value');
  if (rate && rateValue) {
    rate.addEventListener('input', function () {
      rateValue.textContent = Number(rate.value).toFixed(1);
    });
  }

  if (playBtn) {
    playBtn.addEventListener('click', function () {
      unlockSpeech();
      if (!synth) {
        setStatus('This browser cannot speak. Use Chrome or Edge.');
        return;
      }
      if (paused) {
        paused = false;
        speaking = true;
        startKeepAlive();
        beginSpeak();
        return;
      }
      if (speaking) return;
      if (fileUrl && !fileReady) {
        wantsPlay = true;
        setStatus('Loading the whole paper…');
        return;
      }
      beginSpeak();
    });
  }

  var pauseBtn = document.getElementById('tts-pause');
  if (pauseBtn) {
    pauseBtn.addEventListener('click', function () {
      if (!speaking || paused) return;
      paused = true;
      speaking = true;
      clearTimers();
      currentUtterance = null;
      if (synth) {
        try { synth.cancel(); } catch (err) {}
      }
      setStatus('Paused.');
    });
  }

  var stopBtn = document.getElementById('tts-stop');
  if (stopBtn) {
    stopBtn.addEventListener('click', function () {
      speaking = false;
      paused = false;
      wantsPlay = false;
      utterIndex = 0;
      currentUtterance = null;
      clearTimers();
      if (synth) {
        try { synth.cancel(); } catch (err) {}
      }
      markReading(null);
      setStatus('Stopped.');
    });
  }

  function markPage(number) {
    if (!pageNav) return;
    pageNav.querySelectorAll('a').forEach(function (link) {
      link.classList.toggle('is-current', link.getAttribute('href') === '#pdf-page-' + number);
    });
  }

  if (synth) {
    loadVoices();
    if (typeof synth.onvoiceschanged !== 'undefined') {
      synth.onvoiceschanged = loadVoices;
    }
  } else {
    setStatus('This browser cannot speak. Use Chrome or Edge.');
  }
  window.requestAnimationFrame(loadManuscript);
})();
