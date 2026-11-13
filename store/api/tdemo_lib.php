<?php
// ==== ИТК · ДЕМО-СЕРИИ НА СЕРВЕРЕ · api/tdemo_lib.php · №2 (10.11.2026) ====
// Стратегии и индикаторы — точный перенос с телефона (strategies.js, patterns.js, «Отскок от уровней» из app8.js),
// чтобы сервер принимал те же решения, что и приложение. Свеча: ['time'=>сек,'open','high','low','close','volume'].
// Сигнал: ['i'=>номер свечи, 't'=>'long'|'short'|'exit'].

function td_ema($a, $n) { $k = 2 / ($n + 1); $o = []; $e = null; foreach ($a as $i => $v) { $e = $e === null ? $v : $v * $k + $e * (1 - $k); $o[] = $i < $n - 1 ? null : $e; } return $o; }
function td_ema_all($a, $n) { $k = 2 / ($n + 1); $o = []; $e = null; foreach ($a as $v) { $e = $e === null ? $v : $v * $k + $e * (1 - $k); $o[] = $e; } return $o; }
function td_sma($a, $n) { $o = []; $s = 0; foreach ($a as $i => $v) { $s += $v; if ($i >= $n) $s -= $a[$i - $n]; $o[] = $i < $n - 1 ? null : $s / $n; } return $o; }
function td_sd($a, $n) { $m = td_sma($a, $n); $o = []; foreach ($a as $i => $_) { if ($m[$i] === null) { $o[] = null; continue; } $s = 0; for ($j = $i - $n + 1; $j <= $i; $j++) $s += ($a[$j] - $m[$i]) ** 2; $o[] = sqrt($s / $n); } return $o; }
function td_rsi($a, $n) { $o = [null]; $g = 0; $l = 0; for ($i = 1; $i < count($a); $i++) { $d = $a[$i] - $a[$i - 1]; $up = max($d, 0); $dn = max(-$d, 0);
  if ($i <= $n) { $g += $up / $n; $l += $dn / $n; $o[] = $i < $n ? null : 100 - 100 / (1 + $g / ($l ?: 1e-9)); }
  else { $g = ($g * ($n - 1) + $up) / $n; $l = ($l * ($n - 1) + $dn) / $n; $o[] = 100 - 100 / (1 + $g / ($l ?: 1e-9)); } } return $o; }
function td_atr($c, $n) { $tr = []; foreach ($c as $i => $x) $tr[] = $i ? max($x['high'] - $x['low'], abs($x['high'] - $c[$i - 1]['close']), abs($x['low'] - $c[$i - 1]['close'])) : $x['high'] - $x['low'];
  $o = []; $a = null; for ($i = 0; $i < count($tr); $i++) { if ($i < $n - 1) { $o[] = null; continue; } $a = $a === null ? array_sum(array_slice($tr, 0, $n)) / $n : ($a * ($n - 1) + $tr[$i]) / $n; $o[] = $a; } return $o; }
function td_cross($a, $b, $i) { return $a[$i - 1] !== null && $b[$i - 1] !== null && $a[$i - 1] <= $b[$i - 1] && $a[$i] > $b[$i]; }
function td_cl($c) { return array_map(fn($x) => $x['close'], $c); }

// ---------- стратегии из strategies.js ----------
function td_s_ema($c, $p) { $cl = td_cl($c); $f = td_ema($cl, $p['f']); $s = td_ema($cl, $p['s']); $o = []; for ($i = 1; $i < count($c); $i++) { if (td_cross($f, $s, $i)) $o[] = ['i' => $i, 't' => 'long']; elseif (td_cross($s, $f, $i)) $o[] = ['i' => $i, 't' => 'short']; } return $o; }
function td_s_st($c, $p) { $a = td_atr($c, $p['n']); $up = []; $dn = []; $dir = [];
  for ($i = 0; $i < count($c); $i++) { if ($a[$i] === null) { $up[] = null; $dn[] = null; $dir[] = 0; continue; } $m = ($c[$i]['high'] + $c[$i]['low']) / 2; $u = $m - $p['m'] * $a[$i]; $d = $m + $p['m'] * $a[$i];
    if ($i > 0 && $up[$i - 1] !== null && $c[$i - 1]['close'] > $up[$i - 1]) $u = max($u, $up[$i - 1]);
    if ($i > 0 && $dn[$i - 1] !== null && $c[$i - 1]['close'] < $dn[$i - 1]) $d = min($d, $dn[$i - 1]);
    $up[] = $u; $dn[] = $d; $r = ($i > 0 && $dir[$i - 1]) ? $dir[$i - 1] : 1; if ($r === 1 && $c[$i]['close'] < $u) $r = -1; elseif ($r === -1 && $c[$i]['close'] > $d) $r = 1; $dir[] = $r; }
  $o = []; for ($i = 1; $i < count($c); $i++) if ($dir[$i - 1] && $dir[$i] !== $dir[$i - 1]) $o[] = ['i' => $i, 't' => $dir[$i] === 1 ? 'long' : 'short']; return $o; }
function td_s_rsi($c, $p) { $cl = td_cl($c); $r = td_rsi($cl, $p['n']); $e = td_ema($cl, $p['t']); $o = []; $pos = 0;
  for ($i = 1; $i < count($c); $i++) { if ($r[$i] === null || $r[$i - 1] === null || $e[$i] === null) continue;
    if ($pos !== 1 && $r[$i - 1] < $p['lo'] && $r[$i] >= $p['lo'] && $cl[$i] > $e[$i]) { $o[] = ['i' => $i, 't' => 'long']; $pos = 1; }
    elseif ($pos !== -1 && $r[$i - 1] > $p['hi'] && $r[$i] <= $p['hi'] && $cl[$i] < $e[$i]) { $o[] = ['i' => $i, 't' => 'short']; $pos = -1; }
    elseif ($pos === 1 && $r[$i] >= 50 + ($p['hi'] - 50) / 2) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; }
    elseif ($pos === -1 && $r[$i] <= 50 - (50 - $p['lo']) / 2) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } } return $o; }
function td_hl($c, $n, $i) { $h = -1e18; $l = 1e18; for ($j = $i - $n; $j < $i; $j++) { $h = max($h, $c[$j]['high']); $l = min($l, $c[$j]['low']); } return [$h, $l]; }
function td_s_don($c, $p) { $o = []; $pos = 0; for ($i = $p['n']; $i < count($c); $i++) { [$h, $l] = td_hl($c, $p['n'], $i); [$xh, $xl] = td_hl($c, $p['x'], $i); $x = $c[$i]['close'];
    if ($pos !== 1 && $x > $h) { $o[] = ['i' => $i, 't' => 'long']; $pos = 1; } elseif ($pos !== -1 && $x < $l) { $o[] = ['i' => $i, 't' => 'short']; $pos = -1; }
    elseif ($pos === 1 && $x < $xl) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } elseif ($pos === -1 && $x > $xh) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } } return $o; }
function td_s_macd($c, $p) { $cl = td_cl($c); $f = td_ema($cl, $p['f']); $s = td_ema($cl, $p['s']); $m = []; foreach ($cl as $i => $_) $m[] = ($f[$i] === null || $s[$i] === null) ? null : $f[$i] - $s[$i];
  $st = -1; foreach ($m as $i => $v) if ($v !== null) { $st = $i; break; } if ($st < 0) return [];
  $sg = array_merge(array_fill(0, $st, null), td_ema(array_slice($m, $st), $p['g'])); $o = []; $pos = 0;
  for ($i = $st + $p['g']; $i < count($c); $i++) { if (td_cross($m, $sg, $i)) { if ($m[$i] < 0 && $pos === -1) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } elseif ($m[$i] >= 0) { $o[] = ['i' => $i, 't' => 'long']; $pos = 1; } }
    elseif (td_cross($sg, $m, $i)) { if ($m[$i] > 0 && $pos === 1) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } elseif ($m[$i] <= 0) { $o[] = ['i' => $i, 't' => 'short']; $pos = -1; } } } return $o; }
function td_s_bb($c, $p) { $cl = td_cl($c); $m = td_sma($cl, $p['n']); $d = td_sd($cl, $p['n']); $u = []; $l = []; foreach ($m as $i => $v) { $u[] = $v === null ? null : $v + $p['k'] * $d[$i]; $l[] = $v === null ? null : $v - $p['k'] * $d[$i]; }
  $o = []; $pos = 0; for ($i = $p['n']; $i < count($c); $i++) { $x = $c[$i]['close']; $y = $c[$i - 1]['close'];
    if ($pos !== 1 && $l[$i - 1] !== null && $y < $l[$i - 1] && $x > $l[$i]) { $o[] = ['i' => $i, 't' => 'long']; $pos = 1; }
    elseif ($pos !== -1 && $u[$i - 1] !== null && $y > $u[$i - 1] && $x < $u[$i]) { $o[] = ['i' => $i, 't' => 'short']; $pos = -1; }
    elseif ($pos === 1 && $x >= $m[$i]) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } elseif ($pos === -1 && $x <= $m[$i]) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } } return $o; }

// ---------- свечные паттерны и фигуры (patterns.js) ----------
function td_candles($c) { $out = []; $cl = td_cl($c); $e20 = td_ema_all($cl, 20);
  $B = fn($x) => abs($x['close'] - $x['open']); $R = fn($x) => max(1e-12, $x['high'] - $x['low']); $U = fn($x) => $x['high'] - max($x['open'], $x['close']); $L = fn($x) => min($x['open'], $x['close']) - $x['low'];
  $up = fn($x) => $x['close'] > $x['open']; $dn = fn($x) => $x['close'] < $x['open'];
  $avgB = function ($i) use ($c, $B) { $s = 0; $n = 0; for ($j = max(0, $i - 14); $j < $i; $j++) { $s += $B($c[$j]); $n++; } return $n ? $s / $n : $B($c[$i]); };
  for ($i = 5; $i < count($c); $i++) { $x = $c[$i]; $p = $c[$i - 1]; $q = $c[$i - 2]; $downT = $cl[$i - 1] < $e20[$i - 1] && $cl[$i - 1] < $cl[$i - 5]; $upT = $cl[$i - 1] > $e20[$i - 1] && $cl[$i - 1] > $cl[$i - 5]; $ab = $avgB($i);
    $add = function ($dir, $str) use (&$out, $i) { $out[] = ['i' => $i, 'dir' => $dir, 'str' => $str]; };
    if ($downT && $L($x) >= 2 * $B($x) && $U($x) <= max($B($x) * 0.5, $R($x) * 0.1) && $B($x) > $R($x) * 0.05) $add(1, 2);
    if ($upT && $U($x) >= 2 * $B($x) && $L($x) <= max($B($x) * 0.5, $R($x) * 0.1) && $B($x) > $R($x) * 0.05) $add(-1, 2);
    if ($downT && $dn($p) && $up($x) && $x['close'] >= $p['open'] && $x['open'] <= $p['close'] && $B($x) > $B($p) * 1.05) $add(1, 3);
    if ($upT && $up($p) && $dn($x) && $x['close'] <= $p['open'] && $x['open'] >= $p['close'] && $B($x) > $B($p) * 1.05) $add(-1, 3);
    if ($dn($q) && $B($q) > $ab && $B($p) < $B($q) * 0.4 && $up($x) && $x['close'] > ($q['open'] + $q['close']) / 2 && $cl[$i - 2] < $e20[$i - 2]) $add(1, 3);
    if ($up($q) && $B($q) > $ab && $B($p) < $B($q) * 0.4 && $dn($x) && $x['close'] < ($q['open'] + $q['close']) / 2 && $cl[$i - 2] > $e20[$i - 2]) $add(-1, 3);
    if ($up($x) && $up($p) && $up($q) && $x['close'] > $p['close'] && $p['close'] > $q['close'] && $U($x) < $B($x) * 0.3 && $U($p) < $B($p) * 0.3 && $B($x) > $ab * 0.7 && $B($p) > $ab * 0.7 && $B($q) > $ab * 0.7 && !$upT) $add(1, 2);
    if ($dn($x) && $dn($p) && $dn($q) && $x['close'] < $p['close'] && $p['close'] < $q['close'] && $L($x) < $B($x) * 0.3 && $L($p) < $B($p) * 0.3 && $B($x) > $ab * 0.7 && $B($p) > $ab * 0.7 && $B($q) > $ab * 0.7 && !$downT) $add(-1, 2);
    if ($downT && $dn($p) && $B($p) > $ab && $up($x) && $x['open'] < $p['close'] && $x['close'] > ($p['open'] + $p['close']) / 2 && $x['close'] < $p['open']) $add(1, 2);
    if ($upT && $up($p) && $B($p) > $ab && $dn($x) && $x['open'] > $p['close'] && $x['close'] < ($p['open'] + $p['close']) / 2 && $x['close'] > $p['open']) $add(-1, 2);
    if ($downT && $dn($p) && $B($p) > $ab * 1.2 && $up($x) && $x['open'] > $p['close'] && $x['close'] < $p['open'] && $B($x) < $B($p) * 0.5) $add(1, 1);
    if ($upT && $up($p) && $B($p) > $ab * 1.2 && $dn($x) && $x['open'] < $p['close'] && $x['close'] > $p['open'] && $B($x) < $B($p) * 0.5) $add(-1, 1);
    if ($B($x) <= $R($x) * 0.08 && $R($x) > $ab * 0.8 && ($upT || $downT)) $add(0, 1); }
  return $out; }
function td_pivots($c, $k) { $H = []; $L = []; for ($i = $k; $i < count($c) - $k; $i++) { $h = true; $l = true; for ($j = $i - $k; $j <= $i + $k; $j++) { if ($j === $i) continue; if ($c[$j]['high'] > $c[$i]['high']) $h = false; if ($c[$j]['low'] < $c[$i]['low']) $l = false; } if ($h) $H[] = [$i, $c[$i]['high']]; if ($l) $L[] = [$i, $c[$i]['low']]; } return [$H, $L]; }
function td_near($a, $b, $t) { return abs($a - $b) / (($a + $b) / 2) <= $t; }
function td_lineAt($l, $i) { $d = $l[1][0] - $l[0][0]; return $l[0][1] + ($l[1][1] - $l[0][1]) * ($i - $l[0][0]) / ($d ?: 1); }
// фигуры: нужны только номер свечи пробоя (brk), направление (dir) и ключ для удаления дубликатов
function td_figures($c, $k) { [$H, $L] = td_pivots($c, $k); $out = []; $n = count($c);
  $brkBelow = function ($from, $lvl) use ($c, $n) { for ($i = $from; $i < $n; $i++) if ($c[$i]['close'] < (is_callable($lvl) ? $lvl($i) : $lvl)) return $i; return -1; };
  $brkAbove = function ($from, $lvl) use ($c, $n) { for ($i = $from; $i < $n; $i++) if ($c[$i]['close'] > (is_callable($lvl) ? $lvl($i) : $lvl)) return $i; return -1; };
  for ($a = 0; $a < count($H) - 1; $a++) { $h1 = $H[$a]; $h2 = $H[$a + 1]; if ($h2[0] - $h1[0] < 6 || !td_near($h1[1], $h2[1], 0.015)) continue; $lo = null; foreach ($L as $l) if ($l[0] > $h1[0] && $l[0] < $h2[0] && (!$lo || $l[1] < $lo[1])) $lo = $l;
    if (!$lo || (max($h1[1], $h2[1]) - $lo[1]) / $lo[1] < 0.03) continue; $b = $brkBelow($h2[0] + $k, $lo[1]); if ($b >= 0 && $b - $h2[0] > 40) continue; $out[] = ['id' => 'dtop', 'dir' => -1, 'brk' => $b, 'key' => $lo[0]]; }
  for ($a = 0; $a < count($L) - 1; $a++) { $l1 = $L[$a]; $l2 = $L[$a + 1]; if ($l2[0] - $l1[0] < 6 || !td_near($l1[1], $l2[1], 0.015)) continue; $hi = null; foreach ($H as $h) if ($h[0] > $l1[0] && $h[0] < $l2[0] && (!$hi || $h[1] > $hi[1])) $hi = $h;
    if (!$hi || ($hi[1] - min($l1[1], $l2[1])) / $hi[1] < 0.03) continue; $b = $brkAbove($l2[0] + $k, $hi[1]); if ($b >= 0 && $b - $l2[0] > 40) continue; $out[] = ['id' => 'dbot', 'dir' => 1, 'brk' => $b, 'key' => $hi[0]]; }
  for ($a = 0; $a < count($H) - 2; $a++) { $s1 = $H[$a]; $hd = $H[$a + 1]; $s2 = $H[$a + 2]; if (!($hd[1] > $s1[1] * 1.01 && $hd[1] > $s2[1] * 1.01 && td_near($s1[1], $s2[1], 0.03))) continue;
    $l1 = array_values(array_filter($L, fn($l) => $l[0] > $s1[0] && $l[0] < $hd[0])); usort($l1, fn($x, $y) => $x[1] <=> $y[1]); $l2 = array_values(array_filter($L, fn($l) => $l[0] > $hd[0] && $l[0] < $s2[0])); usort($l2, fn($x, $y) => $x[1] <=> $y[1]);
    if (!$l1 || !$l2) continue; $nl = [$l1[0], $l2[0]]; $b = $brkBelow($s2[0] + $k, fn($i) => td_lineAt($nl, $i)); if ($b >= 0 && $b - $s2[0] > 40) continue; $out[] = ['id' => 'hs', 'dir' => -1, 'brk' => $b, 'key' => $hd[0]]; }
  for ($a = 0; $a < count($L) - 2; $a++) { $s1 = $L[$a]; $hd = $L[$a + 1]; $s2 = $L[$a + 2]; if (!($hd[1] < $s1[1] * 0.99 && $hd[1] < $s2[1] * 0.99 && td_near($s1[1], $s2[1], 0.03))) continue;
    $h1 = array_values(array_filter($H, fn($h) => $h[0] > $s1[0] && $h[0] < $hd[0])); usort($h1, fn($x, $y) => $y[1] <=> $x[1]); $h2 = array_values(array_filter($H, fn($h) => $h[0] > $hd[0] && $h[0] < $s2[0])); usort($h2, fn($x, $y) => $y[1] <=> $x[1]);
    if (!$h1 || !$h2) continue; $nl = [$h1[0], $h2[0]]; $b = $brkAbove($s2[0] + $k, fn($i) => td_lineAt($nl, $i)); if ($b >= 0 && $b - $s2[0] > 40) continue; $out[] = ['id' => 'ihs', 'dir' => 1, 'brk' => $b, 'key' => $hd[0]]; }
  $tri = function ($hs, $ls) use (&$out, $c, $n, $k) { if (count($hs) < 2 || count($ls) < 2) return; $st = min($hs[0][0], $ls[0][0]); $en = max($hs[1][0], $ls[1][0]); if ($en - $st < 10) return; $top = [$hs[0], $hs[1]]; $bot = [$ls[0], $ls[1]];
    $dh = ($hs[1][1] - $hs[0][1]) / $hs[0][1]; $dl = ($ls[1][1] - $ls[0][1]) / $ls[0][1];
    if (abs($dh) < 0.01 && $dl > 0.01) { $id = 'asc'; $dir = 1; } elseif (abs($dl) < 0.01 && $dh < -0.01) { $id = 'desc'; $dir = -1; } elseif ($dh < -0.01 && $dl > 0.01) { $id = 'sym'; $dir = 0; }
    elseif ($dh > 0.01 && $dl > 0.01 && $dl > $dh) { $id = 'wedgeR'; $dir = -1; } elseif ($dh < -0.01 && $dl < -0.01 && $dh < $dl) { $id = 'wedgeF'; $dir = 1; } else return;
    $b = -1; $bd = 0; for ($i = $en + $k; $i < $n; $i++) { if ($c[$i]['close'] > td_lineAt($top, $i) * 1.002) { $b = $i; $bd = 1; break; } if ($c[$i]['close'] < td_lineAt($bot, $i) * 0.998) { $b = $i; $bd = -1; break; } } if ($b >= 0 && $b - $en > 30) return;
    $out[] = ['id' => $id, 'dir' => $b >= 0 ? $bd : $dir, 'brk' => $b, 'key' => $top[0][0]]; };
  $tri(array_slice($H, -2), array_slice($L, -2)); if (count($H) >= 3 && count($L) >= 3) $tri(array_slice($H, -3, 2), array_slice($L, -3, 2));
  $seen = []; $r = []; foreach ($out as $f) { $kk = $f['id'] . ':' . $f['key']; if (isset($seen[$kk])) continue; $seen[$kk] = 1; $r[] = $f; } return $r; }
function td_s_pat($c, $p) { $ev = []; if ($p['cnd']) foreach (td_candles($c) as $x) if ($x['dir'] && $x['str'] >= $p['mins']) $ev[] = ['i' => $x['i'], 't' => $x['dir'] > 0 ? 'long' : 'short'];
  if ($p['figs']) foreach (td_figures($c, count($c) > 300 ? 5 : 4) as $f) if ($f['brk'] >= 0 && $f['dir']) $ev[] = ['i' => $f['brk'], 't' => $f['dir'] > 0 ? 'long' : 'short'];
  usort($ev, fn($a, $b) => $a['i'] <=> $b['i']); $o = []; $last = null; foreach ($ev as $x) if ($x['t'] !== $last) { $o[] = $x; $last = $x['t']; } return $o; }
// ---------- «Отскок от уровней» (app8.js) ----------
function td_s_lvl($c, $p) { $k = max(2, (int)$p['k']); $W = (int)$p['win'] ?: 300; $need = (int)$p['touch'] ?: 2; $n = count($c); $out = []; if ($n < $k * 2 + 30) return $out;
  $atr = td_atr($c, 14); $pv = []; $cset = null; if (!empty($p['pat'])) { $cset = []; foreach (td_candles($c) as $x) if ($x['dir']) $cset[$x['i']] = $x['dir']; }
  $last = null; $lastI = -1e9;
  for ($i = $k * 2 + 20; $i < $n; $i++) { $j = $i - $k; $h = true; $l = true; for ($q = $j - $k; $q <= $j + $k; $q++) { if ($q === $j) continue; if ($c[$q]['high'] > $c[$j]['high']) $h = false; if ($c[$q]['low'] < $c[$j]['low']) $l = false; }
    if ($h) $pv[] = ['i' => $j, 'p' => $c[$j]['high']]; if ($l) $pv[] = ['i' => $j, 'p' => $c[$j]['low']];
    while ($pv && $pv[0]['i'] < $i - $W) array_shift($pv);
    $a = $atr[$i]; if (!$a) continue; $tol = $a * 0.6; $zs = [];
    foreach ($pv as $x) { if ($x['i'] >= $i - 1) continue; $f = -1; foreach ($zs as $zi => $z) if (abs($z['p'] - $x['p']) <= $tol) { $f = $zi; break; }
      if ($f >= 0) { $zs[$f]['p'] = ($zs[$f]['p'] * $zs[$f]['n'] + $x['p']) / ($zs[$f]['n'] + 1); $zs[$f]['n']++; } else $zs[] = ['p' => $x['p'], 'n' => 1]; }
    $b = $c[$i]; $prev = $c[$i - 1]['close']; $sig = null;
    foreach ($zs as $z) { if ($z['n'] < $need) continue;
      if ($prev > $z['p'] && $b['low'] <= $z['p'] + $a * 0.25 && $b['low'] >= $z['p'] - $a * 0.8 && $b['close'] > $z['p'] + $a * 0.1 && $b['close'] >= $b['open']) { $sig = 'long'; break; }
      if ($prev < $z['p'] && $b['high'] >= $z['p'] - $a * 0.25 && $b['high'] <= $z['p'] + $a * 0.8 && $b['close'] < $z['p'] - $a * 0.1 && $b['close'] <= $b['open']) { $sig = 'short'; break; } }
    if ($sig && $cset !== null) { $d = $cset[$i] ?? 0; if (!($d && ($d > 0) === ($sig === 'long'))) $sig = null; }
    if ($sig && ($sig !== $last || $i - $lastI >= $k * 3)) { $out[] = ['i' => $i, 't' => $sig]; $last = $sig; $lastI = $i; } }
  return $out; }

// параметры по умолчанию — как в приложении
function td_defaults() { return ['ema' => ['f' => 9, 's' => 21], 'st' => ['n' => 10, 'm' => 3], 'rsi' => ['n' => 14, 'lo' => 30, 'hi' => 70, 't' => 200], 'don' => ['n' => 20, 'x' => 10],
  'macd' => ['f' => 12, 's' => 26, 'g' => 9], 'bb' => ['n' => 20, 'k' => 2], 'pat' => ['mins' => 3, 'figs' => 1, 'cnd' => 1], 'lvl' => ['k' => 4, 'win' => 300, 'touch' => 2, 'pat' => 0]]; }
// ---------- дополнительные стратегии (strat2.js, 10.11.2026) ----------
function td_hh($c, $i, $n) { $h = -1e18; for ($j = $i - $n + 1; $j <= $i; $j++) $h = max($h, $c[$j]['high']); return $h; }
function td_ll($c, $i, $n) { $l = 1e18; for ($j = $i - $n + 1; $j <= $i; $j++) $l = min($l, $c[$j]['low']); return $l; }
function td_x($a, $b, $i) { return isset($a[$i - 1], $b[$i - 1], $a[$i], $b[$i]) && $a[$i - 1] <= $b[$i - 1] && $a[$i] > $b[$i]; }
function td_uniq($o) { $r = []; $l = null; foreach ($o as $x) { if ($x['t'] !== $l) { $r[] = $x; $l = $x['t']; } } return $r; }
function td_pad($a) { $st = 0; while ($st < count($a) && $a[$st] === null) $st++; return $st; }
function td_s_stoch($c, $p) { $n = (int)$p['n'] ?: 14; $s = (int)$p['sm'] ?: 3; $raw = [];
  foreach ($c as $i => $x) { if ($i < $n - 1) { $raw[] = null; continue; } $h = td_hh($c, $i, $n); $l = td_ll($c, $i, $n); $raw[] = $h > $l ? ($x['close'] - $l) / ($h - $l) * 100 : 50; }
  $st = td_pad($raw); $k = array_merge(array_fill(0, $st, null), td_sma(array_slice($raw, $st), $s)); $st2 = td_pad($k); $d = array_merge(array_fill(0, $st2, null), td_sma(array_slice($k, $st2), $s)); $o = [];
  for ($i = 1; $i < count($c); $i++) { if (td_x($k, $d, $i) && $d[$i] < $p['lo']) $o[] = ['i' => $i, 't' => 'long']; elseif (td_x($d, $k, $i) && $d[$i] > $p['hi']) $o[] = ['i' => $i, 't' => 'short']; } return td_uniq($o); }
function td_s_ichi($c, $p) { $t = (int)$p['t'] ?: 9; $k = (int)$p['k'] ?: 26; $b = (int)$p['b'] ?: 52; $n = count($c); $mid = fn($i, $m) => $i < $m - 1 ? null : (td_hh($c, $i, $m) + td_ll($c, $i, $m)) / 2; $T = []; $K = []; $o = [];
  for ($i = 0; $i < $n; $i++) { $T[] = $mid($i, $t); $K[] = $mid($i, $k); }
  for ($i = $k + $b; $i < $n; $i++) { $j = $i - $k; $A = ($T[$j] + $K[$j]) / 2; $B = $mid($j, $b); if ($B === null) continue; $top = max($A, $B); $bot = min($A, $B); $x = $c[$i]['close'];
    if (td_x($T, $K, $i) && $x > $top) $o[] = ['i' => $i, 't' => 'long']; elseif (td_x($K, $T, $i) && $x < $bot) $o[] = ['i' => $i, 't' => 'short']; } return td_uniq($o); }
function td_s_psar($c, $p) { $s = ($p['af'] ?: 2) / 100; $mx = ($p['mx'] ?: 20) / 100; $o = []; if (count($c) < 3) return $o; $up = $c[1]['close'] >= $c[0]['close']; $sar = $up ? $c[0]['low'] : $c[0]['high']; $ep = $up ? $c[1]['high'] : $c[1]['low']; $af = $s;
  for ($i = 2; $i < count($c); $i++) { $sar = $sar + $af * ($ep - $sar);
    if ($up) { $sar = min($sar, $c[$i - 1]['low'], $c[$i - 2]['low']); if ($c[$i]['low'] < $sar) { $up = false; $sar = $ep; $ep = $c[$i]['low']; $af = $s; $o[] = ['i' => $i, 't' => 'short']; } elseif ($c[$i]['high'] > $ep) { $ep = $c[$i]['high']; $af = min($mx, $af + $s); } }
    else { $sar = max($sar, $c[$i - 1]['high'], $c[$i - 2]['high']); if ($c[$i]['high'] > $sar) { $up = true; $sar = $ep; $ep = $c[$i]['high']; $af = $s; $o[] = ['i' => $i, 't' => 'long']; } elseif ($c[$i]['low'] < $ep) { $ep = $c[$i]['low']; $af = min($mx, $af + $s); } } } return $o; }
function td_s_adx($c, $p) { $n = (int)$p['n'] ?: 14; $N = count($c); $pd = [0]; $md = [0]; $tr = [0];
  for ($i = 1; $i < $N; $i++) { $u = $c[$i]['high'] - $c[$i - 1]['high']; $d = $c[$i - 1]['low'] - $c[$i]['low']; $pd[] = $u > $d && $u > 0 ? $u : 0; $md[] = $d > $u && $d > 0 ? $d : 0; $tr[] = max($c[$i]['high'] - $c[$i]['low'], abs($c[$i]['high'] - $c[$i - 1]['close']), abs($c[$i]['low'] - $c[$i - 1]['close'])); }
  $w = function ($a) use ($N, $n) { $r = array_fill(0, $N, null); $s = 0; for ($i = 1; $i < $N; $i++) { if ($i <= $n) { $s += $a[$i]; if ($i === $n) $r[$i] = $s; } else { $s = $s - $s / $n + $a[$i]; $r[$i] = $s; } } return $r; };
  $T = $w($tr); $Pp = $w($pd); $Mm = $w($md); $PDI = []; $MDI = []; $DX = [];
  for ($i = 0; $i < $N; $i++) { $PDI[] = $T[$i] ? 100 * $Pp[$i] / $T[$i] : null; $MDI[] = $T[$i] ? 100 * $Mm[$i] / $T[$i] : null; }
  for ($i = 0; $i < $N; $i++) { $v = $PDI[$i]; $DX[] = $v === null ? null : (($v + $MDI[$i]) ? 100 * abs($v - $MDI[$i]) / ($v + $MDI[$i]) : 0); }
  $A = array_fill(0, $N, null); $s = 0; $k = 0; for ($i = 0; $i < $N; $i++) { if ($DX[$i] === null) continue; $k++; if ($k < $n) $s += $DX[$i]; elseif ($k === $n) { $s += $DX[$i]; $A[$i] = $s / $n; } else $A[$i] = ($A[$i - 1] * ($n - 1) + $DX[$i]) / $n; }
  $o = []; for ($i = 1; $i < $N; $i++) { if ($A[$i] === null || $A[$i] < $p['a']) continue; if (td_x($PDI, $MDI, $i)) $o[] = ['i' => $i, 't' => 'long']; elseif (td_x($MDI, $PDI, $i)) $o[] = ['i' => $i, 't' => 'short']; } return td_uniq($o); }
function td_s_sqz($c, $p) { $n = (int)$p['n'] ?: 20; $x = td_cl($c); $m = td_sma($x, $n); $a = td_atr($c, $n); $o = [];
  $sd = function ($i) use ($x, $m, $n) { $s = 0; for ($j = $i - $n + 1; $j <= $i; $j++) $s += ($x[$j] - $m[$i]) ** 2; return sqrt($s / $n); };
  $on = fn($i) => $m[$i] !== null && $a[$i] !== null && $p['bb'] * $sd($i) < $p['kc'] * $a[$i];
  for ($i = $n + 1; $i < count($c); $i++) { if ($on($i - 1) && !$on($i) && $m[$i] !== null) { if ($x[$i] > $m[$i] && $x[$i] > $x[$i - 1]) $o[] = ['i' => $i, 't' => 'long']; elseif ($x[$i] < $m[$i] && $x[$i] < $x[$i - 1]) $o[] = ['i' => $i, 't' => 'short']; } } return $o; }
function td_s_rsi2($c, $p) { $x = td_cl($c); $T = td_sma($x, (int)$p['t'] ?: 200); $R = td_rsi($x, (int)$p['r'] ?: 2); $E = td_sma($x, (int)$p['x'] ?: 5); $o = []; $pos = 0;
  for ($i = 1; $i < count($c); $i++) { if ($T[$i] === null || $R[$i] === null || $E[$i] === null) continue;
    if ($pos !== 1 && $x[$i] > $T[$i] && $R[$i] < $p['lo']) { $o[] = ['i' => $i, 't' => 'long']; $pos = 1; } elseif ($pos !== -1 && $x[$i] < $T[$i] && $R[$i] > $p['hi']) { $o[] = ['i' => $i, 't' => 'short']; $pos = -1; }
    elseif ($pos === 1 && $x[$i] > $E[$i]) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } elseif ($pos === -1 && $x[$i] < $E[$i]) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } } return $o; }
function td_s_pull($c, $p) { $x = td_cl($c); $F = td_ema($x, (int)$p['f'] ?: 20); $M = td_ema($x, (int)$p['m'] ?: 50); $S = td_ema($x, (int)$p['s'] ?: 200); $o = []; $last = null; $li = -1e9;
  for ($i = 1; $i < count($c); $i++) { if ($F[$i] === null || $M[$i] === null || $S[$i] === null) continue; $b = $c[$i]; $t = null;
    if ($M[$i] > $S[$i] && $x[$i] > $M[$i] && $b['low'] <= $F[$i] && $b['close'] > $F[$i] && $b['close'] > $b['open']) $t = 'long'; elseif ($M[$i] < $S[$i] && $x[$i] < $M[$i] && $b['high'] >= $F[$i] && $b['close'] < $F[$i] && $b['close'] < $b['open']) $t = 'short';
    if ($t && ($t !== $last || $i - $li >= 10)) { $o[] = ['i' => $i, 't' => $t]; $last = $t; $li = $i; } } return $o; }
function td_s_cci($c, $p) { $n = (int)$p['n'] ?: 20; $tp = array_map(fn($x) => ($x['high'] + $x['low'] + $x['close']) / 3, $c); $m = td_sma($tp, $n); $C = []; $z = $p['z'] ?: 100;
  foreach ($tp as $i => $v) { if ($m[$i] === null) { $C[] = null; continue; } $d = 0; for ($j = $i - $n + 1; $j <= $i; $j++) $d += abs($tp[$j] - $m[$i]); $d /= $n; $C[] = $d ? ($v - $m[$i]) / (0.015 * $d) : 0; }
  $o = []; for ($i = 1; $i < count($c); $i++) { if ($C[$i] === null || $C[$i - 1] === null) continue; if ($C[$i - 1] <= -$z && $C[$i] > -$z) $o[] = ['i' => $i, 't' => 'long']; elseif ($C[$i - 1] >= $z && $C[$i] < $z) $o[] = ['i' => $i, 't' => 'short']; } return td_uniq($o); }
function td_s_ha($c, $p) { $n = count($c); $O = []; $C = []; $H = []; $L = []; $o = [];
  for ($i = 0; $i < $n; $i++) { $x = $c[$i]; $hc = ($x['open'] + $x['high'] + $x['low'] + $x['close']) / 4; $ho = $i ? ($O[$i - 1] + $C[$i - 1]) / 2 : ($x['open'] + $x['close']) / 2; $O[] = $ho; $C[] = $hc; $H[] = max($x['high'], $ho, $hc); $L[] = min($x['low'], $ho, $hc); }
  $r = (int)$p['r'] ?: 2; $col = fn($i) => $C[$i] > $O[$i] ? 1 : ($C[$i] < $O[$i] ? -1 : 0); $eps = fn($i) => ($H[$i] - $L[$i]) * 0.02;
  for ($i = $r + 1; $i < $n; $i++) { $d = $col($i); if (!$d) continue; $ok = true; for ($j = 1; $j <= $r; $j++) if ($col($i - $j) !== -$d) $ok = false; if (!$ok) continue;
    if ($d > 0 && min($O[$i], $C[$i]) - $L[$i] <= $eps($i)) $o[] = ['i' => $i, 't' => 'long']; elseif ($d < 0 && $H[$i] - max($O[$i], $C[$i]) <= $eps($i)) $o[] = ['i' => $i, 't' => 'short']; } return $o; }
function td_s_inside($c, $p) { $o = []; $w = max(1, (int)$p['w'] ?: 1); $n = count($c);
  for ($i = 2; $i < $n; $i++) { $m = $c[$i - 2]; $b = $c[$i - 1]; if (!($b['high'] < $m['high'] && $b['low'] > $m['low'])) continue;
    for ($j = $i; $j < min($n, $i + $w); $j++) { if ($c[$j]['close'] > $m['high']) { $o[] = ['i' => $j, 't' => 'long']; break; } if ($c[$j]['close'] < $m['low']) { $o[] = ['i' => $j, 't' => 'short']; break; } } }
  usort($o, fn($a, $b) => $a['i'] <=> $b['i']); return td_uniq($o); }
function td_s_volbo($c, $p) { $n = (int)$p['n'] ?: 20; $v = array_map(fn($x) => $x['volume'] ?? 0, $c); $V = td_sma($v, $n); $o = [];
  for ($i = $n + 1; $i < count($c); $i++) { $h = td_hh($c, $i - 1, $n); $l = td_ll($c, $i - 1, $n); $big = $V[$i - 1] > 0 && $v[$i] >= $p['k'] * $V[$i - 1]; if (!$big) continue;
    if ($c[$i]['close'] > $h) $o[] = ['i' => $i, 't' => 'long']; elseif ($c[$i]['close'] < $l) $o[] = ['i' => $i, 't' => 'short']; } return td_uniq($o); }
function td_s_mtf($c, $p) { $n = count($c); $o = []; if ($n < 10) return $o; $dt = $c[1]['time'] - $c[0]['time']; $f = $dt >= 14400 ? 6 : max(1, (int)round(14400 / $dt)); $span = $dt * $f; $m = (int)$p['m'] ?: 50;
  $x = td_cl($c); $R = td_rsi($x, 2); $E = td_sma($x, (int)$p['x'] ?: 5); $H = []; $HS = []; $b0 = (int)floor($c[0]['time'] / $span); $hs = 0; $pos = 0;
  for ($i = 1; $i < $n; $i++) { $b = (int)floor($c[$i]['time'] / $span); if ($b !== $b0) { $H[] = $c[$i - 1]['close']; $hs += $c[$i - 1]['close']; if (count($H) > $m) $hs -= $H[count($H) - 1 - $m]; $HS[] = count($H) >= $m ? $hs / $m : null; $b0 = $b; }
    $k = count($HS) - 1; $tr = ($k >= 0 && $HS[$k] !== null) ? ($H[$k] > $HS[$k] ? 1 : -1) : 0; if ($R[$i] === null || $E[$i] === null) continue;
    if ($pos !== 1 && $tr === 1 && $R[$i] < $p['lo']) { $o[] = ['i' => $i, 't' => 'long']; $pos = 1; } elseif ($pos !== -1 && $tr === -1 && $R[$i] > $p['hi']) { $o[] = ['i' => $i, 't' => 'short']; $pos = -1; }
    elseif ($pos === 1 && $x[$i] > $E[$i]) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } elseif ($pos === -1 && $x[$i] < $E[$i]) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } } return $o; }
function td_s_orb($c, $p) { $n = count($c); $o = []; if ($n < 3) return $o; $dt = $c[1]['time'] - $c[0]['time']; $dm = $dt / 60; $rng = max($p['r'] ?: 60, $dm); $hold = ($p['h'] ?: 4) * 60; $S = array_map(fn($s) => floor($s / $dm) * $dm, [0, 420, 810]); $cur = null;
  for ($i = 0; $i < $n; $i++) { $t = $c[$i]['time']; $day = intdiv($t, 86400); $mn = ($t % 86400) / 60; $st = null; foreach ($S as $s0) if ($mn >= $s0 && $mn < $s0 + $dm) { $st = $s0; break; }
    if ($st !== null && (!$cur || $cur['day'] !== $day || $cur['s'] != $st)) { if ($cur && $cur['trade'] && !$cur['ex']) $o[] = ['i' => $i, 't' => 'exit']; $cur = ['day' => $day, 's' => $st, 'hi' => -1e18, 'lo' => 1e18, 'trade' => 0, 'ex' => 0]; }
    if (!$cur) continue; $el = ($day - $cur['day']) * 1440 + $mn - $cur['s'];
    if ($el < $rng) { $cur['hi'] = max($cur['hi'], $c[$i]['high']); $cur['lo'] = min($cur['lo'], $c[$i]['low']); continue; }
    if ($el < $rng + $hold) { if (!$cur['trade']) { if ($c[$i]['close'] > $cur['hi']) { $o[] = ['i' => $i, 't' => 'long']; $cur['trade'] = 1; } elseif ($c[$i]['close'] < $cur['lo']) { $o[] = ['i' => $i, 't' => 'short']; $cur['trade'] = 1; } } }
    elseif ($cur['trade'] && !$cur['ex']) { $o[] = ['i' => $i, 't' => 'exit']; $cur['ex'] = 1; } } return $o; }
function td_s_vwapm($c, $p) { $n = count($c); $o = []; $x = td_cl($c); $F = td_ema($x, (int)$p['f'] ?: 12); $S = td_ema($x, (int)$p['s'] ?: 26); $M = []; foreach ($x as $i => $_) $M[] = ($F[$i] === null || $S[$i] === null) ? null : $F[$i] - $S[$i];
  $st = td_pad($M); if ($st >= $n) return $o; $G = array_merge(array_fill(0, $st, null), td_ema(array_slice($M, $st), (int)$p['g'] ?: 9)); $w = (int)$p['w'] ?: 5;
  $V = []; $day = -1; $pv = 0; $vv = 0; for ($i = 0; $i < $n; $i++) { $d = intdiv($c[$i]['time'], 86400); if ($d !== $day) { $day = $d; $pv = 0; $vv = 0; } $tp = ($c[$i]['high'] + $c[$i]['low'] + $c[$i]['close']) / 3; $v = ($c[$i]['volume'] ?? 0) ?: 1; $pv += $tp * $v; $vv += $v; $V[] = $pv / $vv; }
  $lastX = -1e9; $lastD = 0; $pos = 0;
  for ($i = 1; $i < $n; $i++) { if ($G[$i] === null || $G[$i - 1] === null) continue;
    if ($M[$i - 1] <= $G[$i - 1] && $M[$i] > $G[$i]) { $lastX = $i; $lastD = 1; if ($pos === -1) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } } elseif ($M[$i - 1] >= $G[$i - 1] && $M[$i] < $G[$i]) { $lastX = $i; $lastD = -1; if ($pos === 1) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } }
    if ($pos !== 1 && $lastD === 1 && $i - $lastX < $w && $x[$i - 1] <= $V[$i - 1] && $x[$i] > $V[$i]) { $o[] = ['i' => $i, 't' => 'long']; $pos = 1; } elseif ($pos !== -1 && $lastD === -1 && $i - $lastX < $w && $x[$i - 1] >= $V[$i - 1] && $x[$i] < $V[$i]) { $o[] = ['i' => $i, 't' => 'short']; $pos = -1; } } return $o; }
function td_s_kelt($c, $p) { $n = (int)$p['n'] ?: 20; $x = td_cl($c); $E = td_ema($x, $n); $A = td_atr($c, 10); $R = td_rsi($x, 14); $o = []; $k = $p['k'] ?: 2; $r = (int)$p['r'] ?: 2; $pos = 0;
  for ($i = $r; $i < count($c); $i++) { if ($E[$i] === null || $A[$i] === null || $R[$i] === null) continue; $up = true; $dn = true;
    for ($j = $i - $r + 1; $j <= $i; $j++) { if ($E[$j] === null || $A[$j] === null) { $up = $dn = false; break; } if (!($x[$j] > $E[$j] + $k * $A[$j])) $up = false; if (!($x[$j] < $E[$j] - $k * $A[$j])) $dn = false; }
    if ($pos !== 1 && $up && $R[$i] > 50) { $o[] = ['i' => $i, 't' => 'long']; $pos = 1; } elseif ($pos !== -1 && $dn && $R[$i] < 50) { $o[] = ['i' => $i, 't' => 'short']; $pos = -1; }
    elseif ($pos === 1 && $x[$i] < $E[$i]) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } elseif ($pos === -1 && $x[$i] > $E[$i]) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } } return $o; }
function td_s_rsibb($c, $p) { $n = (int)$p['n'] ?: 20; $x = td_cl($c); $m = td_sma($x, $n); $R = td_rsi($x, 4); $o = []; $k = $p['k'] ?: 2; $pos = 0; $U = []; $D = [];
  for ($i = 0; $i < count($c); $i++) { if ($m[$i] === null) { $U[] = null; $D[] = null; continue; } $s = 0; for ($j = $i - $n + 1; $j <= $i; $j++) $s += ($x[$j] - $m[$i]) ** 2; $d = sqrt($s / $n); $U[] = $m[$i] + $k * $d; $D[] = $m[$i] - $k * $d; }
  for ($i = 2; $i < count($c); $i++) { if ($U[$i] === null || $U[$i - 1] === null || $R[$i] === null || $R[$i - 1] === null) continue; $tl = $c[$i]['low'] <= $D[$i] || $c[$i - 1]['low'] <= $D[$i - 1]; $th = $c[$i]['high'] >= $U[$i] || $c[$i - 1]['high'] >= $U[$i - 1];
    if ($pos !== 1 && $tl && $R[$i - 1] < $p['lo'] && $R[$i] >= $p['lo']) { $o[] = ['i' => $i, 't' => 'long']; $pos = 1; } elseif ($pos !== -1 && $th && $R[$i - 1] > $p['hi'] && $R[$i] <= $p['hi']) { $o[] = ['i' => $i, 't' => 'short']; $pos = -1; }
    elseif ($pos === 1 && ($c[$i]['high'] >= $U[$i] || $R[$i] >= $p['hi'])) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } elseif ($pos === -1 && ($c[$i]['low'] <= $D[$i] || $R[$i] <= $p['lo'])) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } } return $o; }
function td_s_sfp($c, $p) { $n = (int)$p['n'] ?: 20; $mm = ($p['m'] ?: 40) / 100; $o = []; $last = -1e9;
  for ($i = $n + 1; $i < count($c); $i++) { $b = $c[$i]; $rg = $b['high'] - $b['low']; if ($rg <= 0) continue; $h = -1e18; $l = 1e18; for ($j = $i - $n; $j < $i; $j++) { $h = max($h, $c[$j]['high']); $l = min($l, $c[$j]['low']); }
    if ($b['low'] < $l && $b['close'] > $l && $b['close'] > $b['open'] && (min($b['open'], $b['close']) - $b['low']) >= $mm * $rg && $i - $last > 2) { $o[] = ['i' => $i, 't' => 'long']; $last = $i; }
    elseif ($b['high'] > $h && $b['close'] < $h && $b['close'] < $b['open'] && ($b['high'] - max($b['open'], $b['close'])) >= $mm * $rg && $i - $last > 2) { $o[] = ['i' => $i, 't' => 'short']; $last = $i; } } return $o; }
function td_s_mpull($c, $p) { $x = td_cl($c); $A = td_ema($x, (int)$p['a'] ?: 9); $B = td_ema($x, (int)$p['b'] ?: 21); $C = td_ema($x, (int)$p['c'] ?: 50); $o = []; $pos = 0;
  for ($i = 3; $i < count($c); $i++) { if ($A[$i] === null || $B[$i] === null || $C[$i] === null || $B[$i - 2] === null) continue; $up = $A[$i] > $B[$i] && $B[$i] > $C[$i]; $dn = $A[$i] < $B[$i] && $B[$i] < $C[$i]; $tl = false; $th = false;
    for ($j = $i - 2; $j <= $i; $j++) { if ($c[$j]['low'] <= $B[$j]) $tl = true; if ($c[$j]['high'] >= $B[$j]) $th = true; }
    if ($pos !== 1 && $up && $tl && $x[$i] > $c[$i - 1]['high'] && $x[$i] > $B[$i]) { $o[] = ['i' => $i, 't' => 'long']; $pos = 1; } elseif ($pos !== -1 && $dn && $th && $x[$i] < $c[$i - 1]['low'] && $x[$i] < $B[$i]) { $o[] = ['i' => $i, 't' => 'short']; $pos = -1; }
    elseif ($pos === 1 && $x[$i] < $C[$i]) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } elseif ($pos === -1 && $x[$i] > $C[$i]) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } } return $o; }
function td_s_btcl($c, $p) { $o = []; $B = $GLOBALS['TD_BTC'] ?? null; if (!$B || count($c) < 3 || count($B) < 3 || ($B[1]['time'] - $B[0]['time']) !== ($c[1]['time'] - $c[0]['time'])) return $o;
  $idx = []; foreach ($B as $j => $b) $idx[$b['time']] = $j; $n = (int)$p['n'] ?: 20; $h = (int)$p['h'] ?: 8; $lag = !empty($p['lag']); $pos = 0; $i0 = 0;
  for ($i = 1; $i < count($c); $i++) { if ($pos && $i - $i0 >= $h) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } $j = $idx[$c[$i]['time']] ?? null; if ($j === null || $j < $n + 1) continue;
    $hh = -1e18; $ll = 1e18; for ($q = $j - $n; $q < $j; $q++) { $hh = max($hh, $B[$q]['high']); $ll = min($ll, $B[$q]['low']); }
    $br = $B[$j]['close'] / $B[$j - 1]['close'] - 1; $cr = $c[$i]['close'] / $c[$i - 1]['close'] - 1; $d = 0;
    if ($B[$j]['close'] > $hh && (!$lag || $cr < $br)) $d = 1; elseif ($B[$j]['close'] < $ll && (!$lag || $cr > $br)) $d = -1;
    if ($d && $d !== $pos) { $o[] = ['i' => $i, 't' => $d > 0 ? 'long' : 'short']; $pos = $d; $i0 = $i; } } return $o; }
function td_s_btct($c, $p) { $o = []; $B = $GLOBALS['TD_BTC'] ?? null; if (!$B || count($c) < 3 || count($B) < 3 || ($B[1]['time'] - $B[0]['time']) !== ($c[1]['time'] - $c[0]['time'])) return $o;
  $f = (int)$p['f'] ?: 9; $s = (int)$p['s'] ?: 21; $rs = (int)$p['rs'] ?: 20; $rf = !empty($p['r']); $kf = 2 / ($f + 1); $ks = 2 / ($s + 1); $EF = []; $ES = []; $ef = null; $es = null; $idx = [];
  foreach ($B as $j => $b) { $v = $b['close']; $ef = $ef === null ? $v : $v * $kf + $ef * (1 - $kf); $es = $es === null ? $v : $v * $ks + $es * (1 - $ks); $EF[] = $ef; $ES[] = $es; $idx[$b['time']] = $j; }
  $pos = 0; for ($i = $rs; $i < count($c); $i++) { $j = $idx[$c[$i]['time']] ?? null; if ($j === null || $j < max($s, $rs) + 1) continue; $bc = $B[$j]['close'];
    $up = $EF[$j] > $ES[$j] && $bc > $EF[$j]; $dn = $EF[$j] < $ES[$j] && $bc < $EF[$j];
    if (($pos > 0 && !$up) || ($pos < 0 && !$dn)) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; continue; }
    if ($pos === 0) { $rl = $c[$i]['close'] / $c[$i - $rs]['close'] - $bc / $B[$j - $rs]['close']; if ($up && (!$rf || $rl > 0)) { $o[] = ['i' => $i, 't' => 'long']; $pos = 1; } elseif ($dn && (!$rf || $rl < 0)) { $o[] = ['i' => $i, 't' => 'short']; $pos = -1; } } }
  return $o; }
function td_atrW($c, $n) { $o = []; $a = null; $N = count($c); for ($i = 0; $i < $N; $i++) { $tr = $i ? max($c[$i]['high'] - $c[$i]['low'], abs($c[$i]['high'] - $c[$i - 1]['close']), abs($c[$i]['low'] - $c[$i - 1]['close'])) : $c[$i]['high'] - $c[$i]['low'];
    $a = $i < $n ? ($a === null ? $tr : $a + $tr) : $a; if ($i === $n - 1) $a = $a / $n; elseif ($i >= $n) $a = ($a * ($n - 1) + $tr) / $n; $o[] = $i >= $n - 1 ? $a : null; } return $o; }
function td_adxW($c, $n) { $N = count($c); $o = array_fill(0, $N, null); $tr = 0; $pd = 0; $md = 0; $adx = null; $dxs = 0; $cnt = 0;
  for ($i = 1; $i < $N; $i++) { $up = $c[$i]['high'] - $c[$i - 1]['high']; $dn = $c[$i - 1]['low'] - $c[$i]['low']; $t = max($c[$i]['high'] - $c[$i]['low'], abs($c[$i]['high'] - $c[$i - 1]['close']), abs($c[$i]['low'] - $c[$i - 1]['close'])); $P = ($up > $dn && $up > 0) ? $up : 0; $M = ($dn > $up && $dn > 0) ? $dn : 0;
    if ($i <= $n) { $tr += $t; $pd += $P; $md += $M; if ($i < $n) continue; } else { $tr = $tr - $tr / $n + $t; $pd = $pd - $pd / $n + $P; $md = $md - $md / $n + $M; }
    $pi = $tr ? 100 * $pd / $tr : 0; $mi = $tr ? 100 * $md / $tr : 0; $dx = ($pi + $mi) ? 100 * abs($pi - $mi) / ($pi + $mi) : 0;
    if ($adx === null) { $dxs += $dx; $cnt++; if ($cnt === $n) { $adx = $dxs / $n; $o[$i] = $adx; } } else { $adx = ($adx * ($n - 1) + $dx) / $n; $o[$i] = $adx; } } return $o; }
function td_emaW($c, $n) { $k = 2 / ($n + 1); $o = []; $e = null; foreach ($c as $x) { $e = $e === null ? $x['close'] : $x['close'] * $k + $e * (1 - $k); $o[] = $e; } return $o; }
function td_lock_gen($c, $p, $mode) { $o = []; $k = (float)($p['k'] ?? 1.5) ?: 1.5; $h = (int)($p['h'] ?? 24) ?: 24; $ad = (float)($p['a'] ?? 0); $A = td_atrW($c, 14); $X = td_adxW($c, 14); $E = td_emaW($c, 20); $B = null; $BA = null; $BE = null; $idx = [];
  if ($mode === 'btc') { $B = $GLOBALS['TD_BTC'] ?? null; if (!$B || count($c) < 3 || count($B) < 3 || ($B[1]['time'] - $B[0]['time']) !== ($c[1]['time'] - $c[0]['time'])) return $o; $BA = td_atrW($B, 14); $BE = td_emaW($B, 9); foreach ($B as $j => $b) $idx[$b['time']] = $j; }
  $L = -1; $P0 = 0; $d = 0; $bP = 0; $bd = 0; $pos = 0; $i0 = 0; $sl = 0; $tp = 0; $best = 0; $N = count($c);
  for ($i = 30; $i < $N; $i++) { $b = $c[$i];
    if ($pos) { if ($i > $i0) { $hitS = $pos > 0 ? $b['low'] <= $sl : $b['high'] >= $sl;
        if ($hitS) $pos = 0; elseif ($mode === 'R' && ($pos > 0 ? $b['high'] >= $tp : $b['low'] <= $tp)) $pos = 0;
        elseif ($mode === 'sig' && ($pos > 0 ? $b['close'] < $E[$i] : $b['close'] > $E[$i])) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; }
        elseif ($mode === 'btc') { $j = $idx[$b['time']] ?? null; if ($j !== null && ($pos > 0 ? $B[$j]['close'] < $BE[$j] : $B[$j]['close'] > $BE[$j])) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } }
        if ($pos && $mode === 'tr') { if ($pos > 0 ? $b['high'] > $best : $b['low'] < $best) $best = $pos > 0 ? $b['high'] : $b['low']; $v = $best - $pos * 3 * $A[$i]; $sl = $pos > 0 ? max($sl, $v) : min($sl, $v); } }
      continue; }
    if ($L < 0) { if ($A[$i] && (!$ad || ($X[$i] !== null && $X[$i] >= $ad))) { if ($mode === 'btc') { $j = $idx[$b['time']] ?? null; if ($j === null || !$BA[$j]) continue; $bP = $B[$j]['close']; $bd = $k * $BA[$j]; } $L = $i; $P0 = $b['close']; $d = $k * $A[$i]; } continue; }
    if ($i - $L > $h) { $L = -1; continue; }
    $side = 0; if ($mode === 'btc') { $j = $idx[$b['time']] ?? null; if ($j === null) continue; $bc = $B[$j]['close']; $side = $bc >= $bP + $bd ? 1 : ($bc <= $bP - $bd ? -1 : 0); } else $side = $b['close'] >= $P0 + $d ? 1 : ($b['close'] <= $P0 - $d ? -1 : 0);
    if ($side) { $o[] = ['i' => $i, 't' => $side > 0 ? 'long' : 'short', 'sl' => $P0]; $pos = $side; $i0 = $i; $sl = $P0; $r = abs($b['close'] - $P0); $tp = $b['close'] + $side * 2 * $r; $best = $b['close']; $L = -1; } }
  return $o; }
function td_s_lockr($c, $p) { return td_lock_gen($c, $p, 'R'); }
function td_s_lockt($c, $p) { return td_lock_gen($c, $p, 'tr'); }
function td_s_locks($c, $p) { return td_lock_gen($c, $p, 'sig'); }
function td_s_lockb($c, $p) { return td_lock_gen($c, $p, 'btc'); }
/* ---------- стратегии по идеям TradingView (11.11.2026) — повтор strat3.js ---------- */
function tv_E($a, $n) { $k = 2 / ($n + 1); $o = []; $e = null; foreach ($a as $v) { if ($v === null) { $o[] = $e; continue; } $e = $e === null ? $v : $v * $k + $e * (1 - $k); $o[] = $e; } return $o; }
function tv_S($a, $n) { $o = []; $s = 0; $N = count($a); for ($i = 0; $i < $N; $i++) { $s += $a[$i] ?: 0; if ($i >= $n) $s -= $a[$i - $n] ?: 0; $o[] = $i >= $n - 1 ? $s / $n : null; } return $o; }
function tv_W($a, $n) { $o = []; $d = $n * ($n + 1) / 2; $N = count($a); for ($i = 0; $i < $N; $i++) { if ($i < $n - 1 || $a[$i - $n + 1] === null) { $o[] = null; continue; } $s = 0; for ($j = 0; $j < $n; $j++) $s += $a[$i - $j] * ($n - $j); $o[] = $s / $d; } return $o; }
function tv_RSI($x, $n) { $N = count($x); $o = array_fill(0, $N, null); $g = 0; $l = 0; for ($i = 1; $i < $N; $i++) { $d = $x[$i] - $x[$i - 1]; $up = $d > 0 ? $d : 0; $dn = $d < 0 ? -$d : 0;
  if ($i <= $n) { $g += $up; $l += $dn; if ($i === $n) { $g /= $n; $l /= $n; $o[$i] = $l == 0 ? 100 : 100 - 100 / (1 + $g / $l); } } else { $g = ($g * ($n - 1) + $up) / $n; $l = ($l * ($n - 1) + $dn) / $n; $o[$i] = $l == 0 ? 100 : 100 - 100 / (1 + $g / $l); } } return $o; }
function tv_HH($c, $a, $b, $k) { $h = -1e18; for ($j = $a; $j <= $b; $j++) $h = max($h, $c[$j][$k]); return $h; }
function tv_LL($c, $a, $b, $k) { $h = 1e18; for ($j = $a; $j <= $b; $j++) $h = min($h, $c[$j][$k]); return $h; }
function tv_flips($D, $from) { $o = []; $p = 0; $N = count($D); for ($i = $from; $i < $N; $i++) { $d = $D[$i] ?: 0; if ($d !== $p) { $o[] = ['i' => $i, 't' => $d === 0 ? 'exit' : ($d > 0 ? 'long' : 'short')]; $p = $d; } } return $o; }
function tv_cl($c) { return array_map(fn($x) => $x['close'], $c); }
function td_s_tvut($c, $p) { $a = (float)$p['a'] ?: 1; $n = (int)$p['n'] ?: 10; $A = td_atrW($c, $n); $x = tv_cl($c); $D = []; $ts = null; $N = count($c);
  for ($i = 0; $i < $N; $i++) { if ($A[$i] === null) { $D[] = 0; continue; } $L = $a * $A[$i]; if ($ts === null) { $ts = $x[$i] - $L; $D[] = 0; continue; } $pv = $x[$i - 1]; $pt = $ts;
    if ($x[$i] > $pt && $pv > $pt) $ts = max($pt, $x[$i] - $L); elseif ($x[$i] < $pt && $pv < $pt) $ts = min($pt, $x[$i] + $L); else $ts = $x[$i] > $pt ? $x[$i] - $L : $x[$i] + $L;
    $D[] = ($x[$i] > $pt && $pv <= $pt) ? 1 : (($x[$i] < $pt && $pv >= $pt) ? -1 : ($D[$i - 1] ?: 0)); } return tv_flips($D, $n + 1); }
function td_s_tvrf($c, $p) { $n = (int)$p['n'] ?: 100; $m = (float)$p['m'] ?: 3; $x = tv_cl($c); $d = []; foreach ($x as $i => $v) $d[] = $i ? abs($v - $x[$i - 1]) : 0; $r = array_map(fn($v) => $v * $m, tv_E(tv_E($d, $n), $n * 2 - 1)); $D = []; $f = $x[0]; $up = 0; $dn = 0; $st = 0;
  foreach ($x as $i => $v) { $pf = $f; $f = $v > $pf ? ($v - $r[$i] < $pf ? $pf : $v - $r[$i]) : ($v + $r[$i] > $pf ? $pf : $v + $r[$i]); $up = $f > $pf ? $up + 1 : ($f < $pf ? 0 : $up); $dn = $f < $pf ? $dn + 1 : ($f > $pf ? 0 : $dn);
    if ($v > $f && $up > 0) $st = 1; elseif ($v < $f && $dn > 0) $st = -1; $D[] = $i < $n ? 0 : $st; } return tv_flips($D, $n); }
function td_s_tvce($c, $p) { $n = (int)$p['n'] ?: 22; $m = (float)$p['m'] ?: 3; $A = td_atrW($c, $n); $D = []; $ls = null; $ss = null; $dir = 0; $N = count($c);
  for ($i = 0; $i < $N; $i++) { if ($i < $n || $A[$i] === null) { $D[] = 0; continue; } $l = tv_HH($c, $i - $n + 1, $i, 'high') - $m * $A[$i]; $s = tv_LL($c, $i - $n + 1, $i, 'low') + $m * $A[$i];
    if ($ls !== null && $c[$i - 1]['close'] > $ls) $l = max($l, $ls); if ($ss !== null && $c[$i - 1]['close'] < $ss) $s = min($s, $ss);
    if ($ss !== null && $c[$i]['close'] > $ss) $dir = 1; elseif ($ls !== null && $c[$i]['close'] < $ls) $dir = -1; $ls = $l; $ss = $s; $D[] = $dir; } return tv_flips($D, $n + 1); }
function td_s_tvssl($c, $p) { $n = (int)$p['n'] ?: 10; $H = tv_S(array_map(fn($x) => $x['high'], $c), $n); $L = tv_S(array_map(fn($x) => $x['low'], $c), $n); $D = []; $h = 0;
  foreach ($c as $i => $b) { if ($H[$i] === null) { $D[] = 0; continue; } if ($b['close'] > $H[$i]) $h = 1; elseif ($b['close'] < $L[$i]) $h = -1; $D[] = $h; } return tv_flips($D, $n); }
function td_s_tvwt($c, $p) { $n1 = (int)$p['n1'] ?: 10; $n2 = (int)$p['n2'] ?: 21; $z = (float)$p['z'] ?: 53; $ap = array_map(fn($x) => ($x['high'] + $x['low'] + $x['close']) / 3, $c); $es = tv_E($ap, $n1); $dd = []; foreach ($ap as $i => $v) $dd[] = abs($v - $es[$i]); $d = tv_E($dd, $n1);
  $ci = []; foreach ($ap as $i => $v) $ci[] = $d[$i] ? ($v - $es[$i]) / (0.015 * $d[$i]) : 0; $w1 = tv_E($ci, $n2); $w2 = tv_S($w1, 4); $o = []; $pos = 0; $N = count($c);
  for ($i = $n1 + $n2 + 4; $i < $N; $i++) { if ($w2[$i] === null || $w2[$i - 1] === null) continue; $up = $w1[$i] > $w2[$i] && $w1[$i - 1] <= $w2[$i - 1]; $dn = $w1[$i] < $w2[$i] && $w1[$i - 1] >= $w2[$i - 1];
    if ($up && $w1[$i] < -$z) { $o[] = ['i' => $i, 't' => 'long']; $pos = 1; } elseif ($dn && $w1[$i] > $z) { $o[] = ['i' => $i, 't' => 'short']; $pos = -1; } elseif (($up && $pos < 0) || ($dn && $pos > 0)) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } } return $o; }
function td_s_tvhull($c, $p) { $n = (int)$p['n'] ?: 55; $x = tv_cl($c); $a = tv_W($x, max(2, intdiv($n, 2))); $b = tv_W($x, $n); $r = []; foreach ($a as $i => $v) $r[] = ($v !== null && $b[$i] !== null) ? 2 * $v - $b[$i] : null; $H = tv_W($r, max(2, (int)floor(sqrt($n)))); $D = [];
  foreach ($H as $i => $v) $D[] = ($i < 2 || $v === null || $H[$i - 2] === null) ? 0 : ($v > $H[$i - 2] ? 1 : ($v < $H[$i - 2] ? -1 : 0));
  for ($i = 1; $i < count($D); $i++) if ($D[$i] === 0 && $H[$i] !== null) $D[$i] = $D[$i - 1]; return tv_flips($D, $n); }
function td_s_tvqqe($c, $p) { $n = (int)$p['n'] ?: 14; $sf = (int)$p['sf'] ?: 5; $q = (float)$p['q'] ?: 4.238; $R = tv_RSI(tv_cl($c), $n); $M = tv_E($R, $sf); $tr = []; foreach ($M as $i => $v) $tr[] = ($i && $v !== null && $M[$i - 1] !== null) ? abs($v - $M[$i - 1]) : null;
  $dar = array_map(fn($v) => $v === null ? null : $v * $q, tv_E(tv_E($tr, 2 * $n - 1), 2 * $n - 1)); $D = []; $lb = null; $sb = null; $t = 0; $N = count($c);
  for ($i = 0; $i < $N; $i++) { if ($M[$i] === null || $dar[$i] === null || $i < $n * 3) { $D[] = 0; continue; } $nl = $M[$i] - $dar[$i]; $ns = $M[$i] + $dar[$i]; $pl = $lb; $ps = $sb;
    $lb = ($pl !== null && $M[$i - 1] > $pl && $M[$i] > $pl) ? max($pl, $nl) : $nl; $sb = ($ps !== null && $M[$i - 1] < $ps && $M[$i] < $ps) ? min($ps, $ns) : $ns; if ($ps !== null && $M[$i] > $ps) $t = 1; elseif ($pl !== null && $M[$i] < $pl) $t = -1; $D[] = $t; } return tv_flips($D, $n * 3); }
function td_s_tvst200($c, $p) { $n = (int)$p['n'] ?: 10; $m = (float)$p['m'] ?: 3; $tl = (int)$p['t'] ?: 200; $A = td_atrW($c, $n); $T = tv_E(tv_cl($c), $tl); $o = []; $up = null; $dn = null; $d = 1; $pos = 0; $N = count($c);
  for ($i = 0; $i < $N; $i++) { if ($A[$i] === null) continue; $h = ($c[$i]['high'] + $c[$i]['low']) / 2; $u = $h - $m * $A[$i]; $l = $h + $m * $A[$i]; $pu = $up; $pd = $dn; $up = ($pu !== null && $c[$i - 1]['close'] > $pu) ? max($u, $pu) : $u; $dn = ($pd !== null && $c[$i - 1]['close'] < $pd) ? min($l, $pd) : $l;
    $pd0 = $d; if ($pd !== null && $c[$i]['close'] > $pd) $d = 1; elseif ($pu !== null && $c[$i]['close'] < $pu) $d = -1; if ($i < $tl) continue;
    if ($d !== $pd0) { if ($pos && $pos !== $d) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } if ($d > 0 && $c[$i]['close'] > $T[$i]) { $o[] = ['i' => $i, 't' => 'long']; $pos = 1; } elseif ($d < 0 && $c[$i]['close'] < $T[$i]) { $o[] = ['i' => $i, 't' => 'short']; $pos = -1; } } } return $o; }
function td_s_tvsweep($c, $p) { $n = (int)$p['n'] ?: 20; $o = []; $N = count($c); for ($i = $n; $i < $N; $i++) { $h = tv_HH($c, $i - $n, $i - 1, 'high'); $l = tv_LL($c, $i - $n, $i - 1, 'low');
    if ($c[$i]['high'] > $h && $c[$i]['close'] < $h) $o[] = ['i' => $i, 't' => 'short']; elseif ($c[$i]['low'] < $l && $c[$i]['close'] > $l) $o[] = ['i' => $i, 't' => 'long']; } return $o; }
function td_s_tvfvg($c, $p) { $n = (int)$p['n'] ?: 20; $w = (int)$p['w'] ?: 10; $o = []; $sh = -1e9; $sl = -1e9; $N = count($c);
  for ($i = $n; $i < $N; $i++) { $h = tv_HH($c, $i - $n, $i - 1, 'high'); $l = tv_LL($c, $i - $n, $i - 1, 'low'); if ($c[$i]['high'] > $h) $sh = $i; if ($c[$i]['low'] < $l) $sl = $i;
    if ($i - $sl <= $w && $i - $sl >= 1 && $c[$i]['low'] > $c[$i - 2]['high']) { $o[] = ['i' => $i, 't' => 'long']; $sl = -1e9; } elseif ($i - $sh <= $w && $i - $sh >= 1 && $c[$i]['high'] < $c[$i - 2]['low']) { $o[] = ['i' => $i, 't' => 'short']; $sh = -1e9; } } return $o; }
function td_s_tvdon($c, $p) { $n = (int)$p['n'] ?: 20; $d = (int)$p['d'] ?: 5; $m = (int)$p['m'] ?: 14; $tp = array_map(fn($x) => ($x['high'] + $x['low'] + $x['close']) / 3, $c); $o = []; $pos = 0; $N = count($c);
  for ($i = $n + $d + $m + 1; $i < $N; $i++) { $u = tv_HH($c, $i - $n - $d, $i - $d - 1, 'high'); $l = tv_LL($c, $i - $n - $d, $i - $d - 1, 'low'); $mid = ($u + $l) / 2; $x = $c[$i]['close'];
    if ($pos > 0 && $x < $mid) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } elseif ($pos < 0 && $x > $mid) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; }
    if (!$pos) { $pf = 0; $nf = 0; for ($j = $i - $m + 1; $j <= $i; $j++) { $f = $tp[$j] * ($c[$j]['volume'] ?? 0); if ($tp[$j] > $tp[$j - 1]) $pf += $f; elseif ($tp[$j] < $tp[$j - 1]) $nf += $f; } $mf = $nf == 0 ? 100 : 100 - 100 / (1 + $pf / $nf);
      if ($x > $u && $mf > 50) { $o[] = ['i' => $i, 't' => 'long']; $pos = 1; } elseif ($x < $l && $mf < 50) { $o[] = ['i' => $i, 't' => 'short']; $pos = -1; } } } return $o; }
function td_s_tvrsi70($c, $p) { $n = (int)$p['n'] ?: 14; $hi = (float)$p['hi'] ?: 70; $lo = (float)$p['lo'] ?: 30; $R = tv_RSI(tv_cl($c), $n); $o = []; $pos = 0; $N = count($c);
  for ($i = $n + 1; $i < $N; $i++) { if ($R[$i] === null || $R[$i - 1] === null) continue; if ($pos > 0 && $R[$i] < 50) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } elseif ($pos < 0 && $R[$i] > 50) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; }
    if (!$pos) { if ($R[$i] > $hi && $R[$i - 1] <= $hi) { $o[] = ['i' => $i, 't' => 'long']; $pos = 1; } elseif ($R[$i] < $lo && $R[$i - 1] >= $lo) { $o[] = ['i' => $i, 't' => 'short']; $pos = -1; } } } return $o; }
function td_s_tvvor($c, $p) { $n = (int)$p['n'] ?: 20; $v = (float)$p['v'] ?: 20; $vo = array_map(fn($x) => $x['volume'] ?? 0, $c); $f = tv_E($vo, 5); $s = tv_E($vo, 10); $O = []; foreach ($f as $i => $x) $O[] = $s[$i] ? ($x - $s[$i]) / $s[$i] * 100 : 0; $o = []; $N = count($c);
  for ($i = $n + 1; $i < $N; $i++) { $j = $i - 1; $h = tv_HH($c, $j - $n, $j - 1, 'high'); $l = tv_LL($c, $j - $n, $j - 1, 'low'); if ($O[$j] < $v) continue;
    if ($c[$j]['low'] < $l && $c[$i]['close'] > $c[$j]['close']) $o[] = ['i' => $i, 't' => 'long']; elseif ($c[$j]['high'] > $h && $c[$i]['close'] < $c[$j]['close']) $o[] = ['i' => $i, 't' => 'short']; } return $o; }
function td_s_tvabs($c, $p) { $n = (int)$p['n'] ?: 20; $k = (float)$p['k'] ?: 2; $A = td_atrW($c, 14); $dl = []; foreach ($c as $x) { $r = $x['high'] - $x['low']; $dl[] = $r > 0 ? ($x['volume'] ?? 0) * ($x['close'] - $x['open']) / $r : 0; } $o = []; $N = count($c);
  for ($i = $n; $i < $N; $i++) { if ($A[$i] === null) continue; $s = 0; for ($j = $i - $n; $j < $i; $j++) $s += abs($dl[$j]); $av = $s / $n; $rg = $c[$i]['high'] - $c[$i]['low']; if (!$av || $rg >= 0.7 * $A[$i]) continue;
    $h = tv_HH($c, $i - $n, $i - 1, 'high'); $l = tv_LL($c, $i - $n, $i - 1, 'low'); if ($dl[$i] > $k * $av && $c[$i]['high'] >= $h) $o[] = ['i' => $i, 't' => 'short']; elseif ($dl[$i] < -$k * $av && $c[$i]['low'] <= $l) $o[] = ['i' => $i, 't' => 'long']; } return $o; }
function td_s_div($c, $p) { $x = td_cl($c); $R = td_rsi($x, (int)$p['n'] ?: 14); $k = (int)$p['k'] ?: 3; $w = (int)$p['w'] ?: 60; $o = []; $pL = null; $pH = null; $pos = 0;
  for ($i = 2 * $k + 1; $i < count($c); $i++) { $j = $i - $k; if ($R[$j] === null) continue; $lo = true; $hi = true;
    for ($q = $j - $k; $q <= $j + $k; $q++) { if ($q === $j) continue; if ($c[$q]['low'] < $c[$j]['low']) $lo = false; if ($c[$q]['high'] > $c[$j]['high']) $hi = false; }
    if ($lo) { if ($pL && $c[$j]['low'] < $pL['v'] && $R[$j] > $pL['r'] && $R[$j] < 40 && $j - $pL['j'] <= $w && $pos !== 1) { $o[] = ['i' => $i, 't' => 'long']; $pos = 1; } $pL = ['j' => $j, 'v' => $c[$j]['low'], 'r' => $R[$j]]; }
    if ($hi) { if ($pH && $c[$j]['high'] > $pH['v'] && $R[$j] < $pH['r'] && $R[$j] > 60 && $j - $pH['j'] <= $w && $pos !== -1) { $o[] = ['i' => $i, 't' => 'short']; $pos = -1; } $pH = ['j' => $j, 'v' => $c[$j]['high'], 'r' => $R[$j]]; }
    if ($R[$i] !== null) { if ($pos === 1 && $R[$i] >= 65) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } elseif ($pos === -1 && $R[$i] <= 35) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } } } return $o; }
function td_s_zrev($c, $p) { $n = (int)$p['n'] ?: 50; $zz = $p['z'] ?: 2.5; $x = td_cl($c); $m = td_sma($x, $n); $o = []; $pos = 0; $Z = [];
  foreach ($x as $i => $v) { if ($m[$i] === null) { $Z[] = null; continue; } $s = 0; for ($j = $i - $n + 1; $j <= $i; $j++) $s += ($x[$j] - $m[$i]) ** 2; $d = sqrt($s / $n); $Z[] = $d ? ($v - $m[$i]) / $d : 0; }
  for ($i = 1; $i < count($c); $i++) { if ($Z[$i] === null || $Z[$i - 1] === null) continue;
    if ($pos !== 1 && $Z[$i - 1] < -$zz && $Z[$i] >= -$zz) { $o[] = ['i' => $i, 't' => 'long']; $pos = 1; } elseif ($pos !== -1 && $Z[$i - 1] > $zz && $Z[$i] <= $zz) { $o[] = ['i' => $i, 't' => 'short']; $pos = -1; }
    elseif ($pos === 1 && $Z[$i] >= 0) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } elseif ($pos === -1 && $Z[$i] <= 0) { $o[] = ['i' => $i, 't' => 'exit']; $pos = 0; } } return $o; }
function td_defaults2() { return ['stoch' => ['n' => 14, 'sm' => 3, 'lo' => 20, 'hi' => 80], 'ichi' => ['t' => 9, 'k' => 26, 'b' => 52], 'psar' => ['af' => 2, 'mx' => 20], 'adx' => ['n' => 14, 'a' => 25],
  'sqz' => ['n' => 20, 'bb' => 2, 'kc' => 1.5], 'rsi2' => ['t' => 200, 'r' => 2, 'lo' => 10, 'hi' => 90, 'x' => 5], 'pull' => ['f' => 20, 'm' => 50, 's' => 200], 'cci' => ['n' => 20, 'z' => 100], 'ha' => ['r' => 2], 'inside' => ['w' => 1], 'volbo' => ['n' => 20, 'k' => 2], 'mtf' => ['m' => 50, 'lo' => 10, 'hi' => 90, 'x' => 5], 'orb' => ['r' => 60, 'h' => 4], 'vwapm' => ['f' => 12, 's' => 26, 'g' => 9, 'w' => 5], 'kelt' => ['n' => 20, 'k' => 2, 'r' => 2], 'rsibb' => ['n' => 20, 'k' => 2, 'lo' => 20, 'hi' => 80], 'sfp' => ['n' => 20, 'm' => 40], 'mpull' => ['a' => 9, 'b' => 21, 'c' => 50], 'btcl' => ['n' => 20, 'h' => 8, 'lag' => 1], 'btct' => ['f' => 9, 's' => 21, 'rs' => 20, 'r' => 1], 'lockr' => ['k' => 1.5, 'h' => 24, 'a' => 20], 'lockt' => ['k' => 1.5, 'h' => 24, 'a' => 20], 'locks' => ['k' => 1.5, 'h' => 24, 'a' => 20], 'lockb' => ['k' => 1.5, 'h' => 24, 'a' => 20], 'tvut' => ['a' => 1, 'n' => 10], 'tvrf' => ['n' => 100, 'm' => 3], 'tvce' => ['n' => 22, 'm' => 3], 'tvssl' => ['n' => 10], 'tvwt' => ['n1' => 10, 'n2' => 21, 'z' => 53], 'tvhull' => ['n' => 55], 'tvqqe' => ['n' => 14, 'sf' => 5, 'q' => 4.238], 'tvst200' => ['n' => 10, 'm' => 3, 't' => 200], 'tvsweep' => ['n' => 20], 'tvfvg' => ['n' => 20, 'w' => 10], 'tvdon' => ['n' => 20, 'd' => 5, 'm' => 14], 'tvrsi70' => ['n' => 14, 'hi' => 70, 'lo' => 30], 'tvvor' => ['n' => 20, 'v' => 20], 'tvabs' => ['n' => 20, 'k' => 2], 'div' => ['n' => 14, 'k' => 3, 'w' => 60], 'zrev' => ['n' => 50, 'z' => 2.5]]; }
function td_run($id, $c, $prm = []) { $d = array_merge(td_defaults(), td_defaults2()); if (!isset($d[$id])) return null; $p = $d[$id]; foreach ((array)$prm as $k => $v) if (array_key_exists($k, $p) && is_numeric($v)) $p[$k] = +$v;
  $f = 'td_s_' . $id; return $f($c, $p); }

function td_now() { return isset($GLOBALS['TD_NOW']) ? $GLOBALS['TD_NOW'] : time(); }
/* ---------- свечи Binance: несколько адресов по очереди ---------- */
function td_bases($mkt) { $r = []; $f = __DIR__ . '/tdemo_relay.php'; if (is_file($f)) { $x = include $f; if (is_string($x) && $x) $r[] = ['relay', rtrim($x, '/')]; }
  if ($mkt === 'fut') $r[] = ['fut', 'https://fapi.binance.com/fapi/v1/klines'];
  $r[] = ['spot', 'https://api.binance.com/api/v3/klines']; $r[] = ['spot', 'https://data-api.binance.vision/api/v3/klines']; return $r; }
function td_get($url) { if (isset($GLOBALS['TD_GET'])) return ($GLOBALS['TD_GET'])($url); $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_TIMEOUT => 15, CURLOPT_USERAGENT => 'ITC-Trading/1.0']);
  $b = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch); return [$code, $b, $err]; }
$TD_SRC = [];
function td_kl($mkt, $sym, $tf, $lim, $start = 0) { global $TD_SRC; static $cache = []; $key = "$mkt|$sym|$tf|$lim|$start|" . intdiv(td_now(), 20); if (isset($cache[$key])) return $cache[$key];
  $q = 'symbol=' . $sym . 'USDT&interval=' . $tf . '&limit=' . $lim . ($start ? '&startTime=' . ($start * 1000) : '');
  foreach (td_bases($mkt) as [$kind, $base]) { $url = $kind === 'relay' ? $base . '?mkt=' . $mkt . '&' . $q : $base . '?' . $q;
    [$code, $b] = td_get($url); $j = json_decode((string)$b, true);
    if ($code === 200 && is_array($j)) { $TD_SRC[$mkt] = $kind; $now = td_now(); $tfs = td_tfsec($tf); $o = [];
      foreach ($j as $k) { $t = (int)($k[0] / 1000); if ($t + $tfs > $now) continue; $o[] = ['time' => $t, 'open' => +$k[1], 'high' => +$k[2], 'low' => +$k[3], 'close' => +$k[4], 'volume' => +$k[5]]; }
      return $cache[$key] = $o; } }
  return $cache[$key] = null; }
function td_tfsec($tf) { $n = (int)$tf; $u = substr($tf, -1); return $n * (['m' => 60, 'h' => 3600, 'd' => 86400, 'w' => 604800][$u] ?? 60); }
function td_price($mkt, $sym) { $c = td_kl($mkt, $sym, '1m', 2); return $c ? end($c)['close'] : null; }

/* ---------- шаг одной серии ---------- */
function td_fee($mkt) { return $mkt === 'spot' ? 0.001 : 0.0005; }
function td_close(&$s, $xp, $t1, $why) { $o = $s['open']; $f = td_fee($s['mkt']);
  $pnl = ($xp - $o['ep']) * $o['qty'] * $o['side'] - ($o['ep'] + $xp) * $o['qty'] * $f;
  $s['trades'][] = ['side' => $o['side'], 'ep' => $o['ep'], 'xp' => $xp, 'qty' => $o['qty'], 't0' => $o['t0'], 't1' => $t1, 'pnl' => round($pnl, 6), 'why' => $why]; $s['open'] = null; }
function td_walk(&$s, $mins, $until) { // минутные свечи до времени $until (сек, не включая): стоп и тейк
  foreach ($mins as $m) { if ($m['time'] <= $s['lastMin']) continue; if ($m['time'] >= $until) break; $s['lastMin'] = $m['time'];
    if (!$s['open'] || $m['time'] < $s['open']['t0']) continue; $o = $s['open'];
    $slp = $o['ep'] * (1 - $o['side'] * $s['sl'] / 100); $tpp = $o['ep'] * (1 + $o['side'] * $s['tp'] / 100);
    $hitS = $o['side'] > 0 ? $m['low'] <= $slp : $m['high'] >= $slp; $hitT = $s['tp'] > 0 && ($o['side'] > 0 ? $m['high'] >= $tpp : $m['low'] <= $tpp);
    if ($hitS) td_close($s, $slp, $m['time'] + 60, 'стоп-лосс'); elseif ($hitT) td_close($s, $tpp, $m['time'] + 60, 'тейк-профит'); } }
function td_step(&$s) { if (!empty($s['sys'])) return td_step_sys($s); if ($s['status'] !== 'run') return false; $now = td_now(); $tfs = td_tfsec($s['tf']); $t0 = (int)floor($s['t0'] / 1000);
  if (empty($s['lastMin'])) $s['lastMin'] = intdiv($t0, 60) * 60 - 60;
  if (empty($s['lastSig'])) $s['lastSig'] = intdiv($t0, $tfs) * $tfs - $tfs;
  if ($now < $s['lastMin'] + 120 && $now < $s['lastSig'] + 2 * $tfs) return false;
  $c = td_kl($s['mkt'], $s['sym'], $s['tf'], 700); if ($c === null) { $s['err'] = 'Нет связи с биржей'; return true; }
  $mins = $s['tf'] === '1m' ? $c : (td_kl($s['mkt'], $s['sym'], '1m', 1000, $s['lastMin'] + 60) ?? []);
  if (in_array($s['strat'], ['btcl', 'btct', 'lockb'], true)) $GLOBALS['TD_BTC'] = td_kl($s['mkt'], 'BTC', $s['tf'], 1000) ?: [];
  if ($s['tf'] === '1m' && $c && $c[0]['time'] > $s['lastMin'] + 60) { $more = td_kl($s['mkt'], $s['sym'], '1m', 1000, $s['lastMin'] + 60); if ($more) $mins = $more; }
  $s['err'] = ''; $done = 0; $maxT = $mins ? end($mins)['time'] + 60 : $now;
  foreach ($c as $i => $b) { if ($b['time'] <= $s['lastSig']) continue; $ct = $b['time'] + $tfs; if ($ct > $maxT || $done++ > 300) break;
    td_walk($s, $mins, $ct);
    if (!$s['open'] && $s['opened'] < $s['n'] && $ct >= $t0) {
      $from = max(0, $i - 399); $win = array_slice($c, $from, $i - $from + 1); $sig = td_run($s['strat'], $win, $s['prm'] ?? []); $L = $sig ? end($sig) : null;
      if ($L && $L['i'] === count($win) - 1 && ($L['t'] === 'long' || ($L['t'] === 'short' && $s['mkt'] === 'fut'))) {
        $side = $L['t'] === 'long' ? 1 : -1; $ep = $b['close']; $qty = $s['amt'] * $s['lev'] / $ep;
        $s['open'] = ['side' => $side, 'ep' => $ep, 'qty' => $qty, 't0' => $ct]; $s['opened']++; } }
    $s['lastSig'] = $b['time']; }
  td_walk($s, $mins, $now + 1);
  if ($s['opened'] >= $s['n'] && !$s['open']) { $s['status'] = 'done'; $s['tdone'] = $now; }
  $s['px'] = $mins ? end($mins)['close'] : ($c ? end($c)['close'] : ($s['px'] ?? null)); $s['upd'] = $now; return true; }
require_once __DIR__ . '/tdemo_sys.php';
