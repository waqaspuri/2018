<?php
include "include/sessionAdmin.php";  
include "include/configure.php";
$search = trim((string)($_GET['q'] ?? ''));
$orderTitle = '';
$orderNote = '';
$id = '';
if(isset($_POST['submit']) && $_POST['submit']=="Submit"){
$noteOrderID = (int)($_POST['oID'] ?? 0);
$submittedStatusID = $_POST['statusID'] ?? '';
if (!is_scalar($submittedStatusID) || !ctype_digit((string)$submittedStatusID)) {
    $submittedStatusID = orderStatusID($noteOrderID);
}
$noteStatusID = (int)$submittedStatusID;
if ($noteStatusID <= 0) {
    $noteStatusID = 1;
}
$noteTitle = mysqli_real_escape_string($conn, (string)($_POST['orderTitle'] ?? ''));
$noteBody = mysqli_real_escape_string($conn, (string)($_POST['orderNote'] ?? ''));
if(isset($_POST['action']) && $_POST['action']=="add"){
mysqli_query($conn,"INSERT INTO order_notes (orderID,statusID,orderTitle,orderNote,createdDate) VALUES ('" . $noteOrderID . "','" . $noteStatusID . "','" . $noteTitle . "','" . $noteBody . "','" . currDateTimeGmt() . "')");
header("location: orderNotes.php?act=1&oID=" . $noteOrderID);
}else if(isset($_POST['action']) && $_POST['action']=="update"){
mysqli_query($conn,"UPDATE order_notes SET orderID='" . $noteOrderID . "',statusID='" . $noteStatusID . "',orderNote='" . $noteBody . "',orderTitle='" . $noteTitle . "' WHERE id='" . (int)($_POST['id'] ?? 0) . "'");
header("location: orderNotes.php?act=2&oID=" . $noteOrderID);
}
}
if(isset($_GET['act']) && $_GET['act']=="delete"){
mysqli_query($conn,"DELETE FROM order_notes WHERE id='".$_GET['id']."'");	
header("location: orderNotes.php?act=3&oID=".$_GET['oID']);
}
$action = "add";
$hding = "Add New";
if(isset($_GET['action']) && $_GET['action']=="edit"){
$sqlEdit = mysqli_query($conn,"SELECT * FROM order_notes WHERE id='".$_GET['id']."'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
extract($rowsEdit);
$action = "update";
$hding = "Update";
} 
include "include/header.php"; 

if (isset($_GET['action']) && in_array($_GET['action'], array("add", "edit"), true)) {
?>
<script src="<?=$siteUrl?>assets/vendor/summernote/summernote.js"></script>
<link rel="stylesheet" href="<?=$siteUrl?>assets/vendor/summernote/summernote.css" />
<link rel="stylesheet" href="<?=$siteUrl?>assets/vendor/summernote/summernote-bs3.css" /> 
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Orders &raquo; Notes</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>orderNotes.php?oID=<?=$_GET['oID']?>">Manage Notes</a></span></li>
<li><span><?=$hding;?> Note</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>orderNotes.php?oID=<?=$_GET['oID']?>"><b>Back</b></a> </div>
<h2 class="panel-title"><?=orderProdName($_GET['oID'])?> &raquo; <?=$hding;?> Note</h2>
</header>
<div class="panel-body">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<label class="col-md-3 control-label" for="inputDefault">Status</label>
<div class="col-md-8"><b><?=statusNm(orderStatusID($_GET['oID']))?></b></div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDefault">Title *</label>
<div class="col-md-8">
<input type="text" class="form-control" name="orderTitle" value="<?=$orderTitle?>" id="inputDefault" required >
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDefault">Comment</label>
<div class="col-md-8"><textarea class="summernote" id="orderNote" name="orderNote" data-plugin-summernote data-plugin-options='{ "height": 100, "codemirror": { "theme": "ambiance" } }'><?php echo htmlspecialchars($orderNote); ?></textarea></div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-8">
<input type="hidden" name="action" value="<?=$action;?>" />
<input type="hidden" name="id" value="<?=$id;?>" />
<input type="hidden" name="oID" value="<?=$_GET['oID'];?>" />
<button class="btn btn-primary" name="submit" value="Submit">Submit</button>
<button type="button" onclick="window.location.href='<?=$siteUrl;?>orderNotes.php?oID=<?=$_GET['oID']?>'" class="btn btn-default">Cancel</button>
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
<h2>Manage Orders &raquo; Notes</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>orderNotes.php">Manage Orders</a></span></li>
<li><span>Manage Notes</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act'])){
if($_GET['act']=="1"){
$msgTxt='Note has been added successfully';
}else if($_GET['act']=="2"){
$msgTxt='Note has been updated successfully';
}else if($_GET['act']=="3"){
$msgTxt='Note has been deleted added successfully';
}	
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.$msgTxt.'</strong></div>';
}
?>
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"><a href="<?=$siteUrl;?>orders.php"><b>Back</b></a> | <a href="<?=$siteUrl;?>orderNotes.php?action=add&oID=<?=$_GET['oID']?>"><b>Add New</b></a> </div>
<h2 class="panel-title"><?=orderProdName($_GET['oID'])?> &raquo;  Notes</h2>
</header>
<div class="panel-body">
<div class="form-group">
<div class="col-md-6">
<form class="form-horizontal form-bordered" method="get">
<input type="text" style="width:80%;float:left" class="form-control" name="q" value="<?=htmlspecialchars($search, ENT_QUOTES, 'UTF-8')?>" placeholder="Search note title or comment" id="inputDefault"  >
<input type="hidden" name="oID" value="<?=$_GET['oID'];?>" />
<button class="btn btn-primary" name="submit" value="Submit">GO</button>
</form> <span class="hintTxt">Search notes by title or comment.</span>
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
<th width="55%">Title</th>
<th width="20%">Created On</th>
<th width="20%" class="text-center">Action</th>
</tr>
</thead>
<tbody>
<?php
$orderID = (int)($_GET['oID'] ?? 0);
$page_name = "orderNotes.php?oID=" . $orderID . "&q=" . rawurlencode($search);
$start = max(0, (int)($_GET['start'] ?? 0));

$eu = ($start - 0); 
$limit = 20;          
$this1 = $eu + $limit; 
$back = $eu - $limit; 
$next = $eu + $limit; 
$sqlSrch = "";
if ($search !== '') {
    $safeSearch = mysqli_real_escape_string($conn, $search);
    $sqlSrch = " AND (orderTitle LIKE '%" . $safeSearch . "%' OR orderNote LIKE '%" . $safeSearch . "%') ";
}
$sqlSeller = "SELECT id,orderTitle,createdDate FROM order_notes WHERE orderID='" . $orderID . "' " . $sqlSrch . " ORDER BY id DESC limit $eu, $limit";
$sqltot = "SELECT id FROM order_notes WHERE orderID='" . $orderID . "' " . $sqlSrch . " ORDER BY id DESC";

$resultSeller= mysqli_query($conn,$sqlSeller);
$resulttot=mysqli_query($conn,$sqltot);
$nume=mysqli_num_rows($resulttot);
if (@mysqli_num_rows($resultSeller)!=0){

$sno=$start;
while($rows=mysqli_fetch_assoc($resultSeller)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.$rows['orderTitle'].'</td>
<td>'.viewDtTm($rows['createdDate']).'</td>
<td align="center">
<a title="Edit this record" class="btn btn-default editBtn" href="orderNotes.php?action=edit&id='.$rows['id'].'&oID='.$_GET['oID'].'">Edit</a>
<a title="Delete this record" class="btn btn-default delBtn" onclick="return confirm(\'Are you sure you want to delete?\');" href="orderNotes.php?act=delete&id='.$rows['id'].'&oID='.$_GET['oID'].'">Delete</a>
</td>
</tr>';
}
?>
<tr>
<td colspan="5">
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
<td colspan='5' align='center'>No Data Found.</td>
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
