<?php
require_once __DIR__ . '/root/config.php';

// Handle AJAX Payment Status Update before any HTML output
if (isset($_POST['action']) && $_POST['action'] === 'update_payment_status') {
    if (ob_get_level()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');


    $quotId = intval($_POST['id'] ?? 0);
    $newPayStatus = ($_POST['payment_status'] === 'Paid') ? 'Paid' : 'Pending';
    $customPaidAmt = isset($_POST['paid_amount']) ? floatval($_POST['paid_amount']) : null;

    if ($quotId > 0) {
        // Fetch current status and party_id before updating
        $qRow = $ai_db->aiGetQueryObj("SELECT party_id, status, grand_total, paid_amount FROM tbl_invoice WHERE id='$quotId' LIMIT 1");
        if (empty($qRow)) {
            echo json_encode(['status' => false, 'message' => 'Invoice not found.']);
            exit;
        }

        // Rule 1: Payment status can ONLY be changed if invoice is Active
        if ($qRow[0]->status !== 'active') {
            echo json_encode(['status' => false, 'message' => 'Cannot change payment status of a Deactive invoice. Please activate it first.']);
            exit;
        }

        $partyId = intval($qRow[0]->party_id ?? 0);
        $grandTotal = floatval($qRow[0]->grand_total ?? 0);

        if ($customPaidAmt !== null) {
            $setPaidAmt = max(0.0, $customPaidAmt);
        } else {
            $setPaidAmt = ($newPayStatus === 'Paid') ? $grandTotal : 0.00;
        }

        $updQry = "UPDATE tbl_invoice SET payment_status='$newPayStatus', paid_amount='$setPaidAmt' WHERE id='$quotId'";
        if (($_SESSION['user_type'] ?? '') === 'company' && !empty($_SESSION['company_id'])) {
            $compFilter = intval($_SESSION['company_id']);
            $updQry .= " AND company_id='$compFilter'";
        }
        $updated = mysqli_query($ai_conn, $updQry);
        if ($updated) {
            // Recalculate party's outstanding balance automatically
            $newPartyBal = 0.00;
            $newPartyType = 'Debit';
            if ($partyId > 0 && function_exists('recalculatePartyOutstanding')) {
                $calcRes = recalculatePartyOutstanding($partyId);
                if (is_array($calcRes)) {
                    $newPartyBal = $calcRes['outstanding'];
                    $newPartyType = $calcRes['outstanding_type'];
                } else {
                    $newPartyBal = $calcRes;
                }
            }
            echo json_encode([
                'status' => true, 
                'message' => 'Payment status updated to ' . $newPayStatus,
                'party_outstanding' => $newPartyBal
            ]);
            exit;
        } else {
            echo json_encode(['status' => false, 'message' => 'Database error: ' . mysqli_error($ai_conn)]);
            exit;
        }
    }
    echo json_encode(['status' => false, 'message' => 'Invalid Invoice ID.']);
    exit;
}

// Handle AJAX Record Payment (Add installment / partial payment)
if (isset($_POST['action']) && $_POST['action'] === 'record_payment') {
    if (ob_get_level()) { ob_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    $invoiceId = intval($_POST['invoice_id'] ?? 0);
    $payAmount = floatval($_POST['amount'] ?? 0);
    $payDate = !empty($_POST['payment_date']) ? date('Y-m-d', strtotime($_POST['payment_date'])) : date('Y-m-d');
    $payMode = addslashes(trim($_POST['payment_mode'] ?? 'Cash'));
    $refNo = addslashes(trim($_POST['reference_no'] ?? ''));
    $notes = addslashes(trim($_POST['notes'] ?? ''));

    if ($invoiceId <= 0 || $payAmount <= 0) {
        echo json_encode(['status' => false, 'message' => 'Please enter a valid payment amount greater than 0.']);
        exit;
    }

    $invRow = $ai_db->aiGetQueryObj("SELECT party_id, company_id, status, grand_total, paid_amount FROM tbl_invoice WHERE id='$invoiceId' LIMIT 1");
    if (empty($invRow)) {
        echo json_encode(['status' => false, 'message' => 'Invoice not found.']);
        exit;
    }

    if ($invRow[0]->status !== 'active') {
        echo json_encode(['status' => false, 'message' => 'Cannot record payment for a Deactive invoice.']);
        exit;
    }

    $partyId = intval($invRow[0]->party_id ?? 0);
    $companyId = intval($invRow[0]->company_id ?? 0);
    $grandTotal = floatval($invRow[0]->grand_total ?? 0);
    $currentPaid = floatval($invRow[0]->paid_amount ?? 0);

    // 1. Insert into tbl_invoice_payments
    $insSql = "INSERT INTO tbl_invoice_payments SET 
        invoice_id='$invoiceId',
        party_id='$partyId',
        company_id='$companyId',
        amount='$payAmount',
        payment_date='$payDate',
        payment_mode='$payMode',
        reference_no='$refNo',
        notes='$notes'";
    $insRes = mysqli_query($ai_conn, $insSql);

    if (!$insRes) {
        echo json_encode(['status' => false, 'message' => 'Database error recording payment: ' . mysqli_error($ai_conn)]);
        exit;
    }

    // 2. Sum up total payments for this invoice
    $sumRow = $ai_db->aiGetQueryObj("SELECT COALESCE(SUM(amount), 0) AS total_paid FROM tbl_invoice_payments WHERE invoice_id='$invoiceId'");
    $newTotalPaid = !empty($sumRow) ? floatval($sumRow[0]->total_paid) : ($currentPaid + $payAmount);

    // 3. Update invoice payment_status and paid_amount
    if ($newTotalPaid >= $grandTotal && $grandTotal > 0) {
        $newPayStatus = 'Paid';
    } elseif ($newTotalPaid > 0) {
        $newPayStatus = 'Partially Paid';
    } else {
        $newPayStatus = 'Pending';
    }

    mysqli_query($ai_conn, "UPDATE tbl_invoice SET paid_amount='$newTotalPaid', payment_status='$newPayStatus' WHERE id='$invoiceId'");

    // 4. Recalculate party outstanding
    $newPartyBal = 0.00;
    $newPartyType = 'Debit';
    if ($partyId > 0 && function_exists('recalculatePartyOutstanding')) {
        $calcRes = recalculatePartyOutstanding($partyId);
        if (is_array($calcRes)) {
            $newPartyBal = $calcRes['outstanding'];
            $newPartyType = $calcRes['outstanding_type'];
        }
    }

    echo json_encode([
        'status' => true,
        'message' => 'Payment of ₹' . number_format($payAmount, 2) . ' recorded successfully!',
        'new_paid_total' => $newTotalPaid,
        'new_status' => $newPayStatus,
        'party_outstanding' => $newPartyBal,
        'party_outstanding_type' => $newPartyType
    ]);
    exit;
}

// Handle AJAX Fetch Payment History for an Invoice
if (isset($_GET['action']) && $_GET['action'] === 'get_payment_history') {
    if (ob_get_level()) { ob_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    $invoiceId = intval($_GET['invoice_id'] ?? 0);
    $invRow = $ai_db->aiGetQueryObj("SELECT id, invoice_no, party_name, grand_total, paid_amount, payment_status FROM tbl_invoice WHERE id='$invoiceId' LIMIT 1");
    if (empty($invRow)) {
        echo json_encode(['status' => false, 'message' => 'Invoice not found.']);
        exit;
    }

    $inv = $invRow[0];
    $gTotal = floatval($inv->grand_total ?? 0);
    $pTotal = floatval($inv->paid_amount ?? 0);
    $dueAmt = max(0.0, $gTotal - $pTotal);

    $history = $ai_db->aiGetQueryObj("SELECT * FROM tbl_invoice_payments WHERE invoice_id='$invoiceId' ORDER BY payment_date DESC, id DESC");

    echo json_encode([
        'status' => true,
        'invoice' => [
            'id' => $inv->id,
            'invoice_no' => $inv->invoice_no,
            'party_name' => $inv->party_name,
            'grand_total' => $gTotal,
            'paid_amount' => $pTotal,
            'due_amount' => $dueAmt,
            'payment_status' => $inv->payment_status
        ],
        'history' => $history ?: []
    ]);
    exit;
}

include('includes/header.php');

$page_nm = "Invoice";
$pageUrl = "manage-invoice-list.php";
$table = "tbl_invoice";


// Handle status toggle for Invoice (active/deactive) and recalculate Party Outstanding
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $toggleId = intval($_GET['id'] ?? 0);
    $toStatus = $_GET['to'] ?? '';
    if ($toggleId > 0 && in_array($toStatus, ['active', 'deactive'], true)) {
        $qRow = $ai_db->aiGetQueryObj("SELECT party_id, payment_status FROM tbl_invoice WHERE id='$toggleId' LIMIT 1");
        
        // Rule 2: If payment status is Paid, status CANNOT be changed (locked)
        if (!empty($qRow) && ($qRow[0]->payment_status ?? '') === 'Paid') {
            $ai_core->aiGoPage($pageUrl . '?msg=paid_locked');
            exit;
        }

        $partyId = !empty($qRow) ? intval($qRow[0]->party_id) : 0;

        $safeTo = addslashes($toStatus);
        $ai_db->aiQuery("UPDATE tbl_invoice SET status='$safeTo' WHERE id='$toggleId'");

        if ($partyId > 0 && function_exists('recalculatePartyOutstanding')) {
            recalculatePartyOutstanding($partyId);
        }

        $ai_core->aiGoPage($pageUrl);
        exit;
    }
}

// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $del_id = intval($_GET['id'] ?? 0);
    if ($del_id > 0) {
        $qRow = $ai_db->aiGetQueryObj("SELECT party_id, payment_status FROM tbl_invoice WHERE id='$del_id' LIMIT 1");
        
        // Block delete if invoice payment is Paid
        if (!empty($qRow) && ($qRow[0]->payment_status ?? '') === 'Paid') {
            $ai_core->aiGoPage($pageUrl . '?msg=paid_locked');
            exit;
        }

        $partyId = !empty($qRow) ? intval($qRow[0]->party_id) : 0;

        // Restore product stock before deleting invoice items
        if (function_exists('restoreInvoiceProductStock')) {
            restoreInvoiceProductStock($del_id);
        }

        $ai_db->aiQuery("DELETE FROM tbl_invoice WHERE id='$del_id'");
        $ai_db->aiQuery("DELETE FROM tbl_invoice_items WHERE invoice_id='$del_id'");
        $ai_db->aiQuery("DELETE FROM tbl_invoice_payments WHERE invoice_id='$del_id'");

        if ($partyId > 0 && function_exists('recalculatePartyOutstanding')) {
            recalculatePartyOutstanding($partyId);
        }

        $ai_core->aiGoPage($pageUrl . '?msg=3');
        exit;
    }
}

$company_where = "";
if (($_SESSION['user_type'] ?? '') === 'company' && !empty($_SESSION['company_id'])) {
    $company_where = " AND q.company_id = '" . intval($_SESSION['company_id']) . "'";
}

$filter_payment = $_GET['payment_status'] ?? 'all';
$pay_where = "";
$todayDate = date('Y-m-d');
if ($filter_payment === 'Pending') {
    $pay_where = " AND q.payment_status = 'Pending'";
} elseif ($filter_payment === 'Partially Paid') {
    $pay_where = " AND q.payment_status = 'Partially Paid'";
} elseif ($filter_payment === 'Paid') {
    $pay_where = " AND q.payment_status = 'Paid'";
} elseif ($filter_payment === 'Overdue') {
    $pay_where = " AND q.payment_status IN ('Pending','Partially Paid') AND q.due_date IS NOT NULL AND q.due_date < '{$todayDate}'";
}

$qry = "SELECT q.*, c.company_name, s.state_name 
        FROM tbl_invoice q 
        LEFT JOIN tbl_company c ON q.company_id = c.id 
        LEFT JOIN tbl_state s ON q.state_id = s.id
        WHERE 1=1
        $company_where
        $pay_where
        ORDER BY q.id DESC";
$invoices = $ai_db->aiGetQueryObj($qry);

// Metrics for Top Summary Cards (Total, Paid, Unpaid) matching user UI design
$metricsQry = "SELECT 
    COUNT(id) AS total_count,
    COALESCE(SUM(grand_total), 0) AS total_amt,
    COUNT(CASE WHEN payment_status = 'Paid' THEN 1 END) AS paid_count,
    COALESCE(SUM(paid_amount), 0) AS paid_amt,
    COUNT(CASE WHEN payment_status != 'Paid' THEN 1 END) AS unpaid_count,
    COALESCE(SUM(CASE WHEN payment_status != 'Paid' THEN (grand_total - paid_amount) ELSE 0 END), 0) AS unpaid_amt,
    COUNT(CASE WHEN payment_status != 'Paid' AND due_date IS NOT NULL AND due_date < '{$todayDate}' THEN 1 END) AS overdue_count,
    COALESCE(SUM(CASE WHEN payment_status != 'Paid' AND due_date IS NOT NULL AND due_date < '{$todayDate}' THEN (grand_total - paid_amount) ELSE 0 END), 0) AS overdue_amt
FROM tbl_invoice q
WHERE 1=1 {$company_where}";
$metricsRes = $ai_db->aiGetQueryObj($metricsQry);
$m = !empty($metricsRes) ? $metricsRes[0] : null;

$statTotalCount = intval($m->total_count ?? 0);
$statTotalAmt = floatval($m->total_amt ?? 0);
$statPaidCount = intval($m->paid_count ?? 0);
$statPaidAmt = floatval($m->paid_amt ?? 0);
$statUnpaidCount = intval($m->unpaid_count ?? 0);
$statUnpaidAmt = floatval($m->unpaid_amt ?? 0);
$statOverdueCount = intval($m->overdue_count ?? 0);
$statOverdueAmt = floatval($m->overdue_amt ?? 0);
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
                                Invoice added successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } elseif (isset($_GET['msg']) && $_GET['msg'] == '2') { ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                Invoice updated successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } elseif (isset($_GET['msg']) && $_GET['msg'] == '3') { ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                Invoice deleted successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } elseif (isset($_GET['msg']) && $_GET['msg'] == 'paid_locked') { ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                ⚠ <strong>Action not allowed:</strong> Invoice cannot be edited or modified because its payment is already <strong>Paid</strong>.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } ?>


                        <div class="card">
                            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <h5 class="mb-0"><?= $page_nm ?> List</h5>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="d-flex align-items-center">
                                        <label for="filterPaymentStatus" class="me-2 text-muted fw-medium text-nowrap" style="font-size: 0.9rem;">Filter Status:</label>
                                        <select id="filterPaymentStatus" class="form-select form-select-sm" style="width: auto; min-width: 155px; border-color: #dbdade; color: #5d596c; font-size: 0.875rem;height: 47px; border-radius: 10px;" onchange="window.location.href='manage-invoice-list.php?payment_status=' + this.value;">
                                            <option value="all" <?= ($filter_payment === 'all') ? 'selected' : '' ?>>All Invoices</option>
                                            <option value="Pending" <?= ($filter_payment === 'Pending') ? 'selected' : '' ?>>Pending</option>
                                            <option value="Overdue" <?= ($filter_payment === 'Overdue') ? 'selected' : '' ?>>Overdue</option>
                                            <option value="Paid" <?= ($filter_payment === 'Paid') ? 'selected' : '' ?>>Paid</option>
                                        </select>
                                    </div>
                                    <a href="manage-invoice-form.php?mode=add" class="btn btn-primary btn-lg">
                                        <i class="ti ti-plus me-1"></i> Add New
                                    </a>
                                </div>
                            </div>

                            <div class="card-datatable">
                                <table class="datatables-ajax table table-bordered align-middle mb-0 text-center w-100" style="font-size:15px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 4%;">#</th>
                                            <th style="width: 14%;">Invoice No.</th>
                                            <th style="width: 11%;">Date</th>
                                            <th style="width: 22%;" class="text-start">Party Name</th>
                                            <th style="width: 16%;" class="text-start">GST / State</th>
                                            <th style="width: 13%;" class="text-start">Grand Total</th>
                                            <th style="width: 9%;">Payment</th>
                                            <th style="width: 6%;">Status</th>
                                            <th style="width: 5%;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($invoices) {
                                            $i = 1;
                                            foreach ($invoices as $q) { 
                                                $payStatus = !empty($q->payment_status) ? $q->payment_status : 'Pending';
                                                $badgeStyle = ($payStatus === 'Paid') ? 'background-color:#dcfce7; color:#15803d; border-color:#86efac;' : 'background-color:#fef3c7; color:#b45309; border-color:#fcd34d;';
                                                $isDeactive = (strtolower(trim($q->status ?? '')) === 'deactive');
                                                $isPaid = ($payStatus === 'Paid');
                                            ?>
                                                <tr>
                                                    <td><?= $i++ ?></td>

                                                    <!-- Invoice No -->
                                                    <td class="text-start">
                                                        <span class="badge bg-label-dark font-monospace text-wrap" style="font-size: 13px;"><?= htmlspecialchars($q->invoice_no) ?></span>
                                                    </td>

                                                    <!-- Date -->
                                                    <td class="text-nowrap" style="font-size: 13.5px;">
                                                        <div><i class="ti ti-calendar me-1"></i><?= !empty($q->invoice_date) ? date('d/m/Y', strtotime($q->invoice_date)) : '-' ?></div>
                                                        <?php if (!empty($q->due_date)) { 
                                                            $isOverdue = ($payStatus === 'Pending' && $q->due_date < date('Y-m-d'));
                                                        ?>
                                                            <div class="mt-1" title="Due Date">
                                                                <?php if ($isOverdue) { ?>
                                                                    <span class="badge bg-label-danger px-1 py-0 fw-bold" style="font-size: 11px;">
                                                                        <i class="ti ti-alert-triangle me-1"></i><?= date('d/m/Y', strtotime($q->due_date)) ?>
                                                                    </span>
                                                                <?php } else { ?>
                                                                    <span class="badge bg-label-warning px-1 py-0" style="font-size: 11px;">
                                                                        Due: <?= date('d/m/Y', strtotime($q->due_date)) ?>
                                                                    </span>
                                                                <?php } ?>
                                                            </div>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- Party Name -->
                                                    <td class="text-start">
                                                        <div class="fw-bold text-dark text-truncate" style="font-size: 14.5px;" title="<?= htmlspecialchars($q->party_name) ?>"><?= htmlspecialchars($q->party_name) ?></div>
                                                        <?php if (!empty($q->company_name)) { ?>
                                                            <div class="text-muted text-truncate small"><?= htmlspecialchars($q->company_name) ?></div>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- GST No / State -->
                                                    <td class="text-start" style="font-size: 13px;">
                                                        <?php if (!empty($q->gst_no)) { ?>
                                                            <div class="text-nowrap"><i class="ti ti-receipt-tax me-1"></i><?= htmlspecialchars($q->gst_no) ?></div>
                                                        <?php } ?>
                                                        <div class="small text-muted text-truncate"><i class="ti ti-map-pin me-1"></i><?= htmlspecialchars($q->state_name ?? '-') ?></div>
                                                    </td>

                                                    <!-- Grand Total & Paid Breakdown -->
                                                    <td class="text-start" style="font-size: 13.5px;">
                                                        <div class="fw-bold text-success text-nowrap" style="font-size: 14.5px;">₹<?= number_format($q->grand_total, 2) ?></div>
                                                        <?php 
                                                            $pAmt = floatval($q->paid_amount ?? 0); 
                                                            $dueAmt = max(0.0, floatval($q->grand_total) - $pAmt);
                                                        ?>
                                                        <div class="text-muted text-nowrap" style="font-size: 12px;">
                                                            Paid: <span class="fw-semibold text-primary">₹<?= number_format($pAmt, 2) ?></span>
                                                        </div>
                                                        <?php if ($dueAmt > 0 && $pAmt > 0) { ?>
                                                            <div class="text-danger fw-semibold text-nowrap" style="font-size: 12px;">
                                                                Due: ₹<?= number_format($dueAmt, 2) ?>
                                                            </div>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- Payment Status & Quick Actions -->
                                                    <td>
                                                        <div class="d-flex flex-column align-items-center gap-1">
                                                            <?php if ($payStatus === 'Paid') { ?>
                                                                <span class="badge bg-label-success px-2 py-1 fw-bold" style="font-size: 12px;">
                                                                    <i class="ti ti-check me-1"></i> Paid
                                                                </span>
                                                            <?php } elseif ($payStatus === 'Partially Paid') { ?>
                                                                <span class="badge bg-label-warning px-2 py-1 fw-bold" style="font-size: 12px;">
                                                                    <i class="ti ti-clock-pause me-1"></i> Partial
                                                                </span>
                                                            <?php } else { ?>
                                                                <span class="badge bg-label-secondary px-2 py-1 fw-bold" style="font-size: 12px;">
                                                                    <i class="ti ti-clock me-1"></i> Pending
                                                                </span>
                                                            <?php } ?>

                                                            <?php if (!$isDeactive && $payStatus !== 'Paid') { ?>
                                                                <button type="button" 
                                                                        class="btn btn-xs btn-primary waves-effect waves-light mt-1 btn-record-payment" 
                                                                        data-id="<?= $q->id ?>" 
                                                                        data-no="<?= htmlspecialchars($q->invoice_no) ?>"
                                                                        data-party="<?= htmlspecialchars($q->party_name) ?>"
                                                                        data-total="<?= number_format($q->grand_total, 2, '.', '') ?>"
                                                                        data-paid="<?= number_format($pAmt, 2, '.', '') ?>"
                                                                        data-due="<?= number_format($dueAmt, 2, '.', '') ?>"
                                                                        style="font-size: 11px; padding: 2px 8px; border-radius: 4px;">
                                                                    <i class="ti ti-plus me-1"></i> Pay
                                                                </button>
                                                            <?php } ?>

                                                            <?php if ($pAmt > 0) { ?>
                                                                <a href="javascript:void(0);" 
                                                                   class="text-muted small btn-view-history" 
                                                                   data-id="<?= $q->id ?>"
                                                                   style="font-size: 11px; text-decoration: underline;">
                                                                    <i class="ti ti-history me-1"></i> History
                                                                </a>
                                                            <?php } ?>
                                                        </div>
                                                    </td>

                                                    <!-- Status -->
                                                    <td>
                                                        <?php if ($isPaid) { ?>
                                                            <span class="badge bg-label-success px-2 py-1 opacity-75" title="Invoice is paid and locked" style="cursor:not-allowed; font-size: 12px;">Active <i class="ti ti-lock ms-1"></i></span>
                                                        <?php } else { ?>
                                                            <?= ai_render_status_toggle($pageUrl, $q->id, $q->status) ?>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- Action -->
                                                    <td>
                                                        <div class="dropdown">
                                                            <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                                <i class="ti ti-dots-vertical fs-4"></i>
                                                            </button>
                                                            <div class="dropdown-menu dropdown-menu-end">
                                                                <a class="dropdown-item" href="invoice-print.php?id=<?= $q->id ?>" target="_blank">
                                                                    <i class="ti ti-printer me-1"></i> Print / View
                                                                </a>
                                                                <a class="dropdown-item" href="invoice-print.php?id=<?= $q->id ?>&download=1" target="_blank">
                                                                    <i class="ti ti-download me-1"></i> Download PDF
                                                                </a>
                                                                <?php if ($isPaid) { ?>
                                                                    <a class="dropdown-item text-muted disabled" href="javascript:void(0);" title="Cannot edit: Invoice payment is Paid" style="cursor:not-allowed; opacity: 0.6;">
                                                                        <i class="ti ti-lock me-1"></i> Edit <span class="badge bg-label-secondary ms-1">Paid Locked</span>
                                                                    </a>
                                                                <?php } else { ?>
                                                                    <a class="dropdown-item" href="manage-invoice-form.php?mode=edit&id=<?= $q->id ?>">
                                                                        <i class="ti ti-pencil me-1"></i> Edit
                                                                    </a>
                                                                    <a class="dropdown-item text-danger" href="manage-invoice-list.php?action=delete&id=<?= $q->id ?>" onclick="return confirm('Are you sure you want to delete this invoice?');">
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

    <!-- MODAL: RECORD PAYMENT -->
    <div class="modal fade" id="modalRecordPayment" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="formRecordPayment">
                    <input type="hidden" name="action" value="record_payment">
                    <input type="hidden" name="invoice_id" id="modal_pay_invoice_id" value="">

                    <div class="modal-header bg-light pb-3">
                        <h5 class="modal-title fw-bold" id="modalRecordPaymentTitle">💳 Record Payment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <!-- Invoice Info Banner -->
                        <div class="p-3 mb-3 rounded border" style="background:#f1f5f9;">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Invoice No:</span>
                                <strong id="modal_inv_no" class="font-monospace text-dark">-</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Party Name:</span>
                                <strong id="modal_party_name" class="text-dark">-</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Total Invoice Amount:</span>
                                <strong class="text-dark" id="modal_grand_total">₹0.00</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Already Paid:</span>
                                <strong class="text-success" id="modal_already_paid">₹0.00</strong>
                            </div>
                            <div class="d-flex justify-content-between pt-1 border-top">
                                <span class="fw-bold small text-danger">Current Due:</span>
                                <strong class="text-danger fs-6" id="modal_due_amount">₹0.00</strong>
                            </div>
                        </div>

                        <!-- Payment Fields -->
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-muted">Payment Amount (₹) <span class="badge bg-label-info ms-1">Full Due Amount</span></label>
                            <input type="number" step="0.01" min="0.01" name="amount" id="modal_amount" class="form-control form-control-lg fw-bold text-success bg-light" placeholder="0.00" readonly required>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-bold small text-uppercase text-muted">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" name="payment_date" id="modal_payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-bold small text-uppercase text-muted">Payment Mode</label>
                                <select name="payment_mode" id="modal_payment_mode" class="form-select">
                                    <option value="Cash">Cash</option>
                                    <option value="UPI / GPay / PhonePe">UPI / GPay / PhonePe</option>
                                    <option value="Bank Transfer (NEFT/RTGS/IMPS)">Bank Transfer</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-muted">Reference / Transaction No.</label>
                            <input type="text" name="reference_no" id="modal_reference_no" class="form-control" placeholder="UPI Ref, Cheque No, Transaction ID...">
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-bold small text-uppercase text-muted">Notes / Remark</label>
                            <textarea name="notes" id="modal_notes" class="form-control" rows="2" placeholder="Optional remark..."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer bg-light pt-2 pb-2">
                        <button type="button" class="btn btn-secondary waves-effect" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success waves-effect waves-light" id="btnSubmitPayment">
                            <i class="ti ti-check me-1"></i> Save Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: PAYMENT HISTORY -->
    <div class="modal fade" id="modalPaymentHistory" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light pb-3">
                    <h5 class="modal-title fw-bold">📋 Payment History - <span id="hist_inv_no" class="font-monospace text-primary"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="d-flex flex-wrap justify-content-between p-3 mb-3 rounded bg-light border">
                        <div><strong>Party:</strong> <span id="hist_party_name">-</span></div>
                        <div><strong>Total:</strong> <span id="hist_grand_total" class="fw-bold text-dark">₹0.00</span></div>
                        <div><strong>Paid:</strong> <span id="hist_paid_total" class="fw-bold text-success">₹0.00</span></div>
                        <div><strong>Remaining Due:</strong> <span id="hist_due_total" class="fw-bold text-danger">₹0.00</span></div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover align-middle mb-0 text-center" style="font-size:14px;">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Amount (₹)</th>
                                    <th>Mode</th>
                                    <th>Reference No.</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody id="hist_table_body">
                                <tr><td colspan="6" class="text-muted py-3">Loading history...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light pt-2 pb-2">
                    <button type="button" class="btn btn-secondary waves-effect" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
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
                    targets: [6, 7, 8],
                    orderable: false
                }]
            });

            // Open Record Payment Modal
            $(document).on('click', '.btn-record-payment', function() {
                var invId = $(this).data('id');
                var invNo = $(this).data('no');
                var party = $(this).data('party');
                var total = parseFloat($(this).data('total')) || 0;
                var paid = parseFloat($(this).data('paid')) || 0;
                var due = parseFloat($(this).data('due')) || 0;

                $('#modal_pay_invoice_id').val(invId);
                $('#modal_inv_no').text(invNo);
                $('#modal_party_name').text(party);
                $('#modal_grand_total').text('₹' + total.toFixed(2));
                $('#modal_already_paid').text('₹' + paid.toFixed(2));
                $('#modal_due_amount').text('₹' + due.toFixed(2));
                $('#modal_amount').val(due > 0 ? due.toFixed(2) : '').attr('max', due > 0 ? (due * 2).toFixed(2) : '');
                $('#modal_reference_no').val('');
                $('#modal_notes').val('');

                $('#modalRecordPayment').modal('show');
            });

            // Submit Record Payment Form
            $('#formRecordPayment').on('submit', function(e) {
                e.preventDefault();
                var $btn = $('#btnSubmitPayment');
                var formData = $(this).serialize();

                $btn.prop('disabled', true).html('<i class="ti ti-loader ti-spin me-1"></i> Saving...');

                $.ajax({
                    url: 'manage-invoice-list.php',
                    type: 'POST',
                    dataType: 'json',
                    data: formData,
                    success: function(res) {
                        $btn.prop('disabled', false).html('<i class="ti ti-check me-1"></i> Save Payment');
                        if (res.status) {
                            $('#modalRecordPayment').modal('hide');
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Payment Recorded!',
                                    text: res.message,
                                    timer: 1800,
                                    showConfirmButton: false,
                                    toast: true,
                                    position: 'top-end'
                                }).then(function() {
                                    location.reload();
                                });
                            } else {
                                alert(res.message);
                                location.reload();
                            }
                        } else {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: res.message || 'Failed to record payment.'
                                });
                            } else {
                                alert(res.message || 'Failed to record payment.');
                            }
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).html('<i class="ti ti-check me-1"></i> Save Payment');
                        alert('Server error occurred while saving payment.');
                    }
                });
            });

            // View Payment History Modal
            $(document).on('click', '.btn-view-history', function() {
                var invId = $(this).data('id');
                var tbody = $('#hist_table_body');
                tbody.html('<tr><td colspan="6" class="text-muted py-3">Loading history...</td></tr>');
                $('#modalPaymentHistory').modal('show');

                $.ajax({
                    url: 'manage-invoice-list.php',
                    type: 'GET',
                    dataType: 'json',
                    data: { action: 'get_payment_history', invoice_id: invId },
                    success: function(res) {
                        if (res.status) {
                            $('#hist_inv_no').text(res.invoice.invoice_no);
                            $('#hist_party_name').text(res.invoice.party_name);
                            $('#hist_grand_total').text('₹' + parseFloat(res.invoice.grand_total).toFixed(2));
                            $('#hist_paid_total').text('₹' + parseFloat(res.invoice.paid_amount).toFixed(2));
                            $('#hist_due_total').text('₹' + parseFloat(res.invoice.due_amount).toFixed(2));

                            if (res.history && res.history.length > 0) {
                                var html = '';
                                $.each(res.history, function(idx, item) {
                                    var pDate = item.payment_date ? item.payment_date.split('-').reverse().join('/') : '-';
                                    html += '<tr>' +
                                        '<td>' + (idx + 1) + '</td>' +
                                        '<td>' + pDate + '</td>' +
                                        '<td class="fw-bold text-success">₹' + parseFloat(item.amount).toFixed(2) + '</td>' +
                                        '<td><span class="badge bg-label-info">' + (item.payment_mode || 'Cash') + '</span></td>' +
                                        '<td>' + (item.reference_no || '-') + '</td>' +
                                        '<td>' + (item.notes || '-') + '</td>' +
                                    '</tr>';
                                });
                                tbody.html(html);
                            } else {
                                tbody.html('<tr><td colspan="6" class="text-muted py-3">No payment entries found yet.</td></tr>');
                            }
                        } else {
                            tbody.html('<tr><td colspan="6" class="text-danger py-3">' + (res.message || 'Error loading history.') + '</td></tr>');
                        }
                    },
                    error: function() {
                        tbody.html('<tr><td colspan="6" class="text-danger py-3">Failed to load payment history.</td></tr>');
                    }
                });
            });
        });
    </script>
</body>
</html>
