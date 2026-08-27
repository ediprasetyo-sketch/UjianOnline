<?php
declare(strict_types=1);

/* Production secrets live in config.local.php or DB_* environment variables. */
$localConfigFile = __DIR__ . '/config.local.php';
$localConfig = is_file($localConfigFile) ? require $localConfigFile : [];
if (!is_array($localConfig)) $localConfig = [];

$dbHost = trim((string)(getenv('DB_HOST') ?: ($localConfig['db_host'] ?? '')));
$dbName = trim((string)(getenv('DB_NAME') ?: ($localConfig['db_name'] ?? '')));
$dbUser = trim((string)(getenv('DB_USER') ?: ($localConfig['db_user'] ?? '')));
$dbPassEnv = getenv('DB_PASS');
$dbPass = (string)($dbPassEnv !== false ? $dbPassEnv : ($localConfig['db_pass'] ?? ''));
if ($dbHost === '' || $dbName === '' || $dbUser === '') {
    http_response_code(500);
    exit('Konfigurasi database belum lengkap. Atur config.local.php atau DB_* environment variables.');
}

const APP_SESSION_NAME = 'ujian_online_session';
date_default_timezone_set('Asia/Jakarta');

/*
 * Session initialization must happen before session_start().
 * Some Synology/PHP-FPM configurations may already start a session before
 * this file is included. In that case PHP forbids changing session settings;
 * simply reuse the active session instead of producing warnings on every page.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if ($isHttps) ini_set('session.cookie_secure', '1');
    session_name(APP_SESSION_NAME);
    session_start();
} else {
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off');
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
/* Exam pages/API responses contain participant data and answers; never cache them. */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
if ($isHttps) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

function public_base_url(): string {
    global $localConfig;
    $configured = trim((string)(getenv('PUBLIC_BASE_URL') ?: ($localConfig['public_base_url'] ?? '')));
    if ($configured !== '') return rtrim($configured, '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') return app_base_path();
    return $scheme . '://' . $host . app_base_path();
}
function public_url(string $path=''): string { return rtrim(public_base_url(), '/') . '/' . ltrim($path, '/'); }
function db(): PDO {
    static $pdo = null;
    global $dbHost, $dbName, $dbUser, $dbPass;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host='.$dbHost.';dbname='.$dbName.';charset=utf8mb4', $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,
        ]);
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
function app_url(string $path=''): string { return app_base_path() . '/' . ltrim($path, '/'); }
function require_login(string $role): void {
    if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? null) !== $role) { header('Location: '.app_url('login.php')); exit; }
}
function json_response(array $data, int $status=200): never {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data); exit;
}
function app_version(): string {
    static $version=null; if($version!==null)return $version;
    $raw=is_file(__DIR__.'/VERSION.txt')?trim((string)file_get_contents(__DIR__.'/VERSION.txt')):'';
    return $version=preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9._-]+)?$/',$raw)?$raw:'0.0.0';
}
function ensure_migrations(): array {
    static $done=false; if($done)return [];
    $pdo=db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(64) PRIMARY KEY, checksum CHAR(64) NOT NULL, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $dir=__DIR__.'/migrations'; if(!is_dir($dir)){$done=true;return [];}
    $files=glob($dir.'/*.sql')?:[]; usort($files,'strnatcasecmp'); $applied=[];
    foreach($files as $file){
        $version=basename($file,'.sql'); $sql=trim((string)file_get_contents($file)); $checksum=hash('sha256',$sql);
        $st=$pdo->prepare('SELECT checksum FROM schema_migrations WHERE version=?'); $st->execute([$version]); $old=$st->fetchColumn();
        if($old!==false){if(!hash_equals((string)$old,$checksum))throw new RuntimeException("Migration checksum berubah: {$version}");continue;}
        if($sql!==''){$pdo->beginTransaction();try{foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql) as $statement){$statement=trim($statement);if($statement!=='')$pdo->exec($statement);} $pdo->prepare('INSERT INTO schema_migrations(version, checksum) VALUES(?,?)')->execute([$version,$checksum]);$pdo->commit();$applied[]=$version;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}}
    }
    $done=true; return $applied;
}
function csrf_token(): string { if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function check_csrf(): void { if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')){http_response_code(419);exit('CSRF token tidak valid');} }
