<?php
// ==== Мой Гуанчжоу · tr.php v3 · 12.11.2026 · известные сети (名创优品 → MINISO и др.) пишем их названием, а не переводом иероглифов ====
// v2 · 16.10.2026 · lang=en: подсказки по-английски (кэш отдельный) ====
// v1 · 23.09.2026 · перевод названий мест через YandexGPT ====
// Ключи берутся из config.php магазина (тот же YandexGPT, что переводит товары).
// Когда вернём DeepSeek — меняется только функция ask_ai() внизу.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

// Пускаем только своё приложение, чтобы чужие не тратили твой лимит Яндекса
$ref = ($_SERVER['HTTP_ORIGIN'] ?? '') . ' ' . ($_SERVER['HTTP_REFERER'] ?? '');
if (strpos($ref, 'metro.itcpartner.store') === false) out(['ok' => false, 'error' => 'forbidden']);

// ---- ключи Яндекса из магазина ----
$cfg = dirname(__DIR__) . '/itcpartner.store/api/config.php';
if (!defined('YC_API_KEY') && is_file($cfg)) { ob_start(); @include_once $cfg; ob_end_clean(); }
$KEY    = defined('YC_API_KEY')   ? YC_API_KEY   : ($GLOBALS['YC_API_KEY']   ?? '');
$FOLDER = defined('YC_FOLDER_ID') ? YC_FOLDER_ID : ($GLOBALS['YC_FOLDER_ID'] ?? '');
if (!$KEY || !$FOLDER) out(['ok' => false, 'error' => 'no_keys', 'hint' => 'Не нашёл YC_API_KEY / YC_FOLDER_ID в ' . $cfg]);


// ---- v3 · известные сети и марки: китайское название → как их знают у нас ----
const BRANDS = [
  '名创优品'=>'MINISO','肯德基'=>'KFC','麦当劳'=>"McDonald's",'星巴克'=>'Starbucks','必胜客'=>'Pizza Hut','汉堡王'=>'Burger King',
  '赛百味'=>'Subway','达美乐'=>"Domino's",'德克士'=>'Dicos','喜茶'=>'HEYTEA','奈雪'=>"Nayuki",'蜜雪冰城'=>'Mixue','瑞幸'=>'Luckin Coffee',
  '库迪'=>'Cotti Coffee','茶百道'=>'ChaPanda','古茗'=>'Goodme','海底捞'=>'Haidilao','太二'=>'Tai Er','西贝'=>'Xibei',
  '优衣库'=>'UNIQLO','无印良品'=>'MUJI','迪卡侬'=>'Decathlon','宜家'=>'IKEA','屈臣氏'=>'Watsons','丝芙兰'=>'Sephora',
  '沃尔玛'=>'Walmart','山姆'=>"Sam's Club",'永旺'=>'AEON','家乐福'=>'Carrefour','盒马'=>'Freshippo (Hema)','麦德龙'=>'Metro',
  '大润发'=>'RT-Mart','华润万家'=>'Vanguard','钱大妈'=>'Qiandama','7-ELEVEn'=>'7-Eleven','7-11'=>'7-Eleven','全家便利'=>'FamilyMart','罗森'=>'Lawson',
  '美宜佳'=>'Meiyijia','Apple Store'=>'Apple','苹果零售店'=>'Apple','苹果授权'=>'Apple','华为'=>'Huawei','小米'=>'Xiaomi','OPPO'=>'OPPO','vivo'=>'vivo','荣耀'=>'Honor',
  '耐克'=>'Nike','阿迪达斯'=>'Adidas','李宁'=>'Li-Ning','安踏'=>'Anta','特步'=>'Xtep','飒拉'=>'ZARA','海澜之家'=>'HLA',
  '泡泡玛特'=>'POP MART','周大福'=>'Chow Tai Fook','周生生'=>'Chow Sang Sang','老凤祥'=>'Lao Feng Xiang',
  '如家'=>'Home Inn','汉庭'=>'Hanting','全季'=>'JI Hotel','亚朵'=>'Atour','希尔顿'=>'Hilton','万豪'=>'Marriott','洲际'=>'InterContinental',
  '中国银行'=>'Bank of China','工商银行'=>'ICBC','建设银行'=>'China Construction Bank','农业银行'=>'Agricultural Bank of China',
  '招商银行'=>'China Merchants Bank','交通银行'=>'Bank of Communications','万达'=>'Wanda','万象城'=>'MixC','太古汇'=>'Taikoo Hui',
];
function brand_of($zh) { foreach (BRANDS as $z => $b) if (mb_stripos($zh, $z) === 0 || mb_stripos($zh, $z) !== false && mb_strlen($z) >= 3) return $b; return ''; }

// ---- вход ----
$in = json_decode(file_get_contents('php://input'), true);
$items = is_array($in['items'] ?? null) ? array_slice($in['items'], 0, 20) : [];
$lang = (($in['lang'] ?? 'ru') === 'en') ? 'en' : 'ru';
if (!$items) out(['ok' => true, 'list' => []]);

// ---- кэш на диске: один раз перевели — дальше мгновенно и бесплатно ----
$cdir = __DIR__ . '/cache';
if (!is_dir($cdir)) @mkdir($cdir, 0755);
$res = []; $todo = []; $order = [];
foreach ($items as $it) {
    $zh = mb_substr(trim((string)($it['zh'] ?? '')), 0, 120);
    $ad = mb_substr(trim((string)($it['addr'] ?? '')), 0, 200);
    $k  = preg_replace('/[^a-z]/', '', (string)($it['k'] ?? 'tip'));
    if ($zh === '') { $order[] = null; continue; }
    $br = brand_of($zh);
    $id = md5($k . '|' . $zh . '|' . $ad . ($lang === 'en' ? '|en' : '') . ($br !== '' ? '|b3' : ''));
    $order[] = $id;
    $f  = "$cdir/$id.json";
    if (is_file($f) && ($c = json_decode(file_get_contents($f), true))) { $res[$id] = $c; continue; }
    $todo[$id] = ['k' => $k, 'zh' => $zh, 'addr' => $ad, 'brand' => $br];
}

if ($todo) {
    $lines = []; $ids = array_keys($todo); $n = 1;
    foreach ($todo as $t) {
        $lines[] = $n++ . '. [' . $t['k'] . '] ' . $t['zh'] . ($t['brand'] !== '' ? ' {сеть: ' . $t['brand'] . '}' : '') . ($t['addr'] !== '' ? ' || адрес: ' . $t['addr'] : '');
    }
    $sys = "Ты помощник русского предпринимателя, который живёт в Фошане (Бэйцзяо) и ездит по Гуанчжоу и Фошаню на метро. "
         . "Тебе дают пронумерованный список китайских названий мест. Тип в квадратных скобках: "
         . "[tip] — место из поиска, [place] — место, куда он едет, [station] — станция метро, [addr] — адрес.\n"
         . "Для КАЖДОЙ строки верни объект с полями:\n"
         . "en — принятое английское название (если официального нет — пиньинь для имени собственного + перевод смысла, например «Jindu International Clothing Mall»);\n"
         . "ru — русское название: смысл по-русски + имя собственное русскими буквами по системе Палладия в кавычках, например «Вещевой рынок «Цзиньду»». Для станций — только название станции русскими буквами по Палладию, со смыслом в скобках, если он понятен: «Гуанчжоу хочэчжань (Главный вокзал)»;\n"
         . "ВАЖНО: если это известная сеть или марка (в фигурных скобках может быть подсказка {сеть: …}; узнавай и другие известные марки сам — например 名创优品 это MINISO, 优衣库 — UNIQLO), "
         . "НЕ переводи иероглифы марки, а пиши марку латиницей, как её знают покупатели. Филиал или торговый центр — в скобках с переводом: "
         . "en — «MINISO (Vanke Li)», ru — «Магазин MINISO (ТЦ «Ванке Ли»)».\n"
         . "note — коротко по-русски, чем это место полезно или что это такое (оптовый рынок часов, жилой комплекс, улица у вокзала). "
         . "Если есть адрес — переведи его кратко (улица, дом, район) и перескажи подсказку про метро, например «490 м пешком от выхода E станции 广州火车站». "
         . "Для [tip] и [station] — одно предложение, для [place] — до трёх предложений с практичным советом. Не выдумывай фактов; если не знаешь место — опиши по смыслу названия.\n"
         . "Китайские названия станций метро в note оставляй иероглифами. "
         . "Ответ — ТОЛЬКО JSON-массив из " . count($lines) . " объектов в том же порядке, без пояснений и без ```.";
    if ($lang === 'en') $sys = "You help a foreign buyer who lives in Foshan (Beijiao) and travels around Guangzhou and Foshan by metro. "
         . "You get a numbered list of Chinese place names. Type in brackets: [tip] — search suggestion, [place] — destination, [station] — metro station, [addr] — address.\n"
         . "IMPORTANT: if it is a well-known chain or brand (a hint {сеть: …} may be given; recognise other famous brands yourself, e.g. 名创优品 is MINISO), do NOT translate the brand characters — write the brand as customers know it, with the branch in brackets, e.g. «MINISO (Vanke Li)».\n"
         . "For EACH line return an object with fields: en — accepted English name (if none, pinyin for proper noun + meaning, e.g. «Jindu International Clothing Mall»); "
         . "ru — leave empty string; note — short English explanation of what the place is / why useful (wholesale watch market, residential complex, street near the station). "
         . "If an address is given, translate it briefly (street, number, district) and restate the metro hint, e.g. «490 m walk from exit E of 广州火车站 station». "
         . "[tip] and [station] — one sentence; [place] — up to three sentences with a practical tip. Do not invent facts. Keep metro station names in Chinese characters in note.\n"
         . "Answer with ONLY a JSON array of " . count($lines) . " objects in the same order, no explanations, no ```.";
    $txt = ask_ai($sys, implode("\n", $lines), $KEY, $FOLDER);
    if ($txt === null) out(['ok' => false, 'error' => 'ai_failed', 'list' => listing($order, $res)]);
    $txt = trim(preg_replace('/^```(json)?|```$/m', '', $txt));
    $a1 = strpos($txt, '['); $a2 = strrpos($txt, ']');
    $arr = ($a1 !== false && $a2 !== false) ? json_decode(substr($txt, $a1, $a2 - $a1 + 1), true) : null;
    if (is_array($arr)) {
        foreach ($ids as $i => $id) {
            if (!isset($arr[$i]) || !is_array($arr[$i])) continue;
            $o = ['en' => (string)($arr[$i]['en'] ?? ''), 'ru' => (string)($arr[$i]['ru'] ?? ''), 'note' => (string)($arr[$i]['note'] ?? '')];
            $br = $todo[$id]['brand'] ?? '';
            if ($br !== '') {   // марка обязательно видна, даже если ИИ ошибся
                if (stripos($o['en'], $br) === false) $o['en'] = $br . ($o['en'] !== '' ? ' — ' . $o['en'] : '');
                if ($o['ru'] !== '' && stripos($o['ru'], $br) === false) $o['ru'] = $br . ' — ' . $o['ru'];
                if ($o['ru'] === '' && $lang === 'ru') $o['ru'] = $br;
            }
            $res[$id] = $o;
            @file_put_contents("$cdir/$id.json", json_encode($o, JSON_UNESCAPED_UNICODE));
        }
    }
}
out(['ok' => true, 'list' => listing($order, $res)]);

function listing($order, $res) { $l = []; foreach ($order as $id) $l[] = ($id && isset($res[$id])) ? $res[$id] : null; return $l; }

// ---- YandexGPT ----
function ask_ai($sys, $user, $key, $folder) {
    $auth = (strpos($key, 't1.') === 0) ? 'Bearer ' . $key : 'Api-Key ' . $key;
    $body = json_encode([
        'modelUri' => 'gpt://' . $folder . '/yandexgpt/latest',
        'completionOptions' => ['stream' => false, 'temperature' => 0.2, 'maxTokens' => '2000'],
        'messages' => [['role' => 'system', 'text' => $sys], ['role' => 'user', 'text' => $user]],
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init('https://llm.api.cloud.yandex.net/foundationModels/v1/completion');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 40,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: ' . $auth, 'x-folder-id: ' . $folder],
        CURLOPT_POSTFIELDS => $body,
    ]);
    $raw = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($raw === false || $http !== 200) { error_log("metro tr yandex http=$http " . substr((string)$raw, 0, 300)); return null; }
    $j = json_decode($raw, true);
    return $j['result']['alternatives'][0]['message']['text'] ?? null;
}
