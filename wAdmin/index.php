<?php
include "include/sessionAdmin.php";
include "include/configure.php";
// Track the timestamp of each order/lead edit automatically in MySQL.
$modifiedDateColumn = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'modifiedDate'");
if ($modifiedDateColumn && mysqli_num_rows($modifiedDateColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE orders ADD COLUMN modifiedDate DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER createdDate");
}
$taskModifiedDateColumn = mysqli_query($conn, "SHOW COLUMNS FROM tasks LIKE 'modifiedDate'");
if ($taskModifiedDateColumn && mysqli_num_rows($taskModifiedDateColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN modifiedDate DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER createdDate");
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
.dashboard-order-status > .panel-body{padding:0;border-radius:0 0 12px 12px}
.dashboard-order-status .table-responsive{overflow:visible;margin:0}
.dashboard-order-status .table{display:block;width:100%;min-width:0;margin:0;border:0}
.dashboard-order-status .table>thead{display:none}
.dashboard-order-status .table>tbody{display:block;padding:2px 0 4px}
.dashboard-order-status .table>tbody>tr{display:flex;flex-wrap:wrap;align-items:center;gap:3px 10px;margin:9px 12px;padding:11px 12px;border:1px solid #e2e8f0;border-radius:9px;background:#fff;box-shadow:0 1px 3px rgba(15,23,42,.035)}
.dashboard-order-status .table>tbody>tr>td{display:block;width:auto;border:0;padding:0!important;font-size:12px;line-height:1.45;color:#475569}
.dashboard-order-status .table>tbody>tr>td:first-child{display:none}
.dashboard-order-status .table>tbody>tr>td:last-child{margin-left:auto}
.dashboard-order-status .table>tbody>tr>td[colspan]{width:100%;text-align:center}
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
$dashboardOrdersPerStatus = 5;
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


<div class="dashboard-record-grid" aria-label="Dashboard summaries">
<div class="col-md-12 noPadding dashboard-record-column">
<details class="dashboard-order-status" style="--status-color:#ffffb3" open>
<summary><span class="dashboard-status-dot" aria-hidden="true"></span><span class="dashboard-status-name">Orders Arrival ETA</span><span class="dashboard-status-count"><?php $arrivalCountResult=mysqli_query($conn,"SELECT COUNT(*) AS total FROM orders WHERE DATE(eta)>='".$dtAry[0]."' AND statusID='8'"); $arrivalCountRow=$arrivalCountResult?mysqli_fetch_assoc($arrivalCountResult):array('total'=>0); echo (int)$arrivalCountRow['total']; ?></span></summary>
<div class="status-orders-link"><span>Latest <?=min(5,(int)$arrivalCountRow['total']);?> of <?=(int)$arrivalCountRow['total'];?></span><a href="<?=$siteUrl;?>orders.php?sID=8">View all <span aria-hidden="true">→</span></a></div>
<ul class="dashboard-order-list">
<?php
$sqlOrdr = mysqli_query($conn,"SELECT o.documentOrderNo,o.billNo,o.latestDateShipment,o.expiryDate,o.documentDate,o.orderTransport,o.eta,o.dta,o.id,o.invoiceNo,o.customerID,p.prodName,o.statusID FROM orders as o,products as p WHERE DATE(o.eta)>='".$dtAry[0]."' and o.statusID='8' and p.id=o.productID ORDER BY COALESCE(o.modifiedDate,o.createdDate) DESC,o.id DESC LIMIT 5");
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
echo '<li class="dashboard-order-item"><div class="dashboard-order-info"><div class="dashboard-order-title"><a href="orders.php?action=view&amp;id='.(int)$rows['id'].'">'.htmlspecialchars((string)$rows['invoiceNo'],ENT_QUOTES,'UTF-8').'</a>';
if (trim((string)$rows['documentOrderNo']) !== '') echo '<span class="dashboard-order-reference">Order '.htmlspecialchars((string)$rows['documentOrderNo'],ENT_QUOTES,'UTF-8').'</span>';
echo '</div><div class="dashboard-order-product"><span>'.htmlspecialchars((string)$rows['prodName'],ENT_QUOTES,'UTF-8').'</span><span class="dashboard-order-quantity">ETA '.htmlspecialchars((string)viewDateFormat($rows['eta']),ENT_QUOTES,'UTF-8').' · Transit '.$getRDStg.'</span></div>';
echo '<div class="dashboard-order-parties"><span><b>Customer</b> '.htmlspecialchars((string)customerName($rows['customerID']),ENT_QUOTES,'UTF-8').'</span><span><b>Status</b> '.htmlspecialchars((string)statusNm($rows['statusID']),ENT_QUOTES,'UTF-8').'</span></div>';
if ($msgTxt !== '') echo '<div class="dashboard-order-date">Missing: '.htmlspecialchars(trim(strip_tags(str_replace('</p>',' · ',$msgTxt))),ENT_QUOTES,'UTF-8').'</div>';
echo '</div><a class="dashboard-order-button dashboard-order-view" href="orders.php?action=view&amp;id='.(int)$rows['id'].'">View <span aria-hidden="true">→</span></a></li>';
}
}else{
echo '<li class="dashboard-order-item"><div class="dashboard-order-info"><span class="text-muted">No ETA orders found.</span></div></li>';
}
?>
</ul>
</details>
</div>

<div class="col-md-12 noPadding dashboard-record-column">
<details class="dashboard-order-status" style="--status-color:#dbeafe" open>
<summary><span class="dashboard-status-dot" aria-hidden="true"></span><span class="dashboard-status-name">Orders Queue</span><span class="dashboard-status-count"><?php $queueCountResult=mysqli_query($conn,"SELECT COUNT(*) AS total FROM orders WHERE CAST(statusID AS UNSIGNED) BETWEEN 5 AND 18"); $queueCountRow=$queueCountResult?mysqli_fetch_assoc($queueCountResult):array('total'=>0); echo (int)$queueCountRow['total']; ?></span></summary>
<div class="status-orders-link"><span>Latest <?=min(5,(int)$queueCountRow['total']);?> of <?=(int)$queueCountRow['total'];?></span><a href="<?=$siteUrl;?>orders.php">View all <span aria-hidden="true">→</span></a></div>
<ul class="dashboard-order-list">
<?php
$sqlOrdr = mysqli_query($conn,"SELECT o.id,o.invoiceNo,o.vendorID,o.customerID,p.prodName,o.createdDate,o.modifiedDate,o.statusID FROM orders as o,products as p WHERE p.id=o.productID AND CAST(o.statusID AS UNSIGNED) BETWEEN 5 AND 18 ORDER BY COALESCE(o.modifiedDate,o.createdDate) DESC,o.id DESC LIMIT 5");
$numRowsOrdr = mysqli_num_rows($sqlOrdr);
if($numRowsOrdr>0){
$sno=0;
while($rows = mysqli_fetch_assoc($sqlOrdr)){
$sno++;
echo '<li class="dashboard-order-item"><div class="dashboard-order-info"><div class="dashboard-order-title"><a href="orders.php?action=view&amp;id='.(int)$rows['id'].'">'.htmlspecialchars((string)$rows['invoiceNo'],ENT_QUOTES,'UTF-8').'</a></div>';
echo '<div class="dashboard-order-product"><span>'.htmlspecialchars((string)$rows['prodName'],ENT_QUOTES,'UTF-8').'</span></div>';
echo '<div class="dashboard-order-parties"><span><b>Vendor</b> '.htmlspecialchars((string)customerName($rows['vendorID']),ENT_QUOTES,'UTF-8').'</span><span><b>Customer</b> '.htmlspecialchars((string)customerName($rows['customerID']),ENT_QUOTES,'UTF-8').'</span></div>';
echo '<div class="dashboard-order-date">'.htmlspecialchars((string)statusNm($rows['statusID']),ENT_QUOTES,'UTF-8').' · Updated '.htmlspecialchars((string)viewDtTm(!empty($rows['modifiedDate']) ? $rows['modifiedDate'] : $rows['createdDate']),ENT_QUOTES,'UTF-8').'</div></div>';
echo '<a class="dashboard-order-button dashboard-order-view" href="orders.php?action=view&amp;id='.(int)$rows['id'].'">View <span aria-hidden="true">→</span></a></li>';
}
}else{
echo '<li class="dashboard-order-item"><div class="dashboard-order-info"><span class="text-muted">No queued orders found.</span></div></li>';
}
?>
</ul>
</details>
</div>

<div class="col-md-12 noPadding dashboard-record-column">
<details class="dashboard-order-status" style="--status-color:#e2e8f0" open>
<summary><span class="dashboard-status-dot" aria-hidden="true"></span><span class="dashboard-status-name">Latest Tasks</span><span class="dashboard-status-count"><?php $taskCountResult=mysqli_query($conn,"SELECT COUNT(*) AS total FROM tasks t JOIN orders o ON o.id=t.orderID"); $taskCountRow=$taskCountResult?mysqli_fetch_assoc($taskCountResult):array('total'=>0); echo (int)$taskCountRow['total']; ?></span></summary>
<div class="status-orders-link"><span>Latest <?=min(5,(int)$taskCountRow['total']);?> of <?=(int)$taskCountRow['total'];?></span><a href="<?=$siteUrl;?>tasks.php">View all <span aria-hidden="true">→</span></a></div>
<ul class="dashboard-order-list">
<?php
$sqlOrdr = mysqli_query($conn,"SELECT t.id,t.subject,t.dueDate,t.repeatDate,t.priorityID,t.assignTo,t.createdDate,t.modifiedDate,o.customerID FROM tasks as t,orders o where o.id=t.orderID ORDER BY COALESCE(t.modifiedDate,t.createdDate) DESC,t.id DESC LIMIT 5");
$numRowsOrdr = mysqli_num_rows($sqlOrdr);
if($numRowsOrdr>0){
$sno=0;
while($rows = mysqli_fetch_assoc($sqlOrdr)){
$sno++;
echo '<li class="dashboard-order-item"><div class="dashboard-order-info"><div class="dashboard-order-title"><a href="tasks.php?action=view&amp;id='.(int)$rows['id'].'">'.htmlspecialchars((string)stringTxtLimit($rows['subject'],50),ENT_QUOTES,'UTF-8').'</a></div>';
$assignToAry = @explode(",",$rows['assignTo']);
$asignToStr = array();
if(is_array($assignToAry)){
foreach($assignToAry as $assignToVal){
if($assignToVal!=""){
$asignToStr[] = customerName($assignToVal);
}
}
 $taskAssignLabel = @implode(", ",$asignToStr);
}
echo '<div class="dashboard-order-product"><span>'.htmlspecialchars((string)customerName($rows['customerID']),ENT_QUOTES,'UTF-8').'</span></div><div class="dashboard-order-parties"><span><b>Due</b> '.htmlspecialchars((string)viewDateFormat($rows['dueDate']),ENT_QUOTES,'UTF-8').'</span>';
if (!empty($rows['repeatDate']) && $rows['repeatDate'] !== '0000-00-00') echo '<span><b>Repeat</b> '.htmlspecialchars((string)viewDateFormat($rows['repeatDate']),ENT_QUOTES,'UTF-8').'</span>';
echo '</div><div class="dashboard-order-date">Assigned to '.htmlspecialchars((string)($taskAssignLabel ?? ''),ENT_QUOTES,'UTF-8').' · Updated '.htmlspecialchars((string)viewDtTm(!empty($rows['modifiedDate']) ? $rows['modifiedDate'] : $rows['createdDate']),ENT_QUOTES,'UTF-8').'</div></div>';
echo '<a class="dashboard-order-button dashboard-order-view" href="tasks.php?action=view&amp;id='.(int)$rows['id'].'">View <span aria-hidden="true">→</span></a></li>';
}
}else{
echo '<li class="dashboard-order-item"><div class="dashboard-order-info"><span class="text-muted">No recent tasks found.</span></div></li>';
}
?>
</ul>
</details>
</div>




<div class="col-md-12 noPadding dashboard-record-column">
<?php
$leadCountResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders WHERE statusID='1'");
$leadCountRow = $leadCountResult ? mysqli_fetch_assoc($leadCountResult) : array('total' => 0);
?>
<details class="dashboard-order-status" style="--status-color:#e9d5ff" open>
<summary><span class="dashboard-status-dot" aria-hidden="true"></span><span class="dashboard-status-name">Order by Leads</span><span class="dashboard-status-count"><?=(int)$leadCountRow['total'];?></span></summary>
<div class="status-orders-link"><span>Latest <?=min(5,(int)$leadCountRow['total']);?> of <?=(int)$leadCountRow['total'];?></span><a href="<?=$siteUrl;?>leads.php">View all <span aria-hidden="true">→</span></a></div>
<ul class="dashboard-order-list">
<?php
$sqlSrch = '';
$sqlOrdr = mysqli_query($conn,"SELECT o.dta,o.eta,o.newETA,o.id,o.invoiceNo,o.vendorID,o.customerID,p.prodName,o.createdDate,o.modifiedDate,o.statusID FROM orders as o,products as p WHERE p.id=o.productID AND o.statusID='1' ".$sqlSrch." ORDER BY COALESCE(o.modifiedDate,o.createdDate) DESC,o.id DESC LIMIT 5");
$numRowsOrdr = $sqlOrdr ? mysqli_num_rows($sqlOrdr) : 0;
if($numRowsOrdr>0){
$sno=0;
while($rows = mysqli_fetch_assoc($sqlOrdr)){
$sno++;
echo '<li class="dashboard-order-item"><div class="dashboard-order-info"><div class="dashboard-order-title"><a href="orders.php?action=view&amp;id='.(int)$rows['id'].'">'.htmlspecialchars((string)$rows['invoiceNo'],ENT_QUOTES,'UTF-8').'</a></div>';
echo '<div class="dashboard-order-product"><span>'.htmlspecialchars((string)$rows['prodName'],ENT_QUOTES,'UTF-8').'</span></div>';
echo '<div class="dashboard-order-parties"><span><b>Vendor</b> '.htmlspecialchars((string)customerName($rows['vendorID']),ENT_QUOTES,'UTF-8').'</span><span><b>Customer</b> '.htmlspecialchars((string)customerName($rows['customerID']),ENT_QUOTES,'UTF-8').'</span></div>';
echo '<div class="dashboard-order-date">Updated '.htmlspecialchars((string)viewDtTm(!empty($rows['modifiedDate']) ? $rows['modifiedDate'] : $rows['createdDate']),ENT_QUOTES,'UTF-8').'</div></div>';
echo '<a class="dashboard-order-button dashboard-order-view" href="orders.php?action=view&amp;id='.(int)$rows['id'].'">View <span aria-hidden="true">→</span></a></li>';
}
}else{
echo '<li class="dashboard-order-item"><div class="dashboard-order-info"><span class="text-muted">No lead orders found.</span></div></li>';
}
?>
 </ul></details>
</div>



</section>
</div>
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
