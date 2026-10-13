<?php
include "php/connect-mysql.php";
include "wAdmin/include/sessionAdmin.php";
include "php/functions.php";
if(isset($_GET['act']) && $_GET['act']=="pdfDwnld"){
ini_set("memory_limit","120M");
ini_set('max_execution_time', 300);
require_once("pdf/dompdf_config.inc.php");
$sqlInv = mysqli_query($conn,"SELECT * FROM pos_sale_billing_draft WHERE id='".$_GET['id']."'");
$rowsInv = mysqli_fetch_assoc($sqlInv);
$sqlAdm = mysqli_query($conn,"SELECT * FROM pos_settings WHERE id='1'");
$rowsAdm = mysqli_fetch_assoc($sqlAdm);
$fileNamePDF = "invoice-".$rowsInv['invoiceNo'].".pdf";
$pdfCnt = "";
$pdfCnt.='<div class="visitReportTb"><table border="0" style="width:100%;font-family:Helvetica;padding:0;margin:0;font-size:14px;" cellpadding="0" cellspacing="0">
<tr>
<td colspan="2" align="center"><h1>SALE TAX INVOICE<br /><span style="padding:0;margin:0px 0 0 0;font-size:11px;">DRAFT INVOICE, NOT VALID</span></h1></td>
</tr>
<tr>
<td colspan="2" class="topHdr">
<table cellpadding="6" cellspacing="0" style="padding:0;margin:0px 0 0 0;font-size:13px;" border="0" width="100%;">
<tr>
<td width="30%">Invoice No : <b>#'.$rowsInv['invoiceNo'].'</b></td>
<td width="30%">Dated: <b> '.date('d-m-Y', strtotime($rowsInv['invoiceDate'])).'</b></td>
<td width="40%">Ref: <b> '.$rowsInv['invoiceRef'].'</b></td>
</tr>
</table>
</td>
</tr>
<tr>
<td align="left" colspan="2" class="addressL">
<table cellpadding="5" cellspacing="0" style="padding:0;margin:14px 0 0 0" border="0" width="100%;">
<tr><td width="20%">Supplier&lsquo;s Name</td><td width="30%"><b>'.$rowsAdm['supperName'].'</b></td> 
<td width="20%">Buyer&lsquo;s Name</td><td width="30%"><b>'.$rowsInv['name'].'</b></td> 
</tr>
<tr><td>Address</td><td ><b>'.$rowsAdm['address'].'</b></td><td>Address</td><td ><b>'.$rowsInv['address'].'</b></td> </tr>
<tr><td>Tel/Fax#</td><td ><b>'.$rowsAdm['telephone'].'</b></td><td>Telephone</td><td ><b>'.$rowsInv['telephone'].'</b></td> </tr>
<tr><td>GST#</td><td><b>'.$rowsAdm['gst'].'</b></td><td>GST#</td><td ><b>'.$rowsInv['gst'].'</b></td>  </tr>
<tr><td>NTN#</td><td><b>'.$rowsAdm['ntn'].'</b></td><td>NTN#</td><td ><b>'.$rowsInv['ntn'].'</b></td> </tr>
</table><br />
</td>
</tr>
<tr>
<td colspan="2" class="topHdr">
<table cellpadding="10" cellspacing="0" style="padding:0;margin:0 0 0 0;font-size:13px;" border="0" width="100%;">
<tr>
<td width="22%" class="txtRight">Terms of sale: <b>'.$rowsInv['termSale'].'</b></td>
</tr>
</table>
</td>
</tr>
<tr><td colspan="2"><br /></td></tr>
</table>
<div class="respInfo">
<div class="col-md-12">
<table  style="width:100%;font-family:Helvetica;padding:0px;margin:0;font-size:14px;" border="0" cellpadding="4" cellspacing="0" width="98%" class="weekDaysLst itmTbl" >
<tr>
<th width="8%">Qty</th>
<th width="30%">Description of goods</th>
<th width="12%">Unit<br />Price</th>
<th width="12%">Value of<br/>Goods</th>
<th width="11%">Rate of<br/>Sale Tax</th>
<th width="12%">Amount of<br/> Sale Tax</th>
<th width="15%">Total</th>
</tr>
<tr class="itemNos">
<td>1</td>
<td>2</td>
<td>3</td>
<td>4</td>
<td>5</td>
<td>6</td>
<td>7</td>
</tr>';
$sqlPrd = mysqli_query($conn,"SELECT * FROM pos_sale_order_products_draft WHERE posSaleBillingID='".$rowsInv['id']."' ");
$numRowPrd = mysqli_num_rows($sqlPrd);
if($numRowPrd>0){
$sno=0;
$ttQty  = $pttAmt = $ptaxAmt =  0;
while($rows = mysqli_fetch_assoc($sqlPrd)){
$sno++;
$prodNm = explode("(H.S Code:",$rows['productName']);
$tAmt = $rows['valueOfGood'];//($rows['productQty']*$rows['productPrice']); 
$taxAmt = priceFormat(($rows['prodTax']/100) * $tAmt);
$pdfCnt.='<tr class="invPrd">
<td align="left">'.$rows['productQty'].' '.$rows['prodUnit'].'</td>
<td align="left">'.$prodNm[0].' (H.S Code: '.substr($prodNm[1],0,5).')</td>
<td align="right"> &nbsp;</td>
<td align="right">'.priceFormatRound($tAmt).'&nbsp; </td>
<td align="right">'.priceFormatRound($rows['prodTax']).'%&nbsp; </td>
<td align="right">'.priceFormatRound($taxAmt).'&nbsp; </td>
<td align="right">'.priceFormatRound($taxAmt+$tAmt).'&nbsp; </td>
</tr>';
$totalAmt+=($tAmt+$taxAmt);
$ttQty+=$rows['productQty'];
$pttAmt+=$tAmt;
$ptaxAmt+=$taxAmt;
}
}
for($i=0;$i<(9-$numRowPrd);$i++){ 
$pdfCnt.='<tr class="invPrd">
<td>&nbsp;</td>
<td>&nbsp;</td>
<td>&nbsp;</td>
<td>&nbsp;</td>
<td>&nbsp;</td>
<td>&nbsp;</td>
<td>&nbsp;</td>
</tr>';
}
$pdfCnt.='<tr class="invPrd">
<td>&nbsp;</td>
<td><b>Total</b></td>
<td>&nbsp;</td>
<td><b>'.priceFormatRound($pttAmt).'</b></td>
<td>&nbsp;</td>
<td><b>'.priceFormatRound($ptaxAmt).'</b></td>
<td><b>'.priceFormatRound($totalAmt).'</b></td>
</tr>
<tr>
</table>
<hr class="ftBtm" />
<table cellpadding="4" cellspacing="0" style="padding:0;margin:14px 0 0 0;font-size:13px;" border="0" width="100%;">
<tr>
<td class="footerInvL">'.$rowsInv['saleNote'].'<br /> &nbsp;<br /> &nbsp; <br /> &nbsp;<br /> &nbsp;<br /> &nbsp; <br /> Signature:- _______________</td>
</tr>
</table>
</div></div></div>';
$html='<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<style type="text/css">
.ftBtm{margin:0;padding:0;}
.txtRight{text-align:right !important;}
.pdfContainer{font-family:Arial;}
.topHdr table tr td{font-size:12px;}
.addressL{width:48%;padding-right:2%;}
.addressL table tr td{font-size:12px;border-bottom:solid 1px #CCC;line-height:18px;}
.addressR table tr td{font-size:12px;border-bottom:solid 1px #CCC;line-height:18px; }
.respInfo .itmTbl tr td{font-size:12px;text-align:center !important;}
.txtRight{text-align:right !important;}
.noBdr{border-bottom:none !important;}
.respInfo .itmTbl tr th{font-size:12px;text-align:center !important;background:#000;color:#fff;line-height:14px;padding:14px 0}
.respInfo .itmTbl tr th:last-child{border-radius:0 10px 0 0;}
.itemNos td{line-height:8px;font-size:10px;text-align:center !important;border-bottom:solid 1px #000;border-right:solid 1px #000; }
.itemNos td:first-child, .invPrd td:first-child{border-left:solid 1px #000;}
.invPrd td:last-child{border-bottom:solid 1px #000 !important;}
.topHdr{text-align:center !important; border-radius:6px;}
.footerInvL{text-align:left !important;font-family:Helvetica;}
.footerInvR{text-align:right !important;}
.footerInvC{text-align:center !important;border-top:solid 1px #CCC;font-family:Helvetica;}
.respInfo .invPrd td{border-bottom:solid 1px #000;border-right:solid 1px #000;}
.weekDaysLst{height:100px;}
.weekDaysLst table tr td{border-right:solid 1px #000;}
.hding2{font-size:12px;font-weight:bold;margin:10px 0 0 10px;}
.rspTxt{font-size:11px;color:#333;margin-left:20px;}
.address{line-height:24px;}
.respInfo b{color:#000;font-weight:bold;}
.respInfo .col-md-10{margin:2px 0 2px 0}
.respInfo .col-md-10 .rspImg{vertical-align:middle;}
.respInfo table{margin:10px 0 0 0;}
.respInfo table tr th{text-align:left;font-weight:bold;color:#000;}
.respInfo table tr td{text-align:left;}
.respInfo table tr td,.respInfo table tr th{padding:8px 6px;}
.respInfo .col-md-3{float:left;width:100%}
.respInfo .col-md-12{float:left;width:100%}
.visitReportTb{margin:0 0 10px 0;float:left;width:100%;}
.visitReportTb table tr th{text-align:left;font-weight:bold;color:#000;}
.weekDaysLst tr td{padding:10px;}
.brdrTop{border-top:solid 0px #999999;}
.invoiceNo h1{line-height:10px;font-size:40px;height:0;}
.footerInfo{text-align:center !important;line-height:24px;}
</style></head><body><div class="pdfContainer">';
$html.=$pdfCnt;
$html.='</div></body></html>';

$old_limit = ini_set("memory_limit", "30M"); 
$dompdf = new DOMPDF();
$dompdf->load_html($html,'UTF-8');
$dompdf->set_paper('a4','portrait');// or landscape
$dompdf->render();
$dompdf->stream($fileNamePDF);
downloadFile($fileNamePDF,'invoice.pdf');
}
?>