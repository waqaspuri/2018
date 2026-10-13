<?php
require_once __DIR__ . "/php/session-bootstrap.php";
require_once __DIR__ . "/php/connect-mysql.php";
if (!isset($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== 'yes' || empty($_SESSION['adminOtpVerifiedAt'])) {
    header('Location: login.php');
    exit;
}
header("Location: " . $siteUrl);
exit;
?>
