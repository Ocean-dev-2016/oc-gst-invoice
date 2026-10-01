<?php
include('includes/header.php');

$page_nm = "Company";
$pageUrl = "manage-company-list.php";
$table = "tbl_company";

ai_handle_status_toggle($table, $pageUrl, 'active', 'deactive');

$qry = "SELECT c.*, s.state_name, s.state_code, ct.city_name 
        FROM tbl_company c 
        LEFT JOIN tbl_state s ON c.state_id = s.id 
        LEFT JOIN tbl_city ct ON c.city_id = ct.id 
        ORDER BY c.id DESC";
$team = $ai_db->aiGetQueryObj($qry);
?>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <?php include('sidebar.php'); ?>

            <div class="layout-page">
                <?php include('navbar.php'); ?>

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <div class="card" id="teamTable">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?= $page_nm ?> List</h5>
                                <a href="manage-company-form.php?mode=add" class="btn btn-primary btn-lg">
                                    <i class="ti ti-plus me-1"></i> Add New
                                </a>
                            </div>

                            <div class="card-datatable">
                                <table class="datatables-ajax table table-bordered align-middle mb-0 text-center" style="font-size:15px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:40px;">#</th>
                                            <th>Company Name</th>
                                            <th>GST No</th>
                                            <th>Owner / Contact</th>
                                            <th>State & City</th>
                                            <th>Username</th>
                                            <th>Status</th>
                                            <th style="width:100px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($team) {
                                            $i = 1;
                                            foreach ($team as $cat) { ?>
                                                <tr>
                                                    <td><?= $i++ ?></td>

                                                    <!-- Company Name -->
                                                    <td class="text-start">
                                                        <strong><?= htmlspecialchars($cat->company_name) ?></strong>
                                                    </td>

                                                    <!-- GST No -->
                                                    <td>
                                                        <?php if (!empty($cat->gst_no)) { ?>
                                                            <span class="badge bg-label-primary font-monospace"><?= htmlspecialchars($cat->gst_no) ?></span>
                                                        <?php } else { ?>
                                                            <span class="text-muted">-</span>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- Owner / Contact -->
                                                    <td class="text-start">
                                                        <?php if (!empty($cat->owner_name)) { ?>
                                                            <div><i class="ti ti-user me-1 text-muted"></i><?= htmlspecialchars($cat->owner_name) ?></div>
                                                        <?php } ?>
                                                        <?php if (!empty($cat->mobile_no)) { ?>
                                                            <small class="text-muted"><i class="ti ti-phone me-1"></i><?= htmlspecialchars($cat->mobile_no) ?></small>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- State & City -->
                                                    <td class="text-start">
                                                        <?php if (!empty($cat->state_name) || !empty($cat->city_name)) { ?>
                                                            <div><?= htmlspecialchars($cat->city_name ?? '') ?><?= (!empty($cat->city_name) && !empty($cat->state_name)) ? ', ' : '' ?><?= htmlspecialchars($cat->state_name ?? '') ?></div>
                                                        <?php } else { ?>
                                                            <span class="text-muted">-</span>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- Username -->
                                                    <td>
                                                        <?php if (!empty($cat->username)) { ?>
                                                            <span class="badge bg-label-info"><?= htmlspecialchars($cat->username) ?></span>
                                                        <?php } else { ?>
                                                            <span class="text-muted">-</span>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- Status -->
                                                    <td>
                                                        <?= ai_render_status_toggle($pageUrl, $cat->id, $cat->status, 'active', 'deactive') ?>
                                                    </td>

                                                    <!-- Action -->
                                                    <td>
                                                        <div class="dropdown">
                                                            <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                                <i class="ti ti-dots-vertical fs-4"></i>
                                                            </button>
                                                            <div class="dropdown-menu dropdown-menu-end">
                                                                <a class="dropdown-item" href="manage-company-form.php?mode=edit&id=<?= $cat->id ?>">
                                                                    <i class="ti ti-pencil me-1"></i> Edit
                                                                </a>
                                                                <a class="dropdown-item text-danger" href="manage-company-form.php?mode=delete&id=<?= $cat->id ?>" onclick="return confirm('Delete this record?');">
                                                                    <i class="ti ti-trash me-1"></i> Delete
                                                                </a>
                                                            </div>
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
        $('.datatables-ajax').DataTable({
            processing: true,
            pageLength: 10,
            ordering: true,
            responsive: false,
            autoWidth: false,
            columnDefs: [{
                targets: [6, 7],
                orderable: false
            }]
        });
    </script>
</body>
</html>
