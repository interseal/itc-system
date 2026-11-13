<?php
// Карточка позиции с таблицей вариантов (литые диски Jinma): у одной модели несколько артикулов
// $it = [раздел, название, [фото], [артикулы — для поиска], [], [], [[артикул, ширина, разболтовка, вылет, DIA, цвет], ...], модель]
$itImgs = (array)$it[2];
$itAsk = $f['name'] . ' ' . $it[1] . ' ' . $it[7];
?>
      <div class="pcr azi azi--rows" id="n-<?= e(strtolower(preg_replace('~[^A-Za-z0-9]~', '', $it[7]))) ?>">
<?php if (count($itImgs) > 1): ?>
        <div class="pcr__img gal">
          <div class="gal__track">
<?php foreach ($itImgs as $ph): ?>
            <img src="<?= e(img($azDir . '/c/' . $ph . $azExt)) ?>" alt="<?= e($it[1]) ?> <?= e($it[7]) ?>" loading="lazy">
<?php endforeach; ?>
          </div>
          <button type="button" class="gal__btn gal__btn--prev" aria-label="Предыдущее фото"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M15.4 7.4 14 6l-6 6 6 6 1.4-1.4-4.6-4.6z" fill="currentColor"/></svg></button>
          <button type="button" class="gal__btn gal__btn--next" aria-label="Следующее фото"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M8.6 16.6 10 18l6-6-6-6-1.4 1.4 4.6 4.6z" fill="currentColor"/></svg></button>
          <div class="gal__dots"><?php foreach ($itImgs as $n => $ph): ?><i<?= $n ? '' : ' class="on"' ?>></i><?php endforeach; ?></div>
        </div>
<?php else: ?>
        <div class="pcr__img"><img src="<?= e(img($azDir . '/c/' . $itImgs[0] . $azExt)) ?>" alt="<?= e($it[1]) ?> <?= e($it[7]) ?>" loading="lazy"></div>
<?php endif; ?>
        <div class="pcr__body">
          <h4><?= e($it[1]) ?> <span class="azi__no"><?= e($it[7]) ?></span></h4>
          <table class="azi__nums azi__rows"><tr><th><?= e($L['no']) ?></th><th><?= e($L['ref']) ?></th></tr>
<?php foreach ($it[6] as $rn => $r): // строка = вариант (артикул, цвет): галочку и количество ставят напротив неё ?>
            <tr class="pick-tr" data-pk="<?= e($r[0] !== '' ? $r[0] : preg_replace('~\s+~', '-', $it[7]) . '-' . $rn) ?>"><td><b class="r-sku"><?= $r[0] !== '' ? e($r[0]) : '—' ?></b><br><small class="r-w"><?= e($r[1]) ?></small></td><td><span class="r-p"><span class="nw"><?= e($r[2]) ?></span> · <span class="nw">ET<?= e($r[3]) ?></span> · <span class="nw">DIA&nbsp;<?= e($r[4]) ?></span></span><br><small class="r-c"><?= e($r[5]) ?></small></td></tr>
<?php endforeach; ?>
          </table>
          <a class="azi__ask" href="#vopros" data-q="<?= e($itAsk) ?>">Узнать цену</a>
        </div>
      </div>
