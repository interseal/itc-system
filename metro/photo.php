<?php
// ==== Мой Гуанчжоу · photo.php v1 · 11.10.2026 · фото к «Моим местам» ====
// Приложение уже уменьшило снимок (до 1280 px, JPEG). Здесь проверяем, что это
// настоящая картинка, и кладём её в папку myphoto/. Возвращаем адрес файла.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function out($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

$ref = ($_SERVER['HTTP_ORIGIN'] ?? '') . ' ' . ($_SERVER['HTTP_REFERER'] ?? '');
if (strpos($ref, 'metro.itcpartner.store') === false) out(['ok' => false, 'error' => 'forbidden']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'error' => 'post_only']);

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$img = (string)($in['img'] ?? '');
if (!preg_match('~^data:image/(jpeg|png|webp);base64,~', $img, $m)) out(['ok' => false, 'error' => 'not_image']);
$bin = base64_decode(substr($img, strpos($img, ',') + 1), true);
if ($bin === false || strlen($bin) < 1000 || strlen($bin) > 5 * 1024 * 1024) out(['ok' => false, 'error' => 'bad_size']);
$info = @getimagesizefromstring($bin);
if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) out(['ok' => false, 'error' => 'not_image']);
$ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2]];

$dir = __DIR__ . '/myphoto';
if (!is_dir($dir)) @mkdir($dir, 0755);
if (!is_file("$dir/index.html")) @file_put_contents("$dir/index.html", '');
$name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
if (@file_put_contents("$dir/$name", $bin) === false) out(['ok' => false, 'error' => 'write_failed']);
out(['ok' => true, 'url' => 'myphoto/' . $name]);
