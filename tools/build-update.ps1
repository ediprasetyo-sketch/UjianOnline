$ErrorActionPreference = 'Stop'

# =========================================
# BUILD UPDATE PACKAGE - PRODUCTION SAFE
# =========================================
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$versionFile = Join-Path $root 'VERSION.txt'
if (-not (Test-Path $versionFile)) { throw "VERSION.txt tidak ditemukan di: $root" }
$version = (Get-Content $versionFile -Raw).Trim()
if ($version -notmatch '^\d+\.\d+\.\d+$') { throw "VERSION.txt tidak valid: $version" }

$required = @(
    'VERSION.txt', 'update-manifest.json', 'index.php', 'login.php', 'logout.php',
    'config.php', 'admin\index.php', 'admin\update.php', 'admin\participants.php',
    'peserta\index.php', 'peserta\access.php'
)
foreach ($rel in $required) {
    if (-not (Test-Path (Join-Path $root $rel) -PathType Leaf)) {
        throw "FILE WAJIB HILANG: $rel"
    }
}

$manifestPath = Join-Path $root 'update-manifest.json'
$manifestData = Get-Content $manifestPath -Raw | ConvertFrom-Json
if ($manifestData.version -ne $version) {
    throw "Versi manifest ($($manifestData.version)) tidak sama dengan VERSION.txt ($version)."
}

# Static source guard: deployment-specific absolute paths are forbidden.
$phpFiles = Get-ChildItem -Path $root -Recurse -File -Filter '*.php' | Where-Object {
    $_.FullName -notmatch '\\(\.git|storage|release|releases)\\'
}
foreach ($file in $phpFiles) {
    $src = Get-Content $file.FullName -Raw
    if ($src -match '/volume1/web/ujian-online') {
        $rel = $file.FullName.Substring($root.Length + 1).Replace('\','/')
        throw "HARD-CODED PATH ditemukan pada $rel."
    }
}

$outDir = Join-Path $root 'release'
New-Item -ItemType Directory -Path $outDir -Force | Out-Null
$zip = Join-Path $outDir ("WebTestOnline-" + $version + ".zip")
if (Test-Path $zip) { Remove-Item $zip -Force }

$tempRoot = [System.IO.Path]::GetTempPath()
$stage = Join-Path $tempRoot ("WebTestOnline-update-" + $version + "-" + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $stage -Force | Out-Null

try {
    $exclude = @('.git', '.storage', 'storage', 'uploads', 'release', 'releases', 'config.php')
    Get-ChildItem -Path $root -Force | Where-Object { $exclude -notcontains $_.Name } | ForEach-Object {
        Copy-Item -Path $_.FullName -Destination $stage -Recurse -Force
    }

    $stageVersion = Join-Path $stage 'VERSION.txt'
    if (-not (Test-Path $stageVersion)) { throw 'VERSION.txt gagal disalin ke paket.' }

    $stageManifest = Join-Path $stage 'update-manifest.json'
    if (-not (Test-Path $stageManifest)) { throw 'update-manifest.json gagal disalin ke paket.' }
    $stageManifestData = Get-Content $stageManifest -Raw | ConvertFrom-Json
    if ($stageManifestData.version -ne $version) { throw 'Manifest staging tidak sinkron.' }

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [System.IO.Compression.ZipFile]::CreateFromDirectory($stage, $zip, [System.IO.Compression.CompressionLevel]::Optimal, $false)

    $archive = [System.IO.Compression.ZipFile]::OpenRead($zip)
    try {
        $zipFiles = @($archive.Entries | ForEach-Object { $_.FullName.Replace('\','/').TrimStart('/') })
        foreach ($rel in @('VERSION.txt','update-manifest.json','index.php','login.php','admin/update.php','admin/index.php','peserta/index.php')) {
            if ($zipFiles -notcontains $rel) { throw "ZIP hasil tidak memiliki $rel" }
        }
    } finally { $archive.Dispose() }

    Write-Host ''
    Write-Host '========================================'
    Write-Host ' PAKET UPDATE BERHASIL DIBUAT'
    Write-Host '========================================'
    Write-Host "VERSI : $version"
    Write-Host "FILE  : $zip"
    Write-Host 'Validasi: VERSION + manifest + entrypoint + hard-coded path = OK'
}
finally {
    if (Test-Path $stage) { Remove-Item -Path $stage -Recurse -Force -ErrorAction SilentlyContinue }
}
