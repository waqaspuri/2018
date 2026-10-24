<?php
require_once "include/sessionAdmin.php";
require_once "include/configure.php";
require_once "../php/lead_pipeline.php";

function ordersPublicFileUrl($fileName)
{
    global $siteUrlMain;
    return rtrim((string)$siteUrlMain, '/') . '/img/' . rawurlencode((string)$fileName);
}

function ordersCopyFileUrlButton($fileName)
{
    $url = htmlspecialchars(ordersPublicFileUrl($fileName), ENT_QUOTES, 'UTF-8');
    return '<span class="order-file-url-copy" style="display:inline-block;max-width:100%;vertical-align:middle;"><input type="text" class="form-control input-sm order-file-url" readonly="readonly" value="' . $url . '" onclick="this.select();" aria-label="Public file URL" style="display:inline-block;width:260px;max-width:100%;vertical-align:middle;"><button type="button" class="btn btn-default btn-xs" onclick="copyOrderFileUrl(this);">Copy URL</button></span>';
}

ensureLeadPipelineSchema($conn);
$modifiedDateColumn = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'modifiedDate'");
if ($modifiedDateColumn && mysqli_num_rows($modifiedDateColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE orders ADD COLUMN modifiedDate DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER createdDate");
}
$noteModifiedDateColumn = mysqli_query($conn, "SHOW COLUMNS FROM order_notes LIKE 'modifiedDate'");
if ($noteModifiedDateColumn && mysqli_num_rows($noteModifiedDateColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE order_notes ADD COLUMN modifiedDate DATETIME NULL DEFAULT NULL AFTER createdDate");
}

$sqlLeadTimeColumn = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'lead_time'");
if (mysqli_num_rows($sqlLeadTimeColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE orders ADD COLUMN lead_time INT UNSIGNED NOT NULL DEFAULT 0 AFTER documentDate");
}
$sqlDocumentReceivedDateColumn = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'documentReceivedDate'");
if (mysqli_num_rows($sqlDocumentReceivedDateColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE orders ADD COLUMN documentReceivedDate DATE NULL DEFAULT NULL AFTER documentDate");
}
$sqlOrderUUIDColumn = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'orderUUID'");
if (mysqli_num_rows($sqlOrderUUIDColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE orders ADD COLUMN orderUUID CHAR(36) NULL AFTER id");
}
mysqli_query($conn, "UPDATE orders SET orderUUID=UUID() WHERE orderUUID IS NULL OR orderUUID=''");
$sqlDhlAlertStateColumn = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'dhlAlertState'");
if (mysqli_num_rows($sqlDhlAlertStateColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE orders ADD COLUMN dhlAlertState VARCHAR(20) NULL AFTER orderUUID");
}
$sqlDhlAlertRemindColumn = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'dhlAlertRemindAt'");
if (mysqli_num_rows($sqlDhlAlertRemindColumn) == 0) {
    mysqli_query($conn, "ALTER TABLE orders ADD COLUMN dhlAlertRemindAt DATETIME NULL AFTER dhlAlertState");
}

if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'view') {
    $requestedId = (string)$_GET['id'];
    if (ctype_digit($requestedId)) {
        $uuidResult = mysqli_query($conn, "SELECT orderUUID FROM orders WHERE id='" . (int)$requestedId . "' LIMIT 1");
        $uuidRow = $uuidResult ? mysqli_fetch_assoc($uuidResult) : false;
        if ($uuidRow && !empty($uuidRow['orderUUID'])) {
            $noteAddedQuery = isset($_GET['noteAdded']) && $_GET['noteAdded'] === '1' ? '&noteAdded=1' : '';
            header("Location: orders.php?action=view&id=" . rawurlencode($uuidRow['orderUUID']) . $noteAddedQuery);
            exit;
        }
    } else {
        $safeUUID = mysqli_real_escape_string($conn, $requestedId);
        $idResult = mysqli_query($conn, "SELECT id FROM orders WHERE orderUUID='" . $safeUUID . "' LIMIT 1");
        $idRow = $idResult ? mysqli_fetch_assoc($idResult) : false;
        if ($idRow) {
            $_GET['id'] = (int)$idRow['id'];
        }
    }
}

if (isset($_POST['dhlAlertAction'], $_POST['dhlAlertOrderID'])) {
    $alertOrderID = (int)$_POST['dhlAlertOrderID'];
    $alertAction = $_POST['dhlAlertAction'];
    if ($alertOrderID > 0 && in_array($alertAction, array('ignore', 'remind', 'create_task'), true)) {
        if ($alertAction === 'ignore') {
            mysqli_query($conn, "UPDATE orders SET dhlAlertState='ignored',dhlAlertRemindAt=NULL WHERE id='" . $alertOrderID . "'");
            header("Location: orders.php?action=view&id=" . $alertOrderID);
            exit;
        }
        if ($alertAction === 'remind') {
            mysqli_query($conn, "UPDATE orders SET dhlAlertState='snoozed',dhlAlertRemindAt=DATE_ADD(NOW(), INTERVAL 1 DAY) WHERE id='" . $alertOrderID . "'");
            header("Location: orders.php?action=view&id=" . $alertOrderID);
            exit;
        }
        $alertOrderResult = mysqli_query($conn, "SELECT eta,vendorID FROM orders WHERE id='" . $alertOrderID . "' LIMIT 1");
        $alertOrder = $alertOrderResult ? mysqli_fetch_assoc($alertOrderResult) : false;
        $taskDueDate = (!empty($alertOrder['eta']) && $alertOrder['eta'] !== '--') ? date('Y-m-d', strtotime($alertOrder['eta'])) : date('Y-m-d');
        $taskAssignTo = $alertOrder ? (int)$alertOrder['vendorID'] : 0;
        $priorityResult = mysqli_query($conn, "SELECT id FROM task_priority ORDER BY id ASC LIMIT 1");
        $priorityRow = $priorityResult ? mysqli_fetch_assoc($priorityResult) : false;
        $taskPriorityID = $priorityRow ? (int)$priorityRow['id'] : 0;
        $taskSubject = mysqli_real_escape_string($conn, 'DHL NUMBER REQUIRED');
        $taskMessage = mysqli_real_escape_string($conn, 'Please send the DHL dispatch no for {product_name} {proforma_invoice}.<br><br>Thanks<br><br>Waqas');
        mysqli_query($conn, "INSERT INTO tasks (orderID,currentStatusID,subject,dueDate,priorityID,assignTo,isSecondReminder,taskMessage,taskSMS,taskSMSID,taskMessageID,repeatDate,sendBy,isExpired,createdDate) VALUES
        ('" . $alertOrderID . "','0','" . $taskSubject . "','" . $taskDueDate . "','" . $taskPriorityID . "','" . $taskAssignTo . "','0','" . $taskMessage . "','','0','0','1970-01-01','mail','0','" . currDateTimeGmt() . "')");
        $taskID = mysqli_insert_id($conn);
        if ($taskID > 0) {
            mysqli_query($conn, "UPDATE orders SET dhlAlertState='task_created',dhlAlertRemindAt=NULL WHERE id='" . $alertOrderID . "'");
            header("Location: tasks.php?action=view&id=" . (int)$taskID);
            exit;
        }
    }
}

if(isset($_GET['action']) && $_GET['action'] == "download"){
    if(isset($_GET['ordImg']) && $_GET['ordImg'] != ""){
        downloadFile('../img/'.$_GET['ordImg'],$_GET['ordImg']);
    }else{
        $sqlImgs = mysqli_query($conn,"SELECT orderImg FROM order_images WHERE id='" . $_GET['id'] . "'");
        $rowImgs = mysqli_fetch_assoc($sqlImgs);
        downloadFile('../img/'.$rowImgs['orderImg'],$rowImgs['orderImg']);
    }
}
if(isset($_POST['submit']) && $_POST['submit'] == "Submit"){
    if(isset($_POST['action']) && $_POST['action'] == "add"){
        $orderImgNm = "";
        $shippingDocImgNm = "";
        $uploadfile = "";
        $uploadfile2 = "";
        $imageName = $_FILES["orderImg"]["name"];
        if(!empty($imageName)){
            $orderImg = @pathinfo($imageName);
            $strDtMix = @date("d").substr((string)microtime(), 2, 6);
            $uploadfile = $strDtMix.".".pathinfo($imageName, PATHINFO_EXTENSION);
            $orderImgNm = $orderImg['filename'];
            move_uploaded_file($_FILES['orderImg']['tmp_name'], "../img/" . $uploadfile);
        }
        $imageName2 = $_FILES["shippingDocImg"]["name"];
        if(!empty($imageName2)){
            $shippingDocImg = @pathinfo($imageName2);
            $strDtMix = @date("d").substr((string)microtime(), 2, 6);
            $uploadfile2 = $strDtMix.".".pathinfo($imageName2, PATHINFO_EXTENSION);
            $shippingDocImgNm = $shippingDocImg['filename'];
            move_uploaded_file($_FILES['shippingDocImg']['tmp_name'], "../img/" . $uploadfile2);
        }
        $documentReceivedDateSql = !empty($_POST['documentReceivedDate']) ? "'" . mysqli_real_escape_string($conn, sqlDtFormat($_POST['documentReceivedDate'])) . "'" : "NULL";
        $submittedStatusID = !empty($_POST['statusID']) ? (int)$_POST['statusID'] : 1;
        $submittedUnitPrice = (isset($_POST['unitPrice']) && trim((string)$_POST['unitPrice']) !== '' && is_numeric($_POST['unitPrice'])) ? (float)$_POST['unitPrice'] : 0;
        $submittedTotalAmt = (isset($_POST['totalAmt']) && trim((string)$_POST['totalAmt']) !== '' && is_numeric($_POST['totalAmt'])) ? (float)$_POST['totalAmt'] : 0;
        mysqli_query($conn, "INSERT INTO orders (orderImgNm,shippingDocImgNm,documentDate,documentReceivedDate,lead_time,expiryDate,latestDateShipment,billNo,documentOrderNo,invoiceNo,shippingDocInfo,statusID,orderUOM,vendorID,customerID,productID,unitPrice,quantity,totalAmt,orderOrigin,orderDelievery,orderPackaging,orderTransport,dta,eta,newETA,paymentTerms,orderImg,shippingDocImg,bankInfo,termCondition,settlementSupplier,settlementApplicant,settlementIDs,createdDate) VALUES
('" . mysqli_real_escape_string($conn, $orderImgNm) . "','" . mysqli_real_escape_string($conn, $shippingDocImgNm) . "','" . mysqli_real_escape_string($conn, sqlDtFormat($_POST['documentDate'])) . "'," . $documentReceivedDateSql . ",'" . mysqli_real_escape_string($conn, (int)($_POST['lead_time'] ?? 0)) . "','" . mysqli_real_escape_string($conn, sqlDtFormat($_POST['expiryDate'] ?? '')) . "','" . mysqli_real_escape_string($conn, sqlDtFormat($_POST['latestDateShipment'] ?? '')) . "','" . mysqli_real_escape_string($conn, $_POST['billNo'] ?? '') . "','" . mysqli_real_escape_string($conn, $_POST['documentOrderNo'] ?? '') . "','" . mysqli_real_escape_string($conn, $_POST['invoiceNo'] ?? '') . "','" . mysqli_real_escape_string($conn, $_POST['shippingDocInfo'] ?? '') . "','" . $submittedStatusID . "','" . mysqli_real_escape_string($conn, $_POST['orderUOM'] ?? '') . "','" . mysqli_real_escape_string($conn, $_POST['vendorID']) . "','" . mysqli_real_escape_string($conn, $_POST['customerID']) . "','" . mysqli_real_escape_string($conn, $_POST['productID']) . "','" . $submittedUnitPrice . "','" . mysqli_real_escape_string($conn, $_POST['quantity'] ?? '') . "','" . $submittedTotalAmt . "','" . mysqli_real_escape_string($conn, $_POST['orderOrigin'] ?? '') . "','" . mysqli_real_escape_string($conn, $_POST['orderDelievery'] ?? '') . "','" . mysqli_real_escape_string($conn, $_POST['orderPackaging'] ?? '') . "','" . mysqli_real_escape_string($conn, $_POST['orderTransport'] ?? '') . "','" . mysqli_real_escape_string($conn, sqlDtFormat($_POST['dta'] ?? '')) . "','" . mysqli_real_escape_string($conn, sqlDtFormat($_POST['eta'] ?? '')) . "','" . mysqli_real_escape_string($conn, sqlDtFormat($_POST['newETA'] ?? '')) . "','" . mysqli_real_escape_string($conn, $_POST['paymentTerms'] ?? '') . "','" . $uploadfile . "','" . $uploadfile2 . "','" . mysqli_real_escape_string($conn, $_POST['bankInfo'] ?? '') . "','" . mysqli_real_escape_string($conn, $_POST['termCondition'] ?? '') . "',
'" . mysqli_real_escape_string($conn, $_POST['settlementSupplier']) . "','" . mysqli_real_escape_string($conn, $_POST['settlementApplicant']) . "','" . mysqli_real_escape_string($conn, @implode(",", (is_array($_POST['settlementIDs'] ?? null) ? $_POST['settlementIDs'] : array()))) . "','" . currDateTimeGmt() . "')");
$orderID = mysqli_insert_id($conn);
mysqli_query($conn, "UPDATE orders SET orderUUID=UUID() WHERE id='" . $orderID . "'");

$imagesName = isset($_FILES["orderImgs"]["name"]) ? $_FILES["orderImgs"]["name"] : null;
if(is_array($imagesName) && count($imagesName)>0){
    $sno = 0;
    foreach($imagesName as $imgsVal){
        $fileNm = @pathinfo($imagesName[$sno]);
        $strDtMix = @date("d").substr((string)microtime(), 2, 6);
        $uploadfile = $strDtMix.".".pathinfo($imagesName[$sno], PATHINFO_EXTENSION);
        move_uploaded_file($_FILES['orderImgs']['tmp_name'][$sno], "../img/" . $uploadfile);
        mysqli_query($conn,"INSERT INTO order_images (fileName,isOrder,orderImg,orderID) VALUES ('". $fileNm['filename'] ."','1','". $uploadfile ."','". $orderID ."')");
        $sno++;
    }
}
$imagesName2 = isset($_FILES["shippingDocImgs"]["name"]) ? $_FILES["shippingDocImgs"]["name"] : null;
if(is_array($imagesName2) && count($imagesName2)>0){
    $sno2 = 0;
    foreach($imagesName2 as $imgsVal2){
        $fileNm2 = @pathinfo($imagesName2[$sno2]);
        $strDtMix2 = @date("d").substr((string)microtime(), 2, 6);
        $uploadfile2 = $strDtMix2.".".pathinfo($imagesName2[$sno2], PATHINFO_EXTENSION);
        move_uploaded_file($_FILES['shippingDocImgs']['tmp_name'][$sno2], "../img/" . $uploadfile2);
        mysqli_query($conn,"INSERT INTO order_images (fileName,isOrder,orderImg,orderID) VALUES ('". $fileNm2['filename'] ."','0','". $uploadfile2 ."','". $orderID ."')");
        $sno2++;
    }
}
header("location: orders.php?act=1");

    }else if(isset($_POST['action']) && $_POST['action'] == "update"){
        $existingOrderID = (int)($_POST['id'] ?? 0);
        $existingAssociationsResult = mysqli_query($conn, "SELECT vendorID,customerID,productID,statusID,leadStage,tags FROM orders WHERE id='" . $existingOrderID . "' LIMIT 1");
        $existingAssociations = $existingAssociationsResult ? mysqli_fetch_assoc($existingAssociationsResult) : null;
        if (!$existingAssociations) {
            header("Location: orders.php");
            exit;
        }
        $sqlCondition2 = "";
        if (isset($_FILES["orderImg"]["name"]) && !empty($_FILES["orderImg"]["name"])) {
            $imageName2 = $_FILES["orderImg"]["name"];
            if(isset($_POST['preImageName']) && $_POST['preImageName']!="" )
                @unlink("../img/" . $_POST['preImageName']);

            $orderImg = @pathinfo($imageName2);
            $strDtMix = @date("d").substr((string)microtime(), 2, 6);
            $uploadfile2 = $strDtMix.".".pathinfo($imageName2, PATHINFO_EXTENSION);
            $orderImgNm = $orderImg['filename'];
            move_uploaded_file($_FILES['orderImg']['tmp_name'], "../img/" . $uploadfile2);
            $sqlCondition2 = "orderImgNm='" . mysqli_real_escape_string($conn, $orderImgNm) . "',orderImg='" . $uploadfile2 . "',";
        }

        $sqlCondition = "";
        if (isset($_FILES["shippingDocImg"]["name"]) && !empty($_FILES["shippingDocImg"]["name"])) {
            $imageName = $_FILES["shippingDocImg"]["name"];
            if(isset($_POST['preImageName2']) && $_POST['preImageName2']!="" )
                @unlink("../img/" . $_POST['preImageName2']);

            $shippingDocImg = @pathinfo($imageName);
            $strDtMix = @date("d").substr((string)microtime(), 2, 6);
            $uploadfile = $strDtMix.".".pathinfo($imageName, PATHINFO_EXTENSION);
            $shippingDocImgNm = $shippingDocImg['filename'];
            move_uploaded_file($_FILES['shippingDocImg']['tmp_name'], "../img/" . $uploadfile);
            $sqlCondition = "shippingDocImgNm='" . mysqli_real_escape_string($conn, $shippingDocImgNm) . "',shippingDocImg='" . $uploadfile . "',";
        }
        $documentReceivedDateSql = !empty($_POST['documentReceivedDate']) ? "'" . mysqli_real_escape_string($conn, sqlDtFormat($_POST['documentReceivedDate'])) . "'" : "NULL";
        $submittedStatusID = !empty($_POST['statusID']) ? (int)$_POST['statusID'] : 1;
        $submittedUnitPrice = (isset($_POST['unitPrice']) && trim((string)$_POST['unitPrice']) !== '' && is_numeric($_POST['unitPrice'])) ? (float)$_POST['unitPrice'] : 0;
        $submittedTotalAmt = (isset($_POST['totalAmt']) && trim((string)$_POST['totalAmt']) !== '' && is_numeric($_POST['totalAmt'])) ? (float)$_POST['totalAmt'] : 0;
        mysqli_query($conn, "UPDATE orders SET ".$sqlCondition2.$sqlCondition."documentDate='" . mysqli_real_escape_string($conn, sqlDtFormat($_POST['documentDate'])) . "',documentReceivedDate=" . $documentReceivedDateSql . ",lead_time='" . mysqli_real_escape_string($conn, (int)($_POST['lead_time'] ?? 0)) . "',expiryDate='" . mysqli_real_escape_string($conn, sqlDtFormat($_POST['expiryDate'] ?? '')) . "',latestDateShipment='" . mysqli_real_escape_string($conn, sqlDtFormat($_POST['latestDateShipment'] ?? '')) . "',billNo='" . mysqli_real_escape_string($conn, $_POST['billNo'] ?? '') . "',documentOrderNo='" . mysqli_real_escape_string($conn, $_POST['documentOrderNo'] ?? '') . "',invoiceNo='" . mysqli_real_escape_string($conn, $_POST['invoiceNo'] ?? '') . "',shippingDocInfo='" . mysqli_real_escape_string($conn, $_POST['shippingDocInfo'] ?? '') . "',statusID='" . $submittedStatusID . "',orderUOM='" . mysqli_real_escape_string($conn, $_POST['orderUOM'] ?? '') . "',vendorID='" . (int)$existingAssociations['vendorID'] . "',customerID='" . (int)$existingAssociations['customerID'] . "',productID='" . (int)$existingAssociations['productID'] . "',unitPrice='" . $submittedUnitPrice . "',quantity='" . mysqli_real_escape_string($conn, $_POST['quantity'] ?? '') . "',totalAmt='" . $submittedTotalAmt . "',orderOrigin='" . mysqli_real_escape_string($conn, $_POST['orderOrigin'] ?? '') . "',orderDelievery='" . mysqli_real_escape_string($conn, $_POST['orderDelievery'] ?? '') . "',orderPackaging='" . mysqli_real_escape_string($conn, $_POST['orderPackaging'] ?? '') . "',orderTransport='" . mysqli_real_escape_string($conn, $_POST['orderTransport'] ?? '') . "',dta='" . mysqli_real_escape_string($conn, sqlDtFormat($_POST['dta'] ?? '')) . "',eta='" . mysqli_real_escape_string($conn, sqlDtFormat($_POST['eta'] ?? '')) . "',newETA='" . mysqli_real_escape_string($conn, sqlDtFormat($_POST['newETA'] ?? '')) . "',paymentTerms='" . mysqli_real_escape_string($conn, $_POST['paymentTerms'] ?? '') . "',
bankInfo='" . mysqli_real_escape_string($conn, $_POST['bankInfo']) . "',termCondition='" . mysqli_real_escape_string($conn, $_POST['termCondition']) . "',settlementSupplier='" . mysqli_real_escape_string($conn, $_POST['settlementSupplier']) . "',settlementApplicant='" . mysqli_real_escape_string($conn, $_POST['settlementApplicant']) . "',settlementIDs='" . mysqli_real_escape_string($conn, @implode(",", (is_array($_POST['settlementIDs'] ?? null) ? $_POST['settlementIDs'] : array()))) . "' WHERE id='" . $_POST['id'] . "'");

        if ((int)$existingAssociations['statusID'] !== $submittedStatusID) {
            $newStatusResult = mysqli_query($conn, "SELECT status FROM status WHERE id='" . $submittedStatusID . "' LIMIT 1");
            $newStatusRow = $newStatusResult ? mysqli_fetch_assoc($newStatusResult) : null;
            $newStatusName = trim((string)($newStatusRow['status'] ?? ''));
            if ($newStatusName !== '') {
                $statusTags = json_decode((string)($existingAssociations['tags'] ?? ''), true);
                if (!is_array($statusTags)) $statusTags = array();
                $statusTags[] = date('j-M-y g:i A') . ' Status Change to ' . $newStatusName;
                $encodedStatusTags = mysqli_real_escape_string($conn, json_encode(array_values($statusTags), JSON_UNESCAPED_UNICODE));
                mysqli_query($conn, "UPDATE orders SET tags='" . $encodedStatusTags . "' WHERE id='" . $existingOrderID . "'");
            }
            if ($submittedStatusID === 2) {
                mysqli_query($conn, "UPDATE orders SET leadStage='Negotiation / Revision',leadArchived='0' WHERE id='" . $existingOrderID . "' AND leadStage IN ('New Inquiry','Sourcing / Supplier Contact','Quote Received & Submitted')");
            } elseif ($submittedStatusID === 3) {
                mysqli_query($conn, "UPDATE orders SET leadStage='PI Issued / Order Confirmed',leadArchived='0' WHERE id='" . $existingOrderID . "'");
            } elseif ($submittedStatusID === 4) {
                $pipelineStages = array('Sourcing / Supplier Contact', 'Quote Received & Submitted', 'Negotiation / Revision', 'PI Issued / Order Confirmed');
                if ((string)$existingAssociations['statusID'] === '1' || in_array((string)$existingAssociations['leadStage'], $pipelineStages, true)) {
                    mysqli_query($conn, "UPDATE orders SET leadStage='Deal Lost / Cancelled',leadArchived='1' WHERE id='" . $existingOrderID . "'");
                }
            } elseif ($submittedStatusID === 1 && (string)($existingAssociations['statusID'] ?? '') === '4') {
                mysqli_query($conn, "UPDATE orders SET leadStage='New Inquiry',leadArchived='0' WHERE id='" . $existingOrderID . "'");
            }
        }

$orderID = $_POST['id'];
$imagesName = isset($_FILES["orderImgs"]["name"]) ? $_FILES["orderImgs"]["name"] : null;
if(is_array($imagesName)){
    $sno = 0;
    foreach($imagesName as $imgsVal){
        if (!empty($imgsVal)) {
            $fileNm = @pathinfo($imagesName[$sno]);
            $strDtMix = @date("d").substr((string)microtime(), 2, 6);
            $uploadfile = $strDtMix.".".pathinfo($imagesName[$sno], PATHINFO_EXTENSION);
            move_uploaded_file($_FILES['orderImgs']['tmp_name'][$sno], "../img/" . $uploadfile);
            mysqli_query($conn,"INSERT INTO order_images (fileName,isOrder,orderImg,orderID) VALUES ('". $fileNm['filename'] ."','1','". $uploadfile ."','". $orderID ."')");
        }
        $sno++;
    }
}

$imagesName2 = isset($_FILES["shippingDocImgs"]["name"]) ? $_FILES["shippingDocImgs"]["name"] : null;
if(is_array($imagesName2)){
    $sno2 = 0;
    foreach($imagesName2 as $imgsVal2){
        if (!empty($imgsVal2)) {
            $fileNm2 = @pathinfo($imagesName2[$sno2]);
            $strDtMix2 = @date("d").substr((string)microtime(), 2, 6);
            $uploadfile2 = $strDtMix2.".".pathinfo($imagesName2[$sno2], PATHINFO_EXTENSION);
            move_uploaded_file($_FILES['shippingDocImgs']['tmp_name'][$sno2], "../img/" . $uploadfile2);
            mysqli_query($conn,"INSERT INTO order_images (fileName,isOrder,orderImg,orderID) VALUES ('". $fileNm2['filename'] ."','0','". $uploadfile2 ."','". $orderID ."')");
        }
        $sno2++;
    }
}
@header("location: orders.php?action=view&id=".$orderID);
}
}

if(isset($_GET['act']) && $_GET['act'] == "delete"){
    $sqlImgs = mysqli_query($conn,"SELECT * FROM order_images WHERE orderID='" . $_GET['id'] . "'");
    while($rowImgs = mysqli_fetch_assoc($sqlImgs)){
        @unlink("../img/" . $rowImgs['orderImg']);
        mysqli_query($conn,"DELETE FROM order_images WHERE id='" . $rowImgs['id'] . "'");
    }
    $sqlImg = mysqli_query($conn,"select orderImg,shippingDocImg FROM orders where id='" . $_GET['id'] . "'");
    $rowImg = mysqli_fetch_assoc($sqlImg);
    @unlink("../img/" . $rowImg['orderImg']);
    @unlink("../img/" . $rowImg['shippingDocImg']);

    mysqli_query($conn,"DELETE FROM tasks WHERE orderID='" . $_GET['id'] . "' ");
    mysqli_query($conn,"DELETE FROM order_notes WHERE orderID='" . $_GET['id'] . "' ");

    mysqli_query($conn,"DELETE FROM orders WHERE id='" . $_GET['id'] . "'");
    header("location: orders.php?act=3");
}

if(isset($_GET['act']) && $_GET['act'] == "imgDelete"){
    $sqlImg = mysqli_query($conn,"select * from order_images where id='" . $_GET['imgID'] . "'");
    $rowImg = mysqli_fetch_assoc($sqlImg);
    @unlink("../img/" . $rowImg['orderImg']);
    mysqli_query($conn,"DELETE FROM order_images WHERE id='" . $_GET['imgID'] . "'");
    header("Location: orders.php?action=edit&id=" . $_GET['id'] . "&act=1");
}

if(isset($_GET['act']) && $_GET['act'] == "imgDelete2"){
    @unlink("../img/" . $_GET['imgID']);
    mysqli_query($conn,"UPDATE orders SET orderImg='' WHERE id='" . $_GET['id'] . "'");
    header("Location: orders.php?action=edit&id=" . $_GET['id'] . "&act=1");
}
if(isset($_GET['act']) && $_GET['act'] == "imgDelete3"){
    @unlink("../img/" . $_GET['imgID']);
    mysqli_query($conn,"UPDATE orders SET shippingDocImg='' WHERE id='" . $_GET['id'] . "'");
    header("Location: orders.php?action=edit&id=" . $_GET['id'] . "&act=1");
}
if(isset($_GET['act']) && $_GET['act'] == "delDate"){
    $dateFields = array('dta','eta','newETA','documentDate','documentReceivedDate','expiryDate','latestDateShipment');
    if (isset($_GET['field']) && in_array($_GET['field'], $dateFields)) {
        if ($_GET['field'] == 'documentReceivedDate') {
            mysqli_query($conn,"UPDATE orders SET documentReceivedDate=NULL WHERE id='" . $_GET['id'] . "'");
        } else {
            $emptyDate = in_array($_GET['field'], array('dta','eta','newETA')) ? '--' : '0000-00-00';
            mysqli_query($conn,"UPDATE orders SET ".$_GET['field']." =>'" . $emptyDate . "' WHERE id='" . $_GET['id'] . "'");
        }
    }
    header("Location: orders.php?action=edit&id=" . $_GET['id'] . "&act=1");
}
$action = "add";
$hdTxt="Add New";
if(isset($_GET['action']) && $_GET['action'] == "edit"){
    $sqlEdit = mysqli_query($conn,"SELECT * FROM orders WHERE id='" . $_GET['id'] . "'");
    $rowsEdit = mysqli_fetch_assoc($sqlEdit);
    extract($rowsEdit);
    $action = "update";
    $hdTxt="Update";
} else {
    $invoiceNo = '';
    $unitPrice = '';
    $quantity = '';
    $totalAmt = '';
    $orderOrigin = '';
    $orderDelievery = '';
    $orderPackaging = '';
    $orderTransport = '';
    $dta = '';
    $eta = '';
    $newETA = '';
    $paymentTerms = '';
    $orderImg = '';
    $orderImgNm = '';
    $shippingDocImg = '';
    $shippingDocImgNm = '';
    $documentDate = '';
    $documentReceivedDate = '';
    $lead_time = '';
    $expiryDate = '';
    $latestDateShipment = '';
    $billNo = '';
    $documentOrderNo = '';
    $shippingDocInfo = '';
    $statusID = '';
    $orderUOM = '';
    $vendorID = '';
    $customerID = '';
    $productID = '';
    $bankInfo = '';
    $termCondition = '';
    $settlementSupplier = '';
    $settlementApplicant = '';
    $settlementIDs = '';
    $id = '';
}
include "include/header.php";

?>
<script>
function copyOrderFileUrl(button) {
    var input = button.parentNode.querySelector('.order-file-url');
    if (!input) return;

    var selectUrl = function () {
        input.focus();
        input.select();
        input.setSelectionRange(0, input.value.length);
    };
    var showResult = function (copied) {
        button.textContent = copied ? 'Copied' : 'Press Ctrl+C';
        selectUrl();
        window.setTimeout(function () { button.textContent = 'Copy URL'; }, 2000);
    };

    selectUrl();
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function' && window.isSecureContext) {
        navigator.clipboard.writeText(input.value).then(function () {
            showResult(true);
        }).catch(function () {
            var copied = false;
            try { copied = document.execCommand('copy'); } catch (error) {}
            showResult(copied);
        });
    } else {
        var copied = false;
        try { copied = document.execCommand('copy'); } catch (error) {}
        showResult(copied);
    }
}
</script>
<?php

if(isset($_GET['action']) && $_GET['action'] == "view"){
    $sqlEdit = mysqli_query($conn,"SELECT * FROM orders WHERE id='" . $_GET['id'] . "'");
    $rowsEdit = mysqli_fetch_assoc($sqlEdit);
    extract($rowsEdit);
    $orderNotes = array();
    $notesOrderID = mysqli_real_escape_string($conn, (string)$id);
    $notesResult = mysqli_query($conn, "SELECT id,orderTitle,createdDate FROM order_notes WHERE orderID='" . $notesOrderID . "' ORDER BY id ASC");
    while ($notesResult && ($noteRow = mysqli_fetch_assoc($notesResult))) {
        $orderNotes[] = $noteRow;
    }
    $shipmentPlanDate = '';
    if (!empty($documentReceivedDate) && $documentReceivedDate != '0000-00-00' && isset($lead_time) && (int)$lead_time > 0) {
        $shipmentPlanDate = date('Y-m-d', strtotime($documentReceivedDate . ' +' . (int)$lead_time . ' days'));
    }
    $dhlAlertVisible = false;
    $dhlAlertDays = 0;
    $dhlAlertEta = '';
    $dhlAlertState = isset($dhlAlertState) ? $dhlAlertState : '';
    $dhlAlertRemindAt = isset($dhlAlertRemindAt) ? $dhlAlertRemindAt : '';
    if (empty(trim((string)$shippingDocInfo)) && !empty($eta) && $eta !== '--' && $eta !== '0000-00-00') {
        $etaTimestamp = strtotime($eta);
        if ($etaTimestamp !== false) {
            $today = new DateTime('today');
            $etaDate = new DateTime(date('Y-m-d', $etaTimestamp));
            $dhlAlertDays = (int)$today->diff($etaDate)->format('%r%a');
            $dhlAlertEta = date('d M Y', $etaTimestamp);
            $dhlAlertVisible = $dhlAlertDays >= 0 && $dhlAlertDays <= 15
                && !in_array($dhlAlertState, array('ignored', 'task_created'), true)
                && ($dhlAlertState !== 'snoozed' || empty($dhlAlertRemindAt) || strtotime($dhlAlertRemindAt) <= time());
        }
    }
?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Orders</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>orders.php">Manage Orders</a></span></li>
<li><span>View Order</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<style>
.order-note-capsules { margin: 0 0 12px; padding: 14px 16px; border: 1px solid #e5e7eb; border-radius: 6px; background: #fafbfc; }
.order-note-capsule-list { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.order-note-capsule { display: inline-flex; align-items: center; gap: 8px; max-width: 100%; padding: 7px 12px; border: 1px solid #d8e3f0; border-radius: 999px; background: #fff; color: #34495e; line-height: 1.35; text-decoration: none; }
.order-note-capsule:hover, .order-note-capsule:focus { border-color: #7aa7d8; background: #f3f8fd; color: #245580; text-decoration: none; }
.order-note-add-button { flex: 0 0 auto; white-space: nowrap; }
.order-note-capsule-text { overflow-wrap: anywhere; }
.order-note-capsule-date { flex: 0 0 auto; color: #7f8c99; font-size: 11px; }
.order-note-capsule-empty { color: #7f8c99; font-size: 13px; }
@media (max-width: 767px) { .order-note-capsule { align-items: flex-start; border-radius: 12px; } }
</style>
<section class="order-note-capsules" aria-label="Order notes">
<?php if (isset($_GET['noteAdded']) && $_GET['noteAdded'] === '1'): ?>
<div class="alert alert-info" role="status"><strong>Note has been added successfully</strong></div>
<?php endif; ?>
<div class="order-note-capsule-list">
<?php if ($orderNotes): foreach ($orderNotes as $orderNote): ?>
    <a class="order-note-capsule" href="orderNotes.php?action=edit&amp;id=<?=rawurlencode((string)$orderNote['id']);?>&amp;oID=<?=rawurlencode((string)$id);?>" title="Edit this note">
        <span class="order-note-capsule-text"><?=htmlspecialchars((string)($orderNote['orderTitle'] ?? ''), ENT_QUOTES, 'UTF-8');?></span>
        <span class="order-note-capsule-date"><?=htmlspecialchars((string)viewDtTm($orderNote['createdDate'] ?? ''), ENT_QUOTES, 'UTF-8');?></span>
    </a>
<?php endforeach; else: ?>
    <span class="order-note-capsule-empty">No notes yet.</span>
<?php endif; ?>
    <a class="btn btn-primary btn-xs order-note-add-button" href="orderNotes.php?action=add&amp;oID=<?=rawurlencode((string)$id);?>">Add Note</a>
</div>
</section>
<header class="panel-heading">
<div class="panel-actions">
<a href="<?=$siteUrl;?>orders.php?action=edit&id=<?=$id?>"><b>Edit</b></a> |
<a style="margin:0 6px;color:#a94442" title="Delete this order" aria-label="Delete this order" onclick="if (!confirm('WARNING: This permanently deletes the order and its related notes, tasks, and files. This cannot be undone.')) return false; if (prompt('Type DELETE to confirm order deletion:') !== 'DELETE') { alert('Order deletion cancelled.'); return false; } return true;" href="<?=$siteUrl;?>orders.php?act=delete&amp;id=<?=(int)$id?>">Delete</a> |
<a href="<?=$siteUrl;?>orders.php"><b>Back</b></a>
</div>
<h2 class="panel-title">View Orders - <?=statusNm($statusID)?></h2>
</header>
<div class="panel-body viewFrm">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<?php if ($dhlAlertVisible): ?>
<div class="alert alert-warning" style="margin-bottom:20px;">
    <strong>DHL number required.</strong>
    ETA is <?=htmlspecialchars($dhlAlertEta, ENT_QUOTES, 'UTF-8')?><?=($dhlAlertDays === 0) ? ' today' : ' in ' . $dhlAlertDays . ' day' . ($dhlAlertDays == 1 ? '' : 's')?>.
    Please add the DHL/shipping number to this order.
    <input type="hidden" name="dhlAlertOrderID" value="<?= (int)$id ?>">
    <div style="margin-top:10px;">
        <button type="submit" name="dhlAlertAction" value="ignore" class="btn btn-default btn-sm">Ignore</button>
        <button type="submit" name="dhlAlertAction" value="create_task" class="btn btn-danger btn-sm">Create Task</button>
        <button type="submit" name="dhlAlertAction" value="remind" class="btn btn-info btn-sm">Remind Me Tomorrow</button>
    </div>
</div>
<?php endif; ?>
<?php
if($statusID=='8'){
    $msgTxt="";
    if($dta=="--"){ $msgTxt.='<p> - Missing ETD</p>';}
    if($eta=="--"){ $msgTxt.='<p> - Missing ETA</p>';}
    if($documentDate==""){ $msgTxt.='<p> - Document Date</p>';}
    if($expiryDate==""){ $msgTxt.='<p> - Expiry Date</p>';}
    if($latestDateShipment==""){ $msgTxt.='<p> - Latest Date Shipment</p>';}
    if($billNo==""){ $msgTxt.='<p> - Bill Number</p>';}
    if($documentOrderNo==""){ $msgTxt.='<p> - Document Order No</p>';}
    if($msgTxt){echo '<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong><p>Error Fields :-</p> '.$msgTxt.'</strong></div>';}
}
?>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Vendor</label>

<div class="col-md-8"><a href="vendors.php?action=view&id=<?=$vendorID?>"><?=companyName($vendorID);?>

</br><a href="https://web.whatsapp.com/send?phone=<?=whatsApp($vendorID);?>&text&app_absent=0" target="_blank">WhatsApp</a></a></div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" for="inputDefault">Customer</label>
<div class="col-md-9"><a href="customers.php?action=view&id=<?=$customerID?>"><?=companyName($customerID);?></br><a href="https://web.whatsapp.com/send?phone=<?=whatsApp($customerID);?>&text&app_absent=0" target="_blank">WhatsApp</a></a></div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Order UUID</label>
<div class="col-md-9"><?=htmlspecialchars(isset($orderUUID) && $orderUUID !== '' ? $orderUUID : '—', ENT_QUOTES, 'UTF-8');?></div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Proforma Invoice</label>
<div class="col-md-8"><?=$invoiceNo;?></div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" for="inputDefault">H.S. Code</label>
<div class="col-md-9"><?=productHSCode($productID)?></div>
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Product</label>
<div class="col-md-8"><?=productNm($productID);?></div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" for="inputDefault">Status</label>
<div class="col-md-9"><?=statusNm($statusID)?></div>
</div>
</div>
<div class="form-group">
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Unit Price</label>
<div class="col-md-9"><?=$unitPrice;?>/<?=$orderUOM;?></div>
</div></div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Quantity <br />(Per Container)</label>
<div class="col-md-8"><?=$quantity;?></div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" for="inputDefault">Total Amt</label>
<div class="col-md-8">$<?=$totalAmt;?></div>
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Origin</label>
<div class="col-md-8"><?=$orderOrigin;?></div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" for="inputDefault">Delievery</label>
<div class="col-md-8"><?=$orderDelievery;?></div>
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Packaging</label>
<div class="col-md-8"><?=$orderPackaging;?></div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" for="inputDefault">Transport</label>
<div class="col-md-9"><?=$orderTransport;?></div>
</div>
</div>
<div class="form-group">
<div class="col-md-4">
<label class="col-md-6 control-label" for="inputDefault">ETD</label>
<div class="col-md-4"><?=($dta=="--")?'<span class="label label-danger">Missing ETD</span>':viewDateFormat($dta).viewByDaysFormat($dta)?></div>
</div>
<div class="col-md-4">
<label class="col-md-4 control-label" for="inputDefault">ETA</label>
<div class="col-md-8"><?=($eta=="--")?'<span class="label label-danger">Missing ETA</span>':viewDateFormat($eta).viewByDaysFormat($eta)?><?=($eta !="--") ? '<button style="none" id="refreshButton">Refresh</button>': ''; ?> <!-- Display response container -->
<div style="font-size:10px" id="responseContainer"></div>
</div>



</div>
<div class="col-md-4">
<label class="col-md-4 control-label" for="inputDefault">New ETA</label>
<div class="col-md-8"><?=($newETA!="")?viewDateFormat($newETA):' '?>

</div>
</div>
</div>


<?php if($eta!="--" && $dta!="--"){

    $getRD = getPerDate($eta,$dta); if($getRD < 101 && $getRD >-1 && $dta !="--" && $eta !="--" && !(timeEst($dta) > 0))   { ?>
<div class="form-group">
<div class="col-md-2"></div>
<div class="col-md-8"><div class="progress">
  <div class="progress-bar" role="progressbar" aria-valuenow="70"
  aria-valuemin="0" aria-valuemax="100" style="background:<?php echo ($getRD < 70)? 'green' : 'red'; ?>; width:<?php echo getPerDate($eta,$dta).'%'; ?>">
<?php echo ($getRD < 70)? ''.getPerDate($eta,$dta).'%' : 'NEAR TO ARRIVAL .... '.getPerDate($eta,$dta).'%'; ?>
  </div>
</div></div>
<div class="col-md-2"></div>
</div>
<?php } } ?>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Payment Terms</label>
<div class="col-md-9"><?=$paymentTerms;?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Tags</label>
<div class="col-md-9">
    
    <style>
input[type="text"] {
    border: 0px;
}
button {
border:0px;
    font-weight:700;
    font-family:sans-serif; 

    
}

        .btn {
            background-color: #4CAF50; /* Green background color */
            border: none; /* Remove borders */
            color: white; /* White text color */
            padding: 10px 20px; /* Padding */
            margin: 5px; /* Margin between buttons */
            cursor: pointer; /* Cursor style on hover */
        }

        .btn:hover {
            background-color: #45a049; /* Darker green on hover */
        }
        
        .data-item {
            background: #2db34a;
  transition-property: background;
  transition-duration: 1s;
  transition-timing-function: linear;
            display:flex;
            width:fit-content;
    background-color: #4CAF50;
    color: white;
    padding: 6px;
    margin-right: 10px !important;
    margin-bottom: 10px;
    border-radius: 4px;
    text-align: center;
}

div#result {
    display: flex;
    flex-direction: row;
        margin-bottom: 10px;
  
    flex-wrap: wrap;

  
}

    </style>
    <div id="result"></div>

    
 <input type="text" style="margin-bottom:10px" id="addData" placeholder="Enter data to add">
    <form id="addForm">
        <button type="button" id="addButton">(+)</button>
        <button type="button" id="removeButton">(-)</button>
    </form>

    


</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputHelpText">Order Image</label>
<div class="col-md-9">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="45%">Documents</th>
<th width="30%">Action</th>
</tr>
</thead>
<tbody>
<?php
$sno=1;
if($orderImg!=""){
$copyFileUrlButton = ordersCopyFileUrlButton($orderImg);
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.(($orderImgNm!="")?$orderImgNm:$orderImg).'</td>
<td><a title="Download this image" href="orders.php?action=download&ordImg='.$orderImg.'"><i class="fa fa-download"></i> Download</a> '.$copyFileUrlButton.'</td>
</tr>';
}
$sqlImg2 = mysqli_query($conn,"SELECT * FROM  order_images WHERE isOrder='1' AND orderID='" . $_GET['id'] . "'");
$numRowImgs2 = mysqli_num_rows($sqlImg2);
if($numRowImgs2>0){
while($rowImg2 = mysqli_fetch_assoc($sqlImg2)){
$sno++;
$copyFileUrlButton = ordersCopyFileUrlButton($rowImg2['orderImg']);
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.(($rowImg2['fileName'])?$rowImg2['fileName']:$rowImg2['orderImg']).'</td>
<td><a title="Download this Doc" href="orders.php?action=download&id='.$rowImg2['id'].'"><i class="fa fa-download"></i> Download</a> '.$copyFileUrlButton.'</td>
</tr>';
}
}
?>
</tbody>
</table>
</div>
</div>

<div class="form-group">
<div class="col-md-4">
<label class="col-md-6 control-label" for="inputDefault">Document Date</label>
<div class="col-md-6">
<?=($documentDate!="")?viewDateFormat($documentDate).viewByDaysFormat($documentDate):''?>
</div>
</div>
<div class="col-md-4">
<label class="col-md-4 control-label" for="inputDefault">Expiry Date</label>
<div class="col-md-8">
<?=($expiryDate!="")?viewDateFormat($expiryDate).viewByDaysFormat($expiryDate):''?>
</div>
</div>
<div class="col-md-4">
<label class="col-md-6 control-label" for="inputDefault">Latest Date Shipment</label>
<div class="col-md-6">
<?=($latestDateShipment!="")?viewDateFormat($latestDateShipment).viewByDaysFormat($latestDateShipment):''?>
</div>
</div>
</div>
<div class="form-group">
<div class="col-md-4">
<label class="col-md-6 control-label" for="inputDefault">Document Received</label>
<div class="col-md-6">
<?=($documentReceivedDate!="")?viewDateFormat($documentReceivedDate).viewByDaysFormat($documentReceivedDate):''?>
</div>
</div>
<div class="col-md-4">
<label class="col-md-4 control-label" for="inputDefault">Lead Time</label>
<div class="col-md-8">
<?=((isset($lead_time) && (int)$lead_time > 0)?(int)$lead_time.' Days':'')?>
</div>
</div>
<div class="col-md-4">
<label class="col-md-6 control-label" for="inputDefault">Shipment Plan Date</label>
<div class="col-md-6">
<?=($shipmentPlanDate!="")?viewDateFormat($shipmentPlanDate).viewByDaysFormat($shipmentPlanDate):''?>
</div>
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Bill of Lading</label>
<div class="col-md-8" id="demo">
<?=$billNo?> </br><br>

<?=(!empty($billNo)) ? '<button id="trackButton">Track Container</button>' : ''; ?>
<!-- Loading message -->
<div id="loading" style="display: none;">Loading...</div>

<a href="https://www.track-trace.com/bol"><?php if(!empty($billNo)) { echo "Track & Trace"; } ?></a>
</div>
</div>
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Document Order No</label>
<div class="col-md-8">
<?=$documentOrderNo?>
</div>
</div>
</div>
<?php $shippingDocInfoValue = isset($shippingDocInfo) ? trim((string)$shippingDocInfo) : ''; ?>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Shipping Info</label>
<div class="col-md-9"><?=htmlspecialchars($shippingDocInfoValue, ENT_QUOTES, 'UTF-8');?>
<?php if ($shippingDocInfoValue !== ''): ?>
<a class="btn btn-info btn-sm" href="dhl_tracking.php?id=<?=urlencode($orderUUID)?>" target="_blank" rel="noopener">Show DHL API Details</a>
<a href="https://www.dhl.com/pk-en/home/tracking/tracking-express.html?submit=1&amp;tracking-id=<?=urlencode($shippingDocInfoValue)?>" target="_blank" rel="noopener">Track DHL</a>
<?php endif; ?></div>
</div>




<div class="form-group">
<label class="col-md-2 control-label" for="inputHelpText">Shipping Docs</label>
<div class="col-md-9">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="45%">Documents</th>
<th width="30%">Action</th>
</tr>
</thead>
<tbody>
<?php
$sno=1;
if($shippingDocImg!=""){
$copyFileUrlButton = ordersCopyFileUrlButton($shippingDocImg);
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.(($shippingDocImgNm!="")?$shippingDocImgNm:$shippingDocImg).'</td>
<td><a title="Download this image" href="orders.php?action=download&ordImg='.$shippingDocImg.'"><i class="fa fa-download"></i> Download</a> '.$copyFileUrlButton.'</td>
</tr>';
}
$sqlImg2 = mysqli_query($conn,"SELECT * FROM  order_images WHERE isOrder='0' AND orderID='" . $_GET['id'] . "'");
$numRowImgs2 = mysqli_num_rows($sqlImg2);
if($numRowImgs2>0){
while($rowImg2 = mysqli_fetch_assoc($sqlImg2)){
$sno++;
$copyFileUrlButton = ordersCopyFileUrlButton($rowImg2['orderImg']);
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.(($rowImg2['fileName'])?$rowImg2['fileName']:$rowImg2['orderImg']).'</td>
<td><a title="Download this Doc" href="orders.php?action=download&id='.$rowImg2['id'].'"><i class="fa fa-download"></i> Download</a> '.$copyFileUrlButton.'</td>
</tr>';
}
}
?>
</tbody>
</table>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Bank Information</label>
<div class="col-md-9"><?=$bankInfo?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Term &amp; Conditions </label>
<div class="col-md-9"><?=$termCondition?></div>
</div>
<div class="settlementBg">
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">&nbsp;</label>
<div class="col-md-9"><b><a id="settlementLnk" href="javascript:void(0)">Settlement</a></b></div>
</div>
<script type="text/javascript">
$( document ).ready(function() {
$("#settlementLnk").click(function(){
$(".settlementInfo").toggle();
});
});
</script>
<div class="settlementInfo" style="display:none">
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Supplier</label>
<div class="col-md-8">$<?=$settlementSupplier?> per metric tons
</div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" for="inputDefault">Applicant</label>
<div class="col-md-8">$<?=$settlementApplicant?> per metric tons
</div>
</div>
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Total:</label>
<div class="col-md-8">$<?php echo get_total($settlementSupplier, $quantity); ?> per consignment
</div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" for="inputDefault">Total:</label>
<div class="col-md-8">$<?php echo get_total($settlementApplicant, $quantity); ?> per consignment
</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Settlements</label>
<div class="col-md-9">
<?php $settlementAry = explode(",",$settlementIDs); ?>
<input type="checkbox" name="settlementIDs[]" value="1" <?=(in_array(1,$settlementAry)?'checked="checked"':'');?> > Remittance from supplier &nbsp; 
<input type="checkbox" name="settlementIDs[]" value="2" <?=(in_array(2,$settlementAry)?'checked="checked"':'');?> > On Arrival from Applicant &nbsp; 
<input type="checkbox" name="settlementIDs[]" value="3" <?=(in_array(3,$settlementAry)?'checked="checked"':'');?> > Licensed</div>
</div>
<hr />
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Created Date</label>
<div class="col-md-9"><?=viewDtTm($createdDate)?></div>
</div>

<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Tasks</label>
<div class="col-md-10">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="25%">Subject</th>
<th width="10%">Due Date</th>
<th width="10%">Priority</th>
<th width="28%">Assign To</th>
<th width="22%">Created On</th>
</tr>
</thead>
<tbody>
<?php
$sqlTask = mysqli_query($conn,"SELECT * FROM tasks WHERE orderID='" . $rowsEdit['id'] . "'  ORDER BY id ASC");
$numRowsTask = mysqli_num_rows($sqlTask);
if($numRowsTask>0){
$sno=0;
while($rows=mysqli_fetch_assoc($sqlTask)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td><a href="tasks.php?action=view&id='.$rows['id'].'">'.stringTxtLimit($rows['subject'],50).'</a></td>
<td>'.viewDateFormat($rows['dueDate']).'</td>
<td>'.priorityNm($rows['priorityID']).'</td>
<td>';
$assignToAry = @explode(",",$rows['assignTo']);
$asignToStr = array();
foreach($assignToAry as $assignToVal){
$asignToStr[] = customerName($assignToVal);
}
echo @implode(", ",$asignToStr);

echo '</td>
<td align="center">'.viewDtTm($rows['createdDate']).'</td>
</tr>';
}
}else{
echo "<tr>
<td colspan='8' align='center'>No Data Found.</td>
</tr>";
}
?>
</tbody>
</table>
<p align="center"><a href="tasks.php?action=add&orderID=<?=$id?>"><b>Add Task</b></a></p>
</div>
</div>

<div class="form-group">
<label class="col-md-5 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<button type="button" onclick="window.location.href='<?=$siteUrl;?>orders.php'" class="btn btn-default">Back</button>
</div>
</div>
</form>
</div>
</section>
</div>
</div>
</section>
</div>
</section>
<?php
}elseif(isset($_GET['action']) && ($_GET['action'] == "add" || $_GET['action'] == "edit")){
?>
<script src="<?=$siteUrl?>assets/vendor/summernote/summernote.js"></script>
<link rel="stylesheet" href="<?=$siteUrl?>assets/vendor/summernote/summernote.css" />
<link rel="stylesheet" href="<?=$siteUrl?>assets/vendor/summernote/summernote-bs3.css" />
<script type="text/javascript">
function createGridDocs(tid){ var objTable = document.getElementById(tid); var tablerows=parseInt(objTable.rows.length); var objTableRow = objTable.insertRow(tablerows); var tabrow = tablerows; var i = tablerows; objTableRow.id = "row_c_"+i; var objTableRowCell0 = objTableRow.insertCell(0); objTableRowCell0.innerHTML="<input type='file' name='orderImgs[]' id='class_"+i+"' />"; var objTableRowCell1 = objTableRow.insertCell(1); objTableRowCell1.innerHTML="<a href='#class_"+i+"' onclick='removeRow1(this)' class='btn btn-default' title='Delete'>Delete</a> "; }
function removeRow1(src){ var i=src.parentNode.parentNode.rowIndex; var objTable = document.getElementById('table_exend'); var objTableRows = parseInt(objTable.rows.length); var objLastRow; document.getElementById("table_exend").deleteRow(i); }
function createGridDocs2(tid){ var objTable = document.getElementById(tid); var tablerows=parseInt(objTable.rows.length); var objTableRow = objTable.insertRow(tablerows); var tabrow = tablerows; var i = tablerows; objTableRow.id = "row_c_"+i; var objTableRowCell0 = objTableRow.insertCell(0); objTableRowCell0.innerHTML="<input type='file' name='shippingDocImgs[]' id='class2_"+i+"' />"; var objTableRowCell1 = objTableRow.insertCell(1); objTableRowCell1.innerHTML="<a href='#class2_"+i+"' onclick='removeRow12(this)' class='btn btn-default' title='Delete'>Delete</a> "; }
function removeRow12(src){ var i=src.parentNode.parentNode.rowIndex; var objTable = document.getElementById('table_exend2'); var objTableRows = parseInt(objTable.rows.length); var objLastRow; document.getElementById("table_exend2").deleteRow(i); }
</script>
<link rel="stylesheet" href="<?=$siteUrl?>assets/bootstrap-select.css">
<script src="<?=$siteUrl?>assets/bootstrap-select.js"></script>
<script type="text/javascript">
$(document).ready(function(){
$(".dta").datepicker({todayHighlight:true,autoclose:true});
$(".eta").datepicker({todayHighlight:true,autoclose:true});
$(".newETA").datepicker({todayHighlight:true,autoclose:true});
$(".documentDate").datepicker({todayHighlight:true,autoclose:true});
$(".documentReceivedDate").datepicker({todayHighlight:true,autoclose:true});
$(".expiryDate").datepicker({todayHighlight:true,autoclose:true});
$(".latestDateShipment").datepicker({todayHighlight:true,autoclose:true});
function loadVendorProducts(vendorID){
    var productSelect = $("#productID");
    if(!vendorID){
        productSelect.html('<option value="">- Select vendor first -</option>');
        return;
    }
    productSelect.html('<option value="">Loading products...</option>');
    $.ajax({
        url:"prodAjax.php?act=vendorProducts&vendorID=" + encodeURIComponent(vendorID),
        type:"GET",
        success:function(data){ productSelect.html(data); },
        error:function(){ productSelect.html('<option value="">- Unable to load products -</option>'); }
    });
}
function updatePartyNotes() {
    [['vendorID', 'vendorNoteContent', 'Select a vendor to view its notes.'], ['customerID', 'customerNoteContent', 'Select a customer to view its notes.']].forEach(function(item) {
        var select = document.getElementById(item[0]);
        var target = document.getElementById(item[1]);
        if (!select || !target) return;
        var selectedOption = select.options[select.selectedIndex];
        var notes = selectedOption ? (selectedOption.getAttribute('data-notes') || '').trim() : '';
        target.textContent = notes || (select.value ? 'No notes recorded.' : item[2]);
        target.classList.toggle('text-muted', !notes);
    });
}
$("#vendorID").change(function(){
    loadVendorProducts($(this).val());
    updatePartyNotes();
});
$("#customerID").change(function(){
    updatePartyNotes();
});
$("#productID").change(function(){
    $.ajax({
        url:"prodAjax.php?act=prod&id=" + $(this).val(),
        type: "GET",
        success: function(data){
            var x = data;
            var prdAry=x.split('|||');
            $("#quantity").val(prdAry[0]);
            $("#orderOrigin").val(prdAry[1]);
            $("#orderPackaging").val(prdAry[2]);
        }
    });
});
$("#unitPrice").change(function(){
    var unitPrc = $(this).val();
    var quantity = $("#quantity").val();
    $("#totalAmt").val(unitPrc*quantity);
});
$("#quantity").change(function(){
    var unitPrc = $("#unitPrice").val();
    var quantity = $(this).val();
    $("#totalAmt").val(unitPrc*quantity);
});
updatePartyNotes();
});
</script>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Orders</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>orders.php">Manage Orders</a></span></li>
<li><span><?=$hdTxt;?> Order</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act'])){
    if($_GET['act']=='1'){
        $msgTxt='Order Image has been deleted successfully';
    }
    echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.$msgTxt.'</strong></div>';
}
?>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>orders.php"><b>Back</b></a> </div>
<h2 class="panel-title"><?=$hdTxt;?> Orders</h2>
</header>
<div class="panel-body">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<?php if ($action === 'add'): ?>
<div class="alert alert-info" id="partyNotesPanel" style="margin:0 0 20px;padding:14px 16px;border-left:4px solid #4b91c8;background:#f3f8fc">
<strong style="display:block;margin-bottom:10px">Vendor and customer notes</strong>
<div class="row">
<div class="col-md-6"><div style="font-weight:600;margin-bottom:4px">Vendor notes</div><div id="vendorNoteContent" style="white-space:pre-wrap;overflow-wrap:anywhere">Select a vendor to view its notes.</div></div>
<div class="col-md-6"><div style="font-weight:600;margin-bottom:4px">Customer notes</div><div id="customerNoteContent" style="white-space:pre-wrap;overflow-wrap:anywhere">Select a customer to view its notes.</div></div>
</div>
</div>
<?php endif; ?>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Vendor *</label>
<div class="col-md-9 selectSrch">
<select class="selectpicker form-control" data-selected-text-format="values" name="vendorID" id="vendorID" data-live-search="true" data-live-search-placeholder="Search" required <?=$action === 'update' ? 'disabled' : '';?>>
<option value="">- Select -</option>
<?php
$sqlVndr = mysqli_query($conn,"SELECT id,companyName,moreInfo FROM customers WHERE type='2' ORDER BY companyName ASC");
while($rowsVndr = mysqli_fetch_assoc($sqlVndr)){
    echo '<option value="'.(int)$rowsVndr['id'].'" data-notes="'.htmlspecialchars((string)($rowsVndr['moreInfo'] ?? ''), ENT_QUOTES, 'UTF-8').'" '.((int)$rowsVndr['id']==(int)$vendorID?'selected="selected"':'').' >'.htmlspecialchars((string)$rowsVndr['companyName'], ENT_QUOTES, 'UTF-8').'</option>';
}
?>
</select>
<?php if ($action === 'update'): ?><input type="hidden" name="vendorID" value="<?=(int)$vendorID;?>"><?php else: ?>
<button type="button" class="btn btn-primary" style="margin-top:8px;padding:8px 16px" onclick="openQuickParty('vendor')">Add Vendor</button>
<?php endif; ?>
</div></div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Customer *</label>
<div class="col-md-9 selectSrch">
<select class="selectpicker form-control" data-selected-text-format="values" name="customerID" id="customerID" data-live-search="true" data-live-search-placeholder="Search" required <?=$action === 'update' ? 'disabled' : '';?>>
<option value="">- Select -</option>
<?php
$sqlCust = mysqli_query($conn,"SELECT id,companyName,moreInfo FROM customers WHERE type='1' ORDER BY applicantName ASC");
while($rowsCust = mysqli_fetch_assoc($sqlCust)){
    echo '<option value="'.(int)$rowsCust['id'].'" data-notes="'.htmlspecialchars((string)($rowsCust['moreInfo'] ?? ''), ENT_QUOTES, 'UTF-8').'" '.((int)$rowsCust['id']==(int)$customerID?'selected="selected"':'').' >'.htmlspecialchars((string)$rowsCust['companyName'], ENT_QUOTES, 'UTF-8').'</option>';
}
?>
</select>
<?php if ($action === 'update'): ?><input type="hidden" name="customerID" value="<?=(int)$customerID;?>"><?php else: ?>
<button type="button" class="btn btn-success" style="margin-top:8px;padding:8px 16px" onclick="openQuickParty('customer')">Add Customer</button>
<?php endif; ?>
</div></div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Proforma Invoice</label>
<div class="col-md-9">
<input type="text" class="form-control" name="invoiceNo" value="<?=$invoiceNo?>" id="inputDefault" >
</div>
</div>

<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Product *</label>
<div class="col-md-9">
<select class="form-control" name="productID" id="productID" required <?=$action === 'update' ? 'disabled' : '';?>>
<?php
if(!empty($vendorID)){
    echo '<option value="">- Select -</option>';
    $sqlPrd = mysqli_query($conn,"SELECT id,prodName FROM products WHERE assignToVendor='".(int)$vendorID."' ORDER BY prodName ASC");
    while($rowsPrd = mysqli_fetch_assoc($sqlPrd)){
        echo '<option value="'.$rowsPrd['id'].'" '.($rowsPrd['id']==$productID?'selected="selected"':'').' >'.htmlspecialchars($rowsPrd['prodName'], ENT_QUOTES, 'UTF-8').'</option>';
    }
}else{
    echo '<option value="">- Select vendor first -</option>';
}
?>
</select>
<?php if ($action === 'update'): ?><input type="hidden" name="productID" value="<?=(int)$productID;?>"><?php endif; ?>
</div></div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Status (defaults to Lead)</label>
<div class="col-md-9 selectSrch">
<select class="form-control" name="statusID" >
<option value="">- Select -</option>
<?php
$sqlStus = mysqli_query($conn,"SELECT id,status FROM status ORDER BY id ASC");
while($rowsStus = mysqli_fetch_assoc($sqlStus)){
    echo '<option value="'.$rowsStus['id'].'" '.($rowsStus['id']==$statusID?'selected="selected"':'').' >'.$rowsStus['status'].'</option>';
}
?>
</select>
</div></div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Unit Price</label>
<div class="col-md-8">
<input type="text" class="form-control" name="unitPrice" value="<?=((float)$unitPrice == 0 ? '' : htmlspecialchars((string)$unitPrice, ENT_QUOTES, 'UTF-8'))?>" id="unitPrice"  >
</div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" for="inputDefault">UOM</label>
<div class="col-md-8">
<input type="text" class="form-control" name="orderUOM" value="<?=$orderUOM?>" id="inputDefault"  >
</div>
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Quantity</label>
<div class="col-md-8">
<input type="text" class="form-control" name="quantity" value="<?=$quantity?>" id="quantity" ><span class="hintTxt">Per Container</span>
</div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" style="padding-left:0;padding-right:0" for="inputDefault">Total Amt</label>
<div class="col-md-8">
<input type="text" class="form-control" name="totalAmt" value="<?=((float)$totalAmt == 0 ? '' : htmlspecialchars((string)$totalAmt, ENT_QUOTES, 'UTF-8'))?>" id="totalAmt" >
</div>
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Origin</label>
<div class="col-md-8">
<input type="text" class="form-control" name="orderOrigin" value="<?=$orderOrigin?>" id="orderOrigin" >
</div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" for="inputDefault">Delievery</label>
<div class="col-md-8">
<input type="text" class="form-control" name="orderDelievery" value="<?=$orderDelievery?>" id="inputDefault" >
</div>
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Packaging</label>
<div class="col-md-8">
<input type="text" class="form-control" name="orderPackaging" value="<?=$orderPackaging?>" id="orderPackaging" >
</div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" for="inputDefault">Transport</label>
<div class="col-md-8">
<input type="text" class="form-control" name="orderTransport" value="<?=$orderTransport?>" id="inputDefault" >
</div>
</div>
</div>
<div class="form-group">
<div class="col-md-4">
<label class="col-md-6 control-label" for="inputDefault">ETD</label>
<div class="col-md-6">
<input type="text" class="form-control dta" readonly="readonly" data-date-format="dd-mm-yyyy" name="dta" value="<?=($dta!="")?viewDateFormat($dta):''?>" id="inputDefault" >
<?=(isset($dta) && $dta!="--")?'<a title="Remove this date" onclick="return confirm(\'Are you sure you want to remove?\');" href="orders.php?action=edit&id='.$id.'&act=delDate&field=dta"><i class="fa fa-times delIcon"></i> Remove</a>':'';?>
</div>
</div>
<div class="col-md-4">
<label class="col-md-4 control-label" for="inputDefault">ETA</label>
<div class="col-md-8">
<input type="text" class="form-control eta" readonly="readonly" data-date-format="dd-mm-yyyy" name="eta" value="<?=($eta!="")?viewDateFormat($eta):''?>" id="inputDefault" >
<?=(isset($eta) && $eta!="--")?'<a title="Remove this date" onclick="return confirm(\'Are you sure you want to remove?\');" href="orders.php?action=edit&id='.$id.'&act=delDate&field=eta"><i class="fa fa-times delIcon"></i> Remove</a>':'';?>
</div>
</div>
<div class="col-md-4">
<label class="col-md-4 control-label" for="inputDefault">New ETA</label>
<div class="col-md-8">
<input type="text" class="form-control newETA" readonly="readonly" data-date-format="dd-mm-yyyy" name="newETA" value="<?=($newETA!="")?viewDateFormat($newETA):''?>" id="inputDefault" >
<?=(isset($newETA) && $newETA!="--")?'<a title="Remove this date" onclick="return confirm(\'Are you sure you want to remove?\');" href="orders.php?action=edit&id='.$id.'&act=delDate&field=newETA"><i class="fa fa-times delIcon"></i> Remove</a>':'';?>
</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Payment Terms</label>
<div class="col-md-9">
<input type="text" class="form-control" name="paymentTerms" value="<?=$paymentTerms?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputHelpText">Order Image</label>
<div class="col-md-9">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="45%">Documents</th>
<th width="30%">Action</th>
</tr>
</thead>
<tbody>
<?php
$sno=1;
if ($action == 'update') {
    if($orderImg!=""){
    $copyFileUrlButton = ordersCopyFileUrlButton($orderImg);
    echo '<tr>
    <td>&nbsp; '.$sno.'</td>
    <td><a href="'.$siteUrlMain."img/" . $orderImg.'" target="_blank" >'.(($orderImgNm)?$orderImgNm:$orderImg).'</a></td>
    <td><a title="Delete this image" onclick="return confirm(\'Are you sure you want to delete?\');" href="orders.php?action=edit&id='.$id.'&act=imgDelete2&imgID='.$orderImg.'"><i class="fa fa-times delIcon"></i> Delete</a> '.$copyFileUrlButton.'</td>
    </tr>';
    }
    $sqlImg = mysqli_query($conn,"SELECT * FROM order_images WHERE isOrder='1' AND orderID='" . $id . "'");
    $numRowImgs = mysqli_num_rows($sqlImg);
    if($numRowImgs>0){
        while($rowImg = mysqli_fetch_assoc($sqlImg)){
            $sno++;
            $copyFileUrlButton = ordersCopyFileUrlButton($rowImg['orderImg']);
            echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td><a href="'.$siteUrlMain."img/" . $rowImg['orderImg'].'" target="_blank" >'.(($rowImg['fileName'])?$rowImg['fileName']:$rowImg['orderImg']).'</a></td>
<td><a title="Delete this image" onclick="return confirm(\'Are you sure you want to delete?\');" href="orders.php?action=edit&id='.$id.'&act=imgDelete&imgID='.$rowImg['id'].'"><i class="fa fa-times delIcon"></i> Delete</a> '.$copyFileUrlButton.'</td>
</tr>';
        }
    }
}
?>
</tbody>
</table>
<?php
if($orderImg==""){  echo '<input type="file" class="form-control" name="orderImg"  /><br />'; } ?>
<a href="javascript:void(0)" name="lnk" id="lnk" class="btn btn-default" onClick="createGridDocs('table_exend');" >Add More Images</a>
<table id="table_exend" border="1px" cellpadding="0">
</table>
</div>
</div>
<div class="form-group">
<div class="col-md-4">
<label class="col-md-6 control-label" for="inputDefault">Document Date</label>
<div class="col-md-6">
<input type="text" class="form-control documentDate" readonly="readonly" data-date-format="dd-mm-yyyy" name="documentDate" value="<?=($documentDate!="")?viewDateFormat($documentDate):''?>" id="inputDefault" >
<?=(isset($documentDate) && $documentDate!="--")?'<a title="Remove this date" onclick="return confirm(\'Are you sure you want to remove?\');" href="orders.php?action=edit&id='.$id.'&act=delDate&field=documentDate"><i class="fa fa-times delIcon"></i> Remove</a>':'';?>
</div>
</div>
<div class="col-md-4">
<label class="col-md-4 control-label" for="inputDefault">Expiry Date</label>
<div class="col-md-8">
<input type="text" class="form-control expiryDate" readonly="readonly" data-date-format="dd-mm-yyyy" name="expiryDate" value="<?=($expiryDate!="")?viewDateFormat($expiryDate):''?>" id="inputDefault" >
<?=(isset($expiryDate) && $expiryDate!="--")?'<a title="Remove this date" onclick="return confirm(\'Are you sure you want to remove?\');" href="orders.php?action=edit&id='.$id.'&act=delDate&field=expiryDate"><i class="fa fa-times delIcon"></i> Remove</a>':'';?>
</div>
</div>
<div class="col-md-4">
<label class="col-md-4 control-label" for="inputDefault">Latest Date Shipment</label>
<div class="col-md-8">
<input type="text" class="form-control latestDateShipment" readonly="readonly" data-date-format="dd-mm-yyyy" name="latestDateShipment" value="<?=($latestDateShipment!="")?viewDateFormat($latestDateShipment):''?>" id="inputDefault" >
<?=(isset($latestDateShipment) && $latestDateShipment!="--")?'<a title="Remove this date" onclick="return confirm(\'Are you sure you want to remove?\');" href="orders.php?action=edit&id='.$id.'&act=delDate&field=latestDateShipment"><i class="fa fa-times delIcon"></i> Remove</a>':'';?>
</div>
</div>
</div>
<div class="form-group">
<div class="col-md-4">
<label class="col-md-6 control-label" for="inputDefault">Document Received</label>
<div class="col-md-6">
<input type="text" class="form-control documentReceivedDate" readonly="readonly" data-date-format="dd-mm-yyyy" name="documentReceivedDate" value="<?=($documentReceivedDate!="")?viewDateFormat($documentReceivedDate):''?>" id="inputDefault" >
<?=(isset($documentReceivedDate) && $documentReceivedDate!="--")?'<a title="Remove this date" onclick="return confirm(\'Are you sure you want to remove?\');" href="orders.php?action=edit&id='.$id.'&act=delDate&field=documentReceivedDate"><i class="fa fa-times delIcon"></i> Remove</a>':'';?>
</div>
</div>
<div class="col-md-4">
<label class="col-md-4 control-label" for="inputDefault">Lead Time</label>
<div class="col-md-8">
<input type="number" min="0" class="form-control" name="lead_time" value="<?=isset($lead_time)?(int)$lead_time:''?>" id="lead_time" >
<span class="hintTxt">Days</span>
</div>
</div>
<div class="col-md-4">
<label class="col-md-6 control-label" for="inputDefault">Shipment Plan Date</label>
<div class="col-md-6">
<?php
$shipmentPlanDate = '';
if (!empty($documentReceivedDate) && $documentReceivedDate != '0000-00-00' && isset($lead_time) && (int)$lead_time > 0) {
    $shipmentPlanDate = date('Y-m-d', strtotime($documentReceivedDate . ' +' . (int)$lead_time . ' days'));
}
echo ($shipmentPlanDate!="")?viewDateFormat($shipmentPlanDate):'';
?>
</div>
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Bill Number</label>
<div class="col-md-8">
<input type="text" class="form-control" name="billNo" value="<?=$billNo?>" id="unitPrice"  >
</div>
</div>
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Document Order No</label>
<div class="col-md-8">
<input type="text" class="form-control" name="documentOrderNo" value="<?=$documentOrderNo?>" id="inputDefault"  >
</div>
</div>
</div>

<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Shipping Docs Info</label>
<div class="col-md-9">
<input type="text" class="form-control" name="shippingDocInfo" value="<?=$shippingDocInfo?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputHelpText">Shipping Docs</label>
<div class="col-md-9">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="45%">Documents</th>
<th width="30%">Action</th>
</tr>
</thead>
<tbody>
<?php
$sno=1;
if ($action == 'update') {
    if($shippingDocImg!=""){
    $copyFileUrlButton = ordersCopyFileUrlButton($shippingDocImg);
    echo '<tr>
    <td>&nbsp; '.$sno.'</td>
    <td><a href="'.$siteUrlMain."img/" . $shippingDocImg.'" target="_blank" >'.(($shippingDocImgNm)?$shippingDocImgNm:$shippingDocImg).'</a></td>
    <td><a title="Delete this image" onclick="return confirm(\'Are you sure you want to delete?\');" href="orders.php?action=edit&id='.$id.'&act=imgDelete2&imgID='.$shippingDocImg.'"><i class="fa fa-times delIcon"></i> Delete</a> '.$copyFileUrlButton.'</td>
    </tr>';
    }
    $sqlImg2 = mysqli_query($conn,"SELECT * FROM order_images WHERE isOrder='0' AND orderID='" . $id . "'");
    $numRowImgs2 = mysqli_num_rows($sqlImg2);
    if($numRowImgs2>0){
        while($rowImg2 = mysqli_fetch_assoc($sqlImg2)){
            $sno++;
            $copyFileUrlButton = ordersCopyFileUrlButton($rowImg2['orderImg']);
            echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td><a href="'.$siteUrlMain."img/" . $rowImg2['orderImg'].'" target="_blank" >'.(($rowImg2['fileName'])?$rowImg2['fileName']:$rowImg2['orderImg']).'</a></td>
<td><a title="Delete this image" onclick="return confirm(\'Are you sure you want to delete?\');" href="orders.php?action=edit&id='.$id.'&act=imgDelete&imgID='.$rowImg2['id'].'"><i class="fa fa-times delIcon"></i> Delete</a> '.$copyFileUrlButton.'</td>
</tr>';
        }
    }
}
?>
</tbody>
</table>
<?php if($shippingDocImg==""){ echo '<input type="file" class="form-control" name="shippingDocImg"  /><br />'; }?>
<a href="javascript:void(0)" name="lnk" id="lnk" class="btn btn-default" onClick="createGridDocs2('table_exend2');">Add More Images</a>
<table id="table_exend2" border="1px" cellpadding="0">
</table>
</div>
</div>

<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Bank Information</label>
<div class="col-md-9">
<textarea class="summernote" id="bankInfo" name="bankInfo" data-plugin-summernote data-plugin-options='{ "height": 100, "codemirror": { "theme": "ambiance" } }'><?php echo htmlspecialchars($bankInfo); ?></textarea>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Term &amp; Conditions </label>
<div class="col-md-9">
<textarea class="summernote" id="termCondition" name="termCondition" data-plugin-summernote data-plugin-options='{ "height": 100, "codemirror": { "theme": "ambiance" } }'><?php echo htmlspecialchars($termCondition); ?></textarea>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">&nbsp;</label>
<div class="col-md-9"><b>Settlement</b></div>
</div>

<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Supplier</label>
<div class="col-md-8">
<input type="text" class="form-control" name="settlementSupplier" value="<?=$settlementSupplier?>" id="inputDefault"  >
</div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" for="inputDefault">Applicant</label>
<div class="col-md-8">
<input type="text" class="form-control" name="settlementApplicant" value="<?=$settlementApplicant?>" id="inputDefault"  >
</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Settlements</label>
<div class="col-md-9">
<div class="paddingTxt"><?php $settlementAry = explode(",",$settlementIDs); ?>
<input type="checkbox" name="settlementIDs[]" value="1" <?=(in_array(1,$settlementAry)?'checked="checked"':'');?> > Remittance from supplier &nbsp; 
<input type="checkbox" name="settlementIDs[]" value="2" <?=(in_array(2,$settlementAry)?'checked="checked"':'');?> > On Arrival from Applicant &nbsp; 
<input type="checkbox" name="settlementIDs[]" value="3" <?=(in_array(3,$settlementAry)?'checked="checked"':'');?> > Licensed</div>
</div>
</div>
<div class="form-group">
<label class="col-md-5 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<input type="hidden" name="action" value="<?=$action;?>" />
<input type="hidden" name="id" value="<?=$id;?>" />
<button class="btn btn-primary" name="submit" value="Submit">Submit</button>
<button type="button" onclick="window.location.href='<?=$siteUrl;?>orders.php'" class="btn btn-default">Cancel</button>
</div>
</div>
</form>
<div class="modal fade" id="quickPartyModal" tabindex="-1" role="dialog" aria-labelledby="quickPartyTitle" aria-hidden="true">
<div class="modal-dialog" style="width: min(720px, 95%);">
<div class="modal-content">
<div class="modal-header">
<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
<h4 class="modal-title" id="quickPartyTitle">Add Customer</h4>
</div>
<div class="modal-body" style="padding:0;height:600px"><iframe id="quickPartyFrame" title="Quick add vendor or customer" style="border:0;width:100%;height:100%"></iframe></div>
</div>
</div>
</div>
<script>
function openQuickParty(type) {
    var label = type === 'vendor' ? 'Vendor' : 'Customer';
    $('#quickPartyTitle').text('Add ' + label);
    $('#quickPartyFrame').attr('src', 'quickCustomer.php?type=' + encodeURIComponent(type));
    $('#quickPartyModal').modal('show');
}
window.addEventListener('message', function(event) {
    if (event.origin !== window.location.origin || !event.data || event.data.type !== 'quick-party-created') return;
    var selectID = event.data.partyType === 'vendor' ? '#vendorID' : '#customerID';
    var select = $(selectID);
    var newPartyOption = $('<option>').val(event.data.id).text(event.data.companyName).attr('data-notes', event.data.moreInfo || '');
    select.append(newPartyOption);
    if ($.fn.selectpicker) select.selectpicker('refresh');
    select.val(String(event.data.id));
    if ($.fn.selectpicker) select.selectpicker('refresh');
    select.trigger('change');
    $('#quickPartyModal').modal('hide');
});
</script>
</div>
</section>
</div>
</div>
</section>
</div>
</section>
<?php }else{  ?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Orders</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span>Manage Orders</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act'])){
    if($_GET['act']=='1'){
        $msgTxt='New Order has been added successfully';
    }else if($_GET['act']=='2'){
        $msgTxt='Order has been updated successfully';
    }else if($_GET['act']=='3'){
        $msgTxt='Order has been deleted successfully';
    }
    echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.$msgTxt.'</strong></div>';
}
?>
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>orders.php?action=add"><b>Add New</b></a> </div>
<h2 class="panel-title">Manage Order</h2>
</header>
<div class="panel-body">
<div class="form-group">
<div class="col-md-12">
<form class="form-horizontal form-bordered" method="get">
<select style="width:40%;float:left" class="form-control" name="sID" >
<option value="">- Select -</option>
<?php
$sqlStus = mysqli_query($conn,"SELECT id,status FROM status ORDER BY id ASC");
while($rowsStus = mysqli_fetch_assoc($sqlStus)){
    echo '<option value="'.$rowsStus['id'].'" '.($rowsStus['id']==(isset($_GET['sID']) ? $_GET['sID'] : '')?'selected="selected"':'').' >'.$rowsStus['status'].'</option>';
}
?>
</select>
<input type="text" style="width:40%;float:left;margin-left:10px;" class="form-control" name="q" value="<?=(isset($_GET['q'])?$_GET['q']:'')?>" placeholder="Search" id="inputDefault"  >
<button class="btn btn-primary" name="submit" value="Submit">GO</button>
</form> <span class="hintTxt">Search :-  product Designation.</span>
</div>
<div class="col-md-6" align="right">
&nbsp;
</div>
</div>
<div class="table-responsive">
<table class="table table-bordered table-striped table-condensed mb-none orders-table">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="6%">ID</th>
<th width="11%">Vendor</th>
<th width="11%">Customer</th>
<th width="18%">Product Designation</th>
<th width="10%">Status</th>
<th width="9%" class="text-center">In-Transit %<br /></th>
<th width="10%">Created On</th>
<th width="20%" class="text-center">Action</th>
</tr>
</thead>
<tbody>
<?php



$page_name="orders.php?q=".isset($_GET['q']);
if(!isset($_REQUEST["start"])) {
    $start = 0;
}
else
    $start = $_REQUEST["start"];

$eu = ($start - 0);
$limit = 20;
$this1 = $eu + $limit;
$back = $eu - $limit;
$next = $eu + $limit;

$sqlSrch = "";
if(isset($_GET['q']) && $_GET['q']!=""){
    $searchTerm = mysqli_real_escape_string($conn, trim((string)$_GET['q']));
    $sqlSrch = " AND (CAST(o.id AS CHAR) LIKE '%" . $searchTerm . "%'
        OR o.invoiceNo LIKE '%" . $searchTerm . "%'
        OR o.documentOrderNo LIKE '%" . $searchTerm . "%'
        OR o.billNo LIKE '%" . $searchTerm . "%'
        OR o.shippingDocInfo LIKE '%" . $searchTerm . "%'
        OR v.applicantName LIKE '%" . $searchTerm . "%'
        OR c.applicantName LIKE '%" . $searchTerm . "%'
        OR p.prodName LIKE '%" . $searchTerm . "%') ";
}
$sqlSeller = "SELECT o.documentOrderNo,o.billNo,o.latestDateShipment,o.expiryDate,o.documentDate,c.applicantName,v.applicantName as vdr,o.dta,o.eta,o.newETA,o.id,o.invoiceNo,p.prodName,o.createdDate,o.modifiedDate,o.statusID,
    GREATEST(
        COALESCE(o.modifiedDate,o.createdDate),
        COALESCE(notes.lastNoteActivity,o.createdDate),
        o.createdDate,
        CASE WHEN JSON_VALID(o.tags) AND JSON_TYPE(o.tags)='ARRAY' AND JSON_LENGTH(o.tags)>0 THEN
            STR_TO_DATE(SUBSTRING_INDEX(JSON_UNQUOTE(JSON_EXTRACT(o.tags, CONCAT('$[',JSON_LENGTH(o.tags)-1,']'))),' Status Change to',1),'%e-%b-%y %l:%i %p')
        ELSE NULL END
    ) AS lastActivity
    FROM orders AS o JOIN products AS p ON p.id=o.productID JOIN customers AS c ON c.id=o.customerID JOIN customers AS v ON v.id=o.vendorID
    LEFT JOIN (SELECT orderID,MAX(COALESCE(modifiedDate,createdDate)) AS lastNoteActivity FROM order_notes GROUP BY orderID) AS notes ON notes.orderID=o.id
    WHERE o.statusID <> '1' ".((isset($_GET['sID']) && $_GET['sID']!="")?' AND o.statusID="'.$_GET['sID'].'" ':'')." ".$sqlSrch." ORDER BY lastActivity DESC,o.createdDate DESC,o.id DESC limit $eu, $limit";
$sqltot = "SELECT o.id FROM orders as o,products as p,customers as c,customers as v WHERE v.id=o.vendorID and c.id=o.customerID and p.id=o.productID AND o.statusID <> '1' ".((isset($_GET['sID']) && $_GET['sID']!="")?' AND o.statusID="'.$_GET['sID'].'" ':'')."  ". $sqlSrch." ORDER BY o.id DESC";
$sqlMergedInvoices = mysqli_query($conn, "SELECT invoiceNo, COUNT(*) AS total_count FROM orders WHERE invoiceNo <> '' GROUP BY invoiceNo HAVING COUNT(*) > 1");
$mergedInvoiceMap = array();
while ($rowMergedInvoice = mysqli_fetch_assoc($sqlMergedInvoices)) {
    $mergedInvoiceMap[$rowMergedInvoice['invoiceNo']] = (int)$rowMergedInvoice['total_count'];
}

$resultSeller= mysqli_query($conn,$sqlSeller);
$resulttot=mysqli_query($conn,$sqltot);
$nume=mysqli_num_rows($resulttot);
if (@mysqli_num_rows($resultSeller)!=0){

$sno=$start;
while($rows=mysqli_fetch_assoc($resultSeller)){
$sno++;
$msgTxt="";
$getRDStg="";

if($rows['dta']!="--" && $rows['eta']!="--" && !(timeEst($rows['dta']) > 0)){
$getRD = getPerDate($rows['eta'],$rows['dta']);

if($getRD>0 && $getRD<=100){

	'<b>'.$getRDStg = $getRD.'%'.'</b>';

}
}

if($rows['statusID']=='8'){
if($rows['dta']=='--'){ $msgTxt.='<p>- Missing ETD</p>';}
if($rows['eta']=='--'){ $msgTxt.='<p>- Missing ETA</p>';}
if($rows['documentDate']==''){ $msgTxt.='<p>- Document Date</p>';}
if($rows['expiryDate']==''){ $msgTxt.='<p>- Expiry Date</p>';}
if($rows['latestDateShipment']==''){ $msgTxt.='<p>- Latest Date Shipment</p>';}
if($rows['billNo']==''){ $msgTxt.='<p>- Bill Number</p>';}
if($rows['documentOrderNo']==''){ $msgTxt.='<p>- Document Order No</p>';}
}
$statusColor = trim((string)statusBg($rows['statusID']));
if (!preg_match('/^(#[0-9a-f]{3,4}|#[0-9a-f]{6}|#[0-9a-f]{8}|[a-z]{1,20})$/i', $statusColor)) {
    $statusColor = '#ffffff';
}
echo '<style type="text/css">table.orders-table > tbody > tr.statusBgTD'.$sno.' > td.order-status-cell{background-color:'.$statusColor.' !important;}</style>';
echo '<tr class="statusBgTD'.$sno.' order-row">
<td>&nbsp; '.$sno.'</td>
<td>'.$rows['invoiceNo'].((isset($mergedInvoiceMap[$rows['invoiceNo']]) && $mergedInvoiceMap[$rows['invoiceNo']] > 1) ? ' <span class="label label-warning">Merged</span>' : '').'<strong><br>'.$rows['documentOrderNo'].'</strong></td>
<td>'.$rows['vdr'].'</td>
<td>'.$rows['applicantName'].'</td>
<td>'.$rows['prodName'];
if($msgTxt){echo '<div class="alert alert-danger smlTxt"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong><p>Error Fields :-</p> '.$msgTxt.'</strong></div>';}
echo '</td>
<td class="order-status-cell">'.statusNm($rows['statusID']).'</td>
<td align="center">'.$getRDStg.'</td>
<td align="center">'.viewDtTm($rows['createdDate']).'</td>
<td align="center"><div class="table-action-buttons">
<a title="View order" aria-label="View order" class="btn btn-default viewBtn icon-action" href="orders.php?action=view&id='.$rows['id'].'"><i class="fa fa-eye" aria-hidden="true"></i><span class="sr-only">View</span></a>
<a title="Order notes ('.orderNotesTotal($rows['id']).')" aria-label="Order notes ('.orderNotesTotal($rows['id']).')" class="btn btn-default notsBtn icon-action" href="orderNotes.php?oID='.$rows['id'].'"><i class="fa fa-file-text-o" aria-hidden="true"></i><span class="sr-only">Notes</span><span class="action-count">'.orderNotesTotal($rows['id']).'</span></a>
<a title="Add task" aria-label="Add task" class="btn btn-default addTaskBtn icon-action" href="tasks.php?action=add&orderID='.$rows['id'].'"><i class="fa fa-tasks" aria-hidden="true"></i><span class="sr-only">Add Task</span></a>
</div></td>
</tr>';
}
?>
<tr>
<td colspan="9">
<?php
echo "<table align = 'center' width='100%'><tr><td  align='left' width='30%'>";
if($back >=0) {
    print "<a href='$page_name&start=$back'><font face='Verdana' size='2'>PREV</font></a>";
}
echo "</td><td align=center width='30%'>Page:";
$i=0;
$l=1;
$total=0;
for($i=0;$i < $nume;$i=$i+$limit){
    if($i <> $eu){
        echo " <a href='$page_name&start=$i'><font face='Verdana' size='2'>$l</font></a> ";
    }
    else {
        echo "<font face='Verdana' size='2' color=red>$l</font>";
    }
    $l=$l+1;
    $total = $total+1;
}
echo " of $total</td><td  align='right' width='30%'>";
if($this1 < $nume) {
    print "<a href='$page_name&start=$next'><font face='Verdana' size='2'>NEXT</font></a>";}
echo "</td></tr></table>";
?>
</td>
</tr><?php
}else{
    echo "<tr>
<td colspan='9' align='center'>No Data Found.</td>
</tr>";
}
?>

</tbody>
</table>
</div>
</div>
</section>
</section>
</div>
</section>
<?php
}
include "include/footer.php"; ?>
