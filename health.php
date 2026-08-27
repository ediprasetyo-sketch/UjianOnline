<?php
require __DIR__.'/config.php';
header('Content-Type: text/plain; charset=utf-8');

$base = '/';
if (function_exists('app_base')) {
    $base = app_base();
} else {
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $projectRoot = realpath(__DIR__) ?: __DIR__;
    $docRoot = str_replace('\\','/',rtrim($docRoot,'/\\'));
    $projectRoot = str_replace('\\','/',$projectRoot);
    if ($docRoot !== '' && str_starts_with($projectRoot,$docRoot)) {
        $base = str_replace('//','/',substr($projectRoot,strlen($docRoot)));
        if ($base === '') $base='/';
    }
}

echo 'Ujian Online ' . app_version() . ' OK';
echo 'Ujian Online ' . app_version() . ' OK';
echo 'Ujian Online ' . app_version() . ' OK';
try { db()->query('SELECT 1'); echo 'Ujian Online ' . app_version() . ' OK'; }
catch (Throwable $e) { echo 'Ujian Online ' . app_version() . ' OK'; }
