<?php


include "include/sessionAdmin.php";

include "include/configure.php";
require_once(__DIR__ . '/../php/task_reminder_tracking.php');
require_once(__DIR__ . '/../vendor/autoload.php');
use PHPMailer\PHPMailer\PHPMailer;
require_once(__DIR__ . '/../PHPMailer/Exception.php');
require_once(__DIR__ . '/../PHPMailer/PHPMailer.php');
require_once(__DIR__ . '/../PHPMailer/SMTP.php');
require_once(__DIR__ . '/../php/mail_config.php');
require_once(__DIR__ . '/../php/task_schedule.php');

ensureTaskScheduleSchema($conn);
require_once(__DIR__ . '/../php/whatsapp_tasks.php');

$taskWhatsAppTemplateColumn = mysqli_query($conn, "SHOW COLUMNS FROM tasks LIKE 'taskWhatsAppTemplate'");
if ($taskWhatsAppTemplateColumn && mysqli_num_rows($taskWhatsAppTemplateColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN taskWhatsAppTemplate VARCHAR(100) NOT NULL DEFAULT '' AFTER taskSMSID");
}
$whatsAppTemplates = whatsgTaskTemplates($conn);

if (!isset($_GET['status']) && empty($_GET['action']) && !isset($_GET['act']) && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET')) {
    $defaultTaskQuery = $_GET;
    $defaultTaskQuery['status'] = 'scheduled';
    header('Location: tasks.php?' . http_build_query($defaultTaskQuery));
    exit;
}

$taskAttachmentFileColumn = mysqli_query($conn, "SHOW COLUMNS FROM tasks LIKE 'attachmentFile'");
if ($taskAttachmentFileColumn && mysqli_num_rows($taskAttachmentFileColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN attachmentFile VARCHAR(255) NOT NULL DEFAULT '' AFTER taskSMS");
}
$taskAttachmentNameColumn = mysqli_query($conn, "SHOW COLUMNS FROM tasks LIKE 'attachmentName'");
if ($taskAttachmentNameColumn && mysqli_num_rows($taskAttachmentNameColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN attachmentName VARCHAR(255) NOT NULL DEFAULT '' AFTER attachmentFile");
}
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS task_attachments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    taskID INT UNSIGNED NOT NULL,
    fileName VARCHAR(255) NOT NULL,
    storedFile VARCHAR(255) NOT NULL,
    createdDate DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY taskID (taskID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function saveTaskAttachment($file, &$error) {
    $error = '';
    if (!isset($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return array('', '');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'The attachment could not be uploaded. Please try again.';
        return false;
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        $error = 'The attachment must be 10 MB or smaller.';
        return false;
    }

    $originalName = basename((string)$file['name']);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo ? finfo_file($fileInfo, $file['tmp_name']) : '';
    if ($fileInfo) {
        finfo_close($fileInfo);
    }
    $isPdf = in_array($mimeType, array('application/pdf', 'application/x-pdf'), true);
    $isZip = in_array($mimeType, array('application/zip', 'application/x-zip', 'application/x-zip-compressed'), true)
        || ($extension === 'zip' && in_array($mimeType, array('', 'application/octet-stream'), true));
    $isImage = strpos($mimeType, 'image/') === 0 && $mimeType !== 'image/svg+xml';
    if (!$isPdf && !$isZip && !$isImage) {
        $error = 'Only PDF, ZIP, and image files are allowed.';
        return false;
    }
    $extension = preg_replace('/[^a-z0-9]/', '', $extension);
    if ($extension === '') {
        $extension = $isPdf ? 'pdf' : ($isZip ? 'zip' : 'img');
    }

    $storedName = 'task_' . str_replace('.', '_', uniqid('', true)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/../img/' . $storedName)) {
        $error = 'The attachment could not be saved. Please try again.';
        return false;
    }
    return array($storedName, $originalName);
}

function saveTaskAttachments($files, &$error) {
    $error = '';
    if (!isset($files) || !isset($files['name'])) {
        return array();
    }
    $fileCount = is_array($files['name']) ? count($files['name']) : 1;
    $savedFiles = array();
    for ($i = 0; $i < $fileCount; $i++) {
        $file = is_array($files['name']) ? array(
            'name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i], 'size' => $files['size'][$i]
        ) : $files;
        $savedFile = saveTaskAttachment($file, $error);
        if ($savedFile === false) {
            foreach ($savedFiles as $saved) {
                @unlink(__DIR__ . '/../img/' . basename($saved[0]));
            }
            return false;
        }
        if (!empty($savedFile[0])) {
            $savedFiles[] = $savedFile;
        }
    }
    return $savedFiles;
}

function taskAttachments($conn, $taskID) {
    $files = array();
    $result = mysqli_query($conn, "SELECT fileName,storedFile FROM task_attachments WHERE taskID='" . (int)$taskID . "' ORDER BY id ASC");
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $files[] = $row;
    }
    return $files;
}

function attachTaskFilesToEmail($mail, $conn, $taskID) {
    foreach (taskAttachments($conn, $taskID) as $file) {
        $path = __DIR__ . '/../img/' . basename($file['storedFile']);
        if (is_file($path)) {
            $mail->addAttachment($path, $file['fileName']);
        }
    }
}

function orderImageFileName($fileName) {
    $safeName = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$fileName);
    if ($safeName === false || $safeName === '') {
        $safeName = preg_replace('/[^\x20-\x7E]/', '_', (string)$fileName);
    }
    return trim($safeName) !== '' ? $safeName : 'task_attachment';
}

function saveTaskTemplatesFromPost($conn, $emailTemplateId, $smsTemplateId) {
    if (!empty($_POST['saveEmailTemplate']) && $emailTemplateId > 0) {
        $emailSubject = (string)($_POST['subject'] ?? '');
        $emailMessage = (string)($_POST['taskMessage'] ?? '');
        $stmt = $conn->prepare("UPDATE send_templates SET subject = ?, message = ? WHERE id = ? AND isSMS = '0'");
        $stmt->bind_param("ssi", $emailSubject, $emailMessage, $emailTemplateId);
        $stmt->execute();
        $stmt->close();
    }

    if (!empty($_POST['saveSMSTemplate']) && $smsTemplateId > 0) {
        $smsMessage = (string)($_POST['taskSMS'] ?? '');
        $stmt = $conn->prepare("UPDATE send_templates SET message = ? WHERE id = ? AND isSMS = '1'");
        $stmt->bind_param("si", $smsMessage, $smsTemplateId);
        $stmt->execute();
        $stmt->close();
    }
}


function sendImmediateTask($conn, $taskID, $sendBy) {
if(in_array('immediate', $sendBy, true)){

	if(in_array('mail', $sendBy, true)){
		$sqlEp = mysqli_query($conn,"SELECT id,assignTo,orderID,subject,taskMessage,taskSMS,sendBy FROM tasks WHERE id='".$taskID."'  ");
		$numRowsExp = mysqli_num_rows($sqlEp);
		if($numRowsExp>0){
		while($rowsEp = mysqli_fetch_assoc($sqlEp)){

			if($rowsEp['assignTo']!=""){
				//Order Info
							$sqlOrdrInfo = mysqli_query($conn,"SELECT o.id,o.unitPrice,o.quantity,o.totalAmt,o.orderOrigin,o.orderDelievery,o.orderPackaging,o.invoiceNo,o.eta,o.dta,o.newETA,o.documentDate,o.expiryDate,o.paymentTerms,o.latestDateShipment,o.documentOrderNo,o.billNo,o.shippingDocInfo,p.prodName, c.email2 FROM orders as o,products as p, customers as c WHERE p.id=o.productID AND c.id=o.customerID AND o.id='".$rowsEp['orderID']."' ");

				//$sqlOrdrInfo = mysqli_query($conn,"SELECT o.id,o.invoiceNo,o.documentOrderNo,p.prodName FROM orders as o,products as p WHERE p.id=o.productID AND o.id='".$rowsEp['orderID']."' ");
				$rowsOrdrInfo = mysqli_fetch_assoc($sqlOrdrInfo);
				$assignToAry = filterAgentRecipientsByChannel($conn, orderAgentRecipientIDs($conn, $rowsEp['orderID'], $rowsEp['assignTo']), 'email');

				if(count($assignToAry)>0){

					foreach($assignToAry as $assignToVal){

						$sqlCtBk = mysqli_query($conn,"SELECT companyName,applicantName,mobileNumber,email,email2,isEnableEmail FROM customers WHERE id='".$assignToVal."' ");
						$rowsCtBk = mysqli_fetch_assoc($sqlCtBk);
						$sendByAry = @explode(",",$rowsEp['sendBy']);

						$to = $rowsCtBk['email'];
						$to2 = $rowsCtBk['email2'];
						$subject = $rowsEp['subject'];
						if (stripos((string)$rowsEp['taskMessage'], 'May I have the price for') === false && !empty($rowsOrdrInfo['prodName'])) {
							$subject .= " / " . $rowsOrdrInfo['prodName']
								. (($rowsOrdrInfo['documentOrderNo'] != "") ? '('.$rowsOrdrInfo['documentOrderNo'].')' : '')
								. (($rowsOrdrInfo['invoiceNo'] != "") ? ' / '.$rowsOrdrInfo['invoiceNo'] : '');
						}

						//merge field
						$values = array(
							'unit_price' => $rowsOrdrInfo['unitPrice'],
							'quantity' => $rowsOrdrInfo['quantity'],
							'total_amount' => $rowsOrdrInfo['totalAmt'],
							'order_transport' => $rowsOrdrInfo['orderDelievery'],
							'order_packaging' => $rowsOrdrInfo['orderPackaging'],
							'product_name'    => $rowsOrdrInfo['prodName'],
							'document_order_number'    => $rowsOrdrInfo['documentOrderNo'],
							'proforma_invoice'    => $rowsOrdrInfo['invoiceNo'],
							'second_email'    => ($rowsCtBk['email2'] ?? ''),
							'arrival_date' => viewDateFormat($rowsOrdrInfo['eta']),
							'departure_date' => viewDateFormat($rowsOrdrInfo['dta']),
							'latest_date_shipment' => viewDateFormat($rowsOrdrInfo['latestDateShipment']),
							'document_bill_no' => $rowsOrdrInfo['billNo'],
							'doucment_expiry_date' => viewDateFormat($rowsOrdrInfo['expiryDate']),
							'arrival_new_date' => viewDateFormat($rowsOrdrInfo['newETA']),
							'payment_terms' => $rowsOrdrInfo['paymentTerms'],
							'shipping_info' => $rowsOrdrInfo['shippingDocInfo']);




							$search = array();
							$replace = array();



							foreach($values as $index => $value) {


								$search[] = '{'.$index.'}';
									$replace[] = $value;



								}




							 //merge field
						$message = buildTaskEmailBody($rowsCtBk['applicantName'], trim($rowsEp['taskMessage']));
						
						$message = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $message);

						
						 $message = str_replace($search,$replace,$message);
						 unset($replace);
                        $mail = new PHPMailer();
                        $mail->CharSet = 'UTF-8';
                        $mail->Encoding = 'base64';
                        configureAppMailer($mail);
                        $mail->addAddress($to, $rowsCtBk['applicantName']);
                        if (!empty($to2)) $mail->addCC($to2, $rowsCtBk['applicantName']);
                        addConfiguredMailBcc($mail);
                        $mail->isHTML(true);
                        $mail->Subject = $subject;
                        $mail->Body = appendConfiguredEmailFooter($conn, $message);
                        attachTaskFilesToEmail($mail, $conn, $rowsEp['id']);
                        $mail->send();
                        insertCommunicationLog('email', trim($rowsCtBk['companyName'].' '.$rowsCtBk['applicantName']), $to, "Subject: ".$subject."\n\n".html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'SENT', array('source'=>'task','task_id'=>(int)$rowsEp['id']));


						


					}
				}
			}
			}
		}
	}

	if(in_array('sms', $sendBy, true)){
	//SMS


		$sqlEp = mysqli_query($conn,"SELECT id,assignTo,orderID,subject,taskMessage,taskSMS,sendBy FROM tasks WHERE id='".$taskID."'  ");
		$numRowsExp = mysqli_num_rows($sqlEp);
		if($numRowsExp>0){
	while($rowsEp = mysqli_fetch_assoc($sqlEp)){

		if($rowsEp['assignTo']!=""){
			//Order Info
		$sqlOrdrInfo = mysqli_query($conn,"SELECT o.id,o.unitPrice,o.quantity,o.totalAmt,o.orderOrigin,o.orderDelievery,o.orderPackaging,o.invoiceNo,o.eta,o.dta,o.newETA,o.documentDate,o.expiryDate,o.paymentTerms,o.latestDateShipment,o.documentOrderNo,o.billNo,o.shippingDocInfo,p.prodName, c.email2 FROM orders as o,products as p, customers as c WHERE p.id=o.productID AND c.id=o.customerID AND o.id='".$rowsEp['orderID']."' ");
			$rowsOrdrInfo = mysqli_fetch_assoc($sqlOrdrInfo);
			$assignToAry = orderAgentRecipientIDs($conn, $rowsEp['orderID'], $rowsEp['assignTo']);
			if(count($assignToAry)>0){
				foreach($assignToAry as $assignToVal){


					$sqlCtBk = mysqli_query($conn,"SELECT applicantName,companyName,mobileNumber,email,email2,isEnableEmail FROM customers WHERE id='".$assignToVal."' ");
					$rowsCtBk = mysqli_fetch_assoc($sqlCtBk);
					$sendByAry = @explode(",",$rowsEp['sendBy']);
						if($rowsCtBk['mobileNumber'] && agentRecipientChannelEnabled($conn, $assignToVal, 'sms')){

					$values = array(
							'unit_price' => $rowsOrdrInfo['unitPrice'],
							'quantity' => $rowsOrdrInfo['quantity'],
							'total_amount' => $rowsOrdrInfo['totalAmt'],
							'order_transport' => $rowsOrdrInfo['orderDelievery'],
							'order_packaging' => $rowsOrdrInfo['orderPackaging'],
							'product_name'    => $rowsOrdrInfo['prodName'],
							'document_order_number'    => $rowsOrdrInfo['documentOrderNo'],
							'proforma_invoice'    => $rowsOrdrInfo['invoiceNo'],
							'second_email'    => ($rowsCtBk['email2'] ?? ''),
							'arrival_date' => viewDateFormat($rowsOrdrInfo['eta']),
							'departure_date' => viewDateFormat($rowsOrdrInfo['dta']),
							'latest_date_shipment' => viewDateFormat($rowsOrdrInfo['latestDateShipment']),
							'document_bill_no' => $rowsOrdrInfo['billNo'],
							'doucment_expiry_date' => viewDateFormat($rowsOrdrInfo['expiryDate']),
							'arrival_new_date' => viewDateFormat($rowsOrdrInfo['newETA']),
							'payment_terms' => $rowsOrdrInfo['paymentTerms'],
							'shipping_info' => $rowsOrdrInfo['shippingDocInfo']);

							$search = array();
							$replace = array();

						foreach($values as $index => $value) {
							if(!empty($value));


						$search[] = "{" . $index . "}"; // Wrapping the text in "{%...%}"
							$replace[] = $value;
							}



							//merge field


					$msg = 'Dear '.$rowsCtBk['applicantName']."\r\n"."\r\n".''.$rowsOrdrInfo['prodName']."\r\n"."\r\n".''.(($rowsOrdrInfo['documentOrderNo']!="")? '('.$rowsOrdrInfo['documentOrderNo'].')':$rowsOrdrInfo['invoiceNo'])."\r\n"."\r\n".''.strip_tags($rowsEp['taskSMS']).' '."\r\n"."\r\n".'WAQAS CHEMICALS'."\n".'03008207530';



					// sending through mail button //

					$msg = str_replace($search,$replace,$msg);
		unset($replace);
					sendSMS($rowsCtBk['mobileNumber'],$msg, trim($rowsCtBk['companyName'].' '.$rowsCtBk['applicantName']), array('source'=>'task','task_id'=>(int)$taskID));

					}
				}
			}
		}
		}
	}
	}
}

if (in_array('immediate', $sendBy, true) && in_array('whatsapp', $sendBy, true)) {
    $whatsAppResult = sendTaskWhatsApp($conn, $taskID);
    return $whatsAppResult;
}
    return null;
}

// End of SendinBlue //




if(isset($_POST['submit']) && in_array($_POST['submit'], array("Submit", "Draft"), true)){
$isDraft = ($_POST['submit'] === 'Draft' || ($_POST['deliveryStatus'] ?? 'send') === 'draft') ? 1 : 0;
if(isset($_POST['action']) && $_POST['action']=="add"){

$sendBy = isset($_POST['sendBy']) && is_array($_POST['sendBy']) ? $_POST['sendBy'] : [];

$isExpired = "0";
if(!$isDraft && in_array('immediate', $sendBy)){
    $isExpired = "1";
    // Scheduled dates and times do not apply to an immediate notification.
    $_POST['dueTime'] = '';
    $_POST['repeatTime'] = '';
}

// Handle dates, setting to a valid default if empty
$repeatDate = isset($_POST['repeatDate']) ? $_POST['repeatDate'] : '';
if(!$isDraft && in_array('immediate', $sendBy)){
    $repeatDate = '';
}
$repeatDateForSql = !empty($repeatDate) ? sqlDtFormat($repeatDate) : '1970-01-01';
$repeatDateSql = "'" . mysqli_real_escape_string($conn, $repeatDateForSql) . "'";
$repeatTimeSql = taskScheduleTimeSql($conn, isset($_POST['repeatTime']) ? $_POST['repeatTime'] : '');

$dueDate = isset($_POST['dueDate']) ? $_POST['dueDate'] : '';
$dueDateForSql = !empty($dueDate) ? sqlDtFormat($dueDate) : '1970-01-01';
$dueDateSql = "'" . mysqli_real_escape_string($conn, $dueDateForSql) . "'";
$dueTimeSql = taskScheduleTimeSql($conn, isset($_POST['dueTime']) ? $_POST['dueTime'] : '');
if (!$isDraft && taskScheduleTimeValue($_POST['dueTime'] ?? '') !== null && trim($dueDate) === '') {
    header('location: tasks.php?action=add&scheduleError=dueDate');
    exit;
}
if (!$isDraft && taskScheduleTimeValue($_POST['repeatTime'] ?? '') !== null && trim($repeatDate) === '') {
    header('location: tasks.php?action=add&scheduleError=repeatDate');
    exit;
}
if (!$isDraft && trim($dueDate) !== '' && taskScheduleIsPast($conn, $dueDateForSql, $_POST['dueTime'] ?? '')) {
    header('location: tasks.php?action=add&scheduleError=duePast');
    exit;
}
if (!$isDraft && trim($repeatDate) !== '' && taskScheduleIsPast($conn, $repeatDateForSql, $_POST['repeatTime'] ?? '')) {
    header('location: tasks.php?action=add&scheduleError=repeatPast');
    exit;
}

$taskSMSID = !empty($_POST['taskSMSID']) ? mysqli_real_escape_string($conn, $_POST['taskSMSID']) : '0';
$taskWhatsAppTemplate = isset($_POST['taskWhatsAppTemplate']) && is_string($_POST['taskWhatsAppTemplate']) && isset($whatsAppTemplates[$_POST['taskWhatsAppTemplate']]) ? mysqli_real_escape_string($conn, $_POST['taskWhatsAppTemplate']) : '';
$taskMessageID = !empty($_POST['taskMessageID']) ? mysqli_real_escape_string($conn, $_POST['taskMessageID']) : '0';
$orderID = !empty($_POST['orderID']) ? mysqli_real_escape_string($conn, $_POST['orderID']) : '0';
$currentStatusID = !empty($_POST['currentStatusID']) ? mysqli_real_escape_string($conn, $_POST['currentStatusID']) : '0';
$subject = isset($_POST['subject']) ? mysqli_real_escape_string($conn, $_POST['subject']) : '';
$priorityID = !empty($_POST['priorityID']) ? mysqli_real_escape_string($conn, $_POST['priorityID']) : '';
$priorityIDSql = ($priorityID !== '') ? "'" . $priorityID . "'" : "NULL";
$assignTo = isset($_POST['assignTo']) && is_array($_POST['assignTo']) ? mysqli_real_escape_string($conn, implode(",", $_POST['assignTo'])) : '';
$isSecondReminder = isset($_POST['isSecondReminder']) ? mysqli_real_escape_string($conn, $_POST['isSecondReminder']) : '0';
$taskMessage = isset($_POST['taskMessage']) ? mysqli_real_escape_string($conn, $_POST['taskMessage']) : '';
$taskSMS = isset($_POST['taskSMS']) ? mysqli_real_escape_string($conn, $_POST['taskSMS']) : '';
$sendByStr = mysqli_real_escape_string($conn, @implode(",", $sendBy));
$attachmentError = '';
if (!$isDraft && in_array('whatsapp', $sendBy, true) && $taskWhatsAppTemplate === '') {
    header('location: tasks.php?action=add&whatsappError=template');
    exit;
}
$attachments = saveTaskAttachments(isset($_FILES['taskAttachments']) ? $_FILES['taskAttachments'] : null, $attachmentError);
if ($attachments === false) {
    header('location: tasks.php?action=add&uploadError=' . rawurlencode($attachmentError));
    exit;
}
$attachmentFile = mysqli_real_escape_string($conn, !empty($attachments[0][0]) ? $attachments[0][0] : '');
$attachmentName = mysqli_real_escape_string($conn, !empty($attachments[0][1]) ? $attachments[0][1] : '');

mysqli_query($conn,"INSERT INTO tasks (isDraft,isExpired,taskSMSID,taskWhatsAppTemplate,taskMessageID,repeatDate,repeatTime,orderID,currentStatusID,subject,dueDate,dueTime,priorityID,assignTo,isSecondReminder,taskMessage,taskSMS,attachmentFile,attachmentName,sendBy,createdDate) VALUES
('".$isDraft."','".$isExpired."','".$taskSMSID."','".$taskWhatsAppTemplate."','".$taskMessageID."',".$repeatDateSql.",".$repeatTimeSql.",'".$orderID."','".$currentStatusID."','".$subject."',".$dueDateSql.",".$dueTimeSql.",".$priorityIDSql.",'".$assignTo."','".$isSecondReminder."','".$taskMessage."','".$taskSMS."','".$attachmentFile."','".$attachmentName."','".$sendByStr."','".currDateTimeGmt()."')");
$taskID = mysqli_insert_id($conn);
if (!$isDraft) saveTaskTemplatesFromPost($conn, (int)$taskMessageID, (int)$taskSMSID);
foreach ($attachments as $attachment) {
    mysqli_query($conn, "INSERT INTO task_attachments (taskID,fileName,storedFile,createdDate) VALUES ('" . $taskID . "','" . mysqli_real_escape_string($conn, $attachment[1]) . "','" . mysqli_real_escape_string($conn, $attachment[0]) . "','" . gmdate('Y-m-d H:i:s') . "')");
    if (!$isDraft && (int)$orderID > 0) {
        mysqli_query($conn, "INSERT INTO order_images (fileName,isOrder,orderImg,orderID) VALUES ('" . mysqli_real_escape_string($conn, orderImageFileName($attachment[1])) . "','1','" . mysqli_real_escape_string($conn, $attachment[0]) . "','" . (int)$orderID . "')");
    }
}

if (!$isDraft) {
    $whatsAppResult = sendImmediateTask($conn, $taskID, $sendBy);
    if ($whatsAppResult !== null) {
        $_SESSION['taskWhatsAppNotice'] = $whatsAppResult[1];
        header('location: tasks.php?action=view&id=' . (int)$taskID . '&act=' . ($whatsAppResult[0] ? '3' : '4'));
        exit;
    }
}
header($isDraft ? 'location: tasks.php?status=draft&act=1' : "location: orders.php?action=view&id=".$_POST['orderID']."&act=1");
}else if(isset($_POST['action']) && $_POST['action']=="update"){

$repeatDate = isset($_POST['repeatDate']) ? $_POST['repeatDate'] : '';
$repeatDateForSql = !empty($repeatDate) ? sqlDtFormat($repeatDate) : '1970-01-01';
$repeatTimeSql = taskScheduleTimeSql($conn, isset($_POST['repeatTime']) ? $_POST['repeatTime'] : '');

$dueDate = isset($_POST['dueDate']) ? $_POST['dueDate'] : '';
$dueDateForSql = !empty($dueDate) ? sqlDtFormat($dueDate) : '1970-01-01';
$dueTimeSql = taskScheduleTimeSql($conn, isset($_POST['dueTime']) ? $_POST['dueTime'] : '');
if (!$isDraft && taskScheduleTimeValue($_POST['dueTime'] ?? '') !== null && trim($dueDate) === '') {
    header('location: tasks.php?action=edit&id=' . (int)($_POST['id'] ?? 0) . '&scheduleError=dueDate');
    exit;
}
if (!$isDraft && taskScheduleTimeValue($_POST['repeatTime'] ?? '') !== null && trim($repeatDate) === '') {
    header('location: tasks.php?action=edit&id=' . (int)($_POST['id'] ?? 0) . '&scheduleError=repeatDate');
    exit;
}
if (!$isDraft && trim($dueDate) !== '' && taskScheduleIsPast($conn, $dueDateForSql, $_POST['dueTime'] ?? '')) {
    header('location: tasks.php?action=edit&id=' . (int)($_POST['id'] ?? 0) . '&scheduleError=duePast');
    exit;
}
if (!$isDraft && trim($repeatDate) !== '' && taskScheduleIsPast($conn, $repeatDateForSql, $_POST['repeatTime'] ?? '')) {
    header('location: tasks.php?action=edit&id=' . (int)($_POST['id'] ?? 0) . '&scheduleError=repeatPast');
    exit;
}

// Sanitize integer fields — default to 0 (NOT NULL columns)
$taskSMSID       = !empty($_POST['taskSMSID'])       ? (int)$_POST['taskSMSID']       : 0;
$taskWhatsAppTemplate = isset($_POST['taskWhatsAppTemplate']) && is_string($_POST['taskWhatsAppTemplate']) && isset($whatsAppTemplates[$_POST['taskWhatsAppTemplate']]) ? mysqli_real_escape_string($conn, $_POST['taskWhatsAppTemplate']) : '';
$taskMessageID   = !empty($_POST['taskMessageID'])   ? (int)$_POST['taskMessageID']   : 0;
$orderID         = !empty($_POST['orderID'])         ? (int)$_POST['orderID']         : 0;
$currentStatusID = !empty($_POST['currentStatusID']) ? (int)$_POST['currentStatusID'] : 0;
$priorityID      = !empty($_POST['priorityID'])      ? (int)$_POST['priorityID']      : 0;

// Fix: safely get isSecondReminder (checkbox — not sent when unchecked)
$isSecondReminder = isset($_POST['isSecondReminder']) ? (int)$_POST['isSecondReminder'] : 0;

// Fix: safely get sendBy (array — not sent when nothing checked)
$sendBy = isset($_POST['sendBy']) && is_array($_POST['sendBy']) ? $_POST['sendBy'] : [];
$sendByStr = mysqli_real_escape_string($conn, implode(',', $sendBy));
$editTaskID = (int)($_POST['id'] ?? 0);
if (!$isDraft && in_array('whatsapp', $sendBy, true) && $taskWhatsAppTemplate === '') {
    header('location: tasks.php?action=edit&id=' . $editTaskID . '&whatsappError=template');
    exit;
}
$attachmentSql = '';
$attachmentError = '';
$attachments = saveTaskAttachments(isset($_FILES['taskAttachments']) ? $_FILES['taskAttachments'] : null, $attachmentError);
if ($attachments === false) {
    header('location: tasks.php?action=edit&id=' . (int)$_POST['id'] . '&uploadError=' . rawurlencode($attachmentError));
    exit;
}
if (!empty($attachments[0][0])) {
    $attachmentSql = "attachmentFile='" . mysqli_real_escape_string($conn, $attachments[0][0]) . "', attachmentName='" . mysqli_real_escape_string($conn, $attachments[0][1]) . "', ";
}

$reminderTrackingResetSql = '';
$existingReminderSettings = mysqli_query($conn, "SELECT dueDate,dueTime,repeatDate,repeatTime,isSecondReminder,isDraft FROM tasks WHERE id=" . (int)$_POST['id'] . " LIMIT 1");
$existingReminderRow = $existingReminderSettings ? mysqli_fetch_assoc($existingReminderSettings) : null;
$publishingDraft = !$isDraft && $existingReminderRow && (int)$existingReminderRow['isDraft'] === 1;
if ($existingReminderRow && (
    $existingReminderRow['dueDate'] !== $dueDateForSql ||
    $existingReminderRow['repeatDate'] !== $repeatDateForSql ||
    taskScheduleTimeValue($existingReminderRow['dueTime']) !== taskScheduleTimeValue($_POST['dueTime'] ?? '') ||
    taskScheduleTimeValue($existingReminderRow['repeatTime']) !== taskScheduleTimeValue($_POST['repeatTime'] ?? '') ||
    (int)$existingReminderRow['isSecondReminder'] !== $isSecondReminder
)) {
    $reminderTrackingResetSql = "firstReminderSentAt=NULL, secondReminderSentAt=NULL, isExpired='0', ";
}

mysqli_query($conn, "UPDATE tasks SET 
    isDraft        = " . $isDraft . ",
    taskMessageID   = " . $taskMessageID   . ",
    taskSMSID       = " . $taskSMSID       . ",
    taskWhatsAppTemplate = '" . $taskWhatsAppTemplate . "',
    repeatDate      = '" . mysqli_real_escape_string($conn, $repeatDateForSql) . "',
    repeatTime      = " . $repeatTimeSql . ",
    orderID         = " . $orderID         . ",
    currentStatusID = " . $currentStatusID . ",
    subject         = '" . mysqli_real_escape_string($conn, $_POST['subject'])    . "',
    dueDate         = '" . mysqli_real_escape_string($conn, $dueDateForSql)       . "',
    dueTime         = " . $dueTimeSql . ",
    priorityID      = " . $priorityID      . ",
    assignTo        = '" . mysqli_real_escape_string($conn, implode(',', isset($_POST['assignTo']) && is_array($_POST['assignTo']) ? $_POST['assignTo'] : [])) . "',
    isSecondReminder= " . $isSecondReminder . ",
    taskMessage     = '" . mysqli_real_escape_string($conn, $_POST['taskMessage']) . "',
    taskSMS         = '" . mysqli_real_escape_string($conn, $_POST['taskSMS'])     . "',
    " . $attachmentSql . "
    " . $reminderTrackingResetSql . "
    sendBy          = '" . $sendByStr . "'
    WHERE id        = " . (int)$_POST['id'] . "
");
if (!$isDraft) saveTaskTemplatesFromPost($conn, (int)$taskMessageID, (int)$taskSMSID);

foreach ($attachments as $attachment) {
    mysqli_query($conn, "INSERT INTO task_attachments (taskID,fileName,storedFile,createdDate) VALUES ('" . (int)$_POST['id'] . "','" . mysqli_real_escape_string($conn, $attachment[1]) . "','" . mysqli_real_escape_string($conn, $attachment[0]) . "','" . gmdate('Y-m-d H:i:s') . "')");
    if (!$isDraft && $orderID > 0 && !$publishingDraft) {
        mysqli_query($conn, "INSERT INTO order_images (fileName,isOrder,orderImg,orderID) VALUES ('" . mysqli_real_escape_string($conn, orderImageFileName($attachment[1])) . "','1','" . mysqli_real_escape_string($conn, $attachment[0]) . "','" . $orderID . "')");
    }
}

if ($publishingDraft) {
    if ($orderID > 0) {
        foreach (taskAttachments($conn, $editTaskID) as $attachment) {
            $storedFile = mysqli_real_escape_string($conn, $attachment['storedFile']);
            $fileName = mysqli_real_escape_string($conn, orderImageFileName($attachment['fileName']));
            mysqli_query($conn, "INSERT INTO order_images (fileName,isOrder,orderImg,orderID)
                SELECT '" . $fileName . "','1','" . $storedFile . "','" . $orderID . "'
                WHERE NOT EXISTS (SELECT 1 FROM order_images WHERE orderID='" . $orderID . "' AND orderImg='" . $storedFile . "')");
        }
    }
    if (in_array('immediate', $sendBy, true)) {
        mysqli_query($conn, "UPDATE tasks SET isExpired='1', repeatDate='1970-01-01' WHERE id=" . $editTaskID);
        $whatsAppResult = sendImmediateTask($conn, $editTaskID, $sendBy);
        if ($whatsAppResult !== null) {
            $_SESSION['taskWhatsAppNotice'] = $whatsAppResult[1];
            header('location: tasks.php?action=view&id=' . $editTaskID . '&act=' . ($whatsAppResult[0] ? '3' : '4'));
            exit;
        }
    }
}
header($isDraft ? 'location: tasks.php?status=draft&act=2' : 'location: tasks.php?act=2');
}
}
if (isset($_GET['action']) && in_array($_GET['action'], array('mail', 'SMS', 'WhatsApp'), true)) {
    $draftCheck = mysqli_query($conn, "SELECT isDraft FROM tasks WHERE id=" . (int)($_GET['id'] ?? 0) . " LIMIT 1");
    $draftTask = $draftCheck ? mysqli_fetch_assoc($draftCheck) : null;
    if ($draftTask && (int)$draftTask['isDraft'] === 1) {
        $_SESSION['taskWhatsAppNotice'] = 'This task is a draft. Change Status to Send and save before sending.';
        header('location: tasks.php?action=view&id=' . (int)$_GET['id'] . '&act=4');
        exit;
    }
}
if(isset($_GET['action']) && $_GET['action']=="mail"){
    $taskIdForChannelCheck = (int)($_GET['id'] ?? 0);
    $channelCheck = mysqli_query($conn, "SELECT sendBy FROM tasks WHERE id='" . $taskIdForChannelCheck . "' LIMIT 1");
    $channelTask = $channelCheck ? mysqli_fetch_assoc($channelCheck) : null;
    $enabledChannels = array_map('trim', explode(',', (string)($channelTask['sendBy'] ?? '')));
    if (!$channelTask || !in_array('mail', $enabledChannels, true)) {
        $_SESSION['taskWhatsAppNotice'] = $channelTask ? 'Mail is not enabled for this task.' : 'Task not found.';
        header('location: tasks.php?action=view&id=' . $taskIdForChannelCheck . '&act=4');
        exit;
    }
    $sqlEp = mysqli_query($conn,"SELECT id,assignTo,orderID,subject,taskMessage,sendBy FROM tasks WHERE id='".mysqli_real_escape_string($conn,$_GET['id'])."'");
    $numRowsExp = mysqli_num_rows($sqlEp);

    if($numRowsExp > 0){
        while($rowsEp = mysqli_fetch_assoc($sqlEp)){

            if($rowsEp['assignTo'] != ""){

                $sqlOrdrInfo = mysqli_query($conn,"SELECT o.id,o.unitPrice,o.quantity,o.totalAmt,o.orderOrigin,o.orderDelievery,o.orderPackaging,o.invoiceNo,o.eta,o.dta,o.newETA,o.documentDate,o.expiryDate,o.paymentTerms,o.latestDateShipment,o.documentOrderNo,o.billNo,o.shippingDocInfo,p.prodName,c.email2 FROM orders as o,products as p, customers as c WHERE p.id=o.productID AND c.id=o.customerID AND o.id='".$rowsEp['orderID']."'");
                $rowsOrdrInfo = mysqli_fetch_assoc($sqlOrdrInfo) ?: array('unitPrice'=>'','quantity'=>'','totalAmt'=>'','orderDelievery'=>'','orderPackaging'=>'','prodName'=>'','documentOrderNo'=>'','invoiceNo'=>'','email2'=>'','eta'=>'','dta'=>'','latestDateShipment'=>'','billNo'=>'','expiryDate'=>'','newETA'=>'','paymentTerms'=>'','shippingDocInfo'=>'');
                $assignToAry = filterAgentRecipientsByChannel($conn, orderAgentRecipientIDs($conn, $rowsEp['orderID'], $rowsEp['assignTo']), 'email');

                foreach($assignToAry as $assignToVal){

                    $sqlCtBk  = mysqli_query($conn,"SELECT companyName,applicantName,mobileNumber,email,email2,isEnableEmail FROM customers WHERE id='".$assignToVal."'");
                    $rowsCtBk = mysqli_fetch_assoc($sqlCtBk);

                    $to  = $rowsCtBk['email'];
                    $to2 = $rowsCtBk['email2'];

                    $subject = $rowsEp['subject'];
                    if (stripos((string)$rowsEp['taskMessage'], 'May I have the price for') === false && !empty($rowsOrdrInfo['prodName'])) {
                        $subject .= " / " . $rowsOrdrInfo['prodName']
                                 . (($rowsOrdrInfo['documentOrderNo'] != "") ? ' ('.$rowsOrdrInfo['documentOrderNo'].')' : '')
                                 . (($rowsOrdrInfo['invoiceNo'] != "") ? ' / '.$rowsOrdrInfo['invoiceNo'] : '');
                    }

                    $message = buildTaskEmailBody($rowsCtBk['applicantName'], trim($rowsEp['taskMessage']));

                    // Merge fields
                    $values = array(
                        'unit_price'            => $rowsOrdrInfo['unitPrice'],
                        'quantity'              => $rowsOrdrInfo['quantity'],
                        'total_amount'          => $rowsOrdrInfo['totalAmt'],
                        'order_transport'       => $rowsOrdrInfo['orderDelievery'],
                        'order_packaging'       => $rowsOrdrInfo['orderPackaging'],
                        'product_name'          => $rowsOrdrInfo['prodName'],
                        'document_order_number' => $rowsOrdrInfo['documentOrderNo'],
                        'proforma_invoice'      => $rowsOrdrInfo['invoiceNo'],
                        'second_email'          => ($rowsCtBk['email2'] ?? ''),
                        'arrival_date'          => viewDateFormat($rowsOrdrInfo['eta']),
                        'departure_date'        => viewDateFormat($rowsOrdrInfo['dta']),
                        'latest_date_shipment'  => viewDateFormat($rowsOrdrInfo['latestDateShipment']),
                        'document_bill_no'      => $rowsOrdrInfo['billNo'],
                        'doucment_expiry_date'  => viewDateFormat($rowsOrdrInfo['expiryDate']),
                        'arrival_new_date'      => viewDateFormat($rowsOrdrInfo['newETA']),
                        'payment_terms'         => $rowsOrdrInfo['paymentTerms'],
                        'shipping_info'         => $rowsOrdrInfo['shippingDocInfo'] ?? ''
                    );

                    $search = $replace = array();
                    foreach($values as $index => $value){
                        $search[]  = '{'.$index.'}';
                        $replace[] = $value;
                    }
                    $message = str_replace($search, $replace, $message);
                    $message  = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $message);
                    unset($replace);

                    // ---- PHPMailer (same as cron job) ----
                    $mail = new PHPMailer();
                    $mail->CharSet  = 'UTF-8';
                    $mail->Encoding = 'base64';
                    configureAppMailer($mail);
                    $mail->addAddress($to, $rowsCtBk['applicantName']);
                    if(!empty($to2)){
                        $mail->addCC($to2, $rowsCtBk['applicantName']);
                    }
                    addConfiguredMailBcc($mail);
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body    = $message;
                    attachTaskFilesToEmail($mail, $conn, $rowsEp['id']);
                    $mail->Body = appendConfiguredEmailFooter($conn, $mail->Body);
                    $mail->send();
                    insertCommunicationLog('email', trim($rowsCtBk['companyName'].' '.$rowsCtBk['applicantName']), $to, "Subject: ".$subject."\n\n".html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'SENT', array('source'=>'task','task_id'=>(int)$rowsEp['id']));
                    // ---- End PHPMailer ----
                }
            }
        }
    }

    $notificationReport = taskNotificationAttemptSummary($conn, (int)$_GET['id']);
    header("location: tasks.php?action=view&id=".urlencode($_GET['id'])."&act=1&notificationReport=".rawurlencode($notificationReport));
    exit;
}
if(isset($_GET['action']) && $_GET['action']=="SMS"){
	$taskIdForChannelCheck = (int)($_GET['id'] ?? 0);
	$channelCheck = mysqli_query($conn, "SELECT sendBy FROM tasks WHERE id='" . $taskIdForChannelCheck . "' LIMIT 1");
	$channelTask = $channelCheck ? mysqli_fetch_assoc($channelCheck) : null;
	$enabledChannels = array_map('trim', explode(',', (string)($channelTask['sendBy'] ?? '')));
	if (!$channelTask || !in_array('sms', $enabledChannels, true)) {
		$_SESSION['taskWhatsAppNotice'] = $channelTask ? 'SMS is not enabled for this task.' : 'Task not found.';
		header('location: tasks.php?action=view&id=' . $taskIdForChannelCheck . '&act=4');
		exit;
	}
	//Notification here
$sqlEp = mysqli_query($conn,"SELECT id,assignTo,orderID,subject,taskSMS,sendBy FROM tasks WHERE id='".$_GET['id']."'  ");
$numRowsExp = mysqli_num_rows($sqlEp);
if($numRowsExp>0){
while($rowsEp = mysqli_fetch_assoc($sqlEp)){

	if($rowsEp['assignTo']!=""){
		//Order Info

				$sqlOrdrInfo = mysqli_query($conn,"SELECT o.id,o.unitPrice,o.quantity,o.totalAmt,o.orderOrigin,o.orderDelievery,o.orderPackaging,o.invoiceNo,o.eta,o.dta,o.newETA,o.documentDate,o.expiryDate,o.paymentTerms,o.latestDateShipment,o.documentOrderNo,o.billNo,o.shippingDocInfo,p.prodName, c.email2 FROM orders as o,products as p, customers as c WHERE p.id=o.productID AND c.id=o.customerID AND o.id='".$rowsEp['orderID']."' ");
		$rowsOrdrInfo = mysqli_fetch_assoc($sqlOrdrInfo);
		$assignToAry = orderAgentRecipientIDs($conn, $rowsEp['orderID'], $rowsEp['assignTo']);
		if(count($assignToAry)>0){
			foreach($assignToAry as $assignToVal){
				$sqlCtBk = mysqli_query($conn,"SELECT applicantName,companyName,mobileNumber,email,email2,isEnableEmail FROM customers WHERE id='".$assignToVal."' ");
				$rowsCtBk = mysqli_fetch_assoc($sqlCtBk);
				$sendByAry = @explode(",",$rowsEp['sendBy']);
					if($rowsCtBk['mobileNumber'] && agentRecipientChannelEnabled($conn, $assignToVal, 'sms')){


							$values = array(
							'unit_price' => $rowsOrdrInfo['unitPrice'],
							'quantity' => $rowsOrdrInfo['quantity'],
							'total_amount' => $rowsOrdrInfo['totalAmt'],
							'order_transport' => $rowsOrdrInfo['orderDelievery'],
							'order_packaging' => $rowsOrdrInfo['orderPackaging'],
							'product_name'    => $rowsOrdrInfo['prodName'],
							'document_order_number'    => $rowsOrdrInfo['documentOrderNo'],
							'proforma_invoice'    => $rowsOrdrInfo['invoiceNo'],
							'second_email'    => ($rowsCtBk['email2'] ?? ''),
							'arrival_date' => viewDateFormat($rowsOrdrInfo['eta']),
							'departure_date' => viewDateFormat($rowsOrdrInfo['dta']),
							'latest_date_shipment' => viewDateFormat($rowsOrdrInfo['latestDateShipment']),
							'document_bill_no' => $rowsOrdrInfo['billNo'],
							'doucment_expiry_date' => viewDateFormat($rowsOrdrInfo['expiryDate']),
							'arrival_new_date' => viewDateFormat($rowsOrdrInfo['newETA']),
							'payment_terms' => $rowsOrdrInfo['paymentTerms'],
							'shipping_info' => $rowsOrdrInfo['shippingDocInfo']);

							$search = array();
							$replace = array();

						foreach($values as $index => $value) {
							if(!empty($value));


						$search[] = "{" . $index . "}"; // Wrapping the text in "{%...%}"
						$replace[] = $value.' ';
							}



							//merge field


					$msg = 'Dear '.$rowsCtBk['applicantName']."\r\n"."\r\n".''.$rowsOrdrInfo['prodName']."\r\n"."\r\n".''.(($rowsOrdrInfo['documentOrderNo']!="")? '('.$rowsOrdrInfo['documentOrderNo'].')':$rowsOrdrInfo['invoiceNo'])."\r\n"."\r\n".''.strip_tags($rowsEp['taskSMS']).' '."\r\n"."\r\n".'WAQAS CHEMICALS'."\n".'03008207530';



					// sending through mail button //

					$msg = str_replace($search,$replace,$msg);
					unset($replace);
					sendSMS($rowsCtBk['mobileNumber'],$msg, trim($rowsCtBk['companyName'].' '.$rowsCtBk['applicantName']), array('source'=>'task','task_id'=>(int)$taskID));
				}

			}
		}
	}
	}
}
$notificationReport = taskNotificationAttemptSummary($conn, (int)$_GET['id']);
header("location: tasks.php?action=view&id=".$_GET['id']."&act=2&notificationReport=".rawurlencode($notificationReport));

}

if (isset($_GET['action']) && $_GET['action'] === 'WhatsAppPreview') {
    $taskID = (int)($_GET['id'] ?? 0);
    $whatsAppResult = sendTaskWhatsApp($conn, $taskID, true);
    $previewData = isset($whatsAppResult[2]) && is_array($whatsAppResult[2]) ? $whatsAppResult[2] : array(
        'selected_contacts' => 0,
        'payload_count' => 0,
        'payloads' => array(),
        'errors' => array(array('error' => (string)$whatsAppResult[1]))
    );
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Content-Type: text/html; charset=utf-8');
    $payloads = isset($previewData['payloads']) && is_array($previewData['payloads']) ? $previewData['payloads'] : array();
    $previewErrors = isset($previewData['errors']) && is_array($previewData['errors']) ? $previewData['errors'] : array();
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>WhatsApp API Payload — Task #<?=(int)$taskID?></title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f7f6;color:#263238;font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.wrap{max-width:980px;margin:0 auto;padding:32px 20px 48px}.top{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:20px}h1{font-size:24px;line-height:1.25;margin:0 0 6px}.sub{color:#607d8b;margin:0}.badge{white-space:nowrap;background:#e2f4ea;color:#167445;border:1px solid #b8e2ca;border-radius:999px;padding:6px 11px;font-weight:600}.notice,.error{border-radius:8px;padding:12px 14px;margin:0 0 16px}.notice{background:#eef7ff;border:1px solid #c9e3f7;color:#285b78}.error{background:#fff1f0;border:1px solid #f0c6c2;color:#a2372d}.card{background:#fff;border:1px solid #dce4e7;border-radius:10px;box-shadow:0 2px 8px rgba(38,50,56,.06);margin:0 0 12px;overflow:hidden}.card[open]{box-shadow:0 4px 14px rgba(38,50,56,.1)}.card-head{padding:15px 18px;display:flex;align-items:center;justify-content:space-between;gap:16px;cursor:pointer;list-style:none}.card-head::-webkit-details-marker{display:none}.card-head:before{content:'\25B6';color:#78909c;font-size:11px;transition:transform .15s ease}.card[open]>.card-head{border-bottom:1px solid #e8edef}.card[open]>.card-head:before{transform:rotate(90deg)}.contact{flex:1;min-width:0}.card-head h2{font-size:16px;margin:0}.meta{color:#607d8b;font-size:13px;margin-top:3px}.method{font:700 12px/1 monospace;background:#e8f5ee;color:#177044;border-radius:4px;padding:7px 8px;height:max-content}.section{padding:15px 18px;border-bottom:1px solid #edf1f2}.section:last-child{border:0}.label{display:block;color:#607d8b;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px}.endpoint{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;word-break:break-all}.values{margin:0;padding:0;list-style:none;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px 18px}.values li{min-width:0}.values code{color:#455a64;word-break:break-word}pre{margin:0;background:#172126;color:#d8e6e0;border-radius:7px;padding:14px;overflow:auto;font:12px/1.55 ui-monospace,SFMono-Regular,Menlo,monospace}.empty{background:#fff;border:1px dashed #c8d2d6;border-radius:10px;padding:28px;text-align:center;color:#607d8b}@media(max-width:600px){.wrap{padding:20px 12px}.top{display:block}.badge{display:inline-block;margin-top:10px}.card-head{gap:10px}.method{display:none}}
</style>
</head>
<body><main class="wrap">
<div class="top"><div><h1>WhatsApp API payload</h1><p class="sub">Task #<?=(int)$taskID?> · exact request prepared for the WhatsApp button</p></div><span class="badge"><?=count($payloads)?> payload<?=count($payloads) === 1 ? '' : 's'?></span></div>
<div class="notice">Preview only — nothing was sent. Secret credentials are masked; all other payload fields and values match the API request.</div>
<?php foreach ($previewErrors as $previewError): ?><div class="error"><?=htmlspecialchars((string)($previewError['error'] ?? 'Unable to prepare payload.'), ENT_QUOTES, 'UTF-8')?></div><?php endforeach; ?>
<?php if (!$payloads): ?><div class="empty"><?=htmlspecialchars((string)$whatsAppResult[1], ENT_QUOTES, 'UTF-8')?></div><?php endif; ?>
<?php foreach ($payloads as $index => $payload):
    $recipient = trim((string)($payload['recipient'] ?? ''));
    $company = trim((string)($payload['company'] ?? ''));
    $body = isset($payload['body']) && is_array($payload['body']) ? $payload['body'] : array();
    $variables = isset($payload['variables']) && is_array($payload['variables']) ? $payload['variables'] : array();
?>
<details class="card">
<summary class="card-head"><div class="contact"><h2><?=htmlspecialchars($recipient !== '' ? $recipient : 'Recipient ' . ($index + 1), ENT_QUOTES, 'UTF-8')?></h2><div class="meta"><?=htmlspecialchars($company !== '' ? $company . ' · ' : '', ENT_QUOTES, 'UTF-8')?><?=htmlspecialchars((string)($body['sendTo'] ?? ''), ENT_QUOTES, 'UTF-8')?></div></div><span class="method">POST</span></summary>
<div class="section"><span class="label">Endpoint</span><div class="endpoint"><?=htmlspecialchars((string)($payload['url'] ?? ''), ENT_QUOTES, 'UTF-8')?></div></div>
<?php if ($variables): ?><div class="section"><span class="label">Template values</span><ul class="values"><?php foreach ($variables as $position => $variable): ?><li><strong>{{<?=$position + 1?>}}</strong> <code><?=htmlspecialchars((string)($variable['value'] ?? ''), ENT_QUOTES, 'UTF-8')?></code> <span class="meta"><?=htmlspecialchars((string)($variable['merge_tag'] ?? ''), ENT_QUOTES, 'UTF-8')?></span></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="section"><span class="label">Exact JSON body</span><pre><?=htmlspecialchars((string)json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8')?></pre></div>
</details>
<?php endforeach; ?>
</main></body></html>
<?php
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'WhatsApp') {
    $taskID = (int)($_GET['id'] ?? 0);
    $whatsAppResult = sendTaskWhatsApp($conn, $taskID);
    $_SESSION['taskWhatsAppNotice'] = $whatsAppResult[1];
    $notificationReport = taskNotificationAttemptSummary($conn, $taskID);
    header('location: tasks.php?action=view&id=' . $taskID . '&act=' . ($whatsAppResult[0] ? '3' : '4') . '&notificationReport=' . rawurlencode($notificationReport));
    exit;
}

if(isset($_GET['act']) && $_GET['act']=="delete"){
mysqli_query($conn, "DELETE FROM task_attachments WHERE taskID='".(int)$_GET['id']."'");
mysqli_query($conn,"DELETE FROM tasks WHERE id='".$_GET['id']."'");
header("location: tasks.php?act=3");
}
$action = "add";
$hdTxt="Add New";
if(isset($_GET['action']) && $_GET['action']=="edit"){
    $sqlEdit = mysqli_query($conn,"SELECT * FROM tasks WHERE id='".$_GET['id']."'");
    $rowsEdit = mysqli_fetch_assoc($sqlEdit);
    extract($rowsEdit);
    $action = "update";
    $hdTxt="Update";
} else {
    $dueDate = '';
    $repeatDate = '';
    $dueTime = '10:30:00';
    $repeatTime = '';
    $orderID = '';
    $taskMessageID = '';
    $taskSMSID = '';
    $taskWhatsAppTemplate = '';
    $subject = '';
    $priorityID = '';
    $assignTo = '';
    $isSecondReminder = '0';
    $isDraft = 0;
    $taskMessage = '';
    $taskSMS = '';
    $sendBy = '';
    $id = '';
}
include "include/header.php";
if(isset($_GET['action']) && $_GET['action']=="view"){
$sqlEdit = mysqli_query($conn,"SELECT * FROM tasks WHERE id='".$_GET['id']."'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
extract($rowsEdit);
$taskFiles = taskAttachments($conn, $id);
if (!$taskFiles && !empty($attachmentFile)) {
    $taskFiles[] = array('fileName' => $attachmentName, 'storedFile' => $attachmentFile);
}

$viewWhatsAppConfig = $whatsAppTemplates[$taskWhatsAppTemplate] ?? null;
$viewWhatsAppVariables = is_array($viewWhatsAppConfig) && isset($viewWhatsAppConfig['variables']) && is_array($viewWhatsAppConfig['variables']) ? array_values(array_map('trim', $viewWhatsAppConfig['variables'])) : array();
$viewWhatsAppVariableCount = count($viewWhatsAppVariables);
$viewOrderResult = mysqli_query($conn, "SELECT o.unitPrice,o.quantity,o.totalAmt,o.orderDelievery,o.orderPackaging,o.invoiceNo,o.eta,o.dta,o.newETA,o.expiryDate,o.paymentTerms,o.latestDateShipment,o.documentOrderNo,o.billNo,o.shippingDocInfo,p.prodName FROM orders o LEFT JOIN products p ON p.id=o.productID WHERE o.id='" . (int)$orderID . "' LIMIT 1");
$viewOrder = $viewOrderResult ? (mysqli_fetch_assoc($viewOrderResult) ?: array()) : array();
$viewOrderValues = array(
    'unit_price' => $viewOrder['unitPrice'] ?? '', 'quantity' => $viewOrder['quantity'] ?? '',
    'total_amount' => $viewOrder['totalAmt'] ?? '', 'order_transport' => $viewOrder['orderDelievery'] ?? '',
    'order_packaging' => $viewOrder['orderPackaging'] ?? '', 'product_name' => $viewOrder['prodName'] ?? '',
    'document_order_number' => $viewOrder['documentOrderNo'] ?? '', 'proforma_invoice' => $viewOrder['invoiceNo'] ?? '',
    'arrival_date' => !empty($viewOrder['eta']) ? viewDateFormat($viewOrder['eta']) : '',
    'departure_date' => !empty($viewOrder['dta']) ? viewDateFormat($viewOrder['dta']) : '',
    'latest_date_shipment' => !empty($viewOrder['latestDateShipment']) ? viewDateFormat($viewOrder['latestDateShipment']) : '',
    'document_bill_no' => $viewOrder['billNo'] ?? '', 'doucment_expiry_date' => !empty($viewOrder['expiryDate']) ? viewDateFormat($viewOrder['expiryDate']) : '',
    'arrival_new_date' => !empty($viewOrder['newETA']) ? viewDateFormat($viewOrder['newETA']) : '',
    'payment_terms' => $viewOrder['paymentTerms'] ?? '',
    'shipping_info' => $viewOrder['shippingDocInfo'] ?? ''
);
$viewAssignedCustomerIDs = filterAgentRecipientsByChannel($conn, orderAgentRecipientIDs($conn, $orderID, $assignTo), 'whatsapp');
$viewWhatsAppPayloadCounts = array();
foreach ($viewAssignedCustomerIDs as $viewCustomerID) {
    $viewCustomerResult = mysqli_query($conn, "SELECT applicantName,companyName,mobileNumber,email2 FROM customers WHERE id=" . $viewCustomerID . " LIMIT 1");
    $viewCustomer = $viewCustomerResult ? (mysqli_fetch_assoc($viewCustomerResult) ?: array()) : array();
    $viewPhone = preg_replace('/[^0-9+]/', '', (string)($viewCustomer['mobileNumber'] ?? ''));
    if (strpos($viewPhone, '00') === 0) $viewPhone = '+' . substr($viewPhone, 2);
    $viewRecipientValues = $viewOrderValues + array(
        'applicant_name' => $viewCustomer['applicantName'] ?? '',
        'company_name' => $viewCustomer['companyName'] ?? '',
        'mobile_number' => $viewPhone,
        'second_email' => $viewCustomer['email2'] ?? ''
    );
    $filledValues = 0;
    foreach ($viewWhatsAppVariables as $viewVariable) {
        if (in_array($viewVariable, whatsgTaskMergeTagNames(), true) && trim((string)($viewRecipientValues[$viewVariable] ?? '')) !== '') $filledValues++;
    }
    $viewWhatsAppPayloadCounts[] = $filledValues;
}
$viewWhatsAppPayloadCount = $viewWhatsAppPayloadCounts ? min($viewWhatsAppPayloadCounts) : 0;
$viewWhatsAppWarning = in_array('whatsapp', explode(',', (string)$sendBy), true)
    && $viewWhatsAppPayloadCount !== $viewWhatsAppVariableCount;
$viewWhatsAppWarningText = 'WhatsApp is not recommended: this template has ' . $viewWhatsAppVariableCount . ' variable(s), but only ' . $viewWhatsAppPayloadCount . ' non-empty payload value(s) are available for every assigned recipient. Turn off WhatsApp or fill the missing task or order values.';

$sqlOrder = mysqli_query($conn,"SELECT statusID FROM orders WHERE id='".$rowsEdit['orderID']."'");
$rowsOrdr = mysqli_fetch_assoc($sqlOrder);
?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Tasks</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>tasks.php">Manage Tasks</a></span></li>
<li><span>View Task</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act']) && $_GET['act']=='1'){
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>Email has been sent successfully</strong></div>';
}else if(isset($_GET['act']) && $_GET['act']=='2'){
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>SMS has been sent successfully</strong></div>';
}else if(isset($_GET['act']) && in_array($_GET['act'], array('3','4'), true)){
$notice = isset($_SESSION['taskWhatsAppNotice']) ? $_SESSION['taskWhatsAppNotice'] : 'WhatsApp send completed.';
unset($_SESSION['taskWhatsAppNotice']);
echo '<div class="alert alert-'.($_GET['act']==='3'?'success':'danger').'"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.htmlspecialchars($notice, ENT_QUOTES, 'UTF-8').'</strong></div>';
}
if (!empty($_GET['notificationReport'])) {
echo '<div class="alert alert-warning"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>Notification attempt summary</strong>'.taskNotificationAttemptSummaryHtml($_GET['notificationReport']).'</div>';
}
?>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"><?php if ($viewWhatsAppWarning): ?> <span class="label label-warning" title="<?=htmlspecialchars($viewWhatsAppWarningText, ENT_QUOTES, 'UTF-8');?>">WhatsApp payload <?=$viewWhatsAppPayloadCount;?>/<?=$viewWhatsAppVariableCount;?></span> &nbsp;<?php endif; ?><a href="<?=$siteUrl;?>tasks.php?action=edit&id=<?=$rowsEdit['id'];?>"><b>Edit</b></a> | <a href="<?=$siteUrl;?>tasks.php"><b>Back</b></a> </div>
<h2 class="panel-title">View Tasks</h2>
</header>
<div class="panel-body viewFrm">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Order *</label>
<div class="col-md-9">
<?php
$sqlOrdPrd = mysqli_query($conn,"SELECT o.id,o.vendorID,o.customerID,p.prodName FROM orders as o,products as p WHERE p.id=o.productID and o.id='".$orderID."'");
$rowsOP = mysqli_fetch_assoc($sqlOrdPrd);
if ($rowsOP) {
echo $rowsOP['prodName'].' (#'.$rowsOP['id'].')<br /><b>Vendor -</b> '.companyName($rowsOP['vendorID']).'<br /><b>Customer -</b> '.companyName($rowsOP['customerID']);
} else {
echo 'Price request (no linked order)<br /><b>Vendor -</b> '.companyName($assignTo);
}
?>
</div></div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Current Status *</label>
<div class="col-md-9 selectSrch"><?=($rowsOrdr ? statusNm($rowsOrdr['statusID']) : 'Price request');?>
</div></div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Subject</label>
<div class="col-md-9"><?=$subject?> <?=($isSecondReminder==1)?'<span class="label label-danger">Repeat</span>':'';?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Due Date</label>
<div class="col-md-9"><?=($dueDate!="" && $dueDate!="0000-00-00" && $dueDate!="1970-01-01")?viewDateFormat($dueDate):''?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label">Due Time</label>
<div class="col-md-9"><?=!empty($dueTime) ? date('g:i A', strtotime($dueTime)) : 'Any time'?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Repeat Date</label>
<div class="col-md-9"><?=($repeatDate!="0000-00-00" && $repeatDate!="1970-01-01")?viewDateFormat($repeatDate):''?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label">Repeat Time</label>
<div class="col-md-9"><?=!empty($repeatTime) ? date('g:i A', strtotime($repeatTime)) : 'Any time'?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Priority *</label>
<div class="col-md-9 selectSrch"><?=($priorityID != '' ? priorityNm($priorityID) : '')?></div></div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Assign To *</label>
<div class="col-md-9"><?php
$assignToAry = array_filter(array_map('intval', explode(",", (string)$assignTo)));
$assignedNames = array_map('customerName', $assignToAry);
echo agentEscape(implode(', ', $assignedNames));
?>
</div></div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Repeat</label>
<div class="col-md-5"><div class="paddingTxt">
<input type="checkbox" name="isSecondReminder" value="1" <?=($isSecondReminder==1)?'checked="checked"':'';?> id="inputDefault" disabled > If Yes, second reminder will be active.</div>
</div>
<label class="col-md-2 control-label">Status</label>
<div class="col-md-2"><div class="paddingTxt"><span class="label <?=((int)$isDraft === 1) ? 'label-warning' : 'label-success';?>"><?=((int)$isDraft === 1) ? 'Draft' : 'Send';?></span></div></div>
</div>

<?php

	//adding merge fields.
$sqlEp = mysqli_query($conn,"SELECT id,assignTo,orderID,subject,taskSMS,sendBy FROM tasks WHERE id='".$_GET['id']."'  ");
$numRowsExp = mysqli_num_rows($sqlEp);
$rowsOrdrInfo = array('unitPrice'=>'','quantity'=>'','totalAmt'=>'','orderDelievery'=>'','orderPackaging'=>'','prodName'=>'','documentOrderNo'=>'','invoiceNo'=>'','email2'=>'','eta'=>'','dta'=>'','latestDateShipment'=>'','billNo'=>'','expiryDate'=>'','newETA'=>'','paymentTerms'=>'','shippingDocInfo'=>'');
$rowsCtBk = array('applicantName'=>'');

while($rowsEp = mysqli_fetch_assoc($sqlEp)){

	if($rowsEp['assignTo']!=""){
		//Order Info

				$sqlOrdrInfo = mysqli_query($conn,"SELECT o.id,o.unitPrice,o.quantity,o.totalAmt,o.orderOrigin,o.orderDelievery,o.orderPackaging,o.invoiceNo,o.eta,o.dta,o.newETA,o.documentDate,o.expiryDate,o.paymentTerms,o.latestDateShipment,o.documentOrderNo,o.billNo,o.shippingDocInfo,p.prodName, c.email2 FROM orders as o,products as p, customers as c WHERE p.id=o.productID AND c.id=o.customerID AND o.id='".$rowsEp['orderID']."' ");
		$rowsOrdrInfo = mysqli_fetch_assoc($sqlOrdrInfo) ?: $rowsOrdrInfo;
		$assignToAry = orderAgentRecipientIDs($conn, $rowsEp['orderID'], $rowsEp['assignTo']);

			foreach($assignToAry as $assignToVal){
				$sqlCtBk = mysqli_query($conn,"SELECT applicantName,companyName,mobileNumber,email,email2,isEnableEmail FROM customers WHERE id='".$assignToVal."' ");
				$rowsCtBk = mysqli_fetch_assoc($sqlCtBk);
			}
	}

}



					$values = array(
							'unit_price' => $rowsOrdrInfo['unitPrice'],
							'quantity' => $rowsOrdrInfo['quantity'],
							'total_amount' => $rowsOrdrInfo['totalAmt'],
							'order_transport' => $rowsOrdrInfo['orderDelievery'],
							'order_packaging' => $rowsOrdrInfo['orderPackaging'],
							'product_name'    => $rowsOrdrInfo['prodName'],
							'document_order_number'    => $rowsOrdrInfo['documentOrderNo'],
							'proforma_invoice'    => $rowsOrdrInfo['invoiceNo'],
							'second_email'    => ($rowsCtBk['email2'] ?? ''),
							'arrival_date' => viewDateFormat($rowsOrdrInfo['eta']),
							'departure_date' => viewDateFormat($rowsOrdrInfo['dta']),
							'latest_date_shipment' => viewDateFormat($rowsOrdrInfo['latestDateShipment']),
							'document_bill_no' => $rowsOrdrInfo['billNo'],
							'doucment_expiry_date' => viewDateFormat($rowsOrdrInfo['expiryDate']),
							'arrival_new_date' => viewDateFormat($rowsOrdrInfo['newETA']),
							'payment_terms' => $rowsOrdrInfo['paymentTerms'],
							'shipping_info' => $rowsOrdrInfo['shippingDocInfo']);

							$search = array();
							$replace = array();

							//var_dump($values);

							foreach($values as $index => $value) {


								if(!empty($value));


								$search[] = '{'.$index.'}';
									$replace[] = $value;


								}




$taskMessage = str_replace($search,$replace,$taskMessage);
$taskSMS = str_replace($search,$replace,$taskSMS);
// end of merge fields in view
?>



<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Message</label>
<div class="col-md-9"><?=(($taskMessageID)?'<b>Template Subject :-  '.sendTemplateNm($taskMessageID).'</b> <br />':'').$taskMessage?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">SMS</label>
<div class="col-md-9"><?=(($taskSMSID)?'<b>Template Subject :- '.sendTemplateNm($taskSMSID).'</b> <br />':'').$taskSMS?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label">WhatsApp template</label>
<div class="col-md-9"><?=htmlspecialchars(isset($whatsAppTemplates[$taskWhatsAppTemplate]) ? $whatsAppTemplates[$taskWhatsAppTemplate]['name'] : '', ENT_QUOTES, 'UTF-8')?></div>
</div>
<?php if (!empty($taskFiles)): ?>
<div class="form-group">
<label class="col-md-2 control-label">Attachments</label>
<div class="col-md-9"><?php foreach ($taskFiles as $taskFile): ?><div><a href="<?=$siteUrlMain?>img/<?=rawurlencode($taskFile['storedFile'])?>" target="_blank"><?=htmlspecialchars($taskFile['fileName'] ? $taskFile['fileName'] : $taskFile['storedFile'])?></a></div><?php endforeach; ?></div>
</div>
<?php endif; ?>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Send By</label>
<div class="col-md-9">
<div class="paddingTxt"><?php $sendByAry = explode(",",$sendBy); ?>
<input type="checkbox" name="sendBy[]" value="mail" <?=(in_array('mail',$sendByAry)?'checked="checked"':'');?> > Mail &nbsp;
<input type="checkbox" name="sendBy[]" value="sms" <?=(in_array('sms',$sendByAry)?'checked="checked"':'');?> > SMS &nbsp;
<input type="checkbox" name="sendBy[]" value="whatsapp" <?=(in_array('whatsapp',$sendByAry)?'checked="checked"':'');?> > WhatsApp &nbsp;
<input type="checkbox" name="sendBy[]" value="immediate" <?=(in_array('immediate',$sendByAry)?'checked="checked"':'');?> > Immediate
&nbsp;   &nbsp;
<?php if (!(int)$isDraft && in_array('mail', $sendByAry, true)): ?><a onclick="return confirm('Are you sure you want send Email?');" class="btn btn-default" href="<?=$siteUrl;?>tasks.php?action=mail&id=<?=$id?>" title="Send Email Notification"><b>Mail</b></a>  &nbsp;<?php endif; ?>
<?php if (!(int)$isDraft && in_array('sms', $sendByAry, true)): ?><a title="Send SMS Notification" onclick="return confirm('Are you sure you want send SMS?');" class="btn btn-default" href="<?=$siteUrl;?>tasks.php?action=SMS&id=<?=$id?>"><b>SMS</b></a><?php endif; ?>
<?php if (in_array('whatsapp', $sendByAry, true)): ?>
&nbsp;<a title="Open the exact WhatsApp API payload in a new window" class="btn btn-default" target="_blank" rel="noopener" href="<?=$siteUrl;?>tasks.php?action=WhatsAppPreview&amp;id=<?=(int)$id?>"><b>View Payload</b></a>
<?php if (!(int)$isDraft): ?>
&nbsp;<a title="Send WhatsApp template" onclick="return confirm('Send WhatsApp template to all assigned contacts?');" class="btn btn-success" href="<?=$siteUrl;?>tasks.php?action=WhatsApp&amp;id=<?=(int)$id?>"><b>WhatsApp</b></a>
<?php endif; ?>
<?php endif; ?>

</div>



</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<button type="button" onclick="window.location.href='<?=$siteUrl;?>tasks.php'" class="btn btn-default">Back</button>
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
}else if(isset($_GET['action']) && ($_GET['action']=="add" || $_GET['action']=="edit")){
?>
<style type="text/css">.aa{color:#3C0 !important;}</style>
<script type="text/javascript">
$(document).ready(function(){
function updateRepeatScheduleFields(clearValues) {
    var enabled = $('#isSecondReminder').is(':checked');
    $('#repeatDate, #repeatTime').prop('disabled', !enabled);
    if (!enabled && clearValues) {
        $('#repeatDate, #repeatTime').val('');
    }
}
$('#isSecondReminder').on('change', function(){ updateRepeatScheduleFields(true); });
updateRepeatScheduleFields(false);
$("#orderID").change(function(){
    var orderID = $(this).val();
    var $assignTo = $('#assignTo');
    $assignTo.empty().append(new Option('- Select -', ''));
    if (!orderID) return;

    $.ajax({
        url: "<?=$siteUrl;?>assignAjax.php?act=urs&id=" + encodeURIComponent(orderID),
        type: "GET",
        dataType: "json",
        success: function(contacts){
            $assignTo.empty().append(new Option(contacts.length ? '- Select -' : 'No contacts available', ''));
            $.each(contacts, function(_, contact){
                var option = new Option(contact.label, contact.id);
                if (parseInt(contact.type, 10) === 3) option.style.color = 'red';
                $assignTo.append(option);
            });
        },
        error: function(){
            $assignTo.empty().append(new Option('Could not load contacts', ''));
        }
    });
});
function updateTaskDraftFields() {
    var isDraft = $('#deliveryStatus').val() === 'draft';
    $('#orderID').prop('required', !isDraft);
}
$('#deliveryStatus').on('change', updateTaskDraftFields);
$('#taskStatusBadgeMenu [data-delivery-status]').on('click', function(event){
    event.preventDefault();
    var status = $(this).attr('data-delivery-status');
    $('#deliveryStatus').val(status).trigger('change');
    $('#taskStatusBadgeText').text(status === 'draft' ? 'Draft' : 'Send');
    $('#taskStatusBadge').toggleClass('label-warning', status === 'draft').toggleClass('label-success', status === 'send');
});
updateTaskDraftFields();
$('#taskForm').on('submit', function(event){
    var submitter = event.originalEvent && event.originalEvent.submitter;
    if ((submitter && submitter.value === 'Draft') || $('#deliveryStatus').val() === 'draft') return;

    var dueDate = $.trim($(this).find('[name="dueDate"]').val() || '');
    var repeatDate = $.trim($(this).find('[name="repeatDate"]').val() || '');
    var dueTime = $.trim($(this).find('[name="dueTime"]').val() || '');
    var repeatTime = $.trim($(this).find('[name="repeatTime"]').val() || '');
    var dueDateField = $(this).find('[name="dueDate"]')[0];
    var repeatDateField = $(this).find('[name="repeatDate"]')[0];
    dueDateField.setCustomValidity('');
    repeatDateField.setCustomValidity('');
    if (dueTime !== '' && dueDate === '') {
        event.preventDefault();
        dueDateField.setCustomValidity('Due Date is required when Due Time is set.');
        dueDateField.reportValidity();
        return false;
    }
    if (repeatTime !== '' && repeatDate === '') {
        event.preventDefault();
        repeatDateField.setCustomValidity('Repeat Date is required when Repeat Time is set.');
        repeatDateField.reportValidity();
        return false;
    }
    function sortableSchedule(dateValue, timeValue) {
        var parts = dateValue.split('-');
        if (parts.length !== 3) return {date: '', dateTime: ''};
        var normalizedDate = parts[2] + '-' + parts[1] + '-' + parts[0];
        return {date: normalizedDate, dateTime: timeValue === '' ? '' : normalizedDate + 'T' + timeValue};
    }
    var currentScheduleTime = '<?=taskScheduleNow($conn)->format('Y-m-d\TH:i')?>';
    var currentScheduleDate = '<?=taskScheduleNow($conn)->format('Y-m-d')?>';
    var dueSchedule = sortableSchedule(dueDate, dueTime);
    var repeatSchedule = sortableSchedule(repeatDate, repeatTime);
    if (dueSchedule.date !== '' && (dueSchedule.date < currentScheduleDate || (dueSchedule.date === currentScheduleDate && dueSchedule.dateTime !== '' && dueSchedule.dateTime < currentScheduleTime))) {
        event.preventDefault();
        dueDateField.setCustomValidity('Due Date and Time cannot be in the past.');
        dueDateField.reportValidity();
        return false;
    }
    if (repeatSchedule.date !== '' && (repeatSchedule.date < currentScheduleDate || (repeatSchedule.date === currentScheduleDate && repeatSchedule.dateTime !== '' && repeatSchedule.dateTime < currentScheduleTime))) {
        event.preventDefault();
        repeatDateField.setCustomValidity('Repeat Date and Time cannot be in the past.');
        repeatDateField.reportValidity();
        return false;
    }
    if (dueDate !== '' && repeatDate !== '' && dueDate === repeatDate) {
        return confirm('Due Date and Repeat Date are the same. Do you want to continue?');
    }
});
$("#taskSMSID").change(function(){ $('#saveSMSTemplate').prop('checked', false); $.ajax({ url:"<?=$siteUrl;?>assignAjax.php?act=smsTmp&id=" + $(this).val(),type: "GET",success: function(data){ $('#taskSMS').html(data); } }); });
$("#taskMessageID").change(function(){ $('#saveEmailTemplate').prop('checked', false); $.ajax({ url:"<?=$siteUrl;?>assignAjax.php?act=mailTmp&id=" + $(this).val(),type: "GET",success: function(data){ var x = data; var optn=x.split(',~,');  $('#subject').val(optn[0]); $('.taskMessageInfo .note-editable').html(optn[1]); } }); });
var whatsAppTemplates = <?=json_encode($whatsAppTemplates, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);?>;
var whatsAppMappedVariables = <?=json_encode(whatsgTaskMergeTagNames());?>;
function updateTaskWhatsAppPayloadWarning() {
    var whatsAppEnabled = $('#taskForm input[name="sendBy[]"][value="whatsapp"]').is(':checked');
    var template = whatsAppTemplates[$('#taskWhatsAppTemplate').val()];
    var warning = $('#taskWhatsAppPayloadWarning');
    if (!whatsAppEnabled || !template || !Array.isArray(template.variables)) {
        warning.hide().text('');
        return;
    }
    var variableCount = template.variables.length;
    var payloadValueCount = template.variables.filter(function(variable) {
        return whatsAppMappedVariables.indexOf(String(variable).trim()) !== -1;
    }).length;
    var exampleValuesMismatch = Array.isArray(template.variableExamples)
        && template.variableExamples.filter(function(value) { return String(value).trim() !== ''; }).length !== variableCount;
    if (payloadValueCount !== variableCount || exampleValuesMismatch) {
        var exampleValueCount = Array.isArray(template.variableExamples)
            ? template.variableExamples.filter(function(value) { return String(value).trim() !== ''; }).length
            : null;
        var detail = 'This template has ' + variableCount + ' variable(s), but only ' + payloadValueCount + ' payload value(s) can be filled';
        if (exampleValueCount !== null) detail += ' and ' + exampleValueCount + ' example value(s) are configured';
        warning.text('WhatsApp is not recommended for this task: ' + detail + '. Turn off WhatsApp or choose a template with all required values.').show();
        return;
    }
    warning.hide().text('');
}
$('#taskWhatsAppTemplate').on('change', updateTaskWhatsAppPayloadWarning);
$('#taskForm input[name="sendBy[]"][value="whatsapp"]').on('change', updateTaskWhatsAppPayloadWarning);
updateTaskWhatsAppPayloadWarning();
});
</script>
<script src="<?=$siteUrl?>assets/vendor/summernote/summernote.js"></script>
<link rel="stylesheet" href="<?=$siteUrl?>assets/vendor/summernote/summernote.css" />
<link rel="stylesheet" href="<?=$siteUrl?>assets/vendor/summernote/summernote-bs3.css" />
<link rel="stylesheet" href="<?=$siteUrl?>assets/bootstrap-select.css">
<script src="<?=$siteUrl?>assets/bootstrap-select.js"></script>
<script type="text/javascript">
$(document).ready(function(){
$(".dueDate").datepicker({todayHighlight:true,autoclose:true});
$(".repeatDate").datepicker({todayHighlight:true,autoclose:true});
});
</script>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Tasks</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>tasks.php">Manage Tasks</a></span></li>
<li><span><?=$hdTxt;?> Task</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>tasks.php"><b>Back</b></a> </div>
<h2 class="panel-title"><?=$hdTxt;?> Tasks</h2>
</header>
<div class="panel-body">
<form id="taskForm" class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<?php if (!empty($_GET['uploadError'])): ?>
<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><?=htmlspecialchars($_GET['uploadError'])?></div>
<?php endif; ?>
<?php if (isset($_GET['whatsappError'])): ?>
<div class="alert alert-danger">Choose a configured WhatsApp template before enabling WhatsApp for this task.</div>
<?php endif; ?>
<?php if (isset($_GET['scheduleError'])): ?>
<div class="alert alert-danger"><?php
$scheduleErrors = array(
    'repeatDate' => 'Repeat Date is required when Repeat Time is set.',
    'duePast' => 'Due Date and Time cannot be in the past.',
    'repeatPast' => 'Repeat Date and Time cannot be in the past.',
    'dueDate' => 'Due Date is required when Due Time is set.'
);
echo htmlspecialchars($scheduleErrors[$_GET['scheduleError']] ?? $scheduleErrors['dueDate'], ENT_QUOTES, 'UTF-8');
?></div>
<?php endif; ?>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Order *</label>
<div class="col-md-9 selectSrch">
<?php if ($action == "update" && (int)$orderID === 0): ?>
<input type="hidden" name="orderID" value="0">
<div class="form-control-static">Price request (no linked order)</div>
<?php else: ?>
<select class="selectpicker form-control" data-selected-text-format="values" name="orderID" id="orderID" data-live-search="true" data-live-search-placeholder="Search" required>
<option value="">- Select -</option>
<?php
$sqlOrdPrd = mysqli_query($conn,"SELECT o.id,p.prodName FROM orders as o,products as p WHERE p.id=o.productID ORDER BY p.prodName ASC");
$numRowsOrdPrd = mysqli_num_rows($sqlOrdPrd);
echo "<script>console.log('Number of orders: " . $numRowsOrdPrd . "');</script>";
while($rowsOP = mysqli_fetch_assoc($sqlOrdPrd)){
$selected = '';
if ($rowsOP['id'] == $orderID) {
    $selected = 'selected="selected"';
}
if (isset($_GET['orderID']) && $rowsOP['id'] == $_GET['orderID']) {
    $selected = 'selected="selected"';
}
echo '<option value="'.$rowsOP['id'].'" '.$selected.' >'.$rowsOP['prodName'].' (#'.$rowsOP['id'].')</option>';
}
?>
</select>
<?php endif; ?>
</div></div>

<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Due Date</label>
<div class="col-md-6">
<input type="text" class="form-control dueDate" readonly="readonly" data-date-format="dd-mm-yyyy" name="dueDate" value="<?=($dueDate!="" && $dueDate!="0000-00-00" && $dueDate!="1970-01-01")?viewDateFormat($dueDate):''?>" id="inputDefault" >
<input type="time" class="form-control" name="dueTime" value="<?=htmlspecialchars(!empty($dueTime) ? substr($dueTime, 0, 5) : '', ENT_QUOTES, 'UTF-8')?>" aria-label="Due time" style="margin-top:8px">
</div>
</div>
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Repeat Date</label>
<div class="col-md-6">
<input type="text" class="form-control repeatDate" readonly="readonly" data-date-format="dd-mm-yyyy" name="repeatDate" value="<?=($repeatDate!="0000-00-00" && $repeatDate!="1970-01-01")?viewDateFormat($repeatDate):''?>" id="repeatDate" <?=((int)$isSecondReminder===1)?'':'disabled'?> >
<input type="time" class="form-control" name="repeatTime" id="repeatTime" value="<?=htmlspecialchars(!empty($repeatTime) ? substr($repeatTime, 0, 5) : '', ENT_QUOTES, 'UTF-8')?>" aria-label="Repeat time" style="margin-top:8px" <?=((int)$isSecondReminder===1)?'':'disabled'?>>
</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="isSecondReminder">Repeat</label>
<div class="col-md-5">
<div class="paddingTxt"><label><input type="checkbox" name="isSecondReminder" value="1" id="isSecondReminder" <?=((int)$isSecondReminder===1)?'checked="checked"':'';?>> If Yes, second reminder will be active.</label></div>
</div>
<label class="col-md-2 control-label" for="taskStatusBadge">Status</label>
<div class="col-md-2">
<input type="hidden" name="deliveryStatus" id="deliveryStatus" value="<?=((int)$isDraft === 1) ? 'draft' : 'send';?>">
<div class="dropdown paddingTxt">
<button type="button" id="taskStatusBadge" class="label <?=((int)$isDraft === 1) ? 'label-warning' : 'label-success';?> dropdown-toggle" style="border:0; font-size:14px; padding:9px 16px; min-width:90px;" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
<span id="taskStatusBadgeText"><?=((int)$isDraft === 1) ? 'Draft' : 'Send';?></span> <span class="caret"></span>
</button>
<ul class="dropdown-menu" id="taskStatusBadgeMenu" aria-labelledby="taskStatusBadge">
<li><a href="#" data-delivery-status="send">Send</a></li>
<li><a href="#" data-delivery-status="draft">Draft</a></li>
</ul>
</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Priority</label>
<div class="col-md-9 selectSrch">
<select class="form-control" name="priorityID">
<option value="">- Select -</option>
<?php
$sqlPrty = mysqli_query($conn,"SELECT id,priorityNm FROM task_priority ORDER BY priorityNm ASC");
$defaultPriorityID = '';
$defaultPriorityResult = mysqli_query($conn,"SELECT id FROM task_priority WHERE LOWER(priorityNm)='low' LIMIT 1");
if ($defaultPriorityResult && ($defaultPriority = mysqli_fetch_assoc($defaultPriorityResult))) {
    $defaultPriorityID = $defaultPriority['id'];
}
while($rowsPrty = mysqli_fetch_assoc($sqlPrty)){
  $selectedPriorityID = !empty($priorityID) ? $priorityID : (($action == 'add') ? $defaultPriorityID : '');
  echo '<option value="'.$rowsPrty['id'].'" '.(($rowsPrty['id']==$selectedPriorityID)?'selected="selected"':'').' >'.$rowsPrty['priorityNm'].'</option>';
}
?>
</select>
</div></div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Assign To * </label>
<div class="col-md-9 selectSrch">
<select class="form-control" name="assignTo[]" id="assignTo" multiple >
<option>- Select -</option>
<?php
if($action == "update" && (int)$orderID === 0){
    $assignToAry = array_filter(array_map('intval', explode(",", (string)$assignTo)));
    if ($assignToAry) {
        $sqlPriceVendors = mysqli_query($conn, "SELECT id,companyName,type FROM customers WHERE id IN (" . implode(",", $assignToAry) . ") ORDER BY companyName ASC");
        while ($rowPriceVendor = mysqli_fetch_assoc($sqlPriceVendors)) {
            echo '<option value="'.$rowPriceVendor['id'].'" '.(in_array((int)$rowPriceVendor['id'], $assignToAry, true) ? 'selected="selected"' : '').' >'.$rowPriceVendor['companyName'].' ('.typeNm($rowPriceVendor['type']).')</option>';
        }
    }
} elseif($action == "update"){
	$assignToAry = @explode(",",$assignTo);
	$sqlOrdr = mysqli_query($conn,"SELECT customerID,vendorID,agentID FROM orders where id='".$orderID."'");
	$rowOrdr = $sqlOrdr ? mysqli_fetch_assoc($sqlOrdr) : false;
	$orderCustomerIDForContacts = $rowOrdr ? (int)$rowOrdr['customerID'] : 0;
	$orderVendorIDForContacts = $rowOrdr ? (int)$rowOrdr['vendorID'] : 0;
	$orderAgentID = $rowOrdr ? (int)$rowOrdr['agentID'] : 0;
	$taskContactAccountIDs = $orderCustomerIDForContacts . ',' . $orderVendorIDForContacts;
	$agentCondition = $orderAgentID > 0 ? ' OR id=' . $orderAgentID : '';
	$sqlOrderCustmr = mysqli_query($conn,"SELECT id,applicantName,companyName,type FROM customers WHERE (isOtherCustomer='1' OR id IN (".$taskContactAccountIDs.") OR assignToID IN (".$taskContactAccountIDs.")".$agentCondition.") ORDER BY type,applicantName ASC");
	$numRowOC = $sqlOrderCustmr ? mysqli_num_rows($sqlOrderCustmr) : 0;
	if($numRowOC>0){
		while($rowOC = mysqli_fetch_assoc($sqlOrderCustmr)){
			$optionLabel = $rowOC['companyName'].' ('.typeNm($rowOC['type']).')'.((int)$rowOC['type'] === 4 ? ' — '.$rowOC['applicantName'] : '');
			echo '<option value="'.$rowOC['id'].'" '.((in_array($rowOC['id'],$assignToAry)?'selected="selected"':'')).' >'.agentEscape($optionLabel).'</option>';
		}
	}
}else{
    $assignToAry = []; // ← ADD THIS LINE

	if(isset($_GET['orderID']) && $_GET['orderID'] != ''){
	$orderIDForContacts = (int)$_GET['orderID'];
	$sqlOrdr = mysqli_query($conn,"SELECT customerID,vendorID,agentID FROM orders where id='".$orderIDForContacts."'");
	$rowOrdr = $sqlOrdr ? mysqli_fetch_assoc($sqlOrdr) : false;
	$orderCustomerIDForContacts = $rowOrdr ? (int)$rowOrdr['customerID'] : 0;
	$orderVendorIDForContacts = $rowOrdr ? (int)$rowOrdr['vendorID'] : 0;
	$orderAgentID = $rowOrdr ? (int)$rowOrdr['agentID'] : 0;
	$taskContactAccountIDs = $orderCustomerIDForContacts . ',' . $orderVendorIDForContacts;
	$agentCondition = $orderAgentID > 0 ? ' OR id=' . $orderAgentID : '';
	$sqlOrderCustmr = mysqli_query($conn,"SELECT id,applicantName,companyName,type FROM customers WHERE (isOtherCustomer='1' OR id IN (".$taskContactAccountIDs.") OR assignToID IN (".$taskContactAccountIDs.")".$agentCondition.") ORDER BY type,applicantName ASC");
	$numRowOC = $sqlOrderCustmr ? mysqli_num_rows($sqlOrderCustmr) : 0;
	if($numRowOC>0){
		while($rowOC = mysqli_fetch_assoc($sqlOrderCustmr)){
			$optionLabel = $rowOC['companyName'].' ('.typeNm($rowOC['type']).')'.((int)$rowOC['type'] === 4 ? ' — '.$rowOC['applicantName'] : '');
			echo '<option value="'.$rowOC['id'].'" '.((in_array($rowOC['id'],$assignToAry)?'selected="selected"':'')).' >'.agentEscape($optionLabel).'</option>';
		}
	}
	}
}
?>
</select>
</div></div>


<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label"  for="inputDefault">Email Template</label>
<div class="col-md-8"><div class="paddingTxt">
<select class="form-control" name="taskMessageID" id="taskMessageID" >
<option value="">- Select -</option>
<?php
$sqlMailTmp = mysqli_query($conn,"SELECT id,subject FROM send_templates WHERE isSMS='0' ORDER BY subject ASC");
while($rowMT = mysqli_fetch_assoc($sqlMailTmp)){
echo '<option value="'.$rowMT['id'].'" '.((($rowMT['id']==$taskMessageID) || ($rowMT['id']==($_GET['taskMessageID'] ?? '')))?'selected="selected"':'').' >'.$rowMT['subject'].'</option>';
}
?>
</select>
<label class="checkbox-inline"><input type="checkbox" name="saveEmailTemplate" id="saveEmailTemplate" value="1"> Save to Template <small>(updates the selected email template's subject and message)</small></label>
</div>
</div>
</div>
<div class="col-md-6">
<label class="col-md-3 control-label" style="padding:12px 0 0 0;" for="inputDefault">SMS Template</label>
<div class="col-md-8"><div class="paddingTxt">
<select class="form-control" name="taskSMSID" id="taskSMSID" >
<option value="">- Select -</option>
<?php
$sqlSMSTmp = mysqli_query($conn,"SELECT id,subject FROM send_templates WHERE isSMS='1' ORDER BY subject ASC");
while($rowSMSTmp = mysqli_fetch_assoc($sqlSMSTmp)){
echo '<option value="'.$rowSMSTmp['id'].'" '.((($rowSMSTmp['id']==$taskSMSID) || ($rowSMSTmp['id']==($_GET['taskSMSID'] ?? '')))?'selected="selected"':'').' >'.$rowSMSTmp['subject'].'</option>';
}
?>
</select>
<label class="checkbox-inline"><input type="checkbox" name="saveSMSTemplate" id="saveSMSTemplate" value="1"> Save to Template <small>(updates the selected SMS template's message)</small></label>
</div>
</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="taskWhatsAppTemplate">WhatsApp Template</label>
<div class="col-md-9">
<select class="form-control" name="taskWhatsAppTemplate" id="taskWhatsAppTemplate">
<option value="">- Select -</option>
<?php foreach ($whatsAppTemplates as $templateLabel=>$templateConfig): ?>
<option value="<?=htmlspecialchars($templateLabel, ENT_QUOTES, 'UTF-8')?>" <?=((string)$taskWhatsAppTemplate===(string)$templateLabel?'selected':'')?>><?=htmlspecialchars($templateLabel, ENT_QUOTES, 'UTF-8')?> (<?=htmlspecialchars($templateConfig['name'], ENT_QUOTES, 'UTF-8')?><?php $templateMediaType = whatsgTemplateMediaType($templateConfig); echo $templateMediaType === 'document' ? ', document header' : ($templateMediaType === 'image' ? ', image header' : ''); ?>)</option>
<?php endforeach; ?>
</select>
<small class="text-muted">Templates come from approved WhatsApp templates. <a href="<?=$siteUrl;?>whatsapp_templates.php">Manage templates</a>.</small>
<div id="taskWhatsAppPayloadWarning" class="alert alert-warning" style="display:none;margin-top:10px" role="alert"></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Subject</label>
<div class="col-md-9">
<input type="text" class="form-control" name="subject" value="<?=$subject?>" id="subject" >
</div>
</div>

<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Merge Tags</label>
<div class="col-md-9">
<select id="texteditor_selection">
    <option value=""></option>
    <option value="{unit_price}">{unit_price}</option>
    <option value="{quantity}">{quantity}</option>
    <option value="{total_amount}">{total_amount}</option>
    <option value="{order_transport}">{order_transport}</option>
    <option value="{product_name}">{product_name}</option>
    <option value="{document_order_number}">{document_order_number}</option>
    <option value="{proforma_invoice}">{proforma_invoice}</option>
    <option value="{second_email}">{second_email}</option>
    <option value="{arrival_date}">{arrival_date}</option>
    <option value="{departure_date}">{departure_date}</option>
    <option value="{document_bill_no}">{document_bill_no}</option>
    <option value="{doucment_expiry_date}">{doucment_expiry_date}</option>
	<option value="{arrival_new_date}">{arrival_new_date}</option>
    <option value="{payment_terms}">{payment_terms}</option>
    <option value="{shipping_info}">{shipping_info}</option>

</select>
<script>
$('#texteditor_selection').change(function(){

$('#taskMessage').code($('#taskMessage').code()+" "+$('#texteditor_selection option:selected').text());


});
</script>
</div>
</div>


<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Message By Email</label>
<div class="col-md-9 taskMessageInfo">

<textarea class="summernote" id="taskMessage" name="taskMessage" data-plugin-summernote data-plugin-options='{ "height": 200, "codemirror": { "theme": "ambiance" } }'><?php echo htmlspecialchars($taskMessage); ?></textarea>
<br /><copyright><strong>Merge Fields : </strong>
<style>

small {
 font-family: Arial;
 font-size: 9px;

}

</style>
<p>Merge Fields</p>
<table>
  <tr>
    <th>Unit price <small> (returns integer)</small></th>
    <th>{unit_price}</th>
  </tr>
  <tr>
 <th>Quanity price <small> (returns integer)</small></th>
    <td>{quantity}</td>
  </tr>
  <tr>
 <th>Total Amount <small>(returns integer)</small></th>
    <td>{total_amount}</td>
  </tr>
  <tr>
   <th>Transport<small> (returns integer)</small></th>
    <td>{order_transport}</td>
  </tr>
  <tr>
   <th>Product Name<small> (returns string) </small></th>
    <td>{product_name}</td>
  </tr>
  <tr>
   <th>Document No. <small> (returns mixed integer) </small></th>
    <td>{document_order_number}</td>
  </tr>
  <tr>
    <th>Proforma Invoice No. <small> (returns mixed) </small></th>
    <td>{proforma_invoice}</td>
  </tr>
  <tr>
 <th>Email (2)<small> (returns strings, symbol) </small></th>
    <td>{second_email}</td>
  </tr>
  <tr>
  <th>Vessel Arrival Date<small> (returns dd-mm-yyyy)</small></th>
    <td>{arrival_date}</td>
  </tr>
  <tr>
 <th>Vessel Departure Date<small> (returns dd-mm-yyyy)</small></th>
    <td>{departure_date}</td>
  </tr>
  <tr>
  <th>Latest Date of Shipment<small>(returns dd-mm-yyyy)</small></th>
    <td>{latest_date_shipment}</td>
  </tr>
  <tr>
    <th>Bill of Lading<small> (returns mixed integer)</small></th>
    <td>{document_bill_no}</td>
  </tr>
  <tr>
 <th>Document Expiry Date<small> (returns dd-mm-yyyy)</small></th>
    <td>{doucment_expiry_date}</td>
  </tr>
  <tr>
 <th>Re-assigned ETA<small> (returns dd-mm-yyyy)</small></th>
    <td>{arrival_new_date}</td>
  </tr>
  <tr>
   <th>Payment Terms <small> (returns integer)</small></th>
    <td>{payment_terms}</td>
  </tr>
  <tr>
    <th>Shipping Info / Tracking Number</th>
    <td>{shipping_info}</td>
  </tr>
</table>
</copyright><br />
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Message By SMS</label>
<div class="col-md-9">
<textarea id="taskSMS" class="form-control" rows="4" name="taskSMS" ><?php echo htmlspecialchars($taskSMS); ?></textarea>

</div>
</div>

<div class="form-group">
<label class="col-md-2 control-label" for="taskAttachment">Attachment</label>
<div class="col-md-9">
<input type="file" class="form-control" name="taskAttachments[]" id="taskAttachment" multiple accept="application/pdf,application/zip,.pdf,.zip,image/*">
<span class="hintTxt">Select one or more PDF, ZIP, or image files (maximum 10 MB each).</span>
<?php $taskFiles = !empty($id) ? taskAttachments($conn, $id) : array(); if (!empty($taskFiles)): ?><br><b>Current files:</b><?php foreach ($taskFiles as $taskFile): ?><div><a href="<?=$siteUrlMain?>img/<?=rawurlencode($taskFile['storedFile'])?>" target="_blank"><?=htmlspecialchars($taskFile['fileName'])?></a></div><?php endforeach; ?><?php endif; ?>
</div>
</div>

<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Tags</label>
<div class="col-md-9">
<select id="sms_selection">
    <option value="{unit_price}">{unit_price}</option>
    <option value="{quantity}">{quantity}</option>
    <option value="{total_amount}">{total_amount}</option>
    <option value="{order_transport}">{order_transport}</option>
    <option value="{product_name}">{product_name}</option>
    <option value="{document_order_number}">{document_order_number}</option>
    <option value="{proforma_invoice}">{proforma_invoice}</option>
    <option value="{second_email}">{second_email}</option>
    <option value="{arrival_date}">{arrival_date}</option>
    <option value="{departure_date}">{departure_date}</option>
    <option value="{document_bill_no}">{document_bill_no}</option>
    <option value="{doucment_expiry_date}">{doucment_expiry_date}</option>
	<option value="{arrival_new_date}">{arrival_new_date}</option>
    <option value="{payment_terms}">{payment_terms}</option>
    <option value="{shipping_info}">{shipping_info}</option>

</select>
<script>
$('#sms_selection').change(function(){
    $('#taskSMS').val($('#taskSMS').val()+" "+$('#sms_selection option:selected').text());
});
</script>
</br>
</br>



<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Send By</label>
<div class="col-md-9">
<div class="paddingTxt"><?php $sendByAry = explode(",",$sendBy); ?>
<input type="checkbox" name="sendBy[]" value="mail" <?=(in_array('mail',$sendByAry)?'checked="checked"':'');?> > Mail &nbsp;
<input type="checkbox" name="sendBy[]" value="sms" <?=(in_array('sms',$sendByAry)?'checked="checked"':'');?> > SMS &nbsp;
<input type="checkbox" name="sendBy[]" value="whatsapp" <?=(in_array('whatsapp',$sendByAry)?'checked="checked"':'');?> > WhatsApp &nbsp;
<?php
if($_GET['action']=="add" || (int)$isDraft === 1){
echo '<input type="checkbox" name="sendBy[]" value="immediate" '.((in_array('immediate',$sendByAry)?'checked="checked"':'')).' > Immediate';
}
?>
</div>
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<input type="hidden" name="action" value="<?=$action;?>" />
<input type="hidden" name="id" value="<?=$id;?>" />
<button class="btn btn-primary" name="submit" value="Submit">Submit</button>
<button type="submit" class="btn btn-default" name="submit" value="Draft" formnovalidate>Draft</button>
<button type="button" onclick="window.location.href='<?=$siteUrl;?>tasks.php'" class="btn btn-default">Cancel</button>
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
<?php }else{  ?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Task</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrlMain;?>cronJobBtn.php" onclick="return confirm(\'Are you sure you want to RUN CRON JOB NOW?\');" > <i class="fa fa-refresh"></i> <b>Run Cron Now</b>  </a> </li>
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span>Manage Task</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act'])){
if($_GET['act']=="1"){
$msgTxt='New Task has been added successfully';
}else if($_GET['act']=="2"){
$msgTxt='Task has been updated successfully';
}else if($_GET['act']=="3"){
$msgTxt='Task has been deleted successfully';
}else if($_GET['act']=="4"){
$msgTxt='<b>Success Message</b> Cron Job has been run successfully';
}else if($_GET['act']=="5"){
$msgTxt='<b>Task Message</b> Nothing to execute. Any scheduled tasks remain scheduled.';
}else if($_GET['act']=="6"){
$msgTxt='<b>Task Message</b> Nothing to execute yet. The scheduled time has not been reached, so the task remains scheduled.';
}
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.$msgTxt.'</strong></div>';
}
if (!empty($_GET['notificationReport'])) {
echo '<div class="alert alert-warning"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>Notification attempt summary</strong>'.taskNotificationAttemptSummaryHtml($_GET['notificationReport']).'</div>';
}
$taskListNow = taskScheduleNow($conn);
$dtAry = array($taskListNow->format('Y-m-d'));
?>
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>tasks.php?action=add"><b>Add New</b></a> </div>
<h2 class="panel-title">Manage Task</h2>
</header>
<div class="panel-body">
<div class="form-group">
<div class="col-md-6">
<form class="form-horizontal form-bordered" method="get">
<input type="text" style="width:80%;float:left" class="form-control" name="q" value="<?=htmlspecialchars(isset($_GET['q']) ? (string)$_GET['q'] : '', ENT_QUOTES, 'UTF-8')?>" placeholder="Search" id="inputDefault"  >
<input type="hidden" name="status" value="<?=(isset($_GET['status']) ? htmlspecialchars($_GET['status'], ENT_QUOTES, 'UTF-8') : 'scheduled')?>" />
<button class="btn btn-primary" name="submit" value="Submit">GO</button>
</form> <span class="hintTxt">Search :-  Subject.</span>
</div>
<div class="col-md-6" align="right">
<?php
$taskStatus = isset($_GET['status']) && in_array($_GET['status'], array('draft', 'scheduled', 'overdue', 'first_sent', 'second_sent', 'all_sent'), true) ? $_GET['status'] : 'scheduled';
$taskStatusConditions = array(
    'draft' => "isDraft=1",
    'scheduled' => "isDraft=0 AND isExpired='0' AND firstReminderSentAt IS NULL AND dueDate>='" . $dtAry[0] . "'",
    'overdue' => "isDraft=0 AND isExpired='0' AND firstReminderSentAt IS NULL AND dueDate>'1970-01-01' AND dueDate<'" . $dtAry[0] . "'",
    'first_sent' => "isDraft=0 AND isSecondReminder='1' AND firstReminderSentAt IS NOT NULL AND secondReminderSentAt IS NULL",
    'second_sent' => "isDraft=0 AND isSecondReminder='1' AND secondReminderSentAt IS NOT NULL",
    'all_sent' => "isDraft=0 AND (firstReminderSentAt IS NOT NULL OR secondReminderSentAt IS NOT NULL OR isExpired<>'0')"
);
$taskStatusCounts = array();
foreach ($taskStatusConditions as $statusKey => $statusCondition) {
    $countResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tasks WHERE createdDate<>'' AND " . $statusCondition);
    $countRow = $countResult ? mysqli_fetch_assoc($countResult) : array('total' => 0);
    $taskStatusCounts[$statusKey] = (int)($countRow['total'] ?? 0);
}
$taskSearchQuery = isset($_GET['q']) && $_GET['q'] !== '' ? '&amp;q=' . rawurlencode($_GET['q']) : '';
?>
<a href="<?=$siteUrl;?>tasks.php?status=draft<?=$taskSearchQuery;?>" class="btn <?=$taskStatus === 'draft' ? 'btn-primary' : 'btn-default';?>">Draft (<?=$taskStatusCounts['draft'];?>)</a>
<a href="<?=$siteUrl;?>tasks.php?status=scheduled<?=$taskSearchQuery;?>" class="btn <?=$taskStatus === 'scheduled' ? 'btn-primary' : 'btn-default';?>">Scheduled (<?=$taskStatusCounts['scheduled'];?>)</a>
<a href="<?=$siteUrl;?>tasks.php?status=overdue<?=$taskSearchQuery;?>" class="btn <?=$taskStatus === 'overdue' ? 'btn-danger' : 'btn-default';?>">Overdue / Not Sent (<?=$taskStatusCounts['overdue'];?>)</a>
<a href="<?=$siteUrl;?>tasks.php?status=first_sent<?=$taskSearchQuery;?>" class="btn <?=$taskStatus === 'first_sent' ? 'btn-primary' : 'btn-default';?>">1st Due Sent (<?=$taskStatusCounts['first_sent'];?>)</a>
<a href="<?=$siteUrl;?>tasks.php?status=second_sent<?=$taskSearchQuery;?>" class="btn <?=$taskStatus === 'second_sent' ? 'btn-primary' : 'btn-default';?>">2nd Due Sent (<?=$taskStatusCounts['second_sent'];?>)</a>
<a href="<?=$siteUrl;?>tasks.php?status=all_sent<?=$taskSearchQuery;?>" class="btn <?=$taskStatus === 'all_sent' ? 'btn-primary' : 'btn-default';?>">All Sent (<?=$taskStatusCounts['all_sent'];?>)</a>
</div>
</div>
<div class="table-responsive">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">Status</th>
<th width="5%">SNo</th>
<th width="15%">Subject</th>
<th width="10%">Due Date</th>
<th width="12%">Repeat Date</th>
<th width="20%">Assign To</th>
<th width="18%">Created On</th>
<th width="20%" class="text-center">Action</th>
</tr>
</thead>
<tbody>
<?php
$page_name="tasks.php?status=".rawurlencode($taskStatus)."&q=".rawurlencode(isset($_GET['q']) ? $_GET['q'] : '');
if(!isset($_REQUEST["start"])) {
$start = 0;
}
else
$start = max(0, (int)$_REQUEST["start"]);

$eu = ($start - 0);
$limit = 20;
$this1 = $eu + $limit;
$back = $eu - $limit;
$next = $eu + $limit;

$sqlSrch = "";
if(isset($_GET['q']) && $_GET['q']!=""){
$taskSearch = mysqli_real_escape_string($conn, (string)$_GET['q']);
$sqlSrch = " and (subject LIKE '%".$taskSearch."%') ";
}

$sqlSort = " and " . $taskStatusConditions[$taskStatus] . " ";
$sqlOrder = "createdDate DESC,id DESC";
if ($taskStatus === 'overdue') $sqlOrder = "dueDate DESC,id DESC";
if ($taskStatus === 'first_sent') $sqlOrder = "firstReminderSentAt DESC,id DESC";
if ($taskStatus === 'second_sent') $sqlOrder = "secondReminderSentAt DESC,id DESC";
if ($taskStatus === 'all_sent') $sqlOrder = "COALESCE(secondReminderSentAt,firstReminderSentAt,createdDate) DESC,id DESC";
$sqlSeller = "SELECT id,orderID,subject,dueDate,repeatDate,isDraft,isExpired,priorityID,assignTo,createdDate,isSecondReminder,firstReminderSentAt,secondReminderSentAt FROM tasks where createdDate<>'' ".$sqlSort.$sqlSrch." ORDER BY ".$sqlOrder." limit $eu, $limit";
$sqltot = "SELECT id FROM tasks where createdDate<>'' ".$sqlSort.$sqlSrch." ORDER BY id DESC ";

$resultSeller= mysqli_query($conn,$sqlSeller);
$resulttot=mysqli_query($conn,$sqltot);
$nume=mysqli_num_rows($resulttot);
if (@mysqli_num_rows($resultSeller)!=0){

$sno=$start;
while($rows=mysqli_fetch_assoc($resultSeller)){
$sno++;
$taskStatusBadge = "<span class='label label-warning'>SCHEDULED</span>";
if ((int)$rows['isDraft'] === 1) {
    $taskStatusBadge = "<span class='label label-default'>DRAFT</span>";
} elseif ($rows['dueDate'] < $dtAry[0] && empty($rows['firstReminderSentAt']) && $rows['isExpired'] == '0') {
    $taskStatusBadge = "<span class='label label-danger'>OVERDUE / NOT SENT</span>";
} elseif ((int)$rows['isSecondReminder'] === 1 && !empty($rows['secondReminderSentAt'])) {
    $taskStatusBadge = "<span class='label label-success'>2nd Due Sent</span>";
} elseif ((int)$rows['isSecondReminder'] === 1 && !empty($rows['firstReminderSentAt'])) {
    $taskStatusBadge = "<span class='label label-success'>1st Due Sent</span>";
} elseif ($rows['isExpired'] != '0') {
    $taskStatusBadge = "<span class='label label-success'>DONE</span>";
}
echo '<tr>
<td>&nbsp; '.$taskStatusBadge.'</td>
<td>&nbsp; '.$sno.'</td>
<td>'.stringTxtLimit($rows['subject'],50).''.(($rows['isSecondReminder'])?'<br /><span class="label label-danger reptBtn">Repeat</span>':'').'</td>
<td>'.viewDateFormat($rows['dueDate']).'</td>
<td>'.(($rows['repeatDate']!='0000-00-00' && $rows['repeatDate']!='1970-01-01')?viewDateFormat($rows['repeatDate']):'').'</td>
<td>';
$assignToAry = array_filter(array_map('intval', explode(",", (string)$rows['assignTo'])));
$assignedNames = array_map('customerName', $assignToAry);
echo agentEscape(implode(', ', $assignedNames));
echo '</td>
<td align="center">'.viewDtTm($rows['createdDate']).'</td>
<td align="center"><div class="table-action-buttons">
<a title="View task" aria-label="View task" class="btn btn-default viewBtn icon-action" href="tasks.php?action=view&id='.$rows['id'].'"><i class="fa fa-eye" aria-hidden="true"></i><span class="sr-only">View</span></a>
<a title="Edit task" aria-label="Edit task" class="btn btn-default editBtn icon-action" href="tasks.php?action=edit&id='.$rows['id'].'"><i class="fa fa-pencil" aria-hidden="true"></i><span class="sr-only">Edit</span></a>
<a title="Delete task" aria-label="Delete task" class="btn btn-default delBtn icon-action" onclick="return confirm(\'Are you sure you want to delete?\');" href="tasks.php?act=delete&id='.$rows['id'].'"><i class="fa fa-trash-o" aria-hidden="true"></i><span class="sr-only">Delete</span></a>
</div></td>
</tr>';
}
?>
<tr>
<td colspan="8">
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
else { echo "<font face='Verdana' size='2' color=red>$l</font>";}
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
<td colspan='7' align='center'>No Data Found.</td>
</tr>";
} ?>

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
