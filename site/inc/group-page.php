<?php
// Страница раздела из меню «Направления» (Сантехника, ХоРеКа): вступление, галерея фото товаров, плитки заводов (3.40.0).
// Перед подключением задать: $title, $descr, $crumb, $gpH1, $gpText, $gpGroup, $gpSelf, $gpTilesTitle; по желанию $gpExtra — ещё плитки,
// $gpSubs — плитки подразделов (site_subsec_list), $gpPhotos — свои фото галереи.
$bodyClass = 'inner';
$gpTiles = site_group_tiles($gpGroup, $gpSelf);
foreach (($gpExtra ?? []) as $t) $gpTiles[] = $t;
$gpSeen = []; $gpTiles = array_values(array_filter($gpTiles, function ($t) use (&$gpSeen) { $k = strtok($t[0], '#'); if (isset($gpSeen[$k])) return false; $gpSeen[$k] = 1; return true; }));
if (empty($gpPhotos)) $gpPhotos = site_group_photos($gpGroup, 8);
if (!empty($gpPhotos[0])) $ogImageUrl = $gpPhotos[0];
require ROOT . '/inc/head.php';
?>
<main class="page">
<?php require ROOT . '/inc/crumbs.php'; ?>

<section class="sec">
  <div class="wrap intro">
    <h1 class="intro__title"><?= e($gpH1) ?></h1>
    <p class="intro__text"><?= e($gpText) ?></p>
  </div>
</section>

<?php if ($gpPhotos): ?>
<section class="sec">
  <div class="wrap gallery gallery--4">
<?php foreach ($gpPhotos as $ph): ?>
    <a class="gallery__i js-zoom" href="<?= e(sup_img($ph)) ?>" style="background-image:url('<?= e(sup_img($ph)) ?>')" aria-label="Увеличить фото"></a>
<?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($gpSubs)): // подразделы HoReCa и «Мебели» (3.45.0) ?>
<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">РАЗДЕЛЫ</h2>
    <p class="whs__lead"><?= e($gpSubsLead ?? 'Выберите, что нужно оснастить, — откроются фабрики с подходящими товарами.') ?></p>
    <div class="pcr-grid">
<?php foreach ($gpSubs as $s): ?>
      <a class="pcr az-tile az-cattile ss-tile" href="<?= e($s[0]) ?>"><div class="pcr__img"><?php if ($s[3] !== ''): ?><img src="<?= e(sup_img($s[3])) ?>" alt="<?= e($s[1]) ?>" loading="lazy"><?php endif; ?></div>
        <div class="pcr__body"><h4><?= e($s[1]) ?></h4><b class="az-what"><?= e($s[2]) ?></b><?php if ($s[4]): ?><span class="az-city"><?= (int)$s[4] ?> <?= e(ss_factories($s[4])) ?></span><?php endif; ?><span class="az-open">Открыть</span></div></a>
<?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($gpTiles): ?>
<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title"><?= e($gpTilesTitle) ?></h2>
    <p class="whs__lead">Нажмите на завод — откроется его страница с моделями и фото.</p>
    <div class="pcr-grid">
<?php foreach ($gpTiles as $f): ?>
      <a class="pcr az-tile az-cattile" href="<?= e($f[0]) ?>"><div class="pcr__img"><img src="<?= e(sup_img($f[4])) ?>" alt="<?= e(sup_t($f[1])) ?>" loading="lazy"></div>
        <div class="pcr__body"><?php if (!empty($f[5])) echo sup_big_badge(); ?><h4><?= e(sup_t($f[1])) ?></h4><b class="az-what"><?= e(sup_t($f[2])) ?></b><?php if ($f[3] !== ''): ?><span class="az-city"><?= e(sup_city($f[3])) ?></span><?php endif; ?><span class="az-open">Подробнее</span></div></a>
<?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
require ROOT . '/inc/steps.php';
$ctaText = 'Есть вопросы — мы готовы проконсультировать, сделать индивидуальный расчёт и подобрать максимально выгодного поставщика под вашу задачу.';
require ROOT . '/inc/cta.php';
$advItems = [
  ['opyt.svg', 'ПОСТАВЩИКИ', 'Собственная большая база проверенных поставщиков'],
  ['adv-logistics.svg', 'СКЛАД', 'Собственный склад в Гуанчжоу в центре торговли в Китае'],
  ['adv-links.svg', 'СОТРУДНИКИ', 'Русскоязычные сотрудники в офисе и на складе.'],
  ['adv-support.svg', 'СОПРОВОЖДЕНИЕ', 'Полное сопровождение — от заявки и оплаты, производства - до выгрузки на вашем складе.<br><br>Сопровождаем Вас на каждом этапе'],
  ['adv-reliable.svg', 'КОНТРОЛЬ', 'Контроль качества производства, закупки. Фото и видео отчет маркировки и отгрузки.'],
  ['adv-contract.svg', 'КОММУНИКАЦИЯ', 'Прозрачная коммуникация — мессенджеры, личный менеджер.'],
];
$advNums = false;
$advBottom = 'Мы не просто «помогаем с поиском поставщика» — мы закрываем под ключ всю китайскую часть бизнеса';
require ROOT . '/inc/adv.php';
require ROOT . '/inc/form-question.php';
?>
</main>
<?php require ROOT . '/inc/foot.php'; ?>
