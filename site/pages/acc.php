<?php
// Регистрация, вход, кабинет, подтверждение почты, новый пароль (3.51.0). Перед подключением задан $accRoute.
require_once ROOT . '/inc/account.php';
header('Cache-Control: no-store');
$db = acc_db();
$me = acc_me();
$err = ''; $ok = '';
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
$go = function ($to) { header('Location: ' . $to, true, 303); exit; };

// Поиск реквизитов по ИНН (для формы)
if ($accRoute === '/acc-inn') {
    header('Content-Type: application/json; charset=utf-8');
    $inn = preg_replace('~\D~', '', (string)($_GET['inn'] ?? ''));
    if (!acc_fk_ok()) { echo json_encode(['ok' => false, 'error' => 'Обновите страницу и попробуйте ещё раз'], JSON_UNESCAPED_UNICODE); exit; }
    if (!acc_inn_ok($inn)) { echo json_encode(['ok' => false, 'error' => 'Проверьте ИНН: 10 цифр у организации, 12 — у ИП'], JSON_UNESCAPED_UNICODE); exit; }
    if (!acc_rate('inn', 30, 3600)) { echo json_encode(['ok' => false, 'error' => 'Слишком много запросов — заполните вручную'], JSON_UNESCAPED_UNICODE); exit; }
    echo json_encode(acc_inn_lookup($inn), JSON_UNESCAPED_UNICODE); exit;
}
if ($accRoute === '/vyhod') { if (acc_fk_ok()) acc_logout(); $go('/'); }

if (!$db) { $err = 'Регистрация временно недоступна. Попробуйте позже или позвоните нам.'; }

// ---------- Подтверждение почты по ссылке из письма ----------
if ($db && $accRoute === '/podtverzhdenie') {
    $t = (string)($_GET['t'] ?? '');
    $u = null;
    if (preg_match('~^[a-f0-9]{48}$~', $t)) { $q = $db->prepare('SELECT * FROM itc_site_users WHERE vtoken = ?'); $q->execute([$t]); $u = $q->fetch(); }
    if ($u) { $db->prepare("UPDATE itc_site_users SET verified = 1, vtoken = '' WHERE id = ?")->execute([$u['id']]); $ok = 'Почта подтверждена. Спасибо!'; }
    else $err = 'Ссылка устарела или уже использована. Войдите в кабинет — там можно отправить письмо ещё раз.';
}

// ---------- Регистрация ----------
$F = ['kind' => 'yur', 'spheres' => '', 'mail_ok' => 0];
if ($db && $accRoute === '/registraciya') {
    if ($me) $go('/kabinet');
    if ($post) {
        [$F, $err] = acc_form_fields($_POST);
        $pass = (string)($_POST['pass'] ?? '');
        if (!empty($_POST['website'])) $go('/');   // бот
        if (!$err && !acc_fk_ok()) $err = 'Обновите страницу и отправьте форму ещё раз';
        if (!$err && mb_strlen($pass) < 8) $err = 'Пароль — не короче 8 знаков';
        if (!$err && $pass !== (string)($_POST['pass2'] ?? '')) $err = 'Пароли не совпадают';
        if (!$err && empty($_POST['pd_ok'])) $err = 'Нужно согласие на обработку персональных данных';
        if (!$err && !acc_rate('reg', 10, 3600)) $err = 'Слишком много регистраций с вашего адреса — попробуйте через час';
        if (!$err) {
            $q = $db->prepare('SELECT id FROM itc_site_users WHERE email = ?'); $q->execute([$F['email']]);
            if ($q->fetchColumn()) $err = 'С этой почтой уже есть регистрация. Войдите или восстановите пароль.';
        }
        if (!$err) {
            $cols = ['kind', 'email', 'phone', 'last_name', 'first_name', 'middle_name', 'inn', 'company', 'ogrn', 'kpp', 'addr', 'contact', 'post', 'spheres', 'mail_ok'];
            $vals = array_map(function ($c) use ($F) { return $F[$c]; }, $cols);
            $db->prepare('INSERT INTO itc_site_users (' . implode(',', $cols) . ', pass, pd_ok_at, mail_ok_at, ip, page, created_at) VALUES ('
                         . implode(',', array_fill(0, count($cols), '?')) . ', ?, NOW(), ' . ($F['mail_ok'] ? 'NOW()' : 'NULL') . ', ?, ?, NOW())')
               ->execute(array_merge($vals, [password_hash($pass, PASSWORD_DEFAULT), $_SERVER['REMOTE_ADDR'] ?? '', acc_clean($_POST['from'] ?? '')]));
            $u = acc_get((int)$db->lastInsertId());
            acc_crm_sync($u);
            acc_send_verify($u);
            acc_notify($u);
            acc_login($u['id']);
            $go('/kabinet?new=1');
        }
    }
}

// ---------- Вход ----------
if ($db && $accRoute === '/vhod') {
    if ($me) $go('/kabinet');
    if ($post) {
        $email = mb_strtolower(acc_clean($_POST['email'] ?? '', 190)); $pass = (string)($_POST['pass'] ?? '');
        $q = $db->prepare('SELECT * FROM itc_site_users WHERE email = ?'); $q->execute([$email]); $u = $q->fetch();
        if (!acc_fk_ok()) $err = 'Обновите страницу и попробуйте ещё раз';
        elseif ($u && (int)$u['fails'] >= 8 && strtotime((string)$u['fail_at']) > time() - 900) $err = 'Много неудачных попыток. Подождите 15 минут или восстановите пароль.';
        elseif ($u && password_verify($pass, $u['pass'])) { acc_login($u['id']); $go('/kabinet'); }
        else {
            if ($u) $db->prepare('UPDATE itc_site_users SET fails = IF(fail_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE), 1, fails + 1), fail_at = NOW() WHERE id = ?')->execute([$u['id']]);
            $err = 'Неверная почта или пароль';
        }
    }
}

// ---------- Забыли пароль ----------
if ($db && $accRoute === '/zabyli-parol' && $post) {
    $email = mb_strtolower(acc_clean($_POST['email'] ?? '', 190));
    if (!acc_fk_ok()) $err = 'Обновите страницу и попробуйте ещё раз';
    elseif (!acc_rate('reset', 5, 3600)) $err = 'Слишком много запросов — попробуйте через час';
    else {
        $q = $db->prepare('SELECT * FROM itc_site_users WHERE email = ?'); $q->execute([$email]);
        if ($u = $q->fetch()) {
            $t = bin2hex(random_bytes(24));
            $db->prepare('UPDATE itc_site_users SET rtoken = ?, rtoken_exp = DATE_ADD(NOW(), INTERVAL 2 HOUR) WHERE id = ?')->execute([$t, $u['id']]);
            acc_mail($u['email'], 'Новый пароль для сайта ИТК', 'Новый пароль',
                '<p>Кто-то (возможно, вы) попросил задать новый пароль для входа на сайт ИТК.</p>' . acc_btn('https://itcpartner.ru/novyj-parol?t=' . $t, 'Задать новый пароль')
                . '<p style="font-size:13px;color:#6c7177">Ссылка действует 2 часа. Если вы ничего не просили, просто удалите это письмо — пароль останется прежним.</p>');
        }
        $ok = 'Если такая почта зарегистрирована, на неё ушло письмо со ссылкой. Проверьте также папку «Спам».';
    }
}

// ---------- Новый пароль по ссылке ----------
$resetUser = null;
if ($db && $accRoute === '/novyj-parol') {
    $t = (string)($_GET['t'] ?? '');
    if (preg_match('~^[a-f0-9]{48}$~', $t)) { $q = $db->prepare('SELECT * FROM itc_site_users WHERE rtoken = ? AND rtoken_exp > NOW()'); $q->execute([$t]); $resetUser = $q->fetch() ?: null; }
    if (!$resetUser) $err = 'Ссылка устарела. Запросите новую.';
    elseif ($post) {
        $pass = (string)($_POST['pass'] ?? '');
        if (!acc_fk_ok()) $err = 'Обновите страницу и попробуйте ещё раз';
        elseif (mb_strlen($pass) < 8) $err = 'Пароль — не короче 8 знаков';
        elseif ($pass !== (string)($_POST['pass2'] ?? '')) $err = 'Пароли не совпадают';
        else {
            // переход по ссылке из письма подтверждает и саму почту
            $db->prepare("UPDATE itc_site_users SET pass = ?, rtoken = '', rtoken_exp = NULL, verified = 1, fails = 0 WHERE id = ?")->execute([password_hash($pass, PASSWORD_DEFAULT), $resetUser['id']]);
            $db->prepare('DELETE FROM itc_site_sess WHERE uid = ?')->execute([$resetUser['id']]);
            acc_login($resetUser['id']); $go('/kabinet?pass=1');
        }
    }
}

// ---------- Кабинет ----------
if ($db && $accRoute === '/kabinet') {
    if (!$me) $go('/vhod');
    if (!empty($_GET['new'])) $ok = 'Готово, вы зарегистрированы! На почту ' . $me['email'] . ' ушло письмо — подтвердите адрес, чтобы получать рассылку.';
    if (!empty($_GET['pass'])) $ok = 'Новый пароль сохранён.';
    if ($post && acc_fk_ok()) {
        $act = (string)($_POST['act'] ?? '');
        if ($act === 'resend') {
            if (acc_rate('verify', 3, 3600)) { acc_send_verify($me); $ok = 'Письмо отправлено ещё раз. Проверьте также папку «Спам».'; }
            else $err = 'Письмо уже отправляли несколько раз — проверьте папку «Спам» или попробуйте через час.';
        } elseif ($act === 'save') {
            $p = $_POST; $p['email'] = $me['email'];
            if ($me['kind'] === 'yur') $p['inn'] = $me['inn'];
            [$F2, $err] = acc_form_fields($p, $me['kind']);
            if (!$err) {
                $cols = ['phone', 'last_name', 'first_name', 'middle_name', 'company', 'ogrn', 'kpp', 'addr', 'contact', 'post', 'spheres', 'mail_ok'];
                $set = implode(', ', array_map(function ($c) { return $c . ' = ?'; }, $cols));
                $mailAt = $F2['mail_ok'] && !$me['mail_ok'] ? ', mail_ok_at = NOW()' : ($F2['mail_ok'] ? '' : ', mail_ok_at = NULL');
                $db->prepare("UPDATE itc_site_users SET $set $mailAt WHERE id = ?")->execute(array_merge(array_map(function ($c) use ($F2) { return $F2[$c]; }, $cols), [$me['id']]));
                $me = acc_get($me['id']); acc_crm_sync($me);
                $ok = 'Сохранено.';
            }
        } elseif ($act === 'pass') {
            $old = (string)($_POST['old'] ?? ''); $pass = (string)($_POST['pass'] ?? '');
            if (!password_verify($old, $me['pass'])) $err = 'Текущий пароль указан неверно';
            elseif (mb_strlen($pass) < 8) $err = 'Новый пароль — не короче 8 знаков';
            elseif ($pass !== (string)($_POST['pass2'] ?? '')) $err = 'Пароли не совпадают';
            else { $db->prepare('UPDATE itc_site_users SET pass = ? WHERE id = ?')->execute([password_hash($pass, PASSWORD_DEFAULT), $me['id']]); $ok = 'Пароль изменён.'; }
        }
    }
    $F = $me;
}

// ---------- Вывод ----------
$titles = ['/registraciya' => 'Регистрация', '/vhod' => 'Вход', '/kabinet' => 'Личный кабинет', '/podtverzhdenie' => 'Подтверждение почты',
           '/zabyli-parol' => 'Восстановление пароля', '/novyj-parol' => 'Новый пароль'];
$title = $titles[$accRoute] . ' — ИТК'; $crumb = $titles[$accRoute]; $bodyClass = 'inner'; $noindex = true; $noAlternates = true;
$fk = acc_fk();
$v = $GLOBALS['v'];
$val = function ($k) use (&$F) { return e((string)($F[$k] ?? '')); };
$sel = explode(',', (string)($F['spheres'] ?? ''));
$spheresHtml = function () use ($sel) {
    $h = '<div class="acc-sph">';
    foreach (acc_spheres() as $k => $n) $h .= '<label class="acc-chip"><input type="checkbox" name="spheres[]" value="' . e($k) . '"' . (in_array($k, $sel, true) ? ' checked' : '') . '><span>' . e($n) . '</span></label>';
    return $h . '</div>';
};
$ic = [
    'person' => 'M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z',
    'biz'    => 'M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z',
    'mail'   => 'M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z',
    'check'  => 'M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z',
    'search' => 'M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z',
    'logout' => 'M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z',
    'bell'   => 'M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z',
    'bolt'   => 'M7 2v11h3v9l7-12h-4l4-8z',
    'star'   => 'M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z',
];
$svg = function ($k) use ($ic) { return '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="' . $ic[$k] . '"/></svg>'; };
require ROOT . '/inc/head.php';
?>
<main class="page">
<?php require ROOT . '/inc/crumbs.php'; ?>
<section class="sec acc">
  <div class="wrap">
<?php if ($err): ?><div class="acc-msg acc-msg--err"><?= e($err) ?></div><?php endif; ?>
<?php if ($ok): ?><div class="acc-msg acc-msg--ok"><?= $svg('check') ?><span><?= e($ok) ?></span></div><?php endif; ?>

<?php if ($accRoute === '/registraciya' && $db): ?>
    <div class="acc-grid">
      <div class="acc-why">
        <h1 class="acc-h">Зарегистрируйтесь — получите больше возможностей</h1>
        <p class="acc-lead">Это бесплатно и займёт минуту.</p>
        <ul class="acc-list">
          <li><?= $svg('bell') ?><span>Первыми узнавайте о новых фабриках и товарах в вашей сфере — письмом на почту.</span></li>
          <li><?= $svg('bolt') ?><span>Запросы цен и заявки — в один клик: имя, телефон и почта уже заполнены.</span></li>
          <li><?= $svg('star') ?><span>Менеджер ИТК видит ваши интересы и предлагает подходящие фабрики.</span></li>
        </ul>
        <p class="acc-small">Уже есть регистрация? <a href="/vhod">Войти</a></p>
      </div>
      <form class="acc-card" method="post" action="/registraciya" id="accReg" novalidate>
        <input type="hidden" name="fk" value="<?= e($fk) ?>">
        <input type="hidden" name="from" value="<?= e(acc_clean($_GET['from'] ?? ($_POST['from'] ?? ''), 255)) ?>">
        <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
        <div class="acc-kind">
          <label><input type="radio" name="kind" value="yur"<?= $F['kind'] === 'yur' ? ' checked' : '' ?>><span><?= $svg('biz') ?>Юрлицо или ИП</span></label>
          <label><input type="radio" name="kind" value="fiz"<?= $F['kind'] === 'fiz' ? ' checked' : '' ?>><span><?= $svg('person') ?>Физическое лицо</span></label>
        </div>
        <div class="acc-yur">
          <label class="acc-f"><span>ИНН компании или ИП</span>
            <div class="acc-inn"><input type="text" name="inn" inputmode="numeric" maxlength="12" value="<?= $val('inn') ?>" placeholder="10 или 12 цифр"><button type="button" class="acc-btn2" id="accInnBtn"><?= $svg('search') ?>Найти по ИНН</button></div>
          </label>
          <div class="acc-innmsg" id="accInnMsg"></div>
          <label class="acc-f"><span>Название компании</span><input type="text" name="company" value="<?= $val('company') ?>"></label>
          <div class="acc-row">
            <label class="acc-f"><span>ОГРН или ОГРНИП</span><input type="text" name="ogrn" inputmode="numeric" maxlength="15" value="<?= $val('ogrn') ?>"></label>
            <label class="acc-f"><span>КПП</span><input type="text" name="kpp" inputmode="numeric" maxlength="9" value="<?= $val('kpp') ?>"></label>
          </div>
          <label class="acc-f"><span>Юридический адрес</span><input type="text" name="addr" value="<?= $val('addr') ?>"></label>
          <div class="acc-row">
            <label class="acc-f"><span>Контактное лицо (ФИО)</span><input type="text" name="contact" value="<?= $val('contact') ?>" autocomplete="name"></label>
            <label class="acc-f"><span>Должность</span><input type="text" name="post" value="<?= $val('post') ?>" autocomplete="organization-title"></label>
          </div>
        </div>
        <div class="acc-fiz">
          <label class="acc-f"><span>Фамилия</span><input type="text" name="last_name" value="<?= $val('last_name') ?>" autocomplete="family-name"></label>
          <div class="acc-row">
            <label class="acc-f"><span>Имя</span><input type="text" name="first_name" value="<?= $val('first_name') ?>" autocomplete="given-name"></label>
            <label class="acc-f"><span>Отчество</span><input type="text" name="middle_name" value="<?= $val('middle_name') ?>" autocomplete="additional-name"></label>
          </div>
        </div>
        <div class="acc-row">
          <label class="acc-f"><span>Телефон</span><input type="tel" name="phone" value="<?= $val('phone') ?>" placeholder="+7 (000) 000-00-00" autocomplete="tel"></label>
          <label class="acc-f"><span>Электронная почта</span><input type="email" name="email" value="<?= $val('email') ?>" autocomplete="email"></label>
        </div>
        <div class="acc-f"><span>Сфера деятельности — можно несколько</span><?= $spheresHtml() ?></div>
        <div class="acc-row">
          <label class="acc-f"><span>Пароль (не короче 8 знаков)</span><input type="password" name="pass" autocomplete="new-password"></label>
          <label class="acc-f"><span>Пароль ещё раз</span><input type="password" name="pass2" autocomplete="new-password"></label>
        </div>
        <label class="agree"><input type="checkbox" name="mail_ok" value="1"<?= !empty($F['mail_ok']) ? ' checked' : '' ?>> <span>Хочу получать на почту новости о новых фабриках и товарах в выбранных сферах. Отписаться можно в любой момент в кабинете или по ссылке в письме.</span></label>
        <label class="agree"><input type="checkbox" name="pd_ok" value="1"> <span>Даю согласие на обработку персональных данных в соответствии с <a href="/privacy" target="_blank">политикой конфиденциальности</a> и <a href="/soglasie-na-obrabotku-personalnih-dannih" target="_blank">согласием на обработку персональных данных</a></span></label>
        <button type="submit" class="fbtn">Зарегистрироваться</button>
      </form>
    </div>

<?php elseif ($accRoute === '/vhod' && $db): ?>
    <form class="acc-card acc-card--sm" method="post" action="/vhod">
      <h1 class="acc-h">Вход</h1>
      <input type="hidden" name="fk" value="<?= e($fk) ?>">
      <label class="acc-f"><span>Электронная почта</span><input type="email" name="email" value="<?= e(acc_clean($_POST['email'] ?? '', 190)) ?>" autocomplete="email" required></label>
      <label class="acc-f"><span>Пароль</span><input type="password" name="pass" autocomplete="current-password" required></label>
      <button type="submit" class="fbtn">Войти</button>
      <p class="acc-small"><a href="/zabyli-parol">Забыли пароль?</a> · <a href="/registraciya">Регистрация</a></p>
    </form>

<?php elseif ($accRoute === '/zabyli-parol' && $db): ?>
    <form class="acc-card acc-card--sm" method="post" action="/zabyli-parol">
      <h1 class="acc-h">Восстановление пароля</h1>
      <p class="acc-small">Укажите почту, с которой регистрировались. Пришлём ссылку, чтобы задать новый пароль.</p>
      <input type="hidden" name="fk" value="<?= e($fk) ?>">
      <label class="acc-f"><span>Электронная почта</span><input type="email" name="email" autocomplete="email" required></label>
      <button type="submit" class="fbtn">Прислать ссылку</button>
      <p class="acc-small"><a href="/vhod">Вход</a></p>
    </form>

<?php elseif ($accRoute === '/novyj-parol' && $resetUser): ?>
    <form class="acc-card acc-card--sm" method="post" action="/novyj-parol?t=<?= e((string)$_GET['t']) ?>">
      <h1 class="acc-h">Новый пароль</h1>
      <p class="acc-small">Для <?= e($resetUser['email']) ?></p>
      <input type="hidden" name="fk" value="<?= e($fk) ?>">
      <label class="acc-f"><span>Новый пароль (не короче 8 знаков)</span><input type="password" name="pass" autocomplete="new-password" required></label>
      <label class="acc-f"><span>Ещё раз</span><input type="password" name="pass2" autocomplete="new-password" required></label>
      <button type="submit" class="fbtn">Сохранить</button>
    </form>

<?php elseif ($accRoute === '/kabinet' && $me): ?>
    <div class="acc-top">
      <h1 class="acc-h">Личный кабинет</h1>
      <a class="acc-btn2" href="/vyhod?fk=<?= e($fk) ?>"><?= $svg('logout') ?>Выйти</a>
    </div>
<?php if (!$me['verified']): ?>
    <form class="acc-note" method="post" action="/kabinet"><input type="hidden" name="fk" value="<?= e($fk) ?>"><input type="hidden" name="act" value="resend">
      <?= $svg('mail') ?><span>Почта <b><?= e($me['email']) ?></b> ещё не подтверждена. Откройте письмо от ИТК и нажмите «Подтвердить почту». Пока почта не подтверждена, рассылка не приходит.</span>
      <button type="submit" class="acc-btn2">Отправить письмо ещё раз</button></form>
<?php endif; ?>
    <form class="acc-card" method="post" action="/kabinet">
      <input type="hidden" name="fk" value="<?= e($fk) ?>"><input type="hidden" name="act" value="save">
      <div class="acc-who"><?= $svg($me['kind'] === 'yur' ? 'biz' : 'person') ?><div><b><?= e($me['kind'] === 'yur' ? $me['company'] : trim($me['last_name'] . ' ' . $me['first_name'] . ' ' . $me['middle_name'])) ?></b>
        <span><?= $me['kind'] === 'yur' ? 'Юрлицо или ИП · ИНН ' . e($me['inn']) : 'Физическое лицо' ?> · <?= e($me['email']) ?><?= $me['verified'] ? ' · почта подтверждена' : '' ?></span></div></div>
<?php if ($me['kind'] === 'yur'): ?>
      <label class="acc-f"><span>Название компании</span><input type="text" name="company" value="<?= $val('company') ?>"></label>
      <div class="acc-row">
        <label class="acc-f"><span>ОГРН или ОГРНИП</span><input type="text" name="ogrn" value="<?= $val('ogrn') ?>"></label>
        <label class="acc-f"><span>КПП</span><input type="text" name="kpp" value="<?= $val('kpp') ?>"></label>
      </div>
      <label class="acc-f"><span>Юридический адрес</span><input type="text" name="addr" value="<?= $val('addr') ?>"></label>
      <div class="acc-row">
        <label class="acc-f"><span>Контактное лицо (ФИО)</span><input type="text" name="contact" value="<?= $val('contact') ?>"></label>
        <label class="acc-f"><span>Должность</span><input type="text" name="post" value="<?= $val('post') ?>"></label>
      </div>
<?php else: ?>
      <label class="acc-f"><span>Фамилия</span><input type="text" name="last_name" value="<?= $val('last_name') ?>"></label>
      <div class="acc-row">
        <label class="acc-f"><span>Имя</span><input type="text" name="first_name" value="<?= $val('first_name') ?>"></label>
        <label class="acc-f"><span>Отчество</span><input type="text" name="middle_name" value="<?= $val('middle_name') ?>"></label>
      </div>
<?php endif; ?>
      <label class="acc-f"><span>Телефон</span><input type="tel" name="phone" value="<?= $val('phone') ?>"></label>
      <div class="acc-f"><span>Сфера деятельности</span><?= $spheresHtml() ?></div>
      <label class="agree"><input type="checkbox" name="mail_ok" value="1"<?= $me['mail_ok'] ? ' checked' : '' ?>> <span>Получать на почту новости о новых фабриках и товарах в выбранных сферах</span></label>
      <button type="submit" class="fbtn">Сохранить</button>
    </form>
    <details class="acc-card acc-pass"><summary>Сменить пароль</summary>
      <form method="post" action="/kabinet"><input type="hidden" name="fk" value="<?= e($fk) ?>"><input type="hidden" name="act" value="pass">
        <label class="acc-f"><span>Текущий пароль</span><input type="password" name="old" autocomplete="current-password"></label>
        <div class="acc-row">
          <label class="acc-f"><span>Новый пароль</span><input type="password" name="pass" autocomplete="new-password"></label>
          <label class="acc-f"><span>Ещё раз</span><input type="password" name="pass2" autocomplete="new-password"></label>
        </div>
        <button type="submit" class="acc-btn2">Сменить пароль</button>
      </form>
    </details>

<?php elseif ($accRoute === '/podtverzhdenie'): ?>
    <div class="acc-card acc-card--sm"><a href="<?= acc_me() ? '/kabinet' : '/vhod' ?>" class="fbtn acc-fbtn"><?= acc_me() ? 'В кабинет' : 'Войти' ?></a></div>
<?php elseif ($accRoute === '/novyj-parol'): ?>
    <div class="acc-card acc-card--sm"><a href="/zabyli-parol" class="fbtn acc-fbtn">Запросить ссылку ещё раз</a></div>
<?php endif; ?>
  </div>
</section>
</main>
<?php if ($accRoute === '/registraciya'): ?>
<script>
(function(){
  var f=document.getElementById('accReg'); if(!f) return;
  function kind(){ var y=f.querySelector('input[name=kind]:checked').value==='yur'; f.classList.toggle('is-yur',y); f.classList.toggle('is-fiz',!y); }
  f.querySelectorAll('input[name=kind]').forEach(function(r){ r.addEventListener('change',kind); }); kind();
  var b=document.getElementById('accInnBtn'), m=document.getElementById('accInnMsg');
  function look(){
    var inn=(f.inn.value||'').replace(/\D/g,''); f.inn.value=inn;
    if(inn.length!==10&&inn.length!==12){ m.className='acc-innmsg err'; m.textContent='ИНН — 10 цифр у организации, 12 — у ИП'; return; }
    m.className='acc-innmsg'; m.textContent='Ищу…'; b.disabled=true;
    fetch('/acc-inn?inn='+inn+'&fk=<?= e($fk) ?>',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
      b.disabled=false;
      if(!d.ok){ m.className='acc-innmsg err'; m.textContent=d.error||'Не нашлось'; return; }
      f.company.value=d.company||''; f.ogrn.value=d.ogrn||''; f.kpp.value=d.kpp||''; f.addr.value=d.addr||'';
      m.className='acc-innmsg ok'; m.textContent=d.closed?'Найдено, но компания закрыта или ликвидируется — проверьте ИНН':'Найдено: '+(d.company||'');
    }).catch(function(){ b.disabled=false; m.className='acc-innmsg err'; m.textContent='Нет связи — заполните вручную'; });
  }
  b.addEventListener('click',look);
  f.inn.addEventListener('keydown',function(e){ if(e.key==='Enter'){ e.preventDefault(); look(); } });
})();
</script>
<?php endif; ?>
<?php require ROOT . '/inc/foot.php'; ?>
