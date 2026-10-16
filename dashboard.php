<?php
require_once __DIR__ . '/root/config.php';

include ('includes/header.php');

// User & Company access control
$userType = $_SESSION['user_type'] ?? 'admin';
$currentCompanyId = intval($_SESSION['company_id'] ?? 0);
$companyFilter = "";
if ($userType === 'company' && $currentCompanyId > 0) {
    $companyFilter = " AND company_id = '$currentCompanyId'";
}

// 1. Total Sales (All active invoices)
$salesRow = $ai_db->aiGetQueryObj("SELECT 
    COALESCE(SUM(grand_total), 0) AS total_sales,
    COALESCE(SUM(paid_amount), 0) AS total_sales_received,
    COALESCE(SUM(cgst_amount), 0) AS total_sales_cgst,
    COALESCE(SUM(sgst_amount), 0) AS total_sales_sgst
    FROM tbl_invoice 
    WHERE status = 'active' {$companyFilter}");
$totalSales = !empty($salesRow) ? floatval($salesRow[0]->total_sales) : 0.00;
$totalSalesReceived = !empty($salesRow) ? floatval($salesRow[0]->total_sales_received) : 0.00;
$totalSalesPending = max(0, $totalSales - $totalSalesReceived);
$totalSalesCgst = !empty($salesRow) ? floatval($salesRow[0]->total_sales_cgst) : 0.00;
$totalSalesSgst = !empty($salesRow) ? floatval($salesRow[0]->total_sales_sgst) : 0.00;

// 2. Total Purchase (All active purchase bills)
$purRow = $ai_db->aiGetQueryObj("SELECT 
    COALESCE(SUM(grand_total), 0) AS total_purchase,
    COALESCE(SUM(paid_amount), 0) AS total_purchase_paid,
    COALESCE(SUM(cgst_amount), 0) AS total_pur_cgst,
    COALESCE(SUM(sgst_amount), 0) AS total_pur_sgst
    FROM tbl_purchase 
    WHERE status = 'active' {$companyFilter}");
$totalPurchase = !empty($purRow) ? floatval($purRow[0]->total_purchase) : 0.00;
$totalPurchasePaid = !empty($purRow) ? floatval($purRow[0]->total_purchase_paid) : 0.00;
// Purchase Payment Payable = total purchase - already paid
$totalPurchasePayable = max(0, $totalPurchase - $totalPurchasePaid);
$totalPurchaseCgst = !empty($purRow) ? floatval($purRow[0]->total_pur_cgst) : 0.00;
$totalPurchaseSgst = !empty($purRow) ? floatval($purRow[0]->total_pur_sgst) : 0.00;

$todayDate = date('Y-m-d');
?>

<style>
/* Clean Theme-based Dashboard Styling */
.card-metric-theme {
    border: 0;
    box-shadow: 0 2px 6px 0 rgba(47, 43, 61, 0.12), 0 0 2px 0 rgba(47, 43, 61, 0.08);
    border-radius: 0.5rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.card-metric-theme:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px 0 rgba(47, 43, 61, 0.16);
}
.stat-pill {
    padding: 12px 14px;
    border-radius: 8px;
    background: #f8f7fa;
    border: 1px solid #eaeaec;
}
.stat-pill-label {
    font-size: 11.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #8c889a;
    margin-bottom: 4px;
}
.stat-pill-val {
    font-size: 17px;
    font-weight: 700;
    line-height: 1.2;
}
.range-badge {
    background: #f8f7fa;
    border: 1px solid #dbdade;
    border-radius: 20px;
    padding: 6px 16px;
    font-size: 12.5px;
    color: #5d596c;
    display: inline-block;
}
</style>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <?php include ('sidebar.php'); ?>

            <div class="layout-page">
                <?php include ('navbar.php'); ?>

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">

                        <!-- TOP 4 METRIC CARDS (Theme Style) -->
                        <div class="row g-4 mb-4">
                            <!-- Card 1: Total Sales -->
                            <div class="col-sm-6 col-xl-3">
                                <div class="card card-metric-theme h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="fw-semibold text-muted text-uppercase small" style="letter-spacing: 0.5px;">Total Sales</span>
                                        </div>
                                        <h3 class="mb-1 fw-bold text-dark">₹<?= number_format($totalSales, 0) ?></h3>
                                        <div class="d-flex align-items-center text-muted small">
                                            <span class="badge bg-label-success me-2 px-2 py-0.5 fw-semibold"> Active</span>
                                            <span>All Invoices</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card 2: Total Purchase -->
                            <div class="col-sm-6 col-xl-3">
                                <div class="card card-metric-theme h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="fw-semibold text-muted text-uppercase small" style="letter-spacing: 0.5px;">Total Purchase</span>
                                        </div>
                                        <h3 class="mb-1 fw-bold text-dark">₹<?= number_format($totalPurchase, 0) ?></h3>
                                        <div class="d-flex align-items-center text-muted small">
                                            <span class="badge bg-label-info me-2 px-2 py-0.5 fw-semibold"> Active</span>
                                            <span>All Bills</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card 3: Total Sales Payment Received -->
                            <div class="col-sm-6 col-xl-3">
                                <div class="card card-metric-theme h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="fw-semibold text-muted text-uppercase small" style="letter-spacing: 0.5px;">Payment Received</span>
                                        </div>
                                        <h3 class="mb-1 fw-bold text-success">₹<?= number_format($totalSalesReceived, 0) ?></h3>
                                        <div class="d-flex align-items-center text-muted small">
                                            <span class="badge bg-label-success me-2 px-2 py-0.5 fw-semibold">Received</span>
                                            <span>From Customers</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card 4: Total Purchase Payment Payable -->
                            <div class="col-sm-6 col-xl-3">
                                <div class="card card-metric-theme h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="fw-semibold text-muted text-uppercase small" style="letter-spacing: 0.5px;">Payment Payable</span>
                                        </div>
                                        <h3 class="mb-1 fw-bold text-warning">₹<?= number_format($totalPurchasePayable, 0) ?></h3>
                                        <div class="d-flex align-items-center text-muted small">
                                            <span class="badge bg-label-warning me-2 px-2 py-0.5 fw-semibold">Payable</span>
                                            <span>To Suppliers</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2 OUTSTANDING SECTION CARDS (Theme Based) -->
                        <div class="row g-4 mb-4">
                            <!-- Left: Sales Outstanding -->
                            <div class="col-lg-6">
                                <div class="card card-metric-theme h-100">
                                    <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="avatar avatar-xs bg-label-primary rounded p-1" style="width:30px;height:30px;">
                                                <i class="ti ti-file-analytics fs-5"></i>
                                            </span>
                                            <h5 class="card-title mb-0 fw-bold">Sales Outstanding</h5>
                                        </div>
                                        <span class="badge bg-label-primary px-2.5 py-1">Customer Overview</span>
                                    </div>
                                    <div class="card-body pt-3">
                                        <!-- Date Filter Inputs -->
                                        <div class="row g-2 align-items-end mb-3">
                                            <div class="col-sm-5">
                                                <label class="form-label text-muted small fw-semibold mb-1">FROM DATE</label>
                                                <input type="date" id="sales_from_date" class="form-control" value="<?= $todayDate ?>">
                                            </div>
                                            <div class="col-sm-5">
                                                <label class="form-label text-muted small fw-semibold mb-1">TO DATE</label>
                                                <input type="date" id="sales_to_date" class="form-control" value="<?= $todayDate ?>">
                                            </div>
                                            <div class="col-sm-2">
                                                <button type="button" id="btnFilterSales" class="btn btn-primary w-100" title="Search">
                                                    <i class="ti ti-search fs-5"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Range Text -->
                                        <div class="text-center my-3">
                                            <span class="range-badge" id="sales_range_text">
                                                From Date :- <strong><?= date('d/m/Y') ?></strong> To Date :- <strong><?= date('d/m/Y') ?></strong>
                                            </span>
                                        </div>

                                        <!-- Metric Counts Row 1: Total Payment, Total Received, Total Pending -->
                                        <div class="row g-2 mb-2">
                                            <div class="col-4">
                                                <div class="stat-pill text-center">
                                                    <div class="stat-pill-label">Total Amount</div>
                                                    <div class="stat-pill-val text-dark" id="sales_total_payment">0</div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="stat-pill text-center">
                                                    <div class="stat-pill-label">Total Received</div>
                                                    <div class="stat-pill-val text-success" id="sales_total_received">0</div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="stat-pill text-center">
                                                    <div class="stat-pill-label">Total Pending</div>
                                                    <div class="stat-pill-val text-danger" id="sales_total_pending">0</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Metric Counts Row 2: Total SGST, Total CGST -->
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <div class="stat-pill text-center py-2">
                                                    <div class="stat-pill-label">Total SGST</div>
                                                    <div class="stat-pill-val text-dark" id="sales_total_sgst">0.00</div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="stat-pill text-center py-2">
                                                    <div class="stat-pill-label">Total CGST</div>
                                                    <div class="stat-pill-val text-dark" id="sales_total_cgst">0.00</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Purchase Outstanding -->
                            <div class="col-lg-6">
                                <div class="card card-metric-theme h-100">
                                    <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="avatar avatar-xs bg-label-info rounded p-1" style="width:30px;height:30px;">
                                                <i class="ti ti-receipt-tax fs-5"></i>
                                            </span>
                                            <h5 class="card-title mb-0 fw-bold">Purchase Outstanding</h5>
                                        </div>
                                        <span class="badge bg-label-info px-2.5 py-1">Supplier Overview</span>
                                    </div>
                                    <div class="card-body pt-3">
                                        <!-- Date Filter Inputs -->
                                        <div class="row g-2 align-items-end mb-3">
                                            <div class="col-sm-5">
                                                <label class="form-label text-muted small fw-semibold mb-1">FROM DATE</label>
                                                <input type="date" id="pur_from_date" class="form-control" value="<?= $todayDate ?>">
                                            </div>
                                            <div class="col-sm-5">
                                                <label class="form-label text-muted small fw-semibold mb-1">TO DATE</label>
                                                <input type="date" id="pur_to_date" class="form-control" value="<?= $todayDate ?>">
                                            </div>
                                            <div class="col-sm-2">
                                                <button type="button" id="btnFilterPur" class="btn btn-info w-100" title="Search">
                                                    <i class="ti ti-search fs-5"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Range Text -->
                                        <div class="text-center my-3">
                                            <span class="range-badge" id="pur_range_text">
                                                From Date :- <strong><?= date('d/m/Y') ?></strong> To Date :- <strong><?= date('d/m/Y') ?></strong>
                                            </span>
                                        </div>

                                        <!-- Metric Counts Row 1: Total Payment, Total Payable, Total Pending -->
                                        <div class="row g-2 mb-2">
                                            <div class="col-4">
                                                <div class="stat-pill text-center">
                                                    <div class="stat-pill-label">Total Bill</div>
                                                    <div class="stat-pill-val text-dark" id="pur_total_payment">0</div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="stat-pill text-center">
                                                    <div class="stat-pill-label">Total Payable</div>
                                                    <div class="stat-pill-val text-warning" id="pur_total_payable">0</div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="stat-pill text-center">
                                                    <div class="stat-pill-label">Total Pending</div>
                                                    <div class="stat-pill-val text-danger" id="pur_total_pending">0</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Metric Counts Row 2: Total SGST, Total CGST -->
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <div class="stat-pill text-center py-2">
                                                    <div class="stat-pill-label">Total SGST</div>
                                                    <div class="stat-pill-val text-dark" id="pur_total_sgst">0.00</div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="stat-pill text-center py-2">
                                                    <div class="stat-pill-label">Total CGST</div>
                                                    <div class="stat-pill-val text-dark" id="pur_total_cgst">0.00</div>
                                                </div>
                                            </div>
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

    <script>
    $(document).ready(function() {
        function formatDateDisplay(dStr) {
            if (!dStr) return '';
            var p = dStr.split('-');
            if (p.length === 3) return p[2] + '/' + p[1] + '/' + p[0];
            return dStr;
        }

        function loadSalesOutstanding() {
            var fromD = $('#sales_from_date').val();
            var toD = $('#sales_to_date').val();

            $('#sales_range_text').html('From Date :- <strong>' + formatDateDisplay(fromD) + '</strong> To Date :- <strong>' + formatDateDisplay(toD) + '</strong>');

            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                data: {
                    action: 'get_dashboard_outstanding',
                    type: 'sales',
                    from_date: fromD,
                    to_date: toD
                },
                dataType: 'json',
                success: function(res) {
                    if (res && res.status) {
                        $('#sales_total_payment').text(res.total_payment);
                        $('#sales_total_received').text(res.total_received);
                        $('#sales_total_pending').text(res.total_pending);
                        $('#sales_total_sgst').text(res.total_sgst);
                        $('#sales_total_cgst').text(res.total_cgst);
                    }
                }
            });
        }

        function loadPurchaseOutstanding() {
            var fromD = $('#pur_from_date').val();
            var toD = $('#pur_to_date').val();

            $('#pur_range_text').html('From Date :- <strong>' + formatDateDisplay(fromD) + '</strong> To Date :- <strong>' + formatDateDisplay(toD) + '</strong>');

            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                data: {
                    action: 'get_dashboard_outstanding',
                    type: 'purchase',
                    from_date: fromD,
                    to_date: toD
                },
                dataType: 'json',
                success: function(res) {
                    if (res && res.status) {
                        $('#pur_total_payment').text(res.total_payment);
                        $('#pur_total_payable').text(res.total_payable);
                        $('#pur_total_pending').text(res.total_pending);
                        $('#pur_total_sgst').text(res.total_sgst);
                        $('#pur_total_cgst').text(res.total_cgst);
                    }
                }
            });
        }

        $('#btnFilterSales').on('click', function() {
            loadSalesOutstanding();
        });

        $('#btnFilterPur').on('click', function() {
            loadPurchaseOutstanding();
        });

        // Trigger on initial page load
        loadSalesOutstanding();
        loadPurchaseOutstanding();
    });
    </script>
</body>
</html>
