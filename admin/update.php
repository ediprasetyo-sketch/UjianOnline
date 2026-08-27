<?php
require_once __DIR__ . '/../config.php';
require_login('admin');

$root = realpath(__DIR__ . '/..');
$backupDir = $root . '/storage/backups';
$uploadDir = $root . '/storage/update_uploads';
$stagingRoot = $root . '/storage/update_staging';
@mkdir($backupDir, 0775, true);
@mkdir($uploadDir, 0775, true);
@mkdir($stagingRoot, 0775, true);
foreach (glob($uploadDir.'/*.zip') ?: [] as $old) {
    if (is_file($old) && filemtime($old) < time() - 7*86400) @unlink($old);
}

function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    @rmdir($dir);
}
function safe_zip_extract($zipFile, $target) {
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) throw new RuntimeException('File ZIP tidak dapat dibuka.');
    if ($zip->numFiles > 5000) throw new RuntimeException('Paket update terlalu banyak file.');
    $total = 0;
    for ($i=0; $i<$zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i); $stat = $zip->statIndex($i); $total += (int)($stat['size'] ?? 0);
        if ($total > 500 * 1024 * 1024) throw new RuntimeException('Ukuran hasil extract terlalu besar.');
        if ($name === false || $name === '' || str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('#^[A-Za-z]:#', $name) || preg_match('#(^|/)\.\.(/|$)#', str_replace('\\','/',$name))) throw new RuntimeException('Paket ZIP memiliki path tidak aman.');
    }
    if (!$zip->extractTo($target)) throw new RuntimeException('Gagal extract paket update.');
    $zip->close();
}
function find_project_dir($dir) {
    if (is_file($dir . '/VERSION.txt')) return $dir;
    $items = glob($dir . '/*', GLOB_ONLYDIR);
    if (count($items) === 1 && is_file($items[0] . '/VERSION.txt')) return $items[0];
    throw new RuntimeException('VERSION.txt tidak ditemukan pada paket update.');
}
function validate_update_manifest($pkg, $version) {
    $file = $pkg . '/update-manifest.json';
    if (!is_file($file)) throw new RuntimeException('update-manifest.json tidak ditemukan. Paket update tidak dapat diverifikasi.');
    $raw = file_get_contents($file);
    $data = json_decode((string)$raw, true);
    if (!is_array($data) || !isset($data['version'])) throw new RuntimeException('update-manifest.json tidak valid.');
    $manifestVersion = trim((string)$data['version']);
    if ($manifestVersion !== $version) throw new RuntimeException('Versi manifest ('.$manifestVersion.') tidak sama dengan VERSION.txt ('.$version.'). Paket mungkin tidak lengkap atau dibuat dari source yang tidak sinkron.');
}
function copy_tree($from, $to, $exclude=[]) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $item) {
        $src = $item->getPathname(); $rel = substr($src, strlen($from)+1); $relNorm = str_replace('\\','/',$rel);
        foreach ($exclude as $x) if ($relNorm === $x || str_starts_with($relNorm, rtrim($x,'/') . '/')) continue 2;
        $dst = $to . '/' . $rel;
        if ($item->isDir()) { if (!is_dir($dst)) mkdir($dst, 0775, true); }
        else { if (!is_dir(dirname($dst))) mkdir(dirname($dst), 0775, true); if (!copy($src,$dst)) throw new RuntimeException('Gagal menyalin: '.$relNorm); }
    }
}
function create_backup($root, $backupDir) {
    $name='before_update_'.date('Ymd_His').'.zip'; $file=$backupDir.'/'.$name; $zip=new ZipArchive();
    if ($zip->open($file, ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) throw new RuntimeException('Gagal membuat backup.');
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($it as $item) { $path=$item->getPathname(); $rel=substr($path,strlen($root)+1); $n=str_replace('\\','/',$rel); if (str_starts_with($n,'storage/backups/') || str_starts_with($n,'storage/update_uploads/') || str_starts_with($n,'storage/update_staging/')) continue; $zip->addFile($path,$rel); }
    $zip->close(); return $file;
}
function csrf_input() { return '<input type="hidden" name="csrf" value="'.htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8').'">'; }

$message=''; $error=''; $details=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        check_csrf();
        if (isset($_POST['upload_update'])) {
            if (!isset($_FILES['update_zip']) || $_FILES['update_zip']['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Pilih file ZIP update yang valid.');
            $f=$_FILES['update_zip']; if ($f['size'] > 100*1024*1024) throw new RuntimeException('Ukuran ZIP maksimal 100 MB.');
            $tmpInfo = new finfo(FILEINFO_MIME_TYPE); $mime = $tmpInfo->file($f['tmp_name']); $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if ($ext !== 'zip' || !in_array($mime,['application/zip','application/x-zip-compressed','application/octet-stream'],true)) throw new RuntimeException('File harus berupa ZIP.');
            $id=bin2hex(random_bytes(8)); $stored=$uploadDir.'/update_'.$id.'.zip'; if (!move_uploaded_file($f['tmp_name'],$stored)) throw new RuntimeException('Gagal menyimpan file upload.');
            $stage=$stagingRoot.'/'.$id; mkdir($stage,0775,true); safe_zip_extract($stored,$stage); $pkg=find_project_dir($stage);
            $new=trim((string)file_get_contents($pkg.'/VERSION.txt')); if (!preg_match('/^\d+\.\d+\.\d+$/',$new)) throw new RuntimeException('Format VERSION.txt tidak valid.');
            validate_update_manifest($pkg, $new);
            if (version_compare($new, app_version(), '<=')) throw new RuntimeException('Versi paket ('.$new.') harus lebih baru dari versi aplikasi ('.app_version().').');
            $backup=create_backup($root,$backupDir); $exclude=['config.php','storage','uploads','release','.git']; copy_tree($pkg,$root,$exclude);
            file_put_contents($root.'/VERSION.txt',$new.PHP_EOL,LOCK_EX);
            $message='Update berhasil diinstal ke versi '.$new.'.'; $details=['Backup: '.basename($backup),'Paket: '.basename($stored),'Versi baru: '.$new,'Manifest tervalidasi']; rrmdir($stage);
        }
    } catch (Throwable $e) { $error=$e->getMessage(); }
}
$current = is_file($root.'/VERSION.txt') ? trim(file_get_contents($root.'/VERSION.txt')) : app_version();
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Update Sistem — Ujian Online</title><link rel="stylesheet" href="assets/admin-ui.css"></head><body class="update-page">
<div class="admin-layout"><aside class="admin-sidebar"><div class="admin-brand"><span class="mark">✓</span> Ujian Online</div><div class="admin-section">MENU UTAMA</div><nav class="admin-nav"><a href="index.php"><span class="ico">⌂</span>Dashboard</a><a href="index.php#ujian-list"><span class="ico">▣</span>Ujian</a><a href="participants.php"><span class="ico">♟</span>Peserta</a><a href="templates.php"><span class="ico">⇩</span>Template Import Soal</a></nav><div class="admin-section">SISTEM</div><nav class="admin-nav"><a class="active" href="update.php"><span class="ico">↻</span>Update Sistem</a></nav><div class="system-box"><h4>Informasi Sistem</h4><div class="system-row"><span>Versi Aplikasi</span><span class="version-chip"><?=htmlspecialchars($current)?></span></div><div class="system-row"><span>Update ZIP</span><span class="prod-chip">Aktif</span></div><div class="system-row"><span>Environment</span><span class="prod-chip">Production</span></div></div></aside>
<main class="admin-main"><header class="admin-topbar"><div class="top-left"><button class="hamb menu-toggle" type="button">☰</button><h1>Update Sistem</h1></div><div class="profile"><div class="avatar"><?=htmlspecialchars(strtoupper(substr((string)($_SESSION['user']['full_name']??'A'),0,1)))?></div><div><b><?=htmlspecialchars($_SESSION['user']['full_name']??'Administrator')?></b><div class="ui-sub">Administrator</div></div><a class="logout-link" href="../logout.php">Keluar</a></div></header><div class="admin-content update-content"><div class="page-head"><div><h2 class="page-title">Update Sistem</h2><p class="page-subtitle">Kelola pembaruan aplikasi dengan aman menggunakan paket ZIP.</p></div><div class="crumb">⌂ Beranda / Sistem / Update</div></div>
<?php if($message): ?><div class="update-alert success"><b>✓ <?=htmlspecialchars($message)?></b><ul><?php foreach($details as $d): ?><li><?=htmlspecialchars($d)?></li><?php endforeach;?></ul></div><?php endif;?><?php if($error): ?><div class="update-alert error"><b>⚠ Update gagal:</b> <?=htmlspecialchars($error)?></div><?php endif;?>
<section class="update-status-grid"><div class="update-status-card"><div class="update-status-icon blue">↻</div><div><span>VERSI SAAT INI</span><strong><?=htmlspecialchars($current)?></strong><small>Versi yang sedang digunakan</small></div></div><div class="update-status-card"><div class="update-status-icon purple">⇧</div><div><span>VERSI PAKET</span><strong id="zipVersion">—</strong><small id="zipVersionText">Pilih file ZIP untuk memeriksa</small></div></div><div class="update-status-card"><div class="update-status-icon orange">▣</div><div><span>SUMBER UPDATE</span><strong>File ZIP</strong><small>Upload manual melalui admin</small></div></div><div class="update-status-card"><div class="update-status-icon green">✓</div><div><span>STATUS SISTEM</span><strong>Siap</strong><small>Backup otomatis aktif</small></div></div></section>
<section class="update-layout"><div class="ui-card update-install-card"><div class="card-head"><div><h3>Install Update</h3><p>Upload paket pembaruan untuk memperbarui aplikasi.</p></div><span class="secure-badge">🛡 Aman</span></div><form method="post" enctype="multipart/form-data" id="updateForm"><?=csrf_input()?><input type="hidden" name="upload_update" value="1"><label class="drop-zone" for="update_zip" id="dropZone"><input id="update_zip" type="file" name="update_zip" accept=".zip,application/zip" required><span class="upload-icon">⇧</span><b id="fileTitle">Klik untuk memilih file ZIP</b><small id="fileName">atau tarik file ke area ini · Maks. 100 MB</small></label><div class="update-note"><span>🛡</span><div><b>Backup otomatis sebelum update</b><small>Salinan aplikasi dibuat terlebih dahulu sehingga perubahan dapat dipulihkan bila diperlukan.</small></div></div><div class="update-actions"><button class="ui-btn secondary" type="reset" id="clearUpdate">Batal</button><button class="ui-btn install-btn" type="submit">↻ Upload & Install Update</button></div></form><div class="requirements"><h4>Persyaratan Paket Update</h4><div class="requirement-grid"><span>✓ Format ZIP</span><span>✓ VERSION.txt sinkron</span><span>✓ Manifest tervalidasi</span><span>✓ Versi lebih baru</span></div></div></div><div class="update-side"><div class="ui-card guide-card"><h3>Panduan Update</h3><div class="guide-step"><i>1</i><div><b>Pilih paket update</b><span>Pilih file ZIP versi terbaru.</span></div></div><div class="guide-step"><i>2</i><div><b>Validasi otomatis</b><span>Sistem memeriksa struktur, VERSION dan manifest.</span></div></div><div class="guide-step"><i>3</i><div><b>Backup dibuat</b><span>Aplikasi saat ini dicadangkan otomatis.</span></div></div><div class="guide-step"><i>4</i><div><b>Install update</b><span>File baru dipasang setelah lolos validasi.</span></div></div></div><div class="ui-card history-card"><div class="history-head"><h3>Riwayat Update</h3><span class="history-dot">● Aktif</span></div><div class="history-row"><div class="history-version">v<?=htmlspecialchars($current)?></div><div><b>Versi saat ini</b><span>Aplikasi aktif</span></div></div></div></div></section></div><footer class="admin-footer"><span>© <?=date('Y')?> Ujian Online. All rights reserved.</span><span>Versi <?=htmlspecialchars($current)?></span></footer></main></div>
<script>const fileInput=document.getElementById('update_zip'),drop=document.getElementById('dropZone');function renderFile(f){if(!f)return;document.getElementById('fileTitle').textContent=f.name;document.getElementById('fileName').textContent='Ukuran: '+(f.size/1024/1024).toFixed(2)+' MB · Siap divalidasi';document.getElementById('zipVersionText').textContent='Paket dipilih · VERSION dan manifest akan divalidasi saat instalasi';}fileInput.addEventListener('change',()=>renderFile(fileInput.files[0]));['dragenter','dragover'].forEach(e=>drop.addEventListener(e,x=>{x.preventDefault();drop.classList.add('drag')}));['dragleave','drop'].forEach(e=>drop.addEventListener(e,x=>{x.preventDefault();drop.classList.remove('drag')}));drop.addEventListener('drop',e=>{if(e.dataTransfer.files.length){fileInput.files=e.dataTransfer.files;renderFile(fileInput.files[0])}});document.getElementById('clearUpdate').addEventListener('click',()=>{document.getElementById('fileTitle').textContent='Klik untuk memilih file ZIP';document.getElementById('fileName').textContent='atau tarik file ke area ini · Maks. 100 MB';});document.querySelector('.menu-toggle').addEventListener('click',()=>document.body.classList.toggle('sidebar-open'));document.getElementById('updateForm').addEventListener('submit',e=>{if(!confirm('Backup otomatis akan dibuat sebelum update. Lanjutkan instalasi?'))e.preventDefault();});</script></body></html>