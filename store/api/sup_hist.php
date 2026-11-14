<?php
/* №555 · ИСТОРИЯ ПОСТАВЩИКА — одна лента на всё, что было с поставщиком: записи менеджеров и Фила (цены, каталоги,
   условия, сроки, скриншоты переписки), переписка WeChat из CRM, сделки, файлы карточки, прайсы.
   Менеджер кидает Филу скриншот или каталог и говорит «занеси в базу» — Фил сам находит поставщика и записывает
   (sup_note); через год любой спросит «что нам предлагал GP Solar?» — Фил ответит по ленте (sup_history).
   Страница ленты: /eco/sup.html?id=<поставщик> (сотрудники ИТК). Записи не удаляются — только руководитель может скрыть ошибочную. */

const SH_KINDS = ['цены', 'каталог', 'условия', 'сроки', 'образцы', 'переписка', 'звонок', 'встреча', 'скриншот', 'счёт', 'претензия', 'заметка'];

function sh_ensure() {
  static $d = false; if ($d) return; $d = true;
  db()->exec("CREATE TABLE IF NOT EXISTS itc_sup_log (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, sup_id VARCHAR(40) NOT NULL, at DATETIME NOT NULL, kind VARCHAR(20) NOT NULL DEFAULT 'заметка',
    title VARCHAR(300) NOT NULL DEFAULT '', text MEDIUMTEXT NULL, files TEXT NULL, deal_id VARCHAR(40) NOT NULL DEFAULT '', source VARCHAR(20) NOT NULL DEFAULT 'fil',
    who VARCHAR(190) NOT NULL DEFAULT '', phone VARCHAR(40) NOT NULL DEFAULT '', hidden TINYINT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sl_sup (sup_id, at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function sh_sup($q) {   // поставщик по id или названию: [строка, ошибка-массив]
  $f = function_exists('kp_find_sup') ? kp_find_sup($q) : [];
  if (!$f) return [null, 'Не нашёл поставщика «' . $q . '» — найди его через suppliers и передай id (если его нет в базе — заведи add_supplier)'];
  if (count($f) > 1) return [null, ['несколько похожих поставщиков — уточни у человека или передай id' => array_map(function ($s) { return ['id' => $s['id'], 'название' => $s['name'], 'город' => $s['city'] ?? '']; }, array_slice($f, 0, 6))]];
  return [$f[0], null];
}
function sh_link($supId) { return 'https://itcpartner.store/eco/sup.html?id=' . rawurlencode((string)$supId); }

/* занести в историю поставщика */
function sh_add($me, $a) {
  sh_ensure();
  [$sup, $err] = sh_sup((string)($a['sup'] ?? '')); if (!$sup) return $err;
  $kind = mb_strtolower(trim((string)($a['kind'] ?? 'заметка'))); if (!in_array($kind, SH_KINDS, true)) $kind = 'заметка';
  $text = trim(mb_substr((string)($a['text'] ?? ''), 0, 20000));
  $at = (string)($a['at'] ?? ''); $at = preg_match('~^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?~', $at) ? substr($at . ' 12:00:00', 0, 19) : date('Y-m-d H:i:s');
  $files = []; $ocr = ''; $fnote = '';
  $ids = array_slice(array_filter(array_map('strval', (array)($a['files'] ?? []))), 0, 6);
  if ($ids && function_exists('fs_attach')) {   // оригиналы — в карточку поставщика (файлы поставщика), ссылки — в запись
    [$done, $ferr] = fs_attach($me, $sup['id'], $ids);
    if ($done) try { $q = db()->prepare('SELECT title,url FROM itc_sup_media WHERE sup_id=? ORDER BY id DESC LIMIT ' . count($done)); $q->execute([$sup['id']]);
      foreach ($q->fetchAll() as $m) $files[] = ['name' => (string)$m['title'], 'url' => (string)$m['url']]; } catch (Throwable $e) {}
    foreach ($ferr ?? [] as $e) $fnote .= "\n(файл не приложен: $e)";
    try { $in = implode(',', array_fill(0, count($ids), '?')); $q = db()->prepare("SELECT name,text FROM itc_fc_file WHERE id IN ($in)"); $q->execute($ids);
      foreach ($q->fetchAll() as $f) $ocr .= "\n— текст из «" . $f['name'] . "»: " . mb_substr(preg_replace('~\s+~u', ' ', (string)$f['text']), 0, 3000); } catch (Throwable $e) {}
  }
  if ($text === '' && $ocr === '') return 'Нечего записать: нужен текст (что предложили, какие цены, сроки, условия) или файл';
  $title = mb_substr(trim((string)($a['title'] ?? '')) ?: ($kind . ': ' . mb_substr(preg_replace('~\s+~u', ' ', $text ?: $ocr), 0, 120)), 0, 300);
  $deal = preg_replace('~[^A-Za-z0-9_-]~', '', (string)($a['deal'] ?? ''));
  db()->prepare('INSERT INTO itc_sup_log (sup_id,at,kind,title,text,files,deal_id,source,who,phone) VALUES (?,?,?,?,?,?,?,?,?,?)')
    ->execute([$sup['id'], $at, $kind, $title, trim($text . $ocr . $fnote), json_encode($files, JSON_UNESCAPED_UNICODE), $deal, (string)($a['source'] ?? 'fil'), (string)($me['name'] ?? ''), (string)($me['phone'] ?? '')]);
  if ($deal !== '' && function_exists('fl_log')) try { fl_log($deal, 'sup', 'Поставщик ' . $sup['name'] . ' — ' . $title, (string)($me['name'] ?? '')); } catch (Throwable $e) {}   // и в карточку сделки CRM
  return ['записано' => $title, 'поставщик' => $sup['name'], 'дата' => substr($at, 0, 10), 'вид' => $kind, 'файлов в карточке' => count($files),
          'лента поставщика' => sh_link($sup['id']), 'как ответить' => 'скажи коротко, что и к какому поставщику записал; дай ссылку на ленту'];
}

/* вся история поставщика одной лентой: [поставщик, записи по убыванию даты] */
function sh_feed($supId, $q = '', $kind = '', $limit = 300) {
  sh_ensure(); $out = []; $like = $q !== '' ? '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%' : '';
  $w = 'sup_id=? AND hidden=0'; $p = [$supId];
  if ($kind !== '') { $w .= ' AND kind=?'; $p[] = $kind; }
  if ($like !== '') { $w .= ' AND (title LIKE ? OR text LIKE ?)'; $p[] = $like; $p[] = $like; }
  $st = db()->prepare("SELECT * FROM itc_sup_log WHERE $w ORDER BY at DESC LIMIT " . (int)$limit); $st->execute($p);
  foreach ($st->fetchAll() as $r) $out[] = ['at' => $r['at'], 'type' => 'запись', 'kind' => $r['kind'], 'title' => $r['title'], 'text' => (string)$r['text'],
    'files' => json_decode((string)$r['files'], true) ?: [], 'who' => $r['who'], 'deal' => $r['deal_id'], 'id' => (int)$r['id']];
  if ($kind === '' || $kind === 'переписка') try {   // переписка WeChat из CRM (чаты, привязанные к поставщику)
    $w = 'c.sup_id=?'; $p = [$supId]; if ($like !== '') { $w .= ' AND (m.ru LIKE ? OR m.text LIKE ?)'; $p[] = $like; $p[] = $like; }
    $st = db()->prepare("SELECT m.at, m.dir, m.who, m.ru, m.text, m.media, c.title FROM itc_wc_msg m JOIN itc_wc_chat c ON c.id=m.chat_id WHERE $w ORDER BY m.at DESC LIMIT " . (int)$limit); $st->execute($p);
    foreach ($st->fetchAll() as $m) $out[] = ['at' => $m['at'], 'type' => 'WeChat', 'kind' => 'переписка', 'title' => ($m['dir'] === 'in' ? 'фабрика' : 'мы') . ' · ' . $m['title'],
      'text' => (string)($m['ru'] ?: $m['text']), 'files' => $m['media'] ? [['name' => 'вложение', 'url' => (string)$m['media']]] : [], 'who' => (string)$m['who']];
  } catch (Throwable $e) {}
  if ($kind === '' || in_array($kind, ['каталог', 'счёт'], true)) try {   // файлы карточки поставщика
    $st = db()->prepare('SELECT * FROM itc_sup_media WHERE sup_id=? ORDER BY id DESC LIMIT 200'); $st->execute([$supId]);
    foreach ($st->fetchAll() as $m) { if ($like !== '' && mb_stripos((string)$m['title'], $q) === false) continue;
      $out[] = ['at' => (string)($m['created_at'] ?? ''), 'type' => 'файл', 'kind' => 'каталог', 'title' => (string)$m['title'], 'text' => '', 'files' => [['name' => (string)$m['title'], 'url' => (string)$m['url']]], 'who' => (string)($m['created_by'] ?? '')]; }
  } catch (Throwable $e) {}
  if ($kind === '') try {   // сделки CRM, где участвовал поставщик
    $st = db()->prepare('SELECT id,title,client,created_at,stage_at,note FROM itc_fl_deal WHERE deleted=0 AND sups LIKE ? ORDER BY created_at DESC LIMIT 50'); $st->execute(['%' . $supId . '%']);
    foreach ($st->fetchAll() as $d) $out[] = ['at' => $d['created_at'], 'type' => 'сделка', 'kind' => 'сделка', 'title' => 'Сделка «' . $d['title'] . '»' . ($d['client'] ? ' · ' . $d['client'] : ''), 'text' => mb_substr((string)$d['note'], 0, 600), 'files' => [], 'who' => '', 'deal' => $d['id']];
  } catch (Throwable $e) {}
  usort($out, function ($x, $y) { return strcmp((string)$y['at'], (string)$x['at']); });
  return array_slice($out, 0, $limit);
}
function sh_history($me, $a) {
  [$sup, $err] = sh_sup((string)($a['sup'] ?? '')); if (!$sup) return $err;
  $kind = mb_strtolower(trim((string)($a['kind'] ?? ''))); if ($kind !== '' && !in_array($kind, SH_KINDS, true)) $kind = '';
  $feed = sh_feed($sup['id'], trim((string)($a['q'] ?? '')), $kind, 120);
  if (!$feed) return ['поставщик' => $sup['name'], 'история' => 'пока пусто — с этим поставщиком ничего не записано и переписки в CRM нет', 'лента' => sh_link($sup['id'])];
  $cnt = []; foreach ($feed as $f) $cnt[$f['kind']] = ($cnt[$f['kind']] ?? 0) + 1;
  return ['поставщик' => $sup['name'], 'всего' => count($feed), 'по видам' => $cnt, 'первое' => substr((string)end($feed)['at'], 0, 10), 'последнее' => substr((string)$feed[0]['at'], 0, 10),
    'лента' => sh_link($sup['id']),
    'записи (новые сверху)' => array_map(function ($f) { return trim(substr((string)$f['at'], 0, 10) . ' · ' . $f['type'] . ' · ' . $f['kind'] . ' · ' . $f['title'] . ($f['who'] ? ' (' . $f['who'] . ')' : ''))
      . ($f['text'] !== '' ? ': ' . mb_substr(preg_replace('~\s+~u', ' ', $f['text']), 0, 500) : '') . ($f['files'] ? ' [файлы: ' . implode(', ', array_map(function ($x) { return $x['url']; }, array_slice($f['files'], 0, 3))) . ']' : ''); }, array_slice($feed, 0, 40)),
    'как ответить' => 'ответь по сути вопроса (цены, сроки, ассортимент, как менялось — сравни по датам), с датами; дай ссылку на ленту'];
}
function sh_tools($me) {
  return [
    'sup_note' => ['{"sup":"id или название поставщика","kind":"' . implode('|', SH_KINDS) . '","text":"что предложили/обсудили: цены (с валютой и условиями), ассортимент, сроки поставки, оплата, MOQ — подробно, своими словами из скриншота/файла","at":"YYYY-MM-DD — дата события, если не сегодня","files":["id приложенных файлов (скриншоты, каталоги, счета)"],"deal":"id сделки CRM, если по сделке"} — ЗАНЕСТИ В ИСТОРИЮ ПОСТАВЩИКА (единая база: карточка поставщика + сделка CRM). «Занеси в базу», скриншот переписки, новый каталог, прайс, предложение — сюда. Поставщика определи сам по тексту/скриншоту (suppliers); файлы прикрепятся к карточке поставщика',
      function ($a) use ($me) { try { return sh_add($me, $a); } catch (Throwable $e) { return 'не записалось: ' . $e->getMessage(); } }],
    'sup_history' => ['{"sup":"id или название поставщика","q":"слово для поиска (цена, модель, срок) или пусто","kind":"цены|каталог|условия|сроки|переписка|… или пусто"} — ИСТОРИЯ ПОСТАВЩИКА за всё время: записи менеджеров, переписка WeChat из CRM, сделки, файлы, каталоги. «Работали ли мы с ним?», «Какие цены давал?», «Когда последнее предложение?», «Как изменилась цена?» — сюда',
      function ($a) use ($me) { try { return sh_history($me, $a); } catch (Throwable $e) { return 'не получилось: ' . $e->getMessage(); } }],
  ];
}
