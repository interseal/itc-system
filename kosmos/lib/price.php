<?php
declare(strict_types=1);
// ==== КОСМОС · lib/price.php · сколько стоят ответы Claude (0.3.3) ====
// Остатка денег на счёте Claude не отдаёт: такого запроса в API нет (баланс виден только на
// platform.claude.com → Settings → Billing). Поэтому «Космос» сам считает, сколько потратил каждый ключ:
// стоимость каждого ответа пишется в usage.cost_u — в миллионных долях доллара (1 000 000 = $1).
// Руководитель один раз указывает остаток со страницы Billing, дальше магазин вычитает расход.
//
// Цены — за 1 млн токенов, в долларах США: вход, выход, запись в кэш на 5 минут, чтение из кэша.
// Источник: platform.claude.com/docs/en/about-claude/pricing (проверено 14.11.2026).
// Haiku 5.5 дороже, если в запросе больше 100 000 токенов: считаются все входные (обычные, запись и чтение кэша).
// Цены поменялись — правим здесь и выпускаем новую версию сервера.

const PRICES = [
  'fable'  => [8.0, 40.0, 10.0, 0.20],
  'opus'   => [4.0, 20.0, 5.0, 0.20],
  'sonnet' => [2.0, 10.0, 2.5, 0.10],
  'haiku'  => [0.10, 0.50, 0.125, 0.01],
  'haiku+' => [0.50, 2.50, 0.625, 0.05],
];
const PRICE_HAIKU_LONG = 100000;

function price_of(string $model, int $prompt): array {
  $m = strtolower($model);
  if (strpos($m, 'haiku') !== false) return PRICES[$prompt > PRICE_HAIKU_LONG ? 'haiku+' : 'haiku'];
  foreach (['fable', 'opus', 'sonnet'] as $f) if (strpos($m, $f) !== false) return PRICES[$f];
  return PRICES['opus'];   // незнакомая модель — по цене Opus, чтобы остаток не выглядел больше, чем есть
}

/* Стоимость одного ответа (или суммы ответов со средним размером запроса) в миллионных долях доллара. */
function cost_u(string $model, int $in, int $out, int $cw, int $cr, int $calls = 1): int {
  [$pi, $po, $pw, $pr] = price_of($model, intdiv($in + $cw + $cr, max(1, $calls)));
  return (int)round($in * $pi + $out * $po + $cw * $pw + $cr * $pr);
}

/* 0.3.3 · колонка cost_u у расхода и пересчёт того, что накопилось до неё (по среднему размеру запроса за день).
   Всё в одной записи базы: второй процесс дождётся и увидит, что колонка уже есть. */
function price_migrate(PDO $d): void {
  $has = function () use ($d) { return in_array('cost_u', array_column($d->query('PRAGMA table_info(usage)')->fetchAll(PDO::FETCH_ASSOC), 'name'), true); };
  if ($has()) return;
  $d->exec('BEGIN IMMEDIATE');
  try {
    if (!$has()) {
      $d->exec('ALTER TABLE usage ADD COLUMN cost_u INTEGER NOT NULL DEFAULT 0');
      $up = $d->prepare('UPDATE usage SET cost_u=? WHERE day=? AND key_id=? AND model=?');
      foreach ($d->query('SELECT * FROM usage')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $c = cost_u((string)$r['model'], (int)$r['tokens_in'], (int)$r['tokens_out'], (int)$r['cache_write'], (int)$r['cache_read'], (int)$r['calls']);
        if ($c > 0) $up->execute([$c, $r['day'], $r['key_id'], $r['model']]);
      }
    }
    $d->exec('COMMIT');
  } catch (Throwable $e) {
    $d->exec('ROLLBACK');
    if (stripos($e->getMessage(), 'duplicate column') === false) throw $e;   // другой процесс успел раньше
  }
}

/* Счёт (организация Claude) ключа: приходит в заголовке каждого ответа — anthropic-organization-id.
   Ключи с одного счёта тратят одни и те же деньги, поэтому остаток показывается по счёту. */
function key_org_seen(int $keyId, string $org): void {
  static $known = [];
  $org = strtolower(trim($org));
  if ($keyId <= 0 || !preg_match('~^[a-f0-9\-]{8,64}$~', $org) || ($known[$keyId] ?? '') === $org) return;
  try {
    db()->prepare("UPDATE keys SET org=? WHERE id=? AND org<>?")->execute([$org, $keyId, $org]);
    $known[$keyId] = $org;
  } catch (Throwable $e) { /* учёт денег не должен мешать ответу */ }
}
