<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_errors.log');

if (function_exists('mysqli_report')) {
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}
function stringTxtLimit($strng, $wordLimit)
{
  if (strlen($strng) > $wordLimit) {
    $dotsStr = "...";
  } else {
    $dotsStr = "";
  }
  $strngTxt = substr(strip_tags($strng), 0, $wordLimit) . $dotsStr;
  return $strngTxt;
}
function currDateTimeGmt()
{
  $user_tz = 5.30;
  $user_offset = $user_tz * 3600;
  $timestamp = time();
  $local_timestamp = $timestamp + $user_offset;
  return date("Y-m-d g:i:s A", $local_timestamp);
}
function viewDate($dt)
{
  $dtAry = explode("-", $dt);
  return $dtAry['2'] . '-' . $dtAry['1'] . '-' . $dtAry['0'];
}
function viewDtTm($dt)
{
  $dtTmAry = explode(" ", $dt);
  return date('d-M-Y', strtotime($dtTmAry[0]));
}
function priceFormat($vl)
{
  if ($vl != "") {
    return number_format($vl, 2, '.', '');
  } else {
    return 0;
  }
}
function checkPhoneNumber($no)
{
  if (ctype_digit($no) && (strlen($no) == 10)) {
    return true;
  } else {
    return false;
  }
}
function checkTxtField($stg)
{
  if (!preg_match("/^[a-zA-Z ]*$/", $stg)) {
    return false;
  } else {
    return true;
  }
}
function checkPasswordField($stg)
{
  if (!preg_match("/^[a-zA-Z0-9]*$/", $stg)) {
    return false;
  } else {
    return true;
  }
}
function checkTextareaField($stg)
{
  if (!preg_match("/[A-Za-z0-9_~\-?!@#\$%\^&\*\(\)]+$/", $stg)) {
    return false;
  } else {
    return true;
  }
}
function checkNumber($no)
{
  if (ctype_digit($no)) {
    return true;
  } else {
    return false;
  }
}
function checkEmail($e)
{
  if (!preg_match("/[A-Za-z0-9_~\-?!@#\$%\^&\*\(\)]+$/", $e)) {
    return false;
  } else {
    return true;
  }
}
function sqlDtFormat($dt)
{
  if (empty($dt) || !is_string($dt)) {
    return '0000-00-00';
  }
  $dtAry = explode("-", $dt);
  if (count($dtAry) === 3) {
    return $dtAry[2] . '-' . $dtAry[1] . '-' . $dtAry[0];
  }
  return '0000-00-00';
}

function getDiff($eta, $dta)
{

  $start = strtotime($eta);
  $end = strtotime($dta);

  $days_between = ceil(abs($end - $start) / 86400);

  return $days_between;


}

function getPerDate($eta, $dta)
{


  $start = strtotime($eta);

  $end = strtotime($dta);

  if ($start === false || $end === false || $start == $end) {
    return 0;
  }

  $today = date("d-m-Y");

  $current = strtotime($today);

  $completed = floor((($current - $end) / ($start - $end)) * 100);

  return abs($completed);


}


//return difference of today - ETA

function timeEst($dt2)
{

  $convertDateDB = date("d-m-Y", strtotime($dt2));
  $today = date("d-m-Y");
  $date = (strtotime($convertDateDB) - strtotime($today)) / 60 / 60 / 24;
  return $date;
}




function viewByDaysFormat($dt)
{

  $convertDateDB = date("d-m-Y", strtotime($dt));
  $today = date("d-m-Y");
  $date = (strtotime($convertDateDB) - strtotime($today)) / 60 / 60 / 24;
  $dtAry = explode("-", $dt);



  return (($date < 0) ? '' : ' <span class="label label-success"> ' . (int) $date . ' Days left</span></br>');

  if ($convertDateDB == '01-01-1970') {

    return '<span class="label label-info">UNDEFINED</span>';

  } else {

    return ($dtAry[2] . '-' . $dtAry[1] . '-' . $dtAry[0]);

  }

}

function viewDateFormat($dt)
{
  if (empty($dt) || $dt == '0000-00-00' || $dt == '1970-01-01') {
      return '';
  }
  $dtAry = explode("-", $dt);
  if (count($dtAry) === 3) {
      return $dtAry[2] . '-' . $dtAry[1] . '-' . $dtAry[0];
  }
  return '';
}

function customerName($id)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT applicantName FROM customers where id='" . $id . "'");
  $rows = mysqli_fetch_assoc($sql);
  return $rows['applicantName'] ?? 'Erased';
}
function assignToName($id)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT companyName,type FROM customers where id='" . $id . "'");
  $rows = mysqli_fetch_assoc($sql);
  if (!$rows) {
    return 'Erased';
  }
  return ($rows['companyName'] ?? 'Erased') . ' (' . ((($rows['type'] ?? '') == '1') ? 'Customer' : 'Vendor') . ')';
}
function companyName($id)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT companyName FROM customers where id='" . $id . "'");
  $rows = mysqli_fetch_assoc($sql);
  return $rows['companyName'] ?? 'Erased';
}
function whatsApp($id)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT mobileNumber FROM customers where id='" . $id . "'");
  $rows = mysqli_fetch_assoc($sql);
  return $rows['mobileNumber'] ?? 'Erased';
}


function typeNm($id)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT type FROM type where id='" . $id . "'");
  $rows = mysqli_fetch_assoc($sql);
  return $rows['type'] ?? 'Erased';
}
function orderNotesTotal($id)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT count(id) as t FROM order_notes WHERE orderID='" . $id . "'");
  $rows = mysqli_fetch_assoc($sql);
  return $rows['t'];
}
function orderStatusTotal($id)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT count(id) as t FROM orders WHERE statusID='" . $id . "'");
  $rows = mysqli_fetch_assoc($sql);
  return $rows['t'];
}
function orderProdName($id)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT p.prodName FROM orders as o,products as p where o.productID=p.id AND o.id='" . $id . "'");
  $rows = mysqli_fetch_assoc($sql);
  return $rows['prodName'] ?? 'Erased';
}
function orderStatusID($id)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT statusID FROM orders where id='" . $id . "'");
  $rows = mysqli_fetch_assoc($sql);
  return $rows['statusID'];
}
function statusNm($id)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT status FROM status where id='" . $id . "'");
  $rows = mysqli_fetch_assoc($sql);
  return $rows['status'];
}
function statusBg($id)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT bgColor FROM status where id='" . $id . "'");
  $rows = mysqli_fetch_assoc($sql);
  return $rows['bgColor'];
}
function priorityNm($id)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT priorityNm FROM task_priority where id='" . $id . "'");
  $rows = mysqli_fetch_assoc($sql);
  return $rows ? $rows['priorityNm'] : '';
}
function invoiceNo()
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT id FROM invoices order by id desc");
  $rows = mysqli_fetch_assoc($sql);
  return ($rows['id'] + 1);
}
function buyerBalance($id)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT balAmt FROM pos_buyer_account where posBuyerID='" . $id . "' order by id desc");
  $numRw = mysqli_num_rows($sql);
  if ($numRw > 0) {
    $rows = mysqli_fetch_assoc($sql);
    return (($rows['balAmt'] > 0) ? '-' . priceFormatRound($rows['balAmt']) : priceFormatRound(str_replace("-", "", $rows['balAmt'])));
  } else {
    return 0;
  }
}
function productNm($id)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT prodName FROM products where id='" . $id . "'");
  $rows = mysqli_fetch_assoc($sql);
  return $rows['prodName'];
}
function productHSCode($id)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT hsCode FROM products where id='" . $id . "'");
  $rows = mysqli_fetch_assoc($sql);
  return $rows['hsCode'];
}
function downloadFile($fl, $flNm)
{
  $file = $fl;
  if (file_exists($file)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header("Content-Type: application/force-download");
    header('Content-Disposition: attachment; filename=' . $flNm);
    // header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));
    ob_clean();
    flush();
    readfile($file);
    exit;
  }
}
function get_total($a, $b)
{
  return ((float)$a * (float)$b);
}
function invoicePaidTotal($id)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT count(id) as t FROM invoices WHERE isPaid='" . $id . "'");
  $rows = mysqli_fetch_assoc($sql);
  return $rows['t'];
}
function quickNotesTotal()
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT count(id) as t FROM to_do where isDone='0'");
  $rows = mysqli_fetch_assoc($sql);
  return $rows['t'];
}
function posBill()
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT count(id) as t FROM pos_sale_billing");
  $rows = mysqli_fetch_assoc($sql);
  return $rows['t'];
}
function cronJobTotal($dt, $type)
{
  global $conn;
  if ($type == "fc") {
    $sqlSort = " dueDate>='" . $dt . "' and isExpired='0' ";
  } else if ($type == "pc") {
    $sqlSort = " dueDate<'" . $dt . "' OR sendBy LIKE '%immediate%' ";
  }
  $sql = mysqli_query($conn, "SELECT count(id) as t FROM tasks where " . $sqlSort);
  $rows = mysqli_fetch_assoc($sql);
  return $rows['t'];
}
function posCateNm($id)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT igmNo FROM pos_category WHERE id='" . $id . "'");
  $rows = mysqli_fetch_assoc($sql);
  return $rows['igmNo'];
}
function posUnitType($id)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT title FROM pos_unit_types WHERE id='" . $id . "'");
  $rows = mysqli_fetch_assoc($sql);
  return $rows['title'];
}
function orderNo()
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT id FROM pos_sale_billing ORDER BY id DESC");
  $rows = mysqli_fetch_assoc($sql);
  return ($rows['id'] + 1);
}
function productCustomEditVal($cID, $pID)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT pos_amt FROM pos_product_customs WHERE pos_CCID='" . $cID . "' AND pos_pID='" . $pID . "'");
  $numRow = mysqli_num_rows($sql);
  if ($numRow > 0) {
    $rows = mysqli_fetch_assoc($sql);
    return $rows['pos_amt'];
  } else {
    return 0;
  }
}
function posInvoiceNo()
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT invoiceNo FROM pos_sale_billing order by id desc");
  $rows = mysqli_fetch_assoc($sql);
  return ($rows['invoiceNo'] + 1);
}
function posDraftInvoiceNo()
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT invoiceNo FROM pos_sale_billing_draft order by id desc");
  $rows = mysqli_fetch_assoc($sql);
  return ($rows['invoiceNo'] + 1);
}
function posHSCode($id)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT hsCode FROM pos_products where id='" . $id . "' ");
  $rows = mysqli_fetch_assoc($sql);
  return $rows['hsCode'];
}
function priceFormatRound($vl)
{
  if ($vl != "") {
    return number_format($vl);
  } else {
    return 0;
  }
}
function sendTemplateNm($id)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT subject FROM send_templates where id='" . $id . "' ");
  $rows = mysqli_fetch_assoc($sql);
  return $rows['subject'];
}
function shipmentTrackingApiKey()
{
  global $conn;
  $sqlShipmentTrackingApiKeyColumn = mysqli_query($conn, "SHOW COLUMNS FROM admin LIKE 'shipmentTrackingApiKey'");
  if (mysqli_num_rows($sqlShipmentTrackingApiKeyColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE admin ADD COLUMN shipmentTrackingApiKey VARCHAR(255) NULL DEFAULT NULL AFTER adminEmail");
  }
  $sql = mysqli_query($conn, "SELECT shipmentTrackingApiKey FROM admin WHERE id='1'");
  if (!$sql) {
    return '';
  }
  $rows = mysqli_fetch_assoc($sql);
  return isset($rows['shipmentTrackingApiKey']) ? trim($rows['shipmentTrackingApiKey']) : '';
}
function getLastInvoiceDate()
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT invoiceDate FROM pos_sale_billing ORDER BY id DESC");
  $rows = mysqli_fetch_assoc($sql);
  return $rows['invoiceDate'];
}
function productGD($id)
{
  global $conn;
  $sql = mysqli_query($conn, "SELECT posCategoryID FROM pos_products WHERE id='" . $id . "'");
  $rows = mysqli_fetch_assoc($sql);
  return posCateNm($rows['posCategoryID']);
}


function trackDHL($id)
{
  if (!empty($id)) {
    $url = 'https://api-eu.dhl.com/track/shipments?trackingNumber=' . rawurlencode($id);
    $context = stream_context_create(array(
      'http' => array(
        'method' => 'GET',
        'header' => "DHL-API-Key: Al6tL5UqfEJggJCQJirFhPW6AYAGS5Um\r\n",
        'timeout' => 15,
        'ignore_errors' => true,
      ),
    ));
    $response = @file_get_contents($url, false, $context);
    if ($response === false || $response === '') {
      return array('detail' => 'DHL request failed or timed out.');
    }
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : array('detail' => 'DHL returned an invalid response.');

    // Creating new date format from that timestamp


    // $estimatedTimeOfDelivery = $response['shipments'][0]['estimatedTimeOfDelivery'];
// //$estimatedTimeOfDelivery = date("d-m-Y",strtotime($response['shipments'][0]['estimatedTimeOfDelivery']));
// $estimatedTimeOfDeliveryRemark = $response['shipments'][0]['estimatedTimeOfDeliveryRemark'];
// $events = $response['shipments'][0]['status']['description'];
// $timestamp = date("d-m-Y",strtotime($response['shipments'][0]['status']['timestamp']));
// $destination = $response['shipments'][0]['destination']['address']['addressLocality'];
//
// return '<br>'.$estimatedTimeOfDelivery . ' <br>  '. $timestamp . ''.' <br> ' . $destination .' <br>'. $estimatedTimeOfDeliveryRemark .'<br> '. $events;
  } // Dump all data of the Array
}

function formatDateDHL($date)
{
  if (!isset($date)) {
    return NULL;
  }
  $date = str_replace('T', ' ', $date);
  $date = date('F j, Y', strtotime($date));
  return $date;
}

//Telenor
$userName = "923424859921";
$password = "gbKg2i01bf2ExE1pG7";
$mask = "WAQASCHM";

function sendSMS($mobile, $msg)
{



  global $mask;
  global $userName;
  global $password;

  if (substr($mobile, 0, 3) === "+92") {
    // remove the + symbol from the beginning of the string
    $mobile = substr($mobile, 1);
  }



  if (substr($mobile, 0, 2) === "92" && strlen($mobile) === 12 && ctype_digit($mobile)) {

    // session_id

    $url = "https://telenorcsms.com.pk:27677/corporate_sms2/api/auth.jsp?msisdn=" . $userName . "&password=" . $password;

    $xml = @simplexml_load_file($url);


    $session_id = '';
    if (!empty($xml)) {
      $session_id = $xml->data;
    } else {
      echo "error! unable to retrieve the session ID";
    }
    // session_id


    $msg = preg_replace('/[^(\x20-\x7F)\x0A\x0D]*/', '', $msg);
    $msg = urlencode($msg);

    $sendSMSurl = "https://telenorcsms.com.pk:27677/corporate_sms2/api/sendsms.jsp?session_id=" . $session_id . "&to=" . $mobile . "&text=" . $msg . "&mask=" . $mask;

    if (!empty($xml)) {

      $sendSMS = @simplexml_load_file($sendSMSurl);
      $client = $mobile;
      $data = $sendSMS ? (string)($sendSMS->data ?? '') : '';
      $response = $sendSMS ? trim((string)($sendSMS->response ?? '')) : 'ERROR: SMS gateway request failed';
      if ($response === '') {
        $response = 'ERROR: gateway returned no status';
      }
      date_default_timezone_set("Asia/Karachi");
      $date = date('l, d-m-y h:i:s a');

    
    } else {

      echo "error! unable to retrieve the session ID";
      $client = $mobile;
      $data = '';
      $response = 'ERROR: unable to retrieve the session ID';
      date_default_timezone_set("Asia/Karachi");
      $date = date('l, d-m-y h:i:s a');

    }

    insertSMSLog($client, $data, $response, $date);
    return (string)$response;
  }
}



function insertSMSLog($client, $data, $response, $date)
{

  global $conn;
  try {
    $stmt = mysqli_prepare($conn, "INSERT INTO sms_log (client,api_data,api_response,time_stamp) VALUES (?,?,?,?)");
    if (!$stmt) {
      return mysqli_error($conn);
    }
    $client = (string)$client;
    $data = (string)$data;
    $response = (string)$response;
    $date = (string)$date;
    mysqli_stmt_bind_param($stmt, 'ssss', $client, $data, $response, $date);
    $inserted = mysqli_stmt_execute($stmt);
    $error = $inserted ? '' : mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    return $inserted ? true : $error;
  } catch (Throwable $exception) {
    return $exception->getMessage();
  }

}

function readSMSLog($limit=50)
{
  global $conn;
  $sql = "SELECT * FROM sms_log ORDER BY id desc LIMIT $limit";
  $result = mysqli_query($conn, $sql);
  return $result; 
}


function getApiData($billNo, $id)
{
  global $conn;

  if (empty($billNo)) {
    return null;
  }

  $shipmentTrackingApiKey = shipmentTrackingApiKey();
  if ($shipmentTrackingApiKey == '') {
    return 'Shipment Tracking API key is missing in Settings.';
  }
  
  $apiUrl = 'https://api.sinay.ai/container-tracking/api/v2/shipment?shipmentNumber=' . $billNo;
  $curl = curl_init();
  curl_setopt_array(
    $curl,
    array(
      CURLOPT_URL => $apiUrl,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Accept: application/json',
        'API_KEY: ' . $shipmentTrackingApiKey
      ),
    )
  );
  $response = curl_exec($curl);
  curl_close($curl);
  $data = json_decode($response, true);

  if ($data) {
    if (isset($data['route']['pod']['date']) || isset($data['route']['pol']['date'])) {
        if (isset($data['route']['pod']['date'])) {
            $lastPortArrival = $data['route']['pod']['date'];
            $formattedDate = date('Y-m-d', strtotime($lastPortArrival));
            mysqli_query($conn, "UPDATE orders SET eta = '$formattedDate' WHERE id = '$id'");
        }
        if (isset($data['route']['pol']['date'])) {
            $lastPortDeparture = $data['route']['pol']['date'];
            $formattedDatePOL = date('Y-m-d', strtotime($lastPortDeparture));
            mysqli_query($conn, "UPDATE orders SET dta = '$formattedDatePOL' WHERE id = '$id'");
        }
    } else if (isset($data['metadata']['shippingStatus'])) {
        return $data['metadata']['shippingStatus'];
    }
  }
  return null;
}

function buildTaskEmailBody($applicantName, $taskMessage)
{
  $recipientName = htmlspecialchars((string)$applicantName, ENT_QUOTES, 'UTF-8');
  return '<html><head><style>body,body *{font-size:medium!important;}</style></head><body style="font-family:Arial,Helvetica,sans-serif;font-size:medium;"><div style="font-family:Arial,Helvetica,sans-serif;font-size:medium;">Dear ' . $recipientName . ',<br><br><div class="task-email-message" style="font-family:Arial,Helvetica,sans-serif;font-size:medium;">' . (string)$taskMessage . '</div></div></body></html>';
}

function appendConfiguredEmailFooter($conn, $message)
{
  static $footerLoaded = false;
  static $emailFooter = '';

  if (!$footerLoaded) {
    $footerLoaded = true;
    $footerColumn = mysqli_query($conn, "SHOW COLUMNS FROM admin LIKE 'emailFooter'");
    if ($footerColumn && mysqli_num_rows($footerColumn) > 0) {
      $footerResult = mysqli_query($conn, "SELECT emailFooter FROM admin WHERE id='1' LIMIT 1");
      $footerRow = $footerResult ? mysqli_fetch_assoc($footerResult) : false;
      $emailFooter = $footerRow ? trim((string)$footerRow['emailFooter']) : '';
    }
  }

  if ($emailFooter === '') {
    return $message;
  }

  $footerHtml = '<div class="email-signature">' . $emailFooter . '</div>';
  if (stripos($message, '</body>') !== false) {
    return preg_replace('/<\/body>/i', $footerHtml . '</body>', $message, 1);
  }
  return $message . $footerHtml;
}

?>
