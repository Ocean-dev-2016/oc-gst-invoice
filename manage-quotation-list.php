<?php
include('includes/header.php');

$page_nm = "Quotation";
$pageUrl = "manage-quotation-list.php";
$table = "tbl_quotation";

// Ensure tables exist
$ai_db->aiQuery("CREATE TABLE IF NOT EXISTS `tbl_quotation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT 0,
  `party_id` int(11) DEFAULT 0,
  `party_name` varchar(255) DEFAULT '',
  `gst_no` varchar(50) DEFAULT '',
  `quotation_no` varchar(50) DEFAULT '',
  `rca` varchar(10) DEFAULT 'No',
  `address` text DEFAULT NULL,
  `state_id` int(11) DEFAULT 0,
  `state_name` varchar(100) DEFAULT '',
  `city_id` int(11) DEFAULT 0,
  `city_name` varchar(100) DEFAULT '',
  `quotation_date` date DEFAULT NULL,
  `gst_type` varchar(20) DEFAULT 'with_gst',
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `cgst_amount` decimal(12,2) DEFAULT 0.00,
  `sgst_amount` decimal(12,2) DEFAULT 0.00,
  `igst_amount` decimal(12,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `grand_total` decimal(12,2) DEFAULT 0.00,
  `status` varchar(20) DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$ai_db->aiQuery("CREATE TABLE IF NOT EXISTS `tbl_quotation_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `hsn_code` varchar(50) DEFAULT '',
  `rate` decimal(12,2) DEFAULT 0.00,
  `gst_percent` decimal(5,2) DEFAULT 0.00,
  `qty` decimal(10,2) DEFAULT 1.00,
  `net_amount` decimal(12,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Handle status toggle
ai_handle_status_toggle($table, $pageUrl, 'active', 'deactive');

// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $del_id = intval($_GET['id'] ?? 0);
    if ($del_id > 0) {
        $ai_db->aiQuery("DELETE FROM tbl_quotation WHERE id='$del_id'");
        $ai_db->aiQuery("DELETE FROM tbl_quotation_items WHERE quotation_id='$del_id'");
        $ai_core->aiGoPage($pageUrl . '?msg=3');
        exit;
    }
}

$company_where = "";
if (($_SESSION['user_type'] ?? '') === 'company' && !empty($_SESSION['company_id'])) {
    $company_where = " WHERE q.company_id = '" . intval($_SESSION['company_id']) . "'";
}

$qry = "SELECT q.*, c.company_name 
        FROM tbl_quotation q 
        LEFT JOIN tbl_company c ON q.company_id = c.id 
        $company_where
        ORDER BY q.id DESC";
$quotations = $ai_db->aiGetQueryObj($qry);
?>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <?php include('sidebar.php'); ?>

            <div class="layout-page">
                <?php include('navbar.php'); ?>

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">

                        <!-- Notification messages -->
                        <?php if (isset($_GET['msg']) && $_GET['msg'] == '1') { ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                Quotation added successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } elseif (isset($_GET['msg']) && $_GET['msg'] == '2') { ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                Quotation updated successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } elseif (isset($_GET['msg']) && $_GET['msg'] == '3') { ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                Quotation deleted successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } ?>

                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?= $page_nm ?> List</h5>
                                <a href="manage-quotation-form.php?mode=add" class="btn btn-primary btn-lg">
                                    <i class="ti ti-plus me-1"></i> Add New Quotation
                                </a>
                            </div>

                            <div class="card-datatable table-responsive">
                                <table class="datatables-ajax table table-bordered align-middle mb-0 text-center" style="font-size:15px;">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:40px;">#</th>
                                            <th>Quotation No.</th>
                                            <th>Date</th>
                                            <th>Party Name</th>
                                            <th>GST No. / State</th>
                                            <th>GST Type</th>
                                            <th>Grand Total</th>
                                            <th>Status</th>
                                            <th style="width:120px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($quotations) {
                                            $i = 1;
                                            foreach ($quotations as $q) { ?>
                                                <tr>
                                                    <td><?= $i++ ?></td>

                                                    <!-- Quotation No -->
                                                    <td class="text-start">
                                                        <span class="badge bg-label-dark font-monospace fs-6"><?= htmlspecialchars($q->quotation_no) ?></span>
                                                    </td>

                                                    <!-- Date -->
                                                    <td>
                                                        <?= !empty($q->quotation_date) ? date('d/m/Y', strtotime($q->quotation_date)) : '-' ?>
                                                    </td>

                                                    <!-- Party Name -->
                                                    <td class="text-start">
                                                        <strong><?= htmlspecialchars($q->party_name) ?></strong>
                                                        <?php if (!empty($q->company_name)) { ?>
                                                            <div class="small text-muted"><?= htmlspecialchars($q->company_name) ?></div>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- GST No / State -->
                                                    <td class="text-start">
                                                        <?php if (!empty($q->gst_no)) { ?>
                                                            <div><i class="ti ti-receipt-tax me-1"></i><?= htmlspecialchars($q->gst_no) ?></div>
                                                        <?php } ?>
                                                        <div class="small text-muted"><i class="ti ti-map-pin me-1"></i><?= htmlspecialchars($q->state_name ?? '-') ?></div>
                                                    </td>

                                                    <!-- GST Type -->
                                                    <td>
                                                        <?php if ($q->gst_type === 'without_gst') { ?>
                                                            <span class="badge bg-label-warning px-3 py-2">Without GST</span>
                                                        <?php } else { ?>
                                                            <span class="badge bg-label-info px-3 py-2">With GST</span>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- Grand Total -->
                                                    <td class="fw-bold text-success">
                                                        ₹<?= number_format($q->grand_total, 2) ?>
                                                    </td>

                                                    <!-- Status -->
                                                    <td>
                                                        <?= ai_render_status_toggle($pageUrl, $q->id, $q->status) ?>
                                                    </td>

                                                    <!-- Action -->
                                                    <td>
                                                        <div class="d-inline-flex gap-1">
                                                            <a href="manage-quotation-form.php?mode=edit&id=<?= $q->id ?>" class="btn btn-sm btn-icon btn-label-primary" title="Edit">
                                                                <i class="ti ti-pencil"></i>
                                                            </a>
                                                            <a href="javascript:void(0);" onclick="if(confirm('Are you sure you want to delete this quotation?')){ window.location.href='manage-quotation-list.php?action=delete&id=<?= $q->id ?>'; }" class="btn btn-sm btn-icon btn-label-danger" title="Delete">
                                                                <i class="ti ti-trash"></i>
                                                            </a>
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
        $(document).ready(function() {
            $('.datatables-ajax').DataTable({
                processing: true,
                pageLength: 10,
                ordering: true,
                responsive: false,
                autoWidth: false,
                columnDefs: [{
                    targets: [7, 8],
                    orderable: false
                }]
            });
        });
    </script>
</body>
</html>
