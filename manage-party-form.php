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
    $company_id = intval($_POST['company_id'] ?? 0);
    $party_name = addslashes(trim($_POST['party_name'] ?? ''));
    $address = addslashes(trim($_POST['address'] ?? ''));
    $state_id = intval($_POST['state_id'] ?? 0);
    $city_id = intval($_POST['city_id'] ?? 0);
    $pincode = addslashes(trim($_POST['pincode'] ?? ''));
    $mobile_no = addslashes(trim($_POST['mobile_no'] ?? ''));
    $gst_no = addslashes(trim($_POST['gst_no'] ?? ''));
    $party_status = addslashes(trim($_POST['party_status'] ?? 'Sales'));
    $status = $_POST['status'] ?? 'active';

    if (empty($party_name)) {
        $err_msg = "Please enter Party Name!";
    } elseif ($company_id <= 0) {
        $err_msg = "Please select Company!";
    } elseif (!empty($mobile_no) && !preg_match('/^[0-9]{10}$/', $mobile_no)) {
        $err_msg = "Mobile Number must be exactly 10 digits!";
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
                mobile_no='" . $mobile_no . "',
                gst_no='" . $gst_no . "',
                party_status='" . $party_status . "',
                status='" . $status . "'";

            $ai_db->aiQuery($add_qry);
            $ai_core->aiGoPage($redirection_url . '?msg=1');
            exit;
        }
    }
}

// ===================== EDIT =====================
if ($mode === 'edit' && ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_POST['btn_submit']))) {
    $company_id = intval($_POST['company_id'] ?? 0);
    $party_name = addslashes(trim($_POST['party_name'] ?? ''));
    $address = addslashes(trim($_POST['address'] ?? ''));
    $state_id = intval($_POST['state_id'] ?? 0);
    $city_id = intval($_POST['city_id'] ?? 0);
    $pincode = addslashes(trim($_POST['pincode'] ?? ''));
    $mobile_no = addslashes(trim($_POST['mobile_no'] ?? ''));
    $gst_no = addslashes(trim($_POST['gst_no'] ?? ''));
    $party_status = addslashes(trim($_POST['party_status'] ?? 'Sales'));
    $status = $_POST['status'] ?? 'active';

    if (empty($party_name)) {
        $err_msg = "Please enter Party Name!";
    } elseif ($company_id <= 0) {
        $err_msg = "Please select Company!";
    } elseif (!empty($mobile_no) && !preg_match('/^[0-9]{10}$/', $mobile_no)) {
        $err_msg = "Mobile Number must be exactly 10 digits!";
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
                mobile_no='" . $mobile_no . "',
                gst_no='" . $gst_no . "',
                party_status='" . $party_status . "',
                status='" . $status . "'
                WHERE id='" . intval($id) . "'";

            $ai_db->aiQuery($edit_qry);
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
                                            <div class="col-md-6">
                                                <label class="form-label">Company Name <span class="text-danger">*</span></label>
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
                                            </div>

                                            <!-- Party Name -->
                                            <div class="col-md-6">
                                                <label class="form-label">Party Name <span class="text-danger">*</span></label>
                                                <input type="text" name="party_name" class="form-control" placeholder="Enter party name" value="<?= htmlspecialchars($_POST['party_name'] ?? $categoryData->party_name ?? '') ?>" required>
                                            </div>

                                            <!-- Address -->
                                            <div class="col-md-12">
                                                <label class="form-label">Address</label>
                                                <textarea name="address" class="form-control" rows="2" placeholder="Enter Address"><?= htmlspecialchars($_POST['address'] ?? $categoryData->address ?? '') ?></textarea>
                                            </div>

                                            <!-- State -->
                                            <div class="col-md-4">
                                                <label class="form-label">State</label>
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

                                            <!-- City (Select2 Dynamic) -->
                                            <div class="col-md-4">
                                                <label class="form-label">City</label>
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

                                            <!-- Pincode -->
                                            <div class="col-md-4">
                                                <label class="form-label">Pincode</label>
                                                <input type="text" name="pincode" class="form-control" placeholder="Enter pincode" value="<?= htmlspecialchars($_POST['pincode'] ?? $categoryData->pincode ?? '') ?>">
                                            </div>

                                            <!-- Mobile No -->
                                            <div class="col-md-4">
                                                <label class="form-label">Mobile No.</label>
                                                <input type="text" name="mobile_no" class="form-control" placeholder="Enter mobile number" value="<?= htmlspecialchars($_POST['mobile_no'] ?? $categoryData->mobile_no ?? '') ?>">
                                            </div>

                                            <!-- GST IN No -->
                                            <div class="col-md-4">
                                                <label class="form-label">GST IN No.</label>
                                                <input type="text" name="gst_no" class="form-control" placeholder="Enter GST in number" value="<?= htmlspecialchars($_POST['gst_no'] ?? $categoryData->gst_no ?? '') ?>">
                                            </div>

                                            <!-- Party Status -->
                                            <div class="col-md-4">
                                                <label class="form-label">Party Status</label>
                                                <?php $sel_pstatus = $_POST['party_status'] ?? $categoryData->party_status ?? 'Sales'; ?>
                                                <select name="party_status" class="form-select select2">
                                                    <option value="">Select Party Status</option>
                                                    <option value="Sales" <?= ($sel_pstatus == 'Sales') ? 'selected' : '' ?>>Sales</option>
                                                    <option value="Purchase" <?= ($sel_pstatus == 'Purchase') ? 'selected' : '' ?>>Purchase</option>
                                                </select>
                                            </div>

                                            <!-- Status -->
                                            <div class="col-md-4">
                                                <label class="form-label">Status</label>
                                                <select name="status" class="form-select select2">
                                                    <option value="active" <?= ((($_POST['status'] ?? $categoryData->status ?? 'active') == 'active')) ? 'selected' : '' ?>>Active</option>
                                                    <option value="deactive" <?= ((($_POST['status'] ?? $categoryData->status ?? '') == 'deactive')) ? 'selected' : '' ?>>Deactive</option>
                                                </select>
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

            // Form Submit Client Validation
            $('#mainForm').on('submit', function(e) {
                var form = this;
                var companyId = $('#company_id').val();
                var partyName = $('input[name="party_name"]').val().trim();
                var mobileNo = $('input[name="mobile_no"]').val().trim();

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
