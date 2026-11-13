<?php
// Текстовые документы: политика и согласие. Перед подключением задать $docFile, $title, $crumb
$bodyClass = 'inner';
require ROOT . '/inc/head.php';
?>
<main class="page">
<?php require ROOT . '/inc/crumbs.php'; ?>
<section class="sec">
  <div class="wrap doc">
<?php if (lang() !== 'ru'): ?>
    <p class="doc__lang"><b>Документ опубликован на русском языке. Юридическую силу имеет русская версия.</b></p>
<?php endif; ?>
    <?php require ROOT . '/pages/' . $docFile; ?></div>
</section>
</main>
<?php require ROOT . '/inc/foot.php'; ?>
