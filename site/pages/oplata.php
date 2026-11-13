<?php
$title = 'Оплата поставщикам в Китай в юанях — на счёт компании, Alipay, WeChat, на карту | ИТК';
$descr = 'Оплатим вашему поставщику в Китае в юанях: на банковский счёт китайской компании, через Alipay, WeChat или на карту частного лица. Своя компания в Китае, договор и закрывающие документы. Вы платите в России.';
$bodyClass = 'inner';
$crumb = 'Оплата поставщикам в Китай';
$canonical = '/oplata-postavshchikam-v-kitay';
$ogImage = 'cap-1.jpg';
$opFaq = [
  ['Как оплатить поставщику в Китае из России?', 'Вы платите нам в России, а мы переводим деньги поставщику в юанях через нашу компанию в Китае: на банковский счёт, через Alipay, WeChat или на карту частного лица. Вам не нужен китайский счёт и не нужно искать банк, который проведёт платёж.'],
  ['Куда вы можете перевести деньги?', 'На банковский счёт китайской компании или фабрики, на Alipay и WeChat продавца, а также на карту частного лица в Китае.'],
  ['Какие документы я получу?', 'Работаем по договору. Вы получаете закрывающие документы на оплату — их можно передать в бухгалтерию.'],
  ['Сколько стоит перевод?', 'Комиссия зависит от суммы и способа оплаты. Напишите сумму и куда нужно заплатить — назовём точную стоимость до начала работы.'],
  ['Можно оплатить, если поставщик не работает с Россией?', 'Да. Поставщик получает деньги от нашей китайской компании, внутри Китая, в юанях. Для него это обычный местный платёж.'],
  ['Можете не только оплатить, но и забрать товар?', 'Да. Примем товар на нашем складе в Гуанчжоу (Фошань), проверим, сделаем фото и видео и отправим в Россию карго или белой доставкой.'],
];
$jsonld = [
  ['@context' => 'https://schema.org', '@type' => 'Service',
   'name' => 'Оплата поставщикам в Китай', 'serviceType' => 'Платежи поставщикам в Китае в юанях',
   'description' => $descr, 'areaServed' => 'RU',
   'provider' => ['@type' => 'Organization', 'name' => 'ООО «Интернациональная Торговая Компания»', 'url' => 'https://itcpartner.ru', 'telephone' => $cfg['phone1_raw']]],
  ['@context' => 'https://schema.org', '@type' => 'FAQPage',
   'mainEntity' => array_map(function ($q) { return ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]]; }, $opFaq)],
  ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => 'https://itcpartner.ru/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Оплата поставщикам в Китай', 'item' => 'https://itcpartner.ru/oplata-postavshchikam-v-kitay']]],
];
require ROOT . '/inc/head.php';
?>
<main class="page">
<?php require ROOT . '/inc/crumbs.php'; ?>

<section class="sec">
  <div class="wrap whs whs--rev">
    <div class="whs__text">
      <h1 class="whs__title">ОПЛАТА ПОСТАВЩИКАМ В КИТАЙ В ЮАНЯХ</h1>
      <p class="whs__lead">Вы платите нам в России — мы переводим деньги вашему поставщику в Китае через нашу китайскую компанию. По договору, с закрывающими документами.</p>
      <ul class="whs__list">
        <li>На банковский счёт китайской компании или фабрики.</li>
        <li>Через Alipay и WeChat.</li>
        <li>На карту частного лица в Китае.</li>
      </ul>
      <a href="#vopros" class="btn btn--green btn--lg js-pop">Рассчитать платёж</a>
    </div>
    <img class="whs__photo" src="<?= e(img('cap-1.jpg')) ?>" alt="Оплата поставщику в Китае в юанях через компанию ИТК">
  </div>
</section>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">КУДА МЫ ПЛАТИМ</h2>
    <p class="whs__lead">Любой способ, которым ваш поставщик принимает деньги.</p>
    <div class="ugrid">
      <div class="ucard"><div><h3>Банковский счёт</h3><p>Перевод на счёт китайской компании или фабрики в юанях — для оптовых закупок по контракту.</p></div></div>
      <div class="ucard"><div><h3>Alipay</h3><p>Для продавцов 1688, Taobao и небольших поставщиков, которые работают через Alipay.</p></div></div>
      <div class="ucard"><div><h3>WeChat</h3><p>Для поставщиков, с которыми вы договорились в WeChat.</p></div></div>
      <div class="ucard"><div><h3>Карта частного лица</h3><p>Перевод на банковскую карту частного лица в Китае.</p></div></div>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">КАК ПРОХОДИТ ОПЛАТА</h2>
    <p class="whs__lead">Вам не нужен китайский счёт и не нужно искать банк, который проведёт платёж в Китай.</p>
    <div class="ugrid ugrid--3">
      <div class="ucard"><div><h3>1. Заявка</h3><p>Присылаете счёт или реквизиты поставщика и сумму в юанях.</p></div></div>
      <div class="ucard"><div><h3>2. Расчёт</h3><p>Называем итоговую сумму в рублях до начала работы.</p></div></div>
      <div class="ucard"><div><h3>3. Договор</h3><p>Заключаем договор, вы оплачиваете в России.</p></div></div>
      <div class="ucard"><div><h3>4. Перевод</h3><p>Наша компания в Китае переводит деньги поставщику в юанях.</p></div></div>
      <div class="ucard"><div><h3>5. Подтверждение</h3><p>Присылаем подтверждение платежа, поставщик отгружает товар.</p></div></div>
      <div class="ucard"><div><h3>6. Документы</h3><p>Передаём закрывающие документы для вашей бухгалтерии.</p></div></div>
    </div>
  </div>
</section>

<?php
$advItems = [
  ['adv-reliable.svg',  'СВОЯ КОМПАНИЯ',  'Собственная компания в Китае — платёж идёт внутри Китая.'],
  ['adv-contract.svg',  'ДОГОВОР',        'Работаем по договору, с закрывающими документами.'],
  ['adv-links.svg',     'ЛЮБОЙ СПОСОБ',   'Счёт компании, Alipay, WeChat, карта частного лица.'],
  ['opyt.svg',          'ОПЫТ',           'Больше 10 лет работаем с китайскими фабриками.'],
  ['adv-logistics.svg', 'СКЛАД',          'Примем оплаченный товар на склад в Гуанчжоу (Фошань).'],
  ['adv-support.svg',   'КОММУНИКАЦИЯ',   'Личный менеджер в мессенджерах.'],
];
$advBottom = 'Вы платите в России — поставщик получает юани в Китае';
require ROOT . '/inc/adv.php';
?>

<section class="sec">
  <div class="wrap whs">
    <div class="whs__text">
      <h2 class="whs__title">ЗАЧЕМ ПЛАТИТЬ ЧЕРЕЗ НАС</h2>
      <p class="whs__lead">Прямой перевод из России в Китай сегодня — долго, дорого и не всегда возможно.</p>
      <ul class="whs__list">
        <li>Многие китайские банки не принимают платежи из России или возвращают их через несколько недель.</li>
        <li>Небольшие поставщики и продавцы 1688 принимают деньги только через Alipay или WeChat.</li>
        <li>Через нас поставщик получает обычный местный платёж в юанях от китайской компании.</li>
        <li>Вы платите в России по договору и получаете документы для бухгалтерии.</li>
      </ul>
      <p class="whs__note">Можем не только оплатить, но и <a href="/vykup-tovarov-iz-kitaya">выкупить товар</a> с 1688, Taobao и Pinduoduo, проверить его на нашем складе и привезти <a href="/kargo-iz-kitaya">карго из Китая</a>.</p>
    </div>
    <div class="whs__text">
      <h2 class="whs__title">ВОПРОСЫ И ОТВЕТЫ</h2>
<?php foreach ($opFaq as $i => $q): ?>
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
