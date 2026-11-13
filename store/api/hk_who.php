<?php
// ==== ИТК · api/hk_who.php (11.11.2026) ====
// Гонконгский сервер проверяет пропуск сотрудника: «чей это пропуск и какая роль?».
// Отвечает только гонконгскому серверу (его ключ X-HK-Token), сам пропуск никуда не сохраняется.
require_once __DIR__ . '/config.php';
@include __DIR__ . '/hk_auth.php';
$k = (string)($_SERVER['HTTP_X_HK_TOKEN'] ?? '');
if (!defined('HK_TOKEN_SHA') || strlen($k) < 32 || !hash_equals(HK_TOKEN_SHA, hash('sha256', $k))) { http_response_code(403); header('Content-Type: application/json'); echo json_encode(['ok' => false, 'error' => 'нет доступа']); exit; }
$S = authUser('itc');
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
echo json_encode(['ok' => true, 'role' => 'itc', 'phone' => (string)($S['phone'] ?? ''), 'staff_role' => (string)($S['staff_role'] ?? ''), 'name' => (string)($S['name'] ?? '')], JSON_UNESCAPED_UNICODE);
