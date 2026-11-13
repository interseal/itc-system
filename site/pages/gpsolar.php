<?php
$title = 'Солнечные панели GPSOLAR напрямую с завода — N-Type, BC, P-Type 430–730 Вт и накопители энергии | ИТК';
$descr = 'Солнечные панели GPSOLAR (格普光能) напрямую с завода в Китае без посредников: двусторонние панели N-Type, BC и P-Type мощностью от 430 до 730 Вт, домашние накопители энергии. Официальный ввоз с документами, оплата в рублях.';
$bodyClass = 'inner';
$crumb = 'Солнечные панели GPSOLAR';
$crumbParent = ['/solnechnaya-energetika', 'Солнечная энергетика'];
$canonical = '/gpsolar';
$ogImage = 'gpsolar/visit.jpg';

// Модели под заказ: [фото], модель, описание
$gpGroups = [
  ['Панели N-Type', 'Двусторонние панели на ячейках N-типа: вырабатывают энергию обеими сторонами. Гарантия на панель 15 лет, на мощность 30 лет.', [
    [['n445', 'n445-2'], '430–455 Вт · GPM10TG-445', '108 ячеек, КПД до 23,3%. Размер 1722×1134×30 мм, вес 23 кг. Компактная панель для частных крыш.'],
    [['n465', 'n465-2'], '440–465 Вт · GPM12RTG-465', '96 ячеек 210R, КПД до 23,27%. Размер 1762×1134×30 мм, вес 23,5 кг.'],
    [['n505', 'n505-2'], '480–505 Вт · GPM10TG-505', '120 ячеек, КПД до 23,33%. Размер 1909×1134×30 мм, вес 25 кг.'],
    [['n605', 'n605-2'], '580–605 Вт · GPM10TG-605', '144 ячейки, КПД до 23,42%, двусторонность 80%. Размер 2278×1134×30 мм, вес 30,8 кг.'],
    [['n635', 'n635-2'], '610–635 Вт · GPM12RTG-635', '132 ячейки 210R, КПД до 23,51%. Размер 2382×1134×30 мм, вес 31,5 кг.'],
    [['n655', 'n655-2'], '630–655 Вт · GPM10TG-655', '156 ячеек, КПД до 23,43%. Размер 2465×1134×30 мм, вес 33 кг.'],
    [['n730', 'n730-2'], '710–730 Вт · GPM12TG-730', 'Самая мощная панель: 132 ячейки 210, КПД до 23,5%. Размер 2384×1303×33 мм, вес 36,5 кг. Для солнечных электростанций.'],
  ]],
  ['Панели BC', 'Технология BC: все контакты на тыльной стороне, поэтому лицевая сторона полностью работает на выработку.', [
    [['bc670', 'bc670-2'], '645–670 Вт · GPM12RCG-670', 'Двусторонняя панель на 132 ячейки, КПД до 24,8% — самый высокий в линейке. Размер 2382×1134×30 мм, вес 32,7 кг.'],
  ]],
  ['Панели P-Type', 'Проверенная технология PERC по доступной цене.', [
    [['p560', 'p560-2', 'ptype'], '540–560 Вт · GPM-560W', 'Односторонняя панель на 144 ячейки, КПД до 21,68%, стекло 3,2 мм. Размер 2278×1134×30 мм, вес 26 кг.'],
    [['p560b', 'p560b-2', 'ptype'], '540–560 Вт · GPMDG-560W', 'Двусторонняя панель на 144 ячейки, КПД до 21,68%, стекло с обеих сторон. Размер 2278×1134×30 мм, вес 30,5 кг.'],
  ]],
  ['Накопители энергии', 'Домашние аккумуляторы высокого напряжения, собираются из модулей в стойку.', [
    [['storage'], 'Накопитель GP2500W-HB', 'Модули по 2,56 кВт·ч, в стойке от 3 до 10 модулей — от 7,68 до 25,6 кВт·ч. 6000 циклов, защита IP65.'],
    [['storage'], 'Накопитель GP5000W-HC', 'Модули по 5,12 кВт·ч, в стойке от 3 до 6 модулей — от 15,36 до 30,72 кВт·ч. 6000 циклов, защита IP65.'],
  ]],
];

$gpProjects = [
  ['proj-china', 'Западный Китай — 586 МВт'],
  ['proj-india', 'Индия — 100 МВт'],
  ['proj-portugal', 'Португалия — 8 МВт'],
  ['proj-shandong', 'Шаньдун, Китай — 5,6 МВт'],
];

$gpFaq = [
  ['Вы покупаете GPSOLAR напрямую у завода?', 'Да. Мы работаем с заводом GPSOLAR напрямую, без посредников и перекупщиков.'],
  ['Какие панели выбрать?', 'Для частного дома обычно берут панели 430–505 Вт — они легче и удобнее на крыше. Для коммерческих крыш и электростанций — 580–730 Вт. Самый высокий КПД у панелей BC. Подскажем по вашему объекту.'],
  ['Сколько стоят панели?', 'Цена зависит от модели, количества и доставки. Пришлите список моделей или мощность станции — подготовим коммерческое предложение.'],
  ['Какая гарантия?', 'На панели N-Type и BC — 15 лет на изделие и 30 лет на мощность. На панели P-Type — 12 лет на изделие и до 30 лет на мощность.'],
  ['Какие документы я получу?', 'Договор, закрывающие документы, документы таможенного оформления, сертификаты или декларации соответствия. Ввозим официально, с уплатой НДС и пошлин.'],
];

$jsonld = [
  ['@context' => 'https://schema.org', '@type' => 'Service',
   'name' => 'Поставка солнечных панелей GPSOLAR напрямую с завода', 'serviceType' => 'Поставка солнечных панелей и накопителей энергии из Китая',
   'description' => $descr, 'areaServed' => 'RU',
   'provider' => ['@type' => 'Organization', 'name' => 'ООО «Интернациональная Торговая Компания»', 'url' => 'https://itcpartner.ru', 'telephone' => $cfg['phone1_raw']]],
  ['@context' => 'https://schema.org', '@type' => 'FAQPage',
   'mainEntity' => array_map(function ($q) { return ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]]; }, $gpFaq)],
  ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => 'https://itcpartner.ru/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Солнечная энергетика', 'item' => 'https://itcpartner.ru/solnechnaya-energetika'],
    ['@type' => 'ListItem', 'position' => 3, 'name' => 'Солнечные панели GPSOLAR', 'item' => 'https://itcpartner.ru/gpsolar']]],
];
require ROOT . '/inc/head.php';
?>
<main class="page" data-pick="gpsolar" data-pick-name="GPSOLAR">
<?php require ROOT . '/inc/crumbs.php'; ?>

<section class="sec">
  <div class="wrap whs whs--rev pcr-hero">
    <div class="whs__text">
      <h1 class="whs__title">СОЛНЕЧНЫЕ ПАНЕЛИ GPSOLAR НАПРЯМУЮ С ЗАВОДА</h1>
      <p class="whs__lead">Солнечные панели N-Type, BC и P-Type мощностью от 430 до 730 Вт и домашние накопители энергии. Покупаем напрямую у завода в Китае — без посредников и лишних наценок.</p>
      <ul class="whs__list">
        <li>Прямая работа с заводом GPSOLAR.</li>
        <li>Двусторонние панели с КПД до 24,8%.</li>
        <li>Гарантия на мощность 30 лет.</li>
        <li>Официальный ввоз: оплата в рублях, сертификаты, полный пакет документов.</li>
      </ul>
      <a href="#vopros" class="btn btn--green btn--lg js-pop">Запросить цены на GPSOLAR</a>
    </div>
    <img class="whs__photo" src="<?= e(img('gpsolar/visit.jpg')) ?>" alt="Директор ИТК на стенде GPSOLAR">
  </div>
</section>

<section class="sec">
  <div class="wrap whs pcr-about">
    <div class="whs__text">
      <h2 class="whs__title">О ЗАВОДЕ GPSOLAR</h2>
      <p class="whs__lead">GPSOLAR (格普光能) — торговая марка компании Zhejiang G&amp;P Sun Energy Technology из провинции Чжэцзян. Компания разрабатывает и выпускает солнечные панели и системы накопления энергии.</p>
      <ul class="whs__list">
        <li>3 производственные площадки, мощность 5 ГВт панелей в год.</li>
        <li>Больше 15 лет опыта, поставки в 80 с лишним стран.</li>
        <li>Автоматизированные линии и цифровой контроль производства.</li>
        <li>Сертификаты ISO 9001, ISO 14001, ISO 45001, TÜV, CE, IEC, CB и другие.</li>
        <li>Входит в мировой рейтинг 100 лучших брендов солнечных панелей.</li>
      </ul>
    </div>
    <img class="whs__photo" src="<?= e(img('gpsolar/factory.jpg')) ?>" alt="Головной офис GPSOLAR">
  </div>
</section>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">МОДЕЛИ, ДОСТУПНЫЕ К ЗАКАЗУ</h2>
    <p class="whs__lead">Все модели ниже можно заказать сейчас. Цены зависят от модели, количества и доставки — пришлите список, и мы подготовим предложение.</p>
<?php foreach ($gpGroups as $g): ?>
    <h3 class="pcr-group"><?= e($g[0]) ?></h3>
    <p class="pcr-group__sub"><?= e($g[1]) ?></p>
    <div class="pcr-grid">
<?php foreach ($g[2] as $m): ?>
      <div class="pcr"><div class="pcr__img gal">
          <div class="gal__track">
<?php foreach ($m[0] as $f): ?>
            <img src="<?= e(img('gpsolar/' . $f . '.jpg')) ?>" alt="<?= e($m[1]) ?>" loading="lazy">
<?php endforeach; ?>
          </div>
<?php if (count($m[0]) > 1): ?>
          <button type="button" class="gal__btn gal__btn--prev" aria-label="Предыдущее фото"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M15.4 7.4 14 6l-6 6 6 6 1.4-1.4-4.6-4.6z" fill="currentColor"/></svg></button>
          <button type="button" class="gal__btn gal__btn--next" aria-label="Следующее фото"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M8.6 16.6 10 18l6-6-6-6-1.4 1.4 4.6 4.6z" fill="currentColor"/></svg></button>
          <div class="gal__dots"><?php foreach ($m[0] as $n => $f): ?><i<?= $n ? '' : ' class="on"' ?>></i><?php endforeach; ?></div>
<?php endif; ?>
        </div>
        <div class="pcr__body"><span class="pcr__tag">Под заказ</span><h4><?= e($m[1]) ?></h4><p><?= e($m[2]) ?></p></div></div>
<?php endforeach; ?>
    </div>
<?php endforeach; ?>
    <a href="#vopros" class="btn btn--green btn--lg js-pop pcr-cta">Получить предложение по моделям</a>
  </div>
</section>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">ПРОЕКТЫ НА ПАНЕЛЯХ GPSOLAR</h2>
    <p class="whs__lead">Электростанции и крыши предприятий в Китае и других странах.</p>
    <div class="pcr-grid">
<?php foreach ($gpProjects as $pj): ?>
      <div class="pcr pcr--nopick"><div class="pcr__img" style="padding:0"><img src="<?= e(img('gpsolar/' . $pj[0] . '.jpg')) ?>" alt="<?= e($pj[1]) ?>" loading="lazy" style="object-fit:cover"></div>
        <div class="pcr__body"><h4><?= e($pj[1]) ?></h4></div></div>
<?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">КОМУ ПОДОЙДЁТ</h2>
    <p class="whs__lead">Панели для частных домов, бизнеса и электростанций.</p>
    <div class="ugrid ugrid--3">
      <div class="ucard"><div><h3>Частные дома и дачи</h3><p>Своё электричество и меньше счетов, вместе с инвертором и аккумуляторами.</p></div></div>
      <div class="ucard"><div><h3>Предприятия и склады</h3><p>Станции на крышах: снижают затраты на электроэнергию.</p></div></div>
      <div class="ucard"><div><h3>Сельское хозяйство</h3><p>Питание ферм, теплиц и насосов вдали от сетей.</p></div></div>
      <div class="ucard"><div><h3>Монтажные компании</h3><p>Панели партиями под проекты клиентов.</p></div></div>
      <div class="ucard"><div><h3>Солнечные электростанции</h3><p>Мощные панели 610–730 Вт для больших объектов.</p></div></div>
      <div class="ucard"><div><h3>Оптовые покупатели</h3><p>Поставки для магазинов и региональных дилеров.</p></div></div>
    </div>
  </div>
</section>

<?php
$advItems = [
  ['adv-links.svg',     'НАПРЯМУЮ С ЗАВОДА', 'Без посредников и перекупщиков — заводские цены.'],
  ['adv-reliable.svg',  'ГАРАНТИЯ',          'До 15 лет на панель и 30 лет на мощность.'],
  ['adv-contract.svg',  'ДОКУМЕНТЫ',         'Официальный ввоз, сертификаты, закрывающие документы.'],
  ['opyt.svg',          'ОПЛАТА В РУБЛЯХ',   'Платите в России — заводу платим мы в юанях.'],
  ['adv-logistics.svg', 'ДОСТАВКА',          'Через наш склад в Фошане до вашего города.'],
  ['adv-support.svg',   'ПОДБОР',            'Подберём панели, инвертор и накопитель под объект.'],
];
$advBottom = 'Солнечные панели GPSOLAR — напрямую с завода до вашего объекта';
require ROOT . '/inc/adv.php';
?>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">КАК ЗАКАЗАТЬ</h2>
    <p class="whs__lead">От списка моделей до панелей на вашем складе.</p>
    <div class="ugrid ugrid--3">
      <div class="ucard"><div><h3>1. Заявка</h3><p>Присылаете модели и количество или мощность станции.</p></div></div>
      <div class="ucard"><div><h3>2. Предложение</h3><p>Подбираем панели, согласуем с заводом цены и сроки.</p></div></div>
      <div class="ucard"><div><h3>3. Договор и оплата</h3><p>Вы платите в рублях в России, мы оплачиваем заводу в юанях.</p></div></div>
      <div class="ucard"><div><h3>4. Производство</h3><p>Завод выпускает и упаковывает партию на паллеты.</p></div></div>
      <div class="ucard"><div><h3>5. Проверка</h3><p>Принимаем товар на нашем складе, проверяем и присылаем фото.</p></div></div>
      <div class="ucard"><div><h3>6. Доставка</h3><p>Везём в Россию с документами — до вашего города.</p></div></div>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap whs">
    <div class="whs__text">
      <h2 class="whs__title">ГОТОВЫ К СОТРУДНИЧЕСТВУ</h2>
      <p class="whs__lead">Ищем партнёров для поставок GPSOLAR в Россию.</p>
      <ul class="whs__list">
        <li>Монтажным компаниям и проектировщикам солнечных станций.</li>
        <li>Предприятиям, которые строят станции для себя.</li>
        <li>Магазинам и селлерам маркетплейсов.</li>
        <li>Оптовым покупателям и региональным дилерам.</li>
      </ul>
      <p class="whs__note">Смотрите также <a href="/snadi">инверторы SNADI</a>. Подробнее о работе: <a href="/oplata-postavshchikam-v-kitay">оплата поставщикам в Китай</a>, <a href="/sklad-v-guanchzhou">склад в Гуанчжоу (Фошань)</a>.</p>
    </div>
    <div class="whs__text">
      <h2 class="whs__title">ВОПРОСЫ И ОТВЕТЫ</h2>
<?php foreach ($gpFaq as $i => $q): ?>
      <div class="faqi<?= $i === 0 ? ' open' : '' ?>">
        <button type="button" class="faqi__q"><span><?= e($q[0]) ?></span><i></i></button>
        <div class="faqi__a"><p><?= e($q[1]) ?></p></div>
      </div>
<?php endforeach; ?>
    </div>
  </div>
</section>

<?php require ROOT . '/inc/form-question.php'; ?>
</main>
<?php require ROOT . '/inc/foot.php'; ?>
