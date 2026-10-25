<?php
require_once 'include/sessionAdmin.php';
require_once 'include/configure.php';
require_once __DIR__ . '/../php/customer_reorders.php';

function reorderEscape($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

$historyOptions = array('2' => 'Last 2 years', '3' => 'Last 3 years', '5' => 'Last 5 years', 'all' => 'All available history');
$history = isset($_GET['history']) && is_string($_GET['history']) && isset($historyOptions[$_GET['history']]) ? $_GET['history'] : '2';
$sort = isset($_GET['sort']) && is_string($_GET['sort']) && $_GET['sort'] === 'oldest' ? 'oldest' : 'newest';
$search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$productID = max(0, (int)($_GET['product'] ?? 0));
$customerID = max(0, (int)($_GET['customer'] ?? 0));
$today = new DateTimeImmutable(date('Y-m-d'));
$from = $history === 'all' ? '2000-01-01' : $today->modify('-' . (int)$history . ' years')->format('Y-m-d');

$statuses = array();
$statusResult = mysqli_query($conn, 'SELECT id,status FROM status ORDER BY id');
while ($statusResult && ($status = mysqli_fetch_assoc($statusResult))) $statuses[(int)$status['id']] = $status['status'];
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
    foreach ($statuses as $statusID => $statusName) if ($statusID !== 4) $selectedStatuses[] = $statusID;
}

$whereParts = array($selectedStatuses ? 'o.statusID IN (' . implode(',', $selectedStatuses) . ')' : '1=0');
$dateSql = customerReorderTimelineDateSql();
$whereParts[] = "($dateSql) >= '$from' AND ($dateSql) <= '" . $today->format('Y-m-d') . "'";
if ($productID) $whereParts[] = 'o.productID=' . $productID;
if ($customerID) $whereParts[] = 'o.customerID=' . $customerID;
if ($search !== '') {
    $searchSql = mysqli_real_escape_string($conn, $search);
    $whereParts[] = "(c.companyName LIKE '%$searchSql%' OR c.applicantName LIKE '%$searchSql%' OR p.prodName LIKE '%$searchSql%')";
}
$where = implode(' AND ', $whereParts);
$orderBy = $sort === 'oldest' ? 'activityDate ASC, o.id ASC' : 'activityDate DESC, o.id DESC';
$pageSize = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$error = false;
$total = 0;
$orders = array();
try {
    $countResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders o JOIN customers c ON c.id=o.customerID JOIN products p ON p.id=o.productID WHERE $where");
    if (!$countResult) throw new RuntimeException('Order count unavailable');
    $countRow = mysqli_fetch_assoc($countResult);
    $total = (int)$countRow['total'];
    $pages = max(1, (int)ceil($total / $pageSize));
    $page = min($page, $pages);
    $offset = ($page - 1) * $pageSize;
    $result = mysqli_query($conn, "SELECT o.id,o.customerID,o.productID,c.companyName,c.applicantName,p.prodName,s.status AS orderStatus,$dateSql AS activityDate
        FROM orders o JOIN customers c ON c.id=o.customerID JOIN products p ON p.id=o.productID
        LEFT JOIN status s ON s.id=o.statusID WHERE $where ORDER BY $orderBy LIMIT $offset,$pageSize");
    if (!$result) throw new RuntimeException('Order history unavailable');
    while ($row = mysqli_fetch_assoc($result)) $orders[] = $row;
} catch (Exception $exception) {
    error_log('Customer order timeline: ' . $exception->getMessage());
    $error = true;
    $pages = 1;
}
$products = mysqli_query($conn, 'SELECT id,prodName FROM products ORDER BY prodName');
$customers = mysqli_query($conn, 'SELECT id,companyName,applicantName FROM customers ORDER BY companyName,applicantName');
$filters = array('history' => $history, 'sort' => $sort, 'q' => $search, 'product' => $productID, 'customer' => $customerID, 'status_filter' => 1, 'statuses' => $selectedStatuses);
include 'include/header.php';
?>
<section role="main" class="content-body">
<header class="page-header"><h2>Customer Order Timeline</h2></header>
<section class="panel"><div class="panel-body">
<style>
.order-timeline{list-style:none;margin:24px 0 30px;padding:0 0 0 18px;border-left:2px solid #d9dfe5}
.order-timeline-item{position:relative;margin:0 0 20px;padding:0 0 0 20px}
.order-timeline-item:last-child{margin-bottom:0}
.order-timeline-item:before{content:"";position:absolute;left:-26px;top:28px;width:12px;height:12px;border:3px solid #fff;border-radius:50%;background:#428bca;box-shadow:0 0 0 1px #428bca}
.order-timeline-date{margin:0 0 6px;color:#687783;font-size:12px;font-weight:600}
.order-timeline-card{padding:12px 15px;border:1px solid #e1e5e8;border-radius:4px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.order-timeline-heading{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:8px}
.order-timeline-product{margin:0 0 5px;font-weight:600;overflow-wrap:anywhere}
.order-timeline-customer{margin:0;color:#687783;overflow-wrap:anywhere}
html.dark .order-timeline-card{background:#282d36;border-color:#21262d}
html.dark .order-timeline-date,html.dark .order-timeline-customer{color:#b8bec8}
.timeline-filters{margin:0 0 18px}
.timeline-filters .form-group{margin:0 0 15px}
.timeline-filters label{display:block;margin-bottom:5px}
.timeline-filters .form-control{width:100%}
.timeline-filter-actions{display:flex;justify-content:flex-end;gap:8px;padding:0}
@media(max-width:600px){.order-timeline{margin-left:2px;padding-left:14px}.order-timeline-item{padding-left:12px}.order-timeline-item:before{left:-20px}}
</style>
<p>Review the actual order history for a product or customer. Each timeline item is one order; no reorder prediction is applied.</p>
<form method="get" class="timeline-filters">
<input type="hidden" name="status_filter" value="1">
<div class="row">
<div class="col-md-4 col-sm-6"><div class="form-group"><label for="timeline-product">Product</label><select class="form-control" id="timeline-product" name="product"><option value="0">All products</option><?php while ($products && ($product=mysqli_fetch_assoc($products))): ?><option value="<?=(int)$product['id']?>" <?=$productID===(int)$product['id']?'selected':''?>><?=reorderEscape($product['prodName'])?></option><?php endwhile; ?></select></div></div>
<div class="col-md-4 col-sm-6"><div class="form-group"><label for="timeline-customer">Customer</label><select class="form-control" id="timeline-customer" name="customer"><option value="0">All customers</option><?php while ($customers && ($customer=mysqli_fetch_assoc($customers))): ?><option value="<?=(int)$customer['id']?>" <?=$customerID===(int)$customer['id']?'selected':''?>><?=reorderEscape($customer['companyName'] ?: $customer['applicantName'])?></option><?php endwhile; ?></select></div></div>
<div class="col-md-4 col-sm-6"><div class="form-group"><label for="timeline-history">Date range</label><select class="form-control" id="timeline-history" name="history"><?php foreach ($historyOptions as $value=>$label): ?><option value="<?=reorderEscape($value)?>" <?=$history===$value?'selected':''?>><?=reorderEscape($label)?></option><?php endforeach; ?></select></div></div>
<div class="col-md-4 col-sm-6"><div class="form-group"><label for="timeline-sort">Sort</label><select class="form-control" id="timeline-sort" name="sort"><option value="newest" <?=$sort==='newest'?'selected':''?>>Newest first</option><option value="oldest" <?=$sort==='oldest'?'selected':''?>>Oldest first</option></select></div></div>
<div class="col-md-4 col-sm-6"><div class="form-group"><label for="timeline-search">Search</label><input class="form-control" id="timeline-search" name="q" value="<?=reorderEscape($search)?>" placeholder="Customer or product"></div></div>
<div class="col-md-4 col-sm-6"><div class="form-group"><label for="timeline-statuses">Order status <small class="text-muted">(use Ctrl/Cmd to select multiple)</small></label><select class="form-control" id="timeline-statuses" name="statuses[]" multiple size="4"><?php foreach ($statuses as $statusID=>$statusName): ?><option value="<?=$statusID?>" <?=in_array($statusID,$selectedStatuses,true)?'selected':''?>><?=reorderEscape($statusName)?></option><?php endforeach; ?></select></div></div>
</div>
<div class="timeline-filter-actions"><button class="btn btn-primary" type="submit">Show timeline</button><a class="btn btn-default" href="customer_reorders.php">Reset</a></div>
</form>
<p class="text-muted"><small>Dates use the order's last modified date, then document date, then creation date. Orders edited later may therefore appear later in the timeline.</small></p>
<?php if (!$selectedStatuses): ?><div class="alert alert-info">Select at least one order status to show orders.</div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger">Order history could not be loaded. Please try again.</div><?php else: ?>
<p><strong><?=number_format($total)?></strong> orders found<?php if ($productID): ?> for the selected product<?php endif; ?>.</p>
<?php if (!$orders): ?><div class="alert alert-info">No orders match these filters. Try another product, status, or date range.</div><?php else: ?>
<ol class="order-timeline">
<?php foreach ($orders as $row): ?>
<li class="order-timeline-item"><div class="order-timeline-date"><?=reorderEscape($row['activityDate'])?></div>
<div class="order-timeline-card"><div class="order-timeline-heading"><strong><a href="orders.php?action=view&amp;id=<?=(int)$row['id']?>">Order #<?=(int)$row['id']?></a></strong> <span class="label label-default"><?=reorderEscape($row['orderStatus'] ?? 'Unknown status')?></span></div>
<p class="order-timeline-product"><?=reorderEscape($row['prodName'])?></p><p class="order-timeline-customer"><i class="fa fa-user"></i> <a href="customers.php?action=view&amp;id=<?=(int)$row['customerID']?>"><?=reorderEscape($row['companyName'] ?: $row['applicantName'])?></a></p></div></li>
<?php endforeach; ?>
</ol>
<nav aria-label="Timeline pages">Page <?=$page?> of <?=$pages?> &nbsp;<?php if ($page>1): ?><a class="btn btn-default btn-sm" href="?<?=reorderEscape(http_build_query(array_merge($filters,array('page'=>$page-1))))?>">Previous</a><?php endif; ?> <?php if ($page<$pages): ?><a class="btn btn-default btn-sm" href="?<?=reorderEscape(http_build_query(array_merge($filters,array('page'=>$page+1))))?>">Next</a><?php endif; ?></nav>
<?php endif; ?>
<?php endif; ?></div></section></section></div></section>
<?php include 'include/footer.php'; ?>
