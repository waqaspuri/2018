<?php
require_once __DIR__ . '/mail_config.php';

function whatsgTaskTemplates()
{
    loadMailEnvironment();
    $raw = trim(mailEnvironmentValue('WHATSG_TEMPLATES_JSON', '{}'));
    if (strlen($raw) >= 2 && (($raw[0] === "'" && substr($raw, -1) === "'") || ($raw[0] === '"' && substr($raw, -1) === '"'))) {
        $raw = substr($raw, 1, -1);
    }
    $templates = json_decode($raw, true);
    if (!is_array($templates)) return array();
    foreach ($templates as $label => $template) {
        if (!is_array($template) || empty($template['name']) || !isset($template['variables']) || !is_array($template['variables'])) unset($templates[$label]);
    }
    return $templates;
}

function whatsgTaskMergeTagNames()
{
    return array(
        'unit_price', 'quantity', 'total_amount', 'order_transport', 'order_packaging',
        'product_name', 'document_order_number', 'proforma_invoice', 'second_email',
        'arrival_date', 'departure_date', 'latest_date_shipment', 'document_bill_no',
        'doucment_expiry_date', 'arrival_new_date', 'payment_terms', 'shipping_info', 'applicant_name',
        'company_name', 'mobile_number'
    );
}

function whatsgTemplateSupportsMedia($template)
{
    return whatsgTemplateMediaType($template) !== '';
}

function whatsgTemplateMediaType($template)
{
    if (!is_array($template)) return '';
    $mediaType = strtolower(trim((string)($template['mediaType'] ?? '')));
    if (in_array($mediaType, array('image', 'document'), true)) return $mediaType;
    // Older template entries used supportsMedia and _media to indicate image headers.
    if (!empty($template['supportsMedia']) || !empty($template['mediaUri'])) return 'image';
    $templateName = strtolower(trim((string)($template['name'] ?? '')));
    return $templateName !== '' && substr($templateName, -6) === '_media' ? 'image' : '';
}

function whatsgTaskLog($taskID, $templateName, $status, $details = array())
{
    $entry = array_merge(array(
        'event' => 'whatsapp_task_send',
        'task_id' => (int)$taskID,
        'template' => (string)$templateName,
        'status' => (string)$status
    ), $details);
    error_log('WhatSG WhatsApp: ' . json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function whatsgTaskDispatchLog($phone, $templateName, $exampleArr, $status, $mediaUri = '')
{
    if (!function_exists('insertSMSLog')) return;
    $requestData = array(
        'templetName' => (string)$templateName,
        'exampleArr' => array_values($exampleArr)
    );
    if ($mediaUri !== '') $requestData['mediaUri'] = (string)$mediaUri;
    $result = insertSMSLog(
        'WHATSAPP: ' . (string)$phone,
        json_encode($requestData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        substr((string)$status, 0, 250),
        date('l, d-m-y h:i:s a')
    );
    if ($result !== true) error_log('WhatSG WhatsApp log write failed: ' . (string)$result);
}

function whatsgTaskMaskedPhone($phone)
{
    $phone = (string)$phone;
    return strlen($phone) > 6 ? substr($phone, 0, 4) . '***' . substr($phone, -3) : '***';
}

function sendTaskWhatsApp($conn, $taskID, $manualPreview = false)
{
    loadMailEnvironment();
    $debugPayload = mailEnvironmentValue('WHATSG_DEBUG_PAYLOAD') === '1';
    if ($debugPayload && !$manualPreview) {
        whatsgTaskLog($taskID, '', 'skipped', array('detail' => 'Debug preview is enabled; non-button WhatsApp sends are paused'));
        return array(false, 'WhatsApp debug preview is active. Use the Preview Payload button; scheduled sends are paused.');
    }
    $apiKey = mailEnvironmentValue('WHATSG_API_KEY');
    $token = mailEnvironmentValue('WHATSG_TOKEN');
    if ($token === '') $token = $apiKey;
    if ($apiKey === '' || $token === '') {
        whatsgTaskLog($taskID, '', 'error', array('detail' => 'API credentials are missing from .env'));
        return array(false, 'WhatsApp API credentials are missing from .env.');
    }
    if (!$manualPreview && !function_exists('curl_init')) {
        whatsgTaskLog($taskID, '', 'error', array('detail' => 'PHP cURL is not available'));
        return array(false, 'PHP cURL is not available.');
    }

    $taskResult = mysqli_query($conn, "SELECT t.assignTo,t.sendBy,t.taskWhatsAppTemplate,t.attachmentFile,t.attachmentName,o.unitPrice,o.quantity,o.totalAmt,o.orderDelievery,o.orderPackaging,o.invoiceNo,o.eta,o.dta,o.newETA,o.expiryDate,o.paymentTerms,o.latestDateShipment,o.documentOrderNo,o.billNo,o.shippingDocInfo,p.prodName
        FROM tasks t LEFT JOIN orders o ON o.id=t.orderID LEFT JOIN products p ON p.id=o.productID WHERE t.id=" . (int)$taskID . " LIMIT 1");
    $task = $taskResult ? mysqli_fetch_assoc($taskResult) : false;
    if (!$task) {
        whatsgTaskLog($taskID, '', 'error', array('detail' => 'Task could not be found'));
        return array(false, 'Task could not be found.');
    }
    $templateKey = (string)$task['taskWhatsAppTemplate'];
    if (!in_array('whatsapp', explode(',', (string)$task['sendBy']), true)) {
        whatsgTaskLog($taskID, $templateKey, 'skipped', array('detail' => 'WhatsApp is not enabled for this task'));
        return array(false, 'WhatsApp is not enabled for this task.');
    }

    $templates = whatsgTaskTemplates();
    if (!isset($templates[$templateKey])) {
        whatsgTaskLog($taskID, $templateKey, 'error', array('detail' => 'No configured template matches the task selection'));
        return array(false, 'Select a configured WhatsApp template for this task.');
    }
    $template = $templates[$templateKey];
    $values = array(
        'unit_price' => $task['unitPrice'] ?? '', 'quantity' => $task['quantity'] ?? '',
        'total_amount' => $task['totalAmt'] ?? '', 'order_transport' => $task['orderDelievery'] ?? '',
        'order_packaging' => $task['orderPackaging'] ?? '', 'product_name' => $task['prodName'] ?? '',
        'document_order_number' => $task['documentOrderNo'] ?? '', 'proforma_invoice' => $task['invoiceNo'] ?? '',
        'arrival_date' => !empty($task['eta']) ? viewDateFormat($task['eta']) : '',
        'departure_date' => !empty($task['dta']) ? viewDateFormat($task['dta']) : '',
        'latest_date_shipment' => !empty($task['latestDateShipment']) ? viewDateFormat($task['latestDateShipment']) : '',
        'document_bill_no' => $task['billNo'] ?? '', 'doucment_expiry_date' => !empty($task['expiryDate']) ? viewDateFormat($task['expiryDate']) : '',
        'arrival_new_date' => !empty($task['newETA']) ? viewDateFormat($task['newETA']) : '',
        'payment_terms' => $task['paymentTerms'] ?? '', 'shipping_info' => $task['shippingDocInfo'] ?? ''
    );
    $customerIDs = array_filter(array_map('intval', explode(',', (string)$task['assignTo'])));
    if (!$customerIDs) {
        whatsgTaskLog($taskID, $template['name'], 'error', array('detail' => 'No customers are assigned to this task'));
        return array(false, 'No customers are assigned to this task.');
    }
    $mediaUri = '';
    $attachmentNames = array();
    $mediaType = whatsgTemplateMediaType($template);
    $mediaAttachment = null;
    if ($mediaType !== '') {
        $attachmentResult = mysqli_query($conn, "SELECT fileName,storedFile FROM task_attachments WHERE taskID=" . (int)$taskID . " ORDER BY id ASC");
        if ($attachmentResult) {
            while ($attachment = mysqli_fetch_assoc($attachmentResult)) {
                $storedFile = basename((string)$attachment['storedFile']);
                $localFile = dirname(__DIR__) . '/img/' . $storedFile;
                if ($storedFile === '' || !is_file($localFile)) continue;
                $fileInfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
                $mimeType = $fileInfo ? (string)finfo_file($fileInfo, $localFile) : '';
                if ($fileInfo) finfo_close($fileInfo);
                $isImage = strpos($mimeType, 'image/') === 0 && $mimeType !== 'image/svg+xml';
                $isPdf = in_array($mimeType, array('application/pdf', 'application/x-pdf'), true);
                if (($mediaType === 'image' && $isImage) || ($mediaType === 'document' && $isPdf)) {
                    $mediaAttachment = array('name' => (string)$attachment['fileName'], 'storedFile' => $storedFile);
                    break;
                }
            }
        }
        // Older tasks may only have the legacy single-attachment fields.
        if (!$mediaAttachment && !empty($task['attachmentFile'])) {
            $storedFile = basename((string)$task['attachmentFile']);
            $localFile = dirname(__DIR__) . '/img/' . $storedFile;
            if (is_file($localFile)) {
                $fileInfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
                $mimeType = $fileInfo ? (string)finfo_file($fileInfo, $localFile) : '';
                if ($fileInfo) finfo_close($fileInfo);
                $isImage = strpos($mimeType, 'image/') === 0 && $mimeType !== 'image/svg+xml';
                $isPdf = in_array($mimeType, array('application/pdf', 'application/x-pdf'), true);
                if (($mediaType === 'image' && $isImage) || ($mediaType === 'document' && $isPdf)) {
                    $mediaAttachment = array('name' => (string)$task['attachmentName'], 'storedFile' => $storedFile);
                }
            }
        }
        if ($mediaAttachment) {
            global $siteUrlMain;
            $mediaBaseUrl = rtrim(mailEnvironmentValue('WHATSG_MEDIA_BASE_URL', (string)$siteUrlMain), '/');
            if ($mediaBaseUrl === '') {
                $detail = 'Public site URL is not configured for the WhatsApp media attachment';
                whatsgTaskLog($taskID, $template['name'], 'error', array('detail' => $detail));
                return array(false, $detail);
            }
            $mediaUri = $mediaBaseUrl . '/img/' . rawurlencode($mediaAttachment['storedFile']);
            $attachmentNames[] = $mediaAttachment['name'];
        } else {
            $expected = $mediaType === 'document' ? 'PDF document header' : 'image header';
            $detail = 'This WhatsApp template requires an attached ' . $expected . ', but no matching file was found.';
            whatsgTaskLog($taskID, $template['name'], 'error', array('detail' => $detail, 'media_type' => $mediaType));
            if ($manualPreview) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(array(
                    'debug' => true,
                    'message' => 'No API requests were sent. This is a preview only.',
                    'selected_contacts' => count($customerIDs),
                    'payload_count' => 0,
                    'payloads' => array(),
                    'errors' => array(array('error' => $detail))
                ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                die();
            }
            return array(false, $detail);
        }
    }
    $sent = 0;
    $failed = 0;
    $payloadPreviews = array();
    $previewErrors = array();
    foreach ($customerIDs as $customerID) {
        $customerResult = mysqli_query($conn, "SELECT applicantName,companyName,mobileNumber,email2 FROM customers WHERE id=" . $customerID . " LIMIT 1");
        $customerInfo = $customerResult ? mysqli_fetch_assoc($customerResult) : false;
        if (!$customerInfo) {
            whatsgTaskLog($taskID, $template['name'], 'error', array('customer_id' => $customerID, 'detail' => 'Customer could not be found'));
            whatsgTaskDispatchLog('customer #' . $customerID, $template['name'], array(), 'ERROR: customer could not be found');
            $failed++;
            if ($manualPreview) $previewErrors[] = array('customer_id' => $customerID, 'error' => 'Customer could not be found');
            continue;
        }
        $phone = $customerInfo ? preg_replace('/[^0-9+]/', '', (string)$customerInfo['mobileNumber']) : '';
        if (strpos($phone, '00') === 0) $phone = '+' . substr($phone, 2);
        if (!preg_match('/^\+[1-9][0-9]{7,14}$/', $phone)) {
            whatsgTaskLog($taskID, $template['name'], 'error', array('customer_id' => $customerID, 'detail' => 'Phone number is not in E.164 format'));
            whatsgTaskDispatchLog($phone !== '' ? $phone : 'customer #' . $customerID, $template['name'], array(), 'ERROR: phone number is not in E.164 format');
            $failed++;
            if ($manualPreview) {
                $previewErrors[] = array('customer_id' => $customerID, 'error' => 'Phone number is not in E.164 format');
            }
            continue;
        }

        $recipientValues = $values + array(
            'applicant_name' => $customerInfo['applicantName'] ?? '',
            'company_name' => $customerInfo['companyName'] ?? '',
            'mobile_number' => $phone,
            'second_email' => $customerInfo['email2'] ?? ''
        );
        $variables = array_values(array_map('trim', $template['variables']));
        $recipientExampleArr = array();
        $debugVariables = array();
        foreach ($variables as $variable) {
            if (!in_array($variable, whatsgTaskMergeTagNames(), true) || !array_key_exists($variable, $recipientValues)) {
                whatsgTaskLog($taskID, $template['name'], 'error', array('customer_id' => $customerID, 'detail' => 'Unknown WhatsApp merge tag', 'merge_tag' => $variable));
                return array(false, 'Unknown WhatsApp merge tag: {' . $variable . '}');
            }
            $recipientExampleArr[] = (string)$recipientValues[$variable];
            $debugVariables[] = array('merge_tag' => '{' . $variable . '}', 'value' => (string)$recipientValues[$variable]);
        }

        $body = array('sendTo' => $phone, 'templetName' => (string)$template['name'], 'exampleArr' => $recipientExampleArr, 'token' => $token);
        if ($mediaUri !== '') $body['mediaUri'] = $mediaUri;
        if ($manualPreview) {
            whatsgTaskDispatchLog($phone, $body['templetName'], $recipientExampleArr, 'PREVIEW: not sent', (string)($body['mediaUri'] ?? ''));
            $preview = array(
                'customer_id' => (int)$customerID,
                'headers' => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer [REDACTED]'),
                'body' => array('sendTo' => $phone, 'templetName' => $body['templetName'], 'exampleArr' => $recipientExampleArr, 'token' => '[REDACTED; configured in .env]') + (isset($body['mediaUri']) ? array('mediaUri' => $body['mediaUri']) : array()),
                'variables' => $debugVariables
            );
            if ($attachmentNames) $preview['attachment_names'] = $attachmentNames;
            if ($mediaType !== '') $preview['media_type'] = $mediaType;
            $payloadPreviews[] = $preview;
            continue;
        }
        $curl = curl_init('https://whatsg.com/api/v1/send_templet');
        curl_setopt_array($curl, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => array('Content-Type: application/json', 'Authorization: Bearer ' . $apiKey),
            CURLOPT_POSTFIELDS => json_encode($body)
        ));
        $response = curl_exec($curl);
        $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);
        $responseData = is_string($response) ? json_decode($response, true) : null;
        if ($curlError !== '' || $httpCode < 200 || $httpCode >= 300 || !is_array($responseData) || empty($responseData['success'])) {
            $message = $curlError !== '' ? $curlError : (isset($responseData['message']) ? (string)$responseData['message'] : 'WhatsApp API rejected the request (HTTP ' . $httpCode . ').');
            whatsgTaskDispatchLog($phone, $template['name'], $recipientExampleArr, 'ERROR: HTTP ' . $httpCode . ' - ' . $message, (string)($body['mediaUri'] ?? ''));
            whatsgTaskLog($taskID, $template['name'], 'error', array(
                'customer_id' => $customerID,
                'phone' => whatsgTaskMaskedPhone($phone),
                'http_status' => $httpCode,
                'provider_status' => $responseData['metaResponse']['status'] ?? null,
                'message_id' => $responseData['metaResponse']['message_id'] ?? null,
                'detail' => $message
            ));
            $failed++;
            continue;
        }
        whatsgTaskDispatchLog($phone, $template['name'], $recipientExampleArr, 'SENT: HTTP ' . $httpCode . ' - ' . json_encode($responseData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (string)($body['mediaUri'] ?? ''));
        whatsgTaskLog($taskID, $template['name'], 'sent', array(
            'customer_id' => $customerID,
            'phone' => whatsgTaskMaskedPhone($phone),
            'http_status' => $httpCode,
            'provider_status' => $responseData['metaResponse']['status'] ?? null,
            'message_id' => $responseData['metaResponse']['message_id'] ?? null
        ));
        $sent++;
    }
    if ($manualPreview) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'debug' => true,
            'message' => 'No API requests were sent. This is a preview only.',
            'selected_contacts' => count($customerIDs),
            'payload_count' => count($payloadPreviews),
            'payloads' => $payloadPreviews,
            'errors' => $previewErrors
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        die();
    }
    if ($sent === 0) {
        whatsgTaskLog($taskID, $template['name'], 'error', array('detail' => 'No messages were sent', 'failed_contacts' => $failed));
        return array(false, 'No messages were sent. ' . $failed . ' request(s) failed; see the WhatsApp tab in SMS Logs.');
    }
    if ($failed > 0) return array(false, 'WhatsApp sent ' . $sent . ' message(s); ' . $failed . ' request(s) failed. See the WhatsApp tab in SMS Logs.');
    return array(true, 'WhatsApp sent ' . $sent . ' message(s).');
}
