<?php
include "include/sessionAdmin.php";  
include "include/configure.php";
include "include/header.php"; 

$logLimit = max(1, min(500, (int)($_GET['limit'] ?? 50)));
$emailResult = mysqli_query($conn, "SELECT * FROM sms_log WHERE client LIKE 'EMAIL: %' ORDER BY id DESC LIMIT " . $logLimit);
$smsResult = mysqli_query($conn, "SELECT * FROM sms_log WHERE client NOT LIKE 'EMAIL: %' AND client NOT LIKE 'WHATSAPP: %' ORDER BY id DESC LIMIT " . $logLimit);
$whatsAppResult = mysqli_query($conn, "SELECT * FROM sms_log WHERE client LIKE 'WHATSAPP: %' ORDER BY id DESC LIMIT " . $logLimit);
$emailLogs = $emailResult ? mysqli_fetch_all($emailResult, MYSQLI_ASSOC) : array();
$smsLogs = $smsResult ? mysqli_fetch_all($smsResult, MYSQLI_ASSOC) : array();
$whatsAppLogs = $whatsAppResult ? mysqli_fetch_all($whatsAppResult, MYSQLI_ASSOC) : array();
?>


<section role="main" class="content-body">
  <header class="page-header">
    <h2>Communication Logs</h2>
    <div class="right-wrapper pull-right">
      <ol class="breadcrumbs">
        <li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
        <li><span>Communication Logs</span></li>
      </ol>
      <a >&nbsp;</a>
    </div>
  </header>

<section class="panel">
  <header class="panel-heading"><h2 class="panel-title">Communication Dispatch Log</h2></header>
  <div class="panel-body">
    <ul class="nav nav-tabs" role="tablist" style="margin-bottom:15px">
      <li class="active"><a href="#emailLogs" role="tab" data-toggle="tab">Email <span class="badge"><?=count($emailLogs);?></span></a></li>
      <li><a href="#smsLogs" role="tab" data-toggle="tab">SMS <span class="badge"><?=count($smsLogs);?></span></a></li>
      <li><a href="#whatsAppLogs" role="tab" data-toggle="tab">WhatsApp <span class="badge"><?=count($whatsAppLogs);?></span></a></li>
    </ul>
    <div class="tab-content">
      <div class="tab-pane active" id="emailLogs">
        <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <th width="25%" style="padding-left:10px">Date</th>
          <th width="10%">Type</th>
          <th width="25%">Recipient</th>
          <th width="30%">Subject / Gateway ID</th>
          <th width="10%">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($emailLogs as $row) {
          $recipientLabel = substr((string)$row['client'], 7);
          $statusLabel = (string)$row['api_response'];
          $successfulLog = strpos($statusLabel, 'SENT') === 0;
        ?>
          <tr>
            <td><?=htmlspecialchars((string)$row['time_stamp'], ENT_QUOTES, 'UTF-8');?></td>
            <td>Email</td>
            <td><?=htmlspecialchars($recipientLabel, ENT_QUOTES, 'UTF-8');?></td>
            <td><?=htmlspecialchars((string)$row['api_data'], ENT_QUOTES, 'UTF-8');?></td>
            <td><span style="color:<?=$successfulLog ? 'green' : '#b94a48';?>"><?=htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8');?></span></td>
          </tr>
        <?php } ?>
        <?php if (!$emailLogs): ?><tr><td colspan="5" class="text-center text-muted">No email logs found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
      </div>
      <div class="tab-pane" id="smsLogs">
        <div class="table-responsive">
          <table class="table table-striped table-hover">
            <thead><tr><th width="25%" style="padding-left:10px">Date</th><th width="25%">Recipient</th><th width="30%">Message / Gateway ID</th><th width="20%">Status</th></tr></thead>
            <tbody>
              <?php foreach ($smsLogs as $row) {
                $statusLabel = (string)$row['api_response'];
                $successfulLog = $statusLabel === 'OK';
              ?>
                <tr>
                  <td><?=htmlspecialchars((string)$row['time_stamp'], ENT_QUOTES, 'UTF-8');?></td>
                  <td><?=htmlspecialchars((string)$row['client'], ENT_QUOTES, 'UTF-8');?></td>
                  <td><?=htmlspecialchars((string)$row['api_data'], ENT_QUOTES, 'UTF-8');?></td>
                  <td><span style="color:<?=$successfulLog ? 'green' : '#b94a48';?>"><?=htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8');?></span></td>
                </tr>
              <?php } ?>
              <?php if (!$smsLogs): ?><tr><td colspan="4" class="text-center text-muted">No SMS logs found.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="tab-pane" id="whatsAppLogs">
        <div class="table-responsive">
          <table class="table table-striped table-hover">
            <thead><tr><th width="20%" style="padding-left:10px">Date</th><th width="20%">Recipient</th><th width="40%">Template / Variables</th><th width="20%">Status</th></tr></thead>
            <tbody>
              <?php foreach ($whatsAppLogs as $row) {
                $statusLabel = (string)$row['api_response'];
                $successfulLog = strpos($statusLabel, 'SENT:') === 0;
                $recipientLabel = substr((string)$row['client'], 10);
                $requestData = json_decode((string)$row['api_data'], true);
                $requestLabel = is_array($requestData) ? json_encode($requestData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string)$row['api_data'];
              ?>
                <tr>
                  <td><?=htmlspecialchars((string)$row['time_stamp'], ENT_QUOTES, 'UTF-8');?></td>
                  <td><?=htmlspecialchars($recipientLabel, ENT_QUOTES, 'UTF-8');?></td>
                  <td><?=htmlspecialchars($requestLabel, ENT_QUOTES, 'UTF-8');?></td>
                  <td><span style="color:<?=$successfulLog ? 'green' : (strpos($statusLabel, 'PREVIEW:') === 0 ? '#777' : '#b94a48');?>"><?=htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8');?></span></td>
                </tr>
              <?php } ?>
              <?php if (!$whatsAppLogs): ?><tr><td colspan="4" class="text-center text-muted">No WhatsApp logs found.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <form method="get" class="form-inline">
      <label for="limit">Recent logs per category&nbsp;</label>
      <select class="form-control" id="limit" name="limit" onchange="this.form.submit()">
        <?php foreach (array(10, 25, 50, 100, 250, 500) as $limitOption): ?>
          <option value="<?=$limitOption;?>" <?=$logLimit === $limitOption ? 'selected' : '';?>><?=$limitOption;?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</section>
</section>

<?php include "include/footer.php"; ?>
