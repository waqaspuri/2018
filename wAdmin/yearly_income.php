<?php
require_once "include/sessionAdmin.php";
require_once "include/configure.php";

$currentYear = (int)date('Y');
$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
if (!$year || $year < 2000 || $year > $currentYear + 1) $year = $currentYear;
$vendorID = filter_input(INPUT_GET, 'vendor', FILTER_VALIDATE_INT);
$productID = filter_input(INPUT_GET, 'product', FILTER_VALIDATE_INT);
$vendorID = $vendorID && $vendorID > 0 ? $vendorID : 0;
$productID = $productID && $productID > 0 ? $productID : 0;

$vendors = mysqli_query($conn, "SELECT id, companyName FROM customers WHERE type='2' ORDER BY companyName ASC");
$products = mysqli_query($conn, "SELECT id, prodName FROM products ORDER BY prodName ASC");

$where = "o.statusID='3' AND o.createdDate >= '" . $year . "-01-01' AND o.createdDate < '" . ($year + 1) . "-01-01'";
if ($vendorID) $where .= " AND o.vendorID=" . $vendorID;
if ($productID) $where .= " AND o.productID=" . $productID;
$sql = "SELECT o.id, o.createdDate, o.invoiceNo, o.settlementSupplier, o.quantity, o.orderUOM,
               v.companyName AS vendorName, c.companyName AS customerName, p.prodName
        FROM orders AS o
        LEFT JOIN customers AS v ON v.id=o.vendorID
        LEFT JOIN customers AS c ON c.id=o.customerID
        LEFT JOIN products AS p ON p.id=o.productID
        WHERE " . $where . " ORDER BY o.createdDate DESC, o.id DESC";
$ordersResult = mysqli_query($conn, $sql);
$rows = array();
$yearTotal = 0.0;
$yearQuantity = 0.0;
if ($ordersResult) {
    while ($order = mysqli_fetch_assoc($ordersResult)) {
        $rate = is_numeric($order['settlementSupplier']) ? (float)$order['settlementSupplier'] : 0.0;
        $quantity = is_numeric($order['quantity']) ? (float)$order['quantity'] : 0.0;
        $order['settlementTotal'] = $rate * $quantity;
        $yearTotal += $order['settlementTotal'];
        $yearQuantity += $quantity;
        $rows[] = $order;
    }
}
function yearlyIncomeEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

include "include/header.php";
?>
<section role="main" class="content-body">
    <header class="page-header">
        <h2>Yearly Income</h2>
        <div class="right-wrapper pull-right"><ol class="breadcrumbs"><li><a href="<?=$siteUrl?>"><i class="fa fa-home"></i></a></li><li><span>Yearly Income</span></li></ol></div>
    </header>
    <div class="row">
        <div class="col-md-4 col-sm-6">
            <section class="panel"><div class="panel-body">
                <div class="h4 text-muted">Supplier settlement total</div>
                <div class="text-primary" style="font-size:28px;font-weight:600">$<?=number_format($yearTotal, 2)?></div>
                <div class="text-muted">Completed orders in <?=yearlyIncomeEscape($year)?></div>
            </div></section>
        </div>
        <div class="col-md-4 col-sm-6">
            <section class="panel"><div class="panel-body">
                <div class="h4 text-muted">Completed accounts</div>
                <div style="font-size:28px;font-weight:600"><?=number_format(count($rows))?></div>
                <div class="text-muted">Matching the selected filters</div>
            </div></section>
        </div>
        <div class="col-md-4 col-sm-6">
            <section class="panel"><div class="panel-body">
                <div class="h4 text-muted">Total quantity</div>
                <div style="font-size:28px;font-weight:600"><?=number_format($yearQuantity, 2)?></div>
                <div class="text-muted">Combined order quantities</div>
            </div></section>
        </div>
    </div>
    <div class="row"><div class="col-lg-12"><section class="panel">
        <header class="panel-heading"><h2 class="panel-title">Filter completed accounts</h2></header>
        <div class="panel-body">
            <form method="get" class="form-inline" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin-bottom:20px">
                <div class="form-group"><label for="year">Year</label><select class="form-control" id="year" name="year">
                    <?php for ($optionYear = $currentYear; $optionYear >= 2000; $optionYear--): ?>
                        <option value="<?=$optionYear?>" <?=$year === $optionYear ? 'selected' : ''?>><?=$optionYear?><?=$optionYear === $currentYear ? ' (Current year)' : ($optionYear === $currentYear - 1 ? ' (Previous year)' : '')?></option>
                    <?php endfor; ?>
                </select></div>
                <div class="form-group"><label for="vendor">Supplier</label><select class="form-control" id="vendor" name="vendor"><option value="">All suppliers</option>
                    <?php if ($vendors): while ($vendor = mysqli_fetch_assoc($vendors)): ?><option value="<?= (int)$vendor['id']?>" <?= $vendorID === (int)$vendor['id'] ? 'selected' : ''?>><?=yearlyIncomeEscape($vendor['companyName'])?></option><?php endwhile; endif; ?>
                </select></div>
                <div class="form-group"><label for="product">Product</label><select class="form-control" id="product" name="product"><option value="">All products</option>
                    <?php if ($products): while ($product = mysqli_fetch_assoc($products)): ?><option value="<?= (int)$product['id']?>" <?= $productID === (int)$product['id'] ? 'selected' : ''?>><?=yearlyIncomeEscape($product['prodName'])?></option><?php endwhile; endif; ?>
                </select></div>
                <button class="btn btn-primary" type="submit"><i class="fa fa-filter"></i> Apply filters</button>
                <a class="btn btn-default" href="yearly_income.php">Reset</a>
            </form>
            <p class="text-muted">Year is based on the order creation date. Each total uses supplier settlement rate × order quantity. Only orders currently marked Completed are included.</p>
            <div class="table-responsive"><table class="table table-bordered table-striped table-condensed mb-none">
                <thead><tr><th>Order</th><th>Order date</th><th>Supplier</th><th>Customer</th><th>Product</th><th>Quantity</th><th>Settlement rate</th><th>Settlement total</th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="8" class="text-center">No completed accounts found for these filters.</td></tr>
                <?php else: foreach ($rows as $order): ?>
                    <tr>
                        <td><a href="orders.php?action=view&id=<?=(int)$order['id']?>">#<?=(int)$order['id']?></a><?php if ($order['invoiceNo'] !== ''): ?><br><small><?=yearlyIncomeEscape($order['invoiceNo'])?></small><?php endif; ?></td>
                        <td><?=yearlyIncomeEscape($order['createdDate'])?></td>
                        <td><?=yearlyIncomeEscape($order['vendorName'] ?? '')?></td>
                        <td><?=yearlyIncomeEscape($order['customerName'] ?? '')?></td>
                        <td><?=yearlyIncomeEscape($order['prodName'] ?? '')?></td>
                        <td><?=yearlyIncomeEscape($order['quantity'])?> <?=yearlyIncomeEscape($order['orderUOM'])?></td>
                        <td>$<?=number_format(is_numeric($order['settlementSupplier']) ? (float)$order['settlementSupplier'] : 0, 2)?> / MT</td>
                        <td><strong>$<?=number_format($order['settlementTotal'], 2)?></strong></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
                <?php if ($rows): ?><tfoot><tr><th colspan="7" class="text-right">Filtered total</th><th>$<?=number_format($yearTotal, 2)?></th></tr></tfoot><?php endif; ?>
            </table></div>
        </div>
    </section></div></div>
</section>
</div>
</section>
<?php include "include/footer.php"; ?>
