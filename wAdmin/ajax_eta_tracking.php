<?php 
require_once "include/sessionAdmin.php";
require_once "include/configure.php";

$billNo = trim((string)($_GET['billNo'] ?? '')); 
$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
if ($billNo === '' || !$id || $id < 1) { http_response_code(400); exit('Invalid tracking request.'); }
$shipmentTrackingApiKey = shipmentTrackingApiKey();

if ($shipmentTrackingApiKey == '') {
  echo 'Shipment Tracking API key is missing in Settings.';
  exit;
}

$apiUrl = 'https://api.sinay.ai/container-tracking/api/v2/shipment?shipmentNumber='.rawurlencode($billNo);
$curl = curl_init();
curl_setopt_array(
  $curl,
  array(
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => array(
      'Accept: application/json',
      'API_KEY: ' . $shipmentTrackingApiKey
    ),
  )
);
$response = curl_exec($curl);
curl_close($curl);
$data = json_decode($response, true);

$polDate = isset($data['route']['pol']['date']) ? $data['route']['pol']['date'] : null;
$podDate = isset($data['route']['pod']['date']) ? $data['route']['pod']['date'] : null;

if ($polDate !== null || $podDate !== null) {

  $lastPortDeparture = $polDate;
  $lastPortArrival = $podDate;

  // Include only the eta value in your INSERT query
  $addDataSQLArrival = false;
  $addDataSQLDepature = false;

  if (!empty($lastPortArrival)) {
    $lastPortArrival = date('Y-m-d', strtotime($lastPortArrival));
    $stmt = mysqli_prepare($conn, 'UPDATE orders SET eta=? WHERE id=?');
    mysqli_stmt_bind_param($stmt, 'si', $lastPortArrival, $id);
    $addDataSQLArrival = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
  }

  if (!empty($lastPortDeparture)) {
    $lastPortDeparture = date('Y-m-d', strtotime($lastPortDeparture));
    $stmt = mysqli_prepare($conn, 'UPDATE orders SET dta=? WHERE id=?');
    mysqli_stmt_bind_param($stmt, 'si', $lastPortDeparture, $id);
    $addDataSQLDepature = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
  }

	  if ($addDataSQLArrival === true || $addDataSQLDepature === true) {
    echo "Updated Successfully!";
	  }
} else { 

   echo ucwords($data['message']);
   

}
