<?php
$kases = json_decode(file_get_contents(ROOT . '/inc/kases.json'), true);
$k = $kases[$slug] ?? null;
if (!$k) { http_response_code(404); require ROOT . '/pages/404.php'; return; }
$title = 'Кейсы - ' . $k['title'] . ' - INTERNATIONAL TRAIDING COMPANY';
$descr = 'Кейс «' . $k['title'] . '»: фабрики в Китае, с которыми мы работаем напрямую, и поставки в РФ.';
$bodyClass = 'inner';
$crumb = $k['title'];
require ROOT . '/inc/head.php';

// Ссылки в тексте делаем кликабельными
function linkify($html) {
    return preg_replace('~(?<!["\'=])(https?://[^\s<]+[^\s<.,)])~u', '<a href="$1" target="_blank" rel="noopener">$1</a>', $html);
}
?>
<main class="page">
<?php require ROOT . '/inc/crumbs.php'; ?>
<?php foreach ($k['factories'] as $i => $f):
  [$vt, $vid] = array_pad(explode(':', $f['video'] ?? '', 2), 2, '');
  $cover = $f['photos'][0] ?? '';
?>
<section class="sec factory">
  <div class="wrap">
    <h2 class="factory__name"><?= e($f['name']) ?></h2>
    <div class="factory__top">
      <div class="factory__text"><?= linkify($f['text']) ?></div>
      <div class="factory__media">
<?php if ($vid): ?>
        <div class="vbox vbox--wide" <?= $vt === 'youtube' ? 'data-youtube' : 'data-kinescope' ?>="<?= e($vid) ?>" style="background-image:url('<?= e($vt === 'youtube' ? 'https://img.youtube.com/vi/' . $vid . '/hqdefault.jpg' : timg($cover)) ?>')">
          <button type="button" class="vbox__play" aria-label="Смотреть видео">
            <svg viewBox="0 0 60 60" width="70" height="70"><circle cx="30" cy="30" r="30" fill="#000"/><path d="M41 29.75 24 19.25v21z" fill="#fff"/></svg>
          </button>
        </div>
<?php endif; ?>
        <div class="factory__mtitle"><?= e($f['mediaTitle']) ?></div>
        <?php if (!empty($f['mediaDescr'])): ?><div class="factory__mdescr"><?= e($f['mediaDescr']) ?></div><?php endif; ?>
      </div>
    </div>
    <div class="gallery">
<?php foreach ($f['photos'] as $ph): ?>
      <a class="gallery__i js-zoom" href="<?= e(timg($ph)) ?>" style="background-image:url('<?= e(timg($ph)) ?>')" aria-label="Увеличить фото"></a>
<?php endforeach; ?>
    </div>
  </div>
</section>
<?php if (!empty($f['models'])): ?>
<section class="sec" data-pick="kase-<?= e($slug) ?>-<?= (int)$i ?>" data-pick-name="<?= e($f['name']) ?>">
  <div class="wrap kases">
    <h2 class="whs__title">ТОВАРЫ, ДОСТУПНЫЕ К ЗАКАЗУ</h2>
    <p class="whs__lead">Все товары ниже можно заказать напрямую с фабрики. Цены зависят от модели, количества и доставки — пришлите список, и мы подготовим предложение.</p>
<?php foreach ($f['models'] as $g): ?>
    <h3 class="pcr-group"><?= e($g[0]) ?></h3>
    <p class="pcr-group__sub"><?= e($g[1]) ?></p>
    <div class="pcr-grid">
<?php foreach ($g[2] as $m): ?>
      <div class="pcr"><div class="pcr__img gal">
          <div class="gal__track">
<?php foreach ($m[0] as $ph): ?>
            <img src="<?= e(img('goodcen/' . $ph . '.jpg')) ?>" alt="<?= e($m[1]) ?>" loading="lazy">
<?php endforeach; ?>
          </div>
<?php if (count($m[0]) > 1): ?>
          <button type="button" class="gal__btn gal__btn--prev" aria-label="Предыдущее фото"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M15.4 7.4 14 6l-6 6 6 6 1.4-1.4-4.6-4.6z" fill="currentColor"/></svg></button>
          <button type="button" class="gal__btn gal__btn--next" aria-label="Следующее фото"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M8.6 16.6 10 18l6-6-6-6-1.4 1.4 4.6 4.6z" fill="currentColor"/></svg></button>
          <div class="gal__dots"><?php foreach ($m[0] as $n => $ph): ?><i<?= $n ? '' : ' class="on"' ?>></i><?php endforeach; ?></div>
<?php endif; ?>
        </div>
        <div class="pcr__body"><span class="pcr__tag">Под заказ</span><h4><?= e($m[1]) ?></h4><p><?= e($m[2]) ?></p></div></div>
<?php endforeach; ?>
    </div>
<?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<section class="sec">
  <div class="wrap cta">
    <p>Хотите сотрудничать с этой фабрикой? Мы поможем!<br>Оставьте заявку на консультацию — мы перезвоним и расскажем все подробности.</p>
    <a href="#vopros" class="btn btn--green btn--xl js-pop">Заказать консультацию</a>
  </div>
</section>
<div class="wrap"><hr class="sep"></div>
<?php endforeach; ?>
</main>
<?php require ROOT . '/inc/foot.php'; ?>
