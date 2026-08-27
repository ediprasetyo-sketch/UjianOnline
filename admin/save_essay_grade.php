<?php
declare(strict_types=1);
require __DIR__.'/../config.php';
require_login('admin');
check_csrf();

$attemptId=(int)($_POST['attempt_id']??0);
$questionId=(int)($_POST['question_id']??0);
$score=(float)($_POST['essay_score']??0);
if($attemptId<1||$questionId<1) exit('Data penilaian tidak lengkap.');

$s=db()->prepare("SELECT q.id,q.points,q.type,q.use_answer_key,a.id AS answer_id,at.exam_id FROM questions q JOIN attempts at ON at.id=? LEFT JOIN answers a ON a.attempt_id=at.id AND a.question_id=q.id WHERE q.id=? AND q.exam_id=at.exam_id LIMIT 1");
$s->execute([$attemptId,$questionId]);
$row=$s->fetch();
if(!$row||$row['type']!=='essay') exit('Soal essay tidak ditemukan.');

$locked=db()->prepare("SELECT event_type FROM audit_logs WHERE attempt_id=? AND event_type IN ('essay_finalized','essay_reopened') ORDER BY id DESC LIMIT 1");
$locked->execute([$attemptId]);
if((string)($locked->fetchColumn()?:'')==='essay_finalized'){
    http_response_code(409);
    exit('Penilaian Essay sudah final. Buka kembali penilaian sebelum mengubah nilai.');
}

$useKey=(int)($row['use_answer_key']??0)===1;
$max=$useKey?max(0,(float)$row['points']):0.0;

if(!$useKey || $max<=0){
    db()->prepare("UPDATE answers SET essay_score=NULL WHERE attempt_id=? AND question_id=?")->execute([$attemptId,$questionId]);
}else{
    $score=max(0,min($max,$score));
    $u=db()->prepare("INSERT INTO answers(attempt_id,question_id,essay_score) VALUES(?,?,?) ON DUPLICATE KEY UPDATE essay_score=VALUES(essay_score)");
    $u->execute([$attemptId,$questionId,$score]);
}

$sum=db()->prepare("SELECT COALESCE(SUM(CASE WHEN q.type='mcq' AND q.use_answer_key=1 AND a.selected_option=q.correct_option THEN q.points ELSE 0 END),0)+COALESCE(SUM(CASE WHEN q.type='essay' AND q.use_answer_key=1 THEN LEAST(GREATEST(COALESCE(a.essay_score,0),0),q.points) ELSE 0 END),0) AS total FROM questions q LEFT JOIN answers a ON a.question_id=q.id AND a.attempt_id=? WHERE q.exam_id=?");
$sum->execute([$attemptId,(int)$row['exam_id']]);
$total=(float)$sum->fetchColumn();
db()->prepare('UPDATE attempts SET score=? WHERE id=?')->execute([$total,$attemptId]);

$next=db()->prepare("SELECT q.id FROM questions q LEFT JOIN answers a ON a.question_id=q.id AND a.attempt_id=? WHERE q.exam_id=? AND q.type='essay' AND q.use_answer_key=1 AND q.points>0 AND a.essay_score IS NULL AND q.id<>? ORDER BY q.sort_order,q.id LIMIT 1");
$next->execute([$attemptId,(int)$row['exam_id'],$questionId]);
$nextId=(int)($next->fetchColumn()?:0);

$returnTo=trim((string)($_POST['return_to']??''));
if($returnTo!=='' && preg_match('/^essay_grading\.php\?attempt_id=\d+$/',$returnTo)){
    $location=$returnTo.'&saved=1'.($nextId>0?'&focus='.$nextId:'');
    header('Location: '.$location);
}else{
    header('Location: results.php?id='.(int)$row['exam_id'].'&attempt_id='.$attemptId.'&graded=1');
}
exit;
