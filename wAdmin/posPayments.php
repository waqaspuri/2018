<?php
include "include/sessionAdmin.php";  
include "include/configure.php";
$q = isset($_GET['q']) ? $_GET['q'] : '';
include "include/header.php";
?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Buyer Payment</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span>Manage Buyer Payment</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act'])){
if($_GET['act']=="1"){
$msgTxt='New Payment has been added successfully';
}
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.$msgTxt.'</strong></div>';
}
?>
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>posPayment.php"><b>Add New</b></a> </div>
<h2 class="panel-title">Manage Buyer Payment</h2>
</header>
<div class="panel-body">
<div class="form-group">
<div class="col-md-6">
<form class="form-horizontal form-bordered" method="get">
<input type="text" style="width:80%;float:left" class="form-control" name="q" value="<?=(isset($_GET['q'])?$_GET['q']:'')?>" placeholder="Search" id="inputDefault"  >
<button class="btn btn-primary" name="submit" value="Submit">GO</button>
</form> <span class="hintTxt">Search :-  Name, Mobile, GST.</span>
</div>
<div class="col-md-6" align="right">
&nbsp;
</div>
</div>	
<div class="table-responsive">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="15%">Buyers</th>
<th width="35%">Particulars</th>
<th width="10%">Date</th>
<th width="25%">Amt</th>
<th width="10%" class="text-center">Action</th>
</tr>
</thead>
<tbody>
<?php
$page_name="posPayments.php?q=".$q;
if(!isset($_REQUEST["start"])) {
$start = 0;
}
else
$start = $_REQUEST["start"];

$eu = ($start - 0); 
$limit = 50;          
$this1 = $eu + $limit; 
$back = $eu - $limit; 
$next = $eu + $limit;
$sqlSrch = "";
if($q != ""){
$sqlSrch = " AND (pb.name LIKE '%".$q."%' OR pb.gst LIKE '%".$q."%' OR pb.mobileNumber LIKE '%".$q."%') ";
}
$sqlSeller = "SELECT pba.*,pb.name FROM pos_buyer_account as pba,pos_buyers as pb where pb.id=pba.posBuyerID AND pba.debitAmt!='0' ".$sqlSrch." ORDER BY pba.id DESC limit $eu, $limit";
$sqltot = "SELECT pba.id FROM pos_buyer_account as pba,pos_buyers as pb where pb.id=pba.posBuyerID AND pba.debitAmt!='0' ".$sqlSrch." ORDER BY pba.id DESC";

$resultSeller= mysqli_query($conn,$sqlSeller);
$resulttot=mysqli_query($conn,$sqltot);
$nume=mysqli_num_rows($resulttot);
if (@mysqli_num_rows($resultSeller)!=0){

$sno=$start;
while($rows=mysqli_fetch_assoc($resultSeller)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.$rows['name'].'</td>
<td>';
if($rows['isDelete']){
echo $rows['deleteReason'];	
}else{
if($rows['posSaleID']){
echo 'Invoice No :- '.$rows['invoiceNo'].' - <a title="Download PDF" href="'.$siteUrlMain.'posInvoicePDF.php?act=pdfDwnld&id='.$rows['posSaleID'].'"><b>Download PDF</b></a>';
}else{
echo 'Bank :- '.$rows['bankDetails'].' - '.(($rows['paymentMethod'])?$rows['paymentMethod']:'').'
'.(($rows['paymentFile'])?'<br /><a href="../img/'.$rows['paymentFile'].'" target="_blank">View File</a>':'');
}
}
echo '</td>
<td>'.viewDateFormat($rows['paymentDate']).'</td>
<td>'.priceFormatRound($rows['debitAmt']).'</td>
<td align="center">
<a title="View this record" class="btn btn-default viewBtn" href="posBuyers.php?action=view&id='.$rows['posBuyerID'].'">View</a> 
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
<?php include "include/footer.php"; ?>