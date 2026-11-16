<?php
// ==== ИТК · api/kb.php · БАЗА ЗНАНИЙ ИТК — страница «Обновления» (№631, 16.11.2026) ====
// Только руководитель (пропуск Экосистемы ?t=…):
//   status     — разделы базы знаний (сколько записей), даты последних копий: хостинг, хранилище S3, Сингапур, Гуанчжоу, Гонконг;
//                подключённые другие модели (без ключей)
//   export     — скачать базу знаний одним архивом (.zip: Markdown по разделам, kb.json, README — как подключить к любому ИИ)
//   import     — восстановить из такого архива (тело запроса — сам архив)
//   prov_save / prov_del / prov_test / prov_use — другие модели ИИ для «Фила» (OpenAI-совместимый вход), см. kb_lib.php
//   fr_status / fr_set / fr_key / fr_test — №634 окно «Модели Фила» (роли китайских моделей), см. fil_roles.php
// Сами знания и функции — api/kb_lib.php.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/kb_lib.php';
require_once __DIR__ . '/ai_lib.php';
require_once __DIR__ . '/fil_roles.php';   // №634 · роли и модели «Фила»
if (is_file(__DIR__ . '/kosmos_lib.php')) require_once __DIR__ . '/kosmos_lib.php';   // навыки — с сервера «Космоса» (шлюз)

function kb_out($a, $code = 200) {
  if (!headers_sent()) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); }
  echo json_encode($a, JSON_UNESCAPED_UNICODE); exit;
}
function kb_in() { static $b = null; if ($b === null) { $j = json_decode((string)file_get_contents('php://input'), true); $b = is_array($j) ? $j : []; } return $b; }
function kb_boss() {
  $s = authUser('itc'); $r = (string)($s['staff_role'] ?? '');
  if (!($r === 'boss' || $r === '')) kb_out(['ok' => false, 'error' => 'База знаний — только руководитель'], 403);
  return trim((string)($s['name'] ?? '')) ?: 'руководитель';
}
function kb_day($d) { $d = preg_replace('~\D~', '', (string)$d); return strlen($d) >= 8 ? substr($d, 6, 2) . '.' . substr($d, 4, 2) . '.' . substr($d, 0, 4) : ''; }

$action = (string)($_GET['action'] ?? '');
try {
  switch ($action) {
  case 'status': {
    kb_boss();
    $o = ['ok' => true] + kb_status();
    /* копии базы знаний — по местам */
    $bk = dirname(__DIR__) . '/uploads/backup'; $bs = is_file("$bk/state.json") ? (array)json_decode((string)file_get_contents("$bk/state.json"), true) : [];
    $loc = glob("$bk/kb_2*.zip") ?: []; rsort($loc);
    $last = (array)($bs['last'] ?? []); $kbIn = false; foreach ((array)($last['files'] ?? []) as $f) if (strpos((string)($f['f'] ?? ''), 'kb_') === 0) $kbIn = true;
    $cp = [];
    $cp[] = ['where' => 'Хостинг (Россия)', 'day' => $loc ? kb_day(basename($loc[0])) : '', 'note' => count($loc) ? 'хранятся 3 последние' : 'первая копия — этой ночью'];
    $cp[] = ['where' => 'Хранилище S3 (Россия)', 'day' => $kbIn && ($last['s3'] ?? '') === 'ok' ? kb_day((string)($last['day'] ?? '')) : '', 'note' => '30 дней + по одной в месяц'];
    $ks = function_exists('ks_dir') ? (array)ks_json(ks_dir() . '/status.json') : [];
    $cp[] = ['where' => 'Сингапур (сервер «Космоса»)', 'day' => kb_day((string)($ks['kb'] ?? '')), 'note' => (string)($ks['kb'] ?? '') === '' ? 'начнёт забирать после обновления сервера до 0.5.7' : 'хранятся 14 последних'];
    $srvf = dirname(__DIR__) . '/uploads/srv/state.json'; $srv = is_file($srvf) ? (array)json_decode((string)file_get_contents($srvf), true) : [];
    $gz = ''; foreach ((array)($srv['hb']['copies'] ?? []) as $c) if (preg_match('~^kb_(\d{8})~', (string)($c['f'] ?? ''), $m) && $m[1] > $gz) $gz = $m[1];
    $cp[] = ['where' => 'Гуанчжоу (сервер Aliyun)', 'day' => kb_day($gz), 'note' => $gz === '' ? 'забирает ночные копии сам; база знаний появится в его списке после первой ночной копии' : 'забирает ночные копии'];
    $cp[] = ['where' => 'Гонконг', 'day' => '', 'note' => 'пока нет: помощнику Гонконга нужна правка с ключом подписи Гонконга — сделает Claude'];
    $o['copies'] = $cp;
    $o['prov'] = array_map(function ($id, $p) { return ['id' => $id] + array_intersect_key($p, array_flip(['name', 'url', 'model', 'has_key', 'key4', 'at'])); }, array_keys(kb_prov_all()), array_values(kb_prov_all()));
    $o['fil_model'] = kb_sp_get('fil_model2');
    kb_out($o);
  }
  case 'export': {
    kb_boss(); @set_time_limit(300);
    $tmp = tempnam(sys_get_temp_dir(), 'kbx'); $what = kb_export_zip($tmp);
    $name = 'baza_znaniy_ITK_' . date('Ymd_Hi') . '.zip';
    while (ob_get_level() > 0) @ob_end_clean();
    header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="' . $name . '"'); header('Content-Length: ' . filesize($tmp)); header('X-KB-What: ' . rawurlencode($what));
    readfile($tmp); @unlink($tmp); exit;
  }
  case 'import': {
    $who = kb_boss(); @set_time_limit(300);
    $bin = (string)file_get_contents('php://input');
    if (strlen($bin) < 30 || strlen($bin) > 80 * 1048576) kb_out(['ok' => false, 'error' => 'Пришлите архив базы знаний (.zip до 80 МБ)'], 400);
    $r = kb_import($bin, $who);
    kb_out($r, empty($r['ok']) ? 400 : 200);
  }
  case 'prov_save': { $who = kb_boss(); $r = kb_prov_save(kb_in(), $who); if (!empty($r['ok'])) $r['test'] = kb_prov_test($r['id']); kb_out($r, empty($r['ok']) ? 400 : 200); }
  case 'prov_del':  { $who = kb_boss(); $id = (string)(kb_in()['id'] ?? ''); if (kb_sp_get('fil_model2') === 'oa:' . $id) kb_sp_put('fil_model2', '', $who); kb_out(kb_prov_del($id, $who)); }
  case 'prov_test': { kb_boss(); kb_out(kb_prov_test((string)(kb_in()['id'] ?? ''))); }
  /* №634 · окно «Модели Фила»: роли, модели, ключи (только «есть / нет»), проверка, расход и дневной лимит */
  case 'fr_status': { kb_boss(); kb_out(fr_status()); }
  case 'fr_set':    { $who = kb_boss(); $r = fr_set(kb_in(), $who); kb_out($r, empty($r['ok']) ? 400 : 200); }
  case 'fr_key':    { $who = kb_boss(); $i = kb_in(); $r = fr_key_put((string)($i['prov'] ?? ''), (string)($i['key'] ?? ''), $who); kb_out($r, empty($r['ok']) ? 400 : 200); }
  case 'fr_test':   { $who = kb_boss(); @set_time_limit(300); kb_out(fr_test((string)(kb_in()['model'] ?? ''), $who)); }
  case 'prov_use':  {   // «Фил» думает этой моделью (или снова DeepSeek — id пустой). Знания те же — из базы знаний
    $who = kb_boss(); $id = (string)(kb_in()['id'] ?? '');
    if ($id !== '' && !isset(kb_prov_all()[$id])) kb_out(['ok' => false, 'error' => 'Нет такой модели'], 400);
    kb_sp_put('fil_model2', $id === '' ? '' : 'oa:' . $id, $who);
    kb_out(['ok' => true]);
  }
  }
  kb_out(['ok' => false, 'error' => 'Неизвестное действие'], 404);
} catch (Throwable $e) { error_log('kb.php: ' . $e->getMessage()); kb_out(['ok' => false, 'error' => 'Ошибка сервера: ' . $e->getMessage()], 500); }
