#!/bin/bash
set -Eeuo pipefail

# FINAL Ujian Online production gate.
# No PHP source patching. Public routing is checked BEFORE deployment.
ROOT="${ROOT:-$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd -P)}"
PHP="${PHP_BIN:-/usr/local/bin/php82}"
PUBLIC_HOST="${PUBLIC_HOST:-ujian.revolearning.online}"
PUBLIC_URL="https://${PUBLIC_HOST}"
TS="$(date +%Y%m%d-%H%M%S)"
BACKUP="$ROOT/_backup_final_$TS"
STAGE="/tmp/ujian-online-final-$TS"
LOG="/tmp/ujian-online-final-$TS.log"
LOCK="$ROOT/.production-release.lock"
BEFORE=""; TARGET=""; DEPLOYED=0

say(){ printf '\n[%s] %s\n' "$1" "$2" | tee -a "$LOG"; }
fail(){ printf '\n==================================================\n FINAL RESULT : GAGAL\n==================================================\n%s\nLOG=%s\n' "$1" "$LOG" | tee -a "$LOG"; exit 1; }
cleanup(){ rm -rf "$STAGE"; rm -f "$LOCK" "$ROOT/.production_release_test.php" "$ROOT/.production_release_test.out"; }
rollback(){
  set +e
  [ "$DEPLOYED" -eq 1 ] || return 0
  [ -n "$BEFORE" ] && git reset --hard "$BEFORE" >/dev/null 2>&1 || true
  [ -f "$BACKUP/config.local.php" ] && cp -p "$BACKUP/config.local.php" "$ROOT/config.local.php" || true
  [ -f "$BACKUP/config.php" ] && cp -p "$BACKUP/config.php" "$ROOT/config.php" || true
  [ -f "$BACKUP/VERSION.txt" ] && cp -p "$BACKUP/VERSION.txt" "$ROOT/VERSION.txt" || true
  sudo synosystemctl restart pkgctl-PHP8.2 >/dev/null 2>&1 || true
  sleep 2
  sudo synosystemctl restart nginx >/dev/null 2>&1 || true
}
on_error(){ rc=$?; rollback; printf '\nROLLBACK SELESAI.\n' | tee -a "$LOG"; exit "$rc"; }
trap cleanup EXIT
trap on_error ERR

[ ! -e "$LOCK" ] || fail "Deployment lain sedang berjalan: $LOCK"
printf '%s\n' "$$" > "$LOCK"
[ -d "$ROOT/.git" ] || fail "Bukan checkout Git: $ROOT"
[ -x "$PHP" ] || fail "PHP 8.2 tidak ditemukan: $PHP"
for c in git curl sudo tar; do command -v "$c" >/dev/null || fail "$c tidak ditemukan."; done
cd "$ROOT"

say "1/10" "BACKUP + FETCH GITHUB"
mkdir -p "$BACKUP"
[ -f config.local.php ] && cp -p config.local.php "$BACKUP/config.local.php"
[ -f config.php ] && cp -p config.php "$BACKUP/config.php"
[ -f VERSION.txt ] && cp -p VERSION.txt "$BACKUP/VERSION.txt"
BEFORE="$(git rev-parse HEAD)"
git status --short > "$BACKUP/BEFORE_STATUS.txt" || true
git fetch --prune origin main
TARGET="$(git rev-parse origin/main)"
printf 'BEFORE=%s\nTARGET=%s\nPUBLIC_HOST=%s\nBACKUP=%s\n' "$BEFORE" "$TARGET" "$PUBLIC_HOST" "$BACKUP" | tee -a "$LOG"

say "2/10" "AUDIT SOURCE RELEASE"
rm -rf "$STAGE"; mkdir -p "$STAGE"; git archive "$TARGET" | tar -x -C "$STAGE"
REQUIRED=(VERSION.txt update-manifest.json index.php config.php health.php login.php logout.php admin/index.php admin/participants.php admin/update.php peserta/index.php peserta/access.php peserta/verify.php peserta/finish.php)
for f in "${REQUIRED[@]}"; do [ -r "$STAGE/$f" ] || fail "FILE WAJIB HILANG: $f"; done
VERSION="$(tr -d '\r\n' < "$STAGE/VERSION.txt")"
MANIFEST="$("$PHP" -r '$d=json_decode(file_get_contents($argv[1]),true);echo is_array($d)?($d["version"]??""):"";' "$STAGE/update-manifest.json")"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || fail "VERSION tidak valid."
[ "$VERSION" = "$MANIFEST" ] || fail "VERSION.txt dan manifest tidak sinkron."
if grep -RInE --include='*.php' --exclude-dir=tools --exclude-dir=storage '/volume1/web/ujian-online' "$STAGE" >/tmp/hard.$$.txt 2>/dev/null; then
  cat /tmp/hard.$$.txt | tee -a "$LOG"; rm -f /tmp/hard.$$.txt
  fail "Source mengandung hard-coded /volume1/web/ujian-online."
fi
rm -f /tmp/hard.$$.txt
SESSION_BAD=0
while IFS= read -r -d '' f; do
  [ "${f#$STAGE/}" = "config.php" ] && continue
  if grep -qE '(^|[^[:alnum:]_])session_start[[:space:]]*\(' "$f"; then
    echo "session_start: ${f#$STAGE/}" | tee -a "$LOG"; SESSION_BAD=1
  fi
done < <(find "$STAGE" -type f -name '*.php' ! -path "$STAGE/tools/*" -print0)
[ "$SESSION_BAD" -eq 0 ] || fail "session_start() ditemukan di luar config.php."
grep -qE 'session_start[[:space:]]*\(' "$STAGE/config.php" || fail "config.php tidak memiliki session_start()."
COUNT=0
while IFS= read -r -d '' f; do
  COUNT=$((COUNT+1))
  "$PHP" -l "$f" >/tmp/lint.$$.txt 2>&1 || { cat /tmp/lint.$$.txt | tee -a "$LOG"; rm -f /tmp/lint.$$.txt; fail "PHP syntax error: ${f#$STAGE/}"; }
done < <(find "$STAGE" -type f -name '*.php' ! -path "$STAGE/tools/*" -print0)
rm -f /tmp/lint.$$.txt
printf 'VERSION=%s MANIFEST=%s PHP_FILES=%s\n' "$VERSION" "$MANIFEST" "$COUNT" | tee -a "$LOG"

check_health(){
  local label="$1" url="$2" extra="${3:-}" body meta code
  body="/tmp/health-$TS-${RANDOM}.txt"
  meta="$(curl -ksS $extra --connect-timeout 8 --max-time 20 -o "$body" -w 'HTTP=%{http_code}\nURL=%{url_effective}' "$url" || true)"
  code="$(printf '%s\n' "$meta" | sed -n 's/^HTTP=//p' | tail -1)"
  printf '%s HTTP=%s URL=%s\n' "$label" "${code:-000}" "$(printf '%s\n' "$meta" | sed -n 's/^URL=//p' | tail -1)" | tee -a "$LOG"
  if [ "${code:-000}" != "200" ] || ! grep -Fq "Ujian Online" "$body"; then
    head -c 5000 "$body" | tee -a "$LOG" || true; rm -f "$body"
    return 1
  fi
  rm -f "$body"
}

say "3/10" "PREDEPLOY LOCAL HTTPS VHOST"
if ! check_health "LOCAL VHOST" "$PUBLIC_URL/health.php" "--resolve ${PUBLIC_HOST}:443:127.0.0.1"; then
  fail "Local HTTPS vhost $PUBLIC_HOST tidak mengarah ke aplikasi. Perbaiki Web Station/Nginx vhost. Production BELUM disentuh."
fi

say "4/10" "PREDEPLOY PUBLIC HTTPS ROUTING"
if ! check_health "PUBLIC ROUTE" "$PUBLIC_URL/health.php"; then
  fail "Public $PUBLIC_HOST belum mengarah ke aplikasi Ujian Online. Ini DNS/port-forward/Web Station/Nginx, BUKAN PHP. Production BELUM disentuh."
fi

say "5/10" "DEPLOY EXACT COMMIT"
git reset --hard "$TARGET" >/dev/null
git clean -fd -e config.local.php -e storage/ -e uploads/ -e "_backup_final_*" -e .production-release.lock >/dev/null 2>&1 || true
DEPLOYED=1
[ -f "$BACKUP/config.local.php" ] && cp -p "$BACKUP/config.local.php" "$ROOT/config.local.php"
find "$ROOT" -type d ! -path "$ROOT/.git*" -exec chmod 755 {} + 2>/dev/null || true
find "$ROOT" -type f -name '*.php' -exec chmod 644 {} + 2>/dev/null || true
[ -f "$ROOT/config.local.php" ] && chmod 640 "$ROOT/config.local.php"
for d in storage storage/backups storage/update_uploads storage/update_staging uploads uploads/questions; do [ -d "$ROOT/$d" ] && chmod 775 "$ROOT/$d"; done
[ "$(git rev-parse HEAD)" = "$TARGET" ] || fail "HEAD bukan TARGET."

say "6/10" "DATABASE PREFLIGHT"
TEST="$ROOT/.production_release_test.php"
cat > "$TEST" <<'PHP'
<?php
declare(strict_types=1);
require __DIR__.'/config.php';
$pdo=db();
$pdo->query('SELECT 1')->fetchColumn();
$pdo->query('SELECT COUNT(*) FROM exams')->fetchColumn();
echo "CONFIG_OK\nDB_OK\nVERSION=".app_version()."\n";
PHP
"$PHP" "$TEST" > "$ROOT/.production_release_test.out" 2>&1 || { cat "$ROOT/.production_release_test.out" | tee -a "$LOG"; fail "Database/config preflight gagal."; }
cat "$ROOT/.production_release_test.out" | tee -a "$LOG"
rm -f "$TEST" "$ROOT/.production_release_test.out"

say "7/10" "RESTART PHP-FPM + NGINX"
sudo synosystemctl restart pkgctl-PHP8.2 >/tmp/release-php.out 2>&1 || { cat /tmp/release-php.out | tee -a "$LOG"; fail "PHP-FPM restart gagal."; }
sleep 3
sudo synosystemctl restart nginx >/tmp/release-nginx.out 2>&1 || { cat /tmp/release-nginx.out | tee -a "$LOG"; fail "Nginx restart gagal."; }
sleep 3

say "8/10" "POSTDEPLOY HTTPS SMOKE TEST"
FAIL=0
for item in "HEALTH|$PUBLIC_URL/health.php" "ROOT|$PUBLIC_URL/" "LOGIN|$PUBLIC_URL/login.php" "ADMIN|$PUBLIC_URL/admin/index.php" "PARTICIPANTS|$PUBLIC_URL/admin/participants.php" "UPDATE|$PUBLIC_URL/admin/update.php"; do
  label="${item%%|*}"; url="${item#*|}"; body="/tmp/smoke-$TS-${RANDOM}.html"
  meta="$(curl -ksS -L --connect-timeout 10 --max-time 30 -o "$body" -w 'HTTP=%{http_code}\nURL=%{url_effective}' "$url" || true)"
  code="$(printf '%s\n' "$meta" | sed -n 's/^HTTP=//p' | tail -1)"
  printf '%-18s HTTP=%s URL=%s\n' "$label" "${code:-000}" "$(printf '%s\n' "$meta" | sed -n 's/^URL=//p' | tail -1)" | tee -a "$LOG"
  if [ "${code:-000}" != "200" ] || grep -Eiq 'Fatal error:|Uncaught (Error|Exception)|Permission denied|failed to open stream|No such file or directory|Warning:.*session|HEALTHCHECK FAILED|Access denied|directory index of.*/synoman/webapi' "$body"; then
    FAIL=1; echo "FAILED BODY ($label):" | tee -a "$LOG"; head -c 5000 "$body" | tee -a "$LOG" || true
  fi
  rm -f "$body"
done
[ "$FAIL" -eq 0 ] || fail "Postdeploy HTTPS smoke test gagal; rollback otomatis."

say "9/10" "FINAL INTEGRITY"
[ "$(git rev-parse HEAD)" = "$TARGET" ] || fail "Final HEAD bukan TARGET."
for f in "${REQUIRED[@]}"; do [ -r "$ROOT/$f" ] || fail "File final tidak readable: $f"; done

say "10/10" "FINAL RESULT"
printf '==================================================\n FINAL RESULT : BERHASIL\n==================================================\nVERSION=%s\nCOMMIT=%s\nPUBLIC_HOST=%s\nBACKUP=%s\nLOG=%s\n\nTidak ada patch PHP otomatis. Release hanya dipasang setelah source audit + local vhost + public routing lulus sebelum deploy.\n' "$VERSION" "$(git rev-parse --short HEAD)" "$PUBLIC_HOST" "$BACKUP" "$LOG" | tee -a "$LOG"
