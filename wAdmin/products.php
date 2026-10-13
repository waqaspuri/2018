<?php
include "include/sessionAdmin.php";  
include "include/configure.php";
/** Store each product's attachments in its own directory so product duplication never copies files. */
function productAttachmentFiles($productID) {
    $dir = dirname(__DIR__) . '/product_photos/' . (int)$productID;
    if (!is_dir($dir)) return array();
    $files = array();
    foreach (scandir($dir) as $file) {
        if ($file !== '.' && $file !== '..' && is_file($dir . '/' . $file)) $files[] = $file;
    }
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    return $files;
}
function saveProductAttachments($productID) {
    if (empty($_FILES['attachments']) || !isset($_FILES['attachments']['name']) || !is_array($_FILES['attachments']['name'])) return;
    $dir = dirname(__DIR__) . '/product_photos/' . (int)$productID;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new RuntimeException('Could not create the product_photos directory.');
    $allowed = array('jpg' => array('image/jpeg'), 'jpeg' => array('image/jpeg'), 'png' => array('image/png'), 'gif' => array('image/gif'), 'webp' => array('image/webp'), 'pdf' => array('application/pdf'));
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    foreach ($_FILES['attachments']['name'] as $i => $originalName) {
        $error = $_FILES['attachments']['error'][$i];
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('An attachment could not be uploaded. Check the file size and try again.');
        $tmp = $_FILES['attachments']['tmp_name'][$i];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mime = $finfo ? finfo_file($finfo, $tmp) : '';
        if (!isset($allowed[$ext]) || !in_array($mime, $allowed[$ext], true)) throw new RuntimeException('Only PDF, JPG, PNG, GIF, and WEBP attachments are allowed.');
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($originalName));
        $target = $dir . '/' . uniqid('', true) . '_' . $safeName;
        if (!move_uploaded_file($tmp, $target)) throw new RuntimeException('Could not save an uploaded attachment.');
    }
    if ($finfo) finfo_close($finfo);
}
if(isset($_POST['submit']) && $_POST['submit']=="Submit"){
if(isset($_POST['action']) && $_POST['action']=="add"){
mysqli_query($conn,"INSERT INTO products (hsCode,prodUMO,assignToVendor,prodName,prodQty,prodOrigin,prodPackaging,prodDesc,createdDate) VALUES ('".mysqli_real_escape_string($conn,$_POST['hsCode'])."','".mysqli_real_escape_string($conn,$_POST['prodUMO'])."','".mysqli_real_escape_string($conn,$_POST['assignToVendor'])."','".mysqli_real_escape_string($conn,$_POST['prodName'])."','".mysqli_real_escape_string($conn,$_POST['prodQty'])."','".mysqli_real_escape_string($conn,$_POST['prodOrigin'])."','".mysqli_real_escape_string($conn,$_POST['prodPackaging'])."','".mysqli_real_escape_string($conn,$_POST['prodDesc'])."','".currDateTimeGmt()."')");
try { saveProductAttachments(mysqli_insert_id($conn)); } catch (RuntimeException $e) { die(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')); }
header("location: products.php?act=1");
}else if(isset($_POST['action']) && $_POST['action']=="update"){
mysqli_query($conn,"UPDATE products SET hsCode='". mysqli_real_escape_string($conn,$_POST['hsCode'])."',prodUMO='". mysqli_real_escape_string($conn,$_POST['prodUMO'])."',assignToVendor='". mysqli_real_escape_string($conn,$_POST['assignToVendor'])."',prodName='". mysqli_real_escape_string($conn,$_POST['prodName'])."',prodQty='". mysqli_real_escape_string($conn,$_POST['prodQty'])."',prodOrigin='". mysqli_real_escape_string($conn,$_POST['prodOrigin'])."',prodPackaging='". mysqli_real_escape_string($conn,$_POST['prodPackaging'])."',prodDesc='". mysqli_real_escape_string($conn,$_POST['prodDesc'])."' WHERE id='".$_POST['id']."'");
try { saveProductAttachments((int)$_POST['id']); } catch (RuntimeException $e) { die(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')); }
header("location: products.php?act=2");
}
}
if(isset($_GET['act']) && $_GET['act']=="duplicate"){
$duplicateID = (int)($_GET['id'] ?? 0);
$createdDate = mysqli_real_escape_string($conn, currDateTimeGmt());
mysqli_query($conn,"INSERT INTO products (hsCode,prodUMO,assignToVendor,prodName,prodQty,prodOrigin,prodPackaging,prodDesc,createdDate) SELECT hsCode,prodUMO,assignToVendor,prodName,prodQty,prodOrigin,prodPackaging,prodDesc,'".$createdDate."' FROM products WHERE id='".$duplicateID."' LIMIT 1");
$newProductID = mysqli_insert_id($conn);
if($newProductID > 0){
header("location: products.php?action=edit&id=".$newProductID);
}else{
header("location: products.php");
}
exit;
}
if(isset($_GET['act']) && $_GET['act']=="delete"){
mysqli_query($conn,"DELETE FROM products WHERE id='".$_GET['id']."'");
header("location: products.php?act=3");
}
$prodName = "";
$assignToVendor = "";
$prodQty = "";
$prodUMO = "";
$prodOrigin = "";
$prodPackaging = "";
$hsCode = "";
$prodDesc = "";
$id = "";
$action = "add";
$hdTxt="Add New";
if(isset($_GET['action']) && $_GET['action']=="edit"){
$sqlEdit = mysqli_query($conn,"SELECT * FROM products WHERE id='".$_GET['id']."'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
extract($rowsEdit);
$action = "update";
$hdTxt="Update";
} 
include "include/header.php"; 
if(isset($_GET['action']) && $_GET['action']=="view"){
$sqlEdit = mysqli_query($conn,"SELECT * FROM products WHERE id='".$_GET['id']."'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
extract($rowsEdit);

?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Products</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>products.php">Manage Products</a></span></li>
<li><span>View Product</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>products.php"><b>Back</b></a> </div>
<h2 class="panel-title">View Products</h2>
</header>
<div class="panel-body viewFrm">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Name</label>
<div class="col-md-9"><?=$prodName?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Vendor Name</label>
<div class="col-md-9"><?=companyName($assignToVendor)?></div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Quantity<br />Per Container</label>
<div class="col-md-8"><?=$prodQty?></div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" for="inputDefault">UMO</label>
<div class="col-md-9"><?=$prodUMO?></div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Origin</label>
<div class="col-md-9"><?=$prodOrigin?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Packaging</label>
<div class="col-md-9"><?=$prodPackaging?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">HS Code</label>
<div class="col-md-9"><?=$hsCode?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Description</label>
<div class="col-md-9"><?=$prodDesc?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label">Attachments</label>
<div class="col-md-9">
<?php $attachmentFiles = productAttachmentFiles($rowsEdit['id']); if ($attachmentFiles) { foreach ($attachmentFiles as $attachment) { echo '<div><a target="_blank" rel="noopener" href="../product_photos/'.(int)$rowsEdit['id'].'/'.rawurlencode($attachment).'">'.htmlspecialchars($attachment, ENT_QUOTES, 'UTF-8').'</a></div>'; } } else { echo 'No attachments'; } ?>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Created Date</label>
<div class="col-md-9"><?=viewDtTm($createdDate)?></div>
</div>

<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Vendors</label>
<div class="col-md-10">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="40%">Company Name</th>
<th width="25%">Name</th>
<th width="30%">Mobile</th>
</tr>
</thead>
<tbody>
<?php
$sqlTask = mysqli_query($conn,"SELECT c.companyName,c.applicantName,c.mobileNumber FROM orders as o,customers as c WHERE c.id=o.vendorID AND o.productID='".$rowsEdit['id']."' group by c.companyName, c.applicantName, c.mobileNumber");
$numRowsTask = mysqli_num_rows($sqlTask);
if($numRowsTask>0){
$sno=0;
while($rows=mysqli_fetch_assoc($sqlTask)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.$rows['companyName'].'</td>
<td>'.$rows['applicantName'].'</td>
<td>'.$rows['mobileNumber'].'</td>
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
<label class="col-md-2 control-label" for="inputDefault">Customers</label>
<div class="col-md-10">
<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="40%">Company Name</th>
<th width="25%">Name</th>
<th width="30%">Mobile</th>
</tr>
</thead>
<tbody>
<?php
$sqlTask = mysqli_query($conn,"SELECT c.companyName,c.applicantName,c.mobileNumber FROM orders as o,customers as c WHERE c.id=o.customerID AND o.productID='".$rowsEdit['id']."' group by c.companyName, c.applicantName, c.mobileNumber");
$numRowsTask = mysqli_num_rows($sqlTask);
if($numRowsTask>0){
$sno=0;
while($rows=mysqli_fetch_assoc($sqlTask)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.$rows['companyName'].'</td>
<td>'.$rows['applicantName'].'</td>
<td>'.$rows['mobileNumber'].'</td>
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
<label class="col-md-2 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<button type="button" onclick="window.location.href='<?=$siteUrl;?>products.php'" class="btn btn-default">Back</button>
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
<h2>Manage Products</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>products.php">Manage Products</a></span></li>
<li><span><?=$hdTxt;?> Product</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>products.php"><b>Back</b></a> </div>
<h2 class="panel-title"><?=$hdTxt;?> Products</h2>
</header>
<div class="panel-body">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Name *</label>
<div class="col-md-9">
<input type="text" class="form-control" name="prodName" value="<?=$prodName?>" id="inputDefault" required >
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Assign To Vendor</label>
<div class="col-md-9 selectSrch">
<select class="selectpicker form-control" data-selected-text-format="values" name="assignToVendor" id="assignToVendor" data-live-search="true" data-live-search-placeholder="Search" required>
<option value="">- Select -</option>
<?php
$sqlVndrCtmr = mysqli_query($conn,"SELECT id,companyName FROM customers WHERE type='2' ORDER BY companyName ASC");
while($rowsVC = mysqli_fetch_assoc($sqlVndrCtmr)){						
echo '<option value="'.$rowsVC['id'].'" '.(($rowsVC['id']==$assignToVendor)?'selected="selected"':'').' >'.$rowsVC['companyName'].'</option>';
}
?>
</select>
</div>
</div>
<div class="form-group">

<div class="col-md-6"><label class="col-md-4 control-label" for="inputDefault">Quantity</label>
<div class="col-md-8">
<input type="text" class="form-control" name="prodQty" value="<?=$prodQty?>" id="inputDefault" ><span class="hintTxt">Per Container</span>
</div></div>
<div class="col-md-6"><label class="col-md-2 control-label" for="inputDefault">UMO</label>
<div class="col-md-8">
<input type="text" class="form-control" name="prodUMO" value="<?=$prodUMO?>" id="inputDefault" ><span class="hintTxt">Per Metic Tons</span>
</div></div>

</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Origin</label>
<div class="col-md-8">
<input type="text" class="form-control" name="prodOrigin" value="<?=$prodOrigin?>" id="inputDefault"  >
</div>
</div>
<div class="col-md-6">
<label class="col-md-2 control-label" for="inputDefault">Packaging</label>
<div class="col-md-8">
<input type="text" class="form-control" name="prodPackaging" value="<?=$prodPackaging?>" id="inputDefault" >
</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">HS Code</label>
<div class="col-md-9">
<input type="text" class="form-control" name="hsCode" value="<?=$hsCode?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Description</label>
<div class="col-md-9">
<textarea cols="2" rows="2" name="prodDesc" class="form-control"><?=$prodDesc?></textarea>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="attachments">Attachments</label>
<div class="col-md-9">
<input type="file" class="form-control" id="attachments" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,application/pdf,image/jpeg,image/png,image/gif,image/webp">
<span class="hintTxt">Upload multiple PDF or image files. Existing attachments are kept when no new files are selected.</span>
<?php $attachmentFiles = $action === 'update' ? productAttachmentFiles($id) : array(); if ($attachmentFiles) { echo '<div class="mt-sm"><strong>Saved attachments</strong>'; foreach ($attachmentFiles as $attachment) { echo '<div><a target="_blank" rel="noopener" href="../product_photos/'.(int)$id.'/'.rawurlencode($attachment).'">'.htmlspecialchars($attachment, ENT_QUOTES, 'UTF-8').'</a></div>'; } echo '</div>'; } ?>
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<input type="hidden" name="action" value="<?=$action;?>" />
<input type="hidden" name="id" value="<?=$id;?>" />
<button class="btn btn-primary" name="submit" value="Submit">Submit</button>
<button type="button" onclick="window.location.href='<?=$siteUrl;?>products.php'" class="btn btn-default">Cancel</button>
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
<h2>Manage Product</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span>Manage Product</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act'])){
if($_GET['act']=="1"){
$msgTxt='New Product has been added successfully';
}else if($_GET['act']=="2"){
$msgTxt='Product has been updated successfully';
}else if($_GET['act']=="3"){
$msgTxt='Product has been deleted successfully';
}	
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.$msgTxt.'</strong></div>';
}
?>
<section class="panel">
<header class="panel-heading">
<div class="panel-actions">
<a href="<?=$siteUrl;?>products_bulk.php"><b>Bulk Add</b></a>
&nbsp;
<a href="<?=$siteUrl;?>products.php?action=add"><b>Add New</b></a>
</div>
<h2 class="panel-title">Manage Product</h2>
</header>
<div class="panel-body">
<div class="form-group">
<div class="col-md-6">
<form class="form-horizontal form-bordered" method="get">
<input type="text" style="width:80%;float:left" class="form-control" name="q" value="<?=(isset($_GET['q'])?$_GET['q']:'')?>" placeholder="Search" id="inputDefault"  >
<button class="btn btn-primary" name="submit" value="Submit">GO</button>
</form> <span class="hintTxt">Search :-  Product Name.</span>
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
<th width="20%">Name</th>
<th width="15%">Quantity</th>
<th width="20%">Packaging</th>
<th width="18%">Created On</th>
<th width="22%" class="text-center">Action</th>
</tr>
</thead>
<tbody>
<?php
$page_name="products.php?q=".(isset($_GET['q']) ? $_GET['q'] : ''); 
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
$sqlSrch = " where (prodName LIKE '%".$_GET['q']."%') ";
}
$sqlSeller = "SELECT id,prodName,assignToVendor,prodQty,prodUMO,prodPackaging,createdDate FROM products ".$sqlSrch." ORDER BY id DESC limit $eu, $limit";
$sqltot = "SELECT id FROM products ".$sqlSrch." ORDER BY id DESC ";

$resultSeller= mysqli_query($conn,$sqlSeller);
$resulttot=mysqli_query($conn,$sqltot);
$nume=mysqli_num_rows($resulttot);
if (@mysqli_num_rows($resultSeller)!=0){

$sno=$start;
while($rows=mysqli_fetch_assoc($resultSeller)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.$rows['prodName'].' ('.companyName($rows['assignToVendor']).')</td>
<td>'.$rows['prodQty'].' '.$rows['prodUMO'].'</td>
<td>'.$rows['prodPackaging'].'</td>
<td align="center">'.viewDtTm($rows['createdDate']).'</td>
<td align="center">
<a title="View this record" class="btn btn-default viewBtn" href="products.php?action=view&id='.$rows['id'].'">View</a>
<a title="Edit this record" class="btn btn-default editBtn" href="products.php?action=edit&id='.$rows['id'].'">Edit</a>
<a title="Delete this record" class="btn btn-default delBtn" onclick="return confirm(\'Are you sure you want to delete?\');" href="products.php?act=delete&id='.$rows['id'].'">Delete</a>
<a title="Duplicate this product" class="btn btn-default" href="products.php?act=duplicate&id='.$rows['id'].'">Duplicate</a>
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
}
?>
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
