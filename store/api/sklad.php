<?php
// ==== ИТК · ПРИЛОЖЕНИЕ «СКЛАД В КИТАЕ» · api/sklad.php · №1 (03.11.2026) ====
// Для кладовщика в Китае (интерфейс на китайском, переключатель на русский) и для офиса.
// Работает поверх того, что уже есть в магазине: выкупы ds_procurement, складские партии stock_batches /
// stock_units, наклейки М-… и С-…, чат склада support_messages (kind='warehouse').
//   sk_me        кто вошёл, склады, язык по умолчанию
//   sk_expected  что едет на склад: выкупы 1688 (заказы покупателей) и складские партии
//   sk_find      опознать посылку по коду: номер заказа 1688, номер доставки (трек), номер партии
//   sk_photo     опознать по фото этикетки: распознаём текст, ищем номера, иначе Фил сравнивает с ожидаемым
//   sk_receive   принять: сколько пришло, брак, фото; недостача и брак — задача офису от Фила
//   sk_chat / sk_chat_send   чат с офисом, Фил переводит в обе стороны
//   sk_tr        перевод коротких строк (названия товаров, сообщения) — с кэшем
//   НЕОПОЗНАННЫЙ ГРУЗ (покупки российского офиса без наклеек: аккумулятор, тукрон и т. п.):
//   sk_cargo_new  кладовщик фотографирует груз → карточка груза + сделка в воронке «Грузы в Китае» у закупщика;
//                 Фил по фото подсказывает, что это может быть
//   sk_cargo_list грузы склада: что это (от закупщика), распоряжение по отправке на китайском, переписка
//   sk_cargo_msg  вопрос / ответ по грузу (Фил переводит), sk_cargo_ship — отправлено: трек, фото
//   Закупщик в карточке сделки пишет «что это» и распоряжение: транспортная компания, контакт, телефон, куда (flow.php fl_cargo_save).
// Фил — виртуальный руководитель (Alice AI LLM, запасной — Qwen 3.6, ключ Яндекса из config.php).

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ai_lib.php';
@set_time_limit(90);
$action = $_GET['action'] ?? '';
$IN = ($_SERVER['REQUEST_METHOD'] === 'POST') ? (json_decode(file_get_contents('php://input'), true) ?: []) : [];

function sk_out($a) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function sk_fail($m, $c = 400) { http_response_code($c); sk_out(['ok' => false, 'error' => $m]); }
function sk_in($k, $d = '') { global $IN; return $IN[$k] ?? ($_GET[$k] ?? $d); }
function sk_s($v, $n) { return mb_substr(trim((string)($v ?? '')), 0, $n); }

function sk_me() {
  $s = authUser('itc');
  $sr = (string)($s['staff_role'] ?? '');
  $isWh = $sr === 'warehouse';
  if (!$isWh && !in_array($sr, ['boss', '', 'supervisor', 'manager', 'bossacct'], true)) sk_fail('Нет доступа к складу', 403);
  $wh = $isWh ? (string)($s['supplier_id'] ?? '') : '';
  $name = trim((string)($s['name'] ?? ''));
  if ($isWh) {
    try { $q = db()->prepare('SELECT name FROM staff WHERE phone=? AND warehouse_id=? LIMIT 1'); $q->execute([$s['phone'] ?? '', $wh]); $n = (string)$q->fetchColumn(); if ($n !== '') $name = $n; } catch (Throwable $e) {}
  }
  return ['wh' => $isWh, 'wh_id' => $wh, 'phone' => (string)($s['phone'] ?? ''), 'name' => $name ?: ($isWh ? '仓库' : 'Офис ИТК'), 'role' => $sr];
}

/* ---------- Фил: короткий вызов модели Яндекса ---------- */
function sk_ai($sys, $user, $maxTok = 1200) {
  $YK = defined('YC_API_KEY') ? YC_API_KEY : ($GLOBALS['YC_API_KEY'] ?? '');
  $YF = defined('YC_FOLDER_ID') ? YC_FOLDER_ID : ($GLOBALS['YC_FOLDER_ID'] ?? '');
  if (!$YK || !$YF) return null;
  foreach (['aliceai-llm', 'qwen3.6-35b-a3b'] as $m) {
    $req = json_encode(['model' => "gpt://$YF/$m", 'temperature' => 0.2, 'max_tokens' => $maxTok, 'stream' => false,
      'messages' => [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]]], JSON_UNESCAPED_UNICODE);
    foreach (['Bearer ', 'Api-Key '] as $auth) {
      [$http, $r] = itcai_post('https://llm.api.cloud.yandex.net/v1/chat/completions', $req, ['Content-Type: application/json', 'Authorization: ' . $auth . $YK, 'OpenAI-Project: ' . $YF, 'x-folder-id: ' . $YF], 60);
      if ($http === 200) {
        $t = trim(preg_replace('~<think>.*?</think>~s', '', (string)(json_decode($r, true)['choices'][0]['message']['content'] ?? '')));
        if ($t !== '') return $t;
        break;
      }
      if (!in_array($http, [401, 403], true)) break;
    }
  }
  return null;
}
/* перевод списка строк: to = zh | ru; кэш на 60 дней */
function sk_tr_list($list, $to) {
  $out = []; $todo = [];
  foreach ($list as $i => $t) {
    $t = trim((string)$t); if ($t === '') { $out[$i] = ''; continue; }
    $k = md5("sktr1|$to|$t");
    if ($c = itcai_cache_get('sktr', $k, 86400 * 60)) { $out[$i] = (string)($c['t'] ?? ''); continue; }
    $todo[$i] = $t;
  }
  if ($todo) {
    $lang = $to === 'zh' ? 'китайский (упрощённый)' : 'русский';
    $num = []; $n = 1; foreach ($todo as $i => $t) { $num[$n] = $i; $n++; }
    $src = implode("\n", array_map(function ($n, $t) { return $n . '. ' . str_replace("\n", ' / ', $t); }, array_keys($num), array_values($todo)));
    $r = sk_ai("Ты — Фил, помощник склада ИТК в Китае. Переведи строки на $lang язык: коротко, просто, как говорят на складе. "
      . "Номера заказов, коды (М-…, С-…), числа и размеры не меняй. Верни ТОЛЬКО JSON-массив [{\"n\":номер,\"t\":\"перевод\"}].", $src, 2000);
    $arr = null;
    if ($r !== null) { $a = strpos($r, '['); $b = strrpos($r, ']'); if ($a !== false && $b !== false) $arr = json_decode(substr($r, $a, $b - $a + 1), true); }
    $by = []; foreach ((array)$arr as $o) if (is_array($o) && isset($o['n'])) $by[(int)$o['n']] = (string)($o['t'] ?? '');
    foreach ($num as $n => $i) {
      $tt = $by[$n] ?? '';
      $out[$i] = $tt;
      if ($tt !== '') itcai_cache_put('sktr', md5("sktr1|$to|" . $todo[$i]), ['t' => $tt]);
    }
  }
  ksort($out); return $out;
}

/* ---------- номера и ключи наклеек — так же, как в магазине (М-заказ-номер, ключ подлинности) ---------- */
function sk_subno($oid) {
  try { db()->query("ALTER TABLE order_items ADD COLUMN sub_no INT NULL"); } catch (Throwable $e) {}
  try { db()->query("ALTER TABLE order_items ADD COLUMN lbl_key VARCHAR(24) NULL"); } catch (Throwable $e) {}
  $q = db()->prepare('SELECT id, sub_no, lbl_key FROM order_items WHERE order_id=? ORDER BY id'); $q->execute([$oid]);
  $rows = $q->fetchAll(); $mx = 0; $out = [];
  foreach ($rows as $r) if ((int)$r['sub_no'] > $mx) $mx = (int)$r['sub_no'];
  foreach ($rows as $r) {
    $n = (int)$r['sub_no']; $k = (string)($r['lbl_key'] ?? '');
    if ($n <= 0) { $n = ++$mx; db()->prepare('UPDATE order_items SET sub_no=? WHERE id=?')->execute([$n, $r['id']]); }
    if ($k === '') { $k = bin2hex(random_bytes(6)); db()->prepare("UPDATE order_items SET lbl_key=? WHERE id=? AND (lbl_key IS NULL OR lbl_key='')")->execute([$k, $r['id']]); }
    $out[$r['id']] = [$n, $k];
  }
  return $out;
}

/* ---------- что едет на склад ---------- */
const SK_PROC_SQL = "SELECT pc.id, pc.order_id, pc.product_id, pc.qty, pc.status, pc.source_order_id, pc.logistics_tracking, pc.ordered_at, pc.received_at,
       COALESCE(NULLIF(p.name,''),(SELECT i2.name FROM order_items i2 WHERE i2.order_id=pc.order_id AND i2.product_id=pc.product_id LIMIT 1)) name_ru, p.img,
       o.num order_num, o.addr_city city, w.name pvz, w.code pvz_code,
       (SELECT sk.properties_cn FROM ob_sku sk WHERE sk.product_id=pc.product_id LIMIT 1) sku_cn,
       (SELECT ob.title_cn FROM ob_sku sk JOIN ob_item ob ON ob.num_iid=sk.num_iid WHERE sk.product_id=pc.product_id LIMIT 1) title_cn,
       (SELECT ob.shop_name FROM ob_sku sk JOIN ob_item ob ON ob.num_iid=sk.num_iid WHERE sk.product_id=pc.product_id LIMIT 1) shop
  FROM ds_procurement pc
  LEFT JOIN products p ON p.id=pc.product_id
  LEFT JOIN orders o ON o.id=pc.order_id
  LEFT JOIN warehouses w ON w.id=o.warehouse_id";
function sk_proc_rows($where, $args) {
  try { $q = db()->prepare(SK_PROC_SQL . ' WHERE ' . $where . ' ORDER BY pc.ordered_at DESC LIMIT 300'); $q->execute($args); return $q->fetchAll(); }
  catch (Throwable $e) {
    // таблиц 1688 может не быть — без китайского названия
    $sql = preg_replace('~,\s*\(SELECT sk\.properties_cn.*?\) shop~s', ", '' sku_cn, '' title_cn, '' shop", SK_PROC_SQL);
    try { $q = db()->prepare($sql . ' WHERE ' . $where . ' ORDER BY pc.ordered_at DESC LIMIT 300'); $q->execute($args); return $q->fetchAll(); } catch (Throwable $e2) { error_log('sklad: ' . $e2->getMessage()); return []; }
  }
}
function sk_batches($where, $args) {
  try {
    $q = db()->prepare("SELECT b.id, b.product_id, b.name, b.img, b.qty, b.source, b.order_id, b.order_num, b.buy_ref, b.bought_at, b.cn_rcv_at,
                               (SELECT ob.title_cn FROM ob_sku sk JOIN ob_item ob ON ob.num_iid=sk.num_iid WHERE sk.product_id=b.product_id LIMIT 1) title_cn,
                               (SELECT sk.properties_cn FROM ob_sku sk WHERE sk.product_id=b.product_id LIMIT 1) sku_cn
                          FROM stock_batches b WHERE $where ORDER BY b.id DESC LIMIT 300");
    $q->execute($args); return $q->fetchAll();
  } catch (Throwable $e) {
    try { $q = db()->prepare("SELECT b.id, b.product_id, b.name, b.img, b.qty, b.source, b.order_id, b.order_num, b.buy_ref, b.bought_at, b.cn_rcv_at, '' title_cn, '' sku_cn FROM stock_batches b WHERE $where ORDER BY b.id DESC LIMIT 300");
          $q->execute($args); return $q->fetchAll(); } catch (Throwable $e2) { return []; }
  }
}
/* план наклеек по найденным выкупам: заказу — наклейки М-…, сверх заказа — складские С-… */
function sk_plan($procs, $batches) {
  $items = []; $seenB = [];
  foreach ($procs as $p) {
    $labels = [];
    if ($p['order_id']) {
      $keys = sk_subno($p['order_id']);
      $q = db()->prepare('SELECT id, qty, name FROM order_items WHERE order_id=? AND product_id=?'); $q->execute([$p['order_id'], $p['product_id']]);
      foreach ($q->fetchAll() as $it) {
        [$n, $k] = $keys[$it['id']] ?? [0, ''];
        $labels[] = ['code' => $p['order_num'] . '-' . $n, 'key' => $k, 'nm' => $it['name'] ?: $p['name_ru'], 'qty' => (int)$it['qty'], 'pvz' => (string)$p['pvz']];
      }
    }
    $items[] = ['kind' => 'order', 'id' => $p['id'], 'qty' => (int)$p['qty'], 'status' => $p['status'], 'name_ru' => $p['name_ru'], 'img' => $p['img'],
      'title_cn' => (string)$p['title_cn'], 'sku_cn' => (string)$p['sku_cn'], 'shop' => (string)$p['shop'], 'src' => (string)$p['source_order_id'],
      'track' => (string)$p['logistics_tracking'], 'order_num' => $p['order_num'], 'city' => (string)$p['city'], 'pvz' => (string)$p['pvz'], 'labels' => $labels];
    // сверх заказа: складская партия того же товара по этому заказу или по тому же заказу 1688
    foreach (sk_batches("b.product_id=? AND b.cn_rcv_at IS NULL AND (b.order_id=? OR (b.buy_ref<>'' AND b.buy_ref=?))", [$p['product_id'], $p['order_id'], (string)$p['source_order_id']]) as $b)
      if (empty($seenB[$b['id']])) { $seenB[$b['id']] = 1; $batches[] = $b; }
  }
  foreach ($batches as $b) {
    if (!empty($seenB[$b['id']]) && $seenB[$b['id']] === 2) continue; $seenB[$b['id']] = 2;
    $uq = db()->prepare("SELECT seq, code, lbl_key FROM stock_units WHERE batch_id=? ORDER BY seq"); $uq->execute([$b['id']]);
    $items[] = ['kind' => 'stock', 'id' => (int)$b['id'], 'qty' => (int)$b['qty'], 'name_ru' => $b['name'], 'img' => $b['img'], 'title_cn' => (string)$b['title_cn'], 'sku_cn' => (string)$b['sku_cn'],
      'src' => (string)$b['buy_ref'], 'received' => !empty($b['cn_rcv_at']),
      'batch' => ['id' => (int)$b['id'], 'num' => str_pad((string)$b['id'], 4, '0', STR_PAD_LEFT), 'name' => $b['name'], 'qty' => (int)$b['qty']],
      'units' => array_map(function ($u) { return ['seq' => (int)$u['seq'], 'code' => $u['code'], 'key' => $u['lbl_key']]; }, $uq->fetchAll())];
  }
  return $items;
}
function sk_find_code($code) {
  $code = trim($code); $tail = preg_replace('~[^0-9A-Za-z]~', '', $code);
  if (mb_strlen($tail) < 5) return [[], []];
  $procs = sk_proc_rows("pc.status IN ('ordered','received','pending') AND (pc.source_order_id=? OR pc.logistics_tracking=? OR (LENGTH(?)>=8 AND (pc.source_order_id LIKE ? OR pc.logistics_tracking LIKE ?)))",
                        [$code, $code, $tail, '%' . $tail . '%', '%' . $tail . '%']);
  // весь заказ 1688: другие товары того же номера
  $srcs = array_unique(array_filter(array_column($procs, 'source_order_id')));
  foreach ($srcs as $sid) foreach (sk_proc_rows("pc.source_order_id=? AND pc.status IN ('ordered','received')", [$sid]) as $r) { $have = array_column($procs, 'id'); if (!in_array($r['id'], $have)) $procs[] = $r; }
  $batches = sk_batches("b.buy_ref<>'' AND (b.buy_ref=? OR (LENGTH(?)>=8 AND b.buy_ref LIKE ?))", [$code, $tail, '%' . $tail . '%']);
  return [$procs, $batches];
}

/* ---------- неопознанный груз ---------- */
function sk_cargo_ensure() {
  db()->exec("CREATE TABLE IF NOT EXISTS itc_sk_cargo (
    id INT AUTO_INCREMENT PRIMARY KEY, wh_id VARCHAR(64) NOT NULL DEFAULT '', status VARCHAR(16) NOT NULL DEFAULT 'new',
    photos TEXT NULL, note TEXT NULL, note_ru TEXT NULL, boxes INT NOT NULL DEFAULT 0, weight DECIMAL(10,2) NOT NULL DEFAULT 0, fil TEXT NULL,
    what_ru TEXT NULL, what_zh TEXT NULL, carrier VARCHAR(190) NOT NULL DEFAULT '', contact VARCHAR(190) NOT NULL DEFAULT '', phone VARCHAR(120) NOT NULL DEFAULT '',
    address TEXT NULL, instr_ru TEXT NULL, instr_zh TEXT NULL, track VARCHAR(190) NOT NULL DEFAULT '', ship_photos TEXT NULL,
    deal_id VARCHAR(40) NOT NULL DEFAULT '', created_by VARCHAR(190) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    shipped_at DATETIME NULL, KEY idx_cargo_deal (deal_id), KEY idx_cargo_wh (wh_id, status)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
/* воронка «Грузы в Китае»: стадия по коду действия */
function sk_cargo_pipe() {
  $db = db();
  $p = (string)$db->query("SELECT id FROM itc_fl_pipe WHERE deleted=0 AND name='Грузы в Китае' LIMIT 1")->fetchColumn();
  if ($p === '') {
    $p = 'fp' . date('ymd') . substr(bin2hex(random_bytes(5)), 0, 8);
    $db->prepare("INSERT INTO itc_fl_pipe (id,name,kind,owner_phone,owner_name,sort,created_by) VALUES (?,'Грузы в Китае','other','','',890,'Фил')")->execute([$p]);
    $ins = $db->prepare('INSERT INTO itc_fl_stage (id,pipe_id,name,color,sem,sort) VALUES (?,?,?,?,?,?)');
    foreach ([['Неопознанный груз', '#FF5752', 'work'], ['Опознан — ждёт распоряжения', '#FFA900', 'work'], ['Распоряжение дано', '#39A8EF', 'work'], ['Отправлен', '#7BD500', 'win'], ['Списан', '#8EA3B8', 'lose']] as $i => $st)
      $ins->execute(['fs' . date('ymd') . substr(bin2hex(random_bytes(5)), 0, 8), $p, $st[0], $st[1], $st[2], ($i + 1) * 10]);
  }
  return $p;
}
function sk_cargo_stage($deal, $name) {
  $q = db()->prepare('SELECT s.id FROM itc_fl_stage s JOIN itc_fl_deal d ON d.pipe_id=s.pipe_id WHERE d.id=? AND s.name=? LIMIT 1'); $q->execute([$deal, $name]);
  $sid = (string)$q->fetchColumn(); if ($sid === '') return;
  db()->prepare('UPDATE itc_fl_deal SET stage_id=?, stage_at=NOW(), updated_at=NOW() WHERE id=? AND stage_id<>?')->execute([$sid, $deal, $sid]);
  db()->prepare("INSERT INTO itc_fl_log (deal_id,kind,text,who) VALUES (?,'stage',?,'Фил')")->execute([$deal, 'Стадия: ' . $name]);
}
/* Фил смотрит на фото груза (Qwen 3.6 видит картинки) */
function sk_vision($img, $note) {
  $YK = defined('YC_API_KEY') ? YC_API_KEY : ($GLOBALS['YC_API_KEY'] ?? ''); $YF = defined('YC_FOLDER_ID') ? YC_FOLDER_ID : ($GLOBALS['YC_FOLDER_ID'] ?? '');
  if (!$YK || !$YF || !preg_match('~^data:image/(jpeg|png);base64,~', $img)) return '';
  $req = json_encode(['model' => "gpt://$YF/qwen3.6-35b-a3b", 'temperature' => 0.2, 'max_tokens' => 400, 'stream' => false, 'messages' => [
    ['role' => 'system', 'content' => 'Ты — Фил, помощник склада ИТК. По фото груза на складе в Китае коротко скажи по-русски, что это за товар (тип, марка и модель, если видно на коробке), сколько мест, есть ли видимые повреждения. 1–3 предложения, без выдумок.'],
    ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Заметка кладовщика: ' . ($note ?: 'нет')], ['type' => 'image_url', 'image_url' => ['url' => $img]]]]]], JSON_UNESCAPED_UNICODE);
  foreach (['Bearer ', 'Api-Key '] as $auth) {
    [$http, $r] = itcai_post('https://llm.api.cloud.yandex.net/v1/chat/completions', $req, ['Content-Type: application/json', 'Authorization: ' . $auth . $YK, 'OpenAI-Project: ' . $YF, 'x-folder-id: ' . $YF], 60);
    if ($http === 200) return trim(preg_replace('~<think>.*?</think>~s', '', (string)(json_decode($r, true)['choices'][0]['message']['content'] ?? '')));
    if (!in_array($http, [401, 403], true)) break;
  }
  return '';
}

/* ---------- проблема при приёмке → задача офису (воронка «Склад в Китае») + сообщение в чат ---------- */
function sk_problem($me, $title, $text) {
  try {
    $db = db();
    $p = $db->query("SELECT id FROM itc_fl_pipe WHERE deleted=0 AND name='Склад в Китае' LIMIT 1")->fetchColumn();
    $resp = (string)$db->query("SELECT def_phone FROM itc_fl_pipe WHERE deleted=0 AND kind='purchase' ORDER BY sort LIMIT 1")->fetchColumn();
    if (!$p) {
      $p = 'fp' . date('ymd') . substr(bin2hex(random_bytes(5)), 0, 8);
      $db->prepare("INSERT INTO itc_fl_pipe (id,name,kind,owner_phone,owner_name,sort,created_by) VALUES (?,'Склад в Китае','other','','',900,'Фил')")->execute([$p]);
      $ins = $db->prepare('INSERT INTO itc_fl_stage (id,pipe_id,name,color,sem,sort) VALUES (?,?,?,?,?,?)');
      foreach ([['Новые проблемы', '#FF5752', 'work'], ['Решаем с продавцом', '#FFA900', 'work'], ['Решено', '#7BD500', 'win'], ['Списано', '#8EA3B8', 'lose']] as $i => $s)
        $ins->execute(['fs' . date('ymd') . substr(bin2hex(random_bytes(5)), 0, 8), $p, $s[0], $s[1], $s[2], ($i + 1) * 10]);
    }
    $st = $db->prepare('SELECT id FROM itc_fl_stage WHERE pipe_id=? ORDER BY sort LIMIT 1'); $st->execute([$p]); $sid = (string)$st->fetchColumn();
    $rn = ''; if ($resp !== '') { $q = $db->prepare('SELECT name FROM staff WHERE phone=? LIMIT 1'); $q->execute([$resp]); $rn = (string)$q->fetchColumn(); }
    $id = 'fd' . date('ymd') . substr(bin2hex(random_bytes(5)), 0, 8);
    $db->prepare("INSERT INTO itc_fl_deal (id,pipe_id,stage_id,title,note,resp_phone,resp_name,next_at,next_do,sort,stage_at,created_by,created_phone) VALUES (?,?,?,?,?,?,?,?,?,-1,NOW(),'Фил','')")
       ->execute([$id, $p, $sid, mb_substr($title, 0, 250), mb_substr($text, 0, 4000), $resp, $rn, date('Y-m-d'), 'Разобраться с приёмкой: написать продавцу 1688']);
    $db->prepare("INSERT INTO itc_fl_log (deal_id,kind,text,who) VALUES (?,'create',?,'Фил')")->execute([$id, 'Склад в Китае: ' . mb_substr($text, 0, 1500)]);
    return $id;
  } catch (Throwable $e) { error_log('sklad problem: ' . $e->getMessage()); return ''; }
}
function sk_chat_put($wh, $sender, $staff, $text) {
  $name = '';
  try { $q = db()->prepare('SELECT name FROM warehouses WHERE id=?'); $q->execute([$wh]); $name = (string)$q->fetchColumn(); } catch (Throwable $e) {}
  db()->prepare("INSERT INTO support_messages (phone,name,sender,topic,text,kind,read_itc,read_buyer,staff,created_at) VALUES (?,?,?,?,?,'warehouse',?,?,?,NOW())")
    ->execute([$wh, $name ?: 'Склад', $sender, 'Склад', $text, $sender === 'buyer' ? 0 : 1, $sender === 'buyer' ? 1 : 0, $staff]);
}

/* ---------- вход кладовщика по коду приглашения (отдельное приложение «ИТК 仓库», без телефона и СМС) ---------- */
function sk_inv_ensure() {
  db()->exec("CREATE TABLE IF NOT EXISTS itc_sk_invite (
    code VARCHAR(10) NOT NULL PRIMARY KEY, wh_id VARCHAR(40) NOT NULL, created_by VARCHAR(190) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, used_at DATETIME NULL, uses INT NOT NULL DEFAULT 0
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
if ($action === 'sk_login') {
  try {
    sk_inv_ensure();
    $code = preg_replace('~\D~', '', (string)sk_in('code'));
    if (strlen($code) !== 6) sk_fail('请输入6位数字登录码 · Введите 6 цифр кода');
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $bad = itcai_cache_get('sklog', md5($ip), 900); if ((int)($bad['n'] ?? 0) >= 8) sk_fail('尝试次数太多，请15分钟后再试 · Слишком много попыток, подождите 15 минут', 429);
    $q = db()->prepare('SELECT * FROM itc_sk_invite WHERE code=? AND created_at > NOW() - INTERVAL 7 DAY'); $q->execute([$code]); $inv = $q->fetch();
    if (!$inv) { itcai_cache_put('sklog', md5($ip), ['n' => (int)($bad['n'] ?? 0) + 1]); sk_fail('登录码不正确或已过期 · Код неверный или устарел', 403); }
    $tok = 't' . bin2hex(random_bytes(14));
    $ph = 'wh-' . $inv['wh_id'];
    try { db()->prepare("INSERT INTO sessions (token,phone,role,supplier_id,staff_role) VALUES (?,?,'itc',?,'warehouse')")->execute([$tok, $ph, $inv['wh_id']]); }
    catch (Throwable $e) { sk_fail('Ошибка входа: ' . $e->getMessage(), 500); }
    db()->prepare('UPDATE itc_sk_invite SET used_at=NOW(), uses=uses+1 WHERE code=?')->execute([$code]);
    sk_out(['ok' => true, 't' => $tok]);
  } catch (Throwable $e) { sk_fail('Ошибка сервера: ' . $e->getMessage(), 500); }
}

try {
  $me = sk_me();
  switch ($action) {

  /* офис: код и ссылка для кладовщика — QR-код рисует страница */
  case 'sk_invite': {
    if ($me['wh']) sk_fail('Нет доступа', 403);
    sk_inv_ensure();
    $wh = (string)sk_in('wh'); if ($wh === '') sk_fail('Выберите склад');
    $q = db()->prepare('SELECT code,created_at FROM itc_sk_invite WHERE wh_id=? AND created_at > NOW() - INTERVAL 6 DAY ORDER BY created_at DESC LIMIT 1'); $q->execute([$wh]);
    $c = $q->fetch();
    if (!$c || (int)sk_in('new') === 1) {
      do { $code = (string)random_int(100000, 999999); $e = db()->prepare('SELECT 1 FROM itc_sk_invite WHERE code=?'); $e->execute([$code]); } while ($e->fetchColumn());
      db()->prepare('INSERT INTO itc_sk_invite (code,wh_id,created_by) VALUES (?,?,?)')->execute([$code, $wh, $me['name']]);
      $c = ['code' => $code, 'created_at' => date('Y-m-d H:i:s')];
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'itcpartner.store';
    sk_out(['ok' => true, 'code' => $c['code'], 'url' => 'https://' . $host . '/sklad/get/?c=' . $c['code'], 'till' => date('d.m.Y', strtotime($c['created_at'] . ' +7 day'))]);
  }

  /* фоновая проверка из приложения склада (без Google): что нового для кладовщика — уведомления на китайском */
  case 'sk_poll': {
    if (!$me['wh']) sk_out(['ok' => true, 'items' => []]);
    $since = (int)sk_in('since', 0); $since = $since > 0 ? date('Y-m-d H:i:s', $since) : date('Y-m-d H:i:s', time() - 900);
    $items = [];
    $q = db()->prepare("SELECT COUNT(*) FROM support_messages WHERE kind='warehouse' AND phone=? AND sender<>'buyer' AND created_at > ?"); $q->execute([$me['wh_id'], $since]);
    if ($n = (int)$q->fetchColumn()) $items[] = ['id' => 'chat', 'title' => '办公室消息', 'text' => '办公室发来 ' . $n . ' 条新消息', 'url' => '/sklad/?tab=ch'];
    try {
      sk_cargo_ensure();
      $q = db()->prepare("SELECT c.id FROM itc_sk_cargo c JOIN itc_fl_log l ON l.deal_id=c.deal_id AND l.kind='cargo' AND l.at > ? WHERE c.wh_id=? AND c.status='order' GROUP BY c.id"); $q->execute([$since, $me['wh_id']]);
      foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) $items[] = ['id' => 'cg' . $id, 'title' => '发货指示', 'text' => '无标签货 №' . $id . '：办公室已给出发货指示', 'url' => '/sklad/?tab=cg'];
      $q = db()->prepare("SELECT c.id FROM itc_sk_cargo c JOIN itc_fl_log l ON l.deal_id=c.deal_id AND l.kind='comment' AND l.at > ? WHERE c.wh_id=? GROUP BY c.id"); $q->execute([$since, $me['wh_id']]);
      foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) $items[] = ['id' => 'cq' . $id, 'title' => '办公室回复', 'text' => '无标签货 №' . $id . '：办公室回复了', 'url' => '/sklad/?tab=cg'];
    } catch (Throwable $e) {}
    sk_out(['ok' => true, 'items' => $items, 'now' => time()]);
  }


  case 'sk_me': {
    $whs = [];
    try { $whs = db()->query("SELECT id,name,city,code FROM warehouses ORDER BY name")->fetchAll(); } catch (Throwable $e) {}
    if ($me['wh']) $whs = array_values(array_filter($whs, function ($w) use ($me) { return (string)$w['id'] === $me['wh_id']; }));
    sk_out(['ok' => true, 'me' => $me, 'whs' => $whs, 'lang' => $me['wh'] ? 'zh' : 'ru', 'printer' => true]);
  }

  case 'sk_expected': {
    $procs = sk_proc_rows("pc.status='ordered'", []);
    $batches = sk_batches("b.bought_at IS NOT NULL AND b.cn_rcv_at IS NULL", []);
    $now = time(); $late = 0;
    $list = [];
    foreach ($procs as $p) {
      $d = $p['ordered_at'] ? (int)floor(($now - strtotime($p['ordered_at'])) / 86400) : 0; if ($d >= 7) $late++;
      $list[] = ['kind' => 'order', 'id' => $p['id'], 'name_ru' => $p['name_ru'], 'img' => $p['img'], 'title_cn' => (string)$p['title_cn'], 'sku_cn' => (string)$p['sku_cn'], 'shop' => (string)$p['shop'],
        'qty' => (int)$p['qty'], 'src' => (string)$p['source_order_id'], 'track' => (string)$p['logistics_tracking'], 'order_num' => $p['order_num'], 'pvz' => (string)$p['pvz'], 'days' => $d];
    }
    foreach ($batches as $b) {
      $d = $b['bought_at'] ? (int)floor(($now - strtotime($b['bought_at'])) / 86400) : 0; if ($d >= 7) $late++;
      $list[] = ['kind' => 'stock', 'id' => (int)$b['id'], 'name_ru' => $b['name'], 'img' => $b['img'], 'title_cn' => (string)$b['title_cn'], 'sku_cn' => (string)$b['sku_cn'], 'shop' => '',
        'qty' => (int)$b['qty'], 'src' => (string)$b['buy_ref'], 'track' => '', 'order_num' => '', 'pvz' => '', 'days' => $d];
    }
    usort($list, function ($a, $b) { return $b['days'] - $a['days']; });
    sk_out(['ok' => true, 'rows' => $list, 'late' => $late]);
  }

  case 'sk_find': {
    [$procs, $batches] = sk_find_code((string)sk_in('code'));
    sk_out(['ok' => true, 'items' => sk_plan($procs, $batches)]);
  }

  case 'sk_open': {   // открыть из списка «Ожидаем»
    $kind = (string)sk_in('kind'); $id = (string)sk_in('id');
    if ($kind === 'stock') sk_out(['ok' => true, 'items' => sk_plan([], sk_batches('b.id=?', [(int)$id]))]);
    $p = sk_proc_rows('pc.id=?', [$id]);
    if ($p && $p[0]['source_order_id']) { [$procs, $batches] = sk_find_code($p[0]['source_order_id']); if ($procs) sk_out(['ok' => true, 'items' => sk_plan($procs, $batches)]); }
    sk_out(['ok' => true, 'items' => sk_plan($p, [])]);
  }

  case 'sk_photo': {
    $img = (string)sk_in('img');
    if (!preg_match('~^data:image/(jpeg|png);base64,~', $img, $m)) sk_fail('Это не фотография');
    $b64 = substr($img, strpos($img, ',') + 1); if (strlen($b64) > 6 * 1024 * 1024) sk_fail('Снимок слишком большой');
    $YK = defined('YC_API_KEY') ? YC_API_KEY : ($GLOBALS['YC_API_KEY'] ?? ''); $YF = defined('YC_FOLDER_ID') ? YC_FOLDER_ID : ($GLOBALS['YC_FOLDER_ID'] ?? '');
    if (!$YK || !$YF) sk_fail('Нет ключа Яндекса');
    $body = json_encode(['mimeType' => $m[1] === 'png' ? 'PNG' : 'JPEG', 'languageCodes' => ['zh', 'en'], 'model' => 'page', 'content' => $b64]);
    $text = '';
    foreach (['Bearer ', 'Api-Key '] as $auth) {
      [$http, $resp] = itcai_post('https://ocr.api.cloud.yandex.net/ocr/v1/recognizeText', $body, ['Content-Type: application/json', 'Authorization: ' . $auth . $YK, 'x-folder-id: ' . $YF, 'x-data-logging-enabled: false'], 30);
      if ($http === 200) { $text = (string)(json_decode($resp, true)['result']['textAnnotation']['fullText'] ?? ''); break; }
      if (!in_array($http, [401, 403], true)) break;
    }
    if (trim($text) === '') sk_out(['ok' => true, 'text' => '', 'items' => [], 'how' => 'none']);
    // 1) номера на этикетке: трек, номер заказа 1688
    preg_match_all('~[A-Z]{0,4}\d[\d\-]{7,}\d~', strtoupper($text), $mm);
    foreach (array_unique($mm[0]) as $c) { [$procs, $batches] = sk_find_code(str_replace('-', '', $c)); if ($procs || $batches) sk_out(['ok' => true, 'text' => $text, 'items' => sk_plan($procs, $batches), 'how' => 'code', 'code' => $c]); }
    // 2) Фил сравнивает этикетку с тем, что едет на склад
    $exp = sk_proc_rows("pc.status='ordered'", []); $bat = sk_batches("b.bought_at IS NOT NULL AND b.cn_rcv_at IS NULL", []);
    $cand = [];
    foreach ($exp as $p) $cand[] = 'P' . $p['id'] . ' | ' . $p['title_cn'] . ' | ' . $p['sku_cn'] . ' | ' . $p['shop'] . ' | ' . $p['name_ru'] . ' | ' . $p['qty'] . ' шт';
    foreach ($bat as $b) $cand[] = 'B' . $b['id'] . ' | ' . $b['title_cn'] . ' | ' . $b['sku_cn'] . ' | ' . $b['name'] . ' | ' . $b['qty'] . ' шт';
    if (!$cand) sk_out(['ok' => true, 'text' => $text, 'items' => [], 'how' => 'none']);
    $r = sk_ai("Ты — Фил, помощник склада ИТК в Китае. По тексту с этикетки посылки (продавец, название товара, вариант, количество) "
      . "выбери из списка ожидаемых выкупов те, что подходят. Верни ТОЛЬКО JSON {\"ids\":[\"P…\" или \"B…\"],\"why\":\"коротко почему\"}. Если ничего не подходит — пустой список.",
      "ТЕКСТ ЭТИКЕТКИ:\n" . mb_substr($text, 0, 2500) . "\n\nОЖИДАЕМ (id | название 1688 | вариант | продавец | название по-русски | кол-во):\n" . implode("\n", array_slice($cand, 0, 250)), 600);
    $j = itcai_json($r); $procs = []; $batches = [];
    foreach ((array)($j['ids'] ?? []) as $id) {
      $id = (string)$id;
      if ($id !== '' && $id[0] === 'P') foreach ($exp as $p) if ('P' . $p['id'] === $id) $procs[] = $p;
      if ($id !== '' && $id[0] === 'B') foreach ($bat as $b) if ('B' . $b['id'] === $id) $batches[] = $b;
    }
    sk_out(['ok' => true, 'text' => $text, 'items' => sk_plan(array_slice($procs, 0, 6), array_slice($batches, 0, 6)), 'how' => 'fil', 'why' => (string)($j['why'] ?? '')]);
  }

  case 'sk_receive': {
    $items = array_values(array_filter((array)sk_in('items', []), 'is_array'));
    if (!$items) sk_fail('Нечего принимать');
    $dam = !empty(sk_in('damaged')); $note = sk_s(sk_in('note'), 1000);
    $photos = array_slice(array_values(array_filter(array_map('strval', (array)sk_in('photos', [])), function ($u) { return preg_match('~^https?://|^/uploads/~', $u); })), 0, 6);
    $who = $me['name']; $short = []; $done = 0;
    foreach ($items as $it) {
      $got = max(0, (int)($it['got'] ?? 0)); $need = max(0, (int)($it['qty'] ?? 0)); $nm = sk_s($it['name'] ?? '', 200);
      if (($it['kind'] ?? '') === 'order') {
        $id = (string)($it['id'] ?? '');
        if ($got >= $need && $got > 0) {
          db()->prepare("UPDATE ds_procurement SET status='received', received_at=NOW() WHERE id=? AND status IN ('ordered','pending')")->execute([$id]);
          if ($photos) { try { db()->query("ALTER TABLE ds_procurement ADD COLUMN wh_photos TEXT NULL"); } catch (Throwable $e) {}
            $q = db()->prepare('SELECT wh_photos FROM ds_procurement WHERE id=?'); $q->execute([$id]); $old = json_decode((string)$q->fetchColumn(), true) ?: [];
            db()->prepare('UPDATE ds_procurement SET wh_photos=? WHERE id=?')->execute([json_encode(array_values(array_unique(array_merge($old, $photos)))), $id]); }
          $done++;
        }
        if ($got < $need) $short[] = "$nm: пришло $got из $need (заказ " . sk_s($it['order_num'] ?? '', 40) . ')';
      } elseif (($it['kind'] ?? '') === 'stock') {
        $bid = (int)($it['id'] ?? 0);
        if ($got > 0) { db()->prepare('UPDATE stock_batches SET cn_rcv_at=NOW(), cn_rcv_by=? WHERE id=? AND cn_rcv_at IS NULL')->execute([$who, $bid]); $done++; }
        if ($got < $need) $short[] = "$nm: пришло $got из $need (складская партия " . str_pad((string)$bid, 4, '0', STR_PAD_LEFT) . ')';
      }
    }
    $deal = '';
    if ($short || $dam) {
      $txt = ($short ? "Недостача:\n" . implode("\n", $short) : '') . ($dam ? ($short ? "\n" : '') . 'Есть брак / повреждение упаковки.' : '')
           . ($note !== '' ? "\nКладовщик: " . $note : '') . ($photos ? "\nФото: " . implode(' ', $photos) : '');
      $deal = sk_problem($me, 'Приёмка: ' . ($short ? 'недостача' : 'брак') . ' — ' . sk_s($items[0]['name'] ?? '', 120), $txt);
      if ($me['wh_id'] !== '') sk_chat_put($me['wh_id'], 'itc', 'Фил', "Фил: при приёмке проблема — офис получил задачу.\n" . $txt);
    }
    sk_out(['ok' => true, 'done' => $done, 'problem' => $deal !== '', 'short' => $short]);
  }

  case 'sk_chat': {
    $wh = $me['wh'] ? $me['wh_id'] : (string)sk_in('wh'); if ($wh === '') sk_fail('Выберите склад');
    $q = db()->prepare("SELECT id, sender, text, staff, created_at FROM support_messages WHERE kind='warehouse' AND phone=? ORDER BY id DESC LIMIT 80"); $q->execute([$wh]);
    $rows = array_reverse($q->fetchAll());
    if ($me['wh']) db()->prepare("UPDATE support_messages SET read_buyer=1 WHERE kind='warehouse' AND phone=?")->execute([$wh]);
    else db()->prepare("UPDATE support_messages SET read_itc=1 WHERE kind='warehouse' AND phone=? AND sender='buyer'")->execute([$wh]);
    $to = sk_in('lang') === 'zh' ? 'zh' : 'ru';
    // сообщения офиса (по-русски) — для китайского экрана переводим; сообщения склада хранятся с переводом
    $need = []; foreach ($rows as $i => $r) { if ($to === 'zh' && $r['sender'] !== 'buyer') $need[$i] = $r['text']; }
    $tr = $need ? sk_tr_list($need, 'zh') : [];
    foreach ($rows as $i => &$r) {
      $orig = ''; $ru = $r['text'];
      if (preg_match('~^(.*)\n\n原文: (.*)$~su', $r['text'], $mm)) { $ru = $mm[1]; $orig = $mm[2]; }
      $r['show'] = $to === 'zh' ? ($r['sender'] === 'buyer' ? ($orig ?: $ru) : (($tr[$i] ?? '') ?: $ru)) : $ru;
      $r['sub'] = $to === 'zh' ? ($r['sender'] === 'buyer' ? '' : $ru) : ($orig ?: '');
    }
    sk_out(['ok' => true, 'rows' => $rows]);
  }

  case 'sk_chat_send': {
    $wh = $me['wh'] ? $me['wh_id'] : (string)sk_in('wh'); if ($wh === '') sk_fail('Выберите склад');
    $t = sk_s(sk_in('text'), 2000); if ($t === '') sk_fail('Пустое сообщение');
    if ($me['wh']) {
      $hasCn = preg_match('~\p{Han}~u', $t);
      $ru = $hasCn ? (sk_tr_list([$t], 'ru')[0] ?? '') : $t;
      sk_chat_put($wh, 'buyer', '', ($ru !== '' ? $ru : $t) . ($hasCn ? "\n\n原文: " . $t : ''));
    } else sk_chat_put($wh, 'itc', $me['name'], $t);
    sk_out(['ok' => true]);
  }

  case 'sk_cargo_new': {
    sk_cargo_ensure();
    $wh = $me['wh'] ? $me['wh_id'] : (string)sk_in('wh');
    $photos = array_slice(array_values(array_filter(array_map('strval', (array)sk_in('photos', [])), function ($u) { return preg_match('~^https?://|^/uploads/~', $u); })), 0, 8);
    if (!$photos) sk_fail('Сделайте хотя бы одно фото груза');
    $note = sk_s(sk_in('note'), 1500); $boxes = max(0, (int)sk_in('boxes', 0)); $w = max(0, (float)str_replace(',', '.', (string)sk_in('weight', 0)));
    $noteRu = ($note !== '' && preg_match('~\p{Han}~u', $note)) ? (sk_tr_list([$note], 'ru')[0] ?? '') : $note;
    $fil = sk_vision((string)sk_in('img'), $noteRu ?: $note);
    db()->prepare('INSERT INTO itc_sk_cargo (wh_id,photos,note,note_ru,boxes,weight,fil,created_by) VALUES (?,?,?,?,?,?,?,?)')
      ->execute([$wh, json_encode($photos), $note, $noteRu, $boxes, $w, $fil, $me['name']]);
    $cid = (int)db()->lastInsertId();
    // сделка закупщику
    $deal = '';
    try {
      $p = sk_cargo_pipe();
      $st = db()->prepare('SELECT id FROM itc_fl_stage WHERE pipe_id=? ORDER BY sort LIMIT 1'); $st->execute([$p]); $sid = (string)$st->fetchColumn();
      $resp = (string)db()->query("SELECT def_phone FROM itc_fl_pipe WHERE deleted=0 AND kind='purchase' ORDER BY sort LIMIT 1")->fetchColumn();
      $rn = ''; if ($resp !== '') { $q = db()->prepare('SELECT name FROM staff WHERE phone=? LIMIT 1'); $q->execute([$resp]); $rn = (string)$q->fetchColumn(); }
      $deal = 'fd' . date('ymd') . substr(bin2hex(random_bytes(5)), 0, 8);
      $title = 'Груз на складе в Китае №' . $cid . ($fil ? ' — ' . mb_substr(preg_replace('~[.!].*$~su', '', $fil), 0, 80) : '');
      db()->prepare("INSERT INTO itc_fl_deal (id,pipe_id,stage_id,title,note,resp_phone,resp_name,next_at,next_do,sort,stage_at,created_by,created_phone) VALUES (?,?,?,?,?,?,?,?,?,-1,NOW(),?,'')")
        ->execute([$deal, $p, $sid, mb_substr($title, 0, 250), trim(($noteRu ?: $note) . ($boxes ? "
Мест: $boxes" : '') . ($w > 0 ? "
Вес: $w кг" : '')), $resp, $rn, date('Y-m-d'), 'Опознать груз и дать распоряжение складу', $me['name']]);
      $lg = db()->prepare("INSERT INTO itc_fl_log (deal_id,kind,text,who) VALUES (?,?,?,?)");
      $lg->execute([$deal, 'create', 'Склад в Китае принял неопознанный груз. ' . ($noteRu ?: $note) . "
Фото: " . implode(' ', $photos), $me['name']]);
      if ($fil !== '') $lg->execute([$deal, 'comment', 'Фил по фото: ' . $fil, 'Фил']);
      db()->prepare('UPDATE itc_sk_cargo SET deal_id=? WHERE id=?')->execute([$deal, $cid]);
    } catch (Throwable $e) { error_log('sklad cargo deal: ' . $e->getMessage()); }
    sk_out(['ok' => true, 'id' => $cid, 'deal' => $deal, 'fil' => $fil]);
  }

  case 'sk_cargo_list': {
    sk_cargo_ensure();
    $w = "c.status<>'shipped' OR c.shipped_at > NOW() - INTERVAL 7 DAY"; $a = [];
    if ($me['wh']) { $w = "($w) AND c.wh_id=?"; $a[] = $me['wh_id']; }
    $q = db()->prepare("SELECT c.* FROM itc_sk_cargo c WHERE $w ORDER BY (c.status='shipped'), c.id DESC LIMIT 100"); $q->execute($a);
    $rows = $q->fetchAll(); $zh = sk_in('lang') === 'zh';
    foreach ($rows as &$r) {
      $r['photos'] = json_decode((string)$r['photos'], true) ?: []; $r['ship_photos'] = json_decode((string)$r['ship_photos'], true) ?: [];
      $l = db()->prepare("SELECT kind,text,who,at FROM itc_fl_log WHERE deal_id=? AND kind IN ('comment','wh') ORDER BY id LIMIT 60"); $l->execute([$r['deal_id']]);
      $msgs = $l->fetchAll(); $need = [];
      foreach ($msgs as $i => $m) {
        $orig = ''; $ru = $m['text'];
        if (preg_match('~^(.*)\n\n原文: (.*)$~su', $m['text'], $mm)) { $ru = $mm[1]; $orig = $mm[2]; }
        $msgs[$i]['ru'] = $ru; $msgs[$i]['orig'] = $orig;
        if ($zh && $orig === '') $need[$i] = $ru;
      }
      $tr = $need ? sk_tr_list($need, 'zh') : [];
      foreach ($msgs as $i => $m) $msgs[$i]['show'] = $zh ? ($m['orig'] ?: (($tr[$i] ?? '') ?: $m['ru'])) : $m['ru'];
      $r['msgs'] = $msgs;
      if ($zh && (((string)$r['what_ru'] !== '' && !$r['what_zh']) || ((string)$r['instr_ru'] !== '' && !$r['instr_zh']))) {
        $t2 = sk_tr_list(['w' => (string)$r['what_ru'], 'i' => (string)$r['instr_ru']], 'zh');   // распоряжение не перевелось сразу — переводим здесь
        if (!$r['what_zh']) $r['what_zh'] = $t2['w'] ?? ''; if (!$r['instr_zh']) $r['instr_zh'] = $t2['i'] ?? '';
      }
      $r['what'] = $zh ? ($r['what_zh'] ?: $r['what_ru']) : $r['what_ru'];
      $r['instr'] = $zh ? ($r['instr_zh'] ?: $r['instr_ru']) : $r['instr_ru'];
    }
    sk_out(['ok' => true, 'rows' => $rows]);
  }

  case 'sk_cargo_msg': {
    sk_cargo_ensure();
    $q = db()->prepare('SELECT * FROM itc_sk_cargo WHERE id=?'); $q->execute([(int)sk_in('id')]); $c = $q->fetch(); if (!$c) sk_fail('Груз не найден');
    if ($me['wh'] && $c['wh_id'] !== $me['wh_id']) sk_fail('Это груз другого склада', 403);
    $t = sk_s(sk_in('text'), 1500); if ($t === '') sk_fail('Пустое сообщение');
    $hasCn = preg_match('~\p{Han}~u', $t); $ru = $hasCn ? (sk_tr_list([$t], 'ru')[0] ?? '') : $t;
    db()->prepare("INSERT INTO itc_fl_log (deal_id,kind,text,who) VALUES (?,?,?,?)")->execute([$c['deal_id'], $me['wh'] ? 'wh' : 'comment', ($ru ?: $t) . ($hasCn ? "

原文: " . $t : ''), $me['wh'] ? 'Склад: ' . $me['name'] : $me['name']]);
    if ($me['wh']) db()->prepare("UPDATE itc_fl_deal SET next_at=CURDATE(), next_do='Ответить складу в Китае по грузу', updated_at=NOW() WHERE id=?")->execute([$c['deal_id']]);
    sk_out(['ok' => true]);
  }

  case 'sk_cargo_ship': {
    sk_cargo_ensure();
    $q = db()->prepare('SELECT * FROM itc_sk_cargo WHERE id=?'); $q->execute([(int)sk_in('id')]); $c = $q->fetch(); if (!$c) sk_fail('Груз не найден');
    if ($me['wh'] && $c['wh_id'] !== $me['wh_id']) sk_fail('Это груз другого склада', 403);
    $track = sk_s(sk_in('track'), 190);
    $photos = array_slice(array_values(array_filter(array_map('strval', (array)sk_in('photos', [])), function ($u) { return preg_match('~^https?://|^/uploads/~', $u); })), 0, 6);
    db()->prepare("UPDATE itc_sk_cargo SET status='shipped', shipped_at=NOW(), track=?, ship_photos=? WHERE id=?")->execute([$track, json_encode($photos), $c['id']]);
    if ($c['deal_id'] !== '') {
      sk_cargo_stage($c['deal_id'], 'Отправлен');
      db()->prepare("INSERT INTO itc_fl_log (deal_id,kind,text,who) VALUES (?,'wh',?,?)")->execute([$c['deal_id'], 'Склад отправил груз' . ($c['carrier'] ? ' через ' . $c['carrier'] : '') . ($track ? ', трек ' . $track : '') . ($photos ? "
Фото: " . implode(' ', $photos) : ''), 'Склад: ' . $me['name']]);
      db()->prepare("UPDATE itc_fl_deal SET next_at=CURDATE(), next_do='Проверить, что груз дошёл до получателя', updated_at=NOW() WHERE id=?")->execute([$c['deal_id']]);
    }
    sk_out(['ok' => true]);
  }

  case 'sk_tr': {
    $list = array_slice(array_map('strval', (array)sk_in('list', [])), 0, 60);
    $to = sk_in('to') === 'ru' ? 'ru' : 'zh';
    sk_out(['ok' => true, 'list' => array_values(sk_tr_list($list, $to))]);
  }

  default:
    sk_fail('Неизвестное действие', 404);
  }
} catch (Throwable $e) {
  error_log('sklad: ' . $e->getMessage());
  sk_fail('Ошибка сервера: ' . $e->getMessage(), 500);
}
