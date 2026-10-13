<?php
require_once "include/sessionAdmin.php";
require_once "include/configure.php";

$templateColumn = mysqli_query($conn, "SHOW COLUMNS FROM admin LIKE 'priceRequestMessage'");
if ($templateColumn && mysqli_num_rows($templateColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE admin ADD COLUMN priceRequestMessage MEDIUMTEXT NULL");
}
$defaultPriceRequestMessage = 'May I have the price for {product_name} 1 FCL on the basis of CFR Karachi, Pakistan?<br><br>Thanks<br>Waqas Chemicals';
$priceRequestTemplateResult = mysqli_query($conn, "SELECT priceRequestMessage FROM admin WHERE id='1' LIMIT 1");
$priceRequestTemplateRow = $priceRequestTemplateResult ? mysqli_fetch_assoc($priceRequestTemplateResult) : false;
$priceRequestTemplate = $priceRequestTemplateRow && trim((string)$priceRequestTemplateRow['priceRequestMessage']) !== ''
    ? $priceRequestTemplateRow['priceRequestMessage']
    : $defaultPriceRequestMessage;
$renderPriceRequestMessage = function ($item) use ($priceRequestTemplate) {
    $productNames = array_values($item['products'] ?? array());
    $productDetails = array_values($item['product_details'] ?? array());
    $fieldList = function ($field) use ($productDetails) {
        $values = array();
        foreach ($productDetails as $product) {
            if (is_array($product) && isset($product[$field]) && trim((string)$product[$field]) !== '') {
                $values[] = trim((string)$product[$field]);
            }
        }
        return implode(', ', $values);
    };
    $mergeValues = array();
    $mergeValues['{product_name}'] = htmlspecialchars(implode(', ', array_map(function ($product) {
        return is_array($product) ? ($product['prodName'] ?? '') : $product;
    }, $productNames)), ENT_QUOTES, 'UTF-8');
    $mergeValues['{product_packaging}'] = htmlspecialchars($fieldList('prodPackaging'), ENT_QUOTES, 'UTF-8');
    $mergeValues['{product_quantity}'] = htmlspecialchars($fieldList('prodQty'), ENT_QUOTES, 'UTF-8');
    $mergeValues['{product_qty}'] = $mergeValues['{product_quantity}'];
    $mergeValues['{product_unit}'] = htmlspecialchars($fieldList('prodUMO'), ENT_QUOTES, 'UTF-8');
    $mergeValues['{product_origin}'] = htmlspecialchars($fieldList('prodOrigin'), ENT_QUOTES, 'UTF-8');
    $mergeValues['{product_hs_code}'] = htmlspecialchars($fieldList('hsCode'), ENT_QUOTES, 'UTF-8');
    $mergeValues['{product_description}'] = htmlspecialchars($fieldList('prodDesc'), ENT_QUOTES, 'UTF-8');
    return strtr($priceRequestTemplate, $mergeValues);
};

if (isset($_SESSION['price_request_session_id']) && $_SESSION['price_request_session_id'] !== session_id()) {
    $_SESSION['price_request_queue'] = array();
}
$_SESSION['price_request_session_id'] = session_id();
if (!isset($_SESSION['price_request_queue']) || !is_array($_SESSION['price_request_queue'])) {
    $_SESSION['price_request_queue'] = array();
}

$queue = &$_SESSION['price_request_queue'];
$notice = '';
$error = '';
$productSearch = trim((string)($_GET['product_search'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['queue_action'] ?? '';

    if ($action === 'add') {
        $productIDs = array_values(array_unique(array_map('intval', $_POST['product_ids'] ?? array())));

        if (!$productIDs) {
            $error = 'Select at least one product.';
        } else {
            $ids = implode(',', $productIDs);
            $productsSQL = mysqli_query($conn, "SELECT p.id,p.prodName,p.prodQty,p.prodUMO,p.prodOrigin,p.prodPackaging,p.hsCode,p.prodDesc,c.id AS vendor_id,c.companyName,c.email,c.email2 FROM products AS p LEFT JOIN customers AS c ON c.id=p.assignToVendor AND c.type='2' WHERE p.id IN (" . $ids . ") ORDER BY p.prodName ASC");
            $added = 0;
            while ($productsSQL && $product = mysqli_fetch_assoc($productsSQL)) {
                $vendorID = (int)$product['vendor_id'];
                if (!$vendorID || (!$product['email'] && !$product['email2'])) {
                    continue;
                }
                if (!isset($queue[$vendorID])) {
                    $queue[$vendorID] = array(
                        'vendor_id' => $vendorID,
                        'vendor_name' => $product['companyName'],
                        'emails' => array_values(array_filter(array($product['email'], $product['email2']))),
                        'products' => array(),
                        'product_details' => array()
                    );
                }
                $queue[$vendorID]['products'][(int)$product['id']] = $product['prodName'];
                $queue[$vendorID]['product_details'][(int)$product['id']] = array(
                    'prodName' => $product['prodName'],
                    'prodQty' => $product['prodQty'],
                    'prodUMO' => $product['prodUMO'],
                    'prodOrigin' => $product['prodOrigin'],
                    'prodPackaging' => $product['prodPackaging'],
                    'hsCode' => $product['hsCode'],
                    'prodDesc' => $product['prodDesc']
                );
                $added++;
            }
            if ($added) {
                $notice = 'Selected products added to the review queue.';
            } else {
                $error = 'The selected products do not have a vendor email address.';
            }
        }
    } elseif ($action === 'remove') {
        unset($queue[(int)($_POST['vendor_id'] ?? 0)]);
        $notice = 'Queue item removed.';
    } elseif ($action === 'clear') {
        $queue = array();
        $notice = 'Queue cleared.';
    } elseif ($action === 'create_tasks') {
        if (!$queue) {
            $error = 'The queue is empty.';
        } else {
            $prioritySQL = mysqli_query($conn, "SELECT id FROM task_priority ORDER BY id ASC LIMIT 1");
            $priorityRow = $prioritySQL ? mysqli_fetch_assoc($prioritySQL) : false;
            $priorityID = $priorityRow ? (int)$priorityRow['id'] : 0;
            $created = 0;

            foreach ($queue as $item) {
                $productIDs = array_map('intval', array_keys($item['products']));
                $productNames = array_values($item['products']);
                $productList = implode(', ', $productNames);
                $subject = mysqli_real_escape_string($conn, $productList);
                $message = mysqli_real_escape_string($conn, $renderPriceRequestMessage($item));
                $orderID = 0;
                $today = date('Y-m-d');

                mysqli_query($conn, "INSERT INTO tasks (orderID,currentStatusID,subject,dueDate,priorityID,assignTo,isSecondReminder,taskMessage,taskSMS,taskSMSID,taskMessageID,repeatDate,sendBy,isExpired,createdDate) VALUES
                ('" . $orderID . "','0','" . $subject . "','" . $today . "','" . $priorityID . "','" . (int)$item['vendor_id'] . "','0','" . $message . "','','0','0','1970-01-01','mail','0','" . currDateTimeGmt() . "')");
                if (mysqli_insert_id($conn) > 0) {
                    $created++;
                }
            }
            $queue = array();
            header("Location: tasks.php?act=1");
            exit;
        }
    }
}

$esc = function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$productSearchSQL = mysqli_real_escape_string($conn, $productSearch);
$productsSQL = mysqli_query($conn, "SELECT p.id,p.prodName,p.prodQty,p.prodUMO,p.prodOrigin,p.prodPackaging,p.hsCode,p.prodDesc,c.id AS vendor_id,c.companyName,c.email,c.email2 FROM products AS p LEFT JOIN customers AS c ON c.id=p.assignToVendor AND c.type='2' WHERE p.prodName LIKE '%" . $productSearchSQL . "%' ORDER BY p.prodName ASC");
include "include/header.php";
?>
<section role="main" class="content-body">
<header class="page-header">
    <h2>Price Requests</h2>
    <div class="right-wrapper pull-right">
        <ol class="breadcrumbs"><li><a href="<?=$siteUrl;?>"><i class="fa fa-home"></i></a></li><li><span>Price Requests</span></li></ol>
    </div>
</header>
<div class="row"><div class="col-lg-12"><section class="panel">
<header class="panel-heading"><h2 class="panel-title">Request Vendor Prices</h2></header>
<div class="panel-body">
<?php if ($notice): ?><div class="alert alert-success"><?=$esc($notice)?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?=$esc($error)?></div><?php endif; ?>

<form method="get" class="form-horizontal form-bordered">
    <div class="form-group">
        <label class="col-md-2 control-label">Products</label>
        <div class="col-md-7">
            <input type="search" class="form-control" name="product_search" value="<?=$esc($productSearch)?>" placeholder="Search product name">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary" type="submit">Search</button>
            <a class="btn btn-default" href="price_requests.php">Clear</a>
        </div>
    </div>
</form>

<form method="post" class="form-horizontal form-bordered">
    <input type="hidden" name="queue_action" value="add">
    <div class="form-group">
        <label class="col-md-2 control-label">Products</label>
        <div class="col-md-9">
            <?php $hasProducts = false; while ($product = $productsSQL ? mysqli_fetch_assoc($productsSQL) : false): $hasProducts = true; ?>
                <label style="display:block;margin:7px 0;">
                    <input type="checkbox" name="product_ids[]" value="<?=$esc($product['id'])?>" <?=$product['vendor_id'] && ($product['email'] || $product['email2']) ? '' : 'disabled'?>>
                    <?=$esc($product['prodName'])?>
                    <span class="text-muted">— <?=$esc($product['companyName'] ?: 'No vendor/email assigned')?></span>
                </label>
            <?php endwhile; ?>
            <?php if (!$hasProducts): ?><span class="text-muted">No products found.</span><?php endif; ?>
        </div>
    </div>
    <?php if ($hasProducts): ?><div class="form-group"><div class="col-md-offset-2 col-md-9"><button class="btn btn-primary" type="submit">Add Selected Products to Review Queue</button></div></div><?php endif; ?>
</form>

<hr>
<h4>Review Queue</h4>
<?php if ($queue): ?>
<table class="table table-bordered table-striped">
    <thead><tr><th>Products</th><th>Vendor</th><th>Email to</th><th>Subject</th><th>Message</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($queue as $item): ?>
    <tr>
        <td><?=$esc(implode(', ', $item['products']))?></td>
        <td><?=$esc($item['vendor_name'])?></td>
        <td><?=$esc(implode(', ', $item['emails']))?></td>
        <td><?=$esc(implode(', ', $item['products']))?></td>
        <td><?=$renderPriceRequestMessage($item)?></td>
        <td><form method="post"><input type="hidden" name="queue_action" value="remove"><input type="hidden" name="vendor_id" value="<?=$esc($item['vendor_id'])?>"><button class="btn btn-default btn-sm" type="submit">Remove</button></form></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<form method="post" style="display:inline;"><input type="hidden" name="queue_action" value="create_tasks"><button class="btn btn-danger" type="submit">Create Task(s)</button></form>
<form method="post" style="display:inline;margin-left:8px;"><input type="hidden" name="queue_action" value="clear"><button class="btn btn-default" type="submit">Clear Queue</button></form>
<?php else: ?>
<p class="text-muted">No vendors or products are waiting for review. This queue is stored only in the current session.</p>
<?php endif; ?>
</div></section></div></div>
</section>
</div>
</section>
