<?php
declare(strict_types=1);

function admin_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('vcmhall_admin');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);

    $instance = substr(hash('sha256', realpath(__DIR__) ?: __DIR__), 0, 12);
    $bases = [__DIR__];
    $localAppData = getenv('LOCALAPPDATA');
    if (is_string($localAppData) && $localAppData !== '') {
        $bases[] = rtrim($localAppData, '/\\') . DIRECTORY_SEPARATOR . 'Temp';
    }
    foreach (['TEMP', 'TMP', 'TMPDIR'] as $variable) {
        $candidate = getenv($variable);
        if (is_string($candidate) && $candidate !== '') $bases[] = $candidate;
    }
    $bases[] = sys_get_temp_dir();

    $sessionPath = '';
    foreach (array_unique($bases) as $base) {
        $base = rtrim($base, '/\\');
        $candidate = $base === rtrim(__DIR__, '/\\')
            ? $base . DIRECTORY_SEPARATOR . 'sessions'
            : $base . DIRECTORY_SEPARATOR . 'vcmhall-admin-' . $instance;
        if (!is_dir($candidate) && !@mkdir($candidate, 0700, true) && !is_dir($candidate)) continue;
        if (is_writable($candidate)) {
            $sessionPath = $candidate;
            break;
        }
    }

    if ($sessionPath === '') {
        error_log('VCMhall admin session storage is not writable.');
        http_response_code(503);
        exit('The admin panel cannot start a secure session. Please contact the person who manages the website.');
    }
    session_save_path($sessionPath);
    if (!session_start()) {
        error_log('VCMhall admin session could not be started.');
        http_response_code(503);
        exit('The admin panel cannot start a secure session. Please contact the person who manages the website.');
    }
}
