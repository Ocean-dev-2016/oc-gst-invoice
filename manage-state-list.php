<?php
include('includes/header.php');

$page_nm = "State";
$pageUrl = "manage-state-list.php";
$table = "tbl_state";

ai_handle_status_toggle($table, $pageUrl, 'active', 'deactive');

$qry = "SELECT * FROM tbl_state ORDER BY order_no ASC, id DESC";
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
                                <a href="manage-state-form.php?mode=add" class="btn btn-primary btn-lg">
                                    <i class="ti ti-plus me-1"></i> Add New
                                </a>
                            </div>

                            <div class="card-datatable">
                                <table class="datatables-ajax table table-bordered align-middle mb-0 text-center" style="font-size:15px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:40px;">#</th>
                                            <th>State Name</th>
                                            <th>GST State Code</th>
                                            <th>Order No</th>
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

                                                    <!-- State Name -->
                                                    <td class="text-start">
                                                        <strong><?= htmlspecialchars($cat->state_name) ?></strong>
                                                    </td>

                                                    <!-- GST State Code -->
                                                    <td>
                                                        <span class="badge bg-label-primary"><?= htmlspecialchars($cat->state_code) ?></span>
                                                    </td>

                                                    <!-- Order No -->
                                                    <td><?= htmlspecialchars($cat->order_no) ?></td>

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
                                                                <a class="dropdown-item" href="manage-state-form.php?mode=edit&id=<?= $cat->id ?>">
                                                                    <i class="ti ti-pencil me-1"></i> Edit
                                                                </a>
                                                                <a class="dropdown-item text-danger" href="manage-state-form.php?mode=delete&id=<?= $cat->id ?>" onclick="return confirm('Delete this record?');">
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
                targets: [4, 5],
                orderable: false
            }]
        });
    </script>
</body>
</html>
