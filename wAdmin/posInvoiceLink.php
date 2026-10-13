<?php
include "include/sessionAdmin.php";
include "include/configure.php";

$invoiceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$invoiceId) {
    $invoiceId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
}
if (!$invoiceId || $invoiceId < 1) {
    http_response_code(400);
    exit('Invalid invoice.');
}

$stmt = $conn->prepare("SELECT publicGuid, isDraft FROM pos_sale_billing WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $invoiceId);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$invoice || (int)$invoice['isDraft'] === 1) {
    http_response_code(404);
    exit('Invoice not found.');
}

$guid = $invoice['publicGuid'];
if (!$guid) {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    $guid = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);

    $update = $conn->prepare("UPDATE pos_sale_billing SET publicGuid = ? WHERE id = ? AND (publicGuid IS NULL OR publicGuid = '')");
    $update->bind_param("si", $guid, $invoiceId);
    $update->execute();
    $update->close();

    // Re-read to handle a concurrent link request without replacing its GUID.
    $stmt = $conn->prepare("SELECT publicGuid FROM pos_sale_billing WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $invoiceId);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $guid = $invoice['publicGuid'];
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string)($_SERVER['HTTP_HOST'] ?? ''));
$projectPath = dirname(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/2018/wAdmin/posInvoiceLink.php')));
$projectPath = rtrim($projectPath, '/') . '/';
$publicUrl = $scheme . '://' . $host . $projectPath . 'posInvoicePDF.php?act=pdfDwnld&guid=' . rawurlencode($guid);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Public Invoice Link</title>
  <style>
    body{font:16px Arial,sans-serif;max-width:760px;margin:48px auto;padding:0 20px;color:#222}
    input{box-sizing:border-box;width:100%;padding:12px;margin:12px 0;border:1px solid #bbb;border-radius:4px}
    button,a{display:inline-block;padding:10px 16px;border:0;border-radius:4px;background:#1674c0;color:#fff;text-decoration:none;cursor:pointer}
  </style>
</head>
<body>
  <h1>Public invoice link</h1>
  <p>Anyone with this unique GUID link can view and download this invoice without signing in. Keep it private if access should be limited.</p>
  <input id="publicInvoiceUrl" type="text" readonly value="<?=htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8')?>">
  <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('publicInvoiceUrl').value).then(function(){document.getElementById('copyStatus').textContent='Copied';});">Copy link</button>
  <a href="<?=htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8')?>" target="_blank" rel="noopener">Open invoice</a>
  <p id="copyStatus" aria-live="polite"></p>
</body>
</html>
