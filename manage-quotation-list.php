<?php
require_once __DIR__ . '/root/config.php';

// Handle AJAX Payment Status Update before any HTML output
if (isset($_POST['action']) && $_POST['action'] === 'update_payment_status') {
    if (ob_get_level()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');

    // Ensure table & column exist
    $colCheck = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_quotation LIKE 'payment_status'");
    if ($colCheck && mysqli_num_rows($colCheck) === 0) {
        @mysqli_query($ai_conn, "ALTER TABLE tbl_quotation ADD COLUMN `payment_status` ENUM('Pending','Paid') NOT NULL DEFAULT 'Pending' AFTER `grand_total`");
    }

    $quotId = intval($_POST['id'] ?? 0);
    $newPayStatus = ($_POST['payment_status'] === 'Paid') ? 'Paid' : 'Pending';

    if ($quotId > 0) {
        // Fetch current status and party_id before updating
        $qRow = $ai_db->aiGetQueryObj("SELECT party_id, status FROM tbl_quotation WHERE id='$quotId' LIMIT 1");
        if (empty($qRow)) {
            echo json_encode(['status' => false, 'message' => 'Quotation not found.']);
            exit;
        }

        // Rule 1: Payment status can ONLY be changed if quotation is Active
        if ($qRow[0]->status !== 'active') {
            echo json_encode(['status' => false, 'message' => 'Cannot change payment status of a Deactive quotation. Please activate it first.']);
            exit;
        }

        $partyId = intval($qRow[0]->party_id ?? 0);

        $updQry = "UPDATE tbl_quotation SET payment_status='$newPayStatus' WHERE id='$quotId'";
        if (($_SESSION['user_type'] ?? '') === 'company' && !empty($_SESSION['company_id'])) {
            $compFilter = intval($_SESSION['company_id']);
            $updQry .= " AND company_id='$compFilter'";
        }
        $updated = mysqli_query($ai_conn, $updQry);
        if ($updated) {
            // Recalculate party's outstanding balance automatically
            $newPartyBal = 0.00;
            if ($partyId > 0 && function_exists('recalculatePartyOutstanding')) {
                $newPartyBal = recalculatePartyOutstanding($partyId);
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
    echo json_encode(['status' => false, 'message' => 'Invalid Quotation ID.']);
    exit;
}

include('includes/header.php');

$page_nm = "Quotation";
$pageUrl = "manage-quotation-list.php";
$table = "tbl_quotation";

// Ensure tables exist
$ai_db->aiQuery("CREATE TABLE IF NOT EXISTS `tbl_quotation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT 0,
  `party_id` int(11) DEFAULT 0,
  `party_name` varchar(255) DEFAULT '',
  `gst_no` varchar(50) DEFAULT '',
  `quotation_no` varchar(50) DEFAULT '',
  `rca` varchar(10) DEFAULT 'No',
  `address` text DEFAULT NULL,
  `state_id` int(11) DEFAULT 0,
  `state_name` varchar(100) DEFAULT '',
  `city_id` int(11) DEFAULT 0,
  `city_name` varchar(100) DEFAULT '',
  `quotation_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `gst_type` varchar(20) DEFAULT 'with_gst',
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `cgst_amount` decimal(12,2) DEFAULT 0.00,
  `sgst_amount` decimal(12,2) DEFAULT 0.00,
  `igst_amount` decimal(12,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `grand_total` decimal(12,2) DEFAULT 0.00,
  `payment_status` enum('Pending','Paid') NOT NULL DEFAULT 'Pending',
  `status` varchar(20) DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Check if payment_status column exists, if not add it
$colCheck = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_quotation LIKE 'payment_status'");
if ($colCheck && mysqli_num_rows($colCheck) === 0) {
    @mysqli_query($ai_conn, "ALTER TABLE tbl_quotation ADD COLUMN `payment_status` ENUM('Pending','Paid') NOT NULL DEFAULT 'Pending' AFTER `grand_total`");
}
$colCheckDueList = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_quotation LIKE 'due_date'");
if ($colCheckDueList && mysqli_num_rows($colCheckDueList) === 0) {
    @mysqli_query($ai_conn, "ALTER TABLE tbl_quotation ADD COLUMN `due_date` DATE NULL DEFAULT NULL AFTER `quotation_date`");
}

$ai_db->aiQuery("CREATE TABLE IF NOT EXISTS `tbl_quotation_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `hsn_code` varchar(50) DEFAULT '',
  `rate` decimal(12,2) DEFAULT 0.00,
  `gst_percent` decimal(5,2) DEFAULT 0.00,
  `qty` decimal(10,2) DEFAULT 1.00,
  `net_amount` decimal(12,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Handle status toggle for Quotation (active/deactive) and recalculate Party Outstanding
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $toggleId = intval($_GET['id'] ?? 0);
    $toStatus = $_GET['to'] ?? '';
    if ($toggleId > 0 && in_array($toStatus, ['active', 'deactive'], true)) {
        $qRow = $ai_db->aiGetQueryObj("SELECT party_id, payment_status FROM tbl_quotation WHERE id='$toggleId' LIMIT 1");
        
        // Rule 2: If payment status is Paid, status CANNOT be changed (locked)
        if (!empty($qRow) && ($qRow[0]->payment_status ?? '') === 'Paid') {
            $ai_core->aiGoPage($pageUrl . '?msg=paid_locked');
            exit;
        }

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

// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $del_id = intval($_GET['id'] ?? 0);
    if ($del_id > 0) {
        $qRow = $ai_db->aiGetQueryObj("SELECT party_id FROM tbl_quotation WHERE id='$del_id' LIMIT 1");
        $partyId = !empty($qRow) ? intval($qRow[0]->party_id) : 0;

        $ai_db->aiQuery("DELETE FROM tbl_quotation WHERE id='$del_id'");
        $ai_db->aiQuery("DELETE FROM tbl_quotation_items WHERE quotation_id='$del_id'");

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
} elseif ($filter_payment === 'Paid') {
    $pay_where = " AND q.payment_status = 'Paid'";
} elseif ($filter_payment === 'Overdue') {
    $pay_where = " AND q.payment_status = 'Pending' AND q.due_date IS NOT NULL AND q.due_date < '{$todayDate}'";
}

$qry = "SELECT q.*, c.company_name 
        FROM tbl_quotation q 
        LEFT JOIN tbl_company c ON q.company_id = c.id 
        WHERE 1=1
        $company_where
        $pay_where
        ORDER BY q.id DESC";
$quotations = $ai_db->aiGetQueryObj($qry);

// Metrics for Top Summary Cards (Total, Paid, Unpaid) matching user UI design
$metricsQry = "SELECT 
    COUNT(id) AS total_count,
    COALESCE(SUM(grand_total), 0) AS total_amt,
    COUNT(CASE WHEN payment_status = 'Paid' THEN 1 END) AS paid_count,
    COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN grand_total ELSE 0 END), 0) AS paid_amt,
    COUNT(CASE WHEN payment_status = 'Pending' THEN 1 END) AS unpaid_count,
    COALESCE(SUM(CASE WHEN payment_status = 'Pending' THEN grand_total ELSE 0 END), 0) AS unpaid_amt,
    COUNT(CASE WHEN payment_status = 'Pending' AND due_date IS NOT NULL AND due_date < '{$todayDate}' THEN 1 END) AS overdue_count,
    COALESCE(SUM(CASE WHEN payment_status = 'Pending' AND due_date IS NOT NULL AND due_date < '{$todayDate}' THEN grand_total ELSE 0 END), 0) AS overdue_amt
FROM tbl_quotation q
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
                        <?php } ?>

                        <!-- 3 SUMMARY METRIC CARDS (TOTAL, PAID, UNPAID / OVERDUE) -->
                        <div class="row g-3 mb-4">
                            <!-- CARD 1: TOTAL -->
                            <div class="col-12 col-md-4">
                                <a href="manage-quotation-list.php?payment_status=all" class="text-decoration-none">
                                    <div class="card h-100 border-0 shadow-sm text-white" 
                                         style="background: linear-gradient(135deg, #1e70e8 0%, #3b82f6 100%); border-radius: 16px; transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: pointer; <?= ($filter_payment === 'all') ? 'outline: 3px solid #1d4ed8; transform: translateY(-2px);' : '' ?>"
                                         onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 20px rgba(30,112,232,0.3)';" 
                                         onmouseout="this.style.transform='<?= ($filter_payment === 'all') ? 'translateY(-2px)' : 'none' ?>'; this.style.boxShadow='none';">
                                        <div class="card-body p-4">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-uppercase fw-bold text-white-50" style="letter-spacing: 1px; font-size: 13px;">TOTAL</span>
                                                <div class="avatar p-2 rounded-circle" style="background: rgba(255, 255, 255, 0.2); width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="ti ti-file-invoice fs-4 text-white"></i>
                                                </div>
                                            </div>
                                            <h1 class="mb-1 text-white fw-bold display-6" style="font-size: 38px; line-height: 1.1;"><?= $statTotalCount ?></h1>
                                            <div class="text-white-50 fw-semibold" style="font-size: 14px;">
                                                ₹<?= number_format($statTotalAmt, 2) ?>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <!-- CARD 2: PAID -->
                            <div class="col-12 col-md-4">
                                <a href="manage-quotation-list.php?payment_status=Paid" class="text-decoration-none">
                                    <div class="card h-100 border-0 shadow-sm text-white" 
                                         style="background: linear-gradient(135deg, #22c55e 0%, #10b981 100%); border-radius: 16px; transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: pointer; <?= ($filter_payment === 'Paid') ? 'outline: 3px solid #15803d; transform: translateY(-2px);' : '' ?>"
                                         onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 20px rgba(34,197,94,0.3)';" 
                                         onmouseout="this.style.transform='<?= ($filter_payment === 'Paid') ? 'translateY(-2px)' : 'none' ?>'; this.style.boxShadow='none';">
                                        <div class="card-body p-4">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-uppercase fw-bold text-white-50" style="letter-spacing: 1px; font-size: 13px;">PAID</span>
                                                <div class="avatar p-2 rounded-circle" style="background: rgba(255, 255, 255, 0.2); width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="ti ti-circle-check fs-4 text-white"></i>
                                                </div>
                                            </div>
                                            <h1 class="mb-1 text-white fw-bold display-6" style="font-size: 38px; line-height: 1.1;"><?= $statPaidCount ?></h1>
                                            <div class="text-white-50 fw-semibold" style="font-size: 14px;">
                                                ₹<?= number_format($statPaidAmt, 2) ?>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <!-- CARD 3: UNPAID / OVERDUE -->
                            <div class="col-12 col-md-4">
                                <a href="manage-quotation-list.php?payment_status=Pending" class="text-decoration-none">
                                    <div class="card h-100 border-0 shadow-sm text-white" 
                                         style="background: linear-gradient(135deg, #ef4444 0%, #f87171 100%); border-radius: 16px; transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: pointer; <?= ($filter_payment === 'Pending' || $filter_payment === 'Overdue') ? 'outline: 3px solid #b91c1c; transform: translateY(-2px);' : '' ?>"
                                         onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 20px rgba(239,68,68,0.3)';" 
                                         onmouseout="this.style.transform='<?= ($filter_payment === 'Pending' || $filter_payment === 'Overdue') ? 'translateY(-2px)' : 'none' ?>'; this.style.boxShadow='none';">
                                        <div class="card-body p-4">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="text-uppercase fw-bold text-white-50" style="letter-spacing: 1px; font-size: 13px;">UNPAID</span>
                                                <div class="avatar p-2 rounded-circle" style="background: rgba(255, 255, 255, 0.2); width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="ti ti-rotate-clockwise fs-4 text-white"></i>
                                                </div>
                                            </div>
                                            <h1 class="mb-1 text-white fw-bold display-6" style="font-size: 38px; line-height: 1.1;"><?= $statUnpaidCount ?></h1>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="text-white-50 fw-semibold" style="font-size: 14px;">
                                                    ₹<?= number_format($statUnpaidAmt, 2) ?>
                                                </div>
                                                <?php if ($statOverdueCount > 0) { ?>
                                                    <span class="badge bg-white text-danger fw-bold px-2 py-1 shadow-sm" style="font-size: 11px;">
                                                        <?= $statOverdueCount ?> Overdue
                                                    </span>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <h5 class="mb-0"><?= $page_nm ?> List</h5>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="d-flex align-items-center">
                                        <label for="filterPaymentStatus" class="me-2 fw-semibold text-muted small text-nowrap">Filter Status:</label>
                                        <select id="filterPaymentStatus" class="form-select form-select-sm" style="min-width: 140px;" onchange="window.location.href='manage-quotation-list.php?payment_status=' + this.value;">
                                            <option value="all" <?= ($filter_payment === 'all') ? 'selected' : '' ?>>All Quotations</option>
                                            <option value="Pending" <?= ($filter_payment === 'Pending') ? 'selected' : '' ?>>⏳ Pending</option>
                                            <option value="Overdue" <?= ($filter_payment === 'Overdue') ? 'selected' : '' ?>>⚠ Overdue</option>
                                            <option value="Paid" <?= ($filter_payment === 'Paid') ? 'selected' : '' ?>>✓ Paid</option>
                                        </select>
                                    </div>
                                    <a href="manage-quotation-form.php?mode=add" class="btn btn-primary btn-sm">
                                        <i class="ti ti-plus me-1"></i> Add New Quotation
                                    </a>
                                </div>
                            </div>

                            <div class="card-datatable table-responsive">
                                <table class="datatables-ajax table table-bordered align-middle mb-0 text-center" style="font-size:15px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Quotation No.</th>
                                            <th>Date</th>
                                            <th>Party Name</th>
                                            <th>GST No. / State</th>
                                            <th>GST Type</th>
                                            <th>Grand Total</th>
                                            <th style="width: 25% !important">Payment Status</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($quotations) {
                                            $i = 1;
                                            foreach ($quotations as $q) { 
                                                $payStatus = !empty($q->payment_status) ? $q->payment_status : 'Pending';
                                                $badgeStyle = ($payStatus === 'Paid') ? 'background-color:#dcfce7; color:#15803d; border-color:#86efac;' : 'background-color:#fef3c7; color:#b45309; border-color:#fcd34d;';
                                                $isDeactive = (strtolower(trim($q->status ?? '')) === 'deactive');
                                                $isPaid = ($payStatus === 'Paid');
                                            ?>
                                                <tr>
                                                    <td><?= $i++ ?></td>

                                                    <!-- Quotation No -->
                                                    <td class="text-start">
                                                        <span class="badge bg-label-dark font-monospace fs-6"><?= htmlspecialchars($q->quotation_no) ?></span>
                                                    </td>

                                                    <!-- Date -->
                                                    <td>
                                                        <div><i class="ti ti-calendar me-1"></i><?= !empty($q->quotation_date) ? date('d/m/Y', strtotime($q->quotation_date)) : '-' ?></div>
                                                        <?php if (!empty($q->due_date)) { 
                                                            $isOverdue = ($payStatus === 'Pending' && $q->due_date < date('Y-m-d'));
                                                        ?>
                                                            <div class="mt-1" title="Due Date">
                                                                <?php if ($isOverdue) { ?>
                                                                    <span class="badge bg-label-danger px-2 py-1 fw-bold" style="font-size: 11px;">
                                                                        <i class="ti ti-alert-triangle me-1"></i>Overdue: <?= date('d/m/Y', strtotime($q->due_date)) ?>
                                                                    </span>
                                                                <?php } else { ?>
                                                                    <span class="badge bg-label-warning px-2 py-0" style="font-size: 11px;">
                                                                        Due: <?= date('d/m/Y', strtotime($q->due_date)) ?>
                                                                    </span>
                                                                <?php } ?>
                                                            </div>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- Party Name -->
                                                    <td class="text-start">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div>
                                                                <div class="fw-bold text-dark"><?= htmlspecialchars($q->party_name) ?></div>
                                                                <?php if (!empty($q->company_name)) { ?>
                                                                    <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($q->company_name) ?></div>
                                                                <?php } ?>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <!-- GST No / State -->
                                                    <td class="text-start">
                                                        <?php if (!empty($q->gst_no)) { ?>
                                                            <div><i class="ti ti-receipt-tax me-1"></i><?= htmlspecialchars($q->gst_no) ?></div>
                                                        <?php } ?>
                                                        <div class="small text-muted"><i class="ti ti-map-pin me-1"></i><?= htmlspecialchars($q->state_name ?? '-') ?></div>
                                                    </td>

                                                    <!-- GST Type -->
                                                    <td>
                                                        <?php if ($q->gst_type === 'without_gst') { ?>
                                                            <span class="badge bg-label-warning px-3 py-2">Without GST</span>
                                                        <?php } else { ?>
                                                            <span class="badge bg-label-info px-3 py-2">With GST</span>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- Grand Total -->
                                                    <td class="fw-bold text-success">
                                                        ₹<?= number_format($q->grand_total, 2) ?>
                                                    </td>

                                                    <!-- Payment Status Dropdown -->
                                                    <td>
                                                        <select class="form-select form-select-sm payment-status-dropdown fw-semibold" 
                                                                data-id="<?= $q->id ?>" 
                                                                <?= $isDeactive ? 'disabled title="Cannot change payment status while quotation is deactive"' : '' ?>
                                                                style="font-size: 13px; font-weight: 600; border-radius: 6px; cursor: <?= $isDeactive ? 'not-allowed' : 'pointer' ?>; <?= $badgeStyle ?> <?= $isDeactive ? 'opacity:0.6;' : '' ?>">
                                                            <option value="Pending" <?= ($payStatus === 'Pending') ? 'selected' : '' ?>>⏳ Pending</option>
                                                            <option value="Paid" <?= ($payStatus === 'Paid') ? 'selected' : '' ?>>✓ Paid</option>
                                                        </select>
                                                        <?php if ($isDeactive) { ?>
                                                            <div class="text-muted" style="font-size:10px;">Activate quotation first</div>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- Status -->
                                                    <td>
                                                        <?php if ($isPaid) { ?>
                                                            <span class="badge bg-label-success px-3 py-2 fs-6 opacity-75" title="Quotation is paid and locked" style="cursor:not-allowed;">Active <i class="ti ti-lock ms-1"></i></span>
                                                        <?php } else { ?>
                                                            <?= ai_render_status_toggle($pageUrl, $q->id, $q->status) ?>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- Action -->
                                                    <td>
                                                        <div class="d-inline-flex gap-1">
                                                            <a href="quotation-print.php?id=<?= $q->id ?>" target="_blank" class="btn btn-sm btn-icon btn-label-info" title="Print / PDF View">
                                                                <i class="ti ti-printer"></i>
                                                            </a>
                                                            <a href="manage-quotation-form.php?mode=edit&id=<?= $q->id ?>" class="btn btn-sm btn-icon btn-label-primary" title="Edit">
                                                                <i class="ti ti-pencil"></i>
                                                            </a>
                                                            <a href="javascript:void(0);" onclick="if(confirm('Are you sure you want to delete this quotation?')){ window.location.href='manage-quotation-list.php?action=delete&id=<?= $q->id ?>'; }" class="btn btn-sm btn-icon btn-label-danger" title="Delete">
                                                                <i class="ti ti-trash"></i>
                                                            </a>
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
                    targets: [7, 8, 9],
                    orderable: false
                }]
            });

            // AJAX Handler for Payment Status Change
            $(document).on('change', '.payment-status-dropdown', function() {
                var $select = $(this);
                var quotationId = $select.data('id');
                var newStatus = $select.val();
                var previousStatus = (newStatus === 'Paid') ? 'Pending' : 'Paid';

                $select.prop('disabled', true);

                $.ajax({
                    url: 'manage-quotation-list.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'update_payment_status',
                        id: quotationId,
                        payment_status: newStatus
                    },
                    success: function(res) {
                        $select.prop('disabled', false);
                        if (res.status) {
                            if (newStatus === 'Paid') {
                                $select.css({
                                    'background-color': '#dcfce7',
                                    'color': '#15803d',
                                    'border-color': '#86efac'
                                });
                            } else {
                                $select.css({
                                    'background-color': '#fef3c7',
                                    'color': '#b45309',
                                    'border-color': '#fcd34d'
                                });
                            }

                            // Show alert message
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Status Updated!',
                                    text: 'Payment status changed to ' + newStatus + ' successfully.',
                                    timer: 2000,
                                    timerProgressBar: true,
                                    showConfirmButton: false,
                                    toast: true,
                                    position: 'top-end'
                                });
                            } else {
                                alert('Payment status changed to ' + newStatus + ' successfully.');
                            }
                        } else {
                            $select.val(previousStatus);
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Update Failed',
                                    text: res.message || 'Error updating payment status.',
                                    timer: 3000,
                                    toast: true,
                                    position: 'top-end'
                                });
                            } else {
                                alert(res.message || 'Error updating payment status.');
                            }
                        }
                    },
                    error: function(xhr) {
                        $select.prop('disabled', false);
                        $select.val(previousStatus);
                        var errMsg = 'Server error occurred while updating payment status.';
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: errMsg,
                                timer: 3000,
                                toast: true,
                                position: 'top-end'
                            });
                        } else {
                            alert(errMsg);
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>
