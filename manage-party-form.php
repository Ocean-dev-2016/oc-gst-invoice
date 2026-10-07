<?php
include('includes/header.php');

$page_nm = 'Party Details';
$table = 'tbl_party';
$redirection_url = 'manage-party-list.php';

error_reporting(E_ALL);

$mode = $_REQUEST['mode'] ?? 'add';
$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

$err_msg = '';
$categoryData = null;

// Fetch all active companies for dropdown
$companies = $ai_db->aiGetQueryObj("SELECT id, company_name FROM tbl_company WHERE status='active' ORDER BY company_name ASC");

// Fetch all active states for dropdown
$states = $ai_db->aiGetQueryObj("SELECT id, state_name, state_code FROM tbl_state WHERE status='active' ORDER BY order_no ASC, state_name ASC");

// ===================== ADD =====================
if ($mode === 'add' && ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_POST['btn_submit']))) {
    $company_id = intval($_POST['company_id'] ?? ($_SESSION['company_id'] ?? 0));
    // If company user, always enforce their own company_id
    if (($_SESSION['user_type'] ?? '') === 'company' && !empty($_SESSION['company_id'])) {
        $company_id = intval($_SESSION['company_id']);
    }
    $party_name = addslashes(trim($_POST['party_name'] ?? ''));
    $address = addslashes(trim($_POST['address'] ?? ''));
    $state_id = intval($_POST['state_id'] ?? 0);
    $city_id = intval($_POST['city_id'] ?? 0);
    $pincode = addslashes(trim($_POST['pincode'] ?? ''));
    $shipping_pincode = addslashes(trim($_POST['shipping_pincode'] ?? ''));
    $shipping_address = addslashes(trim($_POST['shipping_address'] ?? ''));
    $shipping_state_id = intval($_POST['shipping_state_id'] ?? 0);
    $shipping_city_id = intval($_POST['shipping_city_id'] ?? 0);
    $mobile_no = addslashes(trim($_POST['mobile_no'] ?? ''));
    $email = addslashes(trim($_POST['email'] ?? ''));
    $gst_no = addslashes(trim($_POST['gst_no'] ?? ''));
    $pan_no = addslashes(strtoupper(trim($_POST['pan_no'] ?? '')));
    $party_status = addslashes(trim($_POST['party_status'] ?? 'Sales'));
    $business_type = in_array($_POST['business_type'] ?? '', ['Individual', 'Business']) ? $_POST['business_type'] : 'Business';
    $opening_balance = floatval($_POST['opening_balance'] ?? 0);
    $balance_type = in_array($_POST['balance_type'] ?? '', ['Credit', 'Debit']) ? $_POST['balance_type'] : 'Debit';
    $credit_limit = floatval($_POST['credit_limit'] ?? 0);
    // Initial outstanding based on Opening Balance (Debit = customer owes money, Credit = advance/0.00)
    $outstanding = ($balance_type === 'Debit') ? $opening_balance : 0.00;
    $remark = addslashes(trim($_POST['remark'] ?? ''));
    $status = $_POST['status'] ?? 'active';

    if (empty($party_name)) {
        $err_msg = "Please enter Party Name!";
    } elseif ($company_id <= 0) {
        $err_msg = "Please select Company!";
    } elseif (!empty($mobile_no) && !preg_match('/^[0-9]{10}$/', $mobile_no)) {
        $err_msg = "Mobile Number must be exactly 10 digits!";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err_msg = "Please enter a valid Email Address!";
    } elseif (!empty($pan_no) && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan_no)) {
        $err_msg = "PAN Number must be valid 10-character alphanumeric (e.g. ABCDE1234F)!";
    } else {
        // Duplicate check under same company
        $check_dup = $ai_db->aiGetQueryObj("SELECT id FROM $table WHERE company_id='" . $company_id . "' AND LOWER(party_name)='" . strtolower($party_name) . "' LIMIT 1");
        if (!empty($check_dup)) {
            $err_msg = "Party Name '$party_name' already exists for the selected Company!";
        } else {
            $add_qry = "INSERT INTO $table SET 
                company_id='" . $company_id . "',
                party_name='" . $party_name . "',
                address='" . $address . "',
                state_id='" . $state_id . "',
                city_id='" . $city_id . "',
                pincode='" . $pincode . "',
                shipping_address='" . $shipping_address . "',
                shipping_state_id='" . $shipping_state_id . "',
                shipping_city_id='" . $shipping_city_id . "',
                shipping_pincode='" . $shipping_pincode . "',
                mobile_no='" . $mobile_no . "',
                email='" . $email . "',
                gst_no='" . $gst_no . "',
                pan_no='" . $pan_no . "',
                party_status='" . $party_status . "',
                business_type='" . $business_type . "',
                opening_balance='" . $opening_balance . "',
                balance_type='" . $balance_type . "',
                credit_limit='" . $credit_limit . "',
                outstanding='" . $outstanding . "',
                remark='" . $remark . "',
                status='" . $status . "'";

            $ai_db->aiQuery($add_qry);
            $newPartyId = $ai_db->aiLastInsert();

            if ($newPartyId > 0 && function_exists('recalculatePartyOutstanding')) {
                recalculatePartyOutstanding($newPartyId);
            }

            $ai_core->aiGoPage($redirection_url . '?msg=1');
            exit;
        }
    }
}

// ===================== EDIT =====================
if ($mode === 'edit' && ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_POST['btn_submit']))) {
    $company_id = intval($_POST['company_id'] ?? ($_SESSION['company_id'] ?? 0));
    // If company user, always enforce their own company_id
    if (($_SESSION['user_type'] ?? '') === 'company' && !empty($_SESSION['company_id'])) {
        $company_id = intval($_SESSION['company_id']);
    }
    $party_name = addslashes(trim($_POST['party_name'] ?? ''));
    $address = addslashes(trim($_POST['address'] ?? ''));
    $state_id = intval($_POST['state_id'] ?? 0);
    $city_id = intval($_POST['city_id'] ?? 0);
    $pincode = addslashes(trim($_POST['pincode'] ?? ''));
    $shipping_pincode = addslashes(trim($_POST['shipping_pincode'] ?? ''));
    $shipping_address = addslashes(trim($_POST['shipping_address'] ?? ''));
    $shipping_state_id = intval($_POST['shipping_state_id'] ?? 0);
    $shipping_city_id = intval($_POST['shipping_city_id'] ?? 0);
    $mobile_no = addslashes(trim($_POST['mobile_no'] ?? ''));
    $email = addslashes(trim($_POST['email'] ?? ''));
    $gst_no = addslashes(trim($_POST['gst_no'] ?? ''));
    $pan_no = addslashes(strtoupper(trim($_POST['pan_no'] ?? '')));
    $party_status = addslashes(trim($_POST['party_status'] ?? 'Sales'));
    $business_type = in_array($_POST['business_type'] ?? '', ['Individual', 'Business']) ? $_POST['business_type'] : 'Business';
    $opening_balance = floatval($_POST['opening_balance'] ?? 0);
    $balance_type = in_array($_POST['balance_type'] ?? '', ['Credit', 'Debit']) ? $_POST['balance_type'] : 'Debit';
    $credit_limit = floatval($_POST['credit_limit'] ?? 0);
    $remark = addslashes(trim($_POST['remark'] ?? ''));
    $status = $_POST['status'] ?? 'active';

    if (empty($party_name)) {
        $err_msg = "Please enter Party Name!";
    } elseif ($company_id <= 0) {
        $err_msg = "Please select Company!";
    } elseif (!empty($mobile_no) && !preg_match('/^[0-9]{10}$/', $mobile_no)) {
        $err_msg = "Mobile Number must be exactly 10 digits!";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err_msg = "Please enter a valid Email Address!";
    } elseif (!empty($pan_no) && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan_no)) {
        $err_msg = "PAN Number must be valid 10-character alphanumeric (e.g. ABCDE1234F)!";
    } else {
        // Duplicate check excluding current ID
        $check_dup = $ai_db->aiGetQueryObj("SELECT id FROM $table WHERE company_id='" . $company_id . "' AND LOWER(party_name)='" . strtolower($party_name) . "' AND id!='" . intval($id) . "' LIMIT 1");
        if (!empty($check_dup)) {
            $err_msg = "Party Name '$party_name' already exists for the selected Company!";
        } else {
            $edit_qry = "UPDATE $table SET 
                company_id='" . $company_id . "',
                party_name='" . $party_name . "',
                address='" . $address . "',
                state_id='" . $state_id . "',
                city_id='" . $city_id . "',
                pincode='" . $pincode . "',
                shipping_address='" . $shipping_address . "',
                shipping_state_id='" . $shipping_state_id . "',
                shipping_city_id='" . $shipping_city_id . "',
                shipping_pincode='" . $shipping_pincode . "',
                mobile_no='" . $mobile_no . "',
                email='" . $email . "',
                gst_no='" . $gst_no . "',
                pan_no='" . $pan_no . "',
                party_status='" . $party_status . "',
                business_type='" . $business_type . "',
                opening_balance='" . $opening_balance . "',
                balance_type='" . $balance_type . "',
                credit_limit='" . $credit_limit . "',
                remark='" . $remark . "',
                status='" . $status . "'
                WHERE id='" . intval($id) . "'";

            $ai_db->aiQuery($edit_qry);

            if (function_exists('recalculatePartyOutstanding')) {
                recalculatePartyOutstanding(intval($id));
            }

            $ai_core->aiGoPage($redirection_url . '?msg=2');
            exit;
        }
    }
}

// ===================== DELETE =====================
if ($mode === 'delete' && $id) {
    $ai_db->aiQuery("DELETE FROM $table WHERE id='" . intval($id) . "'");
    $ai_core->aiGoPage($redirection_url . '?msg=3');
    exit;
}

// ===================== FETCH =====================
if ($mode === 'edit' && $id && !isset($_POST['btn_submit'])) {
    $query = "SELECT * FROM $table WHERE id='" . intval($id) . "' LIMIT 1";
    $result = $ai_db->aiGetQueryObj($query);
    $categoryData = isset($result[0]) ? $result[0] : null;
}

// Dynamic state & city default values
$selected_company_id = $_POST['company_id'] ?? $categoryData->company_id ?? ($_SESSION['company_id'] ?? 0);
$selected_state_id = $_POST['state_id'] ?? $categoryData->state_id ?? 0;
$cities = [];
if ($selected_state_id > 0) {
    $cities = $ai_db->aiGetQueryObj("SELECT id, city_name FROM tbl_city WHERE state_id='" . intval($selected_state_id) . "' AND status='active' ORDER BY order_no ASC, city_name ASC");
}

$selected_shipping_state_id = $_POST['shipping_state_id'] ?? $categoryData->shipping_state_id ?? 0;
$shipping_cities = [];
if ($selected_shipping_state_id > 0) {
    $shipping_cities = $ai_db->aiGetQueryObj("SELECT id, city_name FROM tbl_city WHERE state_id='" . intval($selected_shipping_state_id) . "' AND status='active' ORDER BY order_no ASC, city_name ASC");
}
?>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <?php include('sidebar.php'); ?>

            <div class="layout-page">
                <?php include('navbar.php'); ?>

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <h4 class="py-3 mb-4">
                            <span class="text-muted fw-light">Dashboard /</span> <?= $page_nm ?>
                        </h4>

                        <div class="row">
                            <div class="col-12">
                                <div class="card mb-4" id="categoryForm">
                                    <h5 class="card-header" id="formTitle">
                                        <?= ($mode == 'edit') ? 'Edit ' . $page_nm : 'Add ' . $page_nm ?>
                                    </h5>

                                    <form class="card-body" id="mainForm" method="post"
                                        action="manage-party-form.php?mode=<?= $mode ?>&id=<?= $id ?>"
                                        enctype="multipart/form-data">

                                        <input type="hidden" name="id" id="cat_id" value="<?= $categoryData->id ?? $id ?>">

                                        <div class="row g-3">
                                             <!-- Select Company -->
                                            <div class="col-md-3">
                                                <label class="form-label">Company Name <span class="text-danger">*</span></label>
                                                <?php if (($_SESSION['user_type'] ?? '') === 'company' && !empty($selected_company_id)) { ?>
                                                    <!-- For Company user: locked to their own company -->
                                                    <input type="hidden" name="company_id" id="company_id" value="<?= (int)$selected_company_id ?>">
                                                    <select class="form-select select2" disabled>
                                                        <?php if (!empty($companies)) {
                                                            foreach ($companies as $comp) {
                                                                if ($selected_company_id == $comp->id) { ?>
                                                                    <option value="<?= $comp->id ?>" selected>
                                                                        <?= htmlspecialchars($comp->company_name) ?>
                                                                    </option>
                                                                <?php }
                                                            }
                                                        } ?>
                                                    </select>
                                                <?php } else { ?>
                                                    <!-- For Admin / other users: full dropdown -->
                                                    <select name="company_id" id="company_id" class="form-select select2" required>
                                                        <option value="">-- Select Company --</option>
                                                        <?php if (!empty($companies)) {
                                                            foreach ($companies as $comp) { ?>
                                                                <option value="<?= $comp->id ?>" <?= ($selected_company_id == $comp->id) ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($comp->company_name) ?>
                                                                </option>
                                                            <?php }
                                                        } ?>
                                                    </select>
                                                <?php } ?>
                                            </div>

                                            <!-- Party Name -->
                                            <div class="col-md-3">
                                                <label class="form-label">Party Name <span class="text-danger">*</span></label>
                                                <input type="text" name="party_name" class="form-control" placeholder="Enter party name" value="<?= htmlspecialchars($_POST['party_name'] ?? $categoryData->party_name ?? '') ?>" required>
                                            </div>

                                            <!-- Mobile No -->
                                            <div class="col-md-3">
                                                <label class="form-label">Mobile No.</label>
                                                <input type="text" name="mobile_no" class="form-control" placeholder="Enter mobile number" value="<?= htmlspecialchars($_POST['mobile_no'] ?? $categoryData->mobile_no ?? '') ?>" maxlength="10">
                                            </div>

                                            <!-- Email -->
                                            <div class="col-md-3">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" name="email" class="form-control" placeholder="Enter email address" value="<?= htmlspecialchars($_POST['email'] ?? $categoryData->email ?? '') ?>">
                                            </div>

                                            <!-- GST IN No -->
                                            <div class="col-md-3">
                                                <label class="form-label">GST IN No.</label>
                                                <input type="text" name="gst_no" class="form-control" placeholder="Enter GST in number" value="<?= htmlspecialchars($_POST['gst_no'] ?? $categoryData->gst_no ?? '') ?>">
                                            </div>

                                            <!-- PAN No -->
                                            <div class="col-md-3">
                                                <label class="form-label">PAN No.</label>
                                                <input type="text" name="pan_no" class="form-control text-uppercase" placeholder="Enter PAN number (e.g. ABCDE1234F)" value="<?= htmlspecialchars($_POST['pan_no'] ?? $categoryData->pan_no ?? '') ?>" maxlength="10" style="text-transform: uppercase;">
                                            </div>

                                             <!-- Party Status -->
                                            <div class="col-md-3">
                                                <label class="form-label">Party Status</label>
                                                <?php $sel_pstatus = $_POST['party_status'] ?? $categoryData->party_status ?? 'Sales'; ?>
                                                <select name="party_status" class="form-select select2">
                                                    <option value="">Select Party Status</option>
                                                    <option value="Sales" <?= ($sel_pstatus == 'Sales') ? 'selected' : '' ?>>Sales</option>
                                                    <option value="Purchase" <?= ($sel_pstatus == 'Purchase') ? 'selected' : '' ?>>Purchase</option>
                                                </select>
                                            </div>

                                            <!-- Business Type (Individual / Business) -->
                                            <div class="col-md-3">
                                                <label class="form-label">Business Type</label>
                                                <?php $sel_btype = $_POST['business_type'] ?? $categoryData->business_type ?? 'Business'; ?>
                                                <select name="business_type" class="form-select select2">
                                                    <option value="Business" <?= ($sel_btype === 'Business') ? 'selected' : '' ?>>Business</option>
                                                    <option value="Individual" <?= ($sel_btype === 'Individual') ? 'selected' : '' ?>>Individual</option>
                                                </select>
                                            </div>

                                            <!-- Opening Balance & Credit/Debit -->
                                            <div class="col-md-3">
                                                <label class="form-label">Opening Balance</label>
                                                <div class="input-group">
                                                    <input type="number" step="0.01" min="0" name="opening_balance" class="form-control" placeholder="0.00" value="<?= htmlspecialchars($_POST['opening_balance'] ?? $categoryData->opening_balance ?? '0.00') ?>">
                                                    <?php $sel_btype_cr_dr = $_POST['balance_type'] ?? $categoryData->balance_type ?? 'Debit'; ?>
                                                    <select name="balance_type" class="form-select" style="max-width: 110px;">
                                                        <option value="Debit" <?= ($sel_btype_cr_dr === 'Debit') ? 'selected' : '' ?>>Debit</option>
                                                        <option value="Credit" <?= ($sel_btype_cr_dr === 'Credit') ? 'selected' : '' ?>>Credit</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Credit Limit -->
                                            <div class="col-md-3">
                                                <label class="form-label">Credit Limit</label>
                                                <input type="number" step="0.01" min="0" name="credit_limit" class="form-control" placeholder="0.00" value="<?= htmlspecialchars($_POST['credit_limit'] ?? $categoryData->credit_limit ?? '0.00') ?>">
                                            </div>

                                            <!-- Status -->
                                            <div class="col-md-3">
                                                <label class="form-label">Status</label>
                                                <select name="status" class="form-select select2">
                                                    <option value="active" <?= ((($_POST['status'] ?? $categoryData->status ?? 'active') == 'active')) ? 'selected' : '' ?>>Active</option>
                                                    <option value="deactive" <?= ((($_POST['status'] ?? $categoryData->status ?? '') == 'deactive')) ? 'selected' : '' ?>>Deactive</option>
                                                </select>
                                            </div>

                                            <!-- Remark -->
                                            <div class="col-md-12">
                                                <label class="form-label">Remark</label>
                                                <textarea name="remark" class="form-control" rows="2" placeholder="Enter remark/notes for this party"><?= htmlspecialchars($_POST['remark'] ?? $categoryData->remark ?? '') ?></textarea>
                                            </div>

                                            <!-- Address (Billing) -->
                                            <div class="col-md-3">
                                                <label class="form-label">Billing Address</label>
                                                <textarea name="address" id="address" class="form-control" rows="2" placeholder="Enter Address"><?= htmlspecialchars($_POST['address'] ?? $categoryData->address ?? '') ?></textarea>
                                            </div>

                                            <!-- State (Billing) -->
                                            <div class="col-md-3">
                                                <label class="form-label">Billing State</label>
                                                <select name="state_id" id="state_id" class="form-select select2">
                                                    <option value="">Select State</option>
                                                    <?php if (!empty($states)) {
                                                        foreach ($states as $st) { ?>
                                                            <option value="<?= $st->id ?>" <?= ($selected_state_id == $st->id) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($st->state_name) ?> (Code: <?= htmlspecialchars($st->state_code) ?>)
                                                            </option>
                                                        <?php }
                                                    } ?>
                                                </select>
                                            </div>

                                            <!-- City (Billing Select2 Dynamic) -->
                                            <div class="col-md-3">
                                                <label class="form-label">Billing City</label>
                                                <select name="city_id" id="city_id" class="form-select select2">
                                                    <option value="">Enter City</option>
                                                    <?php if (!empty($cities)) {
                                                        $selected_city_id = $_POST['city_id'] ?? $categoryData->city_id ?? 0;
                                                        foreach ($cities as $ct) { ?>
                                                            <option value="<?= $ct->id ?>" <?= ($selected_city_id == $ct->id) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($ct->city_name) ?>
                                                            </option>
                                                        <?php }
                                                    } ?>
                                                </select>
                                            </div>

                                            <!-- Pincode (Billing) -->
                                            <div class="col-md-3">
                                                <label class="form-label">Billing Pincode</label>
                                                <input type="text" name="pincode" id="pincode" class="form-control" placeholder="Enter pincode" value="<?= htmlspecialchars($_POST['pincode'] ?? $categoryData->pincode ?? '') ?>">
                                            </div>

                                            <!-- Shipping Details Header with Checkbox -->
                                            <div class="col-12 mt-3">
                                                <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded">
                                                    <span class="fw-bold"><i class="ti ti-truck me-1"></i> Shipping Address Details</span>
                                                    <div class="form-check m-0">
                                                        <input class="form-check-input" type="checkbox" id="same_as_billing" name="same_as_billing">
                                                        <label class="form-check-label fw-semibold text-primary" for="same_as_billing" style="cursor: pointer;">
                                                            Same as Billing Address
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Shipping Address -->
                                            <div class="col-md-3">
                                                <label class="form-label">Shipping Address</label>
                                                <textarea name="shipping_address" id="shipping_address" class="form-control" rows="2" placeholder="Enter Shipping Address"><?= htmlspecialchars($_POST['shipping_address'] ?? $categoryData->shipping_address ?? '') ?></textarea>
                                            </div>

                                            <!-- Shipping State -->
                                            <div class="col-md-3">
                                                <label class="form-label">Shipping State</label>
                                                <select name="shipping_state_id" id="shipping_state_id" class="form-select select2">
                                                    <option value="">Select Shipping State</option>
                                                    <?php if (!empty($states)) {
                                                        foreach ($states as $st) { ?>
                                                            <option value="<?= $st->id ?>" <?= ($selected_shipping_state_id == $st->id) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($st->state_name) ?> (Code: <?= htmlspecialchars($st->state_code) ?>)
                                                            </option>
                                                        <?php }
                                                    } ?>
                                                </select>
                                            </div>

                                            <!-- Shipping City (Dynamic) -->
                                            <div class="col-md-3">
                                                <label class="form-label">Shipping City</label>
                                                <select name="shipping_city_id" id="shipping_city_id" class="form-select select2">
                                                    <option value="">Enter Shipping City</option>
                                                    <?php if (!empty($shipping_cities)) {
                                                        $selected_shipping_city_id = $_POST['shipping_city_id'] ?? $categoryData->shipping_city_id ?? 0;
                                                        foreach ($shipping_cities as $ct) { ?>
                                                            <option value="<?= $ct->id ?>" <?= ($selected_shipping_city_id == $ct->id) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($ct->city_name) ?>
                                                            </option>
                                                        <?php }
                                                    } ?>
                                                </select>
                                            </div>

                                            <!-- Shipping Pincode -->
                                            <div class="col-md-3">
                                                <label class="form-label">Shipping Pincode</label>
                                                <input type="text" name="shipping_pincode" id="shipping_pincode" class="form-control" placeholder="Enter shipping pincode" value="<?= htmlspecialchars($_POST['shipping_pincode'] ?? $categoryData->shipping_pincode ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="pt-4">
                                            <button type="submit" name="btn_submit" class="btn btn-primary me-sm-3 me-1 waves-effect waves-light" id="btnSubmit">
                                                <?= ($mode == 'edit') ? 'Update' : 'Submit' ?>
                                            </button>
                                            <a href="<?= $redirection_url ?>" class="btn btn-label-secondary waves-effect">Cancel</a>
                                        </div>

                                    </form>
                                </div>
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

    <?php if (!empty($err_msg)) { ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error!',
                        text: '<?= addslashes($err_msg) ?>',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true
                    });
                } else {
                    alert('<?= addslashes($err_msg) ?>');
                }
            });
        </script>
    <?php } ?>

    <script>
        $(document).ready(function() {
            // Dynamic City Loading on State Change
            $('#state_id').on('change', function() {
                var stateId = $(this).val();
                var $cityDropdown = $('#city_id');
                $cityDropdown.html('<option value="">Loading cities...</option>');

                if (stateId > 0) {
                    $.ajax({
                        type: "POST",
                        url: "ajax.php",
                        data: {
                            action: "get_cities_by_state",
                            state_id: stateId
                        },
                        dataType: "json",
                        success: function(response) {
                            $cityDropdown.html('<option value="">Enter City</option>');
                            if (response.status === "success" && response.cities.length > 0) {
                                $.each(response.cities, function(i, city) {
                                    $cityDropdown.append('<option value="' + city.id + '">' + city.city_name + '</option>');
                                });
                            }
                            if ($.fn.select2) {
                                $cityDropdown.trigger('change');
                            }
                        }
                    });
                } else {
                    $cityDropdown.html('<option value="">Enter City</option>');
                    if ($.fn.select2) {
                        $cityDropdown.trigger('change');
                    }
                }
            });

            // Dynamic Shipping City Loading on Shipping State Change
            $('#shipping_state_id').on('change', function() {
                var stateId = $(this).val();
                var $shipCityDropdown = $('#shipping_city_id');
                $shipCityDropdown.html('<option value="">Loading cities...</option>');

                if (stateId > 0) {
                    $.ajax({
                        type: "POST",
                        url: "ajax.php",
                        data: {
                            action: "get_cities_by_state",
                            state_id: stateId
                        },
                        dataType: "json",
                        success: function(response) {
                            $shipCityDropdown.html('<option value="">Enter Shipping City</option>');
                            if (response.status === "success" && response.cities.length > 0) {
                                $.each(response.cities, function(i, city) {
                                    $shipCityDropdown.append('<option value="' + city.id + '">' + city.city_name + '</option>');
                                });
                            }
                            if ($.fn.select2) {
                                $shipCityDropdown.trigger('change');
                            }
                        }
                    });
                } else {
                    $shipCityDropdown.html('<option value="">Enter Shipping City</option>');
                    if ($.fn.select2) {
                        $shipCityDropdown.trigger('change');
                    }
                }
            });

            // Same as Billing Address Checkbox Logic
            $('#same_as_billing').on('change', function() {
                if ($(this).is(':checked')) {
                    copyBillingToShipping();
                }
            });

            function copyBillingToShipping() {
                var billAddr  = $('#address').val();
                var billPin   = $('#pincode').val();
                var billState = $('#state_id').val();
                var billCity  = $('#city_id').val();

                $('#shipping_address').val(billAddr);
                $('#shipping_pincode').val(billPin);

                if (billState) {
                    $('#shipping_state_id').val(billState);
                    if ($.fn.select2) {
                        $('#shipping_state_id').trigger('change.select2');
                    }

                    // Populate shipping city options based on billing state
                    var $shipCityDropdown = $('#shipping_city_id');
                    $shipCityDropdown.html('<option value="">Loading cities...</option>');

                    $.ajax({
                        type: "POST",
                        url: "ajax.php",
                        data: {
                            action: "get_cities_by_state",
                            state_id: billState
                        },
                        dataType: "json",
                        success: function(response) {
                            $shipCityDropdown.html('<option value="">Enter Shipping City</option>');
                            if (response.status === "success" && response.cities.length > 0) {
                                $.each(response.cities, function(i, city) {
                                    var sel = (city.id == billCity) ? 'selected' : '';
                                    $shipCityDropdown.append('<option value="' + city.id + '" ' + sel + '>' + city.city_name + '</option>');
                                });
                            }
                            if (billCity) {
                                $shipCityDropdown.val(billCity);
                            }
                            if ($.fn.select2) {
                                $shipCityDropdown.trigger('change');
                            }
                        }
                    });
                } else {
                    $('#shipping_state_id').val('');
                    if ($.fn.select2) {
                        $('#shipping_state_id').trigger('change.select2');
                    }
                    $('#shipping_city_id').html('<option value="">Enter Shipping City</option>');
                    if ($.fn.select2) {
                        $('#shipping_city_id').trigger('change');
                    }
                }
            }

            // If same_as_billing is checked and user types in billing, auto update shipping
            $('#address, #pincode').on('input', function() {
                if ($('#same_as_billing').is(':checked')) {
                    if (this.id === 'address') $('#shipping_address').val($(this).val());
                    if (this.id === 'pincode') $('#shipping_pincode').val($(this).val());
                }
            });

            $('#state_id, #city_id').on('change', function() {
                if ($('#same_as_billing').is(':checked')) {
                    copyBillingToShipping();
                }
            });

            // Form Submit Client Validation
            $('#mainForm').on('submit', function(e) {
                var form = this;
                var companyId = $('#company_id').val();
                var partyName = $('input[name="party_name"]').val().trim();
                var mobileNo = $('input[name="mobile_no"]').val().trim();
                var email = $('input[name="email"]').val().trim();
                var panNo = $('input[name="pan_no"]').val().trim().toUpperCase();
                $('input[name="pan_no"]').val(panNo);

                function showToastError(msg) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error!',
                            text: msg,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 4000,
                            timerProgressBar: true
                        });
                    } else {
                        alert(msg);
                    }
                }

                if (!companyId || companyId === '') {
                    e.preventDefault();
                    showToastError('Please select Company Name!');
                    return false;
                }

                if (partyName === '') {
                    e.preventDefault();
                    showToastError('Please enter Party Name!');
                    return false;
                }

                if (mobileNo !== '' && !/^[0-9]{10}$/.test(mobileNo)) {
                    e.preventDefault();
                    showToastError('Mobile Number must be exactly 10 digits!');
                    return false;
                }

                if (email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    e.preventDefault();
                    showToastError('Please enter a valid Email Address!');
                    return false;
                }

                if (panNo !== '' && !/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(panNo)) {
                    e.preventDefault();
                    showToastError('PAN Number must be valid 10-character alphanumeric (e.g. ABCDE1234F)!');
                    return false;
                }

                if ($(form).data('valid') === true) {
                    return true;
                }

                $(form).data('valid', true);
                HTMLFormElement.prototype.submit.call(form);
            });
        });
    </script>
</body>
</html>
