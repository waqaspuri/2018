<?php
include "include/sessionAdmin.php";
include "include/configure.php";
// Track the timestamp of each order/lead edit automatically in MySQL.
$modifiedDateColumn = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'modifiedDate'");
if ($modifiedDateColumn && mysqli_num_rows($modifiedDateColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE orders ADD COLUMN modifiedDate DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER createdDate");
}
if(isset($_GET['toDo']) && $_GET['toDo']=="mark"){
mysqli_query($conn,"UPDATE to_do SET isDone='1' WHERE id='".$_GET['id']."'");
header("location: index.php");
}
include "include/header.php";
$sqlTask = mysqli_query($conn,"SELECT id,productID,vendorID,customerID,dta,eta,newETA,statusID FROM orders where statusID<>'3' ORDER BY id DESC LIMIT 30");
$numRowsTask = mysqli_num_rows($sqlTask);
$dtAry = explode(" ",currDateTimeGmt());
?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Dashboard</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span>Dashboard</span></li>
</ol>
<a >&nbsp;</i></a> </div>
</header>
<div class="col-md-12 noPadding">
<section class="panel">
<div class="panel-body">
<div class="row">
<div class="col-md-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>tasks.php"><b>View All</b></a> </div>
<h2 class="panel-title">Orders by Status</h2>
</header>
<div class="panel-body" style="padding:15px">
<div class="row" style="display:flex;flex-wrap:wrap">
<?php
$sqlOrdr = mysqli_query($conn,"SELECT s.id,s.status,s.bgColor,COUNT(o.id) AS orderCount,MAX(COALESCE(o.modifiedDate,o.createdDate)) AS lastModified FROM status AS s LEFT JOIN orders AS o ON o.statusID=s.id GROUP BY s.id,s.status,s.bgColor ORDER BY (lastModified IS NULL) ASC,lastModified DESC,s.id ASC");
if($sqlOrdr){
while($rows = mysqli_fetch_assoc($sqlOrdr)){
    $statusID = (int)$rows['id'];
    $statusCount = (int)$rows['orderCount'];
    $statusName = htmlspecialchars((string)$rows['status'], ENT_QUOTES, 'UTF-8');
    $statusColor = trim((string)($rows['bgColor'] ?? ''));
    if (!preg_match('/^(#[0-9a-f]{3,8}|[a-z]{3,20})$/i', $statusColor)) $statusColor = '#f0ad4e';
    $lastModified = trim((string)($rows['lastModified'] ?? ''));
    $lastModifiedLabel = $lastModified !== '' ? 'Last modified ' . htmlspecialchars((string)viewDtTm($lastModified), ENT_QUOTES, 'UTF-8') : 'No orders yet';
    $statusUrl = $statusID === 1 ? 'leads.php' : 'orders.php?sID=' . $statusID;
    echo '<div class="col-xs-6 col-md-4" style="margin-bottom:15px">';
    echo '<a href="'.$statusUrl.'" style="display:block;height:100%;min-height:120px;padding:18px 20px;border:1px solid #e5e5e5;border-left:5px solid '.$statusColor.';border-radius:4px;background:#fff;color:#333;text-decoration:none;box-shadow:0 1px 3px rgba(0,0,0,.07)">';
    echo '<div style="font-size:15px;font-weight:600;line-height:1.35">'.$statusName.'</div>';
    echo '<div style="font-size:30px;font-weight:700;line-height:1.2;margin:10px 0 4px">'.$statusCount.'</div>';
    echo '<div style="font-size:12px;color:#777">'.($statusCount === 1 ? 'order' : 'orders').' &middot; View orders &rarr;</div>';
    echo '<div style="font-size:11px;color:#999;margin-top:8px">'.$lastModifiedLabel.'</div>';
    echo '</a></div>';
}
}
?>
</div>
</div>
</section>
</div>
</div>
</div>
</section>
</div>


<div class="col-md-12 noPadding">
<section class="panel">
<div class="panel-body">
<div class="row">
<div class="col-md-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>orders.php"><b>View All</b></a> </div>
<h2 class="panel-title">Orders Arrival ETA</h2>
</header>
<div class="panel-body" style="padding:0">
<div class="table-responsive">
<table class="table table-bordered table-striped table-condensed mb-none taskLstTab">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="15%">Proforma Invoice</th>
<th width="14%">Customer </th>
<th width="15%">Product</th>
<th width="15%">ETA</th>
<th width="18%">Status</th>
<th width="18%" class="text-center">In-Transit</th>
</tr>
</tr>
</thead>
<tbody>
<?php
$sqlOrdr = mysqli_query($conn,"SELECT o.documentOrderNo,o.billNo,o.latestDateShipment,o.expiryDate,o.documentDate,o.orderTransport,o.eta,o.dta,o.id,o.invoiceNo,o.customerID,p.prodName,o.statusID FROM orders as o,products as p WHERE DATE(o.eta)>='".$dtAry[0]."' and o.statusID='8' and p.id=o.productID ORDER BY o.id DESC LIMIT 10");
$numRowsOrdr = mysqli_num_rows($sqlOrdr);
if($numRowsOrdr>0){
$sno=0;
while($rows = mysqli_fetch_assoc($sqlOrdr)){
$sno++;
$msgTxt = '';
$getRDStg="";
$getRD = getPerDate($rows['eta'],$rows['dta']);
if($getRD>0 && $getRD<=100 && !(timeEst($rows['dta']) > 0)){
	$getRDStg = $getRD.'%';
} else {
$getRDStg = '|||||||||||'; 
}

if($rows['statusID']=='8'){
if($rows['dta']=="--"){ $msgTxt.='<p>- Missing ETD</p>';}
if($rows['eta']=="--"){ $msgTxt.='<p>- Missing ETA</p>';}
if($rows['documentDate']==""){ $msgTxt.='<p>- Document Date</p>';}
if($rows['expiryDate']==""){ $msgTxt.='<p>- Expiry Date</p>';}
if($rows['latestDateShipment']==""){ $msgTxt.='<p>- Latest Date Shipment</p>';}
if($rows['billNo']==""){ $msgTxt.='<p>- Bill Number</p>';}
if($rows['documentOrderNo']==""){ $msgTxt.='<p>- Document Order No</p>';}
echo '<style type="text/css">.statusBgTD'.$sno.' td{background:'.statusBg($rows['statusID']).'}</style>';
}
echo '<tr '.(($rows['dta']=="--" || $rows['eta']=="--")?'class="bgRed"':'').'>
<td>&nbsp; '.$sno.'</td>
<td><a href="orders.php?action=view&id='.$rows['id'].'">'.$rows['invoiceNo'].'</a></td>
<td><a href="orders.php?action=view&id='.$rows['id'].'">'.customerName($rows['customerID']).'</a></td>
<td>'.$rows['prodName'];
if($msgTxt){echo '<div class="alert alert-danger smlTxt"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong><p>Error Fields :-</p> '.$msgTxt.'</strong></div>';}
echo '</td>
<td align="center">'.viewDateFormat($rows['eta']).' <br />'.viewByDaysFormat($rows['eta']).'</td>
<td><span class="statusBg" style="background:'.statusBg($rows['statusID']).';">'.statusNm($rows['statusID']).'</span></td>
<td align="center">'.$getRDStg.'</td>
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
</section>
</div>
</div>
</div>
</section>
</div>

<div class="col-md-12 noPadding">
<section class="panel">
<div class="panel-body">
<div class="row">
<div class="col-md-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>orders.php"><b>View All</b></a> </div>
<h2 class="panel-title">Orders Quene</h2>
</header>
<div class="panel-body" style="padding:0">
<style>
.orders-queue-status{display:inline-block;max-width:100%;padding:6px 10px!important;border-radius:999px!important;font-size:11px!important;font-weight:600;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;vertical-align:middle;color:#263238!important;box-shadow:inset 0 0 0 1px rgba(0,0,0,.08)}
</style>
<div class="table-responsive">
<table class="table table-bordered table-striped table-condensed mb-none taskLstTab">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="8%">ID</th>
<th width="15%">Vendor</th>
<th width="15%">Customer</th>
<th width="18%">Product Designation</th>
<th width="20%">Status</th>
<th width="19%">Created On</th>
</tr>
</tr>
</thead>
<tbody>
<?php
$sqlOrdr = mysqli_query($conn,"SELECT o.id,o.invoiceNo,o.vendorID,o.customerID,p.prodName,o.createdDate,o.statusID FROM orders as o,products as p WHERE p.id=o.productID AND CAST(o.statusID AS UNSIGNED) BETWEEN 5 AND 18 ORDER BY o.id DESC LIMIT 10");
$numRowsOrdr = mysqli_num_rows($sqlOrdr);
if($numRowsOrdr>0){
$sno=0;
while($rows = mysqli_fetch_assoc($sqlOrdr)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td><a href="orders.php?action=view&id='.$rows['id'].'">'.$rows['invoiceNo'].'</a></td>
<td>'.customerName($rows['vendorID']).'</td>
<td>'.customerName($rows['customerID']).'</td>
<td>'.$rows['prodName'].'</td>
<td><span class="label label-default orders-queue-status" title="'.htmlspecialchars((string)statusNm($rows['statusID']), ENT_QUOTES, 'UTF-8').'" style="background:'.htmlspecialchars((string)statusBg($rows['statusID']), ENT_QUOTES, 'UTF-8').';">'.htmlspecialchars((string)statusNm($rows['statusID']), ENT_QUOTES, 'UTF-8').'</span></td>
<td align="center">'.viewDtTm($rows['createdDate']).'</td>
</tr>';
}
}else{
echo "<tr>
<td colspan='7' align='center'>No Data Found.</td>
</tr>";
}
?>
</tbody>
</table>
</div>
</div>
</section>
</div>
</div>
</div>
</section>
</div>

<div class="col-md-12 noPadding">
<section class="panel">
<div class="panel-body">
<div class="row">
<div class="col-md-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>tasks.php"><b>View All</b></a> </div>
<h2 class="panel-title">Latest Tasks</h2>
</header>
<div class="panel-body" style="padding:0">
<div class="table-responsive">
<table class="table table-bordered table-striped table-condensed mb-none taskLstTab">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="15%">Customer</th>
<th width="10%">Subject</th>
<th width="10%">Due Date</th>
<th width="10%">Repeat Date</th>
<th width="20%">Assign To</th>
</tr>
</tr>
</thead>
<tbody>
<?php
$sqlOrdr = mysqli_query($conn,"SELECT t.id,t.subject,t.dueDate,t.repeatDate,t.priorityID,t.assignTo,o.customerID FROM tasks as t,orders o where o.id=t.orderID ORDER BY o.id DESC LIMIT 10");
$numRowsOrdr = mysqli_num_rows($sqlOrdr);
if($numRowsOrdr>0){
$sno=0;
while($rows = mysqli_fetch_assoc($sqlOrdr)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td><a title="View this record" href="tasks.php?action=view&id='.$rows['id'].'">'.customerName($rows['customerID']).'</a></td>
<td>'.stringTxtLimit($rows['subject'],50).'</td>
<td>'.viewDateFormat($rows['dueDate']).'</td>
<td>'.((isset($rows['repeatDate']) && $rows['repeatDate']!='0000-00-00')?viewDateFormat($rows['repeatDate']):'').'</td>
<td>';
$assignToAry = @explode(",",$rows['assignTo']);
$asignToStr = array();
if(is_array($assignToAry)){
foreach($assignToAry as $assignToVal){
if($assignToVal!=""){
$asignToStr[] = customerName($assignToVal);
}
}
echo @implode(", ",$asignToStr);
}
echo '</td>
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
</section>
</div>
</div>
</div>
</section>
</div>




<div class="col-md-12 noPadding">
<section class="panel">
<div class="panel-body">
<div class="row">
<div class="col-md-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>tasks.php"><b>View All</b></a> </div>
<h2 class="panel-title">Order by Leads</h2>
</header>
<div class="panel-body" style="padding:0">
<div class="table-responsive">
<table class="table table-bordered table-striped table-condensed mb-none taskLstTab">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="6%">ID</th>
<th width="15%">Vendor</th>
<th width="15%">Customer</th>
<th width="20%">Product Designation</th>
</tr>
</tr>
</thead>
<tbody>
<?php
$sqlSrch = '';
$sqlOrdr = mysqli_query($conn,"SELECT o.dta,o.eta,o.newETA,o.id,o.invoiceNo,o.vendorID,o.customerID,p.prodName,o.createdDate,o.statusID FROM orders as o,products as p WHERE p.id=o.productID AND o.statusID='1' ".$sqlSrch." ORDER BY o.id DESC LIMIT 10");
$numRowsOrdr = mysqli_num_rows($sqlOrdr);
if($numRowsOrdr>0){
$sno=0;
while($rows = mysqli_fetch_assoc($sqlOrdr)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td><a href="orders.php?action=view&id='.$rows['id'].'">'.$rows['invoiceNo'].'</a></td>
<td>'.customerName($rows['vendorID']).'</td>
<td>'.customerName($rows['customerID']).'</td>
<td>'.$rows['prodName'].'</td>
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
</section>
</div>
</div>
</div>
</section>
</div>



</section>
</div>
</section>
<script type="text/javascript">$(document).ready(function(){
$('#quickNote').modal('show'); $('.close').on('click', function(){   $("body").removeClass("modal-open"); $(".modal-backdrop").removeClass("in"); return true; }); });
</script>
<div class="modal fade" id="quickNote" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-body">
<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>toDo.php"><b>View All</b></a> </div>
<h2 class="panel-title">Manage Quick Notes</h2>
</header>
<div class="panel-body" style="padding:0">
<div class="table-responsive">
<table class="table table-bordered table-striped table-condensed mb-none taskLstTab">
<tbody>
<?php
$sqlOrdr = mysqli_query($conn,"SELECT * FROM to_do where isDone='0' order by id DESC LIMIT 10");
$numRowsOrdr = mysqli_num_rows($sqlOrdr);
if($numRowsOrdr>0){
$sno=0;
while($rows = mysqli_fetch_assoc($sqlOrdr)){
$sno++;
echo '<tr>
<td><b>'.$sno.'. '.$rows['title'].'</b><br />
<div class="quickNotePop">'.stringTxtLimit($rows['description'],500).' <b>on</b>  '.viewDtTm($rows['createdDate']).'
<p>'.(($rows['isDone'])?'<span class="grnTxt">Mark as completed</span>':'<a href="index.php?toDo=mark&id='.$rows['id'].'" class="redTxt">Mark as complete</a>').'</p>
</div>
</td>
</tr>';
}
}else{
echo "<tr>
<td colspan='8' align='center'>No task Found.</td>
</tr>";
}
?>
</tbody>
</table>
</div>
</div>

</div>
</div>
</div>
</div>
<?php include "include/footer.php"; ?>
