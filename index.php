<?php
declare(strict_types=1);

// Public root entrypoint. Keep this file intentionally small so it cannot
// depend on deployment-specific filesystem paths.
require __DIR__ . '/config.php';

if (!empty($_SESSION['user']) && ($_SESSION['user']['role'] ?? null) === 'admin') {
    header('Location: ' . app_url('admin/index.php'));
    exit;
}

header('Location: ' . app_url('login.php'));
exit;
