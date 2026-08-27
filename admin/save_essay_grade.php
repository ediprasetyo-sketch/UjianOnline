<?php
declare(strict_types=1);
require __DIR__.'/../config.php';
require_login('admin');
check_csrf();

$attemptId=(int)($_POST['attempt_id']??0);
$questionId=(int)($_POST['question_id']??0);
$score=(float)($_POST['essay_score']??0);
if($attemptId<1||$questionId<1) exit('Data penilaian tidak lengkap.');

/*
 * Essay V1.2:
 * - Never enable the answer-key/points switch automatically.
 * - A disabled answer-key means the essay contributes 0 points.
 * - Validate that the attempt and question belong to the same exam.
 */
$s=db()->prepare("SELECT q.id,q.points,q.type,q.use_answer_key,a.id AS answer_id,at.exam_id FROM questions q JOIN attempts at ON at.id=? LEFT JOIN answers a ON a.attempt_id=at.id AND a.question_id=q.id WHERE q.id=? AND q.exam_id=at.exam_id LIMIT 1");
$s->execute([$attemptId,$questionId]);
$row=$s->fetch();
if(!$row||$row['type']!=='essay') exit('Soal essay tidak ditemukan.');

$useKey=(int)($row['use_answer_key']??0)===1;
$max=$useKey?max(0,(float)$row['points']):0.0;

/* If grading is disabled for this essay, do not create a score. */
if(!$useKey || $max<=0){
    db()->prepare("UPDATE answers SET essay_score=NULL WHERE attempt_id=? AND question_id=?")->execute([$attemptId,$questionId]);
}else{
    $score=max(0,min($max,$score));
    $u=db()->prepare("INSERT INTO answers(attempt_id,question_id,essay_score) VALUES(?,?,?) ON DUPLICATE KEY UPDATE essay_score=VALUES(essay_score)");
    $u->execute([$attemptId,$questionId,$score]);
}

/* Recalculate the complete attempt score from the current question settings. */
$sum=db()->prepare("SELECT COALESCE(SUM(CASE WHEN q.type='mcq' AND q.use_answer_key=1 AND a.selected_option=q.correct_option THEN q.points ELSE 0 END),0)+COALESCE(SUM(CASE WHEN q.type='essay' AND q.use_answer_key=1 THEN LEAST(GREATEST(COALESCE(a.essay_score,0),0),q.points) ELSE 0 END),0) AS total FROM questions q LEFT JOIN answers a ON a.question_id=q.id AND a.attempt_id=? WHERE q.exam_id=?");
$sum->execute([$attemptId,(int)$row['exam_id']]);
$total=(float)$sum->fetchColumn();
db()->prepare('UPDATE attempts SET score=? WHERE id=?')->execute([$total,$attemptId]);

header('Location: results.php?id='.(int)$row['exam_id'].'&attempt_id='.$attemptId.'&graded=1');
exit;
