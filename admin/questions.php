<?php
declare(strict_types=1);
require __DIR__.'/../config.php';
require_once __DIR__.'/../includes/schema_sync.php';
require_login('admin');
ensure_matrix_disc_schema();

$id=(int)($_GET['id']??0);
$stmt=db()->prepare("SELECT * FROM exams WHERE id=?");
$stmt->execute([$id]);
$exam=$stmt->fetch();
if(!$exam) exit('Ujian tidak ditemukan.');

$qs=db()->prepare("SELECT * FROM questions WHERE exam_id=? ORDER BY sort_order,id");
$qs->execute([$id]);
$questions=$qs->fetchAll();

function question_image_url(?string $path): string {
    if(!$path) return '';
    $path=trim($path);
    if($path==='') return '';
    if(preg_match('~^https?://~i',$path)) return $path;
    $path=ltrim(str_replace('\\','/',$path),'/');
    return '../'.$path;
}
function active_question_options(array $q): array {
    $letters=['A','B','C','D','E','F','G','H'];
    $out=[];
    foreach($letters as $letter){
        $column='option_'.strtolower($letter);
        if(array_key_exists($column,$q) && trim((string)($q[$column]??''))!=='') $out[$letter]=(string)$q[$column];
    }
    return $out;
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kelola Soal</title><link rel="stylesheet" href="assets/admin-ui.css"><link rel="stylesheet" href="assets/ui-consistency-v6380.css?v=6.3.80"></head><body>
<div class="admin-layout"><aside class="admin-sidebar"><div class="admin-brand"><span class="mark">✓</span> Ujian Online</div><div class="admin-section">MENU UTAMA</div><nav class="admin-nav"><a href="index.php">⌂ Dashboard</a><a class="active" href="index.php#ujian-list">▣ Ujian</a><a href="participants.php">♟ Peserta</a><a href="templates.php">⇩ Template Import Soal</a></nav><div class="admin-section">SISTEM</div><nav class="admin-nav"><a href="update.php">↻ Update Sistem</a></nav></aside>
<main class="admin-main"><header class="admin-topbar"><div class="top-left"><button class="hamb menu-toggle">☰</button><h1>Kelola Soal</h1></div><div class="profile"><div class="avatar">A</div><div><b>Administrator</b><div class="ui-sub">Administrator</div></div></div></header><div class="admin-content">
<div class="page-head question-page-head"><div><div class="backline"><a href="index.php">← Daftar Ujian</a></div><h2 class="page-title"><?=htmlspecialchars($exam['title'])?></h2><p class="page-subtitle"><?=count($questions)?> soal · Tambah, edit, hapus, gunakan gambar, atau import soal.</p></div><div class="head-actions"><a class="ui-btn secondary" target="_blank" href="preview.php?id=<?=$id?>">👁 Preview</a><a class="ui-btn secondary" href="exam_link.php?id=<?=$id?>">🔗 Link Ujian</a></div></div>
<?php if(isset($_GET['created'])): ?><div class="flash success">Ujian berhasil dibuat. Silakan isi soal terlebih dahulu.</div><?php endif;?>
<?php if(isset($_GET['updated'])): ?><div class="flash success">Soal berhasil disimpan.</div><?php endif;?>
<?php if(isset($_GET['deleted'])): ?><div class="flash success">Soal berhasil dihapus.</div><?php endif;?>
<?php if(isset($_GET['imported'])): ?><div class="flash success"><?=htmlspecialchars((string)$_GET['imported'])?> soal berhasil diimport.</div><?php endif;?>
<div class="question-layout"><section class="question-list">
<div class="section-heading"><div><h3 class="card-title">Daftar Soal</h3><p class="ui-sub">Urutan soal mengikuti nomor yang tampil di ujian.</p></div><input id="questionSearch" class="list-search" placeholder="Cari isi soal..."></div>
<?php if(!$questions): ?><div class="ui-card empty-state"><b>Belum ada soal.</b><span>Gunakan form di sebelah kanan untuk menambahkan soal pertama.</span></div><?php endif;?>
<?php foreach($questions as $n=>$q): $activeOptions=active_question_options($q); ?><article class="ui-card question-card" data-search="<?=htmlspecialchars(strtolower($q['question_text']))?>"><div class="question-card-head"><div class="question-number"><?=($n+1)?></div><div class="question-meta"><span class="type-pill <?=$q['type']==='essay'?'essay':''?>"><?=$q['type']==='essay'?'Essay':($q['type']==='matrix_disc'?'Matriks / DISC':'Pilihan Ganda')?></span><span><?=$q['points']?> poin</span></div><div class="question-actions"><a class="action-btn primary" href="edit_question.php?id=<?=$q['id']?>">Edit</a><form method="post" action="delete_question.php" class="inline-form" onsubmit="return confirm('Hapus soal ini?')"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>"><input type="hidden" name="id" value="<?=$q['id']?>"><button class="action-btn danger">Hapus</button></form></div></div>
<div class="question-text"><?=nl2br(htmlspecialchars($q['question_text']))?></div>
<?php if(!empty($q['question_image'])): ?><img class="question-image" src="<?=htmlspecialchars(question_image_url($q['question_image']))?>" alt="Gambar soal"><?php endif;?>
<?php if($q['type']==='mcq' || $q['type']==='matrix_disc'): ?><div class="option-grid"><?php foreach($activeOptions as $k=>$value): ?><div class="option-item <?=$q['correct_option']===$k?'correct':''?>"><b><?=$k?></b><span><?=htmlspecialchars($value)?></span><?php if($q['type']==='mcq' && $q['correct_option']===$k): ?><em>✓ Benar</em><?php endif;?><?php if($q['type']==='matrix_disc' && ($q['matrix_correct_mirip']??'')===$k): ?><em>MIRIP</em><?php endif;?><?php if($q['type']==='matrix_disc' && ($q['matrix_correct_tidak']??'')===$k): ?><em>TIDAK MIRIP</em><?php endif;?></div><?php endforeach;?></div><?php else: ?><div class="essay-box">Jawaban essay dinilai melalui hasil ujian.</div><?php endif;?></article><?php endforeach;?>
<div id="noQuestionResult" class="ui-card empty-state" style="display:none"><b>Soal tidak ditemukan.</b></div>
</section>
<aside class="question-side">
<section class="ui-card add-question-card">
<h3>+ Tambah Soal</h3><p class="ui-sub">Buat soal baru untuk ujian ini.</p>
<form method="post" action="save_question.php" enctype="multipart/form-data" class="exam-form">
<input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>"><input type="hidden" name="exam_id" value="<?=$id?>">
<label>Jenis soal</label><select name="type" id="questionType"><option value="mcq">Pilihan Ganda</option><option value="essay">Essay</option><option value="matrix_disc">Matriks / DISC</option></select>
<label>Pertanyaan</label><textarea name="question_text" required placeholder="Tulis pertanyaan..."></textarea>
<label>Gambar soal <span class="ui-sub">(opsional)</span></label><input type="file" name="question_image" accept="image/jpeg,image/png,image/webp,image/gif">
<div id="mcqFields"><div id="optionList" class="form-grid compact-grid"></div><div style="display:flex;gap:8px;margin:10px 0"><button type="button" class="ui-btn secondary" id="addOption">+ Tambah Pilihan</button><button type="button" class="ui-btn secondary" id="removeOption">− Kurangi Pilihan</button></div></div>
<div id="gradingFields"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="use_answer_key" id="useAnswerKey" checked> Gunakan Kunci Jawaban &amp; Poin</label><div id="gradingDetails"><div id="standard-answer-key"><label>Kunci jawaban</label><select name="correct_option" id="correctOption"></select></div></div></div>
<div id="matrixFields" style="display:none"><p class="ui-sub" style="margin:8px 0 10px">Peserta memilih satu jawaban untuk MIRIP dan satu untuk TIDAK MIRIP.</p><div class="form-grid compact-grid"><div><label>Kunci MIRIP</label><select name="matrix_correct_mirip"><option>A</option><option>B</option><option>C</option><option>D</option></select></div><div><label>Kunci TIDAK MIRIP</label><select name="matrix_correct_tidak"><option>A</option><option>B</option><option>C</option><option>D</option></select></div></div></div>
<div id="essayFields" style="display:none"><label>Jawaban acuan <span class="ui-sub">(opsional)</span></label><textarea name="essay_answer_key" placeholder="Kunci atau jawaban yang diharapkan"></textarea></div><button class="ui-btn full-btn" type="submit">Simpan Soal</button>
</form></section>
<section class="ui-card import-card"><h3>⇩ Import Soal</h3><p class="ui-sub">CSV, XLS, atau XLSX.</p><form method="post" action="import_questions.php" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>"><input type="hidden" name="exam_id" value="<?=$id?>"><input type="file" name="file" required accept=".csv,.xls,.xlsx"><button class="ui-btn secondary full-btn" type="submit">Import</button></form></section>
</aside></div></div><footer class="admin-footer"><span>© <?=date('Y')?> Ujian Online</span><span>Versi <?=htmlspecialchars(app_version())?></span></footer></main></div>
<script>
document.querySelector('.menu-toggle').onclick=()=>document.body.classList.toggle('sidebar-open');
const type=document.getElementById('questionType'),optionList=document.getElementById('optionList'),correct=document.getElementById('correctOption'),useKey=document.getElementById('useAnswerKey');
let count=4;const letters='ABCDEFGH';
function renderOptions(){
  optionList.innerHTML='';correct.innerHTML='';
  for(let i=0;i<count;i++){
    let k=letters[i];
    optionList.insertAdjacentHTML('beforeend',`<div><label>Pilihan ${k}</label><input name="${k}" placeholder="Jawaban ${k}" ${i<2?'required':''}></div>`);
    correct.insertAdjacentHTML('beforeend',`<option value="${k}">${k}</option>`);
  }
  document.getElementById('addOption').disabled=count>=8;
  document.getElementById('removeOption').disabled=count<=2;
}
function sync(){
  const v=type.value,isMatrix=v==='matrix_disc',isEssay=v==='essay';
  document.getElementById('mcqFields').style.display=(v==='mcq'||isMatrix)?'block':'none';
  document.getElementById('essayFields').style.display=isEssay?'block':'none';
  document.getElementById('matrixFields').style.display=isMatrix?'block':'none';
  document.getElementById('gradingFields').style.display=isMatrix?'none':'block';
  if(isMatrix)useKey.checked=false;
  document.getElementById('gradingDetails').style.display=useKey.checked?'block':'none';
  document.getElementById('standard-answer-key').style.display=v==='mcq'?'block':'none';
  optionList.querySelectorAll('input').forEach((input,i)=>{ input.required = !isEssay && i<2; });
}
renderOptions();type.onchange=sync;useKey.onchange=sync;
document.getElementById('addOption').onclick=()=>{if(count<8){count++;renderOptions();sync()}};
document.getElementById('removeOption').onclick=()=>{if(count>2){count--;renderOptions();sync()}};
sync();
document.getElementById('questionSearch').oninput=function(){let q=this.value.toLowerCase(),n=0;document.querySelectorAll('.question-card').forEach(x=>{let ok=x.dataset.search.includes(q);x.style.display=ok?'':'none';if(ok)n++});document.getElementById('noQuestionResult').style.display=n?'none':''};
</script>
</body></html>