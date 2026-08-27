<?php
declare(strict_types=1);
require __DIR__.'/../config.php';
require_once __DIR__.'/../includes/participant_session.php';
unset($_SESSION['participant'], $_SESSION['public_exam_token'], $_SESSION['pending_verify_email'], $_SESSION['pending_verify_exam']);
header('Location: '.app_url('peserta/access.php'));
exit;
