<?php
include "include/sessionAdmin.php";  
include "include/configure.php";
require_once __DIR__ . '/../php/admin_auth.php';
$error = '';
if (isset($_POST['submit']) && $_POST['submit'] === 'Submit') {
    $newPassword = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirmPassword'] ?? '');
    $adminID = (int)($_SESSION['adminID'] ?? 0);
    if ($newPassword !== $confirmPassword) {
        $error = '3';
    } elseif (strlen($newPassword) < 12) {
        $error = '4';
    } elseif (!$adminID || !adminEnsurePasswordHashColumn($conn)) {
        $error = '5';
    } else {
        $stmt = mysqli_prepare($conn, 'SELECT id,pass,passHash FROM admin WHERE id=? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $adminID);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $admin = $result ? mysqli_fetch_assoc($result) : false;
        mysqli_stmt_close($stmt);
        if (!$admin || !adminVerifyAndMigratePassword($conn, $admin, (string)($_POST['oldPassword'] ?? ''))) {
            $error = '2';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = mysqli_prepare($conn, "UPDATE admin SET passHash=?, pass='' WHERE id=?");
            mysqli_stmt_bind_param($update, 'si', $newHash, $adminID);
            if (mysqli_stmt_execute($update)) {
                mysqli_stmt_close($update);
                session_regenerate_id(true);
                header("Location: changePassword.php?act=1");
                exit;
            }
            mysqli_stmt_close($update);
            $error = '5';
        }
    }
}
include "include/header.php"; 
?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Chanage Password</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span>Change Password</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act'])){
$error = $_GET['act'];
}
if(isset($error)){
if($error=="1"){
$msgTxt='Password changed sucessfully';
}else if($error=="2"){
$msgTxt='Old password is incorrect';
}else if($error=="3"){
$msgTxt='Password does not match';

}else if($error=="4"){
$msgTxt='New password must be at least 12 characters';
}else if($error=="5"){
$msgTxt='Password could not be updated. Please try again.';
} 	
if (isset($msgTxt)) echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.$msgTxt.'</strong></div>';
}
?>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<h2 class="panel-title">Chanage Password</h2>
</header>
<div class="panel-body">
<form class="form-horizontal form-bordered" method="post">
<div class="form-group">
<label class="col-md-3 control-label" for="inputDefault">Username</label>
<div class="col-md-6">
<b>Admin</b>
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputReadOnly">Old Password</label>
<div class="col-md-6">
<input type="password" class="form-control" name="oldPassword" id="inputDefault" autocomplete="current-password" required >
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputReadOnly">New Password</label>
<div class="col-md-6">
<input type="password" class="form-control" name="password" id="inputDefault" autocomplete="new-password" minlength="12" required > 
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDisabled">Confirm Password</label>
<div class="col-md-6">
<input type="password" class="form-control" name="confirmPassword" id="inputDefault" autocomplete="new-password" minlength="12" required >
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<button class="btn btn-primary" name="submit" value="Submit">Submit</button>
<button type="button" onclick="window.location.href='<?=$siteUrl;?>index.php'" class="btn btn-default">Cancel</button>
</div>
</div> 
</form>
</div>
</section>
</div>
</div>
</section>
</div>
</section>
<?php include "include/footer.php"; ?>
