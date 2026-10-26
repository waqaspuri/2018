<?php
include "include/sessionAdmin.php";  
use PHPMailer\PHPMailer\PHPMailer;
require_once "../PHPMailer/PHPMailer.php";
require_once "../PHPMailer/Exception.php";
include "include/configure.php";
if(isset($_POST['submit']) && $_POST['submit']=="Submit"){
if(isset($_POST['action']) && $_POST['action']=="add"){
mysqli_query($conn,"INSERT INTO send_templates (isSMS,subject,message,createdDate) VALUES 
('0','".mysqli_real_escape_string($conn,$_POST['subject'])."','".mysqli_real_escape_string($conn,$_POST['message'])."', '".currDateTimeGmt()."')");
header("location: emailTemplates.php?act=1");
}else if(isset($_POST['action']) && $_POST['action']=="update"){
mysqli_query($conn,"UPDATE send_templates SET subject='". mysqli_real_escape_string($conn,$_POST['subject'])."',message='". mysqli_real_escape_string($conn,$_POST['message'])."' WHERE id='".$_POST['id']."'");	
header("location: emailTemplates.php?act=2");
}
}
if(isset($_GET['act']) && $_GET['act']=="delete"){
mysqli_query($conn,"DELETE FROM send_templates WHERE id='".$_GET['id']."'");	
header("location: emailTemplates.php?act=3");
}
$action = "add";
$hdTxt="Add New";
$subject = '';
$message = '';
$id = '';
if(isset($_GET['action']) && $_GET['action']=="edit"){
$sqlEdit = mysqli_query($conn,"SELECT * FROM send_templates WHERE id='".$_GET['id']."'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
extract($rowsEdit);
$action = "update";
$hdTxt="Update";
} 
include "include/header.php"; 
if(isset($_GET['action']) && $_GET['action']=="view"){ 
$sqlEdit = mysqli_query($conn,"SELECT * FROM send_templates WHERE id='".$_GET['id']."'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
extract($rowsEdit);
?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Email Templates</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>emailTemplates.php">Manage Email Templates</a></span></li>
<li><span>View Email Template</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>emailTemplates.php?action=edit&id=<?=$rowsEdit['id'];?>"><b>Edit</b></a> | <a href="<?=$siteUrl;?>emailTemplates.php"><b>Back</b></a> </div>
<h2 class="panel-title">View Email Template</h2>
</header>
<div class="panel-body viewFrm">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Subject</label>
<div class="col-md-9"><?=$subject?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Message</label>
<div class="col-md-9"><?=$message?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Created On</label>
<div class="col-md-9"><?=viewDtTm($createdDate)?></div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<button type="button" onclick="window.location.href='<?=$siteUrl;?>emailTemplates.php'" class="btn btn-default">Back</button>
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
<?php
}else if(isset($_GET['action']) && ($_GET['action']=="add" || $_GET['action']=="edit")){ 
?>
<script src="<?=$siteUrl?>assets/vendor/summernote/summernote.js"></script>
<link rel="stylesheet" href="<?=$siteUrl?>assets/vendor/summernote/summernote.css" />
<link rel="stylesheet" href="<?=$siteUrl?>assets/vendor/summernote/summernote-bs3.css" /> 
<link rel="stylesheet" href="<?=$siteUrl?>assets/bootstrap-select.css">
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Email Templates</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>emailTemplates.php">Manage Email Templates</a></span></li>
<li><span><?=$hdTxt;?> Email Template</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>emailTemplates.php"><b>Back</b></a> </div>
<h2 class="panel-title"><?=$hdTxt;?> Email Template</h2>
</header>
<div class="panel-body">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Subject</label>
<div class="col-md-9">
<input type="text" class="form-control" name="subject" value="<?=htmlspecialchars($subject ?? '', ENT_QUOTES, 'UTF-8');?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Message</label>
<div class="col-md-9">
<textarea class="summernote" id="message" name="message" data-plugin-summernote data-plugin-options='{ "height": 200, "codemirror": { "theme": "ambiance" } }'><?php echo htmlspecialchars($message ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
<style>

small {
 font-family: Arial;
 font-size: 9px;
 
}

</style>
</ br>
</ br>
<p>Merge Fields</p>
<table>
  <tr>
    <th>Unit price <small> (returns integer)</small></th>
    <th>{unit_price}</th>
  </tr>
  <tr>
 <th>Quanity price <small> (returns integer)</small></th>
    <td>{quantity}</td>
  </tr>
  <tr>
 <th>Total Amount <small>(returns integer)</small></th>
    <td>{total_amount}</td>
  </tr>
  <tr>
   <th>Transport<small> (returns integer)</small></th>
    <td>{order_transport}</td>
  </tr>
  <tr>
   <th>Product Name<small> (returns string) </small></th>
    <td>{product_name}</td>
  </tr>
  <tr>
   <th>Document No. <small> (returns mixed integer) </small></th>
    <td>{document_order_number}</td>
  </tr>
  <tr>
    <th>Proforma Invoice No. <small> (returns mixed) </small></th>
    <td>{proforma_invoice}</td>
  </tr>
  <tr>
 <th>Email (2)<small> (returns strings, symbol) </small></th>
    <td>{second_email}</td>
  </tr>
  <tr>
  <th>Vessel Arrival Date<small> (returns dd-mm-yyyy)</small></th>
    <td>{arrival_date}</td>
  </tr>
  <tr>
 <th>Vessel Departure Date<small> (returns dd-mm-yyyy)</small></th>
    <td>{departure_date}</td>
  </tr>
  <tr>
  <th>Latest Date of Shipment<small>(returns dd-mm-yyyy)</small></th>
    <td>{latest_date_shipment}</td>
  </tr>
  <tr>
    <th>Bill of Lading<small> (returns mixed integer)</small></th>
    <td>{document_bill_no}</td>
  </tr>
  <tr>
 <th>Document Expiry Date<small> (returns dd-mm-yyyy)</small></th>
    <td>{doucment_expiry_date}</td>
  </tr>
  <tr>
 <th>Re-assigned ETA<small> (returns dd-mm-yyyy)</small></th>
    <td>{arrival_new_date}</td>
  </tr>
  <tr>
   <th>Payment Terms <small> (returns integer)</small></th>
    <td>{payment_terms}</td>
  </tr>
  <tr>
    <th>Shipping Info / Tracking Number</th>
    <td>{shipping_info}</td>
  </tr>
</table>
</copyright><br />
</div>
</div>

<div class="form-group">
<label class="col-md-3 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<input type="hidden" name="action" value="<?=$action;?>" />
<input type="hidden" name="id" value="<?=$id;?>" />
<button class="btn btn-primary" name="submit" value="Submit">Submit</button>
<button type="button" onclick="window.location.href='<?=$siteUrl;?>emailTemplates.php'" class="btn btn-default">Cancel</button>
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
<?php }else{  ?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Email Template</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span>Manage Email Template</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act'])){
if($_GET['act']=="1"){
$msgTxt='New Email Template has been added successfully';
}else if($_GET['act']=="2"){
$msgTxt='Email Template has been updated successfully';
}else if($_GET['act']=="3"){
$msgTxt='Email Template has been deleted successfully';
}else if($_GET['act']=="4"){
$msgTxt='<b>Success Message</b> Cron Job has been run successfully';
}		
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.$msgTxt.'</strong></div>';
}
$dtAry = explode(" ",currDateTimeGmt());
?>
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>emailTemplates.php?action=add"><b>Add New</b></a> </div>
<h2 class="panel-title">Manage Email Template</h2>
</header>
<div class="panel-body">
<div class="form-group">
<div class="col-md-6">
<form class="form-horizontal form-bordered" method="get">
<input type="text" style="width:80%;float:left" class="form-control" name="q" value="<?=(isset($_GET['q'])?$_GET['q']:'')?>" placeholder="Search" id="inputDefault"  >
<input type="hidden" name="so" value="<?=(isset($_GET['so']) ? $_GET['so'] : '')?>" />
<button class="btn btn-primary" name="submit" value="Submit">GO</button>
</form> <span class="hintTxt">Search :-  Subject.</span>
</div>
<div class="col-md-6" align="right">&nbsp;</div>
</div>	
<div class="table-responsive">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="20%">Subject</th>
<th width="45%">Message</th>
<th width="10%">Created On</th>
<th width="20%" class="text-center">Action</th>
</tr>
</thead>
<tbody>
<?php
$page_name="emailTemplates.php?so=".(isset($_GET['so']) ? $_GET['so'] : '')."&q=".(isset($_GET['q']) ? $_GET['q'] : ''); 
if(!isset($_REQUEST["start"])) {
$start = 0;
}
else
$start = $_REQUEST["start"];

$eu = ($start - 0); 
$limit = 20;          
$this1 = $eu + $limit; 
$back = $eu - $limit; 
$next = $eu + $limit; 

$sqlSrch = "";
$sqlSort = ""; // Initialize $sqlSort
if(isset($_GET['q']) && $_GET['q']!=""){
$sqlSrch = " and (subject LIKE '%".$_GET['q']."%') ";
}
$sqlSeller = "SELECT * FROM send_templates where isSMS='0' ".$sqlSort.$sqlSrch." ORDER BY id DESC limit $eu, $limit";
$sqltot = "SELECT id FROM send_templates where isSMS='0' ".$sqlSort.$sqlSrch." ORDER BY id DESC ";

$resultSeller= mysqli_query($conn,$sqlSeller);
$resulttot=mysqli_query($conn,$sqltot);
$nume=mysqli_num_rows($resulttot);
if (@mysqli_num_rows($resultSeller)!=0){

$sno=$start;
while($rows=mysqli_fetch_assoc($resultSeller)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.stringTxtLimit($rows['subject'],50).'</td>
<td>'.stringTxtLimit($rows['message'],100).'</td>
<td align="center">'.viewDtTm($rows['createdDate']).'</td>
<td align="center">
<a title="View this record" class="btn btn-default viewBtn" href="emailTemplates.php?action=view&id='.$rows['id'].'">View</a>
<a title="Edit this record" class="btn btn-default editBtn" href="emailTemplates.php?action=edit&id='.$rows['id'].'">Edit</a>
<a title="Delete this record" class="btn btn-default delBtn" onclick="return confirm(\'Are you sure you want to delete?\');" href="emailTemplates.php?act=delete&id='.$rows['id'].'">Delete</a>
</td>
</tr>';
}
?>
<tr>
<td colspan="7">
<?php
echo "<table align = 'center' width='100%'><tr><td  align='left' width='30%'>";
if($back >=0) { 
print "<a href='$page_name&start=$back'><font face='Verdana' size='2'>PREV</font></a>"; 
} 
echo "</td><td align=center width='30%'>Page:";
$i=0;
$l=1;
$total=0;
for($i=0;$i < $nume;$i=$i+$limit){
if($i <> $eu){
echo " <a href='$page_name&start=$i'><font face='Verdana' size='2'>$l</font></a> ";
}
else { echo "<font face='Verdana' size='2' color=red>$l</font>";}        
$l=$l+1;
$total = $total+1;
}
echo " of $total</td><td  align='right' width='30%'>";
if($this1 < $nume) { 
print "<a href='$page_name&start=$next'><font face='Verdana' size='2'>NEXT</font></a>";} 
echo "</td></tr></table>";
?>
</td>
</tr><?php
}else{
echo "<tr>
<td colspan='7' align='center'>No Data Found.</td>
</tr>";
} ?>

</tbody>
</table>
</div>
</div>
</section>
</section>
</div>
</section>
<?php 
}
include "include/footer.php"; ?>
