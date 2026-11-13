<?php
// ============================================================
//  Регистрация и личный кабинет на сайте itcpartner.ru (3.51.0)
//
//  Вход по почте и паролю — свой, с магазином не связан.
//  Пользователи хранятся в общей базе магазина (таблица itc_site_users),
//  туда же сайт уже ходит за страницами фабрик (inc/supdb.php).
//  Каждый зарегистрированный сразу попадает в базу клиентов Экосистемы
//  (crm_clients + crm_contacts) с пометкой «с сайта».
//  Босс видит регистрации в магазине: Управление → «Регистрации на сайте ИТК».
// ============================================================

const ACC_COOKIE = 'itc_acc';      // вход (случайный ключ, сам пароль нигде не хранится)
const ACC_CSRF   = 'itc_fk';       // защита форм от подделки
const ACC_DAYS   = 60;             // сколько дней помнить вход

// Сферы деятельности — по разделам сайта
function acc_spheres() {
    return [
        'auto'   => 'Автотовары',
        'dom'    => 'Дом и стройка',
        'santeh' => 'Сантехника',
        'horeca' => 'HoReCa (отели, рестораны, кафе)',
        'mebel'  => 'Мебель и фурнитура',
        'food'   => 'Пищевое оборудование',
        'prom'   => 'Промышленность и сельское хозяйство',
        'energy' => 'Энергетика',
        'odezhda' => 'Одежда, обувь и текстиль',
        'life'   => 'Красота и уход',
        'sport'  => 'Спорт и отдых',
        'office' => 'Канцелярия и упаковка',
        'other'  => 'Другое',
    ];
}
function acc_sphere_names($keys) {
    $all = acc_spheres(); $out = [];
    foreach ((array)$keys as $k) if (isset($all[$k])) $out[] = $all[$k];
    return $out;
}

function acc_db() {
    $db = sup_site_db();
    if ($db) acc_tables($db);
    return $db;
}
function acc_tables($db) {
    static $done = false;
    if ($done) return; $done = true;
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS itc_site_users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            kind VARCHAR(4) NOT NULL DEFAULT 'fiz',
            email VARCHAR(190) NOT NULL,
            pass VARCHAR(255) NOT NULL DEFAULT '',
            last_name VARCHAR(100) NOT NULL DEFAULT '',
            first_name VARCHAR(100) NOT NULL DEFAULT '',
            middle_name VARCHAR(100) NOT NULL DEFAULT '',
            phone VARCHAR(40) NOT NULL DEFAULT '',
            inn VARCHAR(12) NOT NULL DEFAULT '',
            company VARCHAR(255) NOT NULL DEFAULT '',
            ogrn VARCHAR(15) NOT NULL DEFAULT '',
            kpp VARCHAR(9) NOT NULL DEFAULT '',
            addr VARCHAR(500) NOT NULL DEFAULT '',
            contact VARCHAR(255) NOT NULL DEFAULT '',
            post VARCHAR(255) NOT NULL DEFAULT '',
            spheres VARCHAR(255) NOT NULL DEFAULT '',
            mail_ok TINYINT(1) NOT NULL DEFAULT 0,
            mail_ok_at DATETIME NULL,
            pd_ok_at DATETIME NULL,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            verified TINYINT(1) NOT NULL DEFAULT 0,
            vtoken VARCHAR(64) NOT NULL DEFAULT '',
            rtoken VARCHAR(64) NOT NULL DEFAULT '',
            rtoken_exp DATETIME NULL,
            fails INT NOT NULL DEFAULT 0,
            fail_at DATETIME NULL,
            crm_client VARCHAR(20) NOT NULL DEFAULT '',
            crm_contact VARCHAR(20) NOT NULL DEFAULT '',
            page VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NULL,
            last_login DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY u_email (email),
            KEY k_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS itc_site_sess (
            token CHAR(64) NOT NULL,
            uid INT UNSIGNED NOT NULL,
            expires DATETIME NOT NULL,
            PRIMARY KEY (token),
            KEY k_uid (uid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}
}

function acc_secure() { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'); }
function acc_cookie($name, $val, $days) {
    setcookie($name, $val, ['expires' => $days ? time() + $days * 86400 : time() - 3600, 'path' => '/',
        'secure' => acc_secure(), 'httponly' => true, 'samesite' => 'Lax']);
}

/* Кто вошёл (или null). Базу трогаем, только если есть кука входа */
function acc_me() {
    static $me = false;
    if ($me !== false) return $me;
    $me = null;
    $t = (string)($_COOKIE[ACC_COOKIE] ?? '');
    if (!preg_match('~^[a-f0-9]{64}$~', $t)) return $me;
    $db = acc_db(); if (!$db) return $me;
    try {
        $q = $db->prepare('SELECT u.* FROM itc_site_sess s JOIN itc_site_users u ON u.id = s.uid WHERE s.token = ? AND s.expires > NOW()');
        $q->execute([hash('sha256', $t)]);
        $me = $q->fetch() ?: null;
    } catch (Throwable $e) {}
    return $me;
}
function acc_login($uid) {
    $db = acc_db(); $t = bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO itc_site_sess (token, uid, expires) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ' . ACC_DAYS . ' DAY))')->execute([hash('sha256', $t), $uid]);
    $db->prepare('UPDATE itc_site_users SET last_login = NOW(), fails = 0 WHERE id = ?')->execute([$uid]);
    $db->exec('DELETE FROM itc_site_sess WHERE expires < NOW()');
    acc_cookie(ACC_COOKIE, $t, ACC_DAYS);
}
function acc_logout() {
    $t = (string)($_COOKIE[ACC_COOKIE] ?? '');
    if ($t !== '' && ($db = acc_db())) { try { $db->prepare('DELETE FROM itc_site_sess WHERE token = ?')->execute([hash('sha256', $t)]); } catch (Throwable $e) {} }
    acc_cookie(ACC_COOKIE, '', 0);
}

/* Защита форм: ключ в куке и тот же ключ в скрытом поле */
function acc_fk() {
    static $k = null;
    if ($k !== null) return $k;
    $k = (string)($_COOKIE[ACC_CSRF] ?? '');
    if (!preg_match('~^[a-f0-9]{32}$~', $k)) { $k = bin2hex(random_bytes(16)); acc_cookie(ACC_CSRF, $k, 1); }
    return $k;
}
function acc_fk_ok() {
    $c = (string)($_COOKIE[ACC_CSRF] ?? ''); $p = (string)($_POST['fk'] ?? ($_GET['fk'] ?? ''));
    return $c !== '' && hash_equals($c, $p);
}

function acc_clean($v, $max = 255) { return mb_substr(trim(preg_replace('~\s+~u', ' ', strip_tags((string)$v))), 0, $max); }
function acc_phone_ok($p) { return strlen(preg_replace('~\D~', '', $p)) >= 10; }

/* Проверка ИНН по контрольным цифрам (10 — организация, 12 — ИП) */
function acc_inn_ok($inn) {
    if (!preg_match('~^\d{10}(\d{2})?$~', $inn)) return false;
    $d = array_map('intval', str_split($inn));
    $sum = function ($w) use ($d) { $s = 0; foreach ($w as $i => $k) $s += $k * $d[$i]; return $s % 11 % 10; };
    if (strlen($inn) === 10) return $sum([2, 4, 10, 3, 5, 9, 4, 6, 8]) === $d[9];
    return $sum([7, 2, 4, 10, 3, 5, 9, 4, 6, 8]) === $d[10] && $sum([3, 7, 2, 4, 10, 3, 5, 9, 4, 6, 8]) === $d[11];
}

/* Реквизиты по ИНН — через DaData, ключ тот же, что в магазине (таблица settings) */
function acc_inn_lookup($inn) {
    $db = acc_db(); if (!$db) return ['ok' => false, 'error' => 'Сервис временно недоступен — заполните вручную'];
    try { $q = $db->prepare("SELECT v FROM settings WHERE k = 'dadata_token'"); $q->execute(); $tok = trim((string)$q->fetchColumn()); }
    catch (Throwable $e) { $tok = ''; }
    if ($tok === '') return ['ok' => false, 'error' => 'Поиск по ИНН временно недоступен — заполните вручную'];
    $ch = curl_init('https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 8,
        CURLOPT_POSTFIELDS => json_encode(['query' => $inn, 'count' => 1]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Token ' . $tok]]);
    $r = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $j = json_decode($r, true);
    if ($code !== 200 || !is_array($j)) return ['ok' => false, 'error' => 'Поиск по ИНН не ответил — заполните вручную'];
    $x = $j['suggestions'][0] ?? null;
    if (!$x) return ['ok' => false, 'error' => 'По этому ИНН ничего не найдено. Проверьте цифры'];
    $d = $x['data'] ?? [];
    $st = (string)($d['state']['status'] ?? '');
    return ['ok' => true, 'company' => (string)($x['value'] ?? ''), 'ogrn' => (string)($d['ogrn'] ?? ''), 'kpp' => (string)($d['kpp'] ?? ''),
            'addr' => (string)($d['address']['unrestricted_value'] ?? ($d['address']['value'] ?? '')),
            'ip' => ($d['type'] ?? '') === 'INDIVIDUAL', 'closed' => in_array($st, ['LIQUIDATED', 'LIQUIDATING', 'BANKRUPT'], true)];
}

/* Ограничение частоты (поиск ИНН, письма) — по адресу посетителя, в файле */
function acc_rate($key, $max, $sec) {
    $f = ROOT . '/data/acc-rate.json';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '-'; $now = time();
    $h = @fopen($f, 'c+'); if (!$h) return true;
    flock($h, LOCK_EX);
    $j = json_decode((string)stream_get_contents($h), true); if (!is_array($j)) $j = [];
    foreach ($j as $k => $v) if ($v[1] < $now) unset($j[$k]);
    $k = $key . '|' . $ip;
    $row = $j[$k] ?? [0, $now + $sec];
    $ok = $row[0] < $max;
    if ($ok) { $row[0]++; $j[$k] = $row; }
    ftruncate($h, 0); rewind($h); fwrite($h, json_encode($j)); fflush($h); flock($h, LOCK_UN); fclose($h);
    return $ok;
}

/* Письмо с сайта (оформленное, в фирменных цветах) */
function acc_mail($to, $subj, $title, $html) {
    global $cfg;
    $enc = function ($t) { return '=?UTF-8?B?' . base64_encode($t) . '?='; };
    $body = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;color:#26262b">'
        . '<div style="padding:16px 0;border-bottom:3px solid #128c04"><img src="https://itcpartner.ru/img/logo.png" alt="ИТК" height="32" style="vertical-align:middle"> '
        . '<b style="font-size:17px;color:#0b247d;vertical-align:middle;margin-left:8px">' . e($title) . '</b></div>'
        . '<div style="padding:18px 0;font-size:15px;line-height:1.55">' . $html . '</div>'
        . '<div style="border-top:1px solid #e3e5ea;padding-top:12px;font-size:12px;color:#6c7177">ООО «Интернациональная Торговая Компания» · <a href="https://itcpartner.ru" style="color:#0b247d">itcpartner.ru</a> · '
        . e($cfg['phone1'] ?? '') . '</div></div>';
    $hd = "From: ИТК <{$cfg['mail_from']}>\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=utf-8\r\n";
    return (bool)@mail($to, $enc($subj), $body, $hd, '-f' . $cfg['mail_from']);
}
function acc_btn($url, $text) {
    return '<p style="margin:22px 0"><a href="' . e($url) . '" style="background:#128c04;color:#fff;text-decoration:none;padding:13px 26px;border-radius:12px;font-weight:bold;display:inline-block">' . e($text) . '</a></p>'
        . '<p style="font-size:12px;color:#6c7177">Если кнопка не нажимается, скопируйте адрес в браузер:<br>' . e($url) . '</p>';
}
function acc_send_verify($u) {
    $db = acc_db(); $t = bin2hex(random_bytes(24));
    $db->prepare('UPDATE itc_site_users SET vtoken = ? WHERE id = ?')->execute([$t, $u['id']]);
    $url = 'https://itcpartner.ru/podtverzhdenie?t=' . $t;
    return acc_mail($u['email'], 'Подтвердите почту на сайте ИТК', 'Подтверждение почты',
        '<p>Здравствуйте' . ($u['first_name'] !== '' ? ', ' . e($u['first_name']) : '') . '!</p><p>Вы зарегистрировались на сайте ИТК. Подтвердите, пожалуйста, адрес почты:</p>'
        . acc_btn($url, 'Подтвердить почту') . '<p style="font-size:13px;color:#6c7177">Если вы не регистрировались, просто удалите это письмо.</p>');
}

/* Уведомление ИТК о новой регистрации: на почту для заявок и в Телеграм, если он настроен */
function acc_notify($u) {
    global $cfg;
    $kind = $u['kind'] === 'yur' ? 'Юрлицо / ИП' : 'Физлицо';
    $who = $u['kind'] === 'yur' ? ($u['company'] . ', ИНН ' . $u['inn'] . "\nКонтактное лицо: " . $u['contact'] . ($u['post'] !== '' ? ', ' . $u['post'] : ''))
                                : trim($u['last_name'] . ' ' . $u['first_name'] . ' ' . $u['middle_name']);
    $text = "Новая регистрация на сайте\n" . $kind . ': ' . $who . "\nТелефон: " . $u['phone'] . "\nПочта: " . $u['email']
          . "\nСферы: " . implode(', ', acc_sphere_names(explode(',', $u['spheres']))) . "\nРассылка: " . ($u['mail_ok'] ? 'согласие есть' : 'нет')
          . "\nВремя: " . date('d.m.Y H:i');
    if (!empty($cfg['mail_to'])) {
        $enc = function ($t) { return '=?UTF-8?B?' . base64_encode($t) . '?='; };
        @mail($cfg['mail_to'], $enc('Регистрация на сайте: ' . ($u['kind'] === 'yur' ? $u['company'] : $who)), $text,
            "From: {$cfg['mail_from']}\r\nReply-To: {$u['email']}\r\nContent-Type: text/plain; charset=utf-8\r\n");
    }
    if (!empty($cfg['tg_bot_token']) && !empty($cfg['tg_chat_id'])) {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query(['chat_id' => $cfg['tg_chat_id'], 'text' => mb_substr($text, 0, 4000)]), 'timeout' => 8]]);
        @file_get_contents('https://api.telegram.org/bot' . $cfg['tg_bot_token'] . '/sendMessage', false, $ctx);
    }
}

/* В базу клиентов Экосистемы: клиент + контактное лицо. Тот же ИНН уже есть — добавляем контакт к нему */
function acc_crm_sync($u) {
    $db = acc_db(); if (!$db) return;
    // таблицы базы клиентов — те же, что заводит Экосистема (api.php, crm_tables), если их ещё нет
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS crm_clients (id VARCHAR(20) NOT NULL, alias VARCHAR(255) NOT NULL DEFAULT '', company VARCHAR(255) NOT NULL DEFAULT '',
            inn VARCHAR(20) NOT NULL DEFAULT '', kpp VARCHAR(20) NOT NULL DEFAULT '', ogrn VARCHAR(20) NOT NULL DEFAULT '', addr VARCHAR(255) NOT NULL DEFAULT '',
            bank VARCHAR(255) NOT NULL DEFAULT '', bik VARCHAR(20) NOT NULL DEFAULT '', acct VARCHAR(34) NOT NULL DEFAULT '', corr VARCHAR(34) NOT NULL DEFAULT '',
            site VARCHAR(255) NOT NULL DEFAULT '', note TEXT, created_at DATETIME NULL, created_by VARCHAR(20) NOT NULL DEFAULT '',
            PRIMARY KEY (id), KEY k_company (company)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS crm_contacts (id VARCHAR(20) NOT NULL, client_id VARCHAR(20) NOT NULL, name VARCHAR(255) NOT NULL,
            post VARCHAR(255) NOT NULL DEFAULT '', area VARCHAR(255) NOT NULL DEFAULT '', phone VARCHAR(40) NOT NULL DEFAULT '', email VARCHAR(255) NOT NULL DEFAULT '',
            note TEXT, PRIMARY KEY (id), KEY k_client (client_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) { return; }
    $fio = trim($u['last_name'] . ' ' . $u['first_name'] . ' ' . $u['middle_name']);
    $yur = $u['kind'] === 'yur';
    $sph = implode(', ', acc_sphere_names(explode(',', $u['spheres'])));
    $note = 'С сайта itcpartner.ru: регистрация ' . date('d.m.Y', strtotime($u['created_at'] ?: 'now')) . ($yur ? '' : ' (физлицо)')
          . '. Сферы: ' . ($sph ?: '—') . '. Рассылка: ' . ($u['mail_ok'] ? 'согласие есть' : 'нет') . '.';
    $cname = $yur ? $u['contact'] : $fio;
    $cid = (string)$u['crm_client']; $kid = (string)$u['crm_contact'];
    try {
        if ($cid !== '' && !$db->query('SELECT 1 FROM crm_clients WHERE id = ' . $db->quote($cid))->fetchColumn()) { $cid = ''; $kid = ''; }
        if ($cid === '' && $yur && $u['inn'] !== '') {
            $q = $db->prepare('SELECT id FROM crm_clients WHERE inn = ? LIMIT 1'); $q->execute([$u['inn']]); $cid = (string)$q->fetchColumn();
        }
        if ($cid === '') {
            $cid = 'cl' . substr(bin2hex(random_bytes(8)), 0, 12);
            $db->prepare('INSERT INTO crm_clients (id, alias, company, inn, kpp, ogrn, addr, bank, bik, acct, corr, site, note, created_at, created_by)
                          VALUES (?, ?, ?, ?, ?, ?, ?, \'\', \'\', \'\', \'\', \'\', ?, NOW(), ?)')
               ->execute([$cid, $yur ? '' : $fio . ' (физлицо)', $yur ? $u['company'] : '', $yur ? $u['inn'] : '', $yur ? $u['kpp'] : '', $yur ? $u['ogrn'] : '',
                          $yur ? mb_substr($u['addr'], 0, 255) : '', $note, 'site']);
        } else {
            // уже есть: обновляем пометку «с сайта», реквизиты не трогаем, если их вписал сотрудник
            $q = $db->prepare('SELECT note, company, kpp, ogrn, addr FROM crm_clients WHERE id = ?'); $q->execute([$cid]); $c = $q->fetch() ?: [];
            $old = (string)($c['note'] ?? '');
            $old = trim(preg_replace('~С сайта itcpartner\.ru:[^\n]*~u', '', $old));
            $db->prepare('UPDATE crm_clients SET note = ?, company = IF(company = \'\', ?, company), kpp = IF(kpp = \'\', ?, kpp), ogrn = IF(ogrn = \'\', ?, ogrn), addr = IF(addr = \'\', ?, addr) WHERE id = ?')
               ->execute([trim($note . "\n" . $old), $yur ? $u['company'] : '', $yur ? $u['kpp'] : '', $yur ? $u['ogrn'] : '', $yur ? mb_substr($u['addr'], 0, 255) : '', $cid]);
        }
        $cnote = 'Регистрация на сайте itcpartner.ru' . ($u['mail_ok'] ? ', согласие на рассылку' : '');
        if ($kid !== '' && $db->query('SELECT 1 FROM crm_contacts WHERE id = ' . $db->quote($kid))->fetchColumn()) {
            $db->prepare('UPDATE crm_contacts SET client_id = ?, name = ?, post = ?, area = ?, phone = ?, email = ?, note = ? WHERE id = ?')
               ->execute([$cid, $cname ?: $u['email'], $yur ? $u['post'] : '', $sph, $u['phone'], $u['email'], $cnote, $kid]);
        } else {
            $kid = 'ct' . substr(bin2hex(random_bytes(8)), 0, 12);
            $db->prepare('INSERT INTO crm_contacts (id, client_id, name, post, area, phone, email, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
               ->execute([$kid, $cid, $cname ?: $u['email'], $yur ? $u['post'] : '', $sph, $u['phone'], $u['email'], $cnote]);
        }
        $db->prepare('UPDATE itc_site_users SET crm_client = ?, crm_contact = ? WHERE id = ?')->execute([$cid, $kid, $u['id']]);
    } catch (Throwable $e) {}
}

function acc_get($id) {
    $q = acc_db()->prepare('SELECT * FROM itc_site_users WHERE id = ?'); $q->execute([$id]); return $q->fetch() ?: null;
}

/* Поля анкеты из формы (регистрация и правка в кабинете). Возвращает [данные, ошибка] */
function acc_form_fields($p, $kindFixed = null) {
    $kind = $kindFixed ?? (($p['kind'] ?? '') === 'yur' ? 'yur' : 'fiz');
    $f = ['kind' => $kind, 'email' => mb_strtolower(acc_clean($p['email'] ?? '', 190)), 'phone' => acc_clean($p['phone'] ?? '', 40),
          'last_name' => acc_clean($p['last_name'] ?? '', 100), 'first_name' => acc_clean($p['first_name'] ?? '', 100), 'middle_name' => acc_clean($p['middle_name'] ?? '', 100),
          'inn' => preg_replace('~\D~', '', (string)($p['inn'] ?? '')), 'company' => acc_clean($p['company'] ?? ''), 'ogrn' => preg_replace('~\D~', '', (string)($p['ogrn'] ?? '')),
          'kpp' => preg_replace('~\D~', '', (string)($p['kpp'] ?? '')), 'addr' => acc_clean($p['addr'] ?? '', 500),
          'contact' => acc_clean($p['contact'] ?? ''), 'post' => acc_clean($p['post'] ?? ''),
          'spheres' => implode(',', array_values(array_intersect(array_keys(acc_spheres()), (array)($p['spheres'] ?? [])))),
          'mail_ok' => !empty($p['mail_ok']) ? 1 : 0];
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) return [$f, 'Проверьте адрес почты'];
    if (!acc_phone_ok($f['phone'])) return [$f, 'Проверьте номер телефона'];
    if ($kind === 'fiz') {
        if ($f['last_name'] === '' || $f['first_name'] === '') return [$f, 'Укажите фамилию и имя'];
        $f['inn'] = $f['company'] = $f['ogrn'] = $f['kpp'] = $f['addr'] = $f['contact'] = $f['post'] = '';
    } else {
        if (!acc_inn_ok($f['inn'])) return [$f, 'Проверьте ИНН: 10 цифр у организации, 12 — у ИП'];
        if ($f['company'] === '') return [$f, 'Укажите название компании (нажмите «Найти по ИНН» или впишите вручную)'];
        if ($f['contact'] === '') return [$f, 'Укажите контактное лицо'];
        if ($f['last_name'] === '' && $f['first_name'] === '') { $parts = explode(' ', $f['contact']); $f['last_name'] = $parts[0] ?? ''; $f['first_name'] = $parts[1] ?? ''; $f['middle_name'] = $parts[2] ?? ''; }
    }
    if ($f['spheres'] === '') return [$f, 'Выберите хотя бы одну сферу деятельности'];
    return [$f, ''];
}
