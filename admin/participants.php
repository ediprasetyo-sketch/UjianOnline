<?php
declare(strict_types=1);
require __DIR__.'/../config.php';

if (!function_exists('participant_display_name')) {
    function participant_display_name(array $row): string {
        $name = trim((string)($row['full_name'] ?? ''));
        $email = trim((string)($row['email'] ?? $row['username'] ?? ''));
        if ($name === '' || ($email !== '' && strcasecmp($name, $email) === 0)) {
            return '';
        }
        return $name;
    }
}
if (!function_exists('participant_display_email')) {
    function participant_display_email(array $row): string {
        return trim((string)($row['email'] ?? $row['username'] ?? ''));
    }
}

require_login('admin');

$pdo=db();
$users=$pdo->query("SELECT id,full_name,username,participant_code,created_at FROM users WHERE role='participant' ORDER BY id DESC")->fetchAll();
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Peserta — Ujian Online</title><link rel="stylesheet" href="assets/admin-ui.css">
</head><body><div class="admin-layout">
<aside class="admin-sidebar">
<div class="admin-brand"><span class="mark">✓</span> Ujian Online</div>
<div class="admin-section">MENU UTAMA</div><nav class="admin-nav">
<a href="index.php"><span class="ico">⌂</span>Dashboard</a><a href="index.php#ujian-list"><span class="ico">▣</span>Ujian</a>
<a class="active" href="participants.php"><span class="ico">♟</span>Peserta</a><a href="templates.php"><span class="ico">⇩</span>Template Import Soal</a>
</nav><div class="admin-section">SISTEM</div><nav class="admin-nav"><a href="update.php"><span class="ico">↻</span>Update Sistem</a></nav>
<div class="system-box"><h4>Informasi Sistem</h4><div class="system-row"><span>Versi Aplikasi</span><span class="version-chip"><?=h(trim(@file_get_contents(__DIR__.'/../VERSION.txt')) ?: '<?=app_version()?>')?></span></div>
<div class="system-row"><span>Total Peserta</span><b><?=count($users)?></b></div><div class="system-row"><span>Environment</span><span class="prod-chip">Production</span></div></div>
</aside>

<main class="admin-main"><header class="admin-topbar"><div class="top-left"><button class="hamb menu-toggle" type="button">☰</button><h1>Peserta</h1></div>
<div class="profile"><div class="avatar"><?=h(strtoupper(substr((string)($_SESSION['user']['full_name']??'A'),0,1)))?></div><div><b><?=h($_SESSION['user']['full_name']??'Administrator')?></b><div class="ui-sub">Administrator</div></div><a class="logout-link" href="../logout.php">Keluar</a></div></header>

<div class="admin-content">
<?php if(!empty($_GET['msg'])):?><div class="flash success"><?=h($_GET['msg'])?></div><?php endif;?>
<?php if(!empty($_GET['error'])):?><div class="flash error"><?=h($_GET['error'])?></div><?php endif;?>
<div class="page-head"><div><h2 class="page-title">Kelola Peserta</h2><p class="page-subtitle">Tambah, edit, hapus, dan import data peserta ujian.</p></div><div class="crumb">⌂ Beranda / Peserta</div></div>

<section class="participant-summary">
<div class="ui-card summary-card"><div class="summary-icon">♟</div><div><div class="ui-label">TOTAL PESERTA</div><div class="ui-value"><?=count($users)?></div><div class="ui-sub">Peserta terdaftar</div></div></div>
<div class="participant-toolbar">
<button class="ui-btn" type="button" id="addParticipant">+ Tambah Peserta</button>
<button class="ui-btn secondary" type="button" id="importParticipant">⇩ Import CSV</button>
<a class="ui-btn secondary" href="participant_template.csv" download>⇩ Download Template</a>
</div></section>

<section class="ui-card card-pad participant-list">
<div class="section-heading"><div><h3 class="card-title">Daftar Peserta</h3><p class="ui-sub"><?=count($users)?> peserta tersedia di sistem.</p></div>
<div class="table-tools"><input id="participantSearch" type="search" placeholder="Cari nama, username, atau kode..."></div></div>
<div class="table-wrap"><table class="ui-table participant-table"><thead><tr><th>No</th><th>Peserta</th><th>Username / NIS</th><th>Kode Peserta</th><th>Dibuat</th><th>Aksi</th></tr></thead><tbody>
<?php foreach($users as $i=>$u): ?><tr data-search="<?=h(strtolower($u['full_name'].' '.$u['username'].' '.($u['participant_code']??'')))?>">
<td><?=$i+1?></td><td><div class="participant-cell"><b><?=h(participant_display_name($u)?:'Peserta')?></b><small><?=h(participant_display_email($u))?></small></div></td><td><?=h($u['username'])?></td><td><?=h($u['participant_code']?:'—')?></td><td><?=h(date('d M Y H:i',strtotime($u['created_at'])))?></td>
<td><div class="row-actions"><button class="action-btn primary editParticipant" type="button" data-id="<?=$u['id']?>" data-name="<?=h($u['full_name'])?>" data-username="<?=h($u['username'])?>" data-code="<?=h($u['participant_code']??'')?>">Edit</button>
<form method="post" action="delete_participant.php" class="inline-form" onsubmit="return confirm('Hapus peserta <?=h(addslashes($u['full_name']))?>?')"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="id" value="<?=$u['id']?>"><button class="action-btn danger" type="submit">Hapus</button></form></div></td>
</tr><?php endforeach;?>
<tr id="noParticipantRow" <?=count($users)?'style="display:none"':''?>><td colspan="6"><div class="empty"><span class="info-i">i</span><div><b>Peserta tidak ditemukan.</b><br><span class="ui-sub">Coba ubah kata kunci pencarian.</span></div></div></td></tr>
</tbody></table></div></section>
</div><footer class="admin-footer"><span>© <?=date('Y')?> Ujian Online. All rights reserved.</span><span>Versi <?=h(trim(@file_get_contents(__DIR__.'/../VERSION.txt')) ?: '<?=app_version()?>')?></span></footer></main></div>

<div class="modal" id="participantModal" aria-hidden="true"><div class="modal-backdrop" data-close-modal></div><div class="modal-card participant-modal"><button class="modal-close" type="button" data-close-modal>×</button>
<h2 id="participantModalTitle">Tambah Peserta</h2><p class="ui-sub">Isi data peserta. Password dapat diubah saat proses edit.</p>
<form method="post" action="save_participant.php" class="exam-form"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="id" id="participantId">
<div><label>Nama lengkap</label><input id="participantName" name="full_name" required placeholder="Nama lengkap peserta"></div>
<div class="form-grid"><div><label>Username / NIS</label><input id="participantUsername" name="username" required placeholder="Username atau NIS"></div><div><label>Kode peserta</label><input id="participantCode" name="participant_code" placeholder="Opsional"></div></div>
<div><label>Password <span class="ui-sub" id="passwordHint">(minimal 6 karakter)</span></label><input id="participantPassword" type="password" name="password" minlength="6" placeholder="Password awal peserta"></div>
<div class="modal-actions"><button type="button" class="ui-btn secondary" data-close-modal>Batal</button><button type="submit" class="ui-btn" id="participantSubmit">Simpan Peserta</button></div>
</form></div></div>

<div class="modal" id="importModal" aria-hidden="true"><div class="modal-backdrop" data-close-import></div><div class="modal-card participant-modal"><button class="modal-close" type="button" data-close-import>×</button>
<h2>Import Peserta dari CSV</h2><p class="ui-sub">Gunakan template agar format kolom sesuai.</p><div class="ui-alert info">Kolom yang digunakan: <b>full_name, username, participant_code, password</b>.</div>
<form method="post" action="import_participants.php" enctype="multipart/form-data" class="exam-form"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
<label>Pilih file CSV</label><input type="file" name="file" accept=".csv,text/csv" required><div class="modal-actions"><a class="ui-btn secondary" href="participant_template.csv" download>Download Template</a><button class="ui-btn" type="submit">Import Peserta</button></div></form>
</div></div>

<script>
const pModal=document.getElementById('participantModal'),iModal=document.getElementById('importModal');
function openModal(m){m.classList.add('show');m.setAttribute('aria-hidden','false')}
function closeModal(m){m.classList.remove('show');m.setAttribute('aria-hidden','true')}
document.getElementById('addParticipant').onclick=()=>{document.getElementById('participantModalTitle').textContent='Tambah Peserta';document.getElementById('participantId').value='';document.getElementById('participantName').value='';document.getElementById('participantUsername').value='';document.getElementById('participantCode').value='';document.getElementById('participantPassword').value='';document.getElementById('participantPassword').required=true;document.getElementById('passwordHint').textContent='(minimal 6 karakter)';document.getElementById('participantSubmit').textContent='Simpan Peserta';openModal(pModal)};
document.querySelectorAll('.editParticipant').forEach(b=>b.onclick=()=>{document.getElementById('participantModalTitle').textContent='Edit Peserta';document.getElementById('participantId').value=b.dataset.id;document.getElementById('participantName').value=b.dataset.name;document.getElementById('participantUsername').value=b.dataset.username;document.getElementById('participantCode').value=b.dataset.code;document.getElementById('participantPassword').value='';document.getElementById('participantPassword').required=false;document.getElementById('passwordHint').textContent='(kosongkan jika password tidak diubah)';document.getElementById('participantSubmit').textContent='Simpan Perubahan';openModal(pModal)});
document.getElementById('importParticipant').onclick=()=>openModal(iModal);
document.querySelectorAll('[data-close-modal]').forEach(b=>b.onclick=()=>closeModal(pModal));document.querySelectorAll('[data-close-import]').forEach(b=>b.onclick=()=>closeModal(iModal));
document.querySelector('.menu-toggle').onclick=()=>document.body.classList.toggle('sidebar-open');
document.getElementById('participantSearch').addEventListener('input',function(){const q=this.value.trim().toLowerCase();let visible=0;document.querySelectorAll('.participant-table tbody tr[data-search]').forEach(r=>{const show=!q||r.dataset.search.includes(q);r.style.display=show?'':'none';if(show)visible++});document.getElementById('noParticipantRow').style.display=visible?'none':''});
</script></body></html>