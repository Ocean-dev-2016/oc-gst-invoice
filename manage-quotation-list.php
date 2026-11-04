<?php
require_once __DIR__ . '/root/config.php';
require_once __DIR__ . '/includes/quotation-status.php';

// Handle AJAX Quotation Workflow Status (Draft, Send, Accepted, Rejected) Update
if (isset($_POST['action']) && $_POST['action'] === 'update_quotation_status') {
    if (ob_get_level()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');

    $quotId = intval($_POST['id'] ?? 0);
    $newStatus = $_POST['quotation_status'] ?? '';

    if ($quotId > 0) {
        if (!in_array($newStatus, getQuotationStatuses(), true)) {
            echo json_encode(['status' => false, 'message' => 'Invalid quotation status.']);
            exit;
        }
        if (!ensureQuotationStatusSchema($ai_conn)) {
            echo json_encode(['status' => false, 'message' => 'Failed to prepare quotation status database field: ' . mysqli_error($ai_conn)]);
            exit;
        }

        $qRow = $ai_db->aiGetQueryObj("SELECT id, converted_invoice_id, due_date FROM tbl_quotation WHERE id='$quotId' LIMIT 1");
        if (empty($qRow)) {
            echo json_encode(['status' => false, 'message' => 'Quotation not found.']);
            exit;
        }
        if (!empty($qRow[0]->converted_invoice_id) && intval($qRow[0]->converted_invoice_id) > 0) {
            echo json_encode(['status' => false, 'message' => 'Cannot change status of a quotation that has already been converted to an invoice.']);
            exit;
        }

        $today = date('Y-m-d');
        $qDueDate = !empty($qRow[0]->due_date) ? date('Y-m-d', strtotime($qRow[0]->due_date)) : '';
        $isExpired = (!empty($qDueDate) && $qDueDate < $today);

        if ($isExpired && in_array($newStatus, ['Send', 'Accepted'], true)) {
            echo json_encode([
                'status' => false,
                'message' => 'Due date has expired (' . date('d-m-Y', strtotime($qDueDate)) . '). This quotation can only be Rejected.'
            ]);
            exit;
        }

        $updQry = "UPDATE tbl_quotation SET quotation_status='$newStatus' WHERE id='$quotId'";
        if (($_SESSION['user_type'] ?? '') === 'company' && !empty($_SESSION['company_id'])) {
            $compFilter = intval($_SESSION['company_id']);
            $updQry .= " AND company_id='$compFilter'";
        }
        $updated = mysqli_query($ai_conn, $updQry);
        if ($updated) {
            echo json_encode([
                'status' => true,
                'message' => 'Quotation status updated to ' . $newStatus,
                'new_status' => $newStatus
            ]);
            exit;
        } else {
            echo json_encode(['status' => false, 'message' => 'Database error: ' . mysqli_error($ai_conn)]);
            exit;
        }
    }
    echo json_encode(['status' => false, 'message' => 'Invalid Quotation ID.']);
    exit;
}

include('includes/header.php');

$page_nm = "Quotation";
$pageUrl = "manage-quotation-list.php";
$table = "tbl_quotation";


// Handle status toggle for Quotation (active/deactive) and recalculate Party Outstanding
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $toggleId = intval($_GET['id'] ?? 0);
    $toStatus = $_GET['to'] ?? '';
    if ($toggleId > 0 && in_array($toStatus, ['active', 'deactive'], true)) {
        $qRow = $ai_db->aiGetQueryObj("SELECT party_id FROM tbl_quotation WHERE id='$toggleId' LIMIT 1");

        $partyId = !empty($qRow) ? intval($qRow[0]->party_id) : 0;

        $safeTo = addslashes($toStatus);
        $ai_db->aiQuery("UPDATE tbl_quotation SET status='$safeTo' WHERE id='$toggleId'");

        if ($partyId > 0 && function_exists('recalculatePartyOutstanding')) {
            recalculatePartyOutstanding($partyId);
        }

        $ai_core->aiGoPage($pageUrl);
        exit;
    }
}

// Auto update expired Draft/Sent quotations to Rejected in database where due_date < today
$todayYmd = date('Y-m-d');
@mysqli_query($ai_conn, "UPDATE tbl_quotation SET quotation_status='Rejected' WHERE quotation_status IN ('Draft','Send') AND due_date IS NOT NULL AND due_date != '' AND due_date != '0000-00-00' AND due_date < '$todayYmd' AND (converted_invoice_id IS NULL OR converted_invoice_id = 0)");

// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $del_id = intval($_GET['id'] ?? 0);
    if ($del_id > 0) {
        $qRow = $ai_db->aiGetQueryObj("SELECT party_id, quotation_no, converted_invoice_id FROM tbl_quotation WHERE id='$del_id' LIMIT 1");
        if (!empty($qRow)) {
            // If already converted to invoice, block deletion
            if (!empty($qRow[0]->converted_invoice_id) && intval($qRow[0]->converted_invoice_id) > 0) {
                $ai_core->aiGoPage($pageUrl . '?msg=del_converted_locked');
                exit;
            }

            $partyId = intval($qRow[0]->party_id ?? 0);

            $ai_db->aiQuery("DELETE FROM tbl_quotation WHERE id='$del_id'");
            $ai_db->aiQuery("DELETE FROM tbl_quotation_items WHERE quotation_id='$del_id'");

            if ($partyId > 0 && function_exists('recalculatePartyOutstanding')) {
                recalculatePartyOutstanding($partyId);
            }

            $ai_core->aiGoPage($pageUrl . '?msg=3');
            exit;
        }
    }
}

$company_where = "";
if (($_SESSION['user_type'] ?? '') === 'company' && !empty($_SESSION['company_id'])) {
    $company_where = " AND q.company_id = '" . intval($_SESSION['company_id']) . "'";
}

$filter_status = $_GET['quotation_status'] ?? 'all';
$status_where = "";
if (in_array($filter_status, getQuotationStatuses(), true)) {
    $status_where = " AND q.quotation_status = '{$filter_status}'";
}

$filter_due_status = $_GET['due_status'] ?? 'all';
$due_where = "";
$todayDate = date('Y-m-d');
if ($filter_due_status === 'Active' || $filter_due_status === 'active') {
    $due_where = " AND (q.due_date IS NULL OR q.due_date = '' OR q.due_date = '0000-00-00' OR q.due_date >= '{$todayDate}')";
} elseif ($filter_due_status === 'Expired' || $filter_due_status === 'expired' || $filter_due_status === 'Overdue') {
    $due_where = " AND q.due_date IS NOT NULL AND q.due_date != '' AND q.due_date != '0000-00-00' AND q.due_date < '{$todayDate}'";
}

$qry = "SELECT q.*, c.company_name 
        FROM tbl_quotation q 
        LEFT JOIN tbl_company c ON q.company_id = c.id 
        WHERE 1=1
        $company_where
        $due_where
        $status_where
        ORDER BY q.id DESC";
$quotations = $ai_db->aiGetQueryObj($qry);

// Metrics for Top Summary Cards
$metricsQry = "SELECT 
    COUNT(id) AS total_count,
    COALESCE(SUM(grand_total), 0) AS total_amt,
    COUNT(CASE WHEN (due_date IS NULL OR due_date = '' OR due_date = '0000-00-00' OR due_date >= '{$todayDate}') THEN 1 END) AS active_count,
    COALESCE(SUM(CASE WHEN (due_date IS NULL OR due_date = '' OR due_date = '0000-00-00' OR due_date >= '{$todayDate}') THEN grand_total ELSE 0 END), 0) AS active_amt,
    COUNT(CASE WHEN due_date IS NOT NULL AND due_date != '' AND due_date != '0000-00-00' AND due_date < '{$todayDate}' THEN 1 END) AS expired_count,
    COALESCE(SUM(CASE WHEN due_date IS NOT NULL AND due_date != '' AND due_date != '0000-00-00' AND due_date < '{$todayDate}' THEN grand_total ELSE 0 END), 0) AS expired_amt
FROM tbl_quotation q
WHERE 1=1 {$company_where}";
$metricsRes = $ai_db->aiGetQueryObj($metricsQry);
$m = !empty($metricsRes) ? $metricsRes[0] : null;

$statTotalCount = intval($m->total_count ?? 0);
$statTotalAmt = floatval($m->total_amt ?? 0);
$statPaidCount = intval($m->active_count ?? 0);
$statPaidAmt = floatval($m->active_amt ?? 0);
$statUnpaidCount = intval($m->expired_count ?? 0);
$statUnpaidAmt = floatval($m->expired_amt ?? 0);
$statOverdueCount = intval($m->expired_count ?? 0);
$statOverdueAmt = floatval($m->expired_amt ?? 0);
?>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <?php include('sidebar.php'); ?>

            <div class="layout-page">
                <?php include('navbar.php'); ?>

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">

                        <!-- Notification messages -->
                        <?php if (isset($_GET['msg']) && $_GET['msg'] == '1') { ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                Quotation added successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } elseif (isset($_GET['msg']) && $_GET['msg'] == '2') { ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                Quotation updated successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } elseif (isset($_GET['msg']) && $_GET['msg'] == '3') { ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                Quotation deleted successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } elseif (isset($_GET['msg']) && $_GET['msg'] == 'paid_locked') { ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                ⚠ <strong>Action not allowed:</strong> Quotation status cannot be changed because its payment is already <strong>Paid</strong>.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } elseif (isset($_GET['msg']) && $_GET['msg'] == 'converted_locked') { ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                ⚠ <strong>Action not allowed:</strong> This quotation cannot be edited because its <strong>Tax Invoice</strong> has already been generated.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } elseif (isset($_GET['msg']) && $_GET['msg'] == 'del_converted_locked') { ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                ⚠ <strong>Action not allowed:</strong> This quotation cannot be deleted because its <strong>Tax Invoice</strong> has already been generated.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } ?>


                        <div class="card">
                            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <h5 class="mb-0"><?= $page_nm ?> List</h5>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="d-flex align-items-center">
                                        <label for="filterQuotationStatus" class="me-2 text-muted fw-medium text-nowrap" style="font-size: 0.9rem;">Status:</label>
                                        <select id="filterQuotationStatus" class="form-select form-select-sm me-2" style="width: auto; min-width: 135px; border-color: #dbdade; color: #5d596c; font-size: 0.875rem; height: 47px; border-radius: 10px;" onchange="window.location.href='manage-quotation-list.php?quotation_status=' + this.value + '&due_status=<?= urlencode($filter_due_status) ?>';">
                                            <option value="all" <?= ($filter_status === 'all') ? 'selected' : '' ?>>All Status</option>
                                            <option value="Draft" <?= ($filter_status === 'Draft') ? 'selected' : '' ?>>Draft</option>
                                            <option value="Send" <?= ($filter_status === 'Send') ? 'selected' : '' ?>>Send</option>
                                            <option value="Accepted" <?= ($filter_status === 'Accepted') ? 'selected' : '' ?>>Accepted</option>
                                            <option value="Rejected" <?= ($filter_status === 'Rejected') ? 'selected' : '' ?>>Rejected</option>
                                        </select>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <label for="filterPaymentStatus" class="me-2 text-muted fw-medium text-nowrap" style="font-size: 0.9rem;">Status:</label>
                                        <select id="filterPaymentStatus" class="form-select form-select-sm" style="width: auto; min-width: 145px; border-color: #dbdade; color: #5d596c; font-size: 0.875rem; height: 47px; border-radius: 10px;" onchange="window.location.href='manage-quotation-list.php?quotation_status=<?= urlencode($filter_status) ?>&due_status=' + this.value;">
                                            <option value="all" <?= ($filter_due_status === 'all') ? 'selected' : '' ?>>All Status</option>
                                            <option value="Active" <?= ($filter_due_status === 'Active') ? 'selected' : '' ?>>Active</option>
                                            <option value="Expired" <?= ($filter_due_status === 'Expired' || $filter_due_status === 'Overdue') ? 'selected' : '' ?>>Expired</option>
                                        </select>
                                    </div>
                                    <a href="manage-quotation-form.php?mode=add" class="btn btn-primary btn-lg">
                                        <i class="ti ti-plus me-1"></i> Add New
                                    </a>
                                </div>
                            </div>

                            <div class="card-datatable">
                                <table class="datatables-ajax table table-bordered align-middle mb-0 text-center" style="font-size:15px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:40px;">Print</th>
                                            <th>Party Name</th>
                                            <th>Quotation No</th>
                                            <th>Date</th>
                                            <th>Due Date</th>
                                            <th>Quotation Status</th>
                                            <th>Status</th>
                                            <th>Grand Total</th>
                                            <th style="width:100px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($quotations) {
                                            foreach ($quotations as $q) {
                                                $isConverted = (!empty($q->converted_invoice_id) && intval($q->converted_invoice_id) > 0);
                                                $qDateStr = !empty($q->quotation_date) ? date('d-m-Y', strtotime($q->quotation_date)) : '-';
                                        ?>
                                                <tr>
                                                    <!-- 1. Print / View Icon -->
                                                    <td>
                                                        <?php $iconViewUrl = 'quotation-print.php?id=' . $q->id . '&store_and_show=1'; ?>
                                                        <a href="<?= $iconViewUrl ?>" target="_blank" class="text-primary" title="Print / View" style="font-size: 20px;">
                                                            <i class="ti ti-printer"></i>
                                                        </a>
                                                    </td>
                                                    <!-- 2. Party Name -->
                                                    <td class="text-start">
                                                        <strong><?= htmlspecialchars($q->party_name) ?></strong>
                                                        <?php if (!empty($q->company_name) && ($_SESSION['user_type'] ?? '') !== 'company') { ?>
                                                            <div class="text-muted small"><?= htmlspecialchars($q->company_name) ?></div>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- 3. Quotation Number -->
                                                    <td>
                                                        <span class="badge bg-label-primary font-monospace"><?= htmlspecialchars($q->quotation_no) ?></span>
                                                    </td>

                                                    <!-- 4. Date -->
                                                    <td>
                                                        <?= $qDateStr ?>
                                                    </td>

                                                    <!-- 5. Due Date -->
                                                    <td>
                                                        <?php
                                                        if (!empty($q->due_date)) {
                                                            $dueDateStr = date('d-m-Y', strtotime($q->due_date));
                                                            $dueYmd = date('Y-m-d', strtotime($q->due_date));
                                                            if ($dueYmd < date('Y-m-d') && !$isConverted) {
                                                                echo '<span class="text-danger fw-semibold" title="Overdue / Expired"><i class="ti ti-alert-triangle me-1"></i>' . $dueDateStr . '</span>';
                                                            } else {
                                                                echo '<span>' . $dueDateStr . '</span>';
                                                            }
                                                        } else {
                                                            echo '<span class="text-muted">-</span>';
                                                        }
                                                        ?>
                                                    </td>

                                                    <!-- 6. Status (Draft/Send/Accepted/Rejected) + GENERATE INVOICE button -->
                                                    <td>
                                                        <div class="d-inline-flex align-items-center justify-content-center gap-2">
                                                            <?php
                                                            $quoStatus = !empty($q->quotation_status) ? $q->quotation_status : 'Draft';
                                                            $isExpired = false;
                                                            if (!empty($q->due_date)) {
                                                                $qDueYmd = date('Y-m-d', strtotime($q->due_date));
                                                                if ($qDueYmd < date('Y-m-d')) {
                                                                    $isExpired = true;
                                                                    // If expired and not already converted or accepted before expiry, force Rejected
                                                                    if (!$isConverted && $quoStatus !== 'Accepted') {
                                                                        $quoStatus = 'Rejected';
                                                                    }
                                                                }
                                                            }

                                                            if ($isConverted) {
                                                                $badgeClass = 'bg-label-info';
                                                            } elseif ($quoStatus === 'Accepted') {
                                                                $badgeClass = 'bg-label-success';
                                                            } elseif ($quoStatus === 'Rejected') {
                                                                $badgeClass = 'bg-label-danger';
                                                            } elseif ($quoStatus === 'Send') {
                                                                $badgeClass = 'bg-label-info';
                                                            } else {
                                                                $badgeClass = 'bg-label-secondary';
                                                            }
                                                            ?>
                                                            <?php if ($isConverted) { ?>
                                                                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($quoStatus) ?></span>
                                                                <a href="manage-invoice-form.php?mode=edit&id=<?= $q->converted_invoice_id ?>"
                                                                    class="badge bg-label-primary px-2 py-1 fw-bold text-decoration-none"
                                                                    title="View Converted Invoice">
                                                                    <i class="ti ti-check me-1"></i> INV #<?= $q->converted_invoice_id ?>
                                                                </a>
                                                            <?php } elseif ($isExpired) { ?>
                                                                <select class="form-select form-select-sm quotation-status-dropdown fw-bold text-center"
                                                                    data-id="<?= $q->id ?>"
                                                                    data-expired="1"
                                                                    style="width: 105px; font-size: 12px; padding: 4px 8px; border-radius: 6px; cursor: pointer; background-color:#fee2e2; color:#b91c1c; border-color:#fca5a5;">
                                                                    <option value="Rejected" selected>Rejected</option>
                                                                </select>
                                                            <?php } else { ?>
                                                                <select class="form-select form-select-sm quotation-status-dropdown fw-bold text-center"
                                                                    data-id="<?= $q->id ?>"
                                                                    data-expired="0"
                                                                    style="width: 105px; font-size: 12px; padding: 4px 8px; border-radius: 6px; cursor: pointer;
                                                                        <?php
                                                                        if ($quoStatus === 'Accepted') echo 'background-color:#dcfce7; color:#15803d; border-color:#86efac;';
                                                                        elseif ($quoStatus === 'Rejected') echo 'background-color:#fee2e2; color:#b91c1c; border-color:#fca5a5;';
                                                                        elseif ($quoStatus === 'Send') echo 'background-color:#e0f2fe; color:#0369a1; border-color:#7dd3fc;';
                                                                        else echo 'background-color:#f1f5f9; color:#475569; border-color:#cbd5e1;';
                                                                        ?>">
                                                                    <option value="Draft" <?= ($quoStatus === 'Draft') ? 'selected' : '' ?>>Draft</option>
                                                                    <option value="Send" <?= ($quoStatus === 'Send') ? 'selected' : '' ?>>Send</option>
                                                                    <option value="Accepted" <?= ($quoStatus === 'Accepted') ? 'selected' : '' ?>>Accepted</option>
                                                                    <option value="Rejected" <?= ($quoStatus === 'Rejected') ? 'selected' : '' ?>>Rejected</option>
                                                                </select>

                                                                <button type="button"
                                                                    class="btn btn-success btn-sm btn-convert-invoice fw-bold px-2 py-1"
                                                                    id="btn-convert-<?= $q->id ?>"
                                                                    style="background-color: #00a859 !important; border-color: #00a859 !important; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-radius: 4px; <?= ($quoStatus === 'Accepted') ? '' : 'display: none !important;' ?>"
                                                                    data-id="<?= $q->id ?>"
                                                                    data-no="<?= htmlspecialchars($q->quotation_no) ?>"
                                                                    title="Generate Tax Invoice">
                                                                    GENERATE INVOICE
                                                                </button>
                                                            <?php } ?>
                                                        </div>
                                                    </td>

                                                    <!-- Status (Active / Expired) -->
                                                    <td>
                                                        <?php if ($isExpired) { ?>
                                                            <span class="badge bg-label-danger px-2 py-1 fw-bold" style="font-size: 12px;">
                                                                <i class="ti ti-clock-off me-1"></i> Expired
                                                            </span>
                                                        <?php } else { ?>
                                                            <span class="badge bg-label-success px-2 py-1 fw-bold" style="font-size: 12px;">
                                                                <i class="ti ti-check me-1"></i> Active
                                                            </span>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- 6. Grand Total -->
                                                    <td class="text-end fw-bold text-success">
                                                        ₹<?= number_format($q->grand_total, 2) ?>
                                                    </td>

                                                    <!-- 7. Action -->
                                                    <td>
                                                        <div class="dropdown">
                                                            <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                                <i class="ti ti-dots-vertical fs-4"></i>
                                                            </button>
                                                            <div class="dropdown-menu dropdown-menu-end">
                                                                <?php
                                                                $viewUrl = 'quotation-print.php?id=' . $q->id . '&store_and_show=1';
                                                                ?>
                                                                <a class="dropdown-item" href="<?= $viewUrl ?>" target="_blank">
                                                                    <i class="ti ti-printer me-1"></i> Print / View
                                                                </a>
                                                                <?php if ($isConverted) { ?>
                                                                    <a class="dropdown-item text-muted disabled" href="javascript:void(0);" title="Cannot edit: Invoice already generated">
                                                                        <i class="ti ti-pencil me-1"></i> Edit <span class="badge bg-label-secondary ms-1">Locked</span>
                                                                    </a>
                                                                <?php } else { ?>
                                                                    <a class="dropdown-item" href="manage-quotation-form.php?mode=edit&id=<?= $q->id ?>">
                                                                        <i class="ti ti-pencil me-1"></i> Edit
                                                                    </a>
                                                                <?php } ?>
                                                                <?php if ($isConverted) { ?>
                                                                    <a class="dropdown-item text-muted disabled" href="javascript:void(0);" title="Cannot delete: Invoice already generated">
                                                                        <i class="ti ti-trash me-1"></i> Delete <span class="badge bg-label-secondary ms-1">Locked</span>
                                                                    </a>
                                                                <?php } else { ?>
                                                                    <a class="dropdown-item text-danger" href="manage-quotation-list.php?action=delete&id=<?= $q->id ?>" onclick="return confirm('Are you sure you want to delete this quotation?');">
                                                                        <i class="ti ti-trash me-1"></i> Delete
                                                                    </a>
                                                                <?php } ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                        <?php }
                                        } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>

                    <?php include('includes/footer.php'); ?>
                    <div class="content-backdrop fade"></div>
                </div>
            </div>
        </div>

        <div class="layout-overlay layout-menu-toggle"></div>
        <div class="drag-target"></div>
    </div>

    <?php include('includes/footer_js.php'); ?>

    <script>
        $(document).ready(function() {
            $('.datatables-ajax').DataTable({
                processing: true,
                pageLength: 10,
                ordering: true,
                responsive: false,
                autoWidth: false,
                columnDefs: [{
                    targets: [0, 5, 6, 8],
                    orderable: false
                }]
            });

            // AJAX Handler for Quotation Status Change (Draft, Send, Accepted, Rejected)
            $(document).on('change', '.quotation-status-dropdown', function() {
                var $select = $(this);
                var quotationId = $select.data('id');
                var newStatus = $select.val();

                $select.prop('disabled', true);

                $.ajax({
                    url: 'manage-quotation-list.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'update_quotation_status',
                        id: quotationId,
                        quotation_status: newStatus
                    },
                    success: function(res) {
                        $select.prop('disabled', false);
                        if (res.status) {
                            var $convBtn = $('#btn-convert-' + quotationId);
                            if (newStatus === 'Accepted') {
                                $select.css({
                                    'background-color': '#dcfce7',
                                    'color': '#15803d',
                                    'border-color': '#86efac'
                                });
                                $convBtn.attr('style', 'background-color: #00a859 !important; border-color: #00a859 !important; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-radius: 4px; display: inline-block !important;');
                            } else if (newStatus === 'Rejected') {
                                $select.css({
                                    'background-color': '#fee2e2',
                                    'color': '#b91c1c',
                                    'border-color': '#fca5a5'
                                });
                                $convBtn.attr('style', 'display: none !important;');
                            } else if (newStatus === 'Send') {
                                $select.css({
                                    'background-color': '#e0f2fe',
                                    'color': '#0369a1',
                                    'border-color': '#7dd3fc'
                                });
                                $convBtn.attr('style', 'display: none !important;');
                            } else {
                                $select.css({
                                    'background-color': '#f1f5f9',
                                    'color': '#475569',
                                    'border-color': '#cbd5e1'
                                });
                                $convBtn.attr('style', 'display: none !important;');
                            }

                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Status Updated!',
                                    text: 'Quotation status updated to ' + newStatus + ' successfully.',
                                    timer: 2000,
                                    timerProgressBar: true,
                                    showConfirmButton: false,
                                    toast: true,
                                    position: 'top-end'
                                });
                            } else {
                                alert('Quotation status updated to ' + newStatus + ' successfully.');
                            }
                        } else {
                            alert(res.message || 'Error updating quotation status.');
                            location.reload();
                        }
                    },
                    error: function() {
                        $select.prop('disabled', false);
                        alert('Server error occurred while updating quotation status.');
                    }
                });
            });

            // Convert Quotation to Invoice handler
            $(document).on('click', '.btn-convert-invoice', function() {
                var $btn = $(this);
                var qId = $btn.data('id');
                var qNo = $btn.data('no');

                if (!confirm('Are you sure you want to convert Quotation ' + qNo + ' into a Tax Invoice?')) {
                    return false;
                }

                $btn.prop('disabled', true).html('<i class="ti ti-loader ti-spin me-1"></i> Converting...');

                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'convert_quotation_to_invoice',
                        quotation_id: qId
                    },
                    success: function(res) {
                        if (res.status === 'success') {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Converted Successfully!',
                                    text: 'Invoice ' + res.invoice_no + ' has been created.',
                                    confirmButtonText: 'View Invoice',
                                    showCancelButton: true,
                                    cancelButtonText: 'Stay Here'
                                }).then(function(result) {
                                    if (result.isConfirmed) {
                                        window.location.href = 'manage-invoice-form.php?mode=edit&id=' + res.invoice_id;
                                    } else {
                                        location.reload();
                                    }
                                });
                            } else {
                                alert(res.message);
                                window.location.href = 'manage-invoice-form.php?mode=edit&id=' + res.invoice_id;
                            }
                        } else if (res.status === 'already_converted') {
                            alert(res.message);
                            window.location.href = 'manage-invoice-form.php?mode=edit&id=' + res.invoice_id;
                        } else if (res.status === 'insufficient_stock') {
                            $btn.prop('disabled', false).html('<i class="ti ti-transform me-1"></i> Convert to Invoice');
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Insufficient Stock!',
                                    html: '<pre style="text-align:left; white-space:pre-wrap; font-family:inherit; font-size:14px; margin-top:10px;">' + res.message + '</pre>',
                                    confirmButtonText: 'OK'
                                });
                            } else {
                                alert(res.message);
                            }
                        } else {
                            $btn.prop('disabled', false).html('<i class="ti ti-transform me-1"></i> Convert to Invoice');
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: res.message || 'Error converting to invoice.'
                                });
                            } else {
                                alert(res.message || 'Error converting to invoice.');
                            }
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).html('<i class="ti ti-transform me-1"></i> Convert to Invoice');
                        alert('Server error occurred during conversion.');
                    }
                });
            });
        });
    </script>

    <!-- Silent background PDF generation iframe if redirected after Add or Edit -->
    <?php if (!empty($_GET['gen_pdf_id']) && intval($_GET['gen_pdf_id']) > 0) {
        $genId = intval($_GET['gen_pdf_id']);
    ?>
        <iframe id="pdfGenFrame" src="quotation-print.php?id=<?= $genId ?>&silent_gen=1" style="position:fixed; top:-9999px; left:-9999px; width:1024px; height:1400px; border:0; visibility:hidden; pointer-events:none;"></iframe>
        <script>
            console.log('Background PDF generation started for Quotation ID: <?= $genId ?>');
        </script>
    <?php } ?>
</body>

</html>
