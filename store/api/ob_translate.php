<?php
/**
 * =====================================================================
 *  ИТК · Перевод 1688 → русский  ·  api/ob_translate.php
 * =====================================================================
 *
 *  Использует готовые функции api.php:
 *      ai_translate_field($text, $kind, $ctx)  — DeepSeek (AI Studio) → YandexGPT Lite → Translate
 *      yx_ds_list(array $lines)                — DeepSeek пачкой (с 07.10.2026)
 *      yx_translate(array $texts)              — Yandex Translate, запасной батч
 *
 *  ЗАЧЕМ БАТЧИНГ: у живого товара 709411502301 — 69 вариантов. Переводить
 *  по одному = 69 запросов к DeepSeek. Гоним пачкой: все имена одним списком,
 *  ответ разбираем по номерам. 69 запросов -> 2-3.
 *
 *  ЧТО ПЕРЕВОДИМ:
 *      ob_item.title_cn  -> title_ru    (название товара, режим 'name')
 *      ob_sku.name_ru                   (имена вариантов, батч)
 *      ob_prop.name_ru / value_ru       (характеристики, батч)
 *      ob_item.desc_ru                  (описание, если есть)
 *  Затем title_ru + name_ru склеиваются в products.name.
 *
 *  Подключение в api.php после ob_1688.php:
 *      require_once __DIR__ . '/ob_translate.php';
 * =====================================================================
 */

if (!defined('OB_TR_LOADED')) {
define('OB_TR_LOADED', 1);

define('OB_TR_BATCH', 25);   // строк в запросе (только для запасного пути)


// ─────────────────────────────────────────────────────────────────────
//  БАТЧ-ПЕРЕВОД СПИСКА КОРОТКИХ СТРОК
//  Отдаём нумерованный список, просим нумерованный обратно.
// ─────────────────────────────────────────────────────────────────────
/**
 *  Фиксированные китайские термины из характеристик.
 *
 *  Значения приходят отдельной пачкой от названий, поэтому переводчик видит
 *  одинокое 是 без всякого контекста и выдаёт «это», а 否 — «отрицать».
 *  Это не сбой движка: у иероглифа вне фразы нет однозначного перевода.
 *  В китайской товарке эти ответы фиксированы — подставляем сами и заодно
 *  не тратим вызов.
 */
function ob_tr_fixed($s) {
    static $map = [
        "\xe6\x98\xaf" => 'да',                 // 是
        "\xe5\x90\xa6" => 'нет',                // 否
        "\xe6\x9c\x89" => 'есть',               // 有
        "\xe6\x97\xa0" => 'нет',                // 无
        "\xe5\x8f\xaf\xe4\xbb\xa5" => 'да',              // 可以
        "\xe4\xb8\x8d\xe5\x8f\xaf\xe4\xbb\xa5" => 'нет',          // 不可以
        "\xe6\x94\xaf\xe6\x8c\x81" => 'поддерживается',   // 支持
        "\xe4\xb8\x8d\xe6\x94\xaf\xe6\x8c\x81" => 'не поддерживается', // 不支持
        "\xe7\x8e\xb0\xe8\xb4\xa7" => 'в наличии',        // 现货
        "\xe5\x8c\x85\xe9\x82\xae" => 'доставка включена', // 包邮
    ];
    $s = trim((string)$s);
    return isset($map[$s]) ? $map[$s] : null;
}

function ob_tr_batch(array $lines, $ctx = '', $what = 'варианты товара') {
    // ВАЖНО: ключи массива — это id записей в БД. array_values() их стирал,
    // из-за чего характеристики не сохранялись вовсе (0 из 20), а имена
    // вариантов писались со сдвигом на единицу. Ключи сохраняем.
    $lines = array_filter(array_map('trim', $lines), fn($x) => $x !== '');
    if (!$lines) return [];

    // Фиксированные термины не отправляем переводчику вовсе — см. ob_tr_fixed()
    $fixed = [];
    foreach ($lines as $k => $v) {
        $f = ob_tr_fixed($v);
        if ($f !== null) { $fixed[$k] = $f; unset($lines[$k]); }
    }
    if (!$lines) return $fixed;

    // ── Путь 0 (07.10.2026): DeepSeek пачкой. Понимает термины
    // (шестигранник, отвёртка, искусственные ветки) и видит название товара.
    // Что он не вернул — доделываем дословным Translate ниже.
    if (function_exists('yx_ds_list')) {
        $ds = yx_ds_list($lines, $ctx, $what);
        if ($ds) {
            $rest = array_diff_key($lines, $ds);
            if (!$rest) return $fixed + $ds;
            $fixed = $fixed + $ds;
            $lines = $rest;
        }
    }

    // ── Путь 1: Yandex Translate. У него батч нативный: отдаём массив строк,
    // получаем массив в том же порядке. Никаких нумерованных списков и их
    // разбора регуляркой — целый класс ошибок просто исчезает.
    if (function_exists('yx_translate') && function_exists('yx_ready') && yx_ready()) {
        $keys = array_keys($lines);
        $res  = yx_translate(array_values($lines));
        if ($res !== null) {
            $out = [];
            foreach ($keys as $n => $key) {
                if (isset($res[$n]) && trim($res[$n]) !== '') $out[$key] = trim($res[$n]);
            }
            if ($out) return $fixed + $out;
        }
    }

    // ── Путь 2 (запасной): нумерованный список одной строкой.
    // Нужен, если Yandex недоступен, а жив DeepSeek или MyMemory.
    if (!function_exists('ai_translate_field')) return $fixed;

    $out = [];
    foreach (array_chunk($lines, OB_TR_BATCH, true) as $chunk) {
        $numbered = [];
        $map = [];
        $i = 1;
        foreach ($chunk as $origIdx => $t) {
            $numbered[] = $i . '. ' . $t;
            $map[$i] = $origIdx;
            $i++;
        }
        $payload = implode("\n", $numbered);

        $sys = 'Это ' . $what . ' с китайской площадки 1688. Переведи КАЖДУЮ строку на русский. '
             . 'Верни РОВНО столько же строк, в том же порядке, в формате «номер. перевод». '
             . 'Числа, размеры и единицы (см, мм, кг) сохраняй как есть. '
             . 'Убирай рекламный мусор. Никаких пояснений — только список.';

        $res = ai_translate_field($payload, 'specs', $ctx . ' ' . $sys);
        if ($res === null) continue;

        foreach (explode("\n", $res) as $line) {
            if (preg_match('/^\s*(\d+)[\.\)]\s*(.+)$/u', trim($line), $m)) {
                $n = (int)$m[1];
                if (isset($map[$n])) $out[$map[$n]] = trim($m[2]);
            }
        }
    }
    return $fixed + $out;
}


// ─────────────────────────────────────────────────────────────────────
//  ПЕРЕВОД ТОВАРА ЦЕЛИКОМ
// ─────────────────────────────────────────────────────────────────────
function ob_translate_item($num_iid, $opts = []) {
    $force = !empty($opts['force']);
    $GLOBALS['yx_ds_fallback'] = 0;   // №848: был ли хоть один отказ DeepSeek
    $it = ob_row("SELECT num_iid,title_cn,title_ru,desc_html,desc_ru,itc_category
                    FROM ob_item WHERE num_iid=?", [$num_iid]);
    if (!$it) return ['ok' => false, 'error' => 'Товар не загружен'];

    $log = [];
    $calls = 0;

    // ---- 1. Название товара ----
    $titleRu = $it['title_ru'] ?? '';
    if ($force || $titleRu === '') {
        if (!function_exists('ai_translate_field')) {
            return ['ok' => false, 'error' => 'ai_translate_field() недоступна — проверьте api.php'];
        }
        $t = ai_translate_field($it['title_cn'], 'name');
        $calls++;
        if ($t === null && function_exists('pdd_translate')) {
            $t = pdd_translate($it['title_cn']);   // откат на MyMemory
            $log[] = 'Название: DeepSeek не ответил, взят MyMemory';
        }
        if ($t) {
            $titleRu = mb_substr($t, 0, 500);
            ob_exec("UPDATE ob_item SET title_ru=? WHERE num_iid=?", [$titleRu, $num_iid]);
            $log[] = 'Название переведено';
        } else {
            $log[] = 'ОШИБКА: название не переведено';
        }
    } else {
        $log[] = 'Название уже переведено (force=1 чтобы перезаписать)';
    }

    $ctx = $titleRu ?: $it['title_cn'];

    // ---- 1.5. Описание товара ----
    // В шапке модуля desc_ru значился с самого начала, но кода перевода не
    // было: поле оставалось пустым, а ob_translate_sync_products() его ждёт
    // и подставляет покупателю. Обещание без исполнения.
    // У многих товаров 1688 описание — только картинки (desc_img), текста нет
    // вовсе; тогда честно говорим об этом, а не молчим.
    $descRu = (string)($it['desc_ru'] ?? '');
    if ($force || $descRu === '') {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($it['desc_html'] ?? ''))));
        if (mb_strlen($plain) >= 20) {
            $dr = ai_translate_field(mb_substr($plain, 0, 2000), 'description', $ctx);
            $calls++;
            if ($dr) {
                $descRu = mb_substr($dr, 0, 3000);
                ob_exec("UPDATE ob_item SET desc_ru=? WHERE num_iid=?", [$descRu, $num_iid]);
                $log[] = 'Описание переведено';
            } else {
                $log[] = 'Описание перевести не удалось';
            }
        } else {
            $log[] = 'Описание: у товара только картинки, текста нет';
        }
    }

    // ---- 2. Имена вариантов (батчем) ----
    $where = $force ? '' : " AND (name_ru IS NULL OR name_ru='' OR name_ru REGEXP '[一-龥]')";
    $skus = ob_all("SELECT id, name_ru, properties_cn FROM ob_sku
                     WHERE num_iid=? {$where} ORDER BY id", [$num_iid]);

    $trSku = 0;
    if ($skus) {
        // Берём чистое имя (без рекламы продавца), а не сырое properties_cn
        $src = [];
        foreach ($skus as $s) {
            // При полном переводе (force) берём китайский оригинал, а не старый
            // русский: иначе переводчик «переводил» бы русский на русский.
            $n = $force ? '' : trim((string)$s['name_ru']);
            if ($n === '' || preg_match('/[\x{4e00}-\x{9fff}]/u', $n)) {
                $n = function_exists('ob_sku_clean_name')
                   ? ob_sku_clean_name($s['properties_cn'])
                   : $s['properties_cn'];
            }
            $src[$s['id']] = $n;
        }
        $tr = ob_tr_batch($src, $ctx, 'названия вариантов товара (размеры, цвета, комплектации)');
        $calls += (int)ceil(count($src) / OB_TR_BATCH);

        $valid = array_column($skus, 'id');   // пишем только в реально существующие id
        foreach ($tr as $id => $ru) {
            if ($ru === '' || !in_array($id, $valid)) continue;
            ob_exec("UPDATE ob_sku SET name_ru=? WHERE id=?", [mb_substr($ru, 0, 250), $id]);
            $trSku++;
        }
        $log[] = "Вариантов переведено: {$trSku} из " . count($skus);
    }

    // ---- 3. Характеристики (батчем) ----
    $props = ob_all("SELECT id,name_cn,value_cn,name_ru,value_ru FROM ob_prop
                      WHERE num_iid=? " . ($force ? '' : " AND (name_ru IS NULL OR name_ru='')")
                    . " ORDER BY sort LIMIT 30", [$num_iid]);
    $trProp = 0;
    if ($props) {
        $srcN = []; $srcV = [];
        foreach ($props as $p) { $srcN[$p['id']] = $p['name_cn']; $srcV[$p['id']] = $p['value_cn']; }

        // №314: названия и значения — одним запросом (раньше два). Ключи n…/v…
        // разводят ответ обратно. Строки идут парами: название, его значение —
        // так модель видит, к чему относится «是» или «黑色».
        $both = [];
        foreach ($props as $p) {
            $both['n' . $p['id']] = $p['name_cn'];
            $both['v' . $p['id']] = $p['value_cn'];
        }
        $trB = ob_tr_batch($both, $ctx, 'характеристики товара: строки парами — название характеристики, затем её значение');
        $trN = []; $trV = [];
        foreach ($trB as $k => $v) {
            if ($k[0] === 'n') $trN[(int)substr($k, 1)] = $v;
            else               $trV[(int)substr($k, 1)] = $v;
        }
        $calls += 1;

        foreach ($props as $p) {
            $n = $trN[$p['id']] ?? null;
            $v = $trV[$p['id']] ?? null;
            if ($n === null && $v === null) continue;
            ob_exec("UPDATE ob_prop SET name_ru=?, value_ru=? WHERE id=?",
                    [$n ?: $p['name_ru'], $v ?: $p['value_ru'], $p['id']]);
            $trProp++;
        }
        $log[] = "Характеристик переведено: {$trProp} из " . count($props);
    }

    // ---- 3.5. Отметка для очереди (№848) ----
    // Всё перевёл DeepSeek — товар готов (1). Где-то сработал запасной
    // движок — оставляем 0: фоновый перевод по расписанию переведёт его
    // DeepSeek заново, когда тот ответит. В магазине пока — временный перевод.
    try {
        ob_pdo()->exec("ALTER TABLE ob_item ADD COLUMN tr_ds TINYINT NOT NULL DEFAULT 0");
    } catch (Throwable $e) {}
    try {
        // 3 = заголовок при загрузке перевёл запасной движок, а без force мы его
        // не трогали — значит, товару всё равно нужен DeepSeek
        $was = ob_row("SELECT tr_ds FROM ob_item WHERE num_iid=?", [$num_iid]);
        $need = !empty($GLOBALS['yx_ds_fallback']) || (!$force && (int)($was['tr_ds'] ?? 0) === 3);
        ob_exec("UPDATE ob_item SET tr_ds=? WHERE num_iid=?", [$need ? 0 : 1, $num_iid]);
    } catch (Throwable $e) {}

    // ---- 4. Обновляем карточки каталога ----
    $upd = ob_translate_sync_products($num_iid);
    if ($upd) $log[] = "Карточек обновлено: {$upd}";

    return ['ok' => true, 'num_iid' => $num_iid,
            'title_ru' => $titleRu, 'desc_ru' => $descRu,
            'skus' => $trSku, 'props' => $trProp,
            'deepseek_calls' => $calls, 'log' => $log];
}


/**
 *  Склеиваем перевод в products.name: «Название товара · Вариант».
 *  Тот же формат, что в ds_create_batch_from_1688.
 */
function ob_translate_sync_products($num_iid) {
    $it = ob_row("SELECT title_ru, desc_ru FROM ob_item WHERE num_iid=?", [$num_iid]);
    if (!$it || trim((string)$it['title_ru']) === '') return 0;

    $rows = ob_all("SELECT product_id, name_ru FROM ob_sku
                     WHERE num_iid=? AND product_id IS NOT NULL", [$num_iid]);
    $n = 0;
    foreach ($rows as $r) {
        $nm = trim($it['title_ru']);
        $sku = trim((string)$r['name_ru']);
        if ($sku !== '') $nm .= ' · ' . $sku;
        $nm = mb_substr($nm, 0, 250);

        if (!empty($it['desc_ru'])) {
            ob_exec("UPDATE products SET name=?, descr=? WHERE id=?",
                    [$nm, $it['desc_ru'], $r['product_id']]);
        } else {
            ob_exec("UPDATE products SET name=? WHERE id=?", [$nm, $r['product_id']]);
        }
        $n++;
    }
    return $n;
}


/** Осталась ли где-то китайщина? Для проверки после перевода. */
function ob_translate_check($num_iid) {
    $zh = '[\x{4e00}-\x{9fff}]';
    $it = ob_row("SELECT title_ru FROM ob_item WHERE num_iid=?", [$num_iid]);

    $badSku = ob_all("SELECT id, name_ru FROM ob_sku
                       WHERE num_iid=? AND name_ru REGEXP '[一-龥]'", [$num_iid]);
    $badPr  = ob_all("SELECT product_id, name FROM products p
                       WHERE p.id IN (SELECT product_id FROM ob_sku
                                       WHERE num_iid=? AND product_id IS NOT NULL)
                         AND p.name REGEXP '[一-龥]'", [$num_iid]);

    return [
        'title_ru'      => $it['title_ru'] ?? null,
        'title_has_zh'  => $it && preg_match('/' . $zh . '/u', (string)$it['title_ru']) ? 1 : 0,
        'sku_with_zh'   => count($badSku),
        'cards_with_zh' => count($badPr),
        'cards'         => array_slice(array_column($badPr, 'name'), 0, 5),
    ];
}

} // OB_TR_LOADED
