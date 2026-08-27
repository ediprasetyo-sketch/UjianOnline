<?php
declare(strict_types=1);
require __DIR__.'/_common.php';

api_auth();
check_api_csrf();

$a = get_attempt((int)(body()['attempt_id'] ?? 0));

// get_attempt() may already finalize an expired session. Only an actually
// active attempt is eligible for a manual submitted state.
if ($a['status'] === 'active') {
    submit_attempt($a);
    $a = get_attempt($a['id']);
}

json_response([
    'status' => $a['status'],
    'score' => $a['score'],
    'finished' => in_array($a['status'], ['submitted', 'expired'], true),
    'message' => $a['status'] === 'expired'
        ? 'Waktu ujian telah habis.'
        : 'Ujian berhasil dikirim.'
]);
