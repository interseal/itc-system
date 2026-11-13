<?php
$title = 'Склад в Гуанчжоу (Фошань) — приём, проверка, хранение и консолидация грузов из Китая | ИТК';
$descr = 'Собственный склад ИТК в Гуанчжоу (Фошань): приём товаров от разных фабрик и продавцов 1688, проверка количества и брака, переупаковка и обрешётка, этикетки для WB и Ozon, длительное хранение, консолидация. Можно отправить груз своим перевозчиком.';
$bodyClass = 'inner';
$crumb = 'Склад в Гуанчжоу (Фошань)';
$canonical = '/sklad-v-guanchzhou';
$ogImage = 'cargo/sklad-2.jpg';
$skFaq = [
  ['Где находится склад?', 'В Гуанчжоу (Фошань), провинция Гуандун. Гуанчжоу и Фошань — одна агломерация и крупнейший торговый центр Китая, рядом тысячи фабрик и оптовых рынков. Адрес склада с кнопкой «Скопировать» есть на этой странице.'],
  ['Можно воспользоваться только складом, без вашей доставки?', 'Да. Примем, проверим, упакуем и подготовим груз, а забрать его может ваш перевозчик или карго. Если удобнее — отправим нашей доставкой.'],
  ['Принимаете товары от разных продавцов?', 'Да. Соберём товары от нескольких фабрик и продавцов 1688, Taobao и Pinduoduo в одну партию и отправим одним грузом — так доставка выходит дешевле.'],
  ['Как проверяете товар?', 'Пересчитываем количество, сверяем модель, цвет и размер с заказом, смотрим на явный брак и присылаем фото и видео. О расхождениях сообщаем до отправки.'],
  ['Можно хранить товар долго?', 'Да, на складе есть длительное хранение. Можно держать товар в Китае и отправлять в Россию частями, по мере продаж. Условия хранения подскажет менеджер.'],
  ['Подготовите товар для Wildberries и Ozon?', 'Да. Наклеим этикетки маркетплейсов, упакуем по их требованиям, а для товаров из обязательного перечня сделаем маркировку «Честный ЗНАК».'],
  ['Кто работает на складе?', 'Русскоязычные сотрудники — вы общаетесь с нами на русском, а с поставщиками и грузчиками мы договариваемся сами.'],
];
$jsonld = [
  ['@context' => 'https://schema.org', '@type' => 'Service',
   'name' => 'Склад в Гуанчжоу (Фошань)', 'serviceType' => 'Складские услуги в Китае: приём, проверка, хранение и консолидация грузов',
   'description' => $descr, 'areaServed' => 'RU',
   'provider' => ['@type' => 'Organization', 'name' => 'ООО «Интернациональная Торговая Компания»', 'url' => 'https://itcpartner.ru', 'telephone' => $cfg['phone1_raw']]],
  ['@context' => 'https://schema.org', '@type' => 'FAQPage',
   'mainEntity' => array_map(function ($q) { return ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]]; }, $skFaq)],
  ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => 'https://itcpartner.ru/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Склад в Гуанчжоу (Фошань)', 'item' => 'https://itcpartner.ru/sklad-v-guanchzhou']]],
];
require ROOT . '/inc/head.php';
?>
<main class="page">
<?php require ROOT . '/inc/crumbs.php'; ?>

<section class="sec">
  <div class="wrap whs whs--rev">
    <div class="whs__text">
      <h1 class="whs__title">СОБСТВЕННЫЙ СКЛАД В ГУАНЧЖОУ (ФОШАНЬ)</h1>
      <p class="whs__lead">Принимаем товары от фабрик и продавцов 1688, проверяем, упаковываем, храним и собираем в одну партию для отправки в Россию.</p>
      <ul class="whs__list">
        <li>Приём товаров от разных фабрик и продавцов.</li>
        <li>Проверка количества и брака, фото и видео.</li>
        <li>Можно только склад — отправите своим перевозчиком.</li>
        <li>Русскоязычные сотрудники.</li>
      </ul>
      <a href="#vopros" class="btn btn--green btn--lg js-pop">Узнать условия склада</a>
    </div>
    <img class="whs__photo" src="<?= e(img('cargo/sklad-2.jpg')) ?>" alt="Склад ИТК в Гуанчжоу (Фошань): приём и хранение грузов">
  </div>
</section>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">ЧТО ДЕЛАЕТ НАШ СКЛАД</h2>
    <p class="whs__lead">Вся работа с товаром в Китае — до того, как он уедет в Россию.</p>
    <div class="ugrid ugrid--3">
      <div class="ucard"><div><h3>Приём</h3><p>Принимаем грузы от фабрик, продавцов 1688, Taobao, Pinduoduo и оптовых рынков.</p></div></div>
      <div class="ucard"><div><h3>Проверка</h3><p>Пересчитываем количество, ищем брак и пересорт, присылаем фото и видео.</p></div></div>
      <div class="ucard"><div><h3>Консолидация</h3><p>Собираем товары от разных поставщиков в одну партию — доставка выходит дешевле.</p></div></div>
      <div class="ucard"><div><h3>Переупаковка</h3><p>Новые коробки, водонепроницаемая упаковка, уголки, рёбра и деревянная обрешётка.</p></div></div>
      <div class="ucard"><div><h3>Этикетки WB и Ozon</h3><p>Наклеиваем этикетки маркетплейсов и маркировку «Честный ЗНАК».</p></div></div>
      <div class="ucard"><div><h3>Длительное хранение</h3><p>Храним товар в Китае и отправляем в Россию частями, по мере продаж.</p></div></div>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap addr-box">
    <img class="addr-box__photo" src="<?= e(img('cargo/sklad-1.jpg')) ?>" alt="Склад компании ИТК в Гуанчжоу (Фошань): паллеты с коробками ITC" loading="lazy" width="900" height="675">
    <div class="addr-box__body">
      <h2 class="intro__title">АДРЕС СКЛАДА ДЛЯ ОТПРАВКИ ГРУЗА</h2>
      <p class="addr-box__hint">Укажите этот адрес продавцу на 1688, Taobao или фабрике — груз придёт на наш склад в Гуанчжоу (Фошань). Перед отправкой напишите менеджеру, чтобы мы ждали ваш груз.</p>
      <div class="addr-box__cn" id="addr-cn">广东省佛山市南海区大沥镇盐步龙涌龙二工业区16号枫莲集团一楼货梯处</div>
      <button type="button" class="btn btn--green btn--lg addr-box__copy" data-copy="addr-cn" data-goal="cargo_copy_addr">Скопировать адрес</button>
      <p class="addr-box__ru"><b>По-русски:</b> Китай, провинция Гуандун, г. Фошань, район Наньхай, посёлок Дали, Яньбу, промзона Лунъюн-2, д. 16, здание группы Fenglian, 1-й этаж.</p>
    </div>
  </div>
</section>
<script>
document.querySelectorAll('[data-goal]').forEach(function (b) {
  b.addEventListener('click', function () { if (window.ym) try { ym(window.__ymId, 'reachGoal', b.getAttribute('data-goal')); } catch (x) {} });
});
document.querySelectorAll('[data-copy]').forEach(function (b) {
  b.addEventListener('click', function () {
    var t = document.getElementById(b.getAttribute('data-copy')).textContent.trim();
    var done = function () { var o = b.textContent; b.textContent = 'Скопировано'; setTimeout(function () { b.textContent = o; }, 2000); };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(t).then(done);
    else { var a = document.createElement('textarea'); a.value = t; document.body.appendChild(a); a.select(); try { document.execCommand('copy'); done(); } catch (e) {} a.remove(); }
  });
});
</script>

<section class="sec">
  <div class="wrap whs">
    <div class="whs__text">
      <h2 class="whs__title">СТОИМОСТЬ УПАКОВКИ</h2>
      <p class="whs__lead">Упаковку подбираем под товар, чтобы груз доехал целым.</p>
      <ul class="whs__list">
        <li>Обмотка лентой — 10 ¥ за место.</li>
        <li>Водонепроницаемая упаковка — 3 $ за место.</li>
        <li>Защитные уголки — 4 $ за штуку, защитные рёбра — 6 $ за штуку.</li>
        <li>Деревянная обрешётка — 10 $ за место.</li>
        <li>Деревянная фиксация и защитная обмотка — 30 $ за место.</li>
      </ul>
      <p class="whs__note">Условия хранения и проверки зависят от объёма груза — их назовёт менеджер.</p>
    </div>
    <div class="whs__text">
      <h2 class="whs__title">ПОЧЕМУ ГУАНЧЖОУ</h2>
      <p class="whs__lead">Гуанчжоу и Фошань — единая агломерация и ключевой торговый центр Китая.</p>
      <ul class="whs__list">
        <li>Рядом тысячи фабрик и крупнейшие оптовые рынки страны.</li>
        <li>Отсюда удобно отправлять грузы в Россию авто, железной дорогой и морем.</li>
      </ul>
      <p class="whs__note">Склад работает вместе с нашими услугами: <a href="/vykup-tovarov-iz-kitaya">выкуп товаров</a> с 1688 и Taobao, <a href="/oplata-postavshchikam-v-kitay">оплата поставщикам в Китай</a>, <a href="/kargo-iz-kitaya">карго из Китая</a>, <a href="/fulfilment-v-kitae">фулфилмент для WB и Ozon</a> и <a href="/page126678496.html">маркировка «Честный ЗНАК»</a>.</p>
    </div>
  </div>
</section>

<?php
$advItems = [
  ['adv-logistics.svg', 'СВОЙ СКЛАД',    'Собственный склад в Гуанчжоу (Фошань).'],
  ['adv-links.svg',     'СОТРУДНИКИ',    'Русскоязычная команда на складе.'],
  ['adv-reliable.svg',  'КОНТРОЛЬ',      'Проверка количества и брака, фото и видео.'],
  ['opyt.svg',          'СВОБОДА',       'Можно только склад — отправка вашим перевозчиком.'],
  ['adv-support.svg',   'МАРКЕТПЛЕЙСЫ',  'Этикетки WB и Ozon, «Честный ЗНАК».'],
  ['adv-contract.svg',  'ХРАНЕНИЕ',      'Длительное хранение и отправка частями.'],
];
$advBottom = 'Ваш товар в Китае под контролем — от приёма до отправки в Россию';
require ROOT . '/inc/adv.php';
?>

<section class="sec">
  <div class="wrap whs">
    <div class="whs__text">
      <h2 class="whs__title">ВОПРОСЫ И ОТВЕТЫ</h2>
<?php foreach ($skFaq as $i => $q): ?>
      <div class="faqi<?= $i === 0 ? ' open' : '' ?>">
        <button type="button" class="faqi__q"><span><?= e($q[0]) ?></span><i></i></button>
        <div class="faqi__a"><p><?= e($q[1]) ?></p></div>
      </div>
<?php endforeach; ?>
    </div>
    <img class="whs__photo" src="<?= e(img('cargo/sklad-portrait.jpg')) ?>" alt="Склад ИТК в Гуанчжоу (Фошань)" loading="lazy">
  </div>
</section>

<?php require ROOT . '/inc/form-question.php'; ?>
</main>
<?php require ROOT . '/inc/foot.php'; ?>
