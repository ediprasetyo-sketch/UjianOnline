<?php
declare(strict_types=1);

/**
 * Small DB-backed login throttle for the administrator portal.
 * Limits both one account and one source IP without revealing which one
 * triggered the limit. The table is created idempotently for older installs.
 */
function login_rate_limit_init(): void {
    static $ready = false;
    if ($ready) return;
    db()->exec("CREATE TABLE IF NOT EXISTS login_rate_limits (
        rate_key CHAR(64) NOT NULL PRIMARY KEY,
        attempts INT UNSIGNED NOT NULL DEFAULT 0,
        window_started_at DATETIME NOT NULL,
        blocked_until DATETIME NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_login_rate_updated (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ready = true;
}

function login_rate_key(string $scope, string $value): string {
    return hash('sha256', $scope . ':' . $value);
}

function login_rate_ip(): string {
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function login_rate_normalize_login(string $login): string {
    return strtolower(trim($login));
}

function login_rate_check(string $login): int {
    login_rate_limit_init();
    $pdo = db();
    $keys = [
        login_rate_key('ip', login_rate_ip()),
        login_rate_key('account', login_rate_normalize_login($login)),
    ];
    $blocked = 0;
    $q = $pdo->prepare('SELECT blocked_until FROM login_rate_limits WHERE rate_key=? LIMIT 1');
    foreach ($keys as $key) {
        $q->execute([$key]);
        $until = $q->fetchColumn();
        if ($until !== false && $until !== null) {
            $seconds = strtotime((string)$until) - time();
            if ($seconds > $blocked) $blocked = $seconds;
        }
    }
    return max(0, $blocked);
}

function login_rate_failure(string $login): void {
    login_rate_limit_init();
    $pdo = db();
    $keys = [
        login_rate_key('ip', login_rate_ip()),
        login_rate_key('account', login_rate_normalize_login($login)),
    ];
    $upsert = $pdo->prepare("INSERT INTO login_rate_limits(rate_key,attempts,window_started_at,blocked_until)
        VALUES(?,1,NOW(),NULL)
        ON DUPLICATE KEY UPDATE
          attempts = IF(window_started_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE), 1, attempts + 1),
          window_started_at = IF(window_started_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE), NOW(), window_started_at),
          blocked_until = IF(
              IF(window_started_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE), 1, attempts + 1) >= 5,
              DATE_ADD(IF(window_started_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE), NOW(), window_started_at), INTERVAL 15 MINUTE),
              NULL
          )");
    foreach ($keys as $key) $upsert->execute([$key]);

    // Keep the small table bounded without requiring a scheduled job.
    $pdo->exec("DELETE FROM login_rate_limits WHERE updated_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
}

function login_rate_success(string $login): void {
    login_rate_limit_init();
    $key = login_rate_key('account', login_rate_normalize_login($login));
    db()->prepare('DELETE FROM login_rate_limits WHERE rate_key=?')->execute([$key]);
}
