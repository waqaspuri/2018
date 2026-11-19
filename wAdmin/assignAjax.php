<?php
include "include/sessionAdmin.php";  
include "include/configure.php";
if($_GET['act']=="urs"){
	header('Content-Type: application/json; charset=utf-8');
	$orderID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
	if ($orderID < 1) {
		echo json_encode(array());
		exit;
	}
	$customerAry = array();
    require_once '../php/order_agents.php';
    ensureOrderAgentSchema($conn);
    $sqlOrdr = mysqli_query($conn,"SELECT customerID,vendorID,agentID FROM orders where id='".$orderID."' LIMIT 1");
	$rowOrdr = $sqlOrdr ? mysqli_fetch_assoc($sqlOrdr) : false;
	if (!$rowOrdr) {
		echo json_encode(array());
		exit;
	}
    $customerID = (int)$rowOrdr['customerID'];
    $vendorID = (int)$rowOrdr['vendorID'];
    $agentID = (int)$rowOrdr['agentID'];
    $accountIDs = $customerID . ',' . $vendorID;
    $agentCondition = $agentID > 0 ? ' OR id=' . $agentID : '';
    $sql = mysqli_query($conn,"SELECT id,applicantName,companyName,type,email,mobileNumber FROM customers WHERE (isOtherCustomer='1' OR id IN (".$accountIDs.") OR assignToID IN (".$accountIDs.")".$agentCondition.") ORDER BY type,applicantName ASC");
	$contacts = array();
	while ($sql && ($row = mysqli_fetch_assoc($sql))) {
		$contacts[] = array(
			'id' => (int)$row['id'],
            'label' => (string)$row['companyName'] . ' (' . typeNm($row['type']) . ')' . ((int)$row['type'] === 4 ? ' — ' . (string)$row['applicantName'] : ''),
			'type' => (int)$row['type']
		);
	}
	echo json_encode($contacts, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	exit;
}else if($_GET['act']=="mailTmp"){
	$sql = mysqli_query($conn,"SELECT subject,message FROM send_templates where id='".$_GET['id']."'");
	$row = mysqli_fetch_assoc($sql);
	echo $row['subject'].',~,'.$row['message'];
}else if($_GET['act']=="smsTmp"){
	$sql = mysqli_query($conn,"SELECT message FROM send_templates where id='".$_GET['id']."'");
	$row = mysqli_fetch_assoc($sql);
	echo $row['message'];
}else{
	header('location: ../');
}
?>
