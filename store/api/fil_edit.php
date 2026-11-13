<?php
// ==== ИТК · fil_edit.php · №42 (05.11.2026) · Фил правит сайт, магазин и приложения по просьбе руководителя ====
// Фил сам ничего не меняет: он ищет нужное место, готовит правку (было → станет) и показывает её.
// Руководитель нажимает «Применить» — старые файлы сохраняются, «Вернуть как было» восстанавливает их.
// Нельзя: ключи и настройки, раздел обновлений, сам Фил (его сервер и приложение) — чтобы ошибкой не сломать отмену.
// Журнал правок — uploads/fil_edits/*.json (его видит Claude, когда забирает файлы с сервера).

const FE_EXT = '~\.(php|html?|js|css|json|txt|svg|xml|md|webmanifest)$~i';
const FE_SKIPDIR = '~^(uploads|backup|cache|tmp|node_modules|img|images|photos|files|sup_seed|fonts|lib|vendor|ai_state|co_files|\..*)$~i';

function fe_dir() { $d = dirname(__DIR__) . '/uploads/fil_edits'; if (!is_dir($d)) @mkdir($d, 0750, true); if (!is_file("$d/.htaccess")) @file_put_contents("$d/.htaccess", "Require all denied\nDeny from all\n"); return $d; }
function fe_roots() {
  static $r = null; if ($r !== null) return $r;
  $st = dirname(__DIR__); $up = dirname($st); $s = json_decode((string)@file_get_contents($st . '/uploads/upd/state.json'), true) ?: [];
  $site = ''; foreach (array_merge(!empty($s['site']) ? [$s['site']] : [], [$up . '/itcpartner.ru', $up . '/www.itcpartner.ru', $up . '/itcpartner.ru/public_html', $up . '/itcpartner.ru/www']) as $p) { $p = rtrim($p, '/'); if (is_file($p . '/inc/core.php')) { $site = $p; break; } }
  $metro = ''; foreach (array_merge(!empty($s['metro']) ? [$s['metro']] : [], [$up . '/metro.itcpartner.store', $up . '/itcpartner.store/metro', $up . '/metro']) as $p) { $p = rtrim($p, '/'); if ($p !== $st && is_file($p . '/index.html')) { $metro = $p; break; } }
  return $r = ['store' => $st, 'site' => $site, 'metro' => $metro];
}
/* «site/pages/contacts.php» → [полный путь, имя] или текст ошибки */
function fe_path($f) {
  $f = trim(str_replace('\\', '/', (string)$f), '/ ');
  if (!preg_match('~^(site|store|metro)/[A-Za-z0-9._\-/]+$~', $f) || strpos($f, '..') !== false) return 'имя файла должно начинаться с site/, store/ или metro/ и состоять из латиницы';
  if (!preg_match(FE_EXT, $f)) return 'такие файлы не правлю (только php, html, js, css, json, txt, svg)';
  $b = basename($f);
  if (preg_match('~^(config.*\.php|upd_key.*|suppliers\.php|\.htaccess|\.user\.ini|state\.json|ya\.json|ds_key\.json)$~i', $b) || preg_match('~(^|/)(uploads|backup|sup_seed)/~', $f)) return 'этот файл с ключами или данными — его не трогаю';
  [$k, $rel] = explode('/', $f, 2); $root = fe_roots()[$k] ?? '';
  if ($root === '') return 'не нашлась папка ' . $k . ' на сервере';
  return [$root . '/' . $rel, $f];
}
function fe_locked($f) {   // править нельзя (читать можно)
  return (bool)preg_match('~^store/(api/(flow|fil_edit|fil_kp|kp_pdf|upd|ai_lib)\.php|eco/upd\.html|apps/fil/.*)$~', $f);
}
function fe_files($k) {
  $root = fe_roots()[$k] ?? ''; $out = []; if ($root === '') return $out;
  $walk = function ($dir, $rel, $depth) use (&$walk, &$out) {
    foreach ((array)@scandir($dir) as $e) {
      if ($e === '.' || $e === '..' || $e === '') continue; $p = $dir . '/' . $e; $r = $rel === '' ? $e : $rel . '/' . $e;
      if (is_dir($p)) { if ($depth > 0 && !preg_match(FE_SKIPDIR, $e)) $walk($p, $r, $depth - 1); continue; }
      if (preg_match(FE_EXT, $e) && @filesize($p) < 4 * 1048576) $out[] = $r;
      if (count($out) > 4000) return;
    }
  };
  $walk($root, '', 5); return array_values(array_filter($out, function ($r) use ($k) { return is_array(fe_path($k . '/' . $r)); }));
}
function fe_draft_get($id) { $id = preg_replace('~[^a-z0-9]~', '', (string)$id); $f = fe_dir() . '/' . $id . '.json'; $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null; return is_array($j) ? $j : null; }
function fe_draft_put($d) { file_put_contents(fe_dir() . '/' . $d['id'] . '.json', json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX); }
/* содержимое файла с учётом уже подготовленных правок этого черновика */
function fe_now($d, $f, $abs) {
  $c = null; foreach ($d['items'] as $it) if ($it['file'] === $f) { if ($it['kind'] === 'new') $c = $it['new']; else { if ($c === null) $c = (string)@file_get_contents($abs); $c = fe_repl($c, $it['old'], $it['new']); } }
  return $c ?? (is_file($abs) ? (string)file_get_contents($abs) : null);
}
function fe_repl($c, $old, $new) { $p = strpos($c, $old); return $p === false ? $c : substr($c, 0, $p) . $new . substr($c, $p + strlen($old)); }
function fe_check($f, $c) {   // проверка, что файл не сломан
  if (preg_match('~\.php$~i', $f)) { try { token_get_all($c, TOKEN_PARSE); } catch (Throwable $e) { return 'ошибка в коде PHP: ' . $e->getMessage() . ' (строка ' . $e->getLine() . ')'; } }
  if (preg_match('~\.(json|webmanifest)$~i', $f) && $c !== '' && json_decode($c) === null && json_last_error() !== JSON_ERROR_NONE) return 'JSON сломан: ' . json_last_error_msg();
  return '';
}

/* ---------- инструменты Фила (только руководитель) ---------- */
function fe_tools($me) {
  if (empty($me['boss'])) return [];
  $GLOBALS['FE_ME'] = $me;
  return [
    'code_find' => ['{"q":"точный текст или слово с сайта/из кода","where":"site|store|metro|all"} — ПРАВКИ САЙТА: найти, в каком файле и строке есть этот текст (до 25 мест). site — сайт itcpartner.ru, store — магазин itcpartner.store, Экосистема и приложения, metro — «Мой Гуанчжоу». Ищи коротким точным куском текста, который виден на странице',
      function ($a) {
        $q = trim((string)($a['q'] ?? '')); if (mb_strlen($q) < 2) return 'напиши, что искать';
        $ws = ($a['where'] ?? 'all') === 'all' || !in_array($a['where'] ?? '', ['site', 'store', 'metro'], true) ? ['site', 'store', 'metro'] : [$a['where']];
        $o = [];
        foreach ($ws as $k) foreach (fe_files($k) as $rel) {
          $abs = fe_roots()[$k] . '/' . $rel; $c = (string)@file_get_contents($abs); if (mb_stripos($c, $q) === false) continue;
          foreach (preg_split('~\R~', $c) as $i => $line) if (mb_stripos($line, $q) !== false) {
            $o[] = $k . '/' . $rel . ':' . ($i + 1) . ': ' . mb_substr(trim($line), 0, 200) . (fe_locked($k . '/' . $rel) ? '  [менять нельзя]' : '');
            if (count($o) >= 25) break 3;
          }
        }
        return $o ?: 'не нашёл «' . $q . '» — попробуй другой кусок текста или слово';
      }],
    'code_read' => ['{"file":"site/pages/contacts.php","from":1,"lines":80} — ПРАВКИ САЙТА: прочитать файл с номерами строк (до 150 строк за раз). Без from — покажу размер и начало',
      function ($a) {
        $p = fe_path($a['file'] ?? ''); if (!is_array($p)) return $p; if (!is_file($p[0])) return 'нет такого файла: ' . $p[1];
        $ls = preg_split('~\R~', (string)file_get_contents($p[0])); $from = max(1, (int)($a['from'] ?? 1)); $n = min(150, max(10, (int)($a['lines'] ?? 80)));
        $o = ['файл' => $p[1], 'всего строк' => count($ls)] + (fe_locked($p[1]) ? ['внимание' => 'этот файл менять нельзя'] : []); $t = [];
        for ($i = $from; $i < $from + $n && $i <= count($ls); $i++) $t[] = $i . ': ' . mb_substr($ls[$i - 1], 0, 600);
        $o['строки'] = implode("\n", $t); return $o;
      }],
    'code_edit' => ['{"file":"site/pages/contacts.php","old":"точный кусок текста из файла (как в code_read, без номеров строк)","new":"на что заменить","why":"что меняю — простыми словами"} — ПРАВКИ САЙТА: подготовить замену. Файл НЕ меняется, пока руководитель не нажмёт «Применить». old должен встречаться в файле ровно один раз — бери кусок с запасом. Несколько мест — несколько code_edit',
      function ($a) {
        $p = fe_path($a['file'] ?? ''); if (!is_array($p)) return $p; [$abs, $f] = $p;
        if (fe_locked($f)) return 'этот файл менять нельзя (сам Фил или раздел обновлений) — это делает Claude';
        if (!is_file($abs)) return 'нет такого файла: ' . $f . ' (новый файл — через code_new)';
        $old = (string)($a['old'] ?? ''); $new = (string)($a['new'] ?? ''); if ($old === '' || $old === $new) return 'нужны old и new, и они должны отличаться';
        if (mb_strlen($new) > 30000) return 'слишком большой кусок — раздели на несколько правок';
        $d = fe_cur(); $c = fe_now($d, $f, $abs); $cnt = substr_count($c, $old);
        if ($cnt === 0) return 'в файле нет такого куска — прочитай место через code_read и скопируй текст точно (пробелы и кавычки тоже)';
        if ($cnt > 1) return 'этот кусок встречается ' . $cnt . ' раз — возьми кусок длиннее, чтобы он был единственным';
        $err = fe_check($f, fe_repl($c, $old, $new)); if ($err !== '') return 'правка сломает файл: ' . $err . ' — исправь';
        $d['items'][] = ['file' => $f, 'kind' => 'edit', 'old' => $old, 'new' => $new, 'why' => mb_substr(trim((string)($a['why'] ?? '')), 0, 300)];
        fe_draft_put($d); $GLOBALS['FE_MADE'] = $d['id'];
        return 'правка подготовлена (' . count($d['items']) . ' в списке). Файл ещё не изменён — руководитель нажмёт «Применить»';
      }],
    'code_new' => ['{"file":"site/pages/new-page.php","content":"полный текст файла","why":"что это"} — ПРАВКИ САЙТА: подготовить НОВЫЙ файл (например, страницу по образцу соседней). Только в site/pages, site/inc, site/lang, store/apps, metro. Существующий файл так не заменить',
      function ($a) {
        $p = fe_path($a['file'] ?? ''); if (!is_array($p)) return $p; [$abs, $f] = $p;
        if (!preg_match('~^(site/(pages|inc|lang)/|store/apps/|metro/)~', $f) || fe_locked($f)) return 'новые файлы — только в site/pages, site/inc, site/lang, store/apps (кроме Фила), metro';
        if (is_file($abs)) return 'такой файл уже есть — правь его через code_edit';
        $c = (string)($a['content'] ?? ''); if (trim($c) === '' || strlen($c) > 300000) return 'нужно содержимое файла (до 300 КБ)';
        $err = fe_check($f, $c); if ($err !== '') return 'файл получится с ошибкой: ' . $err;
        $d = fe_cur(); foreach ($d['items'] as $it) if ($it['file'] === $f) return 'этот файл уже в списке правок';
        $d['items'][] = ['file' => $f, 'kind' => 'new', 'old' => '', 'new' => $c, 'why' => mb_substr(trim((string)($a['why'] ?? '')), 0, 300)];
        fe_draft_put($d); $GLOBALS['FE_MADE'] = $d['id'];
        return 'новый файл подготовлен. Он появится после «Применить»';
      }],
  ];
}
function fe_cur() {   // черновик этого запроса
  if (!empty($GLOBALS['FE_MADE']) && ($d = fe_draft_get($GLOBALS['FE_MADE']))) return $d;
  $me = $GLOBALS['FE_ME'] ?? ['name' => '', 'phone' => ''];
  return ['id' => date('ymdHis') . bin2hex(random_bytes(3)), 'at' => date('d.m.Y H:i'), 'who' => (string)$me['name'], 'phone' => (string)$me['phone'], 'status' => 'draft', 'items' => []];
}
function fe_marks() { $id = $GLOBALS['FE_MADE'] ?? ''; if ($id === '' || !($d = fe_draft_get($id))) return ''; $fs = array_values(array_unique(array_column($d['items'], 'file'))); return "\n[[ed:" . $d['id'] . '|' . count($d['items']) . ' ' . fe_word(count($d['items'])) . ' в ' . count($fs) . ' ' . (count($fs) === 1 ? 'файле' : 'файлах') . ']]'; }
function fe_word($n) { $n %= 100; $m = $n % 10; return ($n > 10 && $n < 20) ? 'правок' : ($m === 1 ? 'правка' : (($m >= 2 && $m <= 4) ? 'правки' : 'правок')); }
const FE_RULES = "\nПРАВКИ САЙТА, МАГАЗИНА, ЭКОСИСТЕМЫ (только для руководителя): если просят поменять что-то на сайте itcpartner.ru, в магазине, в приложениях — найди место (code_find коротким точным текстом со страницы), прочитай его (code_read), подготовь замену (code_edit) или новый файл (code_new). КАЖДУЮ просьбу о правке делай заново инструментами: прошлые правки из разговора не считаются — они могли быть отменены или неверны; если человек повторяет или уточняет просьбу, значит прошлая правка была не та — найди другое место. Ищи видимый текст так, как он стоит в коде страницы (заголовок «КОНТАКТЫ» ищи как «КОНТАКТЫ</h1>» или «>КОНТАКТЫ<», учитывай заглавные буквы). Ты НИЧЕГО не меняешь сам: в ответе скажи коротко, что подготовил, и попроси проверить и нажать «Применить». «Название» или «заголовок» на странице — это крупный текст, который видно на самой странице (обычно <h1> или <h2>), а не \$title во вкладке браузера; если непонятно, что именно менять, — переспроси одним вопросом (do=ask). Меняй минимально — только то, что просили. Если правишь store/index.html или store/apps/<имя>/index.html — добавь правку и в соседний sw.js: подними номер кэша (например itc-crm-v45 → itc-crm-v46). Стиль: шрифт Roboto, значки только Google Material, в русском интерфейсе только русские слова, тексты кнопок без привязки к полу («Готово», а не «Понял»), WhatsApp не предлагать. Страницу фабрики — только в черновик (site/inc/az-factories-draft.php), пока нет товаров с фото из каталога. Сложное (новые функции, оплата, база данных) — честно скажи, что лучше поручить Claude.";

/* ---------- действия руководителя ---------- */
function fe_view($id) {
  $d = fe_draft_get($id); if (!$d) fl_fail('Правка не найдена');
  $cut = function ($s) { $s = (string)$s; return mb_strlen($s) > 1500 ? mb_substr($s, 0, 1500) . '…' : $s; };
  return ['ok' => true, 'id' => $d['id'], 'status' => $d['status'], 'at' => $d['at'], 'items' => array_map(function ($it) use ($cut) { return ['file' => $it['file'], 'kind' => $it['kind'], 'why' => $it['why'], 'old' => $cut($it['old']), 'new' => $cut($it['new'])]; }, $d['items'])];
}
function fe_apply($me, $id) {
  $d = fe_draft_get($id); if (!$d) fl_fail('Правка не найдена'); if ($d['status'] !== 'draft') fl_fail($d['status'] === 'applied' ? 'Уже применено' : 'Эта правка отменена');
  $plan = [];
  foreach (array_unique(array_column($d['items'], 'file')) as $f) {
    $p = fe_path($f); if (!is_array($p) || fe_locked($f)) fl_fail('Нельзя менять ' . $f);
    $abs = $p[0]; $orig = is_file($abs) ? (string)file_get_contents($abs) : null; $c = $orig;
    foreach ($d['items'] as $it) if ($it['file'] === $f) {
      if ($it['kind'] === 'new') { if ($orig !== null) fl_fail('Файл ' . $f . ' уже появился — правку нужно сделать заново'); $c = $it['new']; continue; }
      if ($c === null || substr_count($c, $it['old']) !== 1) fl_fail('Файл ' . $f . ' изменился с тех пор, как Фил готовил правку. Попросите Фила подготовить её заново');
      $c = fe_repl($c, $it['old'], $it['new']);
    }
    $err = fe_check($f, $c); if ($err !== '') fl_fail('В ' . $f . ' получится ' . $err . ' — ничего не изменено');
    $plan[] = [$f, $abs, $orig, $c];
  }
  $bk = fe_dir() . '/bk/' . $d['id']; @mkdir($bk, 0750, true); $done = [];
  foreach ($plan as [$f, $abs, $orig, $c]) {
    if ($orig !== null) { $b = $bk . '/' . $f; @mkdir(dirname($b), 0750, true); if (@file_put_contents($b, $orig) === false) fe_fail_back($d, $done, 'Не удалось сохранить копию ' . $f); }
    @mkdir(dirname($abs), 0755, true); $tmp = $abs . '.fe_tmp';
    if (@file_put_contents($tmp, $c, LOCK_EX) === false || !@rename($tmp, $abs)) { @unlink($tmp); fe_fail_back($d, $done, 'Не удалось записать ' . $f); }
    $done[] = [$f, $abs, $orig !== null];
  }
  $d['status'] = 'applied'; $d['applied_at'] = date('d.m.Y H:i'); $d['applied_by'] = $me['name']; $d['new_files'] = array_values(array_map(function ($x) { return $x[0]; }, array_filter($done, function ($x) { return !$x[2]; })));
  fe_draft_put($d);
  return ['ok' => true, 'files' => count($done), 'say' => 'Готово: изменено файлов — ' . count($done) . '. Если что-то не так — «Вернуть как было».'];
}
function fe_fail_back($d, $done, $msg) { foreach ($done as [$f, $abs, $had]) { $b = fe_dir() . '/bk/' . $d['id'] . '/' . $f; if ($had && is_file($b)) @copy($b, $abs); elseif (!$had) @unlink($abs); } fl_fail($msg . '. Уже изменённое вернул как было.'); }
function fe_undo($me, $id) {
  $d = fe_draft_get($id); if (!$d) fl_fail('Правка не найдена'); if ($d['status'] !== 'applied') fl_fail('Эта правка не применялась');
  $n = 0;
  foreach (array_unique(array_column($d['items'], 'file')) as $f) {
    $p = fe_path($f); if (!is_array($p)) continue; $b = fe_dir() . '/bk/' . $d['id'] . '/' . $f;
    if (in_array($f, $d['new_files'] ?? [], true)) { if (@unlink($p[0])) $n++; }
    elseif (is_file($b) && @copy($b, $p[0])) $n++;
  }
  $d['status'] = 'undone'; $d['undone_at'] = date('d.m.Y H:i'); $d['undone_by'] = $me['name']; fe_draft_put($d);
  return ['ok' => true, 'files' => $n, 'say' => 'Вернул как было: файлов — ' . $n . '.'];
}
function fe_list() {
  $o = [];
  foreach (glob(fe_dir() . '/*.json') ?: [] as $f) { $d = json_decode((string)file_get_contents($f), true); if (!is_array($d) || $d['status'] === 'draft' && filemtime($f) < time() - 7 * 86400) continue;
    $o[] = ['id' => $d['id'], 'at' => $d['at'], 'status' => $d['status'], 'who' => $d['who'], 'files' => array_values(array_unique(array_column($d['items'], 'file'))), 'why' => implode('; ', array_filter(array_column($d['items'], 'why')))]; }
  usort($o, function ($a, $b) { return strcmp($b['id'], $a['id']); }); return array_slice($o, 0, 30);
}
