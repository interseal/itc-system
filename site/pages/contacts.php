<?php
$title = 'Контакты — Закупка и доставка товаров из Китая | ИТК Партнёр';
$descr = 'Контакты Интернациональной Торговой Компании: сотрудники, телефоны, мессенджеры и адреса офисов в Москве, Симферополе, Бишкеке и склада в Гуанчжоу (Фошань).';
$bodyClass = 'inner';
$crumb = 'Контакты';
require ROOT . '/inc/head.php';

$team = [
  ['tild3166-3762-4035-b533-346634353366/IMG_20260310_112631_.jpg', 'Корецкий Алексей Николаевич', 'Генеральный директор', 'tild3731-6264-4030-a135-393765663933/Screenshot_20260321_.jpg',
   ['+7 978 701 03 30', 'tel:+79787010330'], ['@BlackPalmer', 'https://t.me/BlackPalmer'], ['businnes@itcpartner.ru', 'mailto:interseal@ya.ru']],
  // Сьедин Денис — фото своё, из папки сайта (путь с «/»); Telegram, почта и WeChat пока не даны — строки не показываются
  ['/img/t/siedin-denis.jpg', 'Сьедин Денис', 'Менеджер по продажам', null,
   ['+7 918 022-44-06', 'tel:+79180224406'], null, null],
  ['tild6261-3663-4237-b136-643437373062/IMG_20260326_134935_.jpg', 'Мацепа Анна Петровна', 'Бухгалтер', 'tild3935-6234-4339-b637-393364363864/IMG_20260325_163718_.jpg',
   ['+7 978 500 44 02', 'tel:+79785004402'], ['@LLCITC', 'https://t.me/LLCITC'], ['office@itcpartner.ru', 'mailto:info@itcpartner.ru']],
  ['tild3563-3464-4163-b366-343964326636/20260325_161859.jpg', 'Савко Полина Андреевна', 'Специалист по закупкам', 'tild6436-3337-4438-b730-393962633930/IMG_20260325_162755_.jpg',
   ['+7 918 872 44 59', 'tel:+79188724459'], ['@itc_partner', 'https://t.me/ZakaZimport007'], ['savko@itcpartner.ru', 'mailto:info@itcpartner.ru']],
];
// Серый силуэт, если файла фото сотрудника нет
$ctNoPhoto = 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130 160"><rect width="130" height="160" fill="#dfe5ef"/><circle cx="65" cy="60" r="26" fill="#b4bfd0"/><path d="M18 160c4-34 24-52 47-52s43 18 47 52z" fill="#b4bfd0"/></svg>');
$flagRu = 'tild3361-3832-4266-b463-313362616132/0_1200px-Flag_of_Rus.png';
$addr = [
  [$flagRu, 'Российская Федерация<br>г. Москва ул. Чагинская д. 4 стр. 13 помещ. 14/4', 'Russian Federation<br>Moscow, Chaginskaya Street, 4, Building 13<br>room 14/4', ''],
  [$flagRu, 'Российская Федерация, Республика Крым,<br>Симферопольский район, с. Мирное,<br>ул. Деловая, 5, БЦ «Элемент», офис 608', 'Russian Federation, Republic of Crimea,<br>Simferopol district, Mirnoye village,<br>Delovaya Street 5, Business Center Element, office 608', ''],
  ['tild3833-6663-4436-a463-333966623735/Flag_of_Kyrgyzstansv.png', 'Республика Кыргыстан<br>г. Бишкек ул. Интернациональная 28', 'Республика Кыргыстан<br>г. Бишкек ул. Интернациональная 55', ''],
  ['tild3466-3632-4562-b663-656364336335/i.webp', 'Китай, склад в Гуанчжоу (Фошань)<br>Провинция Гуандун, г. Фошань, район Наньхай, посёлок Дали, Яньбу, промзона Лунъюн-2, д. 16, здание группы Fenglian, 1-й этаж', 'China, Guangdong Province, Foshan City, Nanhai District, Dali Town, Yanbu, Longyong Industrial Zone 2, No. 16, Fenglian Group, 1st floor', '广东省佛山市南海区大沥镇盐步<br>龙涌龙二工业区16号枫莲集团一楼货梯处'],
];
?>
<main class="page">
<?php require ROOT . '/inc/crumbs.php'; ?>

<section class="sec">
  <div class="wrap ct-head">
    <h1 class="whs__title">КОНТАКТЫ</h1>
    <p class="whs__lead">Мы на связи ежедневно 24/7. Звоните или пишите любому сотруднику в Telegram или WeChat.</p>
  </div>
</section>

<section class="sec">
  <div class="wrap ct-team">
<?php foreach ($team as $p): ?>
    <div class="ct-card">
      <div class="ct-card__photo" style="background-image:url('<?= e($p[0][0] === '/' ? (is_file(ROOT . $p[0]) ? $p[0] . '?v=' . $v : $ctNoPhoto) : timg($p[0])) ?>')"></div>
      <div class="ct-card__info">
        <h3><?= e($p[1]) ?></h3>
        <span class="ct-card__role"><?= e($p[2]) ?></span>
<?php if ($p[4]): ?>
        <a href="<?= e($p[4][1]) ?>"><svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><circle cx="12" cy="12" r="12" fill="#112C93"/><path fill="#fff" d="M9.1 6.6c.3 0 .6.2.7.5l.8 1.9c.1.3 0 .6-.2.8l-.9.9c.6 1.3 1.6 2.3 2.9 2.9l.9-.9c.2-.2.5-.3.8-.2l1.9.8c.3.1.5.4.5.7v1.7c0 .4-.3.8-.8.8C10.5 16.4 7.6 13.5 7.4 9.4c0-.5.4-.8.8-.8h.9z"/></svg><?= e($p[4][0]) ?></a>
<?php endif; if ($p[5]): ?>
        <a href="<?= e($p[5][1]) ?>" target="_blank" rel="noopener"><img src="<?= e(img('tg.svg')) ?>" alt="" width="26" height="26"><?= e($p[5][0]) ?></a>
<?php endif; if ($p[6]): ?>
        <a href="<?= e($p[6][1]) ?>"><img src="<?= e(img('mail.svg')) ?>" alt="" width="26" height="26"><?= e($p[6][0]) ?></a>
<?php endif; ?>
      </div>
<?php if ($p[3]): ?>
      <a class="ct-card__qr js-zoom" href="<?= e(timg($p[3])) ?>" style="background-image:url('<?= e(timg($p[3])) ?>')" aria-label="QR-код WeChat — нажмите, чтобы увеличить"><span>WeChat</span></a>
<?php endif; ?>
    </div>
<?php endforeach; ?>
  </div>
</section>

<section class="sec">
  <div class="wrap ct-addr">
    <h2 class="whs__title">НАШИ АДРЕСА</h2>
    <div class="ct-addr__grid">
<?php foreach ($addr as $a): ?>
      <div class="ct-addr__item">
        <img src="<?= e(timg($a[0])) ?>" alt="" width="64" height="43">
        <div>
          <p class="ct-addr__ru"><?= $a[1] ?></p>
          <p class="ct-addr__en"><?= $a[2] ?></p>
<?php if ($a[3]): ?>          <p class="ct-addr__cn"><?= $a[3] ?></p>
<?php endif; ?>
        </div>
      </div>
<?php endforeach; ?>
    </div>
  </div>
</section>

<?php require ROOT . '/inc/form-question.php'; ?>
</main>
<?php require ROOT . '/inc/foot.php'; ?>
