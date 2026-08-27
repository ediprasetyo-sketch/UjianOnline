<?php
declare(strict_types=1);
require __DIR__.'/../config.php';
require_once __DIR__.'/../includes/attempt_sync.php';
require_login('admin');
$pdo=db();
sync_attempt_statuses($pdo);
$running=(int)$pdo->query("SELECT COUNT(*) FROM attempts WHERE status='active' AND started_at IS NOT NULL")->fetchColumn();
$completed=(int)$pdo->query("SELECT COUNT(*) FROM attempts WHERE status IN ('submitted','finished','expired')")->fetchColumn();
$live=$pdo->query("SELECT e.id,e.title,e.end_at,
 (SELECT COUNT(*) FROM attempts a WHERE a.exam_id=e.id AND a.status='active') running_count,
 (SELECT COUNT(*) FROM attempts a WHERE a.exam_id=e.id AND a.status IN ('submitted','finished','expired')) completed_count
 FROM exams e WHERE e.active=1 AND e.start_at<=NOW() AND e.end_at>=NOW() ORDER BY e.end_at ASC LIMIT 6")->fetchAll();
$attempts=$pdo->query("SELECT a.id,a.exam_id,a.started_at,u.full_name,u.email,e.title,e.end_at
 FROM attempts a JOIN users u ON u.id=a.user_id JOIN exams e ON e.id=a.exam_id
 WHERE a.status='active' ORDER BY a.started_at DESC LIMIT 8")->fetchAll();
$recent=$pdo->query("SELECT a.id,a.exam_id,a.status,a.submitted_at,u.full_name,u.email,e.title
 FROM attempts a JOIN users u ON u.id=a.user_id JOIN exams e ON e.id=a.exam_id
 WHERE a.status IN ('submitted','finished','expired') ORDER BY a.submitted_at DESC LIMIT 5")->fetchAll();
json_response([
 'ok'=>true,'server_time'=>date(DATE_ATOM),'running'=>$running,'completed'=>$completed,
 'live'=>$live,'attempts'=>$attempts,'recent_completed'=>$recent
]);