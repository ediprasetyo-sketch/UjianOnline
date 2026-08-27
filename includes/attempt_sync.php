<?php
declare(strict_types=1);

/**
 * Shared attempt status synchronization.
 * Expired attempts are finalized with a score and audit event instead of only
 * flipping the status, so admin/background synchronization cannot bypass the
 * normal terminal-state logic.
 */
if (!function_exists('sync_attempt_statuses')) {
    function sync_attempt_statuses(?PDO $pdo=null, ?int $examId=null): int {
        $pdo ??= db();
        $where = "a.status='active' AND a.started_at IS NOT NULL";
        $params = [];
        if ($examId !== null && $examId > 0) {
            $where .= " AND a.exam_id=?";
            $params[] = $examId;
        }

        $deadlineExpr = "LEAST(DATE_ADD(a.started_at, INTERVAL e.duration_seconds SECOND), e.end_at)";
        $repair = $pdo->prepare(
            "UPDATE attempts a JOIN exams e ON e.id=a.exam_id
             SET a.deadline_at={$deadlineExpr}
             WHERE {$where} AND (a.deadline_at IS NULL OR a.deadline_at <> {$deadlineExpr})"
        );
        $repair->execute($params);

        $find = $pdo->prepare(
            "SELECT a.id,a.exam_id,a.user_id FROM attempts a JOIN exams e ON e.id=a.exam_id
             WHERE {$where} AND {$deadlineExpr} <= NOW()"
        );
        $find->execute($params);
        $expired = $find->fetchAll();
        $count = 0;

        foreach ($expired as $attempt) {
            $pdo->beginTransaction();
            try {
                $lock = $pdo->prepare('SELECT id,exam_id,user_id,status FROM attempts WHERE id=? FOR UPDATE');
                $lock->execute([(int)$attempt['id']]);
                $current = $lock->fetch();
                if (!$current || $current['status'] !== 'active') {
                    $pdo->commit();
                    continue;
                }

                // Auto-grade exact essay keys before computing the final score.
                $essay = $pdo->prepare("SELECT q.id,q.points,q.essay_answer_key,a.essay_answer FROM questions q LEFT JOIN answers a ON a.question_id=q.id AND a.attempt_id=? WHERE q.exam_id=? AND q.type='essay'");
                $essay->execute([(int)$current['id'], (int)$current['exam_id']]);
                $saveEssay = $pdo->prepare("INSERT INTO answers(attempt_id,question_id,essay_score) VALUES(?,?,?) ON DUPLICATE KEY UPDATE essay_score=VALUES(essay_score)");
                foreach ($essay as $row) {
                    $key = trim((string)($row['essay_answer_key'] ?? ''));
                    $answer = trim((string)($row['essay_answer'] ?? ''));
                    if ($key === '' || $answer === '') continue;
                    $normalize = static function(string $v): string { $v=trim(mb_strtolower($v,'UTF-8')); return preg_replace('/\s+/u',' ',$v) ?? $v; };
                    if ($normalize($answer) === $normalize($key)) $saveEssay->execute([(int)$current['id'],(int)$row['id'],(float)$row['points']]);
                }

                $scoreStmt = $pdo->prepare("SELECT q.type,q.use_answer_key,q.correct_option,q.matrix_correct_mirip,q.matrix_correct_tidak,q.points,ans.selected_option,ans.matrix_answer,ans.essay_score FROM questions q LEFT JOIN answers ans ON ans.question_id=q.id AND ans.attempt_id=? WHERE q.exam_id=?");
                $scoreStmt->execute([(int)$current['id'], (int)$current['exam_id']]);
                $score = 0.0;
                foreach ($scoreStmt as $row) {
                    if ((int)($row['use_answer_key']??1)===1 && $row['type']==='mcq' && $row['selected_option']===$row['correct_option']) $score += (float)$row['points'];
                    elseif ($row['type']==='matrix_disc') {
                        $answer=json_decode((string)($row['matrix_answer'] ?? ''),true) ?: [];
                        $half=(float)$row['points']/2;
                        if (($answer['mirip'] ?? null)===($row['matrix_correct_mirip'] ?? null)) $score += $half;
                        if (($answer['tidak_mirip'] ?? null)===($row['matrix_correct_tidak'] ?? null)) $score += $half;
                    } elseif ($row['type']==='essay' && $row['essay_score']!==null) $score += (float)$row['essay_score'];
                }

                $update=$pdo->prepare("UPDATE attempts SET status='expired',submitted_at=NOW(),score=? WHERE id=? AND status='active'");
                $update->execute([$score,(int)$current['id']]);
                if ($update->rowCount()===1) {
                    $pdo->prepare("INSERT INTO audit_logs(user_id,exam_id,attempt_id,event_type) VALUES(?,?,?,'attempt_expired')")
                        ->execute([(int)$current['user_id'],(int)$current['exam_id'],(int)$current['id']]);
                    $count++;
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        }
        return $count;
    }
}
