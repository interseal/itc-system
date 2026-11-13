<?php
// Бланк заказа для печати (3.60.0): /{завод}/blank — все модели завода с фото, описанием и таблицей вариантов
// (размеры, цвета и прочие варианты) с пустыми клетками, куда клиент ручкой пишет количество.
// Отдельная страница без шапки и подвала сайта, формат А4. Перед печатью можно выбрать разделы каталога.
$P = $supPage['data'];
$T = function ($s) { return sup_t($s); };
$slug = $P['slug'];
$brand = $P['brand'] ?: $T($P['crumb']);
$U = ['pcs' => 'шт.', 'set' => 'компл.', 'pair' => 'пар', 'm' => 'м'];

// Таблица заказа по вариантам модели: столбцы — вариант с наименьшим числом значений (до 8),
// строки — все сочетания остальных вариантов. Свободные поля (без списка значений) — строкой «___».
function blank_grid($opt) {
    $lists = []; $free = [];
    foreach ($opt as $o) {
        if (isset($o['v']) && count($o['v']) >= 2) $lists[] = $o;
        elseif (isset($o['v']) && count($o['v']) === 1) $free[] = [$o['n'], $o['v'][0]];
        else $free[] = [$o['n'], ''];
    }
    $col = null; $best = 99;
    foreach ($lists as $i => $o) { $n = count($o['v']); if ($n <= 8 && $n < $best) { $best = $n; $col = $i; } }
    $cols = $col === null ? null : $lists[$col];
    $rowOpts = []; foreach ($lists as $i => $o) if ($i !== $col) $rowOpts[] = $o;
    $rows = [[]];
    foreach ($rowOpts as $o) { $nr = []; foreach ($rows as $r) foreach ($o['v'] as $v) { $x = $r; $x[] = $v; $nr[] = $x; } $rows = $nr; }
    return [$cols, $rowOpts, $rows, $free];
}
// Объединение одинаковых значений подряд в левых столбцах (не последний столбец строки): 0 — ячейку не выводить
function blank_span($rows, $ri, $k) {
    if ($k >= count($rows[$ri]) - 1) return 1;
    $same = function ($a, $b) use ($rows, $k) { for ($i = 0; $i <= $k; $i++) if ($rows[$a][$i] !== $rows[$b][$i]) return false; return true; };
    if ($ri > 0 && $same($ri, $ri - 1)) return 0;
    $n = 1; while ($ri + $n < count($rows) && $same($ri, $ri + $n)) $n++;
    return $n;
}
$sel = isset($_GET['g']) ? array_flip(array_map('intval', explode(',', (string)$_GET['g']))) : null;
?><!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($T('Бланк заказа')) ?> — <?= e($brand) ?> | ИТК</title>
<style>
@font-face{font-family:Roboto;font-weight:400;src:url(/assets/fonts/roboto-cyrillic-400-normal.woff2) format('woff2');unicode-range:U+0400-04FF}
@font-face{font-family:Roboto;font-weight:400;src:url(/assets/fonts/roboto-latin-400-normal.woff2) format('woff2')}
@font-face{font-family:Roboto;font-weight:700;src:url(/assets/fonts/roboto-cyrillic-700-normal.woff2) format('woff2');unicode-range:U+0400-04FF}
@font-face{font-family:Roboto;font-weight:700;src:url(/assets/fonts/roboto-latin-700-normal.woff2) format('woff2')}
:root{--ink:#1a2233;--mut:#5b6575;--line:#9aa3b2;--navy:#0d2a6b;--bg:#eef1f6}
*{box-sizing:border-box}
body{margin:0;font:12px/1.35 Roboto,Arial,sans-serif;color:var(--ink);background:var(--bg)}
.bar{position:sticky;top:0;z-index:5;background:#fff;border-bottom:1px solid #d5dbe5;padding:12px 16px}
.bar__in{max-width:900px;margin:0 auto}
.bar h1{font-size:18px;margin:0 0 6px}
.bar p{margin:0 0 10px;color:var(--mut)}
.bar__g{display:flex;flex-wrap:wrap;gap:6px 14px;max-height:140px;overflow:auto;margin:0 0 10px;padding:8px;border:1px solid #e3e7ee;border-radius:8px}
.bar__g label{display:flex;gap:6px;align-items:center;cursor:pointer}
.bar__b{display:flex;flex-wrap:wrap;gap:8px}
.btn{display:inline-flex;align-items:center;gap:6px;border:0;border-radius:8px;padding:10px 16px;font:500 14px Roboto,Arial,sans-serif;cursor:pointer;background:#e8ecf3;color:var(--ink)}
.btn--p{background:var(--navy);color:#fff}
.btn svg{width:18px;height:18px;fill:currentColor}
.sheet{max-width:900px;margin:16px auto;background:#fff;padding:14mm 12mm;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.hd{display:flex;align-items:center;gap:14px;border-bottom:2px solid var(--navy);padding-bottom:8px}
.hd img{height:30px}
.hd h2{margin:0;font-size:18px;color:var(--navy)}
.hd small{display:block;color:var(--mut);font-size:11px}
.cl{display:grid;grid-template-columns:1fr 1fr;gap:6px 18px;margin:10px 0 4px}
.cl div{border-bottom:1px solid var(--line);padding:10px 0 2px;color:var(--mut);font-size:11px}
.howto{margin:6px 0 4px;color:var(--mut);font-size:11px}
.grp{font-size:15px;color:var(--navy);margin:16px 0 6px;padding-bottom:3px;border-bottom:1px solid #c9d1de;break-after:avoid}
.it{display:grid;grid-template-columns:30mm 1fr;gap:10px;padding:8px 0;border-bottom:1px solid #e3e7ee;break-inside:avoid}
.it--long{break-inside:auto}
tr{break-inside:avoid}
tr.sec td{background:#f7f8fb;font-weight:500;color:#0d2a6b}
.it__ph{width:30mm;height:30mm;object-fit:contain;background:#fafbfc;border:1px solid #eef0f4}
.it h3{margin:0 0 2px;font-size:13px}
.it__d{margin:0 0 6px;color:var(--mut);font-size:10.5px}
table{border-collapse:collapse;width:auto;max-width:100%;font-size:10.5px}
th,td{border:1px solid var(--line);padding:3px 6px;text-align:left;vertical-align:middle}
th{background:#f1f3f7;font-weight:500}
td.q{min-width:16mm;height:7mm}
.fr{margin:4px 0;font-size:10.5px}
.fr span{display:inline-block;min-width:40mm;border-bottom:1px solid var(--line)}
.ft{margin-top:14px;padding-top:8px;border-top:1px solid #c9d1de;color:var(--mut);font-size:10.5px}
.off{display:none}
@media print{
  @page{size:A4;margin:10mm}
  body{background:#fff}
  .bar{display:none}
  .sheet{max-width:none;margin:0;padding:0;box-shadow:none}
}
</style>
</head>
<body>
<div class="bar">
  <div class="bar__in">
    <h1><span><?= e($T('Бланк заказа')) ?></span> — <?= e($brand) ?></h1>
    <p><?= e($T('Отметьте разделы, которые нужно распечатать, и нажмите «Распечатать». На бумаге впишите количество в клетки нужных размеров и цветов.')) ?></p>
    <div class="bar__g" id="bg">
<?php foreach ($P['groups'] as $gi => $g): if (!$g['items']) continue; ?>
      <label><input type="checkbox" value="<?= $gi ?>"<?= $sel === null || isset($sel[$gi]) ? ' checked' : '' ?>> <?= e($T($g['name'] ?: $brand)) ?> (<?= count($g['items']) ?>)</label>
<?php endforeach; ?>
    </div>
    <div class="bar__b">
      <button class="btn btn--p" type="button" id="bp"><svg viewBox="0 0 24 24"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg><?= e($T('Распечатать')) ?></button>
      <button class="btn" type="button" id="ba"><?= e($T('Выбрать все')) ?></button>
      <button class="btn" type="button" id="bn"><?= e($T('Снять все')) ?></button>
      <a class="btn" href="<?= e(lang_prefix() . '/' . $slug) ?>"><?= e($T('Вернуться на страницу завода')) ?></a>
    </div>
  </div>
</div>

<div class="sheet">
  <div class="hd">
    <img src="/img/logo.svg?v=<?= $v ?>" alt="ITC">
    <div><h2><span><?= e($T('Бланк заказа')) ?></span>: <?= e($brand) ?></h2><small>ООО «Интернациональная Торговая Компания» · itcpartner.ru/<?= e($slug) ?> · <?= e($cfg['phone1'] ?? '') ?> · <?= e($cfg['email'] ?? '') ?></small></div>
  </div>
  <div class="cl">
    <div><?= e($T('Компания')) ?></div><div><?= e($T('ИНН')) ?></div>
    <div><?= e($T('Контактное лицо')) ?></div><div><?= e($T('Телефон')) ?></div>
    <div><?= e($T('Город доставки')) ?></div><div><?= e($T('Дата')) ?></div>
  </div>
  <p class="howto"><?= e($T('Впишите количество в клетку на пересечении нужного размера и цвета. Заполненный бланк сфотографируйте и отправьте менеджеру — подготовим предложение с ценами и сроками.')) ?></p>

<?php foreach ($P['groups'] as $gi => $g): if (!$g['items']) continue; $on = $sel === null || isset($sel[$gi]); ?>
  <section class="g<?= $on ? '' : ' off' ?>" data-g="<?= $gi ?>">
<?php if ($g['name']): ?>    <h2 class="grp"><?= e($T($g['name'])) ?></h2>
<?php endif; ?>
<?php foreach ($g['items'] as $m):
      [$cols, $rowOpts, $rows, $free] = blank_grid($m['opt'] ?? []);
      $u = $T($U[$m['u'] ?? 'pcs'] ?? 'шт.');
      $ph = !empty($m['photos'][0]) ? sup_img($m['photos'][0]) : ''; ?>
    <div class="it<?= count($rows) > 14 ? ' it--long' : '' ?>">
      <?php if ($ph): ?><img class="it__ph" data-src="<?= e($ph) ?>"<?= $on ? ' src="' . e($ph) . '"' : '' ?> alt=""><?php else: ?><div></div><?php endif; ?>
      <div>
        <h3><?= e($T($m['name'])) ?></h3>
<?php if (!empty($m['desc'])): ?>        <p class="it__d"><?= e($T($m['desc'])) ?></p>
<?php endif; ?>
<?php foreach ($free as $f): ?>        <p class="fr"><?= e($T($f[0])) ?>: <?= $f[1] !== '' ? e($T($f[1])) : '<span></span>' ?></p>
<?php endforeach; ?>
        <table>
<?php if ($cols && !$rowOpts): ?>          <tr><th colspan="<?= count($cols['v']) ?>"><?= e($T($cols['n'])) ?> · <?= e($T('количество')) ?>, <?= e($u) ?></th></tr>
          <tr><?php foreach ($cols['v'] as $cv): ?><th><?= e($T($cv)) ?></th><?php endforeach; ?></tr>
          <tr><?php foreach ($cols['v'] as $cv): ?><td class="q"></td><?php endforeach; ?></tr>
<?php elseif ($cols): ?>          <tr><th><?= e($T(end($rowOpts)['n'])) ?></th><th colspan="<?= count($cols['v']) ?>"><?= e($T($cols['n'])) ?> · <?= e($T('количество')) ?>, <?= e($u) ?></th></tr>
          <tr><th></th><?php foreach ($cols['v'] as $cv): ?><th><?= e($T($cv)) ?></th><?php endforeach; ?></tr>
<?php $prev = null; foreach ($rows as $r): $head = array_slice($r, 0, -1); $last = end($r);
            if ($head && $head !== $prev): $prev = $head; ?>          <tr class="sec"><td colspan="<?= 1 + count($cols['v']) ?>"><?= e(implode(' · ', array_map(function ($i) use ($T, $rowOpts, $head) { return $T($rowOpts[$i]['n']) . ': ' . $T($head[$i]); }, array_keys($head)))) ?></td></tr>
<?php endif; ?>          <tr><td><?= e($T($last)) ?></td><?php foreach ($cols['v'] as $cv): ?><td class="q"></td><?php endforeach; ?></tr>
<?php endforeach; ?>
<?php elseif ($rowOpts): ?>          <tr><th><?= e($T(end($rowOpts)['n'])) ?></th><th><?= e($T('количество')) ?>, <?= e($u) ?></th></tr>
<?php $prev = null; foreach ($rows as $r): $head = array_slice($r, 0, -1); $last = end($r);
            if ($head && $head !== $prev): $prev = $head; ?>          <tr class="sec"><td colspan="2"><?= e(implode(' · ', array_map(function ($i) use ($T, $rowOpts, $head) { return $T($rowOpts[$i]['n']) . ': ' . $T($head[$i]); }, array_keys($head)))) ?></td></tr>
<?php endif; ?>          <tr><td><?= e($T($last)) ?></td><td class="q"></td></tr>
<?php endforeach; ?>
<?php else: ?>          <tr><th><?= e($T('количество')) ?>, <?= e($u) ?></th><td class="q"></td></tr>
<?php endif; ?>
        </table>
      </div>
    </div>
<?php endforeach; ?>
  </section>
<?php endforeach; ?>
  <div class="ft"><?= e($T('Цены, минимальный заказ и сроки подтверждаем после получения бланка. Официальный ввоз, оплата в рублях, полный пакет документов.')) ?></div>
</div>

<script>
(function(){
  var box=document.getElementById('bg');
  function apply(){
    var on={};[].forEach.call(box.querySelectorAll('input'),function(i){on[i.value]=i.checked;});
    [].forEach.call(document.querySelectorAll('section.g'),function(s){
      var v=!!on[s.getAttribute('data-g')]; s.classList.toggle('off',!v);
      if(v)[].forEach.call(s.querySelectorAll('img[data-src]'),function(im){if(!im.getAttribute('src'))im.src=im.getAttribute('data-src');});
    });
  }
  box.addEventListener('change',apply);
  document.getElementById('ba').onclick=function(){[].forEach.call(box.querySelectorAll('input'),function(i){i.checked=true;});apply();};
  document.getElementById('bn').onclick=function(){[].forEach.call(box.querySelectorAll('input'),function(i){i.checked=false;});apply();};
  document.getElementById('bp').onclick=function(){
    apply();
    var ims=[].filter.call(document.querySelectorAll('section.g:not(.off) img'),function(i){return !i.complete;}), left=ims.length, done=false;
    function go(){if(done)return;done=true;window.print();}
    if(!left){go();return;}
    ims.forEach(function(i){i.addEventListener('load',function(){if(--left<=0)go();});i.addEventListener('error',function(){if(--left<=0)go();});});
    setTimeout(go,15000);
  };
})();
</script>
</body>
</html>
