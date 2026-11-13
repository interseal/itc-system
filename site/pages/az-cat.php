<?php
// Раздел полного каталога фабрики: /avtozapchasti-iz-kitaya/<фабрика>/<раздел>
// Данные — inc/az-catalog/<фабрика>.php, фабрика — inc/az-factories.php
$azAll = require ROOT . '/inc/az-factories.php';
$f = $azAll[$azSlug];
$azCatalog = require ROOT . '/inc/az-catalog/' . $azSlug . '.php';
$azC = $azCatalog['cats'][$azCatSlug];
$azDir = $f['dir'] ?? $azSlug;
$azItems = array_values(array_filter($azCatalog['items'], fn($it) => $it[0] === $azCatSlug));
$azCount = count($azItems);
$azPl = fn($n) => $n . ' ' . (($n % 10 == 1 && $n % 100 != 11) ? 'позиция' : (($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20)) ? 'позиции' : 'позиций'));
// «каталог фабрики» или «каталог поставщика» (у поставщика в inc/az-factories.php стоит 'who')
$azWho = $f['who'] ?? 'фабрики';
// большой раздел (TiBAO) — по страницам, поиск по номеру и названию идёт по всему разделу на сервере
$azPer = (int)($azCatalog['per_page'] ?? 0);
$azQ = $azPer ? trim((string)($_GET['q'] ?? '')) : '';
$azAll = $azItems;
if ($azQ !== '') {
  $azNorm = fn($s) => strtolower(preg_replace('~[^A-Za-z0-9]~', '', $s));
  $qn = $azNorm($azQ); $ql = mb_strtolower($azQ);
  $azItems = array_values(array_filter($azItems, function ($it) use ($qn, $ql, $azNorm) {
    if (mb_strlen($ql) >= 3 && mb_strpos(mb_strtolower($it[1]), $ql) !== false) return true;
    if (strlen($qn) >= 3) foreach (array_merge($it[3], $it[4], $it[5]) as $n) if (strpos($azNorm($n), $qn) !== false) return true;
    return false;
  }));
}
$azPages = $azPer ? max(1, (int)ceil(count($azItems) / $azPer)) : 1;
$azPage = min($azPages, max(1, (int)($_GET['p'] ?? 1)));
$azShown = $azPer ? array_slice($azItems, ($azPage - 1) * $azPer, $azPer) : $azItems;
// адрес страницы раздела с поиском и номером страницы
$azUrl = function ($n) use ($azSlug, $azCatSlug, $azQ) {
  $qs = http_build_query(array_filter(['q' => $azQ, 'p' => $n > 1 ? $n : null], fn($x) => $x !== null && $x !== ''));
  return '/avtozapchasti-iz-kitaya/' . $azSlug . '/' . $azCatSlug . ($qs !== '' ? '?' . $qs : '');
};
// позиции по видам товара, в порядке каталога
$azTypes = [];
foreach ($azShown as $it) $azTypes[$it[1]][] = $it;
// если почти у каждой позиции своё название (рейки по моделям) — один общий список без подзаголовков
if (!empty($azCatalog['flat']) || count($azTypes) * 3 > count($azShown)) $azTypes = ['' => $azShown];
// несколько номеров для описания страницы
$azSample = [];
foreach ($azAll as $it) { foreach (array_merge($it[4], $it[3]) as $n) { if (preg_match('~\p{Cyrillic}~u', $n)) continue; $azSample[] = preg_replace('~ \(.*\)$~u', '', $n); break; } if (count($azSample) >= 4) break; }

$title = trim($azC[0] . ' ' . ($azCatalog['for'] ?? '')) . ' из Китая — каталог ' . $f['name'] . ($azPage > 1 ? ', страница ' . $azPage : '') . ' | ИТК';
$descr = $azC[0] . ' ' . $f['name'] . ': ' . $azPl($azCount) . ($azSample ? ' с ' . ($azCatalog['nums'] ?? 'номерами фабрики и кросс-номерами') . ' (' . implode(', ', $azSample) . ' и другие)' : ' с фото') . (isset($f['who']) ? '. Поставка из Китая' : '. Поставка напрямую с фабрики') . ', проверка на складе в Гуанчжоу, документы.';
$bodyClass = 'inner';
$crumb = $azC[0];
$crumbParents = [['/avtozapchasti-iz-kitaya', 'Автозапчасти из Китая'], ['/avtozapchasti-iz-kitaya/' . $azSlug, $f['name']]];
$canonical = '/avtozapchasti-iz-kitaya/' . $azSlug . '/' . $azCatSlug . ($azPage > 1 && $azQ === '' ? '?p=' . $azPage : '');
$ogImage = $azC[2];
$jsonld = [
  ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => 'https://itcpartner.ru/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Автозапчасти из Китая', 'item' => 'https://itcpartner.ru/avtozapchasti-iz-kitaya'],
    ['@type' => 'ListItem', 'position' => 3, 'name' => $f['name'], 'item' => 'https://itcpartner.ru/avtozapchasti-iz-kitaya/' . $azSlug],
    ['@type' => 'ListItem', 'position' => 4, 'name' => $azC[0], 'item' => 'https://itcpartner.ru' . $canonical]]],
];
require ROOT . '/inc/head.php';
?>
<main class="page" data-pick="az-<?= e($azSlug) ?>" data-pick-name="<?= e($f['name']) ?>">
<?php require ROOT . '/inc/crumbs.php'; ?>

<section class="sec">
  <div class="wrap kases">
    <h1 class="whs__title"><?= e(mb_strtoupper($azC[0])) ?> <?= e(mb_strtoupper($f['name'])) ?></h1>
    <p class="whs__lead"><?= e($azC[1]) ?></p>
    <p class="az-intro">В разделе <?= $azPl($azCount) ?> из каталога <?= e($azWho) ?> <?= e($f['name']) ?> (<?= $f['city'] !== '' ? e($f['city']) . ', ' : '' ?>Китай) <?= e($azCatalog['use'] ?? 'для грузовиков, автобусов и прицепов') ?>. <?= e($azCatalog['note'] ?? 'У каждой позиции указан номер фабрики и кросс-номера — по ним можно подобрать замену оригинальной детали.') ?> Введите свой номер в поиск или пришлите список — проверим наличие и пришлём цены.</p>
<?php if ($azPer): ?>
    <form class="azs" method="get" action="<?= lang_prefix() ?>/avtozapchasti-iz-kitaya/<?= e($azSlug) ?>/<?= e($azCatSlug) ?>">
      <input type="search" name="q" class="azs__in" value="<?= e($azQ) ?>" placeholder="Номер детали или название" aria-label="Поиск по номеру детали">
      <button type="submit" class="btn btn--green">Найти</button>
    </form>
<?php if ($azQ !== ''): ?>
    <p class="azs__found">Найдено: <?= count($azItems) ?> <a href="/avtozapchasti-iz-kitaya/<?= e($azSlug) ?>/<?= e($azCatSlug) ?>">Сбросить поиск</a></p>
<?php endif; ?>
<?php else: ?>
    <div class="azs">
      <input type="search" class="azs__in js-azfilter" placeholder="Номер детали или название" aria-label="Поиск по номеру детали">
      <a href="#vopros" class="btn btn--green js-pop">Запросить цены</a>
    </div>
<?php endif; ?>
    <p class="azs__none"<?= $azQ !== '' && !$azItems ? '' : ' hidden' ?>>Такого номера в разделе нет. Попробуйте <a href="/avtozapchasti-iz-kitaya/<?= e($azSlug) ?>">поиск по всему каталогу</a> или пришлите номер нам — подберём замену.</p>

<?php foreach ($azTypes as $tName => $tItems): ?>
    <div class="azt">
<?php if ($tName !== ''): ?>
      <h2 class="pcr-group"><?= e($tName) ?></h2>
      <p class="pcr-group__sub"><?= count($tItems) ?> поз.</p>
<?php endif; ?>
      <div class="pcr-grid azi-grid">
<?php foreach ($tItems as $it) require ROOT . '/inc/az-item.php'; ?>
      </div>
    </div>
<?php endforeach; ?>
<?php if ($azPages > 1):
  // номера страниц: первая, последняя и две рядом с текущей
  $azNums = array_unique(array_merge([1], range(max(1, $azPage - 2), min($azPages, $azPage + 2)), [$azPages])); $azPrev = 0; ?>
    <nav class="azp" aria-label="Страницы раздела">
<?php if ($azPage > 1): ?>
      <a class="azp__arr" href="<?= e($azUrl($azPage - 1)) ?>" aria-label="Предыдущая страница"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M15.4 7.4 14 6l-6 6 6 6 1.4-1.4-4.6-4.6z" fill="currentColor"/></svg></a>
<?php endif; ?>
<?php foreach ($azNums as $n): if ($n - $azPrev > 1): ?>
      <span class="azp__gap">…</span>
<?php endif; $azPrev = $n; ?>
      <?= $n === $azPage ? '<b class="azp__on">' . $n . '</b>' : '<a href="' . e($azUrl($n)) . '">' . $n . '</a>' ?>

<?php endforeach; ?>
<?php if ($azPage < $azPages): ?>
      <a class="azp__arr" href="<?= e($azUrl($azPage + 1)) ?>" aria-label="Следующая страница"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M8.6 16.6 10 18l6-6-6-6-1.4 1.4 4.6 4.6z" fill="currentColor"/></svg></a>
<?php endif; ?>
    </nav>
<?php endif; ?>
  </div>
</section>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">ДРУГИЕ РАЗДЕЛЫ КАТАЛОГА</h2>
    <div class="az-others">
<?php foreach ($azCatalog['cats'] as $cs => $cc): if ($cs === $azCatSlug || (isset($azC[3]) && ($cc[3] ?? '') !== $azC[3])) continue; ?>
      <a href="/avtozapchasti-iz-kitaya/<?= e($azSlug) ?>/<?= e($cs) ?>"><?= e($cc[0]) ?></a>
<?php endforeach; ?>
<?php if (isset($azC[3])): ?>
      <a href="/avtozapchasti-iz-kitaya/<?= e($azSlug) ?>#katalog">Все марки и разделы <?= e($f['name']) ?></a>
<?php endif; ?>
      <a href="/avtozapchasti-iz-kitaya/<?= e($azSlug) ?>"><?= isset($f['who']) ? 'О поставщике' : 'О фабрике' ?> <?= e($f['name']) ?></a>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">КАК ЗАКАЗАТЬ</h2>
    <div class="ugrid ugrid--3">
      <div class="ucard"><div><h3>1. Присылаете список</h3><p>OEM-номера, кросс-номера или фото старой детали. Можно Excel-файлом.</p></div></div>
      <div class="ucard"><div><h3>2. Запрашиваем цены</h3><p>Сами общаемся с фабрикой на китайском и присылаем вам предложение.</p></div></div>
      <div class="ucard"><div><h3>3. Привозим и оформляем</h3><p>Проверяем товар на складе в Гуанчжоу и везём в Россию с документами.</p></div></div>
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
      <form class="lead-form" data-form="Автозапчасти: <?= e($f['name']) ?>, <?= e($azC[0]) ?>">
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
<script>
// поиск по номеру внутри раздела: прячем карточки без совпадений
(function () {
  var inp = document.querySelector('.js-azfilter'); if (!inp) return;
  var norm = function (s) { return s.toLowerCase().replace(/[^a-z0-9а-яё]/g, ''); };
  var cards = [].slice.call(document.querySelectorAll('.azi')), none = document.querySelector('.azs__none');
  cards.forEach(function (c) { c._t = norm(c.textContent); });
  inp.addEventListener('input', function () {
    var q = norm(inp.value), any = false;
    cards.forEach(function (c) { var on = !q || c._t.indexOf(q) >= 0; c.hidden = !on; if (on) any = true; });
    document.querySelectorAll('.azt').forEach(function (t) { t.hidden = !t.querySelector('.azi:not([hidden])'); });
    none.hidden = any;
  });
})();
</script>
<?php require ROOT . '/inc/foot.php'; ?>
