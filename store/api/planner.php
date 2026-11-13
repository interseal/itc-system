<?php
// ==== ИТК · planner.php · №43 (05.11.2026) · планировщик: задачи с датой и временем, напоминания на телефон ====
// Задачи ставят голосом через Фила («напомни в четверг в 11:00 …») или вручную в планировщике.
// В планировщике видны и свои задачи, и следующие шаги из сделок CRM. Руководитель видит всех.
// Время хранится в UTC (at_utc), показывается в часовом поясе устройства. Напоминание — уведомление со звуком
// (приложение ИТК на Android) за remind минут; присылает ft_cron раз в минуту.

const PL_REPEAT = ['' => 'не повторять', 'daily' => 'каждый день', 'weekdays' => 'по будням', 'weekly' => 'каждую неделю', 'monthly' => 'каждый месяц'];

function pl_ensure() {
  static $ok = false; if ($ok) return; $ok = true;
  db()->exec("CREATE TABLE IF NOT EXISTS itc_plan (
    id VARCHAR(20) NOT NULL PRIMARY KEY, phone VARCHAR(40) NOT NULL, title VARCHAR(400) NOT NULL, note TEXT NULL,
    at_utc DATETIME NOT NULL, allday TINYINT NOT NULL DEFAULT 0, tz VARCHAR(40) NOT NULL DEFAULT 'Europe/Moscow',
    remind INT NOT NULL DEFAULT 15, rep VARCHAR(10) NOT NULL DEFAULT '', deal_id VARCHAR(40) NOT NULL DEFAULT '',
    status VARCHAR(10) NOT NULL DEFAULT 'open', remind_utc DATETIME NULL, reminded TINYINT NOT NULL DEFAULT 0,
    created_by VARCHAR(120) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, done_at DATETIME NULL,
    KEY idx_pl_ph (phone, at_utc), KEY idx_pl_rem (status, reminded, remind_utc)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  /* №44 · поручения: кто поставил, ответ исполнителя, сообщили ли о просрочке */
  foreach (["from_phone VARCHAR(40) NOT NULL DEFAULT ''", "from_name VARCHAR(120) NOT NULL DEFAULT ''", "reply TEXT NULL", "late_told TINYINT NOT NULL DEFAULT 0", "seen TINYINT NOT NULL DEFAULT 0"] as $c)
    { try { db()->exec('ALTER TABLE itc_plan ADD COLUMN ' . $c); } catch (Throwable $e) {} }
}
function pl_tz($tz = null) { $tz = (string)($tz ?? ($GLOBALS['ME_TZ'] ?? '')); try { new DateTimeZone($tz); return $tz; } catch (Throwable $e) { return 'Europe/Moscow'; } }
/* «2026-11-07 11:00» в поясе tz → UTC «Y-m-d H:i:s»; без времени — целый день (9:00 местного для напоминания) */
function pl_to_utc($local, $tz, &$allday = null) {
  $local = trim((string)$local); $allday = 0;
  if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $local)) { $local .= ' 09:00'; $allday = 1; }
  if (!preg_match('~^\d{4}-\d{2}-\d{2}[ T]\d{1,2}:\d{2}~', $local)) return '';
  try { $d = new DateTime(str_replace('T', ' ', substr($local, 0, 16)), new DateTimeZone(pl_tz($tz))); } catch (Throwable $e) { return ''; }
  $d->setTimezone(new DateTimeZone('UTC')); return $d->format('Y-m-d H:i:s');
}
function pl_loc($utc, $tz, $fmt = 'Y-m-d H:i') { if (!$utc) return ''; $d = new DateTime($utc, new DateTimeZone('UTC')); $d->setTimezone(new DateTimeZone(pl_tz($tz))); return $d->format($fmt); }
function pl_rem_utc($atUtc, $remind) { return $remind < 0 ? null : gmdate('Y-m-d H:i:s', strtotime($atUtc . ' UTC') - $remind * 60); }
function pl_next($atUtc, $rep, $tz) {   // следующий раз для повторяющейся задачи (по местному времени)
  $d = new DateTime($atUtc, new DateTimeZone('UTC')); $d->setTimezone(new DateTimeZone(pl_tz($tz)));
  do {
    if ($rep === 'daily') $d->modify('+1 day');
    elseif ($rep === 'weekdays') { do { $d->modify('+1 day'); } while ((int)$d->format('N') > 5); }
    elseif ($rep === 'weekly') $d->modify('+1 week');
    elseif ($rep === 'monthly') $d->modify('+1 month');
    else return null;
    $u = clone $d; $u->setTimezone(new DateTimeZone('UTC'));
  } while ($u->getTimestamp() < time() - 3600);
  return $u->format('Y-m-d H:i:s');
}
function pl_who_ok($me, $phone) {   // ставить задачи другим может руководитель
  if ($phone === '' || $phone === $me['phone']) return $me['phone'];
  if (!$me['boss']) fl_fail('Ставить задачи другим может только руководитель', 403);
  foreach (fl_staff() as $s) if ($s['phone'] === $phone || fl_tail10($s['phone']) === fl_tail10($phone)) return $s['phone'];
  fl_fail('Нет такого сотрудника');
}
function pl_add($me, $a) {
  pl_ensure(); $title = trim(mb_substr((string)($a['title'] ?? ''), 0, 400)); if ($title === '') return ['ok' => false, 'error' => 'Напишите, что сделать'];
  $tz = pl_tz($a['tz'] ?? null); $utc = pl_to_utc($a['at'] ?? '', $tz, $allday); if ($utc === '') return ['ok' => false, 'error' => 'Нужна дата и время, например 2026-11-07 11:00'];
  $remind = (int)($a['remind'] ?? 15); if ($remind < -1 || $remind > 10080) $remind = 15; if ($allday && !isset($a['remind'])) $remind = 0;
  $rep = (string)($a['rep'] ?? ''); if (!isset(PL_REPEAT[$rep])) $rep = '';
  $phone = pl_who_ok($me, (string)($a['for'] ?? ''));
  $id = 'p' . date('ymdHis') . bin2hex(random_bytes(2)); $other = $phone !== $me['phone'];
  db()->prepare('INSERT INTO itc_plan (id,phone,title,note,at_utc,allday,tz,remind,rep,deal_id,remind_utc,created_by,from_phone,from_name) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
    ->execute([$id, $phone, $title, mb_substr((string)($a['note'] ?? ''), 0, 2000), $utc, $allday, $tz, $remind, $rep, preg_replace('~[^A-Za-z0-9_-]~', '', (string)($a['deal'] ?? '')), pl_rem_utc($utc, $remind), $me['name'], $other ? $me['phone'] : '', $other ? ($me['boss'] ? fil_boss_io() : fil_io($me['phone'], $me['name'])) : '']);
  $when = pl_loc($utc, $tz, $allday ? 'd.m.Y' : 'd.m.Y H:i');
  /* №44 · поручение — исполнителю сразу уведомление со звуком */
  if ($other) fl_push([$phone], 'Поручение от ' . ($me['boss'] ? fil_boss_io() : fil_io($me['phone'], $me['name'])), $title . ' — срок ' . $when . (trim((string)($a['note'] ?? '')) !== '' ? '. ' . mb_substr(trim($a['note']), 0, 200) : ''), '/apps/fil/?plan=1', 'itc_deals');
  return ['ok' => true, 'id' => $id, 'for' => fil_io($phone, fl_staff_name($phone) ?: $me['name']), 'when' => $when, 'sent' => $other];
}
function pl_get($me, $id) {
  pl_ensure(); $q = db()->prepare('SELECT * FROM itc_plan WHERE id=?'); $q->execute([(string)$id]); $r = $q->fetch();
  if (!$r || ($r['phone'] !== $me['phone'] && $r['from_phone'] !== $me['phone'] && !$me['boss'])) fl_fail('Задача не найдена'); return $r;
}
function pl_done($me, $id, $reply = '') {
  $r = pl_get($me, $id); $reply = trim(mb_substr((string)$reply, 0, 2000));
  /* №44 · поручение выполнено — поставившему уведомление с ответом */
  if ($r['from_phone'] !== '' && $r['from_phone'] !== $me['phone']) fl_push([$r['from_phone']], 'Выполнено: ' . fil_io($me['phone'], $me['name']), $r['title'] . ($reply !== '' ? ' — ' . mb_substr($reply, 0, 300) : ''), '/apps/fil/?plan=2', 'itc_deals');
  if ($reply !== '') db()->prepare('UPDATE itc_plan SET reply=? WHERE id=?')->execute([$reply, $r['id']]);
  if ($r['rep'] !== '' && ($n = pl_next($r['at_utc'], $r['rep'], $r['tz']))) {
    db()->prepare("UPDATE itc_plan SET at_utc=?, remind_utc=?, reminded=0 WHERE id=?")->execute([$n, pl_rem_utc($n, (int)$r['remind']), $r['id']]);
    return ['ok' => true, 'next' => pl_loc($n, $r['tz'], 'd.m H:i')];
  }
  db()->prepare("UPDATE itc_plan SET status='done', done_at=NOW() WHERE id=?")->execute([$r['id']]); return ['ok' => true];
}
function pl_snooze($me, $id, $min) {
  $r = pl_get($me, $id); $min = max(5, min(10080, (int)$min ?: 60));
  $n = gmdate('Y-m-d H:i:s', max(time(), strtotime($r['at_utc'] . ' UTC')) + $min * 60);
  db()->prepare("UPDATE itc_plan SET at_utc=?, remind_utc=?, reminded=0, allday=0 WHERE id=?")->execute([$n, pl_rem_utc($n, (int)$r['remind'] === -1 ? -1 : 0), $r['id']]);
  return ['ok' => true, 'when' => pl_loc($n, $r['tz'], 'd.m H:i')];
}
function pl_del($me, $id) { $r = pl_get($me, $id); db()->prepare('DELETE FROM itc_plan WHERE id=?')->execute([$r['id']]); return ['ok' => true]; }
function pl_edit($me, $a) {
  $r = pl_get($me, $a['id'] ?? ''); $tz = pl_tz($a['tz'] ?? $r['tz']);
  $title = isset($a['title']) ? trim(mb_substr((string)$a['title'], 0, 400)) : $r['title']; if ($title === '') fl_fail('Напишите, что сделать');
  $utc = isset($a['at']) ? pl_to_utc($a['at'], $tz, $allday) : $r['at_utc']; if ($utc === '') fl_fail('Неверная дата'); if (!isset($a['at'])) $allday = (int)$r['allday'];
  $remind = isset($a['remind']) ? (int)$a['remind'] : (int)$r['remind']; $rep = isset($a['rep']) && isset(PL_REPEAT[$a['rep']]) ? $a['rep'] : $r['rep'];
  $phone = isset($a['for']) ? pl_who_ok($me, (string)$a['for']) : $r['phone'];
  db()->prepare('UPDATE itc_plan SET title=?, note=?, at_utc=?, allday=?, tz=?, remind=?, rep=?, phone=?, remind_utc=?, reminded=0 WHERE id=?')
    ->execute([$title, isset($a['note']) ? mb_substr((string)$a['note'], 0, 2000) : $r['note'], $utc, $allday, $tz, $remind, $rep, $phone, pl_rem_utc($utc, $remind), $r['id']]);
  return ['ok' => true];
}
/* список: свои задачи + шаги из сделок CRM, с from по to (местные даты) */
function pl_list($me, $a) {
  pl_ensure(); $tz = pl_tz($a['tz'] ?? null);
  $from = preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)($a['from'] ?? '')) ? $a['from'] : pl_loc(gmdate('Y-m-d H:i:s'), $tz, 'Y-m-d');
  $to = preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)($a['to'] ?? '')) ? $a['to'] : date('Y-m-d', strtotime($from . ' +30 day'));
  $all = $me['boss'] && !empty($a['all']);
  if (!empty($a['given'])) return pl_given($me, $tz);
  $fu = pl_to_utc($from . ' 00:00', $tz); $tu = pl_to_utc($to . ' 23:59', $tz);
  $sql = "SELECT * FROM itc_plan WHERE status='open' AND (at_utc BETWEEN ? AND ? OR at_utc < ?)" . ($all ? '' : ' AND phone=?') . ' ORDER BY at_utc LIMIT 400';
  $q = db()->prepare($sql); $q->execute($all ? [$fu, $tu, $fu] : [$fu, $tu, $fu, $me['phone']]);
  $names = []; foreach (fl_staff() as $s) $names[$s['phone']] = $s['name'];
  $o = [];
  foreach ($q->fetchAll() as $r) $o[] = ['id' => $r['id'], 'kind' => 'task', 'title' => $r['title'], 'note' => (string)$r['note'], 'day' => pl_loc($r['at_utc'], $tz, 'Y-m-d'),
    'time' => $r['allday'] ? '' : pl_loc($r['at_utc'], $tz, 'H:i'), 'late' => strtotime($r['at_utc'] . ' UTC') < time() - 60, 'remind' => (int)$r['remind'], 'rep' => $r['rep'],
    'deal' => $r['deal_id'], 'who' => $names[$r['phone']] ?? '', 'mine' => $r['phone'] === $me['phone'], 'from' => $r['from_phone'] !== '' && $r['from_phone'] !== $r['phone'] ? $r['from_name'] : ''];
  try {   // шаги из сделок: дата без времени
    $dq = db()->prepare("SELECT d.id,d.title,d.client,d.next_at,d.next_do,d.resp_phone,d.resp_name FROM itc_fl_deal d JOIN itc_fl_stage s ON s.id=d.stage_id
      WHERE d.deleted=0 AND s.sem='work' AND d.next_at IS NOT NULL AND d.next_at<=?" . ($all ? '' : ' AND d.resp_phone=?') . ' ORDER BY d.next_at LIMIT 300');
    $dq->execute($all ? [$to] : [$to, $me['phone']]);
    foreach ($dq->fetchAll() as $d) $o[] = ['id' => 'd' . $d['id'], 'kind' => 'deal', 'title' => trim($d['next_do']) ?: 'Следующий шаг по сделке', 'note' => $d['title'] . ($d['client'] ? ' — ' . $d['client'] : ''),
      'day' => $d['next_at'], 'time' => '', 'late' => $d['next_at'] < pl_loc(gmdate('Y-m-d H:i:s'), $tz, 'Y-m-d'), 'deal' => $d['id'], 'who' => $d['resp_name'], 'mine' => $d['resp_phone'] === $me['phone']];
  } catch (Throwable $e) {}
  usort($o, function ($x, $y) { return strcmp($x['day'] . ($x['time'] ?: '00:00'), $y['day'] . ($y['time'] ?: '00:00')); });
  return ['ok' => true, 'items' => $o, 'from' => $from, 'to' => $to, 'tz' => $tz, 'repeat' => PL_REPEAT, 'staff' => $me['boss'] ? array_map(function ($s) { return ['phone' => $s['phone'], 'name' => $s['name']]; }, fl_staff()) : []];
}
/* №44 · «Мои поручения»: что я поручил другим — открытые и выполненные за 14 дней */
function pl_given($me, $tz) {
  $q = db()->prepare("SELECT * FROM itc_plan WHERE from_phone=? AND (status='open' OR done_at > NOW() - INTERVAL 14 DAY) ORDER BY status='done', at_utc LIMIT 200"); $q->execute([$me['phone']]);
  $names = []; foreach (fl_staff() as $s) $names[$s['phone']] = $s['name']; $o = [];
  foreach ($q->fetchAll() as $r) $o[] = ['id' => $r['id'], 'kind' => 'given', 'title' => $r['title'], 'note' => (string)$r['note'], 'day' => pl_loc($r['at_utc'], $tz, 'Y-m-d'), 'time' => $r['allday'] ? '' : pl_loc($r['at_utc'], $tz, 'H:i'),
    'late' => $r['status'] === 'open' && strtotime($r['at_utc'] . ' UTC') < time() - 60, 'done' => $r['status'] === 'done', 'done_at' => $r['done_at'] ? date('d.m H:i', strtotime($r['done_at'])) : '', 'reply' => (string)$r['reply'], 'who' => $names[$r['phone']] ?? $r['phone'], 'deal' => $r['deal_id'], 'rep' => $r['rep']];
  return ['ok' => true, 'items' => $o, 'given' => 1, 'tz' => $tz, 'repeat' => PL_REPEAT, 'staff' => $me['boss'] ? array_map(function ($s) { return ['phone' => $s['phone'], 'name' => $s['name']]; }, fl_staff()) : []];
}
/* раз в минуту: напоминания, у которых подошло время; утром — сводка шагов по сделкам на сегодня */
function pl_tick() {
  try {
    pl_ensure();
    $rows = db()->query("SELECT * FROM itc_plan WHERE status='open' AND reminded=0 AND remind_utc IS NOT NULL AND remind_utc <= UTC_TIMESTAMP() ORDER BY remind_utc LIMIT 30")->fetchAll();
    foreach ($rows as $r) {
      db()->prepare('UPDATE itc_plan SET reminded=1 WHERE id=? AND reminded=0')->execute([$r['id']]);
      $when = $r['allday'] ? 'сегодня' : pl_loc($r['at_utc'], $r['tz'], 'H:i'); $soon = (int)$r['remind'] > 0 && !$r['allday'] ? ' (через ' . pl_min_ru((int)$r['remind']) . ')' : '';
      fl_push([$r['phone']], 'Напоминание: ' . $when . $soon, $r['title'], '/apps/fil/?plan=1', 'itc_deals');
    }
    // повторяющиеся, время которых давно прошло, — на следующий раз
    foreach (db()->query("SELECT * FROM itc_plan WHERE status='open' AND rep<>'' AND at_utc < UTC_TIMESTAMP() - INTERVAL 12 HOUR LIMIT 30")->fetchAll() as $r)
      if ($n = pl_next($r['at_utc'], $r['rep'], $r['tz'])) db()->prepare('UPDATE itc_plan SET at_utc=?, remind_utc=?, reminded=0 WHERE id=?')->execute([$n, pl_rem_utc($n, (int)$r['remind']), $r['id']]);
    // №44 · поручение просрочено больше суток — исполнителю ещё раз, поставившему — сообщение (один раз)
    foreach (db()->query("SELECT * FROM itc_plan WHERE status='open' AND from_phone<>'' AND late_told=0 AND rep='' AND at_utc < UTC_TIMESTAMP() - INTERVAL 24 HOUR LIMIT 20")->fetchAll() as $r) {
      db()->prepare('UPDATE itc_plan SET late_told=1 WHERE id=?')->execute([$r['id']]);
      fl_push([$r['phone']], 'Просрочено поручение', $r['title'] . ' (от ' . $r['from_name'] . ')', '/apps/fil/?plan=1', 'itc_deals');
      fl_push([$r['from_phone']], 'Не выполнено: ' . (fl_staff_name($r['phone']) ?: $r['phone']), $r['title'] . ' — срок прошёл больше суток назад', '/apps/fil/?plan=2', 'itc_deals');
    }
    // 9:00 по Москве — каждому сводка: сколько шагов по сделкам и задач на сегодня
    $msk = new DateTime('now', new DateTimeZone('Europe/Moscow')); $day = $msk->format('Y-m-d');
    if ((int)$msk->format('H') >= 9 && sp_get('pl_digest') !== $day) {
      sp_put('pl_digest', $day, 'сервер');
      foreach (fl_staff() as $s) {
        $q = db()->prepare("SELECT COUNT(*) FROM itc_fl_deal d JOIN itc_fl_stage st ON st.id=d.stage_id WHERE d.deleted=0 AND st.sem='work' AND d.resp_phone=? AND d.next_at<=?"); $q->execute([$s['phone'], $day]); $nd = (int)$q->fetchColumn();
        $q = db()->prepare("SELECT COUNT(*) FROM itc_plan WHERE status='open' AND phone=? AND at_utc<=?"); $q->execute([$s['phone'], pl_to_utc($day . ' 23:59', 'Europe/Moscow')]); $np = (int)$q->fetchColumn();
        if ($nd + $np > 0) fl_push([$s['phone']], 'План на сегодня', 'Задач: ' . $np . ', шагов по сделкам: ' . $nd . '. Откройте планировщик.', '/apps/fil/?plan=1', 'itc_deals');
      }
    }
  } catch (Throwable $e) { error_log('pl_tick: ' . $e->getMessage()); }
}
function pl_min_ru($m) { if ($m % 1440 === 0) { $d = $m / 1440; return $d . ' ' . ($d === 1 ? 'день' : ($d < 5 ? 'дня' : 'дней')); } if ($m % 60 === 0) { $h = $m / 60; return $h . ' ' . ($h === 1 ? 'час' : ($h < 5 ? 'часа' : 'часов')); } return $m . ' мин'; }

/* инструменты Фила */
function pl_tools($me) {
  return [
    'plan_add' => ['{"title":"что сделать","at":"ГГГГ-ММ-ДД ЧЧ:ММ (или только дата — на весь день)","remind":15,"rep":"|daily|weekdays|weekly|monthly","deal":"id сделки или пусто","for":"телефон сотрудника или пусто — себе"} — ПЛАНИРОВЩИК: поставить задачу с напоминанием на телефон. Время — местное время человека (см. «СЕЙЧАС У ЧЕЛОВЕКА»). remind — за сколько минут напомнить (0 — ровно в срок, 60 — за час, 1440 — за день, -1 — без напоминания). Ставится сразу',
      function ($a) use ($me) { $r = pl_add($me, $a); if (empty($r['ok'])) return $r['error'] ?? 'не получилось'; $GLOBALS['PL_MADE'] = true; return 'поставил: ' . $r['when'] . ' — ' . ($a['title'] ?? '') . ($r['for'] !== $me['name'] ? ' (для ' . $r['for'] . ')' : '') . ', id ' . $r['id']; }],
    'plan_list' => ['{"from":"ГГГГ-ММ-ДД или пусто — сегодня","to":"ГГГГ-ММ-ДД или пусто","all":false} — ПЛАНИРОВЩИК: что запланировано (свои задачи и шаги по сделкам). all=true — у всех сотрудников (только руководитель)',
      function ($a) use ($me) { $r = pl_list($me, $a); return array_map(function ($x) { return ($x['late'] ? '[просрочено] ' : '') . $x['day'] . ($x['time'] ? ' ' . $x['time'] : '') . ' — ' . $x['title'] . ($x['note'] && $x['kind'] === 'deal' ? ' (сделка: ' . $x['note'] . ')' : '') . ($x['who'] ? ' · ' . $x['who'] : '') . ' · id ' . $x['id']; }, array_slice($r['items'], 0, 60)) ?: 'ничего не запланировано'; }],
    'plan_done' => ['{"id":"id задачи из plan_list","reply":"ответ поставившему или пусто"} — ПЛАНИРОВЩИК: отметить задачу сделанной (повторяющаяся перейдёт на следующий раз). Если это поручение — поставивший получит уведомление с ответом',
      function ($a) use ($me) { if (strpos((string)($a['id'] ?? ''), 'd') === 0) return 'это шаг сделки — его меняют через do=next'; return pl_done($me, $a['id'] ?? '', (string)($a['reply'] ?? ''))['ok'] ? 'отметил' : 'не получилось'; }],
    'plan_given' => ['{} — ПОРУЧЕНИЯ: что я поручил сотрудникам — кому, что, срок, выполнено ли, ответ исполнителя',
      function ($a) use ($me) { $r = pl_given($me, pl_tz()); return array_map(function ($x) { return ($x['done'] ? '[выполнено ' . $x['done_at'] . '] ' : ($x['late'] ? '[просрочено] ' : '')) . $x['who'] . ': ' . $x['title'] . ' — срок ' . $x['day'] . ($x['time'] ? ' ' . $x['time'] : '') . ($x['reply'] !== '' ? ' — ответ: ' . $x['reply'] : '') . ' · id ' . $x['id']; }, $r['items']) ?: 'поручений нет'; }],
    'plan_move' => ['{"id":"id задачи","at":"ГГГГ-ММ-ДД ЧЧ:ММ","title":"новый текст или пусто"} — ПЛАНИРОВЩИК: перенести задачу на другое время или поправить',
      function ($a) use ($me) { $e = ['id' => $a['id'] ?? '', 'at' => $a['at'] ?? '']; if (!empty($a['title'])) $e['title'] = $a['title']; if ($e['at'] === '') unset($e['at']); pl_edit($me, $e); return 'перенёс'; }],
  ];
}
const PL_RULES = "\nПЛАНИРОВЩИК: «напомни», «поставь задачу на…», «запланируй», «в четверг в 11 позвонить…» — поставь задачу инструментом plan_add (дату и время переведи в ГГГГ-ММ-ДД ЧЧ:ММ по местному времени человека; «завтра», «в пятницу», «через час» считай от «СЕЙЧАС У ЧЕЛОВЕКА»; если время не названо — только дата). Если задача про сделку — укажи deal. «Каждый понедельник» — rep=weekly, «каждый день» — daily, «по будням» — weekdays. По умолчанию напоминание за 15 минут, если человек не сказал иначе. Руководитель может ставить задачи сотрудникам (for = телефон из СОТРУДНИКОВ). «Что у меня на сегодня/на неделю» — plan_list. В ответе коротко подтверди: когда и что поставил.\nПОРУЧЕНИЯ СОТРУДНИКАМ (руководитель говорит «бухгалтеру…», «закупщику…», «скажи Полине…», «поручи Денису…»): найди исполнителя в СОТРУДНИКАХ по имени или должности (бухгалтер — роль accountant или chief_accountant, закупщик — роль buyer, менеджер по продажам — роль manager); если подходит несколько — переспроси одним вопросом. Поставь plan_add с for = его телефон: в title — само поручение коротко, в note — подробности как сказал руководитель; срок — как сказал, а если не сказал — сегодня до конца дня (только дата). Исполнитель сразу получит уведомление. Если это поиск товара или цены у фабрики для закупщика — дополнительно заведи сделку в воронке закупки (do=new_deal, resp = телефон закупщика, note — что найти) и укажи эту сделку в plan_add (deal), если она уже есть. «Что с моими поручениями», «кто не сделал» — plan_given. Исполнитель говорит «сделал», «выполнил», «отправил» — plan_done с reply (что он ответил).";
