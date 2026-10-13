<?php
require_once __DIR__ . '/session-bootstrap.php';
$now = time();
$authenticated = isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === 'yes'
    && !empty($_SESSION['adminID']) && !empty($_SESSION['adminOtpVerifiedAt'])
    && !empty($_SESSION['adminLastActivity'])
    && ($now - (int)$_SESSION['adminLastActivity']) <= 1800
    && ($now - (int)$_SESSION['adminOtpVerifiedAt']) <= 43200;
if (!$authenticated) {
    $_SESSION = array();
    session_destroy();
    header('Location: login.php');
    exit;
}
$_SESSION['adminLastActivity'] = $now;
?>
