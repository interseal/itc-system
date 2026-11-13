<?php
// Подраздел HoReCa или «Мебели» (3.45.0): галерея товаров и плитки фабрик, у которых есть такие товары.
// Задано в index.php: $ssParent ('horeca' | 'mebel'), $ssKey (ключ подраздела из site_subsecs()).
$ssAll = site_subsecs(); $ssP = $ssAll[$ssParent]; $ssS = $ssP[2][$ssKey];
$ssTiles = site_subsec_tiles($ssParent, $ssKey);
if (!$ssTiles) { header('Location: ' . lang_prefix() . $ssP[1], true, 302); exit; }
$ssPhotos = site_subsec_photos($ssParent, $ssKey, 8);
if (!empty($ssPhotos[0])) $ogImageUrl = $ssPhotos[0];
$ssParentT = $ssP[0];   // HoReCa, Мебель, Сантехника
$title = $ssS[0] . ' из Китая — ' . $ssParentT . ' | ИТК';
$descr = $ssS[0] . ' напрямую с китайских фабрик: ' . mb_strtolower($ssS[1]) . '. Подбор, официальный ввоз, документы, оплата в рублях.';
$crumb = $ssS[0]; $crumbParent = [$ssP[1], $ssParentT];
$bodyClass = 'inner';
require ROOT . '/inc/head.php';
?>
<main class="page">
<?php require ROOT . '/inc/crumbs.php'; ?>

<section class="sec">
  <div class="wrap intro">
    <h1 class="intro__title"><?= e(mb_strtoupper($ssS[0])) ?></h1>
    <p class="intro__text"><span><?= e($ssS[1]) ?></span>. <span>Товары напрямую с фабрик Китая: соберём заказ от нескольких фабрик в одну поставку через наш склад в Фошане и привезём с полным пакетом документов.</span></p>
  </div>
</section>

<?php if ($ssPhotos): ?>
<section class="sec">
  <div class="wrap gallery gallery--4">
<?php foreach ($ssPhotos as $ph): ?>
    <a class="gallery__i js-zoom" href="<?= e(sup_img($ph)) ?>" style="background-image:url('<?= e(sup_img($ph)) ?>')" aria-label="Увеличить фото"></a>
<?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">ФАБРИКИ</h2>
    <p class="whs__lead">Нажмите на фабрику — откроются её товары из этого раздела.</p>
    <div class="pcr-grid">
<?php foreach ($ssTiles as $f): $tr = $f[7] ? 'sup_t' : 'strval'; ?>
      <a class="pcr az-tile az-cattile" href="<?= e($f[0]) ?>"><div class="pcr__img"><img src="<?= e(sup_img($f[4])) ?>" alt="<?= e($tr($f[1])) ?>" loading="lazy"></div>
        <div class="pcr__body"><?php if (!empty($f[5])) echo sup_big_badge(); ?><h4><?= e($tr($f[1])) ?></h4><b class="az-what"><?= e(is_array($f[2]) ? implode(', ', array_map($tr, $f[2])) : $tr($f[2])) ?></b><?php if ($f[3] !== ''): ?><span class="az-city"><?= e(sup_city($f[3])) ?></span><?php endif; ?><?php if ($f[6]): ?><span class="az-city"><?= (int)$f[6] ?> <?= e(ss_models($f[6])) ?></span><?php endif; ?><span class="az-open">Подробнее</span></div></a>
<?php endforeach; ?>
    </div>
  </div>
</section>

<?php $ssOther = array_values(array_filter(site_subsec_list($ssParent), function ($s) use ($ssP, $ssKey) { return $s[0] !== $ssP[1] . '/' . $ssKey; })); if ($ssOther): ?>
<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">ДРУГИЕ РАЗДЕЛЫ</h2>
    <div class="ss-links">
<?php foreach ($ssOther as $s): ?>
      <a class="ss-link" href="<?= e($s[0]) ?>"><b><?= e($s[1]) ?></b><span><?= e($s[2]) ?></span></a>
<?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
require ROOT . '/inc/steps.php';
$ctaText = 'Есть вопросы — мы готовы проконсультировать, сделать индивидуальный расчёт и подобрать максимально выгодного поставщика под вашу задачу.';
require ROOT . '/inc/cta.php';
require ROOT . '/inc/form-question.php';
?>
</main>
<?php require ROOT . '/inc/foot.php'; ?>
