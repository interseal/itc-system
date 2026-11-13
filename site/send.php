<?php
date_default_timezone_set('Europe/Moscow');
// Приём заявок с форм сайта: письмо на почту (+ Телеграм, если настроен)
header('Content-Type: application/json; charset=utf-8');
$cfg = require __DIR__ . '/config.php';
// Заявки в Битрикс24 — на отдельный ящик zakaz@ (к CRM подключён только он; спам на info@ в CRM не попадает)
if (empty($cfg['mail_crm'])) $cfg['mail_crm'] = 'zakaz@itcpartner.ru';
require __DIR__ . '/inc/order-mail.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok' => false]); exit; }
// Защита от ботов: скрытое поле должно быть пустым
if (!empty($_POST['website'])) { echo json_encode(['ok' => true]); exit; }

function clean($v) { return trim(strip_tags((string)$v)); }
// Список выбранных товаров приходит в несколько строк — печатаем его с новой строки (3.42.3: раньше обрезался на 6000 знаках)
function clean_items($v) { return "\n" . mb_substr(trim(strip_tags((string)$v)), 0, 40000); }

$labels = [
    'form'     => 'Форма',
    'name'     => 'Имя',
    'phone'    => 'Телефон',
    'email'    => 'Почта',
    'factory'  => 'Фабрика',
    'items'    => 'Выбранные товары',
    'question' => 'Вопрос',
    'city'     => 'Город',
    'product'  => 'Тип товара',
    'weight'   => 'Вес груза',
    'lang'     => 'Язык сайта',
];
$lines = [];
foreach ($labels as $key => $title) {
    if (isset($_POST[$key]) && clean($_POST[$key]) !== '') $lines[] = $title . ': ' . ($key === 'items' ? clean_items($_POST[$key]) : clean($_POST[$key]));
}
$phone = clean($_POST['phone'] ?? '');
if (strlen(preg_replace('/\D/', '', $phone)) < 10) {
    echo json_encode(['ok' => false, 'error' => 'Проверьте номер телефона']); exit;
}
// Почта клиента: в запросе по фабрике обязательна (туда менеджер пришлёт предложение)
$email = clean($_POST['email'] ?? '');
$isPick = isset($_POST['items']);
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Проверьте адрес почты']); exit;
}
if ($isPick && $email === '') {
    echo json_encode(['ok' => false, 'error' => 'Укажите адрес почты']); exit;
}
$lines[] = 'Страница: ' . clean($_POST['page'] ?? '');
if (!empty($_POST['utm_source'])) $lines[] = 'Реклама: ' . clean($_POST['utm_source']) . ' / ' . clean($_POST['utm_campaign'] ?? '');
$lines[] = 'Время: ' . date('d.m.Y H:i');
$text = implode("\n", $lines);

// Письмо на основную почту
$name   = clean($_POST['name'] ?? '') ?: 'Без имени';
$digits = preg_replace('/\D/', '', $phone);
$formNm = clean($_POST['form'] ?? '') ?: 'Заявка';
// Заявка с английской или китайской версии сайта — помечаем в теме письма
$lng = strtoupper(clean($_POST['lang'] ?? ''));
if (in_array($lng, ['EN', 'ZH'], true)) $formNm .= ' (' . $lng . ')';
$enc = function ($t) { return '=?UTF-8?B?' . base64_encode($t) . '?='; };
$subject = $enc('Заявка с сайта: ' . $formNm . ' — ' . $name . ', +' . $digits);
$hBase = "From: {$cfg['mail_from']}\r\n" . ($email !== '' ? "Reply-To: {$email}\r\n" : '');
$headers = $hBase . "Content-Type: text/plain; charset=utf-8\r\n";
// Запрос по фабрике (3.42.3): менеджеру — таблица с фото и файл Excel, а не сплошной список
$order = $isPick ? order_rows($_POST['items_json'] ?? '') : [];
$xlsx = ''; $oHtml = ''; $xName = ''; $more = [];
if ($order) {
    $info = ['factory' => clean($_POST['factory'] ?? ''), 'name' => clean($_POST['name'] ?? ''), 'phone' => nice_phone($digits),
             'email' => $email, 'comment' => clean($_POST['question'] ?? ''), 'page' => clean($_POST['page'] ?? ''),
             'time' => date('d.m.Y H:i'), 'extra' => in_array($lng, ['EN', 'ZH'], true) ? 'Язык сайта: ' . $lng . '. ' : ''];
    if (!preg_match('~^https?://~', $info['page'])) $info['page'] = '';
    // 3.43.0: поставщик в базе (кнопка в письме) и запрос фабрике на китайском — по словарю страницы фабрики
    $ctx = order_sup_ctx(clean($_POST['fac'] ?? ''));
    $zrows = $ctx ? order_zh_rows($order, $ctx) : [];
    $imgs = order_fetch_imgs(array_column($order, 'im'));
    $xlsx = order_xlsx($info, $order, $imgs);
    $oHtml = order_html($info, $order, $ctx, $zrows);
    $xName = 'Запрос ' . preg_replace('/[^\p{L}\p{N} ._-]+/u', '', $info['factory'] . ' ' . date('d.m.Y') . ' ' . $info['name']) . '.xlsx';
    if ($zrows) {
        $zf = $ctx['brand'] ?: $info['factory'];
        $more[] = ['询价 ' . preg_replace('/[^\p{L}\p{N} ._-]+/u', '', $zf . ' ' . date('Y-m-d')) . '.xlsx', order_xlsx(['factory' => $zf], $zrows, $imgs, true)];
    }
    if ($ctx) $text .= "\n\nПоставщик в базе: " . ORDER_ECO_URL . '?sup=' . rawurlencode($ctx['sup'])
                     . ($zrows ? "\n\n----- Запрос фабрике на китайском (без данных клиента) -----\n" . order_zh_text($zrows) : '');
    $sent = order_send($cfg['mail_to'], $subject, $hBase, $text, $oHtml, $xlsx, $xName, '', $more);
} else
$sent = @mail($cfg['mail_to'], $subject, $text, $headers);

$flowCtx = (isset($ctx) && is_array($ctx)) ? $ctx : null;   // 3.64.1: поставщик со страницы — для сделки в воронке

// Письмо в Битрикс24 (ящик заявок подключён к CRM в разделе «Почта»).
// Отправитель — свой адрес для каждого телефона: новый клиент → новый контакт и сделка,
// повторная заявка с того же телефона → письмо прикрепится к уже существующему контакту.
if (!empty($cfg['mail_crm'])) {
    $from = 'site-' . $digits . '@' . ($cfg['mail_domain'] ?? 'itcpartner.ru');
    // Reply-To — только почта клиента (если указал). Адрес info@ здесь ставить нельзя: это ящик самого портала,
    // и Битрикс считает такое письмо внутренним — сделку не создаёт.
    $rCrm = $email !== '' ? "Reply-To: {$email}\r\n" : '';
    $hCrm = "From: " . $enc($name) . " <{$from}>\r\n" . $rCrm . "Content-Type: text/plain; charset=utf-8\r\n";
    $body = "Имя: {$name}\nТелефон: +{$digits}\n" . $text;
    if ($order) { if (order_send($cfg['mail_crm'], $subject, "From: " . $enc($name) . " <{$from}>\r\n" . $rCrm, $body, $oHtml, $xlsx, $xName, '-f' . $cfg['mail_from'], $more)) $sent = true; }
    elseif (@mail($cfg['mail_crm'], $subject, $body, $hCrm, '-f' . $cfg['mail_from'])) $sent = true;
}

// Телеграм
if ($cfg['tg_bot_token'] && $cfg['tg_chat_id']) {
    $url = 'https://api.telegram.org/bot' . $cfg['tg_bot_token'] . '/sendMessage';
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query(['chat_id' => $cfg['tg_chat_id'], 'text' => mb_substr("Заявка с сайта\n" . $text, 0, 4000)]),
        'timeout' => 10,
    ]]);
    if (@file_get_contents($url, false, $ctx)) $sent = true;
}

// Битрикс24: контакт + сделка в воронке (портал в режиме «Простая CRM» — без лидов)
$b24 = '';
if (!empty($cfg['b24_webhook'])) {
    $hook = rtrim($cfg['b24_webhook'], '/') . '/';
    $call = function ($method, $params) use ($hook) {
        $ch = curl_init($hook . $method . '.json');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 15,
                                CURLOPT_POSTFIELDS => http_build_query($params)]);
        $r = json_decode((string)curl_exec($ch), true);
        curl_close($ch);
        return $r;
    };
    $name  = clean($_POST['name'] ?? '') ?: 'Без имени';
    $digits = preg_replace('/\D/', '', $phone);
    // Ищем существующий контакт по телефону, чтобы не плодить дубли
    $contactId = 0;
    $dup = $call('crm.duplicate.findbycomm', ['type' => 'PHONE', 'values' => ['+' . $digits], 'entity_type' => 'CONTACT']);
    if (!empty($dup['result']['CONTACT'][0])) $contactId = (int)$dup['result']['CONTACT'][0];
    if (!$contactId) {
        $c = $call('crm.contact.add', ['fields' => array_merge([
            'NAME' => $name, 'PHONE' => [['VALUE' => '+' . $digits, 'VALUE_TYPE' => 'WORK']],
            'SOURCE_ID' => 'WEB', 'OPENED' => 'Y',
        ], $email !== '' ? ['EMAIL' => [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']]] : [])]);
        $contactId = (int)($c['result'] ?? 0);
    } elseif ($email !== '') {
        // контакт уже есть — дописываем почту, если её там ещё нет
        $old = $call('crm.contact.get', ['id' => $contactId]);
        $has = false;
        foreach ((array)($old['result']['EMAIL'] ?? []) as $m) if (strcasecmp(trim($m['VALUE'] ?? ''), $email) === 0) $has = true;
        if (!$has) $call('crm.contact.update', ['id' => $contactId, 'fields' => ['EMAIL' => [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']]]]);
    }
    $utm = [];
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $u)
        if (!empty($_POST[$u])) $utm[strtoupper($u)] = clean($_POST[$u]);
    $deal = $call('crm.deal.add', ['fields' => array_merge([
        'TITLE'       => 'Сайт itcpartner.ru: ' . (clean($_POST['form'] ?? '') ?: 'заявка') . ' — ' . $name,
        'CATEGORY_ID' => (int)($cfg['b24_category'] ?? 0),
        'CONTACT_ID'  => $contactId,
        'SOURCE_ID'   => 'WEB',
        'SOURCE_DESCRIPTION' => clean($_POST['page'] ?? ''),
        'COMMENTS'    => nl2br(htmlspecialchars($text)),
        'OPENED'      => 'Y',
    ], $utm), 'params' => ['REGISTER_SONET_EVENT' => 'Y']]);
    if (!empty($deal['result'])) { $sent = true; $b24 = 'Битрикс24: сделка №' . $deal['result']; }
    else $b24 = 'Битрикс24: ОШИБКА ' . mb_substr(json_encode($deal, JSON_UNESCAPED_UNICODE), 0, 300);
}

// 3.64.1: заявка — сделкой в «Воронки сделок» Экосистемы (воронка «Продажи», задача менеджеру на сегодня)
require_once __DIR__ . '/inc/flow-in.php';
$flowNote = [];
if ($order) foreach ($order as $r) $flowNote[] = '• ' . $r['t'] . ($r['v'] !== '' ? ' (' . $r['v'] . ')' : '') . ' — ' . $r['q'] . ' ' . ['pcs' => 'шт.', 'set' => 'компл.', 'pair' => 'пар', 'm' => 'м'][$r['u']];
elseif (!empty($_POST['items'])) $flowNote[] = trim(strip_tags((string)$_POST['items']));
foreach (['question' => 'Вопрос', 'city' => 'Город', 'product' => 'Тип товара', 'weight' => 'Вес груза'] as $k => $t)
    if (!empty($_POST[$k])) $flowNote[] = $t . ': ' . clean($_POST[$k]);
$flowId = flow_site_deal(['form' => $formNm, 'name' => $name, 'phone' => '+' . $digits, 'digits' => $digits, 'email' => $email,
    'sup' => ($flowCtx && !empty($flowCtx['sup'])) ? (string)$flowCtx['sup'] : '',
    'brand' => $flowCtx ? (string)($flowCtx['brand'] ?: clean($_POST['factory'] ?? '')) : clean($_POST['factory'] ?? ''),
    'note' => implode("\n", $flowNote), 'text' => $text]);
if ($flowId !== '') $b24 .= ($b24 ? '; ' : '') . 'Воронки: сделка ' . $flowId;

// Копия запроса по фабрике — клиенту на почту (не больше 5 писем в час с одного адреса)
$copy = false;
if ($isPick && $email !== '' && copy_allowed(__DIR__ . '/data/copy-limit.json', $_SERVER['REMOTE_ADDR'] ?? '')) {
    $copy = send_copy($cfg, $email, clean($_POST['name'] ?? ''), clean($_POST['factory'] ?? ''),
                      trim(strip_tags((string)($_POST['items'] ?? ''))), clean($_POST['question'] ?? ''),
                      nice_phone($digits), strtolower(clean($_POST['lang'] ?? '')), $enc, $order);
}

// Запасная копия всех заявок в файл (на случай проблем с почтой)
@file_put_contents(__DIR__ . '/data/leads.log', $text . ($b24 ? "\n" . $b24 : '') . ($copy ? "\nКопия клиенту: отправлена" : '') . "\n-----\n", FILE_APPEND | LOCK_EX);

echo json_encode(['ok' => true, 'copy' => $copy]);

// ---------- Письмо клиенту с копией запроса ----------
// Заказ из корзины (3.41.1): товар, вариант, количество, упаковка — для таблицы в письме
function order_rows($js) {
    $d = json_decode((string)$js, true); $out = [];
    if (!is_array($d)) return [];
    foreach ($d as $r) {
        if (!is_array($r)) continue;
        $t = mb_substr(trim(strip_tags((string)($r['t'] ?? ''))), 0, 200); if ($t === '') continue;
        $u = in_array($r['u'] ?? '', ['pcs', 'set', 'pair', 'm'], true) ? $r['u'] : 'pcs';
        $out[] = ['t' => $t, 'v' => mb_substr(trim(strip_tags((string)($r['v'] ?? ''))), 0, 300),
                  'q' => max(1, min(99999, (int)($r['q'] ?? 1))), 'k' => max(0, min(1000000, (int)($r['k'] ?? 0))), 'u' => $u,
                  'im' => mb_substr(trim((string)($r['im'] ?? '')), 0, 500)];
        if (count($out) >= 300) break;
    }
    return $out;
}
// Российский номер — в привычном виде: +7 (978) 123-45-67
function nice_phone($d) {
    if (strlen($d) === 11 && ($d[0] === '7' || $d[0] === '8'))
        return '+7 (' . substr($d, 1, 3) . ') ' . substr($d, 4, 3) . '-' . substr($d, 7, 2) . '-' . substr($d, 9, 2);
    return '+' . $d;
}
function copy_allowed($file, $ip) {
    $f = @fopen($file, 'c+');
    if (!$f) return true;
    flock($f, LOCK_EX);
    $d = json_decode(stream_get_contents($f), true) ?: [];
    $now = time(); $key = md5($ip);
    foreach ($d as $k => $ts) { $d[$k] = array_values(array_filter((array)$ts, function ($t) use ($now) { return $now - $t < 3600; })); if (!$d[$k]) unset($d[$k]); }
    $ok = count($d[$key] ?? []) < 5;
    if ($ok) $d[$key][] = $now;
    ftruncate($f, 0); rewind($f); fwrite($f, json_encode($d));
    flock($f, LOCK_UN); fclose($f);
    return $ok;
}

function send_copy($cfg, $to, $name, $factory, $items, $comment, $phone, $lang, $enc, $order = []) {
    $L = [
        'ru' => ['subj' => 'Ваш запрос по фабрике %s — ИТК', 'hi' => 'Здравствуйте, %s!', 'hi0' => 'Здравствуйте!',
                 'got' => 'Мы получили ваш запрос по фабрике <b>%s</b>:',
                 'next' => 'Менеджер уточнит у фабрики цены и сроки, пришлёт предложение на эту почту и позвонит вам по номеру %s.',
                 'cmt' => 'Ваш комментарий:', 'reply' => 'Если хотите что-то добавить или изменить — просто ответьте на это письмо.',
                 'co' => 'ООО «Интернациональная Торговая Компания»', 'tel' => 'Отдел по работе с клиентами:', 'pcs' => 'шт.',
                 'th' => ['№', 'Товар', 'Количество', 'Всего'], 'pk' => 'уп.', 'U' => ['pcs' => 'шт.', 'set' => 'компл.', 'pair' => 'пар', 'm' => 'м'], 'tot' => 'Всего позиций:',
                 'site' => 'https://itcpartner.ru'],
        'en' => ['subj' => 'Your request to the factory %s — ITC', 'hi' => 'Hello, %s!', 'hi0' => 'Hello!',
                 'got' => 'We have received your request to the factory <b>%s</b>:',
                 'next' => 'Our manager will confirm prices and lead times with the factory, send you an offer to this email and call you at %s.',
                 'cmt' => 'Your comment:', 'reply' => 'If you want to add or change anything, just reply to this email.',
                 'co' => 'International Trading Company (ITC)', 'tel' => 'Customer service:', 'pcs' => 'pcs',
                 'th' => ['No.', 'Product', 'Quantity', 'Total'], 'pk' => 'pkg', 'U' => ['pcs' => 'pcs', 'set' => 'sets', 'pair' => 'pairs', 'm' => 'm'], 'tot' => 'Items in the order:',
                 'site' => 'https://itcpartner.ru/en/'],
        'zh' => ['subj' => '您对%s工厂的询价单 — ITC', 'hi' => '%s，您好！', 'hi0' => '您好！',
                 'got' => '我们已收到您对<b>%s</b>工厂的询价单：',
                 'next' => '经理会向工厂确认价格和交期，把报价发到此邮箱，并拨打您的电话 %s 与您联系。',
                 'cmt' => '您的备注：', 'reply' => '如需补充或修改，直接回复此邮件即可。',
                 'co' => '国际贸易公司（ITC）', 'tel' => '客户服务部：', 'pcs' => '件',
                 'th' => ['序号', '产品', '数量', '合计'], 'pk' => '箱', 'U' => ['pcs' => '件', 'set' => '套', 'pair' => '对', 'm' => '米'], 'tot' => '产品项数：',
                 'site' => 'https://itcpartner.ru/zh/'],
    ];
    $t = $L[$lang] ?? $L['ru'];
    $h = function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };
    $rows = [];
    foreach (preg_split('/\R/u', $items) as $ln) {
        $ln = trim($ln);
        if ($ln === '') continue;
        if ($t['pcs'] !== 'шт.') $ln = preg_replace('/ шт\.$/u', ' ' . $t['pcs'], $ln);
        $rows[] = $ln;
    }
    // заказ из корзины: строки с упаковками и итогом
    $qty = function ($r) use ($t) {
        $u = $t['U'][$r['u']];
        return $r['k'] ? [$r['q'] . ' ' . $t['pk'] . ' × ' . $r['k'] . ' ' . $u, ($r['q'] * $r['k']) . ' ' . $u] : [$r['q'] . ' ' . $u, $r['q'] . ' ' . $u];
    };
    if ($order) {
        $rows = [];
        foreach ($order as $i => $r) { $q = $qty($r); $rows[] = ($i + 1) . '. ' . $r['t'] . ($r['v'] !== '' ? ' — ' . $r['v'] : '') . ' — ' . $q[0] . ($r['k'] ? ' = ' . $q[1] : ''); }
    }
    $hi = $name !== '' ? sprintf($t['hi'], $name) : $t['hi0'];
    $tel = $cfg['phone2'] ?? ''; $telRaw = $cfg['phone2_raw'] ?? '';
    $replyTo = $cfg['email'] ?: $cfg['mail_to'];

    // простой текст
    $txt = $hi . "\n\n" . strip_tags(sprintf($t['got'], $factory)) . "\n" . implode("\n", $rows) . "\n\n";
    if ($comment !== '') $txt .= $t['cmt'] . ' ' . $comment . "\n\n";
    $txt .= sprintf($t['next'], $phone) . "\n" . $t['reply'] . "\n\n--\n" . $t['co'] . "\n" . $t['tel'] . ' ' . $tel . "\n" . $replyTo . "\n" . $t['site'] . "\n";

    // письмо с оформлением
    $li = '';
    $nw = function ($s) { return '<span style="white-space:nowrap">' . $s . '</span>'; };   // «2 шт.» и номер телефона не разрываем
    if ($order) {
        $th = 'padding:8px 6px;border-bottom:2px solid #112C93;font-size:13px;color:#112C93;text-align:left;font-weight:bold';
        $td = 'padding:8px 6px;border-bottom:1px solid #e6e6e6;font-size:14px;line-height:1.4;color:#1b1b1b;vertical-align:top';
        $li = '<tr><th style="' . $th . ';width:28px">' . $h($t['th'][0]) . '</th><th style="' . $th . '">' . $h($t['th'][1]) . '</th><th style="' . $th . ';text-align:right">' . $h($t['th'][2]) . '</th><th style="' . $th . ';text-align:right">' . $h($t['th'][3]) . '</th></tr>';
        foreach ($order as $i => $r) {
            $q = $qty($r);
            $li .= '<tr><td style="' . $td . ';color:#777">' . ($i + 1) . '</td>'
                 . '<td style="' . $td . '">' . (($r['im'] ?? '') !== '' && order_img_ok($r['im']) ? '<img src="' . $h($r['im']) . '" width="56" alt="" style="float:left;width:56px;height:auto;border:0;border-radius:6px;margin:0 10px 4px 0">' : '')
                 . '<b>' . $h($r['t']) . '</b>' . ($r['v'] !== '' ? '<br><span style="color:#555;font-size:13px">' . $h($r['v']) . '</span>' : '') . '</td>'
                 . '<td style="' . $td . ';text-align:right">' . $nw($h($q[0])) . '</td>'
                 . '<td style="' . $td . ';text-align:right;font-weight:bold">' . $nw($h($q[1])) . '</td></tr>';
        }
        $li .= '<tr><td colspan="4" style="padding:10px 6px 0;font-size:14px;color:#555;text-align:right">' . $h($t['tot']) . ' <b>' . count($order) . '</b></td></tr>';
    } else
    foreach ($rows as $r) $li .= '<tr><td style="padding:8px 0;border-bottom:1px solid #e6e6e6;font-size:15px;line-height:1.4;color:#1b1b1b">'
        . preg_replace_callback('/(\d+ \S+)$/u', function ($m) use ($nw) { return $nw($m[1]); }, $h($r)) . '</td></tr>';
    $html = '<!doctype html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:0;background:#f4f5f4">'
          . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f4"><tr><td align="center" style="padding:24px 12px">'
          . '<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;font-family:Arial,Helvetica,sans-serif;color:#1b1b1b">'
          . '<tr><td style="padding:24px 28px 8px"><a href="' . $t['site'] . '"><img src="https://itcpartner.ru/img/logo.png" width="110" alt="ITC" style="display:block;border:0;width:110px;height:auto"></a></td></tr>'
          . '<tr><td style="padding:12px 28px 0;font-size:16px;line-height:1.5">'
          . '<p style="margin:0 0 12px">' . $h($hi) . '</p>'
          . '<p style="margin:0 0 8px">' . sprintf($t['got'], $h($factory)) . '</p>'
          . '<table width="100%" cellpadding="0" cellspacing="0">' . $li . '</table>'
          . ($comment !== '' ? '<p style="margin:14px 0 0;color:#555"><b>' . $h($t['cmt']) . '</b> ' . nl2br($h($comment)) . '</p>' : '')
          . '<p style="margin:16px 0 8px">' . str_replace($h($phone), $nw($h($phone)), $h(sprintf($t['next'], $phone))) . '</p>'
          . '<p style="margin:0 0 20px;color:#555">' . $h($t['reply']) . '</p>'
          . '</td></tr>'
          . '<tr><td style="padding:16px 28px 24px;border-top:1px solid #e6e6e6;font-size:13px;line-height:1.6;color:#777">'
          . $h($t['co']) . '<br>' . $h($t['tel']) . ' <a href="tel:' . $h($telRaw) . '" style="color:#1b1b1b;white-space:nowrap">' . $h($tel) . '</a><br>'
          . '<a href="mailto:' . $h($replyTo) . '" style="color:#1b1b1b">' . $h($replyTo) . '</a> · <a href="' . $t['site'] . '" style="color:#1b1b1b">itcpartner.ru</a>'
          . '</td></tr></table></td></tr></table></body></html>';

    $b = 'itc' . bin2hex(random_bytes(8));
    $hd = "From: " . $enc($t['co']) . " <{$cfg['mail_from']}>\r\nReply-To: {$replyTo}\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"{$b}\"\r\n";
    $body = "--{$b}\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($txt))
          . "--{$b}\r\nContent-Type: text/html; charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
          . "--{$b}--\r\n";
    return (bool)@mail($to, $enc(sprintf($t['subj'], $factory)), $body, $hd, '-f' . $cfg['mail_from']);
}
