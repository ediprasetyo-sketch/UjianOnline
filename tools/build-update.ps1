$ErrorActionPreference = 'Stop'

# =========================================
# BUILD UPDATE PACKAGE
# =========================================

# Root project
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

# =========================================
# AMBIL VERSI
# =========================================

$versionFile = Join-Path $root 'VERSION.txt'

if (-not (Test-Path $versionFile)) {
    throw "VERSION.txt tidak ditemukan di: $root"
}

$version = (Get-Content $versionFile -Raw).Trim()

if ([string]::IsNullOrWhiteSpace($version)) {
    throw "VERSION.txt tidak valid atau kosong."
}

Write-Host ""
Write-Host "Membuat paket update versi $version..."
Write-Host ""

# =========================================
# FOLDER HASIL
# =========================================

$outDir = Join-Path $root 'release'

if (-not (Test-Path $outDir)) {
    New-Item -ItemType Directory -Path $outDir -Force | Out-Null
}

# Nama ZIP
$zip = Join-Path $outDir ("WebTestOnline-" + $version + ".zip")

# Hapus ZIP lama jika ada
if (Test-Path $zip) {
    Remove-Item $zip -Force
}

# =========================================
# FOLDER SEMENTARA
# =========================================

$tempRoot = [System.IO.Path]::GetTempPath()

$stage = Join-Path `
    $tempRoot `
    ("WebTestOnline-update-" + $version + "-" + [guid]::NewGuid().ToString("N"))

New-Item -ItemType Directory -Path $stage -Force | Out-Null

try {

    # =========================================
    # FILE/FOLDER YANG TIDAK MASUK UPDATE
    # =========================================

    $exclude = @(
        '.git',
        '.storage',
        'uploads',
        'release',
        'config.php'
    )

    # =========================================
    # COPY PROJECT KE STAGING
    # =========================================

    Get-ChildItem -Path $root -Force | Where-Object {
        $exclude -notcontains $_.Name
    } | ForEach-Object {

        Copy-Item `
            -Path $_.FullName `
            -Destination $stage `
            -Recurse `
            -Force
    }

    # =========================================
    # PASTIKAN VERSION.TXT ADA
    # =========================================

    $stageVersion = Join-Path $stage 'VERSION.txt'

    if (-not (Test-Path $stageVersion)) {

        # Salin ulang secara eksplisit
        Copy-Item `
            -Path $versionFile `
            -Destination $stageVersion `
            -Force
    }

    if (-not (Test-Path $stageVersion)) {
        throw "VERSION.txt gagal disalin ke folder paket sementara."
    }

    # =========================================
    # VALIDASI UPDATE-MANIFEST.JSON
    # =========================================

    $manifest = Join-Path $stage 'update-manifest.json'

    if (-not (Test-Path $manifest)) {
        throw "update-manifest.json tidak ditemukan di paket."
    }

    $manifestData = Get-Content $manifest -Raw | ConvertFrom-Json

    if ($manifestData.version -ne $version) {
        throw "Versi manifest ($($manifestData.version)) tidak sama dengan VERSION.txt ($version)."
    }

    # =========================================
    # VALIDASI ADMIN/UPDATE.PHP
    # =========================================

    $updateFile = Join-Path $stage 'admin\update.php'

    if (-not (Test-Path $updateFile)) {
        throw "admin/update.php tidak ditemukan di paket."
    }

    # =========================================
    # BUAT ZIP
    # =========================================

    Add-Type -AssemblyName System.IO.Compression.FileSystem

    [System.IO.Compression.ZipFile]::CreateFromDirectory(
        $stage,
        $zip,
        [System.IO.Compression.CompressionLevel]::Optimal,
        $false
    )

    # =========================================
    # VALIDASI ISI ZIP
    # =========================================

    $archive = [System.IO.Compression.ZipFile]::OpenRead($zip)

    try {

        $zipFiles = @(
            $archive.Entries | ForEach-Object {
                $_.FullName.Replace('\', '/').TrimStart('/')
            }
        )

        Write-Host ""
        Write-Host "Isi utama ZIP:"

        $zipFiles |
            Select-Object -First 30 |
            ForEach-Object {
                Write-Host " - $_"
            }

        Write-Host ""

        # VERSION.txt
        if ($zipFiles -notcontains 'VERSION.txt') {
            throw "ZIP hasil tidak memiliki VERSION.txt."
        }

        # update-manifest.json
        if ($zipFiles -notcontains 'update-manifest.json') {
            throw "ZIP hasil tidak memiliki update-manifest.json."
        }

        # admin/update.php
        if ($zipFiles -notcontains 'admin/update.php') {
            throw "ZIP hasil tidak memiliki admin/update.php."
        }

    }
    finally {

        $archive.Dispose()

    }

    # =========================================
    # BERHASIL
    # =========================================

    Write-Host ""
    Write-Host "========================================"
    Write-Host " PAKET UPDATE BERHASIL DIBUAT"
    Write-Host "========================================"
    Write-Host ""
    Write-Host "VERSI : $version"
    Write-Host "FILE  : $zip"
    Write-Host ""
    Write-Host "Validasi berhasil:"
    Write-Host "- VERSION.txt"
    Write-Host "- update-manifest.json"
    Write-Host "- admin/update.php"
    Write-Host ""

}
finally {

    # Bersihkan folder sementara
    if (Test-Path $stage) {
        Remove-Item `
            -Path $stage `
            -Recurse `
            -Force `
            -ErrorAction SilentlyContinue
    }

}