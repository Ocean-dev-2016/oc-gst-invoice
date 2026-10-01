<?php
include('includes/header.php');

$page_nm = "Manage Profile";
$table = "tbl_admin";
$redirection_url = "manage-profile-list.php";

error_reporting(E_ALL);

$admin_id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : (isset($_SESSION['aid']) ? intval($_SESSION['aid']) : 1);
$mode = $_REQUEST['mode'] ?? 'edit';

$err_msg = '';
$success_msg = '';

// ===================== EDIT / UPDATE PROFILE =====================
if (isset($_POST['btn_submit'])) {
    $username = addslashes(trim($_POST['username'] ?? ''));
    $email = addslashes(trim($_POST['email'] ?? ''));
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username)) {
        $err_msg = "Username cannot be empty!";
    } else {
        // Fetch current user details from DB
        $current_user = $ai_db->aiGetQueryObj("SELECT * FROM $table WHERE id='" . intval($admin_id) . "' LIMIT 1");
        
        if (empty($current_user)) {
            $err_msg = "User record not found!";
        } else {
            $user_data = $current_user[0];
            $update_password = false;
            $new_pass_hash = '';

            // If any password field is filled, validate password change
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
                        WHERE id='" . intval($admin_id) . "'";
                } else {
                    $update_qry = "UPDATE $table SET 
                        username='" . $username . "', 
                        email='" . $email . "' 
                        WHERE id='" . intval($admin_id) . "'";
                }

                $ai_db->aiQuery($update_qry);

                // If updating logged in admin session
                if ($admin_id == ($_SESSION['aid'] ?? 0)) {
                    $_SESSION['username'] = $username;
                    $_SESSION['email'] = $email;
                }

                $ai_core->aiGoPage("manage-profile-list.php?msg=2");
                exit;
            }
        }
    }
}

// Fetch Admin Details
$admin_row = $ai_db->aiGetQueryObj("SELECT * FROM $table WHERE id='" . intval($admin_id) . "' LIMIT 1");
$adminData = !empty($admin_row) ? $admin_row[0] : null;
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
                                <h5 class="mb-0">Edit Profile</h5>
                                <a href="manage-profile-list.php" class="btn btn-secondary">
                                    <i class="ti ti-arrow-left me-1"></i> Back to List
                                </a>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($err_msg)) { ?>
                                    <div class="alert alert-danger alert-dismissible" role="alert">
                                        <?= htmlspecialchars($err_msg) ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php } ?>

                                <form method="post" action="">
                                    <input type="hidden" name="id" value="<?= intval($admin_id) ?>" />

                                    <div class="row">
                                        <!-- Username -->
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label" for="username">Username <span class="text-danger">*</span></label>
                                            <input
                                                type="text"
                                                class="form-control"
                                                id="username"
                                                name="username"
                                                value="<?= htmlspecialchars($adminData->username ?? '') ?>"
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
                                                value="<?= htmlspecialchars($adminData->email ?? '') ?>"
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
        });
    </script>
</body>
</html>
