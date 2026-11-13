<?php
/**
 * =====================================================================
 *  ИТК · Модуль 1688 (onebound)  ·  api/ob_1688.php
 * =====================================================================
 *
 *  Подключение в api.php (один раз, рядом с другими require):
 *      require_once __DIR__ . '/ob_1688.php';
 *
 *  Ключи добавить в api/config.php (config.php правит только Алексей):
 *      $OB_KEY     = 't_79184803066';
 *      $OB_SECRET  = '3066af62';
 *      $OB_ENABLED = true;
 *      $OB_LANG    = 'cn';   // перевод у onebound ПЛАТНЫЙ (класс D) — держим cn, переводим DeepSeek
 *
 *  Схема данных: ob_1688_schema.sql (выполнить до подключения модуля)
 * =====================================================================
 */

if (!defined('OB_1688_LOADED')) {
define('OB_1688_LOADED', 1);

// ─────────────────────────────────────────────────────────────────────
//  КОНСТАНТЫ
// ─────────────────────────────────────────────────────────────────────
define('OB_GW',        'https://api-gw.onebound.cn');
define('OB_GW_BACKUP', 'https://api-1.onebound.cn');   // запасной шлюз onebound
// OB_KD_FACTOR удалён: множитель на собранные габариты врёт в обе стороны.
// Объём фрахта берётся из ds_kd_rules() (замеры ИТК) или 包装尺寸.

// Лимиты фото. На живом товаре 709411502301: item_imgs=73, desc_img=17.
// 90 фото × 2.44 МБ = 220 МБ на ОДИН товар. При 5000 товаров/сутки это ~1 ТБ.
// Качаем только то, что реально нужно карточке.
define('OB_MAX_MAIN_IMGS', 8);    // галерея покупателю
define('OB_MAX_DESC_IMGS', 6);    // полотна описания (через 象寄, дорого)

// Тарифы onebound, ¥ за вызов (прайс от 01.03.2026)
$GLOBALS['OB_PRICE'] = [
    'item_get'          => 0.022,  // класс A
    'item_search'       => 0.022,  // класс A
    'item_password'     => 0.022,  // класс A
    'item_cat_get'      => 0.022,  // класс A
    'item_search_factory'=> 0.022, // класс A
    'item_search_shop'  => 0.05,   // класс B
    'seller_info'       => 0.05,   // класс B
    'item_review'       => 0.05,   // класс B
    'item_list_weight'  => 0.05,   // класс B
];

// Коды, за которые onebound списывает деньги
$GLOBALS['OB_BILLABLE'] = ['0000', '2000'];

// Расшифровка кодов ошибок
$GLOBALS['OB_ERRORS'] = [
    '0000' => 'Успех',
    '2000' => 'Товар не найден',
    '4000' => 'Внутренняя ошибка сервера onebound',
    '4001' => 'Сетевая ошибка',
    '4002' => 'Ошибка целевого сервера (1688)',
    '4003' => 'Ошибка входных параметров',
    '4004' => 'Аккаунт не найден',
    '4005' => 'Ошибка авторизации — ключ/секрет или интерфейс не подключён',
    '4006' => 'API отключён',
    '4007' => 'Аккаунт заблокирован',
    '4008' => 'Превышен лимит одновременных запросов',
    '4009' => 'API на обслуживании',
    '4010' => 'API не существует',
    '4012' => 'Сначала добавьте API',
    '4013' => 'Превышено количество вызовов',
    '4014' => 'Отсутствует параметр',
    '4016' => 'Недостаточно средств',
    '4017' => 'Таймаут запроса',
    '5000' => 'Неизвестная ошибка',
];


// ─────────────────────────────────────────────────────────────────────
//  ПОДКЛЮЧЕНИЕ К БД
//  api.php использует своё соединение — подхватываем его.
//  Если у вас переменная называется иначе — допишите её в список.
// ─────────────────────────────────────────────────────────────────────
function ob_pdo() {
    // api.php использует PDO через глобальную db(). Никакого mysqli.
    return function_exists('db') ? db() : null;
}

/**
 *  Последняя ошибка БД. Раньше исключения глотались молча и запрос просто
 *  возвращал пустоту — так пряталась ошибка сортировки (#1267 Illegal mix
 *  of collations), из-за которой очередь показывала «пусто» вместо карточек.
 *  Теперь ошибка сохраняется и её видно через ob_db_error().
 */
function ob_db_error($set = null) {
    static $last = null;
    if ($set !== null) $last = $set;
    return $last;
}

/** SELECT одной строки. */
function ob_row($sql, $args = []) {
    try { $q = ob_pdo()->prepare($sql); $q->execute($args); $r = $q->fetch(); return $r ?: null; }
    catch (Exception $e) {
        ob_db_error($e->getMessage());
        error_log('ob_row: ' . $e->getMessage() . ' | SQL: ' . mb_substr($sql, 0, 200));
        return null;
    }
}

/** SELECT многих строк. */
function ob_all($sql, $args = []) {
    try { $q = ob_pdo()->prepare($sql); $q->execute($args); return $q->fetchAll(); }
    catch (Exception $e) {
        ob_db_error($e->getMessage());
        error_log('ob_all: ' . $e->getMessage() . ' | SQL: ' . mb_substr($sql, 0, 200));
        return [];
    }
}

/** INSERT/UPDATE/DELETE. Возвращает true/false. */
function ob_exec($sql, $args = []) {
    // 1688 иногда отдаёт массив там, где ждём строку (props[].value).
    // Без этого PDO падает с «Array to string conversion».
    foreach ($args as $i => $a) {
        if (is_array($a))  $args[$i] = implode(', ', array_map('strval', $a));
        elseif (is_bool($a)) $args[$i] = $a ? 1 : 0;
        elseif (is_object($a)) $args[$i] = json_encode($a, JSON_UNESCAPED_UNICODE);
    }
    try { $q = ob_pdo()->prepare($sql); return $q->execute($args); }
    catch (Exception $e) {
        ob_db_error($e->getMessage());
        error_log('ob_exec: ' . $e->getMessage() . ' | SQL: ' . mb_substr($sql, 0, 200));
        return false;
    }
}


// ─────────────────────────────────────────────────────────────────────
//  1. РАЗБОР ССЫЛКИ 1688 → num_iid
//     Понимает:
//       detail.1688.com/offer/610947572360.html
//       m.1688.com/offer/610947572360.html
//       //detail.1688.com/offer/610947572360.html?spm=xxx  (трекинг режем)
//       просто цифры
//       qr.1688.com/share.html?secret=XXXX  → требует item_password
// ─────────────────────────────────────────────────────────────────────
function ob_normalize_url($input) {
    $input = trim((string)$input);
    if ($input === '') {
        return ['ok' => false, 'error' => 'Пустая ссылка'];
    }

    // Голый ID: 9–16 цифр (иногда вставляют просто номер товара)
    if (preg_match('/^\d{9,16}$/', $input)) {
        return ['ok' => true, 'num_iid' => $input,
                'url' => "https://detail.1688.com/offer/{$input}.html"];
    }

    // Короткая ссылка-приглашение — нужен отдельный вызов item_password.
    // Вытаскиваем саму ссылку из текста, если вставлен share-блоб с иероглифами.
    if (stripos($input, 'qr.1688.com') !== false) {
        $word = $input;
        if (preg_match('~https?://qr\.1688\.com/\S+~i', $input, $qm)) $word = $qm[0];
        return ['ok' => false, 'need_password' => true, 'word' => $word,
                'error' => 'Короткая ссылка qr.1688.com — нужен вызов item_password'];
    }

    // Обычная ссылка (detail/m/www .1688.com): вытаскиваем /offer/ЦИФРЫ из любого места текста
    if (preg_match('~1688\.com/(?:offer|o)/(\d{6,16})~i', $input, $m)) {
        return ['ok' => true, 'num_iid' => $m[1],
                'url' => "https://detail.1688.com/offer/{$m[1]}.html"];
    }

    // Запасной вариант: параметр offerId= / offer_id= / id=
    if (preg_match('~[?&](?:offerId|offer_id|id)=(\d{6,16})~i', $input, $m)) {
        return ['ok' => true, 'num_iid' => $m[1],
                'url' => "https://detail.1688.com/offer/{$m[1]}.html"];
    }

    // Последний шанс: где-то в тексте есть «1688» и одно длинное число — берём его.
    // Ловит грязные вставки из приложения, где номер есть, но ссылка не стандартная.
    if (stripos($input, '1688') !== false && preg_match('/(\d{9,16})/', $input, $m)) {
        return ['ok' => true, 'num_iid' => $m[1],
                'url' => "https://detail.1688.com/offer/{$m[1]}.html"];
    }

    return ['ok' => false, 'error' => 'Не удалось распознать ссылку 1688. Скопируйте полную ссылку вида detail.1688.com/offer/…  или просто номер товара (длинное число из ссылки).'];
}


// ─────────────────────────────────────────────────────────────────────
//  2. ВЫЗОВ API
// ─────────────────────────────────────────────────────────────────────
function ob_call($api_name, $params = [], $opts = []) {
    global $OB_KEY, $OB_SECRET, $OB_ENABLED, $OB_LANG;

    $t0 = microtime(true);

    if (empty($OB_ENABLED)) {
        return ['ok' => false, 'error_code' => 'OFF', 'reason' => 'OB_ENABLED = false в config.php'];
    }
    if (empty($OB_KEY) || empty($OB_SECRET)) {
        return ['ok' => false, 'error_code' => 'NOKEY', 'reason' => 'OB_KEY/OB_SECRET не заданы в config.php'];
    }

    $qs = [
        'key'         => $OB_KEY,
        'secret'      => $OB_SECRET,
        'lang'        => $opts['lang']  ?? ($OB_LANG ?? 'cn'),
        'cache'       => $opts['cache'] ?? 'yes',
        'result_type' => 'json',
    ] + $params;

    $url = OB_GW . "/1688/{$api_name}/?" . http_build_query($qs);

    $body = ob_http_get($url);
    // Шлюз не ответил — пробуем запасной
    if ($body === null && !empty($opts['try_backup'])) {
        $url2 = OB_GW_BACKUP . "/1688/{$api_name}/?" . http_build_query($qs);
        $body = ob_http_get($url2);
    }

    $ms = (int)round((microtime(true) - $t0) * 1000);

    if ($body === null) {
        ob_log($api_name, $params['num_iid'] ?? null, 'NET', 'Нет ответа от шлюза', false, 0, false, $ms);
        return ['ok' => false, 'error_code' => 'NET', 'reason' => 'Шлюз onebound не ответил'];
    }

    $j = json_decode($body, true);
    if (!is_array($j)) {
        ob_log($api_name, $params['num_iid'] ?? null, 'BADJSON', 'Ответ не JSON', false, 0, false, $ms);
        return ['ok' => false, 'error_code' => 'BADJSON', 'reason' => 'Ответ не JSON', 'raw' => substr($body, 0, 500)];
    }

    $code     = (string)($j['error_code'] ?? '');
    $reason   = (string)($j['reason'] ?? $j['error'] ?? '');
    $cached   = !empty($j['cache']);
    $billable = in_array($code, $GLOBALS['OB_BILLABLE'], true) && !$cached;
    $cost     = $billable ? ($GLOBALS['OB_PRICE'][$api_name] ?? 0.022) : 0;

    ob_log($api_name, $params['num_iid'] ?? null, $code, $reason, $billable, $cost, $cached, $ms);

    return [
        'ok'         => ($code === '0000'),
        'error_code' => $code,
        'reason'     => $reason ?: (($GLOBALS['OB_ERRORS'][$code] ?? '')),
        'reason_ru'  => $GLOBALS['OB_ERRORS'][$code] ?? 'Неизвестный код',
        'data'       => $j,
        'raw'        => $body,
        'cost_cny'   => $cost,
        'ms'         => $ms,
    ];
}

function ob_http_get($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 40,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING       => 'gzip',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'ITC/1.0',
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    return ($err || $body === false) ? null : $body;
}

function ob_log($api, $iid, $code, $reason, $billable, $cost, $cached, $ms) {
    ob_exec(
        "INSERT INTO ob_log (api_name,num_iid,error_code,reason,billable,cost_cny,from_cache,ms,created_at)
         VALUES (?,?,?,?,?,?,?,?,NOW())",
        [$api, $iid, $code, mb_substr((string)$reason, 0, 250),
         $billable ? 1 : 0, $cost, $cached ? 1 : 0, $ms]
    );
}


// ─────────────────────────────────────────────────────────────────────
//  3. ПОЛУЧЕНИЕ ТОВАРА (с кэшем в ob_raw — вызов платный, кэш бесплатный)
// ─────────────────────────────────────────────────────────────────────
function ob_fetch_item($num_iid, $opts = []) {
    $num_iid = preg_replace('/\D/', '', (string)$num_iid);
    if ($num_iid === '') return ['ok' => false, 'reason' => 'Пустой num_iid'];

    // Кэш: вызов платный, перечитать из ob_raw бесплатно
    if (empty($opts['force'])) {
        $ttl = (int)($opts['ttl_hours'] ?? 168);   // неделя по умолчанию
        $row = ob_row(
            "SELECT payload FROM ob_raw
              WHERE num_iid=? AND api_name='item_get'
                AND error_code='0000'
                AND fetched_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
              LIMIT 1",
            [$num_iid, $ttl]
        );
        if ($row) {
            $j = json_decode($row['payload'], true);
            if (is_array($j)) {
                ob_log('item_get', $num_iid, '0000', 'из ob_raw', false, 0, true, 0);
                return ['ok' => true, 'data' => $j, 'from_cache' => true, 'cost_cny' => 0];
            }
        }
    }

    $params = ['num_iid' => $num_iid];
    if (!empty($opts['sales_data'])) $params['sales_data'] = 1;  // сделки за 30 дней
    if (!empty($opts['agent']))      $params['agent'] = 1;       // цены дропшипа

    $res = ob_call('item_get', $params, ['cache' => $opts['cache'] ?? 'yes']);

    // Пишем сырой ответ даже с ошибкой — пригодится для разбора
    if (!empty($res['raw'])) {
        ob_exec(
            "INSERT INTO ob_raw (num_iid,api_name,payload,error_code,fetched_at)
             VALUES (?,'item_get',?,?,NOW())
             ON DUPLICATE KEY UPDATE payload=VALUES(payload),
                                     error_code=VALUES(error_code),
                                     fetched_at=NOW()",
            [$num_iid, $res['raw'], $res['error_code'] ?? '']
        );
    }
    return $res;
}


// ─────────────────────────────────────────────────────────────────────
//  4. ЛЕСТНИЦА ЦЕН
//     priceRange: [[2,"3.5"],[100,"3.0"],[500,"2.5"]]
//     tier 0 = минимальное количество = САМАЯ ДОРОГАЯ цена → на витрину
//     последняя = самая дешёвая → в калькулятор КП
// ─────────────────────────────────────────────────────────────────────
function ob_price_ladder($item) {
    $pr = $item['priceRange'] ?? null;
    if (!is_array($pr) || !$pr) return [];

    $rows = [];
    foreach ($pr as $row) {
        if (!is_array($row) || count($row) < 2) continue;
        $rows[] = ['min_qty' => (int)$row[0], 'price_cny' => (float)$row[1]];
    }
    if (!$rows) return [];

    // ВАЖНО: на 1688 priceRange — это ЧАЩЕ ВСЕГО ДИАПАЗОН ЦЕН, а не ступени!
    // Живой пример (offer 709411502301):
    //     priceRange = [["1","6.80"],["1","68.80"]],  min_num = 1
    // Обе записи с количеством 1 — лестницей это быть не может,
    // не бывает двух цен на одно количество. Это «от 6.80 до 68.80».
    //
    // Настоящая ступенчатая цена (阶梯价) имеет РАЗНЫЕ min_qty:
    //     [[2,"3.5"],[100,"3.0"],[500,"2.5"]]
    //
    // Отличаем по числу уникальных min_qty.
    $qtys = array_unique(array_column($rows, 'min_qty'));
    if (count($qtys) < 2) {
        return [];   // не лестница — диапазон. Цены берём из SKU.
    }

    usort($rows, fn($a, $b) => $a['min_qty'] <=> $b['min_qty']);
    return $rows;
}

/** Цена на витрину: ступень «1 шт» = первая (самая дорогая). */
function ob_price_retail($item) {
    $t = ob_price_ladder($item);
    return $t ? $t[0]['price_cny'] : null;
}

/** Цена для закупки/КП: нижняя ступень. */
function ob_price_bulk($item) {
    $t = ob_price_ladder($item);
    return $t ? end($t)['price_cny'] : null;
}

/**
 *  ЦЕНА ТОВАРА — ЭТО ДИАПАЗОН, А НЕ ЧИСЛО.
 *
 *  Проверено на живом товаре 1688 (стол + стулья):
 *      шапка карточки: ¥57.46 ~ ¥422.50
 *      ¥57.46  = отдельный стул (самый дешёвый SKU)
 *      ¥422.50 = стол 140x80x72 + 6 стульев (самый дорогой SKU)
 *      item.price = 57.46 — то есть цена «ОТ», а не цена товара.
 *
 *  Тот же эффект в документации: item.price=2.2 против skus[].price=4.99.
 *
 *  ВЫВОД: item.price НИКОГДА не ставится на карточку. Реальная цена есть
 *  только у конкретного SKU. Поэтому 1 SKU = 1 карточка (ds_create_batch_from_1688),
 *  ровно как уже сделано для PDD в ds_create_batch_from_pdd.
 */
function ob_price_range($item, $skus) {
    $prices = [];
    foreach ($skus as $s) {
        if ($s['price_cny'] !== null && $s['price_cny'] > 0) $prices[] = $s['price_cny'];
    }

    // Вариантов нет — товар односоставный, цена одна
    if (!$prices) {
        $p = ob_num($item['price'] ?? null);
        $ladder = ob_price_ladder($item);
        if ($p === null && $ladder) $p = $ladder[0]['price_cny'];
        return ['from' => $p, 'to' => $p, 'count' => 0, 'note' => null];
    }

    $from = min($prices);
    $to   = max($prices);
    $note = null;

    if (count($prices) > 1 && $from > 0 && ($to - $from) / $from > 0.20) {
        $note = 'Цена от ' . $from . ' до ' . $to . ' ¥ по ' . count($prices) . ' вариантам. '
              . 'Заводить карточки ПОВАРИАНТНО (ds_create_batch_from_1688), одна общая цена бессмысленна.';
    }

    return ['from' => $from, 'to' => $to, 'count' => count($prices), 'note' => $note];
}

function ob_num($v) {
    if ($v === null || $v === '' || $v === false) return null;
    $v = str_replace([',', '¥', ' '], '', (string)$v);
    return is_numeric($v) ? (float)$v : null;
}

/**
 *  Как ob_num, но НОЛЬ тоже считает отсутствием значения.
 *  1688 отдаёт item_weight = '0' (строка!), а не пустую строку —
 *  проверено на живом товаре 709411502301. Без этого вес карточки станет 0.
 */
function ob_num_pos($v) {
    $n = ob_num($v);
    return ($n === null || $n <= 0) ? null : $n;
}


// ─────────────────────────────────────────────────────────────────────
//  5. СВОЙСТВА props[]
//     Имена дублируются (颜色 × 9) → группируем в name => [values]
// ─────────────────────────────────────────────────────────────────────
function ob_props_group($item) {
    $out = [];
    foreach (($item['props'] ?? []) as $p) {
        $n = trim((string)($p['name'] ?? ''));
        $v = trim((string)($p['value'] ?? ''));
        if ($n === '') continue;
        $out[$n][] = $v;
    }
    return $out;
}

/**
 *  Габариты из props[].
 *  1688 обычно кладёт item_size/item_weight пустыми, зато в props бывают
 *  «长度(CM)», «宽度(CM)», «高度(CM)», «重量», «尺寸» и т.п.
 */
function ob_dims_from_props($item) {
    $g = ob_props_group($item);
    $res = ['len' => null, 'wid' => null, 'hei' => null, 'weight' => null, 'source' => null];

    $map = [
        'len'    => ['长度', '长', '产品长度', '长度(CM)', '长度(cm)'],
        'wid'    => ['宽度', '宽', '产品宽度', '宽度(CM)', '宽度(cm)'],
        'hei'    => ['高度', '高', '产品高度', '厚度', '高度(CM)'],
        'weight' => ['重量', '毛重', '净重', '单件重量', '产品重量'],
    ];

    foreach ($map as $key => $names) {
        foreach ($g as $pname => $vals) {
            foreach ($names as $needle) {
                if (mb_strpos($pname, $needle) !== false) {
                    $n = ob_extract_number($vals[0] ?? '');
                    if ($n !== null) {
                        // Вес: если в имени/значении кг — оставляем, если г — переводим
                        if ($key === 'weight') {
                            $s = $pname . ' ' . ($vals[0] ?? '');
                            if (preg_match('/\b(g|克)\b/ui', $s) && !preg_match('/(kg|千克|公斤)/ui', $s)) {
                                $n = $n / 1000;
                            }
                        }
                        $res[$key] = $n;
                        $res['source'] = 'props';
                    }
                    break 2;
                }
            }
        }
    }

    // Слитный формат «尺寸: 48*20*88.5» или «48x20x88.5cm»
    if ($res['len'] === null) {
        foreach ($g as $pname => $vals) {
            if (mb_strpos($pname, '尺寸') !== false || mb_strpos($pname, '规格') !== false) {
                $d = ob_parse_dims_string($vals[0] ?? '');
                if ($d) {
                    $res['len'] = $d[0]; $res['wid'] = $d[1]; $res['hei'] = $d[2];
                    $res['source'] = 'props';
                    break;
                }
            }
        }
    }

    // Последняя попытка — из названий SKU («48*20*88.5» встречается в 规格)
    if ($res['len'] === null) {
        foreach (($item['skus']['sku'] ?? []) as $s) {
            $d = ob_parse_dims_string($s['properties_name'] ?? '');
            if ($d) {
                $res['len'] = $d[0]; $res['wid'] = $d[1]; $res['hei'] = $d[2];
                $res['source'] = 'sku';
                break;
            }
        }
    }

    return $res;
}

function ob_extract_number($s) {
    if (preg_match('/(\d+(?:[.,]\d+)?)/', (string)$s, $m)) {
        return (float)str_replace(',', '.', $m[1]);
    }
    return null;
}

/** «48*20*88.5», «48x20x88.5cm», «48×20×88,5 см» → [48,20,88.5] */
function ob_parse_dims_string($s) {
    // Форматы, реально встреченные на 1688:
    //   «单桌:120*60*72cm餐桌4椅» → [120,60,72]
    //   «单椅:长38*宽34*高42»      → [38,34,42]   иероглифы МЕЖДУ числами
    //   «长48cm*宽20cm*高88.5cm»  → [48,20,88.5]
    //   «1000*600*210mm»          → [100,60,21]   ММ, а не см!
    // Первая версия ломалась на 长38*宽34*高42 — поймано тестом на живых SKU.
    //
    // ЕДИНИЦЫ. Старая регулярка «mm» РАСПОЗНАВАЛА, но не переводила: числа
    // уходили как сантиметры. Стол 1000*600*210 мм получал объём
    // (1000/100)*(600/100)*(210/100) = 126 м³ — половина контейнера на одну
    // столешницу, фрахт в 915 раз дороже товара. Верно 0.126 м³.
    $s = str_replace('，', ',', (string)$s);
    $s = preg_replace('/(\d),(\d)/', '$1.$2', $s);

    $num  = '(\d+(?:\.\d+)?)';
    $unit = '(cm|CM|\x{5398}\x{7C73}|mm|MM|\x{6BEB}\x{7C73})?';   // 厘米 / 毫米
    $pre  = '(?:[\x{957F}\x{5BBD}\x{9AD8}\x{539A}\x{6DF1}\x{5F84}])?';
    $sep  = '\s*[*xX\x{00D7}\x{FF0A}]\s*';
    $rx = '/'.$pre.'\s*'.$num.'\s*'.$unit.$sep
             .$pre.'\s*'.$num.'\s*'.$unit.$sep
             .$pre.'\s*'.$num.'\s*'.$unit.'/u';

    if (!preg_match($rx, $s, $m)) return null;

    $dims = [(float)$m[1], (float)$m[3], (float)$m[5]];
    // Единица могла стоять при любом из трёх чисел — берём первую названную.
    // ?? '' обязателен: если единицы нет у последнего числа, группы в $m
    // просто не будет, и PHP пишет предупреждение в лог на каждом товаре.
    $u = mb_strtolower(trim(($m[2] ?? '') ?: (($m[4] ?? '') ?: ($m[6] ?? ''))));
    $isMM = ($u === 'mm' || $u === '毫米');

    // Единицу не написали, а числа великоваты: коробка от 3 метров по всем
    // сторонам не бывает — значит это миллиметры. Порог 300 намеренно высокий:
    // настоящий шкаф 240 см в упаковке существует, коробка 300×300×300 см нет.
    if (!$u && $dims[0] >= 300 && $dims[1] >= 300 && $dims[2] >= 300) $isMM = true;

    if ($isMM) foreach ($dims as $i => $v) $dims[$i] = round($v / 10, 2);

    return $dims;
}

/**
 *  Комплект? «餐桌4椅» = стол + 4 стула.
 *  Габариты в названии — ТОЛЬКО основного предмета. Доп. предметы (стулья)
 *  едут в своих коробках, объём каждого берётся из ds_kd_rules().
 *  Пример: стол 0.10 м3 + 4 стула x 0.04 м3 = 0.26 м3 на комплект.
 */
function ob_detect_set($name) {
    // \x{693F}=椅 \x{51F3}=凳 \x{67DC}=柜 \x{62BD}=抽
    if (preg_match('/(\d+)\s*(?:\x{628A}|\x{5F20})?\s*(\x{693F}|\x{51F3}|\x{67DC})/u', (string)$name, $m)) {
        return ['is_set' => true, 'qty' => (int)$m[1], 'type' => $m[2]];
    }
    return ['is_set' => false, 'qty' => null, 'type' => null];
}

/** «单桌:120*60*72cm餐桌4椅» → «120*60*72cm餐桌4椅»; «0:3:颜色:灰色» → «灰色» */
/**
 *  Разбор properties_name в пары «свойство => значение».
 *  Формат: «0:0:颜色:极小号1层长42cm;1:0:尺寸:...»
 *  Сегменты через «;», внутри — id:id:имя:значение.
 */
function ob_sku_props_pairs($n) {
    $out = [];
    foreach (explode(';', (string)$n) as $seg) {
        $seg = trim($seg);
        if ($seg === '') continue;
        $parts = explode(':', $seg, 4);      // limit 4 — значение может содержать «:»
        if (count($parts) >= 4) {
            $out[trim($parts[2])] = trim($parts[3]);
        } elseif (count($parts) === 3) {
            $out[trim($parts[2])] = '';
        }
    }
    return $out;
}

/**
 *  Чистое имя варианта для карточки.
 *
 *  ВНИМАНИЕ: продавцы 1688 суют РЕКЛАМУ в поля свойств. Живой пример
 *  (offer 709411502301): в поле 尺寸 («размер») лежит
 *      «1.出厂价冲量、发超市同款品质、2.预计12小时内发货»
 *  = «отпускная цена завода, качество как в супермаркете, отгрузка за 12ч».
 *  А настоящее имя варианта — в поле 颜色: «极小号1层长42cm».
 *
 *  Первая версия брала последний кусок строки и вытаскивала именно рекламу.
 *  Теперь разбираем по парам и отсеиваем рекламный мусор.
 */
function ob_sku_clean_name($n) {
    $n = trim((string)$n);
    if ($n === '') return '';

    $pairs = ob_sku_props_pairs($n);
    if (!$pairs) {
        $pos = mb_strpos($n, ':');
        return $pos !== false ? trim(mb_substr($n, $pos + 1)) : $n;
    }

    $good = [];
    foreach ($pairs as $k => $v) {
        if ($v === '') continue;
        if (ob_is_marketing($v)) continue;
        $v = ob_strip_seller_notes($v);
        if ($v !== '') $good[] = $v;
    }
    // Всё отсеялось — берём первое значение, но тоже чистим
    if (!$good) {
        $vals = array_values($pairs);
        $good[] = ob_strip_seller_notes($vals[0] ?? '');
    }

    return trim(implode(' · ', array_slice($good, 0, 2)));
}

/**
 *  Убираем пометки продавца из имени варианта — их нельзя показывать покупателю.
 *
 *  Живые примеры (offer 709411502301):
 *      «极小号1层长42cm【防尘差不建议拍】» — «пылезащита слабая, БРАТЬ НЕ СОВЕТУЕМ»
 *      «超小号1层长60cm【极简版】»        — «упрощённая версия»
 *      «小号4层长60cm【个人版】»          — «частная версия»
 *
 *  Первое — прямая антиреклама, китаец пишет её оптовику, чтобы тот не
 *  заказал не то. Розничный покупатель прочтёт как «не покупайте».
 *  Остальное — внутренняя градация качества, покупателю бессмысленна.
 *
 *  Скобки 【】, （）, () вырезаем целиком. Габариты и «слои» остаются.
 */
function ob_seller_warning($v) {
    $v = (string)$v;
    // Достаём содержимое скобок — там продавец пишет правду
    $inner = '';
    if (preg_match_all('/\x{3010}([^\x{3011}]*)\x{3011}/u', $v, $m)) {
        $inner .= implode(' ', $m[1]);
    }
    if (preg_match_all('/\x{FF08}([^\x{FF09}]*)\x{FF09}/u', $v, $m)) {
        $inner .= ' ' . implode(' ', $m[1]);
    }
    if (trim($inner) === '') return null;

    // Негативные маркеры:
    // \x{4E0D}\x{5EFA}\x{8BAE}=不建议 (не советуем)
    // \x{6B21}\x{54C1}=次品 (брак)
    // \x{7455}\x{75B5}=瑕疵 (дефект)
    // \x{4E0D}\x{5305}\x{9000}=不包退 (возврат не принимаем)
    // \x{6E05}\x{4ED3}=清仓 (распродажа остатков)
    // \x{5904}\x{7406}=处理 (уценка)
    $bad = ["\x{4E0D}\x{5EFA}\x{8BAE}", "\x{6B21}\x{54C1}", "\x{7455}\x{75B5}",
            "\x{4E0D}\x{5305}\x{9000}", "\x{6E05}\x{4ED3}", "\x{5904}\x{7406}"];
    foreach ($bad as $b) {
        if (preg_match('/' . $b . '/u', $inner)) {
            return trim($inner);
        }
    }
    return null;
}

/**
 *  Убираем пометки продавца из имени варианта — их нельзя показывать покупателю.
 *
 *  Живые примеры (offer 709411502301):
 *      «极小号1层长42cm【防尘差不建议拍】» — «пылезащита слабая, БРАТЬ НЕ СОВЕТУЕМ»
 *      «超小号1层长60cm【极简版】»        — «упрощённая версия»
 *      «小号4层长60cm【个人版】»          — «частная версия»
 *
 *  Первое — прямая антиреклама, китаец пишет её оптовику, чтобы тот не
 *  заказал не то. Розничный покупатель прочтёт как «не покупайте».
 *  Остальное — внутренняя градация качества, покупателю бессмысленна.
 *
 *  ВАЖНО: перед вырезанием ob_seller_warning() достаёт негатив для менеджера —
 *  «не советуем» это ценнейшая информация для закупки, терять её нельзя.
 *
 *  Скобки 【】, （）, () вырезаем целиком. Габариты и «слои» остаются.
 */
function ob_strip_seller_notes($v) {
    $v = (string)$v;
    // \x{3010}=【 \x{3011}=】 \x{FF08}=（ \x{FF09}=）
    $v = preg_replace('/\x{3010}[^\x{3011}]*\x{3011}/u', '', $v);
    $v = preg_replace('/\x{FF08}[^\x{FF09}]*\x{FF09}/u', '', $v);
    $v = preg_replace('/\([^)]*\)/u', '', $v);
    // Хвосты вида «- 特价» / «/ 促销»
    $v = preg_replace('/[\s\-\/\|]+$/u', '', $v);
    return trim($v);
}

/**
 *  Рекламный текст в поле свойства?
 *  Признаки: перечисление через «、», обещания отгрузки, длина.
 */
function ob_is_marketing($v) {
    $v = (string)$v;
    if (mb_strlen($v) > 40) return true;
    // \x{3001}=、 \x{53D1}\x{8D27}=发货 \x{9884}\x{8BA1}=预计 \x{51B2}\x{91CF}=冲量
    // \x{51FA}\x{5382}\x{4EF7}=出厂价 \x{5C0F}\x{65F6}=小时 \x{540C}\x{6B3E}=同款
    $marks = ["\x{3001}", "\x{53D1}\x{8D27}", "\x{9884}\x{8BA1}", "\x{51B2}\x{91CF}",
              "\x{51FA}\x{5382}\x{4EF7}", "\x{5C0F}\x{65F6}", "\x{540C}\x{6B3E}"];
    foreach ($marks as $m) {
        if (preg_match('/' . $m . '/u', $v)) return true;
    }
    return false;
}

/**
 *  Геометрический объём по габаритам. БЕЗ множителей.
 *  Используется только там, где габариты — это уже РАЗМЕР КОРОБКИ.
 *
 *  ВНИМАНИЕ: НЕ применять к собранным габаритам из названия SKU!
 *  Проверено на живых данных: множитель 0.38 врёт в обе стороны —
 *      стул  38×34×42: 0.38 даёт 0.0206, замер ИТК = 0.04   (в 1.9x мало)
 *      стол 140×80×72: 0.38 даёт 0.3064, замер ИТК = 0.10   (в 3.1x много)
 *  Один множитель не описывает и плоскую столешницу, и неразборный стул.
 *  Для фрахта: ds_kd_rules() (замеры) либо 包装尺寸 (коробка от поставщика).
 */
function ob_volume_m3($len, $wid, $hei) {
    if (!$len || !$wid || !$hei) return null;
    return round(($len / 100) * ($wid / 100) * ($hei / 100), 4);
}

/**
 *  Размер КОРОБКИ из props. Лучший источник — поставщик указал сам.
 *  Ищем: 包装尺寸 / 外箱尺寸 / 装箱尺寸 / 箱规 / 包装规格
 *  Плюс 装箱数 / 每箱数量 — сколько штук в коробке.
 */
function ob_box_from_props($item) {
    $g = ob_props_group($item);
    $res = ['len'=>null,'wid'=>null,'hei'=>null,'pcs'=>null];

    // \x{5305}\x{88C5}=包装 \x{5C3A}\x{5BF8}=尺寸 \x{5916}\x{7BB1}=外箱
    // \x{88C5}\x{7BB1}=装箱 \x{7BB1}\x{89C4}=箱规 \x{89C4}\x{683C}=规格
    $boxKeys = ["\xe5\x8c\x85\xe8\xa3\x85\xe5\xb0\xba\xe5\xaf\xb8",  // 包装尺寸
                "\xe5\xa4\x96\xe7\xae\xb1\xe5\xb0\xba\xe5\xaf\xb8",  // 外箱尺寸
                "\xe8\xa3\x85\xe7\xae\xb1\xe5\xb0\xba\xe5\xaf\xb8",  // 装箱尺寸
                "\xe7\xae\xb1\xe8\xa7\x84",                                // 箱规
                "\xe5\x8c\x85\xe8\xa3\x85\xe8\xa7\x84\xe6\xa0\xbc"]; // 包装规格

    foreach ($g as $name => $vals) {
        foreach ($boxKeys as $k) {
            if (mb_strpos($name, $k) !== false) {
                $d = ob_parse_dims_string($vals[0] ?? '');
                if ($d) { $res['len']=$d[0]; $res['wid']=$d[1]; $res['hei']=$d[2]; break 2; }
            }
        }
    }

    // Штук в коробке: 装箱数 / 每箱数量
    $pcsKeys = ["\xe8\xa3\x85\xe7\xae\xb1\xe6\x95\xb0",                  // 装箱数
                "\xe6\xaf\x8f\xe7\xae\xb1\xe6\x95\xb0\xe9\x87\x8f"];  // 每箱数量
    foreach ($g as $name => $vals) {
        foreach ($pcsKeys as $k) {
            if (mb_strpos($name, $k) !== false) {
                $n = ob_extract_number($vals[0] ?? '');
                if ($n && $n > 0) { $res['pcs'] = (int)$n; break 2; }
            }
        }
    }

    return $res;
}

/**
 *  ОБЪЁМ ДЛЯ ФРАХТА. Приоритет источников:
 *    1) box_props   — 包装尺寸 от поставщика (настоящая коробка)
 *    2) kd_category — ds_kd_rules(), замеры реальных поставок ИТК
 *    3) none        — не определено, задать вручную
 *
 *  Геометрия по собранным габаритам НЕ используется — проверено, врёт.
 */
function ob_freight_volume($title, $box = null) {
    // 1. Коробка от поставщика
    if ($box && $box['len'] && $box['wid'] && $box['hei']) {
        $v = ob_volume_m3($box['len'], $box['wid'], $box['hei']);
        if ($box['pcs'] && $box['pcs'] > 1) $v = round($v / $box['pcs'], 4);  // на штуку
        return ['volume'=>$v, 'weight'=>null, 'source'=>'box_props', 'cat'=>null];
    }

    // 2. Догадка ds_kd_rules по названию.
    //    ВАЖНО: матч идёт по названию ТОВАРА — имя варианта на него не влияет.
    //    Значит одна цифра ложится на все варианты сразу. Для обувницы это
    //    69 вариантов от 6.80 до 49 ¥ — 0.09 м3 верны максимум для одного.
    //    Поэтому это ПОДСКАЗКА менеджеру, а не основание для цены:
    //    ds_create_batch_from_1688 её к цене не допускает.
    $c = ob_guess_category($title);
    if ($c && ($c['confidence'] ?? '') !== 'default') {
        return ['volume'=>(float)$c['volume_m3'], 'weight'=>(float)$c['weight_kg'],
                'source'=>'kd_category', 'cat'=>$c['product_id'] ?? null,
                'cat_name'=>$c['cat'] ?? null];
    }

    // 3. Дефолт категории — слабо, но лучше нуля; помечаем
    if ($c) {
        return ['volume'=>(float)$c['volume_m3'], 'weight'=>(float)$c['weight_kg'],
                'source'=>'none', 'cat'=>'other', 'cat_name'=>$c['cat'] ?? null];
    }
    return ['volume'=>null, 'weight'=>null, 'source'=>'none', 'cat'=>null];
}

// ─────────────────────────────────────────────────────────────────────
//  6. ФОТО
//     item_imgs — массив ОБЪЕКТОВ {url}, не строк.
//     desc_img  — массив строк, вертикальные полотна с китайским текстом.
//     props_img — {"0:0": url, ...} по коду варианта.
// ─────────────────────────────────────────────────────────────────────
function ob_images($item) {
    $out = ['main' => [], 'sku' => [], 'desc' => []];

    // Берём только первые OB_MAX_MAIN_IMGS — остальные не нужны и стоят места.
    foreach (($item['item_imgs'] ?? []) as $i => $img) {
        if ($i >= OB_MAX_MAIN_IMGS) break;
        $u = is_array($img) ? ($img['url'] ?? '') : (string)$img;
        if ($u) $out['main'][] = ['sort' => $i, 'url' => ob_fix_url($u)];
    }
    // Если item_imgs пуст — берём pic_url
    if (!$out['main'] && !empty($item['pic_url'])) {
        $out['main'][] = ['sort' => 0, 'url' => ob_fix_url($item['pic_url'])];
    }

    foreach (($item['props_img'] ?? []) as $prop => $u) {
        if ($u) $out['sku'][] = ['properties' => $prop, 'url' => ob_fix_url($u)];
    }

    foreach (($item['desc_img'] ?? []) as $i => $u) {
        if ($i >= OB_MAX_DESC_IMGS) break;
        if ($u) $out['desc'][] = ['sort' => $i, 'url' => ob_fix_url($u)];
    }

    return $out;
}

function ob_fix_url($u) {
    $u = trim((string)$u);
    if (strpos($u, '//') === 0) $u = 'https:' . $u;
    return $u;
}

/**
 *  Уменьшенная копия с CDN Alibaba без скачивания.
 *  alicdn поддерживает суффикс размера: ..._400x400.jpg
 *
 *  ВНИМАНИЕ: приём рабочий, но подтверждён не на 100% для всех
 *  поддоменов cbu01/img.alicdn. ПЕРЕД боевым использованием
 *  проверить глазами на 2–3 реальных ссылках. Если не откроется —
 *  качаем оригинал и жмём на своей стороне перед s3_put_object().
 */
function ob_alicdn_resize($url, $size = 400) {
    if (!preg_match('~alicdn\.com~i', $url)) return $url;
    if (preg_match('~_\d+x\d+\.(jpg|jpeg|png|webp)~i', $url)) return $url; // уже с размером
    return preg_replace('~\.(jpg|jpeg|png|webp)(\?.*)?$~i', "_{$size}x{$size}.$1", $url);
}


// ─────────────────────────────────────────────────────────────────────
//  7. РАЗБОР ОТВЕТА → нормальная структура
// ─────────────────────────────────────────────────────────────────────
function ob_parse($json) {
    $item = $json['item'] ?? null;
    if (!is_array($item)) return ['ok' => false, 'error' => 'В ответе нет item'];

    $ladder = ob_price_ladder($item);
    $imgs   = ob_images($item);
    $seller = $item['seller_info'] ?? [];
    $title  = (string)($item['title'] ?? '');

    $box  = ob_box_from_props($item);       // 包装尺寸 — настоящая коробка, если указана
    $skus = ob_parse_skus($item, $box);
    $pr   = ob_price_range($item, $skus);   // цена — ДИАПАЗОН по вариантам

    $notes = [];
    if ($pr['note']) $notes[] = $pr['note'];

    // ---- СОБРАННЫЕ габариты (для покупателя) ----
    $dimsP = ob_dims_from_props($item);
    $len = $dimsP['len']; $wid = $dimsP['wid']; $hei = $dimsP['hei'];
    $dimsSrc = $dimsP['source'];
    if ($len === null && !empty($item['item_size'])) {
        $d = ob_parse_dims_string($item['item_size']);
        if ($d) { [$len, $wid, $hei] = $d; $dimsSrc = 'item_size'; }
    }

    // ---- ОБЪЁМ ФРАХТА (коробка) ----
    $fv  = ob_freight_volume($title, $box);
    $vol = $fv['volume'];
    // item_weight приходит как '0' — ob_num_pos отбрасывает ноль как отсутствие
    $wt  = ob_num_pos($item['item_weight'] ?? null);
    if ($wt === null) $wt = $dimsP['weight'];
    if ($wt === null) $wt = $fv['weight'];

    if ($fv['source'] === 'box_props') {
        $notes[] = 'Объём ' . $vol . ' м3 — по 包装尺寸 от поставщика.';
    } elseif ($fv['source'] === 'kd_category') {
        $notes[] = 'Объём ' . $vol . ' м3 — замер ИТК по категории «' . ($fv['cat_name'] ?? '') . '».';
    } else {
        $notes[] = 'ВНИМАНИЕ: категория не опознана, объём ' . $vol . ' м3 — дефолт. Задать вручную.';
    }

    return [
        'ok'            => true,
        'num_iid'       => (string)($item['num_iid'] ?? ''),
        'title_cn'      => $title,
        'price_cny'     => $pr['from'],          // цена «ОТ» — справочно, НЕ для карточки
        'price_max_cny' => $pr['to'],
        'sku_count'     => $pr['count'],
        // Оптовая цена — ТОЛЬКО если это настоящая ступенчатая цена (阶梯价).
        // Если priceRange оказался диапазоном — лестницы нет, поле пустое.
        'price_min_cny' => $ladder ? end($ladder)['price_cny'] : null,
        'has_ladder'    => $ladder ? 1 : 0,
        'ladder'        => $ladder,
        'import_note'   => $notes ? implode(' | ', $notes) : null,
        'moq'           => (int)($item['min_num'] ?? 1),
        'stock'         => (int)($item['num'] ?? 0),
        'total_sold'    => (int)($item['total_sold'] ?? 0),
        'unit'          => $item['unit'] ?? null,
        'location'      => $item['location'] ?? null,
        'detail_url'    => $item['detail_url'] ?? null,
        'video_url'     => $item['video'] ?? null,
        'cid'           => (string)($item['cid'] ?? ''),
        'itc_category'  => $fv['cat_name'] ?? null,
        'kd_cat'        => $fv['cat'] ?? null,
        'seller_id'     => (string)($item['seller_id'] ?? ($seller['user_num_id'] ?? '')),
        'seller_nick'   => $item['nick'] ?? ($seller['nick'] ?? null),
        'shop_name'     => $seller['shop_name'] ?? null,
        // собранные габариты — покупателю
        'len_cm'        => $len,
        'wid_cm'        => $wid,
        'hei_cm'        => $hei,
        'dims_source'   => $dimsSrc,
        // коробка — фрахту
        'box_len_cm'    => $box['len'],
        'box_wid_cm'    => $box['wid'],
        'box_hei_cm'    => $box['hei'],
        'pcs_per_box'   => $box['pcs'],
        'weight_kg'     => $wt,
        'volume_m3'     => $vol,
        'vol_source'    => $fv['source'],
        'desc_html'     => $item['desc'] ?? null,
        'props'         => $item['props'] ?? [],
        'skus'          => $skus,
        'images'        => $imgs,
    ];
}


function ob_guess_category($title) {
    // pdd_guess_category($name_zh, $name_ru='', $cat_zh='') возвращает:
    //   ['cat','product_id','weight_kg','volume_m3','confidence','note']
    // Она НИКОГДА не возвращает null — есть дефолт 'Прочее' / 1.0 кг / 0.005 м³.
    if (!function_exists('pdd_guess_category')) return null;
    $c = pdd_guess_category($title);
    return is_array($c) ? $c : null;
}


/**
 *  Фото варианта по его ключу свойств.
 *
 *  onebound ключует props_img ОДНИМ свойством — цветом: "0:0".
 *  А properties у варианта составной — цвет + размер: "0:0;1:0".
 *  Прямое isset($propImgs[$props]) не совпадало НИКОГДА: на живом товаре
 *  709411502301 в ob_img лежало 69 фото вариантов, а привязанных — ноль.
 *  Ищем точное совпадение, затем по каждой составляющей.
 */
function ob_prop_img_url($props, $propImgs) {
    $props = trim((string)$props);
    if ($props === '' || !$propImgs) return null;
    if (isset($propImgs[$props])) return $propImgs[$props];
    foreach (explode(';', $props) as $one) {
        $one = trim($one);
        if ($one !== '' && isset($propImgs[$one])) return $propImgs[$one];
    }
    return null;
}

/**
 *  Привязать УЖЕ СОХРАНЁННЫЕ фото к вариантам, не перезагружая товар.
 *  ob_fetch для этого не годится: он сносит ob_sku через DELETE вместе
 *  с переводом и связью product_id.
 *
 *  Порядок важен: сначала ob_photos (перенос в S3), потом эта функция —
 *  иначе привяжем ссылки на alicdn, который из России не открывается.
 *
 *  @return int сколько вариантов получили фото
 */
function ob_link_sku_photos($num_iid) {
    $imgs = ob_all("SELECT properties, url_src, url_s3 FROM ob_img
                     WHERE num_iid=? AND kind='sku' AND properties<>''", [$num_iid]);
    if (!$imgs) return 0;
    $map = [];
    foreach ($imgs as $i) $map[$i['properties']] = $i;

    $hasS3 = (bool)ob_row("SHOW COLUMNS FROM ob_sku LIKE 'img_s3'");
    $skus = ob_all("SELECT id, properties FROM ob_sku WHERE num_iid=?", [$num_iid]);
    $n = 0;
    foreach ($skus as $s) {
        $props = trim((string)$s['properties']);
        $hit = null;
        if (isset($map[$props])) $hit = $map[$props];
        else foreach (explode(';', $props) as $one) {
            $one = trim($one);
            if ($one !== '' && isset($map[$one])) { $hit = $map[$one]; break; }
        }
        if (!$hit) continue;
        $src = $hit['url_src'] ?: null;
        $s3  = $hit['url_s3']  ?: null;
        if ($hasS3) ob_exec("UPDATE ob_sku SET img_url=?, img_s3=? WHERE id=?", [$src, $s3, $s['id']]);
        else        ob_exec("UPDATE ob_sku SET img_url=? WHERE id=?", [$s3 ?: $src, $s['id']]);
        $n++;
    }
    return $n;
}

function ob_parse_skus($item, $box = null) {
    $out = [];
    $propImgs = $item['props_img'] ?? [];
    $title    = (string)($item['title'] ?? '');

    foreach (($item['skus']['sku'] ?? []) as $s) {
        $props  = (string)($s['properties'] ?? '');
        $nameCn = (string)($s['properties_name'] ?? '');

        // Опечатка onebound: на уровне SKU ключ «orginal price» ЧЕРЕЗ ПРОБЕЛ
        $price = ob_num($s['price'] ?? null);
        if ($price === null) $price = ob_num($s['total_price'] ?? null);
        if ($price === null) $price = ob_num($s['orginal price'] ?? null);
        if ($price === null) $price = ob_num($s['orginal_price'] ?? null);

        // СОБРАННЫЕ габариты из названия варианта — ДЛЯ ПОКУПАТЕЛЯ.
        // «单桌:120*60*72cm餐桌4椅» → 120×60×72 = размер стола, НЕ коробки.
        $d   = ob_parse_dims_string($nameCn);
        $set = ob_detect_set($nameCn);

        // Продавец сам предупреждает о качестве? Покупателю не показываем,
        // менеджеру — обязательно. «不建议拍» = «брать не советуем».
        $sellerWarn = ob_seller_warning($nameCn);

        // Продавец бывает не заполняет имя варианта. Живой пример
        // (offer 709411502301): «0:3:颜色:» — цена 16.25 есть, названия нет.
        // Безымянную карточку в каталог не пускаем.
        $clean = ob_sku_clean_name($nameCn);
        $noName = ($clean === '');
        if ($noName) {
            $clean = 'Вариант ' . preg_replace('/[^0-9]/', '', $props);
        }

        // ОБЪЁМ ФРАХТА — только по категории (замеры ИТК) или по 包装尺寸.
        // Геометрия собранного изделия для фрахта НЕ годится (см. ob_volume_m3).
        $fv   = ob_freight_volume($title . ' ' . $nameCn, $box);
        $vol  = $fv['volume'];
        $wt   = $fv['weight'];
        $note = null;

        // Комплект: основной предмет + N доп. предметов, каждый в своей коробке
        if ($set['is_set'] && $set['qty']) {
            $sub = ob_freight_volume(ob_set_sub_title($set['type']), null);
            if ($sub['volume'] !== null && $sub['source'] === 'kd_category') {
                $vol = round(($vol ?? 0) + $set['qty'] * $sub['volume'], 4);
                if ($wt !== null && $sub['weight'] !== null) {
                    $wt = round($wt + $set['qty'] * $sub['weight'], 2);
                }
                $note = 'Комплект: ' . ($fv['cat_name'] ?? 'основной') . ' ' . $fv['volume'] . ' м3 + '
                      . $set['qty'] . ' x ' . ($sub['cat_name'] ?? 'доп.') . ' ' . $sub['volume']
                      . ' м3 = ' . $vol . ' м3';
            } else {
                $note = 'ВНИМАНИЕ: комплект (+' . $set['qty'] . '), категория доп. предмета не опознана. '
                      . 'Объём ' . $vol . ' м3 — только основной предмет, ЗАНИЖЕН.';
            }
        }

        // Здравый смысл: цена косвенно говорит о размере
        $sane = ob_sanity_check($price, $vol, $wt);
        if (!empty($sane['warn'])) {
            $note = trim(($note ? $note . ' | ' : '') . $sane['warn']);
        }
        // Фрахт кратно дороже товара -> объёму верить нельзя
        if (empty($sane['ok'])) { $vol = 0; }

        // Рекомендация менеджеру — ПОДСКАЗКА, а не решение.
        // Карточки не заводятся автоматически: selected=0 по умолчанию,
        // менеджер отмечает сам, глядя на цену и ратио.
        // Порядок важен: сначала НАСТОЯЩАЯ причина, потом следствия.
        // Проверка выше уже могла обнулить $vol — если сначала спросить про объём,
        // менеджер увидит «объём не определён» вместо «фрахт в 14 раз дороже».
        list($sug, $sugNote) = ob_sku_suggest(
            $price, $vol, $sane, (int)($s['quantity'] ?? 0), $sellerWarn, $noName);

        $out[] = [
            'sku_id'        => (string)($s['sku_id'] ?? ''),
            'spec_id'       => (string)($s['spec_id'] ?? ''),
            'properties'    => $props,
            'properties_cn' => $nameCn,
            'name_clean'    => $clean,
            'price_cny'     => $price,
            'quantity'      => (int)($s['quantity'] ?? 0),
            'img_url'       => (($_pi = ob_prop_img_url($props, $propImgs)) ? ob_fix_url($_pi) : null),
            // собранные габариты — покупателю
            'len_cm'        => $d[0] ?? null,
            'wid_cm'        => $d[1] ?? null,
            'hei_cm'        => $d[2] ?? null,
            'dims_source'   => $d ? 'name' : null,
            // объём коробки — фрахту
            'volume_m3'     => $vol,
            'weight_kg'     => $wt,
            'vol_source'    => empty($sane['ok']) ? 'none' : $fv['source'],
            'kd_cat'        => $fv['cat'],
            'is_set'        => $set['is_set'] ? 1 : 0,
            'set_qty'       => $set['qty'],
            'set_note'      => $note,
            'suggest'       => $sug,
            'suggest_note'  => $sugNote,
            'seller_warn'   => $sellerWarn,
            'freight_ratio' => $sane['ratio'] ?? null,
        ];
    }

    return $out;
}

/** Тип доп. предмета комплекта → строка для поиска в ds_kd_rules(). */
function ob_set_sub_title($type) {
    $map = [
        "\xe6\xa4\x85" => "\xe9\xa4\x90\xe6\xa4\x85",                      // 椅 → 餐椅
        "\xe5\x87\xb3" => "\xe5\x90\xa7\xe5\x87\xb3",                      // 凳 → 吧凳
        "\xe6\x9f\x9c" => "\xe5\xba\x8a\xe5\xa4\xb4\xe6\x9f\x9c",       // 柜 → 床头柜
    ];
    return $map[$type] ?? (string)$type;
}


// ─────────────────────────────────────────────────────────────────────
//  8. СОХРАНЕНИЕ В БД
// ─────────────────────────────────────────────────────────────────────
function ob_save($p) {
    if (empty($p['ok'])) return ['ok' => false, 'error' => 'Нет данных для сохранения'];
    $iid = $p['num_iid'];

    ob_exec(
        "INSERT INTO ob_item
          (num_iid,title_cn,price_cny,price_max_cny,sku_count,price_min_cny,moq,stock,total_sold,unit,location,
           detail_url,video_url,seller_nick,shop_name,seller_id,cid,itc_category,
           weight_kg,len_cm,wid_cm,hei_cm,box_len_cm,box_wid_cm,box_hei_cm,pcs_per_box,
           volume_m3,vol_source,dims_source,desc_html,
           import_status,import_note,created_by,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'parsed',?,?,NOW(),NOW())
         ON DUPLICATE KEY UPDATE
            title_cn=VALUES(title_cn), price_cny=VALUES(price_cny),
            price_max_cny=VALUES(price_max_cny), sku_count=VALUES(sku_count),
            box_len_cm=VALUES(box_len_cm), box_wid_cm=VALUES(box_wid_cm),
            box_hei_cm=VALUES(box_hei_cm), pcs_per_box=VALUES(pcs_per_box),
            vol_source=VALUES(vol_source),
            price_min_cny=VALUES(price_min_cny), moq=VALUES(moq), stock=VALUES(stock),
            total_sold=VALUES(total_sold), location=VALUES(location),
            weight_kg=VALUES(weight_kg), len_cm=VALUES(len_cm), wid_cm=VALUES(wid_cm),
            hei_cm=VALUES(hei_cm), volume_m3=VALUES(volume_m3),
            dims_source=VALUES(dims_source), desc_html=VALUES(desc_html),
            itc_category=VALUES(itc_category), import_note=VALUES(import_note),
            updated_at=NOW()",
        [$iid, $p['title_cn'], $p['price_cny'], $p['price_max_cny'], $p['sku_count'],
         $p['price_min_cny'], $p['moq'],
         $p['stock'], $p['total_sold'], $p['unit'], $p['location'], $p['detail_url'],
         $p['video_url'], $p['seller_nick'], $p['shop_name'], $p['seller_id'],
         $p['cid'], $p['itc_category'], $p['weight_kg'], $p['len_cm'], $p['wid_cm'],
         $p['hei_cm'], $p['box_len_cm'], $p['box_wid_cm'], $p['box_hei_cm'], $p['pcs_per_box'],
         $p['volume_m3'], $p['vol_source'], $p['dims_source'], $p['desc_html'],
         $p['import_note'], ($GLOBALS['OB_CREATED_BY'] ?? null)]
    );

    // Лестница цен
    ob_exec("DELETE FROM ob_price_tier WHERE num_iid=?", [$iid]);
    foreach ($p['ladder'] as $i => $t) {
        ob_exec("INSERT INTO ob_price_tier (num_iid,tier_no,min_qty,price_cny) VALUES (?,?,?,?)",
                [$iid, $i, $t['min_qty'], $t['price_cny']]);
    }

    // SKU
    ob_exec("DELETE FROM ob_sku WHERE num_iid=?", [$iid]);
    foreach ($p['skus'] as $k) {
        ob_exec(
            "INSERT INTO ob_sku
              (num_iid,sku_id,spec_id,properties,properties_cn,name_ru,price_cny,quantity,img_url,
               len_cm,wid_cm,hei_cm,volume_m3,weight_kg,is_set,set_qty,set_note,
               dims_source,vol_source,kd_cat,suggest,suggest_note,seller_warn,freight_ratio)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$iid, $k['sku_id'], $k['spec_id'], $k['properties'], $k['properties_cn'],
             $k['name_clean'], $k['price_cny'], $k['quantity'], $k['img_url'],
             $k['len_cm'], $k['wid_cm'], $k['hei_cm'], $k['volume_m3'], $k['weight_kg'],
             $k['is_set'], $k['set_qty'], $k['set_note'],
             $k['dims_source'], $k['vol_source'], $k['kd_cat'],
             $k['suggest'], $k['suggest_note'], $k['seller_warn'], $k['freight_ratio']]
        );
    }

    // Свойства
    ob_exec("DELETE FROM ob_prop WHERE num_iid=?", [$iid]);
    foreach ($p['props'] as $i => $pr) {
        $nm = $pr['name'] ?? ''; $vl = $pr['value'] ?? '';
        if ($nm === '') continue;
        ob_exec("INSERT INTO ob_prop (num_iid,sort,name_cn,value_cn) VALUES (?,?,?,?)",
                [$iid, $i, $nm, $vl]);
    }

    // Фото. Обложка по умолчанию — первое из item_imgs.
    ob_exec("DELETE FROM ob_img WHERE num_iid=?", [$iid]);
    foreach (['main', 'sku', 'desc'] as $kind) {
        foreach ($p['images'][$kind] as $i => $im) {
            $cover = ($kind === 'main' && $i === 0) ? 1 : 0;
            ob_exec(
                "INSERT INTO ob_img (num_iid,kind,sort,properties,url_src,is_cover,status)
                 VALUES (?,?,?,?,?,?,'new')",
                [$iid, $kind, (int)($im['sort'] ?? $i), $im['properties'] ?? null,
                 $im['url'], $cover]
            );
        }
    }

    // Поставщик — в отдельную таблицу для кабинета закупки
    if (!empty($p['seller_id'])) {
        ob_exec(
            "INSERT INTO ob_seller (seller_id,nick,shop_name,location,updated_at)
             VALUES (?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE nick=VALUES(nick), shop_name=VALUES(shop_name),
                                     location=VALUES(location), updated_at=NOW()",
            [$p['seller_id'], $p['seller_nick'], $p['shop_name'], $p['location']]
        );
    }

    return ['ok' => true, 'num_iid' => $iid];
}


// ─────────────────────────────────────────────────────────────────────
//  9. ГЛАВНАЯ ФУНКЦИЯ: ссылка → БД
// ─────────────────────────────────────────────────────────────────────
function ob_import($url_or_id, $opts = []) {
    $u = ob_normalize_url($url_or_id);

    // Короткая ссылка qr.1688.com — разворачиваем через item_password
    if (!empty($u['need_password'])) {
        $r = ob_call('item_password', ['word' => $u['word'], 'title' => 'no']);
        if (!empty($r['data']['item']['num_iid'])) {
            $u = ['ok' => true, 'num_iid' => (string)$r['data']['item']['num_iid']];
        } else {
            return ['ok' => false, 'error' => 'Не удалось развернуть короткую ссылку 1688'];
        }
    }
    if (empty($u['ok'])) return ['ok' => false, 'error' => $u['error']];

    $res = ob_fetch_item($u['num_iid'], $opts);
    if (empty($res['ok'])) {
        return ['ok' => false, 'error' => $res['reason_ru'] ?? $res['reason'] ?? 'Ошибка API',
                'error_code' => $res['error_code'] ?? null];
    }

    $p = ob_parse($res['data']);
    if (empty($p['ok'])) return ['ok' => false, 'error' => $p['error']];

    $sv = ob_save($p);
    return ['ok' => !empty($sv['ok']), 'num_iid' => $p['num_iid'], 'parsed' => $p,
            'from_cache' => !empty($res['from_cache']), 'cost_cny' => $res['cost_cny'] ?? 0];
}


// ─────────────────────────────────────────────────────────────────────
//  10. РАСХОДЫ — сводка по логу
// ─────────────────────────────────────────────────────────────────────
function ob_spend($days = 30) {
    return ob_all(
        "SELECT DATE(created_at) d, COUNT(*) calls,
                SUM(billable) billable, ROUND(SUM(cost_cny),3) cny,
                SUM(from_cache) cached
           FROM ob_log
          WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
          GROUP BY DATE(created_at) ORDER BY d DESC",
        [(int)$days]
    );
}



// ─────────────────────────────────────────────────────────────────────
//  11. ЗАПРОС ГАБАРИТОВ КОРОБКИ У ФАБРИКИ
//      На 1688 包装尺寸 в карточках не публикуют — проверено практикой.
//      Значит объём приходит только перепиской. Генерируем готовый текст,
//      менеджеру остаётся вставить в чат 1688 / WeChat.
// ─────────────────────────────────────────────────────────────────────
function ob_ask_factory_text($title, $sku_name = '', $dims = '') {
    $t  = mb_substr((string)$title, 0, 60);
    $sk = trim((string)$sku_name);
    $dm = trim((string)$dims);

    // 您好！我们是俄罗斯的进口商，想采购这款产品。
    // 麻烦提供以下信息（报关和运费计算需要）：
    // 1. 外箱尺寸（长*宽*高 cm）
    // 2. 每箱装几件
    // 3. 单件毛重 / 整箱毛重（kg）
    // 4. 是否拆装发货（KD平板包装）
    // 谢谢！
    $lines = [];
    $lines[] = "\xe6\x82\xa8\xe5\xa5\xbd\xef\xbc\x81\xe6\x88\x91\xe4\xbb\xac\xe6\x98\xaf\xe4\xbf\x84\xe7\xbd\x97\xe6\x96\xaf\xe7\x9a\x84\xe8\xbf\x9b\xe5\x8f\xa3\xe5\x95\x86\xef\xbc\x8c\xe6\x83\xb3\xe9\x87\x87\xe8\xb4\xad\xe8\xbf\x99\xe6\xac\xbe\xe4\xba\xa7\xe5\x93\x81\xe3\x80\x82";
    $lines[] = "";
    $lines[] = "\xe4\xba\xa7\xe5\x93\x81\xef\xbc\x9a" . $t;   // 产品：
    if ($sk !== '') $lines[] = "\xe8\xa7\x84\xe6\xa0\xbc\xef\xbc\x9a" . $sk;  // 规格：
    if ($dm !== '') $lines[] = "\xe6\x88\x90\xe5\x93\x81\xe5\xb0\xba\xe5\xaf\xb8\xef\xbc\x9a" . $dm; // 成品尺寸：
    $lines[] = "";
    $lines[] = "\xe9\xba\xbb\xe7\x83\xa6\xe6\x8f\x90\xe4\xbe\x9b\xe4\xbb\xa5\xe4\xb8\x8b\xe4\xbf\xa1\xe6\x81\xaf\xef\xbc\x88\xe6\x88\x91\xe4\xbb\xac\xe8\xa6\x81\xe7\xae\x97\xe6\xb5\xb7\xe8\xbf\x90\xe8\xbf\x90\xe8\xb4\xb9\xef\xbc\x89\xef\xbc\x9a"; // 麻烦提供以下信息（我们要算海运运费）：
    $lines[] = "1. \xe5\xa4\x96\xe7\xae\xb1\xe5\xb0\xba\xe5\xaf\xb8\xef\xbc\x88\xe9\x95\xbf*\xe5\xae\xbd*\xe9\xab\x98 cm\xef\xbc\x89"; // 外箱尺寸（长*宽*高 cm）
    $lines[] = "2. \xe6\xaf\x8f\xe7\xae\xb1\xe8\xa3\x85\xe5\x87\xa0\xe4\xbb\xb6";  // 每箱装几件
    $lines[] = "3. \xe5\x8d\x95\xe4\xbb\xb6\xe6\xaf\x9b\xe9\x87\x8d / \xe6\x95\xb4\xe7\xae\xb1\xe6\xaf\x9b\xe9\x87\x8d\xef\xbc\x88kg\xef\xbc\x89"; // 单件毛重/整箱毛重
    $lines[] = "4. \xe6\x98\xaf\xe5\x90\xa6\xe6\x8b\x86\xe8\xa3\x85\xe5\x8f\x91\xe8\xb4\xa7\xef\xbc\x88KD\xe5\xb9\xb3\xe6\x9d\xbf\xe5\x8c\x85\xe8\xa3\x85\xef\xbc\x89"; // 是否拆装发货（KD平板包装）
    $lines[] = "";
    $lines[] = "\xe8\xb0\xa2\xe8\xb0\xa2\xef\xbc\x81";   // 谢谢！

    return implode("\n", $lines);
}



// ─────────────────────────────────────────────────────────────────────
//  12. ПРОВЕРКА НА ЗДРАВЫЙ СМЫСЛ
//
//  Заголовки 1688 — это МЕШОК КЛЮЧЕВИКОВ, а не описание товара:
//     «鞋架子多层防尘简易收纳神器门口宿舍占地小鞋柜多功能省空间家用»
//  тут разом 鞋架 + 鞋柜 + 收纳 + 置物架. Поиск по вхождению слова даёт
//  «совпадение» почти всегда, но оно ничего не значит.
//
//  Проверено на живой выдаче item_search (7 товаров, слово 鞋架):
//     все 7 попали в hallway_rack (0.09 м3) — включая товар за ¥1.98
//     и товар за ¥198. Физически это разные вещи, объём одинаковым быть не может.
//
//  Ловим по цене: она косвенно говорит о размере. Если фрахт кратно
//  превышает стоимость товара — либо объём завышен, либо товар не стоит везти.
//  В обоих случаях нужен человек.
// ─────────────────────────────────────────────────────────────────────
/**
 *  ПОРОГ ФРАХТА — одно число на всю систему.
 *
 *  Было три, в четырёх местах: ob_sanity_check обнуляла объём при 10×,
 *  suggest гасился при 5×, а ob_cards_list в api.php считал категорию
 *  неверной уже при 3×. Вариант с ратио 4.2 получал «советуем» и
 *  «категория вероятно не та» одновременно — система спорила сама с собой.
 */
if (!defined('OB_FREIGHT_SUSPECT')) {
    define('OB_FREIGHT_SUSPECT', 3);   // догадке об объёме верить нельзя
    define('OB_FREIGHT_REJECT', 10);   // товар не стоит везти вовсе
}

/**
 *  Рекомендация менеджеру — ПОДСКАЗКА, а не решение. Карточки автоматом
 *  не заводятся: selected=0 по умолчанию, отмечает человек.
 *
 *  Одно место на всю систему: зовут и разбор ответа onebound (ob_parse_skus),
 *  и пересчёт по сохранённым данным (ob_recalc_item). Второй копии правила
 *  быть не должно — иначе экран снова начнёт спорить с базой.
 *
 *  Порядок веток важен: сначала НАСТОЯЩАЯ причина, потом следствия.
 *  Санитарная проверка выше уже могла обнулить объём — если сначала
 *  спросить про объём, менеджер увидит «объём не определён» вместо
 *  «фрахт в 14 раз дороже».
 *
 *  @return array [suggest 0|1, note|null]
 */
function ob_sku_suggest($price, $vol, $sane, $qty, $sellerWarn, $noName) {
    if ($price === null || $price <= 0)
        return [0, 'нет цены'];
    if (empty($sane['ok']))
        return [0, 'фрахт в ' . round($sane['ratio']) . ' раз дороже товара — везти невыгодно'];
    if (($sane['ratio'] ?? 0) >= OB_FREIGHT_SUSPECT)
        return [0, 'фрахт в ' . $sane['ratio'] . ' раза дороже товара'];
    if ((int)$qty <= 0)
        return [0, 'нет в наличии'];
    // Продавец сам пишет, что товар так себе — не тащим это в каталог
    if ($sellerWarn)
        return [0, 'продавец предупреждает: ' . $sellerWarn];
    if ($noName)
        return [0, 'продавец не указал название варианта — задать вручную'];
    if (!$vol || $vol <= 0)
        return [0, 'объём не определён — уточнить у фабрики'];
    return [1, null];
}

/**
 *  Пересчёт объёма/ратио/рекомендации по УЖЕ СОХРАНЁННЫМ данным.
 *
 *  Зачем отдельно от ob_fetch: тот сносит ob_sku и ob_prop через DELETE и
 *  вставляет заново — вместе с переводом (name_ru затирается китайским
 *  name_clean), характеристиками и связью product_id с заведёнными
 *  карточками. Перезагружать товар ради пересчёта правила — потерять
 *  локализацию. Здесь трогаем только счётные поля, и вызов onebound
 *  не тратится.
 *
 *  @return array ['total'=>N, 'changed'=>N]
 */
function ob_recalc_item($num_iid) {
    $rows = ob_all("SELECT id,price_cny,quantity,volume_m3,weight_kg,vol_source,
                           seller_warn,name_ru,properties_cn,suggest,freight_ratio
                      FROM ob_sku WHERE num_iid=?", [$num_iid]);
    $changed = 0;
    foreach ($rows as $r) {
        $price = $r['price_cny'] !== null ? (float)$r['price_cny'] : null;
        $vol   = (float)($r['volume_m3'] ?? 0);
        $wt    = (float)($r['weight_kg'] ?? 0);

        $sane = ob_sanity_check($price, $vol, $wt ?: null);
        if (empty($sane['ok'])) $vol = 0;

        // Имя пустое только если его не дал продавец. Перевод — не показатель.
        $noName = (trim((string)$r['properties_cn']) === '');

        list($sug, $note) = ob_sku_suggest(
            $price, $vol, $sane, (int)$r['quantity'], $r['seller_warn'], $noName);

        $ratio = $sane['ratio'] ?? null;
        $vsrc  = empty($sane['ok']) ? 'none' : $r['vol_source'];

        if ((int)$r['suggest'] === (int)$sug
            && (string)$r['freight_ratio'] === (string)$ratio) continue;

        ob_exec("UPDATE ob_sku SET suggest=?, suggest_note=?, freight_ratio=?, vol_source=?
                  WHERE id=?", [$sug, $note, $ratio, $vsrc, $r['id']]);
        $changed++;
    }
    $linked = ob_link_sku_photos($num_iid);
    return ['total' => count($rows), 'changed' => $changed, 'photos_linked' => $linked];
}

function ob_sanity_check($price_cny, $volume_m3, $weight_kg = null) {
    $out = ['ok' => true, 'warn' => null, 'ratio' => null];
    if (!$price_cny || $price_cny <= 0 || !$volume_m3 || $volume_m3 <= 0) return $out;

    $st = function_exists('get_settings') ? get_settings() : [];
    $kurs = (float)($st['kurs_cny'] ?? 13.2);
    $cont = (float)($st['cont_vol'] ?? 68);
    $fr_usd = (float)($st['freight_usd'] ?? 10000);
    $kurs_usd = (float)($st['kurs_usd'] ?? 95);
    if ($cont <= 0) $cont = 68;

    $per_m3 = ($fr_usd * $kurs_usd) / $cont;   // ~14 000 ₽ за куб
    $goods  = $price_cny * $kurs;
    $freight = $volume_m3 * $per_m3;
    if ($goods <= 0) return $out;

    $ratio = $freight / $goods;
    $out['ratio'] = round($ratio, 1);

    if ($ratio >= OB_FREIGHT_REJECT) {
        $out['ok'] = false;
        $out['warn'] = 'Фрахт в ' . round($ratio) . ' раз дороже товара ('
                     . round($freight) . ' ₽ против ' . round($goods) . ' ₽). '
                     . 'Либо объём ' . $volume_m3 . ' м3 завышен, либо товар не стоит везти. '
                     . 'Уточнить 外箱尺寸 у фабрики.';
    } elseif ($ratio >= OB_FREIGHT_SUSPECT) {
        $out['warn'] = 'Фрахт в ' . round($ratio, 1) . ' раз дороже товара — проверить объём.';
    }

    // Плотность: мебель обычно 50-400 кг/м3. Выход за границы — подозрительно.
    if ($weight_kg && $weight_kg > 0) {
        $dens = $weight_kg / $volume_m3;
        if ($dens > 800) {
            $out['warn'] = trim(($out['warn'] ? $out['warn'] . ' | ' : '')
                        . 'Плотность ' . round($dens) . ' кг/м3 — слишком тяжело для габарита, проверить.');
        } elseif ($dens < 15) {
            $out['warn'] = trim(($out['warn'] ? $out['warn'] . ' | ' : '')
                        . 'Плотность ' . round($dens) . ' кг/м3 — слишком легко, объём вероятно завышен.');
        }
    }

    return $out;
}

} // OB_1688_LOADED
