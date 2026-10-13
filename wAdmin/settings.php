<?php
include "include/sessionAdmin.php";
include "include/configure.php";
$sqlShipmentTrackingApiKeyColumn = mysqli_query($conn, "SHOW COLUMNS FROM admin LIKE 'shipmentTrackingApiKey'");
if (mysqli_num_rows($sqlShipmentTrackingApiKeyColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE admin ADD COLUMN shipmentTrackingApiKey VARCHAR(255) NULL DEFAULT NULL AFTER adminEmail");
}
$sqlEmailFooterColumn = mysqli_query($conn, "SHOW COLUMNS FROM admin LIKE 'emailFooter'");
if (mysqli_num_rows($sqlEmailFooterColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE admin ADD COLUMN emailFooter MEDIUMTEXT NULL AFTER shipmentTrackingApiKey");
}
$sqlPriceRequestMessageColumn = mysqli_query($conn, "SHOW COLUMNS FROM admin LIKE 'priceRequestMessage'");
if (mysqli_num_rows($sqlPriceRequestMessageColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE admin ADD COLUMN priceRequestMessage MEDIUMTEXT NULL AFTER emailFooter");
}
$sqlRequireAdminOtpColumn = mysqli_query($conn, "SHOW COLUMNS FROM admin LIKE 'requireAdminOtp'");
if ($sqlRequireAdminOtpColumn && mysqli_num_rows($sqlRequireAdminOtpColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE admin ADD COLUMN requireAdminOtp TINYINT(1) NOT NULL DEFAULT 1");
}
if(isset($_POST['submit']) && $_POST['submit']=="Submit"){
if(isset($_POST['action']) && $_POST['action']=="update"){
$requireAdminOtp = isset($_POST['disableAdminOtp']) && $_POST['disableAdminOtp'] === '1' ? 0 : 1;
mysqli_query($conn,"UPDATE admin SET address='". mysqli_real_escape_string($conn,$_POST['address'])."',mobileNumber='". mysqli_real_escape_string($conn,$_POST['mobileNumber'])."',siteUrl='". mysqli_real_escape_string($conn,$_POST['siteUrl'])."',adminEmail='". mysqli_real_escape_string($conn,$_POST['adminEmail'])."',shipmentTrackingApiKey='". mysqli_real_escape_string($conn,$_POST['shipmentTrackingApiKey'] ?? '')."',emailFooter='". mysqli_real_escape_string($conn,$_POST['emailFooter'] ?? '')."',requireAdminOtp='".$requireAdminOtp."' where id='1'");	
header("location: settings.php?act=2");
}
}
$sqlEdit = mysqli_query($conn,"SELECT * FROM admin WHERE id='1'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
$action = "update";
include "include/header.php"; 
?>
<script src="<?=$siteUrl?>assets/vendor/summernote/summernote.js"></script>
<link rel="stylesheet" href="<?=$siteUrl?>assets/vendor/summernote/summernote.css" />
<link rel="stylesheet" href="<?=$siteUrl?>assets/vendor/summernote/summernote-bs3.css" />
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Settings</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span>Manage Settings</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act']) && $_GET['act']==2){
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>Settings has been updated successfully.</strong></div>';
}
?>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<h2 class="panel-title">Manage Settings</h2>
</header>
<div class="panel-body">
<form class="form-horizontal form-bordered" method="post">
<div class="form-group">
<label class="col-md-3 control-label" for="inputDefault">Site URL</label>
<div class="col-md-6">
<input type="text" class="form-control" name="siteUrl" value="<?=$rowsEdit['siteUrl']?>" id="inputDefault" required >
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputHelpText">Email Address</label>
<div class="col-md-6">
<input type="email" class="form-control" name="adminEmail" value="<?=$rowsEdit['adminEmail']?>" id="inputDefault" required >
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputHelpText">Shipment Tracking API Key</label>
<div class="col-md-6">
<input type="text" class="form-control" name="shipmentTrackingApiKey" value="<?=htmlspecialchars(isset($rowsEdit['shipmentTrackingApiKey']) ? $rowsEdit['shipmentTrackingApiKey'] : '', ENT_QUOTES, 'UTF-8')?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="emailFooter">Email Footer / Signature</label>
<div class="col-md-6">
<textarea class="summernote" id="emailFooter" name="emailFooter" data-plugin-summernote data-plugin-options='{ "height": 180 }'><?=htmlspecialchars($rowsEdit['emailFooter'] ?? '', ENT_QUOTES, 'UTF-8')?></textarea>
<p class="help-block">This signature is appended to outgoing emails, including manual and scheduled task emails.</p>
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputHelpText">Address</label>
<div class="col-md-6">
<input type="text" class="form-control" name="address" value="<?=$rowsEdit['address']?>" id="inputDefault" required >
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputHelpText">Mobile Number</label>
<div class="col-md-6">
<input type="text" class="form-control" name="mobileNumber" value="<?=$rowsEdit['mobileNumber']?>" id="inputDefault" required >
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="requireAdminOtp">Disable OTP verification</label>
<div class="col-md-6">
<label><input type="checkbox" id="disableAdminOtp" name="disableAdminOtp" value="1" <?=((int)($rowsEdit['requireAdminOtp'] ?? 1) === 0) ? 'checked' : ''?>> Disable email OTP for admin sign-in</label>
<p class="help-block">When checked, admin sign-in uses the password only and skips the email verification code.</p>
</div>
</div>
<label class="col-md-3 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<input type="hidden" name="action" value="<?=$action;?>" />
<button class="btn btn-primary" name="submit" value="Submit">Submit</button>
<button type="button" onclick="window.location.href='<?=$siteUrl;?>'" class="btn btn-default">Cancel</button>
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
