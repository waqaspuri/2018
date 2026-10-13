<?php
include "include/sessionAdmin.php";  
include "include/configure.php"; 
include "include/header.php"; 
?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Product Stocks</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span>Manage Product Stocks</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> &nbsp; </div>
<h2 class="panel-title">Manage Product Stocks</h2>
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
<th width="35%">Name</th>
<th width="10%">Open Qty</th>
<th width="10%">Remaining Qty</th>
<th width="10%">Sales Tax</th>
<th width="10%">Value</th>
<th width="10%">Total Cost (GST) </th>

</tr>
</thead>
<tbody>
<?php
$page_name="posProductStocks.php?q=".$_GET['q']; 
if(!isset($_REQUEST["start"])) {
$start = 0;
}
else
$start = $_REQUEST["start"];

$eu = ($start - 0); 
$limit = 200;          
$this1 = $eu + $limit; 
$back = $eu - $limit; 
$next = $eu + $limit; 
$sql = mysqli_query($conn,"limit $eu, $limit");
$sqlSrch = "";
if(isset($_GET['q']) && $_GET['q']!=""){
$sqlSrch = " AND (p.itemNo LIKE '%".$_GET['q']."%' OR p.description LIKE '%".$_GET['q']."%' OR p.hsCode LIKE '%".$_GET['q']."%') ";
}
$sqlSeller = "SELECT p.*,ut.title FROM pos_products as p,pos_unit_types as ut where ut.id=p.posUnitTypeID ".$sqlSrch." ORDER BY p.id DESC limit $eu, $limit";
$sqltot = "SELECT p.id FROM pos_products as p,pos_unit_types as ut where ut.id=p.posUnitTypeID ".$sqlSrch." ORDER BY p.id DESC";

$resultSeller= mysqli_query($conn,$sqlSeller);
$resulttot=mysqli_query($conn,$sqltot);
$nume=mysqli_num_rows($resulttot);
if (@mysqli_num_rows($resultSeller)!=0){

$sno=$start;
while($rows=mysqli_fetch_assoc($resultSeller)){
$sno++;

$totalVal = ($rows['prodQtyOrg']*$rows['prodValue']);
$sqlPrdCmp = mysqli_query($conn,"SELECT * FROM pos_sale_order_products WHERE productID='".$rows['id']."'");
$numRowsPrdCmp = mysqli_num_rows($sqlPrdCmp);
if($numRowsPrdCmp>0){
$ttCmp =$ttST = 0;
while($rowCP = mysqli_fetch_assoc($sqlPrdCmp)){
$sno++;
$tAmt =  $rowCP['valueOfGood'];
$taxAmt = priceFormat(($rowCP['prodTax']/100) * $tAmt);
$ttST+=$taxAmt;
$ttCmp+=$tAmt;
}
}else{
$ttCmp =$ttST = 0;
}

$taxAmt = priceFormat(($rows['prodSaleTax']/100) * $totalVal);
if($rows['prodQuantity']>0) {
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td><a href="posProducts.php?action=view&id='.$rows['id'].'">'.$rows['description'].' (H.S. Code '.$rows['hsCode'].')
'.(($rows['prodQuantity']<0)?'<br /><span class="redTxt">NEGATIVE QTY</span>':'').'</a></td>
<td>'.$rows['prodQtyOrg'].''.$rows['title'].'</td>
<td>'.$rows['prodQuantity'].''.$rows['title'].'</td>';


$taxAmt = priceFormat(($rows['prodSaleTax']/100) * $totalVal);
$totalCompt = ($taxAmt-$ttST);
$totalSaleAmt = ($totalVal-$ttCmp);

echo '
<td>' . priceFormatRound($totalCompt) .'</td><td>'
 . priceFormatRound($totalSaleAmt) . '</td><td>'
. priceFormatRound($totalCompt+$totalSaleAmt) . '</td></tr>';

}
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