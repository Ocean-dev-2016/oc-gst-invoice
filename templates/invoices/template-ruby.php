<?php
require_once dirname(dirname(__DIR__)) . '/root/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/invoice-data-loader.php';
require_once dirname(dirname(__DIR__)) . '/includes/invoice-table-renderer.php';
require_once dirname(dirname(__DIR__)) . '/includes/invoice-footer-renderer.php';

// Helper: Convert numbers to Indian Rupees in words
if (!function_exists('getIndianCurrencyWords')) {
function getIndianCurrencyWords(float $number) {
    $decimal = round($number - ($no = floor($number)), 2) * 100;
    $hundred = null;
    $digits_length = strlen($no);
    $i = 0;
    $str = array();
    $words = array(
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty',
        30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy',
        80 => 'Eighty', 90 => 'Ninety'
    );
    $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');
    while ($i < $digits_length) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += ($divider == 10) ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? '' : '';
            $hundred = ($counter == 1 && $str[0]) ? ' and ' : '';
            $str[] = ($number < 21) ? $words[$number] . ' ' . $digits[$counter] . $plural . ' ' . $hundred
                : $words[floor($number / 10) * 10] . ' ' . $words[$number % 10] . ' ' . $digits[$counter] . $plural . ' ' . $hundred;
        } else {
            $str[] = null;
        }
    }
    $Rupees = implode('', array_reverse(array_filter($str)));
    $paise = ""; if ((int)$decimal > 0) { $d = (int)$decimal; $paise = " and " . ($d < 21 ? ($words[$d] ?? "") : (($words[(int)floor($d / 10) * 10] ?? "") . " " . ($words[$d % 10] ?? ""))) . " Paise"; }
    return ($Rupees ? $Rupees . 'Rupees ' : '') . $paise . " Only";
}
}

$isCompanyGst = (!empty($company) && isset($company->gst_applicable)) ? ((int)$company->gst_applicable === 1) : true;
$isGst = ($isCompanyGst && $q->gst_type === 'with_gst');
$hasCgstSgst = ($isGst && ($q->cgst_amount > 0 || $q->sgst_amount > 0));
$hasIgst = ($isGst && $q->igst_amount > 0);

$amountInWords = getIndianCurrencyWords(floatval($q->grand_total ?? 0));
$docType = isset($docType) ? $docType : "invoice";
$docTitle = ($docType === "quotation") ? "QUOTATION" : (($docType === "purchase") ? "PURCHASE BILL" : "TAX INVOICE");
$docNumberLabel = ($docType === "quotation") ? "Quotation No" : (($docType === "purchase") ? "Bill No" : "Invoice No");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ucfirst($docType) ?> - <?= htmlspecialchars($q->invoice_no) ?> (Royal Crimson)</title>
    <link rel="icon" type="image/x-icon" href="<?= ADMIN_URL ?>assets/img/favicon/favicon.png" />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ADMIN_URL ?>assets/vendor/fonts/tabler-icons.css" />
    
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            font-size: 13px;
            line-height: 1.5;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .top-action-bar {
            position: sticky; top: 0; z-index: 1000;
            background: #ffffff; border-bottom: 1px solid #e2e8f0;
            padding: 12px 24px; display: flex; justify-content: flex-end; align-items: center;
        }
        .action-btns { display: flex; gap: 10px; }
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 18px; font-size: 13px; font-weight: 600;
            border-radius: 6px; text-decoration: none; cursor: pointer; border: 1px solid transparent;
        }
        .btn-primary { background-color: #e11d48; color: #fff; }
        .btn-success { background-color: #881337; color: #fff; }
        .btn-secondary { background-color: #f1f5f9; color: #1e293b; }

        .invoice-card {
            max-width: 860px; min-height: unset; margin: 25px auto; background: #ffffff;
            border-radius: 12px; overflow: hidden;
            box-shadow: 0 10px 30px rgba(136, 19, 55, 0.08);
            border: 1px solid #ffe4e6;
            display: flex; flex-direction: column;
        }

        /* Top Crimson Header */
        .crimson-header {
            background: linear-gradient(135deg, #881337 0%, #be123c 100%);
            color: #ffffff; padding: 32px 40px 28px 40px;
            display: flex; justify-content: space-between; align-items: flex-start;
            position: relative;
        }
        .crimson-header::after {
            content: ''; position: absolute; bottom: 0; left: 0; right: 0;
            height: 5px; background: #fb7185;
        }
        .comp-brand-title {
            font-size: 24px; font-weight: 800; letter-spacing: -0.5px;
            color: #ffffff; margin-bottom: 4px;
        }
        .comp-brand-sub { font-size: 12.5px; color: #fecdd3; line-height: 1.4; }

        .doc-title-block { text-align: right; }
        .doc-badge-title {
            font-size: 26px; font-weight: 900; letter-spacing: 2px;
            color: #ffffff; text-transform: uppercase; margin-bottom: 6px;
        }
        .doc-meta-item { font-size: 12.5px; color: #ffe4e6; margin-bottom: 2px; }
        .doc-meta-item strong { color: #ffffff; }

        /* Parties Info Cards */
        .parties-row {
            display: flex; justify-content: space-between; gap: 24px;
            padding: 24px 35px 15px 35px;
        }
        .party-card {
            flex: 1; min-width: 0;
            background: #fff1f2; border: 1px solid #fecdd3;
            border-radius: 8px; padding: 14px 18px;
        }
        .party-card-title {
            font-size: 11px; font-weight: 800; text-transform: uppercase;
            letter-spacing: 0.5px; color: #e11d48; margin-bottom: 8px;
        }
        .party-card-name { font-size: 14px; font-weight: 700; color: #881337; margin-bottom: 4px; }
        .party-card-detail { font-size: 12px; color: #475569; line-height: 1.5; }

        @media print {
            @page { size: A4 portrait; margin: 0 !important; }
            .top-action-bar { display: none !important; }
            body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
            .invoice-card {
                box-shadow: none !important; border: none !important;
                margin: 0 !important; width: 100% !important; max-width: 100% !important;
                min-height: 297mm !important; height: 297mm !important;
                display: flex !important; flex-direction: column !important;
            }
            .invoice-footer-pin {
                margin-top: auto !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }
    </style>
</head>
<body>

    <div class="top-action-bar">
        <div class="action-btns">
            <button onclick="saveAndShowPDF()" class="btn btn-primary"><i class="ti ti-printer"></i> Print</button>
            <button onclick="downloadPDF()" id="btnDownloadPdf" class="btn btn-success"><i class="ti ti-download"></i> Save as PDF</button>
            <button onclick="window.close()" class="btn btn-secondary"><i class="ti ti-x"></i> Cancel</button>
        </div>
    </div>

    <div class="invoice-card">
        <!-- Header -->
        <div class="crimson-header">
            <div>
                <?php if (!empty($company->logo)): ?>
                    <img src="<?= SITE_URL . $company->logo ?>" alt="Company Logo" style="max-height: 50px; max-width: 180px; object-fit: contain; margin-bottom: 8px; filter: brightness(0) invert(1);">
                <?php endif; ?>
                <div class="comp-brand-title"><?= !empty($company->company_name) ? htmlspecialchars($company->company_name) : 'COMPANY NAME' ?></div>
                <div class="comp-brand-sub"><?= htmlspecialchars($company->address ?? '') ?></div>
                <?php if (!empty($company->gst_no)): ?>
                    <div class="comp-brand-sub"><strong>GSTIN:</strong> <?= htmlspecialchars($company->gst_no) ?></div>
                <?php endif; ?>
            </div>

            <div class="doc-title-block">
                <div class="doc-badge-title"><?= $docTitle ?></div>
                <div class="doc-meta-item"><?= $docNumberLabel ?>: <strong><?= htmlspecialchars($q->invoice_no) ?></strong></div>
                <div class="doc-meta-item">Date: <strong><?= date('d M Y', strtotime($q->invoice_date)) ?></strong></div>
                <?php if (!empty($q->due_date)): ?>
                    <div class="doc-meta-item">Due Date: <strong><?= date('d M Y', strtotime($q->due_date)) ?></strong></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Parties Section -->
        <div class="parties-row">
            <div class="party-card">
                <div class="party-card-title">Billed By / Supplier</div>
                <div class="party-card-name"><?= htmlspecialchars($company->company_name ?? '') ?></div>
                <div class="party-card-detail"><?= htmlspecialchars($company->address ?? '') ?></div>
                <div class="party-card-detail">Phone: <?= htmlspecialchars($company->mobile_no ?? 'N/A') ?></div>
                <?php if (!empty($company->email)): ?>
                    <div class="party-card-detail">Email: <?= htmlspecialchars($company->email) ?></div>
                <?php endif; ?>
            </div>

            <div class="party-card">
                <div class="party-card-title">Billed To / Customer</div>
                <div class="party-card-name"><?= htmlspecialchars($q->party_name ?? '') ?></div>
                <div class="party-card-detail"><?= nl2br(htmlspecialchars($q->address ?? '')) ?></div>
                <div class="party-card-detail">Phone: <?= htmlspecialchars($party->mobile_no ?? 'N/A') ?></div>
                <?php if (!empty($party->gst_no)): ?>
                    <div class="party-card-detail"><strong>GSTIN:</strong> <?= htmlspecialchars($party->gst_no) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items & Totals Block -->
        <div style="flex: 1 0 auto; padding: 0 35px;">
            <?php renderInvoiceProductTableAndTotals($items, $q, $isGst, $hasCgstSgst, $hasIgst, $amountInWords, '#e11d48'); ?>
        </div>

        <!-- Footer -->
        <div class="invoice-footer-pin" style="margin-top: auto; flex-shrink: 0; padding: 0 35px 25px 35px;">
            <?php renderStandardInvoiceFooter($company, $q, '#e11d48'); ?>
        </div>
    </div>

    <?php require_once dirname(dirname(__DIR__)) . '/includes/invoice-pdf-script.php'; ?>
</body>
</html>

