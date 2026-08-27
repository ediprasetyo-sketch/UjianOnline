#!/bin/bash
set -Eeuo pipefail

# FINAL production deploy/repair for Synology Ujian Online.
# This script validates the release BEFORE touching production, preserves
# config.local.php, deploys one exact Git commit, then tests the REAL public
# HTTPS host. Any failed post-deploy test triggers automatic rollback.

ROOT="${ROOT:-$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd -P)}"
PHP="${PHP_BIN:-/usr/local/bin/php82}"
REMOTE="origin"
BRANCH="main"
PUBLIC_HOST="${PUBLIC_HOST:-ujian.revolearning.online}"
PUBLIC_URL="https://${PUBLIC_HOST}"
TS="$(date +%Y%m%d-%H%M%S)"
BACKUP="$ROOT/_backup_final_$TS"
STAGE="/tmp/ujian-online-final-$TS"
LOG="/tmp/ujian-online-final-$TS.log"
LOCK="$ROOT/.production-release.lock"
BEFORE=""
TARGET=""
DEPLOYED=0
ROLLBACK_DONE=0

say(){ printf '\n[%s] %s\n' "$1" "$2" | tee -a "$LOG"; }
die(){ if [ "$DEPLOYED" -eq 1 ] && [ "$ROLLBACK_DONE" -eq 0 ]; then rollback; ROLLBACK_DONE=1; DEPLOYED=0; fi; printf '\n==================================================\n FINAL RESULT : GAGAL\n==================================================\n%s\nLOG=%s\n' "$1" "$LOG" | tee -a "$LOG"; exit 1; }
cleanup(){ rm -rf "$STAGE"; rm -f "$LOCK"; }

rollback(){
  ROLLBACK_DONE=1
  set +e
  if [ -n "$BEFORE" ] && [ -d "$ROOT/.git" ]; then git reset --hard "$BEFORE" >/dev/null 2>&1; fi
  if [ -f "$BACKUP/config.local.php" ]; then cp -p "$BACKUP/config.local.php" "$ROOT/config.local.php"; fi
  if [ -f "$BACKUP/config.php" ]; then cp -p "$BACKUP/config.php" "$ROOT/config.php"; fi
  if [ -f "$BACKUP/VERSION.txt" ]; then cp -p "$BACKUP/VERSION.txt" "$ROOT/VERSION.txt"; fi
  rm -f "$ROOT/.production_release_test.php" "$ROOT/.production_release_test.out"
  sudo synosystemctl restart pkgctl-PHP8.2 >/dev/null 2>&1
  sleep 2
  sudo synosystemctl restart nginx >/dev/null 2>&1
  sleep 2
}

trap cleanup EXIT
trap 'rc=$?; rollback; trap - ERR; printf "\\nROLLBACK SELESAI. Production dikembalikan ke commit sebelum deploy.\\n" | tee -a "$LOG"; exit "$rc"' ERR

[ ! -e "$LOCK" ] || die "Deployment lain sedang berjalan: $LOCK"
printf '%s\n' "$$" > "$LOCK"
[ -d "$ROOT/.git" ] || die "Bukan checkout Git: $ROOT"
[ -x "$PHP" ] || die "PHP 8.2 tidak ditemukan: $PHP"
command -v git >/dev/null || die "Git tidak ditemukan."
command -v curl >/dev/null || die "curl tidak ditemukan."
command -v sudo >/dev/null || die "sudo tidak ditemukan."
command -v tar >/dev/null || die "tar tidak ditemukan."
cd "$ROOT"

say "1/10" "BACKUP + PRECHECK LOCAL"
mkdir -p "$BACKUP"
[ -f config.local.php ] && cp -p config.local.php "$BACKUP/config.local.php"
[ -f config.php ] && cp -p config.php "$BACKUP/config.php"
[ -f VERSION.txt ] && cp -p VERSION.txt "$BACKUP/VERSION.txt"
BEFORE="$(git rev-parse HEAD)"
git status --short > "$BACKUP/BEFORE_STATUS.txt" || true
printf 'ROOT=%s\nBEFORE=%s\nPUBLIC_HOST=%s\nBACKUP=%s\n' "$ROOT" "$BEFORE" "$PUBLIC_HOST" "$BACKUP" | tee -a "$LOG"

say "2/10" "FETCH TARGET GITHUB"
git fetch --prune "$REMOTE" "$BRANCH"
TARGET="$(git rev-parse "$REMOTE/$BRANCH")"
printf 'TARGET=%s\n' "$TARGET" | tee -a "$LOG"
[ "$TARGET" != "$BEFORE" ] || say "INFO" "Production sudah berada pada commit TARGET; tetap dilakukan audit + smoke test."

say "3/10" "AUDIT RELEASE SEBELUM DEPLOY"
rm -rf "$STAGE" && mkdir -p "$STAGE"
git archive "$TARGET" | tar -x -C "$STAGE"

REQUIRED=(VERSION.txt update-manifest.json index.php config.php health.php login.php logout.php admin/index.php admin/participants.php admin/update.php peserta/index.php peserta/access.php peserta/verify.php peserta/finish.php)
for f in "${REQUIRED[@]}"; do [ -r "$STAGE/$f" ] || die "FILE WAJIB TIDAK ADA/TIDAK TERBACA: $f"; done

VERSION="$(tr -d '\r\n' < "$STAGE/VERSION.txt")"
MANIFEST_VERSION="$($PHP -r '$d=json_decode(file_get_contents($argv[1]),true); echo is_array($d) ? (string)($d["version"] ?? "") : "";' "$STAGE/update-manifest.json")"
printf 'VERSION=%s MANIFEST=%s\n' "$VERSION" "$MANIFEST_VERSION" | tee -a "$LOG"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || die "VERSION.txt tidak valid."
[ "$VERSION" = "$MANIFEST_VERSION" ] || die "VERSION.txt dan update-manifest.json tidak sinkron."

if grep -RInE --include='*.php' --exclude-dir=tools --exclude-dir=storage '/volume1/web/ujian-online' "$STAGE" >/tmp/ujian-hardpath.$$.txt 2>/dev/null; then
  cat /tmp/ujian-hardpath.$$.txt | tee -a "$LOG"
  rm -f /tmp/ujian-hardpath.$$.txt
  die "Release mengandung hard-coded production filesystem path."
fi
rm -f /tmp/ujian-hardpath.$$.txt

SESSION_BAD=0
while IFS= read -r -d '' f; do
  rel="${f#$STAGE/}"
  [ "$rel" = "config.php" ] && continue
  if grep -nE '(^|[^[:alnum:]_])session_start[[:space:]]*\(' "$f" >/tmp/ujian-session.$$.txt 2>/dev/null; then
    echo "$rel: $(head -1 /tmp/ujian-session.$$.txt)" | tee -a "$LOG"
    SESSION_BAD=1
  fi
done < <(find "$STAGE" -type f -name '*.php' ! -path "$STAGE/tools/*" -print0)
rm -f /tmp/ujian-session.$$.txt
[ "$SESSION_BAD" -eq 0 ] || die "session_start() ditemukan di luar config.php."
grep -qE 'session_start[[:space:]]*\(' "$STAGE/config.php" || die "config.php tidak memiliki session_start()."

say "4/10" "PHP SYNTAX AUDIT SELURUH RELEASE"
PHP_COUNT=0
while IFS= read -r -d '' f; do
  PHP_COUNT=$((PHP_COUNT+1))
  if ! "$PHP" -l "$f" >/tmp/ujian-php-lint.$$.txt 2>&1; then
    cat /tmp/ujian-php-lint.$$.txt | tee -a "$LOG"
    rm -f /tmp/ujian-php-lint.$$
    die "PHP syntax error: ${f#$STAGE/}"
  fi
done < <(find "$STAGE" -type f -name '*.php' ! -path "$STAGE/tools/*" -print0)
rm -f /tmp/ujian-php-lint.$$
printf 'PHP files checked: %s\n' "$PHP_COUNT" | tee -a "$LOG"

say "5/10" "DEPLOY EXACT COMMIT + PRESERVE LOCAL CONFIG"
git reset --hard "$TARGET" >/dev/null
git clean -fd -e config.local.php -e storage/ -e uploads/ -e "_backup_final_*" -e .production-release.lock >/dev/null 2>&1 || true
DEPLOYED=1
if [ -f "$BACKUP/config.local.php" ]; then cp -p "$BACKUP/config.local.php" "$ROOT/config.local.php"; else say "WARN" "config.local.php tidak ada; DB_* environment harus tersedia."; fi

find "$ROOT" -type d ! -path "$ROOT/.git*" -exec chmod 755 {} + 2>/dev/null || true
find "$ROOT" -type f -name '*.php' -exec chmod 644 {} + 2>/dev/null || true
find "$ROOT" -type f -name '*.txt' -exec chmod 644 {} + 2>/dev/null || true
[ -f "$ROOT/config.local.php" ] && chmod 640 "$ROOT/config.local.php"
for d in storage storage/backups storage/update_uploads storage/update_staging uploads uploads/questions; do
  [ -d "$ROOT/$d" ] && chmod 775 "$ROOT/$d"
done
for f in "${REQUIRED[@]}"; do [ -r "$ROOT/$f" ] || die "File production tidak readable setelah deploy: $f"; done
[ "$(git rev-parse HEAD)" = "$TARGET" ] || die "HEAD production bukan TARGET."

say "6/10" "LOCAL CONFIG + DATABASE PREFLIGHT"
TEST="$ROOT/.production_release_test.php"
cat > "$TEST" <<'PHP'
<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$pdo = db();
$pdo->query('SELECT 1')->fetchColumn();
$exam = $pdo->query("SELECT COUNT(*) FROM exams WHERE active=1")->fetchColumn();
echo "CONFIG_OK\n";
echo "DB_OK\n";
echo "VERSION=" . app_version() . "\n";
echo "ACTIVE_EXAMS=" . (int)$exam . "\n";
PHP
chmod 644 "$TEST"
if ! "$PHP" "$TEST" > "$ROOT/.production_release_test.out" 2>&1; then
  cat "$ROOT/.production_release_test.out" | tee -a "$LOG"
  die "Local config/database preflight gagal."
fi
cat "$ROOT/.production_release_test.out" | tee -a "$LOG"
rm -f "$TEST" "$ROOT/.production_release_test.out"

say "7/10" "RESTART PHP-FPM + NGINX"
sudo synosystemctl restart pkgctl-PHP8.2 >/tmp/ujian-release-php.out 2>&1 || { cat /tmp/ujian-release-php.out | tee -a "$LOG"; die "PHP-FPM gagal restart."; }
sleep 3
sudo synosystemctl restart nginx >/tmp/ujian-release-nginx.out 2>&1 || { cat /tmp/ujian-release-nginx.out | tee -a "$LOG"; die "Nginx gagal restart."; }
sleep 3

say "8/10" "REAL PUBLIC HTTPS SMOKE TEST — $PUBLIC_HOST"
EXAM_TOKEN="$($PHP -r 'require $argv[1]; $v=db()->query("SELECT public_token FROM exams WHERE active=1 ORDER BY id DESC LIMIT 1")->fetchColumn(); echo (string)$v;' "$ROOT/config.php" 2>/tmp/ujian-exam-token.err || true)"
[ -n "$EXAM_TOKEN" ] || die "Tidak ada ujian aktif untuk smoke test. Ini bukan error script; aktifkan minimal satu ujian publik."
printf 'ACTIVE_EXAM_TOKEN=%s...\n' "${EXAM_TOKEN:0:8}" | tee -a "$LOG"

FAIL=0
http_test(){
  local name="$1" url="$2" expected="$3" body meta code effective host
  body="/tmp/ujian-http-$TS-${RANDOM}.html"
  meta="$(curl -ksS -L --connect-timeout 10 --max-time 30 -o "$body" -w 'HTTP=%{http_code}\nURL=%{url_effective}\nSIZE=%{size_download}' "$url" || true)"
  code="$(printf '%s\n' "$meta" | sed -n 's/^HTTP=//p' | tail -1)"
  effective="$(printf '%s\n' "$meta" | sed -n 's/^URL=//p' | tail -1)"
  host="$(printf '%s\n' "$effective" | sed -n 's#^https://\([^/]*\).*#\1#p')"
  printf '%-24s HTTP=%s URL=%s\n' "$name" "${code:-000}" "$effective" | tee -a "$LOG"
  if [ "${code:-000}" != "$expected" ]; then
    FAIL=1; echo "EXPECTED HTTP=$expected" | tee -a "$LOG"; head -c 4000 "$body" | tee -a "$LOG" || true; echo | tee -a "$LOG"
  fi
  if [ "$host" != "$PUBLIC_HOST" ]; then
    FAIL=1; echo "WRONG PUBLIC HOST: expected $PUBLIC_HOST got ${host:-EMPTY}" | tee -a "$LOG"
  fi
  if grep -Eiq 'Fatal error:|Uncaught (Error|Exception)|Permission denied|failed to open stream|No such file or directory|Warning:.*session|Access denied|HEALTHCHECK FAILED' "$body"; then
    FAIL=1; echo "PHP/WEB ERROR DETECTED: $name" | tee -a "$LOG"
    grep -Ei 'Fatal error:|Uncaught (Error|Exception)|Permission denied|failed to open stream|No such file or directory|Warning:.*session|Access denied|HEALTHCHECK FAILED' "$body" | head -30 | tee -a "$LOG" || true
  fi
  rm -f "$body"
}

http_test "HEALTH" "$PUBLIC_URL/health.php" "200"
http_test "ROOT" "$PUBLIC_URL/" "200"
http_test "LOGIN" "$PUBLIC_URL/login.php" "200"
http_test "ADMIN" "$PUBLIC_URL/admin/index.php" "200"
http_test "PARTICIPANTS" "$PUBLIC_URL/admin/participants.php" "200"
http_test "UPDATE" "$PUBLIC_URL/admin/update.php" "200"
http_test "PARTICIPANT ACCESS" "$PUBLIC_URL/peserta/access.php?exam=$(printf '%s' "$EXAM_TOKEN" | sed 's/ /%20/g')" "200"

if [ "$FAIL" -ne 0 ]; then
  say "FAILURE" "Public smoke test gagal; mengambil log PHP/Nginx untuk diagnosis objektif."
  sudo tail -n 120 /var/log/nginx/error.log 2>/dev/null | tee -a "$LOG" || true
  die "REAL PUBLIC HTTPS smoke test gagal. Tidak dianggap berhasil dan rollback otomatis dijalankan."
fi

say "9/10" "FINAL INTEGRITY"
[ "$(git rev-parse HEAD)" = "$TARGET" ] || die "Final HEAD tidak sama dengan TARGET."
[ -f "$ROOT/config.local.php" ] || [ -n "${DB_HOST:-}" ] || die "Konfigurasi database production tidak ditemukan."
for f in "${REQUIRED[@]}"; do [ -r "$ROOT/$f" ] || die "Final integrity gagal: $f"; done

say "10/10" "FINAL RESULT"
printf '==================================================\n FINAL RESULT : BERHASIL\n==================================================\nVERSION=%s\nCOMMIT=%s\nPUBLIC_HOST=%s\nBACKUP=%s\nLOG=%s\n\nVALIDASI LULUS: release source, VERSION/manifest, hard-path, session architecture, seluruh PHP syntax, config+database, permissions, PHP-FPM, Nginx, REAL HTTPS HOST, health endpoint, login, admin, peserta, dan active exam access.\n' "$VERSION" "$(git rev-parse --short HEAD)" "$PUBLIC_HOST" "$BACKUP" "$LOG" | tee -a "$LOG"
