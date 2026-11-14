<?php
/* №559 · «ФИЛ НА КОМПЬЮТЕРЕ»: программа на рабочем компьютере сотрудника (ставится вместе с ярлыком «Фил») — «руки» Фила в WeChat.
   Думает Фил (магазин), программа выполняет: читает переписку, забирает файлы (каталоги, прайсы), пишет поставщикам.
   Переписка ложится туда же, куда и WeCom: itc_wc_chat / itc_wc_msg (emp_id = pc:<устройство>) — дальше работает всё, что уже есть:
   перевод, поставщик, задачи закупщику, история поставщика (№555). Файлы — itc_fc_file сотрудника + история поставщика.

   Подключение компьютера: программа показывает 6 цифр → сотрудник говорит Филу «подключи компьютер 123456» (или вводит в настройках) →
   компьютер получает свой ключ. Ключ хранится только у компьютера; в базе — его отпечаток. Отключить — «отключи компьютер».

   Программа (без входа сотрудника, по ключу устройства):  ?action=pc_pair_start | pc_pair_check | pc_poll | pc_done | pc_file | pc_ocr
   Сотрудник (вход ИТК):                                    ?action=pc_pair_ok | pc_list | pc_revoke */

const PC_OPS = ['read', 'files', 'send', 'sessions'];

function pc_ensure() {
  static $d = false; if ($d) return; $d = true;
  db()->exec("CREATE TABLE IF NOT EXISTS itc_pc_dev (
    id VARCHAR(40) NOT NULL PRIMARY KEY, phone VARCHAR(40) NOT NULL DEFAULT '', name VARCHAR(190) NOT NULL DEFAULT '',
    tok VARCHAR(64) NOT NULL DEFAULT '', code VARCHAR(8) NOT NULL DEFAULT '', psec VARCHAR(64) NOT NULL DEFAULT '', code_exp DATETIME NULL,
    wx_ver VARCHAR(40) NOT NULL DEFAULT '', backend VARCHAR(20) NOT NULL DEFAULT '', app_ver VARCHAR(20) NOT NULL DEFAULT '', last_seen DATETIME NULL,
    revoked TINYINT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_pc_phone (phone)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  db()->exec("CREATE TABLE IF NOT EXISTS itc_pc_job (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, dev_id VARCHAR(40) NOT NULL, phone VARCHAR(40) NOT NULL DEFAULT '', op VARCHAR(20) NOT NULL,
    args TEXT NULL, status VARCHAR(10) NOT NULL DEFAULT 'queued', result MEDIUMTEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    taken_at DATETIME NULL, done_at DATETIME NULL, KEY idx_pc_job (dev_id, status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function pc_in() { static $j = null; if ($j === null) { $j = json_decode((string)file_get_contents('php://input'), true); if (!is_array($j)) $j = []; } return $j; }
function pc_out($a, $code = 200) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function pc_dev() {   // устройство по ключу (заголовок X-PC-Token)
  pc_ensure(); $t = (string)($_SERVER['HTTP_X_PC_TOKEN'] ?? '');
  if (strlen($t) < 32) pc_out(['ok' => false, 'error' => 'нет ключа компьютера'], 403);
  $q = db()->prepare('SELECT * FROM itc_pc_dev WHERE tok=? AND revoked=0'); $q->execute([hash('sha256', $t)]); $d = $q->fetch();
  if (!$d) pc_out(['ok' => false, 'error' => 'компьютер отключён — подключите заново', 'repair' => true], 403);
  return $d;
}
function pc_me($dev) {   // сотрудник, чей это компьютер
  foreach (fl_staff() as $s) if ($s['phone'] === $dev['phone']) return ['role' => $s['role'], 'boss' => ($s['role'] === 'boss' || $s['role'] === ''), 'phone' => $s['phone'], 'name' => $s['name']];
  return ['role' => 'staff', 'boss' => false, 'phone' => $dev['phone'], 'name' => $dev['phone']];
}

/* ---- вызовы программы (до входа сотрудника) ---- */
function pc_pre($action) {
  if (strpos($action, 'pc_') !== 0 || in_array($action, ['pc_pair_ok', 'pc_list', 'pc_revoke'], true)) return;
  fl_ensure(); wc_ensure(); pc_ensure(); $in = pc_in();
  if ($action === 'pc_pair_start') {
    $id = fl_id('pc'); $code = (string)random_int(100000, 999999); $sec = bin2hex(random_bytes(16));
    db()->prepare('INSERT INTO itc_pc_dev (id,name,code,psec,code_exp,wx_ver,app_ver) VALUES (?,?,?,?,DATE_ADD(NOW(), INTERVAL 15 MINUTE),?,?)')
      ->execute([$id, mb_substr((string)($in['name'] ?? 'компьютер'), 0, 190), $code, hash('sha256', $sec), mb_substr((string)($in['wx_ver'] ?? ''), 0, 40), mb_substr((string)($in['app_ver'] ?? ''), 0, 20)]);
    pc_out(['ok' => true, 'pair' => $id, 'secret' => $sec, 'code' => $code, 'say' => 'Скажите Филу: «подключи компьютер ' . $code . '» — или введите код в настройках Фила. Код действует 15 минут.']);
  }
  if ($action === 'pc_pair_check') {
    $q = db()->prepare('SELECT * FROM itc_pc_dev WHERE id=? AND psec=?'); $q->execute([(string)($in['pair'] ?? ''), hash('sha256', (string)($in['secret'] ?? ''))]); $d = $q->fetch();
    if (!$d) pc_out(['ok' => false, 'error' => 'нет такого подключения'], 404);
    if ($d['phone'] === '') pc_out(['ok' => true, 'wait' => true, 'expired' => strtotime((string)$d['code_exp']) < time()]);
    if ($d['tok'] !== '') pc_out(['ok' => false, 'error' => 'ключ уже выдан'], 409);
    $tok = bin2hex(random_bytes(32));
    db()->prepare("UPDATE itc_pc_dev SET tok=?, psec='', code='' WHERE id=?")->execute([hash('sha256', $tok), $d['id']]);
    pc_out(['ok' => true, 'token' => $tok, 'who' => fl_staff_name($d['phone']) ?: $d['phone']]);
  }
  $dev = pc_dev();
  db()->prepare('UPDATE itc_pc_dev SET last_seen=NOW(), wx_ver=IF(?<>\'\',?,wx_ver), backend=IF(?<>\'\',?,backend), app_ver=IF(?<>\'\',?,app_ver) WHERE id=?')
    ->execute([$v1 = mb_substr((string)($in['wx_ver'] ?? ''), 0, 40), $v1, $v2 = mb_substr((string)($in['backend'] ?? ''), 0, 20), $v2, $v3 = mb_substr((string)($in['app_ver'] ?? ''), 0, 20), $v3, $dev['id']]);
  if ($action === 'pc_poll') {   // следующее поручение (взятое и брошенное больше 20 минут назад — заново)
    db()->prepare("UPDATE itc_pc_job SET status='queued' WHERE dev_id=? AND status='taken' AND taken_at < NOW() - INTERVAL 20 MINUTE")->execute([$dev['id']]);
    $q = db()->prepare("SELECT * FROM itc_pc_job WHERE dev_id=? AND status='queued' ORDER BY id LIMIT 1"); $q->execute([$dev['id']]); $j = $q->fetch();
    if (!$j) pc_out(['ok' => true, 'job' => null, 'wait' => 20]);
    db()->prepare("UPDATE itc_pc_job SET status='taken', taken_at=NOW() WHERE id=?")->execute([$j['id']]);
    pc_out(['ok' => true, 'job' => ['id' => (int)$j['id'], 'op' => $j['op'], 'args' => json_decode((string)$j['args'], true) ?: []]]);
  }
  if ($action === 'pc_ocr') {   // снимок окна WeChat → текст (Яндекс Vision), для режима «по экрану»
    $bin = base64_decode((string)($in['png'] ?? ''), true); if (!$bin) pc_out(['ok' => false, 'error' => 'нет снимка'], 400);
    $err = ''; $t = function_exists('ff_ocr') ? ff_ocr($bin, 'image/png', $err) : null;
    pc_out(['ok' => $t !== null, 'text' => (string)$t, 'error' => $err]);
  }
  if ($action === 'pc_file') {   // файл из WeChat (каталог, прайс, фото) → файлы сотрудника + история поставщика
    $me = pc_me($dev); $name = mb_substr(trim((string)($in['name'] ?? 'файл')), 0, 250) ?: 'файл';
    $bin = base64_decode((string)($in['data'] ?? ''), true); if (!$bin) pc_out(['ok' => false, 'error' => 'нет файла'], 400);
    if (strlen($bin) > 20 * 1048576) pc_out(['ok' => false, 'error' => 'больше 20 МБ'], 413);
    $r = pc_save_file($me, $name, $bin, (string)($in['chat'] ?? ''), (string)($in['at'] ?? ''));
    pc_out(['ok' => true] + $r);
  }
  if ($action === 'pc_done') {
    $id = (int)($in['job'] ?? 0); $me = pc_me($dev);
    $q = db()->prepare('SELECT * FROM itc_pc_job WHERE id=? AND dev_id=?'); $q->execute([$id, $dev['id']]); $j = $q->fetch();
    if (!$j) pc_out(['ok' => false, 'error' => 'нет такого поручения'], 404);
    $res = ['ok' => !empty($in['ok']), 'error' => mb_substr((string)($in['error'] ?? ''), 0, 500)];
    if (!empty($in['chats'])) $res += pc_ingest($dev, $me, (array)$in['chats']);
    if (isset($in['sessions'])) $res['sessions'] = array_slice(array_map('strval', (array)$in['sessions']), 0, 200);
    if (isset($in['sent'])) $res['sent'] = (bool)$in['sent'];
    if (isset($in['files'])) $res['files'] = (int)$in['files'];
    db()->prepare('UPDATE itc_pc_job SET status=?, result=?, done_at=NOW() WHERE id=?')->execute([$res['ok'] ? 'done' : 'failed', json_encode($res, JSON_UNESCAPED_UNICODE), $id]);
    if ($j['op'] !== 'sessions') try {   // сотруднику — уведомление, что Фил закончил работу в WeChat
      $txt = $res['ok'] ? ($j['op'] === 'send' ? 'Сообщение отправлено в WeChat' : 'WeChat прочитан: чатов ' . ($res['chats'] ?? 0) . ', новых сообщений ' . ($res['new'] ?? 0) . ', файлов ' . ($res['files'] ?? 0))
                        : 'Не получилось в WeChat: ' . $res['error'];
      if (function_exists('fl_push')) fl_push([$me['phone']], 'Фил на компьютере', $txt, '/apps/fil/');
      db()->prepare('INSERT INTO itc_fc_log (phone,who,said,reply,kind,deal_id) VALUES (?,?,?,?,?,?)')->execute([$me['phone'], 'Фил на компьютере', 'WeChat: ' . $j['op'], $txt, 'pc', '']);
    } catch (Throwable $e) {}
    pc_out(['ok' => true]);
  }
  pc_out(['ok' => false, 'error' => 'неизвестное действие'], 404);
}

/* переписка с компьютера → те же таблицы, что WeCom: перевод, поставщик, задачи — уже работают */
function pc_ingest($dev, $me, array $chats) {
  $n = 0; $touched = []; $cnt = 0;
  foreach (array_slice($chats, 0, 60) as $c) {
    $title = mb_substr(trim((string)($c['name'] ?? '')), 0, 190); if ($title === '') continue; $cnt++;
    $cid = wc_chat_for($dev['phone'], $title, $title, '', 'pc:' . $dev['id']);
    foreach (array_slice((array)($c['msgs'] ?? []), -300) as $m) {
      $text = mb_substr((string)($m['text'] ?? ''), 0, 20000); $dir = ($m['dir'] ?? '') === 'out' ? 'out' : 'in';
      $at = preg_match('~^\d{4}-\d{2}-\d{2} \d{2}:\d{2}~', (string)($m['at'] ?? '')) ? substr((string)$m['at'], 0, 19) : date('Y-m-d H:i:s');
      $type = in_array($m['type'] ?? 'text', ['text', 'file', 'image', 'voice', 'video', 'link', 'other'], true) ? $m['type'] : 'text';
      $mid = 'p' . substr(hash('sha256', $dev['id'] . '|' . $title . '|' . $dir . '|' . $text . '|' . substr($at, 0, 16)), 0, 40);   // одно и то же не задвоится
      if (wc_msg_add($cid, $mid, $dir, $dir === 'out' ? $me['name'] : (string)($m['who'] ?? $title), $type, $text, '', $at)) { $n++; if ($dir === 'in') $touched[$cid] = 1; }
    }
  }
  $done = 0; foreach (array_keys($touched) as $cid) { if ($done >= 8) break; try { wc_process($cid, true); } catch (Throwable $e) {} $done++; }
  return ['chats' => $cnt, 'new' => $n, 'processed' => $done];
}
function pc_save_file($me, $name, $bin, $chat, $at) {
  ff_ensure(); ff_cols(); $id = fl_id('ff'); $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  $kind = 'файл из WeChat'; $text = '';
  if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) { $err = ''; $text = (string)ff_ocr($bin, $ext === 'png' ? 'image/png' : 'image/jpeg', $err); $kind = 'фото из WeChat'; }
  elseif ($ext === 'docx') $text = (string)ff_docx($bin);
  elseif ($ext === 'xlsx') $text = (string)ff_xlsx($bin);
  elseif (function_exists('ff_convert')) { $cv = ff_convert($name, $bin); $text = (string)($cv['text'] ?? ''); }
  $text = trim(mb_substr($text, 0, 200000));
  $desc = 'Файл «' . $name . '» из WeChat' . ($chat !== '' ? ', чат «' . $chat . '»' : '') . ($at !== '' ? ', ' . $at : '') . ($text !== '' ? ":\n" . $text : ' (текста нет)');
  db()->prepare('INSERT INTO itc_fc_file (id,phone,name,kind,text,chars) VALUES (?,?,?,?,?,?)')->execute([$id, $me['phone'], $name, $kind, $desc, mb_strlen($desc)]);
  $sx = preg_match('~^[a-z0-9]{1,6}$~', $ext) && !preg_match('~^(php\d?|phtml|phar|pht|cgi|pl|py|sh|exe|bat|cmd|js|htaccess|html?|svgz?)$~', $ext) ? $ext : '';
  if ($sx !== '' && @file_put_contents(ff_dir() . "/$id.$sx", $bin) !== false)
    db()->prepare('UPDATE itc_fc_file SET path=?,mime=?,bytes=? WHERE id=?')->execute(["$id.$sx", 'application/octet-stream', strlen($bin), $id]);
  $sup = '';   // чат привязан к поставщику — файл сразу в его историю
  if ($chat !== '') try { $q = db()->prepare("SELECT sup_id FROM itc_wc_chat WHERE title=? AND emp_phone=? AND sup_id<>'' LIMIT 1"); $q->execute([$chat, $me['phone']]); $sup = (string)$q->fetchColumn(); } catch (Throwable $e) {}
  if ($sup === '' && $chat !== '' && function_exists('kp_find_sup')) {   // чат ещё не привязан — ищем поставщика по названию чата (одно совпадение — берём)
    foreach (array_merge([$chat], preg_split('~[\s,.()·|/_-]+~u', $chat)) as $w) {
      if (mb_strlen($w) < 4) continue; $f = kp_find_sup($w);
      if (count($f) === 1) { $sup = (string)$f[0]['id']; try { db()->prepare("UPDATE itc_wc_chat SET sup_id=? WHERE title=? AND emp_phone=? AND sup_id=''")->execute([$sup, $chat, $me['phone']]); } catch (Throwable $e) {} break; }
    }
  }
  if ($sup !== '' && function_exists('sh_add')) try { sh_add($me, ['sup' => $sup, 'kind' => preg_match('~(прайс|price|报价|价格)~iu', $name . ' ' . $text) ? 'цены' : 'каталог', 'text' => 'Из WeChat: ' . $name, 'files' => [$id], 'at' => $at, 'source' => 'wechat']); } catch (Throwable $e) {}
  return ['id' => $id, 'sup' => $sup];
}

/* ---- сотрудник (вход ИТК) ---- */
function pc_staff($me, $action) {
  pc_ensure();
  if ($action === 'pc_pair_ok') fl_out(pc_pair_ok($me, (string)fl_in('code')));
  if ($action === 'pc_list') fl_out(['ok' => true, 'devs' => pc_devs($me), 'jobs' => pc_jobs($me, 15)]);
  if ($action === 'pc_revoke') { db()->prepare('UPDATE itc_pc_dev SET revoked=1 WHERE id=? AND (phone=? OR ?=1)')->execute([(string)fl_in('id'), $me['phone'], !empty($me['boss']) ? 1 : 0]); fl_out(['ok' => true]); }
}
function pc_pair_ok($me, $code) {
  $code = preg_replace('~\D~', '', $code); if (strlen($code) !== 6) return ['ok' => false, 'error' => 'Код — 6 цифр с экрана компьютера'];
  $q = db()->prepare("SELECT id FROM itc_pc_dev WHERE code=? AND phone='' AND code_exp > NOW() AND revoked=0"); $q->execute([$code]); $id = $q->fetchColumn();
  if (!$id) return ['ok' => false, 'error' => 'Код не найден или устарел — на компьютере нажмите «Получить новый код»'];
  db()->prepare('UPDATE itc_pc_dev SET phone=? WHERE id=?')->execute([$me['phone'], $id]);
  return ['ok' => true, 'say' => 'Компьютер подключён: теперь Фил может читать ваш WeChat и забирать каталоги'];
}
function pc_devs($me) {
  $q = db()->prepare('SELECT id,name,wx_ver,backend,app_ver,last_seen,created_at FROM itc_pc_dev WHERE phone=? AND revoked=0 AND tok<>\'\' ORDER BY last_seen DESC'); $q->execute([$me['phone']]);
  return array_map(function ($d) { $d['online'] = $d['last_seen'] && strtotime((string)$d['last_seen']) > time() - 120; return $d; }, $q->fetchAll());
}
function pc_jobs($me, $n) {
  $q = db()->prepare('SELECT id,op,args,status,result,created_at,done_at FROM itc_pc_job WHERE phone=? ORDER BY id DESC LIMIT ' . (int)$n); $q->execute([$me['phone']]);
  return array_map(function ($j) { $j['args'] = json_decode((string)$j['args'], true); $j['result'] = json_decode((string)$j['result'], true); return $j; }, $q->fetchAll());
}
function pc_job_add($me, $op, $args) {
  pc_ensure(); $devs = array_values(array_filter(pc_devs($me), function ($d) { return $d['online']; })) ?: pc_devs($me);
  if (!$devs) return 'К WeChat нет доступа: компьютер не подключён. Установите «Фила» на компьютер (itcpartner.store/app/pc/) и скажите мне код с экрана: «подключи компьютер 123456».';
  db()->prepare('INSERT INTO itc_pc_job (dev_id,phone,op,args) VALUES (?,?,?,?)')->execute([$devs[0]['id'], $me['phone'], $op, json_encode($args, JSON_UNESCAPED_UNICODE)]);
  $id = (int)db()->lastInsertId();
  return ['принято' => 'поручение компьютеру №' . $id, 'компьютер' => $devs[0]['name'] . ($devs[0]['online'] ? ' (на связи)' : ' (сейчас не на связи — выполнит, когда включится)'),
          'как ответить' => 'скажи, что компьютер начал работу в WeChat; когда закончит — придёт уведомление, а переписка и файлы будут в базе (chats, sup_history); пусть потом попросят «сделай анализ»'];
}
function pc_tools($me) {
  return [
    'pc_pair' => ['{"code":"6 цифр с экрана компьютера"} — ПОДКЛЮЧИТЬ КОМПЬЮТЕР сотрудника (программа «Фил на компьютере» показывает код)',
      function ($a) use ($me) { return pc_pair_ok($me, (string)($a['code'] ?? '')); }],
    'wechat_read' => ['{"chats":["имена чатов/поставщиков — или пусто: все, где есть новое"],"days":1} — ПРОЧИТАТЬ WeChat на компьютере сотрудника: новые сообщения (с переводом, поставщиком, задачами) и файлы — в базу. Работает в фоне, итог — уведомлением; потом анализ — chats / sup_history',
      function ($a) use ($me) { return pc_job_add($me, 'read', ['chats' => array_slice(array_map('strval', (array)($a['chats'] ?? [])), 0, 30), 'days' => max(1, min(14, (int)($a['days'] ?? 1)))]); }],
    'wechat_files' => ['{"days":7} — ЗАБРАТЬ ФАЙЛЫ из WeChat компьютера (каталоги, прайсы, фото за N дней) в базу; к поставщику — если чат с ним',
      function ($a) use ($me) { return pc_job_add($me, 'files', ['days' => max(1, min(60, (int)($a['days'] ?? 7)))]); }],
    'wechat_send' => ['{"chat":"имя чата в WeChat","text":"текст на языке поставщика (китайский)","ru":"перевод для сотрудника"} — НАПИСАТЬ поставщику в WeChat с компьютера сотрудника. ТОЛЬКО ПОСЛЕ ПОДТВЕРЖДЕНИЯ: сначала покажи сотруднику текст и перевод и спроси «отправить?» (do=ask); вызывай, когда он сказал «да»',
      function ($a) use ($me) {
        $t = trim((string)($a['text'] ?? '')); $c = trim((string)($a['chat'] ?? ''));
        if ($t === '' || $c === '') return 'нужны chat и text';
        return pc_job_add($me, 'send', ['chat' => mb_substr($c, 0, 190), 'text' => mb_substr($t, 0, 3000), 'ru' => mb_substr((string)($a['ru'] ?? ''), 0, 3000)]);
      }],
    'pc_unpair' => ['{} — ОТКЛЮЧИТЬ КОМПЬЮТЕР сотрудника от Фила (ключ компьютера перестаёт работать; подключить снова — новым кодом)',
      function ($a) use ($me) { pc_ensure(); $q = db()->prepare('UPDATE itc_pc_dev SET revoked=1 WHERE phone=? AND revoked=0'); $q->execute([$me['phone']]); return ['отключено компьютеров' => $q->rowCount()]; }],
    'wechat_status' => ['{} — КОМПЬЮТЕР СОТРУДНИКА: подключён ли, на связи ли, версия WeChat, последние поручения и их итог',
      function ($a) use ($me) { pc_ensure(); return ['компьютеры' => pc_devs($me), 'поручения' => array_map(function ($j) { return ['№' => $j['id'], 'что' => $j['op'], 'статус' => $j['status'], 'итог' => $j['result']]; }, pc_jobs($me, 6))]; }],
  ];
}
