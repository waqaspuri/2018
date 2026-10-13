<?php
include "include/sessionAdmin.php";  
require 'phpmailer/PHPMailerAutoload.php';
include "include/configure.php";
if(isset($_POST['submit']) && $_POST['submit']=="Submit"){
if(isset($_POST['action']) && $_POST['action']=="add"){
mysqli_query($conn,"INSERT INTO tasks (orderID,currentStatusID,subject,dueDate,priorityID,assignTo,isSecondReminder,taskMessage,sendBy,createdDate) VALUES 
('".mysqli_real_escape_string($conn,$_POST['orderID'])."','".mysqli_real_escape_string($conn,$_POST['currentStatusID'])."','".mysqli_real_escape_string($conn,$_POST['subject'])."','".mysqli_real_escape_string($conn,sqlDtFormat($_POST['dueDate']))."','".mysqli_real_escape_string($conn,$_POST['priorityID'])."','".mysqli_real_escape_string($conn,@implode(",",$_POST['assignTo']))."','".mysqli_real_escape_string($conn,$_POST['isSecondReminder'])."','".mysqli_real_escape_string($conn,$_POST['taskMessage'])."','".mysqli_real_escape_string($conn,@implode(",",$_POST['sendBy']))."','".currDateTimeGmt()."')");
header("location: orders.php?action=view&id=".$_POST['orderID']."&act=1");
}else if(isset($_POST['action']) && $_POST['action']=="update"){
mysqli_query($conn,"UPDATE tasks SET orderID='". mysqli_real_escape_string($conn,$_POST['orderID'])."',currentStatusID='". mysqli_real_escape_string($conn,$_POST['currentStatusID'])."',subject='". mysqli_real_escape_string($conn,$_POST['subject'])."',dueDate='". mysqli_real_escape_string($conn,sqlDtFormat($_POST['dueDate']))."',priorityID='". mysqli_real_escape_string($conn,$_POST['priorityID'])."',assignTo='". mysqli_real_escape_string($conn,@implode(",",$_POST['assignTo']))."',isSecondReminder='". mysqli_real_escape_string($conn,$_POST['isSecondReminder'])."',taskMessage='". mysqli_real_escape_string($conn,$_POST['taskMessage'])."',sendBy='".mysqli_real_escape_string($conn,@implode(",",$_POST['sendBy']))."' WHERE id='".$_POST['id']."'");	
header("location: tasks.php?act=2");
}
}
if(isset($_GET['action']) && $_GET['action']=="mail"){
$sqlEp = mysqli_query($conn,"SELECT id,assignTo,orderID,subject,taskMessage,sendBy FROM tasks WHERE id='".$_GET['id']."'  ");
$numRowsExp = mysqli_num_rows($sqlEp);
if($numRowsExp>0){
while($rowsEp = mysqli_fetch_assoc($sqlEp)){
	
	if($rowsEp['assignTo']!=""){
		//Order Info
		$sqlOrdrInfo = mysqli_query($conn,"SELECT o.id,o.invoiceNo,o.documentOrderNo,p.prodName FROM orders as o,products as p WHERE p.id=o.productID AND o.id='".$rowsEp['orderID']."' ");
		$rowsOrdrInfo = mysqli_fetch_assoc($sqlOrdrInfo);
		$assignToAry = @explode(",",$rowsEp['assignTo']);
		
		if(count($assignToAry)>0){
		
			foreach($assignToAry as $assignToVal){ 
			
				$sqlCtBk = mysqli_query($conn,"SELECT companyName,applicantName,mobileNumber,email,email2,isEnableEmail FROM customers WHERE id='".$assignToVal."' ");
				$rowsCtBk = mysqli_fetch_assoc($sqlCtBk);
				
				$sendByAry = @explode(",",$rowsEp['sendBy']);
				
			 	
						$to = $rowsCtBk['email'];
						$to2 = $rowsCtBk['email2'];
						$subject = $rowsEp['subject']. " / ".$rowsOrdrInfo['prodName']."".(($rowsOrdrInfo['documentOrderNo']!="")? '('.$rowsOrdrInfo['documentOrderNo'].')':'')."".(($rowsOrdrInfo['invoiceNo']!="")?' / '.$rowsOrdrInfo['invoiceNo']:'')." ";	
						
						$message = '
					
						<table>
						<tbody>
						Dear '.$rowsCtBk['applicantName'].'<br />
						<p>'.trim($rowsEp['taskMessage']).'</p>
					
						
						</td>
						</tr>
						<tr>
						<td valign="top">
						
						</td>
						</tr>
						</tbody>
						</table>
		
						



						</html>';
						$headers = "MIME-Version: 1.0" . "\r\n";
						$headers .= "Content-type:text/html;charset=iso-8859-1" . "\r\n";
						$headers .= 'From: Waqas Chemicals <info@waqaschemicals.com>' . "\r\n";

							$message = preg_replace('/[^(\x20-\x7F)\x0A\x0D]*/','', $message);
							$mail = new PHPMailer;

							$mail->setFrom('info@waqaschemicals.com', 'WAQAS CHEMICALS');
							$mail->AddAddress($to,$rowsCtBk['applicantName']);
							$mail->AddCC($to2,$rowsCtBk['applicantName']);
							$mail->IsHTML(true);  
							$mail->AddBCC('waqaspuri@gmail.com', 'Waqas Saeed Puri');

							$mail->Subject  = $subject;
							$mail->Body     = $message;

							$mail->Body = appendConfiguredEmailFooter($conn, $mail->Body);
							$mail->send();


						//@mail($to,$subject,$message,$headers);	
				
			}
		}
	}
	}
}

header("location: tasks.php?action=view&id=".$_GET['id']."&act=1");
}

if(isset($_GET['action']) && $_GET['action']=="SMS"){
	//Notification here
$sqlEp = mysqli_query($conn,"SELECT id,assignTo,orderID,subject,taskMessage,sendBy FROM tasks WHERE id='".$_GET['id']."'  ");
$numRowsExp = mysqli_num_rows($sqlEp);
if($numRowsExp>0){
while($rowsEp = mysqli_fetch_assoc($sqlEp)){
	
	if($rowsEp['assignTo']!=""){
		//Order Info
		$sqlOrdrInfo = mysqli_query($conn,"SELECT o.id,o.invoiceNo,o.documentOrderNo,p.prodName FROM orders as o,products as p WHERE p.id=o.productID AND o.id='".$rowsEp['orderID']."' ");
		$rowsOrdrInfo = mysqli_fetch_assoc($sqlOrdrInfo);
		$assignToAry = @explode(",",$rowsEp['assignTo']);
		if(count($assignToAry)>0){
			foreach($assignToAry as $assignToVal){ 
				$sqlCtBk = mysqli_query($conn,"SELECT applicantName,companyName,mobileNumber,email,isEnableEmail FROM customers WHERE id='".$assignToVal."' ");
				$rowsCtBk = mysqli_fetch_assoc($sqlCtBk);
				$sendByAry = @explode(",",$rowsEp['sendBy']);
					if($rowsCtBk['mobileNumber']){
					$msg = 'Dear '.$rowsCtBk['applicantName']."\r\n"."\r\n".''.$rowsOrdrInfo['prodName']."\r\n"."\r\n".''.(($rowsOrdrInfo['documentOrderNo']!="")? '('.$rowsOrdrInfo['documentOrderNo'].')':$rowsOrdrInfo['invoiceNo'])."\r\n"."\r\n".''.strip_tags($rowsEp['taskMessage']).' '."\r\n"."\r\n".'WAQAS CHEMICALS'."\n".'03008207530'; 
					//$msg = 'Dear '.$rowsCtBk['applicantName'].' ('.$rowsCtBk['companyName'].'),'."\n".'Reference:- '.$rowsOrdrInfo['prodName'].'/'.$rowsOrdrInfo['invoiceNo']."\r\n".' '.strip_tags($rowsEp['taskMessage']).' '."\r\n".' WAQAS CHEMICALS';
					
					
					
					sendSMS($rowsCtBk['mobileNumber'],$msg);	
				}
				
			}
		}
	}
	}
}
header("location: tasks.php?action=view&id=".$_GET['id']."&act=2");

}

if(isset($_GET['act']) && $_GET['act']=="delete"){
mysqli_query($conn,"DELETE FROM tasks WHERE id='".$_GET['id']."'");	
header("location: tasks.php?act=3");
}
$action = "add";
$hdTxt="Add New";
if(isset($_GET['action']) && $_GET['action']=="edit"){
$sqlEdit = mysqli_query($conn,"SELECT * FROM tasks WHERE id='".$_GET['id']."'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
extract($rowsEdit);
$action = "update";
$hdTxt="Update";
} 
include "include/header.php"; 
if(isset($_GET['action']) && $_GET['action']=="view"){ 
$sqlEdit = mysqli_query($conn,"SELECT * FROM tasks WHERE id='".$_GET['id']."'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
extract($rowsEdit);

$sqlOrder = mysqli_query($conn,"SELECT statusID FROM orders WHERE id='".$rowsEdit['orderID']."'");
$rowsOrdr = mysqli_fetch_assoc($sqlOrder);
?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Tasks</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>tasks.php">Manage Tasks</a></span></li>
<li><span>View Task</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act']) && $_GET['act']=='1'){
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>Email has been sent successfully</strong></div>';
}else if(isset($_GET['act']) && $_GET['act']=='2'){
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>SMS has been sent successfully</strong></div>';
}
?>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>tasks.php?action=edit&id=<?=$rowsEdit['id'];?>"><b>Edit</b></a> | <a href="<?=$siteUrl;?>tasks.php"><b>Back</b></a> </div>
<h2 class="panel-title">View Tasks</h2>
</header>
<div class="panel-body viewFrm">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Order *</label>
<div class="col-md-9">
<?php
$sqlOrdPrd = mysqli_query($conn,"SELECT o.id,o.vendorID,o.customerID,p.prodName FROM orders as o,products as p WHERE p.id=o.productID and o.id='".$orderID."'");
$rowsOP = mysqli_fetch_assoc($sqlOrdPrd);
echo $rowsOP['prodName'].' (#'.$rowsOP['id'].')<br /><b>Vendor -</b> '.companyName($rowsOP['vendorID']).'<br /><b>Customer -</b> '.companyName($rowsOP['customerID']);					
?>
</div></div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Current Status *</label>
<div class="col-md-9 selectSrch"><?=statusNm($rowsOrdr['statusID']);?>
</div></div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Subject</label>
<div class="col-md-9"><?=$subject?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Due Date</label>
<div class="col-md-9"><?=($dueDate!="")?viewDateFormat($dueDate):''?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Priority *</label>
<div class="col-md-9 selectSrch"><?=priorityNm($priorityID);?></div></div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Assign To *</label>
<div class="col-md-9"><?php
$assignToAry = @explode(",",$assignTo);
$asignToStr = '';
foreach($assignToAry as $assignToVal){ 
$asignToStr[] = customerName($assignToVal); 
}
echo @implode(", ",$asignToStr);
?>
</div></div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Repeat</label>
<div class="col-md-8"><div class="paddingTxt">
<input type="checkbox" name="isSecondReminder" value="1" <?=($isSecondReminder==1)?'checked="checked"':'';?> id="inputDefault"  > If Yes, second reminder will active.</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Message</label>
<div class="col-md-9"><?=$taskMessage?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Send By</label>
<div class="col-md-9">
<div class="paddingTxt"><?php $sendByAry = explode(",",$sendBy); ?>
<input type="checkbox" name="sendBy[]" value="mail" <?=(in_array('mail',$sendByAry)?'checked="checked"':'');?> > Mail &nbsp; 
<input type="checkbox" name="sendBy[]" value="sms" <?=(in_array('sms',$sendByAry)?'checked="checked"':'');?> > SMS
&nbsp;   &nbsp; 
<a onclick="return confirm('Are you sure you want send Email?');" class="btn btn-default" href="<?=$siteUrl;?>tasks.php?action=mail&id=<?=$id?>" title="Send Email Notification"><b>Mail</b></a>  &nbsp; 
<a title="Send SMS Notification" onclick="return confirm('Are you sure you want send SMS?');" class="btn btn-default" href="<?=$siteUrl;?>tasks.php?action=SMS&id=<?=$id?>"><b>SMS</b></a>  

</div>



</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<button type="button" onclick="window.location.href='<?=$siteUrl;?>tasks.php'" class="btn btn-default">Back</button>
</div>
</div> 
</form>
</div>
</section>
</div>
</div>
</section>
</div>
</section>
<?php
}else if(isset($_GET['action']) && $_GET['action']=="add" || $_GET['action']=="edit"){ 
?>
<style type="text/css">.aa{color:#3C0 !important;}</style>
<script type="text/javascript">
$(document).ready(function(){ 
$("#orderID").change(function(){ $.ajax({ url:"<?=$siteUrl;?>assignAjax.php?act=urs&id=" + $(this).val(),type: "GET",success: function(data){ var x = data;var optn=x.split(',~,'); var bb=''; bb+="<option value=''>- Select -</option>"; for(i=0;i<=optn.length-2;i++) { var optn2 = optn[i]; var hh2=optn2.split('||'); var hh=hh2[1].split('::'); bb+="<option "+hh2[0]+" value='"+hh[0]+"'>"+hh[1]+"</option>"; } $('#assignTo').html(bb); } }); });
}); 
</script>
<script src="<?=$siteUrl?>assets/vendor/summernote/summernote.js"></script>
<link rel="stylesheet" href="<?=$siteUrl?>assets/vendor/summernote/summernote.css" />
<link rel="stylesheet" href="<?=$siteUrl?>assets/vendor/summernote/summernote-bs3.css" /> 
<link rel="stylesheet" href="<?=$siteUrl?>assets/bootstrap-select.css">
<script src="<?=$siteUrl?>assets/bootstrap-select.js"></script>
<script type="text/javascript">
$(document).ready(function(){
$(".dueDate").datepicker({todayHighlight:true,autoclose:true});
});
</script> 
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Tasks</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>tasks.php">Manage Tasks</a></span></li>
<li><span><?=$hdTxt;?> Task</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>tasks.php"><b>Back</b></a> </div>
<h2 class="panel-title"><?=$hdTxt;?> Tasks</h2>
</header>
<div class="panel-body">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Order *</label>
<div class="col-md-9 selectSrch">
<select class="selectpicker form-control" data-selected-text-format="values" name="orderID" id="orderID" data-live-search="true" data-live-search-placeholder="Search" required>
<option value="">- Select -</option>
<?php
$sqlOrdPrd = mysqli_query($conn,"SELECT o.id,p.prodName FROM orders as o,products as p WHERE p.id=o.productID ORDER BY p.prodName ASC");
while($rowsOP = mysqli_fetch_assoc($sqlOrdPrd)){						
echo '<option value="'.$rowsOP['id'].'" '.((($rowsOP['id']==$orderID) || ($rowsOP['id']==$_GET['orderID']))?'selected="selected"':'').' >'.$rowsOP['prodName'].' (#'.$rowsOP['id'].')</option>';
}
?>
</select>
</div></div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Subject</label>
<div class="col-md-9">
<input type="text" class="form-control" name="subject" value="<?=$subject?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Due Date</label>
<div class="col-md-9">
<input type="text" class="form-control dueDate" readonly="readonly" data-date-format="dd-mm-yyyy" name="dueDate" value="<?=($dueDate!="")?viewDateFormat($dueDate):''?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Priority *</label>
<div class="col-md-9 selectSrch">
<select class="form-control" name="priorityID" >
<option>- Select -</option>
<?php
$sqlPrty = mysqli_query($conn,"SELECT id,priorityNm FROM task_priority ORDER BY priorityNm ASC");
while($rowsPrty = mysqli_fetch_assoc($sqlPrty)){						
echo '<option value="'.$rowsPrty['id'].'" '.(($rowsPrty['id']==$priorityID)?'selected="selected"':'').' >'.$rowsPrty['priorityNm'].'</option>';
}
?>
</select>
</div></div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Assign To * </label>
<div class="col-md-9 selectSrch">
<select class="form-control" name="assignTo[]" id="assignTo" multiple >
<option>- Select -</option>
<?php
if($action == "update"){
	$assignToAry = @explode(",",$assignTo);
	
	$sqlOrdr = mysqli_query($conn,"SELECT customerID,vendorID FROM orders where id='".$orderID."'");
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
		
	$sqlOrderCustmr = mysqli_query($conn,"SELECT id,companyName,type FROM customers where (isOtherCustomer='1' OR id in (".@implode(",",$customerAry).")) ORDER BY type,applicantName ASC");
	$numRowOC = mysqli_num_rows($sqlOrderCustmr);
	if($numRowOC>0){
		while($rowOC = mysqli_fetch_assoc($sqlOrderCustmr)){
			echo '<option value="'.$rowOC['id'].'" '.((in_array($rowOC['id'],$assignToAry)?'selected="selected"':'')).' >'.$rowOC['companyName'].' ('.typeNm($rowOC['type']).')</option>';
		}
	}
}else{
		
	$sqlOrdr = mysqli_query($conn,"SELECT customerID,vendorID FROM orders where id='".$_GET['orderID']."'");
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
	
	$sqlOrderCustmr = mysqli_query($conn,"SELECT id,companyName,type FROM customers where (isOtherCustomer='1' OR id in (".@implode(",",$customerAry).")) ORDER BY type,applicantName ASC");
	$numRowOC = mysqli_num_rows($sqlOrderCustmr);
	if($numRowOC>0){
		while($rowOC = mysqli_fetch_assoc($sqlOrderCustmr)){
			echo '<option value="'.$rowOC['id'].'" '.((in_array($rowOC['id'],$assignToAry)?'selected="selected"':'')).' >'.$rowOC['companyName'].' ('.typeNm($rowOC['type']).')</option>';
		}
	}
}
?>
</select>
</div></div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Repeat</label>
<div class="col-md-8"><div class="paddingTxt">
<input type="checkbox" name="isSecondReminder" value="1" <?=($isSecondReminder==1)?'checked="checked"':'';?> id="inputDefault"  > If Yes, second reminder will active.</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Message</label>
<div class="col-md-9">
<textarea class="summernote" id="taskMessage" name="taskMessage" data-plugin-summernote data-plugin-options='{ "height": 200, "codemirror": { "theme": "ambiance" } }'><?php echo htmlspecialchars($taskMessage); ?></textarea>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Send By</label>
<div class="col-md-9">
<div class="paddingTxt"><?php $sendByAry = explode(",",$sendBy); ?>
<input type="checkbox" name="sendBy[]" value="mail" <?=(in_array('mail',$sendByAry)?'checked="checked"':'');?> > Mail &nbsp; 
<input type="checkbox" name="sendBy[]" value="sms" <?=(in_array('sms',$sendByAry)?'checked="checked"':'');?> > SMS</div>
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<input type="hidden" name="action" value="<?=$action;?>" />
<input type="hidden" name="id" value="<?=$id;?>" />
<button class="btn btn-primary" name="submit" value="Submit">Submit</button>
<button type="button" onclick="window.location.href='<?=$siteUrl;?>tasks.php'" class="btn btn-default">Cancel</button>
</div>
</div> 
</form>
</div>
</section>
</div>
</div>
</section>
</div>
</section>
<?php }else{  ?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Task</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrlMain;?>cronJobBtn.php" onclick="return confirm(\'Are you sure you want to RUN CRON JOB NOW?\');" > <i class="fa fa-refresh"></i> <b>Run Cron Now</b>  </a> </li>
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span>Manage Task</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act'])){
if($_GET['act']=="1"){
$msgTxt='New Task has been added successfully';
}else if($_GET['act']=="2"){
$msgTxt='Task has been updated successfully';
}else if($_GET['act']=="3"){
$msgTxt='Task has been deleted successfully';
}else if($_GET['act']=="4"){
$msgTxt='<b>Success Message</b> Cron Job has been run successfully';
}		
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.$msgTxt.'</strong></div>';
}
$dtAry = explode(" ",currDateTimeGmt());
?>
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>tasks.php?action=add"><b>Add New</b></a> </div>
<h2 class="panel-title">Manage Task</h2>
</header>
<div class="panel-body">
<div class="form-group">
<div class="col-md-6">
<form class="form-horizontal form-bordered" method="get">
<input type="text" style="width:80%;float:left" class="form-control" name="q" value="<?=(isset($_GET['q'])?$_GET['q']:'')?>" placeholder="Search" id="inputDefault"  >
<input type="hidden" name="so" value="<?=$_GET['so']?>" />
<button class="btn btn-primary" name="submit" value="Submit">GO</button>
</form> <span class="hintTxt">Search :-  Subject.</span>
</div>
<div class="col-md-6" align="right">
<a href="<?=$siteUrl;?>tasks.php?so=pc" class="btn btn-default blue">Past Cron Job (<?=cronJobTotal($dtAry[0],'pc');?>)</a>            
<a href="<?=$siteUrl;?>tasks.php?so=fc" class="btn btn-default green">Future Cron Job (<?=cronJobTotal($dtAry[0],'fc');?>)</a>
</div>
</div>	
<div class="table-responsive">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="15%">Subject</th>
<th width="10%">Due Date</th>
<th width="10%">Priority</th>
<th width="20%">Assign To</th>
<th width="18%">Created On</th>
<th width="22%" class="text-center">Action</th>
</tr>
</thead>
<tbody>
<?php
$page_name="tasks.php?so=".$_GET['so']."&q=".$_GET['q']; 
if(!isset($_REQUEST["start"])) {
$start = 0;
}
else
$start = $_REQUEST["start"];

$eu = ($start - 0); 
$limit = 20;          
$this1 = $eu + $limit; 
$back = $eu - $limit; 
$next = $eu + $limit; 
$sql = mysqli_query($conn,"limit $eu, $limit");
$sqlSrch = "";
if(isset($_GET['q']) && $_GET['q']!=""){
$sqlSrch = " and (subject LIKE '%".$_GET['q']."%') ";
}

$sqlSort = "";

if(isset($_GET['so']) && $_GET['so']!=""){
	if($_GET['so']=="fc"){
		$sqlSort = " and dueDate>='".$dtAry[0]."' ";
	}else if($_GET['so']=="pc"){
		$sqlSort = " and dueDate<'".$dtAry[0]."' ";
	}
}

$sqlSeller = "SELECT id,subject,dueDate,priorityID,assignTo,createdDate,isSecondReminder FROM tasks where createdDate<>'' ".$sqlSort.$sqlSrch." ORDER BY id DESC limit $eu, $limit";
$sqltot = "SELECT id FROM tasks where createdDate<>'' ".$sqlSort.$sqlSrch." ORDER BY id DESC ";

$resultSeller= mysqli_query($conn,$sqlSeller);
$resulttot=mysqli_query($conn,$sqltot);
$nume=mysqli_num_rows($resulttot);
if (@mysqli_num_rows($resultSeller)!=0){

$sno=$start;
while($rows=mysqli_fetch_assoc($resultSeller)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.stringTxtLimit($rows['subject'],50).''.(($rows['isSecondReminder'])?'<br /><span class="btn-danger reptBtn">Repeat</span>':'').'</td>
<td>'.viewDateFormat($rows['dueDate']).'</td>
<td>'.priorityNm($rows['priorityID']).'</td>
<td>';
$assignToAry = @explode(",",$rows['assignTo']);
$asignToStr = '';
foreach($assignToAry as $assignToVal){ 
$asignToStr[] = customerName($assignToVal); 
}
echo @implode(", ",$asignToStr);
echo '</td>
<td align="center">'.viewDtTm($rows['createdDate']).'</td>
<td align="center">
<a title="View this record" class="btn btn-default viewBtn" href="tasks.php?action=view&id='.$rows['id'].'">View</a>
<a title="Edit this record" class="btn btn-default editBtn" href="tasks.php?action=edit&id='.$rows['id'].'">Edit</a>
<a title="Delete this record" class="btn btn-default delBtn" onclick="return confirm(\'Are you sure you want to delete?\');" href="tasks.php?act=delete&id='.$rows['id'].'">Delete</a>
</td>
</tr>';
}
?>
<tr>
<td colspan="7">
<?php
echo "<table align = 'center' width='100%'><tr><td  align='left' width='30%'>";
if($back >=0) { 
print "<a href='$page_name&start=$back'><font face='Verdana' size='2'>PREV</font></a>"; 
} 
echo "</td><td align=center width='30%'>Page:";
$i=0;
$l=1;
$total=0;
for($i=0;$i < $nume;$i=$i+$limit){
if($i <> $eu){
echo " <a href='$page_name&start=$i'><font face='Verdana' size='2'>$l</font></a> ";
}
else { echo "<font face='Verdana' size='2' color=red>$l</font>";}        
$l=$l+1;
$total = $total+1;
}
echo " of $total</td><td  align='right' width='30%'>";
if($this1 < $nume) { 
print "<a href='$page_name&start=$next'><font face='Verdana' size='2'>NEXT</font></a>";} 
echo "</td></tr></table>";
?>
</td>
</tr><?php
}else{
echo "<tr>
<td colspan='7' align='center'>No Data Found.</td>
</tr>";
} ?>

</tbody>
</table>
</div>
</div>
</section>
</section>
</div>
</section>
<?php 
}
include "include/footer.php"; ?>
