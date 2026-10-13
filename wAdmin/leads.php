<?php
include "include/sessionAdmin.php";
include "include/configure.php";
require_once "../php/lead_pipeline.php";
ensureLeadPipelineSchema($conn);

$stages = leadPipelineStages();
$stageFilter = (string)($_GET['stage'] ?? '');
if (!in_array($stageFilter, $stages, true)) $stageFilter = '';
$requestedView = (($_GET['view'] ?? '') === 'archive') ? 'archive' : 'active';
$view = $stageFilter === 'Deal Lost / Cancelled' ? 'archive' : (($stageFilter !== '' || $requestedView === 'active') ? 'active' : 'archive');
$showPiIssued = $view === 'active' && ((string)($_GET['show_pi'] ?? '') === '1' || $stageFilter === 'PI Issued / Order Confirmed');
$csrfToken = $_SESSION['leadPipelineCsrf'] ?? '';
$stageCounts = array_fill_keys($stages, 0);
$stageCountsResult = mysqli_query($conn, "SELECT leadStage, COUNT(*) AS total FROM orders
    WHERE (leadArchived='0' AND ((leadStage='New Inquiry' AND statusID='1') OR leadStage IN ('Sourcing / Supplier Contact','Quote Received & Submitted','Negotiation / Revision','PI Issued / Order Confirmed')))
       OR (leadArchived='1' AND leadStage='Deal Lost / Cancelled')
    GROUP BY leadStage");
while ($stageCountsResult && ($stageCountRow = mysqli_fetch_assoc($stageCountsResult))) {
    if (array_key_exists($stageCountRow['leadStage'], $stageCounts)) $stageCounts[$stageCountRow['leadStage']] = (int)$stageCountRow['total'];
}
if ($csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['leadPipelineCsrf'] = $csrfToken;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'updateLeadStage') {
    $postedToken = (string)($_POST['csrfToken'] ?? '');
    $orderID = (int)($_POST['orderID'] ?? 0);
    $newStage = (string)($_POST['leadStage'] ?? '');
    if ($orderID > 0 && hash_equals($csrfToken, $postedToken) && in_array($newStage, $stages, true)) {
        $safeStage = mysqli_real_escape_string($conn, $newStage);
        $orderStatus = $newStage === 'Deal Lost / Cancelled' ? 4 : ($newStage === 'PI Issued / Order Confirmed' ? 3 : 1);
        $archivedFlag = $newStage === 'Deal Lost / Cancelled' ? 1 : 0;
        mysqli_query($conn, "UPDATE orders SET leadStage='" . $safeStage . "', leadArchived='" . $archivedFlag . "', statusID='" . $orderStatus . "' WHERE id='" . $orderID . "'");
    }
    $redirectView = $newStage === 'Deal Lost / Cancelled' ? 'archive' : 'active';
    $redirect = 'leads.php?view=' . rawurlencode($redirectView);
    if (!empty($_GET['q'])) $redirect .= '&q=' . rawurlencode((string)$_GET['q']);
    $redirect .= '&selected=' . $orderID;
    header('Location: ' . $redirect);
    exit;
}

$escape = function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$search = trim((string)($_GET['q'] ?? ''));
$searchSql = '';
if ($search !== '') {
    $safeSearch = mysqli_real_escape_string($conn, $search);
    $searchSql = " AND (
        CAST(o.id AS CHAR) LIKE '%" . $safeSearch . "%'
        OR o.invoiceNo LIKE '%" . $safeSearch . "%'
        OR o.documentOrderNo LIKE '%" . $safeSearch . "%'
        OR o.leadStage LIKE '%" . $safeSearch . "%'
        OR v.companyName LIKE '%" . $safeSearch . "%'
        OR v.applicantName LIKE '%" . $safeSearch . "%'
        OR c.companyName LIKE '%" . $safeSearch . "%'
        OR c.applicantName LIKE '%" . $safeSearch . "%'
        OR p.prodName LIKE '%" . $safeSearch . "%'
    )";
}

$archiveCondition = "o.leadArchived='1' AND o.leadStage='Deal Lost / Cancelled'";
$activeStages = "'Sourcing / Supplier Contact','Quote Received & Submitted','Negotiation / Revision'";
if ($showPiIssued) $activeStages .= ",'PI Issued / Order Confirmed'";
$activeCondition = "(o.statusID='1' OR o.leadStage IN (" . $activeStages . ")) AND o.leadArchived='0' AND o.leadStage<>'Deal Lost / Cancelled'";
$listCondition = $view === 'archive' ? $archiveCondition : $activeCondition;
if ($stageFilter !== '') $listCondition .= " AND o.leadStage='" . mysqli_real_escape_string($conn, $stageFilter) . "'";
$summaryResult = mysqli_query($conn, "SELECT COUNT(*) AS total,
    COALESCE(SUM(CASE WHEN statusID='1' AND leadStage IN ('New Inquiry','Sourcing / Supplier Contact','Quote Received & Submitted','Negotiation / Revision') AND createdDate <= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) AS follow_up_due
    FROM orders WHERE " . ($view === 'archive' ? "leadArchived='1' AND leadStage='Deal Lost / Cancelled'" : "(statusID='1' OR leadStage IN (" . $activeStages . ")) AND leadArchived='0' AND leadStage<>'Deal Lost / Cancelled'") . ($stageFilter !== '' ? " AND leadStage='" . mysqli_real_escape_string($conn, $stageFilter) . "'" : ''));
$summary = $summaryResult ? mysqli_fetch_assoc($summaryResult) : array('total' => 0, 'follow_up_due' => 0);
$totalLeads = (int)$summary['total'];
$followUpDue = (int)$summary['follow_up_due'];

$start = max(0, (int)($_GET['start'] ?? 0));
$limit = 20;
$totalResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders AS o
    INNER JOIN products AS p ON p.id=o.productID
    LEFT JOIN customers AS v ON v.id=o.vendorID
    LEFT JOIN customers AS c ON c.id=o.customerID
    WHERE " . $listCondition . $searchSql);
$totalRow = $totalResult ? mysqli_fetch_assoc($totalResult) : array('total' => 0);
$matchingLeads = (int)$totalRow['total'];
$leadsResult = mysqli_query($conn, "SELECT o.id,o.invoiceNo,o.documentOrderNo,o.createdDate,o.leadStage,
    v.companyName AS vendorCompany,v.applicantName AS vendorContact,
    c.companyName AS customerCompany,c.applicantName AS customerContact,
    p.prodName
    FROM orders AS o
    INNER JOIN products AS p ON p.id=o.productID
    LEFT JOIN customers AS v ON v.id=o.vendorID
    LEFT JOIN customers AS c ON c.id=o.customerID
    WHERE " . $listCondition . $searchSql . "
    ORDER BY o.id DESC LIMIT " . $start . "," . $limit);
$leadRows = array();
while ($leadsResult && ($leadRow = mysqli_fetch_assoc($leadsResult))) {
    $leadRows[] = $leadRow;
}

$selectedLeadID = (int)($_GET['selected'] ?? 0);
if ($selectedLeadID <= 0 && !empty($leadRows)) {
    $selectedLeadID = (int)$leadRows[0]['id'];
}
$selectedLeadResult = $selectedLeadID > 0
    ? mysqli_query($conn, "SELECT o.id,o.invoiceNo,o.documentOrderNo,o.createdDate,o.leadStage,
        v.companyName AS vendorCompany,v.applicantName AS vendorContact,
        c.companyName AS customerCompany,c.applicantName AS customerContact,
        p.prodName
        FROM orders AS o
        INNER JOIN products AS p ON p.id=o.productID
        LEFT JOIN customers AS v ON v.id=o.vendorID
        LEFT JOIN customers AS c ON c.id=o.customerID
        WHERE o.id='" . $selectedLeadID . "' AND " . $listCondition . " LIMIT 1")
    : false;
$selectedLead = $selectedLeadResult ? mysqli_fetch_assoc($selectedLeadResult) : false;
if (!$selectedLead) {
    $selectedLeadID = 0;
}
$selectedNotes = array();
if ($selectedLead) {
    $notesResult = mysqli_query($conn, "SELECT orderTitle,orderNote,createdDate FROM order_notes WHERE orderID='" . $selectedLeadID . "' ORDER BY id DESC LIMIT 10");
    while ($notesResult && ($noteRow = mysqli_fetch_assoc($notesResult))) {
        $selectedNotes[] = $noteRow;
    }
}

include "include/header.php";
?>
<section role="main" class="content-body">
<header class="page-header">
    <h2><?=($view === 'archive' ? 'Archived Leads' : 'Lead Follow-up')?></h2>
    <div class="right-wrapper pull-right">
        <ol class="breadcrumbs">
            <li><a href="<?=$siteUrl?>"><i class="fa fa-home"></i></a></li>
            <li><span><?=($view === 'archive' ? 'Archived Leads' : 'Lead Follow-up')?></span></li>
        </ol>
        <a>&nbsp;</a>
    </div>
</header>

<section class="panel">
    <header class="panel-heading">
        <div class="panel-actions"><a href="leads.php?view=active" class="btn btn-default btn-sm">Active leads</a> <a href="leads.php?view=archive" class="btn btn-default btn-sm">Archive</a> <a href="<?=$siteUrl?>orders.php?action=add"><b>+ Add Lead</b></a></div>
        <h2 class="panel-title"><?=$view === 'archive' ? 'Archived leads' : 'Your sales leads'?></h2>
    </header>
    <div class="panel-body">
        <div class="alert alert-info" style="margin-bottom:20px;">
            <strong>Track each inquiry through the sales pipeline.</strong>
            Update the pipeline stage as supplier sourcing, quotes, and negotiations progress. Lost deals are kept in the archive; PI issued or order confirmed leads move to the completed order status.
        </div>

        <div class="row" style="margin-bottom:20px;">
            <div class="col-sm-6 col-md-3">
                <div class="panel" style="border:1px solid #ddd;padding:14px;margin-bottom:10px;">
                    <div class="text-muted"><?=$view === 'archive' ? 'Archived leads' : 'Open leads'?></div>
                    <div style="font-size:26px;font-weight:bold;"><?=$totalLeads?></div>
                    <small><?=$view === 'archive' ? 'Lost or cancelled deals' : 'In progress or PI issued'?></small>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="panel" style="border:1px solid #f0ad4e;padding:14px;margin-bottom:10px;">
                    <div class="text-muted">Follow-up due</div>
                    <div style="font-size:26px;font-weight:bold;color:#b57900;"><?=$followUpDue?></div>
                    <small>Open leads created 7 or more days ago</small>
                </div>
            </div>
        </div>

        <form class="form-inline" method="get" style="margin-bottom:18px;">
            <input type="hidden" name="view" value="<?=$escape($view)?>">
            <?php if ($showPiIssued && $stageFilter !== 'PI Issued / Order Confirmed'): ?><input type="hidden" name="show_pi" value="1"><?php endif; ?>
            <div class="form-group" style="width:min(100%, 560px);">
                <label class="sr-only" for="leadSearch">Search leads</label>
                <input type="search" class="form-control" style="width:100%;" name="q" id="leadSearch" value="<?=$escape($search)?>" placeholder="Search product, vendor, customer, or reference">
            </div>
            <div class="form-group" style="margin-left:8px;">
                <label class="sr-only" for="leadStageFilter">Filter by pipeline stage</label>
                <select class="form-control" id="leadStageFilter" name="stage">
                    <option value="">All statuses</option>
                    <?php foreach ($stages as $stage): ?><option value="<?=$escape($stage)?>" <?=($stageFilter === $stage ? 'selected' : '')?>><?=$escape($stage)?> (<?=number_format($stageCounts[$stage] ?? 0)?>)</option><?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Search</button>
            <?php if ($search !== '' || $stageFilter !== ''): ?><a class="btn btn-default" href="leads.php?view=<?=$escape($view)?><?=($showPiIssued ? '&amp;show_pi=1' : '')?>">Clear</a><?php endif; ?>
            <?php if ($view === 'active'): ?>
                <?php if ($stageFilter !== 'PI Issued / Order Confirmed'): ?>
                    <?php $piToggleUrl = 'leads.php?view=active' . ($showPiIssued ? '' : '&show_pi=1') . ($search !== '' ? '&q=' . rawurlencode($search) : '') . ($stageFilter !== '' ? '&stage=' . rawurlencode($stageFilter) : ''); ?>
                    <a class="btn btn-link" href="<?=$escape($piToggleUrl)?>"><?=$showPiIssued ? 'Hide PI Issued / Order Confirmed' : 'Show PI Issued / Order Confirmed'?></a>
                <?php endif; ?>
            <?php endif; ?>
            <span class="text-muted" style="margin-left:8px;"><?=$matchingLeads?> matching lead<?=$matchingLeads === 1 ? '' : 's'?></span>
        </form>

        <?php if ($matchingLeads > 0 && !empty($leadRows)): ?>
        <style>
            .lead-select-row { cursor:pointer; }
            .lead-select-row:hover td { background:#f5f9fc !important; }
            .lead-select-row.selected td { background:#eaf4fb !important; }
            .lead-notes-panel { min-height:300px; }
            .lead-note-item { border-bottom:1px solid #eee; padding:12px 0; }
            .lead-note-item:last-child { border-bottom:0; }
        </style>
        <div class="row">
        <div class="col-lg-8">
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-condensed mb-none">
                <thead>
                    <tr>
                        <th>Lead / Product</th>
                        <th>Customer</th>
                        <th>Added</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($leadRows as $lead):
                    $createdTimestamp = strtotime((string)$lead['createdDate']);
                    $ageDays = $createdTimestamp ? max(0, (int)floor((time() - $createdTimestamp) / 86400)) : 0;
                    $customerLabel = trim((string)$lead['customerCompany']) !== '' ? $lead['customerCompany'] : $lead['customerContact'];
                    $reference = trim((string)$lead['documentOrderNo']) !== '' ? $lead['documentOrderNo'] : $lead['invoiceNo'];
                    $leadLink = 'leads.php?view=' . rawurlencode($view) . ($showPiIssued ? '&show_pi=1' : '') . ($stageFilter !== '' ? '&stage=' . rawurlencode($stageFilter) : '') . '&q=' . rawurlencode($search) . '&start=' . $start . '&selected=' . (int)$lead['id'];
                ?>
                    <tr class="lead-select-row <?=((int)$lead['id'] === $selectedLeadID) ? 'selected' : ''?>" data-href="<?=$escape($leadLink)?>" tabindex="0" role="button" aria-label="Show notes for lead #<?=$escape($lead['id'])?>">
                        <td>
                            <strong>#<?=$escape($lead['id'])?></strong>
                            <?php if (trim((string)$reference) !== ''): ?><br><small class="text-muted">Ref: <?=$escape($reference)?></small><?php endif; ?>
                            <br><?=$escape($lead['prodName'])?>
                            <br><span class="label <?=($lead['leadStage'] === 'Deal Lost / Cancelled' ? 'label-danger' : ($lead['leadStage'] === 'PI Issued / Order Confirmed' ? 'label-success' : 'label-info'))?>"><?=$escape($lead['leadStage'])?></span>
                        </td>
                        <td><?=$escape($customerLabel)?></td>
                        <td><?=($createdTimestamp ? $escape(viewDtTm($lead['createdDate'])) : 'Date unavailable')?>
                            <br><small class="text-muted"><?=$ageDays?> day<?=$ageDays === 1 ? '' : 's'?> ago</small>
                            <?php if ($ageDays >= 7): ?><br><span class="label label-warning">Follow-up due</span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </div>
        <div class="col-lg-4">
            <section class="panel lead-notes-panel">
                <?php if ($selectedLead): ?>
                <?php
                    $selectedVendor = trim((string)$selectedLead['vendorCompany']) !== '' ? $selectedLead['vendorCompany'] : $selectedLead['vendorContact'];
                    $selectedCustomer = trim((string)$selectedLead['customerCompany']) !== '' ? $selectedLead['customerCompany'] : $selectedLead['customerContact'];
                    $selectedReference = trim((string)$selectedLead['documentOrderNo']) !== '' ? $selectedLead['documentOrderNo'] : $selectedLead['invoiceNo'];
                ?>
                <header class="panel-heading">
                    <div class="panel-actions"><a href="orders.php?action=view&amp;id=<?=$selectedLeadID?>">View lead</a></div>
                    <h2 class="panel-title"><?=$escape($selectedLead['prodName'])?> &raquo; Notes</h2>
                    <small class="text-muted">Lead #<?=$selectedLeadID?></small>
                </header>
                <div class="panel-body">
                    <p><strong><?=$escape($selectedLead['prodName'])?></strong></p>
                    <p class="text-muted" style="margin-bottom:8px;">Customer: <?=$escape($selectedCustomer)?></p>
                    <p class="text-muted">Vendor: <?=$escape($selectedVendor)?></p>
                    <?php if ($selectedReference !== ''): ?><p class="text-muted">Reference: <?=$escape($selectedReference)?></p><?php endif; ?>
                    <form method="post" style="margin:15px 0;">
                        <input type="hidden" name="action" value="updateLeadStage">
                        <input type="hidden" name="csrfToken" value="<?=$escape($csrfToken)?>">
                        <input type="hidden" name="orderID" value="<?=$selectedLeadID?>">
                        <label for="leadStage">Pipeline stage</label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <select class="form-control" id="leadStage" name="leadStage">
                                <?php foreach ($stages as $stage): ?><option value="<?=$escape($stage)?>" <?=($selectedLead['leadStage'] === $stage ? 'selected' : '')?>><?=$escape($stage)?></option><?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary btn-sm" type="submit">Save</button>
                        </div>
                    </form>
                    <a class="btn btn-primary btn-sm" href="orderNotes.php?action=add&amp;oID=<?=$selectedLeadID?>">+ Add follow-up note</a>
                    <hr>
                    <?php if ($selectedNotes): ?>
                        <?php foreach ($selectedNotes as $note):
                            $noteText = preg_replace('/<br\s*\/?\s*>|<\/(p|div|li)>/i', "\n", (string)$note['orderNote']);
                            $noteText = trim(strip_tags($noteText));
                        ?>
                        <div class="lead-note-item">
                            <strong><?=$escape($note['orderTitle'])?></strong>
                            <div style="margin-top:5px;"><?=nl2br($escape($noteText))?></div>
                            <small class="text-muted"><?=($note['createdDate'] ? $escape(viewDtTm($note['createdDate'])) : '')?></small>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-muted" style="padding:12px 0;">No notes yet. Add the first follow-up note to keep the next step clear.</div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="panel-body text-muted">Select a lead to see its notes and follow-up details here.</div>
                <?php endif; ?>
            </section>
        </div>
        </div>

        <?php if ($matchingLeads > $limit): ?>
        <nav aria-label="Lead pages" style="margin-top:15px;">
            <?php if ($start > 0): ?><a class="btn btn-default btn-sm" href="leads.php?view=<?=$escape($view)?><?=($showPiIssued ? '&amp;show_pi=1' : '')?><?=($stageFilter !== '' ? '&amp;stage=' . rawurlencode($stageFilter) : '')?>&amp;q=<?=$escape(rawurlencode($search))?>&amp;start=<?=max(0, $start - $limit)?>">Previous</a><?php endif; ?>
            <span class="text-muted" style="margin:0 10px;">Page <?=((int)floor($start / $limit) + 1)?> of <?=((int)ceil($matchingLeads / $limit))?></span>
            <?php if ($start + $limit < $matchingLeads): ?><a class="btn btn-default btn-sm" href="leads.php?view=<?=$escape($view)?><?=($showPiIssued ? '&amp;show_pi=1' : '')?><?=($stageFilter !== '' ? '&amp;stage=' . rawurlencode($stageFilter) : '')?>&amp;q=<?=$escape(rawurlencode($search))?>&amp;start=<?=$start + $limit?>">Next</a><?php endif; ?>
        </nav>
        <?php endif; ?>
        <?php else: ?>
        <div class="alert alert-warning" style="margin-top:15px;">
            <?=($search !== '') ? 'No leads match that search. Try a product, vendor, customer, stage, or reference.' : ($view === 'archive' ? 'No lost or cancelled leads are archived yet.' : 'No open leads right now. New requests marked with Lead status will appear here.')?>
        </div>
        <?php endif; ?>
    </div>
</section>
</section>
</div>
</section>
<script>
document.querySelectorAll('.lead-select-row').forEach(function (row) {
    function openLeadNotes() {
        window.location.href = row.getAttribute('data-href');
    }
    row.addEventListener('click', function (event) {
        if (event.target.closest('a, button, input, select, textarea')) {
            return;
        }
        openLeadNotes();
    });
    row.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            openLeadNotes();
        }
    });
});
</script>
<?php include "include/footer.php"; ?>
