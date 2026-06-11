<?php
$sessionPath = __DIR__ . '/sessions';

if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0775, true);
}

$tempSessionPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'silm_sessions';

if (!is_dir($tempSessionPath)) {
    @mkdir($tempSessionPath, 0775, true);
}

if (is_dir($sessionPath) && is_writable($sessionPath)) {
    session_save_path($sessionPath);
} elseif (is_dir($tempSessionPath) && is_writable($tempSessionPath)) {
    session_save_path($tempSessionPath);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
