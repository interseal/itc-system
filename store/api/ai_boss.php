<?php
// ==== ИТК · ИИ-РУКОВОДИТЕЛЬ (проба) · ai_boss.php · №1 (03.11.2026) ====
// Проба «виртуального руководителя филиала»: две модели Яндекса (Alice AI LLM и Qwen 3.6)
// прорабатывают клиента «в глубину» по нашей базе поставщиков, босс сравнивает ответы.
//
//  Вызовы:  GET/POST /api/ai_boss.php?action=...&t=<пропуск сотрудника>   (только босс)
//    aib_leads      клиенты из базы «Клиенты» по сегменту / городу / статусу
//    aib_run        проработка клиента: lead_id, model (alice|qwen)
//    aib_situation  подсказка по ситуации: text (+ lead_id), model
//    aib_vote       «лучше эта модель»: model, kind, ref
//    aib_votes      счёт голосов
//
//  Ключ — тот же, что у переводчика и умного поиска: YC_API_KEY / YC_FOLDER_ID из config.php.
//  Модели выбраны 03.11.2026 (решение Алексея: не YandexGPT, не GigaChat, не DeepSeek).

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ai_lib.php';
@set_time_limit(150);
$action = $_GET['action'] ?? '';
$IN = ($_SERVER['REQUEST_METHOD'] === 'POST') ? (json_decode(file_get_contents('php://input'), true) ?: []) : [];

function aib_out($a) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function aib_fail($m, $c = 400) { http_response_code($c); aib_out(['ok' => false, 'error' => $m]); }
function aib_dec($v) { $d = json_decode((string)$v, true); return is_array($d) ? $d : []; }
function aib_cut($s, $n) { $s = trim(preg_replace('/\s+/u', ' ', (string)$s)); return mb_strlen($s) > $n ? mb_substr($s, 0, $n) . '…' : $s; }

function aib_me() {
  $s = authUser('itc');
  $r = $s['staff_role'] ?? '';
  if (!($r === 'boss' || $r === '')) aib_fail('Проба ИИ-руководителя — только для босса', 403);
  return trim((string)($s['name'] ?? '')) ?: 'босс';
}

const AIB_MODELS = [
  'alice' => ['aliceai-llm', 'Alice AI LLM'],
  'qwen'  => ['qwen3.6-35b-a3b', 'Qwen 3.6'],
];

/* Один запрос к модели Яндекса. Возвращает [текст|null, мс, токены, ошибка]. */
function aib_ask($model, $sys, $user, $maxTok = 3500) {
  $YK = defined('YC_API_KEY') ? YC_API_KEY : ($GLOBALS['YC_API_KEY'] ?? '');
  $YF = defined('YC_FOLDER_ID') ? YC_FOLDER_ID : ($GLOBALS['YC_FOLDER_ID'] ?? '');
  if (!$YK || !$YF) return [null, 0, 0, 'нет ключа Яндекса в config.php'];
  $m = AIB_MODELS[$model] ?? null; if (!$m) return [null, 0, 0, 'неизвестная модель'];
  $u = 'https://llm.api.cloud.yandex.net/v1/chat/completions';
  $req = json_encode(['model' => "gpt://$YF/{$m[0]}", 'temperature' => 0.3, 'max_tokens' => $maxTok, 'stream' => false,
    'messages' => [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]]], JSON_UNESCAPED_UNICODE);
  $t0 = microtime(true); $err = '';
  foreach (['Bearer ', 'Api-Key '] as $auth) {
    [$http, $r] = itcai_post($u, $req, ['Content-Type: application/json', 'Authorization: ' . $auth . $YK,
      'OpenAI-Project: ' . $YF, 'x-folder-id: ' . $YF], 120);
    if ($http === 200) {
      $j = json_decode($r, true);
      $txt = (string)($j['choices'][0]['message']['content'] ?? '');
      $txt = trim(preg_replace('~<think>.*?</think>~s', '', $txt));
      $tok = (int)($j['usage']['total_tokens'] ?? 0);
      if ($txt !== '') return [$txt, (int)((microtime(true) - $t0) * 1000), $tok, ''];
      $err = 'пустой ответ';
    } else {
      $e = json_decode($r, true);
      $err = 'ошибка ' . ($http ?: 'связи') . (isset($e['error']['message']) ? ': ' . aib_cut($e['error']['message'], 160) : '');
      if (!in_array($http, [401, 403], true)) break;   // другой способ ключа пробуем только при отказе в доступе
    }
  }
  error_log("ai_boss $model $err");
  return [null, (int)((microtime(true) - $t0) * 1000), 0, $err];
}

/* База поставщиков одним списком для модели (кэш, пока база не меняется). */
function aib_sup_index() {
  $db = db();
  $ver = $db->query("SELECT CONCAT(COUNT(*),'|',IFNULL(MAX(updated_at),'')) v FROM itc_sup WHERE deleted=0")->fetch()['v'];
  try { $ver .= '|' . $db->query("SELECT IFNULL(MAX(updated_at),'') v FROM itc_sup_web")->fetch()['v']; } catch (Throwable $e) {}
  $key = md5('aibix1|' . $ver);
  if ($c = itcai_cache_get('aib', $key, 86400 * 30)) return $c;
  $segs = [];
  try { foreach ($db->query('SELECT code,name FROM itc_sup_seg')->fetchAll() as $g) $segs[$g['code']] = $g['name']; } catch (Throwable $e) {}
  $web = [];
  try {
    foreach ($db->query('SELECT sup_id,slug,status,data FROM itc_sup_web')->fetchAll() as $w) {
      $d = aib_dec($w['data']); $gn = [];
      foreach (($d['groups'] ?? []) as $g) if (!empty($g['name'])) $gn[] = $g['name'] . ' (' . count($g['items'] ?? []) . ')';
      $web[$w['sup_id']] = ['url' => $w['status'] === 'live' && $w['slug'] ? 'https://itcpartner.ru/' . $w['slug'] : '', 'g' => $gn];
    }
  } catch (Throwable $e) {}
  $rows = []; $lines = [];
  foreach ($db->query('SELECT id,name,seg,seg2,city,kind,what,products FROM itc_sup WHERE deleted=0')->fetchAll() as $r) {
    if (preg_match('~трейдер|посредник|торгов~iu', (string)$r['kind'])) continue;   // в работу клиентам — только фабрики
    $w = $web[$r['id']] ?? ['url' => '', 'g' => []];
    $prod = array_values(array_filter(array_map('strval', aib_dec($r['products']))));
    $sg = trim(($segs[$r['seg']] ?? $r['seg']) . ($r['seg2'] ? ', ' . ($segs[$r['seg2']] ?? $r['seg2']) : ''));
    $lines[] = $r['id'] . ' | ' . $r['name'] . ' | ' . $r['city'] . ' | ' . $sg
      . ' | товары: ' . aib_cut(implode(', ', array_slice(array_merge($w['g'], $prod), 0, 16)), 320)
      . ($r['what'] ? ' | ' . aib_cut($r['what'], 220) : '');
    $rows[$r['id']] = ['name' => $r['name'], 'url' => $w['url'], 'city' => $r['city']];
  }
  $out = ['text' => implode("\n", $lines), 'rows' => $rows, 'n' => count($rows)];
  itcai_cache_put('aib', $key, $out);
  return $out;
}

function aib_lead($id) {
  $q = db()->prepare('SELECT * FROM itc_lead WHERE id=? AND deleted=0'); $q->execute([$id]);
  $r = $q->fetch(); if (!$r) aib_fail('Клиент не найден');
  return $r;
}
function aib_lead_text($r) {
  $segs = [];
  try { foreach (db()->query('SELECT code,name FROM itc_lead_seg')->fetchAll() as $g) $segs[$g['code']] = $g['name']; } catch (Throwable $e) {}
  $p = ['Компания: ' . $r['name'], 'Сегмент: ' . ($segs[$r['seg']] ?? $r['seg'])];
  foreach (['type' => 'Тип', 'city' => 'Город', 'locality' => 'Место', 'stars' => 'Звёзды', 'rooms' => 'Номеров', 'chain' => 'Сеть', 'site' => 'Сайт'] as $k => $t)
    if (trim((string)$r[$k]) !== '') $p[] = "$t: " . $r[$k];
  foreach (['descr' => 'Описание', 'room_types' => 'Номера', 'services' => 'Услуги'] as $k => $t) {
    $v = $r[$k]; $d = json_decode((string)$v, true); if (is_array($d)) $v = implode(', ', array_map(function ($x) { return is_array($x) ? implode(' ', $x) : (string)$x; }, $d));
    if (trim((string)$v) !== '') $p[] = "$t: " . aib_cut($v, 600);
  }
  $has = [];
  foreach (['phone' => 'телефон', 'email' => 'почта', 'site' => 'сайт', 'legal' => 'юрлицо'] as $k => $t) $has[] = $t . ': ' . (trim((string)$r[$k]) !== '' ? 'есть' : 'НЕТ');
  $p[] = 'В карточке — ' . implode(', ', $has);
  $p[] = 'Статус работы: ' . $r['status'] . (trim((string)$r['note']) !== '' ? '; заметки менеджера: ' . aib_cut($r['note'], 500) : '');
  return implode("\n", $p);
}

const AIB_ITC = "ИТК (ООО «Интернациональная Торговая Компания», сайт itcpartner.ru) — импорт напрямую с фабрик Китая: "
  . "подбор фабрики, проверка товара, официальный ввоз и растаможка, сертификаты и закрывающие документы, оплата в рублях, "
  . "доставка до объекта клиента. Свой склад в Фошане (Китай). Офисы в Москве и Симферополе.";
const AIB_RULES = "Правила: поставщиков бери ТОЛЬКО из списка базы ниже и указывай их id ровно как в списке. "
  . "Не выдумывай цены, сроки, скидки, сертификаты и факты о клиенте. Не предлагай WhatsApp. Не упоминай FOB и условия поставки. "
  . "Пиши по-русски, простыми словами, без английских слов (кроме названий марок). Обращения — без привязки к полу.";

function aib_sup_check($arr, $IX) {
  $out = [];
  foreach ((array)$arr as $x) {
    $id = trim((string)($x['sup'] ?? '')); if (!isset($IX['rows'][$id])) continue;
    $s = $IX['rows'][$id];
    $out[] = ['sup' => $id, 'name' => $s['name'], 'url' => $s['url'], 'what' => aib_cut($x['what'] ?? '', 300), 'why' => aib_cut($x['why'] ?? '', 300)];
  }
  return $out;
}

function aib_votes_file() { return itcai_dir() . '/aib_votes.json'; }

try {
  $me = aib_me();
  switch ($action) {

  case 'aib_leads': {
    $seg = (string)($IN['seg'] ?? ($_GET['seg'] ?? '')); $city = (string)($IN['city'] ?? ($_GET['city'] ?? ''));
    $st = (string)($IN['st'] ?? ($_GET['st'] ?? 'new')); $n = max(1, min(30, (int)($IN['n'] ?? ($_GET['n'] ?? 10))));
    $w = ['deleted=0']; $a = [];
    if ($seg !== '') { $w[] = 'seg=?'; $a[] = $seg; }
    if ($city !== '') { $w[] = 'city=?'; $a[] = $city; }
    if ($st !== '' && $st !== 'all') { $w[] = 'status=?'; $a[] = $st; }
    $q = db()->prepare('SELECT id,name,type,city,locality,stars,rooms,phone,email,site,status FROM itc_lead WHERE ' . implode(' AND ', $w)
      . " ORDER BY (stars='') , stars DESC, name LIMIT " . $n);
    $q->execute($a);
    $segs = db()->query('SELECT code,name FROM itc_lead_seg ORDER BY sort,name')->fetchAll();
    $cities = db()->query("SELECT city, COUNT(*) c FROM itc_lead WHERE deleted=0 AND city<>'' GROUP BY city ORDER BY city")->fetchAll();
    $IX = aib_sup_index();
    aib_out(['ok' => true, 'rows' => $q->fetchAll(), 'segs' => $segs, 'cities' => $cities, 'sup_n' => $IX['n'],
             'models' => array_map(function ($m) { return $m[1]; }, AIB_MODELS)]);
  }

  case 'aib_run': {
    $model = (string)($IN['model'] ?? ''); if (!isset(AIB_MODELS[$model])) aib_fail('Неизвестная модель');
    $L = aib_lead((string)($IN['lead_id'] ?? ''));
    $IX = aib_sup_index();
    $ck = md5('run1|' . $model . '|' . $L['id'] . '|' . $L['updated_at'] . '|' . md5($IX['text']));
    if (empty($IN['fresh']) && ($c = itcai_cache_get('aib', $ck, 86400 * 3))) aib_out(['ok' => true, 'cached' => true] + $c);
    $sys = "Ты — руководитель филиала компании ИТК и опытный руководитель отдела продаж. " . AIB_ITC . "\n"
      . "Твоя задача — проработать одного клиента В ГЛУБИНУ: понять, что ему может быть нужно на всех участках его работы, "
      . "подобрать из нашей базы поставщиков основное предложение, а затем смежные и неочевидные направления "
      . "(для гостиницы это не только текстиль и тапочки, но и мебель, сантехника, освещение, посуда для ресторана, "
      . "спортивные и детские площадки, покрытия, бассейн, оборудование кухни, отделочные материалы), "
      . "заранее решить, что предложить, если клиент откажется, и расписать менеджеру пошаговый план работы.\n"
      . AIB_RULES . "\n"
      . "Верни ТОЛЬКО JSON без пояснений вокруг:\n"
      . '{"summary":"1–2 предложения: кто клиент и где у него потребности",'
      . '"offer":[{"sup":"id","what":"что именно предложить","why":"почему именно этому клиенту"}],'
      . '"more":[{"sup":"id","what":"смежное или неочевидное предложение","why":"почему"}],'
      . '"if_refuse":"что предложить и как сказать, если клиент откажется от основного предложения",'
      . '"email":{"subject":"тема письма","body":"первое письмо клиенту от менеджера ИТК, 120–200 слов, без цен; в конце подпись: [Имя менеджера], ИТК, itcpartner.ru"},'
      . '"call":{"opening":"первая фраза звонка","questions":["вопросы клиенту, 3–5"],"objections":[{"q":"возражение","a":"ответ"}]},'
      . '"chain":[{"day":0,"do":"что сделать менеджеру"}],'
      . '"missing":["каких данных не хватает в карточке клиента и где их искать"]}' . "\n"
      . "offer — 3–5 поставщиков, more — 3–6 других поставщиков (не повторяй offer), chain — 4–7 шагов на 2–3 недели "
      . "(звонок, письмо, повторный звонок через несколько дней, предложение, встреча или образцы).";
    $user = "КЛИЕНТ:\n" . aib_lead_text($L) . "\n\nБАЗА ПОСТАВЩИКОВ (id | название | город | разделы | товары | что делает):\n" . $IX['text'];
    [$txt, $ms, $tok, $err] = aib_ask($model, $sys, $user, 3500);
    if ($txt === null) aib_fail(AIB_MODELS[$model][1] . ' не ответила: ' . $err);
    $j = itcai_json($txt);
    if (!$j) aib_out(['ok' => true, 'raw' => aib_cut($txt, 6000), 'ms' => $ms, 'tok' => $tok, 'model' => AIB_MODELS[$model][1]]);
    $res = ['model' => AIB_MODELS[$model][1], 'ms' => $ms, 'tok' => $tok,
      'summary' => aib_cut($j['summary'] ?? '', 600), 'offer' => aib_sup_check($j['offer'] ?? [], $IX), 'more' => aib_sup_check($j['more'] ?? [], $IX),
      'if_refuse' => aib_cut($j['if_refuse'] ?? '', 1200),
      'email' => ['subject' => aib_cut($j['email']['subject'] ?? '', 200), 'body' => trim((string)($j['email']['body'] ?? ''))],
      'call' => ['opening' => aib_cut($j['call']['opening'] ?? '', 500), 'questions' => array_slice(array_map('strval', (array)($j['call']['questions'] ?? [])), 0, 8),
                 'objections' => array_slice(array_values(array_filter((array)($j['call']['objections'] ?? []), 'is_array')), 0, 6)],
      'chain' => array_slice(array_values(array_filter((array)($j['chain'] ?? []), 'is_array')), 0, 10),
      'missing' => array_slice(array_map('strval', (array)($j['missing'] ?? [])), 0, 8)];
    itcai_cache_put('aib', $ck, $res);
    aib_out(['ok' => true] + $res);
  }

  case 'aib_situation': {
    $model = (string)($IN['model'] ?? ''); if (!isset(AIB_MODELS[$model])) aib_fail('Неизвестная модель');
    $text = aib_cut($IN['text'] ?? '', 1500); if (mb_strlen($text) < 5) aib_fail('Опишите ситуацию');
    $IX = aib_sup_index();
    $lt = ''; if (!empty($IN['lead_id'])) $lt = "\n\nКАРТОЧКА КЛИЕНТА:\n" . aib_lead_text(aib_lead((string)$IN['lead_id']));
    $sys = "Ты — руководитель филиала компании ИТК. " . AIB_ITC . "\n"
      . "Менеджер описывает ситуацию с клиентом (что заказал, от чего отказался, что спросил). Подскажи, как проработать клиента глубже: "
      . "что ещё ему предложить из нашей базы поставщиков (сопутствующие и смежные товары — например, к сцеплению для Mercedes Sprinter "
      . "предложить другие запчасти и кузовные детали; гостинице, отказавшейся от тапочек, — полотенца, мебель или спортивную площадку), "
      . "как это сказать и какой следующий шаг сделать.\n" . AIB_RULES . "\n"
      . "Верни ТОЛЬКО JSON: {\"read\":\"как ты понял ситуацию, 1 предложение\","
      . "\"recommend\":[{\"sup\":\"id\",\"what\":\"что предложить\",\"why\":\"почему\"}],"
      . "\"say\":\"что сказать клиенту, 2–4 предложения\",\"next\":[{\"day\":0,\"do\":\"следующий шаг\"}],"
      . "\"gap\":\"чего нет в нашей базе и какую фабрику стоит найти (или пусто)\"}. recommend — 3–7 поставщиков.";
    $user = "СИТУАЦИЯ: " . $text . $lt . "\n\nБАЗА ПОСТАВЩИКОВ (id | название | город | разделы | товары | что делает):\n" . $IX['text'];
    [$txt, $ms, $tok, $err] = aib_ask($model, $sys, $user, 2000);
    if ($txt === null) aib_fail(AIB_MODELS[$model][1] . ' не ответила: ' . $err);
    $j = itcai_json($txt);
    if (!$j) aib_out(['ok' => true, 'raw' => aib_cut($txt, 5000), 'ms' => $ms, 'tok' => $tok, 'model' => AIB_MODELS[$model][1]]);
    aib_out(['ok' => true, 'model' => AIB_MODELS[$model][1], 'ms' => $ms, 'tok' => $tok, 'read' => aib_cut($j['read'] ?? '', 500),
      'recommend' => aib_sup_check($j['recommend'] ?? [], $IX), 'say' => aib_cut($j['say'] ?? '', 1200),
      'next' => array_slice(array_values(array_filter((array)($j['next'] ?? []), 'is_array')), 0, 6), 'gap' => aib_cut($j['gap'] ?? '', 500)]);
  }

  case 'aib_vote': {
    $model = (string)($IN['model'] ?? ''); if (!isset(AIB_MODELS[$model])) aib_fail('Неизвестная модель');
    $f = aib_votes_file(); $fp = fopen($f, 'c+'); flock($fp, LOCK_EX);
    $v = json_decode(stream_get_contents($fp), true) ?: ['n' => [], 'log' => []];
    $ref = aib_cut($IN['ref'] ?? '', 80); $kind = aib_cut($IN['kind'] ?? '', 20);
    $v['log'] = array_values(array_filter($v['log'], function ($x) use ($ref, $kind) { return !($x['ref'] === $ref && $x['kind'] === $kind); }));
    $v['log'][] = ['model' => $model, 'ref' => $ref, 'kind' => $kind, 'at' => date('Y-m-d H:i')];
    $v['n'] = []; foreach ($v['log'] as $x) $v['n'][$x['model']] = ($v['n'][$x['model']] ?? 0) + 1;
    ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode($v, JSON_UNESCAPED_UNICODE)); flock($fp, LOCK_UN); fclose($fp);
    aib_out(['ok' => true, 'n' => $v['n']]);
  }

  case 'aib_votes': {
    $v = json_decode((string)@file_get_contents(aib_votes_file()), true) ?: ['n' => []];
    aib_out(['ok' => true, 'n' => $v['n']]);
  }

  default:
    aib_fail('Неизвестное действие', 404);
  }
} catch (Throwable $e) {
  error_log('ai_boss: ' . $e->getMessage());
  aib_fail('Ошибка сервера: ' . $e->getMessage(), 500);
}
