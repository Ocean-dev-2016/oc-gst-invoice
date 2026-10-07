<?php
include('includes/header.php');

$page_nm = 'Quotation';
$table = 'tbl_quotation';
$redirection_url = 'manage-quotation-list.php';

error_reporting(E_ALL);

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

// Check if columns exist
$colCheckForm = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_quotation LIKE 'payment_status'");
if ($colCheckForm && mysqli_num_rows($colCheckForm) === 0) {
    @mysqli_query($ai_conn, "ALTER TABLE tbl_quotation ADD COLUMN `payment_status` ENUM('Pending','Paid') NOT NULL DEFAULT 'Pending' AFTER `grand_total`");
}
$colCheckDue = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_quotation LIKE 'due_date'");
if ($colCheckDue && mysqli_num_rows($colCheckDue) === 0) {
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
  `discount_type` enum('percentage','fixed') NOT NULL DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `qty` decimal(10,2) DEFAULT 1.00,
  `net_amount` decimal(12,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$colCheckDisc = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_quotation_items LIKE 'discount_type'");
if ($colCheckDisc && mysqli_num_rows($colCheckDisc) === 0) {
    @mysqli_query($ai_conn, "ALTER TABLE tbl_quotation_items ADD COLUMN `discount_type` ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage' AFTER `gst_percent`");
    @mysqli_query($ai_conn, "ALTER TABLE tbl_quotation_items ADD COLUMN `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `discount_type`");
    @mysqli_query($ai_conn, "ALTER TABLE tbl_quotation_items ADD COLUMN `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `discount_value`");
}

$mode = $_REQUEST['mode'] ?? 'add';
$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

$err_msg = '';
$quotationData = null;
$quotationItems = [];

// Auto Generate Quotation No company wise if mode=add
function generateQuotationNo($ai_db, $company_id = 0) {
    if (function_exists('generateCompanyQuotationNo')) {
        return generateCompanyQuotationNo($ai_db, $company_id);
    }
    $currentYear = date('y');
    $nextYear = date('y', strtotime('+1 year'));
    $fy = $currentYear . '-' . $nextYear;
    
    $prefix = 'OQ';
    if ($company_id > 0) {
        $compRow = $ai_db->aiGetQueryObj("SELECT company_name FROM tbl_company WHERE id='$company_id' LIMIT 1");
        if (!empty($compRow) && !empty($compRow[0]->company_name)) {
            $words = preg_split('/\s+/', trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $compRow[0]->company_name)), -1, PREG_SPLIT_NO_EMPTY);
            if (count($words) >= 2) {
                $prefix = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
            } else {
                $prefix = strtoupper(substr($words[0], 0, min(2, strlen($words[0]))));
            }
        }
    }
    
    $whereComp = ($company_id > 0) ? "WHERE company_id='$company_id'" : "";
    $maxRow = $ai_db->aiGetQueryObj("SELECT quotation_no FROM tbl_quotation $whereComp ORDER BY id DESC LIMIT 1");
    $nextNum = 1;
    if (!empty($maxRow)) {
        $qNo = $maxRow[0]->quotation_no;
        if (preg_match('~/(\d+)/~', $qNo, $matches)) {
            $nextNum = intval($matches[1]) + 1;
        } else {
            $nextNum = count($ai_db->aiGetQueryObj("SELECT id FROM tbl_quotation $whereComp")) + 1;
        }
    }
    
    return $prefix . '/' . str_pad($nextNum, 3, '0', STR_PAD_LEFT) . '/' . $fy;
}

$userType = $_SESSION['user_type'] ?? '';
$session_company_id = intval($_SESSION['company_id'] ?? 0);
$current_company_id = ($userType === 'company') ? $session_company_id : intval($_POST['company_id'] ?? 0);

// Fetch master data for dropdowns
$companies = $ai_db->aiGetQueryObj("SELECT id, company_name FROM tbl_company WHERE status='active' ORDER BY company_name ASC");

// Fetch parties and products based on logged-in company or selected company
$partyWhere = ($current_company_id > 0) ? "WHERE status='active' AND company_id='$current_company_id'" : "WHERE status='active'";
$parties = $ai_db->aiGetQueryObj("SELECT id, party_name, gst_no, address, state_id, city_id FROM tbl_party $partyWhere ORDER BY party_name ASC");

$productWhere = ($current_company_id > 0) ? "WHERE status='active' AND company_id='$current_company_id'" : "WHERE status='active'";
$products = $ai_db->aiGetQueryObj("SELECT id, product_name, hsn_code, sales_price FROM tbl_product $productWhere ORDER BY product_name ASC");

$states = $ai_db->aiGetQueryObj("SELECT id, state_name, state_code FROM tbl_state WHERE status='active' ORDER BY order_no ASC, state_name ASC");

$default_quotation_no = generateQuotationNo($ai_db, $current_company_id);

// Handle POST save / update
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_POST['btn_submit'])) {
    $company_id = intval($_POST['company_id'] ?? ($_SESSION['company_id'] ?? 0));
    if (($_SESSION['user_type'] ?? '') === 'company' && !empty($_SESSION['company_id'])) {
        $company_id = intval($_SESSION['company_id']);
    }
    $party_id = intval($_POST['party_id'] ?? 0);
    $party_name = addslashes(trim($_POST['party_name'] ?? ''));
    $gst_no = addslashes(trim($_POST['gst_no'] ?? ''));
    $quotation_no = addslashes(trim($_POST['quotation_no'] ?? ''));
    if (empty($quotation_no) && $mode === 'add') {
        $quotation_no = generateQuotationNo($ai_db, $company_id);
    }
    $rca = addslashes(trim($_POST['rca'] ?? 'No'));
    $address = addslashes(trim($_POST['address'] ?? ''));
    $state_id = intval($_POST['state_id'] ?? 0);
    $city_id = intval($_POST['city_id'] ?? 0);
    $city_name = addslashes(trim($_POST['city_name'] ?? ''));
    $quotation_date = !empty($_POST['quotation_date']) ? date('Y-m-d', strtotime($_POST['quotation_date'])) : date('Y-m-d');
    $due_date = !empty($_POST['due_date']) ? date('Y-m-d', strtotime($_POST['due_date'])) : null;
    $due_date_sql = $due_date ? "'$due_date'" : "NULL";
    $gst_type = addslashes(trim($_POST['gst_type'] ?? 'with_gst')); // with_gst OR without_gst
    
    $total_amount = floatval($_POST['total_amount'] ?? 0);
    $cgst_amount = floatval($_POST['cgst_amount'] ?? 0);
    $sgst_amount = floatval($_POST['sgst_amount'] ?? 0);
    $igst_amount = floatval($_POST['igst_amount'] ?? 0);
    $tax_amount = floatval($_POST['tax_amount'] ?? 0);
    $grand_total = floatval($_POST['grand_total'] ?? 0);
    $payment_status = (isset($_POST['payment_status']) && $_POST['payment_status'] === 'Paid') ? 'Paid' : 'Pending';
    $status = $_POST['status'] ?? 'active';

    // Get State Name
    $state_name = '';
    if ($state_id > 0) {
        $stObj = $ai_db->aiGetQueryObj("SELECT state_name FROM tbl_state WHERE id='$state_id' LIMIT 1");
        if ($stObj) $state_name = addslashes($stObj[0]->state_name);
    }

    if (empty($party_name)) {
        $err_msg = "Please enter or select Party Name!";
    } elseif (empty($quotation_no)) {
        $err_msg = "Please enter Quotation Number!";
    } elseif (!empty($due_date) && !empty($quotation_date) && $due_date < $quotation_date) {
        $err_msg = "Due Date cannot be earlier than Quotation Date!";
    } elseif (!empty($gst_no) && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i', $gst_no)) {
        $err_msg = "Please enter a valid 15-digit GST Number (e.g. 24AAAAA0000A1Z5)!";
    } else {
        if ($mode === 'add') {
            $insert_qry = "INSERT INTO tbl_quotation SET
                company_id='$company_id',
                party_id='$party_id',
                party_name='$party_name',
                gst_no='$gst_no',
                quotation_no='$quotation_no',
                rca='$rca',
                address='$address',
                state_id='$state_id',
                state_name='$state_name',
                city_id='$city_id',
                city_name='$city_name',
                quotation_date='$quotation_date',
                due_date=$due_date_sql,
                gst_type='$gst_type',
                total_amount='$total_amount',
                cgst_amount='$cgst_amount',
                sgst_amount='$sgst_amount',
                igst_amount='$igst_amount',
                tax_amount='$tax_amount',
                grand_total='$grand_total',
                payment_status='$payment_status',
                status='$status'";

            $ai_db->aiQuery($insert_qry);
            $quotation_id = $ai_db->aiLastInsert();

            // Insert Items
            if (!empty($_POST['item_description']) && is_array($_POST['item_description'])) {
                foreach ($_POST['item_description'] as $idx => $desc) {
                    $desc = addslashes(trim($desc));
                    if (empty($desc)) continue;
                    $prod_id = intval($_POST['item_product_id'][$idx] ?? 0);
                    $hsn = addslashes(trim($_POST['item_hsn'][$idx] ?? ''));
                    $rate = floatval($_POST['item_rate'][$idx] ?? 0);
                    $gst_pct = floatval($_POST['item_gst_pct'][$idx] ?? 0);
                    $disc_type = in_array($_POST['item_discount_type'][$idx] ?? '', ['percentage', 'fixed'], true) ? $_POST['item_discount_type'][$idx] : 'percentage';
                    $disc_val = floatval($_POST['item_discount_val'][$idx] ?? 0);
                    $disc_amt = floatval($_POST['item_discount_amt'][$idx] ?? 0);
                    $qty = floatval($_POST['item_qty'][$idx] ?? 1);
                    $net_amt = floatval($_POST['item_net_amt'][$idx] ?? 0);
                    $item_tax = floatval($_POST['item_tax_amt'][$idx] ?? 0);
                    $item_total = floatval($_POST['item_total_amt'][$idx] ?? 0);

                    $item_qry = "INSERT INTO tbl_quotation_items SET
                        quotation_id='$quotation_id',
                        product_id='$prod_id',
                        description='$desc',
                        hsn_code='$hsn',
                        rate='$rate',
                        gst_percent='$gst_pct',
                        discount_type='$disc_type',
                        discount_value='$disc_val',
                        discount_amount='$disc_amt',
                        qty='$qty',
                        net_amount='$net_amt',
                        tax_amount='$item_tax',
                        total_amount='$item_total'";
                    $ai_db->aiQuery($item_qry);
                }
            }

            // Recalculate party outstanding automatically
            if ($party_id > 0 && function_exists('recalculatePartyOutstanding')) {
                recalculatePartyOutstanding($party_id);
            }

            $ai_core->aiGoPage($redirection_url . '?msg=1');
            exit;
        } elseif ($mode === 'edit' && $id > 0) {
            // Check previous party_id in case party was changed
            $prevQ = $ai_db->aiGetQueryObj("SELECT party_id FROM tbl_quotation WHERE id='$id' LIMIT 1");
            $prevPartyId = !empty($prevQ) ? intval($prevQ[0]->party_id) : 0;

            $update_qry = "UPDATE tbl_quotation SET
                company_id='$company_id',
                party_id='$party_id',
                party_name='$party_name',
                gst_no='$gst_no',
                quotation_no='$quotation_no',
                rca='$rca',
                address='$address',
                state_id='$state_id',
                state_name='$state_name',
                city_id='$city_id',
                city_name='$city_name',
                quotation_date='$quotation_date',
                due_date=$due_date_sql,
                gst_type='$gst_type',
                total_amount='$total_amount',
                cgst_amount='$cgst_amount',
                sgst_amount='$sgst_amount',
                igst_amount='$igst_amount',
                tax_amount='$tax_amount',
                grand_total='$grand_total',
                payment_status='$payment_status',
                status='$status'
                WHERE id='$id'";

            $ai_db->aiQuery($update_qry);

            // Delete old items and re-insert
            $ai_db->aiQuery("DELETE FROM tbl_quotation_items WHERE quotation_id='$id'");

            if (!empty($_POST['item_description']) && is_array($_POST['item_description'])) {
                foreach ($_POST['item_description'] as $idx => $desc) {
                    $desc = addslashes(trim($desc));
                    if (empty($desc)) continue;
                    $prod_id = intval($_POST['item_product_id'][$idx] ?? 0);
                    $hsn = addslashes(trim($_POST['item_hsn'][$idx] ?? ''));
                    $rate = floatval($_POST['item_rate'][$idx] ?? 0);
                    $gst_pct = floatval($_POST['item_gst_pct'][$idx] ?? 0);
                    $disc_type = in_array($_POST['item_discount_type'][$idx] ?? '', ['percentage', 'fixed'], true) ? $_POST['item_discount_type'][$idx] : 'percentage';
                    $disc_val = floatval($_POST['item_discount_val'][$idx] ?? 0);
                    $disc_amt = floatval($_POST['item_discount_amt'][$idx] ?? 0);
                    $qty = floatval($_POST['item_qty'][$idx] ?? 1);
                    $net_amt = floatval($_POST['item_net_amt'][$idx] ?? 0);
                    $item_tax = floatval($_POST['item_tax_amt'][$idx] ?? 0);
                    $item_total = floatval($_POST['item_total_amt'][$idx] ?? 0);

                    $item_qry = "INSERT INTO tbl_quotation_items SET
                        quotation_id='$id',
                        product_id='$prod_id',
                        description='$desc',
                        hsn_code='$hsn',
                        rate='$rate',
                        gst_percent='$gst_pct',
                        discount_type='$disc_type',
                        discount_value='$disc_val',
                        discount_amount='$disc_amt',
                        qty='$qty',
                        net_amount='$net_amt',
                        tax_amount='$item_tax',
                        total_amount='$item_total'";
                    $ai_db->aiQuery($item_qry);
                }
            }

            // Recalculate party outstanding (both current party and previous party if changed)
            if (function_exists('recalculatePartyOutstanding')) {
                if ($party_id > 0) recalculatePartyOutstanding($party_id);
                if ($prevPartyId > 0 && $prevPartyId !== $party_id) recalculatePartyOutstanding($prevPartyId);
            }

            $ai_core->aiGoPage($redirection_url . '?msg=2');
            exit;
        }
    }
}

// Fetch record for Edit mode
if ($mode === 'edit' && $id > 0) {
    $qRes = $ai_db->aiGetQueryObj("SELECT * FROM tbl_quotation WHERE id='$id' LIMIT 1");
    if ($qRes) {
        $quotationData = $qRes[0];
        $quotationItems = $ai_db->aiGetQueryObj("SELECT * FROM tbl_quotation_items WHERE quotation_id='$id'");
        if (!empty($quotationItems)) {
            foreach ($quotationItems as &$qItem) {
                if (empty($qItem->product_id) || intval($qItem->product_id) <= 0) {
                    $escDesc = addslashes($qItem->description);
                    $compFlt = !empty($quotationData->company_id) ? " AND company_id='{$quotationData->company_id}'" : "";
                    $pFind = $ai_db->aiGetQueryObj("SELECT id FROM tbl_product WHERE LOWER(TRIM(product_name)) = LOWER(TRIM('$escDesc')) $compFlt LIMIT 1");
                    if ($pFind && !empty($pFind[0]->id)) {
                        $qItem->product_id = intval($pFind[0]->id);
                    }
                }
            }
            unset($qItem);
        }
    }
}

$selected_company_id = $_POST['company_id'] ?? $quotationData->company_id ?? ($_SESSION['company_id'] ?? 0);
if ($selected_company_id > 0) {
    $parties = $ai_db->aiGetQueryObj("SELECT id, party_name, gst_no, address, state_id, city_id FROM tbl_party WHERE status='active' AND company_id='$selected_company_id' ORDER BY party_name ASC");
    $products = $ai_db->aiGetQueryObj("SELECT id, product_name, hsn_code, sales_price FROM tbl_product WHERE status='active' AND company_id='$selected_company_id' ORDER BY product_name ASC");
    if ($mode === 'add' && empty($_POST['quotation_no'])) {
        $default_quotation_no = generateQuotationNo($ai_db, $selected_company_id);
    }
}
$selected_state_id = $_POST['state_id'] ?? $quotationData->state_id ?? 0;
$selected_city_id = $_POST['city_id'] ?? $quotationData->city_id ?? 0;
$cities = [];
if ($selected_state_id > 0) {
    $cities = $ai_db->aiGetQueryObj("SELECT id, city_name FROM tbl_city WHERE state_id='" . intval($selected_state_id) . "' AND status='active' ORDER BY order_no ASC, city_name ASC");
}
?>

<style>
.quotation-card {
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    background: #ffffff;
}
.form-section-title {
    font-size: 15px;
    font-weight: 700;
    color: #444;
    border-bottom: 2px solid #eee;
    padding-bottom: 8px;
    margin-bottom: 20px;
}
.btn-submit-orange {
    background-color: #f37021 !important;
    border-color: #f37021 !important;
    color: #fff !important;
    font-weight: 600;
}
.btn-submit-orange:hover {
    background-color: #d95e14 !important;
    border-color: #d95e14 !important;
}
.btn-cancel-orange {
    background-color: #f37021 !important;
    border-color: #f37021 !important;
    color: #fff !important;
    font-weight: 600;
}
.btn-add-product {
    background-color: #10b981 !important;
    border-color: #10b981 !important;
    color: #fff !important;
    font-weight: 600;
}
.btn-add-product:hover {
    background-color: #059669 !important;
}
.summary-box {
    width: 320px;
    margin-left: auto;
}
.summary-label {
    background-color: #f1f3f5;
    font-weight: 600;
    color: #495057;
    width: 130px;
    display: inline-block;
    padding: 6px 12px;
    border-radius: 4px 0 0 4px;
}
.summary-input {
    border-radius: 0 4px 4px 0 !important;
    text-align: right;
    font-weight: 600;
}
</style>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <?php include('sidebar.php'); ?>

            <div class="layout-page">
                <?php include('navbar.php'); ?>

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="fw-bold m-0"><?= ($mode === 'edit') ? 'Edit' : 'Add' ?> Quotation</h4>
                            <div class="d-flex gap-2">
                                <?php if ($mode === 'edit' && $id > 0) { ?>
                                    <a href="quotation-print.php?id=<?= $id ?>&download=1" target="_blank" class="btn btn-success">
                                        <i class="ti ti-download me-1"></i> Download PDF
                                    </a>
                                    <a href="quotation-print.php?id=<?= $id ?>" target="_blank" class="btn btn-info">
                                        <i class="ti ti-printer me-1"></i> Print / PDF
                                    </a>
                                <?php } ?>
                                <a href="<?= $redirection_url ?>" class="btn btn-secondary">
                                    <i class="ti ti-arrow-left me-1"></i> Back to List
                                </a>
                            </div>
                        </div>

                        <?php if (!empty($err_msg)) { ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($err_msg) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } ?>

                        <form id="quotationForm" method="POST" action="">
                            <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">
                            <input type="hidden" name="id" value="<?= intval($id) ?>">

                            <div class="card quotation-card p-4 mb-4">
                                <div class="form-section-title d-flex justify-content-between align-items-center">
                                    <span><?= ($mode === 'edit') ? 'Edit Quotation Details' : 'Add Quotation' ?></span>
                                </div>

                                <?php if (($_SESSION['user_type'] ?? '') !== 'company' && !empty($companies)) { ?>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold text-uppercase">SELECT COMPANY <span class="text-danger">*</span></label>
                                            <select name="company_id" id="company_id" class="form-select select2" required>
                                                <option value="">-- Select Company --</option>
                                                <?php foreach ($companies as $comp) { ?>
                                                    <option value="<?= $comp->id ?>" <?= ($selected_company_id == $comp->id) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($comp->company_name) ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                <?php } else { ?>
                                    <input type="hidden" name="company_id" value="<?= htmlspecialchars($selected_company_id) ?>">
                                <?php } ?>

                                <!-- ROW 1: PARTY NAME | GST NO | QUOTATION NO | RCA -->
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold text-uppercase small text-muted">PARTY NAME <span class="text-danger">*</span></label>
                                        <select name="party_id" id="party_select" class="form-select select2" required>
                                            <option value="">-- Select Party --</option>
                                            <?php foreach ($parties as $pty) { ?>
                                                <option value="<?= $pty->id ?>" 
                                                    data-name="<?= htmlspecialchars($pty->party_name) ?>"
                                                    data-gst="<?= htmlspecialchars($pty->gst_no) ?>"
                                                    data-address="<?= htmlspecialchars($pty->address) ?>"
                                                    data-state="<?= $pty->state_id ?>"
                                                    data-city="<?= $pty->city_id ?>"
                                                    <?= (isset($quotationData->party_id) && $quotationData->party_id == $pty->id) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($pty->party_name) ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                        <input type="hidden" name="party_name" id="party_name" value="<?= htmlspecialchars($quotationData->party_name ?? '') ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label fw-bold text-uppercase small text-muted">GST NO.</label>
                                        <input type="text" name="gst_no" id="gst_no" class="form-control bg-light" placeholder="Party GST no." value="<?= htmlspecialchars($quotationData->gst_no ?? '') ?>" readonly>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label fw-bold text-uppercase small text-muted">QUOTATION NO. <span class="text-danger">*</span></label>
                                        <input type="text" name="quotation_no" id="quotation_no" class="form-control bg-light" placeholder="OQ/008/26-27" value="<?= htmlspecialchars($quotationData->quotation_no ?? $default_quotation_no) ?>" readonly required>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label fw-bold text-uppercase small text-muted">RCA</label>
                                        <select name="rca" id="rca" class="form-select">
                                            <option value="No" <?= (($quotationData->rca ?? 'No') === 'No') ? 'selected' : '' ?>>No</option>
                                            <option value="Yes" <?= (($quotationData->rca ?? '') === 'Yes') ? 'selected' : '' ?>>Yes</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- ROW 2: ADDRESS | STATE | CITY | DATE | GST TYPE -->
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold text-uppercase small text-muted">ADDRESS</label>
                                        <textarea name="address" id="address" class="form-control" rows="1" placeholder="Enter address"><?= htmlspecialchars($quotationData->address ?? '') ?></textarea>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label fw-bold text-uppercase small text-muted">STATE <span class="text-danger">*</span></label>
                                        <select name="state_id" id="state_id" class="form-select select2" required>
                                            <option value="">Select State</option>
                                            <?php foreach ($states as $st) { ?>
                                                <option value="<?= $st->id ?>" data-name="<?= htmlspecialchars($st->state_name) ?>" <?= ($selected_state_id == $st->id) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($st->state_name) ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label fw-bold text-uppercase small text-muted">CITY</label>
                                        <select name="city_id" id="city_id" class="form-select select2">
                                            <option value="">Enter city / Select</option>
                                            <?php foreach ($cities as $ct) { ?>
                                                <option value="<?= $ct->id ?>" <?= ($selected_city_id == $ct->id) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($ct->city_name) ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                        <input type="hidden" name="city_name" id="city_name_input" value="<?= htmlspecialchars($quotationData->city_name ?? '') ?>">
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label fw-bold text-uppercase small text-muted">DATE</label>
                                        <input type="date" name="quotation_date" id="quotation_date" class="form-control" value="<?= htmlspecialchars($quotationData->quotation_date ?? date('Y-m-d')) ?>">
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label fw-bold text-uppercase small text-muted">DUE DATE</label>
                                        <input type="date" name="due_date" id="due_date" class="form-control" min="<?= htmlspecialchars($quotationData->quotation_date ?? date('Y-m-d')) ?>" value="<?= htmlspecialchars(!empty($quotationData->due_date) ? $quotationData->due_date : date('Y-m-d')) ?>">
                                    </div>

                                    <!-- REQUESTED GST TYPE DROPDOWN -->
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold text-uppercase small text-muted">GST TYPE <span class="text-danger">*</span></label>
                                        <select name="gst_type" id="gst_type" class="form-select fw-bold">
                                            <option value="with_gst" <?= (($quotationData->gst_type ?? 'with_gst') === 'with_gst') ? 'selected' : '' ?>>With GST</option>
                                            <option value="without_gst" <?= (($quotationData->gst_type ?? '') === 'without_gst') ? 'selected' : '' ?>>Without GST</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- PRODUCT DETAILS SECTION -->
                            <div class="card quotation-card p-4 mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="fw-bold m-0 text-dark">Add Product Details</h5>
                                </div>

                                <!-- PRODUCT INPUT ROW -->
                                <div class="row g-2 align-items-end mb-4 bg-light p-3 rounded">
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold text-uppercase small text-muted">DESCRIPTION</label>
                                        <select id="product_select" class="form-select select2">
                                            <option value="">-- Select Product --</option>
                                            <?php foreach ($products as $p) { ?>
                                                <option value="<?= $p->id ?>"
                                                    data-title="<?= htmlspecialchars($p->product_name) ?>"
                                                    data-hsn="<?= htmlspecialchars($p->hsn_code) ?>"
                                                    data-rate="<?= $p->sales_price ?>">
                                                    <?= htmlspecialchars($p->product_name) ?> (₹<?= number_format($p->sales_price, 2) ?>)
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label fw-bold text-uppercase small text-muted">HSN CODE</label>
                                        <input type="text" id="input_hsn" class="form-control" placeholder="HSN Code">
                                    </div>

                                    <div class="col-md-1">
                                        <label class="form-label fw-bold text-uppercase small text-muted">RATE</label>
                                        <input type="number" step="0.01" id="input_rate" class="form-control" placeholder="Rate">
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label fw-bold text-uppercase small text-muted">DISCOUNT TYPE</label>
                                        <select id="input_discount_type" class="form-select">
                                            <option value="percentage">Percentage (%)</option>
                                            <option value="fixed">Fixed Amount (₹)</option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label fw-bold text-uppercase small text-muted">DISCOUNT VALUE</label>
                                        <input type="number" step="0.01" min="0" id="input_discount_val" class="form-control" placeholder="0.00" value="0">
                                    </div>

                                    <div class="col-md-2">
                                        <label class="form-label fw-bold text-uppercase small text-muted">SELECT GST</label>
                                        <div class="input-group">
                                            <select id="input_gst_pct" class="form-select">
                                                <option value="18" selected>18%</option>
                                                <option value="12">12%</option>
                                                <option value="5">5%</option>
                                                <option value="28">28%</option>
                                                <option value="0">0%</option>
                                            </select>
                                            <button type="button" id="btnAddItem" class="btn btn-success px-3" title="Add Product to list">
                                                <i class="ti ti-plus fw-bold fs-5"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- PRODUCT ITEMS TABLE -->
                                <div class="table-responsive mb-4">
                                    <table class="table table-bordered align-middle text-center" id="itemsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 5%;">SR NO.</th>
                                                <th style="width: 25%;" class="text-start">DESCRIPTION</th>
                                                <th style="width: 10%;">HSN CODE</th>
                                                <th style="width: 10%;">RATE</th>
                                                <th style="width: 10%;">QTY</th>
                                                <th style="width: 15%;">DISCOUNT TYPE</th>
                                                <th style="width: 10%;">DISCOUNT VALUE</th>
                                                <th style="width: 10%;">NET AMOUNT</th>
                                                <th style="width: 5%;">ACTION</th>
                                            </tr>
                                        </thead>
                                        <tbody id="itemsTableBody">
                                            <!-- Dynamically populated via JS -->
                                        </tbody>
                                    </table>
                                </div>

                                <!-- TOTALS SUMMARY BOX -->
                                <div class="row">
                                    <div class="col-md-6 ms-auto">
                                        <div class="summary-box">
                                            <!-- Total Amount -->
                                            <div class="input-group mb-2">
                                                <span class="summary-label">Total Amount</span>
                                                <input type="text" name="total_amount" id="total_amount" class="form-control summary-input" value="0.00" readonly>
                                            </div>

                                            <!-- CGST Row -->
                                            <div class="input-group mb-2" id="row_cgst">
                                                <span class="summary-label">CGST</span>
                                                <input type="text" name="cgst_amount" id="cgst_amount" class="form-control summary-input" value="0.00" readonly>
                                            </div>

                                            <!-- SGST Row -->
                                            <div class="input-group mb-2" id="row_sgst">
                                                <span class="summary-label">SGST</span>
                                                <input type="text" name="sgst_amount" id="sgst_amount" class="form-control summary-input" value="0.00" readonly>
                                            </div>

                                            <!-- IGST Row -->
                                            <div class="input-group mb-2" id="row_igst" style="display:none;">
                                                <span class="summary-label">IGST</span>
                                                <input type="text" name="igst_amount" id="igst_amount" class="form-control summary-input" value="0.00" readonly>
                                            </div>

                                            <input type="hidden" name="tax_amount" id="tax_amount" value="0.00">

                                            <!-- Grand Total -->
                                            <div class="input-group mb-3">
                                                <span class="summary-label fw-bold" style="background:#e2e8f0;">Grand Total</span>
                                                <input type="text" name="grand_total" id="grand_total" class="form-control summary-input fs-5 fw-bold text-dark" value="0.00" readonly>
                                            </div>

                                            <!-- Payment Status -->
                                            <div class="input-group mb-3">
                                                <span class="summary-label fw-bold" style="background:#f1f5f9;">Payment Status</span>
                                                <select name="payment_status" id="payment_status" class="form-select summary-input fw-semibold">
                                                    <option value="Pending" <?= (($quotationData->payment_status ?? 'Pending') === 'Pending') ? 'selected' : '' ?>>⏳ Pending</option>
                                                    <option value="Paid" <?= (($quotationData->payment_status ?? '') === 'Paid') ? 'selected' : '' ?>>✓ Paid</option>
                                                </select>
                                            </div>

                                            <!-- ACTION BUTTONS -->
                                            <div class="pt-4 d-flex align-items-center flex-wrap gap-2">
                                                <button type="submit" name="btn_submit" class="btn btn-primary waves-effect waves-light" id="btnSubmit">
                                                    <?= ($mode == 'edit') ? 'Update' : 'Submit' ?>
                                                </button>
                                                <?php if ($mode === 'edit' && $id > 0) { ?>
                                                    <a href="quotation-print.php?id=<?= $id ?>&download=1" target="_blank" class="btn btn-success waves-effect waves-light">
                                                        <i class="ti ti-download me-1"></i> Download PDF
                                                    </a>
                                                    <a href="quotation-print.php?id=<?= $id ?>" target="_blank" class="btn btn-info waves-effect waves-light">
                                                        <i class="ti ti-printer me-1"></i> Print / PDF
                                                    </a>
                                                <?php } ?>
                                                <a href="<?= $redirection_url ?>" class="btn btn-label-secondary waves-effect">Cancel</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </form>

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
        var itemsList = [];

        // Existing items from PHP if Edit mode
        <?php if (!empty($quotationItems)) { ?>
            <?php foreach ($quotationItems as $item) { ?>
                itemsList.push({
                    product_id: <?= intval($item->product_id ?? 0) ?>,
                    description: <?= json_encode($item->description) ?>,
                    hsn_code: <?= json_encode($item->hsn_code) ?>,
                    rate: <?= floatval($item->rate) ?>,
                    gst_percent: <?= floatval($item->gst_percent) ?>,
                    discount_type: <?= json_encode(!empty($item->discount_type) ? $item->discount_type : 'percentage') ?>,
                    discount_value: <?= floatval($item->discount_value ?? 0) ?>,
                    qty: <?= floatval($item->qty) ?>
                });
            <?php } ?>
        <?php } ?>

        renderTable();
        updateProductDropdown();

        // Function to disable or hide already-added products in #product_select
        function updateProductDropdown() {
            var addedProductIds = [];
            var addedDescriptions = [];
            $.each(itemsList, function(i, item) {
                if (item.product_id && item.product_id > 0) {
                    addedProductIds.push(String(item.product_id));
                }
                if (item.description) {
                    addedDescriptions.push($.trim(item.description).toLowerCase());
                }
            });

            $('#product_select option').each(function() {
                var val = $(this).val();
                if (!val) return; // Keep placeholder enabled
                var optTitle = $.trim($(this).data('title') || $(this).text()).toLowerCase();
                var isAdded = (addedProductIds.indexOf(String(val)) !== -1) || (addedDescriptions.indexOf(optTitle) !== -1);

                if (isAdded) {
                    $(this).prop('disabled', true);
                    $(this).attr('hidden', true);
                } else {
                    $(this).prop('disabled', false);
                    $(this).removeAttr('hidden');
                }
            });

            // If current selected option is disabled, reset select
            var currentVal = $('#product_select').val();
            var currentTitle = $.trim($('#product_select').find('option:selected').data('title') || '').toLowerCase();
            if (currentVal && (addedProductIds.indexOf(String(currentVal)) !== -1 || (currentTitle && addedDescriptions.indexOf(currentTitle) !== -1))) {
                $('#product_select').val('').trigger('change');
            }

            if ($.fn.select2) {
                $('#product_select').trigger('change.select2');
            }
        }

        // When Company Changed (for Admin): dynamically reload parties, products, and quotation number
        $('#company_id').change(function() {
            var compId = $(this).val();
            var partySelect = $('#party_select');
            var prodSelect = $('#product_select');

            partySelect.html('<option value="">Loading parties...</option>');
            prodSelect.html('<option value="">Loading products...</option>');

            // Clear current items on company change to prevent cross-company products
            itemsList = [];
            renderTable();

            if (compId > 0) {
                $.ajax({
                    type: "POST",
                    url: "ajax.php",
                    data: { action: "get_parties_and_quotation_no", company_id: compId },
                    dataType: "json",
                    success: function(res) {
                        // 1. Populate Parties
                        var partyHtml = '<option value="">-- Select Party --</option>';
                        if (res.status === 'success') {
                            if (res.parties && res.parties.length > 0) {
                                $.each(res.parties, function(i, p) {
                                    partyHtml += '<option value="' + p.id + '" ' +
                                            'data-name="' + (p.party_name || '') + '" ' +
                                            'data-gst="' + (p.gst_no || '') + '" ' +
                                            'data-address="' + (p.address || '') + '" ' +
                                            'data-state="' + (p.state_id || 0) + '" ' +
                                            'data-city="' + (p.city_id || 0) + '">' +
                                            p.party_name + '</option>';
                                });
                            }
                            // 2. Populate Products for the chosen company
                            var prodHtml = '<option value="">-- Select Product --</option>';
                            if (res.products && res.products.length > 0) {
                                $.each(res.products, function(i, p) {
                                    var price = parseFloat(p.sales_price) || 0;
                                    prodHtml += '<option value="' + p.id + '" ' +
                                            'data-title="' + escapeHtml(p.product_name) + '" ' +
                                            'data-hsn="' + escapeHtml(p.hsn_code || '') + '" ' +
                                            'data-rate="' + price + '">' +
                                            escapeHtml(p.product_name) + ' (₹' + price.toFixed(2) + ')</option>';
                                });
                            }
                            prodSelect.html(prodHtml);

                            // 3. Update quotation number if in add mode
                            <?php if ($mode === 'add') { ?>
                                if (res.quotation_no) {
                                    $('#quotation_no').val(res.quotation_no);
                                }
                            <?php } ?>
                        } else {
                            prodSelect.html('<option value="">-- Select Product --</option>');
                        }

                        partySelect.html(partyHtml);

                        if ($.fn.select2) {
                            partySelect.trigger('change.select2');
                            prodSelect.trigger('change.select2');
                        }

                        updateProductDropdown();
                    },
                    error: function() {
                        partySelect.html('<option value="">-- Select Party --</option>');
                        prodSelect.html('<option value="">-- Select Product --</option>');
                    }
                });
            } else {
                partySelect.html('<option value="">-- Select Party --</option>');
                prodSelect.html('<option value="">-- Select Product --</option>');
                if ($.fn.select2) {
                    partySelect.trigger('change.select2');
                    prodSelect.trigger('change.select2');
                }
            }

            // Clear party details
            $('#party_name').val('');
            $('#gst_no').val('');
            $('#address').val('');
            $('#input_hsn').val('');
            $('#input_rate').val('');
            $('#input_discount_val').val('0');
        });

        // When Party Selected from Dropdown
        $('#party_select').change(function() {
            var selected = $(this).find('option:selected');
            var partyId = selected.val();
            if (partyId) {
                $('#party_id').val(partyId);
                $('#party_name').val(selected.data('name') || '');
                $('#gst_no').val(selected.data('gst') || '');
                $('#address').val(selected.data('address') || '');
                var stId = selected.data('state');
                if (stId) {
                    $('#state_id').val(stId).trigger('change');
                }
            }
        });

        // When State Changed -> Load Cities
        $('#state_id').change(function() {
            var stateId = $(this).val();
            var citySelect = $('#city_id');
            citySelect.html('<option value="">Loading...</option>');
            if (stateId > 0) {
                $.ajax({
                    type: "POST",
                    url: "ajax.php",
                    data: { action: "get_cities_by_state", state_id: stateId },
                    dataType: "json",
                    success: function(res) {
                        var html = '<option value="">Enter city / Select</option>';
                        if (res.status === 'success' && res.cities.length > 0) {
                            $.each(res.cities, function(i, city) {
                                html += '<option value="' + city.id + '">' + city.city_name + '</option>';
                            });
                        }
                        citySelect.html(html);
                    },
                    error: function() {
                        citySelect.html('<option value="">Enter city / Select</option>');
                    }
                });
            } else {
                citySelect.html('<option value="">Enter city / Select</option>');
            }
            calculateTotals();
        });

        // When Product Selected from Dropdown
        $('#product_select').change(function() {
            var selected = $(this).find('option:selected');
            if (selected.val()) {
                $('#input_hsn').val(selected.data('hsn') || '');
                $('#input_rate').val(selected.data('rate') || 0);
            } else {
                $('#input_hsn').val('');
                $('#input_rate').val('');
            }
        });

        // Add Product Item Button Click
        $('#btnAddItem').click(function(e) {
            e.preventDefault();
            var selected = $('#product_select').find('option:selected');
            var prodId = parseInt(selected.val()) || 0;
            var desc = selected.data('title') || (selected.val() ? selected.text().trim() : '');
            var hsn = $('#input_hsn').val().trim();
            var rate = parseFloat($('#input_rate').val()) || 0;
            var gstPct = parseFloat($('#input_gst_pct').val()) || 0;
            var discType = $('#input_discount_type').val() || 'percentage';
            var discVal = parseFloat($('#input_discount_val').val()) || 0;
            if (discVal < 0) discVal = 0;

            if (!prodId || desc === '') {
                alert('Please select a Product!');
                $('#product_select').focus();
                return false;
            }

            // Check if product is already added in the table
            var normDesc = $.trim(desc).toLowerCase();
            var alreadyExists = itemsList.some(function(item) {
                var matchId = (prodId > 0 && item.product_id > 0 && item.product_id === prodId);
                var matchDesc = (item.description && $.trim(item.description).toLowerCase() === normDesc);
                return matchId || matchDesc;
            });

            if (alreadyExists) {
                alert('This product (' + desc + ') has already been added to the quotation!');
                $('#product_select').val('').trigger('change');
                return false;
            }

            itemsList.push({
                product_id: prodId,
                description: desc,
                hsn_code: hsn,
                rate: rate,
                gst_percent: gstPct,
                discount_type: discType,
                discount_value: discVal,
                qty: 1
            });

            // Reset inputs
            $('#product_select').val('').trigger('change');
            $('#input_hsn').val('');
            $('#input_rate').val('');
            $('#input_discount_val').val('0');

            renderTable();
            updateProductDropdown();
        });

        // Update item quantity
        $(document).on('input change', '.item-qty-input', function() {
            var index = $(this).data('index');
            var val = parseFloat($(this).val()) || 0;
            if (val < 0) val = 0;
            itemsList[index].qty = val;
            renderTable();
        });

        // Update item discount type
        $(document).on('change', '.item-disc-type-select', function() {
            var index = $(this).data('index');
            itemsList[index].discount_type = $(this).val();
            renderTable();
        });

        // Update item discount value
        $(document).on('input change', '.item-disc-val-input', function() {
            var index = $(this).data('index');
            var val = parseFloat($(this).val()) || 0;
            if (val < 0) val = 0;
            itemsList[index].discount_value = val;
            renderTable();
        });

        // Remove item
        $(document).on('click', '.btn-remove-item', function() {
            var index = $(this).data('index');
            itemsList.splice(index, 1);
            renderTable();
            updateProductDropdown();
        });

        // GST Type or State change recalculation
        $('#gst_type, #state_id').change(function() {
            calculateTotals();
        });

        // Form Submit Validation (including GST validation)
        $('#quotationForm').on('submit', function(e) {
            var gstVal = $('#gst_no').val().trim().toUpperCase();
            var gstRegex = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;

            if (gstVal !== '' && !gstRegex.test(gstVal)) {
                e.preventDefault();
                alert('Please enter a valid 15-character GST Number (e.g. 24AAAAA0000A1Z5)!');
                $('#gst_no').focus();
                return false;
            }

            if (itemsList.length === 0) {
                e.preventDefault();
                alert('Please add at least one product item to the quotation!');
                $('#product_select').focus();
                return false;
            }
        });

        // Auto uppercase GST No on typing
        $('#gst_no').on('input', function() {
            $(this).val($(this).val().toUpperCase());
        });

        // Render Table Rows
        function renderTable() {
            var tbody = $('#itemsTableBody');
            tbody.empty();

            if (itemsList.length === 0) {
                tbody.html('<tr><td colspan="9" class="text-muted py-3">No products added yet. Use the inputs above to add items.</td></tr>');
            } else {
                $.each(itemsList, function(i, item) {
                    var baseAmt = (item.rate * item.qty);
                    var discType = item.discount_type || 'percentage';
                    var discVal = parseFloat(item.discount_value) || 0;
                    var discAmt = 0;

                    if (discType === 'percentage') {
                        discAmt = (baseAmt * discVal) / 100;
                    } else {
                        discAmt = Math.min(baseAmt, discVal);
                    }
                    if (discAmt > baseAmt) discAmt = baseAmt;

                    var netAmt = baseAmt - discAmt;

                    var html = '<tr>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td class="text-start">' +
                            '<strong>' + escapeHtml(item.description) + '</strong>' +
                            '<input type="hidden" name="item_product_id[]" value="' + (item.product_id || 0) + '">' +
                            '<input type="hidden" name="item_description[]" value="' + escapeHtml(item.description) + '">' +
                        '</td>' +
                        '<td>' +
                            escapeHtml(item.hsn_code) +
                            '<input type="hidden" name="item_hsn[]" value="' + escapeHtml(item.hsn_code) + '">' +
                        '</td>' +
                        '<td>' +
                            item.rate.toFixed(2) +
                            '<input type="hidden" name="item_rate[]" value="' + item.rate + '">' +
                            '<input type="hidden" name="item_gst_pct[]" value="' + item.gst_percent + '">' +
                        '</td>' +
                        '<td>' +
                            '<input type="number" step="1" min="1" class="form-control form-control-sm text-center item-qty-input" data-index="' + i + '" name="item_qty[]" value="' + item.qty + '">' +
                        '</td>' +
                        '<td>' +
                            '<select name="item_discount_type[]" class="form-select form-select-sm text-center item-disc-type-select" data-index="' + i + '">' +
                                '<option value="percentage"' + (discType === 'percentage' ? ' selected' : '') + '>Percentage (%)</option>' +
                                '<option value="fixed"' + (discType === 'fixed' ? ' selected' : '') + '>Fixed (₹)</option>' +
                            '</select>' +
                        '</td>' +
                        '<td>' +
                            '<input type="number" step="0.01" min="0" name="item_discount_val[]" class="form-control form-control-sm text-center item-disc-val-input" data-index="' + i + '" value="' + discVal + '">' +
                            (discAmt > 0 ? '<div class="text-muted small mt-1" style="font-size:11px;">-₹' + discAmt.toFixed(2) + '</div>' : '') +
                            '<input type="hidden" name="item_discount_amt[]" class="item-discount-amt" value="' + discAmt.toFixed(2) + '">' +
                        '</td>' +
                        '<td class="fw-bold">' +
                            netAmt.toFixed(2) +
                            '<input type="hidden" name="item_net_amt[]" class="item-net-amt" value="' + netAmt.toFixed(2) + '">' +
                            '<input type="hidden" name="item_tax_amt[]" class="item-tax-amt" value="0">' +
                            '<input type="hidden" name="item_total_amt[]" class="item-total-amt" value="0">' +
                        '</td>' +
                        '<td>' +
                            '<button type="button" class="btn btn-sm btn-outline-danger btn-remove-item" data-index="' + i + '"><i class="ti ti-trash"></i></button>' +
                        '</td>' +
                    '</tr>';
                    tbody.append(html);
                });
            }
            calculateTotals();
        }

        // Calculate Summary Totals based on State & GST Type
        function calculateTotals() {
            var totalAmt = 0;
            var totalTax = 0;

            var gstType = $('#gst_type').val(); // 'with_gst' OR 'without_gst'
            var selectedStateText = $('#state_id option:selected').text().trim().toLowerCase();
            var isGujarat = (selectedStateText.indexOf('gujarat') !== -1);

            $.each(itemsList, function(i, item) {
                var baseAmt = (item.rate * item.qty);
                var discType = item.discount_type || 'percentage';
                var discVal = parseFloat(item.discount_value) || 0;
                var discAmt = 0;

                if (discType === 'percentage') {
                    discAmt = (baseAmt * discVal) / 100;
                } else {
                    discAmt = Math.min(baseAmt, discVal);
                }
                if (discAmt > baseAmt) discAmt = baseAmt;

                var net = baseAmt - discAmt;
                totalAmt += net;

                var tax = 0;
                if (gstType === 'with_gst') {
                    tax = net * (item.gst_percent / 100);
                }
                totalTax += tax;

                // Update hidden inputs per row
                $('#itemsTableBody tr').eq(i).find('.item-discount-amt').val(discAmt.toFixed(2));
                $('#itemsTableBody tr').eq(i).find('.item-net-amt').val(net.toFixed(2));
                $('#itemsTableBody tr').eq(i).find('.item-tax-amt').val(tax.toFixed(2));
                $('#itemsTableBody tr').eq(i).find('.item-total-amt').val((net + tax).toFixed(2));
            });

            $('#total_amount').val(totalAmt.toFixed(2));
            $('#tax_amount').val(totalTax.toFixed(2));

            var cgst = 0;
            var sgst = 0;
            var igst = 0;

            if (gstType === 'with_gst') {
                if (isGujarat) {
                    cgst = totalTax / 2;
                    sgst = totalTax / 2;
                    igst = 0;
                    $('#row_cgst, #row_sgst').show();
                    $('#row_igst').hide();
                } else {
                    igst = totalTax;
                    cgst = 0;
                    sgst = 0;
                    $('#row_cgst, #row_sgst').hide();
                    $('#row_igst').show();
                }
            } else {
                // Without GST -> Everything 0
                cgst = 0;
                sgst = 0;
                igst = 0;
                $('#row_cgst, #row_sgst').show();
                $('#row_igst').hide();
            }

            $('#cgst_amount').val(cgst.toFixed(2));
            $('#sgst_amount').val(sgst.toFixed(2));
            $('#igst_amount').val(igst.toFixed(2));

            var grandTotal = totalAmt + (gstType === 'with_gst' ? totalTax : 0);
            $('#grand_total').val(grandTotal.toFixed(2));
        }

        // Synchronize Quotation Date and Due Date (Due date cannot be less than quotation date)
        $('#quotation_date').on('change', function() {
            var qDate = $(this).val();
            if (qDate) {
                $('#due_date').attr('min', qDate);
                var curDueDate = $('#due_date').val();
                if (curDueDate && curDueDate < qDate) {
                    $('#due_date').val(qDate);
                }
            }
        });

        $('#due_date').on('change', function() {
            var qDate = $('#quotation_date').val();
            var dDate = $(this).val();
            if (qDate && dDate && dDate < qDate) {
                alert('Due Date cannot be earlier than Quotation Date (' + qDate + ')!');
                $(this).val(qDate);
            }
        });

        function escapeHtml(text) {
            return text ? String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;") : '';
        }
    });
    </script>
</body>
</html>
