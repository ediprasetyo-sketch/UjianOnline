#!/bin/bash
set -euo pipefail

ROOT="/volume1/web/ujian-online"
PHP="/usr/local/bin/php82"
REMOTE="origin"
BRANCH="main"
TS="$(date +%Y%m%d-%H%M%S)"
BACKUP="$ROOT/_backup_release_$TS"
STAGE="/tmp/ujian-online-release-$TS"
LOG="/tmp/ujian-online-release-$TS.log"

say(){ printf '\n[%s] %s\n' "$1" "$2" | tee -a "$LOG"; }
die(){
  printf '\n============================================\n FINAL RESULT : GAGAL\n============================================\n%s\n' "$1" | tee -a "$LOG"
  rm -rf "$STAGE"
  exit 1
}
trap 'rm -rf "$STAGE"' EXIT

[ -d "$ROOT/.git" ] || die "Production bukan checkout Git: $ROOT"
[ -x "$PHP" ] || die "PHP 8.2 tidak ditemukan: $PHP"
cd "$ROOT"

say "1/10" "BACKUP CONFIG + REVISION"
mkdir -p "$BACKUP"
cp -a config.php "$BACKUP/config.php" 2>/dev/null || true
cp -a config.local.php "$BACKUP/config.local.php" 2>/dev/null || true
cp -a VERSION.txt "$BACKUP/VERSION.txt" 2>/dev/null || true
git rev-parse HEAD > "$BACKUP/BEFORE_COMMIT.txt"
git status --short > "$BACKUP/BEFORE_STATUS.txt" || true
printf 'Backup: %s\n' "$BACKUP"

say "2/10" "FETCH GITHUB"
git fetch --prune "$REMOTE" "$BRANCH"
TARGET="$(git rev-parse "$REMOTE/$BRANCH")"
BEFORE="$(git rev-parse HEAD)"
printf 'BEFORE=%s\nTARGET=%s\n' "$BEFORE" "$TARGET" | tee -a "$LOG"

say "3/10" "VALIDASI SOURCE SEBELUM PRODUCTION DIUBAH"
rm -rf "$STAGE"
mkdir -p "$STAGE"
git archive "$TARGET" | tar -x -C "$STAGE"

for f in VERSION.txt update-manifest.json index.php login.php logout.php config.php admin/index.php admin/update.php admin/participants.php peserta/index.php peserta/access.php; do
  [ -f "$STAGE/$f" ] || die "FILE WAJIB TIDAK ADA DI RELEASE: $f"
done

if grep -RIn --include='*.php' '/volume1/web/ujian-online' "$STAGE" --exclude-dir=.git --exclude-dir=storage --exclude-dir=release --exclude-dir=releases > "$STAGE/path-audit.txt" 2>/dev/null; then
  cat "$STAGE/path-audit.txt"
  die "Release mengandung hard-coded /volume1/web/ujian-online."
fi

for f in "$STAGE"/*.php; do
  [ -f "$f" ] || continue
  if grep -nE 'require(_once)?[[:space:]]+__DIR__[[:space:]]*\.[[:space:]]*["'\''\.\./config\.php["'\'']' "$f" >/dev/null 2>&1; then
    die "Root entrypoint salah referensi config: $(basename "$f")"
  fi
done

VERSION="$(tr -d '\r\n' < "$STAGE/VERSION.txt")"
MANIFEST_VERSION="$("$PHP" -r '$d=json_decode(file_get_contents($argv[1]),true); echo is_array($d) ? (string)($d["version"] ?? "") : "";' "$STAGE/update-manifest.json")"
printf 'VERSION=%s MANIFEST=%s\n' "$VERSION" "$MANIFEST_VERSION" | tee -a "$LOG"
[ "$VERSION" = "$MANIFEST_VERSION" ] || die "VERSION.txt dan update-manifest.json tidak sinkron."

say "4/10" "PHP SYNTAX AUDIT RELEASE"
while IFS= read -r -d '' f; do
  "$PHP" -l "$f" >/dev/null || die "PHP syntax error: ${f#$STAGE/}"
done < <(find "$STAGE" -type f -name '*.php' ! -path "$STAGE/.git/*" ! -path "$STAGE/storage/*" ! -path "$STAGE/release/*" ! -path "$STAGE/releases/*" -print0)
echo "PHP syntax release: OK" | tee -a "$LOG"

say "5/10" "DEPLOY RELEASE + CONFIG LOCAL TETAP"
git reset --hard "$TARGET" >/dev/null
[ -f "$BACKUP/config.local.php" ] && cp -a "$BACKUP/config.local.php" "$ROOT/config.local.php" || true
chmod 755 "$ROOT" "$ROOT/admin" "$ROOT/peserta" "$ROOT/api" "$ROOT/includes" 2>/dev/null || true
find "$ROOT" -type f -name '*.php' -exec chmod 644 {} \; 2>/dev/null || true
find "$ROOT" -type f -name '*.txt' -exec chmod 644 {} \; 2>/dev/null || true
[ -f "$ROOT/config.local.php" ] && chmod 640 "$ROOT/config.local.php" 2>/dev/null || true

say "6/10" "FPM USER + RUNTIME CONFIG/DB"
FPM_USER="$(ps -eo user=,args= 2>/dev/null | awk '/php-fpm: pool/{print $1; exit}')"
[ -n "${FPM_USER:-}" ] || FPM_USER="http"
printf 'FPM_USER=%s\n' "$FPM_USER" | tee -a "$LOG"

TEST="$ROOT/.production_release_test.php"
cat > "$TEST" <<'PHP'
<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$pdo = db();
echo "CONFIG_OK\n";
echo "DB_NAME=" . (string)$pdo->query('SELECT DATABASE()')->fetchColumn() . "\n";
echo "VERSION=" . app_version() . "\n";
PHP
chmod 644 "$TEST"
if ! sudo -u "$FPM_USER" "$PHP" "$TEST" > "$ROOT/.production_release_test.out" 2>&1; then
  cat "$ROOT/.production_release_test.out"
  rm -f "$TEST" "$ROOT/.production_release_test.out"
  git reset --hard "$BEFORE" >/dev/null 2>&1 || true
  die "FPM tidak dapat memuat config/database. Production dikembalikan ke commit sebelumnya."
fi
cat "$ROOT/.production_release_test.out" | tee -a "$LOG"
rm -f "$TEST" "$ROOT/.production_release_test.out"

say "7/10" "RESTART PHP + NGINX"
sudo synosystemctl restart pkgctl-PHP8.2 >/tmp/ujian-release-php.out 2>&1 || { cat /tmp/ujian-release-php.out; git reset --hard "$BEFORE" >/dev/null 2>&1 || true; die "PHP-FPM gagal restart; release dibatalkan."; }
sleep 3
sudo synosystemctl restart nginx >/tmp/ujian-release-nginx.out 2>&1 || { cat /tmp/ujian-release-nginx.out; git reset --hard "$BEFORE" >/dev/null 2>&1 || true; die "Nginx gagal restart; release dibatalkan."; }
sleep 3

say "8/10" "HTTP SMOKE TEST"
FAIL=0
for u in / /login.php /admin/index.php /admin/participants.php /admin/update.php /peserta/index.php /peserta/access.php; do
  body="/tmp/ujian-http-$TS.html"
  meta="$(curl -sS --max-time 15 -o "$body" -w 'HTTP=%{http_code} SIZE=%{size_download}' "http://127.0.0.1$u" || true)"
  printf '%-30s %s\n' "$u" "$meta" | tee -a "$LOG"
  code="$(printf '%s' "$meta" | sed -n 's/.*HTTP=\([0-9][0-9][0-9]\).*/\1/p')"
  case "${code:-000}" in
    200|301|302|303|307|308) ;;
    *)
      FAIL=1
      echo "--- ERROR $u ---" | tee -a "$LOG"
      grep -E 'Fatal error:|Uncaught|Warning:|Permission denied|No such file' "$body" 2>/dev/null | head -20 | tee -a "$LOG" || true
      ;;
  esac
  rm -f "$body"
done

if [ "$FAIL" -ne 0 ]; then
  echo "--- NGINX ERROR LOG ---" | tee -a "$LOG"
  sudo tail -n 50 /var/log/nginx/error.log 2>/dev/null | tee -a "$LOG" || true
  git reset --hard "$BEFORE" >/dev/null 2>&1 || true
  sudo synosystemctl restart pkgctl-PHP8.2 >/dev/null 2>&1 || true
  sleep 2
  sudo synosystemctl restart nginx >/dev/null 2>&1 || true
  die "HTTP smoke test gagal. Production dikembalikan ke commit sebelum update."
fi

say "9/10" "FINAL FILE + PERMISSION CHECK"
[ -r "$ROOT/VERSION.txt" ] || die "VERSION.txt tidak readable."
[ -r "$ROOT/config.php" ] || die "config.php tidak readable."
[ -d "$ROOT/storage" ] && chmod 775 "$ROOT/storage" 2>/dev/null || true
[ -d "$ROOT/storage/backups" ] && chmod 775 "$ROOT/storage/backups" 2>/dev/null || true
[ -d "$ROOT/storage/update_uploads" ] && chmod 775 "$ROOT/storage/update_uploads" 2>/dev/null || true
[ -d "$ROOT/storage/update_staging" ] && chmod 775 "$ROOT/storage/update_staging" 2>/dev/null || true

say "10/10" "FINAL RESULT"
printf '============================================\n'
printf ' FINAL RESULT : BERHASIL\n'
printf '============================================\n'
printf 'VERSION=%s\n' "$VERSION"
printf 'COMMIT=%s\n' "$(git rev-parse --short HEAD)"
printf 'BACKUP=%s\n' "$BACKUP"
printf 'LOG=%s\n' "$LOG"
printf 'Source divalidasi sebelum deploy, config.local.php dipertahankan, PHP syntax OK, DB OK, permission diperbaiki, dan HTTP smoke test lolos.\n'
