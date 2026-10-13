<?php
include "include/sessionAdmin.php";
include "include/configure.php";
require_once(__DIR__ . '/../vendor/autoload.php');
use PHPMailer\PHPMailer\PHPMailer;
require_once(__DIR__ . '/../PHPMailer/Exception.php');
require_once(__DIR__ . '/../PHPMailer/PHPMailer.php');
require_once(__DIR__ . '/../PHPMailer/SMTP.php');

function bulkMessageIniBytes($value)
{
    $value = trim((string)$value);
    if ($value === '' || $value === '-1' || $value === '0') {
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $number = (float)$value;
    if ($unit === 'g') return (int)($number * 1024 * 1024 * 1024);
    if ($unit === 'm') return (int)($number * 1024 * 1024);
    if ($unit === 'k') return (int)($number * 1024);
    return (int)$number;
}

$phpPostLimitBytes = bulkMessageIniBytes(ini_get('post_max_size'));
$phpUploadLimitBytes = bulkMessageIniBytes(ini_get('upload_max_filesize'));
$maxAttachmentTotalBytes = 25 * 1024 * 1024;
if ($phpPostLimitBytes > 0) {
    // Reserve a quarter of PHP's POST allowance for the message and multipart overhead.
    $maxAttachmentTotalBytes = min($maxAttachmentTotalBytes, (int)($phpPostLimitBytes * 0.75));
}
$maxAttachmentFileBytes = min(12 * 1024 * 1024, $maxAttachmentTotalBytes);
if ($phpUploadLimitBytes > 0) {
    $maxAttachmentFileBytes = min($maxAttachmentFileBytes, $phpUploadLimitBytes);
}

$recipientType = ($_POST['recipientType'] ?? $_GET['recipientType'] ?? 'customer') === 'vendor' ? 'vendor' : 'customer';
$typeID = $recipientType === 'vendor' ? 2 : 1;
$channel = ($_POST['channel'] ?? $_GET['channel'] ?? 'email') === 'sms' ? 'sms' : 'email';
$requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$requestHost = preg_replace('/:\\d+$/', '', $requestHost);
$useLocalMailpit = in_array($requestHost, array('localhost', '127.0.0.1', '::1'), true);
$notice = (string)($_SESSION['bulkMessageNotice'] ?? '');
$noticeClass = (string)($_SESSION['bulkMessageNoticeClass'] ?? 'info');
$deliveryReport = $_SESSION['bulkMessageReport'] ?? array();
if (!is_array($deliveryReport)) {
    $deliveryReport = array();
}
unset($_SESSION['bulkMessageNotice'], $_SESSION['bulkMessageNoticeClass']);
unset($_SESSION['bulkMessageReport']);
$postBodyLimitReached = $_SERVER['REQUEST_METHOD'] === 'POST'
    && empty($_POST)
    && empty($_FILES)
    && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
if ($postBodyLimitReached) {
    $notice = 'The server received no form data. The request may exceed PHP post_max_size (' . (string)ini_get('post_max_size') . '). Reduce attachment sizes or increase the local PHP upload limits.';
    $noticeClass = 'danger';
}
$submittedIDs = $_POST['recipientIDs'] ?? array();
if (!is_array($submittedIDs)) {
    $submittedIDs = array();
}
$selectedIDs = array_values(array_unique(array_filter(array_map('intval', $submittedIDs), static fn($id) => $id > 0)));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sendBulk'])) {
    $subject = trim((string)($_POST['subject'] ?? ''));
    $bcc = trim((string)($_POST['bcc'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));
    $uploadedAttachments = array();
    $attachmentError = '';
    $totalAttachmentBytes = 0;
    if ($channel === 'email' && isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'] ?? null)) {
        $allowedAttachmentTypes = array(
            'pdf' => array('application/pdf'),
            'jpg' => array('image/jpeg'),
            'jpeg' => array('image/jpeg'),
            'png' => array('image/png'),
            'gif' => array('image/gif'),
            'webp' => array('image/webp'),
            'bmp' => array('image/bmp', 'image/x-ms-bmp'),
            'tif' => array('image/tiff'),
            'tiff' => array('image/tiff'),
        );
        $fileCount = count($_FILES['attachments']['name']);
        for ($fileIndex = 0; $fileIndex < $fileCount; $fileIndex++) {
            $uploadCode = (int)($_FILES['attachments']['error'][$fileIndex] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadCode === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $originalName = basename((string)($_FILES['attachments']['name'][$fileIndex] ?? 'attachment'));
            if ($uploadCode !== UPLOAD_ERR_OK) {
                $uploadErrorMessages = array(
                    UPLOAD_ERR_INI_SIZE => 'exceeds the server upload limit',
                    UPLOAD_ERR_FORM_SIZE => 'exceeds the form upload limit',
                    UPLOAD_ERR_PARTIAL => 'was only partially uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'could not be uploaded because the temporary folder is missing',
                    UPLOAD_ERR_CANT_WRITE => 'could not be written to the server',
                    UPLOAD_ERR_EXTENSION => 'was blocked by a server extension',
                );
                $attachmentError = 'Attachment ' . $originalName . ' ' . ($uploadErrorMessages[$uploadCode] ?? 'failed to upload') . '.';
                break;
            }
            $temporaryPath = (string)($_FILES['attachments']['tmp_name'][$fileIndex] ?? '');
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $fileSize = is_uploaded_file($temporaryPath) ? (int)filesize($temporaryPath) : 0;
            $fileInfo = $temporaryPath !== '' && is_uploaded_file($temporaryPath) ? finfo_open(FILEINFO_MIME_TYPE) : false;
            $mimeType = $fileInfo ? (string)finfo_file($fileInfo, $temporaryPath) : '';
            if ($fileInfo) {
                finfo_close($fileInfo);
            }
            if (!isset($allowedAttachmentTypes[$extension]) || !in_array($mimeType, $allowedAttachmentTypes[$extension], true) || $fileSize <= 0) {
                $attachmentError = 'Only valid PDF and image attachments are allowed. Invalid file: ' . $originalName . '.';
                break;
            }
            if ($fileSize > $maxAttachmentFileBytes) {
                $attachmentError = 'This server allows attachments up to ' . number_format($maxAttachmentFileBytes / 1048576, 1) . ' MB each. File too large: ' . $originalName . '.';
                break;
            }
            $totalAttachmentBytes += $fileSize;
            if ($totalAttachmentBytes > $maxAttachmentTotalBytes) {
                $attachmentError = 'This server allows a combined attachment size of ' . number_format($maxAttachmentTotalBytes / 1048576, 1) . ' MB.';
                break;
            }
            $safeName = preg_replace('/[^A-Za-z0-9._ -]/', '_', $originalName);
            $uploadedAttachments[] = array('path' => $temporaryPath, 'name' => $safeName);
        }
    }
    $expectedAttachmentCount = max(0, (int)($_POST['attachmentCount'] ?? 0));
    if ($channel === 'email' && $attachmentError === '' && $expectedAttachmentCount > count($uploadedAttachments)) {
        $attachmentError = 'The browser selected ' . $expectedAttachmentCount . ' attachment(s), but PHP received ' . count($uploadedAttachments) . '. Check post_max_size and upload_max_filesize (' . (string)ini_get('post_max_size') . ' / ' . (string)ini_get('upload_max_filesize') . '). No email was sent.';
    }
    if (!$selectedIDs) {
        $notice = 'Select at least one recipient.';
        $noticeClass = 'danger';
    } elseif ($attachmentError !== '') {
        $notice = $attachmentError;
        $noticeClass = 'danger';
    } elseif (trim(html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === '' && stripos($message, '<img') === false) {
        $notice = 'Message is required.';
        $noticeClass = 'danger';
    } elseif ($channel === 'email' && $subject === '') {
        $notice = 'Subject is required for email.';
        $noticeClass = 'danger';
    } elseif ($channel === 'email' && $bcc !== '' && !filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
        $notice = 'Enter a valid BCC email address or leave the field empty.';
        $noticeClass = 'danger';
    } else {
        $idsSQL = implode(',', $selectedIDs);
        $recipientResult = mysqli_query($conn, "SELECT id,companyName,applicantName,email,email2,mobileNumber FROM customers WHERE type='" . $typeID . "' AND id IN (" . $idsSQL . ")");
        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $failureReasons = array();
        $deliveryReport = array();
        $addDeliveryReport = static function ($entry) use (&$deliveryReport) {
            if (count($deliveryReport) < 100) {
                $deliveryReport[] = (string)$entry;
            }
        };
        $logTime = (new DateTime('now', new DateTimeZone('Asia/Karachi')))->format('l, d-m-y h:i:s a');
        while ($recipientResult && ($recipient = mysqli_fetch_assoc($recipientResult))) {
            $to = $channel === 'email' ? trim((string)$recipient['email']) : trim((string)$recipient['mobileNumber']);
            $recipientName = trim((string)($recipient['companyName'] ?? ''));
            if ($recipientName === '') {
                $recipientName = trim((string)($recipient['applicantName'] ?? '')) ?: 'Recipient #' . (int)$recipient['id'];
            }
            if ($to === '') {
                $skipped++;
                $reason = $recipientName . ': skipped (no ' . ($channel === 'email' ? 'email address' : 'mobile number') . ').';
                $addDeliveryReport($reason);
                if ($channel === 'email') {
                    insertSMSLog('EMAIL: unavailable', $subject, 'SKIPPED: missing email for ' . $recipientName, $logTime);
                } else {
                    insertSMSLog('SMS: unavailable', '', 'SKIPPED: missing mobile for ' . $recipientName, $logTime);
                }
                continue;
            }
            if ($channel === 'email' && !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                $addDeliveryReport($recipientName . ' (' . $to . '): skipped (invalid email address).');
                insertSMSLog('EMAIL: ' . $to, $subject, 'SKIPPED: invalid email address', $logTime);
                continue;
            }
            if ($channel === 'sms') {
                $normalizedMobile = preg_replace('/[\s().-]+/', '', $to);
                if (strpos($normalizedMobile, '+') === 0) {
                    $normalizedMobile = substr($normalizedMobile, 1);
                }
                if (!preg_match('/^92\d{10}$/', $normalizedMobile)) {
                    $skipped++;
                    $addDeliveryReport($recipientName . ' (' . $to . '): skipped (invalid mobile number).');
                    insertSMSLog($to, '', 'SKIPPED: invalid mobile number', $logTime);
                    continue;
                }
                $to = $normalizedMobile;
            }
            $replacements = array(
                '{company_name}' => (string)$recipient['companyName'],
                '{contact_name}' => (string)$recipient['applicantName'],
                '{email}' => (string)$recipient['email'],
                '{mobile}' => (string)$recipient['mobileNumber'],
            );
            $personalMessage = strtr($message, $replacements);
            if ($channel === 'sms') {
                ob_start();
                try {
                    $plainTextMessage = html_entity_decode(strip_tags($personalMessage), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $smsResponse = sendSMS($to, $plainTextMessage);
                    if (strcasecmp(trim((string)$smsResponse), 'OK') === 0) {
                        $sent++;
                        $addDeliveryReport($recipientName . ' (' . $to . '): SMS sent.');
                    } else {
                        $failed++;
                        $smsError = trim((string)$smsResponse) ?: 'SMS gateway did not return OK.';
                        $addDeliveryReport($recipientName . ' (' . $to . '): SMS failed — ' . $smsError);
                        if (count($failureReasons) < 5) $failureReasons[] = $smsError;
                    }
                } catch (Throwable $exception) {
                    $failed++;
                    $addDeliveryReport($recipientName . ' (' . $to . '): SMS failed — ' . $exception->getMessage());
                    if (count($failureReasons) < 5) $failureReasons[] = $exception->getMessage();
                } finally {
                    ob_end_clean();
                }
                continue;
            }

            try {
                $mail = new PHPMailer();
                $mail->CharSet = 'UTF-8';
                $mail->Encoding = 'base64';
                // Keep Mailpit local-only; production uses the application's existing PHP mail transport.
                if ($useLocalMailpit) {
                    $mail->isSMTP();
                    $mail->Host = '127.0.0.1';
                    $mail->Port = 1025;
                    $mail->SMTPAuth = false;
                    $mail->Timeout = 10;
                }
                $mail->setFrom('info@waqaschemicals.com', 'WAQAS CHEMICALS');
                $mail->addAddress($to, (string)$recipient['applicantName']);
                if ($bcc !== '') {
                    $mail->addBCC($bcc);
                }
                $secondEmail = trim((string)$recipient['email2']);
                if ($secondEmail !== '' && filter_var($secondEmail, FILTER_VALIDATE_EMAIL)) {
                    $mail->addCC($secondEmail, (string)$recipient['applicantName']);
                }
                $mail->isHTML(true);
                $mail->Subject = strtr($subject, $replacements);
                $emailBody = '<html><body style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;">' . $personalMessage . '<br><br></body></html>';
                $mail->Body = appendConfiguredEmailFooter($conn, $emailBody);
                foreach ($uploadedAttachments as $attachment) {
                    $mail->addAttachment($attachment['path'], $attachment['name']);
                }
                if ($mail->send()) {
                    $sent++;
                    $emailStatus = 'SENT';
                    $addDeliveryReport($recipientName . ' (' . $to . '): email sent.');
                } else {
                    $failed++;
                    $emailStatus = 'FAILED: ' . ($mail->ErrorInfo !== '' ? $mail->ErrorInfo : 'mailer rejected the message');
                    $addDeliveryReport($recipientName . ' (' . $to . '): email failed — ' . substr($emailStatus, 8));
                    if (count($failureReasons) < 3 && $mail->ErrorInfo !== '') {
                        $failureReasons[] = $mail->ErrorInfo;
                    }
                }
            } catch (Throwable $exception) {
                $failed++;
                $emailStatus = 'FAILED: ' . $exception->getMessage();
                $addDeliveryReport($recipientName . ' (' . $to . '): email failed — ' . $exception->getMessage());
                if (count($failureReasons) < 3) {
                    $failureReasons[] = $exception->getMessage();
                }
            }
            if ($channel === 'email') {
                $emailLogSubject = strtr($subject, $replacements);
                if ($uploadedAttachments) {
                    $emailLogSubject .= ' [attachments: ' . implode(', ', array_column($uploadedAttachments, 'name')) . ']';
                }
                $logResult = insertSMSLog('EMAIL: ' . $to, $emailLogSubject, $emailStatus, $logTime);
                if ($logResult !== true) {
                    $addDeliveryReport('Could not save email log for ' . $to . ': ' . (string)$logResult);
                }
            }
        }
        $notice = ucfirst($channel) . ' dispatch finished: ' . $sent . ' processed, ' . $skipped . ' skipped (missing or invalid destination), ' . $failed . ' failed.';
        if ($channel === 'email' && $uploadedAttachments) {
            $notice .= ' ' . count($uploadedAttachments) . ' attachment(s) included with each email.';
        }
        if ($failureReasons) {
            $notice .= ' Dispatch errors: ' . implode(' | ', array_unique($failureReasons));
        }
        $noticeClass = $failed ? 'warning' : 'success';
        if (count($deliveryReport) >= 100) {
            $deliveryReport[] = 'Only the first 100 delivery details are shown.';
        }
        $selectedIDs = array();
    }

    // Post/Redirect/Get prevents browser refresh from replaying email or SMS sends.
    $_SESSION['bulkMessageNotice'] = $notice;
    $_SESSION['bulkMessageNoticeClass'] = $noticeClass;
    $_SESSION['bulkMessageReport'] = $deliveryReport ?? array();
    header('Location: bulkMessages.php?recipientType=' . urlencode($recipientType) . '&channel=' . urlencode($channel), true, 303);
    exit;
}

$recipients = array();
$recipientResult = mysqli_query($conn, "SELECT id,companyName,applicantName,email,mobileNumber FROM customers WHERE type='" . $typeID . "' ORDER BY companyName ASC");
while ($recipientResult && ($row = mysqli_fetch_assoc($recipientResult))) {
    $recipients[] = $row;
}
include "include/header.php";
?>
<section role="main" class="content-body">
<header class="page-header"><h2>Bulk Email / SMS</h2></header>
<div class="row"><div class="col-lg-12"><section class="panel">
<header class="panel-heading"><h2 class="panel-title">Send a message to selected customers or vendors</h2></header>
<div class="panel-body">
<?php if ($notice !== ''): ?><div class="alert alert-<?=htmlspecialchars($noticeClass, ENT_QUOTES, 'UTF-8');?>"><?=htmlspecialchars($notice, ENT_QUOTES, 'UTF-8');?></div><?php endif; ?>
<?php if ($deliveryReport): ?>
<div class="well well-sm"><strong>Delivery details</strong><ul style="margin:8px 0 0;padding-left:20px">
<?php foreach ($deliveryReport as $reportLine): ?><li><?=htmlspecialchars((string)$reportLine, ENT_QUOTES, 'UTF-8');?></li><?php endforeach; ?>
</ul></div>
<?php endif; ?>
<link rel="stylesheet" href="<?=$siteUrl;?>assets/vendor/summernote/summernote.css">
<link rel="stylesheet" href="<?=$siteUrl;?>assets/vendor/summernote/summernote-bs3.css">
<form method="get" class="form-inline" style="margin-bottom:18px" id="recipientTypeForm">
<span class="text-bold">Recipient type&nbsp;</span>
<label class="radio-inline"><input type="radio" name="recipientType" value="customer" <?=$recipientType === 'customer' ? 'checked' : '';?> onchange="this.form.submit()"> Customer</label>
<label class="radio-inline"><input type="radio" name="recipientType" value="vendor" <?=$recipientType === 'vendor' ? 'checked' : '';?> onchange="this.form.submit()"> Vendor</label>
</form>
<form method="post" id="bulkMessageForm" enctype="multipart/form-data" onsubmit="return confirmBulkSend()">
<input type="hidden" name="recipientType" value="<?=htmlspecialchars($recipientType, ENT_QUOTES, 'UTF-8');?>">
<input type="hidden" name="attachmentCount" id="attachmentCount" value="0">
<div class="form-group" style="margin-right:18px">
<span class="text-bold">Send by&nbsp;</span>
<label class="radio-inline"><input type="radio" name="channel" value="email" <?=$channel === 'email' ? 'checked' : '';?>> Email</label>
<label class="radio-inline"><input type="radio" name="channel" value="sms" <?=$channel === 'sms' ? 'checked' : '';?>> SMS</label>
</div>
<div class="form-group" style="margin:12px 0">
<label><input type="checkbox" id="selectAllRecipients"> Select all eligible</label>
<span id="recipientCount" class="text-muted" style="margin-left:10px"></span>
</div>
<div class="table-responsive">
<table class="table table-bordered table-striped">
<thead><tr><th>Select</th><th>Company</th><th>Contact</th><th>Email</th><th>Mobile</th></tr></thead>
<tbody>
<?php foreach ($recipients as $recipient):
    $destination = $channel === 'sms' ? trim((string)$recipient['mobileNumber']) : trim((string)$recipient['email']);
    $eligible = $destination !== '';
    $checked = in_array((int)$recipient['id'], $selectedIDs, true);
?>
<tr>
<td><input type="checkbox" class="recipient-checkbox" name="recipientIDs[]" value="<?=(int)$recipient['id'];?>" <?=$eligible ? '' : 'disabled';?> <?=$checked ? 'checked' : '';?>></td>
<td><?=htmlspecialchars((string)($recipient['companyName'] ?? ''), ENT_QUOTES, 'UTF-8');?></td>
<td><?=htmlspecialchars((string)($recipient['applicantName'] ?? ''), ENT_QUOTES, 'UTF-8');?></td>
<td><?=htmlspecialchars((string)($recipient['email'] ?? ''), ENT_QUOTES, 'UTF-8');?></td>
<td><?=htmlspecialchars((string)($recipient['mobileNumber'] ?? ''), ENT_QUOTES, 'UTF-8');?></td>
</tr>
<?php endforeach; ?>
<?php if (!$recipients): ?><tr><td colspan="5" class="text-center">No recipients found for this type.</td></tr><?php endif; ?>
</tbody></table></div>
<div class="form-group" id="subjectGroup"><label for="subject">Subject</label><input class="form-control" style="width:100%" type="text" name="subject" id="subject" maxlength="255" value="<?=htmlspecialchars($_POST['subject'] ?? '', ENT_QUOTES, 'UTF-8');?>"></div>
<div class="form-group" id="bccGroup"><label for="bcc">BCC email <span class="text-muted">(optional)</span></label><input class="form-control" style="width:100%" type="email" name="bcc" id="bcc" value="<?=htmlspecialchars($_POST['bcc'] ?? '', ENT_QUOTES, 'UTF-8');?>" placeholder="Leave blank if not needed"><p class="help-block">The BCC recipient receives a copy of each bulk email. This field may be left empty.</p></div>
<div class="form-group" id="messageGroup" style="width:100%"><label for="message">Message</label><textarea class="form-control" style="width:100%;min-height:180px" name="message" id="message"><?=htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8');?></textarea>
<p class="help-block">Email supports rich formatting. SMS is sent as plain text. Merge tags: <code>{company_name}</code>, <code>{contact_name}</code>, <code>{email}</code>, <code>{mobile}</code></p></div>
<div class="form-group" id="attachmentGroup" style="width:100%"><label for="attachments">Attachments</label><input class="form-control" type="file" name="attachments[]" id="attachments" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.bmp,.tif,.tiff,application/pdf,image/jpeg,image/png,image/gif,image/webp,image/bmp,image/tiff"><p class="help-block">Attach multiple PDF or image files (maximum <?=number_format($maxAttachmentFileBytes / 1048576, 1);?> MB each, <?=number_format($maxAttachmentTotalBytes / 1048576, 1);?> MB total). Files are sent as attachments, not embedded images.</p></div>
<button type="button" class="btn btn-default" onclick="showBulkPreview()">Preview message</button>
<button type="submit" name="sendBulk" value="1" class="btn btn-primary">Send Bulk Message</button>
</form>
<div class="modal fade" id="bulkPreviewModal" tabindex="-1" role="dialog" aria-labelledby="bulkPreviewTitle" aria-hidden="true">
<div class="modal-dialog modal-lg" role="document"><div class="modal-content">
<div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button><h4 class="modal-title" id="bulkPreviewTitle">Message preview</h4></div>
<div class="modal-body">
<p><strong>Sample recipient:</strong> <span id="previewRecipient"></span></p>
<div id="previewSubjectWrap"><strong>Subject:</strong> <span id="previewSubject"></span></div>
<div id="previewAttachmentsWrap" style="margin-top:8px;display:none"><strong>Attachments:</strong> <span id="previewAttachments"></span></div>
<iframe id="bulkPreviewFrame" title="Message preview content" sandbox="" style="width:100%;height:320px;border:1px solid #ddd;margin-top:12px;background:#fff"></iframe>
</div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close preview</button></div>
</div></div></div>
</div></section></div></div>
<script src="<?=$siteUrl;?>assets/vendor/summernote/summernote.js"></script>
<script>
function selectedBulkChannel() {
    var selected = document.querySelector('input[name="channel"]:checked');
    return selected ? selected.value : 'email';
}
function updateBulkRecipients() {
    var channel = selectedBulkChannel();
    document.getElementById('subjectGroup').style.display = channel === 'email' ? '' : 'none';
    document.getElementById('bccGroup').style.display = channel === 'email' ? '' : 'none';
    document.getElementById('bcc').disabled = channel !== 'email';
    document.getElementById('attachmentGroup').style.display = channel === 'email' ? '' : 'none';
    document.getElementById('attachments').disabled = channel !== 'email';
    updateMessageEditor(channel);
    document.querySelectorAll('.recipient-checkbox').forEach(function(box) {
        var row = box.closest('tr');
        var destinationCell = row.children[channel === 'sms' ? 4 : 3];
        var destination = destinationCell.textContent.trim();
        var eligible = channel === 'sms' ? validSmsDestination(destination) : /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(destination);
        box.disabled = !eligible;
        if (!eligible) box.checked = false;
    });
    updateBulkCount();
}
function updateMessageEditor(channel) {
    var field = document.getElementById('message');
    var editor = document.querySelector('#messageGroup .note-editor');
    if (channel === 'sms') {
        if (editor) {
            var editable = editor.querySelector('.note-editable');
            var text = document.createElement('div');
            text.innerHTML = editable ? editable.innerHTML : '';
            field.value = (text.textContent || text.innerText || '').replace(/\u00a0/g, ' ');
            editor.style.display = 'none';
            field.style.display = '';
        }
        return;
    }
    if (!editor) {
        $('#message').summernote({height:220});
        editor = document.querySelector('#messageGroup .note-editor');
    } else {
        var currentText = field.value || '';
        var escapedText = currentText.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\r?\n/g, '<br>');
        $('#message').code(escapedText);
    }
    if (editor) editor.style.display = '';
    field.style.display = 'none';
}
function updateBulkCount() {
    var boxes = Array.from(document.querySelectorAll('.recipient-checkbox'));
    var selected = boxes.filter(function(box) { return box.checked; }).length;
    var eligible = boxes.filter(function(box) { return !box.disabled; }).length;
    document.getElementById('recipientCount').textContent = selected + ' selected / ' + eligible + ' eligible';
}
function validSmsDestination(value) {
    var normalized = value.replace(/[\s().-]+/g, '').replace(/^\+/, '');
    return /^92\d{10}$/.test(normalized);
}
function getBulkMessageHtml() {
    if (selectedBulkChannel() === 'sms') return document.getElementById('message').value || '';
    var editable = document.querySelector('#bulkMessageForm .note-editable');
    return editable ? editable.innerHTML : (document.getElementById('message').value || '');
}
function escapePreviewHtml(value) {
    return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
function showBulkPreview() {
    var channel = selectedBulkChannel();
    var selectedBox = document.querySelector('.recipient-checkbox:checked');
    var sample = {company_name: '', contact_name: '', email: '', mobile: ''};
    var recipientLabel = 'No recipient selected';
    if (selectedBox) {
        var cells = selectedBox.closest('tr').children;
        sample.company_name = cells[1].textContent.trim();
        sample.contact_name = cells[2].textContent.trim();
        sample.email = cells[3].textContent.trim();
        sample.mobile = cells[4].textContent.trim();
        recipientLabel = sample.contact_name || sample.company_name || sample.email || sample.mobile;
    }
    document.getElementById('previewRecipient').textContent = recipientLabel + ' (sample; merge tags are recipient-specific)';
    document.getElementById('previewSubject').textContent = document.getElementById('subject').value.replace(/\{(company_name|contact_name|email|mobile)\}/g, function(tag, key) { return sample[key]; });
    document.getElementById('previewSubjectWrap').style.display = channel === 'email' ? '' : 'none';
    var fileInput = document.getElementById('attachments');
    var fileNames = channel === 'email' ? Array.from(fileInput.files).map(function(file) { return file.name; }) : [];
    document.getElementById('previewAttachments').textContent = fileNames.join(', ');
    document.getElementById('previewAttachmentsWrap').style.display = fileNames.length ? '' : 'none';
    var content = getBulkMessageHtml().replace(/\{(company_name|contact_name|email|mobile)\}/g, function(tag, key) { return escapePreviewHtml(sample[key]); });
    var previewDocument = channel === 'email'
        ? '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:Arial,sans-serif;padding:16px;color:#222;word-wrap:break-word}img{max-width:100%;height:auto}</style></head><body>' + content + '</body></html>'
        : '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:Arial,sans-serif;padding:16px;color:#222}pre{white-space:pre-wrap;word-wrap:break-word;font:inherit}</style></head><body><pre>' + escapePreviewHtml(document.getElementById('message').value) + '</pre></body></html>';
    document.getElementById('bulkPreviewFrame').srcdoc = previewDocument;
    $('#bulkPreviewModal').modal('show');
}
function confirmBulkSend() {
    var count = document.querySelectorAll('.recipient-checkbox:checked').length;
    if (!count) { alert('Select at least one eligible recipient.'); return false; }
    var content = getBulkMessageHtml();
    var contentText = document.createElement('div');
    contentText.innerHTML = content;
    var plainText = (contentText.textContent || contentText.innerText || '').replace(/[\s\u00a0\u200b]+/g, ' ').trim();
    if (!plainText && !/<img\b/i.test(content)) { alert('Enter a message before sending.'); return false; }
    if (selectedBulkChannel() === 'email' && !document.getElementById('subject').value.trim()) { alert('Enter an email subject before sending.'); return false; }
    var attachmentInput = document.getElementById('attachments');
    document.getElementById('attachmentCount').value = selectedBulkChannel() === 'email' && !attachmentInput.disabled ? attachmentInput.files.length : 0;
    // The legacy Summernote version does not reliably sync a textarea's value on submit.
    document.getElementById('message').value = content;
    return confirm('Send this ' + selectedBulkChannel().toUpperCase() + ' message to ' + count + ' recipient(s)?');
}
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[name="channel"]').forEach(function(radio) { radio.addEventListener('change', updateBulkRecipients); });
    document.getElementById('selectAllRecipients').addEventListener('change', function() {
        document.querySelectorAll('.recipient-checkbox:not(:disabled)').forEach(function(box) { box.checked = this.checked; }, this);
        updateBulkCount();
    });
    document.querySelectorAll('.recipient-checkbox').forEach(function(box) { box.addEventListener('change', updateBulkCount); });
    updateBulkRecipients();
});
</script>
</section>
<?php include "include/footer.php"; ?>
