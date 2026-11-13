<?php
$title = 'Карго из Китая от 1 $ за кг — доставка от 1 кг в Москву | компания ИТК';
$descr = 'Карго-доставка из Китая в Москву от 1 кг, цена от 1 $ за 1 кг. Выкуп с 1688, Taobao, Pinduoduo и фабрик, свой склад в Фошане, упаковка от 10 ¥, страховка от 1%. Рассчитаем за 30 минут.';
$bodyClass = 'inner';
$crumb = 'Карго из Китая';
$canonical = '/kargo-iz-kitaya';
$ogImage = 'cargo/sklad-1.jpg';
$cargoFaq = [
  ['Сколько стоит карго-доставка из Китая?', 'От 1 $ за 1 кг. Итоговая цена зависит от товара и плотности груза: лёгкий объёмный груз считается по объёму, плотный — по весу. Пришлите описание, вес и объём — рассчитаем точную стоимость.'],
  ['Какой минимальный вес груза?', 'Принимаем грузы от 1 килограмма. Можно отправить одну посылку или собрать сборный груз от разных продавцов.'],
  ['Вы выкупаете товары с 1688, Taobao и Pinduoduo?', 'Да. Выкупаем с 1688, Taobao, Pinduoduo и любых других китайских площадок, а также напрямую с фабрик. Вы присылаете ссылки — мы оплачиваем, проверяем и принимаем товар на склад.'],
  ['Где находится ваш склад в Китае?', 'Наш склад находится в Фошане, провинция Гуандун, рядом с Гуанчжоу. Адрес для отправки груза указан на этой странице — его можно скопировать и передать продавцу.'],
  ['Сколько стоит упаковка груза?', 'Обмотка лентой — 10 ¥ за место, водонепроницаемая упаковка — 3 $ за место, защитные уголки — 4 $ за штуку, защитные рёбра — 6 $ за штуку, деревянная обрешётка — 10 $ за место, деревянная фиксация с защитной обмоткой — 30 $ за место.'],
  ['Как застраховать груз?', 'Страховка зависит от стоимости груза в пересчёте на 1 кг: до 50 $/кг — 1%, от 50 до 100 $/кг — 2%, от 100 до 200 $/кг — 3%. Например, груз 100 кг стоимостью 4 000 $: 40 $/кг, страховка 1% = 40 $.'],
  ['Куда вы доставляете груз?', 'Доставляем груз из Китая до Москвы. Способ доставки и сроки подбираем под ваш товар, вес и бюджет.'],
];
$jsonld = [
  ['@context' => 'https://schema.org', '@type' => 'Service',
   'name' => 'Карго из Китая от компании ИТК', 'serviceType' => 'Карго-доставка грузов из Китая',
   'description' => $descr, 'areaServed' => 'RU',
   'provider' => ['@type' => 'Organization', 'name' => 'ООО «Интернациональная Торговая Компания»', 'url' => 'https://itcpartner.ru', 'telephone' => $cfg['phone1_raw']],
   'offers' => ['@type' => 'Offer', 'priceCurrency' => 'USD', 'price' => '1',
                'priceSpecification' => ['@type' => 'UnitPriceSpecification', 'price' => '1', 'priceCurrency' => 'USD', 'unitText' => 'кг', 'minPrice' => '1']]],
  ['@context' => 'https://schema.org', '@type' => 'FAQPage',
   'mainEntity' => array_map(function ($q) { return ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]]; }, $cargoFaq)],
  ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => 'https://itcpartner.ru/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Карго из Китая', 'item' => 'https://itcpartner.ru/kargo-iz-kitaya']]],
];
require ROOT . '/inc/head.php';
$cards = [
  ['srv-1.png',   'Выкуп с 1688 и всех площадок', 'Выкупаем товар с 1688, Taobao, Pinduoduo и любых других китайских электронных площадок. Вы присылаете ссылки — мы оплачиваем, проверяем и принимаем на склад.'],
  ['cap-2.jpg',   'Выкуп напрямую с фабрик', 'Договоримся с фабрикой, оплатим в юанях и заберём товар. Подходит и для небольших партий, и для крупных заказов.'],
  ['cargo/sklad-1.jpg', 'Склады в Гуанчжоу и Фошане', 'Принимаем грузы от поставщиков на собственные склады в Гуанчжоу и Фошане. Собираем товары от разных продавцов в одну отправку.'],
  ['cargo/upakovka.jpg', 'Проверка и упаковка', 'Проверяем количество и комплектацию, при необходимости переупаковываем: коробки, обрешётка, защита хрупких товаров. Фото и видео отчёт.'],
  ['cargo/sklad-2.jpg', 'Страховка груза', 'Страхуем груз на время перевозки — от 1% стоимости. Вы спокойны за товар на всём пути из Китая.'],
  ['cap-6.jpeg',  'Доставка до Москвы', 'Везём груз из Китая до Москвы. Способ и сроки подберём под ваш товар, вес и бюджет.'],
];
?>
<main class="page">
<?php require ROOT . '/inc/crumbs.php'; ?>

<section class="sec">
  <div class="wrap intro">
    <h1 class="intro__title">КАРГО ИЗ КИТАЯ ОТ КОМПАНИИ ИТК — ДОСТАВКА ОТ 1 КГ</h1>
    <p class="intro__text intro__text--n">Возим любые объёмы — от одной посылки до сборного груза. Выкупим товар на китайских площадках или напрямую на фабрике, примем на свой склад, проверим, упакуем, застрахуем и доставим до Москвы.</p>
  </div>
</section>

<section class="sec">
  <div class="wrap cargo-price">
    <div class="cargo-price__main">
      <span class="cargo-price__from">от</span>
      <span class="cargo-price__num">1 $</span>
      <span class="cargo-price__unit">за 1 кг</span>
    </div>
    <div class="cargo-price__text">
      <p><b>Принимаем грузы от 1 килограмма.</b></p>
      <p>Итоговая цена зависит от товара и плотности груза. Пришлите описание, вес и объём — рассчитаем стоимость под ваш груз.</p>
      <a href="#raschet-form" class="btn btn--green btn--lg js-pop" data-goal="cargo_calc">Рассчитать доставку</a>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap czcards czcards--3">
<?php foreach ($cards as $c): ?>
    <div class="czcard">
      <div class="czcard__img" style="background-image:url('<?= e(img($c[0])) ?>')"><h3><?= e($c[1]) ?></h3></div>
      <p><?= e($c[2]) ?></p>
    </div>
<?php endforeach; ?>
  </div>
</section>

<section class="sec">
  <div class="wrap addr-box">
    <img class="addr-box__photo" src="<?= e(img('cargo/sklad-1.jpg')) ?>" alt="Склад компании ИТК в Фошане: паллеты с коробками ITC" loading="lazy" width="900" height="675">
    <div class="addr-box__body">
      <h2 class="intro__title">АДРЕС СКЛАДА ДЛЯ ОТПРАВКИ ГРУЗА</h2>
      <p class="addr-box__hint">Укажите этот адрес продавцу на 1688, Taobao или фабрике — груз придёт на наш склад в Фошане.</p>
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

<?php
$packs = [
  ['Обмотка прозрачной или жёлтой лентой', 'Базовая защита коробок.', '10 ¥', 'за место'],
  ['Водонепроницаемая упаковка', 'Защита от влаги и загрязнений.', '3 $', 'за место'],
  ['Защитные уголки', 'Снижают риск повреждения углов.', '4 $', 'за штуку'],
  ['Защитные рёбра', 'Усиливают боковые стороны и кромки.', '6 $', 'за штуку'],
  ['Деревянная обрешётка', 'Для хрупких и нестандартных грузов.', '10 $', 'за место'],
  ['Деревянная фиксация и защитная обмотка', 'Максимальная защита для крупного груза.', '30 $', 'за место'],
];
?>
<section class="sec cargo-pack">
  <div class="wrap">
    <h2 class="intro__title center">УПАКОВКА И СТРАХОВАНИЕ</h2>
    <p class="intro__text intro__text--n center cargo-sub">Надёжная упаковка для безопасной доставки. Выберите вариант под ваш груз — или мы подскажем сами.</p>
    <div class="packs">
<?php foreach ($packs as $i => $p): ?>
      <div class="pack">
        <div class="pack__img" style="background-image:url('<?= e(img('cargo/pack-' . ($i + 1) . '.jpg')) ?>')"><span class="pack__n"><?= $i + 1 ?></span></div>
        <div class="pack__body">
          <div><h3><?= e($p[0]) ?></h3><p><?= e($p[1]) ?></p></div>
          <div class="pack__price"><b><?= e($p[2]) ?></b><span><?= e($p[3]) ?></span></div>
        </div>
      </div>
<?php endforeach; ?>
    </div>

    <div class="insure">
      <div class="insure__head">
        <svg viewBox="0 0 24 24" width="56" height="56" aria-hidden="true"><path fill="currentColor" d="M12 1 3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg>
        <div><h3>Страхование груза</h3><p>Защита от непредвиденных ситуаций. Процент зависит от стоимости груза в пересчёте на 1 кг.</p></div>
      </div>
      <div class="insure__rates">
        <div><span>до 50 $/кг</span><b>1%</b></div>
        <div><span>50–100 $/кг</span><b>2%</b></div>
        <div><span>100–200 $/кг</span><b>3%</b></div>
      </div>
      <div class="insure__ex">
        <b>Пример</b>
        <span>Груз 100 кг стоимостью 4 000 $</span>
        <span>4 000 ÷ 100 = 40 $/кг</span>
        <span>Страховка 1% = 40 $</span>
      </div>
    </div>
  </div>
</section>

<?php
$advItems = [
  ['opyt.svg',          'ОТ 1 КГ',        'Возим даже небольшие партии.<br>Цена от 1 $ за 1 кг.'],
  ['adv-logistics.svg', 'СВОИ СКЛАДЫ',    'Склады в Гуанчжоу и Фошане.'],
  ['adv-links.svg',     'ВЫКУП',          'С 1688 и всех китайских площадок, а также напрямую с фабрик.'],
  ['adv-reliable.svg',  'УПАКОВКА',       'Шесть вариантов защиты груза — от обмотки лентой до деревянной обрешётки. Фото и видео отчёт.'],
  ['adv-contract.svg',  'СТРАХОВКА',      'Страхуем груз на время перевозки — от 1% стоимости.'],
  ['adv-support.svg',   'ДО МОСКВЫ',      'Доставка до Москвы. Личный менеджер на связи на каждом этапе.'],
];
$advBottom = 'Закрываем всю китайскую часть: от выкупа товара до получения груза в Москве';
require ROOT . '/inc/adv.php';

$ctaText = 'Пришлите ссылки на товары или описание груза — рассчитаем стоимость выкупа и доставки и подберём самый выгодный вариант.';
require ROOT . '/inc/cta.php';
?>

<section class="sec">
  <div class="wrap intro">
    <h2 class="intro__title">КАК МЫ РАБОТАЕМ</h2>
    <div class="intro__text intro__text--n">
      <ol>
        <li>Вы присылаете ссылки на товары с 1688 и других площадок или контакты фабрики.</li>
        <li>Мы выкупаем товар и принимаем его на наш склад в Гуанчжоу или Фошане.</li>
        <li>Проверяем количество и комплектацию, упаковываем, присылаем фото и видео отчёт.</li>
        <li>Страхуем груз и отправляем его в Россию.</li>
        <li>Вы получаете груз в Москве.</li>
      </ol>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap intro seo-text">
    <h2 class="intro__title">КАРГО ИЗ КИТАЯ В МОСКВУ: КАК ЭТО РАБОТАЕТ</h2>
    <div class="intro__text intro__text--n">
      <p>Карго-доставка — удобный способ привезти товары из Китая без лишних хлопот: вы не разбираетесь с китайскими продавцами, оплатой в юанях и поиском перевозчика. Компания ИТК берёт всё на себя — от выкупа товара до доставки груза в Москву.</p>
      <p>Мы выкупаем товары на <b>1688, Taobao, Pinduoduo</b> и других китайских площадках, а также напрямую у фабрик. Товары от разных продавцов собираем на нашем складе в Фошане (провинция Гуандун, рядом с Гуанчжоу), проверяем количество и комплектацию, при необходимости переупаковываем и отправляем одним сборным грузом.</p>
      <p>Возим грузы <b>от 1 кг</b>, стоимость доставки — <b>от 1 $ за 1 кг</b> и зависит от товара и плотности груза. Подходит и для селлеров маркетплейсов, и для магазинов, и для компаний, которые закупают оборудование и комплектующие в Китае.</p>
    </div>
  </div>
</section>

<section class="sec faq-sec cargo-faq">
  <div class="wrap faq">
    <h2 class="intro__title">ВОПРОСЫ И ОТВЕТЫ</h2>
<?php foreach ($cargoFaq as $i => $q): ?>
    <div class="faqi<?= $i === 0 ? ' open' : '' ?>">
      <button type="button" class="faqi__q"><span><?= e($q[0]) ?></span><i></i></button>
      <div class="faqi__a"><p><?= e($q[1]) ?></p></div>
    </div>
<?php endforeach; ?>
  </div>
</section>

<?php require ROOT . '/inc/form-question.php'; ?>
</main>
<?php require ROOT . '/inc/foot.php'; ?>
