<?php
include "include/sessionAdmin.php";  
include "include/configure.php";
if(isset($_POST['submit']) && $_POST['submit']=="Submit"){
if(isset($_POST['action']) && $_POST['action']=="add"){
mysqli_query($conn,"INSERT INTO pos_products (purchaseType,prodQtyOrg,prodValue,prodSaleTax,posCategoryID,itemNo,prodQuantity,posUnitTypeID,sroNo,hsCode,description,unitDeclared,unitAssessed,totalDeclared,totalAssessed,customDeclared,customAssessed,createdDate) VALUES ('".mysqli_real_escape_string($conn,$_POST['purchaseType'])."','".mysqli_real_escape_string($conn,$_POST['prodQuantity'])."','".mysqli_real_escape_string($conn,$_POST['prodValue'])."','".mysqli_real_escape_string($conn,$_POST['prodSaleTax'])."','".mysqli_real_escape_string($conn,$_POST['posCategoryID'])."','".mysqli_real_escape_string($conn,$_POST['itemNo'])."','".mysqli_real_escape_string($conn,$_POST['prodQuantity'])."','".mysqli_real_escape_string($conn,$_POST['posUnitTypeID'])."','".mysqli_real_escape_string($conn,$_POST['sroNo'])."','".mysqli_real_escape_string($conn,$_POST['hsCode'])."','".mysqli_real_escape_string($conn,$_POST['description'])."','".mysqli_real_escape_string($conn,$_POST['unitDeclared'])."','".mysqli_real_escape_string($conn,$_POST['unitAssessed'])."','".mysqli_real_escape_string($conn,$_POST['totalDeclared'])."','".mysqli_real_escape_string($conn,$_POST['totalAssessed'])."','".mysqli_real_escape_string($conn,$_POST['customDeclared'])."','".mysqli_real_escape_string($conn,$_POST['customAssessed'])."','".currDateTimeGmt()."')");
$pID = mysqli_insert_id($conn);
$cstValPKR = $_POST['customValuePKR'];
$cstVal = $_POST['customValueID'];
foreach($cstVal as $ky=>$val){ 
mysqli_query($conn,"INSERT INTO pos_product_customs (pos_pID,pos_CCID,pos_amt) VALUES ('".mysqli_real_escape_string($conn,$pID)."','".mysqli_real_escape_string($conn,$val)."','".mysqli_real_escape_string($conn,$cstValPKR[$ky])."')");
}
header("location: posProducts.php?act=1");
}else if(isset($_POST['action']) && $_POST['action']=="update"){
mysqli_query($conn,"UPDATE pos_products SET purchaseType='". mysqli_real_escape_string($conn,$_POST['purchaseType'])."',prodSaleTax='". mysqli_real_escape_string($conn,$_POST['prodSaleTax'])."',prodValue='". mysqli_real_escape_string($conn,$_POST['prodValue'])."',posCategoryID='". mysqli_real_escape_string($conn,$_POST['posCategoryID'])."',itemNo='". mysqli_real_escape_string($conn,$_POST['itemNo'])."',prodQuantity='". mysqli_real_escape_string($conn,$_POST['prodQuantity'])."',posUnitTypeID='". mysqli_real_escape_string($conn,$_POST['posUnitTypeID'])."',sroNo='". mysqli_real_escape_string($conn,$_POST['sroNo'])."',hsCode='". mysqli_real_escape_string($conn,$_POST['hsCode'])."',description='". mysqli_real_escape_string($conn,$_POST['description'])."',totalDeclared='". mysqli_real_escape_string($conn,$_POST['totalDeclared'])."',unitDeclared='". mysqli_real_escape_string($conn,$_POST['unitDeclared'])."',unitAssessed='". mysqli_real_escape_string($conn,$_POST['unitAssessed'])."',totalAssessed='". mysqli_real_escape_string($conn,$_POST['totalAssessed'])."',customDeclared='". mysqli_real_escape_string($conn,$_POST['customDeclared'])."',customAssessed='". mysqli_real_escape_string($conn,$_POST['customAssessed'])."' WHERE id='".$_POST['id']."'");	
$pID = $_POST['id'];
mysqli_query($conn,"DELETE FROM pos_product_customs WHERE pos_pID='".$pID."'");	
$cstValPKR = $_POST['customValuePKR'];
$cstVal = $_POST['customValueID'];
foreach($cstVal as $ky=>$val){ 
mysqli_query($conn,"INSERT INTO pos_product_customs (pos_pID,pos_CCID,pos_amt) VALUES ('".mysqli_real_escape_string($conn,$pID)."','".mysqli_real_escape_string($conn,$val)."','".mysqli_real_escape_string($conn,$cstValPKR[$ky])."')");
}

header("location: posProducts.php?act=2");
}
}
if(isset($_GET['act']) && $_GET['act']=="delete"){
mysqli_query($conn,"DELETE FROM pos_products WHERE id='".$_GET['id']."'");	
header("location: posProducts.php?act=3");
}
$rowsEdit = array('posCategoryID' => 0, 'id' => 0);
$posCategoryID = $posUnitTypeID = $id = '';
$purchaseType = $itemNo = $prodQuantity = $prodQtyOrg = '';
$prodValue = $prodSaleTax = $sroNo = $hsCode = $description = '';
$unitDeclared = $unitAssessed = $totalDeclared = $totalAssessed = '';
$customDeclared = $customAssessed = '';
$action = "add";
$hdTxt="Add New";
if(isset($_GET['action']) && $_GET['action']=="edit"){
$sqlEdit = mysqli_query($conn,"SELECT * FROM pos_products WHERE id='".$_GET['id']."'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
extract($rowsEdit);
$action = "update";
$hdTxt="Update";
} 
include "include/header.php"; 
if(isset($_GET['action']) && $_GET['action']=="view"){
$sqlEdit = mysqli_query($conn,"SELECT * FROM pos_products WHERE id='".$_GET['id']."'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
extract($rowsEdit);
?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Products</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>posProducts.php">Manage Products</a></span></li>
<li><span>View Product</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a title="Edit this record" href="posProducts.php?action=edit&id=<?=$rowsEdit['id'];?>"><b>Edit</b></a> | <a href="<?=$siteUrl;?>posProducts.php"><b>Back</b></a> </div>
<h2 class="panel-title">View Product &raquo; <?=$description.' ('.$hsCode.')'?></h2>
</header>
<div class="panel-body viewFrm">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<label class="col-md-4 control-label" for="inputDefault">Good Declaration</label>
<div class="col-md-8"><?=posCateNm($posCategoryID)?></div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Item No</label>
<div class="col-md-7"><?=$itemNo?></div>
</div>
<div class="col-md-6">
<label class="col-md-3 control-label" style="padding-left:0;padding-right:0" for="inputDefault">Purchase Type</label>
<div class="col-md-8"><?=$purchaseType?></div>
</div></div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Quantity</label>
<div class="col-md-8"><?=$prodQuantity.' '.(($prodQuantity<0)?'<span class="redTxt">NEGATIVE QTY</span>':'')?>
&nbsp; &nbsp; 
<b>Opening Qty :-</b> <?=$prodQtyOrg;?>
</div>
</div>
<div class="col-md-6">
<label class="col-md-3 control-label" style="padding-left:0;padding-right:0" for="inputDefault">Units</label>
<div class="col-md-7"><?=posUnitType($posUnitTypeID)?></div>
</div>


</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Product Value</label>
<div class="col-md-8"><?=$prodValue?></div>
</div>
<div class="col-md-6">
<label class="col-md-3 control-label" style="padding-left:0;padding-right:0" for="inputDefault">Sale Tax</label>
<div class="col-md-7"><?=$prodSaleTax?></div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Opening Balance</label>
<div class="col-md-10">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="30%"><b>TOTAL SALES TAX</b></th>
<td width="35%" align="right"><b><?php
$totalVal = ($prodQtyOrg*$prodValue);
$taxAmt = priceFormat(($prodSaleTax/100) * $totalVal);
echo 'Rs.'.$totalVal.' ('.$prodQtyOrg.'x'.$prodValue.') @'.$prodSaleTax.'%';
?></b></td>
<td width="35%"  align="right"><b>Rs.<?=priceFormatRound($taxAmt);?></b> &nbsp;</td>
</tr>
<tr>
<th width="30%"><b>TOTAL AMOUNT</b> &nbsp;</th>
<td width="35%" align="right"><b><?php
$totalVal = ($prodQtyOrg*$prodValue);
echo 'Rs.'.$totalVal.' ('.$prodQtyOrg.'x'.$prodValue.')';
?></b></td>
<td width="35%"  align="right"><b>Rs.<?=priceFormatRound($totalVal);?></b> &nbsp;</td>
</tr>
<tr>
<th width="30%"><b>TOTAL (With Sale Tax)</b> &nbsp;</th>
<td width="35%" colspan="2" align="right"><b>Rs.<?=priceFormatRound($totalVal+$taxAmt);?></b> &nbsp;</td>
</tr>
</thead>
</table>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Outstanding</label>
<?php
$sqlPrdCmp = mysqli_query($conn,"SELECT * FROM pos_sale_order_products WHERE productID='".$rowsEdit['id']."'");
$numRowsPrdCmp = mysqli_num_rows($sqlPrdCmp);
if($numRowsPrdCmp>0){
$ttCmp =$ttST = 0;
while($rowCP = mysqli_fetch_assoc($sqlPrdCmp)){
$sno++;
$tAmt =  $rowCP['valueOfGood'];
$taxAmt = priceFormat(($rowCP['prodTax']/100) * $tAmt);
$ttST+=$taxAmt;
$ttCmp+=$tAmt;
}
}else{
$ttCmp =$ttST = 0;
}
?>
<div class="col-md-10">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="30%"><b>TOTAL SALES TAX </b></th>
<td width="70%"  align="right"><b>
<?php
$totalVal = ($prodQtyOrg*$prodValue);
$taxAmt = priceFormat(($prodSaleTax/100) * $totalVal);
$totalCompt = ($taxAmt-$ttST);
echo 'Rs.'.priceFormatRound($totalCompt);
?>
</b> &nbsp;</td>
</tr>
<tr>
<th width="30%"><b>TOTAL AMOUNT</b> &nbsp;</th>
<td width="70%"  align="right"><b>
<?php
$totalSaleAmt = ($totalVal-$ttCmp);
echo 'Rs.'.priceFormatRound($totalSaleAmt);
?>
</b> &nbsp;</td>
</tr>
<tr>
<th width="30%"><b>Total Value (incl. Sales tax)</b> &nbsp;</th>
<td width="70%"  align="right"><b>
<?php
echo 'Rs.'.priceFormatRound($totalCompt+$totalSaleAmt);
?>
</b> &nbsp;</td>
</tr>
</thead>
</table>
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">SRO No</label>
<div class="col-md-8"><?=$sroNo?></div>
</div>
<div class="col-md-6">
<label class="col-md-3 control-label" style="padding-left:0;padding-right:0" for="inputDefault">HS Code</label>
<div class="col-md-7"><?=$hsCode?></div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Description</label>
<div class="col-md-9"><?=$description?></div>
</div>

<div class="form-group">
<label class="col-md-3 control-label" for="inputDefault"><b>Unit Value</b></label>
<div class="col-md-4">
<label class="col-md-4 control-label" for="inputDefault">Declared<span>*</span></label>
<div class="col-md-8"> <?=$unitDeclared?></div>
</div>
<div class="col-md-4">
<label class="col-md-4 control-label" for="inputDefault">Assessed<span>*</span></label>
<div class="col-md-8"> <?=$unitAssessed?></div>
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDefault"><b>Total Value</b></label>
<div class="col-md-4">
<label class="col-md-4 control-label" for="inputDefault">Declared<span>*</span></label>
<div class="col-md-8"> <?=$totalDeclared?></div>
</div>
<div class="col-md-4">
<label class="col-md-4 control-label" for="inputDefault">Assessed<span>*</span></label>
<div class="col-md-8"> <?=$totalAssessed?></div>
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDefault"><b>Custom Value (PKR)</b></label>
<div class="col-md-4">
<label class="col-md-4 control-label" for="inputDefault">Declared<span>*</span></label>
<div class="col-md-8"> <?=$customDeclared?></div>
</div>
<div class="col-md-4">
<label class="col-md-4 control-label" for="inputDefault">Assessed<span>*</span></label>
<div class="col-md-8"> <?=$customAssessed?></div>
</div>
</div>
<?php
$sqlTax = mysqli_query($conn,"SELECT * FROM pos_custom_charges WHERE pos_categoryID='".$rowsEdit['posCategoryID']."' ORDER BY id ASC");
$numRowsTax = mysqli_num_rows($sqlTax);
if($numRowsTax>0){
echo '<div class="form-group" >
<label class="col-md-2 control-label" for="inputDefault"><b>Custom Charges</b></label>
<div class="col-md-9" id="tdCustomCharges">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="35%">Title</th>
<th width="30%">Amt</th>
<th width="30%">Value</th>
</tr>
</thead>
<tbody >';
$sno=0;
while($rowTx=mysqli_fetch_assoc($sqlTax)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.$rowTx['title'].'</td>
<td><input type="number" step="any" class="form-control" name="customValuePKR[]" value="'.productCustomEditVal($rowTx['id'],$rowsEdit['id']).'" id="inputDefault" >
<input type="hidden" name="customValueID[]" value="'.$rowTx['id'].'" ></td>
<td>--</td>
</tr>';
}
echo "</tbody>
</table>
</div>
</div>";
}
?>
<?php
$sqlSP = mysqli_query($conn,"SELECT psop.*,psb.name,psb.address,psb.telephone,psb.invoiceNo FROM pos_sale_order_products as psop,pos_sale_billing as psb WHERE psb.id=psop.posSaleBillingID AND psop.productID='".$rowsEdit['id']."' ORDER BY psop.id ASC");
$numRowsSP = mysqli_num_rows($sqlSP);
if($numRowsSP>0){
echo '<div class="form-group" >
<label class="col-md-2 control-label" for="inputDefault"><b>Buyers</b></label>
<div class="col-md-9" id="tdCustomCharges">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th align="left" width="5%">&nbsp; SNo</th>
<th align="left" width="15%">Party</th>
<th class="text-center" width="15%">Unit Price</th>
<th class="text-center" width="20%">Value of Goods</th>
<th class="text-center" width="10%">Tax (%)</th>
<th class="text-center" width="15%">Sale Tax</th>
<th class="text-center" width="20%">Total</th>
</tr>
</thead>
<tbody >';
$sno=0;
while($rowSP=mysqli_fetch_assoc($sqlSP)){
$sno++;
$saleTAmt = $rowSP['valueOfGood'];
$saleTTx = priceFormat(($rowSP['prodTax']/100) * $saleTAmt);
$saleTAmt2=$saleTAmt+$saleTTx;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.$rowSP['name'].'<br /><b>Invoice No</b> '.$rowSP['invoiceNo'].'</td>
<td align="right">Rs.'.priceFormatRound($rowSP['productPrice']).' &nbsp;</td>
<td align="right">Rs.'.priceFormatRound($rowSP['valueOfGood']).' &nbsp;</td>
<td align="right">'.$rowSP['prodTax'].'% &nbsp;</td>
<td align="right">Rs.'.priceFormatRound($saleTTx).' &nbsp;</td>
<td align="right">Rs.'.priceFormatRound($saleTAmt2).' &nbsp;</td>
</tr>';
}
echo "</tbody>
</table>
</div>
</div>";
}
?>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<button type="button" onclick="window.location.href='<?=$siteUrl;?>posProducts.php'" class="btn btn-default">Back</button>
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
<script type="text/javascript">
$(document).ready(function(){ 
$("#posCategoryID").change(function(){ $.ajax({ url:"<?=$siteUrl;?>posCustomAjax.php?act=cc&id=" + $(this).val(), type: "GET", success: function(data){ var x = data; $("#tdCustomCharges").html(x);  } });}); 
}); 
</script>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Products</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>posProducts.php">Manage Products</a></span></li>
<li><span><?=$hdTxt;?> Product</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>posProducts.php"><b>Back</b></a> </div>
<h2 class="panel-title"><?=$hdTxt;?> Products</h2>
</header>
<div class="panel-body">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Good Declaration<span>*</span></label>
<div class="col-md-9">
<select class="form-control" name="posCategoryID" id="posCategoryID" not_required>
<option value="">- Select -</option>
<?php
$sqlCAT = mysqli_query($conn,"SELECT id,igmNo FROM pos_category ORDER BY id ASC");
while($rowCAT = mysqli_fetch_assoc($sqlCAT)){						
echo '<option value="'.$rowCAT['id'].'" '.((($rowCAT['id']==$posCategoryID))?'selected="selected"':'').' >'.$rowCAT['igmNo'].'</option>';
}
?>
</select>
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Item No<span>*</span></label>
<div class="col-md-8">
<input type="text" class="form-control" name="itemNo" value="<?=$itemNo?>" id="inputDefault" not_required >
</div>
</div>
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Purchase Type<span>*</span></label>
<div class="col-md-8">
<select class="form-control" name="purchaseType" id="purchaseType" not_required>
<option value="">- Select -</option>
<option value="Local" <?=($purchaseType=="Local")?'selected="selected"':''?> >Local</option>
<option value="Import" <?=($purchaseType=="Import")?'selected="selected"':''?> >Import</option>
</select>
</div>
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Quantity<span>*</span></label>
<div class="col-md-8">
<input type="number" step="any" class="form-control" name="prodQuantity" value="<?=$prodQuantity?>" id="inputDefault" not_required >
</div>
</div>
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Units<span>*</span></label>
<div class="col-md-8">
<select class="form-control" name="posUnitTypeID" id="posUnitTypeID" not_required>
<option value="">- Select -</option>
<?php
$sqlUntTyp = mysqli_query($conn,"SELECT id,title FROM pos_unit_types ORDER BY id ASC");
while($rowUT = mysqli_fetch_assoc($sqlUntTyp)){						
echo '<option value="'.$rowUT['id'].'" '.((($rowUT['id']==$posUnitTypeID))?'selected="selected"':'').' >'.$rowUT['title'].'</option>';
}
?>
</select>
</div>
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Product Value<span>*</span></label>
<div class="col-md-8">
<input type="number" step="any" class="form-control" name="prodValue" value="<?=$prodValue?>" id="inputDefault" not_required>
<span class="hintTxt"><i>i.e. TOTAL COST OF UNIT</i></span>
</div>
</div>
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Sale Tax<span>*</span></label>
<div class="col-md-8">
<input type="number" step="any" class="form-control" name="prodSaleTax" value="<?=$prodSaleTax?>" id="inputDefault" not_required>
<span class="hintTxt"><i>i.e. TOTAL SALES TAX </i></span>
</div>
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">SRO No</label>
<div class="col-md-8">
<input type="text" class="form-control" name="sroNo" value="<?=$sroNo?>" id="inputDefault" >
</div>
</div>
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">HS Code</label>
<div class="col-md-8">
<input type="text" class="form-control" name="hsCode" value="<?=$hsCode?>" id="inputDefault" >
</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Description</label>
<div class="col-md-9">
<input type="text" class="form-control" name="description" value="<?=$description?>" id="inputDefault" >
</div>
</div>

<div class="form-group">
<label class="col-md-3 control-label" for="inputDefault"><b>Unit Value</b></label>
<div class="col-md-4">
<label class="col-md-4 control-label" for="inputDefault">Declared<span>*</span></label>
<div class="col-md-8"> <input type="number" step="any" class="form-control" name="unitDeclared" value="<?=$unitDeclared?>" id="inputDefault" not_required></div>
</div>
<div class="col-md-4">
<label class="col-md-4 control-label" for="inputDefault">Assessed<span>*</span></label>
<div class="col-md-8"> <input type="number" step="any" class="form-control" name="unitAssessed" value="<?=$unitAssessed?>" id="inputDefault" not_required></div>
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDefault"><b>Total Value</b></label>
<div class="col-md-4">
<label class="col-md-4 control-label" for="inputDefault">Declared<span>*</span></label>
<div class="col-md-8"> <input type="number" step="any" class="form-control" name="totalDeclared" value="<?=$totalDeclared?>" id="inputDefault" not_required></div>
</div>
<div class="col-md-4">
<label class="col-md-4 control-label" for="inputDefault">Assessed<span>*</span></label>
<div class="col-md-8"> <input type="number" step="any" class="form-control" name="totalAssessed" value="<?=$totalAssessed?>" id="inputDefault" not_required></div>
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDefault"><b>Custom Value (PKR)</b></label>
<div class="col-md-4">
<label class="col-md-4 control-label" for="inputDefault">Declared<span>*</span></label>
<div class="col-md-8"> <input type="number" step="any" class="form-control" name="customDeclared" value="<?=$customDeclared?>" id="inputDefault" not_required></div>
</div>
<div class="col-md-4">
<label class="col-md-4 control-label" for="inputDefault">Assessed<span>*</span></label>
<div class="col-md-8"> <input type="number" step="any" class="form-control" name="customAssessed" value="<?=$customAssessed?>" id="inputDefault" not_required></div>
</div>
</div>

<?php
//if($action=="update"){
?>
<div class="form-group" >
<label class="col-md-2 control-label" for="inputDefault"><b>Custom Charges</b></label>
<div class="col-md-9" id="tdCustomCharges">
<?php
$sqlTax = mysqli_query($conn,"SELECT * FROM pos_custom_charges WHERE pos_categoryID='".$rowsEdit['posCategoryID']."' ORDER BY id ASC");
$numRowsTax = mysqli_num_rows($sqlTax);
if($numRowsTax>0){
echo '<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="35%">Title</th>
<th width="30%">Amt</th>
<th width="30%">Value</th>
</tr>
</thead>
<tbody >';
$sno=0;
while($rowTx=mysqli_fetch_assoc($sqlTax)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.$rowTx['title'].'</td>
<td><input type="number" step="any" class="form-control" name="customValuePKR[]" value="'.productCustomEditVal($rowTx['id'],$rowsEdit['id']).'" id="inputDefault" >
<input type="hidden" name="customValueID[]" value="'.$rowTx['id'].'" ></td>
<td>--</td>
</tr>';
}
echo "</tbody>
</table>";
}
?>
</div>
</div>
<?php // } ?>
<div class="form-group">
<label class="col-md-5 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<input type="hidden" name="action" value="<?=$action;?>" />
<input type="hidden" name="id" value="<?=$id;?>" />
<button class="btn btn-primary" name="submit" value="Submit">Submit</button>
<button type="button" onclick="window.location.href='<?=$siteUrl;?>posProducts.php'" class="btn btn-default">Cancel</button>
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
<h2>Manage Products</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span>Manage Products</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act'])){
if($_GET['act']=="1"){
$msgTxt='New Product has been added successfully';
}else if($_GET['act']=="2"){
$msgTxt='Product has been updated successfully';
}else if($_GET['act']=="3"){
$msgTxt='Product has been deleted successfully';
}	
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.$msgTxt.'</strong></div>';
}
?>
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>posProducts.php?action=add"><b>Add New</b></a> </div>
<h2 class="panel-title">Manage Products</h2>
</header>
<div class="panel-body">
<div class="form-group">
<div class="col-md-6">
<form class="form-horizontal form-bordered" method="get">
<input type="text" style="width:80%;float:left" class="form-control" name="q" value="<?=htmlspecialchars((string)($_GET['q'] ?? ''), ENT_QUOTES, 'UTF-8')?>" placeholder="Search product or item number" id="inputDefault"  >
<button class="btn btn-primary" name="submit" value="Submit">GO</button>
</form> <span class="hintTxt">Search by product description or item number.</span>
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
<th width="45%">Title</th>
<th width="15%">Opening Qty</th>
<th width="15%" class="text-center">Remaining Qty</th>
<th width="20%" class="text-center">Action</th>
</tr>
</thead>
<tbody>
<?php
$search = trim((string)($_GET['q'] ?? ''));
$safeSearch = mysqli_real_escape_string($conn, $search);
$page_name = "posProducts.php?q=" . rawurlencode($search);
$start = max(0, (int)($_GET["start"] ?? 0));

$eu = ($start - 0); 
$limit = 50;          
$this1 = $eu + $limit; 
$back = $eu - $limit; 
$next = $eu + $limit; 
$sqlSrch = "";
if ($search !== "") {
    $sqlSrch = " AND (p.itemNo LIKE '%" . $safeSearch . "%' OR p.description LIKE '%" . $safeSearch . "%') ";
}
$sqlSeller = "SELECT p.*,ut.title FROM pos_products as p,pos_unit_types as ut where ut.id=p.posUnitTypeID ".$sqlSrch." ORDER BY p.id DESC limit $eu, $limit";
$sqltot = "SELECT p.id FROM pos_products as p,pos_unit_types as ut where ut.id=p.posUnitTypeID ".$sqlSrch." ORDER BY p.id DESC";

$resultSeller= mysqli_query($conn,$sqlSeller);
$resulttot=mysqli_query($conn,$sqltot);
$nume=mysqli_num_rows($resulttot);
if (@mysqli_num_rows($resultSeller)!=0){

$sno=$start;
while($rows=mysqli_fetch_assoc($resultSeller)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.$rows['description'].' (H.S Code: '.$rows['hsCode'].')
'.(($rows['prodQuantity']<0)?'<br /><span class="redTxt">NEGATIVE QTY</span>':'').'</td>
<td>'.$rows['prodQtyOrg'].' '.$rows['title'].'</td>
<td>'.$rows['prodQuantity'].' '.$rows['title'].'</td>
<td align="center">
<a title="View this record" class="btn btn-default viewBtn" href="posProducts.php?action=view&id='.$rows['id'].'">View</a> 
<a title="Edit this record" class="btn btn-default editBtn" href="posProducts.php?action=edit&id='.$rows['id'].'">Edit</a>
<a title="Delete this record" class="btn btn-default delBtn" onclick="return confirm(\'Are you sure you want to delete?\');" href="posProducts.php?act=delete&id='.$rows['id'].'">Delete</a>
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
