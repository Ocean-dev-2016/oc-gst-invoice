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

// Fetch active invoice templates
$templates = $ai_db->aiGetQueryObj("SELECT id, template_name, preview_image, is_premium FROM tbl_invoice_templates WHERE status='active' ORDER BY id ASC");

// Handle Admin updating Company Template from Profile page
if (!$isCompany && isset($_POST['btn_save_company_template'])) {
    $sel_company_id = intval($_POST['company_id'] ?? 0);
    $sel_template_id = intval($_POST['invoice_template_id'] ?? 1);
    if ($sel_template_id <= 0) $sel_template_id = 1;

    if ($sel_company_id <= 0) {
        $err_msg = "Please select a Company!";
    } else {
        $upd = $ai_db->aiQuery("UPDATE tbl_company SET invoice_template_id='$sel_template_id' WHERE id='$sel_company_id'");
        if ($upd) {
            $success_msg = "Company invoice & quotation template updated successfully!";
        } else {
            $err_msg = "Failed to update company template.";
        }
    }
}

// Fetch active companies (for Admin template selection - fetched AFTER update so updated template_id is used)
$allCompanies = [];
if (!$isCompany) {
    $allCompanies = $ai_db->aiGetQueryObj("SELECT id, company_name, gst_no, invoice_template_id FROM tbl_company WHERE status='active' ORDER BY company_name ASC");
}

// Fetch active states for dropdown (for company)
$states = [];
if ($isCompany) {
    $states = $ai_db->aiGetQueryObj("SELECT id, state_name, state_code FROM tbl_state WHERE status='active' ORDER BY order_no ASC, state_name ASC");
}

// ===================== UPDATE PROFILE =====================
if (isset($_POST['btn_submit'])) {
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
        $invoice_template_id = intval($_POST['invoice_template_id'] ?? 1);
        if ($invoice_template_id <= 0) $invoice_template_id = 1;
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
                    username='" . $username . "',
                    invoice_template_id='" . $invoice_template_id . "'
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
        $invoice_template_id = intval($_POST['invoice_template_id'] ?? 1);
        if ($invoice_template_id <= 0) $invoice_template_id = 1;
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

<style>
    /* Template Radio Thumbnail Cards */
    .template-thumb-choice {
        position: relative;
        cursor: pointer;
        display: inline-block;
        margin: 0;
    }

    .template-thumb-choice input[type="radio"] {
        position: absolute;
        top: 8px;
        left: 8px;
        z-index: 3;
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: #7367f0;
    }

    .template-thumb-box {
        width: 130px;
        height: 165px;
        background: #ffffff;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 5px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        transition: all 0.2s ease-in-out;
    }

    .template-thumb-box img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        border-radius: 4px;
        transition: transform 0.2s ease-in-out;
    }

    .template-thumb-choice:hover .template-thumb-box {
        border-color: #7367f0;
        transform: translateY(-2px);
        box-shadow: 0 6px 14px rgba(115, 103, 240, 0.2);
    }

    .template-thumb-choice:hover .template-thumb-box img {
        transform: scale(1.03);
    }

    .template-thumb-choice input[type="radio"]:checked+.template-thumb-box {
        border-color: #7367f0;
        box-shadow: 0 0 0 2px #7367f0, 0 6px 14px rgba(115, 103, 240, 0.25);
    }

    .template-thumb-badge-check {
        position: absolute;
        bottom: 8px;
        right: 8px;
        background: #7367f0;
        color: #fff;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        z-index: 2;
    }

    .template-thumb-choice input[type="radio"]:checked~.template-thumb-badge-check {
        display: flex;
    }

    /* PRO Badge for Premium Templates */
    .template-pro-badge {
        position: absolute;
        top: 6px;
        right: 6px;
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: #ffffff;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.8px;
        padding: 2px 7px;
        border-radius: 4px;
        box-shadow: 0 2px 6px rgba(217, 119, 6, 0.4);
        z-index: 4;
        pointer-events: none;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 3px;
    }

    .template-thumb-choice.is-disabled-pro {
        cursor: not-allowed;
        opacity: 0.82;
    }

    .template-thumb-choice.is-disabled-pro .template-thumb-box {
        background: #f8fafc;
        border-color: #cbd5e1;
        cursor: not-allowed;
    }

    .template-thumb-choice.is-disabled-pro:hover .template-thumb-box {
        border-color: #f59e0b;
        transform: none;
        box-shadow: 0 2px 8px rgba(245, 158, 11, 0.2);
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
                                            <div class="col-md-4 mt-3">
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
                                            <div class="col-md-4 mt-3">
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

                                        <!-- Invoice / Quotation Template Selection (SVG Thumbnails with Radio buttons) -->
                                        <div class="row g-3 mt-3">
                                            <div class="col-12">
                                                <label class="form-label fw-bold text-uppercase small text-dark d-block mb-2">
                                                    <i class="ti ti-layout-grid me-1 text-primary"></i> Invoice &amp; Quotation Template (PDF Layout) <span class="text-danger">*</span>
                                                </label>
                                                <?php
                                                $curr_comp_tpl = intval($_POST['invoice_template_id'] ?? $profileData->invoice_template_id ?? 1);
                                                ?>
                                                <div class="d-flex flex-wrap gap-3 align-items-center p-3 border rounded bg-light">
                                                    <?php if (!empty($templates)) {
                                                        foreach ($templates as $tpl) {
                                                            $isPrem = (!empty($tpl->is_premium) && (int)$tpl->is_premium === 1);
                                                            $isRadioChecked = ($curr_comp_tpl == $tpl->id && !$isPrem);
                                                            $radioId = 'comp_tpl_opt_' . $tpl->id;
                                                            $imgSrc = !empty($tpl->preview_image) ? SITE_URL . $tpl->preview_image : SITE_URL . 'assets/img/templates/template-classic.svg';
                                                    ?>
                                                            <label class="template-thumb-choice <?= $isPrem ? 'is-disabled-pro' : '' ?>" for="<?= $isPrem ? '' : $radioId ?>" title="<?= htmlspecialchars($tpl->template_name) ?><?= $isPrem ? ' (PRO Template)' : '' ?>">
                                                                <?php if ($isPrem): ?>
                                                                    <div class="template-pro-badge">
                                                                        <i class="ti ti-crown"></i> PRO
                                                                    </div>
                                                                <?php endif; ?>
                                                                <input type="radio" name="invoice_template_id" id="<?= $radioId ?>" value="<?= $tpl->id ?>" <?= $isRadioChecked ? 'checked' : '' ?> <?= $isPrem ? 'disabled' : 'required' ?> <?= $isPrem ? 'style="pointer-events:none; cursor:not-allowed;"' : '' ?>>
                                                                <div class="template-thumb-box">
                                                                    <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($tpl->template_name) ?>">
                                                                </div>
                                                                <?php if (!$isPrem): ?>
                                                                    <div class="template-thumb-badge-check">
                                                                        <i class="ti ti-check"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div class="text-center mt-1 small fw-semibold text-truncate" style="max-width: 130px;" title="<?= htmlspecialchars($tpl->template_name) ?>">
                                                                    <?= htmlspecialchars($tpl->template_name) ?>
                                                                </div>
                                                            </label>
                                                        <?php }
                                                    } else { ?>
                                                        <label class="template-thumb-choice" for="comp_tpl_opt_1">
                                                            <input type="radio" name="invoice_template_id" id="comp_tpl_opt_1" value="1" checked required>
                                                            <div class="template-thumb-box">
                                                                <img src="<?= SITE_URL ?>assets/img/templates/template-classic.svg" alt="Classic Template">
                                                            </div>
                                                            <div class="template-thumb-badge-check">
                                                                <i class="ti ti-check"></i>
                                                            </div>
                                                            <div class="text-center mt-1 small fw-semibold">Classic Professional</div>
                                                        </label>
                                                    <?php } ?>
                                                </div>
                                                <small class="text-muted d-block mt-2">All PDF invoices, quotations, and bills generated by your company will automatically follow the selected template layout.</small>
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

                                    <!-- ==================== ADMIN: COMPANY TEMPLATE SELECTION CARD ==================== -->
                                    <div class="card mt-4 border shadow-sm">
                                        <div class="card-header bg-light py-3 border-bottom d-flex align-items-center justify-content-between">
                                            <h6 class="mb-0 fw-bold text-dark">
                                                <i class="ti ti-template me-2 text-primary"></i> Company-wise Invoice &amp; Quotation Template Setting
                                            </h6>
                                            <span class="badge bg-label-primary font-monospace">Company Template Setting</span>
                                        </div>
                                        <div class="card-body pt-3">
                                            <p class="text-muted small mb-3">
                                                Select a company and choose which invoice &amp; quotation template will be used when generating PDFs for that company.
                                            </p>

                                            <form method="post" action="manage-profile-form.php" id="adminCompanyTemplateForm">
                                                <!-- 1. Company Selection Dropdown -->
                                                <div class="row g-3 mb-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-bold" for="admin_sel_company_id">
                                                            <i class="ti ti-building me-1 text-primary"></i> Select Company <span class="text-danger">*</span>
                                                        </label>
                                                        <select name="company_id" id="admin_sel_company_id" class="form-select select2" required>
                                                            <option value="">-- Choose Company --</option>
                                                            <?php
                                                            $last_saved_company_id = intval($_POST['company_id'] ?? 0);
                                                            if (!empty($allCompanies)) {
                                                                foreach ($allCompanies as $c) {
                                                                    $gstText = !empty($c->gst_no) ? ' (' . $c->gst_no . ')' : '';
                                                                    $isCompSelected = ($last_saved_company_id > 0 && $last_saved_company_id == $c->id) ? 'selected' : '';
                                                            ?>
                                                                    <option value="<?= $c->id ?>" data-template-id="<?= (int)($c->invoice_template_id ?? 1) ?>" <?= $isCompSelected ?>>
                                                                        <?= htmlspecialchars($c->company_name . $gstText) ?>
                                                                    </option>
                                                            <?php }
                                                            } ?>
                                                        </select>
                                                    </div>
                                                </div>

                                                <!-- 2. Template Radio Thumbnails (SVG Preview Cards) -->
                                                <div class="row g-3 mb-3">
                                                    <div class="col-12">
                                                        <label class="form-label fw-bold text-uppercase small text-dark d-block mb-2">
                                                            <i class="ti ti-layout-grid me-1 text-primary"></i> Choose Template for Selected Company <span class="text-danger">*</span>
                                                        </label>
                                                        <div class="d-flex flex-wrap gap-3 align-items-center p-3 border rounded bg-light" id="adminTemplateCardsWrapper">
                                                            <?php if (!empty($templates)) {
                                                                foreach ($templates as $tpl) {
                                                                    $isPrem = (!empty($tpl->is_premium) && (int)$tpl->is_premium === 1);
                                                                    $isFirstChecked = ($tpl->id == 1 && !$isPrem);
                                                                    $radioId = 'admin_tpl_opt_' . $tpl->id;
                                                                    $imgSrc = !empty($tpl->preview_image) ? SITE_URL . $tpl->preview_image : SITE_URL . 'assets/img/templates/template-classic.svg';
                                                            ?>
                                                                    <label class="template-thumb-choice <?= $isPrem ? 'is-disabled-pro' : '' ?>" for="<?= $isPrem ? '' : $radioId ?>" title="<?= htmlspecialchars($tpl->template_name) ?><?= $isPrem ? ' (PRO Template)' : '' ?>">
                                                                        <?php if ($isPrem): ?>
                                                                            <div class="template-pro-badge">
                                                                                <i class="ti ti-crown"></i> PRO
                                                                            </div>
                                                                        <?php endif; ?>
                                                                        <input type="radio" name="invoice_template_id" class="admin-tpl-radio" id="<?= $radioId ?>" value="<?= $tpl->id ?>" <?= $isFirstChecked ? 'checked' : '' ?> <?= $isPrem ? 'disabled' : 'required' ?> <?= $isPrem ? 'style="pointer-events:none; cursor:not-allowed;"' : '' ?>>
                                                                        <div class="template-thumb-box">
                                                                            <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($tpl->template_name) ?>">
                                                                        </div>
                                                                        <?php if (!$isPrem): ?>
                                                                            <div class="template-thumb-badge-check">
                                                                                <i class="ti ti-check"></i>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                        <div class="text-center mt-1 small fw-semibold text-truncate" style="max-width: 130px;" title="<?= htmlspecialchars($tpl->template_name) ?>">
                                                                            <?= htmlspecialchars($tpl->template_name) ?>
                                                                        </div>
                                                                    </label>
                                                                <?php }
                                                            } else { ?>
                                                                <label class="template-thumb-choice" for="admin_tpl_opt_1">
                                                                    <input type="radio" name="invoice_template_id" id="admin_tpl_opt_1" value="1" checked required>
                                                                    <div class="template-thumb-box">
                                                                        <img src="<?= SITE_URL ?>assets/img/templates/template-classic.svg" alt="Classic Template">
                                                                    </div>
                                                                    <div class="template-thumb-badge-check">
                                                                        <i class="ti ti-check"></i>
                                                                    </div>
                                                                    <div class="text-center mt-1 small fw-semibold">Classic Professional</div>
                                                                </label>
                                                            <?php } ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Submit Button -->
                                                <div>
                                                    <button type="submit" name="btn_save_company_template" id="btnSaveCompanyTemplate" class="btn btn-success">
                                                        <i class="ti ti-device-floppy me-1"></i> Save Company Template
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
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

            // Admin: When Company changes, auto-check the matching radio button for that company (default to 1)
            $('#admin_sel_company_id').on('change', function() {
                var selectedOption = $(this).find('option:selected');
                var compVal = $(this).val();
                if (!compVal) return;
                var tplId = selectedOption.data('template-id') || 1;
                var $targetRadio = $('#admin_tpl_opt_' + tplId);
                if ($targetRadio.length) {
                    $targetRadio.prop('checked', true);
                } else {
                    $('#admin_tpl_opt_1').prop('checked', true);
                }
            });

            // If a company is already selected on page load (e.g. after save), trigger check
            if ($('#admin_sel_company_id').val()) {
                var initTpl = $('#admin_sel_company_id option:selected').data('template-id') || 1;
                $('#admin_tpl_opt_' + initTpl).prop('checked', true);
            }
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
