<?php
@session_start();
require_once __DIR__ . '/env.php';
loadAppEnvironment();

$siteName     = appEnvironmentValue('APP_SITE_NAME');
$siteUrl      = rtrim(appEnvironmentValue('APP_ADMIN_URL'), '/') . '/';
$siteUrlMain  = rtrim(appEnvironmentValue('APP_BASE_URL'), '/') . '/';
$domainName   = rtrim(appEnvironmentValue('APP_DOMAIN_URL'), '/');
DEFINE ('DB_USER', appEnvironmentValue('DB_USER'));
DEFINE ('DB_PSWD', appEnvironmentValue('DB_PASSWORD'));
DEFINE ('DB_HOST', appEnvironmentValue('DB_HOST', 'localhost'));
DEFINE ('DB_NAME', appEnvironmentValue('DB_DATABASE'));

if ($siteName === '' || $siteUrl === '/' || $siteUrlMain === '/' || $domainName === '') {
    die('Site configuration is missing. Set APP_SITE_NAME, APP_ADMIN_URL, APP_BASE_URL and APP_DOMAIN_URL in .env.');
}

if (DB_USER === '' || DB_NAME === '') {
    die('Database configuration is missing. Set DB_HOST, DB_DATABASE, DB_USER and DB_PASSWORD in .env.');
}

$conn = new mysqli(DB_HOST,DB_USER,DB_PSWD,DB_NAME);
if($conn->connect_error){ die("Connection failed: " . $conn->connect_error);}
mysqli_set_charset($conn, "utf8mb4");
$sqlAdminEmil = mysqli_query($conn,"select adminEmail from admin where id='1'");
$rowAdminEmil = mysqli_fetch_assoc($sqlAdminEmil);
$domainEmail = $rowAdminEmil['adminEmail'];
?>
