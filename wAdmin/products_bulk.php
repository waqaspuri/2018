<?php
include "include/sessionAdmin.php";
include "include/configure.php";

$esc = function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$notice = '';
$error = '';
$rowErrors = array();
$vendorID = (int)($_POST['assignToVendor'] ?? 0);
$csvText = trim((string)($_POST['csv_text'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $vendorSQL = mysqli_query($conn, "SELECT id,companyName FROM customers WHERE id='" . $vendorID . "' AND type='2' LIMIT 1");
    $vendor = $vendorSQL ? mysqli_fetch_assoc($vendorSQL) : false;

    if (!$vendor) {
        $error = 'Please select a valid vendor.';
    } elseif ((!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) && $csvText === '') {
        $uploadErrors = array(
            UPLOAD_ERR_INI_SIZE => 'the file exceeds the server upload limit',
            UPLOAD_ERR_FORM_SIZE => 'the file exceeds the form upload limit',
            UPLOAD_ERR_PARTIAL => 'the file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'no file was selected',
            UPLOAD_ERR_NO_TMP_DIR => 'the server temporary upload folder is missing',
            UPLOAD_ERR_CANT_WRITE => 'the server could not write the uploaded file',
            UPLOAD_ERR_EXTENSION => 'a server extension stopped the upload'
        );
        if (!isset($_FILES['csv_file'])) {
            $requestType = $_SERVER['CONTENT_TYPE'] ?? 'missing';
            $fileSetting = ini_get('file_uploads') ?: 'unknown';
            $error = 'No file was received. Choose a CSV file or paste the CSV content below. Debug: request type=' . $requestType . '; file_uploads=' . $fileSetting . '.';
        } else {
            $uploadCode = (int)$_FILES['csv_file']['error'];
            $error = 'CSV upload failed: ' . ($uploadErrors[$uploadCode] ?? 'unknown upload error') . ' (code ' . $uploadCode . '). You can paste the CSV content below instead.';
        }
    } else {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        } else {
            $handle = tmpfile();
            fwrite($handle, $csvText);
            rewind($handle);
        }
        $parseCsvRow = function ($row) {
            if (is_array($row) && count($row) === 1 && strpos((string)$row[0], ',') !== false) {
                $singleValue = trim((string)$row[0]);
                if (strlen($singleValue) >= 2 && $singleValue[0] === '"' && substr($singleValue, -1) === '"') {
                    $singleValue = substr($singleValue, 1, -1);
                }
                return str_getcsv($singleValue, ',');
            }
            return $row;
        };
        $header = $handle ? $parseCsvRow(fgetcsv($handle, 0, ',')) : false;

        if (!$header) {
            $error = 'The CSV file is empty or could not be read.';
        } else {
            $columns = array();
            foreach ($header as $index => $column) {
                $column = strtolower(trim((string)$column));
                $column = preg_replace('/^\xEF\xBB\xBF/', '', $column);
                $columns[$column] = $index;
            }

            $nameColumn = $columns['prodname'] ?? ($columns['product_name'] ?? ($columns['name'] ?? null));
            if ($nameColumn === null) {
                $error = 'The CSV must contain a prodName column.';
            } else {
                $inserted = 0;
                $rowNumber = 1;
                while (($row = fgetcsv($handle, 0, ',')) !== false) {
                    $row = $parseCsvRow($row);
                    $rowNumber++;
                    $getValue = function ($name) use ($columns, $row) {
                        return isset($columns[$name], $row[$columns[$name]]) ? trim((string)$row[$columns[$name]]) : '';
                    };

                    $prodName = trim((string)($row[$nameColumn] ?? ''));
                    if ($prodName === '') {
                        if (count(array_filter($row, function ($value) { return trim((string)$value) !== ''; })) > 0) {
                            $rowErrors[] = 'Row ' . $rowNumber . ': product name is missing.';
                        }
                        continue;
                    }

                    $values = array(
                        'hsCode' => $getValue('hscode'),
                        'prodUMO' => $getValue('produmo'),
                        'prodQty' => $getValue('prodqty'),
                        'prodOrigin' => $getValue('prodorigin'),
                        'prodPackaging' => $getValue('prodpackaging'),
                        'prodDesc' => $getValue('proddesc')
                    );
                    $insertSQL = "INSERT INTO products (hsCode,prodUMO,assignToVendor,prodName,prodQty,prodOrigin,prodPackaging,prodDesc,createdDate) VALUES ('"
                        . mysqli_real_escape_string($conn, $values['hsCode']) . "','"
                        . mysqli_real_escape_string($conn, $values['prodUMO']) . "','"
                        . $vendorID . "','"
                        . mysqli_real_escape_string($conn, $prodName) . "','"
                        . mysqli_real_escape_string($conn, $values['prodQty']) . "','"
                        . mysqli_real_escape_string($conn, $values['prodOrigin']) . "','"
                        . mysqli_real_escape_string($conn, $values['prodPackaging']) . "','"
                        . mysqli_real_escape_string($conn, $values['prodDesc']) . "','"
                        . mysqli_real_escape_string($conn, currDateTimeGmt()) . "')";

                    if (mysqli_query($conn, $insertSQL)) {
                        $inserted++;
                    } else {
                        $rowErrors[] = 'Row ' . $rowNumber . ': could not be imported.';
                    }
                }
                fclose($handle);
                $notice = $inserted . ' product(s) added to ' . $vendor['companyName'] . '.';
            }
        }
        if (is_resource($handle)) {
            fclose($handle);
        }
    }
}

$vendorsSQL = mysqli_query($conn, "SELECT id,companyName FROM customers WHERE type='2' ORDER BY companyName ASC");
include "include/header.php";
?>
<section role="main" class="content-body">
<header class="page-header">
    <h2>Manage Products</h2>
    <div class="right-wrapper pull-right">
        <ol class="breadcrumbs">
            <li><a href="<?=$siteUrl;?>"><i class="fa fa-home"></i></a></li>
            <li><span><a href="<?=$siteUrl;?>products.php">Manage Products</a></span></li>
            <li><span>Bulk Add Products</span></li>
        </ol>
    </div>
</header>
<div class="row">
<div class="col-lg-12">
<section class="panel">
<header class="panel-heading">
    <div class="panel-actions"><a href="<?=$siteUrl;?>products.php"><b>Back</b></a></div>
    <h2 class="panel-title">Bulk Add Products</h2>
</header>
<div class="panel-body">
<?php if ($notice): ?><div class="alert alert-success"><?=$esc($notice)?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?=$esc($error)?></div><?php endif; ?>
<?php if ($rowErrors): ?><div class="alert alert-warning"><?=implode('<br>', array_map($esc, $rowErrors))?></div><?php endif; ?>

<form class="form-horizontal form-bordered" method="post" enctype="multipart/form-data">
    <div class="form-group">
        <label class="col-md-3 control-label">Vendor *</label>
        <div class="col-md-7 selectSrch">
            <select class="selectpicker form-control" data-live-search="true" name="assignToVendor" required>
                <option value="">- Select Vendor -</option>
                <?php while ($vendorRow = $vendorsSQL ? mysqli_fetch_assoc($vendorsSQL) : false): ?>
                    <option value="<?=$esc($vendorRow['id'])?>" <?=$vendorID == $vendorRow['id'] ? 'selected' : ''?>><?=$esc($vendorRow['companyName'])?></option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>
    <div class="form-group">
        <label class="col-md-3 control-label">CSV File or Paste *</label>
        <div class="col-md-7">
            <input type="file" class="form-control" name="csv_file" accept=".csv,text/csv">
            <span class="hintTxt">Comma-separated CSV. Required column: prodName. You may paste the CSV below if file upload is unavailable.</span>
        </div>
    </div>
    <div class="form-group">
        <label class="col-md-3 control-label">Or Paste CSV</label>
        <div class="col-md-7">
            <textarea class="form-control" name="csv_text" rows="5" placeholder="prodName,prodQty,prodUMO,prodOrigin,prodPackaging,hsCode,prodDesc&#10;Sodium Bisulphate,25,MT,Turkey,25kg Bags,2833.19,Industrial grade sodium bisulphate"></textarea>
        </div>
    </div>
    <div class="form-group">
        <label class="col-md-3 control-label">CSV Columns</label>
        <div class="col-md-7">
            <code>prodName,prodQty,prodUMO,prodOrigin,prodPackaging,hsCode,prodDesc</code>
        </div>
    </div>
    <div class="form-group">
        <div class="col-md-offset-3 col-md-7">
            <button class="btn btn-primary" type="submit">Import Products</button>
            <a href="<?=$siteUrl;?>products.php" class="btn btn-default">Cancel</a>
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
