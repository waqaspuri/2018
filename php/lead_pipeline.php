<?php
function ensureLeadPipelineSchema($conn)
{
    static $checked = false;
    if ($checked) return;
    $checked = true;
    $column = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'leadStage'");
    if ($column && mysqli_num_rows($column) === 0) {
        $added = mysqli_query($conn, "ALTER TABLE orders ADD COLUMN leadStage VARCHAR(64) NOT NULL DEFAULT 'New Inquiry' AFTER statusID");
        if ($added) {
            mysqli_query($conn, "UPDATE orders SET leadStage=CASE
                WHEN statusID='2' THEN 'Negotiation / Revision'
                WHEN statusID='3' THEN 'PI Issued / Order Confirmed'
                ELSE 'New Inquiry' END");
        }
    }
    $archiveColumn = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'leadArchived'");
    if ($archiveColumn && mysqli_num_rows($archiveColumn) === 0) {
        mysqli_query($conn, "ALTER TABLE orders ADD COLUMN leadArchived TINYINT(1) NOT NULL DEFAULT 0 AFTER leadStage");
    }
}

function leadPipelineStages()
{
    return array(
        'New Inquiry',
        'Sourcing / Supplier Contact',
        'Quote Received & Submitted',
        'Negotiation / Revision',
        'PI Issued / Order Confirmed',
        'Deal Lost / Cancelled'
    );
}
?>
