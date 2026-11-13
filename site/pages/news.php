<?php
$title = 'Новости - INTERNATIONAL TRAIDING COMPANY';
$descr = 'Новости Интернациональной Торговой Компании: выставки в Китае, поставки и импорт.';
$bodyClass = 'inner';
$crumb = 'Новости';
require_once ROOT . '/inc/news.php';
$all   = news_upcoming();
$per   = 48;
$pages = max(1, (int)ceil(count($all) / $per));
$page  = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$list  = array_slice($all, ($page - 1) * $per, $per, true);
require ROOT . '/inc/head.php';
?>
<main class="page">
<?php require ROOT . '/inc/crumbs.php'; ?>
<section class="sec news-sec">
  <div class="wrap">
    <h1 class="h42 center">НОВОСТИ КОМПАНИИ</h1>
    <div class="news news--4">
<?php foreach ($list as $slug => $n): ?>
      <a class="newsi" href="/tpost/<?= e($slug) ?>">
        <?= news_cover($n, 'newsi__img') ?>
        <h3><?= e($n['title']) ?></h3>
        <span class="newsi__date"><?= e($n['date']) ?></span>
      </a>
<?php endforeach; ?>
    </div>
<?php if ($pages > 1): ?>
    <nav class="pager">
<?php for ($i = 1; $i <= $pages; $i++): ?>
      <?php if ($i === $page): ?><b><?= $i ?></b><?php else: ?><a href="/news<?= $i > 1 ? '?page=' . $i : '' ?>"><?= $i ?></a><?php endif; ?>
<?php endfor; ?>
    </nav>
<?php endif; ?>
  </div>
</section>
<?php require ROOT . '/inc/form-question.php'; ?>
</main>
<?php require ROOT . '/inc/foot.php'; ?>
