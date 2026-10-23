<?php
include('includes/header.php');

$page_nm = "Ledger";
$pageUrl = "manage-ledger.php";

$companyWhere = "";
$userType = $_SESSION['user_type'] ?? 'admin';
$currentCompanyId = intval($_SESSION['company_id'] ?? 0);

if ($userType === 'company' && $currentCompanyId > 0) {
    $companyWhere = " AND p.company_id = '$currentCompanyId'";
}

// Fetch all parties for initial dropdown & JSON mapping
$allParties = $ai_db->aiGetQueryObj("SELECT p.id, p.party_name, p.party_status, p.company_id, c.company_name 
    FROM tbl_party p 
    LEFT JOIN tbl_company c ON p.company_id = c.id 
    WHERE p.status='active' $companyWhere 
    ORDER BY p.party_name ASC");

// Read Filter Inputs
$filter_status = trim($_GET['party_status'] ?? '');
$filter_party_id = intval($_GET['party_id'] ?? 0);
$filter_start_date = trim($_GET['start_date'] ?? '');
$filter_end_date = trim($_GET['end_date'] ?? '');

$hasSearch = (!empty($filter_party_id) || !empty($filter_start_date) || !empty($filter_end_date) || !empty($filter_status));

// If party selected, load details & transactions
$partyDetails = null;
$transactions = [];
$openingBalance = 0.00;
$openingType = 'Debit';
$runningBalance = 0.00;
$runningType = 'Debit';

if ($filter_party_id > 0) {
    $pRow = $ai_db->aiGetQueryObj("SELECT p.*, c.company_name 
        FROM tbl_party p 
        LEFT JOIN tbl_company c ON p.company_id = c.id 
        WHERE p.id='$filter_party_id' LIMIT 1");
    if (!empty($pRow)) {
        $partyDetails = $pRow[0];
        $openingBalance = floatval($partyDetails->opening_balance ?? 0);
        $openingType = $partyDetails->balance_type ?? 'Debit';
        $partyStatus = $partyDetails->party_status ?? 'Sales';

        // Set starting running balance
        // Debit = (+), Credit = (-) in standardized signed arithmetic
        // For Sales: Debit = Party owes us (+), Credit = We owe party (-)
        // For Purchase: Credit = We owe party (+ payable), Debit = Advance to party (-)
        if ($partyStatus === 'Purchase') {
            $currentSign = ($openingType === 'Credit') ? $openingBalance : -$openingBalance;
        } else {
            $currentSign = ($openingType === 'Debit') ? $openingBalance : -$openingBalance;
        }

        // Fetch Invoices / Purchases
        if ($partyStatus === 'Purchase') {
            $dateWhere = "";
            if (!empty($filter_start_date)) {
                $sDate = date('Y-m-d', strtotime($filter_start_date));
                $dateWhere .= " AND purchase_date >= '$sDate'";
            }
            if (!empty($filter_end_date)) {
                $eDate = date('Y-m-d', strtotime($filter_end_date));
                $dateWhere .= " AND purchase_date <= '$eDate'";
            }

            // 1. Purchase Bills
            $purchases = $ai_db->aiGetQueryObj("SELECT id, purchase_no AS ref_no, purchase_date AS tx_date, 
                grand_total AS debit_or_credit, 'Purchase Bill' AS tx_type, 'purchase' AS doc_type 
                FROM tbl_purchase 
                WHERE party_id='$filter_party_id' AND status='active' $dateWhere");

            // 2. Purchase Payments
            $payDateWhere = "";
            if (!empty($filter_start_date)) {
                $sDate = date('Y-m-d', strtotime($filter_start_date));
                $payDateWhere .= " AND payment_date >= '$sDate'";
            }
            if (!empty($filter_end_date)) {
                $eDate = date('Y-m-d', strtotime($filter_end_date));
                $payDateWhere .= " AND payment_date <= '$eDate'";
            }
            $payments = $ai_db->aiGetQueryObj("SELECT id, reference_no AS ref_no, payment_date AS tx_date, 
                amount AS debit_or_credit, payment_mode, 'payment' AS doc_type, notes 
                FROM tbl_purchase_payments 
                WHERE party_id='$filter_party_id' $payDateWhere");

            // Combine
            $rawList = [];
            foreach ($purchases as $pur) {
                $rawList[] = [
                    'date' => $pur->tx_date,
                    'type' => 'Purchase Bill',
                    'ref_no' => $pur->ref_no,
                    'debit' => 0.00,
                    'credit' => floatval($pur->debit_or_credit),
                    'notes' => 'Bill Total',
                    'sort_order' => 1
                ];
            }
            foreach ($payments as $pay) {
                $pModeLabel = function_exists('getPaymentModeName') ? getPaymentModeName($pay->payment_mode) : $pay->payment_mode;
                $rawList[] = [
                    'date' => $pay->tx_date,
                    'type' => 'Payment (' . $pModeLabel . ')',
                    'ref_no' => !empty($pay->ref_no) ? $pay->ref_no : ('PAY-' . $pay->id),
                    'debit' => floatval($pay->debit_or_credit),
                    'credit' => 0.00,
                    'notes' => $pay->notes ?? 'Paid to Supplier',
                    'sort_order' => 2
                ];
            }

            // Sort by date ascending
            usort($rawList, function($a, $b) {
                $t1 = strtotime($a['date']);
                $t2 = strtotime($b['date']);
                if ($t1 === $t2) {
                    return $a['sort_order'] - $b['sort_order'];
                }
                return $t1 - $t2;
            });

            // Calculate running balance
            $running = $currentSign;
            foreach ($rawList as $item) {
                // In Purchase: Credit increases payable (+), Debit payment decreases payable (-)
                $running += ($item['credit'] - $item['debit']);
                $item['balance'] = abs($running);
                $item['balance_type'] = ($running >= 0) ? 'Credit' : 'Debit';
                $transactions[] = $item;
            }
        } else {
            // Sales Party (Invoices & Receipts)
            $dateWhere = "";
            if (!empty($filter_start_date)) {
                $sDate = date('Y-m-d', strtotime($filter_start_date));
                $dateWhere .= " AND invoice_date >= '$sDate'";
            }
            if (!empty($filter_end_date)) {
                $eDate = date('Y-m-d', strtotime($filter_end_date));
                $dateWhere .= " AND invoice_date <= '$eDate'";
            }

            // 1. Invoices (Debit to customer)
            $invoices = $ai_db->aiGetQueryObj("SELECT id, invoice_no AS ref_no, invoice_date AS tx_date, 
                grand_total AS amount, 'Invoice' AS tx_type 
                FROM tbl_invoice 
                WHERE party_id='$filter_party_id' AND status='active' $dateWhere");

            // 2. Payments (Credit / payment received from customer)
            $payDateWhere = "";
            if (!empty($filter_start_date)) {
                $sDate = date('Y-m-d', strtotime($filter_start_date));
                $payDateWhere .= " AND payment_date >= '$sDate'";
            }
            if (!empty($filter_end_date)) {
                $eDate = date('Y-m-d', strtotime($filter_end_date));
                $payDateWhere .= " AND payment_date <= '$eDate'";
            }
            $payments = $ai_db->aiGetQueryObj("SELECT id, reference_no AS ref_no, payment_date AS tx_date, 
                amount, payment_mode, notes 
                FROM tbl_invoice_payments 
                WHERE party_id='$filter_party_id' $payDateWhere");

            $rawList = [];
            foreach ($invoices as $inv) {
                $rawList[] = [
                    'date' => $inv->tx_date,
                    'type' => 'Invoice',
                    'ref_no' => $inv->ref_no,
                    'debit' => floatval($inv->amount),
                    'credit' => 0.00,
                    'notes' => 'Invoice Generated',
                    'sort_order' => 1
                ];
            }
            foreach ($payments as $pay) {
                $pModeLabel = function_exists('getPaymentModeName') ? getPaymentModeName($pay->payment_mode) : $pay->payment_mode;
                $rawList[] = [
                    'date' => $pay->tx_date,
                    'type' => 'Receipt (' . $pModeLabel . ')',
                    'ref_no' => !empty($pay->ref_no) ? $pay->ref_no : ('REC-' . $pay->id),
                    'debit' => 0.00,
                    'credit' => floatval($pay->amount),
                    'notes' => $pay->notes ?? 'Payment Received',
                    'sort_order' => 2
                ];
            }

            usort($rawList, function($a, $b) {
                $t1 = strtotime($a['date']);
                $t2 = strtotime($b['date']);
                if ($t1 === $t2) {
                    return $a['sort_order'] - $b['sort_order'];
                }
                return $t1 - $t2;
            });

            $running = $currentSign;
            foreach ($rawList as $item) {
                // In Sales: Debit invoice increases receivable (+), Credit payment decreases receivable (-)
                $running += ($item['debit'] - $item['credit']);
                $item['balance'] = abs($running);
                $item['balance_type'] = ($running >= 0) ? 'Debit' : 'Credit';
                $transactions[] = $item;
            }
        }
    }
}
?>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <?php include('sidebar.php'); ?>

            <div class="layout-page">
                <?php include('navbar.php'); ?>

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">

                        <!-- Page Breadcrumb & Header -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h4 class="fw-bold mb-1">Manage Ledger</h4>
                                <span class="text-muted small">Dashboard / <strong>Ledger</strong></span>
                            </div>
                        </div>

                        <!-- FILTER CARD (Matches user screenshot: Sale Ledger / Filter) -->
                        <div class="card mb-4 border-0 shadow-sm" style="border-radius: 12px;">
                            <div class="card-header bg-white border-bottom py-3">
                                <h5 class="mb-0 fw-semibold text-dark">
                                    <i class="ti ti-report-money me-1 text-primary"></i> Party Ledger Statement
                                </h5>
                            </div>
                            <div class="card-body p-4">

                                <form method="GET" action="ledger-print.php" target="_blank" id="ledgerFilterForm">
                                    <input type="hidden" name="download" value="1">
                                    <div class="row g-3 align-items-end">
                                        <!-- PARTY STATUS -->
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold text-uppercase small text-muted">PARTY STATUS</label>
                                            <select name="party_status" id="filter_party_status" class="form-select">
                                                <option value="">Select Party Status</option>
                                                <option value="Sales" <?= ($filter_status === 'Sales') ? 'selected' : '' ?>>Sales</option>
                                                <option value="Purchase" <?= ($filter_status === 'Purchase') ? 'selected' : '' ?>>Purchase</option>
                                            </select>
                                        </div>

                                        <!-- PARTY NAME -->
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold text-uppercase small text-muted">PARTY NAME <span class="text-danger">*</span></label>
                                            <select name="party_id" id="filter_party_id" class="form-select select2" required>
                                                <option value="">Select Party Name</option>
                                                <?php foreach ($allParties as $p) { ?>
                                                    <option value="<?= $p->id ?>" 
                                                        data-status="<?= htmlspecialchars($p->party_status) ?>"
                                                        <?= ($filter_party_id == $p->id) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($p->party_name) ?> (<?= htmlspecialchars($p->party_status) ?>)
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>

                                        <!-- START DATE -->
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold text-uppercase small text-muted">START DATE</label>
                                            <input type="date" name="start_date" id="filter_start_date" class="form-control" value="<?= htmlspecialchars($filter_start_date) ?>">
                                        </div>

                                        <!-- END DATE -->
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold text-uppercase small text-muted">END DATE</label>
                                            <input type="date" name="end_date" id="filter_end_date" class="form-control" value="<?= htmlspecialchars($filter_end_date) ?>">
                                        </div>

                                        <!-- BUTTONS -->
                                        <div class="col-12 text-end mt-4">
                                            <button type="submit" class="btn text-white me-2 px-4 py-2" style="background-color: #f97316; border-color: #ea580c; border-radius: 8px;">
                                                <i class="ti ti-file-download me-1"></i> Filter & Download PDF
                                            </button>
                                            <a href="manage-ledger.php" class="btn btn-secondary px-4 py-2" style="border-radius: 8px;">
                                                <i class="ti ti-rotate me-1"></i> Reset
                                            </a>
                                        </div>
                                    </div>
                                </form>
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
            var rawParties = <?= json_encode($allParties) ?>;
            var currentPartyId = '<?= $filter_party_id ?>';

            function updatePartyDropdown(status) {
                var $partySelect = $('#filter_party_id');
                var prevVal = $partySelect.val() || currentPartyId;
                
                $partySelect.empty();
                $partySelect.append('<option value="">Select Party Name</option>');

                var hasPrevSelected = false;
                $.each(rawParties, function(idx, p) {
                    if (!status || p.party_status === status) {
                        var isSel = (String(p.id) === String(prevVal));
                        if (isSel) hasPrevSelected = true;
                        var optText = p.party_name + ' (' + p.party_status + ')';
                        var $opt = $('<option></option>')
                            .val(p.id)
                            .attr('data-status', p.party_status)
                            .text(optText);
                        if (isSel) {
                            $opt.prop('selected', true);
                        }
                        $partySelect.append($opt);
                    }
                });

                if (!hasPrevSelected && prevVal) {
                    $partySelect.val('');
                }

                if ($.fn.select2) {
                    $partySelect.trigger('change.select2');
                }
            }

            // Bind change event to Party Status
            $('#filter_party_status').on('change', function() {
                var selectedStatus = $(this).val();
                updatePartyDropdown(selectedStatus);
            });

            // Initialize on page load if status already selected
            var initialStatus = $('#filter_party_status').val();
            if (initialStatus) {
                updatePartyDropdown(initialStatus);
            }
        });
    </script>
</body>
</html>
