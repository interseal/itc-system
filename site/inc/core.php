<?php
$cfg    = require dirname(__DIR__) . '/config.php';
$assets = require dirname(__DIR__) . '/assets.php';
define('ROOT', dirname(__DIR__));

// Картинка: своя из папки img/, а если её ещё нет — с оригинального адреса
function img($name) {
    global $assets;
    if (is_file(ROOT . '/img/' . $name)) return '/img/' . $name;
    return $assets[$name] ?? ('/img/' . $name);
}
// Положение элемента в блоке (для компьютера); на телефоне элементы встают друг под другом
function at($x, $y, $w = null, $h = null) {
    $s = "--x:{$x}px;--y:{$y}px;";
    if ($w !== null) $s .= "--w:{$w}px;";
    if ($h !== null) $s .= "--h:{$h}px;";
    return $s;
}
function e($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
$v = '3.65.0';
// Картинка со старого сайта по её адресу на Тильде (tildXXXX-.../file.jpg)
function timg($path) {
    $local = 'img/t/' . str_replace('/', '_', $path);
    if (is_file(ROOT . '/' . $local)) return '/' . $local;
    return 'https://static.tildacdn.com/' . $path;
}
// Адрес страницы без языка: /en/onas → /onas
function page_url() {
    $u = preg_replace('~^/(en|zh)(?=/|$)~', '', strtok($_SERVER['REQUEST_URI'] ?? '/', '?'));
    return $u === '' ? '/' : $u;
}
require __DIR__ . '/lang.php';
require __DIR__ . '/supdb.php';

