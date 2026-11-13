<?php
// ==== ИТК · mail_lib.php · №263 (07.11.2026) · Почта в CRM ИТК ====
// Ящики info@, zakaz@, business@ и личные почты сотрудников: входящие, отправленные, чтение, ответ, письмо в сделку.
// Работает напрямую с почтовым сервером (IMAP — чтение, SMTP — отправка) без модулей хостинга.
// Кто что видит: руководитель — все ящики; «Все сотрудники» — каждый сотрудник CRM; «Только владелец» — владелец и руководитель;
// «Только руководитель» — только руководитель (например business@). Пароли ящиков вводит владелец, хранятся зашифрованными
// ключом с сервера (mail_key.php рядом), в ответах никогда не возвращаются.

const ML_SRV = [   // почтовые серверы по домену: [imap, imap_port, smtp, smtp_port]
  'itcpartner.ru' => ['mail.hosting.reg.ru', 993, 'mail.hosting.reg.ru', 465],
  'itcpartner.store' => ['mail.hosting.reg.ru', 993, 'mail.hosting.reg.ru', 465],
  'mail.ru' => ['imap.mail.ru', 993, 'smtp.mail.ru', 465], 'bk.ru' => ['imap.mail.ru', 993, 'smtp.mail.ru', 465],
  'inbox.ru' => ['imap.mail.ru', 993, 'smtp.mail.ru', 465], 'list.ru' => ['imap.mail.ru', 993, 'smtp.mail.ru', 465],
  'yandex.ru' => ['imap.yandex.ru', 993, 'smtp.yandex.ru', 465], 'ya.ru' => ['imap.yandex.ru', 993, 'smtp.yandex.ru', 465],
  'gmail.com' => ['imap.gmail.com', 993, 'smtp.gmail.com', 465], 'qq.com' => ['imap.qq.com', 993, 'smtp.qq.com', 465],
  '163.com' => ['imap.163.com', 993, 'smtp.163.com', 465],
];
const ML_ACCESS = ['all' => 'Все сотрудники', 'owner' => 'Только владелец', 'boss' => 'Только руководитель'];

function ml_ensure() {
  static $ok = false; if ($ok) return; $ok = true;
  db()->exec("CREATE TABLE IF NOT EXISTS itc_mail_box (
    id VARCHAR(30) NOT NULL PRIMARY KEY, email VARCHAR(190) NOT NULL, name VARCHAR(190) NOT NULL DEFAULT '',
    host VARCHAR(190) NOT NULL DEFAULT '', port INT NOT NULL DEFAULT 993, smtp_host VARCHAR(190) NOT NULL DEFAULT '', smtp_port INT NOT NULL DEFAULT 465,
    login VARCHAR(190) NOT NULL DEFAULT '', pass_enc TEXT NULL, access VARCHAR(10) NOT NULL DEFAULT 'owner', owner_phone VARCHAR(40) NOT NULL DEFAULT '',
    owner_name VARCHAR(190) NOT NULL DEFAULT '', sort INT NOT NULL DEFAULT 100, last_ok DATETIME NULL, last_err VARCHAR(300) NOT NULL DEFAULT '',
    created_by VARCHAR(190) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, deleted TINYINT NOT NULL DEFAULT 0
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* ---------- шифрование паролей ящиков ---------- */
function ml_key() {
  static $k = null; if ($k !== null) return $k;
  $f = __DIR__ . '/mail_key.php';
  if (!is_file($f)) { @file_put_contents($f, "<?php // ключ шифрования паролей почты (создан сервером, не передавать)\nreturn '" . bin2hex(random_bytes(32)) . "';\n", LOCK_EX); @chmod($f, 0600); }
  $v = is_file($f) ? (include $f) : '';
  if (!is_string($v) || strlen($v) !== 64) throw new RuntimeException('Не получилось создать ключ шифрования почты');
  return $k = hex2bin($v);
}
function ml_enc($s) { $iv = random_bytes(12); $tag = ''; $c = openssl_encrypt($s, 'aes-256-gcm', ml_key(), OPENSSL_RAW_DATA, $iv, $tag); return base64_encode($iv . $tag . $c); }
function ml_dec($s) { $b = base64_decode((string)$s, true); if ($b === false || strlen($b) < 29) return ''; $r = openssl_decrypt(substr($b, 28), 'aes-256-gcm', ml_key(), OPENSSL_RAW_DATA, substr($b, 0, 12), substr($b, 12, 16)); return $r === false ? '' : $r; }

/* ---------- доступ ---------- */
function ml_can_see($me, $b) {
  if ($me['boss']) return true;
  if ($b['access'] === 'all') return true;
  if ($b['access'] === 'owner') return $b['owner_phone'] === $me['phone'];
  return false;
}
function ml_can_edit($me, $b) { return $me['boss'] || ($b['access'] === 'owner' && $b['owner_phone'] === $me['phone']); }
function ml_boxes($me) {
  ml_ensure();
  $rows = db()->query('SELECT * FROM itc_mail_box WHERE deleted=0 ORDER BY sort, email')->fetchAll();
  return array_values(array_filter($rows, function ($b) use ($me) { return ml_can_see($me, $b); }));
}
function ml_box($me, $id) {
  ml_ensure();
  $q = db()->prepare('SELECT * FROM itc_mail_box WHERE id=? AND deleted=0'); $q->execute([(string)$id]); $b = $q->fetch();
  if (!$b || !ml_can_see($me, $b)) throw new RuntimeException('Ящик не найден или нет доступа');
  return $b;
}
function ml_box_out($me, $b) {
  return ['id' => $b['id'], 'email' => $b['email'], 'name' => $b['name'], 'access' => $b['access'], 'access_txt' => ML_ACCESS[$b['access']] ?? '',
          'owner_name' => $b['owner_name'], 'owner_phone' => $b['owner_phone'], 'host' => $b['host'], 'port' => (int)$b['port'], 'smtp_host' => $b['smtp_host'], 'smtp_port' => (int)$b['smtp_port'],
          'login' => $b['login'], 'has_pass' => (string)$b['pass_enc'] !== '', 'last_ok' => $b['last_ok'], 'last_err' => $b['last_err'], 'edit' => ml_can_edit($me, $b)];
}
function ml_box_save($me, $a) {
  ml_ensure();
  $id = trim((string)($a['id'] ?? '')); $old = null;
  if ($id !== '') { $old = ml_box($me, $id); if (!ml_can_edit($me, $old)) throw new RuntimeException('Этот ящик настраивает руководитель'); }
  $email = mb_strtolower(trim((string)($a['email'] ?? ($old['email'] ?? ''))));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Впишите адрес почты, например info@itcpartner.ru');
  $dom = substr(strrchr($email, '@'), 1); $def = ML_SRV[$dom] ?? null;
  $host = trim((string)($a['host'] ?? '')) ?: ($old['host'] ?? '') ?: ($def[0] ?? '');
  $smtp = trim((string)($a['smtp_host'] ?? '')) ?: ($old['smtp_host'] ?? '') ?: ($def[2] ?? '');
  if ($host === '' || $smtp === '') throw new RuntimeException('Для этой почты сервер неизвестен — впишите серверы входящей и исходящей почты');
  foreach ([$host, $smtp] as $h) if (!preg_match('~^[a-z0-9.-]+\.[a-z]{2,}$~i', $h)) throw new RuntimeException('Сервер почты записан неверно: ' . $h);
  $access = (string)($a['access'] ?? ($old['access'] ?? 'owner')); if (!isset(ML_ACCESS[$access])) $access = 'owner';
  if (!$me['boss']) $access = 'owner';   // сотрудник подключает только свою почту
  $ownerPhone = $old['owner_phone'] ?? $me['phone']; $ownerName = $old['owner_name'] ?? $me['name'];
  if ($me['boss'] && isset($a['owner_phone']) && $a['owner_phone'] !== '') { $ownerPhone = (string)$a['owner_phone']; $ownerName = fl_staff_name($ownerPhone) ?: $ownerPhone; }
  $f = ['email' => $email, 'name' => fl_s($a['name'] ?? ($old['name'] ?? ''), 190), 'host' => $host, 'port' => (int)($a['port'] ?? ($old['port'] ?? ($def[1] ?? 993))) ?: 993,
        'smtp_host' => $smtp, 'smtp_port' => (int)($a['smtp_port'] ?? ($old['smtp_port'] ?? ($def[3] ?? 465))) ?: 465,
        'login' => trim((string)($a['login'] ?? '')) ?: ($old['login'] ?? '') ?: $email, 'access' => $access, 'owner_phone' => $ownerPhone, 'owner_name' => $ownerName];
  $pass = (string)($a['pass'] ?? '');
  if ($pass !== '') $f['pass_enc'] = ml_enc($pass);
  elseif (!$old) throw new RuntimeException('Впишите пароль от ящика');
  if ($old) {
    $set = []; $v = []; foreach ($f as $k => $x) { $set[] = "$k=?"; $v[] = $x; } $v[] = $id;
    db()->prepare('UPDATE itc_mail_box SET ' . implode(',', $set) . ' WHERE id=?')->execute($v);
  } else {
    $q = db()->prepare('SELECT id FROM itc_mail_box WHERE email=? AND deleted=0'); $q->execute([$email]);
    if ($q->fetchColumn()) throw new RuntimeException('Этот ящик уже подключён');
    $id = fl_id('mb'); $f['id'] = $id; $f['created_by'] = $me['name'];
    db()->prepare('INSERT INTO itc_mail_box (' . implode(',', array_keys($f)) . ') VALUES (' . implode(',', array_fill(0, count($f), '?')) . ')')->execute(array_values($f));
  }
  return $id;
}

/* ---------- IMAP: свой небольшой клиент (без модуля imap на хостинге) ---------- */
class MlImap {
  private $s; private $n = 0; public $err = '';
  function __construct($host, $port) {
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $this->s = @stream_socket_client(($port == 143 ? 'tcp://' : 'ssl://') . $host . ':' . $port, $en, $es, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$this->s) throw new RuntimeException('Не удалось подключиться к почтовому серверу ' . $host . ($es ? ' (' . $es . ')' : ''));
    stream_set_timeout($this->s, 40);
    $hello = fgets($this->s);
    if ($hello === false || stripos($hello, '* OK') !== 0) throw new RuntimeException('Почтовый сервер не ответил');
  }
  function lit($s) { return preg_match('~[^\x20-\x7e]~', $s) ? '{' . strlen($s) . "+}\r\n" . $s : $this->q($s); }
  function q($s) { return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $s) . '"'; }
  /* команда → [строки ответа с подставленными литералами, статус] */
  function cmd($c) {
    $t = 'A' . (++$this->n); fwrite($this->s, $t . ' ' . $c . "\r\n");
    $out = []; $cur = ''; $lits = [];
    while (true) {
      $line = fgets($this->s);
      if ($line === false) throw new RuntimeException('Почтовый сервер оборвал связь');
      if (preg_match('~\{(\d+)\}\r?\n$~', $line, $m)) {
        $cur .= substr($line, 0, -strlen($m[0]));
        $need = (int)$m[1]; $lit = '';
        while (strlen($lit) < $need) { $ch = fread($this->s, min(65536, $need - strlen($lit))); if ($ch === false || $ch === '') throw new RuntimeException('Почтовый сервер оборвал связь'); $lit .= $ch; }
        $cur .= "\x00" . count($lits) . "\x00"; $lits[] = $lit;
        continue;
      }
      $cur .= $line;
      if (strpos($cur, $t . ' ') === 0) {
        $ok = (bool)preg_match('~^' . $t . ' OK~i', $cur);
        if (!$ok) $this->err = trim(substr($cur, strlen($t) + 1));
        return [$out, $ok, $cur];
      }
      $out[] = ['line' => rtrim($cur, "\r\n"), 'lits' => $lits]; $cur = ''; $lits = [];
    }
  }
  function login($u, $p) {
    [, $ok] = $this->cmd('LOGIN ' . $this->q($u) . ' ' . $this->q($p));
    if (!$ok) throw new RuntimeException('Почта не приняла логин или пароль' . ($this->err ? ' (' . mb_substr($this->err, 0, 120) . ')' : ''));
  }
  function close() { if ($this->s) { @fwrite($this->s, "Z LOGOUT\r\n"); @fclose($this->s); $this->s = null; } }
}
function ml_utf7_dec($s) { if (preg_match('~[^\x20-\x7e]~', $s) || strpos($s, '&') === false) return $s; return function_exists('mb_convert_encoding') ? (@mb_convert_encoding($s, 'UTF-8', 'UTF7-IMAP') ?: $s) : $s; }
function ml_utf7_enc($s) { return function_exists('mb_convert_encoding') ? (@mb_convert_encoding($s, 'UTF7-IMAP', 'UTF-8') ?: $s) : $s; }
function ml_open($b) {
  $pass = ml_dec($b['pass_enc']);
  if ($pass === '') throw new RuntimeException('У ящика не сохранён пароль — впишите его в настройках почты');
  $c = new MlImap($b['host'], (int)$b['port']);
  try { $c->login($b['login'] ?: $b['email'], $pass); db()->prepare('UPDATE itc_mail_box SET last_ok=NOW(), last_err=\'\' WHERE id=?')->execute([$b['id']]); }
  catch (Throwable $e) { db()->prepare('UPDATE itc_mail_box SET last_err=? WHERE id=?')->execute([mb_substr($e->getMessage(), 0, 300), $b['id']]); $c->close(); throw $e; }
  return $c;
}
/* папки: входящие, отправленные, спам, корзина — по флагам сервера или по названию */
function ml_folders($c) {
  [$r] = $c->cmd('LIST "" "*"'); $f = [];
  foreach ($r as $x) {
    $l = preg_replace_callback('~\x00(\d+)\x00~', function ($m) use ($x) { return '"' . addcslashes($x['lits'][(int)$m[1]], '"\\') . '"'; }, $x['line']);
    if (!preg_match('~^\* LIST \(([^)]*)\) (?:"[^"]*"|NIL) (.+)$~i', trim($l), $m)) continue;
    $raw = trim($m[2]); if ($raw !== '' && $raw[0] === '"') $raw = stripcslashes(substr($raw, 1, -1));
    if (stripos($m[1], '\\Noselect') !== false) continue;
    $name = ml_utf7_dec($raw); $lc = mb_strtolower($name); $fl = strtolower($m[1]);
    $kind = strtoupper($raw) === 'INBOX' ? 'inbox' : (strpos($fl, '\\sent') !== false || preg_match('~sent|отправлен~u', $lc) ? 'sent'
          : (strpos($fl, '\\junk') !== false || preg_match('~spam|junk|спам~u', $lc) ? 'spam' : (strpos($fl, '\\trash') !== false || preg_match('~trash|корзин|удал~u', $lc) ? 'trash'
          : (strpos($fl, '\\drafts') !== false || preg_match('~draft|черновик~u', $lc) ? 'drafts' : 'other'))));
    $f[] = ['raw' => $raw, 'name' => $name, 'kind' => $kind];
  }
  $title = ['inbox' => 'Входящие', 'sent' => 'Отправленные', 'spam' => 'Спам', 'trash' => 'Корзина', 'drafts' => 'Черновики'];
  foreach ($f as &$x) $x['title'] = $title[$x['kind']] ?? $x['name']; unset($x);
  usort($f, function ($a, $b) { $o = ['inbox' => 0, 'sent' => 1, 'drafts' => 2, 'spam' => 3, 'trash' => 4, 'other' => 5]; return [$o[$a['kind']], $a['name']] <=> [$o[$b['kind']], $b['name']]; });
  return $f;
}
function ml_folder_raw($c, $kindOrRaw) {
  if ($kindOrRaw === '' || $kindOrRaw === 'inbox') return 'INBOX';
  foreach (ml_folders($c) as $f) if ($f['kind'] === $kindOrRaw || $f['raw'] === $kindOrRaw) return $f['raw'];
  throw new RuntimeException('Папка не найдена');
}
function ml_select($c, $raw) { [$r, $ok] = $c->cmd('SELECT ' . $c->q($raw)); if (!$ok) throw new RuntimeException('Папку не открыть'); return $r; }

/* ---------- разбор письма (MIME) ---------- */
function ml_hdr_dec($s) {
  $s = preg_replace('~\?=\s+=\?~', '?==?', trim((string)$s));
  if (function_exists('iconv_mime_decode')) { $r = @iconv_mime_decode($s, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8'); if ($r !== false) return $r; }
  return mb_decode_mimeheader($s);
}
function ml_conv($s, $cs) {
  $cs = strtolower(trim((string)$cs, " \"'")); if ($cs === '' || $cs === 'utf-8' || $cs === 'us-ascii') return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'Windows-1251');
  if ($cs === 'gb2312' || $cs === 'gbk') $cs = 'GB18030';
  $r = @mb_convert_encoding($s, 'UTF-8', $cs); if ($r !== false && $r !== '') return $r;
  $r = @iconv($cs, 'UTF-8//IGNORE', $s); return $r === false ? $s : $r;
}
function ml_headers($raw) {
  $h = []; $raw = preg_replace("~\r?\n[ \t]+~", ' ', $raw);
  foreach (preg_split("~\r?\n~", $raw) as $l) if (preg_match('~^([A-Za-z0-9-]+):\s*(.*)$~', $l, $m)) { $k = strtolower($m[1]); $h[$k] = isset($h[$k]) ? $h[$k] . ', ' . $m[2] : $m[2]; }
  return $h;
}
function ml_param($v, $p) { if (preg_match('~' . $p . '\*?=\s*(?:"([^"]*)"|([^;\s]+))~i', $v, $m)) { $x = $m[1] !== '' ? $m[1] : $m[2]; if (preg_match("~^([\w-]+)''(.+)$~", $x, $q)) return ml_conv(rawurldecode($q[2]), $q[1]); return ml_hdr_dec($x); } return ''; }
function ml_parse($msg, &$out, $path = '1') {
  $sp = preg_split("~\r?\n\r?\n~", $msg, 2); $h = ml_headers($sp[0]); $body = $sp[1] ?? '';
  $ct = strtolower($h['content-type'] ?? 'text/plain'); $type = trim(explode(';', $ct)[0]);
  if (strpos($type, 'multipart/') === 0) {
    $bd = ml_param($h['content-type'] ?? '', 'boundary'); if ($bd === '') return;
    $parts = preg_split('~\r?\n?--' . preg_quote($bd, '~') . '(?:--)?[ \t]*\r?\n?~', $body);
    $i = 0; foreach ($parts as $p) { if (trim($p) === '') continue; $i++; ml_parse($p, $out, $path . '.' . $i); }
    return;
  }
  $enc = strtolower(trim($h['content-transfer-encoding'] ?? ''));
  $data = $enc === 'base64' ? base64_decode(preg_replace('~\s+~', '', $body)) : ($enc === 'quoted-printable' ? quoted_printable_decode($body) : $body);
  $disp = strtolower($h['content-disposition'] ?? '');
  $fname = ml_param($h['content-disposition'] ?? '', 'filename') ?: ml_param($h['content-type'] ?? '', 'name');
  if ($type === 'message/rfc822' && $fname === '') { ml_parse($data, $out, $path); return; }
  if (($type === 'text/plain' || $type === 'text/html') && $fname === '' && strpos($disp, 'attachment') === false) {
    $t = ml_conv($data, ml_param($h['content-type'] ?? '', 'charset'));
    if ($type === 'text/plain') $out['text'] .= ($out['text'] !== '' ? "\n" : '') . $t; else $out['html'] .= $t;
    return;
  }
  $cid = trim((string)($h['content-id'] ?? ''), '<> ');
  $out['att'][] = ['part' => $path, 'name' => $fname ?: ('файл' . count($out['att'])), 'type' => $type, 'size' => strlen($data), 'cid' => $cid, '_data' => $data];
}
function ml_addr_list($s) {   // «Иван <a@b.ru>, c@d.ru» → [[name,email],…]
  $o = []; $s = ml_hdr_dec($s);
  if (preg_match_all('~(?:"?([^"<,]*)"?\s*)?<([^>]+)>|([^\s<>,;"]+@[^\s<>,;"]+)~u', $s, $m, PREG_SET_ORDER))
    foreach ($m as $x) { $e = trim(($x[2] ?? '') !== '' ? $x[2] : ($x[3] ?? '')); if ($e !== '') $o[] = [trim($x[1] ?? ''), mb_strtolower($e)]; }
  return $o;
}
function ml_addr_txt($l) { return implode(', ', array_map(function ($a) { return $a[0] !== '' ? $a[0] . ' <' . $a[1] . '>' : $a[1]; }, $l)); }
/* безопасный HTML письма: без скриптов, форм, обработчиков; показывается в закрытой рамке */
function ml_clean_html($h) {
  $h = preg_replace('~<(script|style|iframe|object|embed|form|meta|link|base)\b[^>]*>.*?</\1>~is', '', $h);
  $h = preg_replace('~<(script|iframe|object|embed|form|meta|link|base|input|button)\b[^>]*/?>~i', '', $h);
  $h = preg_replace('~\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)~i', '', $h);
  $h = preg_replace('~(href|src)\s*=\s*(["\']?)\s*(javascript|vbscript|data:text)~i', '$1=$2#', $h);
  return $h;
}
function ml_text_of($r) {
  if (trim($r['text']) !== '') return trim($r['text']);
  $t = preg_replace('~<(br|/p|/div|/tr|/li|/h\d)\b[^>]*>~i', "\n", $r['html']);
  $t = html_entity_decode(strip_tags(preg_replace('~<(style|script)\b.*?</\1>~is', '', $t)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  return trim(preg_replace("~\n\s*\n\s*\n+~", "\n\n", preg_replace('~[ \t]+~', ' ', $t)));
}

/* ---------- список писем ---------- */
function ml_list($me, $a) {
  $b = ml_box($me, $a['box'] ?? ''); $c = ml_open($b);
  try {
    $raw = ml_folder_raw($c, (string)($a['folder'] ?? 'inbox')); ml_select($c, $raw);
    $qs = trim((string)($a['q'] ?? ''));
    $crit = 'ALL';
    if ($qs !== '') { $crit = 'OR OR FROM ' . $c->lit($qs) . ' SUBJECT ' . $c->lit($qs) . ' TO ' . $c->lit($qs); }
    if (!empty($a['unread'])) $crit = 'UNSEEN' . ($crit !== 'ALL' ? ' ' . $crit : '');
    [$r, $ok] = $c->cmd(($qs !== '' && preg_match('~[^\x20-\x7e]~', $qs) ? 'UID SEARCH CHARSET UTF-8 ' : 'UID SEARCH ') . $crit);
    if (!$ok && $qs !== '') throw new RuntimeException('Почтовый сервер не умеет искать по такому запросу — попробуйте латиницей или часть адреса');
    $uids = [];
    foreach ($r as $x) if (preg_match('~^\* SEARCH ?(.*)$~i', trim($x['line']), $m)) foreach (preg_split('~\s+~', trim($m[1])) as $u) if ($u !== '') $uids[] = (int)$u;
    rsort($uids); $total = count($uids);
    $per = 40; $page = max(0, (int)($a['page'] ?? 0)); $uids = array_slice($uids, $page * $per, $per);
    $items = [];
    if ($uids) {
      [$r] = $c->cmd('UID FETCH ' . implode(',', $uids) . ' (UID FLAGS INTERNALDATE RFC822.SIZE BODYSTRUCTURE BODY.PEEK[HEADER.FIELDS (FROM TO SUBJECT DATE)])');
      foreach ($r as $x) {
        if (empty($x['lits'])) continue;
        $pre = $x['line']; $x['lit'] = $x['lits'][0];
        if (!preg_match('~UID (\d+)~', $pre, $m)) continue;
        $uid = (int)$m[1]; preg_match('~FLAGS \(([^)]*)\)~', $pre, $fm); preg_match('~INTERNALDATE "([^"]+)"~', $pre, $dm); preg_match('~RFC822\.SIZE (\d+)~', $pre, $sm);
        $h = ml_headers($x['lit']); $from = ml_addr_list($h['from'] ?? ''); $to = ml_addr_list($h['to'] ?? '');
        $ts = strtotime($h['date'] ?? '') ?: strtotime($dm[1] ?? '') ?: time();
        $items[$uid] = ['uid' => $uid, 'subject' => ml_hdr_dec($h['subject'] ?? '') ?: '(без темы)', 'from' => $from[0][0] ?? '', 'from_email' => $from[0][1] ?? '',
                        'to' => ml_addr_txt($to), 'date' => date('Y-m-d H:i', $ts), 'seen' => stripos($fm[1] ?? '', '\\Seen') !== false,
                        'att' => (bool)preg_match('~"(attachment|application)~i', $pre), 'size' => (int)($sm[1] ?? 0)];
      }
    }
    $o = []; foreach ($uids as $u) if (isset($items[$u])) $o[] = $items[$u];
    return ['ok' => true, 'items' => $o, 'total' => $total, 'page' => $page, 'more' => ($page + 1) * $per < $total, 'folder' => (string)($a['folder'] ?? 'inbox')];
  } finally { $c->close(); }
}
function ml_fetch($c, $uid) {
  [$r, $ok] = $c->cmd('UID FETCH ' . (int)$uid . ' (UID FLAGS BODY.PEEK[])');
  foreach ($r as $x) if (!empty($x['lits'])) return [$x['lits'][0], $x['line']];
  throw new RuntimeException('Письмо не найдено — возможно, его уже удалили');
}
function ml_read($me, $a, $markSeen = true) {
  $b = ml_box($me, $a['box'] ?? ''); $c = ml_open($b);
  try {
    $raw = ml_folder_raw($c, (string)($a['folder'] ?? 'inbox')); ml_select($c, $raw);
    [$msg] = ml_fetch($c, $a['uid'] ?? 0);
    if ($markSeen) $c->cmd('UID STORE ' . (int)$a['uid'] . ' +FLAGS.SILENT (\\Seen)');
  } finally { $c->close(); }
  $sp = preg_split("~\r?\n\r?\n~", $msg, 2); $h = ml_headers($sp[0]);
  $r = ['text' => '', 'html' => '', 'att' => []]; ml_parse($msg, $r);
  $html = $r['html'];
  if ($html !== '') foreach ($r['att'] as $at) if ($at['cid'] !== '' && strpos($at['type'], 'image/') === 0 && $at['size'] < 3000000)
    $html = str_ireplace('cid:' . $at['cid'], 'data:' . $at['type'] . ';base64,' . base64_encode($at['_data']), $html);
  $from = ml_addr_list($h['from'] ?? '');
  $att = array_values(array_map(function ($x) { return ['part' => $x['part'], 'name' => $x['name'], 'type' => $x['type'], 'size' => $x['size']]; },
           array_filter($r['att'], function ($x) use ($r) { return !($x['cid'] !== '' && strpos($x['type'], 'image/') === 0 && stripos($r['html'], 'cid:' . $x['cid']) !== false); })));
  return ['ok' => true, 'box' => $b['id'], 'uid' => (int)$a['uid'], 'subject' => ml_hdr_dec($h['subject'] ?? '') ?: '(без темы)',
          'from' => $from[0][0] ?? '', 'from_email' => $from[0][1] ?? '', 'to' => ml_addr_txt(ml_addr_list($h['to'] ?? '')), 'cc' => ml_addr_txt(ml_addr_list($h['cc'] ?? '')),
          'reply_to' => ml_addr_txt(ml_addr_list($h['reply-to'] ?? '')), 'msgid' => trim($h['message-id'] ?? ''), 'refs' => trim($h['references'] ?? ''),
          'date' => date('Y-m-d H:i', strtotime($h['date'] ?? '') ?: time()), 'text' => ml_text_of($r), 'html' => $html !== '' ? ml_clean_html($html) : '', 'att' => $att];
}
function ml_att($me, $a) {
  $b = ml_box($me, $a['box'] ?? ''); $c = ml_open($b);
  try { $raw = ml_folder_raw($c, (string)($a['folder'] ?? 'inbox')); ml_select($c, $raw); [$msg] = ml_fetch($c, $a['uid'] ?? 0); } finally { $c->close(); }
  $r = ['text' => '', 'html' => '', 'att' => []]; ml_parse($msg, $r);
  foreach ($r['att'] as $x) if ($x['part'] === (string)($a['part'] ?? '')) return $x;
  throw new RuntimeException('Вложение не найдено');
}
function ml_unseen($me) {   // непрочитанные во «Входящих» по каждому ящику (для счётчика)
  $o = [];
  foreach (ml_boxes($me) as $b) {
    if ((string)$b['pass_enc'] === '') continue;
    try { $c = ml_open($b); [$r] = $c->cmd('STATUS INBOX (UNSEEN)'); $c->close();
          foreach ($r as $x) if (preg_match('~UNSEEN (\d+)~', $x['line'], $m)) $o[$b['id']] = (int)$m[1]; }
    catch (Throwable $e) { $o[$b['id']] = -1; }
  }
  return $o;
}

/* ---------- отправка (SMTP) ---------- */
function ml_smtp($b, $from, $rcpt, $data) {
  $pass = ml_dec($b['pass_enc']); if ($pass === '') throw new RuntimeException('У ящика не сохранён пароль');
  $port = (int)$b['smtp_port'];
  $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
  $s = @stream_socket_client(($port == 465 ? 'ssl://' : 'tcp://') . $b['smtp_host'] . ':' . $port, $en, $es, 20, STREAM_CLIENT_CONNECT, $ctx);
  if (!$s) throw new RuntimeException('Не удалось подключиться к серверу отправки ' . $b['smtp_host']);
  stream_set_timeout($s, 60);
  $rd = function () use ($s) { $o = ''; while (($l = fgets($s)) !== false) { $o .= $l; if (strlen($l) < 4 || $l[3] !== '-') break; } return $o; };
  $say = function ($c, $want) use ($s, $rd) { if ($c !== null) fwrite($s, $c . "\r\n"); $r = $rd(); if ((int)substr($r, 0, 3) !== $want) { fclose($s); throw new RuntimeException('Сервер отправки ответил: ' . mb_substr(trim($r), 0, 160)); } return $r; };
  $say(null, 220); $eh = $say('EHLO itcpartner.store', 250);
  if ($port != 465 && stripos($eh, 'STARTTLS') !== false) { $say('STARTTLS', 220); stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT); $say('EHLO itcpartner.store', 250); }
  $say('AUTH LOGIN', 334); $say(base64_encode($b['login'] ?: $b['email']), 334);
  try { $say(base64_encode($pass), 235); } catch (Throwable $e) { throw new RuntimeException('Сервер отправки не принял логин или пароль'); }
  $say('MAIL FROM:<' . $from . '>', 250);
  foreach ($rcpt as $r) { fwrite($s, 'RCPT TO:<' . $r . ">\r\n"); $x = $rd(); if (!in_array((int)substr($x, 0, 3), [250, 251], true)) { fclose($s); throw new RuntimeException('Адрес не принят: ' . $r); } }
  $say('DATA', 354);
  $data = preg_replace('~^\.~m', '..', str_replace(["\r\n", "\r"], ["\n", "\n"], $data));
  fwrite($s, str_replace("\n", "\r\n", $data) . "\r\n.\r\n"); $say(null, 250);
  @fwrite($s, "QUIT\r\n"); fclose($s);
}
function ml_hdr_enc($s) { return preg_match('~[^\x20-\x7e]~', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s; }
function ml_send($me, $a) {
  $b = ml_box($me, $a['box'] ?? '');
  $to = ml_addr_list((string)($a['to'] ?? '')); $cc = ml_addr_list((string)($a['cc'] ?? ''));
  if (!$to) throw new RuntimeException('Впишите, кому отправить письмо');
  foreach (array_merge($to, $cc) as $x) if (!filter_var($x[1], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Адрес записан неверно: ' . $x[1]);
  $subj = fl_s($a['subject'] ?? '', 250); $text = (string)($a['text'] ?? '');
  if (trim($text) === '' && empty($a['files'])) throw new RuntimeException('Письмо пустое');
  $name = $b['name'] !== '' ? $b['name'] : 'ИТК';
  $mid = '<' . bin2hex(random_bytes(8)) . '.' . time() . '@' . substr(strrchr($b['email'], '@'), 1) . '>';
  $hd = ['From: ' . ml_hdr_enc($name) . ' <' . $b['email'] . '>', 'To: ' . implode(', ', array_map(function ($x) { return ($x[0] !== '' ? ml_hdr_enc($x[0]) . ' ' : '') . '<' . $x[1] . '>'; }, $to))];
  if ($cc) $hd[] = 'Cc: ' . implode(', ', array_map(function ($x) { return '<' . $x[1] . '>'; }, $cc));
  $hd[] = 'Subject: ' . ml_hdr_enc($subj); $hd[] = 'Date: ' . date('r'); $hd[] = 'Message-ID: ' . $mid; $hd[] = 'MIME-Version: 1.0';
  if (!empty($a['in_reply_to'])) { $irt = preg_replace('~[\r\n]~', '', (string)$a['in_reply_to']); $hd[] = 'In-Reply-To: ' . $irt; $hd[] = 'References: ' . trim(preg_replace('~[\r\n]~', ' ', (string)($a['refs'] ?? '')) . ' ' . $irt); }
  $bodyTxt = "Content-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: base64\n\n" . chunk_split(base64_encode($text), 76, "\n");
  $files = is_array($a['files'] ?? null) ? $a['files'] : [];
  if ($files) {
    $bd = '=_itc' . bin2hex(random_bytes(8));
    $hd[] = 'Content-Type: multipart/mixed; boundary="' . $bd . '"';
    $body = '--' . $bd . "\n" . $bodyTxt;
    foreach ($files as $f) $body .= '--' . $bd . "\nContent-Type: " . ($f['type'] ?: 'application/octet-stream') . '; name="' . ml_hdr_enc($f['name']) . "\"\nContent-Transfer-Encoding: base64\nContent-Disposition: attachment; filename=\"" . ml_hdr_enc($f['name']) . "\"\n\n" . chunk_split(base64_encode($f['data']), 76, "\n");
    $body .= '--' . $bd . "--\n";
  } else { $hd[] = 'Content-Type: text/plain; charset=UTF-8'; $hd[] = 'Content-Transfer-Encoding: base64'; $body = chunk_split(base64_encode($text), 76, "\n"); }
  $data = implode("\n", $hd) . "\n\n" . $body;
  ml_smtp($b, $b['email'], array_map(function ($x) { return $x[1]; }, array_merge($to, $cc)), $data);
  // копия в «Отправленные» (если сервер сам не кладёт)
  try { $c = ml_open($b); $sent = ml_folder_raw($c, 'sent'); $d = str_replace("\n", "\r\n", $data);
        $c->cmd('APPEND ' . $c->q($sent) . ' (\\Seen) {' . strlen($d) . "}\r\n" . $d); $c->close(); } catch (Throwable $e) {}
  return ['ok' => true, 'to' => ml_addr_txt($to)];
}

/* ---------- письмо → сделка ---------- */
function ml_to_deal($me, $a) {
  $m = ml_read($me, $a, false);
  $txt = 'Письмо от ' . ($m['from'] !== '' ? $m['from'] . ' <' . $m['from_email'] . '>' : $m['from_email']) . ', ' . date('d.m.Y H:i', strtotime($m['date'])) . "\nТема: " . $m['subject'] . "\n\n" . mb_substr($m['text'], 0, 6000);
  if (!empty($m['att'])) $txt .= "\n\nВложения: " . implode(', ', array_map(function ($x) { return $x['name']; }, $m['att']));
  $dealId = trim((string)($a['deal_id'] ?? ''));
  if ($dealId !== '') { $d = fl_deal_get($me, $dealId); fl_log($d['id'], 'comment', $txt, $me['name']); db()->prepare('UPDATE itc_fl_deal SET updated_at=NOW() WHERE id=?')->execute([$d['id']]); return ['ok' => true, 'deal' => $d['id'], 'title' => $d['title'], 'new' => false]; }
  $pipes = array_values(array_filter(fl_pipes($me), function ($p) { return $p['kind'] === 'sales'; }));
  $pid = (string)($a['pipe'] ?? ''); $pipe = null; foreach (fl_pipes($me) as $p) if ($p['id'] === $pid) $pipe = $p; if (!$pipe) $pipe = $pipes[0] ?? null;
  if (!$pipe) throw new RuntimeException('Нет воронки продаж');
  $q = db()->prepare("SELECT id FROM itc_fl_stage WHERE pipe_id=? AND sem='work' ORDER BY sort, id LIMIT 1"); $q->execute([$pipe['id']]); $sid = (string)$q->fetchColumn();
  $dom = substr(strrchr($m['from_email'], '@'), 1);
  $client = $m['from'] !== '' ? $m['from'] : $m['from_email'];
  if ($dom !== '' && !preg_match('~^(mail|bk|inbox|list|yandex|ya|gmail|qq|163|126|outlook|hotmail|rambler|icloud)\.~', $dom)) $client .= ' (' . $dom . ')';
  $id = fl_id('d');
  db()->prepare('INSERT INTO itc_fl_deal (id,pipe_id,stage_id,title,client,contact,email,resp_phone,resp_name,note,created_by,created_phone,stage_at,source,sort) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?)')
    ->execute([$id, $pipe['id'], $sid, mb_substr($m['subject'], 0, 250), mb_substr($client, 0, 250), mb_substr($m['from'], 0, 190), mb_substr($m['from_email'], 0, 190), $me['phone'], $me['name'], mb_substr($m['text'], 0, 4000), $me['name'], $me['phone'], 'mail', -time()]);
  fl_log($id, 'comment', $txt, $me['name']);
  return ['ok' => true, 'deal' => $id, 'title' => $m['subject'], 'new' => true];
}

/* ---------- Фил и почта ---------- */
function ml_tools($me) {
  return [
    'mail_inbox' => ['{"box":"адрес ящика или пусто — все мои ящики","unread":true,"q":"поиск по отправителю или теме"} — ПОЧТА: последние письма во «Входящих» (кто, тема, когда, прочитано ли). «Что пришло на почту», «есть письма от …», «непрочитанные на zakaz»',
      function ($a) use ($me) {
        $bx = ml_boxes($me); $want = mb_strtolower(trim((string)($a['box'] ?? '')));
        if ($want !== '') $bx = array_values(array_filter($bx, function ($b) use ($want) { return mb_strpos($b['email'], $want) !== false || mb_strpos(mb_strtolower($b['name']), $want) !== false; }));
        if (!$bx) return 'почтовых ящиков нет или нет доступа — подключить можно в CRM ИТК → «Почта» → настройки';
        $o = [];
        foreach ($bx as $b) { try { $r = ml_list($me, ['box' => $b['id'], 'unread' => !empty($a['unread']), 'q' => (string)($a['q'] ?? '')]);
          foreach (array_slice($r['items'], 0, 12) as $m) $o[] = $b['email'] . ' · ' . $m['date'] . ' · ' . ($m['from'] ?: $m['from_email']) . ' · «' . $m['subject'] . '»' . ($m['seen'] ? '' : ' [не прочитано]') . ' · id ' . $b['id'] . ':' . $m['uid']; }
          catch (Throwable $e) { $o[] = $b['email'] . ': ' . $e->getMessage(); } }
        return $o ?: 'писем нет';
      }],
    'mail_read' => ['{"id":"id письма из mail_inbox (ящик:номер)"} — ПРОЧИТАТЬ письмо целиком: от кого, кому, текст, вложения',
      function ($a) use ($me) { [$bid, $uid] = array_pad(explode(':', (string)($a['id'] ?? '')), 2, 0); $m = ml_read($me, ['box' => $bid, 'uid' => (int)$uid]);
        return ['от' => trim($m['from'] . ' <' . $m['from_email'] . '>'), 'кому' => $m['to'], 'дата' => $m['date'], 'тема' => $m['subject'], 'текст' => mb_substr($m['text'], 0, 4000), 'вложения' => array_map(function ($x) { return $x['name']; }, $m['att'])]; }],
    'mail_send' => ['{"box":"с какого ящика (адрес)","to":"адрес","subject":"тема","text":"текст письма","reply_id":"id письма, если это ответ","confirm":false} — ОТПРАВИТЬ ПИСЬМО. Сначала вызывай с confirm:false — получишь черновик, ПРОЧИТАЙ его человеку и спроси «Отправить?». Только после явного «да» — тот же вызов с confirm:true',
      function ($a) use ($me) {
        $bx = ml_boxes($me); $want = mb_strtolower(trim((string)($a['box'] ?? ''))); $b = null;
        foreach ($bx as $x) if ($want === '' ? ($x['access'] === 'owner' && $x['owner_phone'] === $me['phone']) : mb_strpos($x['email'], $want) !== false) { $b = $x; break; }
        if (!$b && $bx && $want === '') $b = $bx[0];
        if (!$b) return 'не нашёл ящик для отправки';
        $p = ['box' => $b['id'], 'to' => (string)($a['to'] ?? ''), 'subject' => (string)($a['subject'] ?? ''), 'text' => (string)($a['text'] ?? '')];
        if (!empty($a['reply_id'])) { [$rb, $ru] = array_pad(explode(':', (string)$a['reply_id']), 2, 0); $m = ml_read($me, ['box' => $rb, 'uid' => (int)$ru], false);
          if ($p['to'] === '') $p['to'] = $m['reply_to'] ?: $m['from_email']; if ($p['subject'] === '') $p['subject'] = (preg_match('~^re:~i', $m['subject']) ? '' : 'Re: ') . $m['subject'];
          $p['in_reply_to'] = $m['msgid']; $p['refs'] = $m['refs']; }
        if (empty($a['confirm'])) return ['черновик' => ['с ящика' => $b['email'], 'кому' => $p['to'], 'тема' => $p['subject'], 'текст' => $p['text']], 'дальше' => 'прочитай черновик и спроси «Отправить?» — после «да» вызови снова с confirm:true'];
        $r = ml_send($me, $p); return 'отправил: ' . $r['to'];
      }],
  ];
}
const ML_RULES = "\nПОЧТА: «что пришло на почту / есть письма от … / непрочитанные» — mail_inbox, «прочитай письмо» — mail_read. Письмо отправляй ТОЛЬКО через черновик: mail_send с confirm:false → прочитай черновик → спроси «Отправить?» → после явного «да» тот же вызов с confirm:true. Никогда не отправляй без подтверждения.";
