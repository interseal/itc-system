<?php
// Раздел «Техника для ухода за волосами»: заводы-поставщики, у каждого своя страница (страницы из базы Экосистемы)
$title = 'Техника для ухода за волосами из Китая напрямую с заводов — фены, выпрямители, машинки для стрижки | ИТК';
$descr = 'Фены, выпрямители, плойки, фены-щётки, машинки для стрижки, триммеры и электробритвы напрямую с заводов в Китае. Подбор, официальный ввоз, документы, оплата в рублях.';
$bodyClass = 'inner';
$crumb = 'Техника для ухода за волосами';
$canonical = '/tehnika-dlya-volos';
$ogImage = 'volosy/hero.jpg';

// Заводы раздела — страницы, опубликованные из базы поставщиков Экосистемы: [адрес, название, что делает, город, фото]
$hcFactories = sup_site_section('tehnika-dlya-volos');

$jsonld = [
  ['@context' => 'https://schema.org', '@type' => 'Service',
   'name' => 'Поставка техники для ухода за волосами из Китая напрямую с заводов', 'serviceType' => 'Поставка фенов, выпрямителей и машинок для стрижки из Китая',
   'description' => $descr, 'areaServed' => 'RU',
   'provider' => ['@type' => 'Organization', 'name' => 'ООО «Интернациональная Торговая Компания»', 'url' => 'https://itcpartner.ru', 'telephone' => $cfg['phone1_raw']]],
  ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => 'https://itcpartner.ru/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Техника для ухода за волосами', 'item' => 'https://itcpartner.ru/tehnika-dlya-volos']]],
];
require ROOT . '/inc/head.php';
?>
<main class="page">
<?php require ROOT . '/inc/crumbs.php'; ?>

<section class="sec">
  <div class="wrap whs whs--rev pcr-hero">
    <div class="whs__text">
      <h1 class="whs__title">ТЕХНИКА ДЛЯ УХОДА ЗА ВОЛОСАМИ ИЗ КИТАЯ</h1>
      <p class="whs__lead">Напрямую с заводов — для магазинов, маркетплейсов, салонов и барбершопов.</p>
      <p class="az-intro">Привозим фены, выпрямители, плойки и фены-щётки, машинки для стрижки, триммеры, наборы для стрижки и электробритвы. Сами общаемся с заводом, подбираем модели под ваш магазин или салон и привозим с полным пакетом документов.</p>
      <a href="#vopros" class="btn btn--green btn--lg js-pop">Подобрать технику</a>
    </div>
    <img class="whs__photo" src="<?= e(img('volosy/hero.jpg')) ?>" alt="Фен, выпрямитель, машинка для стрижки и набор с триммером для носа из Китая">
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
      <div class="ucard"><div><h3>Фены</h3><p>Высокоскоростные фены с бесщёточным мотором, салонные и дорожные фены.</p></div></div>
      <div class="ucard"><div><h3>Выпрямители и плойки</h3><p>Керамические и инфракрасные выпрямители, плойки, автоматические плойки.</p></div></div>
      <div class="ucard"><div><h3>Фены-щётки и стайлеры</h3><p>Фены-щётки, мультистайлеры с насадками, расчёски-выпрямители.</p></div></div>
      <div class="ucard"><div><h3>Машинки для стрижки</h3><p>Аккумуляторные и сетевые машинки, наборы для стрижки.</p></div></div>
      <div class="ucard"><div><h3>Триммеры</h3><p>Для окантовки, бороды и тела, триммеры для носа, женские триммеры.</p></div></div>
      <div class="ucard"><div><h3>Электробритвы</h3><p>Роторные и сеточные бритвы, бритвы для головы.</p></div></div>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">КАК ЗАКАЗАТЬ</h2>
    <p class="whs__lead">От выбора модели до товара на вашем складе.</p>
    <div class="ugrid ugrid--3">
      <div class="ucard"><div><h3>1. Заявка</h3><p>Присылаете модели из каталога или описываете, что хотите продавать.</p></div></div>
      <div class="ucard"><div><h3>2. Предложение</h3><p>Согласуем с заводом цены, комплектацию, упаковку и сроки.</p></div></div>
      <div class="ucard"><div><h3>3. Доставка</h3><p>Проверяем товар и везём в Россию с документами.</p></div></div>
    </div>
  </div>
</section>

<?php require ROOT . '/inc/form-question.php'; ?>
</main>
<?php require ROOT . '/inc/foot.php'; ?>
