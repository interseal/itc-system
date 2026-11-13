/* ИТК · голос Космоса (№15) — общий для приложения «Космос» и кнопки-микрофона в «Воронках сделок».
   Слушать: 1) в приложении ИТК — свой распознаватель телефона (мост window.AndroidSpeech, как в «ИТК Переводчике»):
               сам слышит конец фразы, показывает слова по ходу речи;
            2) в браузере — запись с микрофона → Яндекс SpeechKit (sp_hear).
   Говорить: Яндекс SpeechKit (выбранный голос Космоса); если ключа нет — голос телефона. */
(function () {
  var V = window.FilVoice = {};
  var api = null, native = null, rec = null, cb = {};
  V.init = function (apiFn) { api = apiFn; };
  V.hasNative = function () { try { return !!(window.AndroidSpeech && AndroidSpeech.canListen && AndroidSpeech.canListen()); } catch (e) { return false; } };
  V.listening = function () { return !!(native || rec); };

  /* мост приложения зовёт window.itcSpeech*. На время нашего слушания ставим свои, потом возвращаем прежние
     (в «Сделках» те же имена нужны диктовке разъяснений для бухгалтера) */
  var prev = null;
  function hook() {
    prev = { p: window.itcSpeechPartial, r: window.itcSpeechResult, e: window.itcSpeechError };
    /* №19 · слушаем, пока человек не закончит: телефон отдаёт фразу после короткой паузы — мы её копим
       и сразу слушаем дальше. Конец — повторное нажатие микрофона или долгая тишина (телефон сам скажет «ничего не услышал»). */
    window.itcSpeechPartial = function (t) { if (native && cb.partial) cb.partial((BUF ? BUF + ' ' : '') + t); };
    window.itcSpeechResult = function (t) {
      if (!native) return; t = String(t || '').trim(); if (t) BUF = (BUF ? BUF + ' ' : '') + t;
      if (STOP || Date.now() - T0 > (cb.keep ? 600000 : 120000)) { done(); return; }
      if (cb.partial && BUF) cb.partial(BUF + ' …');
      setTimeout(function () { if (native && !STOP) { try { AndroidSpeech.listen('ru-RU'); } catch (e) { done(); } } else if (native) done(); }, 120);
    };
    window.itcSpeechError = function (m) {
      if (!native) return;
      // №303 · режим «нажал — говоришь — нажал ещё раз»: тишина не заканчивает запись, слушаем дальше до второго нажатия
      if (cb.keep && !STOP && Date.now() - T0 < 600000) { setTimeout(function () { if (native && !STOP) { try { AndroidSpeech.listen('ru-RU'); } catch (e) { if (BUF) done(); } } else if (native) done(); }, 150); return; }
      if (STOP && BUF) { done(); return; }
      if (BUF) { done(); return; }                       // была речь, потом тишина — фраза закончена
      native = null; unhook(); if (cb.error) cb.error(m || 'Не расслышал');
    };
  }
  var BUF = '', STOP = false, T0 = 0;
  function done() { var t = BUF.trim(); BUF = ''; native = null; unhook(); if (cb.final) cb.final(t); }
  function unhook() { if (!prev) return; window.itcSpeechPartial = prev.p; window.itcSpeechResult = prev.r; window.itcSpeechError = prev.e; prev = null; }

  /* начать слушать. ВАЖНО: вызывать прямо из нажатия кнопки (иначе браузер не даст звук) */
  V.listen = function (o) {
    V.hush();                                   // №29: начали говорить сами — Космос замолкает
    cb = o || {};
    if (V.hasNative()) {
      native = true; BUF = ''; STOP = false; T0 = Date.now(); hook();
      try { AndroidSpeech.listen('ru-RU'); if (cb.start) cb.start('native'); } catch (e) { native = null; unhook(); if (cb.error) cb.error('Распознавание недоступно'); }
      return;
    }
    // запись в браузере: звуковой контекст создаём сразу в нажатии, иначе он «спит» и пишет тишину
    var Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { if (cb.error) cb.error('Это устройство не даёт доступ к микрофону — напишите команду'); return; }
    var ctx = new Ctx(); try { ctx.resume(); } catch (e) {}
    rec = { ctx: ctx, bufs: [], stream: null, pr: null, src: null, t0: Date.now(), peak: 0 };
    navigator.mediaDevices.getUserMedia({ audio: { channelCount: 1, echoCancellation: true, noiseSuppression: true, autoGainControl: true } }).then(function (stream) {
      if (!rec) { stream.getTracks().forEach(function (x) { x.stop(); }); return; }
      rec.stream = stream; rec.src = ctx.createMediaStreamSource(stream); rec.pr = ctx.createScriptProcessor(4096, 1, 1);
      var quiet = 0, heard = false;
      rec.pr.onaudioprocess = function (e) {
        if (!rec) return;
        var d = e.inputBuffer.getChannelData(0), s = 0; for (var i = 0; i < d.length; i += 8) s += Math.abs(d[i]);
        var lvl = s / (d.length / 8); rec.peak = Math.max(rec.peak, lvl);
        rec.bufs.push(new Float32Array(d));
        if (cb.level) cb.level(lvl);
        // сам замечаем конец фразы: была речь, потом 1,4 с тишины
        if (lvl > 0.02) { heard = true; quiet = 0; } else if (heard && !cb.keep) { quiet += d.length / ctx.sampleRate; if (quiet > 3) V.stop(); }
        if (Date.now() - rec.t0 > (cb.keep ? 90000 : 29000)) V.stop();
      };
      rec.src.connect(rec.pr); rec.pr.connect(ctx.destination);
      if (ctx.state === 'suspended') { try { ctx.resume(); } catch (e) {} }
      if (cb.start) cb.start('web');
    }).catch(function (e) {
      rec = null; try { ctx.close(); } catch (x) {}
      if (cb.error) cb.error(e && e.name === 'NotAllowedError' ? 'Разрешите доступ к микрофону: настройки телефона → Приложения → ITC GLOBAL → Разрешения → Микрофон' : (e && e.name === 'NotFoundError' ? 'Микрофон не найден на этом устройстве' : e && e.name === 'NotReadableError' ? 'Микрофон занят другим приложением — закройте его и нажмите ещё раз' : 'Микрофон недоступен'));
    });
  };

  /* закончить: у телефона — «хватит слушать», в браузере — отправить запись на распознавание */
  V.stop = function () {
    if (native) { STOP = true; try { AndroidSpeech.stopListen(); } catch (e) {} setTimeout(function () { if (native) done(); }, 1500); return; }
    if (!rec) return;
    var r = rec; rec = null;
    try { r.pr && r.pr.disconnect(); r.src && r.src.disconnect(); } catch (e) {}
    if (r.stream) r.stream.getTracks().forEach(function (x) { x.stop(); });
    var rate = r.ctx.sampleRate; try { r.ctx.close(); } catch (e) {}
    if (r.peak < 0.004) { if (cb.error) cb.error('Микрофон молчит — проверьте, что разрешён доступ к микрофону'); return; }
    var n = 0; r.bufs.forEach(function (b) { n += b.length; });
    if (n < rate * 0.4) { if (cb.error) cb.error('Слишком коротко — скажите ещё раз'); return; }
    var all = new Float32Array(n), o = 0; r.bufs.forEach(function (b) { all.set(b, o); o += b.length; });
    var k = rate / 16000, m = Math.floor(n / k), pcm = new Int16Array(m);
    for (var i = 0; i < m; i++) { var s = all[Math.floor(i * k)]; s = Math.max(-1, Math.min(1, s)); pcm[i] = s < 0 ? s * 0x8000 : s * 0x7fff; }
    var u8 = new Uint8Array(pcm.buffer), bin = '', CH = 0x8000;
    for (var j = 0; j < u8.length; j += CH) bin += String.fromCharCode.apply(null, u8.subarray(j, j + CH));
    if (cb.busy) cb.busy();
    api('sp_hear', { pcm: btoa(bin) }).then(function (x) {
      if (!x || !x.ok) { if (cb.error) cb.error((x && x.error) || 'Не удалось распознать'); return; }
      if (cb.final) cb.final(String(x.text || '').trim());
    }).catch(function () { if (cb.error) cb.error('Нет связи'); });
  };
  V.cancel = function () { if (native) { native = null; BUF = ''; unhook(); try { AndroidSpeech.stopListen(); } catch (e) {} } if (rec) { var r = rec; rec = null; try { r.stream && r.stream.getTracks().forEach(function (x) { x.stop(); }); r.ctx.close(); } catch (e) {} } };

  /* сказать вслух */
  var cur = null, SEQ = 0;
  /* №29 · звук Космоса: можно выключить совсем (запоминается) и остановить на полуслове */
  V.muted = function () { try { return localStorage.getItem('ks_mute') === '1'; } catch (e) { return false; } };
  V.setMute = function (on) { try { localStorage.setItem('ks_mute', on ? '1' : '0'); } catch (e) {} if (on) V.hush(); V.syncBtns(); };
  V.hush = function () {
    SEQ++; try { [].forEach.call(document.querySelectorAll('.rp.on'), function (b) { b.classList.remove('on'); }); } catch (e) {}
    try { if (cur) { cur.pause(); cur.src = ''; } } catch (e) {} cur = null;
    try { if (window.speechSynthesis) speechSynthesis.cancel(); } catch (e) {}
    try { if (window.AndroidSpeech && AndroidSpeech.stopSay) AndroidSpeech.stopSay(); } catch (e) {}
    clearTimeout(V._t); V.talking(false);
  };
  /* кнопки «звук» в окнах: <button data-fil-snd> — иконка меняется сама */
  var IC_ON = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3A4.5 4.5 0 0 0 14 7.97v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>';
  var IC_OFF = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.5 12A4.5 4.5 0 0 0 14 7.97v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51A8.8 8.8 0 0 0 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3 3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06a8.99 8.99 0 0 0 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4 9.91 6.09 12 8.18V4z"/></svg>';
  var IC_STOP = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 6h12v12H6z"/></svg>';
  V.syncBtns = function () {
    var talk = document.documentElement.classList.contains('fil-talk'), m = V.muted();
    [].forEach.call(document.querySelectorAll('[data-fil-snd]'), function (b) {
      b.innerHTML = talk ? IC_STOP : (m ? IC_OFF : IC_ON); b.classList.toggle('off', m && !talk);
      b.title = talk ? 'Замолчать' : (m ? 'Звук выключен — нажмите, чтобы включить' : 'Звук включён — нажмите, чтобы выключить');
    });
  };
  V.sndTap = function () { if (document.documentElement.classList.contains('fil-talk')) V.hush(); else V.setMute(!V.muted()); };
  /* звонок, свернули приложение, переключились на другое окно — Космос сразу замолкает */
  document.addEventListener('visibilitychange', function () { if (document.visibilityState !== 'visible') V.hush(); });
  window.addEventListener('pagehide', function () { V.hush(); });
  window.addEventListener('blur', function () { V.hush(); });
  V.talking = function (on) { try { document.documentElement.classList.toggle('fil-talk', !!on); } catch (e) {} try { V.syncBtns(); } catch (e) {} };
  /* force — человек сам нажал «послушать» у сообщения: играем даже при выключенном звуке */
  /* voice — каким голосом сказать (№537: послушать голоса в настройках); пусто — голос помощника */
  V.say = function (text, yandexOn, force, voice) {
    var mute = function () { return !force && V.muted(); };
    if (!text || mute()) return;
    V.hush(); var my = SEQ;
    function est() { V.talking(true); clearTimeout(V._t); V._t = setTimeout(function () { V.talking(false); }, Math.min(15000, 900 + String(text).length * 65)); }
    function phone() { if (my !== SEQ || mute()) return; est(); try { if (window.AndroidSpeech && AndroidSpeech.say) AndroidSpeech.say(String(text), 'ru-RU'); else if (window.speechSynthesis) { var u = new SpeechSynthesisUtterance(String(text)); u.lang = 'ru-RU'; speechSynthesis.speak(u); } } catch (e) {} }
    // №20 · всегда сначала голос Космоса (Яндекс). Голос телефона — только если ключ Яндекса ещё не вставлен,
    // иначе телефон подставлял свой женский голос, когда Яндекс не успевал.
    api('sp_say', voice ? { text: text, voice: voice } : { text: text }).then(function (r) {
      if (my !== SEQ || mute()) return;          // пока ждали голос — нажали «замолчать»
      if (r && r.ok) { try { cur = new Audio('data:' + (r.type || 'audio/mpeg') + ';base64,' + r.audio); cur.onplaying = function () { clearTimeout(V._t); V.talking(true); }; cur.onended = cur.onpause = function () { V.talking(false); }; cur.play().catch(function () {}); } catch (e) {} }
      else if (r && /не подключ/i.test(r.error || '')) phone();
    }).catch(function () {});
  };
})();
