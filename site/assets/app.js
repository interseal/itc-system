(function () {
  // Язык страницы: ru, en или zh — от него зависят сообщения форм и поле телефона
  var L = (document.documentElement.lang || 'ru').slice(0, 2);
  var T = {
    ru: { ok: 'Спасибо! Заявка отправлена. Мы свяжемся с вами в ближайшее время.', err: 'Не получилось отправить. Позвоните нам, пожалуйста.', net: 'Не получилось отправить. Проверьте интернет или позвоните нам.', phone: 'Проверьте номер телефона', mail: 'Проверьте адрес почты' },
    en: { ok: 'Thank you! Your request has been sent. We will contact you shortly.', err: 'Could not send the request. Please call or message us.', net: 'Could not send the request. Check your connection or message us.', phone: 'Please check the phone number', mail: 'Please check the email address' },
    zh: { ok: '谢谢！您的申请已提交，我们会尽快与您联系。', err: '提交失败，请致电或给我们留言。', net: '提交失败，请检查网络或给我们留言。', phone: '请检查电话号码', mail: '请检查电子邮箱地址' }
  }[L] || null;
  if (!T) { L = 'ru'; }
  T = T || { ok: '', err: '', net: '', phone: '', mail: '' };
  // Метки рекламы (utm) запоминаем при первом заходе — уйдут вместе с заявкой в Битрикс24
  try {
    var qs = new URLSearchParams(location.search), utm = {};
    ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'].forEach(function (k) { if (qs.get(k)) utm[k] = qs.get(k); });
    if (Object.keys(utm).length) localStorage.setItem('itc_utm', JSON.stringify(utm));
  } catch (x) {}

  // ---------- Меню на телефоне ----------
  var hdr = document.querySelector('.hdr');
  var burger = document.querySelector('.hdr__burger');
  if (burger) burger.addEventListener('click', function () {
    hdr.classList.toggle('open');
    document.body.classList.toggle('lock', hdr.classList.contains('open'));
  });
  var mmenu = document.querySelector('.mmenu');
  var mDet = mmenu ? [].slice.call(mmenu.querySelectorAll('details')) : [];
  document.querySelectorAll('.mmenu a').forEach(function (a) {
    a.addEventListener('click', function () {
      // 3.46.0: запоминаем, какие разделы меню были раскрыты — кнопка «Назад» на новой странице вернёт в меню
      var h = a.getAttribute('href') || '';
      if (h && h.charAt(0) !== '#' && a.host === location.host) try {
        sessionStorage.setItem('itc_mb', JSON.stringify({ to: a.pathname, open: mDet.map(function (d, i) { return d.open ? i : -1; }).filter(function (i) { return i >= 0; }), top: mmenu.scrollTop, t: Date.now() }));
      } catch (x) {}
      hdr.classList.remove('open'); document.body.classList.remove('lock');
    });
  });
  // Пришли сюда из меню на телефоне: первое «Назад» открывает меню там же, где выбрали страницу
  try {
    var mb = JSON.parse(sessionStorage.getItem('itc_mb') || 'null');
    sessionStorage.removeItem('itc_mb');
    if (mb && hdr && mmenu && mb.to === location.pathname && Date.now() - mb.t < 600000 && window.matchMedia('(max-width: 999px)').matches) {
      history.replaceState({ mb: 0, m: mb }, ''); history.pushState({ mb: 1, m: mb }, '');
    }
    // и при возврате на такую страницу позже (запись из истории) — тоже
    var hs = history.state;
    if (hs && hs.mb === 1 && hs.m && hdr && mmenu) window.addEventListener('popstate', function onBack(e) {
      if (!e.state || e.state.mb !== 0) return;
      window.removeEventListener('popstate', onBack);
      var m = e.state.m || hs.m;
      mDet.forEach(function (d, i) { d.open = (m.open || []).indexOf(i) >= 0; });
      hdr.classList.add('open'); document.body.classList.add('lock');
      mmenu.scrollTop = m.top || 0;
    });
  } catch (x) {}

  // ---------- Всплывающие формы ----------
  var ALIAS = { fabrika: 'vopros' };
  function popEl(id) { return document.getElementById('pop-' + (ALIAS[id] || id)); }
  function openPop(id) {
    var p = popEl(id);
    if (!p) return false;
    p.hidden = false; document.body.classList.add('lock');
    var f = p.querySelector('input[name=name]'); if (f) setTimeout(function () { f.focus(); }, 50);
    return true;
  }
  function closePop(p) { p.hidden = true; document.body.classList.remove('lock'); }
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href^="#"]');
    if (a) {
      var id = a.getAttribute('href').slice(1);
      if (popEl(id)) {
        e.preventDefault(); openPop(id);
        // кнопка у товара каталога: подставляем его номер в текст заявки
        var q = a.getAttribute('data-q'), t = q && popEl(id).querySelector('textarea[name=question]');
        if (t) t.value = q + '\n';
        return;
      }
    }
    var p = e.target.closest('.pop');
    if (p && (e.target === p || e.target.closest('.pop__close'))) closePop(p);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') document.querySelectorAll('.pop:not([hidden])').forEach(closePop);
  });
  if (location.hash && popEl(location.hash.slice(1))) openPop(location.hash.slice(1));

  // ---------- Маска телефона +7 (000) 000-00-00 (только на русской версии) ----------
  // На английской и китайской — свободный ввод с кодом страны: +86 …, +7 …
  if (L !== 'ru') document.querySelectorAll('input[type=tel]').forEach(function (inp) {
    inp.addEventListener('input', function () { inp.value = inp.value.replace(/[^\d+()\-\s]/g, '').slice(0, 24); });
  });
  if (L === 'ru') document.querySelectorAll('input[type=tel]').forEach(function (inp) {
    inp.addEventListener('focus', function () { if (!inp.value) inp.value = '+7 '; });
    inp.addEventListener('input', function () {
      var d = inp.value.replace(/\D/g, '');
      if (d.charAt(0) === '8') d = '7' + d.slice(1);
      if (d.charAt(0) !== '7') d = '7' + d;
      // после готового «+7» номер набрали целиком с 8 или 7 (8 978 123-45-67) — лишнюю первую цифру убираем
      if (d.length === 12 && (d.charAt(1) === '8' || d.charAt(1) === '7')) d = '7' + d.slice(2);
      d = d.slice(0, 11);
      var r = '+7';
      if (d.length > 1) r += ' (' + d.slice(1, 4);
      if (d.length >= 4) r += ') ' + d.slice(4, 7);
      if (d.length >= 7) r += '-' + d.slice(7, 9);
      if (d.length >= 9) r += '-' + d.slice(9, 11);
      inp.value = r;
    });
  });

  // ---------- Отправка заявок ----------
  document.querySelectorAll('.lead-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = form.querySelector('.form-msg');
      // запрос по фабрике без выбранных товаров не отправляем
      if (form.classList.contains('pick-form') && !form.elements.items.value) return;
      var btn = form.querySelector('button[type=submit]');
      var fd = new FormData(form);
      fd.append('form', form.getAttribute('data-form') || '');
      fd.append('page', location.href);
      if (L !== 'ru') fd.append('lang', L);
      try { var u = JSON.parse(localStorage.getItem('itc_utm') || '{}'); for (var k in u) fd.append(k, u[k]); } catch (x) {}
      btn.disabled = true; msg.className = 'form-msg'; msg.textContent = '';
      fetch('/send.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.ok) {
            msg.className = 'form-msg ok';
            msg.textContent = T.ok;
            form.classList.add('sent');
            try { form.dispatchEvent(new CustomEvent('lead:sent', { detail: res })); } catch (x) {}
            if (window.ym) try { ym(window.__ymId, 'reachGoal', 'lead'); } catch (x) {}
          } else {
            msg.className = 'form-msg err';
            msg.textContent = res.error ? (L === 'ru' ? res.error : (/телефон/i.test(res.error) ? T.phone : (/почт/i.test(res.error) ? T.mail : T.err))) : T.err;
          }
        })
        .catch(function () {
          msg.className = 'form-msg err';
          msg.textContent = T.net;
        })
        .finally(function () { btn.disabled = false; });
    });
  });

  // ---------- Отзывы: стрелки ----------
  var track = document.querySelector('.rev__track');
  if (track) {
    var step = function () { var c = track.querySelector('.revi'); return c ? c.offsetWidth + 40 : 300; };
    document.querySelector('.rev__arrow--l').addEventListener('click', function () { track.scrollBy({ left: -step(), behavior: 'smooth' }); });
    document.querySelector('.rev__arrow--r').addEventListener('click', function () { track.scrollBy({ left: step(), behavior: 'smooth' }); });
  }

  // ---------- Вопросы: раскрытие ----------
  document.querySelectorAll('.faqi__q').forEach(function (q) {
    q.addEventListener('click', function () { q.parentNode.classList.toggle('open'); });
  });

  // ---------- Фото: увеличение по нажатию ----------
  var lb = null;
  document.addEventListener('click', function (e) {
    var a = e.target.closest('.js-zoom');
    if (!a) return;
    e.preventDefault();
    if (!lb) {
      lb = document.createElement('div'); lb.className = 'lbox';
      lb.innerHTML = '<img alt=""><button type="button" aria-label="Закрыть">&times;</button>';
      lb.addEventListener('click', function () { lb.classList.remove('on'); document.body.classList.remove('lock'); });
      document.body.appendChild(lb);
    }
    lb.querySelector('img').src = a.getAttribute('href');
    lb.classList.add('on'); document.body.classList.add('lock');
  });

  // ---------- Видео склада: загружаем по нажатию ----------
  document.querySelectorAll('.vbox').forEach(function (box) {
    box.addEventListener('click', function () {
      if (box.querySelector('iframe')) return;
      var id = box.getAttribute('data-kinescope'), yt = box.getAttribute('data-youtube');
      var src = yt ? 'https://www.youtube.com/embed/' + yt + '?autoplay=1' : 'https://kinescope.io/embed/' + id + '/?autoplay=true';
      box.innerHTML = '<iframe src="' + src + '" allow="autoplay; fullscreen; picture-in-picture; encrypted-media" allowfullscreen></iframe>';
    });
  });

  // ---------- Анимации ----------
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Кружочки летят из Китая в Россию, по очереди, по кругу
  var map = document.querySelector('.hero__map');
  if (map && !reduce && map.animate) {
    var T = [[-213, -78], [-148, -66], [-173, -74], [-240, -66], [-147, -88], [-187, -103]];
    var STEP = 550, CYCLE = STEP * T.length;
    T.forEach(function (t, i) {
      var s = document.createElement('span');
      s.className = 'hero__fly' + (i % 3 === 1 ? ' hero__fly--b' : '');
      map.appendChild(s);
      var k = function (o, f, op) {
        return { offset: o, opacity: op, transform: 'translate(' + (t[0] * f) + 'px,' + (t[1] * f) + 'px)' };
      };
      s.animate([k(0, 0, 0), k(.06, .12, 1), k(.4, .88, 1), k(.48, 1, 0), k(1, 1, 0)],
        { duration: CYCLE, delay: i * STEP, iterations: Infinity });
    });
  }

  // Проявление при прокрутке (корабль и форма «Обсудим подробности?»)
  var rise = document.querySelectorAll('.js-rise, .js-drop');
  if (rise.length && !reduce && 'IntersectionObserver' in window) {
    document.documentElement.classList.add('anim', 'anim-drop');
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
      });
    }, { threshold: .15 });
    rise.forEach(function (el) { io.observe(el); });
  }

  // ---------- Запрос по выбранным товарам: галочки и количество на карточках ----------
  // Один запрос — одна фабрика: выбор хранится отдельно по каждой фабрике (data-pick на странице),
  // поэтому при переходе между разделами каталога одной фабрики отмеченное не теряется.
  (function () {
    var boxes = [].slice.call(document.querySelectorAll('[data-pick]'));
    var pop = document.getElementById('pop-pick');
    if (!boxes.length || !pop) return;
    var W = {
      ru: { prn: 'Распечатать заказ', prnSent: 'Распечатать отправленный заказ', oT: 'Заказ', oSent: 'отправлен', oFac: 'Фабрика', oDate: 'Дата', oItem: 'Товар', oVar: 'Вариант', oQty: 'Количество', oWho: 'Заказчик', oName: 'Имя', oPhone: 'Телефон', oMail: 'Почта', oCmt: 'Комментарий', oFoot: 'Цены, минимальный заказ и сроки менеджер подтвердит после проверки у фабрики. Официальный ввоз, оплата в рублях, полный пакет документов.', pick: 'Выбрать', picked: 'Выбрано', inreq: 'В запросе', added: 'Добавлено', pcs: 'шт.', less: 'Меньше', more: 'Больше', send: 'Сформировать запрос', clear: 'Очистить', del: 'Убрать', empty: 'Список пуст — отметьте товары на странице.', qty: 'Количество', add: 'Добавить', pk: 'уп.', fill: 'Заполните поле', cart: 'Корзина', total: 'Итого', U: { pcs: 'шт.', set: 'компл.', pair: 'пар', m: 'м' }, copy: 'Спасибо! Запрос отправлен. Копию мы прислали на {m}. Менеджер уточнит у фабрики цены и сроки, пришлёт предложение на почту и позвонит вам.' },
      en: { prn: 'Print order', prnSent: 'Print the sent order', oT: 'Order', oSent: 'sent', oFac: 'Factory', oDate: 'Date', oItem: 'Product', oVar: 'Option', oQty: 'Quantity', oWho: 'Customer', oName: 'Name', oPhone: 'Phone', oMail: 'Email', oCmt: 'Comment', oFoot: 'Prices, minimum order and lead times will be confirmed by our manager with the factory. Official import, payment in roubles, full set of documents.', pick: 'Select', picked: 'Selected', inreq: 'In request', added: 'Added', pcs: 'pcs', less: 'Less', more: 'More', send: 'Create request', clear: 'Clear', del: 'Remove', empty: 'The list is empty — tick products on the page.', qty: 'Quantity', add: 'Add', pk: 'pkg', fill: 'Please fill in', cart: 'Cart', total: 'Total', U: { pcs: 'pcs', set: 'sets', pair: 'pairs', m: 'm' }, copy: 'Thank you! Your request has been sent. We have sent a copy to {m}. Our manager will confirm prices and lead times with the factory, email you an offer and call you.' },
      zh: { prn: '打印订单', prnSent: '打印已发送的订单', oT: '订单', oSent: '已发送', oFac: '工厂', oDate: '日期', oItem: '产品', oVar: '规格', oQty: '数量', oWho: '客户', oName: '姓名', oPhone: '电话', oMail: '邮箱', oCmt: '备注', oFoot: '价格、起订量和交期由经理向工厂核实后确认。正规进口，卢布付款，全套单证。', pick: '选择', picked: '已选', inreq: '已加入', added: '已添加', pcs: '件', less: '减少', more: '增加', send: '生成询价单', clear: '清空', del: '移除', empty: '清单为空，请在页面上勾选产品。', qty: '数量', add: '添加', pk: '箱', fill: '请填写', cart: '购物车', total: '合计', U: { pcs: '件', set: '套', pair: '对', m: '米' }, copy: '谢谢！询价单已发送，副本已发到 {m}。经理会向工厂确认价格和交期，把报价发到您的邮箱并给您打电话。' }
    }[L] || null;
    W = W || { prn: 'Распечатать заказ', prnSent: 'Распечатать отправленный заказ', oT: 'Заказ', oSent: 'отправлен', oFac: 'Фабрика', oDate: 'Дата', oItem: 'Товар', oVar: 'Вариант', oQty: 'Количество', oWho: 'Заказчик', oName: 'Имя', oPhone: 'Телефон', oMail: 'Почта', oCmt: 'Комментарий', oFoot: 'Цены, минимальный заказ и сроки менеджер подтвердит после проверки у фабрики. Официальный ввоз, оплата в рублях, полный пакет документов.', pick: 'Выбрать', picked: 'Выбрано', inreq: 'В запросе', added: 'Добавлено', pcs: 'шт.', less: 'Меньше', more: 'Больше', send: 'Сформировать запрос', clear: 'Очистить', del: 'Убрать', empty: 'Список пуст — отметьте товары на странице.', qty: 'Количество', add: 'Добавить', pk: 'уп.', fill: 'Заполните поле', cart: 'Корзина', total: 'Итого', U: { pcs: 'шт.', set: 'компл.', pair: 'пар', m: 'м' } };
    // единицы для текста запроса менеджеру — всегда по-русски
    var RU_U = { pcs: 'шт.', set: 'компл.', pair: 'пар', m: 'м' };
    // в чём считается количество строки: упаковки или штуки/комплекты (3.41.0)
    function unitOf(it) { return it.k ? W.pk : (W.U[it.u] || W.pcs); }
    function eqOf(it, q) { return it.k ? '= ' + (q * it.k) + ' ' + (W.U[it.u] || W.pcs) : ''; }
    function sum(it, ks) {
      var n = ks.length, tot = {}, ord = [];
      ks.forEach(function (k) { var u = unitOf(it[k]); if (!(u in tot)) { tot[u] = 0; ord.push(u); } tot[u] += it[k].q; });
      var qs = ord.map(function (u) { return tot[u] + ' ' + u; }).join(', ');
      if (L === 'en') return n + (n === 1 ? ' item' : ' items') + ', ' + qs;
      if (L === 'zh') return n + '项，' + qs;
      var m10 = n % 10, m100 = n % 100;
      var w = m10 === 1 && m100 !== 11 ? 'позиция' : (m10 >= 2 && m10 <= 4 && (m100 < 12 || m100 > 14) ? 'позиции' : 'позиций');
      return n + ' ' + w + ', ' + qs;
    }
    var KEY = 'itc_pick', TTL = 30 * 864e5, store = {};
    try { store = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (x) { store = {}; }
    for (var k0 in store) if (!store[k0] || !store[k0].t || Date.now() - store[k0].t > TTL) delete store[k0];
    function save() { try { localStorage.setItem(KEY, JSON.stringify(store)); } catch (x) {} }
    function hash(t) { var h = 0; for (var i = 0; i < t.length; i++) h = (h * 31 + t.charCodeAt(i)) | 0; return 'h' + (h >>> 0).toString(36); }
    function txt(el) { return (el ? el.textContent : '').replace(/\s+/g, ' ').trim(); }
    function qnum(v) { v = parseInt(v, 10); return isNaN(v) || v < 1 ? 1 : Math.min(v, 99999); }
    var FAC = {}, order = [], EL = {};   // EL: товар → его карточка (фото для заявки менеджеру)
    function imgOf(c) { var i = c && c.querySelector('.gal__track img, .pcr__img img'); return i ? i.currentSrc || i.src || '' : ''; }
    function itemImg(ik) { return imgOf(EL[ik] || EL[ik.split('§')[0]]); }   // фабрики этой страницы: ключ → { name, cards: { товар: [карточки] } }
    function list(fk) { var f = store[fk]; return f ? f.i : {}; }
    function setItem(fk, ik, label, q, meta) {
      if (!store[fk]) store[fk] = { n: FAC[fk].name, i: {} };
      store[fk].n = FAC[fk].name; store[fk].t = Date.now();
      if (q > 0) {
        var it = store[fk].i[ik];
        if (!it) it = store[fk].i[ik] = { n: label, q: q };
        it.q = q;
        if (!it.im) { var im0 = itemImg(ik); if (im0) it.im = im0; }
        if (meta) { it.u = meta.u; it.k = meta.k; if (meta.t) { it.t = meta.t; it.v = meta.v || ''; } }
      }
      else { delete store[fk].i[ik]; if (!Object.keys(store[fk].i).length) delete store[fk]; }
      save(); paint(fk, ik); var oc = FAC[fk].oc[ik.split('§')[0]]; if (oc) oc(); bar(); if (!pop.hidden && pop.getAttribute('data-fac') === fk) { if (ol.getAttribute('data-keys') === Object.keys(list(fk)).join('|')) sync(fk); else fill(fk); }
    }
    // где на странице стоит галочка товара: карточка целиком или строка таблицы вариантов (у дисков — артикул и цвет)
    function paint(fk, ik) {
      (FAC[fk].cards[ik] || []).forEach(function (u) {
        var it = list(fk)[ik];
        u.cb.checked = !!it; u.el.classList.toggle(u.on, !!it);
        if (u.sp) u.sp.textContent = it ? W.inreq : W.add;   // товар без вариантов: кнопка «Добавить» / «В запросе» (3.42.5)
        u.qi.value = it ? it.q : 1;
        if (u.eq) u.eq.textContent = eqOf(u.meta, it ? it.q : 1);
        if (u.qrow) u.qrow.hidden = !it;
        if (u.card) u.card.classList.toggle('pcr--on', !!u.card.querySelector('.pick-tr--on'));
      });
    }
    function ctl(cls, label) { var b = document.createElement('button'); b.type = 'button'; b.className = cls; b.setAttribute('aria-label', label); b.textContent = cls.indexOf('m') > -1 ? '−' : '+'; return b; }
    function qtyBox(ul) {
      var qw = document.createElement('span'); qw.className = 'pick__qty';
      var mi = ctl('pick__m', W.less), pl = ctl('pick__p', W.more);
      var qi = document.createElement('input'); qi.type = 'number'; qi.min = 1; qi.max = 99999; qi.inputMode = 'numeric'; qi.setAttribute('aria-label', W.qty);
      var u = document.createElement('span'); u.className = 'pick__u'; u.textContent = ul || W.pcs;
      qw.appendChild(mi); qw.appendChild(qi); qw.appendChild(pl); qw.appendChild(u);
      return { qw: qw, qi: qi, mi: mi, pl: pl, u: u };
    }
    function wire(fk, ik, label, cb, q, meta) {
      cb.addEventListener('change', function () { setItem(fk, ik, label, cb.checked ? qnum(q.qi.value) : 0, meta); });
      q.qi.addEventListener('change', function () { q.qi.value = qnum(q.qi.value); if (cb.checked) setItem(fk, ik, label, qnum(q.qi.value), meta); });
      q.mi.addEventListener('click', function () { var n = qnum(q.qi.value) - 1; if (n < 1) { if (cb.checked) setItem(fk, ik, label, 0); return; } q.qi.value = n; if (cb.checked) setItem(fk, ik, label, n, meta); });
      q.pl.addEventListener('click', function () { var n = qnum(q.qi.value) + 1; q.qi.value = n; if (cb.checked) setItem(fk, ik, label, n, meta); });
    }
    // упаковка для выбранных значений: подходит правило, где каждый вариант пуст или совпадает; берём самое точное
    function packFor(ord, sel) {
      var best = null, bs = -1;
      (ord.k || []).forEach(function (r) {
        var sc = 0, ok = (r.w || []).every(function (x, j) { if (x === null || x === undefined) return true; sc++; return sel[j] === x; });
        if (ok && sc > bs) { best = r; bs = sc; }
      });
      return best;
    }
    var SVG_X = '<svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>';
    // карточка с вариантами: выбор значений, количество, «Добавить» и список добавленных строк
    function optCard(fk, c, body, title, base, ord) {
      var box = document.createElement('div'); box.className = 'pko';
      var ctrls = [];
      (ord.o || []).forEach(function (o) {
        var lb = document.createElement('label'); lb.className = 'pko__f';
        var sp = document.createElement('span'); sp.textContent = o.n; lb.appendChild(sp);
        var el;
        if (o.v) {
          el = document.createElement('select');
          o.v.forEach(function (v, i) { var op = document.createElement('option'); op.value = i; op.textContent = v; el.appendChild(op); });
        } else {
          el = document.createElement('input'); el.type = 'text'; el.maxLength = 60; el.placeholder = o.f || '';
          el.addEventListener('input', function () { el.classList.remove('pko__bad'); });
        }
        lb.appendChild(el); box.appendChild(lb); ctrls.push({ o: o, el: el });
      });
      var row = document.createElement('div'); row.className = 'pko__row';
      var q = qtyBox(); q.qi.value = 1;
      var eq = document.createElement('span'); eq.className = 'pko__eq';
      var add = document.createElement('button'); add.type = 'button'; add.className = 'pko__add'; add.textContent = W.add;
      row.appendChild(q.qw); row.appendChild(eq); row.appendChild(add); box.appendChild(row);
      var ul = document.createElement('ul'); ul.className = 'pko__list'; box.appendChild(ul);
      body.appendChild(box);
      function sel() { return ctrls.map(function (x) { return x.o.v ? +x.el.value : null; }); }
      function meta() { var r = packFor(ord, sel()); return r ? { k: r.q, u: r.u || ord.u } : { k: 0, u: ord.u || 'pcs' }; }
      function upd() { var m = meta(); q.u.textContent = m.k ? W.pk : (W.U[m.u] || W.pcs); eq.textContent = eqOf(m, qnum(q.qi.value)); }
      ctrls.forEach(function (x) { x.el.addEventListener(x.o.v ? 'change' : 'input', upd); });
      q.qi.addEventListener('input', upd);
      q.qi.addEventListener('change', function () { q.qi.value = qnum(q.qi.value); upd(); });
      q.mi.addEventListener('click', function () { q.qi.value = Math.max(1, qnum(q.qi.value) - 1); upd(); });
      q.pl.addEventListener('click', function () { q.qi.value = qnum(q.qi.value) + 1; upd(); });
      add.addEventListener('click', function () {
        var parts = [], keys = [], bad = null;
        ctrls.forEach(function (x) {
          if (x.o.v) { var vv = x.o.v[+x.el.value]; parts.push(/^\d/.test(vv) ? x.o.n + ' ' + vv : vv); keys.push(x.el.value); }
          else { var t = x.el.value.replace(/\s+/g, ' ').trim(); if (!t) { bad = bad || x.el; return; } parts.push(x.o.n + ': ' + t); keys.push(t.toLowerCase()); }
        });
        if (bad) { bad.classList.add('pko__bad'); bad.placeholder = W.fill; bad.focus(); return; }
        var ik = base + '§' + keys.join('|'), m = meta(), had = list(fk)[ik];
        var n = qnum(q.qi.value) + (had ? had.q : 0);
        m.t = title; m.v = parts.join(', ');
        setItem(fk, ik, title + (parts.length ? ' — ' + parts.join(', ') : ''), n, m);
        q.qi.value = 1; upd();
        // 3.43.4: кнопка на миг становится зелёной «Добавлено» — видно, что вариант попал в запрос
        clearTimeout(add._t); add.classList.add('pko__add--ok'); add.textContent = W.added || W.inreq;
        add._t = setTimeout(function () { add.classList.remove('pko__add--ok'); add.textContent = W.add; }, 1500);
      });
      function render() {
        var it = list(fk), ks = Object.keys(it).filter(function (k) { return k.indexOf(base + '§') === 0; });
        ul.innerHTML = '';
        ks.forEach(function (k) {
          var li = document.createElement('li'), t = document.createElement('span');
          var nm = it[k].n.indexOf(title + ' — ') === 0 ? it[k].n.slice(title.length + 3) : '';
          t.textContent = (nm ? nm + ' — ' : '') + it[k].q + ' ' + unitOf(it[k]) + (it[k].k ? ' ' + eqOf(it[k], it[k].q) : '');
          var x = document.createElement('button'); x.type = 'button'; x.setAttribute('aria-label', W.del); x.innerHTML = SVG_X;
          x.addEventListener('click', function () { setItem(fk, k, it[k].n, 0); });
          li.appendChild(t); li.appendChild(x); ul.appendChild(li);
        });
        c.classList.toggle('pcr--on', ks.length > 0);
      }
      FAC[fk].oc[base] = render;
      upd(); render();
    }
    boxes.forEach(function (box) {
      var fk = box.getAttribute('data-pick'), name = box.getAttribute('data-pick-name') || '';
      if (!FAC[fk]) { FAC[fk] = { name: name, cards: {}, oc: {} }; order.push(fk); }
      box.querySelectorAll('.pcr-grid > div.pcr:not(.pcr--nopick)').forEach(function (c) {
        var body = c.querySelector('.pcr__body'), h = body && body.querySelector('h4');
        if (!h) return;
        var hc = h.cloneNode(true), no = hc.querySelector('.azi__no');
        if (no) no.remove();
        var trs = c.querySelectorAll('tr.pick-tr');
        if (trs.length) {
          // карточка с вариантами: галочка и количество — напротив каждого варианта
          // раньше такую карточку отмечали целиком — старую отметку убираем
          if (c.id && store[fk] && store[fk].i[c.id]) { delete store[fk].i[c.id]; if (!Object.keys(store[fk].i).length) delete store[fk]; save(); }
          var title = txt(hc) + (no ? ' ' + txt(no) : '');
          [].forEach.call(trs, function (tr) {
            var ik = 'r-' + tr.getAttribute('data-pk'), sku = txt(tr.querySelector('.r-sku'));
            EL[ik] = c;
            var label = title + ' — ' + [sku !== '—' ? sku : '', txt(tr.querySelector('.r-w')), txt(tr.querySelector('.r-p')), txt(tr.querySelector('.r-c'))].filter(Boolean).join(', ');
            var lb = document.createElement('label'); lb.className = 'pick__chk pick__chk--row';
            var cb = document.createElement('input'); cb.type = 'checkbox'; cb.setAttribute('aria-label', W.pick); lb.appendChild(cb);
            tr.cells[0].insertBefore(lb, tr.cells[0].firstChild);
            var q = qtyBox(), qr = document.createElement('tr'), qd = document.createElement('td');
            qr.className = 'pick-qrow'; qr.hidden = true; qd.colSpan = 2; qd.appendChild(q.qw); qr.appendChild(qd);
            tr.parentNode.insertBefore(qr, tr.nextSibling);
            tr.addEventListener('click', function (e) { if (!e.target.closest('label')) cb.click(); });
            (FAC[fk].cards[ik] = FAC[fk].cards[ik] || []).push({ el: tr, on: 'pick-tr--on', cb: cb, qi: q.qi, qrow: qr, card: c });
            wire(fk, ik, label, cb, q);
          });
          c.classList.add('pcr--rows');
          return;
        }
        var label = txt(hc) + (no ? ' — ' + txt(no) : '');
        var ik = c.id || hash(label);
        EL[ik] = c;
        c.classList.add('pcr--pick');
        var ord = null;
        try { ord = JSON.parse(c.getAttribute('data-ord') || 'null'); } catch (x) { ord = null; }
        if (ord && ord.o && ord.o.length) { c.classList.add('pcr--opt'); optCard(fk, c, body, label, ik, ord); return; }
        var r0 = ord ? packFor(ord, []) : null, meta = ord ? { k: r0 ? r0.q : 0, u: (r0 && r0.u) || ord.u || 'pcs' } : null;
        var row = document.createElement('div'); row.className = 'pick pick--btn';
        var lb = document.createElement('label'); lb.className = 'pick__chk';
        var cb = document.createElement('input'); cb.type = 'checkbox';
        var sp = document.createElement('span');
        lb.appendChild(cb); lb.appendChild(sp);
        var q = qtyBox(meta ? unitOf(meta) : '');
        row.appendChild(q.qw); row.appendChild(lb);   // как в карточках с вариантами: количество, затем кнопка
        var eq = null;
        if (meta && meta.k) { eq = document.createElement('span'); eq.className = 'pick__eq'; q.qw.appendChild(eq); q.qi.addEventListener('input', function () { eq.textContent = eqOf(meta, qnum(q.qi.value)); }); }
        body.appendChild(row);
        (FAC[fk].cards[ik] = FAC[fk].cards[ik] || []).push({ el: c, on: 'pcr--on', cb: cb, sp: sp, qi: q.qi, eq: eq, meta: meta });
        wire(fk, ik, label, cb, q, meta);
      });
    });
    order = order.filter(function (fk) { return Object.keys(FAC[fk].cards).length || Object.keys(FAC[fk].oc).length; });
    if (!order.length) return;
    order.forEach(function (fk) { for (var ik in FAC[fk].cards) paint(fk, ik); });   // строки карточек с вариантами уже нарисованы
    function repaint(fk) { for (var ik in FAC[fk].cards) paint(fk, ik); for (var b in FAC[fk].oc) FAC[fk].oc[b](); }
    // Нижняя плашка: по строке на каждую фабрику страницы, где что-то выбрано
    var pb = document.createElement('div'); pb.className = 'pickbar'; pb.hidden = true; document.body.appendChild(pb);
    var rows = {};   // строки плашки не пересоздаём: иначе нажатие на кнопку теряется, если в этот момент поменялось количество
    function bar() {
      order.forEach(function (fk) {
        var it = list(fk), ks = Object.keys(it), r = rows[fk];
        if (!r) {
          r = rows[fk] = document.createElement('div'); r.className = 'pickbar__row';
          var t = document.createElement('div'); t.className = 'pickbar__txt';
          var b = document.createElement('b'); b.textContent = FAC[fk].name; t.appendChild(b); t.appendChild(document.createElement('span'));
          var cl = document.createElement('button'); cl.type = 'button'; cl.className = 'pickbar__clr'; cl.textContent = W.clear;
          cl.addEventListener('click', function () { delete store[fk]; save(); repaint(fk); bar(); });
          var ca = document.createElement('button'); ca.type = 'button'; ca.className = 'pickbar__cart';
          ca.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1.003 1.003 0 0 0 20 4H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg><span></span>';
          ca.querySelector('span').textContent = W.cart;
          ca.addEventListener('click', function () { open(fk); });
          var go = document.createElement('button'); go.type = 'button'; go.className = 'btn btn--green pickbar__go'; go.textContent = W.send;
          go.addEventListener('click', function () { open(fk, true); });
          r.appendChild(t); r.appendChild(cl); r.appendChild(ca); r.appendChild(go); pb.appendChild(r);
        }
        r.querySelector('.pickbar__txt span').textContent = sum(it, ks);
        r.hidden = !ks.length;
      });
      pb.hidden = !order.some(function (fk) { return Object.keys(list(fk)).length; });
      document.body.style.paddingBottom = pb.hidden ? '' : (pb.offsetHeight + 24) + 'px';
    }
    // Всплывающее окно со списком и формой
    var form = pop.querySelector('.pick-form'), ol = pop.querySelector('.pick-list');
    function fill(fk) {
      var it = list(fk), ks = Object.keys(it);
      pop.querySelector('.pick-pop__fac').textContent = FAC[fk].name;
      ol.innerHTML = '';
      ks.forEach(function (ik, n) {
        var li = document.createElement('li');
        var nm = document.createElement('span'); nm.className = 'pick-list__n'; nm.textContent = it[ik].n;
        var qw = document.createElement('span'); qw.className = 'pick__qty';
        var mi = ctl('pick__m', W.less), pl = ctl('pick__p', W.more);
        var qi = document.createElement('input'); qi.type = 'number'; qi.min = 1; qi.max = 99999; qi.inputMode = 'numeric'; qi.value = it[ik].q; qi.setAttribute('aria-label', W.qty);
        var u = document.createElement('span'); u.className = 'pick__u'; u.textContent = unitOf(it[ik]);
        qw.appendChild(mi); qw.appendChild(qi); qw.appendChild(pl); qw.appendChild(u);
        if (it[ik].k) { var eq = document.createElement('span'); eq.className = 'pick__eq'; eq.textContent = eqOf(it[ik], it[ik].q); qw.appendChild(eq); }
        var x = document.createElement('button'); x.type = 'button'; x.className = 'pick-list__x'; x.setAttribute('aria-label', W.del);
        x.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>';
        mi.addEventListener('click', function () { var q = it[ik].q - 1; if (q >= 1) setItem(fk, ik, it[ik].n, q); });
        pl.addEventListener('click', function () { setItem(fk, ik, it[ik].n, it[ik].q + 1); });
        qi.addEventListener('change', function () { setItem(fk, ik, it[ik].n, qnum(qi.value)); });
        x.addEventListener('click', function () { setItem(fk, ik, it[ik].n, 0); });
        li.appendChild(nm); li.appendChild(qw); li.appendChild(x); ol.appendChild(li);
      });
      if (ks.length) { var tl = document.createElement('li'); tl.className = 'pick-list__tot'; tl.appendChild(document.createElement('span')); ol.appendChild(tl); }
      ol.setAttribute('data-keys', ks.join('|'));
      if (!ks.length && !form.classList.contains('sent')) { var e = document.createElement('li'); e.className = 'pick-list__empty'; e.textContent = W.empty; ol.appendChild(e); }
      sync(fk);
    }
    // поля формы и количество в списке — без пересборки списка
    function sync(fk) {
      var it = list(fk), ks = Object.keys(it), lines = [], qs = ol.querySelectorAll('.pick__qty input');
      ks.forEach(function (ik, n) {
        var x = it[ik], uu = RU_U[x.u] || 'шт.';
        lines.push((n + 1) + '. ' + x.n + ' — ' + (x.k ? x.q + ' уп. по ' + x.k + ' ' + uu + ' = ' + (x.q * x.k) + ' ' + uu : x.q + ' ' + uu));
        if (qs[n] && document.activeElement !== qs[n]) qs[n].value = x.q;
        var eq = qs[n] && qs[n].parentNode.querySelector('.pick__eq'); if (eq) eq.textContent = eqOf(x, x.q);
      });
      var tot = ol.querySelector('.pick-list__tot span'); if (tot) tot.textContent = W.total + ': ' + sum(it, ks);
      form.elements.factory.value = FAC[fk].name;
      if (form.elements.fac) form.elements.fac.value = fk.indexOf('sup-') === 0 ? fk.slice(4) : '';   // страница фабрики — для ссылки на поставщика и китайского запроса (3.43.0)
      form.elements.items.value = lines.join('\n');
      // заказ для писем таблицей (менеджеру — с фото и файлом Excel): товар, вариант, упаковки и штуки
      if (form.elements.items_json) form.elements.items_json.value = JSON.stringify(ks.map(function (ik) {
        var x = it[ik], t = x.t, v = x.v;
        if (!t) { var i = x.n.indexOf(' — '); t = i > 0 ? x.n.slice(0, i) : x.n; v = i > 0 ? x.n.slice(i + 3) : ''; }
        return { t: t, v: v || '', q: x.q, k: x.k || 0, u: x.u || 'pcs', im: x.im || itemImg(ik) };
      }));
      form.setAttribute('data-form', 'Запрос по фабрике: ' + FAC[fk].name);
      form.querySelector('button[type=submit]').disabled = !ks.length;
    }
    // Печать заказа (3.60.0): текущий список из корзины или уже отправленный заказ — отдельной страницей А4
    var LAST = 'itc_pick_sent';
    function esc(t) { return String(t == null ? '' : t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function printOrder(o) {
      var ks = Object.keys(o.i || {}); if (!ks.length) return;
      var d = new Date(o.d || Date.now()), dd = ('0' + d.getDate()).slice(-2) + '.' + ('0' + (d.getMonth() + 1)).slice(-2) + '.' + d.getFullYear();
      var rows = ks.map(function (ik, n) {
        var x = o.i[ik], t = x.t, v = x.v;
        if (!t) { var i = x.n.indexOf(' — '); t = i > 0 ? x.n.slice(0, i) : x.n; v = i > 0 ? x.n.slice(i + 3) : ''; }
        var uu = W.U[x.u] || W.pcs, q = x.k ? x.q + ' ' + W.pk + ' × ' + x.k + ' = ' + (x.q * x.k) + ' ' + uu : x.q + ' ' + uu;
        return '<tr><td>' + (n + 1) + '</td><td>' + (x.im ? '<img src="' + esc(x.im) + '">' : '') + '</td><td>' + esc(t) + '</td><td>' + esc(v || '') + '</td><td class="r">' + esc(q) + '</td></tr>';
      }).join('');
      var w = o.w || {}, who = [[W.oName, w.name], [W.oPhone, w.phone], [W.oMail, w.email], [W.oCmt, w.cmt]].map(function (p) { return '<div><small>' + esc(p[0]) + '</small>' + esc(p[1] || '') + '</div>'; }).join('');
      var h = '<!doctype html><html><head><meta charset="utf-8"><title>' + esc(W.oT + ' — ' + o.n) + '</title><style>'
        + '@page{size:A4;margin:12mm}body{font:12px/1.4 Roboto,Arial,sans-serif;color:#1a2233;margin:0}'
        + '.hd{display:flex;align-items:center;gap:14px;border-bottom:2px solid #0d2a6b;padding-bottom:8px;margin-bottom:10px}.hd img{height:30px}.hd h1{margin:0;font-size:18px;color:#0d2a6b}.hd small{display:block;color:#5b6575}'
        + '.who{display:grid;grid-template-columns:1fr 1fr;gap:6px 18px;margin:0 0 12px}.who div{border-bottom:1px solid #9aa3b2;padding:2px 0 3px;min-height:30px}.who small{display:block;color:#5b6575;font-size:10px}'
        + 'table{border-collapse:collapse;width:100%}th,td{border:1px solid #9aa3b2;padding:4px 6px;text-align:left;vertical-align:middle}th{background:#f1f3f7;font-weight:500}td img{width:22mm;height:22mm;object-fit:contain}td.r{white-space:nowrap}tr{break-inside:avoid}'
        + '.ft{margin-top:12px;color:#5b6575;font-size:11px}</style></head><body>'
        + '<div class="hd"><img src="' + location.origin + '/img/logo.svg" alt="ITC"><div><h1>' + esc(W.oT) + (o.s ? ' (' + esc(W.oSent) + ')' : '') + ': ' + esc(o.n) + '</h1><small>' + esc(W.oDate) + ': ' + dd + ' · ООО «Интернациональная Торговая Компания» · itcpartner.ru</small></div></div>'
        + '<div class="who">' + who + '</div>'
        + '<table><tr><th>№</th><th></th><th>' + esc(W.oItem) + '</th><th>' + esc(W.oVar) + '</th><th>' + esc(W.oQty) + '</th></tr>' + rows + '</table>'
        + '<p><b>' + esc(W.total) + ': ' + esc(sum(o.i, ks)) + '</b></p><p class="ft">' + esc(W.oFoot) + '</p>'
        + '<script>window.onload=function(){setTimeout(function(){window.print();},300);};<\/script></body></html>';
      var pw = window.open('', '_blank'); if (!pw) return;
      pw.document.open(); pw.document.write(h); pw.document.close();
    }
    function formWho() { var e = form.elements; return { name: e.name.value.trim(), phone: e.phone.value.trim(), email: e.email.value.trim(), cmt: e.question ? e.question.value.trim() : '' }; }
    var pbtn = document.createElement('button'); pbtn.type = 'button'; pbtn.className = 'pick-print';
    pbtn.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg><span></span>';
    pbtn.querySelector('span').textContent = W.prn;
    ol.parentNode.insertBefore(pbtn, form);
    pbtn.addEventListener('click', function () {
      var fk = pop.getAttribute('data-fac'), f = store[fk], sent = null;
      if (f && Object.keys(f.i).length) printOrder({ n: FAC[fk].name, i: f.i, w: formWho(), d: Date.now() });
      else { try { sent = (JSON.parse(localStorage.getItem(LAST) || '{}') || {})[fk]; } catch (x) {} if (sent) printOrder(sent); }
    });
    function pbtnState(fk) {
      var has = Object.keys(list(fk)).length, sent = null;
      try { sent = (JSON.parse(localStorage.getItem(LAST) || '{}') || {})[fk]; } catch (x) {}
      pbtn.hidden = !has && !sent;
      pbtn.querySelector('span').textContent = has ? W.prn : W.prnSent;
    }
    function open(fk, toForm) {
      pop.setAttribute('data-fac', fk);
      form.classList.remove('sent'); var m = form.querySelector('.form-msg'); m.className = 'form-msg'; m.textContent = '';
      ol.hidden = false; fill(fk); pbtnState(fk); openPop('pick');
      if (toForm) setTimeout(function () { var f = form.elements.name; if (f && !f.value) try { f.focus({ preventScroll: false }); } catch (x) {} }, 120);
    }
    form.addEventListener('lead:sent', function (e) {
      var fk = pop.getAttribute('data-fac');
      if (e.detail && e.detail.copy && W.copy) form.querySelector('.form-msg').textContent = W.copy.replace('{m}', form.elements.email.value.trim());
      // копия отправленного заказа — чтобы его можно было распечатать (3.60.0)
      if (store[fk]) { try { var ls = JSON.parse(localStorage.getItem(LAST) || '{}') || {}; ls[fk] = { n: FAC[fk].name, i: store[fk].i, w: formWho(), d: Date.now(), s: 1 }; localStorage.setItem(LAST, JSON.stringify(ls)); } catch (x) {} }
      delete store[fk]; save(); repaint(fk); bar(); ol.hidden = true; pbtnState(fk);
    });
    bar();
  })();

  // Цели Метрики: нажатия на телефон, WhatsApp, Telegram, почту
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a || !window.ym) return;
    var h = a.getAttribute('href') || '', g = '';
    if (h.indexOf('tel:') === 0) g = 'click_phone';
    else if (/wa\.me|whatsapp/i.test(h)) g = 'click_whatsapp';
    else if (/t\.me\//i.test(h)) g = 'click_telegram';
    else if (h.indexOf('mailto:') === 0) g = 'click_email';
    if (g) try { ym(window.__ymId, 'reachGoal', g); } catch (x) {}
  }, true);
})();

// Листалка фото в карточках товаров
(function () {
  document.querySelectorAll('.gal').forEach(function (g) {
    var tr = g.querySelector('.gal__track'), dots = g.querySelectorAll('.gal__dots i');
    var n = tr.children.length;
    if (n < 2) return;
    function cur() { return Math.round(tr.scrollLeft / tr.clientWidth); }
    function go(i) { i = (i + n) % n; tr.scrollTo({ left: i * tr.clientWidth, behavior: 'smooth' }); }
    g.querySelector('.gal__btn--prev').addEventListener('click', function () { go(cur() - 1); });
    g.querySelector('.gal__btn--next').addEventListener('click', function () { go(cur() + 1); });
    tr.addEventListener('scroll', function () {
      var c = cur();
      dots.forEach(function (d, k) { d.className = k === c ? 'on' : ''; });
    }, { passive: true });
  });
})();

// Увеличение фото товара по нажатию: все фото этого товара, стрелки, листание пальцем, Esc — закрыть (3.39.0)
(function () {
  var box = null, list = [], idx = 0, img, cnt, prev, next, x0 = null, y0 = null;
  var L = { ru: ['Закрыть', 'Предыдущее фото', 'Следующее фото'], en: ['Close', 'Previous photo', 'Next photo'], zh: ['关闭', '上一张', '下一张'] }[(document.documentElement.lang || 'ru').slice(0, 2)] || ['Закрыть', 'Предыдущее фото', 'Следующее фото'];
  var arrow = function (d) { return '<svg viewBox="0 0 24 24" width="30" height="30"><path d="' + d + '" fill="currentColor"/></svg>'; };
  function build() {
    box = document.createElement('div'); box.className = 'zbox';
    box.innerHTML = '<div class="zbox__stage"><img alt=""></div>'
      + '<button type="button" class="zbox__x" aria-label="' + L[0] + '"><svg viewBox="0 0 24 24" width="30" height="30"><path d="M19 6.4 17.6 5 12 10.6 6.4 5 5 6.4 10.6 12 5 17.6 6.4 19 12 13.4 17.6 19 19 17.6 13.4 12z" fill="currentColor"/></svg></button>'
      + '<button type="button" class="zbox__nav zbox__nav--prev" aria-label="' + L[1] + '">' + arrow('M15.4 7.4 14 6l-6 6 6 6 1.4-1.4-4.6-4.6z') + '</button>'
      + '<button type="button" class="zbox__nav zbox__nav--next" aria-label="' + L[2] + '">' + arrow('M8.6 16.6 10 18l6-6-6-6-1.4 1.4 4.6 4.6z') + '</button>'
      + '<div class="zbox__cnt"></div>';
    img = box.querySelector('img'); cnt = box.querySelector('.zbox__cnt');
    prev = box.querySelector('.zbox__nav--prev'); next = box.querySelector('.zbox__nav--next');
    box.querySelector('.zbox__x').addEventListener('click', close);
    prev.addEventListener('click', function (e) { e.stopPropagation(); show(idx - 1); });
    next.addEventListener('click', function (e) { e.stopPropagation(); show(idx + 1); });
    box.addEventListener('click', function (e) { if (e.target === box || e.target.classList.contains('zbox__stage')) close(); });
    box.addEventListener('touchstart', function (e) { if (e.touches.length === 1) { x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; } else x0 = null; }, { passive: true });
    box.addEventListener('touchend', function (e) {
      if (x0 === null || list.length < 2) return;
      var dx = e.changedTouches[0].clientX - x0, dy = e.changedTouches[0].clientY - y0; x0 = null;
      if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) show(idx + (dx < 0 ? 1 : -1));
    }, { passive: true });
    document.body.appendChild(box);
  }
  function show(i) {
    var n = list.length; idx = (i + n) % n;
    img.src = list[idx].src; img.alt = list[idx].alt || '';
    cnt.textContent = n > 1 ? (idx + 1) + ' / ' + n : '';
    prev.hidden = next.hidden = n < 2;
  }
  function close() { box.classList.remove('on'); document.body.classList.remove('lock'); }
  document.addEventListener('keydown', function (e) {
    if (!box || !box.classList.contains('on')) return;
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowLeft') show(idx - 1);
    else if (e.key === 'ArrowRight') show(idx + 1);
  });
  document.addEventListener('click', function (e) {
    var im = e.target.closest('.gal__track img, .pcr--nopick .pcr__img img');
    if (!im || e.target.closest('a')) return;
    var holder = im.closest('.gal__track') || im.parentNode;
    list = [].slice.call(holder.querySelectorAll('img')).map(function (x) { return { src: x.currentSrc || x.src, alt: x.alt }; });
    if (!box) build();
    show([].indexOf.call(holder.querySelectorAll('img'), im));
    box.classList.add('on'); document.body.classList.add('lock');
  });
})();

// Кнопка «Показать все модели» под группой товаров (3.40.0)
(function () {
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('.js-pcr-more'); if (!b) return;
    var grid = b.previousElementSibling; while (grid && !grid.classList.contains('pcr-grid')) grid = grid.previousElementSibling;
    if (!grid) return;
    var open = !grid.classList.contains('is-open');
    grid.classList.toggle('is-open', open); b.classList.toggle('is-open', open);
    if (!open) { var h = grid.previousElementSibling; while (h && !h.classList.contains('pcr-group')) h = h.previousElementSibling; (h || grid).scrollIntoView({ block: 'start' }); }
  });
})();
