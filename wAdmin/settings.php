<?php
include "include/sessionAdmin.php";
include "include/configure.php";
require_once(__DIR__ . '/../php/task_schedule.php');
ensureTaskScheduleSchema($conn);
$sqlStatusTextColorColumn = mysqli_query($conn, "SHOW COLUMNS FROM status LIKE 'textColor'");
if ($sqlStatusTextColorColumn && mysqli_num_rows($sqlStatusTextColorColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE status ADD COLUMN textColor VARCHAR(20) NULL DEFAULT NULL AFTER bgColor");
}
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
$taskTimezone = isset($_POST['taskTimezone']) && in_array($_POST['taskTimezone'], timezone_identifiers_list(), true) ? $_POST['taskTimezone'] : 'Asia/Karachi';
mysqli_query($conn,"UPDATE admin SET address='". mysqli_real_escape_string($conn,$_POST['address'])."',mobileNumber='". mysqli_real_escape_string($conn,$_POST['mobileNumber'])."',siteUrl='". mysqli_real_escape_string($conn,$_POST['siteUrl'])."',adminEmail='". mysqli_real_escape_string($conn,$_POST['adminEmail'])."',taskTimezone='".mysqli_real_escape_string($conn,$taskTimezone)."',shipmentTrackingApiKey='". mysqli_real_escape_string($conn,$_POST['shipmentTrackingApiKey'] ?? '')."',emailFooter='". mysqli_real_escape_string($conn,$_POST['emailFooter'] ?? '')."',requireAdminOtp='".$requireAdminOtp."' where id='1'");	
foreach (array('statusColors' => 'bgColor', 'statusTextColors' => 'textColor') as $colorField => $colorColumn) {
    if (!isset($_POST[$colorField]) || !is_array($_POST[$colorField])) {
        continue;
    }
    foreach ($_POST[$colorField] as $statusID => $statusColor) {
        if (!ctype_digit((string)$statusID) || !is_scalar($statusColor)) {
            continue;
        }
        $statusID = (int)$statusID;
        $statusColor = trim((string)$statusColor);
        if ($colorColumn === 'textColor' && $statusColor === '') {
            mysqli_query($conn, "UPDATE status SET textColor=NULL WHERE id='" . $statusID . "'");
            continue;
        }
        if (!preg_match('/^(#[0-9a-f]{3,4}|#[0-9a-f]{6}|#[0-9a-f]{8}|[a-z]{1,20})$/i', $statusColor)) {
            continue;
        }
        $safeStatusColor = mysqli_real_escape_string($conn, $statusColor);
        mysqli_query($conn, "UPDATE status SET " . $colorColumn . "='" . $safeStatusColor . "' WHERE id='" . $statusID . "'");
    }
}
header("location: settings.php?act=2");
}
}
$sqlEdit = mysqli_query($conn,"SELECT * FROM admin WHERE id='1'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
$lastCronResult = mysqli_query($conn, "SELECT source,startedAt,finishedAt,status FROM cron_run_log ORDER BY id DESC LIMIT 1");
$lastCronRun = $lastCronResult ? mysqli_fetch_assoc($lastCronResult) : null;
$sqlStatuses = mysqli_query($conn, "SELECT id,status,bgColor,textColor FROM status ORDER BY id ASC");
$action = "update";
include "include/header.php"; 
?>
<script src="<?=$siteUrl?>assets/vendor/summernote/summernote.js"></script>
<script src="<?=$siteUrl?>assets/vendor/bootstrap-colorpicker/js/bootstrap-colorpicker.js"></script>
<link rel="stylesheet" href="<?=$siteUrl?>assets/vendor/bootstrap-colorpicker/css/bootstrap-colorpicker.css" />
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
<label class="col-md-3 control-label">Last Cron Run</label>
<div class="col-md-6">
<p class="form-control-static">
<?php if ($lastCronRun): ?>
<?=htmlspecialchars(date('d-M-Y g:i:s A', strtotime($lastCronRun['finishedAt'] ?: $lastCronRun['startedAt'])), ENT_QUOTES, 'UTF-8')?>
&mdash; <?=htmlspecialchars(ucfirst($lastCronRun['status']), ENT_QUOTES, 'UTF-8')?>
(<?=htmlspecialchars($lastCronRun['source'] === 'manual' ? 'Run Cron Now button' : 'automatic cron', ENT_QUOTES, 'UTF-8')?>)
<?php else: ?>
No cron run has been recorded yet.
<?php endif; ?>
</p>
<p class="help-block">This is updated whenever the automatic cron or the Run Cron Now button executes.</p>
</div>
</div>
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
<label class="col-md-3 control-label" for="taskTimezone">Task Timezone</label>
<div class="col-md-6">
<select class="form-control" id="taskTimezone" name="taskTimezone">
<?php
$taskTimezoneOptions = array(
    'Asia/Karachi' => 'Karachi, Pakistan (UTC+05:00)',
    'Asia/Dubai' => 'Dubai, UAE (UTC+04:00)',
    'UTC' => 'UTC (UTC+00:00)'
);
foreach ($taskTimezoneOptions as $timezoneValue => $timezoneLabel): ?>
<option value="<?=htmlspecialchars($timezoneValue, ENT_QUOTES, 'UTF-8')?>" <?=($rowsEdit['taskTimezone'] ?? 'Asia/Karachi') === $timezoneValue ? 'selected' : ''?>><?=htmlspecialchars($timezoneLabel, ENT_QUOTES, 'UTF-8')?></option>
<?php endforeach; ?>
</select>
<p class="help-block">Due and repeat reminder times are evaluated in this timezone. The default is Karachi, Pakistan.</p>
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
<div class="form-group">
<label class="col-md-3 control-label">Order Status Colors</label>
<div class="col-md-6">
<p class="help-block">Set the background and text colors for each status in the orders list. Click either color field to choose a color, or enter a hex color (for example, #4caf50) or a CSS color name (for example, green). Leave text color blank to use the default.</p>
<?php while ($statusRow = mysqli_fetch_assoc($sqlStatuses)) {
    $statusID = (int)$statusRow['id'];
    $statusName = htmlspecialchars((string)$statusRow['status'], ENT_QUOTES, 'UTF-8');
    $statusColor = htmlspecialchars((string)($statusRow['bgColor'] ?? ''), ENT_QUOTES, 'UTF-8');
    $statusTextColor = htmlspecialchars((string)($statusRow['textColor'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div class="row" style="margin-bottom:8px">
<div class="col-sm-4"><span class="control-label"><?=$statusName?></span></div>
<div class="col-sm-4"><label for="statusColor<?=$statusID?>">Background color<span class="sr-only"> for <?=$statusName?></span></label><input type="text" class="form-control" id="statusColor<?=$statusID?>" data-plugin-colorpicker data-plugin-options='{"format":"hex"}' name="statusColors[<?=$statusID?>]" value="<?=$statusColor?>" placeholder="#4caf50" pattern="(#[0-9a-fA-F]{3,4}|#[0-9a-fA-F]{6}|#[0-9a-fA-F]{8}|[a-zA-Z]{1,20})" title="Use a 3, 4, 6, or 8 digit hex color, or a CSS color name"></div>
<div class="col-sm-4"><label for="statusTextColor<?=$statusID?>">Text color<span class="sr-only"> for <?=$statusName?></span></label><input type="text" class="form-control" id="statusTextColor<?=$statusID?>" data-plugin-colorpicker data-plugin-options='{"format":"hex"}' name="statusTextColors[<?=$statusID?>]" value="<?=$statusTextColor?>" placeholder="Default" pattern="(#[0-9a-fA-F]{3,4}|#[0-9a-fA-F]{6}|#[0-9a-fA-F]{8}|[a-zA-Z]{1,20})" title="Use a 3, 4, 6, or 8 digit hex color, or a CSS color name; leave blank for the default"></div>
</div>
<?php } ?>
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
