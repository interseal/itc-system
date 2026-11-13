<?php
// ==== ИТК · СЕРИИ «ПО СИСТЕМЕ» НА СЕРВЕРЕ · api/tdemo_sys.php · №1 (10.11.2026) ====
// Серия торгует так же, как «Проверка на истории» в приложении: правило стопа и выхода (1 к 1/2/3, по сигналу, по уровням, правила стратегии),
// направление (лонг и шорт / только лонг / только шорт), фильтры (старший тренд, без боковика) и комиссия (по рынку / лимитные заявки).
// sys = ['k'=>0..5, 'dir'=>0|1|-1, 'htf'=>0|1, 'adx'=>0|1, 'maker'=>0|1]

function tds_rules() { return [
  'ema' => ['stop' => 'swing', 'n' => 10, 'take' => 'sig'], 'st' => ['stop' => 'line', 'take' => 'sig', 'trail' => 'line'], 'rsi' => ['stop' => 'swing', 'n' => 10, 'take' => 'sig'],
  'don' => ['stop' => 'atr', 'k' => 2, 'take' => 'sig'], 'macd' => ['stop' => 'swing', 'n' => 10, 'take' => 'sig'], 'bb' => ['stop' => 'bar', 'take' => 'sig'],
  'pat' => ['stop' => 'lvl', 'take' => 'lvl'], 'lvl' => ['stop' => 'lvl', 'take' => 'lvl'], 'stoch' => ['stop' => 'swing', 'n' => 10, 'take' => 'R', 'R' => 2],
  'ichi' => ['stop' => 'line', 'take' => 'sig', 'trail' => 'line'], 'psar' => ['stop' => 'line', 'take' => 'sig', 'trail' => 'line'], 'adx' => ['stop' => 'atr', 'k' => 3, 'take' => 'sig', 'trail' => 'chand'],
  'sqz' => ['stop' => 'atr', 'k' => 3, 'take' => 'none', 'trail' => 'chand'], 'rsi2' => ['stop' => 'atr', 'k' => 3, 'take' => 'sig'], 'pull' => ['stop' => 'swing', 'n' => 10, 'take' => 'lvl'],
  'cci' => ['stop' => 'swing', 'n' => 10, 'take' => 'R', 'R' => 2], 'ha' => ['stop' => 'swing', 'n' => 5, 'take' => 'sig'], 'inside' => ['stop' => 'mother', 'take' => 'R', 'R' => 2],
  'volbo' => ['stop' => 'bar', 'take' => 'none', 'trail' => 'chand'], 'mtf' => ['stop' => 'atr', 'k' => 2, 'take' => 'sig'], 'orb' => ['stop' => 'swing', 'n' => 4, 'take' => 'sig'], 'vwapm' => ['stop' => 'swing', 'n' => 5, 'take' => 'sig'], 'kelt' => ['stop' => 'swing', 'n' => 5, 'take' => 'sig'], 'rsibb' => ['stop' => 'swing', 'n' => 5, 'take' => 'sig'], 'sfp' => ['stop' => 'bar', 'take' => 'R', 'R' => 2], 'mpull' => ['stop' => 'swing', 'n' => 5, 'take' => 'sig'], 'btcl' => ['stop' => 'atr', 'k' => 1.5, 'take' => 'sig'], 'btct' => ['stop' => 'atr', 'k' => 2, 'take' => 'sig'], 'lockr' => ['stop' => 'sig', 'take' => 'R', 'R' => 2], 'lockt' => ['stop' => 'sig', 'take' => 'none', 'trail' => 'chand'], 'locks' => ['stop' => 'sig', 'take' => 'sig'], 'lockb' => ['stop' => 'sig', 'take' => 'sig'], 'tvut' => ['stop' => 'atr', 'k' => 2, 'take' => 'sig'], 'tvrf' => ['stop' => 'atr', 'k' => 2, 'take' => 'sig'], 'tvce' => ['stop' => 'atr', 'k' => 3, 'take' => 'sig'], 'tvssl' => ['stop' => 'swing', 'n' => 10, 'take' => 'sig'], 'tvwt' => ['stop' => 'swing', 'n' => 10, 'take' => 'sig'], 'tvhull' => ['stop' => 'atr', 'k' => 2, 'take' => 'sig'], 'tvqqe' => ['stop' => 'atr', 'k' => 2, 'take' => 'sig'], 'tvst200' => ['stop' => 'atr', 'k' => 2, 'take' => 'sig'], 'tvsweep' => ['stop' => 'bar', 'take' => 'R', 'R' => 2], 'tvfvg' => ['stop' => 'swing', 'n' => 5, 'take' => 'R', 'R' => 2], 'tvdon' => ['stop' => 'atr', 'k' => 2, 'take' => 'sig'], 'tvrsi70' => ['stop' => 'atr', 'k' => 3, 'take' => 'sig'], 'tvvor' => ['stop' => 'swing', 'n' => 5, 'take' => 'R', 'R' => 2], 'tvabs' => ['stop' => 'bar', 'take' => 'R', 'R' => 2], 'div' => ['stop' => 'swing', 'n' => 10, 'take' => 'sig'], 'zrev' => ['stop' => 'atr', 'k' => 2, 'take' => 'sig']]; }
function tds_fee($s) { return $s['mkt'] === 'spot' ? 0.001 : (!empty($s['sys']['maker']) ? 0.0002 : 0.0005); }
function tds_lowN($c, $i, $n) { $l = 1e18; for ($j = max(0, $i - $n + 1); $j <= $i; $j++) $l = min($l, $c[$j]['low']); return $l; }
function tds_highN($c, $i, $n) { $h = -1e18; for ($j = max(0, $i - $n + 1); $j <= $i; $j++) $h = max($h, $c[$j]['high']); return $h; }
// линии для стопа: Супертренд, Параболик, Киджун
function tds_lines($id, $c, $p) { $n = count($c);
  if ($id === 'st') { $a = td_atr($c, $p['n']); $up = []; $dn = []; $dir = [];
    for ($i = 0; $i < $n; $i++) { if ($a[$i] === null) { $up[] = null; $dn[] = null; $dir[] = 0; continue; } $m = ($c[$i]['high'] + $c[$i]['low']) / 2; $u = $m - $p['m'] * $a[$i]; $d = $m + $p['m'] * $a[$i];
      if ($i > 0 && $up[$i - 1] !== null && $c[$i - 1]['close'] > $up[$i - 1]) $u = max($u, $up[$i - 1]); if ($i > 0 && $dn[$i - 1] !== null && $c[$i - 1]['close'] < $dn[$i - 1]) $d = min($d, $dn[$i - 1]); $up[] = $u; $dn[] = $d; }
    return ['L' => $up, 'S' => $dn]; }
  if ($id === 'psar') { $o = array_fill(0, $n, null); if ($n < 3) return ['L' => $o, 'S' => $o]; $s = 0.02; $mx = 0.2; $up = $c[1]['close'] >= $c[0]['close']; $sar = $up ? $c[0]['low'] : $c[0]['high']; $ep = $up ? $c[1]['high'] : $c[1]['low']; $af = $s;
    for ($i = 2; $i < $n; $i++) { $sar = $sar + $af * ($ep - $sar);
      if ($up) { $sar = min($sar, $c[$i - 1]['low'], $c[$i - 2]['low']); if ($c[$i]['low'] < $sar) { $up = false; $sar = $ep; $ep = $c[$i]['low']; $af = $s; } elseif ($c[$i]['high'] > $ep) { $ep = $c[$i]['high']; $af = min($mx, $af + $s); } }
      else { $sar = max($sar, $c[$i - 1]['high'], $c[$i - 2]['high']); if ($c[$i]['high'] > $sar) { $up = true; $sar = $ep; $ep = $c[$i]['high']; $af = $s; } elseif ($c[$i]['low'] < $ep) { $ep = $c[$i]['low']; $af = min($mx, $af + $s); } } $o[$i] = $sar; }
    return ['L' => $o, 'S' => $o]; }
  if ($id === 'ichi') { $k = (int)($p['k'] ?? 26) ?: 26; $o = []; for ($i = 0; $i < $n; $i++) $o[] = $i < $k - 1 ? null : (tds_highN($c, $i, $k) + tds_lowN($c, $i, $k)) / 2; return ['L' => $o, 'S' => $o]; }
  return null; }
// точки разворота: стоп за фигурой и тейк у следующего уровня
function tds_piv($c) { $k = 4; $n = count($c); $PH = []; $PL = [];
  for ($j = $k; $j < $n - $k; $j++) { $h = true; $l = true; for ($q = $j - $k; $q <= $j + $k; $q++) { if ($q === $j) continue; if ($c[$q]['high'] > $c[$j]['high']) $h = false; if ($c[$q]['low'] < $c[$j]['low']) $l = false; }
    if ($h) $PH[] = ['i' => $j, 'ok' => $j + $k, 'p' => $c[$j]['high']]; if ($l) $PL[] = ['i' => $j, 'ok' => $j + $k, 'p' => $c[$j]['low']]; } return [$PH, $PL]; }
function tds_seen($A, $i) { return array_values(array_filter($A, fn($x) => $x['ok'] <= $i && $x['i'] >= $i - 200)); }
function tds_next($PH, $PL, $i, $d, $ep, $a) { if ($d > 0) { $v = array_map(fn($x) => $x['p'], array_filter(tds_seen($PH, $i), fn($x) => $x['p'] > $ep + $a * 0.3)); sort($v); return $v ? $v[0] - $a * 0.1 : null; }
  $v = array_map(fn($x) => $x['p'], array_filter(tds_seen($PL, $i), fn($x) => $x['p'] < $ep - $a * 0.3)); rsort($v); return $v ? $v[0] + $a * 0.1 : null; }
function tds_plan_lvl($c, $PH, $PL, $i, $d, $ep, $a, $SM = 1) {
  if ($d > 0) { $L = array_reverse(array_values(array_filter(tds_seen($PL, $i), fn($x) => $x['p'] < $ep))); $b = min($c[$i]['low'], $c[$i - 1]['low']);
    if ($L) { $b = min($b, $L[0]['p']); if (isset($L[1]) && abs($L[1]['p'] - $L[0]['p']) <= $a) $b = min($b, $L[1]['p']); } $stop = min($b - $a * 0.2, $ep - $a * 0.5); }
  else { $H = array_reverse(array_values(array_filter(tds_seen($PH, $i), fn($x) => $x['p'] > $ep))); $b = max($c[$i]['high'], $c[$i - 1]['high']);
    if ($H) { $b = max($b, $H[0]['p']); if (isset($H[1]) && abs($H[1]['p'] - $H[0]['p']) <= $a) $b = max($b, $H[1]['p']); } $stop = max($b + $a * 0.2, $ep + $a * 0.5); }
  if ($SM > 1) $stop = $ep - $d * abs($ep - $stop) * $SM; $risk = abs($ep - $stop);
  if ($SM > 1) { if ($d > 0) { $L = array_values(array_filter(array_map(fn($x) => $x['p'] - $a * 0.1, tds_seen($PH, $i)), fn($p) => $p - $ep >= $risk)); sort($L); } else { $L = array_values(array_filter(array_map(fn($x) => $x['p'] + $a * 0.1, tds_seen($PL, $i)), fn($p) => $ep - $p >= $risk)); rsort($L); } $tp = $L ? $L[0] : $ep + $d * 2 * $risk; }
  else { $t = tds_next($PH, $PL, $i, $d, $ep, $a); $tp = $t !== null ? $t : $ep + $d * 2 * $risk; }
  if (abs($tp - $ep) < $risk) return null; return ['stop' => $stop, 'tp' => $tp]; }
// фильтры — как в приложении: запрещённый вход превращается в «выход»
function tds_adx($c, $n) { $N = count($c); $pd = [0]; $md = [0]; $tr = [0];
  for ($i = 1; $i < $N; $i++) { $u = $c[$i]['high'] - $c[$i - 1]['high']; $d = $c[$i - 1]['low'] - $c[$i]['low']; $pd[] = $u > $d && $u > 0 ? $u : 0; $md[] = $d > $u && $d > 0 ? $d : 0; $tr[] = max($c[$i]['high'] - $c[$i]['low'], abs($c[$i]['high'] - $c[$i - 1]['close']), abs($c[$i]['low'] - $c[$i - 1]['close'])); }
  $w = function ($a) use ($N, $n) { $r = array_fill(0, $N, null); $s = 0; for ($i = 1; $i < $N; $i++) { if ($i <= $n) { $s += $a[$i]; if ($i === $n) $r[$i] = $s; } else { $s = $s - $s / $n + $a[$i]; $r[$i] = $s; } } return $r; };
  $T = $w($tr); $P = $w($pd); $M = $w($md); $A = array_fill(0, $N, null); $s = 0; $k = 0;
  for ($i = 0; $i < $N; $i++) { if (!$T[$i]) continue; $p = 100 * $P[$i] / $T[$i]; $m = 100 * $M[$i] / $T[$i]; $dx = ($p + $m) ? 100 * abs($p - $m) / ($p + $m) : 0; $k++; if ($k < $n) $s += $dx; elseif ($k === $n) { $s += $dx; $A[$i] = $s / $n; } else $A[$i] = ($A[$i - 1] * ($n - 1) + $dx) / $n; } return $A; }
function tds_htf_of($tf) { return ['1m' => '1h', '5m' => '1h', '15m' => '4h', '30m' => '4h', '1h' => '1d', '4h' => '1d'][$tf] ?? null; }
function tds_htf_dir($H, $t, $tfs, $hs) { if (!$H) return 0; $lim = $t + $tfs - $hs; $k = -1; foreach ($H['t'] as $j => $ht) { if ($ht <= $lim) $k = $j; else break; } if ($k < 0 || $H['e'][$k] === null) return 0; return $H['cl'][$k] > $H['e'][$k] ? 1 : -1; }
function tds_sig($s, $win, $H) { $L = td_run($s['strat'], $win, $s['prm'] ?? []); if (!$L) return null; $x = end($L); $last = count($win) - 1; if ($x['i'] !== $last) return null; $t = $x['t']; $sys = $s['sys'];
  if (($t === 'long' || $t === 'short') && !empty($sys['htf']) && $H) { $d = tds_htf_dir($H, $win[$last]['time'], td_tfsec($s['tf']), $H['s']); if (($t === 'long' && $d !== 1) || ($t === 'short' && $d !== -1)) $t = 'exit'; }
  if (($t === 'long' || $t === 'short') && !empty($sys['adx'])) { $A = tds_adx($win, 14); if (!($A[$last] !== null && $A[$last] >= 20)) $t = 'exit'; }
  if (($t === 'long' || $t === 'short') && !empty($sys['hrs'])) { $h = intdiv($win[$last]['time'] % 86400, 3600); if ($h < 7 || $h >= 21) $t = 'exit'; }
  if (($t === 'long' || $t === 'short') && !empty($sys['vol'])) { $A = td_atr($win, 14); $v = []; for ($j = max(0, $last - 199); $j <= $last; $j++) if ($A[$j] !== null) $v[] = $A[$j] / $win[$j]['close']; sort($v); $med = $v ? $v[intdiv(count($v), 2)] : 0; if (!($A[$last] !== null && $A[$last] / $win[$last]['close'] >= $med)) $t = 'exit'; }
  $dir = (int)($sys['dir'] ?? 0); if ($dir === 1 && $t === 'short') $t = 'exit'; if ($dir === -1 && $t === 'long') $t = 'exit';
  if ($t === 'short' && $s['mkt'] !== 'fut') $t = 'exit'; return $t; }
function tds_open(&$s, $win, $side, $ct) { $i = count($win) - 1; $ep = $win[$i]['close']; $A = td_atr($win, 14); $a = $A[$i] ?: $ep * 0.01; $K = (int)$s['sys']['k']; $SM = in_array((float)($s['sys']['sm'] ?? 1), [1.5, 2.0, 3.0], true) ? (float)$s['sys']['sm'] : 1; $stop = null; $tp = null; $exitSig = false; $trail = null;
  if ($K >= 1 && $K <= 3) { $stop = $ep * (1 - $side * $s['sl'] / 100); $tp = $ep * (1 + $side * $K * $s['sl'] / 100); }
  elseif ($K === 0) { $stop = $ep * (1 - $side * $s['sl'] / 100); $exitSig = true; }
  elseif ($K === 4) { [$PH, $PL] = tds_piv($win); $q = tds_plan_lvl($win, $PH, $PL, $i, $side, $ep, $a, $SM); if (!$q) return false; $stop = $q['stop']; $tp = $q['tp']; }
  else { $R = tds_rules()[$s['strat']] ?? ['stop' => 'swing', 'n' => 10, 'take' => 'R', 'R' => 2]; $p = array_merge(td_defaults(), td_defaults2())[$s['strat']] ?? []; foreach ((array)($s['prm'] ?? []) as $kk => $v) if (array_key_exists($kk, $p) && is_numeric($v)) $p[$kk] = +$v;
    $PH = $PL = null; if ($R['stop'] === 'lvl' || $R['take'] === 'lvl') [$PH, $PL] = tds_piv($win);
    if ($R['stop'] === 'sig') { $sg = td_run($s['strat'], $win, (array)($s['prm'] ?? [])); $q = $sg ? end($sg) : null; $stop = ($q && $q['i'] === $i && isset($q['sl']) && ($side > 0 ? $q['sl'] < $ep : $q['sl'] > $ep)) ? $q['sl'] : $ep - $side * 2 * $a; }
    else if ($R['stop'] === 'swing') $stop = $side > 0 ? tds_lowN($win, $i, $R['n']) - $a * 0.1 : tds_highN($win, $i, $R['n']) + $a * 0.1;
    elseif ($R['stop'] === 'atr') $stop = $ep - $side * $R['k'] * $a;
    elseif ($R['stop'] === 'bar') $stop = $side > 0 ? min($win[$i]['low'], $win[$i - 1]['low']) - $a * 0.2 : max($win[$i]['high'], $win[$i - 1]['high']) + $a * 0.2;
    elseif ($R['stop'] === 'mother') { $m = $win[$i - 2]; for ($j = $i - 1; $j >= max(2, $i - 3); $j--) { $b = $win[$j]; $mm = $win[$j - 1]; if ($b['high'] < $mm['high'] && $b['low'] > $mm['low']) { $m = $mm; break; } } $stop = $side > 0 ? $m['low'] - $a * 0.1 : $m['high'] + $a * 0.1; }
    elseif ($R['stop'] === 'line') { $Ln = tds_lines($s['strat'], $win, $p); $v = $Ln ? ($side > 0 ? $Ln['L'][$i] : $Ln['S'][$i]) : null; $stop = ($v !== null && ($side > 0 ? $v < $ep : $v > $ep)) ? $v : $ep - $side * 2 * $a; }
    elseif ($R['stop'] === 'lvl') { $q = tds_plan_lvl($win, $PH, $PL, $i, $side, $ep, $a, $SM); if (!$q) return false; $stop = $q['stop']; $tp = $q['tp']; }
    $stop = $side > 0 ? min($stop, $ep - $a * 0.5) : max($stop, $ep + $a * 0.5); if ($SM > 1 && $R['stop'] !== 'lvl') $stop = $ep - $side * abs($ep - $stop) * $SM; $risk = abs($ep - $stop);
    if ($R['take'] === 'R') $tp = $ep + $side * $R['R'] * $risk;
    elseif ($R['take'] === 'lvl' && $tp === null) { $q = tds_next($PH, $PL, $i, $side, $ep, $a); $tp = $q !== null ? $q : $ep + $side * 2 * $risk; if (abs($tp - $ep) < $risk) return false; }
    $exitSig = $R['take'] === 'sig'; $trail = $R['trail'] ?? null; }
  $qty = $s['amt'] * $s['lev'] / $ep; $s['open'] = ['side' => $side, 'ep' => $ep, 'qty' => $qty, 't0' => $ct, 'stop' => $stop, 'tp' => $tp, 'stop0' => $stop, 'best' => $ep, 'xs' => $exitSig, 'trail' => $trail]; $s['opened']++; return true; }
function tds_close(&$s, $xp, $t1, $why) { $o = $s['open']; $f = tds_fee($s) * (in_array($s['strat'], ['lockr', 'lockt', 'locks', 'lockb'], true) ? 2 : 1);
  $pnl = ($xp - $o['ep']) * $o['qty'] * $o['side'] - ($o['ep'] + $xp) * $o['qty'] * $f;
  $s['trades'][] = ['side' => $o['side'], 'ep' => $o['ep'], 'xp' => $xp, 'qty' => $o['qty'], 't0' => $o['t0'], 't1' => $t1, 'pnl' => round($pnl, 6), 'why' => $why]; $s['open'] = null; }
function tds_walk(&$s, $mins, $until) { foreach ($mins as $m) { if ($m['time'] <= $s['lastMin']) continue; if ($m['time'] >= $until) break; $s['lastMin'] = $m['time'];
    if (!$s['open'] || $m['time'] < $s['open']['t0']) continue; $o = $s['open']; $d = $o['side'];
    $hitS = $d > 0 ? $m['low'] <= $o['stop'] : $m['high'] >= $o['stop']; $hitT = $o['tp'] !== null && ($d > 0 ? $m['high'] >= $o['tp'] : $m['low'] <= $o['tp']);
    if ($hitS) { $px = $d > 0 ? min($m['open'], $o['stop']) : max($m['open'], $o['stop']); tds_close($s, $px, $m['time'] + 60, ($d > 0 ? $o['stop'] > $o['ep'] : $o['stop'] < $o['ep']) ? 'скользящий стоп' : 'стоп-лосс'); }
    elseif ($hitT) tds_close($s, $o['tp'], $m['time'] + 60, 'тейк-профит'); } }
function tds_trail(&$s, $win) { $o = &$s['open']; if (!$o || empty($o['trail'])) return; $i = count($win) - 1; $d = $o['side'];
  if ($d > 0 ? $win[$i]['high'] > $o['best'] : $win[$i]['low'] < $o['best']) $o['best'] = $d > 0 ? $win[$i]['high'] : $win[$i]['low'];
  if ($o['trail'] === 'line') { $p = array_merge(td_defaults(), td_defaults2())[$s['strat']] ?? []; foreach ((array)($s['prm'] ?? []) as $kk => $v) if (array_key_exists($kk, $p) && is_numeric($v)) $p[$kk] = +$v;
    $Ln = tds_lines($s['strat'], $win, $p); $v = $Ln ? ($d > 0 ? $Ln['L'][$i] : $Ln['S'][$i]) : null; if ($v !== null && ($d > 0 ? $v < $win[$i]['close'] : $v > $win[$i]['close'])) $o['stop'] = $d > 0 ? max($o['stop'], $v) : min($o['stop'], $v); }
  if ($o['trail'] === 'chand') { $A = td_atr($win, 14); $a = $A[$i] ?: $o['ep'] * 0.01; $SM = in_array((float)($s['sys']['sm'] ?? 1), [1.5, 2.0, 3.0], true) ? (float)$s['sys']['sm'] : 1; $v = $o['best'] - $d * 3 * $SM * $a; $o['stop'] = $d > 0 ? max($o['stop'], $v) : min($o['stop'], $v); } }
function td_step_sys(&$s) { if ($s['status'] !== 'run') return false; $now = td_now(); $tfs = td_tfsec($s['tf']); $t0 = (int)floor($s['t0'] / 1000);
  if (empty($s['lastMin'])) $s['lastMin'] = intdiv($t0, 60) * 60 - 60;
  if (empty($s['lastSig'])) $s['lastSig'] = intdiv($t0, $tfs) * $tfs - $tfs;
  if ($now < $s['lastMin'] + 120 && $now < $s['lastSig'] + 2 * $tfs) return false;
  $c = td_kl($s['mkt'], $s['sym'], $s['tf'], 1000); if ($c === null) { $s['err'] = 'Нет связи с биржей'; return true; }
  $mins = $s['tf'] === '1m' ? $c : (td_kl($s['mkt'], $s['sym'], '1m', 1000, $s['lastMin'] + 60) ?? []);
  if (in_array($s['strat'], ['btcl', 'btct', 'lockb'], true)) $GLOBALS['TD_BTC'] = td_kl($s['mkt'], 'BTC', $s['tf'], 1000) ?: [];
  if ($s['tf'] === '1m' && $c && $c[0]['time'] > $s['lastMin'] + 60) { $more = td_kl($s['mkt'], $s['sym'], '1m', 1000, $s['lastMin'] + 60); if ($more) $mins = $more; }
  $H = null; if (!empty($s['sys']['htf']) && ($h = tds_htf_of($s['tf']))) { $hc = td_kl($s['mkt'], $s['sym'], $h, 400); if ($hc) { $cl = td_cl($hc); $H = ['t' => array_map(fn($x) => $x['time'], $hc), 'cl' => $cl, 'e' => td_ema($cl, 200), 's' => td_tfsec($h)]; } }
  $s['err'] = ''; $done = 0; $maxT = $mins ? end($mins)['time'] + 60 : $now;
  foreach ($c as $i => $b) { if ($b['time'] <= $s['lastSig']) continue; $ct = $b['time'] + $tfs; if ($ct > $maxT || $done++ > 300) break;
    tds_walk($s, $mins, $ct);
    if ($ct >= $t0 && $i >= 30) { $from = max(0, $i - 999); $win = array_slice($c, $from, $i - $from + 1); $t = tds_sig($s, $win, $H);
      if ($s['open'] && !empty($s['open']['xs']) && $t !== null && ($t === 'exit' || ($t === 'long' ? 1 : -1) !== $s['open']['side'])) tds_close($s, $b['close'], $ct, 'по сигналу');
      if (!$s['open'] && $s['opened'] < $s['n'] && ($t === 'long' || $t === 'short')) tds_open($s, $win, $t === 'long' ? 1 : -1, $ct);
      elseif ($s['open'] && $s['open']['t0'] < $ct) tds_trail($s, $win); }
    $s['lastSig'] = $b['time']; }
  tds_walk($s, $mins, $now + 1);
  if ($s['opened'] >= $s['n'] && !$s['open']) { $s['status'] = 'done'; $s['tdone'] = $now; }
  $s['px'] = $mins ? end($mins)['close'] : ($c ? end($c)['close'] : ($s['px'] ?? null)); $s['upd'] = $now; return true; }
