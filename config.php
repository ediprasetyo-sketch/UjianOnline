<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const DB_HOST = '127.0.0.1';
const DB_NAME = 'ujian_online';
const DB_USER = 'ujian_app';
const DB_PASS = 'iT@dm1nrevo';

/**
 * Optional public base URL override.
 * Set PUBLIC_BASE_URL in the web-server environment when the application is
 * behind a reverse proxy, Cloudflare Tunnel, or uses a fixed public domain.
 * Example: https://example.com/ujian-online
 */
function public_base_url(): string {
    $configured = trim((string)(getenv('PUBLIC_BASE_URL') ?: ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $forwardedProto = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]);
    $https = ($forwardedProto !== '')
        ? strtolower($forwardedProto) === 'https'
        : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';

    $forwardedHost = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''))[0]);
    $host = $forwardedHost !== '' ? $forwardedHost : trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return app_base_path();
    }

    return $scheme . '://' . $host . app_base_path();
}

/** Build an absolute URL suitable for public links and email verification. */
function public_url(string $path=''): string {
    return rtrim(public_base_url(), '/') . '/' . ltrim($path, '/');
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $pdo->exec("SET time_zone = '+07:00'");
    }
    return $pdo;
}

function app_base_path(): string {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $pos = strpos($script, '/admin/');
    if ($pos === false) $pos = strpos($script, '/peserta/');
    if ($pos === false) {
        $dir = str_replace('\\', '/', dirname($script));
        return ($dir === '/' || $dir === '.' || $dir === '\\') ? '' : rtrim($dir, '/');
    }
    return substr($script, 0, $pos);
}

function app_url(string $path=''): string {
    return app_base_path() . '/' . ltrim($path, '/');
}

function require_login(string $role): void {
    if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? null) !== $role) {
        header('Location: ' . app_url('login.php'));
        exit;
    }
}

function json_response(array $data, int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function app_version(): string {
    static $version = null;
    if ($version !== null) return $version;
    $file = __DIR__ . '/VERSION.txt';
    $raw = is_file($file) ? trim((string)file_get_contents($file)) : '';
    $version = preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9._-]+)?$/', $raw) ? $raw : '0.0.0';
    return $version;
}

function ensure_migrations(): array {
    static $done = false;
    if ($done) return [];
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(64) PRIMARY KEY,
        checksum CHAR(64) NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $dir = __DIR__ . '/migrations';
    if (!is_dir($dir)) { $done = true; return []; }
    $files = glob($dir . '/*.sql') ?: [];
    usort($files, 'strnatcasecmp');
    $applied = [];
    foreach ($files as $file) {
        $version = basename($file, '.sql');
        $sql = trim((string)file_get_contents($file));
        $checksum = hash('sha256', $sql);
        $st = $pdo->prepare("SELECT checksum FROM schema_migrations WHERE version=?");
        $st->execute([$version]);
        $old = $st->fetchColumn();
        if ($old !== false) {
            if (!hash_equals((string)$old, $checksum)) throw new RuntimeException("Migration checksum berubah: {$version}");
            continue;
        }
        if ($sql !== '') {
            $pdo->beginTransaction();
            try {
                foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
                    $statement = trim($statement);
                    if ($statement !== '') $pdo->exec($statement);
                }
                $pdo->prepare("INSERT INTO schema_migrations(version, checksum) VALUES(?,?)")->execute([$version, $checksum]);
                $pdo->commit();
                $applied[] = $version;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        }
    }
    $done = true;
    return $applied;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function check_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419); exit('CSRF token tidak valid');
    }
}
