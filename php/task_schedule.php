<?php

function ensureTaskScheduleSchema($conn)
{
    foreach (array('dueTime', 'repeatTime') as $column) {
        $result = mysqli_query($conn, "SHOW COLUMNS FROM tasks LIKE '" . $column . "'");
        if ($result && mysqli_num_rows($result) === 0) {
            $after = $column === 'dueTime' ? 'dueDate' : 'repeatDate';
            mysqli_query($conn, "ALTER TABLE tasks ADD COLUMN " . $column . " TIME NULL DEFAULT NULL AFTER " . $after);
        }
    }

    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS cron_run_log (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        source VARCHAR(20) NOT NULL,
        startedAt DATETIME NOT NULL,
        finishedAt DATETIME NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'running',
        PRIMARY KEY (id),
        KEY finishedAt (finishedAt)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $timezoneColumn = mysqli_query($conn, "SHOW COLUMNS FROM admin LIKE 'taskTimezone'");
    if ($timezoneColumn && mysqli_num_rows($timezoneColumn) === 0) {
        mysqli_query($conn, "ALTER TABLE admin ADD COLUMN taskTimezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Karachi' AFTER adminEmail");
    }
}

function taskScheduleTimezone($conn)
{
    $timezone = 'Asia/Karachi';
    $result = mysqli_query($conn, "SELECT taskTimezone FROM admin WHERE id=1 LIMIT 1");
    if ($result && ($row = mysqli_fetch_assoc($result)) && !empty($row['taskTimezone'])) {
        $candidate = (string)$row['taskTimezone'];
        if (in_array($candidate, timezone_identifiers_list(), true)) {
            $timezone = $candidate;
        }
    }
    return $timezone;
}

function taskScheduleNow($conn)
{
    return new DateTimeImmutable('now', new DateTimeZone(taskScheduleTimezone($conn)));
}

function taskScheduleDateTime($conn, $date, $time)
{
    $time = taskScheduleTimeValue($time);
    if ($time === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date)) {
        return null;
    }
    $timezone = new DateTimeZone(taskScheduleTimezone($conn));
    $value = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date . ' ' . $time, $timezone);
    return $value instanceof DateTimeImmutable ? $value : null;
}

function taskScheduleIsPast($conn, $date, $time = '')
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date)) {
        return false;
    }
    $now = taskScheduleNow($conn);
    if ($date < $now->format('Y-m-d')) {
        return true;
    }
    $scheduled = taskScheduleDateTime($conn, $date, $time);
    return $scheduled !== null && $scheduled < $now;
}

function taskScheduleTimeValue($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? null : date('H:i:s', $timestamp);
}

function taskScheduleTimeSql($conn, $value)
{
    $time = taskScheduleTimeValue($value);
    return $time === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $time) . "'";
}

function taskScheduleDueCondition($column, $currentTime)
{
    return "(" . $column . " IS NULL OR " . $column . "='00:00:00' OR " . $column . "<='" . $currentTime . "')";
}

function startCronRunLog($conn, $source)
{
    ensureTaskScheduleSchema($conn);
    $startedAt = taskScheduleNow($conn)->format('Y-m-d H:i:s');
    mysqli_query($conn, "INSERT INTO cron_run_log (source,startedAt,status) VALUES ('" . mysqli_real_escape_string($conn, $source) . "','" . mysqli_real_escape_string($conn, $startedAt) . "','running')");
    return (int)mysqli_insert_id($conn);
}

function finishCronRunLog($conn, $runID, $status)
{
    if ($runID > 0) {
        $finishedAt = taskScheduleNow($conn)->format('Y-m-d H:i:s');
        mysqli_query($conn, "UPDATE cron_run_log SET finishedAt='" . mysqli_real_escape_string($conn, $finishedAt) . "',status='" . mysqli_real_escape_string($conn, $status) . "' WHERE id=" . (int)$runID);
    }
}
