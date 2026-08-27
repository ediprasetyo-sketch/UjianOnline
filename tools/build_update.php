<?php
declare(strict_types=1);

// CLI: php tools/build_update.php 6.3.62
// Creates a clean update package containing application code only.
$version = $argv[1] ?? '';
if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
    exit("Usage: php tools/build_update.php x.y.z\n");
}
$root = realpath(__DIR__ . '/..');
$outDir = $root . '/releases';
@mkdir($outDir, 0775, true);
$out = $outDir . '/ujian-online-v' . $version . '-update.zip';

function excluded_release_path(string $rel): bool {
    $rel = str_replace('\\', '/', $rel);
    $prefixes = ['storage/', 'uploads/', 'releases/', '.git/', 'node_modules/'];
    foreach ($prefixes as $prefix) if (str_starts_with($rel, $prefix)) return true;
    return in_array($rel, ['config.php'], true) || str_ends_with(strtolower($rel), '.zip');
}

$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    exit("Cannot create update package\n");
}
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
foreach ($it as $file) {
    if (!$file->isFile()) continue;
    $path = $file->getPathname();
    $rel = str_replace('\\', '/', substr($path, strlen($root) + 1));
    if (excluded_release_path($rel)) continue;
    $zip->addFile($path, $rel);
}
$zip->close();
echo $out . PHP_EOL;
