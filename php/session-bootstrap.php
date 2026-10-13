<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    if (basename($scriptDir) === 'wAdmin') $scriptDir = dirname($scriptDir);
    $cookiePath = rtrim(str_replace('\\', '/', $scriptDir), '/') . '/';
    if ($cookiePath === '//') $cookiePath = '/';
    $secureCookie = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path' => $cookiePath,
        'domain' => '',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax'
    ));
    session_start();
}
