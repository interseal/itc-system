<?php
declare(strict_types=1);
// ==== ИТК · КОСМОС · lib/boot.php · ядро ====
// «Космос» — помощник ИТК на Claude. Память, переписка, задачи и ключи хранятся здесь,
// на сервере «Космоса» (база SQLite). В аккаунте Anthropic не хранится ничего,
// поэтому ключ Claude можно менять — «Космос» продолжает с того же места.

const KOSMOS_VERSION = '0.4.7';

// Модели Claude на выбор руководителю (настройки «Космоса»). Можно вписать и другую — по её названию в API.
const MODEL_LIST = [
  ['id' => 'claude-haiku-5-5',  'name' => 'Haiku 5.5 — быстрый и недорогой'],
  ['id' => 'claude-sonnet-5-5', 'name' => 'Sonnet 5.5 — сильный, основной'],
  ['id' => 'claude-opus-5-5',   'name' => 'Opus 5.5 — самый сильный из основных, дороже'],
  ['id' => 'claude-fable-5-2',  'name' => 'Fable 5.2 — старший уровень, самый дорогой'],
];

function cfg(?string $k = null, $def = null) {
  static $c = null;
  if ($c === null) {
    $f = getenv('KOSMOS_CONFIG') ?: '/etc/kosmos/config.php';
    $c = is_file($f) ? (require $f) : [];
    if (!is_array($c)) $c = [];
    $c += [
      'store_url'        => 'https://itcpartner.store',
      'store_token_file' => '/etc/kosmos/store_token',
      'gw_key_file'      => '/etc/kosmos/gw.key',   // 0.2.0 · ключ шлюза: магазин спрашивает Claude от имени «Космоса» в CRM
      'master_key_file'  => '/etc/kosmos/master.key',
      'db'               => '/opt/kosmos/data/kosmos.sqlite',
      'claude_url'       => 'https://api.anthropic.com/v1/messages',
      'models'           => ['fast' => 'claude-haiku-5-5', 'deep' => 'claude-sonnet-5-5', 'max' => 'claude-opus-5-5'],
      'tz'               => 'Asia/Bishkek',     // время компании (Бишкек), а не сервера
      'embed_origins'    => ['https://itcpartner.store', 'https://www.itcpartner.store'],
      'dev_users'        => [],      // только для проверки у себя: пропуск => [phone, name, role]
      'msg_per_min'      => 20,      // защита от случайных расходов: сообщений в минуту на человека
    ];
  }
  return $k === null ? $c : ($c[$k] ?? $def);
}

require_once __DIR__ . '/util.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/price.php';   // 0.3.3 · цены Claude и расход по ключам
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/keys.php';
require_once __DIR__ . '/claude.php';
require_once __DIR__ . '/memory.php';
require_once __DIR__ . '/prompt.php';
require_once __DIR__ . '/tools.php';
require_once __DIR__ . '/chat.php';
require_once __DIR__ . '/tasks.php';
require_once __DIR__ . '/gw.php';
require_once __DIR__ . '/skills.php';
require_once __DIR__ . '/dev.php';    // 0.3.9 · «Космос»-разработчик (только руководитель)   // 0.3.1 · навыки «Космоса» и «Фила»
require_once __DIR__ . '/media.php';  // 0.4.4 · обработка PDF, фото, видео для Фила и «Космоса»

@date_default_timezone_set((string)cfg('tz'));
