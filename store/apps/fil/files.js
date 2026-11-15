/* ИТК · фото и файлы для Фила (№17) — общий для «Воронок сделок» (и других окон).
   FilFiles.read(fileList, api, onChange) → Promise со списком [{name, id, kind, chars, err}].
   Фото уменьшаем до 2000 точек и отдаём на распознавание (Яндекс), PDF с текстом читаем прямо в телефоне,
   Word/Excel/текст разбирает сервер (fc_file). */
(function () {
  var F = window.FilFiles = {};
  function b64(buf) { var u8 = new Uint8Array(buf), s = '', CH = 0x8000; for (var i = 0; i < u8.length; i += CH) s += String.fromCharCode.apply(null, u8.subarray(i, i + CH)); return btoa(s); }
  function shrink(file) {
    return new Promise(function (ok, no) {
      var img = new Image(), u = URL.createObjectURL(file);
      img.onload = function () { var k = Math.min(1, 2000 / Math.max(img.width, img.height)), c = document.createElement('canvas'); c.width = Math.round(img.width * k); c.height = Math.round(img.height * k);
        var g = c.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height);   // №627 · прозрачное — на белом
        g.drawImage(img, 0, 0, c.width, c.height); URL.revokeObjectURL(u); ok(c.toDataURL('image/jpeg', 0.84).split(',')[1]); };
      img.onerror = function () { URL.revokeObjectURL(u); no(); }; img.src = u;
    });
  }
  var PDFJS = null;
  function pdfLib() {
    if (PDFJS) return PDFJS;
    PDFJS = new Promise(function (ok, no) { var s = document.createElement('script'); s.src = '/apps/fil/lib/pdf.min.js'; s.onload = function () { pdfjsLib.GlobalWorkerOptions.workerSrc = '/apps/fil/lib/pdf.worker.min.js'; ok(pdfjsLib); }; s.onerror = no; document.head.appendChild(s); });
    return PDFJS;
  }
  function pdfText(buf) {
    return pdfLib().then(function (L) { return L.getDocument({ data: buf }).promise; }).then(function (doc) {
      var n = Math.min(doc.numPages, 40), out = [], p = Promise.resolve();
      for (var i = 1; i <= n; i++) (function (i) { p = p.then(function () { return doc.getPage(i).then(function (pg) { return pg.getTextContent(); }).then(function (tc) {
        var last = null, line = ''; tc.items.forEach(function (it) { var y = Math.round(it.transform[5]); if (last !== null && Math.abs(y - last) > 3) { out.push(line); line = ''; } line += (line && !/\s$/.test(line) ? ' ' : '') + it.str; last = y; });
        out.push(line); }); }); })(i);
      return p.then(function () { return out.join('\n').replace(/\n{3,}/g, '\n\n').trim(); });
    });
  }
  function one(a, file, api) {
    var name = file.name || ('Фото ' + new Date().toTimeString().substr(0, 5) + '.jpg'), ext = (name.split('.').pop() || '').toLowerCase(); a.name = name;
    var send = function (d) { return api('fc_file', d).then(function (r) { return (!r || !r.ok) && d.data && d.text ? api('fc_file', { name: d.name, text: d.text }) : r; }).then(function (r) { a.busy = false; if (!r || !r.ok) a.err = (r && r.error) || 'не прочитался'; else { a.id = r.id; a.kind = r.kind; a.chars = r.chars; } return a; }); };
    // №581 · большой файл (больше 12 МБ, до 500 МБ) — кусками по 2 МБ
    if (file.size > 12 * 1048576 && !/^image\//.test(file.type)) {
      if (file.size > 500 * 1048576) { a.busy = false; a.err = 'больше 500 МБ — дайте ссылкой'; return Promise.resolve(a); }
      return api('fc_up_start', { name: name, mime: file.type || '', size: file.size }).then(function (r) {
        if (!r || !r.ok) return r; var up = r.up, ch = r.chunk || 2097152, off = 0, tries = 0;
        var next = function () {
          if (off >= file.size) return api('fc_up_done', { up: up, text: '' });
          return file.slice(off, off + ch).arrayBuffer().then(function (b) { return api('fc_up_part', { up: up, off: off, data: b64(b) }); }).then(function (x) {
            if (!x || !x.ok) throw new Error((x && x.error) || 'обрыв'); tries = 0; off = x.have; a.prog = Math.floor(off / file.size * 100); return next();
          }, function (e) { if (++tries > 4) throw e; return new Promise(function (ok) { setTimeout(ok, 2000 * tries); }).then(next); });
        };
        return next();
      }).then(function (r) { a.busy = false; if (!r || !r.ok) a.err = (r && r.error) || 'не загрузился'; else { a.id = r.id; a.kind = r.kind; a.chars = r.chars; } return a; },
        function () { a.busy = false; a.err = 'связь оборвалась — прикрепите ещё раз'; return a; });
    }
    if (/^image\//.test(file.type) || /^(jpe?g|png|heic|webp)$/.test(ext)) return shrink(file).then(function (d) { return send({ name: name, mime: 'image/jpeg', data: d }); }, function () { return file.arrayBuffer().then(function (b) { return send({ name: name, mime: file.type, data: b64(b) }); }); });
    return file.arrayBuffer().then(function (buf) {
      if (ext === 'pdf') return pdfText(buf.slice(0)).then(function (t) { return t.length > 40 ? send(buf.byteLength <= 12582912 ? { name: name, text: t, mime: 'application/pdf', data: b64(buf) } : { name: name, text: t }) : send({ name: name, mime: 'application/pdf', data: b64(buf) }); },
        function () { return send({ name: name, mime: 'application/pdf', data: b64(buf) }); });
      return send({ name: name, mime: file.type, data: b64(buf) });
    });
  }
  F.read = function (files, api, onChange) {
    var list = [].slice.call(files || []).slice(0, 6), out = list.map(function (f) { return { name: f.name || 'Фото', busy: true }; });
    if (onChange) onChange(out);
    var q = list.map(function (f, i) { return i; }), work = function () {   // №627 · не больше 3 загрузок одновременно
      if (!q.length) return Promise.resolve(); var i = q.shift(), f = list[i];
      return one(out[i], f, api).catch(function () { out[i].busy = false; out[i].err = 'не прочитался'; }).then(function () { if (onChange) onChange(out); }).then(work);
    };
    return Promise.all([work(), work(), work()]).then(function () { return out; });
  };
})();
