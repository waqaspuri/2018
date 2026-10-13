<?php
@session_start();
$siteName     = "Waqas Chemicals";
$siteUrl      = "http://localhost:8080/2018/wAdmin/";
$siteUrlMain  = "http://localhost:8080/2018/";
$domainName   = "http://localhost:8080";
DEFINE ('DB_USER', 'root');
DEFINE ('DB_PSWD', 'root'); 
DEFINE ('DB_HOST', 'localhost'); 
DEFINE ('DB_NAME', '2018');
$conn = new mysqli(DB_HOST,DB_USER,DB_PSWD,DB_NAME);
if($conn->connect_error){ die("Connection failed: " . $conn->connect_error);}
mysqli_set_charset($conn, "utf8mb4");
$sqlAdminEmil = mysqli_query($conn,"select adminEmail from admin where id='1'");
$rowAdminEmil = mysqli_fetch_assoc($sqlAdminEmil);
$domainEmail = $rowAdminEmil['adminEmail'];
?>