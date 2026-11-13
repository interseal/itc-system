<?php
// ==== ИТК · mail_spam.php · №491 (12.11.2026) · Фильтр спама почты CRM ИТК ====
// Новые письма во «Входящих» каждого подключённого ящика раз в несколько минут проверяются и, если это спам,
// переносятся в папку «Спам» прямо на почтовом сервере (тогда и с телефона картина та же).
// Порядок решения: свой домен и «не спам»-правила → оставить; «спам»-правила → в спам; адреса клиентов,
// контактов и сделок CRM → оставить; пометка почтового сервера «спам» → в спам; остальные незнакомые
// письма проверяет ИИ. Если ИИ недоступен — в спам уходят только массовые рассылки незнакомых отправителей.
// Кнопки «Это спам» / «Не спам» переносят письмо и запоминают отправителя (правило).

function msp_ensure() {
  static $ok = false; if ($ok) return; $ok = true; ml_ensure();
  db()->exec("CREATE TABLE IF NOT EXISTS itc_mail_rule (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, pat VARCHAR(190) NOT NULL, kind VARCHAR(5) NOT NULL DEFAULT 'spam', note VARCHAR(190) NOT NULL DEFAULT '',
    hits INT NOT NULL DEFAULT 0, created_by VARCHAR(190) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_mr_pat (pat)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  db()->exec("CREATE TABLE IF NOT EXISTS itc_mail_state (
    box_id VARCHAR(30) NOT NULL PRIMARY KEY, uidv VARCHAR(30) NOT NULL DEFAULT '', last_uid INT NOT NULL DEFAULT 0, at DATETIME NULL, err VARCHAR(300) NOT NULL DEFAULT ''
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  db()->exec("CREATE TABLE IF NOT EXISTS itc_mail_spamlog (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, box_id VARCHAR(30) NOT NULL, from_email VARCHAR(190) NOT NULL DEFAULT '', from_name VARCHAR(190) NOT NULL DEFAULT '',
    subject VARCHAR(300) NOT NULL DEFAULT '', reason VARCHAR(190) NOT NULL DEFAULT '', who VARCHAR(190) NOT NULL DEFAULT '', at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_msl (box_id, at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* ---------- правила ---------- */
function msp_dom($email) { $p = strrchr((string)$email, '@'); return $p === false ? '' : mb_strtolower(substr($p, 1)); }
function msp_rule_for($email) {   // правило на адрес важнее правила на домен
  msp_ensure(); $e = mb_strtolower(trim($email)); if ($e === '') return null;
  $q = db()->prepare('SELECT * FROM itc_mail_rule WHERE pat IN (?,?) ORDER BY CHAR_LENGTH(pat) DESC LIMIT 1'); $q->execute([$e, '@' . msp_dom($e)]);
  return $q->fetch() ?: null;
}
function msp_rule_put($pat, $kind, $who, $note = '') {
  msp_ensure(); $pat = mb_strtolower(trim($pat));
  if (!preg_match('~^(@[a-z0-9.-]+\.[a-z0-9-]{2,}|[^\s@]+@[a-z0-9.-]+\.[a-z0-9-]{2,})$~u', $pat)) throw new RuntimeException('Впишите адрес (name@site.ru) или домен (@site.ru)');
  $kind = $kind === 'ok' ? 'ok' : 'spam';
  db()->prepare('INSERT INTO itc_mail_rule (pat,kind,note,created_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE kind=VALUES(kind), note=VALUES(note), created_by=VALUES(created_by), created_at=NOW()')
    ->execute([$pat, $kind, mb_substr($note, 0, 190), $who]);
  return $pat;
}
function msp_rules() { msp_ensure(); return db()->query('SELECT id,pat,kind,note,hits,created_by,created_at FROM itc_mail_rule ORDER BY kind, pat')->fetchAll(); }

/* адрес знаком CRM: контакт, сделка или клиент */
function msp_known($email) {
  static $cache = []; $e = mb_strtolower(trim($email)); if ($e === '') return false;
  if (isset($cache[$e])) return $cache[$e];
  $hit = false;
  foreach (['SELECT 1 FROM crm_contacts WHERE LOWER(email) LIKE ? LIMIT 1', 'SELECT 1 FROM itc_fl_deal WHERE deleted=0 AND LOWER(email) LIKE ? LIMIT 1'] as $sql) {
    try { $q = db()->prepare($sql); $q->execute(['%' . $e . '%']); if ($q->fetchColumn()) { $hit = true; break; } } catch (Throwable $x) {}
  }
  return $cache[$e] = $hit;
}

/* ---------- папка «Спам» и перенос ---------- */
function msp_spam_raw($c) {
  foreach (ml_folders($c) as $f) if ($f['kind'] === 'spam') return $f['raw'];
  [, $ok] = $c->cmd('CREATE "Spam"'); if ($ok) { $c->cmd('SUBSCRIBE "Spam"'); return 'Spam'; }
  throw new RuntimeException('На почте нет папки «Спам» и создать её не получилось');
}
function msp_move($c, $uids, $to) {   // письма уже в выбранной (SELECT) папке
  if (!$uids) return; $set = implode(',', array_map('intval', $uids));
  [, $ok] = $c->cmd('UID MOVE ' . $set . ' ' . $c->q($to)); if ($ok) return;
  [, $ok] = $c->cmd('UID COPY ' . $set . ' ' . $c->q($to)); if (!$ok) throw new RuntimeException('Почтовый сервер не дал перенести письмо');
  $c->cmd('UID STORE ' . $set . ' +FLAGS.SILENT (\\Deleted)');
  [, $ok] = $c->cmd('UID EXPUNGE ' . $set); if (!$ok) $c->cmd('EXPUNGE');
}
function msp_log($boxId, $m, $reason, $who) {
  db()->prepare('INSERT INTO itc_mail_spamlog (box_id,from_email,from_name,subject,reason,who) VALUES (?,?,?,?,?,?)')
    ->execute([$boxId, mb_substr($m['from_email'], 0, 190), mb_substr($m['from'], 0, 190), mb_substr($m['subject'], 0, 300), mb_substr($reason, 0, 190), $who]);
}

/* ---------- ИИ: какие из незнакомых писем — спам ---------- */
function msp_ai($rows) {
  if (!$rows || !function_exists('fl_ai')) return null;
  $sys = 'Ты фильтр почты российской импортной компании ИТК: закупки на заводах Китая, поставки в Россию, интернет-магазин itcpartner.store. '
    . 'Для каждого письма реши: spam или ok. spam — реклама, рассылки, акции, уведомления сервисов и систем (Битрикс24, соцсети, маркетплейсы, «вам пришло уведомление»), '
    . 'предложения SEO, кредитов, продвижения сайтов, баз данных, фишинг и мошенничество, письма без смысла. '
    . 'ok — письма живых людей и компаний по делу: клиенты, поставщики, заводы (в том числе китайские на английском или китайском), предложения товаров и цен от заводов, '
    . 'банки, налоговая и госорганы, таможня, логистика, бухгалтерия, счета, договоры, ответы на наши письма, коды входа и подтверждения. '
    . 'Если сомневаешься — ok. Ответ строго JSON: {"r":[{"i":номер,"v":"spam"|"ok","why":"2–4 слова по-русски"}]}';
  $u = []; foreach ($rows as $i => $m) $u[] = ($i + 1) . '. От: ' . trim($m['from'] . ' <' . $m['from_email'] . '>') . "\nТема: " . $m['subject'] . ($m['bulk'] ? "\n(есть признаки массовой рассылки)" : '') . "\nТекст: " . mb_substr($m['snip'], 0, 500);
  $j = fl_ai($sys, implode("\n\n", $u), 1200, true);
  if (!is_array($j) || empty($j['r'])) return null;
  $o = []; foreach ((array)$j['r'] as $x) { $i = (int)($x['i'] ?? 0) - 1; if (isset($rows[$i])) $o[$i] = [($x['v'] ?? '') === 'spam', mb_substr((string)($x['why'] ?? ''), 0, 60)]; }
  return $o;
}

/* ---------- разбор новых писем одного ящика ---------- */
function msp_box($b, $aiMax = 40) {
  msp_ensure();
  $st = db()->prepare('SELECT * FROM itc_mail_state WHERE box_id=?'); $st->execute([$b['id']]); $s = $st->fetch() ?: ['uidv' => '', 'last_uid' => 0];
  $c = ml_open($b); $moved = 0; $checked = 0;
  try {
    $sel = ml_select($c, 'INBOX'); $uidv = '';
    foreach ($sel as $x) if (preg_match('~UIDVALIDITY (\d+)~i', $x['line'], $m)) $uidv = $m[1];
    $last = $s['uidv'] === $uidv ? (int)$s['last_uid'] : 0;
    [$r] = $c->cmd('UID SEARCH UID ' . ($last + 1) . ':*');
    $uids = []; foreach ($r as $x) if (preg_match('~^\* SEARCH ?(.*)$~i', trim($x['line']), $m)) foreach (preg_split('~\s+~', trim($m[1])) as $u) if ($u !== '' && (int)$u > $last) $uids[] = (int)$u;
    sort($uids);
    if ($last === 0) $uids = array_slice($uids, -150);   // первый запуск — разбираем последние 150 писем
    $uids = array_slice($uids, 0, 40);                   // за раз — не больше 40 (остальные — в следующий заход)
    if (!$uids) { db()->prepare('INSERT INTO itc_mail_state (box_id,uidv,last_uid,at,err) VALUES (?,?,?,NOW(),\'\') ON DUPLICATE KEY UPDATE uidv=VALUES(uidv), at=NOW(), err=\'\'')->execute([$b['id'], $uidv, $last]); return ['moved' => 0, 'checked' => 0]; }
    [$r] = $c->cmd('UID FETCH ' . implode(',', $uids) . ' (UID RFC822.SIZE BODY.PEEK[HEADER.FIELDS (FROM SUBJECT LIST-UNSUBSCRIBE LIST-ID PRECEDENCE AUTO-SUBMITTED X-SPAM-FLAG X-SPAM-STATUS X-SPAM X-MAILRU-SPAM)])');
    $own = msp_dom($b['email']); $spam = []; $ask = []; $cut = null;
    usort($r, function ($x, $y) { preg_match('~UID (\d+)~', $x['line'], $a); preg_match('~UID (\d+)~', $y['line'], $b); return (int)($a[1] ?? 0) <=> (int)($b[1] ?? 0); });
    foreach ($r as $x) {
      if (empty($x['lits']) || !preg_match('~UID (\d+)~', $x['line'], $m)) continue;
      $uid = (int)$m[1]; preg_match('~RFC822\.SIZE (\d+)~', $x['line'], $sm); $h = ml_headers($x['lits'][0]); $f = ml_addr_list($h['from'] ?? '');
      $msg = ['uid' => $uid, 'from' => $f[0][0] ?? '', 'from_email' => $f[0][1] ?? '', 'subject' => ml_hdr_dec($h['subject'] ?? '') ?: '(без темы)', 'size' => (int)($sm[1] ?? 0),
              'bulk' => !empty($h['list-unsubscribe']) || !empty($h['list-id']) || preg_match('~bulk|list|junk~i', $h['precedence'] ?? '') || preg_match('~auto-generated~i', $h['auto-submitted'] ?? ''),
              'flag' => preg_match('~^\s*yes~i', $h['x-spam-flag'] ?? '') || preg_match('~^\s*yes~i', $h['x-spam-status'] ?? '') || preg_match('~^\s*yes~i', $h['x-spam'] ?? ''), 'snip' => ''];
      if (count($ask) >= $aiMax) { $cut = $uid - 1; break; }                              // остальные — в следующий заход
      $checked++; $e = $msg['from_email'];
      if ($e !== '' && msp_dom($e) === $own) continue;                                    // свои сотрудники
      $rule = msp_rule_for($e);
      if ($rule) { db()->prepare('UPDATE itc_mail_rule SET hits=hits+1 WHERE id=?')->execute([$rule['id']]);
                   if ($rule['kind'] === 'spam') $spam[] = [$msg, 'правило: ' . $rule['pat']]; continue; }
      if (msp_known($e)) continue;                                                         // клиент, контакт или сделка CRM
      if ($msg['flag']) { $spam[] = [$msg, 'пометка почтового сервера']; continue; }
      $ask[] = $msg;
    }
    /* незнакомые — ИИ; текст начала письма берём только у небольших писем */
    $res = null;
    if ($ask) {
      foreach ($ask as &$m) if ($m['size'] < 1500000) { try { [$raw] = ml_fetch($c, $m['uid']); $pr = ['text' => '', 'html' => '', 'att' => []]; ml_parse($raw, $pr); $m['snip'] = preg_replace('~\s+~u', ' ', ml_text_of($pr)); } catch (Throwable $e) {} }
      unset($m);
      $res = msp_ai($ask);
      foreach ($ask as $i => $m) {
        if ($res !== null) { if (!empty($res[$i][0])) $spam[] = [$m, 'ИИ: ' . ($res[$i][1] ?: 'реклама или рассылка')]; }
        elseif ($m['bulk']) $spam[] = [$m, 'массовая рассылка'];
      }
    }
    if ($spam) {
      $to = msp_spam_raw($c); ml_select($c, 'INBOX');
      msp_move($c, array_map(function ($x) { return $x[0]['uid']; }, $spam), $to);
      foreach ($spam as $x) msp_log($b['id'], $x[0], $x[1], 'фильтр'); $moved = count($spam);
    }
    $newLast = $cut !== null ? max($last, $cut) : max($uids);
    db()->prepare('INSERT INTO itc_mail_state (box_id,uidv,last_uid,at,err) VALUES (?,?,?,NOW(),\'\') ON DUPLICATE KEY UPDATE uidv=VALUES(uidv), last_uid=VALUES(last_uid), at=NOW(), err=\'\'')->execute([$b['id'], $uidv, $newLast]);
    return ['moved' => $moved, 'checked' => $checked];
  } catch (Throwable $e) {
    db()->prepare('INSERT INTO itc_mail_state (box_id,at,err) VALUES (?,NOW(),?) ON DUPLICATE KEY UPDATE at=NOW(), err=VALUES(err)')->execute([$b['id'], mb_substr($e->getMessage(), 0, 300)]);
    throw $e;
  } finally { $c->close(); }
}

/* планировщик: раз в 5 минут — все ящики с паролем */
function msp_tick($force = false) {
  msp_ensure();
  if (!$force && function_exists('sp_get') && time() - (int)sp_get('msp_last') < 280) return null;
  if (function_exists('sp_put')) sp_put('msp_last', (string)time(), 'сервер');
  $o = [];
  foreach (db()->query("SELECT * FROM itc_mail_box WHERE deleted=0 AND pass_enc IS NOT NULL AND pass_enc<>''")->fetchAll() as $b) {
    try { $o[$b['email']] = msp_box($b); } catch (Throwable $e) { $o[$b['email']] = ['error' => $e->getMessage()]; }
  }
  return $o;
}

/* ---------- кнопки «Это спам» / «Не спам» ---------- */
function msp_mark($me, $a, $isSpam) {
  msp_ensure();
  $b = ml_box($me, $a['box'] ?? ''); $uid = (int)($a['uid'] ?? 0); $c = ml_open($b);
  try {
    $from = $isSpam ? ml_folder_raw($c, (string)($a['folder'] ?? 'inbox')) : msp_spam_raw($c);
    ml_select($c, $from);
    [$r] = $c->cmd('UID FETCH ' . $uid . ' (UID BODY.PEEK[HEADER.FIELDS (FROM SUBJECT)])'); $h = [];
    foreach ($r as $x) if (!empty($x['lits'])) $h = ml_headers($x['lits'][0]);
    if (!$h) throw new RuntimeException('Письмо не найдено — возможно, его уже перенесли');
    $f = ml_addr_list($h['from'] ?? ''); $m = ['from' => $f[0][0] ?? '', 'from_email' => $f[0][1] ?? '', 'subject' => ml_hdr_dec($h['subject'] ?? '')];
    $to = $isSpam ? msp_spam_raw($c) : 'INBOX'; ml_select($c, $from);
    msp_move($c, [$uid], $to);
  } finally { $c->close(); }
  $pat = '';
  if ($m['from_email'] !== '' && msp_dom($m['from_email']) !== msp_dom($b['email'])) {
    $pat = !empty($a['domain']) ? '@' . msp_dom($m['from_email']) : $m['from_email'];
    msp_rule_put($pat, $isSpam ? 'spam' : 'ok', $me['name'], $m['from'] !== '' ? $m['from'] : $m['subject']);
  }
  msp_log($b['id'], $m, $isSpam ? 'вручную: это спам' : 'вручную: не спам', $me['name']);
  return ['ok' => true, 'rule' => $pat, 'to' => $isSpam ? 'spam' : 'inbox'];
}

/* почему письмо в спаме — для списка папки «Спам» */
function msp_why($boxId, $items) {
  msp_ensure(); if (!$items) return $items;
  $q = db()->prepare('SELECT from_email,subject,reason FROM itc_mail_spamlog WHERE box_id=? ORDER BY id DESC LIMIT 800'); $q->execute([$boxId]); $map = [];
  foreach ($q->fetchAll() as $x) { $k = $x['from_email'] . '|' . $x['subject']; if (!isset($map[$k])) $map[$k] = $x['reason']; }
  foreach ($items as &$m) { $k = ($m['from_email'] ?? '') . '|' . ($m['subject'] ?? ''); if (isset($map[$k]) && strpos($map[$k], 'не спам') === false) $m['why'] = $map[$k]; }
  unset($m); return $items;
}

/* ---------- для Фила ---------- */
function msp_tools($me) {
  return [
    'mail_spam' => ['{"id":"id письма из mail_inbox (ящик:номер)","spam":true,"domain":false} — ПЕРЕНЕСТИ ПИСЬМО В СПАМ (spam:true) или вернуть из спама во «Входящие» (spam:false); отправитель запоминается, его следующие письма разложатся так же. domain:true — запомнить весь домен отправителя',
      function ($a) use ($me) { [$bid, $uid] = array_pad(explode(':', (string)($a['id'] ?? '')), 2, 0); $sp = !isset($a['spam']) || !empty($a['spam']);
        $r = msp_mark($me, ['box' => $bid, 'uid' => (int)$uid, 'folder' => $sp ? 'inbox' : 'spam', 'domain' => !empty($a['domain'])], $sp);
        return ($sp ? 'перенёс в спам' : 'вернул во «Входящие»') . ($r['rule'] !== '' ? '; запомнил: ' . $r['rule'] : ''); }],
    'mail_spam_rule' => ['{"pat":"адрес name@site.ru или домен @site.ru","kind":"spam|ok"} — ЗАПОМНИТЬ ОТПРАВИТЕЛЯ: «всё от bitrix24 — в спам» → {"pat":"@bitrix24.ru","kind":"spam"}; «письма от … не спам» → kind:ok',
      function ($a) use ($me) { $p = msp_rule_put((string)($a['pat'] ?? ''), (string)($a['kind'] ?? 'spam'), $me['name'], 'через Фила');
        return 'запомнил: ' . $p . ' — ' . (($a['kind'] ?? 'spam') === 'ok' ? 'не спам' : 'спам'); }],
  ];
}
const MSP_RULES = "\nСПАМ: «это спам / убери в спам» — mail_spam {spam:true}; «это не спам, верни» — mail_spam {spam:false}; «всё от … в спам» — mail_spam_rule.";
