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
<section class="dashboard-orders-panel">
<header class="dashboard-orders-heading">
<div><h2>Orders by status</h2><p>Your latest orders, grouped by progress.</p></div>
<a class="dashboard-order-button" href="<?=$siteUrl;?>orders.php">View all orders <span aria-hidden="true">&rarr;</span></a>
</header>
<style>
.dashboard-orders-panel{clear:both;margin-bottom:24px;color:#253349}
.dashboard-orders-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}
.dashboard-orders-heading h2{margin:0;font-size:20px;line-height:1.3;font-weight:600;letter-spacing:-.4px;color:#1e293b}
.dashboard-orders-heading p{margin:4px 0 0;font-size:12px;color:#64748b}
.dashboard-status-grid{display:flex;flex-wrap:wrap;align-items:flex-start;gap:14px}
.dashboard-order-status{flex:1 1 340px;min-width:0;max-width:100%;border:1px solid #dfe5ed;border-radius:12px;background:#fff;box-shadow:0 2px 6px rgba(15,23,42,.035);overflow:hidden}
.dashboard-order-status > summary{display:flex;align-items:center;gap:8px;padding:13px 14px;cursor:pointer;font-size:13px;line-height:1.4;font-weight:600;color:#253349;list-style:none;background:#f8fafc}
.dashboard-order-status > summary::-webkit-details-marker{display:none}
.dashboard-order-status > summary::after{content:'';width:6px;height:6px;border-right:1.5px solid #64748b;border-bottom:1.5px solid #64748b;transform:rotate(-45deg);margin-left:4px;flex-shrink:0}
.dashboard-order-status[open] > summary::after{transform:rotate(45deg);margin-top:-3px}
.dashboard-status-dot{width:9px;height:9px;flex-shrink:0;border-radius:50%;background:var(--status-color,#94a3b8);box-shadow:0 0 0 1px rgba(15,23,42,.12)}
.dashboard-status-name{flex:1;min-width:0;overflow-wrap:anywhere}
.dashboard-status-count{padding:2px 7px;border-radius:6px;background:#e8edf3;color:#475569;font-size:11px;font-variant-numeric:tabular-nums}
.dashboard-order-status > summary:focus-visible,.dashboard-orders-panel a:focus-visible{outline:2px solid #2563eb;outline-offset:-2px}
.dashboard-order-status .status-orders-link{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:7px 14px;border-top:1px solid #edf1f5;color:#64748b;font-size:11px}
.dashboard-orders-panel a{color:#2563eb;text-decoration:none}
.dashboard-orders-panel a:hover{text-decoration:underline}
.dashboard-orders-panel .dashboard-order-button{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:5px 10px;min-height:32px;border:1px solid #dce3ec;border-radius:7px;background:#fff;color:#334155;font-size:12px;font-weight:600;line-height:1.4;white-space:nowrap;text-decoration:none}
.dashboard-orders-panel .dashboard-order-button:hover{background:#eff6ff;border-color:#b9cff5;color:#1d4ed8}
.dashboard-order-list{list-style:none;margin:0;padding:0}
.dashboard-order-item{display:flex;align-items:center;gap:10px;padding:9px 14px;border-top:1px solid #edf1f5}
.dashboard-order-item:hover{background:#fafcff}
.dashboard-order-info{flex:1;min-width:0;font-size:12px;line-height:1.5;overflow-wrap:anywhere}
.dashboard-order-title,.dashboard-order-product,.dashboard-order-parties{display:flex;flex-wrap:wrap;align-items:baseline;column-gap:8px;row-gap:2px}
.dashboard-order-title{font-weight:600;font-size:12px}
.dashboard-order-reference{font-size:10px;font-weight:400;color:#64748b}
.dashboard-order-product{margin-top:2px;color:#334155;font-weight:600}
.dashboard-order-quantity{color:#475569;font-size:10px;font-weight:400;background:#f1f5f9;border-radius:4px;padding:0 5px}
.dashboard-order-parties{margin-top:2px;color:#64748b;font-size:11px}
.dashboard-order-parties b{font-weight:500;color:#475569}
.dashboard-order-date{font-size:10px;color:#64748b}
.dashboard-order-view{flex-shrink:0;margin-left:auto}
@media(min-width:1200px){.dashboard-order-status{flex:0 0 calc(50% - 7px)}}
@media(min-width:1700px){.dashboard-order-status{flex-basis:calc(33.333333% - 10px)}}
@media(max-width:480px){.dashboard-orders-heading{align-items:flex-start;flex-direction:column}.dashboard-order-item{padding:9px 10px}.dashboard-order-status > summary{padding:12px 10px}}
</style>
<div class="dashboard-status-grid">
<?php
$dashboardOrdersByStatus = array();
$dashboardOrdersPerStatus = 10;
$dashboardOrdersResult = mysqli_query($conn, "SELECT o.id,o.statusID,o.invoiceNo,o.documentOrderNo,o.quantity,o.orderUOM,o.createdDate,
    v.applicantName AS vendorName,c.applicantName AS customerName,p.prodName
    FROM orders AS o
    JOIN status AS s ON s.id=o.statusID
    LEFT JOIN customers AS v ON v.id=o.vendorID
    LEFT JOIN customers AS c ON c.id=o.customerID
    LEFT JOIN products AS p ON p.id=o.productID
    ORDER BY COALESCE(o.modifiedDate,o.createdDate) DESC,o.id DESC");
if ($dashboardOrdersResult) {
    while ($dashboardOrder = mysqli_fetch_assoc($dashboardOrdersResult)) {
        $dashboardStatusID = (int)$dashboardOrder['statusID'];
        if (count($dashboardOrdersByStatus[$dashboardStatusID] ?? array()) < $dashboardOrdersPerStatus) {
            $dashboardOrdersByStatus[$dashboardStatusID][] = $dashboardOrder;
        }
    }
}
$sqlOrdr = mysqli_query($conn,"SELECT s.id,s.status,s.bgColor,COUNT(o.id) AS orderCount,MAX(COALESCE(o.modifiedDate,o.createdDate)) AS lastModified FROM status AS s LEFT JOIN orders AS o ON o.statusID=s.id GROUP BY s.id,s.status,s.bgColor HAVING COUNT(o.id) > 0 ORDER BY (lastModified IS NULL) ASC,lastModified DESC,s.id ASC");
if($sqlOrdr){
if (mysqli_num_rows($sqlOrdr) === 0) {
    echo '<p class="text-muted">No orders found.</p>';
}
while($rows = mysqli_fetch_assoc($sqlOrdr)){
    $statusID = (int)$rows['id'];
    $statusCount = (int)$rows['orderCount'];
    $statusName = htmlspecialchars((string)$rows['status'], ENT_QUOTES, 'UTF-8');
    $statusColor = trim((string)($rows['bgColor'] ?? ''));
    if (!preg_match('/^(#[0-9a-f]{3,8}|[a-z]{3,20})$/i', $statusColor)) $statusColor = '#f0ad4e';
    $lastModified = trim((string)($rows['lastModified'] ?? ''));
    $lastModifiedLabel = $lastModified !== '' ? 'Last modified ' . htmlspecialchars((string)viewDtTm($lastModified), ENT_QUOTES, 'UTF-8') : 'No orders yet';
    $statusUrl = $statusID === 1 ? 'leads.php' : 'orders.php?sID=' . $statusID;
    echo '<details class="dashboard-order-status" style="--status-color:'.$statusColor.'" open>';
    echo '<summary title="'.$lastModifiedLabel.'"><span class="dashboard-status-dot" aria-hidden="true"></span><span class="dashboard-status-name">'.$statusName.'</span><span class="dashboard-status-count">'.$statusCount.'</span></summary>';
    echo '<div class="status-orders-link"><span>Latest '.min($statusCount, $dashboardOrdersPerStatus).' of '.$statusCount.'</span><a href="'.$statusUrl.'">View all <span aria-hidden="true">&rarr;</span></a></div>';
    echo '<ul class="dashboard-order-list">';
    foreach ($dashboardOrdersByStatus[$statusID] ?? array() as $dashboardOrder) {
        $orderUrl = 'orders.php?action=view&amp;id=' . (int)$dashboardOrder['id'];
        $invoiceLabel = trim((string)$dashboardOrder['invoiceNo']);
        if ($invoiceLabel === '') $invoiceLabel = '#' . (int)$dashboardOrder['id'];
        echo '<li class="dashboard-order-item"><div class="dashboard-order-info"><div class="dashboard-order-title"><a href="'.$orderUrl.'">'.htmlspecialchars($invoiceLabel, ENT_QUOTES, 'UTF-8').'</a>';
        if (trim((string)$dashboardOrder['documentOrderNo']) !== '') {
            echo '<span class="dashboard-order-reference">Order '.htmlspecialchars((string)$dashboardOrder['documentOrderNo'], ENT_QUOTES, 'UTF-8').'</span>';
        }
        echo '</div><div class="dashboard-order-product"><span>'.htmlspecialchars((string)($dashboardOrder['prodName'] ?? ''), ENT_QUOTES, 'UTF-8').'</span>';
        echo '<span class="dashboard-order-quantity">Qty '.htmlspecialchars(trim((string)$dashboardOrder['quantity'].' '.(string)$dashboardOrder['orderUOM']), ENT_QUOTES, 'UTF-8').'</span></div>';
        echo '<div class="dashboard-order-parties"><span><b>Vendor</b> '.htmlspecialchars((string)($dashboardOrder['vendorName'] ?? ''), ENT_QUOTES, 'UTF-8').'</span>';
        echo '<span><b>Customer</b> '.htmlspecialchars((string)($dashboardOrder['customerName'] ?? ''), ENT_QUOTES, 'UTF-8').'</span></div>';
        echo '<div class="dashboard-order-date">Created '.htmlspecialchars((string)viewDtTm($dashboardOrder['createdDate']), ENT_QUOTES, 'UTF-8').'</div></div>';
        echo '<a class="dashboard-order-button dashboard-order-view" aria-label="View order '.htmlspecialchars($invoiceLabel, ENT_QUOTES, 'UTF-8').'" href="'.$orderUrl.'">View <span aria-hidden="true">&rarr;</span></a></li>';
    }
    echo '</ul></details>';
}
}
?>
</div>
</section>


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
