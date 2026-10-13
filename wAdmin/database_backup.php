<?php
require_once "include/sessionAdmin.php";
require_once "include/configure.php";

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    @set_time_limit(0);
    @ini_set('memory_limit', '-1');
    while (ob_get_level() > 0) ob_end_clean();

    $filename = 'mysql-backup-' . date('Y-m-d-His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    echo "-- MySQL database backup\n-- Generated: " . date('c') . "\n\n";
    echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    $tablesResult = mysqli_query($conn, 'SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
    if (!$tablesResult) {
        echo "-- Unable to list database tables: " . mysqli_real_escape_string($conn, mysqli_error($conn)) . "\n";
        exit;
    }

    while ($tableRow = mysqli_fetch_row($tablesResult)) {
        $table = $tableRow[0];
        $quotedTable = '`' . str_replace('`', '``', $table) . '`';
        $createResult = mysqli_query($conn, 'SHOW CREATE TABLE ' . $quotedTable);
        if (!$createResult || !($createRow = mysqli_fetch_assoc($createResult))) continue;
        echo "DROP TABLE IF EXISTS " . $quotedTable . ";\n" . $createRow['Create Table'] . ";\n\n";

        $dataResult = mysqli_query($conn, 'SELECT * FROM ' . $quotedTable, MYSQLI_USE_RESULT);
        if (!$dataResult) continue;
        $columns = array();
        foreach ($dataResult->fetch_fields() as $field) $columns[] = '`' . str_replace('`', '``', $field->name) . '`';
        $columnSQL = implode(',', $columns);
        while ($row = mysqli_fetch_row($dataResult)) {
            $values = array();
            foreach ($row as $value) {
                $values[] = $value === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $value) . "'";
            }
            echo 'INSERT INTO ' . $quotedTable . ' (' . $columnSQL . ') VALUES (' . implode(',', $values) . ");\n";
        }
        mysqli_free_result($dataResult);
        echo "\n";
    }
    mysqli_free_result($tablesResult);
    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
}

include "include/header.php";
?>
<section role="main" class="content-body">
    <header class="page-header">
        <h2>MySQL Backup</h2>
        <div class="right-wrapper pull-right">
            <ol class="breadcrumbs"><li><a href="<?=$siteUrl?>"><i class="fa fa-home"></i></a></li><li><span>MySQL Backup</span></li></ol>
        </div>
    </header>
    <div class="row"><div class="col-lg-12"><section class="panel">
        <header class="panel-heading"><h2 class="panel-title">Download Database Backup</h2></header>
        <div class="panel-body">
            <p>Download a SQL backup of the database.</p>
            <form method="post">
                <button class="btn btn-primary" type="submit"><i class="fa fa-download"></i> Download SQL Backup</button>
            </form>
        </div>
    </section></div></div>
</section>
</div>
</section>
<?php include "include/footer.php"; ?>
