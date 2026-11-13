<?php
// ==== ИТК · СТРАНИЦЫ ПОСТАВЩИКОВ · sup_web_lib.php · №4 (23.10.2026) ====
// №4: раздел «Мебель» (section mebel) — плитка фабрики на странице /mebel.
// Общие функции страниц поставщиков. Подключается из suppliers.php и sup_web.php.
// №3: раздел сайта (section), город и проекты на странице; файл вынесен отдельно,
//     чтобы перенос базы (sup_seed_run) мог сразу ставить страницы на сайт.
// ============================================================
//  Подключается из suppliers.php (функции оттуда уже объявлены).
//  Из карточки поставщика в Экосистеме собирается страница для сайта
//  по образцу /pecron: главное фото, о заводе, товары с фото, вопросы.
//  НА САЙТ НЕ ИДУТ: контакты фабрики, их сайты, каталоги, заметки.
//
//  Только для босса:
//    sup_web_get      черновик / опубликованная страница поставщика
//    sup_web_save     сохранить черновик
//    sup_web_draft    составить текст страницы через DeepSeek из карточки
//    sup_web_publish  перевести на английский и китайский и выложить на сайт
//    sup_web_unpublish  снять с сайта
//    sup_media_raw    отдать файл из хранилища через свой адрес (для показа PDF)
//  Для сайта (без пропуска, только опубликованное):
//    sup_web_list     список опубликованных страниц (меню, карта сайта)
//    sup_web_page     одна страница по адресу
// ============================================================

/* Адреса, которые заняты служебными страницами сайта, — их брать нельзя.
   Адреса заводов (gpsolar, kingfeels, fascar…) брать МОЖНО: страница из базы
   заменяет на сайте старую страницу с тем же адресом — так переносим их в базу. */
const SUP_WEB_RESERVED = ['onas','contacts','news','mebel','kargo-iz-kitaya','vykup-tovarov-iz-kitaya',
  'oplata-postavshchikam-v-kitay','sklad-v-guanchzhou','fulfilment-v-kitae','chestniy-znak','page126678496-html',
  'privacy','soglasie-na-obrabotku-personalnih-dannih','avtozapchasti-iz-kitaya','selhoztehnika','solnechnaya-energetika','oborudovanie-dlya-kafe','tehnika-dlya-volos',
  'cargo','services-itc','sitemap','robots','tpost','en','zh','assets','img','data','inc','lang','pages',
  'brand-refresh','send','index','home'];
/* Разделы сайта, куда может встать страница завода (плиткой на странице раздела).
   Пустой раздел — отдельной строкой в меню «Направления». */
const SUP_WEB_SECTIONS = ['' => 'Отдельной строкой в меню «Направления»',
  'solnechnaya-energetika' => 'Солнечная энергетика', 'selhoztehnika' => 'Сельхозтехника',
  'oborudovanie-dlya-kafe' => 'Оборудование для кафе и торговли',
  'tehnika-dlya-volos' => 'Техника для ухода за волосами', 'mebel' => 'Мебель'];
/* Ключ, которым Экосистема просит сайт сбросить запомненную копию страницы.
   Тот же ключ записан на сайте в inc/brands.php. */
const SUP_WEB_SITE = 'https://itcpartner.ru';
const SUP_WEB_REFRESH_KEY = 'itc-brand-7c41e9a2';

function sup_web_ensure() {
  sup_ensure();
  static $done = false; if ($done) return; $done = true;
  db()->exec("CREATE TABLE IF NOT EXISTS itc_sup_web (
    sup_id VARCHAR(40) NOT NULL PRIMARY KEY,
    slug VARCHAR(80) NOT NULL DEFAULT '',
    status VARCHAR(10) NOT NULL DEFAULT 'draft',
    data MEDIUMTEXT NULL,
    tr MEDIUMTEXT NULL,
    published_at DATETIME NULL,
    updated_by VARCHAR(190) NOT NULL DEFAULT '',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_supweb_slug (slug)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function sup_web_row($supId) {
  $q = db()->prepare('SELECT * FROM itc_sup_web WHERE sup_id=?'); $q->execute([$supId]);
  return $q->fetch() ?: null;
}

function sup_slugify($s) {
  $s = mb_strtolower(trim((string)$s));
  $map = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k',
          'л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c',
          'ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
  $s = strtr($s, $map);
  $s = preg_replace('/\(.*?\)/', '', $s);
  $s = preg_replace('/[^a-z0-9]+/', '-', $s);
  return trim(substr(trim($s, '-'), 0, 60), '-');
}

/* Привести данные страницы к порядку: только нужные поля, обрезка длины.
   Контакты, сайты и каталоги сюда попасть не могут — таких полей нет. */
/* Заказ по вариантам и упаковкам (4.10.5): у товара
   opt  — до 4 вариантов выбора [{n: название, v: [до 40 значений]}] или свободное поле [{n, f: подсказка}],
   u    — в чём считать: pcs (шт.), set (компл.), pair (пар), m (м),
   pack — упаковка [{q: сколько в коробке, u, w: [для каждого варианта null или номер значения]}]. */
const SUP_WEB_UNITS = ['pcs', 'set', 'pair', 'm'];
function sup_web_order($it, $s) {
  $out = []; $opt = []; $keep = [];
  foreach ((is_array($it['opt'] ?? null) ? $it['opt'] : []) as $oi => $o) {
    if (!is_array($o)) continue;
    $on = $s($o['n'] ?? '', 40); if ($on === '') continue;
    if (array_key_exists('f', $o) && !isset($o['v'])) { $opt[] = ['n' => $on, 'f' => $s($o['f'] ?? '', 40)]; $keep[] = $oi; }
    else {
      $vv = [];
      foreach ((is_array($o['v'] ?? null) ? $o['v'] : []) as $v) { $v = $s($v, 70); if ($v !== '' && !in_array($v, $vv, true)) $vv[] = $v; if (count($vv) >= 40) break; }
      if (count($vv) < 2) continue;
      $opt[] = ['n' => $on, 'v' => $vv]; $keep[] = $oi;
    }
    if (count($opt) >= 4) break;
  }
  $u = in_array($it['u'] ?? '', SUP_WEB_UNITS, true) ? $it['u'] : 'pcs';
  $pack = [];
  foreach ((is_array($it['pack'] ?? null) ? $it['pack'] : []) as $p) {
    $q = (int)($p['q'] ?? 0); if ($q < 1 || $q > 1000000) continue;
    $w = []; $src = is_array($p['w'] ?? null) ? $p['w'] : [];
    foreach ($keep as $j => $oi) {
      $x = $src[$oi] ?? null;
      $w[] = (is_int($x) && isset($opt[$j]['v']) && $x >= 0 && $x < count($opt[$j]['v'])) ? $x : null;
    }
    $pack[] = ['q' => $q, 'u' => in_array($p['u'] ?? '', SUP_WEB_UNITS, true) ? $p['u'] : $u, 'w' => $w];
    if (count($pack) >= 60) break;
  }
  if ($opt) $out['opt'] = $opt;
  if ($pack) $out['pack'] = $pack;
  if ($opt || $pack || $u !== 'pcs') $out['u'] = $u;
  return $out;
}

function sup_web_clean($d) {
  $s = function($v, $n = 600) { return sup_str(is_string($v) ? $v : '', $n); };
  $lines = function($v, $n = 400, $max = 20) use ($s) {
    $o = []; foreach ((is_array($v) ? $v : []) as $x) { $x = $s($x, $n); if ($x !== '') $o[] = $x; if (count($o) >= $max) break; } return $o;
  };
  $url = function($v) { $v = trim((string)$v); return preg_match('~^https?://~', $v) ? mb_substr($v, 0, 600) : ''; };
  $out = [
    'slug' => sup_slugify($d['slug'] ?? ''),
    'brand' => $s($d['brand'] ?? '', 80),
    'crumb' => $s($d['crumb'] ?? '', 120),
    'menu_sub' => $s($d['menu_sub'] ?? '', 120),
    'title' => $s($d['title'] ?? '', 250),
    'descr' => $s($d['descr'] ?? '', 400),
    'h1' => $s($d['h1'] ?? '', 160),
    'lead' => $s($d['lead'] ?? '', 500),
    'bullets' => $lines($d['bullets'] ?? [], 200, 6),
    'cta' => $s($d['cta'] ?? '', 80),
    'hero' => $url($d['hero'] ?? ''),
    'hero_alt' => $s($d['hero_alt'] ?? '', 160),
    'about_title' => $s($d['about_title'] ?? '', 120),
    'about_lead' => $s($d['about_lead'] ?? '', 700),
    'about_bullets' => $lines($d['about_bullets'] ?? [], 250, 8),
    'factory' => $url($d['factory'] ?? ''),
    'factory_alt' => $s($d['factory_alt'] ?? '', 160),
    'models_title' => $s($d['models_title'] ?? '', 120),
    'models_lead' => $s($d['models_lead'] ?? '', 300),
    'groups' => [],
    'uses_title' => $s($d['uses_title'] ?? '', 120),
    'uses_lead' => $s($d['uses_lead'] ?? '', 300),
    'uses' => [],
    'adv' => [],
    'adv_bottom' => $s($d['adv_bottom'] ?? '', 160),
    'coop_lead' => $s($d['coop_lead'] ?? '', 200),
    'coop' => $lines($d['coop'] ?? [], 200, 6),
    'faq' => [],
    'section' => isset(SUP_WEB_SECTIONS[$d['section'] ?? '']) ? (string)$d['section'] : '',
    'city' => $s($d['city'] ?? '', 80),
    'projects' => [],
    'awards' => [],
    'stats' => [],
    'blocks' => [],
  ];
  // цифры фабрики (4.13.0): «2007» — «год основания»
  foreach ((is_array($d['stats'] ?? null) ? $d['stats'] : []) as $x) {
    $n = $s($x['n'] ?? '', 24); $t = $s($x['t'] ?? '', 80);
    if ($n !== '' && $t !== '') $out['stats'][] = ['n' => $n, 't' => $t];
    if (count($out['stats']) >= 8) break;
  }
  // блоки о фабрике (4.13.0): производство, технологии, сертификаты, упаковка, работа под заказ
  foreach ((is_array($d['blocks'] ?? null) ? $d['blocks'] : []) as $b) {
    $it = [];
    foreach ((is_array($b['items'] ?? null) ? $b['items'] : []) as $x) { $ph = $url($x['photo'] ?? ''); if (!$ph) continue; $it[] = ['photo' => $ph, 'cap' => $s($x['cap'] ?? '', 200)]; if (count($it) >= 16) break; }
    $row = ['kind' => $s($b['kind'] ?? '', 20), 'title' => $s($b['title'] ?? '', 120), 'lead' => $s($b['lead'] ?? '', 700), 'bullets' => $lines($b['bullets'] ?? [], 300, 10), 'items' => $it];
    if ($row['title'] === '' && $row['lead'] === '' && !$it && !$row['bullets']) continue;
    $out['blocks'][] = $row;
    if (count($out['blocks']) >= 10) break;
  }
  foreach ((is_array($d['projects'] ?? null) ? $d['projects'] : []) as $p) {
    $ph = $url($p['photo'] ?? ''); if (!$ph) continue;
    $out['projects'][] = ['photo' => $ph, 'cap' => $s($p['cap'] ?? '', 160)];
    if (count($out['projects']) >= 8) break;
  }
  // награды и сертификаты завода (4.10.7): фото грамоты/сертификата и подпись; без фото — только подпись
  foreach ((is_array($d['awards'] ?? null) ? $d['awards'] : []) as $a) {
    $ph = $url($a['photo'] ?? ''); $cap = $s($a['cap'] ?? '', 200);
    if ($ph === '' && $cap === '') continue;
    $out['awards'][] = ['photo' => $ph, 'cap' => $cap];
    if (count($out['awards']) >= 24) break;
  }
  foreach ((is_array($d['groups'] ?? null) ? $d['groups'] : []) as $g) {
    $items = [];
    foreach ((is_array($g['items'] ?? null) ? $g['items'] : []) as $it) {
      $ph = []; foreach ((is_array($it['photos'] ?? null) ? $it['photos'] : []) as $p) { $p = $url($p); if ($p) $ph[] = $p; if (count($ph) >= 12) break; }
      $nm = $s($it['name'] ?? '', 120); if ($nm === '' && !$ph) continue;
      $row = ['name' => $nm, 'desc' => $s($it['desc'] ?? '', 400), 'photos' => $ph];
      $ord = sup_web_order($it, $s);
      if ($ord) $row += $ord;
      // таблица «Характеристики» (4.10.7): пары «поле — значение»
      $sp = [];
      foreach ((is_array($it['spec'] ?? null) ? $it['spec'] : []) as $r) {
        if (!is_array($r)) continue;
        $k = $s($r[0] ?? '', 80); $v = $s($r[1] ?? '', 300);
        if ($k !== '' && $v !== '') $sp[] = [$k, $v];
        if (count($sp) >= 30) break;
      }
      if ($sp) $row['spec'] = $sp;
      $items[] = $row;
      if (count($items) >= 250) break; // большие каталоги: каждая модель отдельно (4.10.4)
    }
    $gn = $s($g['name'] ?? '', 120);
    if ($gn === '' && !$items) continue;
    $grow = ['name' => $gn, 'sub' => $s($g['sub'] ?? '', 250), 'items' => $items];
    // обложка и вступление раздела каталога (4.13.0)
    $cv = $url($g['cover'] ?? ''); if ($cv) $grow['cover'] = $cv;
    $gi = $s($g['intro'] ?? '', 600); if ($gi !== '') $grow['intro'] = $gi;
    $out['groups'][] = $grow;
    if (count($out['groups']) >= 80) break;
  }
  foreach ((is_array($d['uses'] ?? null) ? $d['uses'] : []) as $u) {
    $h = $s($u['h'] ?? '', 80); $p = $s($u['p'] ?? '', 250);
    if ($h !== '' || $p !== '') $out['uses'][] = ['h' => $h, 'p' => $p];
    if (count($out['uses']) >= 6) break;
  }
  foreach ((is_array($d['adv'] ?? null) ? $d['adv'] : []) as $a) {
    $h = $s($a['h'] ?? '', 40); $p = $s($a['p'] ?? '', 160);
    if ($h !== '' || $p !== '') $out['adv'][] = ['h' => $h, 'p' => $p];
    if (count($out['adv']) >= 6) break;
  }
  foreach ((is_array($d['faq'] ?? null) ? $d['faq'] : []) as $f) {
    $q = $s($f['q'] ?? '', 200); $a = $s($f['a'] ?? '', 900);
    if ($q !== '' && $a !== '') $out['faq'][] = ['q' => $q, 'a' => $a];
    if (count($out['faq']) >= 10) break;
  }
  return $out;
}

/* Вызов DeepSeek: вернуть разобранный JSON или текст ошибки. */
function sup_ds_json($system, $user, $maxTokens = 4000) {
  if (!defined('DEEPSEEK_API_KEY') || !DEEPSEEK_API_KEY) return 'нет ключа DeepSeek';
  $payload = json_encode([
    'model' => 'deepseek-v4-flash',
    'messages' => [['role'=>'system','content'=>$system], ['role'=>'user','content'=>$user]],
    'response_format' => ['type'=>'json_object'],
    'temperature' => 0.4, 'max_tokens' => $maxTokens, 'stream' => false,
  ], JSON_UNESCAPED_UNICODE);
  $ch = curl_init('https://api.deepseek.com/chat/completions');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . DEEPSEEK_API_KEY],
    CURLOPT_POSTFIELDS => $payload, CURLOPT_TIMEOUT => 240, CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
  ]);
  $raw = curl_exec($ch); $http = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
  if ($raw === false || $http !== 200) return 'DeepSeek не ответил (' . ($http ?: $err) . ')';
  $resp = json_decode($raw, true);
  $c = trim((string)($resp['choices'][0]['message']['content'] ?? ''));
  $c = trim(preg_replace('/^```(?:json)?|```$/m', '', $c));
  $j = json_decode($c, true);
  return is_array($j) ? $j : 'DeepSeek вернул неразборчивый ответ';
}

/* Пример стиля — страница Pecron. Передаём модели как образец тона. */
function sup_web_sample() {
  return "Образец тона (страница Pecron):\n"
   . "h1: ЭЛЕКТРОСТАНЦИИ PECRON НАПРЯМУЮ С ЗАВОДА\n"
   . "lead: Портативные электростанции, батареи расширения и солнечные панели Pecron под заказ. Покупаем напрямую у завода в Китае — без посредников и лишних наценок.\n"
   . "bullets: Прямая работа с заводом Pecron, личный контакт с производством. / Серии E и F на надёжных батареях LiFePO4 — 3500+ циклов заряда. / Оплата в рублях, официальный ввоз, полный пакет документов. / Гарантия завода 24 месяца.\n"
   . "about_lead: Pecron (百克龙) — китайский производитель портативных электростанций и систем накопления энергии. Компания основана в 2012 году, головной офис находится в Шэньчжэне.\n"
   . "about_bullets: Собственный завод площадью 10 000 м² и мощностью более 100 000 станций в год. / Сертификат ISO 9001. / Продажи в США, Канаде, Европе и Японии.\n"
   . "Группа товаров: «Электростанции серии F» — «Новое поколение: быстрая зарядка, сенсорные экраны, большая мощность.» Товар: «F1000LFP» — «Портативная станция 1500 Вт, 960 Втч, полная зарядка за 1 час.»\n"
   . "faq: «Вы покупаете Pecron напрямую у завода?» — «Да. Мы работаем с заводом Pecron в Шэньчжэне напрямую, без посредников и перекупщиков.» / «Сколько стоят электростанции?» — «Цена зависит от модели, количества и способа доставки. Пришлите список моделей и количество — подготовим коммерческое предложение с доставкой до вашего города.»";
}

