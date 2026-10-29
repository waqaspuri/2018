<?php
require_once __DIR__ . '/mail_config.php';

function whatsgTaskEnsureTemplateStorage($conn)
{
    if (!$conn instanceof mysqli) return false;
    $schema = mysqli_query($conn, "CREATE TABLE IF NOT EXISTS whatsapp_templates (
        templateLabel VARCHAR(100) COLLATE utf8mb4_bin NOT NULL,
        templateName VARCHAR(150) NOT NULL,
        mediaType VARCHAR(20) NOT NULL DEFAULT '',
        variablesJson TEXT NOT NULL,
        createdDate DATETIME NOT NULL,
        modifiedDate DATETIME NOT NULL,
        PRIMARY KEY (templateLabel)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!$schema) return false;
    return true;
}

function whatsgTaskTemplates($db = null)
{
    if ($db === null) {
        global $conn;
        $db = $conn;
    }
    if (!whatsgTaskEnsureTemplateStorage($db)) return array();
    $result = mysqli_query($db, 'SELECT templateLabel, templateName, mediaType, variablesJson FROM whatsapp_templates ORDER BY templateLabel ASC');
    if (!$result) return array();
    $templates = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $variables = json_decode((string)$row['variablesJson'], true);
        if (!is_array($variables)) $variables = array();
        $template = array('name' => (string)$row['templateName'], 'variables' => array_values($variables), 'mediaType' => (string)$row['mediaType']);
        if ($template['mediaType'] === 'image') $template['supportsMedia'] = true;
        $templates[(string)$row['templateLabel']] = $template;
    }
    mysqli_free_result($result);
    return $templates;
}

function whatsgTaskSaveTemplate($conn, $label, $templateName, $variables, $mediaType)
{
    if (!whatsgTaskEnsureTemplateStorage($conn)) return false;
    $variablesJson = json_encode(array_values($variables), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($variablesJson === false) return false;
    $statement = mysqli_prepare($conn, 'INSERT INTO whatsapp_templates (templateLabel, templateName, mediaType, variablesJson, createdDate, modifiedDate) VALUES (?, ?, ?, ?, NOW(), NOW())');
    if (!$statement) return false;
    mysqli_stmt_bind_param($statement, 'ssss', $label, $templateName, $mediaType, $variablesJson);
    $saved = mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    return $saved;
}

function whatsgTaskUpdateTemplate($conn, $originalLabel, $templateName, $variables, $mediaType)
{
    if (!whatsgTaskEnsureTemplateStorage($conn)) return false;
    $variablesJson = json_encode(array_values($variables), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($variablesJson === false) return false;
    $statement = mysqli_prepare($conn, 'UPDATE whatsapp_templates SET templateName = ?, mediaType = ?, variablesJson = ?, modifiedDate = NOW() WHERE templateLabel = ?');
    if (!$statement) return false;
    mysqli_stmt_bind_param($statement, 'ssss', $templateName, $mediaType, $variablesJson, $originalLabel);
    $saved = mysqli_stmt_execute($statement) && mysqli_stmt_affected_rows($statement) >= 0;
    mysqli_stmt_close($statement);
    return $saved;
}

function whatsgTaskDeleteTemplate($conn, $label)
{
    if (!whatsgTaskEnsureTemplateStorage($conn)) return false;
    $statement = mysqli_prepare($conn, 'DELETE FROM whatsapp_templates WHERE templateLabel = ?');
    if (!$statement) return false;
    mysqli_stmt_bind_param($statement, 's', $label);
    $deleted = mysqli_stmt_execute($statement) && mysqli_stmt_affected_rows($statement) === 1;
    mysqli_stmt_close($statement);
    return $deleted;
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

function whatsgTaskAttachmentMediaType($localFile, $storedFile)
{
    $fileInfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    $mimeType = $fileInfo ? (string)finfo_file($fileInfo, $localFile) : '';
    if ($fileInfo) finfo_close($fileInfo);
    $extension = strtolower(pathinfo((string)$storedFile, PATHINFO_EXTENSION));
    if (in_array($mimeType, array('application/pdf', 'application/x-pdf'), true) || $extension === 'pdf') return 'document';
    if (in_array($mimeType, array('application/zip', 'application/x-zip', 'application/x-zip-compressed'), true) || ($extension === 'zip' && in_array($mimeType, array('', 'application/octet-stream'), true))) return 'document';
    if (strpos($mimeType, 'image/') === 0 && $mimeType !== 'image/svg+xml') return 'image';
    return '';
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

    $templates = whatsgTaskTemplates($conn);
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
        $documentAttachment = null;
        $imageAttachment = null;
        $attachmentResult = mysqli_query($conn, "SELECT fileName,storedFile FROM task_attachments WHERE taskID=" . (int)$taskID . " ORDER BY id ASC");
        if ($attachmentResult) {
            while ($attachment = mysqli_fetch_assoc($attachmentResult)) {
                $storedFile = basename((string)$attachment['storedFile']);
                $localFile = dirname(__DIR__) . '/img/' . $storedFile;
                if ($storedFile === '' || !is_file($localFile)) continue;
                $attachmentMediaType = whatsgTaskAttachmentMediaType($localFile, $storedFile);
                if ($attachmentMediaType === 'document' && !$documentAttachment) $documentAttachment = array('name' => (string)$attachment['fileName'], 'storedFile' => $storedFile);
                if ($attachmentMediaType === 'image' && !$imageAttachment) $imageAttachment = array('name' => (string)$attachment['fileName'], 'storedFile' => $storedFile);
            }
        }
        // Older tasks may only have the legacy single-attachment fields.
        if (!$documentAttachment && !$imageAttachment && !empty($task['attachmentFile'])) {
            $storedFile = basename((string)$task['attachmentFile']);
            $localFile = dirname(__DIR__) . '/img/' . $storedFile;
            if (is_file($localFile)) {
                $attachmentMediaType = whatsgTaskAttachmentMediaType($localFile, $storedFile);
                if ($attachmentMediaType === 'document') $documentAttachment = array('name' => (string)$task['attachmentName'], 'storedFile' => $storedFile);
                if ($attachmentMediaType === 'image') $imageAttachment = array('name' => (string)$task['attachmentName'], 'storedFile' => $storedFile);
            }
        }
        // The WhatSG task payload uses the same mediaUri field for either file type.
        // Prefer a PDF when both are attached; otherwise send the available image.
        $mediaAttachment = $documentAttachment ?: $imageAttachment;
        if ($mediaAttachment) $mediaType = $documentAttachment ? 'document' : 'image';
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
            $detail = 'This WhatsApp template has media enabled, but no attached PDF, ZIP, or image was found.';
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
        $requestUrl = 'https://whatsg.com/api/v1/send_templet';
        if ($debugPayload) {
            $debugBody = $body;
            $debugBody['token'] = '[REDACTED; configured in .env]';
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'debug' => true,
                'message' => 'No API request was sent. This is the request prepared for WhatsApp.',
                'task_id' => (int)$taskID,
                'method' => 'POST',
                'url' => $requestUrl,
                'headers' => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer [REDACTED; configured in .env]'),
                'body' => $debugBody,
                'attachment_names' => $attachmentNames,
                'media_type' => $mediaType
            ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            die();
        }
        $curl = curl_init($requestUrl);
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
