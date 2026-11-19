<?php
require_once 'include/sessionAdmin.php';
require_once 'include/configure.php';
require_once '../php/order_agents.php';
ensureOrderAgentSchema($conn);
$error = '';
$agentID = (int)($_GET['id'] ?? 0);
$editing = isset($_GET['add']) || $agentID > 0;
$fields = array('applicantName'=>'Contact person','companyName'=>'Company name','mobileNumber'=>'Mobile / WhatsApp','email'=>'Email','email2'=>'CC email','phoneNumber'=>'Phone','address'=>'Address','city'=>'City','country'=>'Country','moreInfo'=>'Information');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    agentRequireCsrf();
    try {
        if (isset($_POST['deleteAgent'])) {
            deleteUnassignedAgent($conn, (int)($_POST['id'] ?? 0));
            header('Location: agents.php?deleted=1'); exit;
        }
        if (isset($_POST['duplicateContact'])) {
            $agentID = duplicateContactAsAgent($conn, (int)$_POST['duplicateContact']);
            header('Location: agents.php?id=' . $agentID . '&created=1'); exit;
        }
        $agentID = (int)($_POST['id'] ?? 0);
        $editing = true;
        if ($agentID && !mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM customers WHERE id=$agentID AND type=4"))) throw new InvalidArgumentException('Agent not found.');
        $values = array();
        foreach ($fields as $field=>$label) $values[$field] = trim((string)($_POST[$field] ?? ''));
        if ($values['applicantName'] === '') throw new InvalidArgumentException('Contact person is required.');
        foreach (array('email','email2') as $field) if ($values[$field] !== '' && !filter_var($values[$field], FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email address.');
        if ($values['mobileNumber'] !== '' && !preg_match('/^\+[1-9][0-9]{7,14}$/D', $values['mobileNumber'])) throw new InvalidArgumentException('Use an international mobile number, for example +923001234567.');
        $values['isEnableEmail'] = isset($_POST['isEnableEmail']) ? 1 : 0;
        $values['isEnableSMS'] = isset($_POST['isEnableSMS']) ? 1 : 0;
        $values['isEnableWhatsApp'] = isset($_POST['isEnableWhatsApp']) ? 1 : 0;
        if (!$agentID) {
            foreach (array('bankName','bankBranch','appAddress','appCity','appCountry','appSwiftCode','appIBAN','appAccountNo') as $field) $values[$field] = '';
            $values += array('type'=>4,'isOtherCustomer'=>0,'assignToID'=>0,'createdDate'=>date('Y-m-d H:i:s'));
        }
        $assignments = array();
        foreach ($values as $field=>$value) $assignments[] = '`'.$field."`='".mysqli_real_escape_string($conn,(string)$value)."'";
        mysqli_query($conn, ($agentID ? 'UPDATE customers SET ' : 'INSERT INTO customers SET ') . implode(',', $assignments) . ($agentID ? " WHERE id=$agentID AND type=4" : ''));
        if (!$agentID) $agentID = (int)mysqli_insert_id($conn);
        header('Location: agents.php?id=' . $agentID . '&saved=1'); exit;
    } catch (InvalidArgumentException $failure) { $error = $failure->getMessage(); }
}
$agent = $agentID ? mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM customers WHERE id=$agentID AND type=4")) : array();
if ($agentID && !$agent) { http_response_code(404); exit('Agent not found.'); }
include 'include/header.php';
?>
<section role="main" class="content-body"><header class="page-header"><h2>Manage Agents</h2></header>
<section class="panel"><header class="panel-heading"><div class="panel-actions"><a href="agents.php?add=1">Add Agent</a> | <a href="contactBook.php">Contacts</a></div><h2 class="panel-title"><?=$editing?($agentID?'Edit Agent':'Add Agent'):'Agents'?></h2></header><div class="panel-body">
<?php if ($error): ?><div class="alert alert-danger"><?=agentEscape($error)?></div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Agent saved.</div><?php endif; ?>
<?php if (isset($_GET['created'])): ?><div class="alert alert-success">Contact duplicated as Agent. The original contact is unchanged.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Agent deleted successfully.</div><?php endif; ?>
<?php if ($editing): ?>
<form method="post" class="form-horizontal"><input type="hidden" name="agent_csrf" value="<?=agentEscape(agentCsrfToken())?>"><input type="hidden" name="id" value="<?=(int)$agentID?>">
<div class="form-group"><label class="col-md-3 control-label">Contact type</label><div class="col-md-7"><p class="form-control-static">Agent</p></div></div>
<?php foreach ($fields as $field=>$label): ?>
<div class="form-group"><label class="col-md-3 control-label" for="<?=$field?>"><?=$label?></label><div class="col-md-7">
<?php if ($field==='moreInfo'): ?><textarea class="form-control" id="<?=$field?>" name="<?=$field?>" rows="3"><?=agentEscape($_POST[$field] ?? $agent[$field] ?? '')?></textarea>
<?php else: ?><input class="form-control" id="<?=$field?>" name="<?=$field?>" type="<?=strpos($field,'email')===0?'email':'text'?>" value="<?=agentEscape($_POST[$field] ?? $agent[$field] ?? '')?>" <?=$field==='applicantName'?'required':''?>><?php endif; ?></div></div>
<?php endforeach; ?>
<div class="form-group"><label class="col-md-3 control-label">Notifications</label><div class="col-md-7 agent-notification-options">
<label><input type="checkbox" name="isEnableEmail" value="1" <?=(!empty($_POST) ? !empty($_POST['isEnableEmail']) : !empty($agent['isEnableEmail']))?'checked':''?>> Enable email</label>
<label><input type="checkbox" name="isEnableSMS" value="1" <?=(!empty($_POST) ? !empty($_POST['isEnableSMS']) : !empty($agent['isEnableSMS']))?'checked':''?>> Enable SMS</label>
<label><input type="checkbox" name="isEnableWhatsApp" value="1" <?=(!empty($_POST) ? !empty($_POST['isEnableWhatsApp']) : !empty($agent['isEnableWhatsApp']))?'checked':''?>> Enable WhatsApp messages</label>
<p class="help-block">Use an international mobile number for SMS and WhatsApp, for example +923001234567.</p></div></div>
<div class="form-group"><div class="col-md-offset-3 col-md-7"><button class="btn btn-primary">Save Agent</button> <a class="btn btn-default" href="agents.php">Back</a></div></div>
</form>
<?php if ($agentID): ?>
<?php $agentCounts = agentAssignmentCounts($conn, $agentID); ?>
<hr><h3>Orders and commissions</h3><p>Open an order to set its commission.</p><?php renderAgentOrders($conn,$agentID); ?>
<hr><h3>Delete Agent</h3>
<?php if ($agentCounts['orders'] === 0 && $agentCounts['tasks'] === 0): ?>
<p>This agent has no assigned orders or tasks and can be deleted.</p>
<form method="post" onsubmit="return confirm('Delete this agent permanently?');"><input type="hidden" name="agent_csrf" value="<?=agentEscape(agentCsrfToken())?>"><input type="hidden" name="id" value="<?=(int)$agentID?>"><button type="submit" name="deleteAgent" value="1" class="btn btn-danger">Delete Agent</button></form>
<?php else: ?>
<div class="alert alert-warning">Deletion is unavailable: this agent has <?=(int)$agentCounts['orders']?> assigned order(s) and <?=(int)$agentCounts['tasks']?> assigned task(s).</div>
<?php endif; ?>
<?php endif; ?>
<?php else: ?>
<p>Add an agent here, or use <strong>Duplicate as Agent</strong> in <a href="contactBook.php">Contacts</a>.</p>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Contact person</th><th>Company</th><th>Mobile</th><th>Orders</th><th>Tasks</th><th>Completed</th><th></th></tr></thead><tbody>
<?php
$agents=mysqli_query($conn,"SELECT c.id,c.applicantName,c.companyName,c.mobileNumber,COUNT(DISTINCT o.id) AS orders,COALESCE((SELECT COUNT(*) FROM tasks t WHERE FIND_IN_SET(CAST(c.id AS CHAR), REPLACE(t.assignTo, ' ', '')) > 0),0) AS tasks,SUM(CASE WHEN LOWER(TRIM(s.status))='completed' THEN 1 ELSE 0 END) AS completed FROM customers c LEFT JOIN orders o ON o.agentID=c.id LEFT JOIN status s ON s.id=o.statusID WHERE c.type=4 GROUP BY c.id,c.applicantName,c.companyName,c.mobileNumber ORDER BY c.applicantName");
while($row=mysqli_fetch_assoc($agents)): ?>
<tr><td><?=agentEscape($row['applicantName'])?></td><td><?=agentEscape($row['companyName'])?></td><td><?=agentEscape($row['mobileNumber'])?></td><td><?=(int)$row['orders']?></td><td><?=(int)$row['tasks']?></td><td><?=(int)$row['completed']?></td><td><a class="btn btn-default" href="agents.php?id=<?=(int)$row['id']?>">Edit / Orders</a><?php if ((int)$row['orders'] === 0 && (int)$row['tasks'] === 0): ?> <form method="post" style="display:inline" onsubmit="return confirm('Delete this agent permanently?');"><input type="hidden" name="agent_csrf" value="<?=agentEscape(agentCsrfToken())?>"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><button type="submit" name="deleteAgent" value="1" class="btn btn-danger">Delete</button></form><?php endif; ?></td></tr>
<?php endwhile; ?></tbody></table></div>
<?php endif; ?></div></section></section>
<?php include 'include/footer.php'; ?>
