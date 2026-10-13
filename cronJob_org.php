<?php
//$to = "waqaspuri@gmail.com";
//$subject = "cron job working now";
//$message = "cron job working...";
//$from = "info@waqaschemicals.com";
//$headers = "From:" . $from;
//@mail($to,$subject,$message,$headers);
//echo "Mail Sent.";
?>

<?php
include "php/connect-mysql.php";
include "php/functions.php";
//exprd
$dtAry = explode(" ",currDateTimeGmt());
print_r($dtAry);
$sqlEp = mysqli_query($conn,"SELECT id,assignTo,orderID,subject,taskMessage,sendBy FROM tasks WHERE dueDate<='".$dtAry[0]."' AND isExpired='0' ");
$numRowsExp = mysqli_num_rows($sqlEp);
if($numRowsExp>1){
while($rowsEp = mysqli_fetch_assoc($sqlEp)){
	
	if($rowsEp['assignTo']!=""){
		//Order Info
		$sqlOrdrInfo = mysqli_query($conn,"SELECT o.id,p.prodName,o.invoiceNo FROM orders as o,products as p WHERE p.id=o.productID AND o.id='".$rowsEp['orderID']."' ");
		$rowsOrdrInfo = mysqli_fetch_assoc($sqlOrdrInfo);
		$assignToAry = @explode(",",$rowsEp['assignTo']);
		
		if(count($assignToAry)>0){
		
			foreach($assignToAry as $assignToVal){ 
			
				$sqlCtBk = mysqli_query($conn,"SELECT companyName,applicantName,mobileNumber,email,isEnableEmail FROM customers WHERE id='".$assignToVal."' ");
				$rowsCtBk = mysqli_fetch_assoc($sqlCtBk);
				
				$sendByAry = @explode(",",$rowsEp['sendBy']);
				
				if(in_array("mail", $sendByAry)){
				
					if($rowsCtBk['isEnableEmail']){
					
						$to = $rowsCtBk['email'];
						$subject = $rowsEp['subject']." ".(($rowsOrdrInfo['invoiceNo']!="")?$rowsOrdrInfo['invoiceNo'].' - ':'')." ".$rowsOrdrInfo['prodName']." - Waqas Chemical";	
						$message = '<table width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#FFFFFF" style="font-family:arial,sans-serif;border:0px solid #000">
						<tbody>
						<p style="line-height:20px;">
						<b>With Reference PI: </b> :- '.(($rowsOrdrInfo['invoiceNo']!="")?$rowsOrdrInfo['invoiceNo'].' - ':'').' '.$rowsOrdrInfo['prodName'].'  <br /><br /><br /><br />
						Dear '.$rowsCtBk['applicantName'].' ('.$rowsCtBk['companyName'].'),<br />
						<b></b> '.$rowsEp['taskMessage'].' <br />
						</p>
						</td>
						</tr>
						<tr>
						<td valign="top">
						<p style="font-size:11px;color:#999999;text-align:center;width:100%;float:left;" >Copyright &copy; '.date('Y').' '.$domainName.' All Rights Reserved.</p>
						</td>
						</tr>
						</tbody>
						</table>';
						$headers = "MIME-Version: 1.0" . "\r\n";
						$headers .= "Content-type:text/html;charset=iso-8859-1" . "\r\n";
						$headers .= 'From: Waqas Chemicals <info@waqaschemicals.com>' . "\r\n";
						$message = appendConfiguredEmailFooter($conn, $message);
						@mail($to,$subject,$message,$headers);	
					}
				}
				
				if(in_array("sms", $sendByAry)){
					if((substr($rowsCtBk['mobileNumber'],0,2)==92)){
						$msg = 'Dear '.$rowsCtBk['applicantName'].' ('.$rowsCtBk['companyName'].'),'."\n".'Reference:- '.$rowsOrdrInfo['prodName'].'/'.$rowsOrdrInfo['invoiceNo'].' Expired'."\n".' '.strip_tags($rowsEp['taskMessage']).' '."\n".' WAQAS CHEMICALS';
						sendSMS($rowsCtBk['mobileNumber'],$msg);	
					}
				}
			}
		}
	}
	mysqli_query($conn,"UPDATE tasks SET isExpired='1' WHERE id='".$rowsEp['id']."'");
}
}
?>
