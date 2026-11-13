<!DOCTYPE html>
<html lang="<?= lang_code() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Импорт из Китая под ключ без риска и задержек') ?></title>
<meta name="description" content="<?= e($descr ?? 'Официальный импорт товаров из Китая под ключ: поиск фабрик, выкуп, оплата в юанях, склад в Гуанчжоу, маркировка «Честный знак», доставка и таможенное оформление.') ?>">
<?php
// Пока сайт на тестовом адресе new.itcpartner.ru — поисковикам индексировать нельзя (иначе дубль сайта)
$isTest = strpos($_SERVER['HTTP_HOST'] ?? '', 'new.') === 0;
$pagePath = $canonical ?? (page_url() === '/' ? '/' : rtrim(page_url(), '/'));
$canonicalUrl = 'https://itcpartner.ru' . lang_prefix() . $pagePath;
$ogImg = !empty($ogImageUrl) ? $ogImageUrl : img($ogImage ?? 'og.jpg');
if (strpos($ogImg, 'http') !== 0) $ogImg = 'https://itcpartner.ru' . $ogImg;
?>
<?php if ($isTest || !empty($noindex)): ?><meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<meta name="yandex-verification" content="1da0d82d76d3dd42">
<link rel="canonical" href="<?= e($canonicalUrl) ?>">
<?php if (empty($noAlternates)): foreach (LANGS as $langRow): ?>
<link rel="alternate" hreflang="<?= $langRow[1] ?>" href="https://itcpartner.ru<?= e($langRow[2] . ($pagePath === '/' && $langRow[2] ? '/' : $pagePath)) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="https://itcpartner.ru<?= e($pagePath) ?>">
<?php endif; ?>
<meta property="og:locale" content="<?= ['ru' => 'ru_RU', 'en' => 'en_US', 'zh' => 'zh_CN'][lang()] ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="ИТК — Интернациональная Торговая Компания">
<meta property="og:url" content="<?= e($canonicalUrl) ?>">
<meta property="og:title" content="<?= e($title ?? 'Импорт из Китая под ключ без риска и задержек') ?>">
<meta property="og:description" content="<?= e($descr ?? 'Прямые поставки из Китая в РФ. Подбираем лучших производителей и организовываем логистику в любую точку России.') ?>">
<meta property="og:image" content="<?= e($ogImg) ?>">
<?php foreach ($jsonld ?? [] as $ld): ?>
<script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endforeach; ?>
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/img/favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="/img/favicon-180.png">
<link rel="stylesheet" href="/assets/style.css?v=<?= $v ?>">
<style>
<?php foreach ([300=>'Light',400=>'Regular',500=>'Medium',600=>'Semibold',700=>'Bold'] as $fw => $fn): ?>
@font-face{font-family:'Gilroy';src:url('<?= e(img("Gilroy-$fn.woff")) ?>') format('woff');font-weight:<?= $fw ?>;font-style:normal;font-display:swap}
<?php endforeach; ?>
</style>

</head>
<body class="<?= e($bodyClass ?? '') ?>">

<!-- ===================== ШАПКА ===================== -->
<header class="hdr" id="top">
  <div class="hdr__row1">
    <a href="/" class="hdr__logo"><img src="/img/logo.svg?v=<?= $v ?>" alt="ITC — International Trading Company" width="100" height="37"></a>
    <div class="hdr__right">
      <span class="hdr__hours">Ежедневно <b>24/7</b></span>
      <div class="hdr__soc">
        <a href="<?= e($cfg['telegram']) ?>" target="_blank" rel="noopener" aria-label="Телеграм"><img src="<?= e(img('tg.svg')) ?>" alt="" width="30" height="30"></a>
        <a href="mailto:<?= e($cfg['email']) ?>" aria-label="Почта"><img src="<?= e(img('mail.svg')) ?>" alt="" width="30" height="30"></a>
      </div>
      <div class="hdr__phones">
        <a href="tel:<?= e($cfg['phone1_raw']) ?>"><?= e($cfg['phone1']) ?></a>
        <a href="tel:<?= e($cfg['phone2_raw']) ?>"><?= e($cfg['phone2']) ?></a>
      </div>
<?php if (lang() === 'ru'): require_once ROOT . '/inc/account.php'; $accMe = acc_me(); ?>
      <a href="<?= $accMe ? '/kabinet' : '/vhod' ?>" class="hdr__acc" title="<?= $accMe ? 'Личный кабинет' : 'Вход и регистрация' ?>"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg><span><?= $accMe ? 'Кабинет' : 'Войти' ?></span></a>
<?php endif; ?>
      <a href="#zvonok" class="btn btn--green btn--sm js-pop">Заказать звонок</a>
      <a href="#raschet-form" class="btn btn--navy btn--sm js-pop">Быстрый расчет</a>
    </div>
    <nav class="lsw" aria-label="Язык сайта">
<?php foreach (LANGS as $langKey => $langRow): ?>
      <a href="<?= e(lang_switch_url($langKey)) ?>" hreflang="<?= $langRow[1] ?>"<?= $langKey === lang() ? ' class="on" aria-current="true"' : '' ?>><?= $langRow[0] ?></a>
<?php endforeach; ?>
    </nav>
    <button class="hdr__burger" aria-label="Меню" type="button">
      <svg viewBox="0 0 24 24" width="30" height="30"><path fill="currentColor" d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
    </button>
  </div>
  <nav class="hdr__nav">
    <a href="/">Главная</a>
    <a href="/onas">О компании</a>
    <div class="hdr__sub">
      <a href="/#services" class="hdr__subbtn">Услуги
        <svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M7 10l5 5 5-5z"/></svg></a>
      <div class="hdr__drop hdr__drop--one">
        <a href="/kargo-iz-kitaya">ИТК Карго<span>Доставка из Китая от 1 кг, выкуп с 1688 и фабрик</span></a>
        <a href="/vykup-tovarov-iz-kitaya">Выкуп товаров из Китая<span>1688, Taobao, Pinduoduo, Poizon — комиссия 5%</span></a>
        <a href="/oplata-postavshchikam-v-kitay">Оплата поставщикам в Китай<span>В юанях: счёт компании, Alipay, WeChat, карта</span></a>
        <a href="/sklad-v-guanchzhou">Склад в Гуанчжоу (Фошань)<span>Приём, проверка, хранение, сборка груза</span></a>
        <a href="/fulfilment-v-kitae">Фулфилмент для WB и Ozon<span>Этикетки, упаковка, наборы — на складе в Китае</span></a>
        <a href="/page126678496.html">Маркировка «Честный ЗНАК»<span>Регистрация, коды и этикетки на складе в Китае</span></a>
      </div>
    </div>
    <div class="hdr__sub">
      <a href="/#cases" class="hdr__subbtn">Направления
        <svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M7 10l5 5 5-5z"/></svg></a>
      <div class="hdr__drop hdr__drop--dirs">
<?php $dirs = site_dirs(); foreach (site_dirs_cols() as $col): ?>
        <div class="dcol">
<?php foreach ($col as $gk): if (empty($dirs[$gk])) continue; [$gName, $gItems] = $dirs[$gk]; ?>
          <div class="dgrp"><b class="dgrp__t"><?= e($gName) ?></b>
<?php foreach ($gItems as $it): $tr = $it[3] ? 'sup_t' : 'strval'; ?>
            <a href="<?= e($it[0]) ?>"><?= e($tr($it[1])) ?><span><?= e($tr($it[2])) ?></span></a>
<?php endforeach; ?>
          </div>
<?php endforeach; ?>
        </div>
<?php endforeach; ?>
      </div>
    </div>
    <a href="/news">Новости</a>
    <a href="/contacts">Контакты</a>
  </nav>
  <!-- Меню для телефона -->
  <div class="mmenu">
    <a href="/">Главная</a>
    <a href="/onas">О компании</a>
    <details class="mmenu__grp"><summary>Услуги<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M7 10l5 5 5-5z"/></svg></summary><div class="mmenu__list">
      <a href="/kargo-iz-kitaya">ИТК Карго</a>
      <a href="/vykup-tovarov-iz-kitaya">Выкуп товаров из Китая</a>
      <a href="/oplata-postavshchikam-v-kitay">Оплата поставщикам в Китай</a>
      <a href="/sklad-v-guanchzhou">Склад в Гуанчжоу (Фошань)</a>
      <a href="/fulfilment-v-kitae">Фулфилмент для WB и Ozon</a>
      <a href="/page126678496.html">Маркировка «Честный ЗНАК»</a>
    </div></details>
    <details class="mmenu__grp"><summary>Направления<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M7 10l5 5 5-5z"/></svg></summary><div class="mmenu__list mmenu__list--dirs">
<?php foreach (site_dirs() as [$gName, $gItems]): ?>
      <details class="mmenu__sub"><summary><?= e($gName) ?><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M7 10l5 5 5-5z"/></svg></summary><div class="mmenu__sublist">
<?php foreach ($gItems as $it): ?>
        <a href="<?= e($it[0]) ?>"><?= e($it[3] ? sup_t($it[1]) : $it[1]) ?></a>
<?php endforeach; ?>
      </div></details>
<?php endforeach; ?>
    </div></details>
    <a href="/news">Новости</a>
    <a href="/contacts">Контакты</a>
<?php if (lang() === 'ru'): ?>
    <a href="<?= !empty($accMe) ? '/kabinet' : '/vhod' ?>"><?= !empty($accMe) ? 'Личный кабинет' : 'Вход и регистрация' ?></a>
<?php endif; ?>
    <div class="mmenu__contacts">
      <span>Ежедневно 24/7</span>
      <a href="tel:<?= e($cfg['phone1_raw']) ?>"><?= e($cfg['phone1']) ?></a>
      <a href="tel:<?= e($cfg['phone2_raw']) ?>"><?= e($cfg['phone2']) ?></a>
    </div>
    <a href="#zvonok" class="btn btn--green js-pop">Заказать звонок</a>
    <a href="#raschet-form" class="btn btn--navy js-pop">Быстрый расчет</a>
  </div>
</header>

