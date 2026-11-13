<?php
// ==== ИТК · сайт 3.64.1 (03.11.2026) · заявки с сайта → «Воронки сделок» Экосистемы ====
// Каждая заявка с формы сайта (запрос по фабрике, вопрос, расчёт доставки) становится сделкой
// в воронке «Продажи» (первая стадия) с задачей «Связаться с клиентом» на сегодня.
// Фабрика со страницы — сразу в поставщиках сделки, выбранные товары — в «Что нужно клиенту».
// Дальше Фил (виртуальный руководитель) подсвечивает товары из базы, менеджер согласует с клиентом
// и переносит сделку в «Запрос цены у закупки» — закупщик получает задачу.
// Пишем прямо в базу магазина (та же, что у страниц фабрик). Нет базы или воронок — заявка уходит как раньше.

function flow_site_deal($f) {
    if (!function_exists('sup_site_db')) { if (!defined('ROOT')) define('ROOT', dirname(__DIR__)); require_once __DIR__ . '/supdb.php'; }
    $db = sup_site_db(); if (!$db) return '';
    try {
        $p = $db->query("SELECT id, def_phone FROM itc_fl_pipe WHERE deleted=0 AND kind='sales' AND owner_phone='' ORDER BY sort, created_at LIMIT 1")->fetch();
        if (!$p) return '';
        $st = $db->prepare('SELECT id FROM itc_fl_stage WHERE pipe_id=? ORDER BY sort LIMIT 1'); $st->execute([$p['id']]);
        $sid = (string)$st->fetchColumn(); if ($sid === '') return '';
        try { $db->exec("ALTER TABLE itc_fl_deal ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT ''"); } catch (Throwable $e) {}
        $now = date('Y-m-d H:i:s');
        // повторная заявка с того же телефона за 30 минут — дописываем в ту же сделку
        $dup = $db->prepare("SELECT id FROM itc_fl_deal WHERE deleted=0 AND source='site' AND REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'+','') LIKE ? AND created_at > ? ORDER BY created_at DESC LIMIT 1");
        $dup->execute(['%' . substr($f['digits'], -10), date('Y-m-d H:i:s', time() - 1800)]);
        $old = (string)$dup->fetchColumn();
        $log = $db->prepare('INSERT INTO itc_fl_log (deal_id,kind,text,who,at) VALUES (?,?,?,?,?)');
        if ($old !== '') { $log->execute([$old, 'site', 'Ещё одна заявка с сайта: ' . $f['form'] . "\n" . mb_substr($f['text'], 0, 2500), 'сайт', $now]); return $old; }
        // клиент из базы «Клиенты» — по телефону или почте
        $lead = '';
        try {
            $lq = $db->prepare("SELECT id FROM itc_lead WHERE deleted=0 AND ((? <> '' AND (email=? OR email2=?)) OR REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'+','') LIKE ?) LIMIT 1");
            $lq->execute([$f['email'], $f['email'], $f['email'], '%' . substr($f['digits'], -10)]); $lead = (string)$lq->fetchColumn();
        } catch (Throwable $e) {}
        $rn = '';
        if ($p['def_phone'] !== '') { try { $s = $db->prepare('SELECT name FROM staff WHERE phone=? LIMIT 1'); $s->execute([$p['def_phone']]); $rn = (string)$s->fetchColumn(); } catch (Throwable $e) {} }
        $sups = $f['sup'] !== '' ? json_encode([['id' => $f['sup'], 'name' => $f['brand']]], JSON_UNESCAPED_UNICODE) : '[]';
        $id = 'fd' . date('ymd') . substr(bin2hex(random_bytes(5)), 0, 8);
        $title = 'Сайт: ' . ($f['brand'] !== '' ? $f['brand'] . ' — ' : ($f['form'] !== '' ? $f['form'] . ' — ' : '')) . $f['name'];
        $db->prepare('INSERT INTO itc_fl_deal (id,pipe_id,stage_id,title,lead_id,client,contact,phone,email,resp_phone,resp_name,sups,items,note,next_at,next_do,sort,stage_at,created_by,created_phone,source)
                      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,\'[]\',?,?,?,-1,?,?,\'\',\'site\')')
           ->execute([$id, $p['id'], $sid, mb_substr($title, 0, 250), $lead, $f['name'], $f['name'], $f['phone'], $f['email'], $p['def_phone'], $rn ?: $p['def_phone'],
                      $sups, mb_substr($f['note'], 0, 5000), date('Y-m-d'), 'Связаться с клиентом по заявке с сайта', $now, 'сайт']);
        $log->execute([$id, 'site', 'Заявка с сайта: ' . $f['form'] . "\n" . mb_substr($f['text'], 0, 2500), 'сайт', $now]);
        return $id;
    } catch (Throwable $e) { error_log('flow-in: ' . $e->getMessage()); return ''; }
}
