<?php
include('includes/header.php');

$page_nm = "Party Details";
$pageUrl = "manage-party-list.php";
$table = "tbl_party";

ai_handle_status_toggle($table, $pageUrl, 'active', 'deactive');

$company_where = "";
if (($_SESSION['user_type'] ?? '') === 'company' && !empty($_SESSION['company_id'])) {
    $company_where = " WHERE p.company_id = '" . intval($_SESSION['company_id']) . "'";
}

$qry = "SELECT p.*, c.company_name, s.state_name, ct.city_name 
        FROM tbl_party p 
        LEFT JOIN tbl_company c ON p.company_id = c.id 
        LEFT JOIN tbl_state s ON p.state_id = s.id 
        LEFT JOIN tbl_city ct ON p.city_id = ct.id 
        $company_where
        ORDER BY p.id DESC";
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
                                <a href="manage-party-form.php?mode=add" class="btn btn-primary btn-lg">
                                    <i class="ti ti-plus me-1"></i> Add New
                                </a>
                            </div>

                            <div class="card-datatable">
                                <table class="datatables-ajax table table-bordered align-middle mb-0 text-center" style="font-size:15px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:40px;">#</th>
                                            <th>Party Name</th>
                                            <th>Company Name</th>
                                            <th>Contact / GST</th>
                                            <th>State / City</th>
                                            <th>Party Status</th>
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

                                                    <!-- Party Name -->
                                                    <td class="text-start">
                                                        <strong><?= htmlspecialchars($cat->party_name) ?></strong>
                                                    </td>

                                                    <!-- Company Name -->
                                                    <td class="text-start">
                                                        <span class="badge bg-label-primary"><?= htmlspecialchars($cat->company_name ?? 'N/A') ?></span>
                                                    </td>

                                                    <!-- Contact / GST -->
                                                    <td class="text-start">
                                                        <?php if (!empty($cat->mobile_no)) { ?>
                                                            <div><i class="ti ti-phone me-1"></i><?= htmlspecialchars($cat->mobile_no) ?></div>
                                                        <?php } ?>
                                                        <?php if (!empty($cat->gst_no)) { ?>
                                                            <div class="small text-muted"><i class="ti ti-receipt-tax me-1"></i>GST: <?= htmlspecialchars($cat->gst_no) ?></div>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- State / City -->
                                                    <td class="text-start">
                                                        <?= htmlspecialchars($cat->state_name ?? '-') ?>
                                                        <?php if (!empty($cat->city_name)) { ?>
                                                            , <strong><?= htmlspecialchars($cat->city_name) ?></strong>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- Party Status -->
                                                    <td>
                                                        <?php
                                                        $pStatus = $cat->party_status ?? 'Sales';
                                                        $badgeClass = ($pStatus === 'Sales') ? 'bg-label-success' : 'bg-label-info';
                                                        ?>
                                                        <span class="badge <?= $badgeClass ?> px-3 py-2 fs-6"><?= htmlspecialchars($pStatus) ?></span>
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
                                                                <a class="dropdown-item" href="manage-party-form.php?mode=edit&id=<?= $cat->id ?>">
                                                                    <i class="ti ti-pencil me-1"></i> Edit
                                                                </a>
                                                                <a class="dropdown-item text-danger" href="manage-party-form.php?mode=delete&id=<?= $cat->id ?>" onclick="return confirm('Delete this record?');">
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
