<?php
declare(strict_types=1);
require __DIR__.'/../config.php';
require_login('admin');
check_csrf();

$attemptId=(int)($_POST['attempt_id']??0);
$questionId=(int)($_POST['question_id']??0);
$score=(float)($_POST['essay_score']??0);
if($attemptId<1||$questionId<1) exit('Data penilaian tidak lengkap.');

$s=db()->prepare("SELECT q.points,q.type,a.id AS answer_id,at.exam_id FROM questions q JOIN attempts at ON at.id=? LEFT JOIN answers a ON a.attempt_id=at.id AND a.question_id=q.id WHERE q.id=? AND q.exam_id=at.exam_id LIMIT 1");
$s->execute([$attemptId,$questionId]);
$row=$s->fetch();
if(!$row||$row['type']!=='essay') exit('Soal essay tidak ditemukan.');

/* Backward compatibility: old essay questions created without an explicit
   point value were saved as 0, making manual grading impossible. */
$max=(float)$row['points'];
if($max<=0){
    $max=1.0;
    db()->prepare("UPDATE questions SET points=1,use_answer_key=1 WHERE id=? AND type='essay'")->execute([$questionId]);
}
$score=max(0,min($max,$score));

$u=db()->prepare("INSERT INTO answers(attempt_id,question_id,essay_score) VALUES(?,?,?) ON DUPLICATE KEY UPDATE essay_score=VALUES(essay_score)");
$u->execute([$attemptId,$questionId,$score]);

$sum=db()->prepare("SELECT COALESCE(SUM(CASE WHEN q.type='mcq' AND a.selected_option=q.correct_option THEN q.points ELSE 0 END),0)+COALESCE(SUM(CASE WHEN q.type='essay' THEN COALESCE(a.essay_score,0) ELSE 0 END),0) AS total FROM questions q LEFT JOIN answers a ON a.question_id=q.id AND a.attempt_id=? WHERE q.exam_id=?");
$sum->execute([$attemptId,(int)$row['exam_id']]);
$total=(float)$sum->fetchColumn();
db()->prepare('UPDATE attempts SET score=? WHERE id=?')->execute([$total,$attemptId]);
header('Location: results.php?id='.(int)$row['exam_id'].'&attempt_id='.$attemptId.'&graded=1');
exit;
