<?php
include "include/sessionAdmin.php";  
include "include/configure.php";
$search = trim((string)($_GET['q'] ?? ''));
if(isset($_GET['toDo']) && $_GET['toDo']=="mark"){
mysqli_query($conn,"UPDATE to_do SET isDone='1' WHERE id='".$_GET['id']."'");	
header("location: toDo.php?act=2");
}
if(isset($_POST['submit']) && $_POST['submit']=="Submit"){
if(isset($_POST['action']) && $_POST['action']=="add"){
mysqli_query($conn,"INSERT INTO to_do (title,description,isDone,createdDate) VALUES ('".mysqli_real_escape_string($conn,$_POST['title'])."','".mysqli_real_escape_string($conn,$_POST['description'])."','0','".currDateTimeGmt()."')");
header("location: toDo.php?act=1");
}else if(isset($_POST['action']) && $_POST['action']=="update"){
mysqli_query($conn,"UPDATE to_do SET title='". mysqli_real_escape_string($conn,$_POST['title'])."',description='". mysqli_real_escape_string($conn,$_POST['description'])."' WHERE id='".$_POST['id']."'");	
header("location: toDo.php?act=2");
}
}
if(isset($_GET['act']) && $_GET['act']=="delete"){
mysqli_query($conn,"DELETE FROM to_do WHERE id='".$_GET['id']."'");	
header("location: toDo.php?act=3");
}
$action = "add";
$hdTxt="Add New";
$title = '';
$description = '';
$id = 0;
if(isset($_GET['action']) && $_GET['action']=="edit"){
$sqlEdit = mysqli_query($conn,"SELECT * FROM to_do WHERE id='".$_GET['id']."'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
if ($rowsEdit) {
    $title = (string)($rowsEdit['title'] ?? '');
    $description = (string)($rowsEdit['description'] ?? '');
    $id = (int)($rowsEdit['id'] ?? 0);
    $action = "update";
    $hdTxt="Update";
}
}
include "include/header.php"; 
if(isset($_GET['action']) && $_GET['action']=="view"){ 
$sqlEdit = mysqli_query($conn,"SELECT * FROM to_do WHERE id='".$_GET['id']."'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
extract($rowsEdit);
?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Quick Notes</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>toDo.php">Manage Quick Notes</a></span></li>
<li><span>View Quick Notes</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>toDo.php"><b>Back</b></a> </div>
<h2 class="panel-title">View Quick Notess</h2>
</header>
<div class="panel-body viewFrm">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Title</label>
<div class="col-md-8"><?=htmlspecialchars((string)($title ?? ''), ENT_QUOTES, 'UTF-8')?></div>
</div>
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Description</label>
<div class="col-md-8"><?=nl2br(htmlspecialchars((string)($description ?? ''), ENT_QUOTES, 'UTF-8'))?>
</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Created Date</label>
<div class="col-md-9"><?=viewDtTm($createdDate)?></div>
</div>
<div class="form-group">
<label class="col-md-5 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<button type="button" onclick="window.location.href='<?=$siteUrl;?>toDo.php'" class="btn btn-default">Back</button>
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
}elseif (isset($_GET['action']) && in_array($_GET['action'], array('add', 'edit'), true)){
?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Quick Notes</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>toDo.php">Manage Quick Notes</a></span></li>
<li><span><?=$hdTxt;?> Quick Notes</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>toDo.php"><b>Back</b></a> </div>
<h2 class="panel-title"><?=$hdTxt;?> Quick Notess</h2>
</header>
<div class="panel-body">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Title *</label>
<div class="col-md-9">
<input type="text" class="form-control" name="title" value="<?=$title?>" id="inputDefault" required >
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Description </label>
<div class="col-md-9">
<textarea cols="2" rows="8" name="description" class="form-control"><?=$description?></textarea>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-8">
<input type="hidden" name="action" value="<?=$action;?>" />
<input type="hidden" name="id" value="<?=$id;?>" />
<button class="btn btn-primary" name="submit" value="Submit">Submit</button>
<button type="button" onclick="window.location.href='<?=$siteUrl;?>toDo.php'" class="btn btn-default">Cancel</button>
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
<h2>Manage Quick Notes</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span>Manage Quick Notes</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act'])){
if($_GET['act']=="1"){
$msgTxt='New Quick Notes has been added successfully';
}else if($_GET['act']=="2"){
$msgTxt='Quick Notes has been updated successfully';
}else if($_GET['act']=="3"){
$msgTxt='Quick Notes has been deleted successfully';
}	
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.$msgTxt.'</strong></div>';
}
?>
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>toDo.php?action=add"><b>Add New</b></a> </div>
<h2 class="panel-title">Manage Quick Notes</h2>
</header>
<div class="panel-body">
<div class="form-group">
<div class="col-md-6">
<form class="form-horizontal form-bordered" method="get">
<input type="text" style="width:80%;float:left" class="form-control" name="q" value="<?=htmlspecialchars($search, ENT_QUOTES, 'UTF-8')?>" placeholder="Search" id="inputDefault"  >
<button class="btn btn-primary" name="submit" value="Submit">GO</button>
</form> <span class="hintTxt">Search :-  title.</span>
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
<th width="20%">Title</th>
<th width="35%">Description</th>
<th width="18%">Created On</th>
<th width="22%" class="text-center">Action</th>
</tr>
</thead>
<tbody>
<?php
$page_name = "toDo.php?q=" . rawurlencode($search);
$start = max(0, (int)($_GET['start'] ?? 0));

$eu = ($start - 0); 
$limit = 20;          
$this1 = $eu + $limit; 
$back = $eu - $limit; 
$next = $eu + $limit; 
$sqlSrch = "";
if ($search !== '') {
    $safeSearch = mysqli_real_escape_string($conn, $search);
    $sqlSrch = " WHERE title LIKE '%" . $safeSearch . "%' ";
}
$sqlSeller = "SELECT * FROM to_do ".$sqlSrch." ORDER BY id DESC limit $eu, $limit";
$sqltot = "SELECT id FROM to_do ".$sqlSrch." ORDER BY id DESC ";

$resultSeller= mysqli_query($conn,$sqlSeller);
$resulttot=mysqli_query($conn,$sqltot);
$nume=mysqli_num_rows($resulttot);
if (@mysqli_num_rows($resultSeller)!=0){

$sno=$start;
while($rows=mysqli_fetch_assoc($resultSeller)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.$rows['title'].'
<p>'.(($rows['isDone'])?'<span class="grnTxt">Mark as completed</span>':'<a href="toDo.php?toDo=mark&id='.$rows['id'].'" class="redTxt">Mark as complete</a>').'</p>
</td>
<td>'.stringTxtLimit($rows['description'],100).'</td>
<td align="center">'.viewDtTm($rows['createdDate']).'</td>
<td align="center">
<a title="View this record" class="btn btn-default viewBtn" href="toDo.php?action=view&id='.$rows['id'].'">View</a>
<a title="Edit this record" class="btn btn-default editBtn" href="toDo.php?action=edit&id='.$rows['id'].'">Edit</a>
<a title="Delete this record" class="btn btn-default delBtn" onclick="return confirm(\'Are you sure you want to delete?\');" href="toDo.php?act=delete&id='.$rows['id'].'">Delete</a>
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
