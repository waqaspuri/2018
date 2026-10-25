<?php
include "include/sessionAdmin.php";  
include "include/configure.php";
$id = "";
$companyName = "";
$applicantName = "";
$address = "";
$city = "";
$country = "";
$mobileNumber = "";
$phoneNumber = "";
$email = "";
$email2 = "";
$assignToID = "";
$moreInfo = "";
$createdDate = "";
$rowsEdit = array(
'isEnableEmail' => '0',
'isOtherCustomer' => '0'
);

// Download a ready-to-fill CSV template for the contact book.
if (isset($_GET['download']) && $_GET['download'] === 'sample') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="contact-import-sample.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Contact Person', 'Mobile Number', 'Company Name', 'Email', 'Phone Number', 'Address', 'City', 'Country', 'Information'));
    fputcsv($output, array('Ali Khan', '+923001234567', 'Example Company', 'ali@example.com', '', 'Street 1', 'Karachi', 'Pakistan', ''));
    fclose($output);
    exit;
}

// Import contacts from a CSV file. Blank optional text fields are stored empty;
// Assign To uses the existing unassigned integer sentinel (0); Enable is off.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['importContacts'])) {
    $importSummary = array('added' => 0, 'skipped' => 0, 'errors' => array());
    if (!isset($_FILES['contactCsv']) || !is_uploaded_file($_FILES['contactCsv']['tmp_name']) || $_FILES['contactCsv']['error'] !== UPLOAD_ERR_OK) {
        $importSummary['errors'][] = 'Choose a valid CSV file to import.';
    } else {
        $csv = fopen($_FILES['contactCsv']['tmp_name'], 'r');
        $headers = $csv ? fgetcsv($csv) : false;
        $normalizeHeader = static function ($value) {
            return strtolower(preg_replace('/[^a-z0-9]/i', '', trim((string)$value)));
        };
        $headerMap = array();
        if (is_array($headers)) {
            foreach ($headers as $index => $header) {
                $headerMap[$normalizeHeader($header)] = $index;
            }
        }
        $aliases = array(
            'applicantName' => array('contactperson', 'applicantname', 'name'),
            'mobileNumber' => array('mobilenumber', 'mobile', 'whatsapp'),
            'companyName' => array('companyname', 'company'),
            'email' => array('email'), 'phoneNumber' => array('phonenumber', 'phone'),
            'address' => array('address'), 'city' => array('city'), 'country' => array('country'),
            'moreInfo' => array('information', 'moreinfo', 'notes')
        );
        $columnIndexes = array();
        foreach ($aliases as $column => $names) {
            foreach ($names as $name) {
                if (array_key_exists($name, $headerMap)) { $columnIndexes[$column] = $headerMap[$name]; break; }
            }
        }
        if (!isset($columnIndexes['applicantName'], $columnIndexes['mobileNumber'])) {
            $importSummary['errors'][] = 'CSV headers must include Contact Person and Mobile Number.';
        } else {
            $line = 1;
            while (($cells = fgetcsv($csv)) !== false) {
                $line++;
                $row = array();
                foreach ($aliases as $column => $_names) {
                    $row[$column] = isset($columnIndexes[$column]) ? trim((string)($cells[$columnIndexes[$column]] ?? '')) : '';
                }
                if (implode('', $row) === '') { continue; }
                if ($row['applicantName'] === '' || !preg_match('/^\\+923[0-9]{9}$/', $row['mobileNumber'])) {
                    $importSummary['skipped']++;
                    if (count($importSummary['errors']) < 10) {
                        $importSummary['errors'][] = 'Row ' . $line . ' skipped: Contact Person is required and Mobile Number must be E.164 (example: +923001234567).';
                    }
                    continue;
                }
                $dbColumns = array('bankName','bankBranch','isOtherCustomer','assignToID','type','applicantName','companyName','mobileNumber','email','email2','phoneNumber','isEnableEmail','address','city','country','appAddress','appCity','appCountry','appSwiftCode','appIBAN','appAccountNo','moreInfo','createdDate');
                $data = array(
                    'bankName' => '', 'bankBranch' => '', 'isOtherCustomer' => '0', 'assignToID' => '0', 'type' => '3',
                    'applicantName' => $row['applicantName'], 'companyName' => $row['companyName'],
                    'mobileNumber' => $row['mobileNumber'], 'email' => $row['email'], 'email2' => '',
                    'phoneNumber' => $row['phoneNumber'], 'isEnableEmail' => '0',
                    'address' => $row['address'], 'city' => $row['city'], 'country' => $row['country'],
                    'appAddress' => '', 'appCity' => '', 'appCountry' => '', 'appSwiftCode' => '', 'appIBAN' => '',
                    'appAccountNo' => '', 'moreInfo' => $row['moreInfo'], 'createdDate' => currDateTimeGmt()
                );
                $sqlValues = array();
                foreach ($dbColumns as $column) {
                    $value = $data[$column];
                    $sqlValues[] = $value === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, (string)$value) . "'";
                }
                try {
                    mysqli_query($conn, 'INSERT INTO customers (' . implode(',', $dbColumns) . ') VALUES (' . implode(',', $sqlValues) . ')');
                    $importSummary['added']++;
                } catch (mysqli_sql_exception $exception) {
                    $importSummary['skipped']++;
                    if (count($importSummary['errors']) < 10) { $importSummary['errors'][] = 'Row ' . $line . ' could not be saved: ' . $exception->getMessage(); }
                }
            }
        }
        if ($csv) { fclose($csv); }
    }
}

if(isset($_POST['submit']) && $_POST['submit']=="Submit"){
if(isset($_POST['action']) && $_POST['action']=="add"){
mysqli_query($conn,"INSERT INTO customers (bankName,bankBranch,isOtherCustomer,assignToID,type,applicantName,companyName,mobileNumber,email,email2,phoneNumber,isEnableEmail,address,city,country,appAddress,appCity,appCountry,appSwiftCode,appIBAN,appAccountNo,moreInfo,createdDate) VALUES ('','','".mysqli_real_escape_string($conn,($_POST['isOtherCustomer'] ?? '0'))."','".mysqli_real_escape_string($conn,($_POST['assignToID'] ?? ''))."','3','".mysqli_real_escape_string($conn,($_POST['applicantName'] ?? ''))."','".mysqli_real_escape_string($conn,($_POST['companyName'] ?? ''))."','".mysqli_real_escape_string($conn,($_POST['mobileNumber'] ?? ''))."','".mysqli_real_escape_string($conn,($_POST['email'] ?? ''))."','".mysqli_real_escape_string($conn,($_POST['email2'] ?? ''))."','".mysqli_real_escape_string($conn,($_POST['phoneNumber'] ?? ''))."','".mysqli_real_escape_string($conn,($_POST['isEnableEmail'] ?? '0'))."','".mysqli_real_escape_string($conn,($_POST['address'] ?? ''))."','".mysqli_real_escape_string($conn,($_POST['city'] ?? ''))."','".mysqli_real_escape_string($conn,($_POST['country'] ?? ''))."','','','','','','','".mysqli_real_escape_string($conn,($_POST['moreInfo'] ?? ''))."','".currDateTimeGmt()."')");
header("location: contactBook.php?act=1");
}else if(isset($_POST['action']) && $_POST['action']=="update"){
mysqli_query($conn,"UPDATE customers SET isOtherCustomer='". mysqli_real_escape_string($conn,($_POST['isOtherCustomer'] ?? '0'))."',assignToID='". mysqli_real_escape_string($conn,($_POST['assignToID'] ?? ''))."',applicantName='". mysqli_real_escape_string($conn,($_POST['applicantName'] ?? ''))."',companyName='". mysqli_real_escape_string($conn,($_POST['companyName'] ?? ''))."',mobileNumber='". mysqli_real_escape_string($conn,($_POST['mobileNumber'] ?? ''))."',email='". mysqli_real_escape_string($conn,($_POST['email'] ?? ''))."',phoneNumber='". mysqli_real_escape_string($conn,($_POST['phoneNumber'] ?? ''))."',isEnableEmail='". mysqli_real_escape_string($conn,($_POST['isEnableEmail'] ?? '0'))."',address='". mysqli_real_escape_string($conn,($_POST['address'] ?? ''))."',city='". mysqli_real_escape_string($conn,($_POST['city'] ?? ''))."',country='". mysqli_real_escape_string($conn,($_POST['country'] ?? ''))."',moreInfo='". mysqli_real_escape_string($conn,($_POST['moreInfo'] ?? ''))."' WHERE id='".mysqli_real_escape_string($conn,($_POST['id'] ?? ''))."'");	
header("location: contactBook.php?act=2");
}
}
if(isset($_GET['act']) && $_GET['act']=="delete"){
mysqli_query($conn,"DELETE FROM customers WHERE id='".mysqli_real_escape_string($conn,($_GET['id'] ?? ''))."'");	
header("location: contactBook.php?act=3");
}
$action = "add";
$hdTxt="Add New";
if(isset($_GET['action']) && $_GET['action']=="edit"){
$sqlEdit = mysqli_query($conn,"SELECT * FROM customers WHERE id='".mysqli_real_escape_string($conn,($_GET['id'] ?? ''))."'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
if (!is_array($rowsEdit)) {
$rowsEdit = array();
}
$rowsEdit = array_merge(array('isEnableEmail' => '0', 'isOtherCustomer' => '0'), $rowsEdit);
extract($rowsEdit);
$action = "update";
$hdTxt="Update";
}
include "include/header.php"; 
if(isset($_GET['action']) && $_GET['action']=="view"){ 
$sqlEdit = mysqli_query($conn,"SELECT * FROM customers WHERE id='".mysqli_real_escape_string($conn,($_GET['id'] ?? ''))."'");
$rowsEdit = mysqli_fetch_assoc($sqlEdit);
if (!is_array($rowsEdit)) {
$rowsEdit = array();
}
$rowsEdit = array_merge(array('isEnableEmail' => '0', 'isOtherCustomer' => '0'), $rowsEdit);
extract($rowsEdit);
?>
<section role="main" class="content-body">
<header class="page-header">
<h2>Manage Contact Book</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>contactBook.php">Manage Contacts Book</a></span></li>
<li><span>View Contact Book</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>contactBook.php"><b>Back</b></a> </div>
<h2 class="panel-title">View Contact Books</h2>
</header>
<div class="panel-body viewFrm">
<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Company Name</label>
<div class="col-md-8"><?=$companyName?></div>
</div>
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Contact Person</label>
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
<label class="col-md-4 control-label" for="inputDefault">Country</label>
<div class="col-md-8"><?=$country?></div>
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Mobile No</label>
<div class="col-md-8"><?=$mobileNumber?></div>
</div>
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Phone No</label>
<div class="col-md-8"><?=$phoneNumber?></div>
</div>
</div>
<div class="form-group">
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Email</label>
<div class="col-md-8"><?=$email?></div>
    </div>
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Enable</label>
<div class="col-md-8"><input type="checkbox" name="isEnableEmail" value="1" <?=($rowsEdit['isEnableEmail']=='1')?'checked="checked"':'';?> > On Email Notification 
</div>
</div>
</div>
<div class="form-group">
<div class="col-md-12">
<label class="col-md-2 control-label" for="inputDefault">Others Task Assign</label>
<div class="col-md-8">
<div class="paddingTxt"><input type="checkbox" name="isOtherCustomer" value="1" <?=($rowsEdit['isOtherCustomer']=='1')?'checked="checked"':'';?> > Assign in list of Other Customer </div>
</div>
</div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">&nbsp;</label>
<div class="col-md-9"><b>About Details</b></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Assign To </label>
<div class="col-md-9"><?=((string)$assignToID === '0' || $assignToID === '') ? 'Unassigned' : assignToName($assignToID)?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Information </label>
<div class="col-md-9"><?=$moreInfo?></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Created Date</label>
<div class="col-md-9"><?=viewDtTm($createdDate)?></div>
</div>
<div class="form-group">
<label class="col-md-5 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<button type="button" onclick="window.location.href='<?=$siteUrl;?>contactBook.php'" class="btn btn-default">Back</button>
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
<h2>Manage Contact Book</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span><a href="<?=$siteUrl;?>contactBook.php">Manage Contacts Book</a></span></li>
<li><span><?=$hdTxt;?> Contact Book</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>contactBook.php"><b>Back</b></a> </div>
<h2 class="panel-title"><?=$hdTxt;?> Contact Books</h2>
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
<label class="col-md-3 control-label" for="inputDefault" style="padding-left:0;padding-right:0;">Contact Person*</label>
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
<input type="text" class="form-control" name="city" value="<?=$city?>" id="inputDefault" >
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
<div class="col-md-6">
<label class="col-md-4 control-label" for="inputDefault">Mobile No *</label>
<div class="col-md-8">
<input type="text" class="form-control" name="mobileNumber" value="<?=$mobileNumber?>" id="inputDefault" required >
</div>
</div>
<div class="col-md-6">
<label class="col-md-3 control-label" for="inputDefault">Phone No</label>
<div class="col-md-8">
<input type="text" class="form-control" name="phoneNumber" value="<?=$phoneNumber?>" id="inputDefault" >
</div>
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
<label class="col-md-3 control-label" for="inputDefault">Enable</label>
<div class="col-md-8">
<div class="paddingTxt"><input type="checkbox" name="isEnableEmail" value="1" <?=($rowsEdit['isEnableEmail']=='1')?'checked="checked"':'';?> > On Email Notification </div>
</div>
</div>
</div>
<div class="form-group">
<div class="col-md-12">
<label class="col-md-2 control-label" for="inputDefault">Others Task Assign</label>
<div class="col-md-8">
<div class="paddingTxt"><input type="checkbox" name="isOtherCustomer" value="1" <?=($rowsEdit['isOtherCustomer']=='1')?'checked="checked"':'';?> > Assign in list of Other Customer </div>
</div>
</div>
</div>

<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">&nbsp;</label>
<div class="col-md-9"><b>About Details</b></div>
</div>
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Assign To </label>
<div class="col-md-9 selectSrch">
<select class="selectpicker form-control" data-selected-text-format="values" name="assignToID" id="assignToID" data-live-search="true" data-live-search-placeholder="Search" >
<option value="">- Select -</option>
<?php
$sqlVndrCtmr = mysqli_query($conn,"SELECT id,companyName,type FROM customers WHERE type<>'3' ORDER BY companyName ASC");
while($rowsVC = mysqli_fetch_assoc($sqlVndrCtmr)){						
echo '<option value="'.$rowsVC['id'].'" '.(($rowsVC['id']==$assignToID)?'selected="selected"':'').' >'.$rowsVC['companyName'].' ('.(($rowsVC['type']=='1')?'Customer':'Vendor').') </option>';
}
?>
</select>
</div>
</div>
    
<div class="form-group">
<label class="col-md-2 control-label" for="inputDefault">Information </label>
<div class="col-md-9">
<textarea cols="2" rows="2" name="moreInfo" class="form-control"><?=$moreInfo?></textarea>
</div>
</div>

<div class="form-group">
<label class="col-md-5 control-label" for="inputDisabled">&nbsp;</label>
<div class="col-md-6">
<input type="hidden" name="action" value="<?=$action;?>" />
<input type="hidden" name="id" value="<?=$id;?>" />
<button class="btn btn-primary" name="submit" value="Submit">Submit</button>
<button type="button" onclick="window.location.href='<?=$siteUrl;?>contactBook.php'" class="btn btn-default">Cancel</button>
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
<h2>Manage Contact Book</h2>
<div class="right-wrapper pull-right">
<ol class="breadcrumbs">
<li> <a href="<?=$siteUrl;?>"> <i class="fa fa-home"></i> </a> </li>
<li><span>Manage Contact Book</span></li>
</ol>
<a >&nbsp;</a></div>
</header>
<?php if (isset($importSummary)): ?>
<div class="alert alert-info">
<strong>Import finished:</strong> <?= (int)$importSummary['added']; ?> added, <?= (int)$importSummary['skipped']; ?> skipped.
<?php if (!empty($importSummary['errors'])): ?><ul><?php foreach ($importSummary['errors'] as $importError): ?><li><?=htmlspecialchars($importError, ENT_QUOTES, 'UTF-8');?></li><?php endforeach; ?></ul><?php endif; ?>
</div>
<?php endif; ?>
<?php
if(isset($_GET['act'])){
$msgTxt='';
if($_GET['act']=="1"){
$msgTxt='New Contact Book has been added successfully';
}else if($_GET['act']=="2"){
$msgTxt='Contact Book has been updated successfully';
}else if($_GET['act']=="3"){
$msgTxt='Contact Book has been deleted successfully';
}	
if($msgTxt!=""){
echo '<div class="alert alert-info"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">x</button><strong>'.$msgTxt.'</strong></div>';
}
}
?>
<section class="panel">
<header class="panel-heading">
<div class="panel-actions"> <a href="<?=$siteUrl;?>contactBook.php?action=add"><b>Add New</b></a> </div>
<h2 class="panel-title">Manage Contact Book</h2>
</header>
<div class="panel-body">
<div class="well">
<h4>Import contacts from CSV</h4>
<p>Required columns: <strong>Contact Person</strong> and <strong>Mobile Number</strong>. Mobile numbers must use Pakistan E.164 format, for example <code>+923001234567</code>. Rows missing either required value, or with an invalid mobile number, are skipped. Optional blank cells are left empty. Others Task Assign defaults to No; Assign To defaults to unassigned and Enable defaults to Off.</p>
<p><a class="btn btn-default" href="<?=$siteUrl;?>contactBook.php?download=sample">Download sample CSV</a></p>
<form method="post" enctype="multipart/form-data" class="form-inline">
<input type="file" name="contactCsv" accept=".csv,text/csv" required>
<button type="submit" name="importContacts" value="1" class="btn btn-primary">Import CSV</button>
</form>
</div>
<div class="form-group">
<div class="col-md-6">
<form class="form-horizontal form-bordered" method="get">
<input type="text" style="width:80%;float:left" class="form-control" name="q" value="<?=(isset($_GET['q'])?$_GET['q']:'')?>" placeholder="Search" id="inputDefault"  >
<button class="btn btn-primary" name="submit" value="Submit">GO</button>
</form> <span class="hintTxt">Search :-  Company Name,Name.</span>
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
<th width="15%">Mobile</th>
<th width="20%">Address</th>
<th width="18%">Created On</th>
<th width="22%" class="text-center">Action</th>
</tr>
</thead>
<tbody>
<?php
$page_name="contactBook.php?q=".(isset($_GET['q']) ? $_GET['q'] : ''); 
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
$q = mysqli_real_escape_string($conn,$_GET['q']);
$sqlSrch = " and (companyName LIKE '%".$q."%' OR applicantName LIKE '%".$q."%' OR mobileNumber LIKE '%".$q."%' OR email LIKE '%".$q."%') ";
}
$sqlSeller = "SELECT companyName,id,applicantName,address,city,country,mobileNumber,createdDate FROM customers WHERE id<>'47' and type='3' ".$sqlSrch." ORDER BY id DESC limit $eu, $limit";
$sqltot = "SELECT id FROM customers WHERE type='3' ".$sqlSrch." ORDER BY id DESC ";

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
<td>'.$rows['mobileNumber'].'</td>
<td>'.$rows['address'].' '.(($rows['city']!="")?', '.$rows['city']:'').' '.(($rows['country']!="")?', '.$rows['country']:'').'</td>
<td align="center">'.viewDtTm($rows['createdDate']).'</td>
<td align="center">
<a title="View this record" class="btn btn-default viewBtn" href="contactBook.php?action=view&id='.$rows['id'].'">View</a>
<a title="Edit this record" class="btn btn-default editBtn" href="contactBook.php?action=edit&id='.$rows['id'].'">Edit</a>
<a title="Delete this record" class="btn btn-default delBtn" onclick="return confirm(\'Are you sure you want to delete?\');" href="contactBook.php?act=delete&id='.$rows['id'].'">Delete</a>
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
