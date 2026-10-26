<?php

use PHPMailer\PHPMailer\PHPMailer;

include "php/connect-mysql.php";
include "php/functions.php";
require_once(__DIR__ . '/php/task_reminder_tracking.php');
require_once(__DIR__ . '/PHPMailer/Exception.php');
require_once(__DIR__ . '/PHPMailer/PHPMailer.php');
require_once(__DIR__ . '/PHPMailer/SMTP.php');
require_once(__DIR__ . '/php/mail_config.php');
require_once(__DIR__ . '/php/whatsapp_tasks.php');

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS task_attachments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    taskID INT UNSIGNED NOT NULL,
    fileName VARCHAR(255) NOT NULL,
    storedFile VARCHAR(255) NOT NULL,
    createdDate DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY taskID (taskID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
function addTaskAttachmentsToMail($mail, $conn, $taskID) {
    $files = mysqli_query($conn, "SELECT fileName,storedFile FROM task_attachments WHERE taskID='" . (int)$taskID . "'");
    while ($files && ($file = mysqli_fetch_assoc($files))) {
        $path = __DIR__ . '/img/' . basename($file['storedFile']);
        if (is_file($path)) {
            $mail->addAttachment($path, $file['fileName']);
        }
    }
}

//exprd
$dtAry = explode(" ", currDateTimeGmt());
$notificationSent = false;

$sqlEp = mysqli_query($conn, "SELECT id,assignTo,orderID,subject,taskMessage,taskSMS,sendBy,isSecondReminder,repeatDate FROM tasks WHERE dueDate='" . $dtAry[0] . "' AND isExpired='0' AND firstReminderSentAt IS NULL");
$numRowsExp = mysqli_num_rows($sqlEp);
if ($numRowsExp > 0) {
	while ($rowsEp = mysqli_fetch_assoc($sqlEp)) {
		$enabledChannels = array_map('trim', explode(',', (string)$rowsEp['sendBy']));
		if (!array_intersect(array('mail', 'sms', 'whatsapp'), $enabledChannels)) {
			error_log('Task reminder skipped: no delivery channel enabled for task ' . (int)$rowsEp['id']);
			continue;
		}
		if ($rowsEp['assignTo'] != "") {
			//Order Info
			$sqlOrdrInfo = mysqli_query($conn, "SELECT o.id,o.unitPrice,o.quantity,o.totalAmt,o.orderOrigin,o.orderDelievery,o.orderPackaging,o.invoiceNo,o.eta,o.dta,o.newETA,o.documentDate,o.expiryDate,o.paymentTerms,o.latestDateShipment,o.documentOrderNo,o.billNo,o.shippingDocInfo,p.prodName FROM orders as o,products as p WHERE p.id=o.productID AND o.id='" . $rowsEp['orderID'] . "' ");
			//$sqlOrdrInfo = mysqli_query($conn,"SELECT o.id,p.prodName,o.documentOrderNo,o.invoiceNo FROM orders as o,products as p WHERE p.id=o.productID AND o.id='".$rowsEp['orderID']."' ");
			$rowsOrdrInfo = mysqli_fetch_assoc($sqlOrdrInfo);
			if (!$rowsOrdrInfo) {
				$rowsOrdrInfo = array_fill_keys(array('unitPrice','quantity','totalAmt','orderDelievery','orderPackaging','invoiceNo','eta','dta','newETA','documentDate','expiryDate','paymentTerms','latestDateShipment','documentOrderNo','billNo','prodName','email2'), '');
			}
			$assignToAry = @explode(",", $rowsEp['assignTo']);

			if (count($assignToAry) > 0) {

				foreach ($assignToAry as $assignToVal) {

					$sqlCtBk = mysqli_query($conn, "SELECT companyName,applicantName,mobileNumber,email,email2,isEnableEmail FROM customers WHERE id='" . $assignToVal . "' ");
					$rowsCtBk = mysqli_fetch_assoc($sqlCtBk);
					if (!$rowsCtBk) {
						$rowsCtBk = array('companyName'=>'','applicantName'=>'','mobileNumber'=>'','email'=>'','email2'=>'','isEnableEmail'=>'0');
					}

					$sendByAry = @explode(",", $rowsEp['sendBy']);

					if (in_array("mail", $sendByAry)) {

						if ($rowsCtBk['isEnableEmail'] == "0" or $rowsCtBk['isEnableEmail'] == "1") {

							$to = $rowsCtBk['email'];
							$to2 = $rowsCtBk['email2'];

							$subject = $rowsEp['subject'];
							if (!empty($rowsOrdrInfo['prodName']) && stripos((string)$rowsEp['taskMessage'], 'May I have the price for') === false) {
								$subject .= " / " . $rowsOrdrInfo['prodName'] . (($rowsOrdrInfo['documentOrderNo'] != "") ? '(' . $rowsOrdrInfo['documentOrderNo'] . ')' : '') . (($rowsOrdrInfo['invoiceNo'] != "") ? ' / ' . $rowsOrdrInfo['invoiceNo'] : '');
							}
							//$subject = $rowsEp['subject']. " for ".$rowsOrdrInfo['prodName']." - proforma invoice: ".(($rowsOrdrInfo['invoiceNo']!="")?' - '.$rowsOrdrInfo['invoiceNo']:'')." ";

							$values = array(
								'unit_price' => $rowsOrdrInfo['unitPrice'],
								'quantity' => $rowsOrdrInfo['quantity'],
								'total_amount' => $rowsOrdrInfo['totalAmt'],
								'order_transport' => $rowsOrdrInfo['orderDelievery'],
								'order_packaging' => $rowsOrdrInfo['orderPackaging'],
								'product_name' => $rowsOrdrInfo['prodName'],
								'document_order_number' => $rowsOrdrInfo['documentOrderNo'],
								'proforma_invoice' => $rowsOrdrInfo['invoiceNo'],
								'second_email' => ($rowsOrdrInfo['email2'] ?? ''),
								'arrival_date' => viewDateFormat($rowsOrdrInfo['eta']),
								'departure_date' => viewDateFormat($rowsOrdrInfo['dta']),
								'latest_date_shipment' => viewDateFormat($rowsOrdrInfo['latestDateShipment']),
								'document_bill_no' => $rowsOrdrInfo['billNo'],
								'doucment_expiry_date' => viewDateFormat($rowsOrdrInfo['expiryDate']),
								'arrival_new_date' => viewDateFormat($rowsOrdrInfo['newETA']),
								'payment_terms' => $rowsOrdrInfo['paymentTerms'],
								'shipping_info' => ($rowsOrdrInfo['shippingDocInfo'] ?? '')
							);





							$message = buildTaskEmailBody($rowsCtBk['applicantName'], $rowsEp['taskMessage']);


							$search = array();
							$replace = array();



							foreach ($values as $index => $value) {


								if (!empty($value))
									;

								$search[] = '{' . $index . '}';
								$replace[] = $value;



							}




							//merge field


							$message = str_replace($search, $replace, $message);
							unset($replace);


							$mail = new PHPMailer();
							$mail->CharSet = 'UTF-8';
							$mail->Encoding = 'base64';
							configureAppMailer($mail);
							addConfiguredMailBcc($mail);
							$mail->addAddress($to, $rowsCtBk['applicantName']);
							if (!empty($to2)) {
								$mail->addCC($to2, $rowsCtBk['applicantName']);
							}
							$mail->isHTML(true);
							$mail->Subject = $subject;
							$mail->Body = $message;
							addTaskAttachmentsToMail($mail, $conn, $rowsEp['id']);
							$mail->Body = appendConfiguredEmailFooter($conn, $mail->Body);
							$mail->send();
							$notificationSent = true;

						}
					}

					if (in_array("sms", $sendByAry)) {
						//if((substr($rowsCtBk['mobileNumber'],0,2)==92)){
						if ($rowsCtBk['mobileNumber']) {

							//merge fields
							$values = array(
								'unit_price' => $rowsOrdrInfo['unitPrice'],
								'quantity' => $rowsOrdrInfo['quantity'],
								'total_amount' => $rowsOrdrInfo['totalAmt'],
								'order_transport' => $rowsOrdrInfo['orderDelievery'],
								'order_packaging' => $rowsOrdrInfo['orderPackaging'],
								'product_name' => $rowsOrdrInfo['prodName'],
								'document_order_number' => $rowsOrdrInfo['documentOrderNo'],
								'proforma_invoice' => $rowsOrdrInfo['invoiceNo'],
								'second_email' => ($rowsOrdrInfo['email2'] ?? ''),
								'arrival_date' => viewDateFormat($rowsOrdrInfo['eta']),
								'departure_date' => viewDateFormat($rowsOrdrInfo['dta']),
								'latest_date_shipment' => viewDateFormat($rowsOrdrInfo['latestDateShipment']),
								'document_bill_no' => $rowsOrdrInfo['billNo'],
								'doucment_expiry_date' => viewDateFormat($rowsOrdrInfo['expiryDate']),
								'arrival_new_date' => viewDateFormat($rowsOrdrInfo['newETA']),
								'payment_terms' => $rowsOrdrInfo['paymentTerms'],
								'shipping_info' => ($rowsOrdrInfo['shippingDocInfo'] ?? '')
							);

							$search = array();
							$replace = array();



							foreach ($values as $index => $value) {



								if (!empty($value))
									;

								$search[] = '{' . $index . '}';
								$replace[] = $value;


							}



							$msg = 'Dear ' . $rowsCtBk['applicantName'] . "\r\n" . "\r\n" . '' . $rowsOrdrInfo['prodName'] . "\r\n" . "\r\n" . '' . (($rowsOrdrInfo['documentOrderNo'] != "") ? '(' . $rowsOrdrInfo['documentOrderNo'] . ')' : $rowsOrdrInfo['invoiceNo']) . "\r\n" . "\r\n" . '' . strip_tags($rowsEp['taskSMS']) . ' ' . "\r\n" . "\r\n" . 'WAQAS CHEMICALS' . "\n" . '03008207530';


							//$msg = 'Dear '.$rowsCtBk['applicantName'].' ('.$rowsCtBk['companyName'].'),'."\n".'Reference:- '.$rowsOrdrInfo['prodName'].'/'.$rowsOrdrInfo['invoiceNo']."\r\n".' '.strip_tags($rowsEp['taskMessage']).' '."\r\n".' WAQAS CHEMICALS';


							$msg = str_replace($search, $replace, $msg);
							sendSMS($rowsCtBk['mobileNumber'], $msg);
							$notificationSent = true;
							unset($replace);
						} else {

							// API CALL TO THE SMS //







						}
					}


					//send a copy to myself //

					// Remove previous recipients

					$headers = "MIME-Version: 1.0" . "\r\n";
					$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

					// More headers
					$headers .= 'From: <info@waqaschemicals.com>' . "\r\n";



					$results_to_email = "<pre>";
					$results_to_email .= "<br>" . $rowsCtBk['mobileNumber'] . '- > ' . (isset($to) ? $to : '');

					$results_to_email .= "</pre>";




				}
			}
		}


		if (in_array('whatsapp', explode(',', (string)$rowsEp['sendBy']), true)) {
			$whatsAppResult = sendTaskWhatsApp($conn, (int)$rowsEp['id']);
			if (!$whatsAppResult[0]) error_log('Task WhatsApp reminder failed: ' . $whatsAppResult[1]);
			else $notificationSent = true;
		}
		$nextExpired = ($rowsEp['isSecondReminder'] == "1") ? '0' : '1';
		mysqli_query($conn, "UPDATE tasks SET firstReminderSentAt=NOW(), isExpired='" . $nextExpired . "' WHERE id='" . (int)$rowsEp['id'] . "' AND firstReminderSentAt IS NULL");

	}
}

	$sendRepeatDateEmail = true;
	$sqlEp = mysqli_query($conn, "SELECT id,assignTo,orderID,subject,taskMessage,taskSMS,sendBy,isSecondReminder FROM tasks WHERE repeatDate='" . $dtAry[0] . "' AND isSecondReminder='1' AND isExpired='0' AND secondReminderSentAt IS NULL");
$numRowsExp = mysqli_num_rows($sqlEp);
if ($numRowsExp > 0) {
	while ($rowsEp = mysqli_fetch_assoc($sqlEp)) {
		$enabledChannels = array_map('trim', explode(',', (string)$rowsEp['sendBy']));
		if (!array_intersect(array('mail', 'sms', 'whatsapp'), $enabledChannels)) {
			error_log('Task repeat reminder skipped: no delivery channel enabled for task ' . (int)$rowsEp['id']);
			continue;
		}
		if ($rowsEp['assignTo'] != "") {
			//Order Info
			$sqlOrdrInfo = mysqli_query($conn, "SELECT o.id,o.unitPrice,o.quantity,o.totalAmt,o.orderOrigin,o.orderDelievery,o.orderPackaging,o.invoiceNo,o.eta,o.dta,o.newETA,o.documentDate,o.expiryDate,o.paymentTerms,o.latestDateShipment,o.documentOrderNo,o.billNo,o.shippingDocInfo,p.prodName FROM orders as o,products as p WHERE p.id=o.productID AND o.id='" . $rowsEp['orderID'] . "' ");
			$rowsOrdrInfo = mysqli_fetch_assoc($sqlOrdrInfo);
			if (!$rowsOrdrInfo) {
				$rowsOrdrInfo = array_fill_keys(array('unitPrice','quantity','totalAmt','orderDelievery','orderPackaging','invoiceNo','eta','dta','newETA','documentDate','expiryDate','paymentTerms','latestDateShipment','documentOrderNo','billNo','prodName','email2'), '');
			}
			$assignToAry = @explode(",", $rowsEp['assignTo']);

			$values = array(
				'unit_price' => $rowsOrdrInfo['unitPrice'],
				'quantity' => $rowsOrdrInfo['quantity'],
				'total_amount' => $rowsOrdrInfo['totalAmt'],
				'order_transport' => $rowsOrdrInfo['orderDelievery'],
				'order_packaging' => $rowsOrdrInfo['orderPackaging'],
				'product_name' => $rowsOrdrInfo['prodName'],
				'document_order_number' => $rowsOrdrInfo['documentOrderNo'],
				'proforma_invoice' => $rowsOrdrInfo['invoiceNo'],
				'second_email' => ($rowsOrdrInfo['email2'] ?? ''),
				'arrival_date' => viewDateFormat($rowsOrdrInfo['eta']),
				'departure_date' => viewDateFormat($rowsOrdrInfo['dta']),
				'latest_date_shipment' => viewDateFormat($rowsOrdrInfo['latestDateShipment']),
				'document_bill_no' => $rowsOrdrInfo['billNo'],
				'doucment_expiry_date' => viewDateFormat($rowsOrdrInfo['expiryDate']),
				'arrival_new_date' => viewDateFormat($rowsOrdrInfo['newETA']),
				'payment_terms' => $rowsOrdrInfo['paymentTerms'],
				'shipping_info' => ($rowsOrdrInfo['shippingDocInfo'] ?? '')
			);



			if (count($assignToAry) > 0) {

				foreach ($assignToAry as $assignToVal) {

					$sqlCtBk = mysqli_query($conn, "SELECT companyName,applicantName,mobileNumber,email,email2,isEnableEmail FROM customers WHERE id='" . $assignToVal . "' ");
					$rowsCtBk = mysqli_fetch_assoc($sqlCtBk);
					if (!$rowsCtBk) {
						$rowsCtBk = array('companyName'=>'','applicantName'=>'','mobileNumber'=>'','email'=>'','email2'=>'','isEnableEmail'=>'0');
					}

					$sendByAry = @explode(",", $rowsEp['sendBy']);
						if ($sendRepeatDateEmail && in_array("mail", $sendByAry)) {

						if ($rowsCtBk['isEnableEmail'] == "0" or $rowsCtBk['isEnableEmail'] == "1") {

							$to = $rowsCtBk['email'];
							$to2 = $rowsCtBk['email2'];

							$subject = $rowsEp['subject'];
							if (!empty($rowsOrdrInfo['prodName']) && stripos((string)$rowsEp['taskMessage'], 'May I have the price for') === false) {
								$subject .= " / " . $rowsOrdrInfo['prodName'] . (($rowsOrdrInfo['documentOrderNo'] != "") ? '(' . $rowsOrdrInfo['documentOrderNo'] . ')' : '') . (($rowsOrdrInfo['invoiceNo'] != "") ? ' / ' . $rowsOrdrInfo['invoiceNo'] : '');
							}
							//$subject = $rowsEp['subject']. " for ".$rowsOrdrInfo['prodName']." - proforma invoice: ".(($rowsOrdrInfo['invoiceNo']!="")?' - '.$rowsOrdrInfo['invoiceNo']:'')." ";



							$values = array(
								'unit_price' => $rowsOrdrInfo['unitPrice'],
								'quantity' => $rowsOrdrInfo['quantity'],
								'total_amount' => $rowsOrdrInfo['totalAmt'],
								'order_transport' => $rowsOrdrInfo['orderDelievery'],
								'order_packaging' => $rowsOrdrInfo['orderPackaging'],
								'product_name' => $rowsOrdrInfo['prodName'],
								'document_order_number' => $rowsOrdrInfo['documentOrderNo'],
								'proforma_invoice' => $rowsOrdrInfo['invoiceNo'],
								'second_email' => ($rowsOrdrInfo['email2'] ?? ''),
								'arrival_date' => viewDateFormat($rowsOrdrInfo['eta']),
								'departure_date' => viewDateFormat($rowsOrdrInfo['dta']),
								'latest_date_shipment' => viewDateFormat($rowsOrdrInfo['latestDateShipment']),
								'document_bill_no' => $rowsOrdrInfo['billNo'],
								'doucment_expiry_date' => viewDateFormat($rowsOrdrInfo['expiryDate']),
								'arrival_new_date' => viewDateFormat($rowsOrdrInfo['newETA']),
								'payment_terms' => $rowsOrdrInfo['paymentTerms'],
								'shipping_info' => ($rowsOrdrInfo['shippingDocInfo'] ?? '')
							);



							$search = array();
							$replace = array();



							foreach ($values as $index => $value) {



								if (!empty($value))
									;

								$search[] = '{' . $index . '}';
								$replace[] = $value;



							}



							$message = buildTaskEmailBody($rowsCtBk['applicantName'], $rowsEp['taskMessage']);
							$headers = "MIME-Version: 1.0" . "\r\n";
							$headers .= "Content-type:text/html;charset=iso-8859-1" . "\r\n";
							$headers .= 'From: Waqas Chemicals <info@waqaschemicals.com>' . "\r\n";



							$message = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $message);

							$message = str_replace($search, $replace, $message);

								$mail = new PHPMailer();
							$mail->CharSet = 'UTF-8';
							$mail->Encoding = 'base64';
							configureAppMailer($mail);
							$mail->addAddress($to, $rowsCtBk['applicantName']);
							if (!empty($to2)) {
								$mail->addCC($to2, $rowsCtBk['applicantName']);
							}
							addConfiguredMailBcc($mail);
							$mail->isHTML(true);
							$mail->Subject = $subject;
							$mail->Body = $message;
							addTaskAttachmentsToMail($mail, $conn, $rowsEp['id']);
							$mail->Body = appendConfiguredEmailFooter($conn, $mail->Body);
							$mail->send();

							unset($replace);

						}
					}

					if (in_array("sms", $sendByAry)) {
						//if((substr($rowsCtBk['mobileNumber'],0,2)==92)){
						if ($rowsCtBk['mobileNumber']) {

							//merge fields




							$values = array(
								'unit_price' => $rowsOrdrInfo['unitPrice'],
								'quantity' => $rowsOrdrInfo['quantity'],
								'total_amount' => $rowsOrdrInfo['totalAmt'],
								'order_transport' => $rowsOrdrInfo['orderDelievery'],
								'order_packaging' => $rowsOrdrInfo['orderPackaging'],
								'product_name' => $rowsOrdrInfo['prodName'],
								'document_order_number' => $rowsOrdrInfo['documentOrderNo'],
								'proforma_invoice' => $rowsOrdrInfo['invoiceNo'],
								'second_email' => ($rowsOrdrInfo['email2'] ?? ''),
								'arrival_date' => viewDateFormat($rowsOrdrInfo['eta']),
								'departure_date' => viewDateFormat($rowsOrdrInfo['dta']),
								'latest_date_shipment' => viewDateFormat($rowsOrdrInfo['latestDateShipment']),
								'document_bill_no' => $rowsOrdrInfo['billNo'],
								'doucment_expiry_date' => viewDateFormat($rowsOrdrInfo['expiryDate']),
								'arrival_new_date' => viewDateFormat($rowsOrdrInfo['newETA']),
								'payment_terms' => $rowsOrdrInfo['paymentTerms'],
								'shipping_info' => ($rowsOrdrInfo['shippingDocInfo'] ?? '')
							);

							$search = array();
							$replace = array();



							foreach ($values as $index => $value) {


								if (!empty($value))
									;


								$search[] = '{' . $index . '}';
								$replace[] = $value;

							}





							$msg = 'Dear ' . $rowsCtBk['applicantName'] . "\r\n" . "\r\n" . '' . $rowsOrdrInfo['prodName'] . "\r\n" . "\r\n" . '' . (($rowsOrdrInfo['documentOrderNo'] != "") ? '(' . $rowsOrdrInfo['documentOrderNo'] . ')' : $rowsOrdrInfo['invoiceNo']) . "\r\n" . "\r\n" . '' . strip_tags($rowsEp['taskSMS']) . ' ' . "\r\n" . "\r\n" . 'WAQAS CHEMICALS' . "\n" . '03008207530';


							//$msg = 'Dear '.$rowsCtBk['applicantName'].' ('.$rowsCtBk['companyName'].'),'."\n".'Reference:- '.$rowsOrdrInfo['prodName'].'/'.$rowsOrdrInfo['invoiceNo']."\r\n".' '.strip_tags($rowsEp['taskMessage']).' '."\r\n".' WAQAS CHEMICALS';


							$msg = str_replace($search, $replace, $msg);
							sendSMS($rowsCtBk['mobileNumber'], $msg);
							$notificationSent = true;
							unset($replace);
						} else {

							// API CALL TO THE SMS //







						}
					}


					//send a copy to myself //

					// Remove previous recipients

					$headers = "MIME-Version: 1.0" . "\r\n";
					$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

					// More headers
					$headers .= 'From: <info@waqaschemicals.com>' . "\r\n";



					$results_to_email = "<pre>";
						$results_to_email .= "<br>" . $rowsCtBk['mobileNumber'] . '- > ' . (isset($to) ? $to : '');
					$results_to_email .= "</pre>";






				}
			}
		}


		if (in_array('whatsapp', explode(',', (string)$rowsEp['sendBy']), true)) {
			$whatsAppResult = sendTaskWhatsApp($conn, (int)$rowsEp['id']);
			if (!$whatsAppResult[0]) error_log('Task WhatsApp repeat reminder failed: ' . $whatsAppResult[1]);
			else $notificationSent = true;
		}
		mysqli_query($conn, "UPDATE tasks SET secondReminderSentAt=NOW(), isExpired='0' WHERE id='" . (int)$rowsEp['id'] . "' AND secondReminderSentAt IS NULL");



	}
}

	if ($notificationSent) {
	$act = 4;
} else {
	$act = 5;
}


header('Location: wAdmin/tasks.php?act=' . $act);



?>
