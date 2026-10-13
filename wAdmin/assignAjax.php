<?php
include "include/sessionAdmin.php";  
include "include/configure.php";
if($_GET['act']=="urs"){
	$customerAry = array();
	$sqlOrdr = mysqli_query($conn,"SELECT customerID,vendorID FROM orders where id='".$_GET['id']."'");
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
	$sql = mysqli_query($conn,"SELECT id,companyName,type FROM customers where (isOtherCustomer='1' OR id in (".@implode(",",$customerAry).")) ORDER BY type,applicantName ASC");
	$numRow = mysqli_num_rows($sql);
	if($numRow>0){
		while($row = mysqli_fetch_assoc($sql)){
			$str.=(($row['type']==3)?'style="color:red"':' ').'||'.$row['id']."::".$row['companyName'].' ('.typeNm($row['type']).'),~,';
		}
	}else{
		$str=" :No data found,";
	}
	echo $str;
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