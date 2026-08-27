<?php
declare(strict_types=1);
require __DIR__.'/../config.php'; require_login('admin');
$id=(int)($_GET['id']??$_POST['id']??0); if(!$id) exit('ID ujian tidak valid.');
$s=db()->prepare('SELECT * FROM exams WHERE id=? LIMIT 1');$s->execute([$id]);$exam=$s->fetch();if(!$exam)exit('Ujian tidak ditemukan.');
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf(); $title=trim((string)($_POST['title']??''));$duration=(int)($_POST['duration']??0);
 $start=str_replace('T',' ',(string)($_POST['start_at']??''));$end=str_replace('T',' ',(string)($_POST['end_at']??''));
 $mode=$_POST['question_mode']??'all';$active=(int)($_POST['active']??1);$rq=(int)($_POST['randomize_questions']??0);$ro=(int)($_POST['randomize_options']??0);
 if($title==='')exit('Judul ujian wajib diisi.');if($duration<1||$duration>1440)exit('Durasi harus antara 1 sampai 1440 menit.');
 $st=strtotime($start);$et=strtotime($end);if($st===false||$et===false||$et<=$st)exit('Jadwal ujian tidak valid.');
 if(!in_array($mode,['all','one_by_one'],true))$mode='all';if(!in_array($active,[0,1],true))$active=1;
 if(!in_array($rq,[0,1],true))$rq=0;if(!in_array($ro,[0,1],true))$ro=0;
 $u=db()->prepare('UPDATE exams SET title=?,duration_seconds=?,start_at=?,end_at=?,question_mode=?,active=?,randomize_questions=?,randomize_options=? WHERE id=?');
 $u->execute([$title,$duration*60,$start,$end,$mode,$active,$rq,$ro,$id]);
 header('Location: index.php?msg='.rawurlencode('Perubahan ujian berhasil disimpan.'));exit;
}
function dtv(string $v):string{return date('Y-m-d\\TH:i',strtotime($v));}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Edit Ujian</title><link rel="stylesheet" href="assets/admin-ui.css"></head><body>
<div class="admin-layout"><aside class="admin-sidebar"><div class="admin-brand"><span class="mark">✓</span> Ujian Online</div><div class="admin-section">MENU UTAMA</div><nav class="admin-nav"><a href="index.php">⌂ Dashboard</a><a class="active" href="index.php#ujian-list">▣ Ujian</a><a href="participants.php">♟ Peserta</a><a href="templates.php">⇩ Template Import Soal</a></nav><div class="admin-section">SISTEM</div><nav class="admin-nav"><a href="update.php">↻ Update Sistem</a></nav></aside>
<main class="admin-main"><header class="admin-topbar"><div class="top-left"><button class="hamb menu-toggle">☰</button><h1>Edit Ujian</h1></div><div class="profile"><div class="avatar">A</div><div><b>Administrator</b><div class="ui-sub">Administrator</div></div></div></header><div class="admin-content">
<div class="page-head"><div><h2 class="page-title"><?=htmlspecialchars($exam['title'])?></h2><p class="page-subtitle">Atur informasi, jadwal, status, dan perilaku ujian.</p></div><div class="crumb">⌂ Beranda / Ujian / Edit</div></div>
<div class="exam-editor-layout"><section class="ui-card editor-card"><div class="card-section-title"><h3>Informasi Ujian</h3><p>Perubahan akan langsung digunakan pada halaman ujian peserta.</p></div>
<form method="post" class="exam-form"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>"><input type="hidden" name="id" value="<?=$id?>">
<div class="form-grid"><div><label>Judul ujian</label><input name="title" value="<?=htmlspecialchars($exam['title'])?>" required></div><div><label>Durasi (menit)</label><input type="number" name="duration" min="1" max="1440" value="<?=floor($exam['duration_seconds']/60)?>" required></div><div><label>Mulai</label><input type="datetime-local" name="start_at" value="<?=dtv($exam['start_at'])?>" required></div><div><label>Selesai</label><input type="datetime-local" name="end_at" value="<?=dtv($exam['end_at'])?>" required></div></div>
<div class="form-grid"><div><label>Tampilan soal</label><select name="question_mode"><option value="all" <?=$exam['question_mode']==='all'?'selected':''?>>Semua sekaligus</option><option value="one_by_one" <?=$exam['question_mode']==='one_by_one'?'selected':''?>>Satu per satu</option></select></div><div><label>Status</label><select name="active"><option value="1" <?=$exam['active']?'selected':''?>>Aktif</option><option value="0" <?=!$exam['active']?'selected':''?>>Nonaktif</option></select></div><div><label>Randomisasi soal</label><select name="randomize_questions"><option value="0" <?=!$exam['randomize_questions']?'selected':''?>>Soal normal</option><option value="1" <?=$exam['randomize_questions']?'selected':''?>>Acak soal</option></select></div><div><label>Randomisasi pilihan</label><select name="randomize_options"><option value="0" <?=!$exam['randomize_options']?'selected':''?>>Normal</option><option value="1" <?=$exam['randomize_options']?'selected':''?>>Acak A-D</option></select></div></div>
<div class="form-actions"><a class="ui-btn secondary" href="index.php">← Kembali</a><a class="ui-btn secondary" href="questions.php?id=<?=$id?>">Kelola Soal</a><a class="ui-btn secondary" href="exam_link.php?id=<?=$id?>">Bagikan Link</a><button class="ui-btn">Simpan Perubahan</button></div></form></section>
<aside class="editor-side"><div class="ui-card side-info"><h3>Ringkasan</h3><div class="info-row"><span>Status</span><b><?=$exam['active']?'Aktif':'Nonaktif'?></b></div><div class="info-row"><span>Durasi</span><b><?=floor($exam['duration_seconds']/60)?> menit</b></div><div class="info-row"><span>Mode</span><b><?=htmlspecialchars($exam['question_mode'])?></b></div></div></aside></div>
</div><footer class="admin-footer"><span>© <?=date('Y')?> Ujian Online</span><span>Versi <?=htmlspecialchars(trim(@file_get_contents(__DIR__.'/../VERSION.txt'))?:'<?=app_version()?>')?></span></footer></main></div>
<script>document.querySelector('.menu-toggle').onclick=()=>document.body.classList.toggle('sidebar-open')</script></body></html>