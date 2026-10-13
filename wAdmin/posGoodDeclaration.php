<?php
include "include/sessionAdmin.php";  
include "include/configure.php";
if(isset($_POST['submit']) && $_POST['submit']=="Submit"){
if(isset($_POST['action']) && $_POST['action']=="add"){
mysqli_query($conn,"INSERT INTO pos_category (igmNo,igmDate,decleratantInfo,lcNo,exporterConsigner,exchangeRate,cfrValue,eFormNo,paymentTerms,deleiveryTerms,netWeight,grossWeight,noOfPackages,typeOfPackage,createdDate) VALUES 
('".mysqli_real_escape_string($conn,$_POST['igmNo'])."','".mysqli_real_escape_string($conn,$_POST['igmDate'])."','".mysqli_real_escape_string($conn,$_POST['decleratantInfo'])."','".mysqli_real_escape_string($conn,$_POST['lcNo'])."','".mysqli_real_escape_string($conn,$_POST['exporterConsigner'])."','".mysqli_real_escape_string($conn,$_POST['exchangeRate'])."','".mysqli_real_escape_string($conn,$_POST['cfrValue'])."','".mysqli_real_escape_string($conn,$_POST['eFormNo'])."','".mysqli_real_escape_string($conn,$_POST['paymentTerms'])."','".mysqli_real_escape_string($conn,$_POST['deleiveryTerms'])."','".mysqli_real_escape_string($conn,$_POST['netWeight'])."','".mysqli_real_escape_string($conn,$_POST['grossWeight'])."','".mysqli_real_escape_string($conn,$_POST['noOfPackages'])."','".mysqli_real_escape_string($conn,$_POST['typeOfPackage'])."','".currDateTimeGmt()."')");
$cID = mysqli_insert_id($conn);
$cstNm = $_POST['customCharges'];
$ccAmt = $_POST['ccAmt'];
foreach($cstNm as $key=>$cstNm){ 
	if($cstNm!=""){
		mysqli_query($conn,"INSERT INTO pos_custom_charges (ccAmt,title,pos_categoryID,createdDate) VALUES ('".mysqli_real_escape_string($conn,$ccAmt[$key])."','".mysqli_real_escape_string($conn,$cstNm)."','".mysqli_real_escape_string($conn,$cID)."','".currDateTimeGmt()."')");
	}
}
header("location: posGoodDeclaration.php?act=1");
}else if(isset($_POST['action']) && $_POST['action']=="update"){
mysqli_query($conn,"UPDATE pos_category SET igmNo='". mysqli_real_escape_string($conn,$_POST['igmNo'])."',igmDate='". mysqli_real_escape_string($conn,$_POST['igmDate'])."',decleratantInfo='". mysqli_real_escape_string($conn,$_POST['decleratantInfo'])."',lcNo='". mysqli_real_escape_string($conn,$_POST['lcNo'])."',exporterConsigner='". mysqli_real_escape_string($conn,$_POST['exporterConsigner'])."',exchangeRate='". mysqli_real_escape_string($conn,$_POST['exchangeRate'])."',cfrValue='". mysqli_real_escape_string($conn,$_POST['cfrValue'])."',eFormNo='". mysqli_real_escape_string($conn,$_POST['eFormNo'])."',paymentTerms='". mysqli_real_escape_string($conn,$_POST['paymentTerms'])."',deleiveryTerms='". mysqli_real_escape_string($conn,$_POST['deleiveryTerms'])."',netWeight='". mysqli_real_escape_string($conn,$_POST['netWeight'])."',grossWeight='". mysqli_real_escape_string($conn,$_POST['grossWeight'])."',noOfPackages='". mysqli_real_escape_string($conn,$_POST['noOfPackages'])."',typeOfPackage='". mysqli_real_escape_string($conn,$_POST['typeOfPackage'])."' WHERE id='".$_POST['id']."'");	
$cID = $_POST['id'];
$cstNm = $_POST['customCharges'];
$ccAmt = $_POST['ccAmt'];
foreach($cstNm as $key=>$cstNm){
	if($cstNm!=""){
		mysqli_query($conn,"INSERT INTO pos_custom_charges (ccAmt,title,pos_categoryID,createdDate) VALUES ('".mysqli_real_escape_string($conn,$ccAmt[$key])."','".mysqli_real_escape_string($conn,$cstNm)."','".mysqli_real_escape_string($conn,$cID)."','".currDateTimeGmt()."')");
	}
}
if(count($cstNm)>0){
header("location: posGoodDeclaration.php?action=edit&id=".$_POST['id'].'&actn=3#cc');
}else{
header("location: posGoodDeclaration.php?act=2");	
}
}else if(isset($_POST['action']) && $_POST['action']=="customCharges"){
mysqli_query($conn,"UPDATE pos_custom_charges SET title='". mysqli_real_escape_string($conn,$_POST['title'])."',ccAmt='". mysqli_real_escape_string($conn,$_POST['ccAmt'])."' WHERE id='".$_POST['ccID']."'");	
header("location: posGoodDeclaration.php?action=edit&act=2&id=".$_GET['id'].'&actn=1#cc');
}
}
if(isset($_GET['customChrge']) && $_GET['customChrge']=="delete"){
mysqli_query($conn,"DELETE FROM pos_custom_charges WHERE id='".$_GET['ccID']."'");	
header("location: posGoodDeclaration.php?action=edit&id=".$_GET['id']."&actn=2#cc");
}
if(isset($_GET['act']) && $_GET['act']=="delete"){
mysqli_query($conn,"DELETE FROM pos_custom_charges WHERE pos_categoryID='".$_GET['id']."'");	
mysqli_query($conn,"DELETE FROM pos_category WHERE id='".$_GET['id']."'");	
header("location: posGoodDeclaration.php?act=3");
}
$action = "add";
$hdTxt="Add New";
if(isset($_GET['action']) && $_GET['action']=="edit"){
$sqlEdit = mysqli_query($conn,"SELECT * FROM pos_category WHERE id='".$_GET['id']."'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
extract($rowsEdit);
$action = "update";
$hdTxt="Update";
} 
include "include/header.php"; 

if(isset($_GET['action']) && $_GET['action']=="view"){
$sqlEdit = mysqli_query($conn,"SELECT * FROM pos_category WHERE id='".$_GET['id']."'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
extract($rowsEdit);
?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Good Declaration</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>posGoodDeclaration.php">Manage Good Declaration</a></span></li>
<li><span>View Good Declaration</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>posGoodDeclaration.php"><b>Back</b></a> </div>
<h2 class="panel-title">View Good Declaration</h2>
</header>
<div class="panel-body viewFrm">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">IGM No</label>
<div class="col-md-8"><?=$igmNo?></div>
</div>
<div class="col-md-6">
<label class="col-md-3 control-label" style="padding-left:0;padding-right:0" for="inputDefault">IGM Date</label>
<div class="col-md-7"><?=$igmDate?></div>
</div>
</div>

<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Decleratant</label>
<div class="col-md-9"><?=$decleratantInfo?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">LC No</label>
<div class="col-md-9"><?=$lcNo?></div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Exporter Consigner</label>
<div class="col-md-8"><?=$exporterConsigner?></div>
</div>
<div class="col-md-6">
<label class="col-md-3 control-label" for="inputDefault">Exchange Rate</label>
<div class="col-md-7"><?=$exchangeRate?></div>
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">CFR Value</label>
<div class="col-md-8"><?=$cfrValue?></div>
</div>
<div class="col-md-6">
<label class="col-md-3 control-label" for="inputDefault">E-Form No</label>
<div class="col-md-7"><?=$eFormNo?></div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Payment Terms</label>
<div class="col-md-9"><?=$paymentTerms?></div>
</div> 
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Deleivery Terms</label>
<div class="col-md-9"><?=$deleiveryTerms?></div>
</div> 
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Net Weight</label>
<div class="col-md-7"><?=$netWeight?></div>
</div>
<div class="col-md-6">
<label class="col-md-3 control-label" for="inputDefault">Gross Weight</label>
<div class="col-md-7"><?=$grossWeight?></div>
</div>
</div>

<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">No Of Packages</label>
<div class="col-md-8"><?=$noOfPackages?></div>
</div>
<div class="col-md-6">
<label class="col-md-3 control-label" for="inputDefault">Type Of Package</label>
<div class="col-md-7"><?=$typeOfPackage?></div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Created Date</label>
<div class="col-md-9"><?=viewDtTm($createdDate)?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Custom Charges</label>
<div class="col-md-10">
<?php
$sqlCC = mysqli_query($conn,"SELECT * FROM pos_custom_charges where pos_categoryID='".$rowsEdit['id']."' ORDER BY id ASC");
$numRowsCC = mysqli_num_rows($sqlCC);
if($numRowsCC>0){
echo '<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="55%">Custom Charges</th>
<th width="40%">Amt</th>
</tr>
</thead>
<tbody>';
$sno=0;
$ccTl=0;
while($rowCC = mysqli_fetch_assoc($sqlCC)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.$rowCC['title'].'</td>
<td>Rs.'.$rowCC['ccAmt'].'</td>
</tr>';
$ccTl+=$rowCC['ccAmt'];
}
echo '<tr>
<td>&nbsp;</td>
<td><b>Total</b></td>
<td><b>Rs.'.$ccTl.'</b></td>
</tr>
</tbody>
</table>';
}
?>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Products</label>
<div class="col-md-10">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="60%">Title</th>
<th width="15%">Quantity</th>
<th width="20%" class="text-center">Created</th>
</tr>
</thead>
<tbody>
<?php
$sqlPrd = mysqli_query($conn,"SELECT p.*,ut.title FROM pos_products as p,pos_unit_types as ut where ut.id=p.posUnitTypeID AND p.posCategoryID='".$rowsEdit['id']."' ORDER BY p.id ASC");
$numRowsPrd = mysqli_num_rows($sqlPrd);
if($numRowsPrd>0){
$sno=0;
while($rows=mysqli_fetch_assoc($sqlPrd)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td><a href="posProducts.php?action=view&id='.$rows['id'].'">'.$rows['description'].' (H.S Code: '.$rows['hsCode'].')</a></td>
<td>'.$rows['prodQuantity'].' '.$rows['title'].'</td>
<td align="center">'.viewDtTm($rows['createdDate']).'</td>
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
<label class="col-md-5 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<button type="button" onclick="window.location.href='<?=$siteUrl;?>posGoodDeclaration.php'" class="btn btn-default">Back</button>
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
<style type="text/css">
.prodField{width:400px;}
.prodQty{width:100px;}
.prodPrice{width:120px;}
#table_exend tr td{padding:0 10px 10px 0;}
</style>
<script type="text/javascript">
function createGridDocs(tid){ 
var objTable = document.getElementById(tid); var tablerows=parseInt(objTable.rows.length); var objTableRow = objTable.insertRow(tablerows); var tabrow = tablerows; var i = tablerows; objTableRow.id = "row_c_"+i;  var objTableRowCell0 = objTableRow.insertCell(0); var objTableRowCell1 = objTableRow.insertCell(1); var objTableRowCell2 = objTableRow.insertCell(2); 
objTableRowCell0.innerHTML="<input type='text' placeholder='i.e. CD, ST, ACD etc.' name='customCharges[]' class='form-control' required >"; objTableRowCell1.innerHTML="<input type='number' step='any' placeholder='i.e. Amt' name='ccAmt[]' class='form-control' required >";  objTableRowCell2.innerHTML="<a href='#class_"+i+"' onclick='removeRow1(this)' title='Delete'><i class='fa fa-times delIcon'></i></a>"; 
}
function removeRow1(src){ var i=src.parentNode.parentNode.rowIndex; var objTable = document.getElementById('table_exend'); var objTableRows = parseInt(objTable.rows.length); var objLastRow; document.getElementById("table_exend").deleteRow(i); }
</script>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Good Declaration</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>posGoodDeclaration.php">Manage Good Declaration</a></span></li>
<li><span><?=$hdTxt;?> Good Declaration</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>posGoodDeclaration.php"><b>Back</b></a> </div>
<h2 class="panel-title"><?=$hdTxt;?> Good Declaration</h2>
</header>
<div class="panel-body">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">IGM No *</label>
<div class="col-md-8">
<input type="text" class="form-control" name="igmNo" value="<?=$igmNo?>" id="inputDefault" required >
</div>
</div>
<div class="col-md-6">
<label class="col-md-3 control-label" for="inputDefault" style="padding-left:0;padding-right:0;">IGM Date *</label>
<div class="col-md-8">
<input type="text" class="form-control" name="igmDate" value="<?=$igmDate?>" id="inputDefault" required >
</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Decleratant</label>
<div class="col-md-9">
<input type="text" class="form-control" name="decleratantInfo" value="<?=$decleratantInfo?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">LC No</label>
<div class="col-md-9">
<input type="text" class="form-control" name="lcNo" value="<?=$lcNo?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Exporter Consigner</label>
<div class="col-md-8">
<input type="text" class="form-control" name="exporterConsigner" value="<?=$exporterConsigner?>" id="inputDefault"  >
</div>
</div>
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Exchange Rate</label>
<div class="col-md-8">
<input type="text" class="form-control" name="exchangeRate" value="<?=$exchangeRate?>" id="inputDefault" >
</div>
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">CFR Value</label>
<div class="col-md-8">
<input type="text" class="form-control" name="cfrValue" value="<?=$cfrValue?>" id="inputDefault" >
</div>
</div>
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">E-Form No</label>
<div class="col-md-8">
<input type="text" class="form-control" name="eFormNo" value="<?=$eFormNo?>" id="inputDefault" >
</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Payment Terms</label>
<div class="col-md-9">
<input type="text" class="form-control" name="paymentTerms" value="<?=$paymentTerms?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Deleivery Terms</label>
<div class="col-md-9">
<input type="text" class="form-control" name="deleiveryTerms" value="<?=$deleiveryTerms?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Net Weight<a name="cc">&nbsp;</a></label>
<div class="col-md-8">
<input type="text" class="form-control" name="netWeight" value="<?=$netWeight?>" id="inputDefault" >
</div>
</div>
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Gross Weight</label>
<div class="col-md-8">
<input type="text" class="form-control" name="grossWeight" value="<?=$grossWeight?>" id="inputDefault"  >
</div>
</div>
</div>

<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">No Of Packages</label>
<div class="col-md-8">
<input type="text" class="form-control" name="noOfPackages" value="<?=$noOfPackages?>" id="inputDefault" >
</div>
</div>
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Type Of Package</label>
<div class="col-md-8">
<input type="text" class="form-control" name="typeOfPackage" value="<?=$typeOfPackage?>" id="inputDefault" >
</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputReadOnly">Custom Charges</label>
<div class="col-md-8">
<?php
if(isset($_GET['actn'])){
if($_GET['actn']=="1"){
$msgTxt='Custom Charges has been updated successfully';
}else if($_GET['actn']=="2"){
$msgTxt='Custom Charges has been deleted successfully';
}else if($_GET['actn']=="3"){
$msgTxt='Custom Charges has been added successfully';
}	
	
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.$msgTxt.'</strong></div>';
}

$sqlCC = mysqli_query($conn,"SELECT * FROM pos_custom_charges where pos_categoryID='".$rowsEdit['id']."' ORDER BY id ASC");
$numRowsCC = mysqli_num_rows($sqlCC);
if($numRowsCC>0){
echo '<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="35%">Custom Charges</th>
<th width="30%">Amt</th>
<th width="30%" class="text-center">Action</th>
</tr>
</thead>
<tbody>';
$sno=0;
while($rowCC = mysqli_fetch_assoc($sqlCC)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.$rowCC['title'].'</td>
<td>'.$rowCC['ccAmt'].'</td>
<td align="center">
<a title="Edit this record" class="btn btn-default editBtn" href="posGoodDeclaration.php?action=edit&customChrge=edit&ccID='.$rowCC['id'].'&id='.$rowsEdit['id'].'#cc">Edit</a>
<a title="Delete this record" class="btn btn-default delBtn" onclick="return confirm(\'Are you sure you want to delete?\');" href="posGoodDeclaration.php?customChrge=delete&ccID='.$rowCC['id'].'&id='.$rowsEdit['id'].'">Delete</a>
</td>
</tr>';
}
echo '</tbody>
</table>';
}
if($_GET['customChrge']=="edit"){
$sqlEditCC = mysqli_query($conn,"SELECT * FROM pos_custom_charges WHERE id='".$_GET['ccID']."'");
$rowsEditCC = mysqli_fetch_assoc($sqlEditCC);
$action = "customCharges";
?>
<p>&nbsp;</p>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault"><b>Update</b></label>
<div class="col-md-3">
<input type="text" class="form-control" name="title" value="<?=$rowsEditCC['title']?>" id="inputDefault" >
</div>
<div class="col-md-3">
<input type="number" step="any" class="form-control" name="ccAmt" value="<?=$rowsEditCC['ccAmt']?>" id="inputDefault" >
</div>
<div class="col-md-4">
<input type="hidden" name="action" value="customCharges" />
<input type="hidden" name="ccID" value="<?=$rowsEditCC['id'];?>" />
<input type="hidden" name="id" value="<?=$_GET['id'];?>" />
<button class="btn btn-primary" name="submit" value="Submit">Submit</button>
<button type="button" onclick="window.location.href='<?=$siteUrl;?>action=edit&id=<?=$_GET['id']?>'" class="btn btn-default">Cancel</button>
</div>
</div>
<?php }else{?>
<p>&nbsp;</p>
<a name="apt">&nbsp;</a>
<a class="btn btn-primary" href="javascript:void(0)" title="Add Items" name="lnk" id="lnk" onClick="createGridDocs('table_exend');" >Add New Item</a><br />&nbsp;
<table id="table_exend" border="0" cellpadding="5" cellspacing="5"></table>	
<?php } ?>
</div>
</div>
<?php if($_GET['customChrge']!="edit"){?>
<div class="form-group">
<label class="col-md-5 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<input type="hidden" name="action" value="<?=$action;?>" />
<input type="hidden" name="id" value="<?=$id;?>" />
<button class="btn btn-primary" name="submit" value="Submit">Submit</button>
<button type="button" onclick="window.location.href='<?=$siteUrl;?>posGoodDeclaration.php'" class="btn btn-default">Cancel</button>
</div>
</div>
<?php }?> 
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
<h2>Manage Good Declaration</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span>Manage Good Declaration</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act'])){
if($_GET['act']=="1"){
$msgTxt='New Good Declaration has been added successfully';
}else if($_GET['act']=="2"){
$msgTxt='Good Declaration has been updated successfully';
}else if($_GET['act']=="3"){
$msgTxt='Good Declaration has been deleted successfully';
}	
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.$msgTxt.'</strong></div>';
}
?>
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>posGoodDeclaration.php?action=add"><b>Add New</b></a> </div>
<h2 class="panel-title">Manage Good Declaration</h2>
</header>
<div class="panel-body">
<div class="form-group">
<div class="col-md-6">
<form class="form-horizontal form-bordered" method="get">
<input type="text" style="width:80%;float:left" class="form-control" name="q" value="<?=htmlspecialchars((string)($_GET['q'] ?? ''), ENT_QUOTES, 'UTF-8')?>" placeholder="Search" id="inputDefault"  >
<button class="btn btn-primary" name="submit" value="Submit">GO</button>
</form> <span class="hintTxt">Search :-  IGM No, LC No.</span>
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
<th width="20%">IGM No</th>
<th width="20%">LC No</th>
<th width="15%">E-Form No</th>
<th width="18%" class="text-center">Created On</th>
<th width="22%" class="text-center">Action</th>
</tr>
</thead>
<tbody>
<?php
$search = trim((string)($_GET['q'] ?? ''));
$safeSearch = mysqli_real_escape_string($conn, $search);
$page_name = "posGoodDeclaration.php?q=" . urlencode($search);
$start = max(0, (int)($_GET["start"] ?? 0));

$eu = ($start - 0); 
$limit = 20;          
$this1 = $eu + $limit; 
$back = $eu - $limit; 
$next = $eu + $limit; 
$sqlSrch = "";
if($search !== ""){
$sqlSrch = " WHERE (igmNo LIKE '%".$safeSearch."%' OR lcNo LIKE '%".$safeSearch."%' OR eFormNo LIKE '%".$safeSearch."%') ";
}
$sqlSeller = "SELECT id,igmNo,lcNo,eFormNo,createdDate FROM pos_category ".$sqlSrch." ORDER BY id DESC limit $eu, $limit";
$sqltot = "SELECT id FROM pos_category ".$sqlSrch." ORDER BY id DESC ";

$resultSeller= mysqli_query($conn,$sqlSeller);
$resulttot=mysqli_query($conn,$sqltot);
$nume=mysqli_num_rows($resulttot);
if (@mysqli_num_rows($resultSeller)!=0){

$sno=$start;
while($rows=mysqli_fetch_assoc($resultSeller)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.$rows['igmNo'].'</td>
<td>'.$rows['lcNo'].'</td>
<td>'.$rows['eFormNo'].'</td>
<td align="center">'.viewDtTm($rows['createdDate']).'</td>
<td align="center">
<a title="View this record" class="btn btn-default viewBtn" href="posGoodDeclaration.php?action=view&id='.$rows['id'].'">View</a>
<a title="Edit this record" class="btn btn-default editBtn" href="posGoodDeclaration.php?action=edit&id='.$rows['id'].'">Edit</a>
<a title="Delete this record" class="btn btn-default delBtn" onclick="return confirm(\'Are you sure you want to delete?\');" href="posGoodDeclaration.php?act=delete&id='.$rows['id'].'">Delete</a>
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
