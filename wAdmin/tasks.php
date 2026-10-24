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
require_once(__DIR__ . '/../php/whatsapp_tasks.php');

$taskWhatsAppTemplateColumn = mysqli_query($conn, "SHOW COLUMNS FROM tasks LIKE 'taskWhatsAppTemplate'");
if ($taskWhatsAppTemplateColumn && mysqli_num_rows($taskWhatsAppTemplateColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN taskWhatsAppTemplate VARCHAR(100) NOT NULL DEFAULT '' AFTER taskSMSID");
}
$whatsAppTemplates = whatsgTaskTemplates();

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
    $isImage = strpos($mimeType, 'image/') === 0 && $mimeType !== 'image/svg+xml';
    if (!$isPdf && !$isImage) {
        $error = 'Only PDF files and image files are allowed.';
        return false;
    }
    $extension = preg_replace('/[^a-z0-9]/', '', $extension);
    if ($extension === '') {
        $extension = $isPdf ? 'pdf' : 'img';
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


// End of SendinBlue //




if(isset($_POST['submit']) && $_POST['submit']=="Submit"){
if(isset($_POST['action']) && $_POST['action']=="add"){

$sendBy = isset($_POST['sendBy']) && is_array($_POST['sendBy']) ? $_POST['sendBy'] : [];

$isExpired = "0";
if(in_array('immediate', $sendBy)){
    $isExpired = "1";
}

// Handle dates, setting to a valid default if empty
$repeatDate = isset($_POST['repeatDate']) ? $_POST['repeatDate'] : '';
if(in_array('immediate', $sendBy)){
    $repeatDate = '';
}
$repeatDateForSql = !empty($repeatDate) ? sqlDtFormat($repeatDate) : '1970-01-01';
$repeatDateSql = "'" . mysqli_real_escape_string($conn, $repeatDateForSql) . "'";

$dueDate = isset($_POST['dueDate']) ? $_POST['dueDate'] : '';
$dueDateForSql = !empty($dueDate) ? sqlDtFormat($dueDate) : '1970-01-01';
$dueDateSql = "'" . mysqli_real_escape_string($conn, $dueDateForSql) . "'";

$taskSMSID = !empty($_POST['taskSMSID']) ? mysqli_real_escape_string($conn, $_POST['taskSMSID']) : '0';
$taskWhatsAppTemplate = isset($_POST['taskWhatsAppTemplate']) && is_string($_POST['taskWhatsAppTemplate']) && isset($whatsAppTemplates[$_POST['taskWhatsAppTemplate']]) ? mysqli_real_escape_string($conn, $_POST['taskWhatsAppTemplate']) : '';
$taskMessageID = !empty($_POST['taskMessageID']) ? mysqli_real_escape_string($conn, $_POST['taskMessageID']) : '0';
$orderID = !empty($_POST['orderID']) ? mysqli_real_escape_string($conn, $_POST['orderID']) : '0';
$currentStatusID = !empty($_POST['currentStatusID']) ? mysqli_real_escape_string($conn, $_POST['currentStatusID']) : '0';
$subject = isset($_POST['subject']) ? mysqli_real_escape_string($conn, $_POST['subject']) : '';
$priorityID = !empty($_POST['priorityID']) ? mysqli_real_escape_string($conn, $_POST['priorityID']) : '';
$priorityIDSql = ($priorityID !== '') ? "'" . $priorityID . "'" : "NULL";
$assignTo = isset($_POST['assignTo']) ? mysqli_real_escape_string($conn, @implode(",", $_POST['assignTo'])) : '';
$isSecondReminder = isset($_POST['isSecondReminder']) ? mysqli_real_escape_string($conn, $_POST['isSecondReminder']) : '0';
$taskMessage = isset($_POST['taskMessage']) ? mysqli_real_escape_string($conn, $_POST['taskMessage']) : '';
$taskSMS = isset($_POST['taskSMS']) ? mysqli_real_escape_string($conn, $_POST['taskSMS']) : '';
$sendByStr = mysqli_real_escape_string($conn, @implode(",", $sendBy));
$attachmentError = '';
if (in_array('whatsapp', $sendBy, true) && $taskWhatsAppTemplate === '') {
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

mysqli_query($conn,"INSERT INTO tasks (isExpired,taskSMSID,taskWhatsAppTemplate,taskMessageID,repeatDate,orderID,currentStatusID,subject,dueDate,priorityID,assignTo,isSecondReminder,taskMessage,taskSMS,attachmentFile,attachmentName,sendBy,createdDate) VALUES
('".$isExpired."','".$taskSMSID."','".$taskWhatsAppTemplate."','".$taskMessageID."',".$repeatDateSql.",'".$orderID."','".$currentStatusID."','".$subject."',".$dueDateSql.",".$priorityIDSql.",'".$assignTo."','".$isSecondReminder."','".$taskMessage."','".$taskSMS."','".$attachmentFile."','".$attachmentName."','".$sendByStr."','".currDateTimeGmt()."')");
$taskID = mysqli_insert_id($conn);
saveTaskTemplatesFromPost($conn, (int)$taskMessageID, (int)$taskSMSID);
foreach ($attachments as $attachment) {
    mysqli_query($conn, "INSERT INTO task_attachments (taskID,fileName,storedFile,createdDate) VALUES ('" . $taskID . "','" . mysqli_real_escape_string($conn, $attachment[1]) . "','" . mysqli_real_escape_string($conn, $attachment[0]) . "','" . gmdate('Y-m-d H:i:s') . "')");
    if ((int)$orderID > 0) {
        mysqli_query($conn, "INSERT INTO order_images (fileName,isOrder,orderImg,orderID) VALUES ('" . mysqli_real_escape_string($conn, orderImageFileName($attachment[1])) . "','1','" . mysqli_real_escape_string($conn, $attachment[0]) . "','" . (int)$orderID . "')");
    }
}

if(in_array('immediate', $sendBy, true)){

	if(in_array('mail', $sendBy, true)){
		$sqlEp = mysqli_query($conn,"SELECT id,assignTo,orderID,subject,taskMessage,taskSMS,sendBy FROM tasks WHERE id='".$taskID."'  ");
		$numRowsExp = mysqli_num_rows($sqlEp);
		if($numRowsExp>0){
		while($rowsEp = mysqli_fetch_assoc($sqlEp)){

			if($rowsEp['assignTo']!=""){
				//Order Info
							$sqlOrdrInfo = mysqli_query($conn,"SELECT o.id,o.unitPrice,o.quantity,o.totalAmt,o.orderOrigin,o.orderDelievery,o.orderPackaging,o.invoiceNo,o.eta,o.dta,o.newETA,o.documentDate,o.expiryDate,o.paymentTerms,o.latestDateShipment,o.documentOrderNo,o.billNo,p.prodName, c.email2 FROM orders as o,products as p, customers as c WHERE p.id=o.productID AND c.id=o.customerID AND o.id='".$rowsEp['orderID']."' ");

				//$sqlOrdrInfo = mysqli_query($conn,"SELECT o.id,o.invoiceNo,o.documentOrderNo,p.prodName FROM orders as o,products as p WHERE p.id=o.productID AND o.id='".$rowsEp['orderID']."' ");
				$rowsOrdrInfo = mysqli_fetch_assoc($sqlOrdrInfo);
				$assignToAry = @explode(",",$rowsEp['assignTo']);

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
							'second_email'    => $rowsOrdrInfo['email2'],
							'arrival_date' => viewDateFormat($rowsOrdrInfo['eta']),
							'departure_date' => viewDateFormat($rowsOrdrInfo['dta']),
							'latest_date_shipment' => viewDateFormat($rowsOrdrInfo['latestDateShipment']),
							'document_bill_no' => $rowsOrdrInfo['billNo'],
							'doucment_expiry_date' => viewDateFormat($rowsOrdrInfo['expiryDate']),
							'arrival_new_date' => viewDateFormat($rowsOrdrInfo['newETA']),
							'payment_terms' => $rowsOrdrInfo['paymentTerms']);




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
		$sqlOrdrInfo = mysqli_query($conn,"SELECT o.id,o.unitPrice,o.quantity,o.totalAmt,o.orderOrigin,o.orderDelievery,o.orderPackaging,o.invoiceNo,o.eta,o.dta,o.newETA,o.documentDate,o.expiryDate,o.paymentTerms,o.latestDateShipment,o.documentOrderNo,o.billNo,p.prodName, c.email2 FROM orders as o,products as p, customers as c WHERE p.id=o.productID AND c.id=o.customerID AND o.id='".$rowsEp['orderID']."' ");
			$rowsOrdrInfo = mysqli_fetch_assoc($sqlOrdrInfo);
			$assignToAry = @explode(",",$rowsEp['assignTo']);
			if(count($assignToAry)>0){
				foreach($assignToAry as $assignToVal){


					$sqlCtBk = mysqli_query($conn,"SELECT applicantName,companyName,mobileNumber,email,isEnableEmail FROM customers WHERE id='".$assignToVal."' ");
					$rowsCtBk = mysqli_fetch_assoc($sqlCtBk);
					$sendByAry = @explode(",",$rowsEp['sendBy']);
						if($rowsCtBk['mobileNumber']){

					$values = array(
							'unit_price' => $rowsOrdrInfo['unitPrice'],
							'quantity' => $rowsOrdrInfo['quantity'],
							'total_amount' => $rowsOrdrInfo['totalAmt'],
							'order_transport' => $rowsOrdrInfo['orderDelievery'],
							'order_packaging' => $rowsOrdrInfo['orderPackaging'],
							'product_name'    => $rowsOrdrInfo['prodName'],
							'document_order_number'    => $rowsOrdrInfo['documentOrderNo'],
							'proforma_invoice'    => $rowsOrdrInfo['invoiceNo'],
							'second_email'    => $rowsOrdrInfo['email2'],
							'arrival_date' => viewDateFormat($rowsOrdrInfo['eta']),
							'departure_date' => viewDateFormat($rowsOrdrInfo['dta']),
							'latest_date_shipment' => viewDateFormat($rowsOrdrInfo['latestDateShipment']),
							'document_bill_no' => $rowsOrdrInfo['billNo'],
							'doucment_expiry_date' => viewDateFormat($rowsOrdrInfo['expiryDate']),
							'arrival_new_date' => viewDateFormat($rowsOrdrInfo['newETA']),
							'payment_terms' => $rowsOrdrInfo['paymentTerms']);

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
					sendSMS($rowsCtBk['mobileNumber'],$msg);

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
    $_SESSION['taskWhatsAppNotice'] = $whatsAppResult[1];
    header('location: tasks.php?action=view&id=' . (int)$taskID . '&act=' . ($whatsAppResult[0] ? '3' : '4'));
    exit;
}
header("location: orders.php?action=view&id=".$_POST['orderID']."&act=1");
}else if(isset($_POST['action']) && $_POST['action']=="update"){

$repeatDate = isset($_POST['repeatDate']) ? $_POST['repeatDate'] : '';
$repeatDateForSql = !empty($repeatDate) ? sqlDtFormat($repeatDate) : '1970-01-01';

$dueDate = isset($_POST['dueDate']) ? $_POST['dueDate'] : '';
$dueDateForSql = !empty($dueDate) ? sqlDtFormat($dueDate) : '1970-01-01';

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
if (in_array('whatsapp', $sendBy, true) && $taskWhatsAppTemplate === '') {
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
$existingReminderSettings = mysqli_query($conn, "SELECT dueDate,repeatDate,isSecondReminder FROM tasks WHERE id=" . (int)$_POST['id'] . " LIMIT 1");
$existingReminderRow = $existingReminderSettings ? mysqli_fetch_assoc($existingReminderSettings) : null;
if ($existingReminderRow && (
    $existingReminderRow['dueDate'] !== $dueDateForSql ||
    $existingReminderRow['repeatDate'] !== $repeatDateForSql ||
    (int)$existingReminderRow['isSecondReminder'] !== $isSecondReminder
)) {
    $reminderTrackingResetSql = "firstReminderSentAt=NULL, secondReminderSentAt=NULL, isExpired='0', ";
}

mysqli_query($conn, "UPDATE tasks SET 
    taskMessageID   = " . $taskMessageID   . ",
    taskSMSID       = " . $taskSMSID       . ",
    taskWhatsAppTemplate = '" . $taskWhatsAppTemplate . "',
    repeatDate      = '" . mysqli_real_escape_string($conn, $repeatDateForSql) . "',
    orderID         = " . $orderID         . ",
    currentStatusID = " . $currentStatusID . ",
    subject         = '" . mysqli_real_escape_string($conn, $_POST['subject'])    . "',
    dueDate         = '" . mysqli_real_escape_string($conn, $dueDateForSql)       . "',
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
saveTaskTemplatesFromPost($conn, (int)$taskMessageID, (int)$taskSMSID);

foreach ($attachments as $attachment) {
    mysqli_query($conn, "INSERT INTO task_attachments (taskID,fileName,storedFile,createdDate) VALUES ('" . (int)$_POST['id'] . "','" . mysqli_real_escape_string($conn, $attachment[1]) . "','" . mysqli_real_escape_string($conn, $attachment[0]) . "','" . gmdate('Y-m-d H:i:s') . "')");
    if ($orderID > 0) {
        mysqli_query($conn, "INSERT INTO order_images (fileName,isOrder,orderImg,orderID) VALUES ('" . mysqli_real_escape_string($conn, orderImageFileName($attachment[1])) . "','1','" . mysqli_real_escape_string($conn, $attachment[0]) . "','" . $orderID . "')");
    }
}

header("location: tasks.php?act=2");
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

                $sqlOrdrInfo = mysqli_query($conn,"SELECT o.id,o.unitPrice,o.quantity,o.totalAmt,o.orderOrigin,o.orderDelievery,o.orderPackaging,o.invoiceNo,o.eta,o.dta,o.newETA,o.documentDate,o.expiryDate,o.paymentTerms,o.latestDateShipment,o.documentOrderNo,o.billNo,p.prodName,c.email2 FROM orders as o,products as p, customers as c WHERE p.id=o.productID AND c.id=o.customerID AND o.id='".$rowsEp['orderID']."'");
                $rowsOrdrInfo = mysqli_fetch_assoc($sqlOrdrInfo) ?: array('unitPrice'=>'','quantity'=>'','totalAmt'=>'','orderDelievery'=>'','orderPackaging'=>'','prodName'=>'','documentOrderNo'=>'','invoiceNo'=>'','email2'=>'','eta'=>'','dta'=>'','latestDateShipment'=>'','billNo'=>'','expiryDate'=>'','newETA'=>'','paymentTerms'=>'');
                $assignToAry  = explode(",", $rowsEp['assignTo']);

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
                        'second_email'          => ($rowsOrdrInfo['email2'] ?? ''),
                        'arrival_date'          => viewDateFormat($rowsOrdrInfo['eta']),
                        'departure_date'        => viewDateFormat($rowsOrdrInfo['dta']),
                        'latest_date_shipment'  => viewDateFormat($rowsOrdrInfo['latestDateShipment']),
                        'document_bill_no'      => $rowsOrdrInfo['billNo'],
                        'doucment_expiry_date'  => viewDateFormat($rowsOrdrInfo['expiryDate']),
                        'arrival_new_date'      => viewDateFormat($rowsOrdrInfo['newETA']),
                        'payment_terms'         => $rowsOrdrInfo['paymentTerms']
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
                    // ---- End PHPMailer ----
                }
            }
        }
    }

    header("location: tasks.php?action=view&id=".urlencode($_GET['id'])."&act=1");
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

				$sqlOrdrInfo = mysqli_query($conn,"SELECT o.id,o.unitPrice,o.quantity,o.totalAmt,o.orderOrigin,o.orderDelievery,o.orderPackaging,o.invoiceNo,o.eta,o.dta,o.newETA,o.documentDate,o.expiryDate,o.paymentTerms,o.latestDateShipment,o.documentOrderNo,o.billNo,p.prodName, c.email2 FROM orders as o,products as p, customers as c WHERE p.id=o.productID AND c.id=o.customerID AND o.id='".$rowsEp['orderID']."' ");
		$rowsOrdrInfo = mysqli_fetch_assoc($sqlOrdrInfo);
		$assignToAry = @explode(",",$rowsEp['assignTo']);
		if(count($assignToAry)>0){
			foreach($assignToAry as $assignToVal){
				$sqlCtBk = mysqli_query($conn,"SELECT applicantName,companyName,mobileNumber,email,isEnableEmail FROM customers WHERE id='".$assignToVal."' ");
				$rowsCtBk = mysqli_fetch_assoc($sqlCtBk);
				$sendByAry = @explode(",",$rowsEp['sendBy']);
					if($rowsCtBk['mobileNumber']){


							$values = array(
							'unit_price' => $rowsOrdrInfo['unitPrice'],
							'quantity' => $rowsOrdrInfo['quantity'],
							'total_amount' => $rowsOrdrInfo['totalAmt'],
							'order_transport' => $rowsOrdrInfo['orderDelievery'],
							'order_packaging' => $rowsOrdrInfo['orderPackaging'],
							'product_name'    => $rowsOrdrInfo['prodName'],
							'document_order_number'    => $rowsOrdrInfo['documentOrderNo'],
							'proforma_invoice'    => $rowsOrdrInfo['invoiceNo'],
							'second_email'    => $rowsOrdrInfo['email2'],
							'arrival_date' => viewDateFormat($rowsOrdrInfo['eta']),
							'departure_date' => viewDateFormat($rowsOrdrInfo['dta']),
							'latest_date_shipment' => viewDateFormat($rowsOrdrInfo['latestDateShipment']),
							'document_bill_no' => $rowsOrdrInfo['billNo'],
							'doucment_expiry_date' => viewDateFormat($rowsOrdrInfo['expiryDate']),
							'arrival_new_date' => viewDateFormat($rowsOrdrInfo['newETA']),
							'payment_terms' => $rowsOrdrInfo['paymentTerms']);

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
					sendSMS($rowsCtBk['mobileNumber'],$msg);
				}

			}
		}
	}
	}
}
header("location: tasks.php?action=view&id=".$_GET['id']."&act=2");

}

if (isset($_GET['action']) && $_GET['action'] === 'WhatsAppPreview') {
    $taskID = (int)($_GET['id'] ?? 0);
    $whatsAppResult = sendTaskWhatsApp($conn, $taskID, true);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('debug' => true, 'message' => $whatsAppResult[1], 'payload_count' => 0, 'payloads' => array()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'WhatsApp') {
    $taskID = (int)($_GET['id'] ?? 0);
    $whatsAppResult = sendTaskWhatsApp($conn, $taskID);
    $_SESSION['taskWhatsAppNotice'] = $whatsAppResult[1];
    header('location: tasks.php?action=view&id=' . $taskID . '&act=' . ($whatsAppResult[0] ? '3' : '4'));
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
    $orderID = '';
    $taskMessageID = '';
    $taskSMSID = '';
    $taskWhatsAppTemplate = '';
    $subject = '';
    $priorityID = '';
    $assignTo = '';
    $isSecondReminder = '1';
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
?>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>tasks.php?action=edit&id=<?=$rowsEdit['id'];?>"><b>Edit</b></a> | <a href="<?=$siteUrl;?>tasks.php"><b>Back</b></a> </div>
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
<label class="col-md-2 control-label" for="inputDefault">Repeat Date</label>
<div class="col-md-9"><?=($repeatDate!="0000-00-00" && $repeatDate!="1970-01-01")?viewDateFormat($repeatDate):''?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Priority *</label>
<div class="col-md-9 selectSrch"><?=($priorityID != '' ? priorityNm($priorityID) : '')?></div></div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Assign To *</label>
<div class="col-md-9"><?php
$assignToAry = @explode(",",$assignTo);
$asignToStr = array();
foreach($assignToAry as $assignToVal){
$asignToStr[] = customerName($assignToVal);
}
echo @implode(", ",$asignToStr);
?>
</div></div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Repeat</label>
<div class="col-md-8"><div class="paddingTxt">
<input type="checkbox" name="isSecondReminder" value="1" <?=($isSecondReminder==1)?'checked="checked"':'';?> id="inputDefault"  > If Yes, second reminder will active.</div>
</div>
</div>

<?php

	//adding merge fields.
$sqlEp = mysqli_query($conn,"SELECT id,assignTo,orderID,subject,taskSMS,sendBy FROM tasks WHERE id='".$_GET['id']."'  ");
$numRowsExp = mysqli_num_rows($sqlEp);
$rowsOrdrInfo = array('unitPrice'=>'','quantity'=>'','totalAmt'=>'','orderDelievery'=>'','orderPackaging'=>'','prodName'=>'','documentOrderNo'=>'','invoiceNo'=>'','email2'=>'','eta'=>'','dta'=>'','latestDateShipment'=>'','billNo'=>'','expiryDate'=>'','newETA'=>'','paymentTerms'=>'');
$rowsCtBk = array('applicantName'=>'');

while($rowsEp = mysqli_fetch_assoc($sqlEp)){

	if($rowsEp['assignTo']!=""){
		//Order Info

				$sqlOrdrInfo = mysqli_query($conn,"SELECT o.id,o.unitPrice,o.quantity,o.totalAmt,o.orderOrigin,o.orderDelievery,o.orderPackaging,o.invoiceNo,o.eta,o.dta,o.newETA,o.documentDate,o.expiryDate,o.paymentTerms,o.latestDateShipment,o.documentOrderNo,o.billNo,p.prodName, c.email2 FROM orders as o,products as p, customers as c WHERE p.id=o.productID AND c.id=o.customerID AND o.id='".$rowsEp['orderID']."' ");
		$rowsOrdrInfo = mysqli_fetch_assoc($sqlOrdrInfo) ?: $rowsOrdrInfo;
		$assignToAry = @explode(",",$rowsEp['assignTo']);

			foreach($assignToAry as $assignToVal){
				$sqlCtBk = mysqli_query($conn,"SELECT applicantName,companyName,mobileNumber,email,isEnableEmail FROM customers WHERE id='".$assignToVal."' ");
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
							'second_email'    => $rowsOrdrInfo['email2'],
							'arrival_date' => viewDateFormat($rowsOrdrInfo['eta']),
							'departure_date' => viewDateFormat($rowsOrdrInfo['dta']),
							'latest_date_shipment' => viewDateFormat($rowsOrdrInfo['latestDateShipment']),
							'document_bill_no' => $rowsOrdrInfo['billNo'],
							'doucment_expiry_date' => viewDateFormat($rowsOrdrInfo['expiryDate']),
							'arrival_new_date' => viewDateFormat($rowsOrdrInfo['newETA']),
							'payment_terms' => $rowsOrdrInfo['paymentTerms']);

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
<?php if (in_array('mail', $sendByAry, true)): ?><a onclick="return confirm('Are you sure you want send Email?');" class="btn btn-default" href="<?=$siteUrl;?>tasks.php?action=mail&id=<?=$id?>" title="Send Email Notification"><b>Mail</b></a>  &nbsp;<?php endif; ?>
<?php if (in_array('sms', $sendByAry, true)): ?><a title="Send SMS Notification" onclick="return confirm('Are you sure you want send SMS?');" class="btn btn-default" href="<?=$siteUrl;?>tasks.php?action=SMS&id=<?=$id?>"><b>SMS</b></a><?php endif; ?>
<?php if (in_array('whatsapp', $sendByAry, true)): ?>
&nbsp;<a title="Preview WhatsApp payload without sending" onclick="return confirm('Preview payloads for all assigned contacts? No API messages will be sent.');" class="btn btn-default" href="<?=$siteUrl;?>tasks.php?action=WhatsAppPreview&amp;id=<?=(int)$id?>"><b>Preview Payload</b></a>
&nbsp;<a title="Send WhatsApp template" onclick="return confirm('Send WhatsApp template to all assigned contacts?');" class="btn btn-success" href="<?=$siteUrl;?>tasks.php?action=WhatsApp&amp;id=<?=(int)$id?>"><b>WhatsApp</b></a>
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
$('#taskForm').on('submit', function(){
    var dueDate = $.trim($(this).find('[name="dueDate"]').val() || '');
    var repeatDate = $.trim($(this).find('[name="repeatDate"]').val() || '');
    if (dueDate !== '' && repeatDate !== '' && dueDate === repeatDate) {
        return confirm('Due Date and Repeat Date are the same. Do you want to continue?');
    }
});
$("#taskSMSID").change(function(){ $('#saveSMSTemplate').prop('checked', false); $.ajax({ url:"<?=$siteUrl;?>assignAjax.php?act=smsTmp&id=" + $(this).val(),type: "GET",success: function(data){ $('#taskSMS').html(data); } }); });
$("#taskMessageID").change(function(){ $('#saveEmailTemplate').prop('checked', false); $.ajax({ url:"<?=$siteUrl;?>assignAjax.php?act=mailTmp&id=" + $(this).val(),type: "GET",success: function(data){ var x = data; var optn=x.split(',~,');  $('#subject').val(optn[0]); $('.taskMessageInfo .note-editable').html(optn[1]); } }); });
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
</div>
</div>
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Repeat Date</label>
<div class="col-md-6">
<input type="text" class="form-control repeatDate" readonly="readonly" data-date-format="dd-mm-yyyy" name="repeatDate" value="<?=($repeatDate!="0000-00-00" && $repeatDate!="1970-01-01")?viewDateFormat($repeatDate):''?>" id="inputDefault" >
</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="isSecondReminder">Repeat</label>
<div class="col-md-9">
<div class="paddingTxt"><label><input type="checkbox" name="isSecondReminder" value="1" id="isSecondReminder" <?=((int)$isSecondReminder===1)?'checked="checked"':'';?>> If Yes, second reminder will be active.</label></div>
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

	$sqlOrdr = mysqli_query($conn,"SELECT customerID,vendorID FROM orders where id='".$orderID."'");
	$rowOrdr = mysqli_fetch_assoc($sqlOrdr);
	$customerAry[] = $rowOrdr['customerID'];
	$customerAry[] = $rowOrdr['vendorID'];

	$vendrCustmrNoID = "";
	$sqlContactVndr = mysqli_query($conn,"SELECT id FROM customers where assignToID='".$rowOrdr['vendorID']."'");
	$numRowContactVndr = mysqli_num_rows($sqlContactVndr);
	if($numRowContactVndr>0){
		$rowConactVendr = mysqli_fetch_assoc($sqlContactVndr);
		$customerAry[] = $rowConactVendr['id'];
	}

	$sqlContactCustmr = mysqli_query($conn,"SELECT id FROM customers where assignToID='".$rowOrdr['customerID']."'");
	$numRowContactCustmr = mysqli_num_rows($sqlContactCustmr);
	if($numRowContactCustmr>0){
		$rowConactCustmr = mysqli_fetch_assoc($sqlContactCustmr);
		$customerAry[] = $rowConactCustmr['id'];
	}

	$sqlOrderCustmr = mysqli_query($conn,"SELECT id,companyName,type FROM customers where (isOtherCustomer='1' OR id in (".@implode(",",$customerAry).")) ORDER BY type,applicantName ASC");
	$numRowOC = mysqli_num_rows($sqlOrderCustmr);
	if($numRowOC>0){
		while($rowOC = mysqli_fetch_assoc($sqlOrderCustmr)){
			echo '<option value="'.$rowOC['id'].'" '.((in_array($rowOC['id'],$assignToAry)?'selected="selected"':'')).' >'.$rowOC['companyName'].' ('.typeNm($rowOC['type']).')</option>';
		}
	}
}else{
    $assignToAry = []; // ← ADD THIS LINE

	if(isset($_GET['orderID']) && $_GET['orderID'] != ''){
	$sqlOrdr = mysqli_query($conn,"SELECT customerID,vendorID FROM orders where id='".$_GET['orderID']."'");
	$rowOrdr = mysqli_fetch_assoc($sqlOrdr);
	$customerAry[] = $rowOrdr['customerID'];
	$customerAry[] = $rowOrdr['vendorID'];

	$vendrCustmrNoID = "";
	$sqlContactVndr = mysqli_query($conn,"SELECT id FROM customers where assignToID='".$rowOrdr['vendorID']."'");
	$numRowContactVndr = mysqli_num_rows($sqlContactVndr);
	if($numRowContactVndr>0){
		$rowConactVendr = mysqli_fetch_assoc($sqlContactVndr);
		$customerAry[] = $rowConactVendr['id'];
	}

	$sqlContactCustmr = mysqli_query($conn,"SELECT id FROM customers where assignToID='".$rowOrdr['customerID']."'");
	$numRowContactCustmr = mysqli_num_rows($sqlContactCustmr);
	if($numRowContactCustmr>0){
		$rowConactCustmr = mysqli_fetch_assoc($sqlContactCustmr);
		$customerAry[] = $rowConactCustmr['id'];
	}

	$sqlOrderCustmr = mysqli_query($conn,"SELECT id,companyName,type FROM customers where (isOtherCustomer='1' OR id in (".@implode(",",$customerAry).")) ORDER BY type,applicantName ASC");
	$numRowOC = mysqli_num_rows($sqlOrderCustmr);
	if($numRowOC>0){
		while($rowOC = mysqli_fetch_assoc($sqlOrderCustmr)){
			echo '<option value="'.$rowOC['id'].'" '.((in_array($rowOC['id'],$assignToAry)?'selected="selected"':'')).' >'.$rowOC['companyName'].' ('.typeNm($rowOC['type']).')</option>';
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
<option value="<?=htmlspecialchars($templateLabel, ENT_QUOTES, 'UTF-8')?>" <?=((string)$taskWhatsAppTemplate===(string)$templateLabel?'selected':'')?>><?=htmlspecialchars($templateLabel, ENT_QUOTES, 'UTF-8')?> (<?=htmlspecialchars($templateConfig['name'], ENT_QUOTES, 'UTF-8')?><?=whatsgTemplateSupportsMedia($templateConfig) ? ', image header' : '';?>)</option>
<?php endforeach; ?>
</select>
<small class="text-muted">Templates come from approved WhatsApp templates. <a href="<?=$siteUrl;?>whatsapp_templates.php">Manage templates</a>.</small>
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
<input type="file" class="form-control" name="taskAttachments[]" id="taskAttachment" multiple accept="application/pdf,image/*">
<span class="hintTxt">Select one or more PDF or image files (maximum 10 MB each).</span>
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
if($_GET['action']=="add"){
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
$msgTxt='<b>Task Message</b> No declared task to run';
}
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.$msgTxt.'</strong></div>';
}
$dtAry = explode(" ",currDateTimeGmt());
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
$taskStatus = isset($_GET['status']) && in_array($_GET['status'], array('scheduled', 'overdue', 'first_sent', 'second_sent', 'all_sent'), true) ? $_GET['status'] : 'scheduled';
$taskStatusConditions = array(
    'scheduled' => "isExpired='0' AND firstReminderSentAt IS NULL AND dueDate>='" . $dtAry[0] . "'",
    'overdue' => "isExpired='0' AND firstReminderSentAt IS NULL AND dueDate>'1970-01-01' AND dueDate<'" . $dtAry[0] . "'",
    'first_sent' => "isSecondReminder='1' AND firstReminderSentAt IS NOT NULL AND secondReminderSentAt IS NULL",
    'second_sent' => "isSecondReminder='1' AND secondReminderSentAt IS NOT NULL",
    'all_sent' => "(firstReminderSentAt IS NOT NULL OR secondReminderSentAt IS NOT NULL OR isExpired<>'0')"
);
$taskStatusCounts = array();
foreach ($taskStatusConditions as $statusKey => $statusCondition) {
    $countResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tasks WHERE createdDate<>'' AND " . $statusCondition);
    $countRow = $countResult ? mysqli_fetch_assoc($countResult) : array('total' => 0);
    $taskStatusCounts[$statusKey] = (int)($countRow['total'] ?? 0);
}
$taskSearchQuery = isset($_GET['q']) && $_GET['q'] !== '' ? '&amp;q=' . rawurlencode($_GET['q']) : '';
?>
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
$sqlSeller = "SELECT id,subject,dueDate,repeatDate,isExpired,priorityID,assignTo,createdDate,isSecondReminder,firstReminderSentAt,secondReminderSentAt FROM tasks where createdDate<>'' ".$sqlSort.$sqlSrch." ORDER BY ".$sqlOrder." limit $eu, $limit";
$sqltot = "SELECT id FROM tasks where createdDate<>'' ".$sqlSort.$sqlSrch." ORDER BY id DESC ";

$resultSeller= mysqli_query($conn,$sqlSeller);
$resulttot=mysqli_query($conn,$sqltot);
$nume=mysqli_num_rows($resulttot);
if (@mysqli_num_rows($resultSeller)!=0){

$sno=$start;
while($rows=mysqli_fetch_assoc($resultSeller)){
$sno++;
$taskStatusBadge = "<span class='label label-warning'>SCHEDULED</span>";
if ($rows['dueDate'] < $dtAry[0] && empty($rows['firstReminderSentAt']) && $rows['isExpired'] == '0') {
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
$assignToAry = @explode(",",$rows['assignTo']);
$asignToStr = array();
foreach($assignToAry as $assignToVal){
$asignToStr[] = customerName($assignToVal);
}
echo @implode(", ",$asignToStr);
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
