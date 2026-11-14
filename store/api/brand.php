<?php
/* №556 · ФИРМЕННЫЙ СТИЛЬ ИТК — одна база для всех документов: КП, комплексные КП, счета, коммерческие запросы поставщикам.
   Кто бы ни попросил Фила или «Космоса» — документ выходит одинаковым: тот же логотип, цвета, шапка, подвал, подпись.
   Основа взята с сайта itcpartner.ru (логотип /img/logo.png, тёмно-синий #0d2a6b, зелёный #128c04).
   Руководитель меняет стиль одним разом (инструмент brand_set или настройки): логотип — общий и для каждой компании свой,
   цвета, слоган, подпись. Хранится: itc_secret[brand] (JSON) + uploads/brand/*.png|jpg. */

const BR_DEFAULT = ['primary' => '0d2a6b', 'accent' => '128c04', 'text' => '26262b', 'gray' => '6c7177', 'light' => 'f1f5fb',
                    'slogan' => 'Поставки напрямую с заводов Китая', 'slogan_en' => 'Direct supplies from factories in China', 'site_logo' => 'https://itcpartner.ru/img/logo.png', 'sign_name' => '', 'sign_title' => ''];

function br_dir() { $d = dirname(__DIR__) . '/uploads/brand'; if (!is_dir($d)) @mkdir($d, 0755, true); return $d; }
function br_get() {
  $j = function_exists('sp_get') ? json_decode((string)sp_get('brand'), true) : null;
  $b = BR_DEFAULT; if (is_array($j)) foreach ($j as $k => $v) if (array_key_exists($k, BR_DEFAULT) || $k === 'logos') $b[$k] = $v;
  foreach (['primary', 'accent', 'text', 'gray', 'light'] as $k) if (!preg_match('~^[0-9a-f]{6}$~i', (string)$b[$k])) $b[$k] = BR_DEFAULT[$k];
  $b['logos'] = is_array($b['logos'] ?? null) ? $b['logos'] : [];
  return $b;
}
function br_save(array $b, $who) {
  $keep = []; foreach ($b as $k => $v) if (array_key_exists($k, BR_DEFAULT) || $k === 'logos') $keep[$k] = $v;
  sp_put('brand', json_encode($keep, JSON_UNESCAPED_UNICODE), $who);
}
/* логотип для документа (JPEG): компании → общий → со старых мест → с сайта (скачивается один раз) */
function br_logo($coId = '') {
  $b = br_get(); $d = br_dir();
  foreach (array_filter([(string)($b['logos'][(string)$coId] ?? ''), (string)($b['logos']['main'] ?? '')]) as $fn) {
    $f = $d . '/' . basename($fn); if (is_file($f)) { $j = function_exists('kp_jpeg') ? kp_jpeg((string)file_get_contents($f), 700) : ''; if ($j !== '') return $j; }
  }
  $own = __DIR__ . '/kp_fonts/itc-logo.jpg'; if (is_file($own)) return (string)file_get_contents($own);
  foreach ([dirname(__DIR__) . '/eco/itc-logo.png', (function_exists('bk_site_root') ? bk_site_root() : '') . '/img/logo.png', $d . '/site-logo.png'] as $f)
    if ($f !== '' && is_file($f) && function_exists('kp_jpeg')) { $j = kp_jpeg((string)file_get_contents($f), 700); if ($j !== '') return $j; }
  $u = (string)$b['site_logo'];   // ни одного файла — берём логотип с сайта и кладём рядом, чтобы больше не скачивать
  if (preg_match('~^https://~', $u) && function_exists('curl_init')) {
    $ch = curl_init($u); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3]);
    $bin = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code === 200 && strlen($bin) > 100 && @getimagesizefromstring($bin)) { @file_put_contents($d . '/site-logo.png', $bin); return function_exists('kp_jpeg') ? kp_jpeg($bin, 700) : ''; }
  }
  return '';
}
/* установить стиль (только руководитель): логотип из приложенного файла, цвета, слоган, подпись */
function br_set($me, $a) {
  if (empty($me['boss'])) return 'Фирменный стиль меняет только руководитель';
  $b = br_get(); $changed = [];
  foreach (['primary', 'accent', 'text', 'gray', 'light'] as $k) if (!empty($a[$k])) {
    $v = ltrim(strtolower((string)$a[$k]), '#'); if (!preg_match('~^[0-9a-f]{6}$~', $v)) return "цвет $k — вида #0d2a6b"; $b[$k] = $v; $changed[] = $k; }
  foreach (['slogan', 'slogan_en', 'sign_name', 'sign_title'] as $k) if (isset($a[$k])) { $b[$k] = mb_substr(trim((string)$a[$k]), 0, 160); $changed[] = $k; }
  if (!empty($a['logo_file'])) {
    if (function_exists('ff_cols')) ff_cols();
    $q = db()->prepare('SELECT name,path FROM itc_fc_file WHERE id=?'); $q->execute([(string)$a['logo_file']]); $f = $q->fetch();
    $src = $f && $f['path'] !== '' && function_exists('ff_dir') ? ff_dir() . '/' . basename((string)$f['path']) : '';
    if ($src === '' || !is_file($src)) return 'логотип: нет оригинала файла — пришлите картинку (PNG/JPG) ещё раз';
    $bin = (string)file_get_contents($src); $sz = @getimagesizefromstring($bin);
    if (!$sz || $sz[0] < 80) return 'логотип: это не картинка или она слишком маленькая (нужно от 300 px по ширине)';
    $co = preg_replace('~[^A-Za-z0-9_-]~', '', (string)($a['company_id'] ?? '')) ?: 'main';
    if (!empty($a['company']) && function_exists('kp_company')) { $c = kp_company((string)$a['company']); if (!empty($c['id'])) $co = $c['id']; }
    $fn = $co . '-' . date('Ymd-His') . ($sz[2] === IMAGETYPE_PNG ? '.png' : '.jpg');
    file_put_contents(br_dir() . '/' . $fn, $bin); $b['logos'][$co] = $fn; $changed[] = 'логотип ' . ($co === 'main' ? '(общий)' : '(' . $co . ')');
  }
  if (!$changed) return 'Что поменять? Логотип (приложите файл), цвета primary/accent, slogan, подпись sign_name/sign_title';
  br_save($b, (string)($me['name'] ?? 'руководитель'));
  return ['готово' => 'фирменный стиль обновлён: ' . implode(', ', $changed), 'дальше' => 'все новые КП, счета и запросы будут в этом стиле, у всех менеджеров одинаково'];
}
function br_info() {
  $b = br_get();
  return ['цвета' => ['основной' => '#' . $b['primary'], 'акцент' => '#' . $b['accent'], 'текст' => '#' . $b['text']], 'слоган' => $b['slogan'],
          'логотипы' => $b['logos'] ? array_keys($b['logos']) : ['с сайта itcpartner.ru'], 'подпись' => trim($b['sign_name'] . ' ' . $b['sign_title']) ?: 'нет',
          'где используется' => 'все КП (make_kp), счета (make_invoice), коммерческие запросы поставщикам (make_request)'];
}

/* ---------- №556 · КОММЕРЧЕСКИЙ ЗАПРОС поставщику — в фирменном стиле (PDF + Excel) ---------- */
function rq_make($me, $a) {
  if (!function_exists('kp_dir')) return 'нет модуля документов';
  kp_ensure();
  $items = [];
  foreach (array_slice((array)($a['items'] ?? []), 0, 60) as $it) {
    if (!is_array($it) || trim((string)($it['name'] ?? '')) === '') continue;
    $items[] = ['name' => mb_substr(trim((string)$it['name']), 0, 200), 'spec' => mb_substr(trim((string)($it['spec'] ?? '')), 0, 400), 'qty' => mb_substr(trim((string)($it['qty'] ?? '')), 0, 40)];
  }
  if (!$items) return 'Нужен список: items [{name, spec, qty}] — что запрашиваем';
  $lang = ($a['lang'] ?? 'ru') === 'en' ? 'en' : 'ru';
  $sup = null; if (!empty($a['sup']) && function_exists('kp_find_sup')) { $f = kp_find_sup((string)$a['sup']); if (count($f) === 1) $sup = $f[0]; elseif (count($f) > 1) return ['несколько похожих поставщиков — передай id' => array_map(function ($s) { return ['id' => $s['id'], 'название' => $s['name']]; }, array_slice($f, 0, 6))]; }
  $co = kp_company($a['company'] ?? ''); $b = br_get();
  $T = $lang === 'en'
    ? ['h' => 'Request for Quotation', 'to' => 'To', 'from' => 'From', 'no' => '№', 'name' => 'Product', 'spec' => 'Specification', 'qty' => 'Quantity', 'ask' => 'Please quote',
       'asks' => ['Unit price (EXW / FOB), currency', 'MOQ and lead time', 'Payment terms', 'Packing, weight and volume', 'Certificates (CE, ISO …), warranty', 'Catalogue and photos'], 'reply' => 'Please reply by', 'sign' => 'Best regards']
    : ['h' => 'Коммерческий запрос', 'to' => 'Кому', 'from' => 'От', 'no' => '№', 'name' => 'Товар', 'spec' => 'Характеристики', 'qty' => 'Количество', 'ask' => 'Просим указать',
       'asks' => ['Цена за единицу (EXW / FOB), валюта', 'Минимальный заказ (MOQ) и срок производства', 'Условия оплаты', 'Упаковка, вес и объём', 'Сертификаты (CE, ISO …), гарантия', 'Каталог и фото'], 'reply' => 'Ответ просим до', 'sign' => 'С уважением'];
  $to = trim((string)($a['to'] ?? '')) ?: ($sup ? $sup['name'] : '');
  $due = preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)($a['due'] ?? '')) ? date('d.m.Y', strtotime($a['due'])) : date('d.m.Y', time() + 5 * 86400);
  $no = 'RQ-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
  $F = __DIR__ . '/kp_fonts/'; $p = new KpPdf($F . 'Roboto-Regular.ttf', $F . 'Roboto-Bold.ttf');
  $P = $b['primary']; $A = $b['accent']; $G = $b['gray']; $X = $b['text']; $logo = br_logo($co['id'] ?? '');
  $p->page(); $y = 0;
  if ($logo !== '') $p->image($logo, 15, 9, 34, 13);
  $p->font('B', 15); $p->color($P); $p->text(56, 16, $T['h'] . '  ' . $no);
  $cname = $lang === 'en' && trim((string)($co['name2'] ?? '')) !== '' ? (string)$co['name2'] : ($lang === 'en' ? 'International Trading Company (ITC)' : $co['name']);
  $slog = $lang === 'en' ? (string)$b['slogan_en'] : (string)$b['slogan'];
  $p->font('R', 8.5); $p->color($G); $p->text(56, 21.5, ($lang === 'en' ? 'ITC' : $co['short']) . ' · ' . date('d.m.Y') . ($slog ? ' · ' . $slog : ''));
  $p->line(15, 26, 195, 26, $A, 0.8); $y = 35;
  $p->font('R', 10); $p->color($X);
  if ($to !== '') { $p->font('B', 10); $p->text(15, $y, $T['to'] . ': '); $p->font('R', 10); $p->text(30, $y, $to); $y += 6; }
  $p->font('B', 10); $p->text(15, $y, $T['from'] . ': '); $p->font('R', 10);
  foreach ($p->wrap($cname . ($co['site'] ? ' · ' . $co['site'] : '') . ($co['email'] ? ' · ' . $co['email'] : ''), 165) as $l) { $p->text(30, $y, $l); $y += 5; } $y += 3;
  $intro = trim((string)($a['text'] ?? ''));
  if ($intro !== '') { foreach ($p->wrap($intro, 180) as $l) { $p->text(15, $y, $l); $y += 5; } $y += 3; }
  // таблица
  $cols = [[15, 10, $T['no']], [25, 60, $T['name']], [85, 80, $T['spec']], [165, 30, $T['qty']]];
  $p->rect(15, $y - 4.5, 180, 7, $P); $p->font('B', 9); $p->color('ffffff'); foreach ($cols as $c) $p->text($c[0] + 1.5, $y, $c[2]); $y += 6;
  $p->font('R', 9); $p->color($X);
  foreach ($items as $i => $it) {
    $nl = $p->wrap($it['name'], 57); $sl = $it['spec'] !== '' ? $p->wrap($it['spec'], 77) : ['']; $h = max(count($nl), count($sl)) * 4.3 + 2.5;
    if ($y + $h > 262) { $p->page(); $y = 20; }
    if ($i % 2) $p->rect(15, $y - 3.8, 180, $h, $b['light']);
    $p->text(16.5, $y, (string)($i + 1)); $yy = $y; foreach ($nl as $l) { $p->text(26.5, $yy, $l); $yy += 4.3; }
    $yy = $y; foreach ($sl as $l) { $p->text(86.5, $yy, $l); $yy += 4.3; } $p->text(166.5, $y, $it['qty']);
    $y += $h;
  }
  $y += 5; if ($y > 225) { $p->page(); $y = 20; }
  $p->font('B', 10.5); $p->color($P); $p->text(15, $y, $T['ask'] . ':'); $y += 6; $p->font('R', 9.5); $p->color($X);
  foreach ($T['asks'] as $q) { $p->rect(15, $y - 2.2, 1.6, 1.6, $A); $p->text(19, $y, $q); $y += 5; }
  $y += 3; $p->font('B', 10); $p->color($A); $p->text(15, $y, $T['reply'] . ' ' . $due); $y += 10;
  $p->font('R', 10); $p->color($X); $p->text(15, $y, $T['sign'] . ','); $y += 5;
  $sn = trim((string)($a['sign'] ?? '')) ?: (trim($b['sign_name']) ?: (string)($me['name'] ?? ''));
  $p->font('B', 10); $p->text(15, $y, $sn); $y += 5; $p->font('R', 9); $p->color($G); $p->text(15, $y, trim($b['sign_title'] . ' · ' . ($lang === 'en' ? $cname : $co['short']), ' ·'));
  $p->line(15, 282, 195, 282, 'd9deea', 0.3); $p->font('R', 7.5); $p->color($G);
  $p->text(15, 286.5, trim($co['name'] . ($co['inn'] ? ' · ИНН ' . $co['inn'] : ''))); $p->text(15, 290.5, trim(implode(' · ', array_filter([$co['phone'], $co['email'], $co['site']]))));
  $id = 'kp' . bin2hex(random_bytes(6));
  file_put_contents(kp_dir() . "/$id.pdf", $p->output());
  $nice = ($lang === 'en' ? 'RFQ ' : 'Запрос ') . ($to !== '' ? $to : $no);
  db()->prepare('INSERT INTO itc_fil_kp (id,phone,title,kind,sup_id,client,files,items) VALUES (?,?,?,?,?,?,?,?)')->execute([$id, $me['phone'], mb_substr($nice, 0, 190), 'rq', $sup['id'] ?? '', $to, 'pdf', count($items)]);
  $GLOBALS['KP_MADE'][] = ['id' => $id, 't' => $nice];
  if ($sup && function_exists('sh_add')) try { sh_add($me, ['sup' => $sup['id'], 'kind' => 'переписка', 'text' => 'Отправлен коммерческий запрос ' . $no . ': ' . implode('; ', array_map(function ($x) { return $x['name'] . ($x['qty'] !== '' ? ' — ' . $x['qty'] : ''); }, $items)), 'source' => 'fil']); } catch (Throwable $e) {}
  return ['готово' => $nice, 'номер' => $no, 'позиций' => count($items), 'язык' => $lang, 'от компании' => $co['short'], 'ответ до' => $due,
          'как выдать' => 'файл уже прикреплён к ответу кнопкой — скажи, что запрос готов' . ($sup ? '; запись о запросе — в истории поставщика' : '')];
}
function br_tools($me) {
  $t = [
    'brand' => ['{} — ФИРМЕННЫЙ СТИЛЬ: логотипы, цвета, слоган, подпись — что сейчас используется во всех документах', function ($a) { return br_info(); }],
    'make_request' => ['{"sup":"поставщик из базы (id/название) или пусто","to":"кому, если не из базы","items":[{"name":"товар","spec":"характеристики","qty":"количество"}],"text":"вступление или пусто","lang":"ru|en","due":"YYYY-MM-DD — до какого числа ответить","company":"наша компания или пусто","sign":"кто подписывает или пусто"} — КОММЕРЧЕСКИЙ ЗАПРОС поставщику (запрос цены) PDF в фирменном стиле ИТК; запись о запросе — в историю поставщика. Для Китая — lang=en',
      function ($a) use ($me) { try { return rq_make($me, $a); } catch (Throwable $e) { return 'не получилось: ' . $e->getMessage(); } }],
  ];
  if (!empty($me['boss'])) $t['brand_set'] = ['{"logo_file":"id приложенной картинки","company":"чья (наша компания) — пусто: общий","primary":"#0d2a6b","accent":"#128c04","slogan":"…","sign_name":"…","sign_title":"…"} — ИЗМЕНИТЬ ФИРМЕННЫЙ СТИЛЬ (только руководитель): логотип, цвета, слоган, подпись — сразу для всех документов всех менеджеров',
    function ($a) use ($me) { try { return br_set($me, $a); } catch (Throwable $e) { return 'не получилось: ' . $e->getMessage(); } }];
  return $t;
}
