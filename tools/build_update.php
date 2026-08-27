<?php
declare(strict_types=1);

// CLI: php tools/build_update.php
// Reads VERSION.txt, validates the source tree, and creates a clean update ZIP.
$root = realpath(__DIR__ . '/..');
if ($root === false) exit("Project root tidak ditemukan.\n");

$version = trim((string)@file_get_contents($root . '/VERSION.txt'));
if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
    exit("VERSION.txt tidak valid: {$version}\n");
}

$required = [
    'VERSION.txt',
    'update-manifest.json',
    'index.php',
    'login.php',
    'logout.php',
    'config.php',
    'admin/index.php',
    'admin/update.php',
    'admin/participants.php',
    'peserta/index.php',
    'peserta/access.php',
];
foreach ($required as $rel) {
    if (!is_file($root . '/' . $rel)) exit("FILE WAJIB HILANG: {$rel}\n");
}

$manifest = json_decode((string)file_get_contents($root . '/update-manifest.json'), true);
if (!is_array($manifest) || trim((string)($manifest['version'] ?? '')) !== $version) {
    exit("update-manifest.json tidak sinkron dengan VERSION.txt.\n");
}

// Never allow deployment-specific filesystem paths into the package source.
$itLint = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($itLint as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if (str_starts_with($rel, 'storage/') || str_starts_with($rel, 'releases/') || str_starts_with($rel, '.git/')) continue;
    $src = (string)file_get_contents($file->getPathname());
    if (str_contains($src, '/volume1/web/ujian-online')) {
        exit("HARD-CODED PATH ditemukan pada {$rel}. Paket dibatalkan.\n");
    }
}

$outDir = $root . '/release';
@mkdir($outDir, 0775, true);
$out = $outDir . '/ujian-online-v' . $version . '-update.zip';

function excluded_release_path(string $rel): bool {
    $rel = str_replace('\\', '/', $rel);
    $prefixes = ['storage/', 'uploads/', 'release/', 'releases/', '.git/', 'node_modules/'];
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

echo "UPDATE PACKAGE OK\n";
echo "VERSION={$version}\n";
echo "FILE={$out}\n";
