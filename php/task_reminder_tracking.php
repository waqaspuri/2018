<?php
/** Ensure cron jobs can safely run only one reminder of each type per task. */
function ensureTaskReminderTracking($conn) {
    $columns = array(
        'firstReminderSentAt' => "ALTER TABLE tasks ADD COLUMN firstReminderSentAt DATETIME NULL",
        'secondReminderSentAt' => "ALTER TABLE tasks ADD COLUMN secondReminderSentAt DATETIME NULL"
    );

    foreach ($columns as $column => $alterSql) {
        $columnName = mysqli_real_escape_string($conn, $column);
        $result = mysqli_query($conn, "SHOW COLUMNS FROM tasks LIKE '$columnName'");
        if ($result && mysqli_num_rows($result) === 0) {
            mysqli_query($conn, $alterSql);
        }
    }
}

ensureTaskReminderTracking($conn);
