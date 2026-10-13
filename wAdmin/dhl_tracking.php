<?php
require_once "include/sessionAdmin.php";
require_once "include/configure.php";

$requestedId = isset($_GET['id']) ? trim((string)$_GET['id']) : '';
$order = false;

if ($requestedId !== '') {
    $where = ctype_digit($requestedId)
        ? "id='" . (int)$requestedId . "'"
        : "orderUUID='" . mysqli_real_escape_string($conn, $requestedId) . "'";
    $sql = mysqli_query($conn, "SELECT shippingDocInfo,orderUUID FROM orders WHERE " . $where . " LIMIT 1");
    $order = $sql ? mysqli_fetch_assoc($sql) : false;
    if ($order && ctype_digit($requestedId) && !empty($order['orderUUID'])) {
        header("Location: dhl_tracking.php?id=" . rawurlencode($order['orderUUID']));
        exit;
    }
}

$trackingNumber = ($order && isset($order['shippingDocInfo'])) ? trim((string)$order['shippingDocInfo']) : '';
$data = $trackingNumber !== '' ? trackDHL($trackingNumber) : array('detail' => 'No DHL tracking number found for this order.');
$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    $json = '{"detail":"Unable to format DHL response."}';
}
$shipment = (isset($data['shipments'][0]) && is_array($data['shipments'][0])) ? $data['shipments'][0] : array();
$statusInfo = isset($shipment['status']) && is_array($shipment['status']) ? $shipment['status'] : array();
$details = isset($shipment['details']) && is_array($shipment['details']) ? $shipment['details'] : array();
$product = isset($details['product']) && is_array($details['product']) ? $details['product'] : array();
$origin = isset($shipment['origin']) && is_array($shipment['origin']) ? $shipment['origin'] : array();
$destination = isset($shipment['destination']) && is_array($shipment['destination']) ? $shipment['destination'] : array();
$originAddress = isset($origin['address']) && is_array($origin['address']) ? $origin['address'] : array();
$destinationAddress = isset($destination['address']) && is_array($destination['address']) ? $destination['address'] : array();
$events = isset($shipment['events']) && is_array($shipment['events']) ? $shipment['events'] : array();
$pieceIds = isset($details['pieceIds']) && is_array($details['pieceIds']) ? $details['pieceIds'] : array();
$proofOfDelivery = isset($details['proofOfDelivery']) && is_array($details['proofOfDelivery']) ? $details['proofOfDelivery'] : array();
$esc = function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$value = function ($array, $key, $default = '—') {
    return isset($array[$key]) && $array[$key] !== '' ? $array[$key] : $default;
};
$formatDate = function ($date) {
    if (!$date || $date === '—') {
        return '—';
    }
    try {
        $dateObject = new DateTime($date);
        return $dateObject->format('d M Y, H:i T');
    } catch (Exception $exception) {
        return $date;
    }
};
$statusCode = strtolower((string)$value($statusInfo, 'statusCode', ''));
$statusClass = preg_replace('/[^a-z0-9_-]/', '', $statusCode);
$originLocation = $value($originAddress, 'addressLocality', $value($originAddress, 'countryCode'));
$destinationLocation = $value($destinationAddress, 'addressLocality', $value($destinationAddress, 'countryCode'));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>DHL API Details</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #333; background: #fafafa; }
        .page { max-width: 1100px; margin: 0 auto; }
        h2 { color: #d40511; margin-bottom: 6px; }
        h3 { margin: 26px 0 10px; color: #444; }
        .tracking-number { color: #666; margin-top: 0; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 16px; margin-top: 16px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 9px 10px; text-align: left; vertical-align: top; }
        th { width: 190px; background: #f5f5f5; font-weight: 600; }
        .events th { width: auto; }
        .events td { font-size: 14px; }
        .status { display: inline-block; padding: 4px 9px; border-radius: 12px; color: #fff; background: #777; font-weight: 600; text-transform: capitalize; }
        .status.delivered { background: #218838; }
        .status.transit { background: #007bff; }
        .status.exception, .status.failure { background: #c82333; }
        .button { display: inline-block; padding: 7px 11px; margin: 2px 5px 2px 0; color: #fff; background: #d40511; border-radius: 3px; text-decoration: none; font-size: 13px; }
        .button.secondary { background: #666; }
        .muted { color: #777; }
        pre { padding: 16px; background: #f5f5f5; border: 1px solid #ddd; overflow: auto; white-space: pre-wrap; font-size: 12px; }
        details summary { cursor: pointer; color: #555; font-weight: 600; }
        @media (max-width: 700px) { body { margin: 10px; } th, td { display: block; width: auto; } .events thead { display: none; } .events td:before { content: attr(data-label); display: block; font-weight: 600; color: #666; margin-bottom: 3px; } }
    </style>
</head>
<body>
<div class="page">
    <h2>DHL Shipment Details</h2>
    <p class="tracking-number"><strong>Tracking number:</strong> <?=$esc($trackingNumber !== '' ? $trackingNumber : 'Not available')?></p>

    <?php if (!$shipment): ?>
        <div class="card"><?= $esc($value($data, 'detail', 'No shipment information was returned by DHL.')) ?></div>
    <?php else: ?>
        <div class="card">
            <table>
                <tr><th>Current status</th><td><span class="status <?=$esc($statusClass)?>"><?=$esc($value($statusInfo, 'description'))?></span></td></tr>
                <tr><th>Last update</th><td><?=$esc($formatDate($value($statusInfo, 'timestamp')))?></td></tr>
                <tr><th>Origin</th><td><?=$esc($originLocation)?></td></tr>
                <tr><th>Destination</th><td><?=$esc($destinationLocation)?></td></tr>
                <tr><th>Service</th><td><?=$esc($value($shipment, 'service'))?></td></tr>
                <?php if ($value($shipment, 'division') !== '—'): ?><tr><th>Carrier division</th><td><?=$esc($value($shipment, 'division'))?></td></tr><?php endif; ?>
                <tr><th>Product</th><td><?=$esc($value($product, 'productName'))?> <span class="muted">(<?= $esc($value($product, 'productCode')) ?>)</span></td></tr>
                <tr><th>Total pieces</th><td><?=$esc($value($details, 'totalNumberOfPieces'))?></td></tr>
                <tr><th>Shipment activated</th><td><?=$esc($formatDate($value($details, 'shipmentActivationDate')))?></td></tr>
                <?php if ($value($statusInfo, 'remark', '-') !== '-'): ?><tr><th>Remark</th><td><?=$esc($value($statusInfo, 'remark'))?></td></tr><?php endif; ?>
                <?php if ($value($statusInfo, 'nextSteps', '-') !== '-'): ?><tr><th>Next steps</th><td><?=$esc($value($statusInfo, 'nextSteps'))?></td></tr><?php endif; ?>
            </table>
        </div>

        <h3>Shipment route</h3>
        <div class="card">
            <table>
                <tr><th>Origin service area</th><td><?=$esc($originLocation)?></td><td><?php if (isset($origin['servicePoint']['url'])): ?><a class="button secondary" href="<?=$esc($origin['servicePoint']['url'])?>" target="_blank" rel="noopener">Open DHL area</a><?php endif; ?></td></tr>
                <tr><th>Destination service area</th><td><?=$esc($destinationLocation)?></td><td><?php if (isset($destination['servicePoint']['url'])): ?><a class="button secondary" href="<?=$esc($destination['servicePoint']['url'])?>" target="_blank" rel="noopener">Open DHL area</a><?php endif; ?></td></tr>
            </table>
        </div>

        <?php if ($pieceIds): ?>
            <h3>Package pieces</h3>
            <div class="card">
                <table>
                    <tr><th>Piece number</th><th>Tracking piece ID</th></tr>
                    <?php foreach ($pieceIds as $pieceId): ?><tr><td><?= $esc(count($pieceIds) > 1 ? 'Piece ' . (array_search($pieceId, $pieceIds, true) + 1) : 'Piece 1') ?></td><td><?= $esc($pieceId) ?></td></tr><?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($proofOfDelivery): ?>
            <h3>Proof of delivery</h3>
            <div class="card">
                <?php if (!empty($proofOfDelivery['documentUrl'])): ?><a class="button" href="<?=$esc($proofOfDelivery['documentUrl'])?>" target="_blank" rel="noopener">Open delivery document</a><?php endif; ?>
                <?php if (!empty($proofOfDelivery['signatureUrl']) && !empty($details['proofOfDeliverySignedAvailable'])): ?><a class="button" href="<?=$esc($proofOfDelivery['signatureUrl'])?>" target="_blank" rel="noopener">Open signature</a><?php endif; ?>
                <?php if (empty($proofOfDelivery['documentUrl']) && empty($proofOfDelivery['signatureUrl'])): ?><span class="muted">No delivery document is available.</span><?php endif; ?>
            </div>
        <?php endif; ?>

        <h3>Tracking history</h3>
        <div class="card">
            <?php if ($events): ?>
                <table class="events">
                    <thead><tr><th>Date and time</th><th>Location</th><th>Status</th><th>Details</th></tr></thead>
                    <tbody>
                    <?php foreach ($events as $event):
                        $eventLocation = isset($event['location']['address']['addressLocality']) ? $event['location']['address']['addressLocality'] : '—';
                        $eventDescription = $value($event, 'description');
                    ?>
                        <tr>
                            <td data-label="Date and time"><?=$esc($formatDate($value($event, 'timestamp')))?></td>
                            <td data-label="Location"><?=$esc($eventLocation)?></td>
                            <td data-label="Status"><?=$esc($eventDescription)?></td>
                            <td data-label="Details"><span class="muted">Code:</span> <?=$esc($value($event, 'statusCode'))?> / <?=$esc($value($event, 'status'))?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <span class="muted">No tracking events were returned.</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <h3>Original API response</h3>
    <div class="card">
        <details>
            <summary>Show raw JSON</summary>
            <pre><?=$esc($json)?></pre>
        </details>
    </div>
</div>
</body>
</html>
