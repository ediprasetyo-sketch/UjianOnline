<?php
declare(strict_types=1);

/**
 * Isolated participant session helpers.
 * Kept outside config.php so participant entry points always load the same API.
 */
if (!function_exists('participant_session')) {
    function participant_session(): ?array {
        $p = $_SESSION['participant'] ?? null;
        return is_array($p) && !empty($p['id']) && ($p['role'] ?? '') === 'participant' ? $p : null;
    }
}

if (!function_exists('require_participant')) {
    function require_participant(): array {
        $p = participant_session();
        if (!$p) {
            if (function_exists('json_response')) {
                json_response(['error' => 'Unauthorized participant'], 401);
            }
            http_response_code(401);
            exit('Unauthorized participant');
        }
        return $p;
    }
}

if (!function_exists('participant_logout_url')) {
    function participant_logout_url(): string {
        return app_url('peserta/logout.php');
    }
}
