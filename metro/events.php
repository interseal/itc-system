<?php
// ==== Мой Гуанчжоу · events.php v1 · 11.10.2026 ====
// Отдаёт приложению базу «Событий» (data/events.json — её собирает events_sync.php).
// Пока автоматическое обновление ни разу не прошло — отдаёт стартовую базу data/events_base.json.
// Поле stale=true значит «база старше суток» — приложение тогда тихо запускает обновление.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');
$d = __DIR__ . '/data';
$f = "$d/events.json"; $b = "$d/events_base.json";
$src = is_file($f) ? $f : (is_file($b) ? $b : null);
if (!$src) { echo '{"ok":false,"error":"no_data"}'; exit; }
$j = json_decode(file_get_contents($src), true);
if (!$j) { echo '{"ok":false,"error":"bad_data"}'; exit; }
$age = is_file($f) ? time() - filemtime($f) : 1e9;
$j['ok'] = true;
$j['stale'] = $age > 20 * 3600;
if (empty($j['updated'])) $j['updated'] = $j['built'] ?? '';
echo json_encode($j, JSON_UNESCAPED_UNICODE);
