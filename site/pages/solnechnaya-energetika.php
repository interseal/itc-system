<?php
// Раздел «Солнечная энергетика»: список заводов, у каждого своя страница
$title = 'Солнечная энергетика из Китая напрямую с заводов — панели, инверторы, накопители, кабель, крепления | ИТК';
$descr = 'Оборудование для солнечных электростанций из Китая напрямую с заводов: солнечные панели, гибридные инверторы, накопители энергии и аккумуляторы LiFePO4, солнечный кабель и коннекторы MC4, крепления для панелей. Подбор под проект, официальный ввоз, документы, оплата в рублях.';
$bodyClass = 'inner';
$crumb = 'Солнечная энергетика';
$canonical = '/solnechnaya-energetika';
$ogImage = 'kingfeels/hero.jpg';

// Заводы: адрес, название, что делает, город, фото
$shFactories = [
  ['/gpsolar', 'GPSOLAR', 'Солнечные панели 430–730 Вт и накопители', 'Цзиньхуа', 'gpsolar/n505.jpg'],
  ['/snadi', 'SNADI', 'Гибридные инверторы и аккумуляторы', 'Фошань', 'snadi/li-ion-wall.jpg'],
  ['/riwas', 'RIWAS', 'Накопители энергии и электростанции', 'Чжуншань', 'riwas/shcs3000.jpg'],
  ['/pntech', 'PNTECH', 'Солнечный кабель и коннекторы MC4', 'Нинбо', 'pntech/k-pv004.jpg'],
  ['/kingfeels', 'Kingfeels', 'Крепления для солнечных панелей', 'Сямынь', 'kingfeels/tile.jpg'],
];
// заводы этого раздела из базы поставщиков Экосистемы (страницы, опубликованные кнопкой)
$shHave = array_column($shFactories, 0);
foreach (sup_site_section('solnechnaya-energetika') as $sf) if (!in_array($sf[0], $shHave, true)) $shFactories[] = $sf;


$jsonld = [
  ['@context' => 'https://schema.org', '@type' => 'Service',
   'name' => 'Поставка оборудования для солнечных электростанций из Китая напрямую с заводов', 'serviceType' => 'Поставка солнечного оборудования из Китая',
   'description' => $descr, 'areaServed' => 'RU',
   'provider' => ['@type' => 'Organization', 'name' => 'ООО «Интернациональная Торговая Компания»', 'url' => 'https://itcpartner.ru', 'telephone' => $cfg['phone1_raw']]],
  ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => 'https://itcpartner.ru/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Солнечная энергетика', 'item' => 'https://itcpartner.ru/solnechnaya-energetika']]],
];
require ROOT . '/inc/head.php';
?>
<main class="page">
<?php require ROOT . '/inc/crumbs.php'; ?>

<section class="sec">
  <div class="wrap whs whs--rev pcr-hero">
    <div class="whs__text">
      <h1 class="whs__title">СОЛНЕЧНАЯ ЭНЕРГЕТИКА ИЗ КИТАЯ</h1>
      <p class="whs__lead">Напрямую с заводов — для монтажных компаний, предприятий, дилеров и частных домов.</p>
      <p class="az-intro">Собираем солнечную станцию целиком у проверенных заводов: панели, инверторы, накопители, кабель с коннекторами и крепления. Сами общаемся с заводами, подбираем оборудование под ваш проект и привозим с полным пакетом документов.</p>
      <a href="#vopros" class="btn btn--green btn--lg js-pop">Подобрать оборудование</a>
    </div>
    <img class="whs__photo" src="<?= e(img('kingfeels/hero.jpg')) ?>" alt="Солнечная электростанция">
  </div>
</section>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">ЗАВОДЫ</h2>
    <p class="whs__lead">Нажмите на завод — откроется его страница с товарами и фото.</p>
    <div class="pcr-grid">
<?php foreach ($shFactories as $f): ?>
      <a class="pcr az-tile az-cattile" href="<?= e($f[0]) ?>"><div class="pcr__img"><img src="<?= e(sup_img($f[4])) ?>" alt="<?= e(sup_t($f[1])) ?>" loading="lazy"></div>
        <div class="pcr__body"><h4><?= e(sup_t($f[1])) ?></h4><b class="az-what"><?= e(sup_t($f[2])) ?></b><?php if ($f[3] !== ''): ?><span class="az-city"><?= e(sup_city($f[3])) ?></span><?php endif; ?><span class="az-open">Подробнее</span></div></a>
<?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">ЧТО ВЕЗЁМ</h2>
    <p class="whs__lead">Направления, по которым у нас уже есть заводы-поставщики.</p>
    <div class="ugrid ugrid--3">
      <div class="ucard"><div><h3>Солнечные панели</h3><p>Двусторонние N-Type и BC мощностью 430–730 Вт.</p></div></div>
      <div class="ucard"><div><h3>Инверторы</h3><p>Гибридные, автономные и сетевые, для дома и бизнеса.</p></div></div>
      <div class="ucard"><div><h3>Накопители энергии</h3><p>Аккумуляторы LiFePO4, шкафы и портативные электростанции.</p></div></div>
      <div class="ucard"><div><h3>Кабель и коннекторы</h3><p>Солнечный кабель, MC4, разветвители, автоматика.</p></div></div>
      <div class="ucard"><div><h3>Крепления</h3><p>На крышу, на землю, навесы для машин и трекеры.</p></div></div>
      <div class="ucard"><div><h3>Станция под ключ</h3><p>Соберём комплект от разных заводов в одну поставку.</p></div></div>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">КАК ЗАКАЗАТЬ</h2>
    <p class="whs__lead">От проекта до оборудования на вашем объекте.</p>
    <div class="ugrid ugrid--3">
      <div class="ucard"><div><h3>1. Заявка</h3><p>Присылаете проект, список оборудования или задачу.</p></div></div>
      <div class="ucard"><div><h3>2. Предложение</h3><p>Подбираем оборудование, согласуем с заводами цены и сроки.</p></div></div>
      <div class="ucard"><div><h3>3. Доставка</h3><p>Проверяем оборудование и везём в Россию с документами.</p></div></div>
    </div>
  </div>
</section>

<?php require ROOT . '/inc/form-question.php'; ?>
</main>
<?php require ROOT . '/inc/foot.php'; ?>
