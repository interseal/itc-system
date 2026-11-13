<?php
require_once ROOT . '/inc/news.php';
$news = news_all();
$post = $news[$slug] ?? null;
if (!$post && lang() !== 'ru') { header('Location: /tpost/' . $slug, true, 302); exit; }
if (!$post) { http_response_code(404); require ROOT . '/pages/404.php'; return; }
$title = $post['title'];
$descr = mb_substr(trim(strip_tags($post['html'])), 0, 160);
$bodyClass = 'inner';
$crumb = 'Новости';
require ROOT . '/inc/head.php';
?>
<main class="page">
<nav class="crumbs wrap"><a href="/">Главная</a><span>→</span><a href="/news">Новости</a><span>→</span><b><?= e($post['title']) ?></b></nav>
<article class="sec post">
  <div class="wrap post__in">
    <h1><?= e($post['title']) ?></h1>
    <span class="newsi__date"><?= e($post['date']) ?></span>
<?php if (empty($post['auto'])): ?>
    <img class="post__cover" src="<?= e(timg($post['cover'])) ?>" alt="">
<?php else: ?>
    <?= news_cover($post, 'post__cover post__cover--auto') ?>
<?php endif; ?>
    <div class="post__text"><?= $post['html'] ?></div>
    <a class="plus-line plus-line--link" href="/news"><span class="plus">+</span>ВСЕ НОВОСТИ</a>
  </div>
</article>
<?php require ROOT . '/inc/form-question.php'; ?>
</main>
<?php require ROOT . '/inc/foot.php'; ?>
