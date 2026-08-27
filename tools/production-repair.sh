#!/bin/sh
set -eu

ROOT="/volume1/web/ujian-online"
PHP="/usr/local/bin/php82"
REMOTE="origin"
BRANCH="main"
TS="$(date +%Y%m%d-%H%M%S)"
BACKUP="$ROOT/_backup_repair_$TS"

say(){ printf '\n[%s] %s\n' "$1" "$2"; }
fail(){ printf '\n============================================\n FINAL RESULT : MASIH ERROR\n============================================\n%s\n' "$1"; exit 1; }

[ -d "$ROOT/.git" ] || fail "Folder production bukan checkout Git: $ROOT"
cd "$ROOT"

say 1 "BACKUP CONFIG + CURRENT REVISION"
mkdir -p "$BACKUP"
cp -a config.php "$BACKUP/config.php" 2>/dev/null || true
cp -a VERSION.txt "$BACKUP/VERSION.txt" 2>/dev/null || true
[ -f config.local.php ] && cp -a config.local.php "$BACKUP/config.local.php" || true
git rev-parse HEAD > "$BACKUP/BEFORE_COMMIT.txt" 2>/dev/null || true
printf 'Backup: %s\n' "$BACKUP"

say 2 "SYNC SOURCE DARI GITHUB"
git fetch "$REMOTE" "$BRANCH"
TARGET="$(git rev-parse "$REMOTE/$BRANCH")"
printf 'TARGET=%s\n' "$TARGET"
git reset --hard "$TARGET"

say 3 "CHECK FILE WAJIB"
for f in config.php index.php login.php logout.php VERSION.txt update-manifest.json admin/index.php admin/update.php admin/participants.php peserta/index.php peserta/access.php; do
  [ -f "$ROOT/$f" ] || fail "FILE WAJIB TIDAK ADA: $f"
done

say 4 "PERMISSION + PATH GUARD"
FPM_USER="$(ps -eo user=,args= 2>/dev/null | awk '/php-fpm: pool/{print $1; exit}')"
FPM_GROUP="$(ps -eo group=,args= 2>/dev/null | awk '/php-fpm: pool/{print $1; exit}')"
[ -n "${FPM_USER:-}" ] || FPM_USER=http
[ -n "${FPM_GROUP:-}" ] || FPM_GROUP=http
printf 'FPM_USER=%s FPM_GROUP=%s\n' "$FPM_USER" "$FPM_GROUP"
chmod 755 "$ROOT" "$ROOT/admin" "$ROOT/peserta" "$ROOT/api" "$ROOT/includes" 2>/dev/null || true
chmod 644 "$ROOT"/*.php "$ROOT"/*.txt 2>/dev/null || true
find "$ROOT/admin" "$ROOT/peserta" "$ROOT/api" "$ROOT/includes" -type f -name '*.php' -exec chmod 644 {} \; 2>/dev/null || true
[ -f "$ROOT/config.local.php" ] && { chgrp "$FPM_GROUP" "$ROOT/config.local.php" 2>/dev/null || true; chmod 640 "$ROOT/config.local.php"; }

if grep -RInE --include='*.php' '(/volume1/web/ujian-online|require[[:space:]]*["'"']?/volume1|include[[:space:]]*["'"']?/volume1)' "$ROOT" \
  --exclude-dir=.git --exclude-dir=storage --exclude-dir=release --exclude-dir=releases 2>/dev/null; then
  fail "Masih ditemukan hard-coded production filesystem path."
fi

say 5 "PHP SYNTAX AUDIT"
if ! find "$ROOT" -type f -name '*.php' ! -path "$ROOT/.git/*" ! -path "$ROOT/_backup_*/*" \
  ! -path "$ROOT/storage/*" ! -path "$ROOT/release/*" ! -path "$ROOT/releases/*" \
  -exec "$PHP" -l {} \; >/tmp/ujian-repair-lint.out 2>&1; then
  cat /tmp/ujian-repair-lint.out
  fail "PHP syntax error ditemukan."
fi
echo 'PHP syntax: OK'

say 6 "CONFIG + DATABASE TEST SEBAGAI PHP-FPM"
TEST="$ROOT/.production_repair_runtime.php"
cat > "$TEST" <<'PHP'
<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$pdo = db();
echo "CONFIG_OK\n";
echo "DB_NAME=" . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n";
PHP
chmod 644 "$TEST"
if ! sudo -u "$FPM_USER" "$PHP" "$TEST" >/tmp/ujian-repair-db.out 2>&1; then
  cat /tmp/ujian-repair-db.out
  rm -f "$TEST"
  fail "PHP-FPM user tidak dapat memuat config/database."
fi
cat /tmp/ujian-repair-db.out
rm -f "$TEST"

say 7 "RESTART PHP + NGINX"
sudo synosystemctl restart pkgctl-PHP8.2 >/tmp/ujian-repair-php.out 2>&1 || { cat /tmp/ujian-repair-php.out; fail 'PHP-FPM gagal restart'; }
sleep 3
sudo synosystemctl restart nginx >/tmp/ujian-repair-nginx.out 2>&1 || { cat /tmp/ujian-repair-nginx.out; fail 'Nginx gagal restart'; }
sleep 3
cat /tmp/ujian-repair-php.out /tmp/ujian-repair-nginx.out

say 8 "FINAL HTTP TEST"
URLS='/ /login.php /admin/index.php /admin/participants.php /admin/update.php /peserta/index.php /peserta/access.php'
FAIL=0
for u in $URLS; do
  body="/tmp/ujian-http-body.html"
  meta="$(curl -sS --max-time 15 -o "$body" -w 'HTTP=%{http_code} SIZE=%{size_download}' "http://127.0.0.1$u" || true)"
  printf '%-30s %s\n' "$u" "$meta"
  code="$(printf '%s' "$meta" | sed -n 's/.*HTTP=\([0-9][0-9][0-9]\).*/\1/p')"
  if [ "${code:-000}" != 200 ]; then
    FAIL=1
    echo "--- ERROR BODY: $u ---"
    grep -Eo '(<title>[^<]*|PHP (Fatal error|Warning|Notice)|Fatal error:|Uncaught [^<]*)' "$body" 2>/dev/null | head -20 || true
  fi
done

if [ "$FAIL" -ne 0 ]; then
  echo '--- NGINX ERROR LOG ---'
  sudo tail -n 40 /var/log/nginx/error.log 2>/dev/null || true
  fail "HTTP masih mengembalikan 500. Source sudah disinkronkan, tetapi runtime masih bermasalah."
fi

say 9 "FINAL RESULT"
printf '%s\n' '============================================'
printf '%s\n' ' FINAL RESULT : BERHASIL'
printf '%s\n' '============================================'
printf 'VERSION='; cat "$ROOT/VERSION.txt"
printf 'COMMIT='; git rev-parse --short HEAD
printf 'Backup=%s\n' "$BACKUP"
echo 'Semua file wajib ada, path bersih, syntax OK, DB OK, PHP-FPM/Nginx OK, dan smoke test HTTP = 200.'
