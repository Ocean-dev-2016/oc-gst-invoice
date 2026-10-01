<?php
include('includes/header.php');

$page_nm = "Manage Profile";
$pageUrl = "manage-profile-list.php";
$table = "tbl_admin";

ai_handle_status_toggle($table, $pageUrl, '1', '0');

$qry = "SELECT * FROM $table ORDER BY id ASC";
$admins = $ai_db->aiGetQueryObj($qry);
$msg = $_GET['msg'] ?? '';
?>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <?php include('sidebar.php'); ?>

            <div class="layout-page">
                <?php include('navbar.php'); ?>

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        
                        <?php if ($msg == '2') { ?>
                            <div class="alert alert-success alert-dismissible" role="alert">
                                Profile updated successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } ?>

                        <div class="card" id="profileTable">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?= $page_nm ?> List</h5>
                                <a href="manage-profile-form.php?mode=edit&id=<?= $_SESSION['aid'] ?? 1 ?>" class="btn btn-primary">
                                    <i class="ti ti-edit me-1"></i> Edit My Profile
                                </a>
                            </div>

                            <div class="card-datatable">
                                <table class="datatables-ajax table table-bordered align-middle mb-0 text-center" style="font-size:15px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:50px;">#</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Created At</th>
                                            <th style="width:120px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($admins) {
                                            $i = 1;
                                            foreach ($admins as $admin) { ?>
                                                <tr>
                                                    <td><?= $i++ ?></td>
                                                    <td class="text-start">
                                                        <strong><?= htmlspecialchars($admin->username) ?></strong>
                                                        <?php if ($admin->id == ($_SESSION['aid'] ?? 0)) { ?>
                                                            <span class="badge bg-label-info ms-1">You</span>
                                                        <?php } ?>
                                                    </td>
                                                    <td class="text-start"><?= htmlspecialchars($admin->email) ?></td>
                                                    <td>
                                                        <?php if ($admin->is_active == '1') { ?>
                                                            <span class="badge bg-label-success">Active</span>
                                                        <?php } else { ?>
                                                            <span class="badge bg-label-danger">Inactive</span>
                                                        <?php } ?>
                                                    </td>
                                                    <td><?= date('d-m-Y H:i', strtotime($admin->created_at ?? 'now')) ?></td>
                                                    <td>
                                                        <a href="manage-profile-form.php?mode=edit&id=<?= $admin->id ?>" 
                                                           class="btn btn-sm btn-icon btn-primary" 
                                                           title="Edit Profile">
                                                            <i class="ti ti-pencil"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php }
                                        } else { ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">No records found</td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <?php include('includes/footer.php'); ?>
                </div>
            </div>
        </div>
    </div>

    <?php include('includes/footer_js.php'); ?>
</body>
</html>
