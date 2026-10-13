<?php
require_once "include/sessionAdmin.php";
require_once "include/configure.php";

$templateColumn = mysqli_query($conn, "SHOW COLUMNS FROM admin LIKE 'priceRequestMessage'");
if ($templateColumn && mysqli_num_rows($templateColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE admin ADD COLUMN priceRequestMessage MEDIUMTEXT NULL");
}

$defaultTemplate = 'May I have the price for {product_name} 1 FCL on the basis of CFR Karachi, Pakistan?<br><br>Thanks<br>Waqas Chemicals';
$notice = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $template = mysqli_real_escape_string($conn, (string)($_POST['priceRequestMessage'] ?? ''));
    mysqli_query($conn, "UPDATE admin SET priceRequestMessage='" . $template . "' WHERE id='1'");
    header("Location: price_request_settings.php?saved=1");
    exit;
}

$templateResult = mysqli_query($conn, "SELECT priceRequestMessage FROM admin WHERE id='1' LIMIT 1");
$templateRow = $templateResult ? mysqli_fetch_assoc($templateResult) : false;
$priceRequestMessage = $templateRow && trim((string)$templateRow['priceRequestMessage']) !== ''
    ? $templateRow['priceRequestMessage']
    : $defaultTemplate;

include "include/header.php";
?>
<script src="<?=$siteUrl?>assets/vendor/summernote/summernote.js"></script>
<link rel="stylesheet" href="<?=$siteUrl?>assets/vendor/summernote/summernote.css" />
<link rel="stylesheet" href="<?=$siteUrl?>assets/vendor/summernote/summernote-bs3.css" />
<section role="main" class="content-body">
<header class="page-header">
    <h2>Price Request Message</h2>
    <div class="right-wrapper pull-right">
        <ol class="breadcrumbs"><li><a href="<?=$siteUrl?>"><i class="fa fa-home"></i></a></li><li><a href="<?=$siteUrl?>price_requests.php">Price Requests</a></li><li><span>Custom Message</span></li></ol>
    </div>
</header>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-info">Price request message saved.</div><?php endif; ?>
<div class="row"><div class="col-lg-12"><section class="panel">
<header class="panel-heading"><h2 class="panel-title">Custom Price Request Email</h2></header>
<div class="panel-body">
    <form method="post" class="form-horizontal form-bordered">
        <div class="form-group">
            <label class="col-md-2 control-label" for="priceRequestMessage">Message</label>
            <div class="col-md-9">
                <textarea class="summernote" id="priceRequestMessage" name="priceRequestMessage" data-plugin-summernote data-plugin-options='{ "height": 240 }'><?=htmlspecialchars($priceRequestMessage, ENT_QUOTES, 'UTF-8')?></textarea>
            </div>
        </div>
        <div class="form-group"><div class="col-md-offset-2 col-md-9">
            <button class="btn btn-primary" type="submit">Save Message</button>
            <a class="btn btn-default" href="<?=$siteUrl?>price_requests.php">Back to Price Requests</a>
        </div></div>
    </form>
    <div class="text-muted" style="font-size:12px;margin:8px 0 0 16.6667%;">
        <div style="margin-bottom:5px;">Product merge tags</div>
        <div style="display:flex;flex-wrap:wrap;gap:4px 14px;">
            <code>{product_name}</code>
            <code>{product_packaging}</code>
            <code>{product_quantity}</code>
            <code>{product_qty}</code>
            <code>{product_unit}</code>
            <code>{product_origin}</code>
            <code>{product_hs_code}</code>
            <code>{product_description}</code>
        </div>
    </div>
</div>
</section></div></div>
</section>
</div>
</section>
<?php include "include/footer.php"; ?>
