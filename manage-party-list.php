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

$qry = "SELECT p.*, c.company_name, c.gst_applicable, s.state_name, ct.city_name 
        FROM tbl_party p 
        LEFT JOIN tbl_company c ON p.company_id = c.id 
        LEFT JOIN tbl_state s ON p.state_id = s.id 
        LEFT JOIN tbl_city ct ON p.city_id = ct.id 
        $company_where
        ORDER BY p.id DESC";
$team = $ai_db->aiGetQueryObj($qry);

// Compute Party Metrics based on logged-in user (admin sees all, company sees their own)
$party_filter_sql = "";
$inv_company_filter_sql = "";
if (($_SESSION['user_type'] ?? '') === 'company' && !empty($_SESSION['company_id'])) {
    $cid = intval($_SESSION['company_id']);
    $party_filter_sql = " AND company_id = '$cid'";
    $inv_company_filter_sql = " AND company_id = '$cid'";
}

// 1. Total Parties Count
$totalPartiesRes = $ai_db->aiGetQueryObj("SELECT COUNT(id) AS total_count FROM tbl_party WHERE 1=1 {$party_filter_sql}");
$statTotalParties = intval($totalPartiesRes[0]->total_count ?? 0);

// 2. Total Receivable Amount (Debit Outstanding) and Count of parties having receivable amount
$receivableRes = $ai_db->aiGetQueryObj("SELECT 
    COALESCE(SUM(outstanding), 0) AS total_receivable_amt,
    COUNT(id) AS receivable_parties_count
FROM tbl_party 
WHERE 1=1 
  AND outstanding > 0 
  AND (outstanding_type = 'Debit' OR outstanding_type IS NULL OR outstanding_type = '') 
  {$party_filter_sql}");
$statReceivableAmt = floatval($receivableRes[0]->total_receivable_amt ?? 0);
$statReceivableParties = intval($receivableRes[0]->receivable_parties_count ?? 0);

// 3. Total Overdue Amount (from unpaid invoices past their due date) and Count of parties with overdue invoices
$todayDate = date('Y-m-d');
$overdueRes = $ai_db->aiGetQueryObj("SELECT 
    COALESCE(SUM(grand_total - paid_amount), 0) AS total_overdue_amt,
    COUNT(DISTINCT party_id) AS overdue_parties_count
FROM tbl_invoice 
WHERE status = 'active' 
  AND payment_status != 'Paid' 
  AND due_date IS NOT NULL 
  AND due_date < '{$todayDate}'
  {$inv_company_filter_sql}");
$statOverdueAmt = floatval($overdueRes[0]->total_overdue_amt ?? 0);
$statOverdueParties = intval($overdueRes[0]->overdue_parties_count ?? 0);
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
                            <div class="alert alerts-success alert-dismissible fade show" role="alert">
                                Party added successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } elseif (isset($_GET['msg']) && $_GET['msg'] == '2') { ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                Party updated successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } elseif (isset($_GET['msg']) && $_GET['msg'] == '3') { ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                Party deleted successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php } ?>


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
                                            <th>Business / Status</th>
                                            <th>Balance</th>
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

                                                    <!-- Contact / GST / PAN -->
                                                    <td class="text-start">
                                                        <?php if (!empty($cat->mobile_no)) { ?>
                                                            <div><i class="ti ti-phone me-1"></i><?= htmlspecialchars($cat->mobile_no) ?></div>
                                                        <?php } ?>
                                                        <?php if (!empty($cat->email)) { ?>
                                                            <div class="small"><i class="ti ti-mail me-1"></i><?= htmlspecialchars($cat->email) ?></div>
                                                        <?php } ?>
                                                        <?php if ((int)($cat->gst_applicable ?? 0) === 1 && !empty($cat->gst_no)) { ?>
                                                            <div class="small text-muted"><i class="ti ti-receipt-tax me-1"></i>GST: <?= htmlspecialchars($cat->gst_no) ?></div>
                                                        <?php } ?>
                                                        <?php if (!empty($cat->pan_no)) { ?>
                                                            <div class="small text-muted"><i class="ti ti-id me-1"></i>PAN: <?= htmlspecialchars($cat->pan_no) ?></div>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- State / City -->
                                                    <td class="text-start">
                                                        <?= htmlspecialchars($cat->state_name ?? '-') ?>
                                                        <?php if (!empty($cat->city_name)) { ?>
                                                            , <strong><?= htmlspecialchars($cat->city_name) ?></strong>
                                                        <?php } ?>
                                                    </td>

                                                    <!-- Party Status & Business Type -->
                                                    <td>
                                                        <?php
                                                        $pStatus = $cat->party_status ?? 'Sales';
                                                        $badgeClass = ($pStatus === 'Sales') ? 'bg-label-success' : 'bg-label-info';
                                                        $bType = $cat->business_type ?? 'Business';
                                                        $bTypeBadge = ($bType === 'Business') ? 'bg-label-primary' : 'bg-label-warning';
                                                        ?>
                                                        <div><span class="badge <?= $badgeClass ?> px-2 py-1 fs-6"><?= htmlspecialchars($pStatus) ?></span></div>
                                                        <div class="mt-1"><span class="badge <?= $bTypeBadge ?> px-2 py-1"><?= htmlspecialchars($bType) ?></span></div>
                                                    </td>

                                                    <!-- Balance -->
                                                    <td class="text-start small">
                                                        <div><strong>Opening:</strong> ₹<?= number_format((float)($cat->opening_balance ?? 0), 2) ?> <span class="badge <?= (($cat->balance_type ?? 'Debit') === 'Credit') ? 'bg-label-info' : 'bg-label-secondary' ?> py-0 px-1"><?= htmlspecialchars($cat->balance_type ?? 'Debit') ?></span></div>
                                                        <?php 
                                                            $outVal = (float)($cat->outstanding ?? 0);
                                                            $outType = $cat->outstanding_type ?? 'Debit';
                                                            if ($outVal > 0) {
                                                                $outColor = ($outType === 'Debit') ? 'text-danger fw-bold' : 'text-success fw-bold';
                                                                $badgeClass = ($outType === 'Debit') ? 'bg-label-danger' : 'bg-label-success';
                                                            } else {
                                                                $outColor = 'text-muted fw-bold';
                                                                $badgeClass = 'bg-label-secondary';
                                                            }
                                                        ?>
                                                        <div class="mt-1">
                                                            <strong>Outstanding:</strong> 
                                                            <span class="<?= $outColor ?>">₹<?= number_format($outVal, 2) ?></span>
                                                            <span class="badge <?= $badgeClass ?> py-0 px-1 ms-1"><?= htmlspecialchars($outType) ?></span>
                                                        </div>
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
