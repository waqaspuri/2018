<?php
include "include/sessionAdmin.php";  
include "include/configure.php";
if(isset($_POST['submit']) && $_POST['submit']=="Submit"){
$adminID = isset($_SESSION['adminID']) ? $_SESSION['adminID'] : '0';
if(isset($_POST['action']) && $_POST['action']=="add"){
mysqli_query($conn,"INSERT INTO customers (bankName,bankBranch,type,companyName,applicantName,address,city,country,mobileNumber,phoneNumber,isEnableEmail,assignToID,isOtherCustomer,email,email2,appAddress,appCity,appCountry,appSwiftCode,appIBAN,appAccountNo,moreInfo,createdDate) VALUES 
('".mysqli_real_escape_string($conn,$_POST['bankName'] ?? '')."','".mysqli_real_escape_string($conn,$_POST['bankBranch'] ?? '')."','2','".mysqli_real_escape_string($conn,$_POST['companyName'] ?? '')."','".mysqli_real_escape_string($conn,$_POST['applicantName'] ?? '')."','".mysqli_real_escape_string($conn,$_POST['address'] ?? '')."','".mysqli_real_escape_string($conn,$_POST['city'] ?? '')."','".mysqli_real_escape_string($conn,$_POST['country'] ?? '')."',".((isset($_POST['mobileNumber']) && $_POST['mobileNumber'] !== '') ? "'".mysqli_real_escape_string($conn, $_POST['mobileNumber'])."'" : "NULL").",".((isset($_POST['mobileNumber']) && $_POST['mobileNumber'] !== '') ? "'".mysqli_real_escape_string($conn, $_POST['mobileNumber'])."'" : "NULL").",'1','".$adminID."','0','".mysqli_real_escape_string($conn,$_POST['email'] ?? '')."','".mysqli_real_escape_string($conn,$_POST['email2'] ?? '')."','".mysqli_real_escape_string($conn,$_POST['appAddress'] ?? '')."','".mysqli_real_escape_string($conn,$_POST['appCity'] ?? '')."','".mysqli_real_escape_string($conn,$_POST['appCountry'] ?? '')."','".mysqli_real_escape_string($conn,$_POST['appSwiftCode'] ?? '')."','".mysqli_real_escape_string($conn,$_POST['appIBAN'] ?? '')."','".mysqli_real_escape_string($conn,$_POST['appAccountNo'] ?? '')."','".mysqli_real_escape_string($conn,$_POST['moreInfo'] ?? '')."','".currDateTimeGmt()."')");
header("location: vendors.php?act=1");
}else if(isset($_POST['action']) && $_POST['action']=="update"){
mysqli_query($conn,"UPDATE customers SET bankName='". mysqli_real_escape_string($conn,$_POST['bankName'] ?? '')."',bankBranch='". mysqli_real_escape_string($conn,$_POST['bankBranch'] ?? '')."',companyName='". mysqli_real_escape_string($conn,$_POST['companyName'] ?? '')."',applicantName='". mysqli_real_escape_string($conn,$_POST['applicantName'] ?? '')."',address='". mysqli_real_escape_string($conn,$_POST['address'] ?? '')."',city='". mysqli_real_escape_string($conn,$_POST['city'] ?? '')."',country='". mysqli_real_escape_string($conn,$_POST['country'] ?? '')."',mobileNumber=".((isset($_POST['mobileNumber']) && $_POST['mobileNumber'] !== '') ? "'".mysqli_real_escape_string($conn, $_POST['mobileNumber'])."'" : "NULL").",phoneNumber=".((isset($_POST['mobileNumber']) && $_POST['mobileNumber'] !== '') ? "'".mysqli_real_escape_string($conn, $_POST['mobileNumber'])."'" : "NULL").",isEnableEmail='1',assignToID='".$adminID."',isOtherCustomer='0',email='". mysqli_real_escape_string($conn,$_POST['email'] ?? '')."',email2='". mysqli_real_escape_string($conn,$_POST['email2'] ?? '')."',appAddress='". mysqli_real_escape_string($conn,$_POST['appAddress'] ?? '')."',appCity='". mysqli_real_escape_string($conn,$_POST['appCity'] ?? '')."',appCountry='". mysqli_real_escape_string($conn,$_POST['appCountry'] ?? '')."',appIBAN='". mysqli_real_escape_string($conn,$_POST['appIBAN'] ?? '')."',appAccountNo='". mysqli_real_escape_string($conn,$_POST['appAccountNo'] ?? '')."',appSwiftCode='". mysqli_real_escape_string($conn,$_POST['appSwiftCode'] ?? '')."',moreInfo='". mysqli_real_escape_string($conn,$_POST['moreInfo'] ?? '')."' WHERE id='".$_POST['id']."'");	
header("location: vendors.php?act=2");
}
}
if(isset($_GET['act']) && $_GET['act']=="delete"){
mysqli_query($conn,"DELETE FROM customers WHERE id='".$_GET['id']."'");	
header("location: vendors.php?act=3");
}
$action = "add";
$hdTxt="Add New";
$companyName = $applicantName = $address = $city = $country = $mobileNumber = $email = $email2 = $bankName = $bankBranch = $appAccountNo = $appIBAN = $appSwiftCode = $moreInfo = $id = '';
if(isset($_GET['action']) && $_GET['action']=="edit"){
$sqlEdit = mysqli_query($conn,"SELECT * FROM customers WHERE id='".$_GET['id']."'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
extract($rowsEdit);
$action = "update";
$hdTxt="Update";
} 
include "include/header.php"; 

if(isset($_GET['action']) && $_GET['action']=="view"){ 
$sqlEdit = mysqli_query($conn,"SELECT * FROM customers WHERE id='".$_GET['id']."'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
extract($rowsEdit);
?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Vendors</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>vendors.php">Manage Vendors</a></span></li>
<li><span>View Vendor</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>vendors.php"><b>Back</b></a> </div>
<h2 class="panel-title">View Vendors</h2>
</header>
<div class="panel-body viewFrm">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Company Name</label>
<div class="col-md-8"><?=$companyName?></div>
</div>
<div class="col-md-6">
<label class="col-md-3 control-label" style="padding-left:0;padding-right:0;" for="inputDefault">Contact Person</label>
<div class="col-md-8"><?=$applicantName?></div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Address</label>
<div class="col-md-9"><?=$address?></div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">City</label>
<div class="col-md-8"><?=$city?></div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" for="inputDefault">Country</label>
<div class="col-md-8"><?=$country?></div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Mobile Number</label>
<div class="col-md-9"><?=$mobileNumber?></div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Email</label>
<div class="col-md-8"><?=$email?></div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" for="inputDefault">Email-2</label>
<div class="col-md-8"><?=$email2?></div>
</div>
</div>
 
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">&nbsp;</label>
<div class="col-md-9"><b>Bank Information</b></div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Bank Name</label>
<div class="col-md-7"><?=$bankName?></div>
</div>
<div class="col-md-6">
<label class="col-md-3 control-label" for="inputDefault">Bank Branch</label>
<div class="col-md-7"><?=$bankBranch?></div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Account Number</label>
<div class="col-md-9"><?=$appAccountNo?></div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">IBAN</label>
<div class="col-md-7"><?=$appIBAN?></div>
</div>
<div class="col-md-6">
<label class="col-md-3 control-label" for="inputDefault">SWIFT Code</label>
<div class="col-md-7"><?=$appSwiftCode?></div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">&nbsp;</label>
<div class="col-md-9"><b>About Applicant</b></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Other Information </label>
<div class="col-md-9"><?=$moreInfo?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Created Date</label>
<div class="col-md-9"><?=viewDtTm($createdDate)?></div>
</div>

<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Products</label>
<div class="col-md-10">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="10%">ID</th>
<th width="60%">Product</th>
<th width="30%">HS Code</th>
</tr>
</thead>
<tbody>
<?php
$vendorID = (int)$rowsEdit['id'];
$sqlVendorProducts = mysqli_query($conn, "SELECT id,prodName,hsCode FROM products WHERE assignToVendor='" . $vendorID . "' ORDER BY prodName ASC");
if ($sqlVendorProducts && mysqli_num_rows($sqlVendorProducts) > 0) {
    while ($productRow = mysqli_fetch_assoc($sqlVendorProducts)) {
        echo '<tr>
<td>' . (int)$productRow['id'] . '</td>
<td><a href="products.php?action=view&id=' . (int)$productRow['id'] . '">' . htmlspecialchars($productRow['prodName'], ENT_QUOTES, 'UTF-8') . '</a></td>
<td>' . htmlspecialchars($productRow['hsCode'], ENT_QUOTES, 'UTF-8') . '</td>
</tr>';
    }
} else {
    echo "<tr><td colspan='3' align='center'>No products associated with this vendor.</td></tr>";
}
?>
</tbody>
</table>
</div>
</div>

<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Orders</label>
<div class="col-md-10">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="10%">ID</th>
<th width="25%">Company Name</th>
<th width="30%">Product Designation</th>
<th width="30%">Created On</th>
</tr>
</thead>
<tbody>
<?php
$sqlTask = mysqli_query($conn,"SELECT o.id,o.vendorID,o.customerID,p.prodName,o.createdDate FROM orders as o,products as p WHERE p.id=o.productID AND vendorID='".$rowsEdit['id']."'  ORDER BY id ASC");
$numRowsTask = mysqli_num_rows($sqlTask);
if($numRowsTask>0){
$sno=0;
while($rows=mysqli_fetch_assoc($sqlTask)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td><a href="orders.php?action=view&id='.$rows['id'].'">#'.$rows['id'].'</a></td>
<td>'.companyName($rows['customerID']).'</td>
<td>'.$rows['prodName'].'</td>
<td align="center">'.viewDtTm($rows['createdDate']).'</td>
</tr>';
}
}else{
echo "<tr>
<td colspan='8' align='center'>No Data Found.</td>
</tr>";
}
?>
</tbody>
</table>
	
</div>
</div>


<div class="form-group">
<label class="col-md-3 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<button type="button" onclick="window.location.href='<?=$siteUrl;?>vendors.php'" class="btn btn-default">Back</button>
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
}else if(isset($_GET['action']) && ($_GET['action']=="add" || $_GET['action']=="edit")){ 
?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Vendors</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>vendors.php">Manage Vendors</a></span></li>
<li><span><?=$hdTxt;?> Vendor</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>vendors.php"><b>Back</b></a> </div>
<h2 class="panel-title"><?=$hdTxt;?> Vendors</h2>
</header>
<div class="panel-body">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Company Name *</label>
<div class="col-md-8">
<input type="text" class="form-control" name="companyName" value="<?=$companyName?>" id="inputDefault" required >
</div>
</div>
<div class="col-md-6">
<label class="col-md-3 control-label" for="inputDefault" style="padding-left:0;padding-right:0;">Contact Person *</label>
<div class="col-md-8">
<input type="text" class="form-control" name="applicantName" value="<?=$applicantName?>" id="inputDefault" required >
</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Address</label>
<div class="col-md-9">
<input type="text" class="form-control" name="address" value="<?=$address?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">City</label>
<div class="col-md-8">
<input type="text" class="form-control" name="city" value="<?=$city?>" id="inputDefault"  >
</div>
</div>
<div class="col-md-6">
<label class="col-md-3 control-label" for="inputDefault">Country</label>
<div class="col-md-8">
<input type="text" class="form-control" name="country" value="<?=$country?>" id="inputDefault" >
</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Mobile Number*</label>
<div class="col-md-9">
<input type="text" class="form-control" name="mobileNumber" value="<?=$mobileNumber?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Email</label>
<div class="col-md-8">
<input type="text" class="form-control" name="email" value="<?=$email?>" id="inputDefault" >
</div>
</div>
<div class="col-md-6">
<label class="col-md-3 control-label" for="inputDefault">Email-2</label>
<div class="col-md-8">
<input type="text" class="form-control" name="email2" value="<?=$email2?>" id="inputDefault" >
</div>
</div>
</div>

<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">&nbsp;</label>
<div class="col-md-9"><b>Bank Information</b></div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Bank Name</label>
<div class="col-md-8">
<input type="text" class="form-control" name="bankName" value="<?=$bankName?>" id="inputDefault" >
</div>
</div>
<div class="col-md-6">
<label class="col-md-3 control-label" for="inputDefault">Bank Branch</label>
<div class="col-md-7">
<input type="text" class="form-control" name="bankBranch" value="<?=$bankBranch?>" id="inputDefault"  >
</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Account Number</label>
<div class="col-md-9">
<input type="text" class="form-control" name="appAccountNo" value="<?=$appAccountNo?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">IBAN</label>
<div class="col-md-8">
<input type="text" class="form-control" name="appIBAN" value="<?=$appIBAN?>" id="inputDefault" >
</div>
</div>
<div class="col-md-6">
<label class="col-md-3 control-label" for="inputDefault">SWIFT Code</label>
<div class="col-md-7">
<input type="text" class="form-control" name="appSwiftCode" value="<?=$appSwiftCode?>" id="inputDefault"  >
</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">&nbsp;</label>
<div class="col-md-9"><b>About Applicant</b></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Other Information </label>
<div class="col-md-9">
<textarea cols="2" rows="2" name="moreInfo" class="form-control"><?=$moreInfo?></textarea>
</div>
</div>

<div class="form-group">
<label class="col-md-3 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<input type="hidden" name="action" value="<?=$action;?>" />
<input type="hidden" name="id" value="<?=$id;?>" />
<button class="btn btn-primary" name="submit" value="Submit">Submit</button>
<button type="button" onclick="window.location.href='<?=$siteUrl;?>vendors.php'" class="btn btn-default">Cancel</button>
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
<h2>Manage Vendor</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span>Manage Vendor</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act'])){
if($_GET['act']=="1"){
$msgTxt='New Vendor has been added successfully';
}else if($_GET['act']=="2"){
$msgTxt='Vendor has been updated successfully';
}else if($_GET['act']=="3"){
$msgTxt='Vendor has been deleted successfully';
}	
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.$msgTxt.'</strong></div>';
}
?>
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>vendors.php?action=add"><b>Add New</b></a> </div>
<h2 class="panel-title">Manage Vendor</h2>
</header>
<div class="panel-body">
<div class="form-group">
<div class="col-md-6">
<form class="form-horizontal form-bordered" method="get">
<input type="text" style="width:80%;float:left" class="form-control" name="q" value="<?=(isset($_GET['q'])?$_GET['q']:'')?>" placeholder="Search" id="inputDefault"  >
<button class="btn btn-primary" name="submit" value="Submit">GO</button>
</form> <span class="hintTxt">Search :-  Company Name,Name, Mobile, Email.</span>
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
<th width="20%">Company</th>
<th width="15%">Contact Person</th>
<th width="20%">Address</th>
<th width="18%">Country</th>
<th width="22%" class="text-center">Action</th>
</tr>
</thead>
<tbody>
<?php
$page_name="vendors.php?q=".(isset($_GET['q']) ? $_GET['q'] : ''); 
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

$sqlSrch = "";
if(isset($_GET['q']) && $_GET['q']!=""){
$sqlSrch = " AND (companyName LIKE '%".$_GET['q']."%' or applicantName LIKE '%".$_GET['q']."%' or mobileNumber LIKE '%".$_GET['q']."%' or email LIKE '%".$_GET['q']."%' or email2 LIKE '%".$_GET['q']."%') ";
}
$sqlSeller = "SELECT id,companyName,address,city,country,applicantName,createdDate FROM customers WHERE type='2' ".$sqlSrch." ORDER BY id DESC limit $eu, $limit";
$sqltot = "SELECT id FROM customers WHERE type='2' ".$sqlSrch." ORDER BY id DESC ";

$resultSeller= mysqli_query($conn,$sqlSeller);
$resulttot=mysqli_query($conn,$sqltot);
$nume=mysqli_num_rows($resulttot);
if (@mysqli_num_rows($resultSeller)!=0){

$sno=$start;
while($rows=mysqli_fetch_assoc($resultSeller)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.$rows['companyName'].'</td>
<td>'.$rows['applicantName'].'</td>
<td>'.$rows['address'].' '.(($rows['city']!="")?', '.$rows['city']:'').'</td>
<td>'.$rows['country'].'</td>
<td align="center">
<a title="View this record" class="btn btn-default viewBtn" href="vendors.php?action=view&id='.$rows['id'].'">View</a>
<a title="Edit this record" class="btn btn-default editBtn" href="vendors.php?action=edit&id='.$rows['id'].'">Edit</a>
<a title="Delete this record" class="btn btn-default delBtn" onclick="return confirm(\'Are you sure you want to delete?\');" href="vendors.php?act=delete&id='.$rows['id'].'">Delete</a>
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
