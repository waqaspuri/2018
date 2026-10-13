<?php
include "include/sessionAdmin.php";  
include "include/configure.php";
if(isset($_POST['submit']) && $_POST['submit']=="Submit"){
if(isset($_POST['action']) && $_POST['action']=="add"){
$imageName = $_FILES["paymentFile"]["name"];
if(!empty($imageName)) {
$strDtMix = @date("d").substr((string)microtime(), 2, 6);
$uploadfile = $strDtMix.".".pathinfo($imageName, PATHINFO_EXTENSION);
move_uploaded_file($_FILES['paymentFile']['tmp_name'], "../img/".$uploadfile);
}
$sqlBln = mysqli_query($conn,"SELECT balAmt FROM pos_buyer_account where posBuyerID='".$_POST['customerID']."' ORDER BY id DESC");
$rowBln = mysqli_fetch_assoc($sqlBln);				

if($_POST['payMode']=="Add"){
$debitAmt  = $_POST['totalAmt'];
$creditAmt = 0;
if($rowBln['balAmt']>0){
$balAmt    = $rowBln['balAmt']-$debitAmt;
}else{
$balAmt    = $rowBln['balAmt']-$debitAmt;
}
}else{
$debitAmt  = 0;
$creditAmt = $_POST['totalAmt'];
if($rowBln['balAmt']>0){
$balAmt    = $rowBln['balAmt']+$creditAmt;
}else{
$balAmt    = $rowBln['balAmt']+$creditAmt;
}
}
mysqli_query($conn,"INSERT INTO pos_buyer_account (posBuyerID,paymentMethod,paymentDate,paymentStatus,bankDetails,paymentFile,creditAmt,debitAmt,balAmt,createdDate) VALUES 
('".mysqli_real_escape_string($conn,$_POST['customerID'])."','".mysqli_real_escape_string($conn,$_POST['paymentMethod'])."','".mysqli_real_escape_string($conn,sqlDtFormat($_POST['paymentDate']))."','".mysqli_real_escape_string($conn,$_POST['paymentStatus'])."','".mysqli_real_escape_string($conn,$_POST['bankDetails'])."','".mysqli_real_escape_string($conn,$uploadfile)."','".mysqli_real_escape_string($conn,$creditAmt)."','".mysqli_real_escape_string($conn,$debitAmt)."','".mysqli_real_escape_string($conn,$balAmt)."','".currDateTimeGmt()."')");
header("location: posPayments.php?act=1");
}
}
$action = "add";
$hdTxt="Add New"; 
include "include/header.php"; 
?>
<link rel="stylesheet" href="<?=$siteUrl?>assets/bootstrap-select.css">
<script src="<?=$siteUrl?>assets/bootstrap-select.js"></script>
<script type="text/javascript">$(document).ready(function(){ $(".paymentDate").datepicker({todayHighlight:true,autoclose:true}); });</script>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Buyer Payment</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><?=$hdTxt;?> Buyer Payment</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act'])){
if($_GET['act']=="1"){
$msgTxt='Payment has been updated successfully';
}	
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.$msgTxt.'</strong></div>';
}
?>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>posPayments.php"><b>Back</b></a> </div>
<h2 class="panel-title"><?=$hdTxt;?> Payment</h2>
</header>
<div class="panel-body">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Buyer</label>
<div class="col-md-9 selectSrch">
<select class="selectpicker form-control" data-selected-text-format="values" name="customerID" id="customerID" data-live-search="true" data-live-search-placeholder="Search" required>
<option value="">- Select -</option>
<?php
$sqlCst = mysqli_query($conn,"SELECT id,name FROM pos_buyers ORDER BY name ASC");
while($rowsCst = mysqli_fetch_assoc($sqlCst)){						
echo '<option value="'.$rowsCst['id'].'" >'.$rowsCst['name'].'</option>';
}
?>
</select>
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Payment Method</label>
<div class="col-md-8 selectSrch">
<select class="form-control" name="paymentMethod" id="paymentMethod" required >
<option value=""> - Select -</option>
<option value="By Cheque">By Cheque</option>
<option value="Pay Order">Pay Order</option>
<option value="Pay Direct Deposit">Pay Direct Deposit</option>
</select>
</div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" for="inputDefault">Date</label>
<div class="col-md-8">
<input type="text" class="form-control paymentDate" readonly="readonly" data-date-format="dd-mm-yyyy" placeholder="Payment Date (dd-mm-yyyy) *" name="paymentDate" value="" id="inputDefault" required >
</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Pay Mode</label>
<div class="col-md-9" style="margin:6px 0 0 0 ">
<input type="radio" name="payMode" value="Add" required="required" /> Add Payment &nbsp; <input type="radio" name="payMode" value="Reverse" required="required" /> Reverse Payment
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Total Amount</label>
<div class="col-md-8 selectSrch">
<input type="number" step="any" min="0" class="form-control" name="totalAmt" value="<?=$totalAmt?>" id="inputDefault" required >
</div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" for="inputDefault">Reason</label>
<div class="col-md-8">
<input type="text" class="form-control" name="paymentStatus" value="<?=$paymentStatus?>" id="inputDefault" >
</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Bank Details</label>
<div class="col-md-9">
<textarea cols="2" rows="2" name="bankDetails" class="form-control"></textarea>
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Attachment</label>
<div class="col-md-8">
<input type="file" class="form-control" name="paymentFile"  />
</div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" for="inputDefault">&nbsp;</label>
<div class="col-md-10">
<input type="hidden" name="action" value="<?=$action;?>" />
<button class="btn btn-primary" name="submit" value="Submit">Submit</button>
<button type="button" onclick="window.location.href='<?=$siteUrl;?>posPayments.php'" class="btn btn-default">Cancel</button>
</div>
</div>
</div>

<div class="form-group">
<div class="col-md-12">
&nbsp;
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