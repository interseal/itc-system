<?php
// Страница завода из базы поставщиков Экосистемы ($supPage задан в index.php).
// Тексты страницы — на русском; на английский и китайский переводим сами
// по переводам, сохранённым вместе со страницей (sup_t), без словаря сайта.
$P = $supPage['data'];
$T = function ($s) { return sup_t($s); };
$slug = $P['slug'];

$SECTIONS = ['solnechnaya-energetika' => 'Солнечная энергетика', 'selhoztehnika' => 'Сельхозтехника', 'oborudovanie-dlya-kafe' => 'Оборудование для кафе и торговли', 'tehnika-dlya-volos' => 'Техника для ухода за волосами', 'mebel' => 'Мебель'];
$title = $P['title'] ?: ($P['crumb'] . ' напрямую с завода | ИТК');
$descr = $P['descr'] ?: $P['lead'];
$bodyClass = 'inner';
$crumb = $P['crumb'] ?: $P['brand'];
if (isset($SECTIONS[$P['section'] ?? ''])) $crumbParent = ['/' . $P['section'], $SECTIONS[$P['section']]];
$canonical = '/' . $slug;
$ogImageUrl = $P['hero'] ?? '';

$items = [];
foreach ($P['groups'] as $g) foreach ($g['items'] as $it) $items[] = $it;

$crumbsLd = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => 'https://itcpartner.ru/']];
if (!empty($crumbParent)) $crumbsLd[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $crumbParent[1], 'item' => 'https://itcpartner.ru' . $crumbParent[0]];
$crumbsLd[] = ['@type' => 'ListItem', 'position' => count($crumbsLd) + 1, 'name' => $T($crumb), 'item' => 'https://itcpartner.ru/' . $slug];
$jsonld = [
  ['@context' => 'https://schema.org', '@type' => 'Service',
   'name' => $T($crumb), 'serviceType' => $T($crumb),
   'description' => $T($descr), 'areaServed' => 'RU',
   'provider' => ['@type' => 'Organization', 'name' => 'ООО «Интернациональная Торговая Компания»', 'url' => 'https://itcpartner.ru', 'telephone' => $cfg['phone1_raw']]],
  ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $crumbsLd],
];
if ($P['faq']) $jsonld[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage',
  'mainEntity' => array_map(function ($q) use ($T) { return ['@type' => 'Question', 'name' => $T($q['q']), 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $T($q['a'])]]; }, $P['faq'])];
$title = $T($title); $descr = $T($descr);
require ROOT . '/inc/head.php';
?>
<main class="page" data-pick="sup-<?= e($slug) ?>" data-pick-name="<?= e($P['brand'] ?: $T($crumb)) ?>">
<?php $crumb = $T($crumb); require ROOT . '/inc/crumbs.php'; ?>

<section class="sec">
  <div class="wrap whs whs--rev pcr-hero">
    <div class="whs__text">
<?php if (!empty($supPage['big'])): ?>      <?= sup_big_badge() ?>
<?php endif; ?>
      <h1 class="whs__title"><?= e($T($P['h1'])) ?></h1>
<?php if ($P['lead']): ?>      <p class="whs__lead"><?= e($T($P['lead'])) ?></p>
<?php endif; ?>
<?php if ($P['bullets']): ?>      <ul class="whs__list">
<?php foreach ($P['bullets'] as $b): ?>        <li><?= e($T($b)) ?></li>
<?php endforeach; ?>      </ul>
<?php endif; ?>
      <a href="#vopros" class="btn btn--green btn--lg js-pop"><?= e($T($P['cta'] ?: 'Запросить цены')) ?></a>
    </div>
    <img class="whs__photo" src="<?= e(sup_img($P['hero'])) ?>" alt="<?= e($T($P['hero_alt'] ?: $crumb)) ?>">
  </div>
</section>

<?php if ($P['about_lead'] || $P['about_bullets']): ?>
<section class="sec">
  <div class="wrap whs pcr-about">
    <div class="whs__text">
      <h2 class="whs__title"><?= e($T($P['about_title'] ?: 'О ЗАВОДЕ')) ?></h2>
<?php if ($P['about_lead']): ?>      <p class="whs__lead"><?= e($T($P['about_lead'])) ?></p>
<?php endif; ?>
<?php if ($P['about_bullets']): ?>      <ul class="whs__list">
<?php foreach ($P['about_bullets'] as $b): ?>        <li><?= e($T($b)) ?></li>
<?php endforeach; ?>      </ul>
<?php endif; ?>
    </div>
<?php if ($P['factory']): ?>    <img class="whs__photo" src="<?= e(sup_img($P['factory'])) ?>" alt="<?= e($T($P['factory_alt'] ?: $crumb)) ?>" loading="lazy">
<?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($P['stats'])): // цифры фабрики (3.46.0) ?>
<section class="sec">
  <div class="wrap kases"><div class="fstats">
<?php foreach ($P['stats'] as $st): ?>    <div class="fstats__i"><b><?= e($T($st['n'])) ?></b><span><?= e($T($st['t'])) ?></span></div>
<?php endforeach; ?>
  </div></div>
</section>
<?php endif; ?>

<?php foreach (($P['blocks'] ?? []) as $bl): // производство, технологии, сертификаты, упаковка, работа под заказ (3.46.0) ?>
<section class="sec">
  <div class="wrap kases fblock">
<?php if ($bl['title']): ?>    <h2 class="whs__title"><?= e($T($bl['title'])) ?></h2>
<?php endif; ?>
<?php if ($bl['lead']): ?>    <p class="whs__lead"><?= e($T($bl['lead'])) ?></p>
<?php endif; ?>
<?php if ($bl['bullets']): ?>    <ul class="whs__list fblock__list">
<?php foreach ($bl['bullets'] as $b): ?>      <li><?= e($T($b)) ?></li>
<?php endforeach; ?>    </ul>
<?php endif; ?>
<?php if ($bl['items']): ?>    <?php $fbn = count($bl['items']); ?><div class="fb-grid fb-grid--<?= $fbn <= 2 ? 'big' : ($fbn % 3 == 0 || $fbn == 5 ? 'c3' : 'c4') ?>">
<?php foreach ($bl['items'] as $fi): ?>      <figure class="fb-i"><a class="js-zoom" href="<?= e(sup_img($fi['photo'])) ?>"><img src="<?= e(sup_img($fi['photo'])) ?>" alt="<?= e($T($fi['cap'] ?: $bl['title'])) ?>" loading="lazy"></a><?php if ($fi['cap']): ?><figcaption><?= e($T($fi['cap'])) ?></figcaption><?php endif; ?></figure>
<?php endforeach; ?>    </div>
<?php endif; ?>
  </div>
</section>
<?php endforeach; ?>

<?php if (!empty($P['awards'])): // награды и сертификаты завода (3.42.3) ?>
<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title"><?= e($T('НАГРАДЫ И СЕРТИФИКАТЫ ЗАВОДА')) ?></h2>
    <div class="pcr-grid awd-grid">
<?php foreach ($P['awards'] as $aw): ?>
<?php if (!empty($aw['photo'])): ?>      <div class="pcr pcr--nopick awd"><div class="pcr__img awd__img"><img src="<?= e(sup_img($aw['photo'])) ?>" alt="<?= e($T($aw['cap'] ?: 'Сертификат завода')) ?>" loading="lazy"></div>
<?php if ($aw['cap']): ?>        <div class="pcr__body"><p class="awd__cap"><?= e($T($aw['cap'])) ?></p></div>
<?php endif; ?>      </div>
<?php else: ?>      <div class="pcr pcr--nopick awd awd--txt"><svg class="awd__ic" viewBox="0 0 24 24" width="40" height="40" aria-hidden="true"><path fill="currentColor" d="M9.68 13.69 12 11.93l2.31 1.76-.88-2.85L15.75 9h-2.84L12 6.19 11.09 9H8.25l2.31 1.84-.88 2.85zM20 10c0-4.42-3.58-8-8-8s-8 3.58-8 8c0 2.03.76 3.87 2 5.28V23l6-2 6 2v-7.72A7.96 7.96 0 0 0 20 10zm-8-6c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6 2.69-6 6-6z"/></svg><p class="awd__cap"><?= e($T($aw['cap'])) ?></p></div>
<?php endif; ?>
<?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($items): ?>
<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title"><?= e($T($P['models_title'] ?: 'ТОВАРЫ, ДОСТУПНЫЕ К ЗАКАЗУ')) ?></h2>
<?php if ($P['models_lead']): ?>    <p class="whs__lead"><?= e($T($P['models_lead'])) ?></p>
<?php endif; ?>
    <p class="pcr-blank"><a class="btn btn--blank" href="<?= e(lang_prefix() . '/' . $slug . '/blank') ?>" target="_blank" rel="noopener nofollow"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg><?= e($T('Распечатать бланк заказа')) ?></a> <span><?= e($T('Каталог со всеми размерами и цветами — отметьте количество ручкой и отправьте фото менеджеру.')) ?></span></p>
<?php $gList = array_values(array_filter($P['groups'], function ($g) { return $g['items'] && $g['name']; })); if (count($gList) >= 6): // оглавление для больших каталогов (3.40.0) ?>
    <nav class="pcr-nav" aria-label="Разделы каталога"><?php foreach ($P['groups'] as $gi => $g): if (!$g['items'] || !$g['name']) continue; ?><a href="#grp-<?= $gi ?>"><?= e($T($g['name'])) ?> <b><?= count($g['items']) ?></b></a><?php endforeach; ?></nav>
<?php endif; ?>
<?php foreach ($P['groups'] as $gi => $g): if (!$g['items']) continue; $many = count($g['items']) > 6; // в больших группах сначала один ряд (3.40.0) ?>
<?php if ($g['name']): ?>    <h3 class="pcr-group" id="grp-<?= $gi ?>"><?= e($T($g['name'])) ?></h3>
<?php endif; ?>
<?php if ($g['sub']): ?>    <p class="pcr-group__sub"><?= e($T($g['sub'])) ?></p>
<?php endif; ?>
<?php if (!empty($g['cover']) || !empty($g['intro'])): // обложка раздела из каталога (3.46.0) ?>    <div class="pcr-gcov<?= empty($g['cover']) ? ' pcr-gcov--txt' : '' ?>"><?php if (!empty($g['cover'])): ?><a class="js-zoom pcr-gcov__img" href="<?= e(sup_img($g['cover'])) ?>"><img src="<?= e(sup_img($g['cover'])) ?>" alt="<?= e($T($g['name'])) ?>" loading="lazy"></a><?php endif; ?><?php if (!empty($g['intro'])): ?><p><?= e($T($g['intro'])) ?></p><?php endif; ?></div>
<?php endif; ?>
    <div class="pcr-grid">
<?php foreach ($g['items'] as $mi => $m):
      // варианты заказа и упаковка (3.41.0): выбор строками, количество в упаковках
      $ord = '';
      if (!empty($m['opt']) || !empty($m['pack']) || (($m['u'] ?? 'pcs') !== 'pcs')) {
        $oo = [];
        foreach (($m['opt'] ?? []) as $o) $oo[] = isset($o['v']) ? ['n' => $T($o['n']), 'v' => array_map($T, $o['v'])] : ['n' => $T($o['n']), 'f' => $T($o['f'] ?? '')];
        $ord = json_encode(['o' => $oo, 'k' => array_values($m['pack'] ?? []), 'u' => $m['u'] ?? 'pcs'], JSON_UNESCAPED_UNICODE);
      } ?>
      <div class="pcr<?= $many && $mi >= 4 ? ' pcr--more' : '' ?>"<?= $ord ? ' data-ord="' . e($ord) . '"' : '' ?>><div class="pcr__img gal">
          <div class="gal__track">
<?php foreach ($m['photos'] as $f): ?>
            <img src="<?= e(sup_img($f)) ?>" alt="<?= e($T($m['name'])) ?>" loading="lazy">
<?php endforeach; ?>
          </div>
<?php if (count($m['photos']) > 1): ?>
          <button type="button" class="gal__btn gal__btn--prev" aria-label="Предыдущее фото"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M15.4 7.4 14 6l-6 6 6 6 1.4-1.4-4.6-4.6z" fill="currentColor"/></svg></button>
          <button type="button" class="gal__btn gal__btn--next" aria-label="Следующее фото"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M8.6 16.6 10 18l6-6-6-6-1.4 1.4 4.6 4.6z" fill="currentColor"/></svg></button>
          <div class="gal__dots"><?php foreach ($m['photos'] as $n => $f): ?><i<?= $n ? '' : ' class="on"' ?>></i><?php endforeach; ?></div>
<?php endif; ?>
        </div>
        <div class="pcr__body"><span class="pcr__tag">Под заказ</span><h4><?= e($T($m['name'])) ?></h4><?php if ($m['desc']): ?><p><?= e($T($m['desc'])) ?></p><?php endif; ?><?php if (!empty($m['spec'])): // характеристики (3.42.4) ?><details class="pcr__spec"><summary><?= e($T('Характеристики')) ?></summary><table><?php foreach ($m['spec'] as $r): ?><tr><th><?= e($T($r[0])) ?></th><td><?= e($T($r[1])) ?></td></tr><?php endforeach; ?></table></details><?php endif; ?></div></div>
<?php endforeach; ?>
    </div>
<?php if ($many): ?>    <button type="button" class="pcr-more js-pcr-more"><span class="pcr-more__on">Показать все модели</span><span class="pcr-more__off">Свернуть</span> <b><?= count($g['items']) ?></b></button>
<?php endif; ?>
<?php endforeach; ?>
    <a href="#vopros" class="btn btn--green btn--lg js-pop pcr-cta">Получить предложение по товарам</a>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($P['projects'])): ?>
<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title"><?= e($T('ПРОЕКТЫ НА ПРОДУКЦИИ ЗАВОДА')) ?></h2>
    <div class="pcr-grid">
<?php foreach ($P['projects'] as $pj): ?>
      <div class="pcr pcr--nopick"><div class="pcr__img" style="padding:0"><img src="<?= e(sup_img($pj['photo'])) ?>" alt="<?= e($T($pj['cap'])) ?>" loading="lazy" style="object-fit:cover"></div>
<?php if ($pj['cap']): ?>        <div class="pcr__body"><h4><?= e($T($pj['cap'])) ?></h4></div>
<?php endif; ?>      </div>
<?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($P['uses']): ?>
<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title"><?= e($T($P['uses_title'] ?: 'КОМУ ПОДОЙДЁТ')) ?></h2>
<?php if ($P['uses_lead']): ?>    <p class="whs__lead"><?= e($T($P['uses_lead'])) ?></p>
<?php endif; ?>
    <div class="ugrid ugrid--3">
<?php foreach ($P['uses'] as $u): ?>
      <div class="ucard"><div><h3><?= e($T($u['h'])) ?></h3><?php if ($u['p']): ?><p><?= e($T($u['p'])) ?></p><?php endif; ?></div></div>
<?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
$advIcons = ['adv-links.svg', 'adv-reliable.svg', 'adv-contract.svg', 'opyt.svg', 'adv-logistics.svg', 'adv-support.svg'];
$advSrc = $P['adv'] ?: [
  ['h' => 'НАПРЯМУЮ С ЗАВОДА', 'p' => 'Без посредников и перекупщиков — заводские цены.'],
  ['h' => 'ПРОВЕРКА', 'p' => 'Проверяем товар на нашем складе перед отправкой.'],
  ['h' => 'ДОКУМЕНТЫ', 'p' => 'Официальный ввоз, сертификаты, закрывающие документы.'],
  ['h' => 'ОПЛАТА В РУБЛЯХ', 'p' => 'Платите в России — заводу платим мы в юанях.'],
  ['h' => 'ДОСТАВКА', 'p' => 'Через наш склад в Фошане до вашего города.'],
  ['h' => 'ПОДБОР', 'p' => 'Подберём модели под вашу задачу.'],
];
$advItems = [];
foreach (array_slice($advSrc, 0, 6) as $i => $a) $advItems[] = [$advIcons[$i], $T($a['h']), e($T($a['p']))];
$advBottom = $T($P['adv_bottom'] ?: (($P['brand'] ?: $crumb) . ' — напрямую с завода до вашего склада'));
require ROOT . '/inc/adv.php';
?>

<section class="sec">
  <div class="wrap kases">
    <h2 class="whs__title">КАК ЗАКАЗАТЬ</h2>
    <p class="whs__lead">От списка товаров до поставки на ваш склад.</p>
    <div class="ugrid ugrid--3">
      <div class="ucard"><div><h3>1. Заявка</h3><p>Присылаете список товаров и количество.</p></div></div>
      <div class="ucard"><div><h3>2. Предложение</h3><p>Согласуем с заводом цены и сроки, готовим расчёт с доставкой.</p></div></div>
      <div class="ucard"><div><h3>3. Договор и оплата</h3><p>Вы платите в рублях в России, мы оплачиваем заводу в юанях.</p></div></div>
      <div class="ucard"><div><h3>4. Производство</h3><p>Завод выпускает и упаковывает вашу партию.</p></div></div>
      <div class="ucard"><div><h3>5. Проверка</h3><p>Принимаем товар на нашем складе, проверяем и присылаем фото.</p></div></div>
      <div class="ucard"><div><h3>6. Доставка</h3><p>Везём в Россию с документами — до вашего города.</p></div></div>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap whs">
    <div class="whs__text">
      <h2 class="whs__title">ГОТОВЫ К СОТРУДНИЧЕСТВУ</h2>
<?php if ($P['coop_lead']): ?>      <p class="whs__lead"><?= e($T($P['coop_lead'])) ?></p>
<?php endif; ?>
<?php if ($P['coop']): ?>      <ul class="whs__list">
<?php foreach ($P['coop'] as $c): ?>        <li><?= e($T($c)) ?></li>
<?php endforeach; ?>      </ul>
<?php endif; ?>
      <p class="whs__note">Подробнее о работе: <a href="/oplata-postavshchikam-v-kitay">оплата поставщикам в Китай</a>, <a href="/sklad-v-guanchzhou">склад в Гуанчжоу (Фошань)</a>.</p>
    </div>
<?php if ($P['faq']): ?>
    <div class="whs__text">
      <h2 class="whs__title">ВОПРОСЫ И ОТВЕТЫ</h2>
<?php foreach ($P['faq'] as $i => $q): ?>
      <div class="faqi<?= $i === 0 ? ' open' : '' ?>">
        <button type="button" class="faqi__q"><span><?= e($T($q['q'])) ?></span><i></i></button>
        <div class="faqi__a"><p><?= e($T($q['a'])) ?></p></div>
      </div>
<?php endforeach; ?>
    </div>
<?php endif; ?>
  </div>
</section>

<?php require ROOT . '/inc/acc-cta.php'; ?>
<?php require ROOT . '/inc/form-question.php'; ?>
</main>
<?php require ROOT . '/inc/foot.php'; ?>
