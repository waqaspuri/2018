<?php
include "include/sessionAdmin.php";
include "include/configure.php";
if(isset($_POST['submit']) && $_POST['submit']=="Submit"){
if(isset($_POST['action']) && $_POST['action']=="update"){
mysqli_query($conn,"UPDATE pos_settings SET ntn='". mysqli_real_escape_string($conn,$_POST['ntn'])."',supperName='". mysqli_real_escape_string($conn,$_POST['supperName'])."',address='". mysqli_real_escape_string($conn,$_POST['address'])."',telephone='". mysqli_real_escape_string($conn,$_POST['telephone'])."',gst='". mysqli_real_escape_string($conn,$_POST['gst'])."' where id='1'");	
header("location: posSettings.php?act=2");
}
}
$sqlEdit = mysqli_query($conn,"SELECT * FROM pos_settings WHERE id='1'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
$action = "update";
include "include/header.php"; 
?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage POS Settings</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span>Manage POS Settings</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php
if(isset($_GET['act']) && $_GET['act']==2){
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>Settings has been updated successfully.</strong></div>';
}
?>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<h2 class="panel-title">Manage POS Settings</h2>
</header>
<div class="panel-body">
<form class="form-horizontal form-bordered" method="post">
<div class="form-group">
<label class="col-md-3 control-label" for="inputDefault">Name</label>
<div class="col-md-6">
<input type="text" class="form-control" name="supperName" value="<?=$rowsEdit['supperName']?>" id="inputDefault" required >
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputHelpText">Address</label>
<div class="col-md-6">
<input type="text" class="form-control" name="address" value="<?=$rowsEdit['address']?>" id="inputDefault" required >
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputHelpText">Telephone</label>
<div class="col-md-6">
<input type="text" class="form-control" name="telephone" value="<?=$rowsEdit['telephone']?>" id="inputDefault" required >
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputHelpText">GST</label>
<div class="col-md-6">
<input type="text" class="form-control" name="gst" value="<?=$rowsEdit['gst']?>" id="inputDefault" required >
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDefault">NTN</label>
<div class="col-md-6">
<input type="text" class="form-control" name="ntn" value="<?=$rowsEdit['ntn']?>" id="inputDefault" >
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<input type="hidden" name="action" value="<?=$action;?>" />
<button class="btn btn-primary" name="submit" value="Submit">Submit</button>
<button type="button" onclick="window.location.href='<?=$siteUrl;?>'" class="btn btn-default">Cancel</button>
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
<?php include "include/footer.php"; ?>