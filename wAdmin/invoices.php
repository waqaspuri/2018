<?php
include "include/sessionAdmin.php";  
include "include/configure.php";
function parseMergedInvoiceLines($prodDescription)
{
	$lines = preg_split("/\r\n|\n|\r/", (string)$prodDescription);
	$items = [];
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === '' || strpos($line, '||') === false) {
			continue;
		}
		$parts = array_map('trim', explode('||', $line));
		if (count($parts) < 5) {
			continue;
		}
		$items[] = [
			'invoiceNo' => $parts[0],
			'description' => $parts[1],
			'qty' => $parts[2],
			'rate' => $parts[3],
			'amount' => $parts[4],
		];
	}
	return $items;
}

function buildMergedInvoiceContent($conn, $invoiceIDs)
{
	$invoiceIDs = array_values(array_unique(array_map('intval', (array)$invoiceIDs)));
	$invoiceIDs = array_filter($invoiceIDs, function ($id) {
		return $id > 0;
	});
	if (count($invoiceIDs) < 2) {
		return [false, 'Please select at least two invoices to merge.'];
	}

	$idList = implode(',', $invoiceIDs);
	$sqlMerge = mysqli_query($conn, "SELECT * FROM invoices WHERE id IN (" . $idList . ") ORDER BY id ASC");
	$rows = [];
	while ($row = mysqli_fetch_assoc($sqlMerge)) {
		$rows[] = $row;
	}
	if (count($rows) < 2) {
		return [false, 'Unable to load all selected invoices.'];
	}

	$base = $rows[0];
	$signatureFields = ['companyName', 'applicantName', 'address', 'city', 'country', 'mobileNumber'];
	$baseSignature = [];
	foreach ($signatureFields as $field) {
		$baseSignature[$field] = trim((string)$base[$field]);
	}
	foreach ($rows as $row) {
		foreach ($signatureFields as $field) {
			if (trim((string)$row[$field]) !== $baseSignature[$field]) {
				return [false, 'All merged invoices must belong to the same bill-to details.'];
			}
		}
	}

	$sourceInvoiceNos = [];
	$sourceProformas = [];
	$lineItems = [];
	$totalAmt = 0;
	$dueDates = [];

	foreach ($rows as $row) {
		$invoiceNo = trim((string)$row['invoiceNo']);
		if ($invoiceNo !== '') {
			$sourceInvoiceNos[] = $invoiceNo;
		}
		$proformaNo = trim((string)$row['proformaNo']);
		if ($proformaNo !== '') {
			$sourceProformas[] = $proformaNo;
		}
		$lineItems[] = [
			'proformaNo' => $proformaNo,
			'description' => trim((string)$row['prodDescription']),
			'qty' => trim((string)$row['prodQty']),
			'rate' => trim((string)$row['unitPrice']),
			'amount' => trim((string)$row['totalAmt']),
		];
		$totalAmt += (float)str_replace(',', '', (string)$row['totalAmt']);
		if (!empty($row['dueDate'])) {
			$dueDates[] = $row['dueDate'];
		}
	}

	$invoiceNo = substr(strtoupper(str_replace(array(" ", '&'), array("", ''), $base['companyName'])), 0, 3) . '/' . invoiceNo() . '/' . date('m/Y');
	$mergedDescriptionLines = [];
	foreach ($lineItems as $lineItem) {
		$mergedDescriptionLines[] = implode('||', [
			$lineItem['proformaNo'],
			$lineItem['description'],
			$lineItem['qty'],
			$lineItem['rate'],
			$lineItem['amount'],
		]);
	}

	return [true, [
		'transferReceipt' => '',
		'dueDate' => !empty($dueDates) ? max($dueDates) : '',
		'proformaNo' => implode(', ', array_values(array_unique($sourceProformas))),
		'prodQty' => count($lineItems) . ' invoices',
		'unitPrice' => '0.00',
		'totalAmt' => priceFormat($totalAmt),
		'orderID' => $base['orderID'],
		'invoiceDate' => '',
		'invoiceNo' => $invoiceNo,
		'companyName' => $base['companyName'],
		'applicantName' => $base['applicantName'],
		'address' => $base['address'],
		'city' => $base['city'],
		'country' => $base['country'],
		'mobileNumber' => $base['mobileNumber'],
		'prodDescription' => implode("\n", $mergedDescriptionLines),
		'paymentTerms' => $base['paymentTerms'],
		'invoiceNote' => '',
		'merged_from' => implode(', ', array_values(array_unique($sourceInvoiceNos))),
		'createdDate' => currDateTimeGmt(),
		'isPaid' => '0',
	]];
}
if(isset($_POST['submit']) && $_POST['submit']=="Submit"){
if(isset($_POST['action']) && $_POST['action']=="add"){
$sqlCust = mysqli_query($conn,"SELECT p.prodName,o.settlementSupplier,o.invoiceNo,o.quantity,o.totalAmt,o.unitPrice,o.orderUOM,c.companyName,c.applicantName,c.address,c.city,c.country,c.mobileNumber FROM customers AS c,orders as o,products as p WHERE p.id=o.productID and o.vendorID=c.id AND o.id='".$_POST['orderID']."'");
$rowCust = mysqli_fetch_assoc($sqlCust);
$invoiceNo = substr(strtoupper(str_replace(array(" ",'&'),array("",''),$rowCust['companyName'])),0,3).'/'.invoiceNo().'/'.date('m/Y');
$uploadfile = '';
mysqli_query($conn,"INSERT INTO invoices (transferReceipt,dueDate,proformaNo,prodQty,unitPrice,totalAmt,orderID,invoiceDate,invoiceNo,companyName,applicantName,address,city,country,mobileNumber,prodDescription,paymentTerms,invoiceNote,createdDate,isPaid) VALUES ('".$uploadfile."','".mysqli_real_escape_string($conn,sqlDtFormat($_POST['dueDate']))."','".mysqli_real_escape_string($conn,$rowCust['invoiceNo'])."','".mysqli_real_escape_string($conn,$rowCust['quantity'].'/'.$rowCust['orderUOM'])."','".mysqli_real_escape_string($conn,$rowCust['settlementSupplier'])."','".mysqli_real_escape_string($conn,priceFormat($rowCust['quantity']*$rowCust['settlementSupplier']))."','".mysqli_real_escape_string($conn,$_POST['orderID'])."','".mysqli_real_escape_string($conn,sqlDtFormat($_POST['invoiceDate']))."','".mysqli_real_escape_string($conn,$invoiceNo)."','".mysqli_real_escape_string($conn,$rowCust['companyName'])."','".mysqli_real_escape_string($conn,$rowCust['applicantName'])."','".mysqli_real_escape_string($conn,$rowCust['address'])."','".mysqli_real_escape_string($conn,$rowCust['city'])."','".mysqli_real_escape_string($conn,$rowCust['country'])."','".mysqli_real_escape_string($conn,$rowCust['mobileNumber'])."','".mysqli_real_escape_string($conn,$rowCust['prodName'].' / '.$_POST['prodDescription'])."','".mysqli_real_escape_string($conn,$_POST['paymentTerms'])."','".mysqli_real_escape_string($conn,$_POST['invoiceNote'])."','".currDateTimeGmt()."','0')");
header("location: invoices.php?act=2");
}
}
if(isset($_POST['submit']) && $_POST['submit']=="Merge"){
$invoiceIDs = isset($_POST['invoiceIDs']) ? (array)$_POST['invoiceIDs'] : [];
list($mergeOk, $mergeResult) = buildMergedInvoiceContent($conn, $invoiceIDs);
if(!$mergeOk){
header("location: invoices.php?action=merge&act=mergeErr&msg=".urlencode($mergeResult));
exit;
}
$mergeData = $mergeResult;
if(!empty($_POST['invoiceDate'])){
$mergeData['invoiceDate'] = sqlDtFormat($_POST['invoiceDate']);
}
if(!empty($_POST['dueDate'])){
$mergeData['dueDate'] = sqlDtFormat($_POST['dueDate']);
}elseif(!empty($mergeData['dueDate'])){
$mergeData['dueDate'] = sqlDtFormat($mergeData['dueDate']);
}
if(!empty($_POST['paymentTerms'])){
$mergeData['paymentTerms'] = $_POST['paymentTerms'];
}
if(!empty($_POST['invoiceNote'])){
$mergeData['invoiceNote'] = trim($_POST['invoiceNote']);
}
if(!empty($_POST['prodDescription'])){
$mergeData['invoiceNote'] = trim($mergeData['invoiceNote']."\n".$_POST['prodDescription']);
}
mysqli_query($conn,"INSERT INTO invoices (transferReceipt,dueDate,proformaNo,prodQty,unitPrice,totalAmt,orderID,invoiceDate,invoiceNo,companyName,applicantName,address,city,country,mobileNumber,prodDescription,paymentTerms,invoiceNote,merged_from,createdDate,isPaid) VALUES ('".mysqli_real_escape_string($conn,$mergeData['transferReceipt'])."','".mysqli_real_escape_string($conn,$mergeData['dueDate'])."','".mysqli_real_escape_string($conn,$mergeData['proformaNo'])."','".mysqli_real_escape_string($conn,$mergeData['prodQty'])."','".mysqli_real_escape_string($conn,$mergeData['unitPrice'])."','".mysqli_real_escape_string($conn,$mergeData['totalAmt'])."','".mysqli_real_escape_string($conn,$mergeData['orderID'])."','".mysqli_real_escape_string($conn,$mergeData['invoiceDate'])."','".mysqli_real_escape_string($conn,$mergeData['invoiceNo'])."','".mysqli_real_escape_string($conn,$mergeData['companyName'])."','".mysqli_real_escape_string($conn,$mergeData['applicantName'])."','".mysqli_real_escape_string($conn,$mergeData['address'])."','".mysqli_real_escape_string($conn,$mergeData['city'])."','".mysqli_real_escape_string($conn,$mergeData['country'])."','".mysqli_real_escape_string($conn,$mergeData['mobileNumber'])."','".mysqli_real_escape_string($conn,$mergeData['prodDescription'])."','".mysqli_real_escape_string($conn,$mergeData['paymentTerms'])."','".mysqli_real_escape_string($conn,$mergeData['invoiceNote'])."','".mysqli_real_escape_string($conn,$mergeData['merged_from'])."','".mysqli_real_escape_string($conn,$mergeData['createdDate'])."','".mysqli_real_escape_string($conn,$mergeData['isPaid'])."')");
header("location: invoices.php?act=2");
exit;
}
if(isset($_POST['submit']) && $_POST['submit']=="Upload"){
$sqlCust = mysqli_query($conn,"SELECT p.prodName,o.settlementSupplier,o.invoiceNo,o.quantity,o.totalAmt,o.unitPrice,o.orderUOM,c.companyName,c.applicantName,c.address,c.city,c.country,c.mobileNumber FROM customers AS c,orders as o,products as p WHERE p.id=o.productID and o.vendorID=c.id AND o.id='".$_POST['orderID']."'");
$rowCust = mysqli_fetch_assoc($sqlCust);
$imageName = $_FILES["transferReceipt"]["name"];
if(!empty($imageName)){
$orderImg = @pathinfo($imageName);
$strDtMix = @str_replace(' ',"-",@strtolower(@trim($orderImg['filename'])));
$invoiceNo = substr($rowCust['companyName'],0,3).invoiceNo().date('my').".".pathinfo($imageName, PATHINFO_EXTENSION);
move_uploaded_file($_FILES['transferReceipt']['tmp_name'], "../img/".$invoiceNo);
mysqli_query($conn,"UPDATE invoices SET transferReceipt='". mysqli_real_escape_string($conn,$invoiceNo)."' WHERE id='".$_POST['id']."'");	
}
header("location: invoices.php?act=2");
}

if(isset($_GET['status']) && $_GET['status']=="invoice"){
mysqli_query($conn,"UPDATE invoices SET isPaid='".mysqli_real_escape_string($conn,$_GET['st'])."' where id='".$_GET['id']."'");	
header("location: invoices.php?act=3");
}
	
if(isset($_GET['act']) && $_GET['act']=="delete"){
mysqli_query($conn,"DELETE FROM invoices WHERE id='".$_GET['id']."'");	
header("location: invoices.php?act=3");
}
$action = "add";
$hdTxt="Add New";
$paymentTerms = "";
$prodDescription = "";
$invoiceNote = "";
$id = "";
$invoiceDate = "";
$dueDate = "";
$orderID = "";
if(isset($_GET['action']) && $_GET['action']=="edit"){
$sqlEdit = mysqli_query($conn,"SELECT * FROM invoices WHERE id='".$_GET['id']."'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
extract($rowsEdit);
$action = "update";
$hdTxt="Update";
} 
include "include/header.php"; 
if(isset($_GET['action']) && $_GET['action']=="view"){ 
$sqlVw = mysqli_query($conn,"SELECT * FROM invoices WHERE id='".$_GET['id']."'");
$rowsVw = mysqli_fetch_assoc($sqlVw);
extract($rowsVw);
$viewMergedItems = parseMergedInvoiceLines($rowsVw['prodDescription']);
?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Invoices</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>invoices.php">Manage Invoices</a></span></li>
<li><span>View Invoice</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>invoices.php"><b>Back</b></a> </div>
<h2 class="panel-title">View Invoices</h2>
</header>
<div class="panel-body viewFrm">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Order *</label>
<div class="col-md-9">
<?php
if(!empty($viewMergedItems)){
	$mergedLabels = [];
	foreach($viewMergedItems as $item){
		$mergedLabels[] = $item['invoiceNo'];
	}
	echo 'Merged invoice from: '.implode(', ', $mergedLabels);
}else{
	$sqlOrdPrd = mysqli_query($conn,"SELECT o.id,p.prodName FROM orders as o,products as p WHERE p.id=o.productID and o.id='".$orderID."'");
	$rowsOP = mysqli_fetch_assoc($sqlOrdPrd);
	echo $rowsOP['prodName'].' (#'.$rowsOP['id'].')';
}
?>
</div></div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Invoice No</label>
<div class="col-md-9"><?=$invoiceNo?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Invoice Date</label>
<div class="col-md-9"><?=($invoiceDate!="")?viewDateFormat($invoiceDate):''?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Due Date</label>
<div class="col-md-9"><?=($dueDate!="")?viewDateFormat($dueDate):''?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Tranfer Receipt File</label>
<div class="col-md-9">
<?php
if($transferReceipt!=""){
echo '<a href="'.$siteUrlMain."img/".$transferReceipt.'" target="_blank" >View Receipt</a>';
}else{
echo '-';
}
?><br />
<input type="file" class="form-control" name="transferReceipt"  />
<input type="hidden" name="orderID" value="<?=$orderID;?>" />
<input type="hidden" name="id" value="<?=$id;?>" />
<button class="btn btn-primary" name="submit" value="Upload">Upload</button>
</div>
</div>    
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Proforma Invoice</label>
<div class="col-md-9"><?=$proformaNo?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Payment Terms</label>
<div class="col-md-9"><?=$paymentTerms?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Bill To *</label>
<div class="col-md-9 selectSrch"><?=$companyName.'<br />'.$applicantName."<br />".$address.','.$city.','.$country."<br />".$mobileNumber;?>
</div></div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Product</label>
<div class="col-md-10">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="55%">Item</th>
<th width="15%">Qty</th>
<th width="15%">Rate</th>
<th width="15%">Amount</th>
</tr>
</thead>
<tbody>
<?php
	if(!empty($viewMergedItems)){
		foreach($viewMergedItems as $item){
			$itemTitle = trim((string)$item['invoiceNo']);
			$itemBody = htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8');
			if($itemTitle !== ''){
				$itemBody = '<b>ProInv : '.htmlspecialchars($itemTitle, ENT_QUOTES, 'UTF-8').'</b><br />'.$itemBody;
			}
			echo '<tr>
			<td>'.$itemBody.'</td>
			<td>'.htmlspecialchars($item['qty'], ENT_QUOTES, 'UTF-8').'</td>
			<td>'.htmlspecialchars($item['rate'], ENT_QUOTES, 'UTF-8').'</td>
			<td>'.htmlspecialchars($item['amount'], ENT_QUOTES, 'UTF-8').'</td>
			</tr>';
		}
}else{
echo '<tr>
<td>'.htmlspecialchars($rowsVw['prodDescription'], ENT_QUOTES, 'UTF-8').'</td>
<td>'.htmlspecialchars($rowsVw['prodQty'], ENT_QUOTES, 'UTF-8').'</td>
<td>'.priceFormat($rowsVw['unitPrice']).'</td>
<td>'.priceFormat($rowsVw['totalAmt']).'</td>
</tr>';
}
?>
</tbody>
</table>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Notes</label>
<div class="col-md-9"><?=nl2br(htmlspecialchars($invoiceNote, ENT_QUOTES, 'UTF-8'))?></div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<button type="button" onclick="window.location.href='<?=$siteUrl;?>invoices.php'" class="btn btn-default">Back</button>
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
}else if(isset($_GET['action']) && $_GET['action']=="add"){ 
?>
<script src="<?=$siteUrl?>assets/vendor/summernote/summernote.js"></script>
<link rel="stylesheet" href="<?=$siteUrl?>assets/vendor/summernote/summernote.css" />
<link rel="stylesheet" href="<?=$siteUrl?>assets/vendor/summernote/summernote-bs3.css" /> 
<link rel="stylesheet" href="<?=$siteUrl?>assets/bootstrap-select.css">
<script src="<?=$siteUrl?>assets/bootstrap-select.js"></script>
<script type="text/javascript">$(document).ready(function(){ $(".invoiceDate").datepicker({todayHighlight:true,autoclose:true});
$(".dueDate").datepicker({todayHighlight:true,autoclose:true}); }); </script> 
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Invoices</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>invoices.php">Manage Invoices</a></span></li>
<li><span><?=$hdTxt;?> Invoice</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>invoices.php"><b>Back</b></a> </div>
<h2 class="panel-title"><?=$hdTxt;?> Invoices</h2>
</header>
<div class="panel-body">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Order *</label>
<div class="col-md-9 selectSrch">
<select class="selectpicker form-control" data-selected-text-format="values" name="orderID" id="orderID" data-live-search="true" data-live-search-placeholder="Search" required>
<option value="">- Select -</option>
<?php
$sqlOrdPrd = mysqli_query($conn,"SELECT o.id,p.prodName,o.invoiceNo FROM orders as o,products as p WHERE p.id=o.productID ORDER BY p.prodName ASC");
while($rowsOP = mysqli_fetch_assoc($sqlOrdPrd)){						
echo '<option value="'.$rowsOP['id'].'" '.((($rowsOP['id']==$orderID) || ($rowsOP['id']==$_GET['orderID']))?'selected="selected"':'').' >'.$rowsOP['prodName'].' (#'.$rowsOP['invoiceNo'].')</option>';
}
?>
</select>
</div></div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Invoice Date</label>
<div class="col-md-9">
<input type="text" class="form-control invoiceDate" readonly="readonly" data-date-format="dd-mm-yyyy" name="invoiceDate" value="<?=($invoiceDate!="")?viewDateFormat($invoiceDate):''?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Due Date</label>
<div class="col-md-9">
<input type="text" class="form-control dueDate" readonly="readonly" data-date-format="dd-mm-yyyy" name="dueDate" value="<?=($dueDate!="")?viewDateFormat($dueDate):''?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Payment Terms</label>
<div class="col-md-9">
<input type="text" class="form-control" name="paymentTerms" value="<?=$paymentTerms?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Item Description</label>
<div class="col-md-9">
<input type="text" class="form-control" name="prodDescription" value="<?=$prodDescription?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Invoice Note</label>
<div class="col-md-9">
<input type="text" class="form-control" name="invoiceNote" value="<?=$invoiceNote?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<input type="hidden" name="action" value="<?=$action;?>" />
<input type="hidden" name="id" value="<?=$id;?>" />
<button class="btn btn-primary" name="submit" value="Submit">Submit</button>
<button type="button" onclick="window.location.href='<?=$siteUrl;?>invoices.php'" class="btn btn-default">Cancel</button>
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
<?php }else if(isset($_GET['action']) && $_GET['action']=="merge"){ 
$invoiceOptions = [];
$sqlMergeOptions = mysqli_query($conn, "SELECT id, invoiceNo, proformaNo, companyName, prodDescription, totalAmt, invoiceNote, createdDate FROM invoices WHERE isPaid='0' ORDER BY id DESC");
while ($rowMerge = mysqli_fetch_assoc($sqlMergeOptions)) {
	$invoiceOptions[] = $rowMerge;
}
?>
<script src="<?=$siteUrl?>assets/vendor/summernote/summernote.js"></script>
<link rel="stylesheet" href="<?=$siteUrl?>assets/vendor/summernote/summernote.css" />
<link rel="stylesheet" href="<?=$siteUrl?>assets/vendor/summernote/summernote-bs3.css" /> 
<link rel="stylesheet" href="<?=$siteUrl?>assets/bootstrap-select.css">
<script src="<?=$siteUrl?>assets/bootstrap-select.js"></script>
<script type="text/javascript">
$(document).ready(function(){
	$(".invoiceDate").datepicker({todayHighlight:true,autoclose:true});
	$(".dueDate").datepicker({todayHighlight:true,autoclose:true});
	function renderMergePreview() {
		var selected = $("#invoiceIDs").val() || [];
		var body = "";
		var total = 0;
		if (!selected.length) {
			body = "<tr><td colspan='4' align='center'>Select invoices to preview the merged result.</td></tr>";
			$("#mergePreviewBody").html(body);
			$("#mergeTotal").text("0.00");
			return;
		}
		selected.forEach(function(id) {
			var opt = $("#invoiceIDs option[value='" + id + "']");
			var amt = parseFloat(opt.data("amount")) || 0;
			total += amt;
			body += "<tr>" +
				"<td>" + opt.data("invoice") + "</td>" +
				"<td>" + opt.data("company") + "</td>" +
				"<td>" + opt.data("description") + "</td>" +
				"<td style='text-align:right'>" + amt.toFixed(2) + "</td>" +
			"</tr>";
		});
		body += "<tr><td colspan='3'><b>Total</b></td><td style='text-align:right'><b>" + total.toFixed(2) + "</b></td></tr>";
		$("#mergePreviewBody").html(body);
		$("#mergeTotal").text(total.toFixed(2));
	}
	$("#invoiceIDs").on("change", renderMergePreview);
	renderMergePreview();
});
</script>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Invoices</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>invoices.php">Manage Invoices</a></span></li>
<li><span>Merge Invoice</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>invoices.php"><b>Back</b></a> </div>
<h2 class="panel-title">Merge Invoice</h2>
</header>
<div class="panel-body">
<?php
if(isset($_GET['act']) && $_GET['act']=="mergeErr"){
	$mergeMsg = isset($_GET['msg']) ? $_GET['msg'] : 'Unable to merge invoices.';
	echo '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.htmlspecialchars($mergeMsg, ENT_QUOTES, 'UTF-8').'</strong></div>';
}
?>
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Invoices *</label>
<div class="col-md-9 selectSrch">
<select class="selectpicker form-control" multiple data-selected-text-format="count" name="invoiceIDs[]" id="invoiceIDs" data-live-search="true" data-live-search-placeholder="Search" required>
<?php
foreach($invoiceOptions as $rowMerge){
	$descPreview = trim(strip_tags($rowMerge['prodDescription']));
	if(strlen($descPreview) > 70){
		$descPreview = substr($descPreview, 0, 70) . '...';
	}
	$notePreview = trim(strip_tags($rowMerge['invoiceNote']));
	if($notePreview !== ''){
		if(strlen($notePreview) > 50){
			$notePreview = substr($notePreview, 0, 50) . '...';
		}
		$descPreview = trim($descPreview . ' | Note: ' . $notePreview);
	}
	$rawAmount = str_replace(',', '', (string)$rowMerge['totalAmt']);
	echo '<option value="'.(int)$rowMerge['id'].'" data-invoice="'.htmlspecialchars($rowMerge['invoiceNo'], ENT_QUOTES, 'UTF-8').'" data-company="'.htmlspecialchars($rowMerge['companyName'], ENT_QUOTES, 'UTF-8').'" data-description="'.htmlspecialchars($descPreview, ENT_QUOTES, 'UTF-8').'" data-amount="'.htmlspecialchars($rawAmount, ENT_QUOTES, 'UTF-8').'">'.$rowMerge['invoiceNo'].' - '.$rowMerge['companyName'].' - '.$descPreview.'</option>';
}
?>
</select>
</div></div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Invoice Date</label>
<div class="col-md-9">
<input type="text" class="form-control invoiceDate" readonly="readonly" data-date-format="dd-mm-yyyy" name="invoiceDate" value="<?=($invoiceDate!="")?viewDateFormat($invoiceDate):''?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Due Date</label>
<div class="col-md-9">
<input type="text" class="form-control dueDate" readonly="readonly" data-date-format="dd-mm-yyyy" name="dueDate" value="<?=($dueDate!="")?viewDateFormat($dueDate):''?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Payment Terms</label>
<div class="col-md-9">
<input type="text" class="form-control" name="paymentTerms" value="<?=$paymentTerms?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Extra Note</label>
<div class="col-md-9">
<textarea class="form-control" name="prodDescription" id="inputDefault" rows="4" placeholder="Optional extra note for the merged invoice"><?=$prodDescription?></textarea>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Invoice Note</label>
<div class="col-md-9">
<textarea class="form-control" name="invoiceNote" id="inputDefault" rows="3" placeholder="Optional extra note"><?=$invoiceNote?></textarea>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label">Preview</label>
<div class="col-md-9">
<div class="table-responsive">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="18%">Invoice No</th>
<th width="22%">Company</th>
<th width="45%">Item / Notes</th>
<th width="15%" style="text-align:right">Amount</th>
</tr>
</thead>
<tbody id="mergePreviewBody">
<tr><td colspan="4" align="center">Select invoices to preview the merged result.</td></tr>
</tbody>
</table>
</div>
<div class="help-block">Merged total: <b id="mergeTotal">0.00</b></div>
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<input type="hidden" name="action" value="add" />
<input type="hidden" name="id" value="<?=$id;?>" />
<button class="btn btn-primary" name="submit" value="Merge">Merge</button>
<button type="button" onclick="window.location.href='<?=$siteUrl;?>invoices.php'" class="btn btn-default">Cancel</button>
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
<h2>Manage Invoice</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span>Manage Invoice</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act'])){
    $msgTxt = '';
    if($_GET['act'] == '2'){
        $msgTxt = 'Invoice has been processed successfully.';
    } else if($_GET['act'] == '3'){
        $msgTxt = 'Invoice has been deleted successfully.';
    }

    if(!empty($msgTxt)){
        echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.$msgTxt.'</strong></div>';
    }
}
?>
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>invoices.php?action=add"><b>Add New</b></a> | <a href="<?=$siteUrl;?>invoices.php?action=merge"><b>Merge Invoices</b></a> </div>
<h2 class="panel-title">Manage Invoice</h2>
</header>
<div class="panel-body">
<div class="form-group">
<div class="col-md-6">
<form class="form-horizontal form-bordered" method="get">
<input type="text" style="width:80%;float:left" class="form-control" name="q" value="<?=(isset($_GET['q'])?$_GET['q']:'')?>" placeholder="Search" id="inputDefault"  >
<button class="btn btn-primary" name="submit" value="Submit">GO</button>
</form> <span class="hintTxt">Search :-  Subject.</span>
</div>
<div class="col-md-6" align="right">
<a href="<?=$siteUrl;?>invoices.php?sort=1" class="btn btn-default green">Paid (<?=invoicePaidTotal(1)?>)</a>
<a href="<?=$siteUrl;?>invoices.php?sort=0" class="btn btn-default blue">Unpaid (<?=invoicePaidTotal(0)?>)</a>            
</div>
</div>	
<div class="table-responsive">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="10%">Proforma Invoice</th>
<th width="15%">Vendor</th>
<th width="30%">Product Designation</th>
<th width="10%">Invoice PDF</th>
<th width="10%">Created On</th>
<th width="20%" class="text-center">Action</th>
</tr>
</thead>
<tbody>
<?php
$q = $_GET['q'] ?? '';
$page_name="invoices.php?q=".$q;
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
if(!empty($q)){
$sqlSrch = " and (invoiceNo LIKE '%".$q."%') ";
}

$sqlSeller = "SELECT id,proformaNo,isPaid,invoiceNo,prodDescription,companyName,createdDate FROM invoices where isPaid='".((isset($_GET['sort'])?$_GET['sort']:'0'))."' ".$sqlSrch." ORDER BY id DESC limit $eu, $limit";
$sqltot = "SELECT id FROM invoices where isPaid='".((isset($_GET['sort'])?$_GET['sort']:'0'))."' ".$sqlSrch." ORDER BY id DESC";

$resultSeller= mysqli_query($conn,$sqlSeller);
$resulttot=mysqli_query($conn,$sqltot);
$nume=mysqli_num_rows($resulttot);
if(@mysqli_num_rows($resultSeller)!=0){

$sno=$start;
while($rows = mysqli_fetch_assoc($resultSeller)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.$rows['proformaNo'].'</td>
<td>'.$rows['companyName'].'</td>
<td>'.$rows['prodDescription'].'</td>
<td>'.$rows['invoiceNo'].'</td>
<td align="center">'.viewDtTm($rows['createdDate']).'</td>
<td align="center">
<a title="View Order" class="btn btn-default viewBtn" href="invoices.php?action=view&id='.$rows['id'].'">View</a>
<a title="Download PDF" class="btn btn-default downBtn" href="'.$siteUrlMain.'invoicePDF.php?act=pdfDwnld&id='.$rows['id'].'">Download</a>';
if($rows['isPaid']){
echo '<a title="Invoice Status" class="btn btn-default editBtn" href="invoices.php?status=invoice&st=0&id='.$rows['id'].'">Unpaid</a>';
}else{
echo '<a title="Invoice Status" class="btn btn-default editBtn" href="invoices.php?status=invoice&st=1&id='.$rows['id'].'">Paid</a>';
}
echo '<a title="Delete this record" class="btn btn-default delBtn" onclick="return confirm(\'Are you sure you want to delete?\');" href="invoices.php?act=delete&id='.$rows['id'].'">Delete</a>
</td>
</tr>';
} //.,.viewBtn,.delBtn,.notsBtn,.pdfBtn,.downBtn{font-size:13px;padding:5px 10px;border-radius:10px;color:#000;font-weight:600}
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
