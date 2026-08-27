<?php
declare(strict_types=1);

// Lightweight production health endpoint. It must fail when the runtime
// configuration or database is unavailable so deployment smoke tests cannot
// report a false positive.
try {
    require __DIR__ . '/config.php';
    db()->query('SELECT 1');
    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Ujian Online ' . app_version() . ' OK';
} catch (Throwable $e) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Ujian Online HEALTHCHECK FAILED';
}
