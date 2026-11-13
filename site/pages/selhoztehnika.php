<?php
// Раздел «Сельхозтехника»: список заводов, у каждого своя страница
$title = 'Сельхозтехника из Китая напрямую с заводов — пресс-подборщики, косилки, почвофрезы, бороны | ИТК';
$descr = 'Сельхозтехника из Китая напрямую с заводов: рулонные пресс-подборщики, косилки, грабли, почвофрезы, дисковые бороны и плуги, ножи для почвофрез. Подбор под ваш трактор, официальный ввоз, документы, оплата в рублях.';
$bodyClass = 'inner';
$crumb = 'Сельхозтехника';
$canonical = '/selhoztehnika';
$ogImage = 'jiujia/9gyz49-3.jpg';

// Заводы: адрес, название, что делает, город, фото
$shFactories = [
  ['/fascar', 'FASCAR (Jiujia)', 'Пресс-подборщики, косилки и грабли', 'Хулунбуир', 'jiujia/s9000-beyond.jpg'],
  ['/zhengtong', 'ZHENGTONG', 'Почвофрезы, дисковые бороны и плуги', 'Куньмин', 'zhengtong/a149_0.jpg'],
];
// заводы этого раздела из базы поставщиков Экосистемы (страницы, опубликованные кнопкой)
$shHave = array_column($shFactories, 0);
foreach (sup_site_section('selhoztehnika') as $sf) if (!in_array($sf[0], $shHave, true)) $shFactories[] = $sf;


$jsonld = [
  ['@context' => 'https://schema.org', '@type' => 'Service',
   'name' => 'Поставка сельхозтехники из Китая напрямую с заводов', 'serviceType' => 'Поставка сельскохозяйственной техники из Китая',
   'description' => $descr, 'areaServed' => 'RU',
   'provider' => ['@type' => 'Organization', 'name' => 'ООО «Интернациональная Торговая Компания»', 'url' => 'https://itcpartner.ru', 'telephone' => $cfg['phone1_raw']]],
  ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => 'https://itcpartner.ru/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Сельхозтехника', 'item' => 'https://itcpartner.ru/selhoztehnika']]],
];
require ROOT . '/inc/head.php';
?>
<main class="page">
<?php require ROOT . '/inc/crumbs.php'; ?>

<section class="sec">
  <div class="wrap whs whs--rev pcr-hero">
    <div class="whs__text">
      <h1 class="whs__title">СЕЛЬХОЗТЕХНИКА ИЗ КИТАЯ</h1>
      <p class="whs__lead">Напрямую с заводов — для фермерских хозяйств, агрохолдингов и дилеров.</p>
      <p class="az-intro">Привозим технику для заготовки кормов и обработки почвы: пресс-подборщики, косилки и грабли, почвофрезы, дисковые бороны и плуги, ножи и запчасти. Сами общаемся с заводом, подбираем модели под ваш трактор и привозим с полным пакетом документов.</p>
      <a href="#vopros" class="btn btn--green btn--lg js-pop">Подобрать технику</a>
    </div>
    <img class="whs__photo" src="<?= e(img('jiujia/9gyz49-3.jpg')) ?>" alt="Сельхозтехника из Китая в поле">
  </div>
</section>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">ЗАВОДЫ</h2>
    <p class="whs__lead">Нажмите на завод — откроется его страница с моделями и фото.</p>
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
      <div class="ucard"><div><h3>Пресс-подборщики</h3><p>Рулонные и для прямоугольных тюков, с обмоткой плёнкой.</p></div></div>
      <div class="ucard"><div><h3>Косилки и грабли</h3><p>Самоходные косилки-плющилки, прицепные косилки, роторные и колёсные грабли.</p></div></div>
      <div class="ucard"><div><h3>Почвофрезы</h3><p>Навесные фрезы шириной от 1,4 до 2,8 м и мотоблоки.</p></div></div>
      <div class="ucard"><div><h3>Бороны и плуги</h3><p>Дисковые бороны от 12 до 48 дисков, дисковые и оборотные плуги.</p></div></div>
      <div class="ucard"><div><h3>Рулоновозы и измельчители</h3><p>Перевозка рулонов и подготовка кормов.</p></div></div>
      <div class="ucard"><div><h3>Ножи и запчасти</h3><p>Ножи и валы для почвофрез, детали для пресс-подборщиков.</p></div></div>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">КАК ЗАКАЗАТЬ</h2>
    <p class="whs__lead">От выбора модели до техники в вашем хозяйстве.</p>
    <div class="ugrid ugrid--3">
      <div class="ucard"><div><h3>1. Заявка</h3><p>Присылаете модели или задачу и мощность вашего трактора.</p></div></div>
      <div class="ucard"><div><h3>2. Предложение</h3><p>Подбираем технику, согласуем с заводом цены и сроки.</p></div></div>
      <div class="ucard"><div><h3>3. Доставка</h3><p>Проверяем технику и везём в Россию с документами.</p></div></div>
    </div>
  </div>
</section>

<?php require ROOT . '/inc/form-question.php'; ?>
</main>
<?php require ROOT . '/inc/foot.php'; ?>
