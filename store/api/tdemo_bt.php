<?php
// ==== ИТК · ПРОВЕРКА СЕРИИ НА ИСТОРИИ С РАЗНЫМИ НАСТРОЙКАМИ · api/tdemo_bt.php · №565 (14.11.2026) ====
// Руководитель: «больше минусовых сделок — может, стоп длиннее, или рынок не туда?».
// Берём ту же серию (монета, стратегия, правило стопа, фильтры) и прогоняем её по минутным свечам Binance
// за последние N дней теми же правилами, что и сервер (tdemo_sys.php), но с разными стопами, свечами и фильтрами.
// Отвечает по одной монете за запрос — приложение спрашивает монеты по очереди и складывает итог.

function tdb_mins($mkt, $sym, $from, $to) { $o = []; $st = $from;
  for ($k = 0; $k < 16 && $st < $to; $k++) { $c = td_kl($mkt, $sym, '1m', 1000, $st); if (!$c) break;
    foreach ($c as $x) if ($x['time'] >= $st) $o[] = $x; $last = end($c)['time']; if ($last + 60 <= $st) break; $st = $last + 60; if (count($c) < 900) break; }
  return $o; }

function tdb_agg($m, $tf) { $tfs = td_tfsec($tf); if ($tfs <= 60) return $m; $o = []; $cur = null; $cnt = 0;
  foreach ($m as $x) { $b = intdiv($x['time'], $tfs) * $tfs;
    if (!$cur || $cur['time'] !== $b) { if ($cur) $o[] = $cur; $cur = ['time' => $b, 'open' => $x['open'], 'high' => $x['high'], 'low' => $x['low'], 'close' => $x['close'], 'volume' => $x['volume']]; }
    else { $cur['high'] = max($cur['high'], $x['high']); $cur['low'] = min($cur['low'], $x['low']); $cur['close'] = $x['close']; $cur['volume'] += $x['volume']; } }
  if ($cur && $cur['time'] + $tfs <= end($m)['time'] + 60) $o[] = $cur; return $o; }

// сигналы по всей истории сразу + те же фильтры, что в tds_sig
function tdb_sigs($s, $c, $H) { $n = count($c); $L = td_run($s['strat'], $c, $s['prm'] ?? []) ?: []; $T = []; foreach ($L as $x) $T[$x['i']] = $x['t'];
  $sys = $s['sys']; $tfs = td_tfsec($s['tf']); $A = !empty($sys['adx']) ? tds_adx($c, 14) : null; $V = null;
  if (!empty($sys['vol'])) { $at = td_atr($c, 14); $V = []; for ($i = 0; $i < $n; $i++) { $v = []; for ($j = max(0, $i - 199); $j <= $i; $j++) if ($at[$j] !== null) $v[] = $at[$j] / $c[$j]['close']; sort($v); $med = $v ? $v[intdiv(count($v), 2)] : 0; $V[$i] = $at[$i] !== null && $at[$i] / $c[$i]['close'] >= $med; } }
  foreach ($T as $i => $t) { if ($t !== 'long' && $t !== 'short') continue;
    if (!empty($sys['htf']) && $H) { $d = tds_htf_dir($H, $c[$i]['time'], $tfs, $H['s']); if (($t === 'long' && $d !== 1) || ($t === 'short' && $d !== -1)) $t = 'exit'; }
    if (($t === 'long' || $t === 'short') && $A !== null && !($A[$i] !== null && $A[$i] >= 20)) $t = 'exit';
    if (($t === 'long' || $t === 'short') && !empty($sys['hrs'])) { $h = intdiv($c[$i]['time'] % 86400, 3600); if ($h < 7 || $h >= 21) $t = 'exit'; }
    if (($t === 'long' || $t === 'short') && $V !== null && empty($V[$i])) $t = 'exit';
    $dir = (int)($sys['dir'] ?? 0); if ($dir === 1 && $t === 'short') $t = 'exit'; if ($dir === -1 && $t === 'long') $t = 'exit';
    if ($t === 'short' && $s['mkt'] !== 'fut') $t = 'exit'; $T[$i] = $t; }
  return $T; }

function tdb_sim($s0, $c, $mins, $T, $startT) { $s = $s0; $s['open'] = null; $s['trades'] = []; $s['opened'] = 0; $s['n'] = PHP_INT_MAX; $tfs = td_tfsec($s['tf']);
  $mp = 0; $M = count($mins); $endT = $M ? $mins[$M - 1]['time'] + 60 : 0;
  $walk = function ($until) use (&$s, &$mp, $mins, $M) { while ($mp < $M && $mins[$mp]['time'] < $until) { $m = $mins[$mp++]; if (!$s['open'] || $m['time'] < $s['open']['t0']) continue; $o = $s['open']; $d = $o['side'];
      $hitS = $d > 0 ? $m['low'] <= $o['stop'] : $m['high'] >= $o['stop']; $hitT = $o['tp'] !== null && ($d > 0 ? $m['high'] >= $o['tp'] : $m['low'] <= $o['tp']);
      if ($hitS) { $px = $d > 0 ? min($m['open'], $o['stop']) : max($m['open'], $o['stop']); tds_close($s, $px, $m['time'] + 60, 'стоп'); }
      elseif ($hitT) tds_close($s, $o['tp'], $m['time'] + 60, 'тейк'); } };
  foreach ($c as $i => $b) { $ct = $b['time'] + $tfs; if ($ct > $endT) break; $walk($ct);
    if ($ct < $startT || $i < 30) continue; $t = $T[$i] ?? null;
    if ($s['open'] && !empty($s['open']['xs']) && $t !== null && ($t === 'exit' || ($t === 'long' ? 1 : -1) !== $s['open']['side'])) tds_close($s, $b['close'], $ct, 'сигнал');
    if (!$s['open'] && ($t === 'long' || $t === 'short')) { $f = max(0, $i - 299); tds_open($s, array_slice($c, $f, $i - $f + 1), $t === 'long' ? 1 : -1, $ct); }
    elseif ($s['open'] && $s['open']['t0'] < $ct && !empty($s['open']['trail'])) { $f = max(0, $i - 299); tds_trail($s, array_slice($c, $f, $i - $f + 1)); } }
  $walk(PHP_INT_MAX); $openNow = 0; if ($s['open']) { $openNow = 1; tds_close($s, $mins[$M - 1]['close'], $endT, 'конец проверки'); }
  $f = tds_fee($s) * (in_array($s['strat'], ['lockr', 'lockt', 'locks', 'lockb'], true) ? 2 : 1); $n = 0; $w = 0; $sum = 0; $fee = 0;
  foreach ($s['trades'] as $x) { $n++; if ($x['pnl'] > 0) $w++; $sum += $x['pnl']; $fee += ($x['ep'] + $x['xp']) * $x['qty'] * $f; }
  return ['n' => $n, 'win' => $w, 'sum' => round($sum, 4), 'fee' => round($fee, 4), 'open' => $openNow]; }

// варианты: свеча × стоп при текущих фильтрах + текущие настройки с фильтром тренда / с лимитными заявками
function tdb_variants($s) { $K = (int)($s['sys']['k'] ?? 2); $tfs = [$s['tf']];
  foreach (['5m', '15m', '1h'] as $t) if (td_tfsec($t) > td_tfsec($s['tf']) && count($tfs) < 3 && td_tfsec($t) <= 60 * td_tfsec($s['tf'])) $tfs[] = $t;
  $stops = $K <= 3 ? array_values(array_unique(array_map(fn($x) => round($x, 2), array_merge([(float)$s['sl']], [0.5, 0.8, 1.0, 1.5])))) : array_values(array_unique([(float)($s['sys']['sm'] ?? 1), 1.0, 1.5, 2.0]));
  sort($stops); $o = [];
  foreach ($tfs as $tf) foreach ($stops as $v) $o[] = ['tf' => $tf, 'stop' => $v, 'htf' => (int)!empty($s['sys']['htf']), 'maker' => (int)!empty($s['sys']['maker'])];
  $cur = $K <= 3 ? round((float)$s['sl'], 2) : (float)($s['sys']['sm'] ?? 1);
  if (empty($s['sys']['htf'])) $o[] = ['tf' => $s['tf'], 'stop' => $cur, 'htf' => 1, 'maker' => (int)!empty($s['sys']['maker'])];
  if (empty($s['sys']['maker'])) $o[] = ['tf' => $s['tf'], 'stop' => $cur, 'htf' => (int)!empty($s['sys']['htf']), 'maker' => 1];
  if (empty($s['sys']['htf']) && empty($s['sys']['maker'])) $o[] = ['tf' => $s['tf'], 'stop' => $cur, 'htf' => 1, 'maker' => 1];
  return $o; }

function tdb_run($s, $days) { if (empty($s['sys'])) $s['sys'] = ['k' => $s['tp'] > 0 ? max(1, min(3, (int)round($s['tp'] / max(0.01, $s['sl'])))) : 0, 'dir' => 0];
  $now = td_now(); $startT = $now - $days * 86400; $vars = tdb_variants($s); $maxTf = max(array_map(fn($v) => td_tfsec($v['tf']), $vars));
  $warm = min(3 * 86400, max(86400, 250 * $maxTf)); $mins = tdb_mins($s['mkt'], $s['sym'], intdiv($startT - $warm, 60) * 60, $now);
  if (count($mins) < 500) return ['ok' => false, 'error' => 'Нет свечей Binance по ' . $s['sym']];
  $K = (int)($s['sys']['k'] ?? 2); $C = []; $SG = []; $Hc = []; $rows = [];
  foreach ($vars as $v) { $tf = $v['tf']; if (!isset($C[$tf])) $C[$tf] = tdb_agg($mins, $tf);
    $key = $tf . '|' . $v['htf']; if (!isset($SG[$key])) { $s2 = $s; $s2['tf'] = $tf; $s2['sys']['htf'] = $v['htf']; $H = null;
      if ($v['htf'] && ($h = tds_htf_of($tf))) { if (!array_key_exists($h, $Hc)) { $hc = td_kl($s['mkt'], $s['sym'], $h, 1000); $Hc[$h] = $hc ? ['t' => array_map(fn($x) => $x['time'], $hc), 'cl' => td_cl($hc), 'e' => td_ema(td_cl($hc), 200), 's' => td_tfsec($h)] : null; } $H = $Hc[$h]; }
      $SG[$key] = tdb_sigs($s2, $C[$tf], $H); }
    $s2 = $s; $s2['tf'] = $tf; $s2['sys']['htf'] = $v['htf']; $s2['sys']['maker'] = $v['maker'];
    if ($K <= 3) $s2['sl'] = $v['stop']; else $s2['sys']['sm'] = $v['stop'];
    $r = tdb_sim($s2, $C[$tf], $mins, $SG[$key], $startT); $rows[] = $v + $r; }
  return ['ok' => true, 'sym' => $s['sym'], 'days' => $days, 'k' => $K, 'cur' => ['tf' => $s['tf'], 'stop' => $K <= 3 ? round((float)$s['sl'], 2) : (float)($s['sys']['sm'] ?? 1), 'htf' => (int)!empty($s['sys']['htf']), 'maker' => (int)!empty($s['sys']['maker'])], 'rows' => $rows]; }
