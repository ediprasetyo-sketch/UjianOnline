#!/bin/sh
set -eu

# Read-only production audit. It never edits application source and never uses
# `sudo -u <FPM-user>`, because Synology process listings may truncate names.
ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd -P)"
PHP="${PHP_BIN:-/usr/local/bin/php82}"
LOG="/tmp/ujian-online-doctor-$(date +%Y%m%d-%H%M%S).log"

say(){ printf '\n[%s] %s\n' "$1" "$2" | tee -a "$LOG"; }
fail(){ printf '\n============================================\n FINAL RESULT : MASIH ERROR\n============================================\n%s\nLOG=%s\n' "$1" "$LOG" | tee -a "$LOG"; exit 1; }

[ -d "$ROOT/.git" ] || fail "Bukan checkout Git: $ROOT"
[ -x "$PHP" ] || fail "PHP 8.2 tidak ditemukan: $PHP"
command -v git >/dev/null || fail "Git tidak ditemukan."
command -v curl >/dev/null || fail "curl tidak ditemukan."
cd "$ROOT"

say 1 "IDENTITAS RELEASE"
printf 'ROOT=%s\nVERSION=' "$ROOT" | tee -a "$LOG"; cat VERSION.txt | tee -a "$LOG"
printf 'GIT='; git rev-parse --short HEAD | tee -a "$LOG"

say 2 "PHP SYNTAX SELURUH SOURCE"
COUNT=0
while IFS= read -r -d '' f; do
  COUNT=$((COUNT+1))
  "$PHP" -l "$f" >/tmp/ujian-doctor-lint.$$ 2>&1 || { cat /tmp/ujian-doctor-lint.$$ | tee -a "$LOG"; rm -f /tmp/ujian-doctor-lint.$$; fail "PHP syntax error: ${f#$ROOT/}"; }
done < <(find "$ROOT" -type f -name '*.php' ! -path "$ROOT/_backup_*/*" -print0)
rm -f /tmp/ujian-doctor-lint.$$
printf 'PHP files checked: %s\n' "$COUNT" | tee -a "$LOG"

say 3 "STATIC PATH + CONFIG REFERENCE AUDIT"
if grep -RInE --include='*.php' '/volume1/web/ujian-online|require(_once)?[[:space:]]*\(?[[:space:]]*["'"']\.\./config\.php["'"']' . ! -path './_backup_*/*' ! -path './tools/*' 2>/tmp/ujian-doctor-grep.$$; then
  cat /tmp/ujian-doctor-grep.$$ | tee -a "$LOG" || true
  fail "Ditemukan runtime path/config reference yang tidak valid."
fi
rm -f /tmp/ujian-doctor-grep.$$

audit_file(){ [ -r "$ROOT/$1" ] || fail "File tidak readable: $1"; }
for f in VERSION.txt update-manifest.json config.php health.php index.php login.php admin/index.php admin/update.php admin/participants.php peserta/index.php peserta/access.php; do audit_file "$f"; done

say 4 "CONFIG + DATABASE PREFLIGHT"
TEST="$ROOT/.doctor_runtime.php"
cat > "$TEST" <<'PHP'
<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$pdo=db();
$pdo->query('SELECT 1')->fetchColumn();
echo "CONFIG_OK\nDB_OK\nVERSION=" . app_version() . "\n";
PHP
chmod 644 "$TEST"
if ! "$PHP" "$TEST" >/tmp/ujian-doctor-runtime.$$ 2>&1; then
  cat /tmp/ujian-doctor-runtime.$$ | tee -a "$LOG"; rm -f "$TEST" /tmp/ujian-doctor-runtime.$$; fail "Config/database preflight gagal.";
fi
cat /tmp/ujian-doctor-runtime.$$ | tee -a "$LOG"
rm -f "$TEST" /tmp/ujian-doctor-runtime.$$

say 5 "HTTP RUNTIME TEST"
FAIL=0
for u in / /health.php /login.php /admin/index.php /admin/participants.php /admin/update.php /peserta/index.php /peserta/access.php; do
  body="/tmp/ujian-doctor-http.html"
  meta="$(curl -sS --max-time 15 -o "$body" -w 'HTTP=%{http_code} SIZE=%{size_download}' "http://127.0.0.1$u" || true)"
  printf '%-32s %s\n' "$u" "$meta" | tee -a "$LOG"
  code="$(printf '%s' "$meta" | sed -n 's/.*HTTP=\([0-9][0-9][0-9]\).*/\1/p')"
  case "${code:-000}" in 200|301|302|303|307|308) ;; *) FAIL=1; grep -E 'Fatal error:|Uncaught|Warning:|Permission denied|No such file|failed to open|HEALTHCHECK FAILED' "$body" 2>/dev/null | head -30 | tee -a "$LOG" || true ;; esac
  rm -f "$body"
done

say 6 "PERMISSION AUDIT"
printf 'config.local.php='; if [ -f "$ROOT/config.local.php" ]; then stat -c '%A %U:%G' "$ROOT/config.local.php" 2>/dev/null || ls -l "$ROOT/config.local.php"; else echo 'not present (DB_* environment expected)'; fi
printf 'VERSION.txt='; stat -c '%A %U:%G' "$ROOT/VERSION.txt" 2>/dev/null || ls -l "$ROOT/VERSION.txt"

if [ "$FAIL" -ne 0 ]; then
  echo '--- NGINX ERROR LOG ---' | tee -a "$LOG"
  sudo tail -n 80 /var/log/nginx/error.log 2>/dev/null | tee -a "$LOG" || true
  fail 'HTTP runtime test masih gagal. Tidak ada source yang diubah oleh doctor.'
fi

echo
echo '============================================' | tee -a "$LOG"
echo ' FINAL RESULT : BERHASIL' | tee -a "$LOG"
echo '============================================' | tee -a "$LOG"
printf 'Log: %s\n' "$LOG" | tee -a "$LOG"
echo 'Audit read-only: syntax, path, config, DB, permissions, health dan HTTP smoke test lolos.' | tee -a "$LOG"
