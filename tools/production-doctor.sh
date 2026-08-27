#!/bin/sh
set -eu
ROOT="/volume1/web/ujian-online"
PHP="/usr/local/bin/php82"
TS="$(date +%Y%m%d-%H%M%S)"
BACKUP="$ROOT/_backup_doctor_$TS"
mkdir -p "$BACKUP"

say(){ printf '\n[%s] %s\n' "$1" "$2"; }
fail(){ printf '\nFINAL RESULT : MASIH ERROR\n\n%s\n' "$1"; exit 1; }

cd "$ROOT"

say 1 "BACKUP + IDENTITAS"
cp -a config.php "$BACKUP/config.php" 2>/dev/null || true
[ -f config.local.php ] && cp -a config.local.php "$BACKUP/config.local.php" || true
printf 'ROOT=%s\n' "$ROOT"
printf 'VERSION='; cat VERSION.txt 2>/dev/null || printf 'unknown\n'
printf 'GIT='; git rev-parse --short HEAD 2>/dev/null || printf 'no-git\n'

say 2 "DETECT PHP-FPM USER"
FPM_USER="$(ps -eo user=,group=,args= 2>/dev/null | awk '/php-fpm: pool/{print $1; exit}')"
FPM_GROUP="$(ps -eo user=,group=,args= 2>/dev/null | awk '/php-fpm: pool/{print $2; exit}')"
if [ -z "${FPM_USER:-}" ]; then FPM_USER="http"; fi
if [ -z "${FPM_GROUP:-}" ]; then FPM_GROUP="http"; fi
printf 'FPM_USER=%s FPM_GROUP=%s\n' "$FPM_USER" "$FPM_GROUP"

say 3 "FIX RUNTIME PATH + PERMISSION"
if command -v grep >/dev/null 2>&1; then
  grep -RIl --include='*.php' '/volume1/web/ujian-online' . 2>/dev/null | while IFS= read -r f; do
    case "$f" in
      ./_backup_*/*) continue ;;
      *)
        sed -i "s#/volume1/web/ujian-online/\./config\\.php#__DIR__.'/config.php'#g; s#/volume1/web/ujian-online/config\\.php#__DIR__.'/config.php'#g" "$f"
        ;;
    esac
  done
fi

chmod 755 "$ROOT" "$ROOT/admin" "$ROOT/peserta" "$ROOT/includes" "$ROOT/api" 2>/dev/null || true
chmod 644 "$ROOT"/*.php "$ROOT"/*.txt 2>/dev/null || true
[ -f "$ROOT/config.local.php" ] && chmod 644 "$ROOT/config.local.php"
find "$ROOT/admin" "$ROOT/peserta" "$ROOT/api" "$ROOT/includes" -type f -name '*.php' -exec chmod 644 {} \; 2>/dev/null || true

rm -f "$ROOT/php_check.php" "$ROOT/_db_final_test.php" "$ROOT/_fix.php" 2>/dev/null || true

say 4 "SYNTAX AUDIT"
find "$ROOT" -type f -name '*.php' ! -path "$ROOT/_backup_*/*" -exec "$PHP" -l {} \; >/tmp/doctor-lint.out 2>&1 || { cat /tmp/doctor-lint.out; fail 'Ada PHP syntax error.'; }
echo 'PHP syntax: OK'

say 5 "TEST CONFIG SEBAGAI PHP-FPM"
TEST="$ROOT/.doctor_runtime.php"
cat > "$TEST" <<'PHP'
<?php
declare(strict_types=1);
require __DIR__.'/config.php';
$pdo=db();
echo "CONFIG_OK\n";
echo "DB_NAME=".$pdo->query('SELECT DATABASE()')->fetchColumn()."\n";
PHP
chmod 644 "$TEST"
if ! sudo -u "$FPM_USER" "$PHP" "$TEST" >/tmp/doctor-runtime.out 2>&1; then
  cat /tmp/doctor-runtime.out
  rm -f "$TEST"
  fail "PHP-FPM user tidak dapat memuat config/database. Kemungkinan permission config.local.php atau environment DB."
fi
cat /tmp/doctor-runtime.out
rm -f "$TEST"

say 6 "RESTART PHP-FPM + NGINX"
sudo synosystemctl restart pkgctl-PHP8.2 >/tmp/doctor-php-restart.out 2>&1 || { cat /tmp/doctor-php-restart.out; fail 'PHP-FPM gagal restart'; }
sleep 3
sudo synosystemctl restart nginx >/tmp/doctor-nginx-restart.out 2>&1 || { cat /tmp/doctor-nginx-restart.out; fail 'Nginx gagal restart'; }
sleep 3
cat /tmp/doctor-php-restart.out /tmp/doctor-nginx-restart.out

say 7 "FINAL HTTP + ERROR BODY"
URLS='/ /login.php /admin/index.php /admin/participants.php /admin/update.php /peserta/index.php /peserta/access.php'
FAIL=0
for u in $URLS; do
  body="/tmp/doctor-body.html"
  meta="$(curl -sS --max-time 15 -o "$body" -w 'HTTP=%{http_code} SIZE=%{size_download}' "http://127.0.0.1$u" || true)"
  printf '%-30s %s\n' "$u" "$meta"
  code="$(printf '%s' "$meta" | sed -n 's/.*HTTP=\([0-9][0-9][0-9]\).*/\1/p')"
  if [ "${code:-000}" != 200 ]; then
    FAIL=1
    echo "--- RESPONSE ERROR: $u ---"
    grep -Eo '(<title>[^<]*|PHP (Fatal error|Warning|Notice)|Fatal error:|Uncaught [^<]*)' "$body" 2>/dev/null | head -20 || true
    echo "--- NGINX ERROR LOG ---"
    sudo tail -n 30 /var/log/nginx/error.log 2>/dev/null || true
  fi
done

say 8 "STATIC PATH AUDIT"
if grep -RInE --include='*.php' "(/volume1/web/ujian-online|require[[:space:]]*['\"]?/volume1|include[[:space:]]*['\"]?/volume1)" . ! -path './_backup_*/*' 2>/dev/null; then
  fail "Masih ditemukan hard-coded runtime path."
fi

if [ "$FAIL" -ne 0 ]; then
  echo "Backup: $BACKUP"
  fail "HTTP masih mengembalikan 500. Lihat ERROR BODY di atas; script sengaja tidak menyatakan berhasil."
fi

echo
echo '============================================'
echo ' FINAL RESULT : BERHASIL'
echo '============================================'
printf 'Backup: %s\n' "$BACKUP"
echo 'Semua smoke test HTTP = 200, config dapat dibaca user PHP-FPM, DB OK, syntax OK.'
