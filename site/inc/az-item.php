<?php
// Карточка одной позиции полного каталога фабрики.
// Нужны: $it = [раздел, название, фото, [номера фабрики], [кросс-номера], [ремкомплекты]], $azDir, $f (фабрика)
// пометка к номеру («длинный», «короткий») — отдельным словом, чтобы её можно было перевести
if (!function_exists('az_ref')) { function az_ref($r) { return preg_replace('~ \((\p{Cyrillic}+)\)$~u', ' <small>(<i>$1</i>)</small>', e($r)); } }
$L = ($azCatalog['labels'] ?? []) + ['no' => 'Номер фабрики', 'ref' => 'Кросс-номер', 'refs' => 'Кросс-номера', 'kit' => 'Ремкомплект', 'm' => 'Модели', 'r' => 'Примечание', 'b' => 'Подходит для'];
$azExt = '.' . ($azCatalog['ext'] ?? 'jpg');
// позиция с таблицей вариантов (артикул, размер, разболтовка, цвет) — своя карточка
if (!empty($it[6][0])) { require ROOT . '/inc/az-item-rows.php'; return; }
// дополнительные строки позиции: модели машин, примечание, марки
$itMore = isset($it[6]) && is_array($it[6]) ? $it[6] : [];
[$itCat, $itName, $itImg, $itNo, $itRef, $itKit] = $it;
$itPairs = count($itNo) === count($itRef);
$itAsk = $f['name'] . ' ' . ($itNo || $itRef ? implode(', ', $itNo) . ($itRef ? ' / ' . implode(', ', $itRef) : '') : implode(', ', $itKit ?: (array)$itImg));
?>
      <div class="pcr azi" id="n-<?= e(strtolower(preg_replace('~[^A-Za-z0-9]~', '', $itNo[0] ?? ((array)$itImg)[0]))) ?>">
<?php $itImgs = (array)$itImg; if (count($itImgs) > 1): ?>
        <div class="pcr__img gal">
          <div class="gal__track">
<?php foreach ($itImgs as $ph): ?>
            <img src="<?= e(img($azDir . '/c/' . $ph . $azExt)) ?>" alt="<?= e($itName) ?>" loading="lazy">
<?php endforeach; ?>
          </div>
          <button type="button" class="gal__btn gal__btn--prev" aria-label="Предыдущее фото"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M15.4 7.4 14 6l-6 6 6 6 1.4-1.4-4.6-4.6z" fill="currentColor"/></svg></button>
          <button type="button" class="gal__btn gal__btn--next" aria-label="Следующее фото"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M8.6 16.6 10 18l6-6-6-6-1.4 1.4 4.6 4.6z" fill="currentColor"/></svg></button>
          <div class="gal__dots"><?php foreach ($itImgs as $n => $ph): ?><i<?= $n ? '' : ' class="on"' ?>></i><?php endforeach; ?></div>
        </div>
<?php else: ?>
        <div class="pcr__img"><img src="<?= e(img($azDir . '/c/' . $itImgs[0] . $azExt)) ?>" alt="<?= e($itName) ?>" loading="lazy"></div>
<?php endif; ?>
        <div class="pcr__body">
          <h4><?= e($itName) ?> <?php if ($itNo): ?><span class="azi__no"><?= e(preg_replace('~\(\w+\)$~', '', $itNo[0])) ?></span><?php endif; ?></h4>
<?php if ($itPairs && $itRef): ?>
          <table class="azi__nums"><tr><th><?= e($L['no']) ?></th><th><?= e($L['ref']) ?></th></tr>
<?php foreach ($itNo as $n => $no): ?>
            <tr><td><?= e($no) ?></td><td><?= az_ref($itRef[$n]) ?></td></tr>
<?php endforeach; ?>
          </table>
<?php else: ?>
<?php if ($itNo): ?>
          <p class="azi__line"><span><?= e($L['no']) ?>:</span> <?= e(implode(', ', $itNo)) ?></p>
<?php endif; ?>
<?php if ($itRef): ?>
          <p class="azi__line"><span><?= e($L['refs']) ?>:</span> <?= implode(', ', array_map('az_ref', $itRef)) ?></p>
<?php endif; ?>
<?php endif; ?>
<?php if ($itKit): ?>
          <p class="azi__line"><span><?= e($L['kit']) ?>:</span> <?= e(implode(', ', $itKit)) ?></p>
<?php endif; ?>
<?php foreach (['m', 'r', 'b'] as $k): if (!empty($itMore[$k])): ?>
          <p class="azi__line"><span><?= e($L[$k]) ?>:</span> <?= e($itMore[$k]) ?></p>
<?php endif; endforeach; ?>
          <a class="azi__ask" href="#vopros" data-q="<?= e($itAsk) ?>">Узнать цену</a>
        </div>
      </div>
