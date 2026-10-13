<?php
require_once __DIR__ . "/php/session-bootstrap.php";
include "php/connect-mysql.php";
include "php/session.php";
include "php/functions.php";
if(isset($_GET['act']) && $_GET['act']=="pdfDwnld"){
ini_set("memory_limit","120M");
ini_set('max_execution_time', 300);
require 'vendor/autoload.php';
$fileNamePDF = "invoice.pdf";
$sqlInv = mysqli_query($conn,"SELECT * FROM invoices WHERE id='".$_GET['id']."'");
$rowsInv = mysqli_fetch_assoc($sqlInv);
$mergedItems = [];
$mergedLines = preg_split("/\r\n|\n|\r/", (string)$rowsInv['prodDescription']);
foreach ($mergedLines as $line) {
	$line = trim($line);
	if ($line === '' || strpos($line, '||') === false) {
		continue;
	}
	$parts = array_map('trim', explode('||', $line));
	if (count($parts) < 5) {
		continue;
	}
	$mergedItems[] = $parts;
}
$sqlAdm = mysqli_query($conn,"SELECT * FROM admin WHERE id='1'");
$rowsAdm = mysqli_fetch_assoc($sqlAdm);
$mergedFromText = trim((string)$rowsInv['merged_from']);
$noteText = (string)$rowsInv['invoiceNote'];
$logoPath = __DIR__ . '/wAdmin/assets/images/logo.png';
$logoSrc = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
$pdfCnt = "";
$pdfCnt.='<div class="visitReportTb"><table border="0" style="width:100%;font-family:Helvetica;padding:0;margin:0;font-size:14px;" cellpadding="0" cellspacing="0">
<tr>
<td align="left" width="70%"><img src="'.$logoSrc.'" alt="Waqas Chemicals" style="width:100px; height:auto;" /></td>
<td align="right" width="30%" class="invoiceNo"><div class="debitNote"><h1>Invoice</h1><span>'.strtoupper($rowsInv['invoiceNo']).'</span></div></td>
</tr>
<tr>
<td align="center" colspan="2" class="brdrTop">
<table cellpadding="4" cellspacing="0" style="padding:0;margin:14px 0 0 0" border="0" width="100%;">
<tr><td width="60%" class="address"><b>WAQAS CHEMICALS</b><br />Address :- '.$rowsAdm['address'].'<br />Phone :- '.$rowsAdm['mobileNumber'].'</td>
<td width="40%">
<table cellpadding="4" cellspacing="0" style="padding:0;margin:14px 0 0 0;font-size:11px;line-height:14px;" border="0" width="100%;" class="invoiceMeta">
<tr><td>Date</td><td>'.date('F d, Y', strtotime($rowsInv['invoiceDate'])).'</td></tr>
<tr><td>Payment Terms</td><td>'.$rowsInv['paymentTerms'].'</td></tr>
<tr><td>Due Date</td><td>'.date('F d, Y', strtotime($rowsInv['dueDate'])).'</td></tr>
</table>
</td></tr>
</br></br>
<tr><td colspan="2" class="address billToBlock">
<div class="billToTitle">Bill To</div>
<div class="billToCompany"><b>'.$rowsInv['companyName'].'</b></div>
<div class="billToLine">'.$rowsInv['applicantName'].'</div>
<div class="billToLine">Address :- '.$rowsInv['address'].',</div>
<div class="billToLine">'.$rowsInv['city'].',</div>
<div class="billToLine">'.$rowsInv['country'].'</div>
<div class="billToLine">Phone :- '.$rowsInv['mobileNumber'].'</div>
</td></tr>
<tr><td colspan="2" style="height:18px;line-height:18px;font-size:1px;">&nbsp;</td></tr>
</td>
</tr>
</table>
<div class="respInfo">
<div class="col-md-12">
		<table style="width:100%;font-family:Helvetica;padding:0px;margin:0;font-size:12px;" border="0" cellpadding="0" cellspacing="0" width="100%" class="weekDaysLst" >
	<tr>
	<th align="left" width="55%">Item</th>
	<th align="left" width="15%">Quantity</th>
	<th align="left" width="15%">Per Unit</th>
	<th align="center" width="15%">Amount</th>
	</tr>
';
if (!empty($mergedItems)) {
	foreach ($mergedItems as $mergedItem) {
		$itemTitle = trim((string)$mergedItem[0]);
		$itemBody = htmlspecialchars($mergedItem[1], ENT_QUOTES, 'UTF-8');
		if ($itemTitle !== '') {
			$itemBody = '<b> - '.htmlspecialchars($itemTitle, ENT_QUOTES, 'UTF-8').'</b><br />'.$itemBody;
		}
		$pdfCnt .= '<tr class="brdrBtmTb">
				<td>'.$itemBody.'</td>
			<td>'.htmlspecialchars($mergedItem[2], ENT_QUOTES, 'UTF-8').'</td>
			<td>$'.htmlspecialchars($mergedItem[3], ENT_QUOTES, 'UTF-8').'</td>
			<td>$'.htmlspecialchars($mergedItem[4], ENT_QUOTES, 'UTF-8').'</td>
			</tr>';
	}
	} else {
		$pdfCnt .= '
		<tr class="brdrBtmTb">
		<td>ProInv : '.htmlspecialchars($rowsInv['proformaNo'], ENT_QUOTES, 'UTF-8').'<br />'.htmlspecialchars($rowsInv['prodDescription'], ENT_QUOTES, 'UTF-8').'</td>
		<td>'.$rowsInv['prodQty'].'</td>
		<td>$'.priceFormat($rowsInv['unitPrice']).'</td>
		<td>$'.priceFormat($rowsInv['totalAmt']).'</td>
		</tr>';
	}
$pdfCnt .= '
	<tr>
	<td colspan="3">Total</td>
	<td>$'.priceFormat($rowsInv['totalAmt']).'</td>
	</tr>
<tr>
	<td colspan="4" style="height:24px;line-height:24px;font-size:1px;">&nbsp;</td>
</tr>
<tr>
		<td colspan="4" class="address bankBlock">
		<b>Account Details for Remittance to Pakistan:</b><br />
		<b>BANK NAME:</b> HABIB METROPOLITAN BANK LIMITED<br />	
		<b>ACCOUNT TITLE:</b> WAQAS CHEMICALS<br />
		<b>ACCOUNT IBAN:</b> PK24 MPBL 0163 0271 4012 8951<br />		
		<b>BRANCH SWIFT CODE:</b> MPBLPKKA063<br />
		<b>BRANCH NAME:</b> MARRIOT ROAD, BR, KARACHI<br />
		<br />'.
	($mergedFromText !== '' ? '<b>Merged from:</b> '.htmlspecialchars($mergedFromText, ENT_QUOTES, 'UTF-8').'<br /><br />' : '').
		'<b>Notes</b><br /><br />'.htmlspecialchars($noteText, ENT_QUOTES, 'UTF-8').'</td>
	</tr>

</table>
</div></div></div>';
	
$html='<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<style type="text/css">
.pdfContainer{font-family:Arial;font-size:12px;padding-bottom:55px;}
.hding{font-size:13px;font-weight:bold;margin:10px 0 8px 0;}
.hding2{font-size:11px;font-weight:bold;margin:8px 0 0 8px;}
.rspTxt{font-size:10px;color:#333;margin-left:20px;}
.address{line-height:14px;font-size:10px;}
.bankBlock{font-size:9px;line-height:13px;}
.bankBlock b{font-weight:bold;}
.billToBlock{line-height:12px !important;font-size:10px;}
.billToBlock .billToTitle,.billToBlock .billToCompany,.billToBlock .billToLine{margin:0;padding:0;line-height:12px;}
.invoiceMeta td{font-size:11px;line-height:14px;padding:2px 4px;}
.debitNote h1{float:left;}
.debitNote{border:solid 0px #000;text-align:left;}
.debitNote span{float:left;margin-top:-10px;}
.rspType5 .col-md-10{float:left;width:100%;}
.rspType5 .col-md-1{float:left;width:5%;}
.rspType5 .col-md-3{float:left;width:20%;}
.rspType5 .col-md-3 checkbox{float:left;width:20px;}
.respInfo b{color:#000;font-weight:bold;}
.respInfo .col-md-10{margin:2px 0 2px 0}
.respInfo .col-md-10 .rspImg{vertical-align:middle;}
.respInfo table{margin:6px 0 0 0;}
.respInfo table tr th{text-align:left;font-weight:bold;background:#818181;color:#fff;}
.respInfo table tr td{text-align:left;}
.respInfo table tr td,.respInfo table tr th{padding:3px 5px;font-size:10px;line-height:11px;}
.respInfo .col-md-3{float:left;width:100%}
.respInfo .col-md-12{float:left;width:100%}
.visitReportTb{margin:0 0 8px 0;float:left;width:100%;}
.visitReportTb table tr th{text-align:left;font-weight:bold;background:#000;color:#fff;}
.weekDaysLst tr td{padding:5px 5px;}
.brdrBtmTb td{border-bottom:solid 1px #999999;}
.brdrTop{border-top:solid 0px #999999;}
.invoiceNo h1{line-height:10px;font-size:40px;height:0;}
.footerInfo{position:fixed;left:0;right:0;bottom:14px;text-align:center !important;line-height:16px;font-size:10px;}
</style></head><body><div class="pdfContainer">';
$html.=$pdfCnt;
$html.='<div class="footerInfo">if you have any questions about this invoice, please contact info@waqaschemcicals.com<br /><b>Thank You For Your Business!</b></div>';
$html.='</div></body></html>';
$old_limit = ini_set("memory_limit", "30M"); 
$dompdf = new Dompdf\Dompdf();
$dompdf->setBasePath(__DIR__ . '/');
$dompdf->load_html($html,'UTF-8');
$dompdf->set_paper('a4','portrait');// or landscape
$dompdf->render();
$dompdf->stream($fileNamePDF);
downloadFile($fileNamePDF,'timesheet.pdf');
}
?>
