<?php
// ==== ИТК · api/hk_export.php (11.11.2026) ====
// Однократный перенос данных «ИТК Трейдинг» на гонконгский сервер: серии, архив проверок, каталог монет, итоги тестов.
// Отдаёт строки таблиц по частям — только гонконгскому серверу (его ключ X-HK-Token). Ключи Binance не переносятся.
require_once __DIR__ . '/config.php';
@include __DIR__ . '/hk_auth.php';
$k = (string)($_SERVER['HTTP_X_HK_TOKEN'] ?? '');
if (!defined('HK_TOKEN_SHA') || strlen($k) < 32 || !hash_equals(HK_TOKEN_SHA, hash('sha256', $k))) { http_response_code(403); header('Content-Type: application/json'); echo json_encode(['ok' => false, 'error' => 'нет доступа']); exit; }
@set_time_limit(120);
$T = ['itc_tdemo' => 'id', 'itc_tanalysis' => 'id', 'itc_tkv' => 'phone, k', 'itc_tcoinres' => 'phone, sym, tf, sig'];
$t = (string)($_GET['tab'] ?? ''); $off = max(0, (int)($_GET['off'] ?? 0)); $lim = max(1, min(500, (int)($_GET['lim'] ?? 200)));
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
if (!isset($T[$t])) { echo json_encode(['ok' => false, 'error' => 'нет такой таблицы']); exit; }
try { $q = db()->query("SELECT * FROM $t ORDER BY {$T[$t]} LIMIT $lim OFFSET $off"); $rows = $q->fetchAll(PDO::FETCH_ASSOC); }
catch (Throwable $e) { $rows = []; }
echo json_encode(['ok' => true, 'rows' => $rows, 'more' => count($rows) === $lim], JSON_UNESCAPED_UNICODE);
