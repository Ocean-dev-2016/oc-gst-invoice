<?php
include('includes/header.php');

$page_nm = "Product Details";
$pageUrl = "manage-product-list.php";
$table = "tbl_product";

ai_handle_status_toggle($table, $pageUrl, 'active', 'deactive');

$company_where = "";
if (($_SESSION['user_type'] ?? '') === 'company' && !empty($_SESSION['company_id'])) {
    $company_where = " WHERE p.company_id = '" . intval($_SESSION['company_id']) . "'";
}

$qry = "SELECT p.*, c.company_name 
        FROM tbl_product p 
        LEFT JOIN tbl_company c ON p.company_id = c.id 
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
                                <a href="manage-product-form.php?mode=add" class="btn btn-primary btn-lg">
                                    <i class="ti ti-plus me-1"></i> Add New
                                </a>
                            </div>

                            <div class="card-datatable">
                                <table class="datatables-ajax table table-bordered align-middle mb-0 text-center" style="font-size:15px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:40px;">#</th>
                                            <th>Product Name</th>
                                            <th>HSN Code</th>
                                            <th>Purchase Price</th>
                                            <th>Sales Price</th>
                                            <th>Company Name</th>
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

                                                    <!-- Product Name -->
                                                    <td class="text-start">
                                                        <strong><?= htmlspecialchars($cat->product_name) ?></strong>
                                                    </td>

                                                    <!-- HSN Code -->
                                                    <td>
                                                        <?php if (!empty($cat->hsn_code)) { ?>
                                                            <span class="badge bg-label-info"><?= htmlspecialchars($cat->hsn_code) ?></span>
                                                        <?php } else { ?>
                                                            <span class="text-muted">-</span>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- Purchase Price -->
                                                    <td class="text-end">
                                                        ₹<?= number_format((float)$cat->purchase_price, 2) ?>
                                                    </td>

                                                    <!-- Sales Price -->
                                                    <td class="text-end fw-bold text-success">
                                                        ₹<?= number_format((float)$cat->sales_price, 2) ?>
                                                    </td>

                                                    <!-- Company Name -->
                                                    <td class="text-start">
                                                        <span class="badge bg-label-primary"><?= htmlspecialchars($cat->company_name ?? 'N/A') ?></span>
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
                                                                <a class="dropdown-item" href="manage-product-form.php?mode=edit&id=<?= $cat->id ?>">
                                                                    <i class="ti ti-pencil me-1"></i> Edit
                                                                </a>
                                                                <a class="dropdown-item text-danger" href="manage-product-form.php?mode=delete&id=<?= $cat->id ?>" onclick="return confirm('Delete this record?');">
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
