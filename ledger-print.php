<?php
require_once __DIR__ . '/root/config.php';

// Check login
if (empty($_SESSION['aid'])) {
    header('Location: index.php');
    exit;
}

$party_id = isset($_GET['party_id']) ? intval($_GET['party_id']) : 0;
if ($party_id <= 0) {
    die("Invalid Party ID!");
}

$pRow = $ai_db->aiGetQueryObj("SELECT p.*, c.company_name, c.address AS comp_address, c.mobile_no AS comp_mobile, c.email AS comp_email, c.gst_no AS comp_gst
    FROM tbl_party p 
    LEFT JOIN tbl_company c ON p.company_id = c.id 
    WHERE p.id='$party_id' LIMIT 1");

if (empty($pRow)) {
    die("Party not found!");
}
$party = $pRow[0];

$filter_start_date = trim($_GET['start_date'] ?? '');
$filter_end_date = trim($_GET['end_date'] ?? '');

$openingBalance = floatval($party->opening_balance ?? 0);
$openingType = $party->balance_type ?? 'Debit';
$partyStatus = $party->party_status ?? 'Sales';

// Set starting running balance
if ($partyStatus === 'Purchase') {
    $currentSign = ($openingType === 'Credit') ? $openingBalance : -$openingBalance;

    $dateWhere = "";
    if (!empty($filter_start_date)) {
        $sDate = date('Y-m-d', strtotime($filter_start_date));
        $dateWhere .= " AND purchase_date >= '$sDate'";
    }
    if (!empty($filter_end_date)) {
        $eDate = date('Y-m-d', strtotime($filter_end_date));
        $dateWhere .= " AND purchase_date <= '$eDate'";
    }
    $purchases = $ai_db->aiGetQueryObj("SELECT id, purchase_no AS ref_no, purchase_date AS tx_date, grand_total AS debit_or_credit 
        FROM tbl_purchase 
        WHERE party_id='$party_id' AND status='active' $dateWhere");

    $payDateWhere = "";
    if (!empty($filter_start_date)) {
        $sDate = date('Y-m-d', strtotime($filter_start_date));
        $payDateWhere .= " AND payment_date >= '$sDate'";
    }
    if (!empty($filter_end_date)) {
        $eDate = date('Y-m-d', strtotime($filter_end_date));
        $payDateWhere .= " AND payment_date <= '$eDate'";
    }
    $payments = $ai_db->aiGetQueryObj("SELECT id, reference_no AS ref_no, payment_date AS tx_date, amount AS debit_or_credit, payment_mode, notes 
        FROM tbl_purchase_payments 
        WHERE party_id='$party_id' $payDateWhere");

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
            'notes' => $pay->notes ?? 'Paid',
            'sort_order' => 2
        ];
    }

    usort($rawList, function($a, $b) {
        $t1 = strtotime($a['date']);
        $t2 = strtotime($b['date']);
        if ($t1 === $t2) return $a['sort_order'] - $b['sort_order'];
        return $t1 - $t2;
    });

    $running = $currentSign;
    $transactions = [];
    foreach ($rawList as $item) {
        $running += ($item['credit'] - $item['debit']);
        $item['balance'] = abs($running);
        $item['balance_type'] = ($running >= 0) ? 'Credit' : 'Debit';
        $transactions[] = $item;
    }
} else {
    // Sales Party
    $currentSign = ($openingType === 'Debit') ? $openingBalance : -$openingBalance;

    $dateWhere = "";
    if (!empty($filter_start_date)) {
        $sDate = date('Y-m-d', strtotime($filter_start_date));
        $dateWhere .= " AND invoice_date >= '$sDate'";
    }
    if (!empty($filter_end_date)) {
        $eDate = date('Y-m-d', strtotime($filter_end_date));
        $dateWhere .= " AND invoice_date <= '$eDate'";
    }
    $invoices = $ai_db->aiGetQueryObj("SELECT id, invoice_no AS ref_no, invoice_date AS tx_date, grand_total AS amount 
        FROM tbl_invoice 
        WHERE party_id='$party_id' AND status='active' $dateWhere");

    $payDateWhere = "";
    if (!empty($filter_start_date)) {
        $sDate = date('Y-m-d', strtotime($filter_start_date));
        $payDateWhere .= " AND payment_date >= '$sDate'";
    }
    if (!empty($filter_end_date)) {
        $eDate = date('Y-m-d', strtotime($filter_end_date));
        $payDateWhere .= " AND payment_date <= '$eDate'";
    }
    $payments = $ai_db->aiGetQueryObj("SELECT id, reference_no AS ref_no, payment_date AS tx_date, amount, payment_mode, notes 
        FROM tbl_invoice_payments 
        WHERE party_id='$party_id' $payDateWhere");

    $rawList = [];
    foreach ($invoices as $inv) {
        $rawList[] = [
            'date' => $inv->tx_date,
            'type' => 'Invoice',
            'ref_no' => $inv->ref_no,
            'debit' => floatval($inv->amount),
            'credit' => 0.00,
            'notes' => 'Sales Invoice',
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
            'notes' => $pay->notes ?? 'Receipt',
            'sort_order' => 2
        ];
    }

    usort($rawList, function($a, $b) {
        $t1 = strtotime($a['date']);
        $t2 = strtotime($b['date']);
        if ($t1 === $t2) return $a['sort_order'] - $b['sort_order'];
        return $t1 - $t2;
    });

    $running = $currentSign;
    $transactions = [];
    foreach ($rawList as $item) {
        $running += ($item['debit'] - $item['credit']);
        $item['balance'] = abs($running);
        $item['balance_type'] = ($running >= 0) ? 'Debit' : 'Credit';
        $transactions[] = $item;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ledger_Statement_<?= preg_replace('/[^a-zA-Z0-9_\-]/', '_', $party->party_name) ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css" />
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Public Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            font-size: 13px;
            line-height: 1.5;
        }

        .action-bar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 24px;
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid transparent;
            text-decoration: none;
        }
        .btn-primary { background: #f97316; color: #fff; border-color: #ea580c; }
        .btn-primary:hover { background: #ea580c; }
        .btn-secondary { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
        .btn-secondary:hover { background: #e2e8f0; }

        .ledger-container {
            max-width: 900px;
            margin: 24px auto 40px;
            padding: 0 15px;
        }
        .ledger-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 32px 36px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid #f97316;
            padding-bottom: 18px;
            margin-bottom: 20px;
        }
        .company-info h2 {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .company-info p {
            color: #64748b;
            font-size: 12px;
            margin-bottom: 2px;
        }
        .title-block {
            text-align: right;
        }
        .title-block h1 {
            font-size: 22px;
            font-weight: 800;
            color: #ea580c;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .title-block p {
            font-size: 12px;
            color: #64748b;
        }

        .party-summary-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .party-name {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .party-detail {
            color: #475569;
            font-size: 12px;
            margin-bottom: 2px;
        }
        .balance-box {
            text-align: right;
            border-left: 1px solid #e2e8f0;
            padding-left: 16px;
        }
        .balance-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 600;
        }
        .balance-amount {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 2px;
        }

        table.ledger-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 20px;
        }
        table.ledger-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 600;
            padding: 9px 10px;
            text-align: center;
            border: 1px solid #cbd5e1;
            font-size: 11.5px;
            text-transform: uppercase;
        }
        table.ledger-table td {
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .text-start { text-align: left !important; }
        .text-center { text-align: center !important; }
        .text-end { text-align: right !important; }
        .text-danger { color: #dc2626 !important; }
        .text-success { color: #16a34a !important; }
        .fw-bold { font-weight: 700 !important; }
        .fw-semibold { font-weight: 600 !important; }

        .footer-sign {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 36px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .sign-box {
            text-align: center;
            min-width: 180px;
        }
        .sign-line {
            border-bottom: 1px solid #94a3b8;
            margin-bottom: 6px;
        }

        @media print {
            body { background: #fff !important; }
            .action-bar { display: none !important; }
            .ledger-container { max-width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .ledger-card { border: none !important; box-shadow: none !important; padding: 0 !important; }
        }
    </style>
</head>
<body>

    <div class="action-bar">
        <a href="manage-ledger.php?party_status=<?= urlencode($partyStatus) ?>&party_id=<?= $party_id ?>" class="btn btn-secondary">
            <i class="ti ti-arrow-left"></i> Back to Ledger
        </a>
        <div style="display: flex; gap: 8px;">
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="ti ti-printer"></i> Print Statement
            </button>
            <button id="btnDownloadPdf" onclick="downloadPDF()" class="btn btn-primary">
                <i class="ti ti-download"></i> Download PDF
            </button>
        </div>
    </div>

    <div class="ledger-container">
        <div class="ledger-card" id="ledgerPrintContent">

            <!-- HEADER -->
            <div class="header-section">
                <div class="company-info">
                    <h2><?= htmlspecialchars(!empty($party->company_name) ? $party->company_name : 'COMPANY STATEMENT') ?></h2>
                    <?php if (!empty($party->comp_address)) { ?><p><?= htmlspecialchars($party->comp_address) ?></p><?php } ?>
                    <?php if (!empty($party->comp_mobile)) { ?><p>Mobile: <?= htmlspecialchars($party->comp_mobile) ?></p><?php } ?>
                    <?php if (!empty($party->comp_gst)) { ?><p>GSTIN: <?= htmlspecialchars($party->comp_gst) ?></p><?php } ?>
                </div>
                <div class="title-block">
                    <h1>LEDGER STATEMENT</h1>
                    <p>Period: <?= !empty($filter_start_date) ? date('d-m-Y', strtotime($filter_start_date)) : 'Start' ?> to <?= !empty($filter_end_date) ? date('d-m-Y', strtotime($filter_end_date)) : date('d-m-Y') ?></p>
                    <p>Date of Issue: <?= date('d-m-Y h:i A') ?></p>
                </div>
            </div>

            <!-- PARTY SUMMARY -->
            <div class="party-summary-grid">
                <div>
                    <div class="party-name"><?= htmlspecialchars($party->party_name) ?></div>
                    <div class="party-detail"><strong>Category:</strong> <?= htmlspecialchars($partyStatus) ?> Party</div>
                    <?php if (!empty($party->mobile_no)) { ?><div class="party-detail"><strong>Mobile:</strong> <?= htmlspecialchars($party->mobile_no) ?></div><?php } ?>
                    <?php if (!empty($party->gst_no)) { ?><div class="party-detail"><strong>GST No:</strong> <?= htmlspecialchars($party->gst_no) ?></div><?php } ?>
                    <?php if (!empty($party->address)) { ?><div class="party-detail"><strong>Address:</strong> <?= htmlspecialchars($party->address) ?></div><?php } ?>
                </div>
                <div class="balance-box">
                    <div class="balance-label">Opening Balance</div>
                    <div class="balance-amount">₹<?= number_format($openingBalance, 2) ?> <span style="font-size:13px; color:#64748b;">(<?= htmlspecialchars($openingType) ?>)</span></div>
                    
                    <div class="balance-label" style="margin-top: 10px;">Closing Outstanding</div>
                    <div class="balance-amount" style="color: <?= (($party->outstanding_type ?? 'Debit') === 'Debit') ? '#dc2626' : '#16a34a' ?>;">
                        ₹<?= number_format(floatval($party->outstanding ?? 0), 2) ?> <span style="font-size:13px;">(<?= htmlspecialchars($party->outstanding_type ?? 'Debit') ?>)</span>
                    </div>
                </div>
            </div>

            <!-- TABLE -->
            <table class="ledger-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 12%;">Date</th>
                        <th style="width: 18%;">Particulars / Type</th>
                        <th style="width: 18%;">Ref / Voucher No.</th>
                        <th style="width: 15%;" class="text-end">Debit (₹)</th>
                        <th style="width: 15%;" class="text-end">Credit (₹)</th>
                        <th style="width: 17%;" class="text-end">Balance (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Opening Row -->
                    <tr style="background:#f8fafc; font-weight:600;">
                        <td class="text-center">-</td>
                        <td class="text-center"><?= !empty($filter_start_date) ? date('d-m-Y', strtotime($filter_start_date)) : '-' ?></td>
                        <td class="text-start" colspan="2">Opening Balance B/F</td>
                        <td class="text-end"><?= ($openingType === 'Debit') ? number_format($openingBalance, 2) : '-' ?></td>
                        <td class="text-end"><?= ($openingType === 'Credit') ? number_format($openingBalance, 2) : '-' ?></td>
                        <td class="text-end">₹<?= number_format($openingBalance, 2) ?> (<?= htmlspecialchars($openingType) ?>)</td>
                    </tr>

                    <?php 
                    $totDebit = ($openingType === 'Debit') ? $openingBalance : 0.00;
                    $totCredit = ($openingType === 'Credit') ? $openingBalance : 0.00;
                    $idx = 1;

                    if (!empty($transactions)) {
                        foreach ($transactions as $tx) {
                            $totDebit += $tx['debit'];
                            $totCredit += $tx['credit'];
                    ?>
                        <tr>
                            <td class="text-center"><?= $idx++ ?></td>
                            <td class="text-center"><?= date('d-m-Y', strtotime($tx['date'])) ?></td>
                            <td class="text-start">
                                <strong><?= htmlspecialchars($tx['type']) ?></strong>
                                <?php if (!empty($tx['notes'])) { ?>
                                    <div style="font-size: 10.5px; color: #64748b;"><?= htmlspecialchars($tx['notes']) ?></div>
                                <?php } ?>
                            </td>
                            <td class="text-center font-monospace" style="font-weight:600;"><?= htmlspecialchars($tx['ref_no']) ?></td>
                            <td class="text-end text-danger"><?= ($tx['debit'] > 0) ? number_format($tx['debit'], 2) : '-' ?></td>
                            <td class="text-end text-success"><?= ($tx['credit'] > 0) ? number_format($tx['credit'], 2) : '-' ?></td>
                            <td class="text-end fw-bold">₹<?= number_format($tx['balance'], 2) ?> (<?= htmlspecialchars($tx['balance_type']) ?>)</td>
                        </tr>
                    <?php 
                        }
                    } else { ?>
                        <tr>
                            <td colspan="7" class="text-center" style="padding: 24px; color: #94a3b8;">No transactions found for the specified period.</td>
                        </tr>
                    <?php } ?>

                    <!-- TOTALS ROW -->
                    <tr style="background:#f1f5f9; font-weight:700; border-top: 2px solid #cbd5e1;">
                        <td colspan="4" class="text-end" style="text-transform:uppercase;">Total</td>
                        <td class="text-end text-danger">₹<?= number_format($totDebit, 2) ?></td>
                        <td class="text-end text-success">₹<?= number_format($totCredit, 2) ?></td>
                        <td class="text-end" style="color: #0f172a;">
                            Closing: ₹<?= number_format(floatval($party->outstanding ?? 0), 2) ?> (<?= htmlspecialchars($party->outstanding_type ?? 'Debit') ?>)
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- SIGNATURE -->
            <div class="footer-sign">
                <div style="font-size:11px; color:#94a3b8;">
                    This is a computer-generated ledger statement and does not require a physical signature.
                </div>
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <div style="font-size:11px; font-weight:600; color:#334155;"><?= htmlspecialchars(!empty($party->company_name) ? $party->company_name : 'Authorized Signatory') ?></div>
                    <div style="font-size:10px; color:#64748b;">Authorized Signatory</div>
                </div>
            </div>

        </div>
    </div>

    <!-- html2pdf for direct PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadPDF() {
            var btn = document.getElementById('btnDownloadPdf');
            var originalText = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="ti ti-loader"></i> Generating PDF...';
            }

            var element = document.getElementById('ledgerPrintContent');
            var partyName = <?= json_encode(preg_replace('/[^a-zA-Z0-9_\-]/', '_', $party->party_name)) ?>;
            var fileName = 'Ledger_' + partyName + '_' + (new Date().toISOString().slice(0, 10)) + '.pdf';

            var opt = {
                margin:       [8, 8, 8, 8],
                filename:     fileName,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, logging: false },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save().then(function() {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            }).catch(function(err) {
                console.error('PDF error:', err);
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
                window.print();
            });
        }

        window.addEventListener('load', function() {
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('download') === '1') {
                setTimeout(downloadPDF, 400);
            } else if (urlParams.get('print') === '1') {
                window.print();
            }
        });
    </script>
</body>
</html>
