<?php
// ==== ИТК · СТРАНИЦЫ ПОСТАВЩИКОВ НА САЙТЕ itcpartner.ru · sup_web.php · №4 (09.11.2026) ====
// №4: sup_web_fetch — файл с сайта завода через свой адрес (только босс).
// №2: страницы на сайте — только босс (сотрудникам кнопка не видна и сервер не пускает).
// №3: общие функции вынесены в sup_web_lib.php; при публикации переводятся только новые фразы.
// Подключается из suppliers.php.
require_once __DIR__ . '/sup_web_lib.php';

$wa = $action;

/* ---------- публичные: для сайта ---------- */
if ($wa === 'sup_web_list') {
  sup_web_ensure();
  $rows = db()->query("SELECT w.slug, w.data, w.updated_at FROM itc_sup_web w JOIN itc_sup s ON s.id=w.sup_id AND s.deleted=0 WHERE w.status='live' ORDER BY w.published_at")->fetchAll();
  $out = [];
  foreach ($rows as $r) { $d = sup_dec($r['data']); $out[] = ['slug'=>$r['slug'], 'crumb'=>$d['crumb'] ?? '', 'menu_sub'=>$d['menu_sub'] ?? '', 'updated'=>$r['updated_at']]; }
  jout(['ok'=>true, 'pages'=>$out]);
}
if ($wa === 'sup_web_page') {
  sup_web_ensure();
  $slug = sup_slugify($_GET['slug'] ?? '');
  $q = db()->prepare("SELECT w.* FROM itc_sup_web w JOIN itc_sup s ON s.id=w.sup_id AND s.deleted=0 WHERE w.slug=? AND w.status='live' LIMIT 1");
  $q->execute([$slug]); $r = $q->fetch();
  if (!$r) fail('Страница не найдена', 404);
  jout(['ok'=>true, 'page'=>sup_dec($r['data']), 'tr'=>sup_dec($r['tr']), 'updated'=>$r['updated_at']]);
}

/* ---------- для сотрудников ---------- */
$me = sup_me(); sup_web_ensure();

if ($wa === 'sup_media_raw') {
  // PDF и фото из хранилища — через свой адрес, чтобы Экосистема могла их нарисовать
  $id = (int)($_GET['id'] ?? 0);
  $q = db()->prepare('SELECT url,mime FROM itc_sup_media WHERE id=?'); $q->execute([$id]);
  $m = $q->fetch(); if (!$m) fail('Файл не найден', 404);
  @set_time_limit(300);
  header('Content-Type: ' . ($m['mime'] ?: 'application/octet-stream'));
  header('Cache-Control: private, max-age=3600');
  $ch = curl_init($m['url']);
  curl_setopt_array($ch, [CURLOPT_FOLLOWLOCATION=>true, CURLOPT_CONNECTTIMEOUT=>15, CURLOPT_TIMEOUT=>280,
    CURLOPT_WRITEFUNCTION=>function($c, $chunk){ echo $chunk; return strlen($chunk); }]);
  curl_exec($ch); curl_close($ch);
  exit;
}

// Страницы на сайте — только босс (решение Алексея 10.10.2026)
if (!$me['boss']) fail('Страницы на сайте может делать только босс', 403);

if ($wa === 'sup_web_fetch') {
  // №4 (09.11.2026): каталог или фото с сайта завода — через свой адрес (только босс),
  // чтобы Экосистема могла сохранить его в хранилище, как файл, загруженный вручную.
  $u = trim((string)($_GET['url'] ?? ''));
  $pu = parse_url($u);
  if (!$pu || !in_array(strtolower($pu['scheme'] ?? ''), ['http', 'https'], true) || empty($pu['host'])) fail('Неверный адрес');
  $ip = gethostbyname($pu['host']);
  if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) fail('Адрес недоступен');
  @set_time_limit(300);
  $max = 120 * 1024 * 1024; $got = 0; $sent = false;
  $ch = curl_init($u);
  curl_setopt_array($ch, [CURLOPT_FOLLOWLOCATION=>true, CURLOPT_MAXREDIRS=>5, CURLOPT_CONNECTTIMEOUT=>20, CURLOPT_TIMEOUT=>280,
    CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,
    CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130 Safari/537.36',
    CURLOPT_HEADERFUNCTION=>function($c, $h){ if (preg_match('~^Content-Type:\s*([^\r\n;]+)~i', $h, $m)) header('Content-Type: ' . trim($m[1])); return strlen($h); },
    CURLOPT_WRITEFUNCTION=>function($c, $chunk) use (&$got, $max){ $got += strlen($chunk); if ($got > $max) return 0; echo $chunk; return strlen($chunk); }]);
  header('Cache-Control: no-store');
  curl_exec($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
  if ($got === 0) { http_response_code($code >= 400 ? $code : 502); }
  exit;
}

$supId = sup_str(($_GET['id'] ?? '') ?: (body()['id'] ?? ''), 40);
$q = db()->prepare('SELECT * FROM itc_sup WHERE id=? AND deleted=0'); $q->execute([$supId]);
$sup = $q->fetch();
if (!$sup) fail('Поставщик не найден');

if ($wa === 'sup_web_get') {
  $r = sup_web_row($supId);
  jout(['ok'=>true, 'web'=>$r ? sup_dec($r['data']) : null, 'status'=>$r['status'] ?? '',
        'published_at'=>$r['published_at'] ?? null, 'site'=>SUP_WEB_SITE]);
}


/* Адрес страницы: свой, не занятый сайтом и другим поставщиком. */
$slugCheck = function($slug) use ($supId) {
  if ($slug === '' || strlen($slug) < 3) return 'Укажите адрес страницы латиницей, не короче 3 букв';
  if (in_array($slug, SUP_WEB_RESERVED, true)) return 'Адрес /' . $slug . ' уже занят страницей сайта — выберите другой';
  $q = db()->prepare('SELECT sup_id FROM itc_sup_web WHERE slug=? AND sup_id<>?'); $q->execute([$slug, $supId]);
  if ($q->fetchColumn()) return 'Адрес /' . $slug . ' уже занят другим поставщиком';
  return '';
};
$saveRow = function($data, $status = null, $tr = null) use ($supId, $me) {
  $r = sup_web_row($supId);
  $st = $status ?? ($r['status'] ?? 'draft');
  $js = json_encode($data, JSON_UNESCAPED_UNICODE);
  if ($r) {
    $sql = 'UPDATE itc_sup_web SET slug=?, status=?, data=?, updated_by=?, updated_at=NOW()' . ($tr !== null ? ', tr=?' : '')
         . ($status === 'live' ? ', published_at=COALESCE(published_at, NOW())' : '') . ' WHERE sup_id=?';
    $args = [$data['slug'], $st, $js, $me['who']]; if ($tr !== null) $args[] = json_encode($tr, JSON_UNESCAPED_UNICODE); $args[] = $supId;
    db()->prepare($sql)->execute($args);
  } else {
    db()->prepare('INSERT INTO itc_sup_web (sup_id,slug,status,data,tr,updated_by,published_at) VALUES (?,?,?,?,?,?,' . ($status === 'live' ? 'NOW()' : 'NULL') . ')')
        ->execute([$supId, $data['slug'], $st, $js, json_encode($tr ?? [], JSON_UNESCAPED_UNICODE), $me['who']]);
  }
};
/* Сайт помнит страницу 10 минут — после изменений просим его забыть сразу. */
$pingSite = function($slug) {
  $u = SUP_WEB_SITE . '/brand-refresh?k=' . SUP_WEB_REFRESH_KEY . '&slug=' . rawurlencode($slug);
  $ch = curl_init($u);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_SSL_VERIFYPEER=>false]);
  $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  return $code === 200;
};

if ($wa === 'sup_web_save') {
  $data = sup_web_clean(body()['web'] ?? []);
  if ($e = $slugCheck($data['slug'])) fail($e);
  $saveRow($data);
  $r = sup_web_row($supId);
  if (($r['status'] ?? '') === 'live') $pingSite($data['slug']);
  jout(['ok'=>true, 'web'=>$data, 'status'=>$r['status'] ?? 'draft']);
}

if ($wa === 'sup_web_draft') {
  @set_time_limit(300);
  $old = sup_web_row($supId);
  $oldData = $old ? sup_dec($old['data']) : [];
  $card = [
    'название' => $sup['name'], 'полное название' => $sup['full_name'], 'город' => $sup['city'],
    'кто это' => $sup['kind'], 'чем занимаются' => (string)$sup['what'],
    'факты' => sup_dec($sup['facts']), 'товары' => sup_dec($sup['products']),
  ];
  $sys = "Ты пишешь страницы сайта российской импортной компании ИТК (ООО «Интернациональная Торговая Компания»). "
    . "ИТК покупает товар напрямую у китайских заводов и привозит в Россию официально: оплата в рублях, договор, документы, сертификаты.\n"
    . "По данным о заводе составь страницу о нём в формате JSON. Пиши по-русски, просто, конкретно, без воды и без восклицательных знаков.\n"
    . "СТРОГО ЗАПРЕЩЕНО: телефоны, почта, WeChat, адреса сайтов завода, имена менеджеров, точный адрес завода, "
    . "упоминания каталогов, фразы вроде «мы лично были на заводе», «фото из нашей поездки», выдуманные цифры. "
    . "Используй только факты из данных. Если фактов мало — пиши короче. Цены не указывай.\n"
    . "Поля JSON:\n"
    . "slug — адрес страницы латиницей: название бренда маленькими буквами (например ronma, yinen-power);\n"
    . "brand — короткое имя бренда, как пишут латиницей;\n"
    . "crumb — название для меню, 2–4 слова: тип товара + бренд (например «Солнечные панели Ronma»);\n"
    . "menu_sub — подпись в меню до 45 знаков (например «Панели и накопители с завода»);\n"
    . "title — заголовок для поиска до 110 знаков, в конце « | ИТК»;\n"
    . "descr — описание для поиска 150–250 знаков;\n"
    . "h1 — ЗАГЛАВНЫМИ: ЧТО + БРЕНД + НАПРЯМУЮ С ЗАВОДА;\n"
    . "lead — 1–2 предложения: что поставляем под заказ, «Покупаем напрямую у завода в Китае — без посредников и лишних наценок.»;\n"
    . "bullets — 4 пункта (прямая работа с заводом; главное о товаре; оплата в рублях, официальный ввоз, документы; гарантия, если известна);\n"
    . "cta — текст кнопки, например «Запросить цены на Ronma»;\n"
    . "hero_alt, factory_alt — подписи к фото;\n"
    . "about_title — «О ЗАВОДЕ БРЕНД» заглавными; about_lead — 1–2 предложения о заводе; about_bullets — 3–6 фактов;\n"
    . "models_title — например «МОДЕЛИ, ДОСТУПНЫЕ К ЗАКАЗУ» или «ОБОРУДОВАНИЕ, ДОСТУПНОЕ К ЗАКАЗУ»; models_lead — 1 предложение;\n"
    . "groups — группы товаров [{name, sub, items:[{name, desc}]}]: разложи товары из данных по 1–5 группам, у товара короткое название и 1 предложение с характеристиками;\n"
    . "uses_title — «ГДЕ ПРИГОДИТСЯ …» или «КОМУ ПОДОЙДЁТ»; uses_lead — 1 предложение; uses — ровно 6 карточек [{h, p}];\n"
    . "adv — ровно 6 преимуществ [{h, p}], h заглавными 1–2 слова (НАПРЯМУЮ С ЗАВОДА, …, ДОКУМЕНТЫ, ОПЛАТА В РУБЛЯХ, ДОСТАВКА, …), p — одно короткое предложение;\n"
    . "adv_bottom — одна строка «Товар БРЕНД — напрямую с завода в Китае до вашего склада»;\n"
    . "coop_lead — «Ищем партнёров для поставок БРЕНД в Россию.»; coop — 4 пункта, кому подходит;\n"
    . "faq — 5–7 вопросов [{q, a}]: напрямую ли у завода, сколько стоит, сроки, минимальный заказ, подходит ли для России, документы, гарантия (если есть данные).\n\n"
    . sup_web_sample();
  $r = sup_ds_json($sys, "Данные о заводе:\n" . json_encode($card, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 5000);
  if (!is_array($r)) fail($r);
  // фото, уже подобранные к товарам и обложке, не теряем
  $r['hero'] = $oldData['hero'] ?? ''; $r['factory'] = $oldData['factory'] ?? '';
  $oldPh = [];
  foreach (($oldData['groups'] ?? []) as $g) foreach (($g['items'] ?? []) as $it) if (!empty($it['photos'])) $oldPh[mb_strtolower(trim($it['name']))] = $it['photos'];
  foreach (($r['groups'] ?? []) as $gi => $g) foreach (($g['items'] ?? []) as $ii => $it) {
    $k = mb_strtolower(trim((string)($it['name'] ?? '')));
    $r['groups'][$gi]['items'][$ii]['photos'] = $oldPh[$k] ?? [];
  }
  if (!empty($oldData['slug'])) $r['slug'] = $oldData['slug'];
  $data = sup_web_clean($r);
  if ($data['slug'] === '' || $slugCheck($data['slug'])) $data['slug'] = sup_slugify($sup['name']) ?: ('brand-' . substr(md5($supId), 0, 6));
  if ($slugCheck($data['slug'])) $data['slug'] .= '-' . substr(md5($supId), 0, 4);
  $saveRow($data);
  jout(['ok'=>true, 'web'=>$data, 'status'=>sup_web_row($supId)['status'] ?? 'draft']);
}

if ($wa === 'sup_web_publish') {
  @set_time_limit(400);
  $data = sup_web_clean(body()['web'] ?? []);
  if ($e = $slugCheck($data['slug'])) fail($e);
  $miss = [];
  if ($data['h1'] === '') $miss[] = 'заголовок';
  if ($data['hero'] === '') $miss[] = 'главное фото';
  if ($data['about_lead'] === '') $miss[] = 'описание завода';
  $nItems = 0; $nNoPhoto = 0;
  foreach ($data['groups'] as $g) foreach ($g['items'] as $it) { $nItems++; if (!$it['photos']) $nNoPhoto++; }
  if (!$nItems) $miss[] = 'хотя бы один товар';
  if ($miss) fail('Для публикации не хватает: ' . implode(', ', $miss));
  if ($nNoPhoto) fail('У ' . $nNoPhoto . ' товар(ов) нет фото. Вырежьте фото из каталога или уберите такие товары');
  // Все тексты страницы одним списком — переводим на английский и китайский
  $texts = [];
  $add = function($v) use (&$texts) { $v = trim((string)$v); if ($v !== '' && preg_match('/\p{Cyrillic}/u', $v)) $texts[$v] = 1; };
  foreach (['crumb','menu_sub','title','descr','h1','lead','cta','hero_alt','about_title','about_lead','factory_alt',
            'models_title','models_lead','uses_title','uses_lead','adv_bottom','coop_lead'] as $k) $add($data[$k]);
  foreach (['bullets','about_bullets','coop'] as $k) foreach ($data[$k] as $v) $add($v);
  foreach ($data['groups'] as $g) { $add($g['name']); $add($g['sub']); foreach ($g['items'] as $it) { $add($it['name']); $add($it['desc']);
    foreach (($it['opt'] ?? []) as $o) { $add($o['n']); $add($o['f'] ?? ''); foreach (($o['v'] ?? []) as $v) $add($v); }
    foreach (($it['spec'] ?? []) as $r) { $add('Характеристики'); $add($r[0]); $add($r[1]); } } }
  foreach (['uses','adv'] as $k) foreach ($data[$k] as $c) { $add($c['h']); $add($c['p']); }
  foreach ($data['faq'] as $f) { $add($f['q']); $add($f['a']); }
  foreach ($data['projects'] as $p) $add($p['cap']);
  if ($data['awards']) { $add('НАГРАДЫ И СЕРТИФИКАТЫ ЗАВОДА'); $add('Сертификат завода'); }
  foreach ($data['awards'] as $a) $add($a['cap']);
  foreach (($data['stats'] ?? []) as $x) { $add($x['n']); $add($x['t']); }
  foreach (($data['blocks'] ?? []) as $b) { $add($b['title']); $add($b['lead']); foreach ($b['bullets'] as $v) $add($v); foreach ($b['items'] as $x) $add($x['cap']); }
  foreach ($data['groups'] as $g) $add($g['intro'] ?? '');
  $add($data['city']);
  // уже переведённые фразы берём из прошлой публикации — переводим только новые
  $oldRow = sup_web_row($supId); $oldTr = $oldRow ? sup_dec($oldRow['tr']) : [];
  $tr = ['en'=>[], 'zh'=>[]];
  foreach (['en'=>'английский', 'zh'=>'китайский (упрощённые иероглифы)'] as $lng => $lname) {
    $list = [];
    foreach (array_keys($texts) as $t) { if (isset($oldTr[$lng][$t])) $tr[$lng][$t] = $oldTr[$lng][$t]; else $list[] = $t; }
    foreach (array_chunk($list, 60) as $part) {
      $num = []; foreach ($part as $i => $t) $num[(string)($i+1)] = $t;
      $sys = "Переведи тексты страницы сайта на $lname язык. Это сайт импортной компании ИТК, тексты о китайском заводе-поставщике. "
           . "Переводи живо и точно, как для сайта; заглавные строки оставляй заглавными; названия брендов и моделей не переводи; «ИТК» → «ITC». "
           . "Верни JSON-объект с теми же номерами ключей и переводами.";
      $r = sup_ds_json($sys, json_encode($num, JSON_UNESCAPED_UNICODE), 8000);
      if (!is_array($r)) fail('Не удалось перевести страницу: ' . $r);
      foreach ($num as $k => $ru) if (isset($r[$k]) && is_string($r[$k]) && trim($r[$k]) !== '') $tr[$lng][$ru] = trim($r[$k]);
    }
  }
  $saveRow($data, 'live', $tr);
  $pinged = $pingSite($data['slug']);
  jout(['ok'=>true, 'web'=>$data, 'status'=>'live', 'url'=>SUP_WEB_SITE . '/' . $data['slug'], 'pinged'=>$pinged]);
}

if ($wa === 'sup_web_unpublish') {
  $r = sup_web_row($supId);
  if (!$r) jout(['ok'=>true, 'status'=>'draft']);
  db()->prepare("UPDATE itc_sup_web SET status='draft', updated_by=?, updated_at=NOW() WHERE sup_id=?")->execute([$me['who'], $supId]);
  $pingSite($r['slug']);
  jout(['ok'=>true, 'status'=>'draft']);
}

fail('Неизвестное действие', 404);
