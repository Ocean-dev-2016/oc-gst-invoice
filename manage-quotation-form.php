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
  `gst_type` varchar(20) DEFAULT 'with_gst',
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `cgst_amount` decimal(12,2) DEFAULT 0.00,
  `sgst_amount` decimal(12,2) DEFAULT 0.00,
  `igst_amount` decimal(12,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `grand_total` decimal(12,2) DEFAULT 0.00,
  `status` varchar(20) DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

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

$mode = $_REQUEST['mode'] ?? 'add';
$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

$err_msg = '';
$quotationData = null;
$quotationItems = [];

// Fetch master data for dropdowns
$companies = $ai_db->aiGetQueryObj("SELECT id, company_name FROM tbl_company WHERE status='active' ORDER BY company_name ASC");
$parties = $ai_db->aiGetQueryObj("SELECT id, party_name, gst_no, address, state_id, city_id FROM tbl_party WHERE status='active' ORDER BY party_name ASC");
$products = $ai_db->aiGetQueryObj("SELECT id, product_name, hsn_code, sales_price FROM tbl_product WHERE status='active' ORDER BY product_name ASC");
$states = $ai_db->aiGetQueryObj("SELECT id, state_name, state_code FROM tbl_state WHERE status='active' ORDER BY order_no ASC, state_name ASC");

// Auto Generate Quotation No if mode=add
function generateQuotationNo($ai_db) {
    $currentYear = date('y');
    $nextYear = date('y', strtotime('+1 year'));
    $fy = $currentYear . '-' . $nextYear;
    
    $maxRow = $ai_db->aiGetQueryObj("SELECT quotation_no FROM tbl_quotation ORDER BY id DESC LIMIT 1");
    $nextNum = 1;
    if (!empty($maxRow)) {
        $qNo = $maxRow[0]->quotation_no;
        preg_match('~/(\d+)/~', $qNo, $matches);
        if (!empty($matches[1])) {
            $nextNum = intval($matches[1]) + 1;
        } else {
            $nextNum = count($ai_db->aiGetQueryObj("SELECT id FROM tbl_quotation")) + 1;
        }
    }
    
    return 'OQ/' . str_pad($nextNum, 3, '0', STR_PAD_LEFT) . '/' . $fy;
}

$default_quotation_no = generateQuotationNo($ai_db);

// Handle POST save / update
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_POST['btn_submit'])) {
    $company_id = intval($_POST['company_id'] ?? ($_SESSION['company_id'] ?? 0));
    $party_id = intval($_POST['party_id'] ?? 0);
    $party_name = addslashes(trim($_POST['party_name'] ?? ''));
    $gst_no = addslashes(trim($_POST['gst_no'] ?? ''));
    $quotation_no = addslashes(trim($_POST['quotation_no'] ?? ''));
    $rca = addslashes(trim($_POST['rca'] ?? 'No'));
    $address = addslashes(trim($_POST['address'] ?? ''));
    $state_id = intval($_POST['state_id'] ?? 0);
    $city_id = intval($_POST['city_id'] ?? 0);
    $city_name = addslashes(trim($_POST['city_name'] ?? ''));
    $quotation_date = !empty($_POST['quotation_date']) ? date('Y-m-d', strtotime($_POST['quotation_date'])) : date('Y-m-d');
    $gst_type = addslashes(trim($_POST['gst_type'] ?? 'with_gst')); // with_gst OR without_gst
    
    $total_amount = floatval($_POST['total_amount'] ?? 0);
    $cgst_amount = floatval($_POST['cgst_amount'] ?? 0);
    $sgst_amount = floatval($_POST['sgst_amount'] ?? 0);
    $igst_amount = floatval($_POST['igst_amount'] ?? 0);
    $tax_amount = floatval($_POST['tax_amount'] ?? 0);
    $grand_total = floatval($_POST['grand_total'] ?? 0);
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
                gst_type='$gst_type',
                total_amount='$total_amount',
                cgst_amount='$cgst_amount',
                sgst_amount='$sgst_amount',
                igst_amount='$igst_amount',
                tax_amount='$tax_amount',
                grand_total='$grand_total',
                status='$status'";

            $ai_db->aiQuery($insert_qry);
            $quotation_id = $ai_db->aiLastInsert();

            // Insert Items
            if (!empty($_POST['item_description']) && is_array($_POST['item_description'])) {
                foreach ($_POST['item_description'] as $idx => $desc) {
                    $desc = addslashes(trim($desc));
                    if (empty($desc)) continue;
                    $hsn = addslashes(trim($_POST['item_hsn'][$idx] ?? ''));
                    $rate = floatval($_POST['item_rate'][$idx] ?? 0);
                    $gst_pct = floatval($_POST['item_gst_pct'][$idx] ?? 0);
                    $qty = floatval($_POST['item_qty'][$idx] ?? 1);
                    $net_amt = floatval($_POST['item_net_amt'][$idx] ?? 0);
                    $item_tax = floatval($_POST['item_tax_amt'][$idx] ?? 0);
                    $item_total = floatval($_POST['item_total_amt'][$idx] ?? 0);

                    $item_qry = "INSERT INTO tbl_quotation_items SET
                        quotation_id='$quotation_id',
                        description='$desc',
                        hsn_code='$hsn',
                        rate='$rate',
                        gst_percent='$gst_pct',
                        qty='$qty',
                        net_amount='$net_amt',
                        tax_amount='$item_tax',
                        total_amount='$item_total'";
                    $ai_db->aiQuery($item_qry);
                }
            }

            $ai_core->aiGoPage($redirection_url . '?msg=1');
            exit;
        } elseif ($mode === 'edit' && $id > 0) {
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
                gst_type='$gst_type',
                total_amount='$total_amount',
                cgst_amount='$cgst_amount',
                sgst_amount='$sgst_amount',
                igst_amount='$igst_amount',
                tax_amount='$tax_amount',
                grand_total='$grand_total',
                status='$status'
                WHERE id='$id'";

            $ai_db->aiQuery($update_qry);

            // Delete old items and re-insert
            $ai_db->aiQuery("DELETE FROM tbl_quotation_items WHERE quotation_id='$id'");

            if (!empty($_POST['item_description']) && is_array($_POST['item_description'])) {
                foreach ($_POST['item_description'] as $idx => $desc) {
                    $desc = addslashes(trim($desc));
                    if (empty($desc)) continue;
                    $hsn = addslashes(trim($_POST['item_hsn'][$idx] ?? ''));
                    $rate = floatval($_POST['item_rate'][$idx] ?? 0);
                    $gst_pct = floatval($_POST['item_gst_pct'][$idx] ?? 0);
                    $qty = floatval($_POST['item_qty'][$idx] ?? 1);
                    $net_amt = floatval($_POST['item_net_amt'][$idx] ?? 0);
                    $item_tax = floatval($_POST['item_tax_amt'][$idx] ?? 0);
                    $item_total = floatval($_POST['item_total_amt'][$idx] ?? 0);

                    $item_qry = "INSERT INTO tbl_quotation_items SET
                        quotation_id='$id',
                        description='$desc',
                        hsn_code='$hsn',
                        rate='$rate',
                        gst_percent='$gst_pct',
                        qty='$qty',
                        net_amount='$net_amt',
                        tax_amount='$item_tax',
                        total_amount='$item_total'";
                    $ai_db->aiQuery($item_qry);
                }
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
    }
}

$selected_company_id = $_POST['company_id'] ?? $quotationData->company_id ?? ($_SESSION['company_id'] ?? 0);
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
                            <a href="<?= $redirection_url ?>" class="btn btn-secondary">
                                <i class="ti ti-arrow-left me-1"></i> Back to List
                            </a>
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
                                        <input type="text" name="gst_no" id="gst_no" class="form-control" placeholder="Enter GST no." value="<?= htmlspecialchars($quotationData->gst_no ?? '') ?>">
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label fw-bold text-uppercase small text-muted">QUOTATION NO. <span class="text-danger">*</span></label>
                                        <input type="text" name="quotation_no" id="quotation_no" class="form-control" placeholder="OQ/008/26-27" value="<?= htmlspecialchars($quotationData->quotation_no ?? $default_quotation_no) ?>" required>
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
                                    <div class="col-md-4">
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

                                    <div class="col-md-2">
                                        <label class="form-label fw-bold text-uppercase small text-muted">RATE</label>
                                        <input type="number" step="0.01" id="input_rate" class="form-control" placeholder="Rate">
                                    </div>

                                    <div class="col-md-3">
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
                                                <th style="width: 60px;">SR NO.</th>
                                                <th class="text-start">DESCRIPTION</th>
                                                <th style="width: 140px;">HSN CODE</th>
                                                <th style="width: 120px;">RATE</th>
                                                <th style="width: 100px;">QTY</th>
                                                <th style="width: 140px;">NET AMOUNT</th>
                                                <th style="width: 60px;">ACTION</th>
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

                                            <!-- ACTION BUTTONS -->
                                            <div class="pt-4">
                                            <button type="submit" name="btn_submit" class="btn btn-primary me-sm-3 me-1 waves-effect waves-light" id="btnSubmit">
                                                <?= ($mode == 'edit') ? 'Update' : 'Submit' ?>
                                            </button>
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
                    description: <?= json_encode($item->description) ?>,
                    hsn_code: <?= json_encode($item->hsn_code) ?>,
                    rate: <?= floatval($item->rate) ?>,
                    gst_percent: <?= floatval($item->gst_percent) ?>,
                    qty: <?= floatval($item->qty) ?>
                });
            <?php } ?>
        <?php } ?>

        renderTable();

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
            var desc = selected.data('title') || (selected.val() ? selected.text().trim() : '');
            var hsn = $('#input_hsn').val().trim();
            var rate = parseFloat($('#input_rate').val()) || 0;
            var gstPct = parseFloat($('#input_gst_pct').val()) || 0;

            if (!selected.val() || desc === '') {
                alert('Please select a Product!');
                $('#product_select').focus();
                return false;
            }

            itemsList.push({
                description: desc,
                hsn_code: hsn,
                rate: rate,
                gst_percent: gstPct,
                qty: 1
            });

            // Reset inputs
            $('#product_select').val('').trigger('change');
            $('#input_hsn').val('');
            $('#input_rate').val('');

            renderTable();
        });

        // Update item quantity
        $(document).on('input change', '.item-qty-input', function() {
            var index = $(this).data('index');
            var val = parseFloat($(this).val()) || 0;
            if (val < 0) val = 0;
            itemsList[index].qty = val;
            renderTable();
        });

        // Remove item
        $(document).on('click', '.btn-remove-item', function() {
            var index = $(this).data('index');
            itemsList.splice(index, 1);
            renderTable();
        });

        // GST Type or State change recalculation
        $('#gst_type, #state_id').change(function() {
            calculateTotals();
        });

        // Render Table Rows
        function renderTable() {
            var tbody = $('#itemsTableBody');
            tbody.empty();

            if (itemsList.length === 0) {
                tbody.html('<tr><td colspan="7" class="text-muted py-3">No products added yet. Use the inputs above to add items.</td></tr>');
            } else {
                $.each(itemsList, function(i, item) {
                    var netAmt = (item.rate * item.qty);
                    var html = '<tr>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td class="text-start">' +
                            '<strong>' + escapeHtml(item.description) + '</strong>' +
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
                        '<td class="fw-bold">' +
                            netAmt.toFixed(2) +
                            '<input type="hidden" name="item_net_amt[]" class="item-net-amt" value="' + netAmt + '">' +
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
                var net = (item.rate * item.qty);
                totalAmt += net;

                var tax = 0;
                if (gstType === 'with_gst') {
                    tax = net * (item.gst_percent / 100);
                }
                totalTax += tax;

                // Update hidden inputs per row
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

        function escapeHtml(text) {
            return text ? text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;") : '';
        }
    });
    </script>
</body>
</html>
