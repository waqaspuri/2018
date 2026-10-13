<?php
require_once 'include/sessionAdmin.php';
require_once 'include/configure.php';
require_once __DIR__ . '/../php/customer_reorders.php';

function reorderEscape($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$history = isset($_GET['history']) && is_string($_GET['history']) ? $_GET['history'] : '2';
if (!in_array($history, array('2', '3', '5', 'all'), true)) $history = '2';
$horizon = isset($_GET['horizon']) ? (int)$_GET['horizon'] : 30;
if (!in_array($horizon, array(30, 60, 90), true)) $horizon = 30;
$search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$productID = max(0, (int)($_GET['product'] ?? 0));
$customerID = max(0, (int)($_GET['customer'] ?? 0));
$timingLabels = array('due' => 'Due / overdue', 'soon' => 'Due soon', 'later' => 'Later', 'dormant' => 'Long overdue', 'insufficient' => 'Insufficient history');
$timing = isset($_GET['timing']) && is_string($_GET['timing']) ? $_GET['timing'] : '';
if (!isset($timingLabels[$timing])) $timing = '';
$today = new DateTimeImmutable(date('Y-m-d'));
$from = $history === 'all' ? '2000-01-01' : $today->modify('-' . $history . ' years')->format('Y-m-d');
$statuses = array();
$statusResult = mysqli_query($conn, 'SELECT id,status FROM status ORDER BY id');
while ($statusResult && ($status = mysqli_fetch_assoc($statusResult))) {
    $statuses[(int)$status['id']] = $status['status'];
}
$statusFilterApplied = isset($_GET['status_filter']) || array_key_exists('statuses', $_GET);
$selectedStatuses = array();
if ($statusFilterApplied) {
    $submittedStatuses = isset($_GET['statuses']) && is_array($_GET['statuses']) ? $_GET['statuses'] : array();
    foreach ($submittedStatuses as $statusID) {
        if (!is_scalar($statusID) || !ctype_digit((string)$statusID)) continue;
        $statusID = (int)$statusID;
        if (isset($statuses[$statusID])) $selectedStatuses[$statusID] = $statusID;
    }
    $selectedStatuses = array_values($selectedStatuses);
} else {
    // Preserve the previous default; Cancelled can now be explicitly selected.
    foreach ($statuses as $statusID => $statusName) {
        if ($statusID !== 4) $selectedStatuses[] = $statusID;
    }
}
$statusWhere = $selectedStatuses ? 'o.statusID IN (' . implode(',', $selectedStatuses) . ')' : '1=0';
$dateSql = "CASE WHEN o.documentDate >= '2000-01-01' THEN DATE(o.documentDate) ELSE DATE(o.createdDate) END";
$where = "$statusWhere AND ($dateSql) >= '$from' AND ($dateSql) <= '" . $today->format('Y-m-d') . "'";
if ($productID) $where .= ' AND o.productID=' . $productID;
if ($customerID) $where .= ' AND o.customerID=' . $customerID;
$groups = array();
$error = false;
try {
    $purchases = mysqli_query($conn, "SELECT o.id,o.customerID,o.productID,c.companyName,c.applicantName,p.prodName,s.status AS orderStatus,$dateSql AS purchaseDate
        FROM orders o JOIN customers c ON c.id=o.customerID JOIN products p ON p.id=o.productID
        LEFT JOIN status s ON s.id=o.statusID
        WHERE $where ORDER BY purchaseDate,o.id");
    if (!$purchases) throw new RuntimeException('Purchase history unavailable');
    while ($purchase = mysqli_fetch_assoc($purchases)) {
        $key = $purchase['customerID'] . ':' . $purchase['productID'];
        if (!isset($groups[$key])) $groups[$key] = array('dates' => array(), 'orders' => 0);
        $groups[$key]['dates'][] = $purchase['purchaseDate'];
        $groups[$key]['orders']++;
        $groups[$key]['order'] = $purchase;
    }
} catch (Exception $exception) {
    error_log('Customer reorder forecast: ' . $exception->getMessage());
    $error = true;
}
$rows = array();
foreach ($groups as $group) {
    $order = $group['order'];
    if ($search !== '' && stripos($order['companyName'] . ' ' . $order['applicantName'] . ' ' . $order['prodName'], $search) === false) continue;
    $estimate = customerReorderEstimate($group['dates'], $today, $horizon);
    if (!$estimate['purchases'] || ($timing !== '' && $estimate['timing'] !== $timing)) continue;
    $rows[] = array_merge($order, $estimate, array('orderCount' => $group['orders']));
}
usort($rows, function ($a, $b) {
    return ($a['rank'] <=> $b['rank']) ?: (($a['daysUntil'] ?? PHP_INT_MAX) <=> ($b['daysUntil'] ?? PHP_INT_MAX)) ?: strcmp($a['companyName'], $b['companyName']) ?: ($a['productID'] <=> $b['productID']);
});
$total = count($rows);
$pages = max(1, (int)ceil($total / 25));
$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$visible = array_slice($rows, ($page - 1) * 25, 25);
$products = mysqli_query($conn, 'SELECT id,prodName FROM products ORDER BY prodName');
$customers = mysqli_query($conn, "SELECT DISTINCT c.id,c.companyName FROM customers c JOIN orders o ON o.customerID=c.id ORDER BY c.companyName");
$filters = array('history' => $history, 'horizon' => $horizon, 'q' => $search, 'product' => $productID, 'customer' => $customerID, 'timing' => $timing, 'status_filter' => 1, 'statuses' => $selectedStatuses);
include 'include/header.php';
?>
<section role="main" class="content-body">
<header class="page-header"><h2>Customer Reorders</h2></header>
<section class="panel"><div class="panel-body">
<p>Possible next purchases by customer and product. Due and upcoming reorders appear first.</p>
<form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-bottom:18px">
<input type="hidden" name="status_filter" value="1">
<div><label for="reorder-statuses">Order statuses</label>
<select class="form-control" id="reorder-statuses" name="statuses[]" multiple size="6" aria-describedby="reorder-status-help" style="min-width:240px">
<?php foreach ($statuses as $statusID => $statusName): ?><option value="<?=$statusID?>" <?=in_array($statusID, $selectedStatuses, true)?'selected':''?>><?=reorderEscape($statusName)?></option><?php endforeach; ?>
</select>
<small id="reorder-status-help" class="text-muted">Hold Ctrl (Windows) or Command (Mac) to select several.</small><br>
<button class="btn btn-link btn-xs" type="button" onclick="Array.prototype.forEach.call(document.getElementById('reorder-statuses').options,function(option){option.selected=true;});">Select all</button>
<button class="btn btn-link btn-xs" type="button" onclick="Array.prototype.forEach.call(document.getElementById('reorder-statuses').options,function(option){option.selected=false;});">Clear</button></div>
<label>Search<input class="form-control" name="q" value="<?=reorderEscape($search)?>" placeholder="Customer or product"></label>
<label>History<select class="form-control" name="history"><?php foreach (array('2'=>'Last 2 years','3'=>'Last 3 years','5'=>'Last 5 years','all'=>'All available history') as $value=>$label): ?><option value="<?=reorderEscape($value)?>" <?=(string)$value===$history?'selected':''?>><?=reorderEscape($label)?></option><?php endforeach; ?></select></label>
<label>Customer<select class="form-control" name="customer"><option value="0">All customers</option><?php while ($customers && ($customer=mysqli_fetch_assoc($customers))): ?><option value="<?=(int)$customer['id']?>" <?=$customerID===(int)$customer['id']?'selected':''?>><?=reorderEscape($customer['companyName'])?></option><?php endwhile; ?></select></label>
<label>Product<select class="form-control" name="product"><option value="0">All products</option><?php while ($products && ($product=mysqli_fetch_assoc($products))): ?><option value="<?=(int)$product['id']?>" <?=$productID===(int)$product['id']?'selected':''?>><?=reorderEscape($product['prodName'])?></option><?php endwhile; ?></select></label>
<label>Upcoming window<select class="form-control" name="horizon"><?php foreach (array(30,60,90) as $days): ?><option value="<?=$days?>" <?=$horizon===$days?'selected':''?>><?=$days?> days</option><?php endforeach; ?></select></label>
<label>Timing<select class="form-control" name="timing"><option value="">All</option><?php foreach ($timingLabels as $value=>$label): ?><option value="<?=$value?>" <?=$timing===$value?'selected':''?>><?=$label?></option><?php endforeach; ?></select></label>
<button class="btn btn-primary" type="submit">Generate report</button><a class="btn btn-default" href="customer_reorders.php">Reset</a>
</form>
<?php if (!$selectedStatuses): ?><div class="alert alert-info">Select at least one order status and click Generate report.</div><?php endif; ?>
<p class="text-muted"><small>Included statuses: <?=reorderEscape(implode(', ', array_intersect_key($statuses, array_flip($selectedStatuses)))) ?: 'None'?>.</small></p>
<p class="text-muted"><small>Only orders with the selected current statuses contribute to order counts, intervals and predictions. Order date uses the document date, or creation date if missing. The usual interval uses up to five recent gaps, giving more weight to recent orders. Orders on the same day count as one occasion. Confidence reflects consistency, not payment confirmation or a guaranteed repurchase. One order occasion is insufficient.</small></p>
<p class="text-muted"><small>Lookup: <?=reorderEscape($from)?> to <?=$today->format('Y-m-d')?>. Customers with less history use the available records. Refreshed each time this page opens.</small></p>
<?php if ($error): ?><div class="alert alert-danger">Purchase history could not be loaded. Please try again.</div><?php else: ?>
<p><?=number_format($total)?> customer–product matches</p>
<div class="table-responsive"><table class="table table-striped table-condensed">
<thead><tr><th>Customer</th><th>Product</th><th>Order occasions</th><th>Latest order</th><th>Usual interval</th><th>Estimated next purchase</th><th>Timing</th><th>Confidence</th></tr></thead>
<tbody><?php if (!$visible): ?><tr><td colspan="8">No orders found for the selected statuses and filters. Try other statuses or a longer history.</td></tr><?php endif; ?>
<?php foreach ($visible as $row): ?>
<tr><td><a href="customers.php?action=view&amp;id=<?=(int)$row['customerID']?>"><?=reorderEscape($row['companyName'] ?: $row['applicantName'])?></a></td>
<td><?=reorderEscape($row['prodName'])?></td><td><?=(int)$row['purchases']?> days <small class="text-muted">(<?=(int)$row['orderCount']?> orders)</small></td>
<td><a href="orders.php?action=view&amp;id=<?=(int)$row['id']?>"><?=reorderEscape($row['last'])?></a><br><small class="text-muted"><?=reorderEscape($row['orderStatus'] ?? '')?></small></td>
<td><?=reorderEscape(customerReorderIntervalLabel($row['interval']))?><?php if ($row['interval']!==null): ?><br><small class="text-muted"><?=(int)$row['interval']?> days · <?=(int)$row['gapCount']?> recent gaps</small><?php endif; ?></td><td><?=reorderEscape($row['next'] ?? 'Not enough history')?></td>
<td><span class="label label-<?=in_array($row['timing'],array('due','soon'),true)?'warning':'default'?>"><?=reorderEscape($timingLabels[$row['timing']])?></span><?php if ($row['daysUntil']!==null): ?><br><small><?=$row['daysUntil']<0?abs($row['daysUntil']).' days overdue':($row['daysUntil']===0?'Today':'In '.$row['daysUntil'].' days')?></small><?php endif; ?></td>
<td><?=reorderEscape($row['confidence'])?></td></tr>
<?php endforeach; ?></tbody></table></div>
<nav aria-label="Reorder pages">Page <?=$page?> of <?=$pages?> &nbsp;
<?php if ($page>1): ?><a class="btn btn-default btn-sm" href="?<?=reorderEscape(http_build_query(array_merge($filters,array('page'=>$page-1))))?>">Previous</a><?php endif; ?>
<?php if ($page<$pages): ?><a class="btn btn-default btn-sm" href="?<?=reorderEscape(http_build_query(array_merge($filters,array('page'=>$page+1))))?>">Next</a><?php endif; ?></nav>
<?php endif; ?></div></section></section></div></section>
<?php include 'include/footer.php'; ?>
