<?php
// Раздел «Оборудование для кафе и торговли»: заводы-поставщики, у каждого своя страница (страницы из базы Экосистемы)
$title = 'Оборудование для кафе и торговли из Китая напрямую с заводов — торговые автоматы, холодильные витрины и шкафы | ИТК';
$descr = 'Торговые автоматы сока, кофе и мороженого, холодильные витрины для тортов, холодильные и морозильные шкафы, столы, кофемашины и соковыжималки напрямую с заводов в Китае. Подбор, официальный ввоз, документы, оплата в рублях.';
$bodyClass = 'inner';
$crumb = 'Оборудование для кафе и торговли';
$canonical = '/oborudovanie-dlya-kafe';
$ogImage = 'horeca/hero.jpg';

// Заводы раздела — страницы, опубликованные из базы поставщиков Экосистемы: [адрес, название, что делает, город, фото]
$hcFactories = sup_site_section('oborudovanie-dlya-kafe');

$jsonld = [
  ['@context' => 'https://schema.org', '@type' => 'Service',
   'name' => 'Поставка оборудования для кафе и торговли из Китая напрямую с заводов', 'serviceType' => 'Поставка торгового и холодильного оборудования из Китая',
   'description' => $descr, 'areaServed' => 'RU',
   'provider' => ['@type' => 'Organization', 'name' => 'ООО «Интернациональная Торговая Компания»', 'url' => 'https://itcpartner.ru', 'telephone' => $cfg['phone1_raw']]],
  ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => 'https://itcpartner.ru/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Оборудование для кафе и торговли', 'item' => 'https://itcpartner.ru/oborudovanie-dlya-kafe']]],
];
require ROOT . '/inc/head.php';
?>
<main class="page">
<?php require ROOT . '/inc/crumbs.php'; ?>

<section class="sec">
  <div class="wrap whs whs--rev pcr-hero">
    <div class="whs__text">
      <h1 class="whs__title">ОБОРУДОВАНИЕ ДЛЯ КАФЕ И ТОРГОВЛИ ИЗ КИТАЯ</h1>
      <p class="whs__lead">Напрямую с заводов — для кафе, ресторанов, кондитерских, магазинов и вендинга.</p>
      <p class="az-intro">Привозим торговые автоматы сока, кофе и мороженого, холодильные витрины для тортов и десертов, холодильные и морозильные шкафы и столы для кухни, кофемашины, соковыжималки и аппараты напитков. Сами общаемся с заводом, подбираем модели под ваше помещение и привозим с полным пакетом документов.</p>
      <a href="#vopros" class="btn btn--green btn--lg js-pop">Подобрать оборудование</a>
    </div>
    <img class="whs__photo" src="<?= e(img('horeca/hero.jpg')) ?>" alt="Торговые автоматы, холодильные витрины и шкафы из Китая">
  </div>
</section>

<?php if ($hcFactories): ?>
<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">ЗАВОДЫ</h2>
    <p class="whs__lead">Нажмите на завод — откроется его страница с моделями и фото.</p>
    <div class="pcr-grid">
<?php foreach ($hcFactories as $f): ?>
      <a class="pcr az-tile az-cattile" href="<?= e($f[0]) ?>"><div class="pcr__img"><img src="<?= e(sup_img($f[4])) ?>" alt="<?= e(sup_t($f[1])) ?>" loading="lazy"></div>
        <div class="pcr__body"><h4><?= e(sup_t($f[1])) ?></h4><b class="az-what"><?= e(sup_t($f[2])) ?></b><?php if ($f[3] !== ''): ?><span class="az-city"><?= e(sup_city($f[3])) ?></span><?php endif; ?><span class="az-open">Подробнее</span></div></a>
<?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">ЧТО ВЕЗЁМ</h2>
    <p class="whs__lead">Направления, по которым у нас уже есть заводы-поставщики.</p>
    <div class="ugrid ugrid--3">
      <div class="ucard"><div><h3>Торговые автоматы</h3><p>Свежевыжатый сок, кофе, мороженое, попкорн, снеки, обеды, цветы.</p></div></div>
      <div class="ucard"><div><h3>Холодильные витрины</h3><p>Для тортов и десертов, мороженого, салатов, суши и напитков.</p></div></div>
      <div class="ucard"><div><h3>Шкафы для кухни</h3><p>Холодильные и морозильные шкафы из нержавеющей стали под гастроёмкости.</p></div></div>
      <div class="ucard"><div><h3>Холодильные столы</h3><p>Столы с дверями и ящиками, саладетты для пиццы, барные холодильники.</p></div></div>
      <div class="ucard"><div><h3>Кофемашины и соковыжималки</h3><p>Автоматические кофемашины, апельсиновые и шнековые соковыжималки.</p></div></div>
      <div class="ucard"><div><h3>Мороженое и напитки</h3><p>Фризеры, граниторы, охладители сока, аппараты газировки.</p></div></div>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">КАК ЗАКАЗАТЬ</h2>
    <p class="whs__lead">От выбора модели до оборудования на вашей точке.</p>
    <div class="ugrid ugrid--3">
      <div class="ucard"><div><h3>1. Заявка</h3><p>Присылаете, что нужно, или размеры помещения и задачу.</p></div></div>
      <div class="ucard"><div><h3>2. Предложение</h3><p>Подбираем модели, согласуем с заводом цены и сроки.</p></div></div>
      <div class="ucard"><div><h3>3. Доставка</h3><p>Проверяем оборудование и везём в Россию с документами.</p></div></div>
    </div>
  </div>
</section>

<?php require ROOT . '/inc/form-question.php'; ?>
</main>
<?php require ROOT . '/inc/foot.php'; ?>
