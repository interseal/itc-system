<?php // Приглашение зарегистрироваться (3.51.0) — внизу страниц фабрик, только на русской версии и только тем, кто не вошёл
if (lang() !== 'ru') return;
require_once ROOT . '/inc/account.php';
if (acc_me()) return;
?>
<section class="sec acc-cta">
  <div class="wrap acc-cta__in">
    <div class="acc-cta__ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg></div>
    <div class="acc-cta__txt">
      <b>Зарегистрируйтесь — получите больше возможностей</b>
      <span>Новые фабрики и товары в вашей сфере — первыми, на почту. Запросы цен в один клик, без повторного ввода данных.</span>
    </div>
    <a class="btn btn--green btn--lg acc-cta__btn" href="/registraciya?from=<?= e(rawurlencode(page_url())) ?>">Зарегистрироваться</a>
  </div>
</section>
