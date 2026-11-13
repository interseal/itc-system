<?php
/* ==== ИТК · crm_lib.php · №237 (07.11.2026) — общий модуль «Клиенты» ====
   Им пользуются карточка клиента в Экосистеме (api.php) и Фил (flow.php):
   • crm_norm — одно название без «ООО», кавычек и регистра («ООО «Ромашка»» = «Ромашка»);
   • crm_find — найти рабочего клиента по названию или ИНН (голос пишет с ошибками — ищем по частям);
   • crm_hist — вся история клиента: сделки воронок, учёт сделок, КП, счета, договоры, оплаты,
     заказы магазина, дела и записи — с номенклатурой (что именно брали);
   • crm_client_put / crm_contact_put — завести или дополнить клиента и контактное лицо (без дублей);
   • crm_merge_do — склеить дубли;
   • crm_dadata_inn — реквизиты по ИНН. */

function crm_norm($s) {
  $s = mb_strtolower(trim((string)$s));
  $s = str_replace('ё', 'е', $s);
  $s = preg_replace('~[«»"\'“”„`]+~u', ' ', $s);
  $s = preg_replace('~(^|[\s.,(])(ооо|оао|зао|пао|ао|нао|ип|чп|тоо|ллс|llc|ltd|общество с ограниченной ответственностью|индивидуальный предприниматель|акционерное общество)(?=$|[\s.,)])~u', ' ', $s);
  $s = preg_replace('~[^\p{L}\p{N}]+~u', ' ', $s);
  return trim(preg_replace('~\s+~u', ' ', $s));
}
/* Самое длинное слово названия — по нему грубо ищем кандидатов, точное совпадение проверяем по crm_norm */
function crm_core($k) {
  $best = '';
  foreach (explode(' ', $k) as $w) if (mb_strlen($w) > mb_strlen($best)) $best = $w;
  return $best;
}
function crm_tbl_ok($t) {
  static $c = [];
  if (!isset($c[$t])) { try { db()->query("SELECT 1 FROM $t LIMIT 1"); $c[$t] = true; } catch (Throwable $e) { $c[$t] = false; } }
  return $c[$t];
}
function crm_gen_id($p) { return $p . date('ymd') . substr(bin2hex(random_bytes(4)), 0, 6); }
function crm_name($c) { return trim((string)($c['alias'] ?? '')) !== '' ? trim($c['alias']) : trim((string)($c['company'] ?? '')); }

/* Позиции из JSON любой формы → «Название × кол-во по цене» (сделки, КП, счета хранят по-разному) */
function crm_items_flat($json, $max = 40) {
  $a = is_array($json) ? $json : json_decode((string)$json, true);
  if (!is_array($a)) return [];
  $o = [];
  foreach ($a as $x) {
    if (is_string($x)) { $x = trim($x); if ($x !== '') $o[] = mb_substr($x, 0, 160); }
    elseif (is_array($x)) {
      $n = '';
      foreach (['name', 'title', 'n', 'model', 'item', 'товар'] as $k) if (!empty($x[$k]) && is_scalar($x[$k])) { $n = trim((string)$x[$k]); break; }
      if ($n === '') continue;
      $q = ''; foreach (['qty', 'q', 'count', 'кол'] as $k) if (isset($x[$k]) && is_scalar($x[$k]) && (string)$x[$k] !== '') { $q = (string)$x[$k]; break; }
      $u = ''; foreach (['unit', 'u', 'ед'] as $k) if (!empty($x[$k]) && is_scalar($x[$k])) { $u = (string)$x[$k]; break; }
      $p = ''; foreach (['price', 'per', 'p', 'цена'] as $k) if (isset($x[$k]) && is_numeric($x[$k]) && (float)$x[$k] > 0) { $p = (string)round((float)$x[$k], 2); break; }
      $o[] = mb_substr($n, 0, 160) . ($q !== '' ? ' × ' . $q . ($u !== '' ? ' ' . $u : '') : '') . ($p !== '' ? ' по ' . $p : '');
    }
    if (count($o) >= $max) break;
  }
  return $o;
}

/* Найти рабочих клиентов (crm_clients) по названию, псевдониму или ИНН.
   Возвращает лучшие совпадения: сначала точное название, потом начало, потом по словам. */
function crm_find($q, $limit = 8) {
  if (!crm_tbl_ok('crm_clients')) return [];
  $q = trim((string)$q); if ($q === '') return [];
  $digits = preg_replace('~\D~', '', $q);
  if (strlen($digits) >= 10 && strlen($digits) === strlen(preg_replace('~\s~', '', $q))) {
    $st = db()->prepare('SELECT * FROM crm_clients WHERE inn=? LIMIT 5'); $st->execute([$digits]); return $st->fetchAll();
  }
  $k = crm_norm($q); if ($k === '') return [];
  /* №239: общие слова («центр», «строй», «групп»…) не делают названия похожими — ищем по отличительной части */
  static $stop = ['центр','центра','групп','группа','компания','компании','строй','торг','сервис','отель','гостиница','магазин','фирма','завод','холдинг','трейд','плюс','про','дом','мир','юг','крым','рус','россия','опт'];
  $all = array_values(array_filter(explode(' ', $k), function ($w) { return mb_strlen($w) >= 3; }));
  $words = array_values(array_filter($all, function ($w) use ($stop) { return !in_array($w, $stop, true); }));
  if (!$words) $words = $all ?: [$k];
  $w = []; $a = [];
  foreach (array_slice($words, 0, 4) as $x) { $w[] = 'alias LIKE ? OR company LIKE ?'; $a[] = '%' . $x . '%'; $a[] = '%' . $x . '%'; }
  $st = db()->prepare('SELECT * FROM crm_clients WHERE ' . implode(' OR ', $w) . ' LIMIT 200'); $st->execute($a);
  $sc = [];
  foreach ($st->fetchAll() as $r) {
    $s = 0;
    foreach ([crm_norm($r['alias']), crm_norm($r['company'])] as $n) {
      if ($n === '') continue;
      if ($n === $k) $s = max($s, 100);
      elseif (mb_strpos($n, $k) === 0) $s = max($s, 80);
      elseif (mb_strpos($n, $k) !== false) $s = max($s, 60);
      else { $hit = 0; foreach ($words as $x) if (mb_strpos($n, $x) !== false) $hit++; if ($hit) $s = max($s, 20 + 30 * $hit / count($words)); }
    }
    if ($s > 0) { $r['_score'] = $s; $sc[] = $r; }
  }
  usort($sc, function ($x, $y) { return $y['_score'] <=> $x['_score']; });
  return array_slice($sc, 0, $limit);
}

/* Вся история клиента. $all — видит всё (руководитель, бухгалтерия); иначе только свои сделки. */
function crm_hist($c, $all, $phone) {
  $id = (string)$c['id'];
  $keys = [];
  foreach ([$c['alias'] ?? '', $c['company'] ?? ''] as $n) { $k = crm_norm($n); if (mb_strlen($k) >= 2) $keys[$k] = 1; }
  $inn = preg_replace('~\D~', '', (string)($c['inn'] ?? ''));
  $match = function ($name) use ($keys) { return isset($keys[crm_norm($name)]); };
  $likes = [];
  foreach (array_keys($keys) as $k) { $w = crm_core($k); if (mb_strlen($w) >= 2) $likes['%' . $w . '%'] = 1; }
  $likes = array_keys($likes);
  $inIds = function ($n) { return implode(',', array_fill(0, $n, '?')); };

  $ev = []; $sum = ['deals' => 0, 'open' => 0, 'won' => 0, 'won_sum' => 0, 'paid' => 0, 'kp' => 0, 'ct' => 0, 'inv' => 0, 'inv_sum' => 0, 'orders' => 0, 'tasks' => 0];

  // потенциальные клиенты с тем же названием — сделки воронок ссылаются на них
  $leadIds = [];
  if ($likes && crm_tbl_ok('itc_lead')) {
    foreach ($likes as $L) {
      $q = db()->prepare("SELECT id,name FROM itc_lead WHERE deleted=0 AND name LIKE ? LIMIT 200"); $q->execute([$L]);
      foreach ($q->fetchAll() as $r) if ($match($r['name'])) $leadIds[$r['id']] = 1;
    }
  }

  // сделки воронок
  $deals = [];
  if (crm_tbl_ok('itc_fl_deal')) {
    $w = []; $a = [];
    foreach ($likes as $L) { $w[] = 'd.client LIKE ?'; $a[] = $L; }
    if ($leadIds) { $w[] = 'd.lead_id IN (' . $inIds(count($leadIds)) . ')'; $a = array_merge($a, array_keys($leadIds)); }
    if ($w) {
      $sql = "SELECT d.id,d.title,d.amount,d.currency,d.client,d.lead_id,d.resp_name,d.items,d.note,d.created_at,d.updated_at,d.closed_at,
                     IFNULL(s.name,'') stage, IFNULL(s.sem,'work') sem, IFNULL(p.name,'') pipe
              FROM itc_fl_deal d LEFT JOIN itc_fl_stage s ON s.id=d.stage_id LEFT JOIN itc_fl_pipe p ON p.id=d.pipe_id
              WHERE d.deleted=0 AND (" . implode(' OR ', $w) . ")";
      if (!$all) { $sql .= ' AND (d.resp_phone=? OR d.created_phone=?)'; $a[] = $phone; $a[] = $phone; }
      $q = db()->prepare($sql . ' ORDER BY d.updated_at DESC LIMIT 200'); $q->execute($a);
      foreach ($q->fetchAll() as $r) {
        if (!isset($leadIds[$r['lead_id']]) && !$match($r['client'])) continue;
        $deals[$r['id']] = $r; $sum['deals']++;
        if ($r['sem'] === 'work') $sum['open']++;
        if ($r['sem'] === 'win') { $sum['won']++; $sum['won_sum'] += (float)$r['amount']; }
        $ev[] = ['at' => $r['created_at'], 'kind' => 'deal', 'id' => $r['id'], 'title' => ($r['title'] ?: 'Сделка без названия'),
                 'sub' => trim($r['pipe'] . ' · ' . $r['stage'] . ($r['resp_name'] ? ' · ' . $r['resp_name'] : ''), ' ·'),
                 'amount' => (float)$r['amount'], 'cur' => $r['currency'], 'sem' => $r['sem'], 'items' => crm_items_flat($r['items'])];
      }
    }
  }
  $dealIds = array_keys($deals);

  // учёт сделок (раздел «Сделки» CRM экосистемы)
  if (crm_tbl_ok('crm_deals')) {
    $q = db()->prepare('SELECT id,title,status,sold_plan,sold_fact,started_on,closed_on,created_at FROM crm_deals WHERE client_id=? ORDER BY created_at DESC LIMIT 100'); $q->execute([$id]);
    foreach ($q->fetchAll() as $r)
      $ev[] = ['at' => $r['started_on'] ?: $r['created_at'], 'kind' => 'acc', 'id' => $r['id'], 'title' => ($r['title'] ?: 'Сделка без названия'),
               'sub' => ($r['status'] === 'closed' ? 'закрыта' : 'в работе') . ($r['closed_on'] ? ' · закрыта ' . $r['closed_on'] : ''),
               'amount' => (float)($r['sold_fact'] > 0 ? $r['sold_fact'] : $r['sold_plan']), 'cur' => 'RUB'];
  }

  // КП из полного расчёта и от Фила
  if (crm_tbl_ok('crm_kp')) {
    $q = db()->prepare('SELECT id,no,dt,total,items,created_at FROM crm_kp WHERE client_id=? ORDER BY created_at DESC LIMIT 100'); $q->execute([$id]);
    foreach ($q->fetchAll() as $r) {
      $sum['kp']++;
      $ev[] = ['at' => $r['created_at'] ?: $r['dt'], 'kind' => 'kp', 'id' => $r['id'], 'title' => 'КП № ' . $r['no'], 'sub' => 'из полного расчёта',
               'amount' => (float)$r['total'], 'cur' => 'RUB', 'items' => crm_items_flat($r['items'])];
    }
  }
  if ($likes && crm_tbl_ok('itc_fil_kp')) {
    $seen = [];
    foreach ($likes as $L) {
      $sql = "SELECT id,title,kind,client,phone,created_at FROM itc_fil_kp WHERE kind<>'inv' AND client LIKE ?"; $a = [$L];
      if (!$all) { $sql .= ' AND phone=?'; $a[] = $phone; }
      $q = db()->prepare($sql . ' ORDER BY created_at DESC LIMIT 100'); $q->execute($a);
      foreach ($q->fetchAll() as $r) {
        if (isset($seen[$r['id']]) || !$match($r['client'])) continue; $seen[$r['id']] = 1; $sum['kp']++;
        $ev[] = ['at' => $r['created_at'], 'kind' => 'kp', 'id' => $r['id'], 'file' => $r['id'], 'title' => ($r['title'] ?: 'КП без названия'), 'sub' => 'сделал Фил', 'amount' => 0, 'cur' => ''];
      }
    }
  }

  // счета на оплату
  if (crm_tbl_ok('crm_inv')) {
    $sql = 'SELECT * FROM crm_inv WHERE client_id=?'; $a = [$id];
    if (!$all) { $sql .= ' AND created_phone=?'; $a[] = $phone; }
    $q = db()->prepare($sql . ' ORDER BY created_at DESC LIMIT 100'); $q->execute($a);
    foreach ($q->fetchAll() as $r) {
      $sum['inv']++; $sum['inv_sum'] += (float)$r['total'];
      $ev[] = ['at' => $r['created_at'], 'kind' => 'inv', 'id' => $r['id'], 'file' => $r['file_id'], 'title' => 'Счёт № ' . $r['no'] . ' от ' . date('d.m.Y', strtotime($r['dt'])),
               'sub' => trim($r['company'] . ($r['created_by'] ? ' · выписал(а) ' . $r['created_by'] : ''), ' ·'), 'amount' => (float)$r['total'], 'cur' => 'RUB', 'items' => crm_items_flat($r['items'])];
    }
  }

  // договоры и оплаты по ним
  if (crm_tbl_ok('itc_ct')) {
    $w = []; $a = [];
    if (strlen($inn) >= 10) { $w[] = 'inn=?'; $a[] = $inn; }
    if ($dealIds) { $w[] = 'deal_id IN (' . $inIds(count($dealIds)) . ')'; $a = array_merge($a, $dealIds); }
    foreach ($likes as $L) { $w[] = 'client LIKE ?'; $a[] = $L; }
    if ($w) {
      $q = db()->prepare('SELECT id,deal_id,no,dt,client,inn,amount,currency,status,due_at,created_at FROM itc_ct WHERE ' . implode(' OR ', $w) . ' LIMIT 200'); $q->execute($a);
      $cts = [];
      foreach ($q->fetchAll() as $r) {
        $ok = (strlen($inn) >= 10 && $r['inn'] === $inn) || isset($deals[$r['deal_id']]) || $match($r['client']);
        if (!$ok || (!$all && !isset($deals[$r['deal_id']]))) continue;
        $cts[$r['id']] = $r; $sum['ct']++;
        $ev[] = ['at' => $r['dt'] ?: $r['created_at'], 'kind' => 'ct', 'id' => $r['id'], 'deal' => $r['deal_id'], 'title' => 'Договор' . ($r['no'] !== '' ? ' № ' . $r['no'] : ''),
                 'sub' => $r['due_at'] ? 'оплатить до ' . date('d.m.Y', strtotime($r['due_at'])) : '', 'amount' => (float)$r['amount'], 'cur' => $r['currency']];
      }
      if ($cts && crm_tbl_ok('itc_ct_in')) {
        $q = db()->prepare('SELECT ct_id,amount,currency,amount_rub,paid_on,doc_no,created_at FROM itc_ct_in WHERE ct_id IN (' . $inIds(count($cts)) . ') ORDER BY paid_on'); $q->execute(array_keys($cts));
        foreach ($q->fetchAll() as $r) {
          $sum['paid'] += (float)$r['amount_rub']; $ct = $cts[$r['ct_id']];
          $ev[] = ['at' => $r['paid_on'] ?: $r['created_at'], 'kind' => 'pay', 'id' => $r['ct_id'], 'title' => 'Оплата по договору' . ($ct['no'] !== '' ? ' № ' . $ct['no'] : ''),
                   'sub' => $r['doc_no'] !== '' ? 'платёжка № ' . $r['doc_no'] : '', 'amount' => (float)$r['amount'], 'cur' => $r['currency']];
        }
      }
    }
  }

  // заказы интернет-магазина по счёту на этот ИНН
  if (strlen($inn) >= 10 && $all && crm_tbl_ok('orders')) {
    try {
      $q = db()->prepare("SELECT id,num,total,paid,COALESCE(inv_at,created_at) at FROM orders WHERE inv_inn=? ORDER BY at DESC LIMIT 50"); $q->execute([$inn]);
      foreach ($q->fetchAll() as $r) {
        $sum['orders']++; $items = [];
        try {
          $qi = db()->prepare('SELECT p.name, i.qty FROM order_items i LEFT JOIN products p ON p.id=i.product_id WHERE i.order_id=? LIMIT 40'); $qi->execute([$r['id']]);
          foreach ($qi->fetchAll() as $it) $items[] = trim((string)$it['name']) . ' × ' . (0 + $it['qty']);
        } catch (Throwable $e) {}
        $ev[] = ['at' => $r['at'], 'kind' => 'order', 'id' => $r['id'], 'title' => 'Заказ в магазине № ' . $r['num'], 'sub' => $r['paid'] ? 'оплачен' : 'не оплачен',
                 'amount' => (float)$r['total'], 'cur' => 'RUB', 'items' => $items];
      }
    } catch (Throwable $e) {}
  }

  // дела планировщика и записи в сделках
  if ($dealIds && crm_tbl_ok('itc_plan')) {
    $q = db()->prepare("SELECT id,title,at_utc,status,deal_id FROM itc_plan WHERE deal_id IN (" . $inIds(count($dealIds)) . ") ORDER BY at_utc DESC LIMIT 100"); $q->execute($dealIds);
    foreach ($q->fetchAll() as $r) {
      $sum['tasks']++;
      $ev[] = ['at' => $r['at_utc'], 'kind' => 'task', 'id' => $r['id'], 'deal' => $r['deal_id'], 'title' => $r['title'], 'sub' => $r['status'] === 'done' ? 'сделано' : 'запланировано', 'done' => $r['status'] === 'done'];
    }
  }
  if ($dealIds && crm_tbl_ok('itc_fl_log')) {
    $q = db()->prepare("SELECT deal_id,kind,text,who,at FROM itc_fl_log WHERE deal_id IN (" . $inIds(count($dealIds)) . ") ORDER BY at DESC LIMIT 60"); $q->execute($dealIds);
    foreach ($q->fetchAll() as $r) {
      if (trim((string)$r['text']) === '') continue;
      $ev[] = ['at' => $r['at'], 'kind' => 'log', 'deal' => $r['deal_id'], 'title' => mb_substr((string)$r['text'], 0, 300), 'sub' => trim(($r['who'] ?: '') . ' · ' . ($deals[$r['deal_id']]['title'] ?? ''), ' ·')];
    }
  }

  usort($ev, function ($x, $y) { return strcmp((string)$y['at'], (string)$x['at']); });
  return ['sum' => $sum, 'events' => array_slice($ev, 0, 300), 'deal_ids' => $dealIds];
}

/* Завести или дополнить клиента. Дубль (то же название без ООО или тот же ИНН) не заводим —
   дополняем найденного: пустые поля заполняем, заполненные меняем только при $over. */
function crm_client_put($d, $who, $over = false) {
  if (!crm_tbl_ok('crm_clients')) return ['ok' => false, 'error' => 'Раздел «Рабочие клиенты» ещё ни разу не открывали — откройте его в Экосистеме один раз'];
  $f = ['alias', 'company', 'inn', 'kpp', 'ogrn', 'addr', 'bank', 'bik', 'acct', 'corr', 'site', 'note'];
  $v = []; foreach ($f as $k) $v[$k] = trim(mb_substr((string)($d[$k] ?? ''), 0, $k === 'note' ? 2000 : 255));
  $v['inn'] = preg_replace('~\D~', '', $v['inn']);
  $cur = null;
  if (!empty($d['id'])) { $st = db()->prepare('SELECT * FROM crm_clients WHERE id=?'); $st->execute([(string)$d['id']]); $cur = $st->fetch() ?: null; }
  if (!$cur && strlen($v['inn']) >= 10) { $st = db()->prepare('SELECT * FROM crm_clients WHERE inn=? LIMIT 1'); $st->execute([$v['inn']]); $cur = $st->fetch() ?: null; }
  if (!$cur) foreach ([$v['alias'], $v['company']] as $n) {
    $k = crm_norm($n); if ($k === '') continue;
    foreach (crm_find($n, 5) as $r) if (crm_norm($r['alias']) === $k || crm_norm($r['company']) === $k) { $cur = $r; break 2; }
  }
  if ($cur) {
    $set = []; $a = []; $changed = [];
    foreach ($f as $k) {
      if ($v[$k] === '') continue;
      if ($k === 'note') { if (mb_strpos((string)$cur['note'], $v['note']) === false) { $set[] = 'note=?'; $a[] = trim((string)$cur['note'] . "\n" . $v['note']); $changed[] = 'заметка'; } continue; }
      if (trim((string)$cur[$k]) === '' || ($over && trim((string)$cur[$k]) !== $v[$k])) { $set[] = "$k=?"; $a[] = $v[$k]; $changed[] = $k; }
    }
    if ($set) { $a[] = $cur['id']; db()->prepare('UPDATE crm_clients SET ' . implode(',', $set) . ' WHERE id=?')->execute($a); }
    return ['ok' => true, 'id' => $cur['id'], 'new' => false, 'changed' => $changed, 'name' => crm_name(array_merge($cur, array_filter($v, 'strlen')))];
  }
  if ($v['alias'] === '' && $v['company'] === '') return ['ok' => false, 'error' => 'Нужно хотя бы название клиента'];
  $id = crm_gen_id('cl');
  $cols = array_merge(['id'], $f, ['created_at', 'created_by']);
  $vals = array_merge([$id], array_map(function ($k) use ($v) { return $v[$k]; }, $f), [date('Y-m-d H:i:s'), mb_substr((string)$who, 0, 20)]);
  db()->prepare('INSERT INTO crm_clients (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute($vals);
  return ['ok' => true, 'id' => $id, 'new' => true, 'changed' => [], 'name' => crm_name($v)];
}

/* Контактное лицо клиента: то же имя у того же клиента — дополняем, не дублируем */
function crm_contact_put($cid, $d) {
  if (!crm_tbl_ok('crm_contacts')) return ['ok' => false, 'error' => 'Нет таблицы контактов'];
  $f = ['name', 'post', 'area', 'phone', 'email', 'note'];
  $v = []; foreach ($f as $k) $v[$k] = trim(mb_substr((string)($d[$k] ?? ''), 0, 255));
  if ($v['name'] === '') return ['ok' => false, 'error' => 'Нужно имя контактного лица'];
  $st = db()->prepare('SELECT * FROM crm_contacts WHERE client_id=?'); $st->execute([$cid]);
  foreach ($st->fetchAll() as $r) if (mb_strtolower(trim($r['name'])) === mb_strtolower($v['name'])) {
    $set = []; $a = [];
    foreach ($f as $k) if ($v[$k] !== '' && trim((string)$r[$k]) !== $v[$k]) { $set[] = "$k=?"; $a[] = $v[$k]; }
    if ($set) { $a[] = $r['id']; db()->prepare('UPDATE crm_contacts SET ' . implode(',', $set) . ' WHERE id=?')->execute($a); }
    return ['ok' => true, 'id' => $r['id'], 'new' => false];
  }
  $id = crm_gen_id('ct');
  db()->prepare('INSERT INTO crm_contacts (id,client_id,' . implode(',', $f) . ') VALUES (?,?,' . implode(',', array_fill(0, count($f), '?')) . ')')
    ->execute(array_merge([$id, $cid], array_map(function ($k) use ($v) { return $v[$k]; }, $f)));
  return ['ok' => true, 'id' => $id, 'new' => true];
}

/* Склеить дубли в одного клиента: контакты, КП, учёт сделок и счета переезжают, пустые реквизиты дополняются */
function crm_merge_do($keep, $drop) {
  $st = db()->prepare('SELECT * FROM crm_clients WHERE id=?');
  $st->execute([$keep]); $k = $st->fetch();
  if (!$k) return ['ok' => false, 'error' => 'Клиент не найден'];
  $fields = ['alias', 'company', 'inn', 'kpp', 'ogrn', 'addr', 'bank', 'bik', 'acct', 'corr', 'site'];
  $notes = [trim((string)$k['note'])]; $n = 0;
  $pdo = db(); $pdo->beginTransaction();
  try {
    foreach ($drop as $x) {
      if ($x === $keep) continue;
      $st->execute([$x]); $r = $st->fetch(); if (!$r) continue;
      foreach ($fields as $f) if (trim((string)$k[$f]) === '' && trim((string)$r[$f]) !== '') $k[$f] = $r[$f];
      $nt = trim((string)$r['note']); if ($nt !== '' && !in_array($nt, $notes, true)) $notes[] = $nt;
      foreach (['crm_contacts', 'crm_deals', 'crm_kp', 'crm_inv'] as $t) if (crm_tbl_ok($t)) $pdo->prepare("UPDATE $t SET client_id=? WHERE client_id=?")->execute([$keep, $x]);
      $pdo->prepare('DELETE FROM crm_clients WHERE id=?')->execute([$x]); $n++;
    }
    $set = []; $a = [];
    foreach ($fields as $f) { $set[] = "$f=?"; $a[] = $k[$f]; }
    $set[] = 'note=?'; $a[] = implode("\n", array_filter($notes, 'strlen')); $a[] = $keep;
    $pdo->prepare('UPDATE crm_clients SET ' . implode(',', $set) . ' WHERE id=?')->execute($a);
    $pdo->commit();
  } catch (Throwable $e) { $pdo->rollBack(); return ['ok' => false, 'error' => 'Не удалось склеить: ' . $e->getMessage()]; }
  return ['ok' => true, 'id' => $keep, 'merged' => $n, 'name' => crm_name($k)];
}

/* Группы дублей: одно название без ООО/кавычек или один ИНН */
function crm_dup_groups() {
  if (!crm_tbl_ok('crm_clients')) return [];
  $rows = db()->query('SELECT id,alias,company,inn,created_at FROM crm_clients')->fetchAll();
  $par = []; $own = [];
  $find = function ($x) use (&$par, &$find) { return $par[$x] === $x ? $x : ($par[$x] = $find($par[$x])); };
  foreach ($rows as $r) {
    $par[$r['id']] = $r['id']; $ks = [];
    foreach ([$r['alias'], $r['company']] as $n) { $k = crm_norm($n); if (mb_strlen($k) >= 2) $ks[] = 'n:' . $k; }
    $inn = preg_replace('~\D~', '', (string)$r['inn']); if (strlen($inn) >= 10) $ks[] = 'i:' . $inn;
    foreach ($ks as $k) {
      if (isset($own[$k])) { $a = $find($own[$k]); $b = $find($r['id']); if ($a !== $b) $par[$b] = $a; }
      else $own[$k] = $r['id'];
    }
  }
  $g = []; foreach ($rows as $r) $g[$find($r['id'])][] = $r;
  $out = [];
  foreach ($g as $list) if (count($list) > 1) { usort($list, function ($x, $y) { return strcmp((string)$x['created_at'], (string)$y['created_at']); }); $out[] = $list; }
  return $out;
}

/* Реквизиты по ИНН (DaData; токен лежит в настройках магазина) */
function crm_dadata_inn($inn) {
  $inn = preg_replace('~\D~', '', (string)$inn); if (strlen($inn) !== 10 && strlen($inn) !== 12) return null;
  try { $q = db()->prepare("SELECT v FROM settings WHERE k='dadata_token'"); $q->execute(); $tok = trim((string)$q->fetchColumn()); } catch (Throwable $e) { $tok = ''; }
  if ($tok === '') return null;
  $ch = curl_init('https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party');
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 8,
    CURLOPT_POSTFIELDS => json_encode(['query' => $inn, 'count' => 1]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Token ' . $tok]]);
  $res = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  if ($code !== 200 || !$res) return null;
  $j = json_decode($res, true); $x = $j['suggestions'][0] ?? null; if (!$x) return null;
  $d = $x['data'] ?? [];
  return ['company' => $x['value'] ?? '', 'inn' => $d['inn'] ?? $inn, 'kpp' => $d['kpp'] ?? '', 'ogrn' => $d['ogrn'] ?? '',
          'addr' => $d['address']['unrestricted_value'] ?? ($d['address']['value'] ?? ''), 'status' => $d['state']['status'] ?? '', 'head' => $d['management']['name'] ?? ''];
}
