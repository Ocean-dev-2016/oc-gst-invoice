<?php
include('includes/header.php');

$page_nm = 'Company';
$table = 'tbl_company';
$redirection_url = 'manage-company-list.php';

error_reporting(E_ALL);

$mode = $_REQUEST['mode'] ?? 'add';
$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

$err_msg = '';
$categoryData = null;

function validatePasswordComplexity($password, $confirm_password) {
    if (empty($password)) return '';
    if (strlen($password) < 8) {
        return "Password must be at least 8 characters long!";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return "Password must contain at least 1 uppercase letter!";
    }
    if (!preg_match('/[0-9]/', $password)) {
        return "Password must contain at least 1 number!";
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return "Password must contain at least 1 special character!";
    }
    if ($password !== $confirm_password) {
        return "Password and Confirm Password do not match!";
    }
    return '';
}

// Fetch active states for dropdown
$states = $ai_db->aiGetQueryObj("SELECT id, state_name, state_code FROM tbl_state WHERE status='active' ORDER BY order_no ASC, state_name ASC");

// ===================== ADD =====================
if ($mode === 'add' && ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_POST['btn_submit']))) {
    $company_name = addslashes(trim($_POST['company_name'] ?? ''));
    $gst_no = addslashes(trim($_POST['gst_no'] ?? ''));
    $state_id = intval($_POST['state_id'] ?? 0);
    $city_id = intval($_POST['city_id'] ?? 0);
    $address = addslashes(trim($_POST['address'] ?? ''));
    $shipping_address = addslashes(trim($_POST['shipping_address'] ?? ''));
    $shipping_state_id = intval($_POST['shipping_state_id'] ?? 0);
    $shipping_city_id = intval($_POST['shipping_city_id'] ?? 0);
    $owner_name = addslashes(trim($_POST['owner_name'] ?? ''));
    $mobile_no = addslashes(trim($_POST['mobile_no'] ?? ''));
    $email = addslashes(trim($_POST['email'] ?? ''));
    $username = addslashes(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $order_no = intval($_POST['order_no'] ?? 0);
    $status = $_POST['status'] ?? 'deactive';

    $pass_err = validatePasswordComplexity($password, $confirm_password);

    if (empty($company_name)) {
        $err_msg = "Company Name is required!";
    } elseif (!empty($mobile_no) && !preg_match('/^[0-9]{10}$/', $mobile_no)) {
        $err_msg = "Mobile Number must be exactly 10 digits!";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err_msg = "Please enter a valid Email Address!";
    } elseif (!empty($pass_err)) {
        $err_msg = $pass_err;
    } else {
        // Check duplicate
        $dup_conds = ["LOWER(company_name)='" . strtolower($company_name) . "'"];
        if (!empty($gst_no)) $dup_conds[] = "LOWER(gst_no)='" . strtolower($gst_no) . "'";
        if (!empty($mobile_no)) $dup_conds[] = "mobile_no='" . $mobile_no . "'";
        if (!empty($email)) $dup_conds[] = "LOWER(email)='" . strtolower($email) . "'";
        if (!empty($username)) $dup_conds[] = "LOWER(username)='" . strtolower($username) . "'";

        $check_dup = $ai_db->aiGetQueryObj("SELECT id, company_name, gst_no, mobile_no, email, username FROM $table WHERE (" . implode(" OR ", $dup_conds) . ") LIMIT 1");
        
        if (!empty($check_dup)) {
            if (strtolower($check_dup[0]->company_name) === strtolower($company_name)) {
                $err_msg = "Company Name '$company_name' already exists!";
            } elseif (!empty($gst_no) && strtolower($check_dup[0]->gst_no) === strtolower($gst_no)) {
                $err_msg = "GST Number '$gst_no' already exists!";
            } elseif (!empty($mobile_no) && $check_dup[0]->mobile_no === $mobile_no) {
                $err_msg = "Mobile Number '$mobile_no' already exists!";
            } elseif (!empty($email) && strtolower($check_dup[0]->email) === strtolower($email)) {
                $err_msg = "Email Address '$email' already exists!";
            } elseif (!empty($username) && strtolower($check_dup[0]->username) === strtolower($username)) {
                $err_msg = "Username '$username' already exists!";
            }
        }

        if (empty($err_msg)) {
            $pass_hash = !empty($password) ? md5($password) : '';

            $add_qry = "INSERT INTO $table SET 
                company_name='" . $company_name . "',
                gst_no='" . $gst_no . "',
                state_id='" . $state_id . "',
                city_id='" . $city_id . "',
                address='" . $address . "',
                shipping_address='" . $shipping_address . "',
                shipping_state_id='" . $shipping_state_id . "',
                shipping_city_id='" . $shipping_city_id . "',
                owner_name='" . $owner_name . "',
                mobile_no='" . $mobile_no . "',
                email='" . $email . "',
                username='" . $username . "',
                password='" . $pass_hash . "',
                status='" . $status . "'";

            $ai_db->aiQuery($add_qry);
            $ai_core->aiGoPage($redirection_url . '?msg=1');
            exit;
        }
    }
}

// ===================== EDIT =====================
if ($mode === 'edit' && (isset($_POST['btn_submit']) || $_SERVER['REQUEST_METHOD'] === 'POST')) {
    $company_name = addslashes(trim($_POST['company_name'] ?? ''));
    $gst_no = addslashes(trim($_POST['gst_no'] ?? ''));
    $state_id = intval($_POST['state_id'] ?? 0);
    $city_id = intval($_POST['city_id'] ?? 0);
    $address = addslashes(trim($_POST['address'] ?? ''));
    $shipping_address = addslashes(trim($_POST['shipping_address'] ?? ''));
    $shipping_state_id = intval($_POST['shipping_state_id'] ?? 0);
    $shipping_city_id = intval($_POST['shipping_city_id'] ?? 0);
    $owner_name = addslashes(trim($_POST['owner_name'] ?? ''));
    $mobile_no = addslashes(trim($_POST['mobile_no'] ?? ''));
    $email = addslashes(trim($_POST['email'] ?? ''));
    $username = addslashes(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $order_no = intval($_POST['order_no'] ?? 0);
    $status = $_POST['status'] ?? 'deactive';

    $pass_err = validatePasswordComplexity($password, $confirm_password);

    if (empty($company_name)) {
        $err_msg = "Company Name is required!";
    } elseif (!empty($mobile_no) && !preg_match('/^[0-9]{10}$/', $mobile_no)) {
        $err_msg = "Mobile Number must be exactly 10 digits!";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err_msg = "Please enter a valid Email Address!";
    } elseif (!empty($pass_err)) {
        $err_msg = $pass_err;
    } else {
        // Check duplicate excluding current ID
        $dup_conds = ["LOWER(company_name)='" . strtolower($company_name) . "'"];
        if (!empty($gst_no)) $dup_conds[] = "LOWER(gst_no)='" . strtolower($gst_no) . "'";
        if (!empty($mobile_no)) $dup_conds[] = "mobile_no='" . $mobile_no . "'";
        if (!empty($email)) $dup_conds[] = "LOWER(email)='" . strtolower($email) . "'";
        if (!empty($username)) $dup_conds[] = "LOWER(username)='" . strtolower($username) . "'";

        $check_dup = $ai_db->aiGetQueryObj("SELECT id, company_name, gst_no, mobile_no, email, username FROM $table WHERE (" . implode(" OR ", $dup_conds) . ") AND id!='" . intval($id) . "' LIMIT 1");
        
        if (!empty($check_dup)) {
            if (strtolower($check_dup[0]->company_name) === strtolower($company_name)) {
                $err_msg = "Company Name '$company_name' already exists!";
            } elseif (!empty($gst_no) && strtolower($check_dup[0]->gst_no) === strtolower($gst_no)) {
                $err_msg = "GST Number '$gst_no' already exists!";
            } elseif (!empty($mobile_no) && $check_dup[0]->mobile_no === $mobile_no) {
                $err_msg = "Mobile Number '$mobile_no' already exists!";
            } elseif (!empty($email) && strtolower($check_dup[0]->email) === strtolower($email)) {
                $err_msg = "Email Address '$email' already exists!";
            } elseif (!empty($username) && strtolower($check_dup[0]->username) === strtolower($username)) {
                $err_msg = "Username '$username' already exists!";
            }
        }

        if (empty($err_msg)) {
            $pass_sql = !empty($password) ? ", password='" . md5($password) . "'" : "";

            $edit_qry = "UPDATE $table SET 
                company_name='" . $company_name . "',
                gst_no='" . $gst_no . "',
                state_id='" . $state_id . "',
                city_id='" . $city_id . "',
                address='" . $address . "',
                shipping_address='" . $shipping_address . "',
                shipping_state_id='" . $shipping_state_id . "',
                shipping_city_id='" . $shipping_city_id . "',
                owner_name='" . $owner_name . "',
                mobile_no='" . $mobile_no . "',
                email='" . $email . "',
                username='" . $username . "'
                $pass_sql,
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

// Pre-fetch cities if state is selected
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
                            <span class="text-muted fw-light">Manage /</span> <?= $page_nm ?>
                        </h4>

                        <div class="row">
                            <div class="col-12">
                                <div class="card mb-4" id="categoryForm">
                                    <h5 class="card-header" id="formTitle">
                                        <?= ($mode == 'edit') ? 'Edit ' . $page_nm : 'Add ' . $page_nm ?>
                                    </h5>

                                    <form class="card-body" id="mainForm" method="post"
                                        action="manage-company-form.php?mode=<?= $mode ?>&id=<?= $id ?>"
                                        enctype="multipart/form-data">

                                        <input type="hidden" name="id" id="cat_id" value="<?= $categoryData->id ?? $id ?>">

                                        <div class="row g-3">
                                            <!-- Company Name -->
                                            <div class="col-md-6">
                                                <label class="form-label">Company Name <span class="text-danger">*</span></label>
                                                <input type="text" name="company_name" class="form-control" placeholder="e.g. Ocean Infotech" value="<?= htmlspecialchars($_POST['company_name'] ?? $categoryData->company_name ?? '') ?>" required>
                                            </div>

                                            <!-- GST No (Optional) -->
                                            <div class="col-md-6">
                                                <label class="form-label">GST No <small class="text-muted">(Optional)</small></label>
                                                <input type="text" name="gst_no" class="form-control" placeholder="e.g. 24AAAAA0000A1Z5" value="<?= htmlspecialchars($_POST['gst_no'] ?? $categoryData->gst_no ?? '') ?>">
                                            </div>

                                            <!-- State (Select2) -->
                                            <div class="col-md-4">
                                                <label class="form-label">State</label>
                                                <select name="state_id" id="state_id" class="form-select select2">
                                                    <option value="">-- Select State --</option>
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
                                                    <option value="">-- Select City --</option>
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

                                            <!-- Address -->
                                            <div class="col-md-4">
                                                <label class="form-label">Address</label>
                                                <textarea name="address" class="form-control" rows="2" placeholder="Enter full address..."><?= htmlspecialchars($_POST['address'] ?? $categoryData->address ?? '') ?></textarea>
                                            </div>

                                            <!-- Shipping Details Section -->
                                            <div class="col-12 mt-3">
                                                <h6 class="fw-bold mb-1"><i class="ti ti-truck me-1"></i> Shipping Details</h6>
                                                <p class="text-muted small mb-2">Optional shipping address and destination details.</p>
                                                <hr class="mt-1 mb-2">
                                            </div>

                                            <!-- Shipping State (Select2) -->
                                            <div class="col-md-4">
                                                <label class="form-label">Shipping State</label>
                                                <select name="shipping_state_id" id="shipping_state_id" class="form-select select2">
                                                    <option value="">-- Select Shipping State --</option>
                                                    <?php if (!empty($states)) {
                                                        foreach ($states as $st) { ?>
                                                            <option value="<?= $st->id ?>" <?= ($selected_shipping_state_id == $st->id) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($st->state_name) ?> (Code: <?= htmlspecialchars($st->state_code) ?>)
                                                            </option>
                                                        <?php }
                                                    } ?>
                                                </select>
                                            </div>

                                            <!-- Shipping City (Select2 Dynamic) -->
                                            <div class="col-md-4">
                                                <label class="form-label">Shipping City</label>
                                                <select name="shipping_city_id" id="shipping_city_id" class="form-select select2">
                                                    <option value="">-- Select Shipping City --</option>
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

                                            <!-- Shipping Address -->
                                            <div class="col-md-4">
                                                <label class="form-label">Shipping Address</label>
                                                <textarea name="shipping_address" class="form-control" rows="2" placeholder="Enter shipping address..."><?= htmlspecialchars($_POST['shipping_address'] ?? $categoryData->shipping_address ?? '') ?></textarea>
                                            </div>

                                            <!-- Company Owner & Contact Details Section -->
                                            <div class="col-12 mt-3">
                                                <h6 class="fw-bold mb-1"><i class="ti ti-user me-1"></i> Contact & Credentials</h6>
                                                <hr class="mt-1 mb-2">
                                            </div>

                                            <!-- Owner Name -->
                                            <div class="col-md-4">
                                                <label class="form-label">Owner Name</label>
                                                <input type="text" name="owner_name" class="form-control" placeholder="e.g. John Doe" value="<?= htmlspecialchars($_POST['owner_name'] ?? $categoryData->owner_name ?? '') ?>">
                                            </div>

                                            <!-- Mobile No -->
                                            <div class="col-md-4">
                                                <label class="form-label">Mobile No</label>
                                                <input type="text" name="mobile_no" class="form-control" placeholder="e.g. 9876543210" value="<?= htmlspecialchars($_POST['mobile_no'] ?? $categoryData->mobile_no ?? '') ?>">
                                            </div>

                                            <!-- Email -->
                                            <div class="col-md-4">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" name="email" class="form-control" placeholder="e.g. info@company.com" value="<?= htmlspecialchars($_POST['email'] ?? $categoryData->email ?? '') ?>">
                                            </div>

                                            <!-- Username -->
                                            <div class="col-md-4">
                                                <label class="form-label">Username</label>
                                                <input type="text" name="username" class="form-control" placeholder="e.g. oceanadmin" value="<?= htmlspecialchars($_POST['username'] ?? $categoryData->username ?? '') ?>">
                                            </div>

                                            <!-- Password -->
                                            <div class="col-md-4">
                                                <label class="form-label">Password <?= $mode === 'edit' ? '<small class="text-muted">(Leave blank to keep unchanged)</small>' : '' ?></label>
                                                <div class="input-group input-group-merge">
                                                    <input type="password" name="password" id="password" class="form-control" placeholder="••••••••">
                                                    <span class="input-group-text cursor-pointer toggle-pass"><i class="ti ti-eye-off"></i></span>
                                                </div>
                                            </div>

                                            <!-- Confirm Password -->
                                            <div class="col-md-4">
                                                <label class="form-label">Confirm Password</label>
                                                <div class="input-group input-group-merge">
                                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="••••••••">
                                                    <span class="input-group-text cursor-pointer toggle-pass"><i class="ti ti-eye-off"></i></span>
                                                </div>
                                            </div>

                                            <!-- Status -->
                                            <div class="col-md-4">
                                                <label class="form-label">Status</label>
                                                <select name="status" id="cat_status" class="form-select select2">
                                                    <option value="active" <?= ((($_POST['status'] ?? $categoryData->status ?? 'active') == 'active')) ? 'selected' : '' ?>>Active</option>
                                                    <option value="deactive" <?= ((($_POST['status'] ?? $categoryData->status ?? '') == 'deactive')) ? 'selected' : '' ?>>Deactive</option>
                                                </select>
                                            </div>

                                            <!-- Password Requirements Terms -->
                                            <div class="col-md-4">
                                                <div class="card bg-label-warning border-warning border shadow-none mb-0">
                                                    <div class="card-body py-2 px-3">
                                                        <h6 class="card-title text-warning fw-bold mb-1" style="font-size: 13px;">
                                                            <i class="ti ti-lock me-1"></i> Password Requirements:
                                                        </h6>
                                                        <ul class="list-unstyled mb-0 small" style="font-size: 12px; line-height: 1.6;">
                                                            <li id="rule-minlen" class="text-muted"><i class="ti ti-circle-dot me-1"></i> Minimum 8 characters</li>
                                                            <div id="rule-number" class="text-muted"><i class="ti ti-circle-dot me-1"></i> At least 1 number</div>
                                                            <div id="rule-uppercase" class="text-muted"><i class="ti ti-circle-dot me-1"></i> At least 1 uppercase letter</div>
                                                            <div id="rule-special" class="text-muted"><i class="ti ti-circle-dot me-1"></i> At least 1 special character</div>
                                                        </ul>
                                                    </div>
                                                </div>
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
            // Toggle Password Visibility
            $(".toggle-pass").click(function() {
                var input = $(this).closest('.input-group').find('input');
                var icon = $(this).find('i');
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('ti-eye-off').addClass('ti-eye');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('ti-eye').addClass('ti-eye-off');
                }
            });

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
                            $cityDropdown.html('<option value="">-- Select City --</option>');
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
                    $cityDropdown.html('<option value="">-- Select City --</option>');
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
                            $shipCityDropdown.html('<option value="">-- Select Shipping City --</option>');
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
                    $shipCityDropdown.html('<option value="">-- Select Shipping City --</option>');
                    if ($.fn.select2) {
                        $shipCityDropdown.trigger('change');
                    }
                }
            });

            // Real-time password requirement checklist update
            $('#password').on('keyup input change', function() {
                var val = $(this).val();

                // 1. Min 8 chars
                if (val.length >= 8) {
                    $('#rule-minlen').removeClass('text-muted').addClass('text-success fw-bold')
                        .find('i').removeClass('ti-circle-dot').addClass('ti-circle-check');
                } else {
                    $('#rule-minlen').removeClass('text-success fw-bold').addClass('text-muted')
                        .find('i').removeClass('ti-circle-check').addClass('ti-circle-dot');
                }

                // 2. Number
                if (/[0-9]/.test(val)) {
                    $('#rule-number').removeClass('text-muted').addClass('text-success fw-bold')
                        .find('i').removeClass('ti-circle-dot').addClass('ti-circle-check');
                } else {
                    $('#rule-number').removeClass('text-success fw-bold').addClass('text-muted')
                        .find('i').removeClass('ti-circle-check').addClass('ti-circle-dot');
                }

                // 3. Uppercase
                if (/[A-Z]/.test(val)) {
                    $('#rule-uppercase').removeClass('text-muted').addClass('text-success fw-bold')
                        .find('i').removeClass('ti-circle-dot').addClass('ti-circle-check');
                } else {
                    $('#rule-uppercase').removeClass('text-success fw-bold').addClass('text-muted')
                        .find('i').removeClass('ti-circle-check').addClass('ti-circle-dot');
                }

                // 4. Special char
                if (/[^A-Za-z0-9]/.test(val)) {
                    $('#rule-special').removeClass('text-muted').addClass('text-success fw-bold')
                        .find('i').removeClass('ti-circle-dot').addClass('ti-circle-check');
                } else {
                    $('#rule-special').removeClass('text-success fw-bold').addClass('text-muted')
                        .find('i').removeClass('ti-circle-check').addClass('ti-circle-dot');
                }
            });

            // Client AJAX validation for duplicate company name / gst / username / mobile / email
            $('#mainForm').on('submit', function(e) {
                var form = this;
                var companyName = $('input[name="company_name"]').val().trim();
                var gstNo = $('input[name="gst_no"]').val().trim();
                var mobileNo = $('input[name="mobile_no"]').val().trim();
                var email = $('input[name="email"]').val().trim();
                var username = $('input[name="username"]').val().trim();
                var password = $('#password').val();
                var confirmPassword = $('#confirm_password').val();
                var id = $('input[name="id"]').val();

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

                if (companyName === '') {
                    e.preventDefault();
                    showToastError('Company Name is required!');
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

                if (password !== '') {
                    if (password.length < 8) {
                        e.preventDefault();
                        showToastError('Password must be at least 8 characters long!');
                        return false;
                    }
                    if (!/[A-Z]/.test(password)) {
                        e.preventDefault();
                        showToastError('Password must contain at least 1 uppercase letter!');
                        return false;
                    }
                    if (!/[0-9]/.test(password)) {
                        e.preventDefault();
                        showToastError('Password must contain at least 1 number!');
                        return false;
                    }
                    if (!/[^A-Za-z0-9]/.test(password)) {
                        e.preventDefault();
                        showToastError('Password must contain at least 1 special character!');
                        return false;
                    }
                    if (password !== confirmPassword) {
                        e.preventDefault();
                        showToastError('Password and Confirm Password do not match!');
                        return false;
                    }
                }

                if ($(form).data('valid') === true) {
                    return true;
                }

                e.preventDefault();

                $.ajax({
                    type: "POST",
                    url: "ajax.php",
                    data: {
                        action: "check_company_duplicate",
                        company_name: companyName,
                        gst_no: gstNo,
                        mobile_no: mobileNo,
                        email: email,
                        username: username,
                        id: id
                    },
                    dataType: "json",
                    success: function(response) {
                        if (response.status === "duplicate") {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Duplicate Entry!',
                                    text: response.message,
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 4000,
                                    timerProgressBar: true
                                });
                            } else {
                                alert(response.message);
                            }
                        } else {
                            $(form).data('valid', true);
                            HTMLFormElement.prototype.submit.call(form);
                        }
                    },
                    error: function() {
                        $(form).data('valid', true);
                        HTMLFormElement.prototype.submit.call(form);
                    }
                });
            });
        });
    </script>
</body>
</html>
