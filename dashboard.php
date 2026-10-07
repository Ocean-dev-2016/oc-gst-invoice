<?php
require_once __DIR__ . '/root/config.php';

include ('includes/header.php');

// Identify logged-in company or admin filter
$company_filter = "";
if (($_SESSION['user_type'] ?? '') === 'company' && !empty($_SESSION['company_id'])) {
    $cid = intval($_SESSION['company_id']);
    $company_filter = " AND company_id = '$cid'";
}

// Compute Dashboard Metrics
$summaryQry = "SELECT 
                    COUNT(id) AS total_customers,
                    COALESCE(SUM(CASE WHEN outstanding > 0 THEN outstanding ELSE 0 END), 0) AS total_receivable,
                    COUNT(CASE WHEN outstanding > 0 THEN 1 END) AS receivable_clients_count,
                    COALESCE(SUM(CASE WHEN credit_limit > 0 AND outstanding > credit_limit THEN (outstanding - credit_limit) ELSE 0 END), 0) AS total_overdue,
                    COUNT(CASE WHEN credit_limit > 0 AND outstanding > credit_limit THEN 1 END) AS overdue_clients_count
               FROM tbl_party 
               WHERE status = 'active' {$company_filter}";
$summaryRes = $ai_db->aiGetQueryObj($summaryQry);
$sumData = !empty($summaryRes) ? $summaryRes[0] : null;

$totalCustomers = intval($sumData->total_customers ?? 0);
$receivableAmt = floatval($sumData->total_receivable ?? 0);
$receivableClients = intval($sumData->receivable_clients_count ?? 0);
$overdueAmt = floatval($sumData->total_overdue ?? 0);
$overdueClients = intval($sumData->overdue_clients_count ?? 0);
?>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <?php include ('sidebar.php'); ?>

            <div class="layout-page">
                <?php include ('navbar.php'); ?>

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <!-- Welcome Banner -->
                        <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(72.47deg,#e77820 22.16%,#ffffff 50%,#146e29 76.47%); color: #fff;">
                            <div class="card-body py-4">
                                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                                    <div>
                                        <h3 class="mb-1 text-white">Welcome, <?= strtoupper(htmlspecialchars($_SESSION['username'])) ?></h3>
                                        <p class="mb-0 text-white">Welcome to your dashboard. Control panel is ready.</p>
                                    </div>
                                    <div>
                                        <a href="manage-party-list.php" class="btn btn-light text-primary fw-semibold me-2 shadow-sm">
                                            <i class="ti ti-users me-1"></i> View Customers
                                        </a>
                                        <a href="manage-quotation-form.php?mode=add" class="btn btn-dark fw-semibold shadow-sm">
                                            <i class="ti ti-plus me-1"></i> New Quotation
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3 Metric Cards: Customers, Receivable, Overdue -->
                        <div class="row g-4 mb-4">
                            <!-- Card 1: Total Customers -->
                            <div class="col-sm-6 col-xl-4">
                                <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); color: #fff; border-radius: 14px;">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <span class="text-uppercase fw-bold text-white-50" style="letter-spacing: 1px; font-size: 13px;">Total Customers</span>
                                                <h2 class="mb-0 mt-2 text-white fw-bold"><?= number_format($totalCustomers) ?></h2>
                                            </div>
                                            <div class="avatar p-2 rounded-circle" style="background: rgba(255, 255, 255, 0.2);">
                                                <i class="ti ti-users fs-2 text-white"></i>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center text-white-50 small">
                                            <span class="badge bg-white text-primary fw-semibold me-2 px-2 py-1">+ Active</span>
                                            <span>Active Registered Clients</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card 2: Receivable Amount -->
                            <div class="col-sm-6 col-xl-4">
                                <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: #fff; border-radius: 14px;">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <span class="text-uppercase fw-bold text-white-50" style="letter-spacing: 1px; font-size: 13px;">Total Receivable</span>
                                                <h2 class="mb-0 mt-2 text-white fw-bold">₹<?= number_format($receivableAmt, 2) ?></h2>
                                            </div>
                                            <div class="avatar p-2 rounded-circle" style="background: rgba(255, 255, 255, 0.2);">
                                                <i class="ti ti-cash fs-2 text-white"></i>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center text-white-50 small">
                                            <span class="badge bg-white text-success fw-semibold me-2 px-2 py-1"><?= $receivableClients ?> clients</span>
                                            <span>Pending to receive</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card 3: Overdue Amount -->
                            <div class="col-sm-6 col-xl-4">
                                <div class="card h-100 border-0 shadow-sm" style="background: linear-gradient(135deg, #dc2626 0%, #f87171 100%); color: #fff; border-radius: 14px;">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <span class="text-uppercase fw-bold text-white-50" style="letter-spacing: 1px; font-size: 13px;">Overdue Amount</span>
                                                <h2 class="mb-0 mt-2 text-white fw-bold">₹<?= number_format($overdueAmt, 2) ?></h2>
                                            </div>
                                            <div class="avatar p-2 rounded-circle" style="background: rgba(255, 255, 255, 0.2);">
                                                <i class="ti ti-alert-triangle fs-2 text-white"></i>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center text-white-50 small">
                                            <span class="badge bg-white text-danger fw-semibold me-2 px-2 py-1"><?= ($overdueAmt > 0) ? 'Needs follow-up' : 'All clear' ?></span>
                                            <span><?= $overdueClients ?> clients exceeded credit limit</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php include ('includes/footer.php'); ?>
                    <div class="content-backdrop fade"></div>
                </div>
            </div>
        </div>

        <div class="layout-overlay layout-menu-toggle"></div>
        <div class="drag-target"></div>
    </div>

    <?php include ('includes/footer_js.php'); ?>
</body>
</html>
