<?php
include "include/sessionAdmin.php";  
include "include/configure.php";
if(isset($_POST['submit']) && $_POST['submit']=="Submit"){
if(isset($_POST['action']) && $_POST['action']=="add"){
mysqli_query($conn,"INSERT INTO pos_buyers (ntn,name,address,telephone,gst,createdDate) VALUES ('".mysqli_real_escape_string($conn,$_POST['ntn'])."','".mysqli_real_escape_string($conn,$_POST['name'])."','".mysqli_real_escape_string($conn,$_POST['address'])."','".mysqli_real_escape_string($conn,$_POST['telephone'])."','".mysqli_real_escape_string($conn,$_POST['gst'])."','".currDateTimeGmt()."')");
header("location: posBuyers.php?act=1");
}else if(isset($_POST['action']) && $_POST['action']=="update"){
mysqli_query($conn,"UPDATE pos_buyers SET ntn='". mysqli_real_escape_string($conn,$_POST['ntn'])."',name='". mysqli_real_escape_string($conn,$_POST['name'])."',address='". mysqli_real_escape_string($conn,$_POST['address'])."',telephone='". mysqli_real_escape_string($conn,$_POST['telephone'])."',gst='". mysqli_real_escape_string($conn,$_POST['gst'])."' WHERE id='".$_POST['id']."'");	
header("location: posBuyers.php?act=2");
}
}
if(isset($_GET['act']) && $_GET['act']=="delete"){
mysqli_query($conn,"DELETE FROM pos_buyers WHERE id='".$_GET['id']."'");	
header("location: posBuyers.php?act=3");
}
$action = "add";
$hdTxt="Add New";
if(isset($_GET['action']) && $_GET['action']=="edit"){
$sqlEdit = mysqli_query($conn,"SELECT * FROM pos_buyers WHERE id='".$_GET['id']."'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
extract($rowsEdit);
$action = "update";
$hdTxt="Update";
} 
include "include/header.php"; 
if(isset($_GET['action']) && $_GET['action']=="view"){
$sqlEdit = mysqli_query($conn,"SELECT * FROM pos_buyers WHERE id='".$_GET['id']."'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
extract($rowsEdit);
?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Buyers</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>posBuyers.php">Manage Buyers</a></span></li>
<li><span>View Buyer</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>posBuyers.php"><b>Back</b></a> </div>
<h2 class="panel-title">View Buyers</h2>
</header>
<div class="panel-body viewFrm">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Name</label>
<div class="col-md-9"><?=$name?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Address</label>
<div class="col-md-9"><?=$address?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Mobile Number</label>
<div class="col-md-9"><?=$telephone?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">GST</label>
<div class="col-md-9"><?=$gst?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">NTN</label>
<div class="col-md-9"><?=$ntn?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Created Date</label>
<div class="col-md-9"><?=viewDtTm($createdDate)?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Payments</label>
<div class="col-md-10">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="35%">Title</th>
<th width="15%">Date</th>
<th width="15%" class="text-right">Debit &nbsp;</th>
<th width="15%" class="text-right">Credit &nbsp;</th>
<th width="15%" class="text-right">Balance &nbsp;</th>
</tr>
</thead>
<tbody>
<?php
$sqlPym = mysqli_query($conn,"SELECT * FROM pos_buyer_account WHERE posBuyerID='".$rowsEdit['id']."'  ORDER BY id DESC");
$numRowPym = mysqli_num_rows($sqlPym);
if($numRowPym>0){
$sno=0;
while($rowPym = mysqli_fetch_assoc($sqlPym)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>';
if($rowPym['isDelete']){
echo $rowPym['deleteReason'];	
}else{
if($rowPym['posSaleID']){
	
$sqlInvNo = mysqli_query($conn,"SELECT invoiceNo FROM pos_sale_billing WHERE id='".$rowPym['posSaleID']."' ");
$numRowInvNo = mysqli_num_rows($sqlInvNo);
if($numRowInvNo>0){
	$rowInvNo = mysqli_fetch_assoc($sqlInvNo);	
	echo 'Invoice No :- '.$rowInvNo['invoiceNo'].' - <a title="Download PDF" href="'.$siteUrlMain.'posInvoicePDF.php?act=pdfDwnld&id='.$rowPym['posSaleID'].'"><b>Download PDF</b></a>';
}else{
	echo 'Invoice No :- '.$rowPym['invoiceNo'].' - <b>Deleted<b/>';
}

}else{
echo 'Bank :- '.$rowPym['bankDetails'].' - '.(($rowPym['paymentMethod'])?$rowPym['paymentMethod']:'').'
'.(($rowPym['paymentFile'])?'<br /><a href="../img/'.$rowPym['paymentFile'].'" target="_blank">View File</a>':'');
}
}
echo '</td>
<td align="left">'.viewDateFormat($rowPym['paymentDate']).' &nbsp;</td>
<td align="right">'.(($rowPym['debitAmt']>0)?priceFormatRound($rowPym['debitAmt']):'').' &nbsp;</td>
<td align="right">'.(($rowPym['creditAmt']>0)?priceFormatRound($rowPym['creditAmt']):'').' &nbsp;</td>
<td align="right">'.(($rowPym['balAmt']>0)?'-'.priceFormatRound($rowPym['balAmt']):priceFormatRound(str_replace("-","",$rowPym['balAmt']))).' &nbsp;</td>
</tr>';
}
}else{
echo "<tr>
<td colspan='8' align='center'>No Data Found.</td>
</tr>";
}
?>
</tbody>
</table>
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<button type="button" onclick="window.location.href='<?=$siteUrl;?>posBuyers.php'" class="btn btn-default">Back</button>
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
}else if((isset($_GET['action']) && $_GET['action']=="add") || (isset($_GET['action']) && $_GET['action']=="edit")){ 
?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Buyers</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>posBuyers.php">Manage Buyers</a></span></li>
<li><span><?=$hdTxt;?> Buyer</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>posBuyers.php"><b>Back</b></a> </div>
<h2 class="panel-title"><?=$hdTxt;?> Buyers</h2>
</header>
<div class="panel-body">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Name</label>
<div class="col-md-9">
<input type="text" class="form-control" name="name" value="<?=$name?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Address</label>
<div class="col-md-9">
<input type="text" class="form-control" name="address" value="<?=$address?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Telephone</label>
<div class="col-md-9">
<input type="text" class="form-control" name="telephone" value="<?=$telephone?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">GST</label>
<div class="col-md-9">
<input type="text" class="form-control" name="gst" value="<?=$gst?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">NTN</label>
<div class="col-md-9">
<input type="text" class="form-control" name="ntn" value="<?=$ntn?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<input type="hidden" name="action" value="<?=$action;?>" />
<input type="hidden" name="id" value="<?=$id;?>" />
<button class="btn btn-primary" name="submit" value="Submit">Submit</button>
<button type="button" onclick="window.location.href='<?=$siteUrl;?>posBuyers.php'" class="btn btn-default">Cancel</button>
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
<h2>Manage Buyers</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span>Manage Buyers</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act'])){
if($_GET['act']=="1"){
$msgTxt='New Buyer has been added successfully';
}else if($_GET['act']=="2"){
$msgTxt='Buyer has been updated successfully';
}else if($_GET['act']=="3"){
$msgTxt='Buyer has been deleted successfully';
}	
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.$msgTxt.'</strong></div>';
}
?>
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>posBuyers.php?action=add"><b>Add New</b></a> </div>
<h2 class="panel-title">Manage Buyers</h2>
</header>
<div class="panel-body">
<div class="form-group">
<div class="col-md-6">
<form class="form-horizontal form-bordered" method="get">
<input type="text" style="width:80%;float:left" class="form-control" name="q" value="<?=(isset($_GET['q'])?$_GET['q']:'')?>" placeholder="Search" id="inputDefault"  >
<button class="btn btn-primary" name="submit" value="Submit">GO</button>
</form> <span class="hintTxt">Search :-  Name, Mobile, GST.</span>
</div>
<div class="col-md-6" align="right">
&nbsp;
</div>
</div>	
<div class="table-responsive">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="20%">Name</th>
<th width="35%">Address</th>
<th width="18%" class="text-center">Created On</th>
<th width="22%" class="text-center">Action</th>
</tr>
</thead>
<tbody>
<?php
$search = trim((string)($_GET['q'] ?? ''));
$page_name = "posBuyers.php?q=" . rawurlencode($search);
$start = max(0, (int)($_GET['start'] ?? 0));

$eu = ($start - 0); 
$limit = 20;          
$this1 = $eu + $limit; 
$back = $eu - $limit; 
$next = $eu + $limit; 
$sqlSrch = "";
if($search !== ""){
$safeSearch = mysqli_real_escape_string($conn, $search);
$sqlSrch = " WHERE (name LIKE '%".$safeSearch."%' OR telephone LIKE '%".$safeSearch."%' OR gst LIKE '%".$safeSearch."%') ";
}
$sqlSeller = "SELECT * FROM pos_buyers ".$sqlSrch." ORDER BY id DESC limit $eu, $limit";
$sqltot = "SELECT id FROM pos_buyers ".$sqlSrch." ORDER BY id DESC ";

$resultSeller= mysqli_query($conn,$sqlSeller);
$resulttot=mysqli_query($conn,$sqltot);
$nume=mysqli_num_rows($resulttot);
if (@mysqli_num_rows($resultSeller)!=0){

$sno=$start;
while($rows=mysqli_fetch_assoc($resultSeller)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.$rows['name'].'<br />
<a title="View this record"  href="posBuyers.php?action=view&id='.$rows['id'].'"><b>View</b></a> | 
<a title="Edit this record"  href="posBuyers.php?action=edit&id='.$rows['id'].'"><b>Edit</b></a> | 
<a title="Delete this record"  onclick="return confirm(\'Are you sure you want to delete?\');" href="posBuyers.php?act=delete&id='.$rows['id'].'"><b>Delete</b></a>
</td>
<td>'.$rows['address'].'<br />
<b>Tel:- </b> '.$rows['telephone'].'</td>
<td align="center">'.viewDtTm($rows['createdDate']).'</td>
<td align="center">'.buyerBalance($rows['id']).'</td>
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
