#!/bin/bash
set -Eeuo pipefail

# One-command production deployment/repair for Synology.
# Safety rule: NOTHING is considered successful until the real public URL,
# PHP runtime, database, permissions and active exam link all pass smoke tests.
ROOT="${ROOT:-$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd -P)}"
PHP="${PHP_BIN:-/usr/local/bin/php82}"
REMOTE="origin"
BRANCH="main"
TS="$(date +%Y%m%d-%H%M%S)"
BACKUP="$ROOT/_backup_release_$TS"
STAGE="/tmp/ujian-online-release-$TS"
LOG="/tmp/ujian-online-release-$TS.log"
LOCK="$ROOT/.production-release.lock"

say(){ printf '\n[%s] %s\n' "$1" "$2" | tee -a "$LOG"; }
die(){ printf '\n============================================\n FINAL RESULT : GAGAL\n============================================\n%s\nLOG=%s\n' "$1" "$LOG" | tee -a "$LOG"; exit 1; }

rollback(){
  local rc=$?
  set +e
  if [ -n "${BEFORE:-}" ] && [ -d "$ROOT/.git" ]; then
    git reset --hard "$BEFORE" >/dev/null 2>&1 || true
  fi
  if [ -f "$BACKUP/config.local.php" ]; then cp -p "$BACKUP/config.local.php" "$ROOT/config.local.php" || true; fi
  rm -f "$ROOT/.production_release_test.php" "$ROOT/.production_release_test.out"
  chmod 644 "$ROOT/config.php" "$ROOT/VERSION.txt" "$ROOT/health.php" 2>/dev/null || true
  [ -f "$ROOT/config.local.php" ] && chmod 640 "$ROOT/config.local.php" 2>/dev/null || true
  sudo synosystemctl restart pkgctl-PHP8.2 >/dev/null 2>&1 || true
  sleep 2
  sudo synosystemctl restart nginx >/dev/null 2>&1 || true
  sleep 2
  return $rc
}

cleanup(){ rm -rf "$STAGE"; rm -f "$LOCK"; }
trap cleanup EXIT
trap 'rollback; exit 1' ERR

[ ! -e "$LOCK" ] || die "Deployment lain sedang berjalan: $LOCK"
printf '%s\n' "$$" > "$LOCK"
[ -d "$ROOT/.git" ] || die "Direktori ini bukan checkout Git: $ROOT"
[ -x "$PHP" ] || die "PHP 8.2 tidak ditemukan: $PHP"
command -v git >/dev/null || die "Git tidak ditemukan."
command -v curl >/dev/null || die "curl tidak ditemukan."
command -v sudo >/dev/null || die "sudo tidak ditemukan."
cd "$ROOT"

say "1/10" "BACKUP CONFIG + REVISION"
mkdir -p "$BACKUP"
[ -f config.local.php ] && cp -p config.local.php "$BACKUP/config.local.php"
[ -f config.php ] && cp -p config.php "$BACKUP/config.php"
[ -f VERSION.txt ] && cp -p VERSION.txt "$BACKUP/VERSION.txt"
git rev-parse HEAD > "$BACKUP/BEFORE_COMMIT.txt"
git status --short > "$BACKUP/BEFORE_STATUS.txt" || true
BEFORE="$(git rev-parse HEAD)"
printf 'ROOT=%s\nBEFORE=%s\nBACKUP=%s\n' "$ROOT" "$BEFORE" "$BACKUP" | tee -a "$LOG"

say "2/10" "FETCH GITHUB"
git fetch --prune "$REMOTE" "$BRANCH"
TARGET="$(git rev-parse "$REMOTE/$BRANCH")"
printf 'TARGET=%s\n' "$TARGET" | tee -a "$LOG"

say "3/10" "AUDIT RELEASE SEBELUM DEPLOY"
rm -rf "$STAGE" && mkdir -p "$STAGE"
git archive "$TARGET" | tar -x -C "$STAGE"

REQUIRED=(VERSION.txt update-manifest.json index.php config.php health.php login.php admin/index.php admin/participants.php admin/update.php peserta/index.php peserta/access.php peserta/verify.php peserta/finish.php)
for f in "${REQUIRED[@]}"; do [ -r "$STAGE/$f" ] || die "FILE WAJIB TIDAK ADA/TIDAK TERBACA: $f"; done

VERSION="$(tr -d '\r\n' < "$STAGE/VERSION.txt")"
MANIFEST_VERSION="$($PHP -r '$d=json_decode(file_get_contents($argv[1]),true); echo is_array($d) ? (string)($d["version"] ?? "") : "";' "$STAGE/update-manifest.json")"
printf 'VERSION=%s MANIFEST=%s\n' "$VERSION" "$MANIFEST_VERSION" | tee -a "$LOG"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || die "VERSION.txt tidak valid."
[ "$VERSION" = "$MANIFEST_VERSION" ] || die "VERSION.txt dan update-manifest.json tidak sinkron."

# Never allow a release to depend on a NAS-specific absolute filesystem path.
if grep -RInE --include='*.php' --exclude-dir=tools --exclude-dir=storage '/volume1/web/ujian-online' "$STAGE" >/tmp/ujian-hardpath.$$.txt 2>/dev/null; then
  cat /tmp/ujian-hardpath.$$.txt | tee -a "$LOG"
  rm -f /tmp/ujian-hardpath.$$.txt
  die "Release mengandung hard-coded production filesystem path."
fi
rm -f /tmp/ujian-hardpath.$$.txt

# config.php is the ONLY owner of session initialization.
# Any other PHP file calling session_start() is a release blocker; allowing it
# creates the exact warnings seen in production when config.php is loaded later.
while IFS= read -r -d '' f; do
  rel="${f#$STAGE/}"
  session_line="$(grep -nE '(^|[^[:alnum:]_])session_start[[:space:]]*\(' "$f" | head -1 | cut -d: -f1 || true)"
  [ "$rel" = "config.php" ] && continue
  if [ -n "$session_line" ]; then
    die "session_start() hanya boleh berada di config.php: $rel:$session_line"
  fi
done < <(find "$STAGE" -type f -name '*.php' ! -path "$STAGE/tools/*" -print0)

# config.php itself must own session_start().
grep -qE 'session_start[[:space:]]*\(' "$STAGE/config.php" || die "config.php tidak memiliki session_start()."

say "4/10" "PHP SYNTAX AUDIT SELURUH RELEASE"
PHP_COUNT=0
while IFS= read -r -d '' f; do
  PHP_COUNT=$((PHP_COUNT+1))
  if ! "$PHP" -l "$f" >/tmp/ujian-php-lint.$$ 2>&1; then
    cat /tmp/ujian-php-lint.$$ | tee -a "$LOG"
    rm -f /tmp/ujian-php-lint.$$
    die "PHP syntax error: ${f#$STAGE/}"
  fi
done < <(find "$STAGE" -type f -name '*.php' ! -path "$STAGE/tools/*" -print0)
rm -f /tmp/ujian-php-lint.$$
printf 'PHP files checked: %s\n' "$PHP_COUNT" | tee -a "$LOG"

say "5/10" "DEPLOY EXACT COMMIT + PRESERVE LOCAL CONFIG"
git reset --hard "$TARGET" >/dev/null
if [ -f "$BACKUP/config.local.php" ]; then
  cp -p "$BACKUP/config.local.php" "$ROOT/config.local.php"
else
  printf '%s\n' 'config.local.php tidak ada; aplikasi harus menggunakan DB_* environment variables.' | tee -a "$LOG"
fi

# Public source is readable; writable application directories keep write access.
find "$ROOT" -type d ! -path "$ROOT/.git*" -exec chmod 755 {} + 2>/dev/null || true
find "$ROOT" -type f -name '*.php' -exec chmod 644 {} + 2>/dev/null || true
find "$ROOT" -type f -name '*.txt' -exec chmod 644 {} + 2>/dev/null || true
[ -f "$ROOT/config.local.php" ] && chmod 640 "$ROOT/config.local.php"
for d in storage storage/backups storage/update_uploads storage/update_staging; do
  [ -d "$ROOT/$d" ] && chmod 775 "$ROOT/$d"
done

for f in "${REQUIRED[@]}"; do [ -r "$ROOT/$f" ] || die "File production tidak readable setelah deploy: $f"; done

say "6/10" "LOCAL PHP PREFLIGHT + DATABASE"
TEST="$ROOT/.production_release_test.php"
cat > "$TEST" <<'PHP'
<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$pdo = db();
$pdo->query('SELECT 1')->fetchColumn();
echo "CONFIG_OK\n";
echo "DB_OK\n";
echo "VERSION=" . app_version() . "\n";
PHP
chmod 644 "$TEST"
if ! "$PHP" "$TEST" > "$ROOT/.production_release_test.out" 2>&1; then
  cat "$ROOT/.production_release_test.out" | tee -a "$LOG"
  rollback
  trap - ERR
  die "Local config/database preflight gagal. Production dikembalikan ke commit sebelumnya."
fi
cat "$ROOT/.production_release_test.out" | tee -a "$LOG"
rm -f "$TEST" "$ROOT/.production_release_test.out"

say "7/10" "RESTART PHP-FPM + NGINX"
sudo synosystemctl restart pkgctl-PHP8.2 >/tmp/ujian-release-php.out 2>&1 || {
  cat /tmp/ujian-release-php.out | tee -a "$LOG"
  rollback
  trap - ERR
  die "PHP-FPM gagal restart."
}
sleep 3
sudo synosystemctl restart nginx >/tmp/ujian-release-nginx.out 2>&1 || {
  cat /tmp/ujian-release-nginx.out | tee -a "$LOG"
  rollback
  trap - ERR
  die "Nginx gagal restart."
}
sleep 3

say "8/10" "REAL PUBLIC HOST + EXAM LINK SMOKE TEST"
# The old test only queried http://127.0.0.1. That can return 200 for a
# different Nginx server block while the real HTTPS domain returns 403.
PUBLIC_URL="$($PHP -r 'require $argv[1]; echo public_base_url();' "$ROOT/config.php" 2>/tmp/ujian-public-url.err || true)"
if [ -z "$PUBLIC_URL" ]; then
  PUBLIC_URL="https://ujian.revolearning.online"
fi
PUBLIC_URL="${PUBLIC_URL%/}"
printf 'PUBLIC_URL=%s\n' "$PUBLIC_URL" | tee -a "$LOG"

EXAM_TOKEN="$($PHP -r 'require $argv[1]; $v=db()->query("SELECT public_token FROM exams WHERE active=1 ORDER BY id DESC LIMIT 1")->fetchColumn(); echo (string)$v;' "$ROOT/config.php" 2>/tmp/ujian-exam-token.err || true)"
[ -n "$EXAM_TOKEN" ] || die "Tidak ada ujian aktif untuk smoke test. Aktifkan minimal satu ujian sebelum deploy production."
printf 'ACTIVE_EXAM_TOKEN=%s...\n' "${EXAM_TOKEN:0:8}" | tee -a "$LOG"

FAIL=0
http_test(){
  local name="$1" url="$2" expect="$3" body meta code
  body="/tmp/ujian-http-$TS-${RANDOM}.html"
  meta="$(curl -ksS -L --max-time 20 -o "$body" -w 'HTTP=%{http_code} SIZE=%{size_download} URL=%{url_effective}' "$url" || true)"
  code="$(printf '%s' "$meta" | sed -n 's/.*HTTP=\([0-9][0-9][0-9]\).*/\1/p')"
  printf '%-24s %s\n' "$name" "$meta" | tee -a "$LOG"
  if [ "${code:-000}" != "$expect" ]; then
    FAIL=1
    echo "--- FAILED $name expected HTTP=$expect ---" | tee -a "$LOG"
    head -c 4000 "$body" | tee -a "$LOG" || true
    echo | tee -a "$LOG"
  fi
  if grep -Eiq 'Fatal error:|Uncaught (Error|Exception)|Permission denied|failed to open stream|Access denied|No such file or directory|Warning:.*session|HEALTHCHECK FAILED' "$body"; then
    FAIL=1
    echo "--- PHP/WEB ERROR DETECTED: $name ---" | tee -a "$LOG"
    grep -Ei 'Fatal error:|Uncaught (Error|Exception)|Permission denied|failed to open stream|Access denied|No such file or directory|Warning:.*session|HEALTHCHECK FAILED' "$body" | head -30 | tee -a "$LOG" || true
  fi
  rm -f "$body"
}

http_test "ROOT" "$PUBLIC_URL/" "200"
http_test "LOGIN" "$PUBLIC_URL/login.php" "200"
http_test "ADMIN" "$PUBLIC_URL/admin/index.php" "200"
http_test "ADMIN PARTICIPANTS" "$PUBLIC_URL/admin/participants.php" "200"
http_test "ADMIN UPDATE" "$PUBLIC_URL/admin/update.php" "200"
http_test "PARTICIPANT ACCESS" "$PUBLIC_URL/peserta/access.php?exam=$(printf '%s' "$EXAM_TOKEN" | sed 's/ /%20/g')" "200"

if [ "$FAIL" -ne 0 ]; then
  echo "--- NGINX ERROR LOG ---" | tee -a "$LOG"
  sudo tail -n 120 /var/log/nginx/error.log 2>/dev/null | tee -a "$LOG" || true
  rollback
  trap - ERR
  die "REAL PUBLIC HOST smoke test gagal. Production dikembalikan otomatis ke commit sebelum update."
fi

say "9/10" "FINAL INTEGRITY + CONFIG PRESERVATION"
[ -f "$ROOT/config.local.php" ] || [ -n "${DB_HOST:-}" ] || die "config.local.php hilang dan DB_HOST environment tidak tersedia."
for f in "${REQUIRED[@]}"; do [ -r "$ROOT/$f" ] || die "Final integrity gagal: $f"; done
[ "$(git rev-parse HEAD)" = "$TARGET" ] || die "Commit production tidak sesuai TARGET GitHub."

say "10/10" "FINAL RESULT"
printf '============================================\n FINAL RESULT : BERHASIL\n============================================\nVERSION=%s\nCOMMIT=%s\nBACKUP=%s\nLOG=%s\n\nAUDIT LULUS: source, VERSION/manifest, hard-path, session architecture, PHP syntax, config+DB, permissions, PHP-FPM, Nginx, HTTPS/public host, active exam link, dan halaman utama.\n' "$VERSION" "$(git rev-parse --short HEAD)" "$BACKUP" "$LOG" | tee -a "$LOG"
