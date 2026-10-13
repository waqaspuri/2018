<?php
require_once "include/sessionAdmin.php";
require_once "include/configure.php";
date_default_timezone_set('Asia/Karachi');
header('Content-Type: application/json; charset=utf-8');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id && isset($_POST['id'])) $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    echo json_encode(array('error' => 'Invalid order ID.'));
    exit;
}

$select = mysqli_prepare($conn, 'SELECT tags FROM orders WHERE id=? LIMIT 1');
mysqli_stmt_bind_param($select, 'i', $id);
mysqli_stmt_execute($select);
$result = mysqli_stmt_get_result($select);
$row = $result ? mysqli_fetch_assoc($result) : false;
mysqli_stmt_close($select);
if (!$row) {
    http_response_code(404);
    echo json_encode(array('error' => 'Order not found.'));
    exit;
}
$tags = json_decode((string)$row['tags'], true);
if (!is_array($tags)) $tags = array();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode($tags);
    exit;
}

$action = (string)($_POST['action'] ?? '');
$data = trim((string)($_POST['data'] ?? ''));
if (($action !== 'add' && $action !== 'remove') || $data === '') {
    http_response_code(400);
    echo json_encode(array('error' => 'Invalid request.'));
    exit;
}
if ($action === 'add') {
    $tags[] = date('j-M-y g:i A') . ' ' . $data;
} else {
    $key = array_search($data, $tags, true);
    if ($key !== false) unset($tags[$key]);
    $tags = array_values($tags);
}
$jsonTags = json_encode($tags, JSON_UNESCAPED_UNICODE);
$update = mysqli_prepare($conn, 'UPDATE orders SET tags=? WHERE id=?');
mysqli_stmt_bind_param($update, 'si', $jsonTags, $id);
$ok = mysqli_stmt_execute($update);
mysqli_stmt_close($update);
if (!$ok) {
    http_response_code(500);
    echo json_encode(array('error' => 'Could not update order tags.'));
    exit;
}
echo json_encode(array('success' => true));
