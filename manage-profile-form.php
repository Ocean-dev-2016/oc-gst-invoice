<?php
include('includes/header.php');
require_once __DIR__ . '/includes/company-logo-migrate.php';
require_once __DIR__ . '/includes/image-transparent-helper.php';

$userType = $_SESSION['user_type'] ?? (function_exists('ai_perm_detect_user_type') && isset($ai_db) ? ai_perm_detect_user_type($ai_db) : 'admin');
$isCompany = ($userType === 'company');

$page_nm = $isCompany ? "Company Profile" : "Manage Profile";
$table = $isCompany ? "tbl_company" : "tbl_admin";

error_reporting(E_ALL);
if ($isCompany) {
    $profile_id = !empty($_SESSION['company_id']) ? intval($_SESSION['company_id']) : intval($_SESSION['aid'] ?? 0);
} else {
    $profile_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : (isset($_SESSION['aid']) ? intval($_SESSION['aid']) : 1);
}

$mode = 'edit';
$err_msg = '';
$success_msg = '';

if (isset($_GET['msg']) && $_GET['msg'] == '2') {
    $success_msg = 'Profile updated successfully!';
}

// Fetch active states for dropdown (for company)
$states = [];
if ($isCompany) {
    $states = $ai_db->aiGetQueryObj("SELECT id, state_name, state_code FROM tbl_state WHERE status='active' ORDER BY order_no ASC, state_name ASC");
}

// ===================== UPDATE PROFILE =====================
if (isset($_POST['btn_submit']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isCompany) {
        // Handle Company Profile Update
        $company_name = addslashes(trim($_POST['company_name'] ?? ''));
        $gst_applicable = isset($_POST['gst_applicable']) ? intval($_POST['gst_applicable']) : 0;
        $gst_no = ($gst_applicable === 1) ? addslashes(trim($_POST['gst_no'] ?? '')) : '';
        $state_id = intval($_POST['state_id'] ?? 0);
        $city_id = intval($_POST['city_id'] ?? 0);
        $pincode = addslashes(trim($_POST['pincode'] ?? ''));
        $address = addslashes(trim($_POST['address'] ?? ''));
        $owner_name = addslashes(trim($_POST['owner_name'] ?? ''));
        $mobile_no = addslashes(trim($_POST['mobile_no'] ?? ''));
        $email = addslashes(trim($_POST['email'] ?? ''));
        $username = addslashes(trim($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($company_name)) {
            $err_msg = "Company Name is required!";
        } elseif ($gst_applicable === 1 && empty($gst_no)) {
            $err_msg = "GST Number is required when GST is Applicable!";
        } elseif ($gst_applicable === 1 && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i', $gst_no)) {
            $err_msg = "Please enter a valid 15-digit GST Number (e.g. 24AAAAA0000A1Z5)!";
        } elseif (!empty($pincode) && !preg_match('/^[0-9]{6}$/', $pincode)) {
            $err_msg = "Pincode must be exactly 6 digits!";
        } elseif (!empty($mobile_no) && !preg_match('/^[0-9]{10}$/', $mobile_no)) {
            $err_msg = "Mobile Number must be exactly 10 digits!";
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err_msg = "Please enter a valid Email Address!";
        } elseif (!empty($password) && $password !== $confirm_password) {
            $err_msg = "Password and Confirm Password do not match!";
        } else {
            // Check duplicates excluding current company ID
            $dup_conds = [];
            if (!empty($gst_no)) $dup_conds[] = "LOWER(gst_no)='" . strtolower($gst_no) . "'";
            if (!empty($mobile_no)) $dup_conds[] = "mobile_no='" . $mobile_no . "'";
            if (!empty($email)) $dup_conds[] = "LOWER(email)='" . strtolower($email) . "'";
            if (!empty($username)) $dup_conds[] = "LOWER(username)='" . strtolower($username) . "'";

            if (!empty($dup_conds)) {
                $check_dup = $ai_db->aiGetQueryObj("SELECT id, company_name, gst_no, mobile_no, email, username FROM $table WHERE (" . implode(" OR ", $dup_conds) . ") AND id!='" . intval($profile_id) . "' LIMIT 1");
                
                if (!empty($check_dup)) {
                    if (!empty($gst_no) && strtolower($check_dup[0]->gst_no) === strtolower($gst_no)) {
                        $err_msg = "GST Number '$gst_no' already exists!";
                    } elseif (!empty($mobile_no) && $check_dup[0]->mobile_no === $mobile_no) {
                        $err_msg = "Mobile Number '$mobile_no' already exists!";
                    } elseif (!empty($email) && strtolower($check_dup[0]->email) === strtolower($email)) {
                        $err_msg = "Email Address '$email' already exists!";
                    } elseif (!empty($username) && strtolower($check_dup[0]->username) === strtolower($username)) {
                        $err_msg = "Username '$username' already exists!";
                    }
                }
            }

            if (empty($err_msg)) {
                $uploadDir = __DIR__ . '/uploads/company/';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);

            $logo_sql = "";
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'svg', 'webp'])) {
                    $newLogo = 'logo_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $newLogo)) {
                        $logo_sql = ", logo='uploads/company/" . $newLogo . "'";
                    }
                }
            }

            $sign_sql = "";
            if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
                $newSign = 'sign_' . time() . '_' . rand(100, 999) . '.png';
                $destSignPath = $uploadDir . $newSign;
                if (function_exists('makeSignatureTransparent') && makeSignatureTransparent($_FILES['signature']['tmp_name'], $destSignPath)) {
                    $sign_sql = ", signature='uploads/company/" . $newSign . "'";
                } elseif (move_uploaded_file($_FILES['signature']['tmp_name'], $destSignPath)) {
                    $sign_sql = ", signature='uploads/company/" . $newSign . "'";
                }
            }

            $pass_sql = !empty($password) ? ", password='" . md5($password) . "'" : "";

                $edit_qry = "UPDATE $table SET 
                    company_name='" . $company_name . "',
                    gst_applicable='" . $gst_applicable . "',
                    gst_no='" . $gst_no . "',
                    state_id='" . $state_id . "',
                    city_id='" . $city_id . "',
                    pincode='" . $pincode . "',
                    address='" . $address . "',
                    owner_name='" . $owner_name . "',
                    mobile_no='" . $mobile_no . "',
                    email='" . $email . "',
                    username='" . $username . "'
                    $pass_sql
                    $logo_sql
                    $sign_sql
                    WHERE id='" . intval($profile_id) . "'";

                $ai_db->aiQuery($edit_qry);

                // Update session details
                $_SESSION['company_name'] = $company_name;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;

                $ai_core->aiGoPage("manage-profile-form.php?msg=2");
                exit;
            }
        }
    } else {
        // Handle Admin Profile Update (Admin has NO GST fields)
        $username = addslashes(trim($_POST['username'] ?? ''));
        $email = addslashes(trim($_POST['email'] ?? ''));
        $old_password = $_POST['old_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($username)) {
            $err_msg = "Username cannot be empty!";
        } else {
            $current_user = $ai_db->aiGetQueryObj("SELECT * FROM $table WHERE id='" . intval($profile_id) . "' LIMIT 1");
            
            if (empty($current_user)) {
                $err_msg = "User record not found!";
            } else {
                $user_data = $current_user[0];
                $update_password = false;
                $new_pass_hash = '';

                if (!empty($old_password) || !empty($new_password) || !empty($confirm_password)) {
                    if (empty($old_password)) {
                        $err_msg = "Please enter your Old Password!";
                    } elseif (md5($old_password) !== $user_data->password) {
                        $err_msg = "Old Password is incorrect!";
                    } elseif (empty($new_password)) {
                        $err_msg = "Please enter a New Password!";
                    } elseif ($new_password !== $confirm_password) {
                        $err_msg = "New Password and Confirm Password do not match!";
                    } else {
                        $update_password = true;
                        $new_pass_hash = md5($new_password);
                    }
                }

                if (empty($err_msg)) {
                    if ($update_password) {
                        $update_qry = "UPDATE $table SET 
                            username='" . $username . "', 
                            email='" . $email . "', 
                            password='" . $new_pass_hash . "' 
                            WHERE id='" . intval($profile_id) . "'";
                    } else {
                        $update_qry = "UPDATE $table SET 
                            username='" . $username . "', 
                            email='" . $email . "' 
                            WHERE id='" . intval($profile_id) . "'";
                    }

                    $ai_db->aiQuery($update_qry);

                    if ($profile_id == ($_SESSION['aid'] ?? 0)) {
                        $_SESSION['username'] = $username;
                        $_SESSION['email'] = $email;
                    }

                    $ai_core->aiGoPage("manage-profile-list.php?msg=2");
                    exit;
                }
            }
        }
    }
}

// Fetch record
$profile_row = $ai_db->aiGetQueryObj("SELECT * FROM $table WHERE id='" . intval($profile_id) . "' LIMIT 1");
$profileData = !empty($profile_row) ? $profile_row[0] : null;

// Pre-fetch cities if state is selected (for company)
$cities = [];
if ($isCompany && $profileData) {
    $selected_state_id = $_POST['state_id'] ?? $profileData->state_id ?? 0;
    if ($selected_state_id > 0) {
        $cities = $ai_db->aiGetQueryObj("SELECT id, city_name FROM tbl_city WHERE state_id='" . intval($selected_state_id) . "' AND status='active' ORDER BY order_no ASC, city_name ASC");
    }
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
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?= htmlspecialchars($page_nm) ?></h5>
                                <?php if (!$isCompany) { ?>
                                    <a href="manage-profile-list.php" class="btn btn-secondary">
                                        <i class="ti ti-arrow-left me-1"></i> Back to List
                                    </a>
                                <?php } else { ?>
                                    <a href="dashboard.php" class="btn btn-secondary">
                                        <i class="ti ti-arrow-left me-1"></i> Dashboard
                                    </a>
                                <?php } ?>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($err_msg)) { ?>
                                    <div class="alert alert-danger alert-dismissible" role="alert">
                                        <?= htmlspecialchars($err_msg) ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php } ?>

                                <?php if (!empty($success_msg)) { ?>
                                    <div class="alert alert-success alert-dismissible" role="alert">
                                        <?= htmlspecialchars($success_msg) ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php } ?>

                                <?php if ($isCompany) { ?>
                                    <!-- ==================== COMPANY PROFILE FORM (Includes GST fields) ==================== -->
                                    <form class="card-body p-0" id="mainForm" method="post" action="manage-profile-form.php" enctype="multipart/form-data">
                                        <input type="hidden" name="id" value="<?= intval($profile_id) ?>">

                                        <div class="row g-3">
                                            <!-- Company Name -->
                                            <div class="col-md-4">
                                                <label class="form-label">Company Name <span class="text-danger">*</span></label>
                                                <input type="text" name="company_name" class="form-control" placeholder="e.g. Ocean Infotech" value="<?= htmlspecialchars($_POST['company_name'] ?? $profileData->company_name ?? '') ?>" required>
                                            </div>

                                            <!-- Owner Name -->
                                            <div class="col-md-4">
                                                <label class="form-label">Owner Name</label>
                                                <input type="text" name="owner_name" class="form-control" placeholder="e.g. John Doe" value="<?= htmlspecialchars($_POST['owner_name'] ?? $profileData->owner_name ?? '') ?>">
                                            </div>

                                            <!-- Mobile No -->
                                            <div class="col-md-4">
                                                <label class="form-label">Mobile No</label>
                                                <input type="text" name="mobile_no" class="form-control" placeholder="e.g. 9876543210" value="<?= htmlspecialchars($_POST['mobile_no'] ?? $profileData->mobile_no ?? '') ?>">
                                            </div>

                                            <!-- Email -->
                                            <div class="col-md-4">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" name="email" class="form-control" placeholder="e.g. info@company.com" value="<?= htmlspecialchars($_POST['email'] ?? $profileData->email ?? '') ?>">
                                            </div>

                                            <!-- Username -->
                                            <div class="col-md-4">
                                                <label class="form-label">Username</label>
                                                <input type="text" name="username" class="form-control" placeholder="e.g. oceanadmin" value="<?= htmlspecialchars($_POST['username'] ?? $profileData->username ?? '') ?>">
                                            </div>

                                            <!-- Password -->
                                            <div class="col-md-4">
                                                <label class="form-label">Password <small class="text-muted">(Leave blank to keep unchanged)</small></label>
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

                                            <?php 
                                            $curr_gst_applicable = isset($_POST['gst_applicable']) 
                                                ? intval($_POST['gst_applicable']) 
                                                : intval($profileData->gst_applicable ?? 0);
                                            ?>
                                            <!-- GST Applicable -->
                                            <div class="col-md-4">
                                                <label class="form-label">GST Applicable <span class="text-danger">*</span></label>
                                                <select name="gst_applicable" id="gst_applicable" class="form-select select2" required>
                                                    <option value="0" <?= ($curr_gst_applicable === 0) ? 'selected' : '' ?>>No</option>
                                                    <option value="1" <?= ($curr_gst_applicable === 1) ? 'selected' : '' ?>>Yes</option>
                                                </select>
                                            </div>

                                            <!-- GST No (Shown only when GST Applicable is Yes) -->
                                            <div class="col-md-4" id="gst_no_wrapper" style="<?= ($curr_gst_applicable === 1) ? '' : 'display: none;' ?>">
                                                <label class="form-label">GST No <span class="text-danger">*</span></label>
                                                <input type="text" name="gst_no" id="gst_no" class="form-control text-uppercase" placeholder="e.g. 24AAAAA0000A1Z5" value="<?= htmlspecialchars($_POST['gst_no'] ?? $profileData->gst_no ?? '') ?>" maxlength="15">
                                            </div>

                                            <!-- State (Select2) -->
                                            <div class="col-md-4">
                                                <label class="form-label">State</label>
                                                <select name="state_id" id="state_id" class="form-select select2">
                                                    <option value="">-- Select State --</option>
                                                    <?php 
                                                    $sel_state = $_POST['state_id'] ?? $profileData->state_id ?? 0;
                                                    if (!empty($states)) {
                                                        foreach ($states as $st) { ?>
                                                            <option value="<?= $st->id ?>" <?= ($sel_state == $st->id) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($st->state_name) ?>
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
                                                        $selected_city_id = $_POST['city_id'] ?? $profileData->city_id ?? 0;
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
                                                <input type="text" name="pincode" id="pincode" class="form-control" placeholder="e.g. 360004" value="<?= htmlspecialchars($_POST['pincode'] ?? $profileData->pincode ?? '') ?>" maxlength="6">
                                            </div>

                                            <!-- Address -->
                                            <div class="col-md-4">
                                                <label class="form-label">Address</label>
                                                <textarea name="address" class="form-control" rows="2" placeholder="Enter full address..."><?= htmlspecialchars($_POST['address'] ?? $profileData->address ?? '') ?></textarea>
                                            </div>

                                            <!-- Company Logo Box -->
                                            <div class="col-md-6 mt-3">
                                                <label class="form-label fw-bold text-dark">
                                                    <i class="ti ti-photo me-1 text-primary"></i> Company Logo (for Invoices)
                                                </label>
                                                <input type="file" name="logo" id="logoInput" class="form-control" accept="image/*">
                                                <small class="text-muted d-block mt-1">Recommended: PNG or JPG. Appears on invoice header.</small>
                                                
                                                <div class="mt-2 p-3 bg-light border rounded text-center position-relative" style="min-height: 110px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #fafafa;">
                                                    <span class="text-muted fs-7 mb-1" id="logoPreviewTitle" style="<?= empty($profileData->logo) ? 'display:none;' : '' ?>">Current Logo:</span>
                                                    <img id="logoPreview" src="<?= !empty($profileData->logo) ? SITE_URL . $profileData->logo : '' ?>" alt="Logo Preview" style="max-height: 70px; max-width: 220px; object-fit: contain; <?= empty($profileData->logo) ? 'display:none;' : '' ?>">
                                                    <div id="logoPlaceholder" class="text-muted" style="<?= !empty($profileData->logo) ? 'display:none;' : '' ?>">
                                                        <i class="ti ti-photo-off fs-2 d-block text-secondary"></i>
                                                        <span class="small">No Logo uploaded yet</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Company Signature Box -->
                                            <div class="col-md-6 mt-3">
                                                <label class="form-label fw-bold text-dark">
                                                    <i class="ti ti-writing-sign me-1 text-primary"></i> Authorized Signature / Stamp
                                                </label>
                                                <input type="file" name="signature" id="signatureInput" class="form-control" accept="image/*">
                                                <small class="text-muted d-block mt-1">Background will be made 100% transparent automatically.</small>
                                                
                                                <div class="mt-2 p-3 bg-light border rounded text-center position-relative" style="min-height: 110px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #fafafa;">
                                                    <span class="text-muted fs-7 mb-1" id="signPreviewTitle" style="<?= empty($profileData->signature) ? 'display:none;' : '' ?>">Current Signature:</span>
                                                    <img id="signPreview" src="<?= !empty($profileData->signature) ? SITE_URL . $profileData->signature : '' ?>" alt="Signature Preview" style="max-height: 65px; max-width: 180px; object-fit: contain; <?= empty($profileData->signature) ? 'display:none;' : '' ?>">
                                                    <div id="signPlaceholder" class="text-muted" style="<?= !empty($profileData->signature) ? 'display:none;' : '' ?>">
                                                        <i class="ti ti-signature-off fs-2 d-block text-secondary"></i>
                                                        <span class="small">No Signature uploaded yet</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="pt-4">
                                            <button type="submit" name="btn_submit" class="btn btn-primary me-sm-3 me-1 waves-effect waves-light" id="btnSubmit">
                                                <i class="ti ti-check me-1"></i> Update Profile
                                            </button>
                                            <a href="dashboard.php" class="btn btn-label-secondary waves-effect">Cancel</a>
                                        </div>
                                    </form>

                                <?php } else { ?>
                                    <!-- ==================== ADMIN PROFILE FORM (NO GST Fields) ==================== -->
                                    <form method="post" action="">
                                        <input type="hidden" name="id" value="<?= intval($profile_id) ?>" />

                                        <div class="row">
                                            <!-- Username -->
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label" for="username">Username <span class="text-danger">*</span></label>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    id="username"
                                                    name="username"
                                                    value="<?= htmlspecialchars($_POST['username'] ?? $profileData->username ?? '') ?>"
                                                    required
                                                    placeholder="Enter username" />
                                            </div>

                                            <!-- Email -->
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label" for="email">Email Address <span class="text-danger">*</span></label>
                                                <input
                                                    type="email"
                                                    class="form-control"
                                                    id="email"
                                                    name="email"
                                                    value="<?= htmlspecialchars($_POST['email'] ?? $profileData->email ?? '') ?>"
                                                    required
                                                    placeholder="Enter email address" />
                                            </div>
                                        </div>

                                        <hr class="my-4" />
                                        <h6 class="fw-bold mb-3"><i class="ti ti-key me-2"></i>Change Password (Leave blank to keep current password)</h6>

                                        <div class="row">
                                            <!-- Old Password -->
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label" for="old_password">Old Password</label>
                                                <div class="input-group input-group-merge">
                                                    <input
                                                        type="password"
                                                        class="form-control"
                                                        id="old_password"
                                                        name="old_password"
                                                        placeholder="••••••••" />
                                                    <span class="input-group-text cursor-pointer toggle-pass"><i class="ti ti-eye-off"></i></span>
                                                </div>
                                            </div>

                                            <!-- New Password -->
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label" for="new_password">New Password</label>
                                                <div class="input-group input-group-merge">
                                                    <input
                                                        type="password"
                                                        class="form-control"
                                                        id="new_password"
                                                        name="new_password"
                                                        placeholder="••••••••" />
                                                    <span class="input-group-text cursor-pointer toggle-pass"><i class="ti ti-eye-off"></i></span>
                                                </div>
                                            </div>

                                            <!-- Confirm Password -->
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label" for="confirm_password">Confirm Password</label>
                                                <div class="input-group input-group-merge">
                                                    <input
                                                        type="password"
                                                        class="form-control"
                                                        id="confirm_password"
                                                        name="confirm_password"
                                                        placeholder="••••••••" />
                                                    <span class="input-group-text cursor-pointer toggle-pass"><i class="ti ti-eye-off"></i></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-3">
                                            <button type="submit" name="btn_submit" class="btn btn-primary me-2">
                                                <i class="ti ti-check me-1"></i> Update Profile
                                            </button>
                                            <a href="manage-profile-list.php" class="btn btn-label-secondary">Cancel</a>
                                        </div>
                                    </form>
                                <?php } ?>

                            </div>
                        </div>
                    </div>

                    <?php include('includes/footer.php'); ?>
                </div>
            </div>
        </div>
    </div>

    <?php include('includes/footer_js.php'); ?>

    <script>
    $(document).ready(function() {
        // Instant Logo Preview upon file select
        $('#logoInput').on('change', function(e) {
            var file = e.target.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('#logoPreview').attr('src', e.target.result).show();
                    $('#logoPreviewTitle').text('Selected Logo Preview:').show();
                    $('#logoPlaceholder').hide();
                }
                reader.readAsDataURL(file);
            }
        });

        // Instant Signature Preview upon file select
        $('#signatureInput').on('change', function(e) {
            var file = e.target.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('#signPreview').attr('src', e.target.result).show();
                    $('#signPreviewTitle').text('Selected Signature Preview:').show();
                    $('#signPlaceholder').hide();
                }
                reader.readAsDataURL(file);
            }
        });
    });
    </script>

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

            <?php if ($isCompany) { ?>
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

            // Toggle GST No field based on GST Applicable (Yes / No)
            function syncGstField() {
                var gstApp = $('#gst_applicable').val();
                if (gstApp === '1' || gstApp === 1) {
                    $('#gst_no_wrapper').slideDown(200);
                    $('#gst_no').prop('required', true);
                } else {
                    $('#gst_no_wrapper').slideUp(200);
                    $('#gst_no').prop('required', false).val('');
                }
            }

            $('#gst_applicable').on('change', function() {
                syncGstField();
            });

            syncGstField();

            // Client AJAX validation for duplicate company name / gst / username / mobile / email
            $('#mainForm').on('submit', function(e) {
                var form = this;
                var companyName = $('input[name="company_name"]').val().trim();
                var gstApplicable = $('#gst_applicable').val();
                var gstNo = (gstApplicable === '1' || gstApplicable === 1) ? $('input[name="gst_no"]').val().trim() : '';
                var mobileNo = $('input[name="mobile_no"]').val().trim();
                var pincode = $('input[name="pincode"]').val().trim();
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

                if ((gstApplicable === '1' || gstApplicable === 1) && gstNo === '') {
                    e.preventDefault();
                    showToastError('GST Number is required when GST is Applicable!');
                    $('#gst_no').focus();
                    return false;
                }

                var gstRegex = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i;
                if ((gstApplicable === '1' || gstApplicable === 1) && gstNo !== '' && !gstRegex.test(gstNo)) {
                    e.preventDefault();
                    showToastError('Please enter a valid 15-digit GST Number (e.g. 24AAAAA0000A1Z5)!');
                    $('#gst_no').focus();
                    return false;
                }

                if (pincode !== '' && !/^[0-9]{6}$/.test(pincode)) {
                    e.preventDefault();
                    showToastError('Pincode must be exactly 6 digits!');
                    $('#pincode').focus();
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

                if (password !== '' && password !== confirmPassword) {
                    e.preventDefault();
                    showToastError('Password and Confirm Password do not match!');
                    return false;
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
            <?php } ?>
        });
    </script>
</body>
</html>
