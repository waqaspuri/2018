<?php
// Uses temporary tables only. Never dispatches email, SMS or WhatsApp.
require __DIR__ . '/../php/connect-mysql.php';
require __DIR__ . '/../php/order_agents.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
foreach (array('customers','orders','products','status') as $table) {
    $definition=$conn->query("SHOW CREATE TABLE `$table`")->fetch_row()[1];
    $conn->query(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $definition));
}
ensureOrderAgentSchema($conn);
function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
function fixture($conn, $table, $overrides) {
    $values = array();
    $columns = $conn->query("SHOW COLUMNS FROM `$table`");
    while ($column=$columns->fetch_assoc()) {
        if ($column['Extra']==='auto_increment') continue;
        $name=$column['Field'];
        $values[$name]=$column['Default'] ?? ($column['Null']==='YES' ? null : (preg_match('/int|float|decimal|double/', $column['Type']) ? 0 : ''));
    }
    $values=array_merge($values,$overrides);
    $sql=array();
    foreach ($values as $name=>$value) $sql[]="`$name`=".($value===null?'NULL':"'".$conn->real_escape_string((string)$value)."'");
    $conn->query("INSERT INTO `$table` SET ".implode(',',$sql));
    return (int)$conn->insert_id;
}
$customer=fixture($conn,'customers',array('type'=>1,'applicantName'=>'Customer','email'=>'customer@example.test','isEnableEmail'=>1,'isEnableSMS'=>1,'isEnableWhatsApp'=>1));
$vendor=fixture($conn,'customers',array('type'=>2,'applicantName'=>'Vendor'));
$contact=fixture($conn,'customers',array('type'=>3,'assignToID'=>$customer,'applicantName'=>'Contact','mobileNumber'=>'+923001234567','email'=>'agent@example.test','email2'=>'agent-cc@example.test'));
$vendorContact=fixture($conn,'customers',array('type'=>3,'assignToID'=>$vendor));
$unrelated=fixture($conn,'customers',array('type'=>3));
$agent=duplicateContactAsAgent($conn,$contact);
$original=$conn->query("SELECT * FROM customers WHERE id=$contact")->fetch_assoc();
$copy=$conn->query("SELECT * FROM customers WHERE id=$agent")->fetch_assoc();
check((int)$original['type']===3 && (int)$copy['type']===4 && $agent!==$contact,'Duplicate preserves original and creates Agent contact type');
check($copy['email']===$original['email'] && $copy['email2']===$original['email2'] && $copy['mobileNumber']===$original['mobileNumber'] && (int)$copy['assignToID']===0,'Duplicate copies notification details without customer assignment');
$product=fixture($conn,'products',array('prodName'=>'Test product'));
$status=fixture($conn,'status',array('status'=>'Completed'));
$order=fixture($conn,'orders',array('customerID'=>$customer,'vendorID'=>$vendor,'productID'=>$product,'statusID'=>$status));
$recipients=implode(',',array($customer,$contact,$vendor,$vendorContact,$unrelated,$customer));
check(orderAgentRecipientIDs($conn,$order,$recipients)===array($customer,$contact,$vendor,$vendorContact,$unrelated),'Disabled Set Agent preserves normal recipients');
$_POST['agent_csrf']=agentCsrfToken();
$input=array('setAgent'=>1,'agentID'=>$agent,'agentCommission'=>'1250.75','agentCommissionCurrency'=>'PKR');
$assignment=validateOrderAgentInput($conn,$input);
saveOrderAgent($conn,$order,$assignment);
check(orderAgentRecipientIDs($conn,$order,$recipients)===array($customer,$contact,$vendor,$vendorContact,$unrelated,$agent),'Customer, customer contacts, vendor and other recipients remain; Agent is added once');
check(orderAgentRecipientIDs($conn,0,$recipients)===array($customer,$contact,$vendor,$vendorContact,$unrelated),'Unlinked tasks remain unchanged');
check(orderAgentRecipientIDs($conn,$order,array($agent,$customer,$contact))===array($agent,$customer,$contact),'Already selected agent is not sent duplicate messages');
$conn->query("UPDATE customers SET isEnableEmail=0,isEnableSMS=0,isEnableWhatsApp=0 WHERE id=$agent");
check(filterAgentRecipientsByChannel($conn,array($customer,$agent),'email')===array($customer),'Unticked email preference blocks Agent but keeps enabled Customer');
check(filterAgentRecipientsByChannel($conn,array($customer,$agent),'sms')===array($customer),'Unticked SMS preference blocks Agent but keeps enabled Customer');
check(filterAgentRecipientsByChannel($conn,array($customer,$agent),'whatsapp')===array($customer),'Unticked WhatsApp preference blocks Agent but keeps enabled Customer');
$conn->query("UPDATE customers SET isEnableEmail=0,isEnableSMS=0,isEnableWhatsApp=0 WHERE id=$customer");
check(filterAgentRecipientsByChannel($conn,array($customer),'email')===array(),'Unticked email preference also blocks Customer');
check(filterAgentRecipientsByChannel($conn,array($customer),'sms')===array(),'Unticked SMS preference also blocks Customer');
check(filterAgentRecipientsByChannel($conn,array($customer),'whatsapp')===array(),'Unticked WhatsApp preference also blocks Customer');
$conn->query("UPDATE customers SET isEnableEmail=1,isEnableSMS=1,isEnableWhatsApp=1 WHERE id=$customer");
$summaryTask=fixture($conn,'tasks',array('orderID'=>$order,'assignTo'=>(string)$customer,'sendBy'=>'mail,sms,whatsapp','dueDate'=>'1970-01-01','repeatDate'=>'1970-01-01','createdDate'=>date('Y-m-d H:i:s')));
$attemptSummary=taskNotificationAttemptSummary($conn,$summaryTask);
check(strpos($attemptSummary,'Email attempted:')!==false && substr_count($attemptSummary,'skipped (recipient notification setting disabled)')===3,'Attempt summary reports allowed recipients and all disabled channels');
$attemptSummaryHtml=taskNotificationAttemptSummaryHtml($attemptSummary);
check(strpos($attemptSummaryHtml,'<table')!==false && strpos($attemptSummaryHtml,'Will receive')!==false && strpos($attemptSummaryHtml,'Skipped — preference disabled')!==false,'Attempt summary renders as a readable channel table');
$conn->query("UPDATE customers SET isEnableEmail=1,isEnableSMS=1,isEnableWhatsApp=1 WHERE id=$agent");
check(filterAgentRecipientsByChannel($conn,array($customer,$agent),'email')===array($customer,$agent),'Enabled email preference includes Agent');
check(filterAgentRecipientsByChannel($conn,array($customer,$agent),'sms')===array($customer,$agent),'Enabled SMS preference includes Agent');
check(filterAgentRecipientsByChannel($conn,array($customer,$agent),'whatsapp')===array($customer,$agent),'Enabled WhatsApp preference includes Agent');
$row=agentOrderRows($conn,$agent)->fetch_assoc();
check($row['agentCommission']==='1250.75' && $row['status']==='Completed','Commission saved precisely and assigned completed order reported');
ob_start();renderAgentOrders($conn,$agent);$html=ob_get_clean();
check(strpos($html,'1 assigned orders')!==false && strpos($html,'1 completed orders')!==false && strpos($html,'1,250.75')!==false,'Agent report shows counts and commission');
$secondAgent=duplicateContactAsAgent($conn,$contact);
$assignment['agentID']=$secondAgent;saveOrderAgent($conn,$order,$assignment);
check(orderAgentRecipientIDs($conn,$order,array($customer))===array($customer,$secondAgent),'Queued delivery keeps customer and follows Agent reassignment');
check(agentOrderRows($conn,$agent)->num_rows===0,'Old agent no longer includes reassigned order');
foreach (array(array('agentID'=>$customer),array('agentID'=>99999999),array('agentCommission'=>'-1'),array('agentCommission'=>'1.234'),array('agentCommission'=>'1e6'),array('agentCommissionCurrency'=>'XXX')) as $invalid) {
    $rejected=false;
    try {validateOrderAgentInput($conn,array_merge($input,$invalid));} catch(InvalidArgumentException $error) {$rejected=true;}
    check($rejected,'Invalid assignment/commission rejected: '.json_encode($invalid));
}
saveOrderAgent($conn,$order,validateOrderAgentInput($conn,array()));
$row=$conn->query("SELECT agentID,agentCommission FROM orders WHERE id=$order")->fetch_assoc();
check((int)$row['agentID']===0 && $row['agentCommission']==='0.00','Disabling Set Agent clears assignment and commission');
check(orderAgentRecipientIDs($conn,$order,array($customer))===array($customer),'Disabled assignment restores customer delivery');
echo "All agent integration checks passed; temporary fixtures disappear when connection closes.\n";
