<?php
$title = 'Автозапчасти из Китая напрямую с заводов | ИТК';
$descr = 'Поставки автозапчастей из Китая напрямую с фабрик: тормозные системы, радиаторы, двигатели, рулевые рейки, рычаги подвески, фары и обвесы. Проверенные заводы, официальный ввоз, документы.';
$bodyClass = 'inner';
$crumb = 'Автозапчасти из Китая';
$canonical = '/avtozapchasti-iz-kitaya';
$ogImage = 'az/hero.jpg';

// Что везём: название, описание
$azWhat = [
  ['Тормозные системы', 'Пневматика для грузовиков и прицепов, в том числе для КамАЗ.'],
  ['Радиаторы и охлаждение', 'Радиаторы, интеркулеры, конденсаторы кондиционера.'],
  ['Двигатели и ГБЦ', 'Двигатели в сборе, головки блока, валы, турбины.'],
  ['Рулевые рейки', 'Электрические и гидравлические, детали для ремонта реек.'],
  ['Рычаги подвески', 'Нижние и верхние рычаги для легковых машин и пикапов.'],
  ['Фары и фонари', 'Светодиодная оптика, в том числе для Lada, ГАЗ, УАЗ, КамАЗ.'],
  ['Обвесы и кузовные детали', 'Обвесы для BMW и Mercedes-Benz, детали для грузовиков.'],
  ['Оборудование для СТО', 'Шиномонтаж, подъёмники, пусковые и зарядные устройства, сварка.'],
];

$azFactories = require ROOT . '/inc/az-factories.php';

$azFaq = [
  ['Как отправить список запчастей?', 'Пришлите OEM-номера, кросс-номера или фото старой детали — в форме на сайте, в Telegram или Excel-файлом на почту. Мы подберём завод под каждую позицию.'],
  ['Вы работаете с фабриками напрямую?', 'Да. У нас свой офис и склад в Китае, мы сами общаемся с заводами на китайском, без перекупщиков. С фабриками на этой странице лично встречались на Кантонской ярмарке.'],
  ['Как вы проверяете завод?', 'Смотрим лицензию и сертификаты, запрашиваем видео производства, при необходимости едем на завод.'],
  ['Как проверяется товар?', 'Партия приходит на наш склад в Гуанчжоу. Сверяем номера и количество, проверяем на брак и присылаем фото.'],
  ['Какие документы я получу?', 'При белой поставке — договор, оплата по безналу в России, закрывающие документы и сертификаты соответствия. Если удобнее, можем привезти карго.'],
  ['Сколько стоит?', 'Цена зависит от позиции, количества и доставки. Запрашиваем цены у 2–3 фабрик, сравниваем и показываем вам.'],
];

$jsonld = [
  ['@context' => 'https://schema.org', '@type' => 'Service',
   'name' => 'Поставка автозапчастей из Китая напрямую с заводов', 'serviceType' => 'Поставка автозапчастей из Китая',
   'description' => $descr, 'areaServed' => 'RU',
   'provider' => ['@type' => 'Organization', 'name' => 'ООО «Интернациональная Торговая Компания»', 'url' => 'https://itcpartner.ru', 'telephone' => $cfg['phone1_raw']]],
  ['@context' => 'https://schema.org', '@type' => 'FAQPage',
   'mainEntity' => array_map(function ($q) { return ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]]; }, $azFaq)],
  ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => 'https://itcpartner.ru/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Автозапчасти из Китая', 'item' => 'https://itcpartner.ru/avtozapchasti-iz-kitaya']]],
];
require ROOT . '/inc/head.php';
?>
<main class="page">
<?php require ROOT . '/inc/crumbs.php'; ?>

<section class="sec">
  <div class="wrap whs whs--rev">
    <div class="whs__text">
      <h1 class="whs__title">АВТОЗАПЧАСТИ ИЗ КИТАЯ</h1>
      <p class="whs__lead">Напрямую с заводов — для магазинов, СТО и автопарков.</p>
      <p class="az-intro">Подбираем и везём запчасти с китайских фабрик: от тормозной пневматики для грузовиков до радиаторов, рулевых реек и фар для легковых машин. Работаем по OEM-номерам и кросс-номерам, сами общаемся с заводом, проверяем товар на складе в Китае и привозим с полным пакетом документов.</p>
      <div class="az-btns">
        <a href="#vopros" class="btn btn--green btn--lg js-pop">Отправить список запчастей</a>
      </div>
    </div>
    <img class="whs__photo" src="<?= e(img('az/hero.jpg')) ?>" alt="Автозапчасти из Китая напрямую с заводов">
  </div>
</section>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">ЧТО ВЕЗЁМ</h2>
    <p class="whs__lead">Направления, по которым у нас уже есть фабрики-поставщики.</p>
    <div class="ugrid ugrid--4">
<?php foreach ($azWhat as $w): ?>
      <div class="ucard"><div><h3><?= e($w[0]) ?></h3><p><?= e($w[1]) ?></p></div></div>
<?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">ФАБРИКИ, С КОТОРЫМИ РАБОТАЕМ</h2>
    <p class="whs__lead">С каждой встречались на Кантонской ярмарке. Нажмите на фабрику, чтобы узнать о ней подробнее и посмотреть товары.</p>
    <div class="pcr-grid">
<?php foreach ($azFactories as $slug => $f): ?>
      <a class="pcr az-tile" href="/avtozapchasti-iz-kitaya/<?= e($slug) ?>"><div class="pcr__img"><img src="<?= e(img($f['thumb'])) ?>" alt="<?= e($f['name']) ?> — <?= e($f['what']) ?>" loading="lazy"></div>
        <div class="pcr__body"><h4><?= e($f['name']) ?></h4><b class="az-what"><?= e($f['what']) ?></b><span class="az-city"><?= $f['city'] !== '' ? e($f['city']) . ', ' : '' ?>Китай</span><span class="az-more"><?= isset($f['who']) ? 'Подробнее о поставщике' : 'Подробнее о фабрике' ?></span></div></a>
<?php endforeach; ?>
    </div>
    <a href="#vopros" class="btn btn--green btn--lg js-pop pcr-cta">Нужна другая запчасть? Отправьте список</a>
  </div>
  </div>
</section>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">КАК МЫ РАБОТАЕМ</h2>
    <p class="whs__lead">От списка запчастей до вашего склада.</p>
    <div class="ugrid ugrid--3">
      <div class="ucard"><div><h3>1. Присылаете список</h3><p>OEM-номера, кросс-номера или фото старой детали. Можно Excel-файлом.</p></div></div>
      <div class="ucard"><div><h3>2. Подбираем завод</h3><p>Запрашиваем цены у 2–3 фабрик, сравниваем и показываем вам.</p></div></div>
      <div class="ucard"><div><h3>3. Проверяем фабрику</h3><p>Лицензия, сертификаты, видео производства; при необходимости едем на завод.</p></div></div>
      <div class="ucard"><div><h3>4. Оплачиваем в Китай</h3><p>Вы платите в рублях российской компании, мы рассчитываемся с фабрикой.</p></div></div>
      <div class="ucard"><div><h3>5. Принимаем на складе в Гуанчжоу</h3><p>Сверяем номера и количество, проверяем на брак, фотографируем.</p></div></div>
      <div class="ucard"><div><h3>6. Привозим и оформляем</h3><p>Официальный ввоз, сертификаты соответствия, полный комплект документов. Или карго — как вам удобнее.</p></div></div>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap whs">
    <div class="whs__text">
      <h2 class="whs__title">ПОЧЕМУ С НАМИ</h2>
      <p class="whs__lead">Запчасти с завода — без перекупщиков и путаницы.</p>
      <div class="ugrid">
        <div class="ucard"><div><h3>Свой офис и склад в Китае</h3><p>Сами общаемся с фабрикой на китайском, без перекупщиков.</p></div></div>
        <div class="ucard"><div><h3>Проверяем каждый завод</h3><p>По реестрам, сертификатам и лично на выставках.</p></div></div>
        <div class="ucard"><div><h3>Работаем по номерам</h3><p>OEM и кросс-номера, без путаницы в применяемости.</p></div></div>
        <div class="ucard"><div><h3>Белая поставка</h3><p>Договор, безнал в России, закрывающие документы, сертификаты.</p></div></div>
      </div>
      <p class="whs__note">Подробнее о работе: <a href="/oplata-postavshchikam-v-kitay">оплата поставщикам в Китай</a>, <a href="/sklad-v-guanchzhou">склад в Гуанчжоу (Фошань)</a>, <a href="/kargo-iz-kitaya">карго из Китая</a>.</p>
    </div>
    <div class="whs__text">
      <h2 class="whs__title">ВОПРОСЫ И ОТВЕТЫ</h2>
<?php foreach ($azFaq as $i => $q): ?>
      <div class="faqi<?= $i === 0 ? ' open' : '' ?>">
        <button type="button" class="faqi__q"><span><?= e($q[0]) ?></span><i></i></button>
        <div class="faqi__a"><p><?= e($q[1]) ?></p></div>
      </div>
<?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec" id="question">
  <div class="wrap whs whs--form">
    <div class="whs__text">
      <h2 class="whs__title">ОТПРАВЬТЕ СПИСОК ЗАПЧАСТЕЙ — ПОСЧИТАЕМ</h2>
      <p class="whs__lead">Укажите OEM-номера, кросс-номера или названия деталей и количество.</p>
      <ul class="whs__list"><li>Большой список удобнее прислать Excel-файлом в <a href="<?= e($cfg['telegram']) ?>" target="_blank" rel="noopener">Telegram</a> или на почту <a href="mailto:<?= e($cfg['email']) ?>"><?= e($cfg['email']) ?></a>.</li></ul>
      <img class="whs__decor" src="<?= e(img('form2-decor.png')) ?>" alt="" loading="lazy">
    </div>
    <div class="fcard">
      <h3 class="fcard__title fcard__title--sm">Запросим цены у заводов и пришлём предложение</h3>
      <form class="lead-form" data-form="Автозапчасти (список)">
        <textarea name="question" rows="5" placeholder="Список запчастей: номера, названия, количество" required></textarea>
        <input type="text" name="name" placeholder="Ваше имя" required>
        <input type="tel" name="phone" placeholder="+7 (000) 000-00-00" required>
        <label class="agree"><input type="checkbox" required checked> <span>Ознакомлен и даю согласие на обработку персональных данных в соответствии с <a href="/privacy">политикой конфиденциальности</a> и обработкой персональных данных</span></label>
        <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
        <button type="submit" class="fbtn">Отправить</button>
        <div class="form-msg"></div>
      </form>
    </div>
  </div>
</section>
</main>
<?php require ROOT . '/inc/foot.php'; ?>
