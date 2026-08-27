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
command -v git >/dev/null || die "Git tidak ditemukan."
command -v curl >/dev/null || die "curl tidak ditemukan."
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

say "3/10" "AUDIT RELEASE SEBELUM DEPLOY"
rm -rf "$STAGE"
mkdir -p "$STAGE"
git archive "$TARGET" | tar -x -C "$STAGE"

for f in VERSION.txt update-manifest.json index.php login.php logout.php config.php health.php admin/index.php admin/update.php admin/participants.php peserta/index.php peserta/access.php; do
  [ -f "$STAGE/$f" ] || die "FILE WAJIB TIDAK ADA DI RELEASE: $f"
done

# The previous audit used a pattern that also matched the valid root form
# __DIR__ . '/config.php'. Only ../config.php is invalid for root entrypoints.
for f in "$STAGE"/*.php; do
  [ -f "$f" ] || continue
  if grep -nE "require(_once)?[[:space:]]*\(?[[:space:]]*__DIR__[[:space:]]*\.[[:space:]]*['\"]\.\./config\.php['\"]" "$f" >/tmp/ujian-root-config-error.$$ 2>/dev/null; then
    cat /tmp/ujian-root-config-error.$$ | tee -a "$LOG"
    rm -f /tmp/ujian-root-config-error.$$
    die "Root entrypoint salah referensi config: $(basename "$f")"
  fi
  rm -f /tmp/ujian-root-config-error.$$
done

# Deployment-specific absolute paths are forbidden in application PHP source.
# tools/ is excluded because deployment scripts legitimately know the server path.
DEPLOYMENT_PATH='/volume1/web/'"ujian-online"
PATH_HITS="$STAGE/path-audit.txt"
: > "$PATH_HITS"
while IFS= read -r -d '' f; do
  rel="${f#$STAGE/}"
  case "$rel" in tools/*|storage/*|release/*|releases/*) continue ;; esac
  if grep -nF "$DEPLOYMENT_PATH" "$f" >> "$PATH_HITS" 2>/dev/null; then
    printf 'FILE=%s\n' "$rel" >> "$PATH_HITS"
  fi
done < <(find "$STAGE" -type f -name '*.php' -print0)
if [ -s "$PATH_HITS" ]; then
  cat "$PATH_HITS" | tee -a "$LOG"
  die "Release mengandung hard-coded production path."
fi

VERSION="$(tr -d '\r\n' < "$STAGE/VERSION.txt")"
MANIFEST_VERSION="$("$PHP" -r '$d=json_decode(file_get_contents($argv[1]),true); echo is_array($d) ? (string)($d["version"] ?? "") : "";' "$STAGE/update-manifest.json")"
printf 'VERSION=%s MANIFEST=%s\n' "$VERSION" "$MANIFEST_VERSION" | tee -a "$LOG"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || die "VERSION.txt tidak valid."
[ "$VERSION" = "$MANIFEST_VERSION" ] || die "VERSION.txt dan update-manifest.json tidak sinkron."

say "4/10" "PHP SYNTAX AUDIT SELURUH RELEASE"
PHP_COUNT=0
while IFS= read -r -d '' f; do
  PHP_COUNT=$((PHP_COUNT+1))
  if ! "$PHP" -l "$f" >/tmp/ujian-php-lint.$$ 2>&1; then
    cat /tmp/ujian-php-lint.$$ | tee -a "$LOG"
    rm -f /tmp/ujian-php-lint.$$
    die "PHP syntax error: ${f#$STAGE/}"
  fi
done < <(find "$STAGE" -type f -name '*.php' ! -path "$STAGE/tools/*" ! -path "$STAGE/storage/*" ! -path "$STAGE/release/*" ! -path "$STAGE/releases/*" -print0)
rm -f /tmp/ujian-php-lint.$$
printf 'PHP files checked: %s\n' "$PHP_COUNT" | tee -a "$LOG"

audit_readable(){
  local f="$1"
  [ -r "$f" ] || die "File tidak readable: ${f#$ROOT/}"
}

say "5/10" "DEPLOY RELEASE + CONFIG LOCAL TETAP"
# Save the production-local config outside Git, deploy the exact Git target,
# then restore the local secret file. No manual source edits are performed.
[ -f "$BACKUP/config.local.php" ] && true || die "config.local.php production tidak ditemukan."
git reset --hard "$TARGET" >/dev/null
cp -a "$BACKUP/config.local.php" "$ROOT/config.local.php"

# Normalize permissions so Nginx/PHP-FPM can read application files while
# keeping config.local.php restricted.
find "$ROOT" -type f -name '*.php' -exec chmod 644 {} + 2>/dev/null || true
find "$ROOT" -type f -name '*.txt' -exec chmod 644 {} + 2>/dev/null || true
[ -f "$ROOT/config.local.php" ] && chmod 640 "$ROOT/config.local.php" 2>/dev/null || true
chmod 755 "$ROOT" "$ROOT/admin" "$ROOT/peserta" "$ROOT/api" "$ROOT/includes" "$ROOT/config" "$ROOT/tools" 2>/dev/null || true

for f in VERSION.txt config.php health.php index.php login.php admin/index.php admin/update.php admin/participants.php peserta/index.php peserta/access.php; do
  audit_readable "$ROOT/$f"
done

say "6/10" "RUNTIME CONFIG + DATABASE CHECK"
FPM_USER="$(ps -eo user=,args= 2>/dev/null | awk '/php-fpm: pool/{print $1; exit}')"
[ -n "${FPM_USER:-}" ] || FPM_USER="http"
printf 'FPM_USER=%s\n' "$FPM_USER" | tee -a "$LOG"

TEST="$ROOT/.production_release_test.php"
cat > "$TEST" <<'PHP'
<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$pdo = db();
if ((string)$pdo->query('SELECT DATABASE()')->fetchColumn() === '') {
    throw new RuntimeException('Database aktif tidak terdeteksi.');
}
echo "CONFIG_OK\n";
echo "DB_NAME=" . (string)$pdo->query('SELECT DATABASE()')->fetchColumn() . "\n";
echo "VERSION=" . app_version() . "\n";
PHP
chmod 644 "$TEST"
if ! sudo -u "$FPM_USER" "$PHP" "$TEST" > "$ROOT/.production_release_test.out" 2>&1; then
  cat "$ROOT/.production_release_test.out" | tee -a "$LOG"
  rm -f "$TEST" "$ROOT/.production_release_test.out"
  git reset --hard "$BEFORE" >/dev/null 2>&1 || true
  [ -f "$BACKUP/config.local.php" ] && cp -a "$BACKUP/config.local.php" "$ROOT/config.local.php" || true
  die "FPM/config/database preflight gagal. Production dikembalikan ke commit sebelumnya."
fi
cat "$ROOT/.production_release_test.out" | tee -a "$LOG"
rm -f "$TEST" "$ROOT/.production_release_test.out"

say "7/10" "RESTART PHP + NGINX"
sudo synosystemctl restart pkgctl-PHP8.2 >/tmp/ujian-release-php.out 2>&1 || {
  cat /tmp/ujian-release-php.out | tee -a "$LOG"
  git reset --hard "$BEFORE" >/dev/null 2>&1 || true
  [ -f "$BACKUP/config.local.php" ] && cp -a "$BACKUP/config.local.php" "$ROOT/config.local.php" || true
  die "PHP-FPM gagal restart; release dibatalkan."
}
sleep 3
sudo synosystemctl restart nginx >/tmp/ujian-release-nginx.out 2>&1 || {
  cat /tmp/ujian-release-nginx.out | tee -a "$LOG"
  git reset --hard "$BEFORE" >/dev/null 2>&1 || true
  [ -f "$BACKUP/config.local.php" ] && cp -a "$BACKUP/config.local.php" "$ROOT/config.local.php" || true
  die "Nginx gagal restart; release dibatalkan."
}
sleep 3

say "8/10" "HTTP SMOKE TEST"
FAIL=0
for u in / /health.php /login.php /admin/index.php /admin/participants.php /admin/update.php /peserta/index.php /peserta/access.php; do
  body="/tmp/ujian-http-$TS.html"
  meta="$(curl -sS --max-time 15 -o "$body" -w 'HTTP=%{http_code} SIZE=%{size_download}' "http://127.0.0.1$u" || true)"
  printf '%-32s %s\n' "$u" "$meta" | tee -a "$LOG"
  code="$(printf '%s' "$meta" | sed -n 's/.*HTTP=\([0-9][0-9][0-9]\).*/\1/p')"
  case "${code:-000}" in
    200|301|302|303|307|308) ;;
    *)
      FAIL=1
      echo "--- ERROR $u ---" | tee -a "$LOG"
      grep -E 'Fatal error:|Uncaught|Warning:|Permission denied|No such file|failed to open' "$body" 2>/dev/null | head -30 | tee -a "$LOG" || true
      ;;
  esac
  rm -f "$body"
done

if [ "$FAIL" -ne 0 ]; then
  echo "--- NGINX ERROR LOG ---" | tee -a "$LOG"
  sudo tail -n 80 /var/log/nginx/error.log 2>/dev/null | tee -a "$LOG" || true
  git reset --hard "$BEFORE" >/dev/null 2>&1 || true
  [ -f "$BACKUP/config.local.php" ] && cp -a "$BACKUP/config.local.php" "$ROOT/config.local.php" || true
  sudo synosystemctl restart pkgctl-PHP8.2 >/dev/null 2>&1 || true
  sleep 2
  sudo synosystemctl restart nginx >/dev/null 2>&1 || true
  die "HTTP smoke test gagal. Production dikembalikan ke commit sebelum update."
fi

say "9/10" "FINAL PERMISSION + FILE INTEGRITY CHECK"
for f in VERSION.txt config.php health.php index.php login.php admin/index.php admin/update.php admin/participants.php peserta/index.php peserta/access.php; do
  audit_readable "$ROOT/$f"
done
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
printf 'Audit source + syntax + config path + DB + permissions + HTTP smoke test seluruhnya lolos.\n'
