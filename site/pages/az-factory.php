<?php
// Страница одной фабрики автозапчастей. Данные — в inc/az-factories.php
$azAll = require ROOT . '/inc/az-factories.php';
$f = $azAll[$azSlug];
$azWhatL = mb_strtolower(mb_substr($f['what'], 0, 1)) . mb_substr($f['what'], 1);
$title = $f['name'] . ' — ' . $azWhatL . ' из Китая | ИТК';
$descr = $f['name'] . ' (' . ($f['city'] !== '' ? $f['city'] . ', ' : '') . 'Китай): ' . $azWhatL . '. ' . $f['short'] . ' Поставка напрямую, проверка на складе в Гуанчжоу, документы.';
$bodyClass = 'inner';
$crumb = $f['name'];
$crumbParent = ['/avtozapchasti-iz-kitaya', 'Автозапчасти из Китая'];
$canonical = '/avtozapchasti-iz-kitaya/' . $azSlug;
$azPhoto = $f['photo'] ?? '';
if ($azPhoto) $ogImage = $azPhoto;

$jsonld = [
  ['@context' => 'https://schema.org', '@type' => 'Service',
   'name' => 'Поставка продукции ' . $f['name'] . ' из Китая', 'serviceType' => 'Поставка автозапчастей из Китая',
   'description' => $descr, 'areaServed' => 'RU',
   'provider' => ['@type' => 'Organization', 'name' => 'ООО «Интернациональная Торговая Компания»', 'url' => 'https://itcpartner.ru', 'telephone' => $cfg['phone1_raw']]],
  ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => 'https://itcpartner.ru/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Автозапчасти из Китая', 'item' => 'https://itcpartner.ru/avtozapchasti-iz-kitaya'],
    ['@type' => 'ListItem', 'position' => 3, 'name' => $f['name'], 'item' => 'https://itcpartner.ru' . $canonical]]],
];
$azDir = $f['dir'] ?? (!empty($f['groups']) ? explode('/', $f['photo'] ?? '')[0] : '');
require ROOT . '/inc/head.php';
?>
<main class="page" data-pick="az-<?= e($azSlug) ?>" data-pick-name="<?= e($f['name']) ?>">
<?php require ROOT . '/inc/crumbs.php'; ?>

<section class="sec">
  <div class="wrap whs whs--rev<?= $azPhoto ? '' : ' az-nophoto' ?>">
    <div class="whs__text">
      <h1 class="whs__title"><?= e(mb_strtoupper($f['name'])) ?></h1>
      <p class="whs__lead"><?= e($f['what']) ?></p>
      <div class="az-meta"><span><?= $f['city'] !== '' ? e($f['city']) . ', ' : '' ?>Китай</span><span>Под заказ</span></div>
      <p class="az-intro"><?= e($f['about']) ?></p>
<?php if (!empty($f['facts'])): ?>
      <ul class="whs__list">
<?php foreach ($f['facts'] as $fact): ?>
        <li><?= e($fact) ?></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
<?php if (!empty($f['note'])): ?>
      <p class="az-note"><?= e($f['note']) ?></p>
<?php endif; ?>
      <div class="az-btns">
        <a href="#vopros" class="btn btn--green btn--lg js-pop">Запросить цены</a>
<?php if (is_file(ROOT . '/inc/az-catalog/' . $azSlug . '.php')): ?>
        <a href="#katalog" class="btn btn--lg az-wa">Смотреть каталог</a>
<?php endif; ?>
      </div>
      <a class="az-back" href="/avtozapchasti-iz-kitaya">← Все фабрики</a>
    </div>
<?php if ($azPhoto): ?>
    <img class="whs__photo" src="<?= e(img($azPhoto)) ?>" alt="<?= e($f['name']) ?> — <?= e($azWhatL) ?>">
<?php endif; ?>
  </div>
</section>

<?php
// полный каталог фабрики (если есть): поиск по номеру + разделы
$azCatFile = ROOT . '/inc/az-catalog/' . $azSlug . '.php';
$azCatalog = is_file($azCatFile) ? require $azCatFile : null;
if ($azCatalog):
  $azCnt = [];
  foreach ($azCatalog['items'] as $it) $azCnt[$it[0]] = ($azCnt[$it[0]] ?? 0) + 1;
  $azPl = fn($n) => $n . ' ' . (($n % 10 == 1 && $n % 100 != 11) ? 'позиция' : (($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20)) ? 'позиции' : 'позиций'));
  $azQ = trim((string)($_GET['q'] ?? ''));
  $azNorm = fn($s) => strtolower(preg_replace('~[^A-Za-z0-9]~', '', $s));
?>
<section class="sec" id="katalog">
  <div class="wrap kases">
    <h2 class="whs__title">КАТАЛОГ <?= e(mb_strtoupper($f['who'] ?? 'фабрики')) ?> — <?= e(mb_strtoupper($azPl(count($azCatalog['items'])))) ?></h2>
    <p class="whs__lead">Найдите деталь по номеру или выберите раздел — у каждой позиции есть фото и номера.</p>
    <form class="azs" method="get" action="<?= lang_prefix() ?>/avtozapchasti-iz-kitaya/<?= e($azSlug) ?>#katalog">
      <input type="search" name="q" class="azs__in" value="<?= e($azQ) ?>" placeholder="Номер детали или OE-номер" aria-label="Поиск по номеру детали">
      <button type="submit" class="btn btn--green">Найти</button>
    </form>
<?php if ($azQ !== ''):
  $qn = $azNorm($azQ);
  $azFound = mb_strlen($qn) < 3 ? [] : array_values(array_filter($azCatalog['items'], function ($it) use ($qn, $azNorm) {
    foreach (array_merge($it[3], $it[4], $it[5]) as $n) if (strpos($azNorm($n), $qn) !== false) return true;
    return false;
  }));
?>
<?php if ($azFound): ?>
    <h3 class="pcr-group">Найдено: <?= count($azFound) ?></h3>
    <div class="pcr-grid azi-grid">
<?php foreach (array_slice($azFound, 0, 40) as $it) require ROOT . '/inc/az-item.php'; ?>
    </div>
<?php else: ?>
    <p class="azs__none">По этому номеру ничего не нашли. Пришлите номер нам — подберём замену у этой или другой фабрики.</p>
<?php endif; ?>
<?php endif; ?>
<?php
  // разделы с маркой машины (TiBAO): карточка марки со списком узлов
  $azGroups = [];
  foreach ($azCatalog['cats'] as $cs => $cc) if (isset($cc[3])) $azGroups[$cc[3]][$cs] = $cc;
  if ($azGroups):
?>
    <h3 class="pcr-group">Разделы каталога по маркам</h3>
    <p class="pcr-group__sub">Выберите марку и узел машины — откроются все позиции с фото и номерами.</p>
    <div class="az-brands">
<?php foreach ($azGroups as $gName => $gCats): $gN = 0; foreach ($gCats as $cs => $cc) $gN += $azCnt[$cs] ?? 0; ?>
      <div class="az-brand">
        <div class="az-brand__head"><img src="<?= e(img($azCatalog['groups'][$gName] ?? reset($gCats)[2])) ?>" alt="Запчасти <?= e($gName) ?>" loading="lazy"><div><h4><?= e($gName) ?></h4><b><?= e($azPl($gN)) ?></b></div></div>
        <ul class="az-brand__list">
<?php foreach ($gCats as $cs => $cc): ?>
          <li><a href="/avtozapchasti-iz-kitaya/<?= e($azSlug) ?>/<?= e($cs) ?>"><span><?= e($cc[4] ?? $cc[0]) ?></span><i><?= $azCnt[$cs] ?? 0 ?></i></a></li>
<?php endforeach; ?>
        </ul>
      </div>
<?php endforeach; ?>
    </div>
<?php else: ?>
    <h3 class="pcr-group">Разделы каталога</h3>
    <p class="pcr-group__sub">Нажмите на раздел — откроются все позиции с фото и номерами.</p>
    <div class="pcr-grid">
<?php foreach ($azCatalog['cats'] as $cs => $cc): ?>
      <a class="pcr az-tile az-cattile" href="/avtozapchasti-iz-kitaya/<?= e($azSlug) ?>/<?= e($cs) ?>"><div class="pcr__img"><img src="<?= e(img($cc[2])) ?>" alt="<?= e($cc[0]) ?>" loading="lazy"></div>
        <div class="pcr__body"><span class="az-sec">Раздел</span><h4><?= e($cc[0]) ?></h4><b class="az-what"><?= e($azPl($azCnt[$cs] ?? 0)) ?></b><span class="az-city"><?= e($cc[1]) ?></span><span class="az-open">Открыть раздел</span></div></a>
<?php endforeach; ?>
    </div>
<?php endif; ?>
    <a href="#vopros" class="btn btn--green btn--lg js-pop pcr-cta">Запросить цены</a>
  </div>
</section>
<?php elseif (!empty($f['groups'])): ?>
<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">ТОВАРЫ ИЗ КАТАЛОГА</h2>
    <p class="whs__lead">Всё можно заказать. Цены зависят от позиции и количества — пришлите номера или фото детали, и мы подготовим предложение.</p>
<?php foreach ($f['groups'] as $g): ?>
    <h3 class="pcr-group"><?= e($g[0]) ?></h3>
    <p class="pcr-group__sub"><?= e($g[1]) ?></p>
    <div class="pcr-grid">
<?php foreach ($g[2] as $m): ?>
      <div class="pcr"><div class="pcr__img gal">
          <div class="gal__track">
<?php foreach ($m[0] as $ph): ?>
            <img src="<?= e(img($azDir . '/' . $ph . '.jpg')) ?>" alt="<?= e($m[1]) ?>" loading="lazy">
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
    <a href="#vopros" class="btn btn--green btn--lg js-pop pcr-cta">Запросить цены</a>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($f['items'])): ?>
<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">ЧТО ДЕЛАЕТ ФАБРИКА</h2>
    <p class="whs__lead">Привезём любую позицию из ассортимента — пришлите номера или фото детали.</p>
    <div class="ugrid ugrid--3">
<?php foreach ($f['items'] as $it): ?>
      <div class="ucard"><div><h3><?= e($it[0]) ?></h3><p><?= e($it[1]) ?></p></div></div>
<?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">КАК ЗАКАЗАТЬ</h2>
    <p class="whs__lead">От списка запчастей до вашего склада.</p>
    <div class="ugrid ugrid--3">
      <div class="ucard"><div><h3>1. Присылаете список</h3><p>OEM-номера, кросс-номера или фото старой детали. Можно Excel-файлом.</p></div></div>
      <div class="ucard"><div><h3>2. Запрашиваем цены</h3><p>Сами общаемся с фабрикой на китайском и присылаем вам предложение.</p></div></div>
      <div class="ucard"><div><h3>3. Привозим и оформляем</h3><p>Проверяем товар на складе в Гуанчжоу и везём в Россию с документами.</p></div></div>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">ДРУГИЕ ФАБРИКИ</h2>
    <div class="az-others">
<?php foreach ($azAll as $s => $o): if ($s === $azSlug) continue; ?>
      <a href="/avtozapchasti-iz-kitaya/<?= e($s) ?>"><?= e($o['name']) ?> <span><?= e($o['what']) ?></span></a>
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
      <h3 class="fcard__title fcard__title--sm">Запросим цены и пришлём предложение</h3>
      <form class="lead-form" data-form="Автозапчасти: <?= e($f['name']) ?>">
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
