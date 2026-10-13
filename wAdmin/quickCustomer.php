<?php
include "include/sessionAdmin.php";
include "include/configure.php";

$error = '';
$partyType = $_POST['partyType'] ?? ($_GET['type'] ?? 'customer');
$partyType = $partyType === 'vendor' ? 'vendor' : 'customer';
$typeID = $partyType === 'vendor' ? 2 : 1;
$partyLabel = $partyType === 'vendor' ? 'Vendor' : 'Customer';
$companyName = trim($_POST['companyName'] ?? '');
$applicantName = trim($_POST['applicantName'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($companyName === '' || $applicantName === '') {
        $error = 'Company name and contact person are required.';
    } else {
        $adminID = (int)($_SESSION['adminID'] ?? 0);
        $fields = [
            'bankName' => $_POST['bankName'] ?? '',
            'bankBranch' => $_POST['bankBranch'] ?? '',
            'companyName' => $companyName,
            'applicantName' => $applicantName,
            'address' => $_POST['address'] ?? '',
            'city' => $_POST['city'] ?? '',
            'country' => $_POST['country'] ?? '',
            'mobileNumber' => $_POST['mobileNumber'] ?? '',
            'phoneNumber' => $_POST['mobileNumber'] ?? '',
            'email' => $_POST['email'] ?? '',
            'email2' => $_POST['email2'] ?? '',
            'appAddress' => $_POST['appAddress'] ?? '',
            'appCity' => $_POST['appCity'] ?? '',
            'appCountry' => $_POST['appCountry'] ?? '',
            'appSwiftCode' => $_POST['appSwiftCode'] ?? '',
            'appIBAN' => $_POST['appIBAN'] ?? '',
            'appAccountNo' => $_POST['appAccountNo'] ?? '',
            'moreInfo' => $_POST['moreInfo'] ?? '',
        ];
        $escaped = array_map(static fn($value) => "'" . mysqli_real_escape_string($conn, (string)$value) . "'", $fields);
        $values = array_values($escaped);
        array_splice($values, 2, 0, ["'" . $typeID . "'"]);
        $values[] = "'1'";
        $values[] = "'" . $adminID . "'";
        $values[] = "'0'";
        $values[] = "'" . mysqli_real_escape_string($conn, currDateTimeGmt()) . "'";
        $columns = 'bankName,bankBranch,type,companyName,applicantName,address,city,country,mobileNumber,phoneNumber,email,email2,appAddress,appCity,appCountry,appSwiftCode,appIBAN,appAccountNo,moreInfo,isEnableEmail,assignToID,isOtherCustomer,createdDate';
        $query = "INSERT INTO customers ($columns) VALUES (" . implode(',', $values) . ")";

        if (mysqli_query($conn, $query)) {
            $customerID = (int)mysqli_insert_id($conn);
            $safeCompany = json_encode($companyName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            echo '<!doctype html><html><body><script>window.parent.postMessage({type:"quick-party-created",partyType:' . json_encode($partyType) . ',id:' . $customerID . ',companyName:' . $safeCompany . ',moreInfo:' . json_encode((string)($fields['moreInfo'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . '},window.location.origin);</script><p>' . htmlspecialchars($partyLabel, ENT_QUOTES, 'UTF-8') . ' added. You can close this window.</p></body></html>';
            exit;
        }
        $error = 'Could not save the customer. Please check the details and try again.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?=$siteUrl;?>assets/vendor/bootstrap/css/bootstrap.css">
<style>body{padding:18px;background:#fff}.form-group{margin-bottom:12px}.required-note{color:#777}</style>
</head>
<body>
<div class="container-fluid">
<?php if ($error !== ''): ?><div class="alert alert-danger"><?=htmlspecialchars($error, ENT_QUOTES, 'UTF-8');?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="partyType" value="<?=htmlspecialchars($partyType, ENT_QUOTES, 'UTF-8');?>">
<p class="required-note">Quick <?=strtolower($partyLabel);?> entry. Company name and contact person are required.</p>
<div class="form-group"><label>Company Name *</label><input class="form-control" name="companyName" value="<?=htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8');?>" required></div>
<div class="form-group"><label>Contact Person *</label><input class="form-control" name="applicantName" value="<?=htmlspecialchars($applicantName, ENT_QUOTES, 'UTF-8');?>" required></div>
<div class="form-group"><label>Mobile</label><input class="form-control" name="mobileNumber" value="<?=htmlspecialchars($_POST['mobileNumber'] ?? '', ENT_QUOTES, 'UTF-8');?>"></div>
<div class="form-group"><label>Email</label><input type="email" class="form-control" name="email" value="<?=htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8');?>"></div>
<div class="form-group"><label>Address</label><input class="form-control" name="address" value="<?=htmlspecialchars($_POST['address'] ?? '', ENT_QUOTES, 'UTF-8');?>"></div>
<div class="form-group"><label>City</label><input class="form-control" name="city" value="<?=htmlspecialchars($_POST['city'] ?? '', ENT_QUOTES, 'UTF-8');?>"></div>
<div class="form-group"><label>Country</label><input class="form-control" name="country" value="<?=htmlspecialchars($_POST['country'] ?? '', ENT_QUOTES, 'UTF-8');?>"></div>
<button class="btn btn-primary" type="submit">Save <?=$partyLabel;?></button>
</form>
</div>
</body>
</html>
