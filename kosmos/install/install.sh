#!/bin/bash
# ==== ИТК · КОСМОС · install/install.sh ====
# Установка и обновление «Космоса» на сервере Ubuntu 24.04 (Aliyun, Сингапур).
# Запускается одноразовой командой из Экосистемы (api/kosmos.php?a=install) или агентом при обновлении:
#   bash install.sh <версия> <подпись> <sha256 пакета>
# Повторный запуск безопасен: база, ключи и настройки не трогаются.
set -euo pipefail
VER="${1:?версия}"; SIG="${2:?подпись}"; SHA="${3:?sha256}"
SRC="$(cd "$(dirname "$0")/.." && pwd)"          # распакованный пакет: lib, www, bin, install
APP=/opt/kosmos; ETC=/etc/kosmos; DATA=$APP/data
say(){ echo "== Космос: $*"; }
[ "$(id -u)" = 0 ] || { echo 'Запустите через sudo'; exit 1; }
umask 022

FIRST=0; [ -f "$APP/VERSION" ] || FIRST=1

if [ "$FIRST" = 1 ] || ! command -v php-fpm8.3 >/dev/null || ! command -v nginx >/dev/null; then
  say "ставлю nginx и PHP (2–4 минуты)"
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -qq
  apt-get install -y -qq nginx php8.3-fpm php8.3-cli php8.3-sqlite3 php8.3-curl php8.3-mbstring certbot python3-certbot-nginx >/dev/null
fi

say "проверяю подпись пакета"
php -r '
  $sig = base64_decode($argv[1], true); $ok = false;   // 0.3.7 · в sign.pub может быть несколько ключей (ноутбук и сам сервер)
  $msg = "kosmos\n" . $argv[2] . "\n" . $argv[3];
  foreach (preg_split("~\\s+~", trim((string)@file_get_contents("/etc/kosmos/sign.pub"))) as $l) { $pub = base64_decode($l, true); if ($pub && strlen($pub) === 32 && $sig && sodium_crypto_sign_verify_detached($sig, $msg, $pub)) $ok = true; }
  if (!$ok) { fwrite(STDERR, "Подпись пакета не сходится — установка остановлена\n"); exit(1); }
' "$SIG" "$VER" "$SHA"

say "раскладываю файлы версии $VER"
mkdir -p "$APP" "$DATA" "$ETC" /var/backups/kosmos /var/log/kosmos
chmod 711 "$ETC"; chmod 700 /var/backups/kosmos
for d in lib www bin install; do
  rm -rf "$APP/$d.new"; cp -a "$SRC/$d" "$APP/$d.new"
  rm -rf "$APP/$d.old"; [ -d "$APP/$d" ] && mv "$APP/$d" "$APP/$d.old"
  mv "$APP/$d.new" "$APP/$d"
done
chown -R root:root "$APP/lib" "$APP/www" "$APP/bin" "$APP/install"
chmod 755 "$APP"; chmod -R u=rwX,go=rX "$APP/lib" "$APP/www" "$APP/bin" "$APP/install"
chown www-data:www-data "$DATA"; chmod 750 "$DATA"
chown root:www-data /var/log/kosmos; chmod 770 /var/log/kosmos

[ -f "$ETC/master.key" ] || { head -c 48 /dev/urandom > "$ETC/master.key"; say "создан ключ шифрования базы (/etc/kosmos/master.key)"; }
[ -f "$ETC/config.php" ] || cat > "$ETC/config.php" <<'C'
<?php
// Настройки «Космоса» на этом сервере. Ключи Claude здесь не хранятся — они в базе, зашифрованы.
return [
  'store_url' => 'https://itcpartner.store',
  'db'        => '/opt/kosmos/data/kosmos.sqlite',
  'tz'        => 'Asia/Bishkek',
];
C
for f in master.key config.php store_token gw.key; do [ -f "$ETC/$f" ] && { chown root:www-data "$ETC/$f"; chmod 640 "$ETC/$f"; }; done
[ -f "$ETC/sign.pub" ] && chmod 644 "$ETC/sign.pub"

say "настраиваю PHP, nginx и службы"
install -m 644 "$APP/install/fpm-kosmos.conf" /etc/php/8.3/fpm/pool.d/kosmos.conf
if [ -f /etc/nginx/sites-available/kosmos ] && grep -q 'managed by Certbot' /etc/nginx/sites-available/kosmos; then
  : # сертификат уже вписан certbot — файл сайта не трогаем
else
  install -m 644 "$APP/install/nginx-kosmos.conf" /etc/nginx/sites-available/kosmos
fi
# 0.5.0 (№624): в файле сайта, который уже правил certbot, поднимаем предел тела запроса (было 2m — снимки к Claude и файлы не проходили)
sed -i -E 's/client_max_body_size[[:space:]]+[0-9]+[kKmM]?;/client_max_body_size 64m;/' /etc/nginx/sites-available/kosmos 2>/dev/null || true
ln -sf /etc/nginx/sites-available/kosmos /etc/nginx/sites-enabled/kosmos
rm -f /etc/nginx/sites-enabled/default
for u in kosmos-worker.service kosmos-agent.service kosmos-agent.timer; do install -m 644 "$APP/install/$u" "/etc/systemd/system/$u"; done
printf '%s\n' "$VER" > "$APP/VERSION"

if [ "$FIRST" = 1 ] && [ ! -f "$DATA/kosmos.sqlite" ]; then
  php "$APP/bin/agent.php" restore || true          # до запуска служб: если сервер заменяют — поднимаем память из ночной копии в магазине
fi

if [ -d /run/systemd/system ]; then
  systemctl daemon-reload
  systemctl enable -q php8.3-fpm nginx
  systemctl restart php8.3-fpm
  nginx -t -q && systemctl reload-or-restart nginx
  systemctl enable -q --now kosmos-worker.service kosmos-agent.timer
  systemctl restart kosmos-worker.service
fi

php "$APP/bin/agent.php" || true                  # сразу сообщить магазину, что сервер на связи

IP=$(php "$APP/bin/agent.php" ip 2>/dev/null || true)
say "готово, версия $VER"
if [ "$FIRST" = 1 ]; then
  echo
  echo "IP этого сервера: ${IP:-не определился}"
  echo "Дальше: в reg.ru добавить DNS-запись A: kosmos → ${IP:-<IP сервера>} (домен itcpartner.store)."
  echo "Сертификат https сервер получит сам в течение 10 минут после того, как запись заработает."
  echo "Ход видно на странице «Космоса» в Экосистеме."
fi
