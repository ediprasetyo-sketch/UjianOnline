<?php
declare(strict_types=1);
require __DIR__.'/../config.php';
require_login('admin');

$attemptId=(int)($_GET['attempt_id']??0);
if($attemptId<1) exit('Attempt tidak ditemukan.');

$d=db()->prepare("SELECT a.*,u.full_name,u.email,u.username,e.title FROM attempts a JOIN users u ON u.id=a.user_id JOIN exams e ON e.id=a.exam_id WHERE a.id=? LIMIT 1");
$d->execute([$attemptId]);
$detail=$d->fetch();
if(!$detail) exit('Attempt tidak ditemukan.');

$q=db()->prepare("SELECT q.id,q.type,q.question_text,q.question_image,q.points,q.use_answer_key,q.essay_answer_key,a.essay_answer,a.essay_score FROM questions q LEFT JOIN answers a ON a.question_id=q.id AND a.attempt_id=? WHERE q.exam_id=? AND q.type='essay' ORDER BY q.sort_order,q.id");
$q->execute([$attemptId,(int)$detail['exam_id']]);
$questions=$q->fetchAll();
$enabled=array_values(array_filter($questions,fn($x)=>(int)$x['use_answer_key']===1 && (float)$x['points']>0));
$graded=count(array_filter($enabled,fn($x)=>$x['essay_score']!==null));
$remaining=count($enabled)-$graded;
$maxTotal=array_sum(array_map(fn($x)=>(float)$x['points'],$enabled));
$gradedTotal=array_sum(array_map(fn($x)=>$x['essay_score']===null?0:(float)$x['essay_score'],$enabled));
$percent=$maxTotal>0?($gradedTotal/$maxTotal*100):0;
$focusId=(int)($_GET['focus']??0);
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function question_image_url(?string $path): string {
    if(!$path) return '';
    $path=trim($path);
    if($path==='') return '';
    if(preg_match('~^https?://~i',$path)) return $path;
    $path=ltrim(str_replace('\\','/',$path),'/');
    return '../'.$path;
}
$return='essay_grading.php?attempt_id='.$attemptId;
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Penilaian Essay — <?=h($detail['title'])?></title><link rel="stylesheet" href="assets/admin-ui.css?v=<?=urlencode(app_version())?>"><style>
body{background:#f4f7fb}.eg-wrap{max-width:1180px;margin:0 auto;padding:28px}.eg-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:18px}.eg-head h1{margin:0 0 6px;font-size:26px}.eg-sub{color:#60708d}.eg-actions{display:flex;gap:8px}.eg-btn{display:inline-flex;align-items:center;justify-content:center;border:1px solid #bfd0ea;background:#fff;color:#2457ad;border-radius:9px;padding:9px 14px;text-decoration:none;font-weight:700}.eg-btn.primary{background:#2861c4;color:#fff;border-color:#2861c4}.eg-summary{background:#fff;border:1px solid #dce5f2;border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 3px 12px rgba(30,60,100,.05)}.eg-summary-line{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}.eg-progress{height:9px;background:#e8eef7;border-radius:99px;overflow:hidden;margin-top:10px}.eg-progress>span{display:block;height:100%;background:#2e67c7}.eg-stat{font-size:13px;color:#536783}.eg-stat strong{color:#183b70}.eg-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.eg-card{background:#fff;border:1px solid #dce5f2;border-radius:14px;padding:18px;box-shadow:0 3px 12px rgba(30,60,100,.05);scroll-margin-top:20px}.eg-card.focused{outline:3px solid rgba(46,103,199,.18)}.eg-card.disabled{opacity:.7}.eg-qhead{display:flex;justify-content:space-between;gap:10px;align-items:center}.eg-number{font-weight:800;color:#244b86}.eg-badge{font-size:12px;padding:5px 9px;border-radius:999px;background:#fff0d9;color:#9a5500}.eg-badge.done{background:#e7f8ee;color:#137441}.eg-question{margin:15px 0;font-size:16px;line-height:1.55}.eg-img{display:block;max-width:100%;max-height:300px;border-radius:9px;border:1px solid #dce5f2;margin-bottom:12px;object-fit:contain}.eg-panel{border:1px solid #e0e7f1;border-radius:9px;padding:12px;margin-top:10px;background:#f8fafc}.eg-panel.key{background:#fff7e8;border-color:#f2d49c}.eg-label{font-size:12px;font-weight:800;margin-bottom:5px}.eg-answer{white-space:pre-wrap;line-height:1.5}.eg-form{margin-top:14px}.eg-score-row{display:flex;gap:8px;align-items:center}.eg-score{flex:1;min-width:0;padding:10px;border:1px solid #cbd7e8;border-radius:8px;font-size:15px}.quick{display:flex;gap:5px;flex-wrap:wrap;margin:8px 0}.quick button{border:1px solid #cbd7e8;background:#fff;border-radius:7px;padding:5px 8px;font-size:12px;cursor:pointer}.quick button:hover{background:#f2f6fc}.eg-save{border:0;background:#2861c4;color:#fff;border-radius:8px;padding:10px 14px;font-weight:800;cursor:pointer}.eg-note{margin-top:12px;color:#64748b;font-size:13px}.flash{padding:12px 14px;border-radius:9px;background:#e8f8ef;color:#17683d;border:1px solid #b9e7ca;margin-bottom:16px}.complete{padding:14px 16px;border-radius:10px;background:#eef6ff;color:#174d91;border:1px solid #c7dcfa;margin-top:12px}.eg-nav{display:flex;gap:6px;flex-wrap:wrap;margin-top:12px}.eg-nav a{border:1px solid #cbd7e8;border-radius:7px;padding:5px 8px;text-decoration:none;color:#2457ad;font-size:12px}.eg-nav a.todo{font-weight:800}.eg-nav a.done{background:#e8f8ef;color:#17683d}.eg-nav a.current{outline:2px solid rgba(46,103,199,.2)}@media(max-width:800px){.eg-wrap{padding:16px}.eg-head{display:block}.eg-actions{margin-top:12px}.eg-grid{grid-template-columns:1fr}.eg-score-row{align-items:stretch}.eg-save{white-space:nowrap}}
</style></head><body><main class="eg-wrap">
<div class="eg-head"><div><a class="eg-btn" href="results.php?id=<?=h($detail['exam_id'])?>&attempt_id=<?=h($attemptId)?>">← Kembali ke Hasil</a><h1>Penilaian Essay</h1><div class="eg-sub"><b><?=h($detail['full_name'])?></b> · <?=h($detail['title'])?> · Attempt #<?=h($attemptId)?></div></div><div class="eg-actions"><?php if($remaining>0):?><a class="eg-btn primary" href="#q-<?=h($enabled[array_key_first(array_filter($enabled,fn($x)=>$x['essay_score']===null))]['id']??'')?>">Mulai/lanjut menilai</a><?php else:?><a class="eg-btn primary" href="results.php?id=<?=h($detail['exam_id'])?>&attempt_id=<?=h($attemptId)?>">Semua selesai ✓</a><?php endif;?></div></div>
<?php if(isset($_GET['saved'])):?><div class="flash">Nilai berhasil disimpan dan total attempt diperbarui.</div><?php endif;?>
<section class="eg-summary"><div class="eg-summary-line"><div><b>Progress penilaian</b> — <?=$graded?> / <?=count($enabled)?> soal berkunci sudah dinilai</div><div class="eg-stat">Selesai <strong><?=round($percent,2)?>%</strong> · Belum dinilai <strong><?=$remaining?></strong></div></div><div class="eg-progress"><span style="width:<?=count($enabled)?round($graded/count($enabled)*100):0?>%"></span></div><div class="eg-stat" style="margin-top:8px">Nilai Essay <strong><?=h(number_format($gradedTotal,2,'.',''))?> / <?=h(number_format($maxTotal,2,'.',''))?></strong></div><?php if($remaining===0 && count($enabled)>0):?><div class="complete"><b>Penilaian Essay selesai.</b> Semua soal yang memiliki kunci jawaban dan poin sudah dinilai.</div><?php endif;?><div class="eg-note">Soal Essay tanpa kunci & poin tidak masuk proses penilaian.</div><div class="eg-nav"><?php foreach($enabled as $n=>$nav): $navDone=$nav['essay_score']!==null; ?><a class="<?=$navDone?'done':'todo'?> <?=$focusId===(int)$nav['id']?'current':''?>" href="#q-<?=h($nav['id'])?>">Soal <?=($n+1)?> <?=$navDone?'✓':'•'?></a><?php endforeach;?></div></section>
<div class="eg-grid">
<?php foreach($questions as $i=>$a): $active=(int)$a['use_answer_key']===1 && (float)$a['points']>0; $done=$a['essay_score']!==null; $focused=$focusId===(int)$a['id']; ?>
<article class="eg-card <?=$focused?'focused ':''?><?=!$active?'disabled':''?>" id="q-<?=h($a['id'])?>"><div class="eg-qhead"><div class="eg-number">Soal <?=($i+1)?> · <?=h($a['points'])?> poin</div><span class="eg-badge <?=$done?'done':''?>"><?=!$active?'Tidak dinilai':($done?'Sudah dinilai':'Belum dinilai')?></span></div>
<div class="eg-question"><?=nl2br(h($a['question_text']))?></div>
<?php if(!empty($a['question_image'])):?><img class="eg-img" src="<?=h(question_image_url($a['question_image']))?>" alt="Gambar soal"><?php endif;?>
<div class="eg-panel"><div class="eg-label">Jawaban peserta</div><div class="eg-answer"><?=nl2br(h($a['essay_answer']??'Belum dijawab'))?></div></div>
<?php if(!empty($a['essay_answer_key'])):?><div class="eg-panel key"><div class="eg-label">Jawaban acuan</div><div class="eg-answer"><?=nl2br(h($a['essay_answer_key']))?></div></div><?php endif;?>
<?php if($active):?><form class="eg-form" method="post" action="save_essay_grade.php"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="attempt_id" value="<?=h($attemptId)?>"><input type="hidden" name="question_id" value="<?=h($a['id'])?>"><input type="hidden" name="return_to" value="<?=h($return)?>"><div class="eg-label">Nilai (maks. <?=h($a['points'])?>)</div><div class="quick"><button type="button" data-score="0">0%</button><button type="button" data-score="25">25%</button><button type="button" data-score="50">50%</button><button type="button" data-score="75">75%</button><button type="button" data-score="100">100%</button></div><div class="eg-score-row"><input class="eg-score" type="number" step="0.5" min="0" max="<?=h($a['points'])?>" name="essay_score" value="<?=$done?h($a['essay_score']):''?>" placeholder="Belum dinilai" required><button class="eg-save" type="submit">Simpan &amp; lanjut</button></div></form><?php else:?><div class="eg-note">Penilaian dengan kunci &amp; poin tidak aktif untuk soal ini.</div><?php endif;?></article>
<?php endforeach;?>
</div></main><script>
document.querySelectorAll('.quick').forEach(q=>{const input=q.parentElement.querySelector('.eg-score');const max=parseFloat(input.max||'0');q.querySelectorAll('button').forEach(b=>b.addEventListener('click',()=>{input.value=(max*parseFloat(b.dataset.score)/100).toFixed(2).replace(/\.00$/,'');input.focus()}));});
const focusId=<?=json_encode($focusId)?>; if(focusId){const el=document.getElementById('q-'+focusId);if(el) setTimeout(()=>el.scrollIntoView({behavior:'smooth',block:'start'}),120);}
</script></body></html>
