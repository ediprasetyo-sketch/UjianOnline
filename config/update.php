<?php
declare(strict_types=1);

return [
    'manifest_url' => getenv('EXAMFLOW_UPDATE_MANIFEST') ?: '',
    'allow_local_update' => true,
    'backup_dir' => __DIR__ . '/../storage/backups',
    'temp_dir' => __DIR__ . '/../storage/update',
];
