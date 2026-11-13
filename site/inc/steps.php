<?php
// Блок «Этапы работы». Тексты можно поменять переменными $st1, $st2, $st3, $stTitle, $stNums
$stTitle = $stTitle ?? 'ЭТАПЫ РАБОТЫ';
$st1 = $st1 ?? 'Мы получаем от вас задачу: какой товар нужен, в какой категории, с какими характеристиками и объёмами. Определяем сроки, бюджет и условия работы.';
$st2 = $st2 ?? 'Проводим поиск производителей и поставщиков в Китае. Проверяем их надёжность, договариваемся о ценах и условиях. При необходимости заказываем образцы для проверки качества.';
$st3 = $st3 ?? 'Доставка до клиента';
$stNums = $stNums ?? true;
?>
<section class="sec" id="steps">
  <div class="zb" style="height:1310px">
    <svg class="a circles" style="<?= at(70, 155, 700, 700) ?>" viewBox="0 0 700 700" aria-hidden="true"><circle cx="350" cy="350" r="349.5" fill="none" stroke="#d8f0f3"/></svg>
    <svg class="a circles" style="<?= at(1093, 621, 317, 317) ?>" viewBox="0 0 317 317" aria-hidden="true"><circle cx="158.5" cy="158.5" r="158" fill="none" stroke="#d8f0f3"/></svg>
    <h2 class="a h65" style="<?= at(620, 103, 560) ?>"><?= e($stTitle) ?></h2>
    <div class="a photo photo--r5" style="<?= at(0, 213, 580, 219) ?>background-image:url('<?= e(img('step-1.png')) ?>')"></div>
    <h3 class="a h30" style="<?= at(620, 209, 400) ?>">ЭТАП 1: ЗАПРОС</h3>
    <p class="a t18" style="<?= at(620, 264, 520) ?>"><?= $st1 ?></p>
    <p class="a t14up" style="<?= at(620, 371, 520) ?>">Анализ запроса<br>Подбор товарных групп<br>Определение критериев качества</p>
    <h3 class="a h30" style="<?= at(20, 498, 520) ?>">ЭТАП 2: ПОИСК И ПОДГОТОВКА</h3>
    <p class="a t18" style="<?= at(20, 557, 560) ?>"><?= $st2 ?></p>
<?php if ($stNums): ?>
    <div class="a nums" style="<?= at(20, 675, 560) ?>">
      <div><b>10</b><span>[дней поиска]</span></div>
      <div><b>5</b><span>[проверок поставщиков]</span></div>
      <div><b>8</b><span>[этапов согласования]</span></div>
    </div>
<?php endif; ?>
    <p class="a plus-line plus-line--sm" style="<?= at(21, 765, 400) ?>"><span class="plus">+</span>ТОЛЬКО ПРОВЕРЕННЫЕ ПОСТАВЩИКИ</p>
    <div class="a photo photo--r5" style="<?= at(620, 493, 260, 286) ?>background-image:url('<?= e(img('step-2a.png')) ?>')"></div>
    <div class="a photo photo--r5" style="<?= at(920, 493, 260, 286) ?>background-image:url('<?= e(img('step-2b.png')) ?>')"></div>
    <div class="a photo" style="<?= at(0, 853, 1200, 396) ?>background-image:url('<?= e(img('step-3.png')) ?>')"></div>
    <div class="a step3" style="<?= at(520, 903, 620) ?>">
      <h3>ЭТАП 3: РЕАЛИЗАЦИЯ</h3>
      <p>Заключаем договор, контролируем производство, делаем фото- и видеоотчёты. Консолидируем груз на нашем складе в Гуанчжоу и организуем доставку в Россию.</p>
      <p>Контроль качества<br>Брендирование и маркировка<br><?= e($st3) ?></p>
    </div>
  </div>
</section>

