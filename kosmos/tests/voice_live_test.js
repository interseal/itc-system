/* №629 · живая расшифровка голоса в поле ввода (Фил, Космос): voice.js в node с поддельными мостом Android,
   распознаванием браузера (Web Speech) и Яндексом (sp_hear). Запуск: node kosmos/tests/voice_live_test.js <путь к voice.js> */
'use strict';
const fs = require('fs'), vm = require('vm');
const FILE = process.argv[2] || 'store/apps/fil/voice.js';
const SRC = fs.readFileSync(FILE, 'utf8');
let ok = 0, bad = 0;
function check(name, cond, extra) { if (cond) { ok++; } else { bad++; console.log('✗ ' + name + (extra !== undefined ? ' — ' + JSON.stringify(extra) : '')); } }
const sleep = ms => new Promise(r => setTimeout(r, ms));

function env(o) {
  o = o || {};
  const store = {};
  const w = {
    localStorage: { getItem: k => (k in store ? store[k] : null), setItem: (k, v) => { store[k] = String(v); } },
    document: { addEventListener() {}, querySelectorAll: () => [], documentElement: { classList: { toggle() {}, contains: () => false, add() {}, remove() {} } } },
    navigator: { userAgent: o.ua || 'Mozilla/5.0 (X11; Linux x86_64) Chrome/130', mediaDevices: o.media },
    addEventListener() {}, setTimeout, clearTimeout, Promise, Date, Math, String, JSON, Float32Array, Int16Array, Uint8Array,
    btoa: s => Buffer.from(s, 'binary').toString('base64'), console,
  };
  if (o.android) w.AndroidSpeech = o.android;
  if (o.WS) w.webkitSpeechRecognition = o.WS;
  if (o.AC) w.AudioContext = o.AC;
  w.window = w; w.store = store;
  vm.createContext(w); vm.runInContext(SRC, w);
  return w;
}
/* как в fil/kosmos index.html (micGo): поле + LV, итог — в поле или «отправить» */
function page(w, field, RM) {
  const V = w.FilVoice, el = { value: field || '' }, P = { el, sent: [], msgs: [], seen: [], LV: null, RM: RM || 'send', errs: [] };
  P.cb = {
    keep: true,
    start() { P.LV = V.live(el); P.LV.start(); },
    partial(t, info) { P.LV.show(info || { fin: t, cur: '' }); P.seen.push(el.value); },
    final(t) { const R = P.LV ? P.LV.end(t) : { text: t, heard: t, pre: '' }; P.LV = null;
      if (P.RM === 'cancel') { el.value = R.pre; return; }
      if (!R.heard) { el.value = R.pre; P.msgs.push('нет'); return; }
      if (P.RM === 'field') { el.value = R.text; return; }
      el.value = ''; P.sent.push(R.text); },
    error(m) { P.errs.push(m); if (P.LV) { el.value = P.LV.base(); P.LV = null; } },
  };
  return P;
}
function fakeAndroid() {
  const A = { calls: [], canListen: () => true, listen(l) { A.calls.push('listen'); }, listenLong(l) { A.calls.push('long'); }, stopListen() { A.calls.push('stop'); }, stopSay() {} };
  return A;
}

(async () => {
  /* 1 · Android: слова в поле по ходу речи, набранное раньше остаётся, итог без повторов, отправка одна */
  { const A = fakeAndroid(), w = env({ android: A }), P = page(w, 'Задача:');
    w.FilVoice.listen(P.cb);
    check('Android: keep — долгое слушание (4 с, №617)', A.calls[0] === 'long', A.calls);
    w.itcSpeechPartial('купи');
    check('Android: первое слово сразу в поле после набранного', P.el.value === 'Задача: купи', P.el.value);
    w.itcSpeechPartial('купи молоко');
    check('Android: фраза растёт, а не повторяется', P.el.value === 'Задача: купи молоко', P.el.value);
    w.itcSpeechResult('купи молоко');
    check('Android: итог фразы заменил черновик', P.el.value === 'Задача: купи молоко', P.el.value);
    await sleep(5);
    check('Android: после фразы слушает дальше (keep)', A.calls.filter(x => x === 'long').length === 2, A.calls);
    w.itcSpeechPartial('и хлеб');
    check('Android: вторая фраза дописывается', P.el.value === 'Задача: купи молоко и хлеб', P.el.value);
    w.FilVoice.stop(); await sleep(800);   // недослушанный кусок — ждём точный текст до 0,7 с (№626)
    check('Android: отправлено одно сообщение без дублей', P.sent.length === 1 && P.sent[0] === 'Задача: купи молоко и хлеб', P.sent);
    check('Android: поле после отправки пустое', P.el.value === '', P.el.value);
    check('Android: мост возвращён', !w.FilVoice.listening());
  }
  /* 2 · Android: человек поправил текст пальцем во время диктовки */
  { const A = fakeAndroid(), w = env({ android: A }), P = page(w, '', 'field');
    w.FilVoice.listen(P.cb);
    w.itcSpeechPartial('позвони Ивану'); w.itcSpeechResult('позвони Ивану'); await sleep(2);
    P.el.value = 'Позвони Ивану Петровичу,';                  // правка пальцем
    w.itcSpeechPartial('завтра');
    check('правка: новые слова после исправленного текста', P.el.value === 'Позвони Ивану Петровичу, завтра', P.el.value);
    w.itcSpeechPartial('завтра утром');
    check('правка: черновик меняется без повторов', P.el.value === 'Позвони Ивану Петровичу, завтра утром', P.el.value);
    w.FilVoice.stop(); await sleep(800);
    check('правка: итог (стоп → в поле) с правкой и без дублей', P.el.value === 'Позвони Ивану Петровичу, завтра утром', P.el.value);
  }
  /* 3 · правка посреди недоговорённой фразы */
  { const A = fakeAndroid(), w = env({ android: A }), P = page(w, '');
    w.FilVoice.listen(P.cb);
    w.itcSpeechPartial('купи моло');
    P.el.value = 'Купи молоко 2 литра';
    w.itcSpeechPartial('купи молоко и сыр');
    check('правка в фразе: дописаны только новые слова', P.el.value === 'Купи молоко 2 литра и сыр', P.el.value);
    w.itcSpeechResult('купи молоко и сыр'); await sleep(2);
    check('правка в фразе: итог фразы не повторил её', P.el.value === 'Купи молоко 2 литра и сыр', P.el.value);
    w.itcSpeechPartial('сегодня');
    w.FilVoice.stop(); await sleep(800);
    check('правка в фразе: отправлено без повторов', P.sent[0] === 'Купи молоко 2 литра и сыр сегодня', P.sent);
  }
  /* 4 · отмена — поле как было, ничего не отправлено; ничего не сказано — не отправляет */
  { const A = fakeAndroid(), w = env({ android: A }), P = page(w, 'черновик');
    w.FilVoice.listen(P.cb); w.itcSpeechPartial('лишнее');
    w.FilVoice.cancel(); P.el.value = P.LV.base();
    check('отмена: поле как до записи', P.el.value === 'черновик' && !P.sent.length, P.el.value);
    const P2 = page(w, 'черновик'); w.FilVoice.listen(P2.cb); w.FilVoice.stop(); await sleep(300);
    check('пусто: не отправлено, текст на месте', !P2.sent.length && P2.el.value === 'черновик' && P2.msgs.length === 1, [P2.el.value, P2.sent]);
  }
  /* 5 · listen() без keep: быстрая фраза (Сделки) — конец через 1,2 с, listen (1 с), не listenLong */
  { const A = fakeAndroid(), w = env({ android: A }), got = [];
    w.FilVoice.listen({ final: t => got.push(t), partial: () => {} });
    check('без keep: обычное слушание (1 с)', A.calls[0] === 'listen', A.calls);
    w.itcSpeechPartial('сделка'); w.itcSpeechResult('сделка с Ромашкой');
    await sleep(1400);
    check('без keep: конец фразы через 1,2 с, текст целиком', got.length === 1 && got[0] === 'сделка с Ромашкой', got);
  }
  /* 6 · браузер: Web Speech с промежуточными словами */
  { let inst = [];
    function WS() { inst.push(this); this.started = false; }
    WS.prototype.start = function () { this.started = true; };
    WS.prototype.stop = function () { const r = this; setTimeout(() => r.onend && r.onend(), 5); };
    WS.prototype.abort = function () {};
    const res = (list) => ({ results: list.map(([t, f]) => Object.assign([{ transcript: t }], { isFinal: f })) });
    const w = env({ WS }), P = page(w, 'Привет');
    w.FilVoice.listen(P.cb);
    const r = inst[0];
    check('браузер: распознавание браузера, ru-RU, промежуточные слова', r && r.started && r.lang === 'ru-RU' && r.interimResults === true && r.continuous === true, r && [r.lang, r.interimResults, r.continuous]);
    r.onresult(res([['напиши', false]]));
    check('браузер: слово сразу в поле', P.el.value === 'Привет напиши', P.el.value);
    r.onresult(res([['напиши письмо', true], [' поставщику', false]]));
    check('браузер: итог + черновик', P.el.value === 'Привет напиши письмо поставщику', P.el.value);
    r.onresult(res([['напиши письмо', true], [' поставщику', true]]));
    r.onend();                                                    // браузер сам закончил — keep: слушает дальше
    const r2 = inst[1];
    check('браузер keep: после паузы слушает снова', r2 && r2.started, inst.length);
    r2.onresult(res([['в Китай', false]]));
    check('браузер: новая фраза дописана после прежних', P.el.value === 'Привет напиши письмо поставщику в Китай', P.el.value);
    w.FilVoice.stop(); r2.onresult(res([['в Китай', true]])); await sleep(30);
    check('браузер: отправлено один раз без дублей', P.sent.length === 1 && P.sent[0] === 'Привет напиши письмо поставщику в Китай', P.sent);
    check('браузер: слушание закончено', !w.FilVoice.listening());
  }
  /* 7 · телефон в браузере: по фразам (непрерывный режим Chrome на Android повторяет слова) */
  { const inst = []; function WS() { inst.push(this); } WS.prototype.start = function () {}; WS.prototype.abort = function () {}; WS.prototype.stop = function () {};
    const w = env({ WS, ua: 'Mozilla/5.0 (Linux; Android 14) Chrome/130 Mobile' }), P = page(w, '');
    w.FilVoice.listen(P.cb);
    check('телефон-браузер: continuous выключен', inst[0].continuous === false);
    w.FilVoice.cancel();
  }
  /* 8 · распознавание браузера недоступно → запись для Яндекса, запоминаем */
  { const inst = []; function WS() { inst.push(this); } WS.prototype.start = function () {}; WS.prototype.abort = function () {};
    let asked = 0; const media = { getUserMedia: () => { asked++; return Promise.reject({ name: 'NotFoundError' }); } };
    function AC() { this.sampleRate = 16000; } AC.prototype.resume = function () {}; AC.prototype.close = function () {};
    const w = env({ WS, media, AC }), P = page(w, '');
    w.FilVoice.listen(P.cb);
    inst[0].onerror({ error: 'network' }); inst[0].onend(); await sleep(5);
    check('нет связи у браузера → запись для Яндекса', asked === 1, asked);
    check('запомнили на 3 дня: дальше сразу Яндекс', +w.store.itc_ws_bad > Date.now() - 5000, w.store);
    w.FilVoice.listen(page(w, '').cb); await sleep(5);
    check('второй раз распознавание браузера не пробуем', inst.length === 1 && asked === 2, [inst.length, asked]);
  }
  /* 9 · Яндекс по кускам: каждый кусок — в поле сразу, по порядку; итог без дублей */
  { let now = 1e6; const RD = Date; function D() { return new RD(now); } D.now = () => now;
    const pend = [];
    function AC() { this.sampleRate = 16000; this.state = 'running'; }
    AC.prototype.resume = function () {}; AC.prototype.close = function () {};
    AC.prototype.createMediaStreamSource = () => ({ connect() {}, disconnect() {} });
    let proc; AC.prototype.createScriptProcessor = function () { proc = { connect() {}, disconnect() {} }; return proc; };
    AC.prototype.destination = {};
    const media = { getUserMedia: () => Promise.resolve({ getTracks: () => [{ stop() {} }] }) };
    const w = env({ media, AC }); w.Date = D;
    vm.runInContext('Date = this.Date', w);
    const P = page(w, 'Итак:');
    w.FilVoice.init((a, d) => new Promise(res => pend.push({ a, res })));
    w.FilVoice.listen(P.cb); await sleep(5);
    const buf = v => ({ inputBuffer: { getChannelData: () => new Float32Array(4096).fill(v) } });
    function talk(sec, v) { for (let t = 0; t < sec; t += 0.256) { now += 256; proc.onaudioprocess(buf(v)); } }
    talk(6.5, 0.1); talk(0.8, 0);
    check('Яндекс: на паузе после 6 с кусок ушёл на распознавание', pend.length === 1, pend.length);
    talk(6.5, 0.1); talk(0.8, 0);
    check('Яндекс: второй кусок', pend.length === 2, pend.length);
    pend[1].res({ ok: 1, text: 'второй кусок' }); await sleep(2);
    check('Яндекс: второй не показан раньше первого', P.el.value === 'Итак:', P.el.value);
    pend[0].res({ ok: 1, text: 'первый кусок' }); await sleep(2);
    check('Яндекс: куски в поле по порядку, сразу после распознавания', P.el.value === 'Итак: первый кусок второй кусок', P.el.value);
    talk(2, 0.1);
    w.FilVoice.stop(); await sleep(2);
    check('Яндекс: хвост дораспознаётся', pend.length === 3, pend.length);
    pend[2].res({ ok: 1, text: 'хвост' }); await sleep(5);
    check('Яндекс: отправлено без дублей', P.sent.length === 1 && P.sent[0] === 'Итак: первый кусок второй кусок хвост', P.sent);
    /* запоздалый кусок прошлой записи не попадает в новую диктовку */
    talk(6.5, 0.1); talk(0.8, 0);   // (сессия уже закончена — ничего не шлёт)
    const P2 = page(w, ''); w.FilVoice.listen(P2.cb); await sleep(5);
    talk(6.5, 0.1); talk(0.8, 0); talk(1, 0.1);
    const n0 = pend.length; w.FilVoice.stop(); await sleep(2);
    const P3 = page(w, 'новое'); w.FilVoice.listen(P3.cb); await sleep(5);
    for (let i = n0 - 1; i < pend.length; i++) pend[i].res({ ok: 1, text: 'старое' }); await sleep(5);
    check('Яндекс: ответ прошлой записи не попал в новую', P3.el.value === 'новое' && !P3.sent.length && !P2.sent.length, [P3.el.value, P3.sent, P2.sent]);
    w.FilVoice.cancel();
  }
  console.log(`голос: ${ok} ✓ / ${bad} ✗`);
  process.exit(bad ? 1 : 0);
})().catch(e => { console.log('сбой: ' + (e && e.stack || e)); process.exit(1); });
