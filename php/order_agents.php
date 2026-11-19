<?php
// Agent contacts, per-order commission and notification routing (admin managed).
function ensureOrderAgentSchema($conn)
{
    static $ready = false;
    if ($ready) return;
    $columns = array(
        'agentID' => 'INT NOT NULL DEFAULT 0',
        'agentCommission' => 'DECIMAL(14,2) NOT NULL DEFAULT 0.00',
        'agentCommissionCurrency' => "CHAR(3) NOT NULL DEFAULT 'PKR'"
    );
    foreach ($columns as $name => $definition) {
        $result = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE '$name'");
        if (mysqli_num_rows($result) === 0) mysqli_query($conn, "ALTER TABLE orders ADD COLUMN `$name` $definition");
    }
    $index = mysqli_query($conn, "SHOW INDEX FROM orders WHERE Key_name='orders_agent_id'");
    if (mysqli_num_rows($index) === 0) mysqli_query($conn, 'ALTER TABLE orders ADD INDEX orders_agent_id (agentID)');
    $agentColumns = array(
        'isEnableSMS' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'isEnableWhatsApp' => 'TINYINT(1) NOT NULL DEFAULT 1',
        'notificationPreferencesInitialized' => 'TINYINT(1) NOT NULL DEFAULT 0'
    );
    foreach ($agentColumns as $name => $definition) {
        $result = mysqli_query($conn, "SHOW COLUMNS FROM customers LIKE '$name'");
        if ($result && mysqli_num_rows($result) === 0) mysqli_query($conn, "ALTER TABLE customers ADD COLUMN `$name` $definition");
    }
    mysqli_query($conn, 'ALTER TABLE customers MODIFY isEnableSMS TINYINT(1) NOT NULL DEFAULT 1');
    mysqli_query($conn, 'ALTER TABLE customers MODIFY isEnableWhatsApp TINYINT(1) NOT NULL DEFAULT 1');
    mysqli_query($conn, "UPDATE customers SET isEnableSMS=1,isEnableWhatsApp=1,notificationPreferencesInitialized=1 WHERE notificationPreferencesInitialized=0 AND type<>4");
    mysqli_query($conn, "UPDATE customers SET notificationPreferencesInitialized=1 WHERE notificationPreferencesInitialized=0");
    mysqli_query($conn, 'ALTER TABLE customers MODIFY notificationPreferencesInitialized TINYINT(1) NOT NULL DEFAULT 1');
    $ready = true;
}

function agentEscape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function agentCsrfToken()
{
    if (empty($_SESSION['agent_csrf'])) $_SESSION['agent_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['agent_csrf'];
}

function agentRequireCsrf()
{
    if (!hash_equals(agentCsrfToken(), (string)($_POST['agent_csrf'] ?? ''))) {
        http_response_code(403);
        exit('Your form expired. Go back, reload the page and try again.');
    }
}

function duplicateContactAsAgent($conn, $sourceID)
{
    ensureOrderAgentSchema($conn);
    $sourceID = (int)$sourceID;
    $source = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM customers WHERE id=$sourceID AND type=3"));
    if (!$source) throw new InvalidArgumentException('Contact not found.');
    unset($source['id']);
    $source['type'] = 4;
    $source['assignToID'] = 0;
    $source['isOtherCustomer'] = 0;
    $source['createdDate'] = date('Y-m-d H:i:s');
    $columns = array();
    $values = array();
    foreach ($source as $key => $value) {
        $columns[] = '`' . str_replace('`', '``', $key) . '`';
        $values[] = $value === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, (string)$value) . "'";
    }
    mysqli_begin_transaction($conn);
    try {
        mysqli_query($conn, 'INSERT INTO customers (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ')');
        $agentID = (int)mysqli_insert_id($conn);
        mysqli_commit($conn);
        return $agentID;
    } catch (Throwable $error) {
        mysqli_rollback($conn);
        throw $error;
    }
}

function validateOrderAgentInput($conn, $input)
{
    agentRequireCsrf();
    $agentID = !empty($input['setAgent']) ? (int)($input['agentID'] ?? 0) : 0;
    if (!empty($input['setAgent'])) {
        $agent = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM customers WHERE id=$agentID AND type=4"));
        if (!$agent) throw new InvalidArgumentException('Choose an agent when Set Agent is enabled.');
    }
    $amount = trim((string)($input['agentCommission'] ?? '0'));
    $currency = strtoupper(trim((string)($input['agentCommissionCurrency'] ?? 'PKR')));
    if (!preg_match('/^\d{1,12}(\.\d{1,2})?$/D', $amount)) throw new InvalidArgumentException('Commission must be a non-negative amount with at most two decimal places.');
    if (!in_array($currency, array('PKR', 'USD', 'EUR', 'GBP', 'AED'), true)) throw new InvalidArgumentException('Choose a commission currency.');
    return array('agentID' => $agentID, 'amount' => $agentID ? $amount : '0', 'currency' => $currency);
}

function saveOrderAgent($conn, $orderID, $assignment)
{
    $stmt = mysqli_prepare($conn, 'UPDATE orders SET agentID=?,agentCommission=?,agentCommissionCurrency=? WHERE id=?');
    mysqli_stmt_bind_param($stmt, 'issi', $assignment['agentID'], $assignment['amount'], $assignment['currency'], $orderID);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Resolve at delivery time so queued tasks follow the current order assignment.
// When a customer or customer contact is selected, the agent is added as an
// additional recipient. Existing customer, supplier and other recipients remain.
function orderAgentRecipientIDs($conn, $orderID, $recipients)
{
    ensureOrderAgentSchema($conn);
    $ids = array_values(array_unique(array_filter(array_map('intval', is_array($recipients) ? $recipients : explode(',', (string)$recipients)), function ($id) { return $id > 0; })));
    if (!$ids || (int)$orderID < 1) return $ids;
    $order = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT customerID,agentID FROM orders WHERE id=' . (int)$orderID));
    if (!$order || !(int)$order['agentID']) return $ids;
    $customerID = (int)$order['customerID'];
    $customerRecipients = array($customerID);
    $contacts = mysqli_query($conn, "SELECT id FROM customers WHERE type=3 AND assignToID=$customerID");
    while ($contact = mysqli_fetch_assoc($contacts)) $customerRecipients[] = (int)$contact['id'];
    if (array_intersect($ids, $customerRecipients)) $ids[] = (int)$order['agentID'];
    return array_values(array_unique($ids));
}

function agentRecipientChannelEnabled($conn, $recipientID, $channel)
{
    ensureOrderAgentSchema($conn);
    $recipientID = (int)$recipientID;
    $column = $channel === 'sms' ? 'isEnableSMS' : ($channel === 'whatsapp' ? 'isEnableWhatsApp' : 'isEnableEmail');
    $result = mysqli_query($conn, 'SELECT type,' . $column . ' AS enabled FROM customers WHERE id=' . $recipientID . ' LIMIT 1');
    $recipient = $result ? mysqli_fetch_assoc($result) : false;
    if (!$recipient) return false;
    return (int)$recipient['enabled'] === 1;
}

function filterAgentRecipientsByChannel($conn, $recipientIDs, $channel)
{
    return array_values(array_filter(array_map('intval', (array)$recipientIDs), function ($recipientID) use ($conn, $channel) {
        return agentRecipientChannelEnabled($conn, $recipientID, $channel);
    }));
}

function notificationRecipientNames($conn, $recipientIDs)
{
    $names = array();
    foreach ((array)$recipientIDs as $recipientID) {
        $result = mysqli_query($conn, 'SELECT applicantName,companyName FROM customers WHERE id=' . (int)$recipientID . ' LIMIT 1');
        $recipient = $result ? mysqli_fetch_assoc($result) : false;
        if (!$recipient) continue;
        $name = trim((string)$recipient['applicantName']);
        $company = trim((string)$recipient['companyName']);
        $names[] = $name !== '' && $company !== '' ? $name . ' — ' . $company : ($name !== '' ? $name : $company);
    }
    return $names;
}

function taskNotificationAttemptSummary($conn, $taskID)
{
    $taskID = (int)$taskID;
    $result = mysqli_query($conn, 'SELECT id,orderID,assignTo,sendBy FROM tasks WHERE id=' . $taskID . ' LIMIT 1');
    $task = $result ? mysqli_fetch_assoc($result) : false;
    if (!$task) return 'Task #' . $taskID . ': task not found.';
    $allRecipients = orderAgentRecipientIDs($conn, $task['orderID'], $task['assignTo']);
    $channels = array_values(array_intersect(array('mail', 'sms', 'whatsapp'), array_map('trim', explode(',', (string)$task['sendBy']))));
    if (!$channels) return 'Task #' . $taskID . ': no notification channel enabled.';
    $parts = array();
    foreach ($channels as $channel) {
        $preferenceChannel = $channel === 'mail' ? 'email' : $channel;
        $allowed = filterAgentRecipientsByChannel($conn, $allRecipients, $preferenceChannel);
        $skipped = array_values(array_diff($allRecipients, $allowed));
        $allowedNames = notificationRecipientNames($conn, $allowed);
        $skippedNames = notificationRecipientNames($conn, $skipped);
        $label = $channel === 'mail' ? 'Email' : ($channel === 'sms' ? 'SMS' : 'WhatsApp');
        $parts[] = $label . ' attempted: ' . ($allowedNames ? implode(', ', $allowedNames) : 'none');
        if ($skippedNames) $parts[] = $label . ' skipped (recipient notification setting disabled): ' . implode(', ', $skippedNames);
    }
    return 'Task #' . $taskID . ' — ' . implode('; ', $parts) . '.';
}

function taskNotificationAttemptSummaryHtml($summary)
{
    $lines = preg_split('/\r?\n/', trim((string)$summary));
    $html = '';
    foreach ($lines as $line) {
        if (!preg_match('/^Task #(\d+)\s+[—-]\s+(.+)$/u', trim($line), $match)) {
            $html .= '<div class="text-muted">' . agentEscape($line) . '</div>';
            continue;
        }
        $rows = array();
        foreach (preg_split('/;\s*/', rtrim($match[2], '.')) as $item) {
            if (preg_match('/^(Email|SMS|WhatsApp) attempted:\s*(.*)$/', $item, $part)) {
                $rows[$part[1]] = array('attempted' => $part[2], 'skipped' => 'None');
            } elseif (preg_match('/^(Email|SMS|WhatsApp) skipped \(recipient notification setting disabled\):\s*(.*)$/', $item, $part)) {
                if (!isset($rows[$part[1]])) $rows[$part[1]] = array('attempted' => 'None', 'skipped' => 'None');
                $rows[$part[1]]['skipped'] = $part[2];
            }
        }
        $html .= '<div class="table-responsive" style="margin-top:8px"><table class="table table-bordered table-condensed" style="margin-bottom:8px">'
            . '<caption style="font-weight:bold;color:#333;padding:4px 0">Task #' . (int)$match[1] . '</caption>'
            . '<thead><tr><th style="width:110px">Channel</th><th>Will receive</th><th>Skipped — preference disabled</th></tr></thead><tbody>';
        foreach ($rows as $channel => $values) {
            $html .= '<tr><td><strong>' . agentEscape($channel) . '</strong></td><td>' . agentEscape($values['attempted']) . '</td><td>' . agentEscape($values['skipped']) . '</td></tr>';
        }
        $html .= '</tbody></table></div>';
    }
    return $html;
}

function agentOrderRows($conn, $agentID)
{
    return mysqli_query($conn, 'SELECT o.id,o.invoiceNo,o.documentOrderNo,o.createdDate,o.agentCommission,o.agentCommissionCurrency,p.prodName,s.status FROM orders o LEFT JOIN products p ON p.id=o.productID LEFT JOIN status s ON s.id=o.statusID WHERE o.agentID=' . (int)$agentID . ' ORDER BY o.id DESC');
}

function agentAssignmentCounts($conn, $agentID)
{
    $agentID = (int)$agentID;
    $orderResult = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM orders WHERE agentID=' . $agentID);
    $taskResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tasks WHERE FIND_IN_SET('" . $agentID . "', REPLACE(assignTo, ' ', '')) > 0");
    $orderRow = $orderResult ? mysqli_fetch_assoc($orderResult) : array('total' => 0);
    $taskRow = $taskResult ? mysqli_fetch_assoc($taskResult) : array('total' => 0);
    return array('orders' => (int)$orderRow['total'], 'tasks' => (int)$taskRow['total']);
}

function deleteUnassignedAgent($conn, $agentID)
{
    $agentID = (int)$agentID;
    $agent = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT id FROM customers WHERE id=' . $agentID . ' AND type=4 LIMIT 1'));
    if (!$agent) throw new InvalidArgumentException('Agent not found.');
    $counts = agentAssignmentCounts($conn, $agentID);
    if ($counts['orders'] > 0 || $counts['tasks'] > 0) {
        throw new InvalidArgumentException('This agent cannot be deleted because it has ' . $counts['orders'] . ' assigned order(s) and ' . $counts['tasks'] . ' assigned task(s).');
    }
    if (!mysqli_query($conn, 'DELETE FROM customers WHERE id=' . $agentID . ' AND type=4')) {
        throw new RuntimeException('The agent could not be deleted.');
    }
}

function renderAgentOrders($conn, $agentID, $admin = true)
{
    $result = agentOrderRows($conn, $agentID);
    $orders = array(); $completed = 0; $totals = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $orders[] = $row;
        if (strcasecmp(trim((string)$row['status']), 'Completed') === 0) $completed++;
        $currency = $row['agentCommissionCurrency'];
        $totals[$currency] = ($totals[$currency] ?? 0) + (float)$row['agentCommission'];
    }
    echo '<div class="alert alert-info"><strong>' . count($orders) . ' assigned orders</strong> &nbsp; · &nbsp; ' . $completed . ' completed orders</div>';
    foreach ($totals as $currency => $total) echo '<p><strong>Total commission (' . agentEscape($currency) . '): ' . number_format($total, 2) . '</strong></p>';
    echo '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Order</th><th>Product</th><th>Invoice</th><th>Status</th><th>Commission</th></tr></thead><tbody>';
    foreach ($orders as $row) {
        echo '<tr><td>' . ($admin ? '<a href="orders.php?action=edit&amp;id=' . (int)$row['id'] . '">#' . (int)$row['id'] . '</a>' : '#' . (int)$row['id']) . '</td><td>' . agentEscape($row['prodName']) . '</td><td>' . agentEscape($row['invoiceNo']) . '</td><td>' . agentEscape($row['status']) . '</td><td>' . agentEscape($row['agentCommissionCurrency']) . ' ' . number_format((float)$row['agentCommission'], 2) . '</td></tr>';
    }
    if (!$orders) echo '<tr><td colspan="5">No orders assigned yet.</td></tr>';
    echo '</tbody></table></div>';
}
