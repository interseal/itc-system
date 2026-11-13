<?php
$title = 'Страница не найдена';
$bodyClass = 'inner';
$crumb = 'Страница не найдена';
require ROOT . '/inc/head.php';
?>
<main class="page">
<?php require ROOT . '/inc/crumbs.php'; ?>
<section class="sec">
  <div class="wrap nf">
    <h1 class="h70">404</h1>
    <p>Такой страницы нет. Возможно, она переехала.</p>
    <a href="/" class="btn btn--green btn--lg">На главную</a>
  </div>
</section>
</main>
<?php require ROOT . '/inc/foot.php'; ?>
