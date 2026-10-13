<?php
@session_start();
$siteName     = "Waqas Chemicals";
$siteUrl      = "https://waqaschemicals.com/2018/wAdmin/";
$siteUrlMain  = "https://waqaschemicals.com/2018/";
$domainName   = "https://waqaschemicals.com";
DEFINE ('DB_USER', 'waqasche_new2018');
DEFINE ('DB_PSWD', 'WCJ:sSRztV_,x3o4'); 
DEFINE ('DB_HOST', 'localhost'); 
DEFINE ('DB_NAME', 'waqasche_new2018');
$conn = new mysqli(DB_HOST,DB_USER,DB_PSWD,DB_NAME);
if($conn->connect_error){ die("Connection failed: " . $conn->connect_error);}
mysqli_set_charset($conn, "utf8mb4");
$sqlAdminEmil = mysqli_query($conn,"select adminEmail from admin where id='1'");
$rowAdminEmil = mysqli_fetch_assoc($sqlAdminEmil);
$domainEmail = $rowAdminEmil['adminEmail'];
?>
