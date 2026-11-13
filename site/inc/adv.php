<?php
// Блок «Преимущества работы с нами».
// $advItems: [иконка, заголовок, текст]; $advNums: показывать [01]…; $advBottom: текст внизу
$advNums = $advNums ?? true;
?>
<section class="sec adv-sec">
  <svg class="adv__circles" viewBox="0 0 607 607" aria-hidden="true"><g fill="none" stroke="#d8f0f3"><circle cx="303.5" cy="303.5" r="303"/><circle cx="319.5" cy="268.5" r="203"/></g></svg>
  <div class="wrap adv">
    <h2 class="h60">ПРЕИМУЩЕСТВА РАБОТЫ С НАМИ</h2>
    <div class="adv__grid">
<?php foreach ($advItems as $i => $a): ?>
      <div class="advi">
        <img src="<?= e(img($a[0])) ?>" alt="" width="60" height="60">
        <div>
          <h3><?= e($a[1]) ?><?php if ($advNums): ?> <sup>[0<?= $i + 1 ?>]</sup><?php endif; ?></h3>
          <p><?= $a[2] ?></p>
        </div>
      </div>
<?php endforeach; ?>
    </div>
    <div class="adv__bottom">
      <img class="dots" src="<?= e(img('dots.svg')) ?>" alt="" width="100" height="35">
      <p class="plus-line plus-line--big"><span class="plus">+</span><?= e($advBottom) ?></p>
    </div>
  </div>
</section>
