<?php
// ==== ИТК · ОБНОВЛЕНИЯ ОДНИМ ФАЙЛОМ · upd.php · №9 (11.11.2026) · копия Гонконга ещё и в Гуанчжоу · №8: часть «для Гонконга» (hk/…): почта для сервера в Гонконге ====
// ==== №7 (05.11.2026) · + «Файлы для Claude» забирают и сайт, и правки Фила ====
// Босс загружает в Экосистеме один файл obnovlenie_N.zip — сервер сам раскладывает его:
//   site/...   → папка сайта itcpartner.ru
//   store/...  → папка магазина itcpartner.store (Экосистема, api)
//   metro/...  → папка приложения «Мой Гуанчжоу» (metro.itcpartner.store)
//   seed/...   → /eco/sup_seed (новые фабрики; перенос в базу запускает страница upd.html)
//   hk/...     → «почта» для сервера в Гонконге: uploads/upd/hk/N.zip. Помощник сервера сам забирает её раз в минуту
//               (hk_list, hk_zip — по его ключу X-HK-Token), проверяет подпись Claude и ставит у себя; hk_report — его отчёт,
//               hk_bk_put — ночная копия данных Гонконга (храним 7 последних).
// Внутри архива: manifest.json (номер, что заменить, контрольные суммы) и sig.txt (подпись).
// Подпись проверяется ключом из upd_key.php — чужой или испорченный файл не принимается.
// Перед заменой старые файлы сохраняются; «Вернуть как было» восстанавливает последнее обновление.
//
//  Вызовы (только босс):  /api/upd.php?action=...&t=<пропуск>
//    upd_status   номер установленного обновления, найден ли сайт, история, каталоги к загрузке
//    upd_chunk    кусок загружаемого файла (по 2 МБ, base64)
//    upd_check    проверить собранный архив: подпись, номер, состав
//    upd_apply    установить (файлы сайта и магазина, папка начальной базы)
//    upd_rollback вернуть файлы последнего обновления как было
//    upd_site     запомнить путь к папке сайта, если он не нашёлся сам
//    upd_cat_done отметить каталог загруженным (сам файл грузится кнопкой карточки — suppliers.php)
//    ya_token     сохранить или удалить ключ Яндекса (токен хранится только на сервере, в uploads/upd/ya.json)
//    ya_stats     цифры Вебмастера (страницы в поиске, ошибки, ИКС) и Метрики (посетители, источники)
//    ya_recrawl   отправить свежие страницы сайта на переобход (по карте сайта, в пределах дневной нормы)
//    upd_pull     скачать архив с текущими файлами магазина и «Моего Гуанчжоу» — для Claude,
//                 чтобы он правил то, что стоит на сервере (без config.php, ключей и данных)
// ============================================================

require_once __DIR__ . '/config.php';
@date_default_timezone_set('Europe/Moscow');   // время в истории — московское
@include __DIR__ . '/upd_key.php';           // define('UPD_KEY', '...') — ключ подписи
@include __DIR__ . '/hk_auth.php';           // №8 · define('HK_TOKEN_SHA', '...') — отпечаток ключа помощника в Гонконге (сам ключ — только на том сервере)

$action = $_GET['action'] ?? '';
define('UPD_MAX', 400 * 1024 * 1024);         // архив — до 400 МБ

function upd_me() {
  $s = authUser('itc');
  $r = $s['staff_role'] ?? '';
  if (!($r === 'boss' || $r === '')) fail('Обновления ставит только босс', 403);
  $who = trim((string)($s['name'] ?? '')) ?: trim((string)($s['phone'] ?? '')) ?: 'босс';
  return $who;
}
function upd_store() { return dirname(__DIR__); }
function upd_dir() {
  $d = upd_store() . '/uploads/upd';
  if (!is_dir($d)) @mkdir($d, 0755, true);
  if (!file_exists($d . '/.htaccess')) @file_put_contents($d . '/.htaccess', "Require all denied\nDeny from all\n");
  return $d;
}
function upd_state() {
  $f = upd_dir() . '/state.json';
  $s = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
  if (!is_array($s)) $s = ['n' => 0, 'hist' => [], 'site' => '', 'cats' => []];
  $s += ['n' => 0, 'hist' => [], 'site' => '', 'cats' => []];
  return $s;
}
function upd_save($s) { file_put_contents(upd_dir() . '/state.json', json_encode($s, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX); }

/* Папка сайта itcpartner.ru: сохранённый путь, иначе рядом с папкой магазина */
function upd_site_root($s = null) {
  $s = $s ?: upd_state();
  $c = [];
  if (!empty($s['site'])) $c[] = $s['site'];
  if (defined('UPD_SITE_ROOT')) $c[] = UPD_SITE_ROOT;
  $up = dirname(upd_store());
  foreach (['itcpartner.ru', 'www.itcpartner.ru', 'itcpartner.ru/public_html', 'itcpartner.ru/www'] as $n) $c[] = $up . '/' . $n;
  foreach ($c as $p) { $p = rtrim($p, '/'); if (is_file($p . '/inc/core.php')) return $p; }
  return '';
}

/* Папка «Мой Гуанчжоу» (поддомен metro.itcpartner.store) — рядом с папкой магазина */
function upd_metro_root($s = null) {
  $s = $s ?: upd_state();
  $c = [];
  if (!empty($s['metro'])) $c[] = $s['metro'];
  if (defined('UPD_METRO_ROOT')) $c[] = UPD_METRO_ROOT;
  $up = dirname(upd_store());
  foreach (['metro.itcpartner.store', 'itcpartner.store/metro', 'metro'] as $n) $c[] = $up . '/' . $n;
  foreach ($c as $p) { $p = rtrim($p, '/'); if (is_file($p . '/index.html') && $p !== upd_store()) return $p; }
  return '';
}

/* Путь внутри архива: только буквы, цифры, точка, дефис, подчёркивание и «/», без «..» */
function upd_safe($p) {
  if (!preg_match('~^[A-Za-z0-9._\-/]+$~', $p) || strpos($p, '..') !== false || $p[0] === '/') return false;
  $b = basename($p);
  if (in_array($b, ['config.php', 'upd_key.php', '.htaccess', 'state.json', 'ya.json'], true)) return false;   // ключи и настройки не трогаем
  return true;
}

function upd_zip_path() { return upd_dir() . '/incoming.zip'; }

/* №6 · запасной помощник DeepSeek: свой ключ подписи (создаёт босс кнопкой, хранится только тут).
   Его обновления помечаются «сделано DeepSeek», не могут менять раздел обновлений и базу фабрик. */
function upd_ds_file() { return upd_dir() . '/ds_key.json'; }
function upd_ds() { $j = is_file(upd_ds_file()) ? json_decode((string)file_get_contents(upd_ds_file()), true) : null; return is_array($j) && strlen((string)($j['k'] ?? '')) >= 32 ? $j : null; }
function upd_ds_deny($p) { return (bool)preg_match('~^(seed/|store/api/upd\.php$|store/eco/upd\.html$|store/api/upd_|.*\.(pem|key)$)~', $p); }

/* Открыть архив и проверить подпись и состав. Возвращает [zip, manifest] */
function upd_open() {
  if (!defined('UPD_KEY') || strlen(UPD_KEY) < 32) fail('На сервере нет ключа обновлений (api/upd_key.php)');
  if (!class_exists('ZipArchive')) fail('На хостинге нет распаковки архивов (ZipArchive) — напишите Claude');
  $zp = upd_zip_path();
  if (!is_file($zp)) fail('Файл обновления не загружен');
  $z = new ZipArchive();
  if ($z->open($zp) !== true) fail('Архив повреждён — скачайте файл ещё раз');
  $mj = $z->getFromName('manifest.json'); $sig = trim((string)$z->getFromName('sig.txt'));
  if ($mj === false || $sig === '') fail('Это не файл обновления ИТК (нет описи или подписи)');
  $by = 'claude';
  if (!hash_equals(hash_hmac('sha256', $mj, UPD_KEY), $sig)) {
    $ds = upd_ds();
    if (!$ds || !hash_equals(hash_hmac('sha256', $mj, $ds['k']), $sig)) fail('Подпись не сходится — файл чужой или испорчен');
    $by = 'deepseek';
  }
  $m = json_decode($mj, true);
  if (!is_array($m) || !isset($m['n'], $m['files'])) fail('Опись обновления повреждена');
  if ($by === 'deepseek') foreach ($m['files'] as $f) if (upd_ds_deny((string)($f['p'] ?? ''))) fail('Помощнику DeepSeek нельзя менять ' . $f['p'] . ' — это делает только Claude');
  $m['_by'] = $by;
  return [$z, $m];
}

/* №261 · установлено ли обновление N (с учётом «Вернуть как было») */
function upd_has(array $s, int $n): bool {
  $c = 0; foreach ($s['hist'] as $h) if ((int)($h['n'] ?? 0) === $n) $c += !empty($h['back']) ? -1 : 1;
  return $c > 0;
}
/* №261 · обновление только с фабриками (без файлов сайта и приложений) можно ставить не по порядку номеров */
function upd_seed_only(array $m): bool {
  foreach ($m['files'] as $f) if (strpos((string)$f['p'], 'seed/') !== 0) return false;
  return true;
}

/* ---------- №8 · сервер в Гонконге ---------- */
function hk_dir() { $d = upd_dir() . '/hk'; if (!is_dir($d)) @mkdir($d, 0755, true); return $d; }
function hk_auth() {
  $t = (string)($_SERVER['HTTP_X_HK_TOKEN'] ?? '');
  if (!defined('HK_TOKEN_SHA') || strlen($t) < 32 || !hash_equals(HK_TOKEN_SHA, hash('sha256', $t))) { http_response_code(403); header('Content-Type: application/json'); echo json_encode(['ok' => false, 'error' => 'нет доступа']); exit; }
}
function hk_state() { $f = hk_dir() . '/state.json'; $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null; return is_array($j) ? $j : []; }
/* после установки обновления: файлы hk/… собрать в один архив N.zip для помощника */
function hk_pack(int $n, string $in) {
  if (!is_dir($in)) return 0;
  $z = new ZipArchive(); $zp = hk_dir() . '/' . $n . '.zip'; @unlink($zp);
  if ($z->open($zp, ZipArchive::CREATE) !== true) fail('Не удалось собрать часть для Гонконга');
  $c = 0;
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($in, FilesystemIterator::SKIP_DOTS));
  foreach ($it as $f) { $z->addFile($f->getPathname(), 'hk/' . substr($f->getPathname(), strlen($in) + 1)); $c++; }
  $z->close(); upd_rm($in);
  foreach (glob(hk_dir() . '/*.zip') as $o) if ((int)basename($o, '.zip') < $n - 30) @unlink($o);   // храним последние
  return $c;
}

try {
  switch ($action) {

  case 'upd_status': {
    upd_me(); $s = upd_state(); $site = upd_site_root($s);
    $cats = [];
    foreach ($s['cats'] as $c) {
      $done = !empty($c['done']);
      if (!$done) {   // уже есть в карточке файл того же размера — значит загружен
        try { $q = db()->prepare('SELECT COUNT(*) FROM itc_sup_media WHERE sup_id=? AND bytes=?'); $q->execute([$c['sup'], (int)$c['bytes']]); $done = $q->fetchColumn() > 0; }
        catch (Throwable $e) {}
      }
      $c['done'] = $done; $cats[] = $c;
    }
    $seedLeft = is_file(upd_store() . '/eco/sup_seed/seed.json');
    $metro = upd_metro_root($s);
    jout(['ok' => true, 'n' => (int)$s['n'], 'hist' => array_slice(array_reverse($s['hist']), 0, 15),
          'metro' => $metro, 'metro_ok' => $metro !== '' && is_writable($metro),
          'site' => $site, 'site_ok' => $site !== '' && is_writable($site . '/inc'),
          'store_ok' => is_writable(upd_store() . '/eco'), 'zip' => class_exists('ZipArchive'),
          'key' => defined('UPD_KEY'), 'ya' => ya_tok() !== '', 'cats' => $cats, 'seed_left' => $seedLeft,
          'ds' => ($dsk = upd_ds()) ? ['at' => (string)($dsk['at'] ?? ''), 'who' => (string)($dsk['who'] ?? '')] : null,
          'free_mb' => (int)(@disk_free_space(upd_store()) / 1048576), 'hk' => hk_state() ?: null]);
  }

  case 'upd_chunk': {
    upd_me(); $d = body();
    $idx = (int)($d['idx'] ?? -1);
    $bin = base64_decode((string)($d['data'] ?? ''), true);
    if ($bin === false || $bin === '') fail('Кусок файла пустой');
    $zp = upd_zip_path();
    if ($idx === 0) @unlink($zp);
    /* №261 · повтор куска после обрыва связи: сервер обрезает файл до нужного места, кусок не задвоится */
    $off = isset($d['off']) ? (int)$d['off'] : -1;
    if ($idx > 0 && $off >= 0) {
      clearstatcache(); $have = is_file($zp) ? filesize($zp) : 0;
      if ($have < $off) fail('Пропал кусок файла — загрузка начнётся заново');
      if ($have > $off) { $h = @fopen($zp, 'r+'); if ($h) { ftruncate($h, $off); fclose($h); } clearstatcache(); }
    }
    $have = is_file($zp) ? filesize($zp) : 0;
    if ($have + strlen($bin) > UPD_MAX) { @unlink($zp); fail('Файл больше 400 МБ'); }
    if (@file_put_contents($zp, $bin, FILE_APPEND | LOCK_EX) === false) fail('Сервер не смог записать кусок — возможно, кончилось место на хостинге');
    jout(['ok' => true, 'have' => filesize($zp)]);
  }

  case 'upd_check': {
    upd_me(); $d = body();
    if (isset($d['size']) && (int)$d['size'] !== @filesize(upd_zip_path())) fail('Файл дошёл не полностью — загрузите ещё раз');
    [$z, $m] = upd_open(); $s = upd_state();
    $n = (int)$m['n']; $need = (int)($m['needs'] ?? 0);
    $site = upd_site_root($s);
    $cnt = ['site' => 0, 'store' => 0, 'metro' => 0, 'seed' => 0, 'hk' => 0];
    foreach ($m['files'] as $f) { $k = explode('/', $f['p'], 2)[0]; if (isset($cnt[$k])) $cnt[$k]++; }
    $z->close();
    $err = '';
    $late = $n <= (int)$s['n'] && upd_seed_only($m) && !upd_has($s, $n);
    if ($n <= (int)$s['n'] && !$late) $err = 'Обновление №' . $n . ' уже установлено (сейчас стоит №' . $s['n'] . ')';
    elseif ($need > (int)$s['n']) $err = 'Сначала установите обновление №' . $need . ' (сейчас стоит №' . $s['n'] . ')';
    elseif ($cnt['site'] && $site === '') $err = 'Не нашлась папка сайта itcpartner.ru — укажите путь ниже';
    elseif ($cnt['metro'] && upd_metro_root($s) === '') $err = 'Не нашлась папка «Мой Гуанчжоу» (metro.itcpartner.store) — пришлите Claude снимок папок хостинга';
    jout(['ok' => true, 'n' => $n, 'title' => (string)($m['title'] ?? ''), 'what' => $m['what'] ?? [],
          'cnt' => $cnt, 'cats' => $m['cats'] ?? [], 'err' => $err, 'by' => $m['_by'], 'have' => upd_has($s, $n) || ($n <= (int)$s['n'] && !upd_seed_only($m)),
          'files' => $m['_by'] === 'deepseek' ? array_map(function ($f) { return (string)$f['p']; }, $m['files']) : []]);
  }

  case 'upd_apply': {
    $who = upd_me(); @set_time_limit(900); @ignore_user_abort(true);
    [$z, $m] = upd_open(); $s = upd_state();
    $n = (int)$m['n']; $need = (int)($m['needs'] ?? 0);
    if ($n <= (int)$s['n'] && (!upd_seed_only($m) || upd_has($s, $n))) fail('Обновление №' . $n . ' уже установлено');
    if ($need > (int)$s['n']) fail('Сначала установите обновление №' . $need);
    $roots = ['store' => upd_store(), 'site' => upd_site_root($s), 'metro' => upd_metro_root($s), 'seed' => upd_store() . '/eco', 'hk' => upd_dir() . '/hk_in/' . $n];
    $hasSeed = false;
    foreach ($m['files'] as $f) if (strpos($f['p'], 'seed/') === 0) $hasSeed = true;
    if ($hasSeed && is_file(upd_store() . '/eco/sup_seed/seed.json')) fail('В папке ещё лежит прошлый перенос базы — сначала завершите перенос (кнопка на этой странице)');
    // 1) проверить все файлы до записи
    $plan = [];
    foreach ($m['files'] as $f) {
      $p = (string)$f['p']; [$k, $rel] = array_pad(explode('/', $p, 2), 2, '');
      if (!isset($roots[$k]) || $rel === '' || !upd_safe($rel)) fail('Недопустимый путь в архиве: ' . $p);
      if ($roots[$k] === '') fail($k === 'metro' ? 'Не нашлась папка «Мой Гуанчжоу»' : 'Не нашлась папка сайта itcpartner.ru');
      $data = $z->getFromName($p);
      if ($data === false) fail('В архиве нет файла ' . $p);
      if (!hash_equals((string)$f['h'], hash('sha256', $data))) fail('Файл ' . $p . ' испорчен — скачайте обновление ещё раз');
      $plan[] = [$k, $rel, $roots[$k] . '/' . $rel];
      unset($data);
    }
    // 2) сохранить старые файлы и записать новые
    $bk = upd_dir() . '/backup/' . $n; @mkdir($bk, 0755, true);
    $done = []; $newf = []; $nSeed = 0;
    foreach ($plan as [$k, $rel, $dst]) {
      if ($k === 'hk') { /* почта Гонконга — без копий */ }
      elseif ($k !== 'seed' && is_file($dst)) {
        $b = $bk . '/' . $k . '/' . $rel; @mkdir(dirname($b), 0755, true);
        if (!@copy($dst, $b)) fail('Не удалось сохранить копию ' . $k . '/' . $rel . ' — ничего не заменено после этого файла');
      } elseif ($k !== 'seed') $newf[] = $k . '/' . $rel;
      if ($k === 'hk') { $nHk = ($nHk ?? 0) + 1; }
      @mkdir(dirname($dst), 0755, true);
      $tmp = $dst . '.upd_tmp';
      if (@file_put_contents($tmp, $z->getFromName($k . '/' . $rel), LOCK_EX) === false || !@rename($tmp, $dst)) {
        @unlink($tmp);
        fail('Не удалось записать ' . $k . '/' . $rel . ' (заменено файлов до этого: ' . count($done) . '). Нажмите «Вернуть как было» и напишите Claude');
      }
      $done[] = $k . '/' . $rel; if ($k === 'seed') $nSeed++;
    }
    $z->close(); @unlink(upd_zip_path());
    $newf = array_values(array_filter($newf, function ($p) { return strpos($p, 'hk/') !== 0; }));
    $nHk = hk_pack($n, upd_dir() . '/hk_in/' . $n);
    file_put_contents($bk . '/new.json', json_encode($newf));
    // 3) каталоги, которые нужно загрузить в карточки фабрик
    foreach (($m['cats'] ?? []) as $c) {
      $key = ($c['sup'] ?? '') . '|' . (int)($c['bytes'] ?? 0);
      $have = false; foreach ($s['cats'] as $o) if (($o['sup'] . '|' . (int)$o['bytes']) === $key) $have = true;
      if (!$have && !empty($c['sup'])) $s['cats'][] = ['sup' => $c['sup'], 'name' => (string)($c['name'] ?? ''), 'title' => (string)($c['title'] ?? ''), 'bytes' => (int)($c['bytes'] ?? 0), 'done' => false];
    }
    $s['n'] = max((int)$s['n'], $n);
    $s['hist'][] = ['n' => $n, 'title' => (string)($m['title'] ?? ''), 'at' => date('d.m.Y H:i'), 'who' => $who,
                    'files' => count($done) - $nSeed - $nHk, 'seed' => $hasSeed, 'seed_files' => $nSeed, 'hk' => $nHk, 'by' => $m['_by']];
    upd_save($s);
    // старые копии — храним последние 5 обновлений
    foreach (glob(upd_dir() . '/backup/*', GLOB_ONLYDIR) as $d) if ((int)basename($d) < $n - 4) upd_rm($d);
    jout(['ok' => true, 'n' => $n, 'files' => count($done) - $nSeed - $nHk, 'seed' => $hasSeed, 'seed_files' => $nSeed, 'hk' => $nHk]);
  }

  case 'upd_rollback': {
    upd_me(); $s = upd_state(); $n = (int)$s['n'];
    $bk = upd_dir() . '/backup/' . $n;
    if ($n < 1 || !is_dir($bk)) fail('Нечего возвращать');
    $roots = ['store' => upd_store(), 'site' => upd_site_root($s), 'metro' => upd_metro_root($s)];
    $back = 0;
    foreach (['store', 'site', 'metro'] as $k) {
      if (!is_dir($bk . '/' . $k) || $roots[$k] === '') continue;
      $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($bk . '/' . $k, FilesystemIterator::SKIP_DOTS));
      foreach ($it as $f) {
        $rel = substr($f->getPathname(), strlen($bk . '/' . $k) + 1);
        if (@copy($f->getPathname(), $roots[$k] . '/' . $rel)) $back++;
      }
    }
    $new = json_decode((string)@file_get_contents($bk . '/new.json'), true) ?: [];
    foreach ($new as $p) { [$k, $rel] = explode('/', $p, 2); if (!empty($roots[$k])) @unlink($roots[$k] . '/' . $rel); }
    upd_rm($bk);
    $prev = 0; foreach ($s['hist'] as $h) if ((int)$h['n'] < $n) $prev = max($prev, (int)$h['n']);
    $s['n'] = $prev;
    $s['hist'][] = ['n' => $n, 'title' => 'Возврат: обновление №' . $n . ' снято', 'at' => date('d.m.Y H:i'), 'who' => upd_me(), 'files' => $back, 'seed' => false, 'back' => true];
    upd_save($s);
    jout(['ok' => true, 'n' => $prev, 'files' => $back, 'removed' => count($new)]);
  }

  case 'upd_site': {
    upd_me(); $d = body(); $p = rtrim(trim((string)($d['path'] ?? '')), '/');
    if ($p === '' || !is_file($p . '/inc/core.php')) fail('В этой папке нет сайта itcpartner.ru (не найден inc/core.php)');
    $s = upd_state(); $s['site'] = $p; upd_save($s);
    jout(['ok' => true, 'site' => $p]);
  }

  case 'upd_indexnow': {
    // №4 · после обновления сообщаем Яндексу о новых и изменённых страницах сайта (IndexNow, сайт 3.49.0)
    upd_me();
    $ch = curl_init('https://itcpartner.ru/indexnow-ping');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_FOLLOWLOCATION => true]);
    $r = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $j = json_decode($r, true);
    if (!is_array($j)) jout(['ok' => false, 'error' => 'Сайт не ответил (' . $code . ')']);
    jout($j);
  }

  /* ---------- №5 · Яндекс: Вебмастер и Метрика ---------- */
  case 'ya_token': {
    upd_me(); $d = body(); $t = trim((string)($d['token'] ?? ''));
    if ($t === '') { ya_save(['token' => '']); jout(['ok' => true, 'removed' => true]); }
    if (!preg_match('~^[A-Za-z0-9_\-]{20,200}$~', $t)) fail('Это не похоже на ключ Яндекса — скопируйте его целиком, без пробелов');
    [$c, $j] = ya_call('https://api.webmaster.yandex.net/v4/user', $t);
    if ($c === 401) fail('Яндекс не принял ключ — возможно, он отозван. Получите новый по ссылке из инструкции');
    if ($c === 0) fail('Сервер не смог связаться с Яндексом — попробуйте позже');
    $uid = (int)($j['user_id'] ?? 0);
    if (!$uid) fail('Ключ подошёл, но Вебмастер не ответил (' . $c . '). Проверьте, что при создании приложения выбран доступ к Вебмастеру');
    ya_save(['token' => $t, 'uid' => $uid, 'at' => date('d.m.Y H:i')]);
    jout(['ok' => true]);
  }

  case 'ya_stats': {
    upd_me(); $y = ya_cfg(); $t = ya_tok();
    if ($t === '') fail('Ключ Яндекса ещё не сохранён');
    $fresh = !empty($_GET['fresh']);
    if (!$fresh && !empty($y['cache']) && (int)($y['cache']['v'] ?? 0) === 2 && time() - (int)($y['cache_t'] ?? 0) < 600) jout($y['cache'] + ['cached' => true]);
    $out = ['ok' => true, 'v' => 2, 'wm' => [], 'mk' => [], 'errs' => [], 'at' => date('d.m.Y H:i')];
    // Вебмастер
    $uid = (int)($y['uid'] ?? 0);
    if (!$uid) { [$c, $j] = ya_call('https://api.webmaster.yandex.net/v4/user', $t); $uid = (int)($j['user_id'] ?? 0); if ($uid) { $y['uid'] = $uid; ya_save($y); } }
    if (!$uid) $out['errs'][] = 'Вебмастер не ответил';
    else {
      $base = 'https://api.webmaster.yandex.net/v4/user/' . $uid . '/hosts';
      [$c, $j] = ya_call($base, $t);
      if ($c === 401) fail('Яндекс больше не принимает ключ — получите новый и сохраните его заново');
      $hosts = [];
      foreach ((array)($j['hosts'] ?? []) as $h) if (!empty($h['verified']) && stripos((string)($h['ascii_host_url'] ?? ''), 'itcpartner') !== false) $hosts[] = $h;
      // зеркало http:// не показываем, если тот же сайт подтверждён и на https:// (у зеркала всегда 0 страниц и нет карты сайта)
      $https = []; foreach ($hosts as $h) if (stripos((string)$h['ascii_host_url'], 'https://') === 0) $https[strtolower(preg_replace('~^https?://(www\.)?~i', '', rtrim((string)$h['ascii_host_url'], '/')))] = 1;
      $hosts = array_values(array_filter($hosts, function ($h) use ($https) {
        $u = (string)$h['ascii_host_url'];
        return stripos($u, 'http://') !== 0 || empty($https[strtolower(preg_replace('~^https?://(www\.)?~i', '', rtrim($u, '/')))]);
      }));
      if (!$hosts) $out['errs'][] = 'В Вебмастере нет подтверждённых сайтов itcpartner';
      foreach (array_slice($hosts, 0, 4) as $h) {
        $hid = (string)$h['host_id']; $hu = $base . '/' . rawurlencode($hid);
        [$c1, $s1] = ya_call($hu . '/summary', $t);
        [$c2, $q] = ya_call($hu . '/recrawl/quota', $t);
        $pr = (array)($s1['site_problems'] ?? []);
        $out['wm'][] = ['host' => $hid, 'url' => rtrim((string)($h['unicode_host_url'] ?? $h['ascii_host_url'] ?? ''), '/'),
          'in' => isset($s1['searchable_pages_count']) ? (int)$s1['searchable_pages_count'] : null,
          'out' => isset($s1['excluded_pages_count']) ? (int)$s1['excluded_pages_count'] : null,
          'sqi' => isset($s1['sqi']) ? (int)$s1['sqi'] : null,
          'fatal' => (int)($pr['FATAL'] ?? 0), 'crit' => (int)($pr['CRITICAL'] ?? 0), 'poss' => (int)($pr['POSSIBLE_PROBLEM'] ?? 0), 'rec' => (int)($pr['RECOMMENDATION'] ?? 0),
          'q' => isset($q['daily_quota']) ? (int)$q['daily_quota'] : null, 'ql' => isset($q['quota_remainder']) ? (int)$q['quota_remainder'] : null];
      }
    }
    // Метрика
    [$c, $j] = ya_call('https://api-metrika.yandex.net/management/v1/counters?per_page=100', $t);
    if ($c === 403) $out['errs'][] = 'Нет доступа к Метрике — при создании приложения не выбран доступ к Метрике';
    $cnt = [];
    foreach ((array)($j['counters'] ?? []) as $k) if (stripos((string)($k['site'] ?? ''), 'itcpartner') !== false) $cnt[] = $k;
    if ($c === 200 && !$cnt) $out['errs'][] = 'В Метрике нет счётчиков сайтов itcpartner';
    usort($cnt, function ($a, $b) { return strcmp((string)$a['site'], (string)$b['site']) * -1; });   // itcpartner.ru раньше .store
    foreach (array_slice($cnt, 0, 3) as $k) {
      $id = (int)$k['id']; $per = [];
      foreach (['d' => 'today', 'w' => '6daysAgo', 'm' => '29daysAgo'] as $key => $d1) {
        [$cc, $r] = ya_call('https://api-metrika.yandex.net/stat/v1/data?ids=' . $id . '&metrics=ym:s:users,ym:s:visits,ym:s:pageviews&date1=' . $d1 . '&date2=today&lang=ru', $t);
        $tt = (array)($r['totals'] ?? []);
        $per[$key] = ['users' => (int)round($tt[0] ?? 0), 'visits' => (int)round($tt[1] ?? 0), 'views' => (int)round($tt[2] ?? 0)];
      }
      [$cc, $r] = ya_call('https://api-metrika.yandex.net/stat/v1/data?ids=' . $id . '&dimensions=ym:s:lastTrafficSource&metrics=ym:s:visits&date1=29daysAgo&date2=today&sort=-ym:s:visits&limit=8&lang=ru', $t);
      $src = [];
      foreach ((array)($r['data'] ?? []) as $row) $src[] = ['n' => (string)($row['dimensions'][0]['name'] ?? '—'), 'v' => (int)round($row['metrics'][0] ?? 0)];
      [$cc, $r] = ya_call('https://api-metrika.yandex.net/stat/v1/data?ids=' . $id . '&dimensions=ym:s:searchPhrase&metrics=ym:s:visits&date1=29daysAgo&date2=today&sort=-ym:s:visits&limit=10&lang=ru', $t);
      $ph = [];
      foreach ((array)($r['data'] ?? []) as $row) { $n = (string)($row['dimensions'][0]['name'] ?? ''); if ($n !== '') $ph[] = ['n' => $n, 'v' => (int)round($row['metrics'][0] ?? 0)]; }
      $out['mk'][] = ['id' => $id, 'site' => (string)$k['site'], 'name' => (string)($k['name'] ?? ''), 'per' => $per, 'src' => $src, 'ph' => $ph];
    }
    $y = ya_cfg(); $y['cache'] = $out; $y['cache_t'] = time(); ya_save($y);
    jout($out);
  }

  case 'ya_recrawl': {
    upd_me(); @set_time_limit(60); $t0 = time(); $d = body(); $y = ya_cfg(); $t = ya_tok(); $uid = (int)($y['uid'] ?? 0);
    if ($t === '' || !$uid) fail('Ключ Яндекса ещё не сохранён');
    $hid = (string)($d['host'] ?? '');
    if (!preg_match('~^https?:[a-z0-9.\-]+:\d+$~i', $hid)) fail('Не указан сайт');
    $hu = 'https://api.webmaster.yandex.net/v4/user/' . $uid . '/hosts/' . rawurlencode($hid);
    [$c, $q] = ya_call($hu . '/recrawl/quota', $t);
    $left = (int)($q['quota_remainder'] ?? 0);
    if ($left < 1) fail('На сегодня норма переобхода исчерпана — завтра Яндекс даст новую');
    [$sch, $host, $port] = explode(':', $hid);
    $site = $sch . '://' . $host;
    // карту сайта читаем раз в 6 часов и запоминаем — так каждое нажатие укладывается в несколько секунд
    $sm = (array)($y['sm'][$hid] ?? []);
    if (!empty($sm['u']) && time() - (int)($sm['t'] ?? 0) < 6 * 3600) $urls = (array)$sm['u'];
    else {
      $urls = ya_sitemap($site);
      if (!$urls) fail('Не удалось прочитать карту сайта ' . $site . '/sitemap.xml');
      $y['sm'][$hid] = ['t' => time(), 'u' => $urls]; ya_save($y);
    }
    // по очереди: сначала важные страницы (приоритет в карте сайта), отправленные за последние 14 дней пропускаем —
    // так за несколько дней Яндекс переобходит весь сайт
    $rc = (array)($y['rc'][$hid] ?? []); $now = time();
    foreach ($rc as $u => $tm) if ($now - (int)$tm > 14 * 86400) unset($rc[$u]);
    $i = 0; $rank = [];
    foreach ($urls as $u => $pri) $rank[$u] = [-(float)$pri, $i++];
    uksort($urls, function ($a, $b) use ($rank) { return $rank[$a] <=> $rank[$b]; });
    $max = min($left, 10); $take = [];   // по 10 страниц за один заход; экран сам повторяет заходы
    foreach ($urls as $u => $pri) { if (isset($rc[$u])) continue; $take[] = $u; if (count($take) >= $max) break; }
    if (!$take) jout(['ok' => true, 'sent' => 0, 'left' => $left, 'all' => count($urls), 'note' => 'Все страницы сайта уже отправлялись за последние 14 дней']);
    $sent = 0; $err = ''; $fails = 0;
    foreach ($take as $u) {
      if (time() - $t0 > 40) break;   // не доводим до обрыва связи — остальное уйдёт следующим заходом
      [$c, $j] = ya_call($hu . '/recrawl/queue', $t, ['url' => $u], 8);
      if ($c >= 500 || $c === 0) { usleep(1500000); [$c, $j] = ya_call($hu . '/recrawl/queue', $t, ['url' => $u], 8); }   // сбой у Яндекса — ещё одна попытка
      if ($c >= 200 && $c < 300) { $sent++; $rc[$u] = $now; }
      elseif ($c === 409) { $rc[$u] = $now; }   // страница уже стоит в очереди Яндекса — не повторяем
      else {
        $err = (string)($j['error_message'] ?? $j['error_code'] ?? '');
        $err = ($c >= 500 || $c === 0 ? 'Яндекс сейчас не отвечает (код ' . $c . ')' : 'Яндекс не принял страницу (код ' . $c . ')') . ($err !== '' ? ': ' . $err : '') . ' — ' . $u;
        $fails++;
        if ($c === 429 || stripos($err, 'QUOTA') !== false || $fails >= 3) break;
      }
    }
    $y = ya_cfg(); unset($y['cache']); $y['rc'][$hid] = $rc; ya_save($y);
    $wait = 0; foreach ($urls as $u => $pri) if (!isset($rc[$u])) $wait++;
    jout(['ok' => $sent > 0 || $err === '', 'sent' => $sent, 'left' => max(0, $left - $sent), 'all' => count($urls), 'wait' => $wait,
          'error' => $sent ? '' : ($err ?: 'Яндекс не принял страницы')]);
  }

  case 'upd_cat_done': {
    upd_me(); $d = body(); $s = upd_state();
    foreach ($s['cats'] as &$c) if ($c['sup'] === ($d['sup'] ?? '') && (int)$c['bytes'] === (int)($d['bytes'] ?? -1)) $c['done'] = true;
    unset($c); upd_save($s);
    jout(['ok' => true]);
  }

  /* №6 · ключ для помощника DeepSeek: создать (отдаётся один раз файлом) или отключить */
  case 'upd_ds_key': {
    $who = upd_me(); $d = body(); $act = (string)($d['act'] ?? '');
    if ($act === 'off') { @unlink(upd_ds_file()); jout(['ok' => true, 'ds' => null]); }
    if ($act !== 'new') fail('Неизвестное действие');
    $k = bin2hex(random_bytes(32));
    file_put_contents(upd_ds_file(), json_encode(['k' => $k, 'at' => date('d.m.Y H:i'), 'who' => $who]), LOCK_EX); @chmod(upd_ds_file(), 0600);
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="itc_deepseek_klyuch.txt"');
    echo $k; exit;
  }

  /* ---------- №8 · вызовы помощника в Гонконге (по его ключу, без входа босса) ---------- */
  case 'hk_list': {
    hk_auth(); $have = (int)($_GET['have'] ?? 0); $ns = [];
    foreach (glob(hk_dir() . '/*.zip') as $f) { $k = (int)basename($f, '.zip'); if ($k > $have) $ns[] = $k; }
    sort($ns);
    $st = hk_state(); $st['seen'] = time(); @file_put_contents(hk_dir() . '/state.json', json_encode($st, JSON_UNESCAPED_UNICODE), LOCK_EX);
    jout(['ok' => true, 'ns' => $ns]);
  }
  case 'hk_zip': {
    hk_auth(); $n = (int)($_GET['n'] ?? 0); $f = hk_dir() . '/' . $n . '.zip';
    if (!is_file($f)) fail('Нет такой части');
    header('Content-Type: application/zip'); header('Content-Length: ' . filesize($f)); readfile($f); exit;
  }
  case 'hk_report': {
    hk_auth(); $d = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $st = hk_state();
    $st['seen'] = time(); $st['at'] = date('d.m.Y H:i'); $st['n'] = (int)($d['n'] ?? 0); $st['ok'] = !empty($d['ok']);
    $st['msg'] = mb_substr((string)($d['msg'] ?? ''), 0, 600); $st['info'] = is_array($d['info'] ?? null) ? $d['info'] : [];
    if (!empty($d['bk'])) { $st['bk'] = (string)$d['bk']; $st['bk_size'] = (int)($d['bk_size'] ?? 0); }
    if (!empty($d['bk_gz'])) $st['bk_gz'] = (string)$d['bk_gz'];   // №9 · вторая копия — на сервере в Гуанчжоу
    if (!$st['ok']) { $st['err'] = $st['msg']; $st['err_at'] = $st['at']; } elseif (stripos($st['msg'], 'установлено') !== false) unset($st['err'], $st['err_at']);
    @file_put_contents(hk_dir() . '/state.json', json_encode($st, JSON_UNESCAPED_UNICODE), LOCK_EX);
    jout(['ok' => true]);
  }
  case 'hk_bk_put': {
    hk_auth(); $day = preg_replace('~\D~', '', (string)($_GET['day'] ?? '')); $off = (int)($_GET['off'] ?? 0); $size = (int)($_GET['size'] ?? 0);
    if (strlen($day) !== 8 || $size <= 0 || $size > 2000 * 1048576) fail('Неверная копия');
    $d = upd_dir() . '/hk_bk'; if (!is_dir($d)) @mkdir($d, 0755, true);
    $f = $d . '/' . $day . '.tar.gz'; $part = $f . '.part';
    if ($off === 0) @unlink($part);
    clearstatcache(); if ((is_file($part) ? filesize($part) : 0) !== $off) fail('Кусок копии не по порядку');
    if (@file_put_contents($part, file_get_contents('php://input'), FILE_APPEND | LOCK_EX) === false) fail('Нет места для копии');
    clearstatcache();
    if (filesize($part) >= $size) { @rename($part, $f); $all = glob($d . '/*.tar.gz'); sort($all); while (count($all) > 7) @unlink(array_shift($all)); }
    jout(['ok' => true]);
  }

  case 'upd_pull': {
    upd_me(); @set_time_limit(300); $s = upd_state();
    if (!class_exists('ZipArchive')) fail('На сервере нет ZipArchive');
    $tmp = upd_dir() . '/pull_' . date('Ymd_His') . '.zip';
    $z = new ZipArchive(); if ($z->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) fail('Не удалось собрать архив');
    $skip = '~(^|/)(config[^/]*\.php|upd_key[^/]*|suppliers\.php|ds_key\.json|\.htaccess|\.user\.ini|state\.json|ya\.json)$~i';
    $ok = '~\.(html|js|php|json|webmanifest|css|txt)$~i';
    $list = [];
    $add = function ($root, $rel, $pref) use ($z, $skip, $ok, &$list) {
      $f = $root . '/' . $rel;
      if (!is_file($f) || preg_match($skip, $rel) || !preg_match($ok, $rel) || filesize($f) > 20 * 1048576) return;
      $z->addFile($f, $pref . '/' . $rel); $list[] = $pref . '/' . $rel;
    };
    $walk = function ($root, $dir, $pref, $depth) use (&$walk, $add) {
      $d = $root . ($dir === '' ? '' : '/' . $dir);
      foreach ((array)@scandir($d) as $e) {
        if ($e === '.' || $e === '..' || $e === '') continue;
        $rel = ($dir === '' ? '' : $dir . '/') . $e;
        if (is_dir($root . '/' . $rel)) { if ($depth > 0 && !preg_match('~^(uploads|backup|cache|tmp|icons|img|images|photos|files|sup_seed|node_modules|ai_state|co_files|fil_edits|srv|srv_jobs)$~i', $e)) $walk($root, $rel, $pref, $depth - 1); }
        else $add($root, $rel, $pref);
      }
    };
    $st = upd_store();
    foreach (['index.html', 'sw.js', 'manifest.json'] as $r) $add($st, $r, 'store');
    foreach (['esim', 'apps', 'api', 'eco', 'sklad'] as $dir) if (is_dir($st . '/' . $dir)) $walk($st, $dir, 'store', 3);   // №7: всё, что может править Фил
    $sr = upd_site_root($s); if ($sr !== '') $walk($sr, '', 'site', 4);
    $mr = upd_metro_root($s); if ($mr !== '') $walk($mr, '', 'metro', 1);
    foreach (glob($st . '/uploads/fil_edits/*.json') ?: [] as $f) { $z->addFile($f, 'fil_edits/' . basename($f)); $list[] = 'fil_edits/' . basename($f); }   // журнал правок Фила
    $z->addFromString('opis.txt', "Файлы с сервера на " . date('d.m.Y H:i') . " (МСК). Обновление №" . (int)$s['n'] . ".\n" . implode("\n", $list) . "\n");
    $z->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="faily_dlya_claude_' . date('Ymd_Hi') . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp); @unlink($tmp); exit;
  }

  default: fail('Неизвестное действие');
  }
} catch (Throwable $e) { fail('Ошибка сервера: ' . $e->getMessage(), 500); }

function upd_rm($d) {
  if (!is_dir($d)) return;
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
  foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
  @rmdir($d);
}

/* ---------- №5 · Яндекс: ключ хранится только на сервере (папка закрыта от интернета) ---------- */
function ya_file() { return upd_dir() . '/ya.json'; }
function ya_cfg() { $j = is_file(ya_file()) ? json_decode((string)file_get_contents(ya_file()), true) : null; return is_array($j) ? $j : []; }
function ya_save($y) { file_put_contents(ya_file(), json_encode($y, JSON_UNESCAPED_UNICODE), LOCK_EX); @chmod(ya_file(), 0600); }
function ya_tok() { return (string)(ya_cfg()['token'] ?? ''); }
function ya_call($url, $tok, $post = null, $tmo = 25) {
  $ch = curl_init($url);
  $h = ['Authorization: OAuth ' . $tok, 'Accept: application/json'];
  $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $tmo, CURLOPT_CONNECTTIMEOUT => 8];
  if ($post !== null) { $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($post); $h[] = 'Content-Type: application/json'; }
  $o[CURLOPT_HTTPHEADER] = $h;
  curl_setopt_array($ch, $o);
  $r = (string)curl_exec($ch); $c = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  $j = json_decode($r, true);
  return [$c, is_array($j) ? $j : []];
}
/* Адреса из карты сайта: [адрес => приоритет] (вложенные карты — до 10 штук) */
function ya_sitemap($site) {
  $get = function ($u) { $ch = curl_init($u); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_FOLLOWLOCATION => true]); $r = (string)curl_exec($ch); curl_close($ch); return $r; };
  $out = []; $queue = [$site . '/sitemap.xml']; $seen = 0;
  while ($queue && $seen < 11) {
    $x = $get(array_shift($queue)); $seen++;
    if ($x === '') continue;
    if (stripos($x, '<sitemapindex') !== false) {
      if (preg_match_all('~<loc>\s*([^<\s]+)\s*</loc>~i', $x, $m)) foreach ($m[1] as $u) $queue[] = html_entity_decode($u);
      continue;
    }
    if (preg_match_all('~<url>(.*?)</url>~is', $x, $m)) foreach ($m[1] as $blk) {
      if (!preg_match('~<loc>\s*([^<\s]+)\s*</loc>~i', $blk, $l)) continue;
      $u = html_entity_decode($l[1]);
      if (strpos($u, $site) !== 0) continue;
      $out[$u] = preg_match('~<priority>\s*([0-9.]+)~i', $blk, $mm) ? (float)$mm[1] : 0.5;
    }
  }
  return $out;
}
