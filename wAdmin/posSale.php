<?php
include "include/sessionAdmin.php";  
include "include/configure.php";
if((isset($_POST['submit']) && $_POST['submit']=="Submit") || (isset($_POST['submit']) && $_POST['submit']=="Draft")){
if(isset($_POST['action']) && $_POST['action']=="add"){
$prodNm = $_POST['productID'];
$prodQty = $_POST['qty'];
$prodTax = $_POST['tax'];
$prodRate = $_POST['rate'];
$prodValue = $_POST['prodValue'];   
if(count($prodNm)>0){
$sqlCst = mysqli_query($conn,"select * from pos_buyers WHERE id='".$_POST['customerID']."'");
$rowCst = mysqli_fetch_assoc($sqlCst);
$isShowGD = isset($_POST['isShowGD']) && (string)$_POST['isShowGD'] === '1' ? 1 : 0;
$isDraft = isset($_POST['submit']) && $_POST['submit'] === 'Draft' ? 1 : 0;
$initialGrandTotal = 0;
mysqli_query($conn,"INSERT INTO pos_sale_billing (saleNote,isShowGD,isDraft,grandTotal,invoiceNo,ntn,gst,termSale,invoiceRef,invoiceDate,posBuyerID,name,address,telephone,createdDate)  VALUES ('".mysqli_real_escape_string($conn,$_POST['saleNote'] ?? '')."',$isShowGD,$isDraft,'$initialGrandTotal','".mysqli_real_escape_string($conn,$_POST['invoiceNo'] ?? '')."','".mysqli_real_escape_string($conn,$rowCst['ntn'] ?? '')."','".mysqli_real_escape_string($conn,$rowCst['gst'] ?? '')."','".mysqli_real_escape_string($conn,$_POST['termSale'] ?? '')."','".mysqli_real_escape_string($conn,$_POST['invoiceRef'] ?? '')."','".mysqli_real_escape_string($conn,sqlDtFormat($_POST['invoiceDate'] ?? ''))."','".mysqli_real_escape_string($conn,$_POST['customerID'] ?? '')."','".mysqli_real_escape_string($conn,$rowCst['name'] ?? '')."','".mysqli_real_escape_string($conn,$rowCst['address'] ?? '')."','".mysqli_real_escape_string($conn,$rowCst['telephone'] ?? '')."','".currDateTimeGmt()."')");
$orderID = mysqli_insert_id($conn); 
$totalAmt = 0;
foreach($prodNm as $key=>$prodNm){ 
if($prodNm!=""){
$prodNmAry = @explode("-",$prodNm);
$prodNm = $prodNmAry[0];
$sqlPrd = mysqli_query($conn,"select description,hsCode,prodQuantity,posUnitTypeID from pos_products WHERE id='".$prodNm."'");
$rowPrd = mysqli_fetch_assoc($sqlPrd);
$prdNm = $rowPrd['description'].' (H.S Code: '.$rowPrd['hsCode'].')';
mysqli_query($conn,"INSERT INTO pos_sale_order_products (valueOfGood,prodUnit,prodTax,productName,productQty,productPrice,posSaleBillingID,productID) VALUES ('".mysqli_real_escape_string($conn,$prodValue[$key])."','".mysqli_real_escape_string($conn,posUnitType($rowPrd['posUnitTypeID']))."','".mysqli_real_escape_string($conn,$prodTax[$key])."','".mysqli_real_escape_string($conn,$prdNm)."','".mysqli_real_escape_string($conn,$prodQty[$key])."','".mysqli_real_escape_string($conn,$prodRate[$key])."','".mysqli_real_escape_string($conn,$orderID)."','".mysqli_real_escape_string($conn,$prodNm)."')");
$newQty = $rowPrd['prodQuantity']-$prodQty[$key];
mysqli_query($conn,"UPDATE pos_products SET prodQuantity='".mysqli_real_escape_string($conn,$newQty)."' WHERE id='".$prodNm."'");
$tAmt =  $prodValue[$key];//($prodQty[$key]*$prodRate[$key]); 
$taxAmt = priceFormat(($prodTax[$key]/100) * $tAmt);
$totalAmt+=($tAmt+$taxAmt);
}
}
$grndTotal = priceFormat($totalAmt);
mysqli_query($conn,"UPDATE pos_sale_billing SET grandTotal='".$grndTotal."' WHERE id='".$orderID."' ");

$sqlBln = mysqli_query($conn,"SELECT balAmt FROM pos_buyer_account where posBuyerID='".$_POST['customerID']."' ORDER BY id DESC");
$rowBln = mysqli_fetch_assoc($sqlBln);				

$debitAmt  = 0;
$creditAmt = $grndTotal;
$debitAmt  = 0;
$creditAmt = $grndTotal;
$balAmt    = $rowBln['balAmt']+$creditAmt;
mysqli_query($conn,"INSERT INTO pos_buyer_account (posSaleID,posBuyerID,invoiceNo,isDelete,deleteReason,paymentMethod,paymentDate,paymentStatus,bankDetails,paymentFile,creditAmt,debitAmt,balAmt,createdDate) VALUES 
('".mysqli_real_escape_string($conn,$orderID)."','".mysqli_real_escape_string($conn,$_POST['customerID'] ?? '')."','".mysqli_real_escape_string($conn,$_POST['invoiceNo'] ?? '')."','0','','".mysqli_real_escape_string($conn,$_POST['termSale'] ?? '')."','".mysqli_real_escape_string($conn,sqlDtFormat($_POST['invoiceDate'] ?? ''))."','".mysqli_real_escape_string($conn,'Paid')."','".mysqli_real_escape_string($conn,$_POST['bankDetails'] ?? '')."','".mysqli_real_escape_string($conn,$uploadfile ?? '')."','".mysqli_real_escape_string($conn,$creditAmt)."','".mysqli_real_escape_string($conn,$debitAmt)."','".mysqli_real_escape_string($conn,$balAmt)."','".currDateTimeGmt()."')");
header("location: posSale.php?act=1");
}
}
}	
if(isset($_GET['act']) && $_GET['act']=="delete"){
$sqlPOSBln = mysqli_query($conn,"SELECT grandTotal,id,posBuyerID,invoiceNo FROM pos_sale_billing where id='".$_GET['id']."'");
$rowPOSBln = mysqli_fetch_assoc($sqlPOSBln);		

mysqli_query($conn,"UPDATE pos_buyer_account SET invoiceNo='".$rowPOSBln['invoiceNo']."' WHERE posSaleID='".$rowPOSBln['id']."' ");

$sqlBln = mysqli_query($conn,"SELECT balAmt FROM pos_buyer_account where posBuyerID='".$rowPOSBln['posBuyerID']."' ORDER BY id DESC");
$rowBln = mysqli_fetch_assoc($sqlBln);				
$dtAry =explode(" ",currDateTimeGmt());

$debitAmt  = $rowPOSBln['grandTotal'];
$creditAmt = 0;
$balAmt = ($rowBln['balAmt']-$debitAmt);
$deleteReason = 'Reverse Entry - #'.$rowPOSBln['invoiceNo'];
mysqli_query($conn,"INSERT INTO pos_buyer_account (isDelete,deleteReason,posBuyerID,paymentDate,creditAmt,debitAmt,balAmt,createdDate) VALUES 
('1','".mysqli_real_escape_string($conn,$deleteReason)."','".mysqli_real_escape_string($conn,$rowPOSBln['posBuyerID'])."','".mysqli_real_escape_string($conn,$dtAry[0])."','".mysqli_real_escape_string($conn,$creditAmt)."','".mysqli_real_escape_string($conn,$debitAmt)."','".mysqli_real_escape_string($conn,$balAmt)."','".currDateTimeGmt()."')");

$sqlPrd = mysqli_query($conn,"SELECT * FROM pos_sale_order_products WHERE posSaleBillingID='".$_GET['id']."'");
while($rowPrd = mysqli_fetch_assoc($sqlPrd)){
$sqlPrdBk = mysqli_query($conn,"select prodQuantity from pos_products WHERE id='".$rowPrd['productID']."'");
$rowPrdBk = mysqli_fetch_assoc($sqlPrdBk);
$newQty = $rowPrdBk['prodQuantity']+$rowPrd['productQty'];
mysqli_query($conn,"UPDATE pos_products SET prodQuantity='".mysqli_real_escape_string($conn,$newQty)."' WHERE id='".$rowPrd['productID']."'");
}	
mysqli_query($conn,"DELETE FROM pos_sale_billing WHERE id='".$_GET['id']."'");
mysqli_query($conn,"DELETE FROM pos_sale_order_products WHERE posSaleBillingID='".$_GET['id']."'");
header("location: posSale.php?act=3");
}
if(isset($_GET['actn']) && $_GET['actn']=="dwn"){
$sqlSrch = "";
$csv_Excel = '"Invoice No","Date","Name of buyer","NTN of Buyer","H.S.Code","Total Qty","Value of Good","Sale Tax","Total"' . "\r\n";
$qExport = isset($_GET['q']) ? $_GET['q'] : '';
$tExport = isset($_GET['t']) ? $_GET['t'] : '';

if($qExport != '' || $tExport != ''){
if($tExport=='r'){
$rAry = @explode("-",$qExport);
if(count($rAry) > 1){
$sqlSrch = " WHERE (invoiceNo BETWEEN ".$rAry[0]." AND ".$rAry[1].")";
}
}else if($tExport=='k'){
$sqlSrch = " WHERE (invoiceNo LIKE '%".$qExport."%' OR name LIKE '%".$qExport."%' OR ntn LIKE '%".$qExport."%' OR telephone LIKE '%".$qExport."%'  OR address LIKE '%".$qExport."%') ";
}else if($tExport != ''){
$dtAry = @explode("-",$tExport);
if(count($dtAry) > 1){
$sqlSrch = " WHERE month(invoiceDate)='".$dtAry[1]."' AND YEAR(invoiceDate)='".$dtAry[0]."'  AND (invoiceNo LIKE '%".$qExport."%' OR name LIKE '%".$qExport."%' OR ntn LIKE '%".$qExport."%' OR telephone LIKE '%".$qExport."%'  OR address LIKE '%".$qExport."%')";
}
}
}

$sqlUser = mysqli_query($conn,"SELECT * FROM pos_sale_billing ".$sqlSrch." ORDER BY invoiceNo ASC");
while($rowsUser = mysqli_fetch_assoc($sqlUser)){

$saleTTx = $saleTAmt  = $saleTAmt2 = $saleQty = 0;
$hsCode = '';
$prodUnit = '';
$sqlPrdSale = mysqli_query($conn,"SELECT * FROM pos_sale_order_products WHERE posSaleBillingID='".$rowsUser['id']."'");
$numRowPS = mysqli_num_rows($sqlPrdSale);
if($numRowPS>0){
while($rowsPS = mysqli_fetch_assoc($sqlPrdSale)){
$saleTAmt = $rowsPS['valueOfGood'];
$saleTTx+= priceFormat(($rowsPS['prodTax']/100) * $saleTAmt);
$saleTAmt2+=$saleTAmt;
$hsCode = posHSCode($rowsPS['productID']).' ('.$rowsPS['productQty'].' '.$rowsPS['prodUnit'].')';
$prodUnit = $rowsPS['prodUnit'];
$saleQty+=$rowsPS['productQty'];
}
}
$saleQtyNEW = $saleQty.' '.$prodUnit;
$csv_Excel.='"'.$rowsUser['invoiceNo'].'","'.viewDateFormat($rowsUser['invoiceDate']).'","'.$rowsUser['name'].'","'.$rowsUser['ntn'].'","'.$hsCode.'","'.$saleQtyNEW.'","'.priceFormatRound($saleTAmt2).'","'.priceFormatRound($saleTTx).'","'.priceFormatRound($saleTAmt2+$saleTTx).'"'."\r\n";
}
$myFile = "sales.csv";
$fh = fopen($myFile,'w') or die("can't open file");
fwrite($fh,$csv_Excel);
fclose($fh);
downloadFile($myFile,'sales.csv');
} 
$action = '';
$hdTxt = '';
$id = '';
$saleNote = '';
$isShowGD = '0';
$q = isset($_GET['q']) ? $_GET['q'] : '';
$t = isset($_GET['t']) ? $_GET['t'] : '';
$st = isset($_GET['st']) ? $_GET['st'] : '';
$d = isset($_GET['d']) ? $_GET['d'] : '';
include "include/header.php";
if(isset($_GET['action']) && $_GET['action']=="view"){
$sqlView = mysqli_query($conn,"SELECT * FROM pos_sale_billing WHERE id='".$_GET['id']."'");
$rowsView = mysqli_fetch_assoc($sqlView);
?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Sales</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>posSale.php?st=<?=$st?>">Manage Sales</a></span></li>
<li><span>View Sales</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act'])){
if($_GET['act']=="1"){
$msgTxt='Sale status has been updated successfully';
} 	
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.$msgTxt.'</strong></div>';
}
//<a target="_blank" href="<?=$siteUrlMain>order-receipt?id=<?=$rowsView['id'];"><b>Print</b></a> |
?>
<div class="row">
<div class="col-lg-12">
<section class="panel">

<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>posSale.php?st=<?=$st?>"><b>Back</b></a> </div>
<h2 class="panel-title">View Sale Details</h2>
</header>
<div class="panel-body viewInfo">

<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Invoice</label>
<div class="col-md-8">#<?=$rowsView['invoiceNo'];?></div>
</div>
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Invoice Date</label>
<div class="col-md-7"><?=viewDateFormat($rowsView['invoiceDate']);?></div>
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Term of Sale</label>
<div class="col-md-8"><?=$rowsView['termSale'];?></div>
</div>
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Ref</label>
<div class="col-md-7"><?=$rowsView['invoiceRef'];?></div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">&nbsp; &nbsp; Customer</label>
<div class="col-md-8"><?=$rowsView['name'].'<br />'.$rowsView['address'].", ".$rowsView['telephone'];?>
<?=($rowsView['isShowGD']?'<p><input type="checkbox"  name="isShowGD" value="1" checked="checked" /> Show GD Number In PDF Invoice</p>':'');?>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputReadOnly">Sale Product</label>
<div class="col-md-10">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="10%">Qty</th>
<th width="10%">Description of Goods</th>
<th width="15%" class="text-right">Unit Price</th>
<th width="15%" class="text-right">Value of Goods</th>
<th width="15%" class="text-right">Rate of<br/> Sales Tax</th>
<th width="15%" class="text-right">Amount of <br/> Sale Tax</th>
<th width="15%" class="text-right">Total</th>
</tr>
</thead>
<tbody>
<?php
$sqlTask = mysqli_query($conn,"SELECT * FROM pos_sale_order_products WHERE posSaleBillingID='".$rowsView['id']."'");
$numRowsTask = mysqli_num_rows($sqlTask);
if($numRowsTask>0){
$sno=0; $totalValOfGood = $totalAmountSaleTax = 0;
while($rows=mysqli_fetch_assoc($sqlTask)){
$sno++;
$tAmt =  $rows['valueOfGood'];//($rows['productQty']*$rows['productPrice']);
$taxAmt = priceFormat((priceFormatRound($rows['prodTax'])/100) * $tAmt);
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.$rows['productQty'].'</td>
<td><a href="posProducts.php?action=view&id='.$rows['productID'].'">'.$rows['productName'].'</a></td>
<td align="right">'.$rows['productPrice'].'&nbsp;<hr />'.productGD($rows['productID']).' </td>
<td align="right">'.$tAmt.'&nbsp; </td>
<td align="right">'.priceFormatRound($rows['prodTax']).'%&nbsp; </td>
<td align="right">'.priceFormatRound($taxAmt).'&nbsp; </td>
<td align="right">'.priceFormatRound($taxAmt+$tAmt).'&nbsp; </td>
</tr>';
$totalValOfGood+=$tAmt;
$totalAmountSaleTax+=($taxAmt);
}
echo '<tr>
<td colspan="4" align="right">&nbsp; <b>Total</b></td>
<td align="right"><b>'.priceFormatRound($totalValOfGood).'&nbsp;</b> </td>
<td align="right">&nbsp; </td>
<td align="right"><b>'.priceFormatRound($totalAmountSaleTax).'&nbsp;</b> </td>
<td align="right">&nbsp;</td>
</tr>';

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
<label class="col-md-2 control-label" for="inputHelpText">Note</label>
<div class="col-md-8"><?=$rowsView['saleNote']?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputHelpText">Sale Date</label>
<div class="col-md-8"><?=viewDtTm($rowsView['createdDate'])?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-8">
<button type="button" onclick="window.location.href='<?=$siteUrl;?>posSale.php?st=<?=$st?>'" class="btn btn-default">Back</button>
</div>
</div>
</div>
</section>
</div>
</div>
</section>
</div>
</section>
<?php 

}else if(isset($_GET['action']) && $_GET['action']=="add"){
$action = 'add';
$hdTxt = 'Add';
?>
<style type="text/css">
.prodFieldH{width:24%;} .prodQtyH{width:13%;} .prodRateH{width:10%;} .prodTaxH{width:13%;} .prodValueH{width:10%;} .prodSaleTaxH{width:13%;} .prodGrandTotalH{width:13%;} .prodActnH{width:4px;}
.prodFieldT{width:24%;} .prodQtyT{width:13%;} .prodRateT{width:10%;} .prodTaxT{width:13%;} .prodValueT{width:10%;} .prodSaleTaxT{width:13%;} .prodGrandTotalT{width:17%;}
#table_exend tr td{padding:0 10px 10px 0;}
.table_exend2 tr td{padding:0 10px 10px 0;}
#prodQtyTotal, #prodValueTotal, #prodSaleTaxTotal , #prodGrandTotalAll{font-weight:bold;}
</style>
<script src="<?=$siteUrl?>assets/javascripts/tinymce/tinymce.min.js"></script>
<script src="<?=$siteUrl?>assets/javascripts/tinymce/tinymceStp.js"></script>
<script type='text/javascript' src='<?=$siteUrl;?>assets/javascripts/jquery-ui-1.8.2.custom.min.js'></script> 
<?php
$prdOptn="";
$prodSno=0;
$sqlPrd = mysqli_query($conn,"SELECT pc.igmDate,p.id,p.description,p.hsCode,p.prodQuantity,p.prodValue,ut.title,p.prodSaleTax FROM pos_category as pc,pos_products as p,pos_unit_types as ut WHERE pc.id=p.posCategoryID AND p.prodQuantity<>0 AND p.posUnitTypeID=ut.id ORDER BY p.itemNo ASC");
while($rowsPrd = mysqli_fetch_assoc($sqlPrd)){						
$prodSno++;
$prdOptn.="<option value='".$rowsPrd['id']."-".$rowsPrd['prodValue']."-".$rowsPrd['prodQuantity']."-".$rowsPrd['prodSaleTax']."' rel='".$rowsPrd['id']."' >".$prodSno.". ".$rowsPrd['description']." - ".$rowsPrd['prodQuantity']."".$rowsPrd['title']." - ".$rowsPrd['igmDate']." - ".$rowsPrd['hsCode']."</option>";
}
?>
<script type="text/javascript">
$(function(){
$('#invoiceDate').datepicker({ daysOfWeekDisabled: [0] });
});
function createGridDocs(tid){
var objTable = document.getElementById(tid); var tablerows=parseInt(objTable.rows.length); var objTableRow = objTable.insertRow(tablerows); var tabrow = tablerows; var i = tablerows; objTableRow.id = "row_c_"+i; var objTableRowCell0 = objTableRow.insertCell(0); var objTableRowCell1 = objTableRow.insertCell(1);  var objTableRowCell2 = objTableRow.insertCell(2); var objTableRowCell3 = objTableRow.insertCell(3);  var objTableRowCell4 = objTableRow.insertCell(4); var objTableRowCell5 = objTableRow.insertCell(5); var objTableRowCell6 = objTableRow.insertCell(6); var objTableRowCell7 = objTableRow.insertCell(7);   objTableRowCell0.innerHTML="<select class='form-control prodField' onChange='prodID("+i+")' id='productID"+i+"' name='productID[]' id='class_"+i+"' required><option value=''>- Select -</option><?=$prdOptn?></select>";   objTableRowCell1.innerHTML="<input type='number' onChange='prodRate("+i+")' id='prodRate"+i+"' placeholder='Rate' rel='"+i+"' name='rate[]' min='0' step='any' class='form-control prodRate' required >";  objTableRowCell2.innerHTML="<input type='number' onChange='prodQty("+i+")' placeholder='Qty' name='qty[]' id='prodQty"+i+"' min='0' step='any' class='form-control prodQty' rel='"+i+"' required >";
objTableRowCell3.innerHTML="<input type='number' min='0' step='any' value='' onChange='prodValue("+i+")' name='prodValue[]' id='prodValue"+i+"' class='form-control prodValue' rel='"+i+"' >";  objTableRowCell4.innerHTML="<input type='number' onChange='prodTax("+i+")' rel='"+i+"' placeholder='Tax' name='tax[]' min='0' step='any' id='prodTax"+i+"' class='form-control prodTax' required >";  objTableRowCell5.innerHTML="<input type='text' value='' id='prodSaleTax"+i+"' class='form-control prodSaleTax' rel='"+i+"' readonly='readonly' >";  objTableRowCell6.innerHTML="<input type='text' value='' id='prodGrandTotal"+i+"' class='form-control prodGrandTotal' rel='"+i+"' readonly='readonly' >";  objTableRowCell7.innerHTML="<a href='#class_"+i+"' onclick='removeRow1(this)' title='Delete'><i class='fa fa-times delIcon'></i></a>"; 
}
function removeRow1(src){ var i=src.parentNode.parentNode.rowIndex; var objTable = document.getElementById('table_exend'); var objTableRows = parseInt(objTable.rows.length); var objLastRow; document.getElementById("table_exend").deleteRow(i); }
function prodID(id){
var prodSaleTxAry = $("#productID"+id).val();var pSaleTx =  prodSaleTxAry.split("-");
var pRate = $("#prodRate"+id).val(pSaleTx[1]); 
var pQty = $("#prodQty"+id).val(pSaleTx[2]); var pTax = $("#prodTax"+id).val(pSaleTx[3]); var ttAmt = (pSaleTx[1]*pSaleTx[2]);
var taxAmt = parseFloat((pSaleTx[3]/100) * ttAmt); var grndAmt = parseFloat(taxAmt) + parseFloat(ttAmt);
$("#prodValue"+id).val(ttAmt); $("#prodSaleTax"+id).val(taxAmt.toFixed(2)); $("#prodGrandTotal"+id).val(grndAmt.toFixed(2));
calculateSum();
return false;
}
function calculateSum(){   
var pqSum = 0; var pvSum = 0; var pstSum = 0; var pgtSum = 0; 
$(".prodQty").each(function(){  if(!isNaN(this.value) && this.value.length != 0){ pqSum += parseFloat(this.value); }  }); $("#prodQtyTotal").html(pqSum.toFixed(2));
$(".prodValue").each(function(){  if(!isNaN(this.value) && this.value.length != 0){ pvSum += parseFloat(this.value); }  }); $("#prodValueTotal").html(pvSum.toFixed(2));
$(".prodSaleTax").each(function(){  if(!isNaN(this.value) && this.value.length != 0){ pstSum += parseFloat(this.value); }  }); $("#prodSaleTaxTotal").html(pstSum.toFixed(2));
$(".prodGrandTotal").each(function(){  if(!isNaN(this.value) && this.value.length != 0){ pgtSum += parseFloat(this.value); }  }); $("#prodGrandTotalAll").html(pgtSum.toFixed(2));
}
function prodQty(id){
var prodSaleTxAry = $("#productID"+id).val();var pSaleTx =  prodSaleTxAry.split("-");
var pRate = $("#prodRate"+id).val(); var pQty = $("#prodQty"+id).val(); var pTax = $("#prodTax"+id).val(); var ttAmt = (pRate*pQty);
var taxAmt = parseFloat((pTax/100) * ttAmt); var grndAmt = parseFloat(taxAmt) + parseFloat(ttAmt);
$("#prodValue"+id).val(ttAmt); $("#prodSaleTax"+id).val(taxAmt.toFixed(2)); $("#prodGrandTotal"+id).val(grndAmt.toFixed(2));
calculateSum();
return false;
}
function prodValue(id){
var prodSaleTxAry = $("#productID"+id).val();var pSaleTx =  prodSaleTxAry.split("-");
var pRate = $("#prodRate"+id).val(); var pQty = $("#prodQty"+id).val(); var pTax = $("#prodTax"+id).val(); 
var ttAmt = (pRate*pQty);
var ttAmt = $("#prodValue"+id).val();
var taxAmt = parseFloat((pTax/100) * ttAmt); var grndAmt = parseFloat(taxAmt) + parseFloat(ttAmt);
$("#prodValue"+id).val(ttAmt); $("#prodSaleTax"+id).val(taxAmt.toFixed(2)); $("#prodGrandTotal"+id).val(grndAmt.toFixed(2));
calculateSum();
return false;
}
function prodRate(id){
var prodSaleTxAry = $("#productID"+id).val();var pSaleTx =  prodSaleTxAry.split("-");
var pRate = $("#prodRate"+id).val(); var pQty = $("#prodQty"+id).val(); var pTax = $("#prodTax"+id).val(); var ttAmt = (pRate*pQty);
var taxAmt = parseFloat((pTax/100) * ttAmt); var grndAmt = parseFloat(taxAmt) + parseFloat(ttAmt);
$("#prodValue"+id).val(ttAmt); $("#prodSaleTax"+id).val(taxAmt.toFixed(2)); $("#prodGrandTotal"+id).val(grndAmt.toFixed(2));
calculateSum();
return false;
}
function prodTax(id){
var prodSaleTxAry = $("#productID"+id).val();var pSaleTx =  prodSaleTxAry.split("-");
var pRate = $("#prodRate"+id).val(); var pQty = $("#prodQty"+id).val(); var pTax = $("#prodTax"+id).val(); var ttAmt = (pRate*pQty);
var taxAmt = parseFloat((pTax/100) * ttAmt); var grndAmt = parseFloat(taxAmt) + parseFloat(ttAmt);
$("#prodValue"+id).val(ttAmt); $("#prodSaleTax"+id).val(taxAmt.toFixed(2)); $("#prodGrandTotal"+id).val(grndAmt.toFixed(2));
calculateSum();
return false;
}
$(document).ready(function(){ $(".invoiceDate").datepicker({todayHighlight:true,autoclose:true}); });
</script>
<?php
$currentDayAry = explode(" ",currDateTimeGmt());
$invDate = date("d-m-Y", strtotime("+1 day", strtotime(getLastInvoiceDate())));
?>
<link rel="stylesheet" href="<?=$siteUrl?>assets/bootstrap-select.css">
<script src="<?=$siteUrl?>assets/bootstrap-select.js"></script>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Billing</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>posSale.php">Manage Billing</a></span></li>
<li><span><?=$hdTxt;?> Billing</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>posSale.php"><b>Back</b></a> </div>
<h2 class="panel-title"><?=$hdTxt;?> Billing</h2>
</header>
<div class="panel-body">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Invoice No</label>
<div class="col-md-8">
<input type="number" class="form-control" name="invoiceNo" value="<?=posInvoiceNo();?>" >
</div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" for="inputDefault">Date</label>
<div class="col-md-8">
<input id="invoiceDate" type="text" class="form-control invoiceDate" readonly="readonly" data-date-format="dd-mm-yyyy" placeholder="Invoice Date (dd-mm-yyyy) *" name="invoiceDate" value="<?=$invDate;?>" required >
</div>
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Term of Sale</label>
<div class="col-md-8">
<input type="text" class="form-control" name="termSale" value="" placeholder="i.e Credit">
</div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" for="inputDefault">Ref</label>
<div class="col-md-8">
<input type="text" class="form-control" name="invoiceRef" value="" placeholder="i.e 002" >
</div>
</div>
</div>
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
<label class="col-md-2 control-label" for="inputDefault">&nbsp;</label>
<div class="col-md-9">
<input type="checkbox"  name="isShowGD" value="1" <?=($isShowGD=='1')?'checked="checked"':''?> > Show GD Number In PDF Invoice
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputReadOnly"></label>
<div class="col-md-12">
<a name="apt">&nbsp;</a>
<a class="btn btn-primary" href="#lnk" title="Add Product Items" name="lnk" id="lnk" onClick="createGridDocs('table_exend');" >Add New Item</a><br />&nbsp;
<table id="table_exend" border="0" cellpadding="5" cellspacing="5" width="100%;">
<tr>
<th class="text-left prodFieldH">Description of Goods</th>
<th class="text-center prodRateH">Unit Price</th>
<th class="text-center prodQtyH">Qty</th>
<th class="text-center prodTaxH">Value of Goods</th>
<th class="text-center prodValueH">Rate of<br/> Sales Tax</th>
<th class="text-center prodSaleTaxH">Amount of <br/> Sale Tax</th>
<th class="text-center prodGrandTotalH">Total</th>
<th class="text-center prodActnH">#</th>
</tr>
</table>
<table class='table_exend2' border="0" cellpadding="5" cellspacing="5" id="totalAmt" width="100%;">
<tr>
<td class="text-left prodFieldT">&nbsp;</td>
<td class="text-center prodRateT">&nbsp;</td>
<td class="text-right prodQtyT"><span id="prodQtyTotal"></span></td>
<td class="text-right prodTaxT"><span id="prodValueTotal"></span></td>
<td class="text-center prodValueT">&nbsp;</td>
<td class="text-right prodSaleTaxT">&nbsp; <span id="prodSaleTaxTotal"></span></td>
<td class="text-center prodGrandTotalT" colspan="2">&nbsp; <span id="prodGrandTotalAll"></span></td>
</tr>
</table>	
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Note</label>
<div class="col-md-9"><textarea class="form-control" cols="10" rows="2" name="saleNote" required="required"><?=$saleNote;?></textarea></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<input type="hidden" name="action" value="<?=$action;?>" />
<input type="hidden" name="id" value="<?=$id;?>" />
<button class="btn btn-primary" name="submit" value="Submit">Submit</button>
<button type="button" onclick="window.location.href='<?=$siteUrl;?>posSale.php'" class="btn btn-default">Cancel</button>
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
}else{
?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Sales</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span>Manage Sales</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act'])){
if($_GET['act']=="1"){
$msgTxt='New Sale bill has been generated successfully.';
}else if($_GET['act']=="3"){
$msgTxt='Sale bill has been deleted successfully.';
}
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.$msgTxt.'</strong></div>';
}
?>
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"><a href="<?=$siteUrl;?>posSale.php?actn=dwn&t=<?=$t?>&q=<?=$q?>"><b>Download Excel</b></a> | <a href="<?=$siteUrl;?>posSale.php?action=add"><b>Add New</b></a></div>
<h2 class="panel-title">Manage Sales Billing</h2>
</header>
<div class="panel-body">
<div class="form-group">
<div class="col-md-9">
<form class="form-horizontal form-bordered" method="get">
<select class="form-control" style="width:20%;float:left" name="t" id="t" >
<option value="">- Select -</option>
<option value="k" <?=($t=='k')?'selected="selected"':''?> >Keyword</option>
<option value="r" <?=($t=='r')?'selected="selected"':''?> >Range</option>

<?php
$sqlOrMtn = mysqli_query($conn,"SELECT MIN(invoiceDate) AS invoiceDate FROM pos_sale_billing GROUP BY YEAR(invoiceDate),MONTH(invoiceDate) ORDER BY YEAR(invoiceDate) DESC");
$numRowsOM = mysqli_num_rows($sqlOrMtn);
if($numRowsOM>0){
while($rowsOM = mysqli_fetch_assoc($sqlOrMtn)){
echo '<option value="'.$rowsOM['invoiceDate'].'" '.(($rowsOM['invoiceDate']==$t)?'selected="selected"':'').'>'.date('M Y', strtotime($rowsOM['invoiceDate'])).' </option>';
}
}
?>


</select>
<input type="text" style="width:60%;float:left" class="form-control" name="q" value="<?=(isset($_GET['q'])?$_GET['q']:'')?>" placeholder="Search Invoice" id="inputDefault"  >
<input type="hidden" name="st" value="<?=$st?>">
<input type="hidden" name="d" value="<?=$d?>">
<button class="btn btn-primary" name="submit" value="Submit">GO</button>
</form> <span class="hintTxt">Search :-  Name, Mobile Number, NTN, Email Address.</span>
</div>
<div class="col-md-3" align="right">
&nbsp;
</div>
</div>
<div class="table-responsive">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th align="left" width="5%">&nbsp; SNo</th>
<th align="left" width="13%">Party</th>
<th align="left" width="20%">Address</th>
<th class="text-center" width="10%">Value of Good</th>
<th class="text-center" width="12%">Sale Tax</th>
<th class="text-center" width="12%">Total Amt</th>
<th class="text-center" width="10%">Invoice Date</th>
<th class="text-center" width="18%">Action</th>
</tr>  
</thead>
<tbody>
<?php
$page_name="posSale.php?st=".$st."&t=".$t."&q=".$q."&d=".$d;
$start = isset($_REQUEST["start"]) ? (int)$_REQUEST["start"] : 0;

$eu = ($start - 0);
$limit = 50;          
$this1 = $eu + $limit; 
$back = $eu - $limit; 
$next = $eu + $limit;
$sqlSrch = "";
if($q != '' || $t != ''){
if($t=='r'){
$rAry = @explode("-",$q);
if(count($rAry) > 1 && $rAry[0] !== '' && $rAry[1] !== ''){
$sqlSrch = " AND (invoiceNo BETWEEN ".$rAry[0]." AND ".$rAry[1].")";
}
}else if($t=='k'){
$sqlSrch = " AND (invoiceNo LIKE '%".$q."%' OR name LIKE '%".$q."%' OR ntn LIKE '%".$q."%' OR telephone LIKE '%".$q."%'  OR address LIKE '%".$q."%') ";
}else if($t != ''){
$dtAry = @explode("-",$t);
if(count($dtAry) > 1 && $dtAry[0] !== '' && $dtAry[1] !== ''){
$sqlSrch = " AND month(invoiceDate)='".$dtAry[1]."' AND YEAR(invoiceDate)='".$dtAry[0]."'  AND (invoiceNo LIKE '%".$q."%' OR name LIKE '%".$q."%' OR ntn LIKE '%".$q."%' OR telephone LIKE '%".$q."%'  OR address LIKE '%".$q."%')";
}
}
}
$drf = 0;
if(isset($_GET['d'])){ $drf = $_GET['d']; }

$sqlSeller = "SELECT * FROM pos_sale_billing WHERE createdDate<>'' ".$sqlSrch." ORDER BY id DESC  limit $eu, $limit";
$sqltot = "SELECT id FROM pos_sale_billing WHERE createdDate<>'' ".$sqlSrch." ORDER BY id DESC";

$resultSeller= mysqli_query($conn,$sqlSeller);
$resulttot=mysqli_query($conn,$sqltot);
$nume=mysqli_num_rows($resulttot);
if (@mysqli_num_rows($resultSeller)!=0){

$ttSaleTxt = $ttAmtSale  = $ttSaleTAmt2 = 0;
$sno=0;
while($rows=mysqli_fetch_assoc($resultSeller)){
$sno++;

$saleTTx = $saleTAmt  = $saleTAmt2 = 0;
$sqlPrdSale = mysqli_query($conn,"SELECT * FROM pos_sale_order_products WHERE posSaleBillingID='".$rows['id']."'");
$numRowPS = mysqli_num_rows($sqlPrdSale);
if($numRowPS>0){
while($rowsPS = mysqli_fetch_assoc($sqlPrdSale)){
$saleTAmt = $rowsPS['valueOfGood'];
$saleTTx+= priceFormat(($rowsPS['prodTax']/100) * $saleTAmt);
$saleTAmt2+=$saleTAmt;
}
}
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.$rows['name'].'<br />
<b>Invoice No</b> '.$rows['invoiceNo'].'</td>
<td>'.$rows['address'].'<br />
<b>Tel:-</b> '.$rows['telephone'].'</td>
<td align="center">Rs.'.priceFormatRound($saleTAmt2).'</td>
<td align="center">Rs.'.priceFormatRound($saleTTx).'</td>
<td align="center">Rs.'.priceFormatRound($saleTAmt2+$saleTTx).'</td>
<td align="center">'.viewDtTm($rows['invoiceDate']).'</td>
<td align="center">
<a href="posSale.php?action=view&id='.$rows['id'].'&st='.$st.'" class="btn btn-default viewBtn" title="View this record">View</a>
<a title="Download PDF" class="btn btn-default editBtn" href="'.$siteUrlMain.'posInvoicePDF.php?act=pdfDwnld&id='.$rows['id'].'">Download</a>
<a title="Create or copy a public GUID invoice link" class="btn btn-default" href="posInvoiceLink.php?id='.$rows['id'].'">Share</a>
<a title="Delete this record" class="btn btn-default delBtn" onclick="return confirm(\'Are you sure you want to delete?\');" href="posSale.php?act=delete&id='.$rows['id'].'&bID='.$rows['posBuyerID'].'">Delete</a>
</td>
</tr>';
$ttSaleTAmt2+=$saleTAmt2;
$ttSaleTxt+=$saleTTx;
$ttAmtSale+=($saleTAmt2+$saleTTx);
}

//if(isset($_GET['q']) && $_GET['q']!=""){
echo '<tr>
<td colspan="3">&nbsp;</td>
<td align="center">'.priceFormatRound($ttSaleTAmt2).'</td>
<td align="center">'.priceFormatRound($ttSaleTxt).'</td>
<td align="center">'.priceFormatRound($ttAmtSale).'</td>
<td colspan="2">&nbsp;</td>
</tr>';
//}
?>
<tr>
<td colspan="8">
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
<td colspan='6' align='center'>No Data Found.</td>
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
include "include/footer.php";
?>
