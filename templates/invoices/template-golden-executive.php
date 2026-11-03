<?php
require_once dirname(dirname(__DIR__)) . '/root/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/invoice-data-loader.php';
require_once dirname(dirname(__DIR__)) . '/includes/invoice-table-renderer.php';
require_once dirname(dirname(__DIR__)) . '/includes/invoice-footer-renderer.php';

// Helper: Convert numbers to Indian Rupees in words
if (!function_exists('getIndianCurrencyWords')) {
    function getIndianCurrencyWords(float $number)
    {
        $decimal = (int)round($number - ($no = floor($number)), 2) * 100;
        $no = (int)$no;
        $hundred = null;
        $digits_length = strlen((string)$no);
        $i = 0;
        $str = array();
        $words = array(
            0 => '',
            1 => 'One',
            2 => 'Two',
            3 => 'Three',
            4 => 'Four',
            5 => 'Five',
            6 => 'Six',
            7 => 'Seven',
            8 => 'Eight',
            9 => 'Nine',
            10 => 'Ten',
            11 => 'Eleven',
            12 => 'Twelve',
            13 => 'Thirteen',
            14 => 'Fourteen',
            15 => 'Fifteen',
            16 => 'Sixteen',
            17 => 'Seventeen',
            18 => 'Eighteen',
            19 => 'Nineteen',
            20 => 'Twenty',
            30 => 'Thirty',
            40 => 'Forty',
            50 => 'Fifty',
            60 => 'Sixty',
            70 => 'Seventy',
            80 => 'Eighty',
            90 => 'Ninety'
        );
        $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');
        while ($i < $digits_length) {
            $divider = ($i == 2) ? 10 : 100;
            $number = (int)floor($no % $divider);
            $no = (int)floor($no / $divider);
            $i += ($divider == 10) ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? '' : '';
                $hundred = ($counter == 1 && $str[0]) ? ' and ' : '';
                $str[] = ($number < 21) ? ($words[$number] ?? '') . ' ' . ($digits[$counter] ?? '') . $plural . ' ' . $hundred
                    : ($words[(int)floor($number / 10) * 10] ?? '') . ' ' . ($words[$number % 10] ?? '') . ' ' . ($digits[$counter] ?? '') . $plural . ' ' . $hundred;
            } else {
                $str[] = null;
            }
        }
        $Rupees = implode('', array_reverse(array_filter($str)));
        $paise = '';
        if ($decimal > 0) {
            if ($decimal < 21) {
                $paise = " and " . ($words[$decimal] ?? '') . ' Paise';
            } else {
                $paise = " and " . ($words[(int)floor($decimal / 10) * 10] ?? '') . " " . ($words[$decimal % 10] ?? '') . ' Paise';
            }
        }
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
    <title><?= ucfirst($docType) ?> - <?= htmlspecialchars($q->invoice_no) ?> (Golden Executive)</title>
    <link rel="icon" type="image/x-icon" href="<?= ADMIN_URL ?>assets/img/favicon/favicon.png" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ADMIN_URL ?>assets/vendor/fonts/tabler-icons.css" />

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #262626;
            color: #1e1e1e;
            font-size: 13px;
            line-height: 1.5;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .top-action-bar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 24px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }

        .action-btns {
            display: flex;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            font-size: 13px;
            font-weight: 700;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .btn-primary {
            background-color: #e5ad45;
            color: #1e1e1e;
        }

        .btn-success {
            background-color: #1e1e1e;
            color: #fff;
        }

        .btn-secondary {
            background-color: #f1f5f9;
            color: #1e1e1e;
        }

        .invoice-card {
            max-width: 860px;
            min-height: 1120px;
            margin: 0 auto;
            background: #ffffff;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        /* Top Header Split */
        .header-split {
            display: flex;
            position: relative;
            background: #1a1a1a;
        }

        .header-dark {
            flex: 1;
            padding: 30px 35px 25px 35px;
            color: #ffffff;
        }

        .header-gold {
            width: 320px;
            background: #e5ad45;
            padding: 25px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .inv-main-title {
            font-size: 38px;
            font-weight: 900;
            letter-spacing: 3px;
            color: #ffffff;
            line-height: 1;
            margin-bottom: 12px;
        }

        .inv-title-line {
            width: 220px;
            height: 3px;
            background: #ffffff;
            margin-bottom: 20px;
        }

        .header-meta-table {
            font-size: 12.5px;
            color: #a3a3a3;
        }

        .header-meta-table td {
            padding: 3px 0;
        }

        .header-meta-table td.label {
            width: 140px;
        }

        .header-meta-table td.colon {
            width: 15px;
        }

        .header-meta-table td.val {
            color: #ffffff;
            font-weight: 600;
        }

        .company-gold-brand h3 {
            font-size: 22px;
            font-weight: 900;
            color: #1a1a1a;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 6px;
        }

        /* Two Column Information */
        .info-section {
            padding: 20px 25px 12px 25px;
            display: flex;
            justify-content: space-between;
            gap: 30px;
        }

        .info-section>div {
            flex: 1;
            min-width: 0;
        }

        .info-col-title {
            font-size: 14px;
            font-weight: 800;
            color: #1a1a1a;
            margin-bottom: 14px;
            text-transform: capitalize;
        }

        .info-table {
            width: 100%;
            font-size: 12px;
        }

        .info-table td {
            padding: 3px 0;
            vertical-align: top;
        }

        .info-table td.label {
            width: 110px;
            color: #1a1a1a;
            font-weight: 600;
        }

        .info-table td.colon {
            width: 15px;
            color: #1a1a1a;
        }

        .info-table td.val {
            color: #525252;
        }

        /* Items Table */
        .items-wrapper {
            padding: 0 25px;
            margin-bottom: 0px;
        }

        .gold-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #e5ad45;
        }

        .gold-table th {
            background: #e5ad45;
            color: #1a1a1a;
            font-size: 12px;
            font-weight: 800;
            padding: 10px 12px;
            border: 1.5px solid #e5ad45;
            text-align: center;
        }

        .gold-table td {
            padding: 10px 12px;
            font-size: 12px;
            border: 1.5px solid #e5ad45;
            color: #1a1a1a;
        }

        .gold-table td.desc {
            text-align: left;
            font-weight: 500;
        }

        .gold-table td.center {
            text-align: center;
        }

        .gold-table td.right {
            text-align: right;
        }

        /* Total Strip */
        .total-strip {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #e5ad45;
            padding: 12px 45px;
            margin: 15px 45px 30px 45px;
            font-weight: 900;
            font-size: 15px;
            color: #1a1a1a;
        }

        .total-strip .amount {
            font-size: 18px;
        }

        /* Payment & Signature Section */
        .footer-section {
            padding: 0 45px 45px 45px;
            display: flex;
            justify-content: space-between;
            gap: 30px;
            align-items: flex-end;
        }

        .pay-title {
            font-size: 14px;
            font-weight: 800;
            color: #1a1a1a;
            margin-bottom: 12px;
        }

        .sign-area {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .sign-date {
            font-size: 11.5px;
            color: #737373;
            margin-bottom: 10px;
        }

        .sign-img {
            max-height: 55px;
            max-width: 160px;
            object-fit: contain;
            margin-bottom: 4px;
        }

        .sign-underline {
            width: 170px;
            height: 1.5px;
            background: #1a1a1a;
            margin-bottom: 6px;
        }

        .sign-owner {
            font-size: 13px;
            font-weight: 800;
            color: #1a1a1a;
        }

        /* Bottom Decorative Bar */
        .bottom-bar {
            height: 25px;
            background: #1a1a1a;
            display: flex;
        }

        .bottom-bar-dark {
            flex: 1;
        }

        .bottom-bar-gold {
            width: 320px;
            background: #e5ad45;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0 !important;
            }

            .top-action-bar {
                display: none !important;
            }

            body {
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .invoice-card {
                box-shadow: none !important;
                margin: 0 auto !important;
                width: 100% !important;
                max-width: 100% !important;
                min-height: 297mm !important;
                height: 297mm !important;
                display: flex !important;
                flex-direction: column !important;
                padding: 0 !important;
            }

            /* Footer stays with content — never breaks to next page */
            .invoice-footer-pin {
                page-break-before: avoid !important;
                break-before: avoid !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                margin-top: auto !important;
            }

            .bottom-bar {
                page-break-before: avoid !important;
                break-before: avoid !important;
            }

            .info-section {
                padding: 20px 30px 15px 30px !important;
            }

            .items-wrapper {
                padding: 0 30px !important;
            }

            .footer-section {
                padding: 0 30px 30px 30px !important;
            }
        }

        @media screen and (max-width: 768px) {
            .top-action-bar {
                padding: 10px 15px;
                justify-content: center;
            }

            .action-btns {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }

            .invoice-card {
                margin: 10px;
            }

            .header-split {
                flex-direction: column;
                height: auto;
            }

            .header-dark {
                padding: 25px 20px;
                clip-path: none;
                width: 100%;
            }

            .header-gold {
                width: 100%;
                padding: 25px 20px;
            }

            .info-section {
                grid-template-columns: 1fr;
                gap: 20px;
                padding: 20px;
            }

            .items-wrapper {
                padding: 0 15px;
            }

            .footer-section {
                grid-template-columns: 1fr;
                gap: 25px;
                padding: 0 15px 25px 15px;
            }

            .bottom-bar {
                flex-direction: column;
                height: auto;
            }

            .bottom-bar-gold {
                width: 100%;
                height: 10px;
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
        <!-- Split Header -->
        <div class="header-split">
            <div class="header-dark">
                <div class="inv-main-title"><?= $docTitle ?></div>
                <div class="inv-title-line"></div>
                <table class="header-meta-table">
                    <tr>
                        <td class="label"><?= $docNumberLabel ?></td>
                        <td class="colon">:</td>
                        <td class="val"><?= htmlspecialchars($q->invoice_no) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Invoice Date</td>
                        <td class="colon">:</td>
                        <td class="val"><?= date('F d, Y', strtotime($q->invoice_date)) ?></td>
                    </tr>
                    <?php if (!empty($q->due_date)): ?>
                        <tr>
                            <td class="label">Due Date</td>
                            <td class="colon">:</td>
                            <td class="val"><?= date('F d, Y', strtotime($q->due_date)) ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>

            <div class="header-gold">
                <?php if (!empty($company->logo)): ?>
                    <img src="<?= SITE_URL . $company->logo ?>" alt="Company Logo" style="max-height: 55px; max-width: 180px; object-fit: contain; margin-bottom: 8px;">
                <?php endif; ?>
                <div class="company-gold-brand">
                    <h3><?= !empty($company->company_name) ? htmlspecialchars($company->company_name) : 'COMPANY NAME' ?></h3>
                </div>
            </div>
        </div>

        <!-- Information Section -->
        <div class="info-section">
            <div>
                <div class="info-col-title">Company Information</div>
                <table class="info-table">
                    <tr>
                        <td class="label">Name</td>
                        <td class="colon">:</td>
                        <td class="val"><?= htmlspecialchars($company->owner_name ?? $company->company_name ?? '') ?></td>
                    </tr>
                    <tr>
                        <td class="label">Address</td>
                        <td class="colon">:</td>
                        <td class="val"><?= htmlspecialchars($company->address ?? '') ?></td>
                    </tr>
                    <tr>
                        <td class="label">Phone</td>
                        <td class="colon">:</td>
                        <td class="val"><?= htmlspecialchars($company->mobile_no ?? '') ?></td>
                    </tr>
                    <tr>
                        <td class="label">Email</td>
                        <td class="colon">:</td>
                        <td class="val"><?= htmlspecialchars($company->email ?? '') ?></td>
                    </tr>
                    <?php if (!empty($company->gst_no)): ?>
                        <tr>
                            <td class="label">GSTIN</td>
                            <td class="colon">:</td>
                            <td class="val" style="font-weight:700; color:#1a1a1a;"><?= htmlspecialchars($company->gst_no) ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>

            <div>
                <div class="info-col-title">Client Information</div>
                <table class="info-table">
                    <tr>
                        <td class="label">Customer Name</td>
                        <td class="colon">:</td>
                        <td class="val" style="font-weight:700; color:#1a1a1a;"><?= htmlspecialchars($q->party_name) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Address</td>
                        <td class="colon">:</td>
                        <td class="val"><?= nl2br(htmlspecialchars($q->address ?? '')) ?></td>
                    </tr>
                    <tr>
                        <td class="label">Phone</td>
                        <td class="colon">:</td>
                        <td class="val"><?= htmlspecialchars($party->mobile_no ?? '') ?></td>
                    </tr>
                    <tr>
                        <td class="label">Email</td>
                        <td class="colon">:</td>
                        <td class="val"><?= htmlspecialchars($party->email ?? '') ?></td>
                    </tr>
                    <?php if (!empty($party->gst_no)): ?>
                        <tr>
                            <td class="label">GSTIN</td>
                            <td class="colon">:</td>
                            <td class="val" style="font-weight:700; color:#1a1a1a;"><?= htmlspecialchars($party->gst_no) ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Items Table (grows to fill space) -->
        <div style="flex: 1 0 auto; padding: 0 25px;">
            <?php renderInvoiceProductTableAndTotals($items, $q, $isGst, $hasCgstSgst, $hasIgst, $amountInWords, '#e5ad45'); ?>
        </div>

        <!-- Footer pinned to bottom -->
        <div class="invoice-footer-pin" style="margin-top: auto; flex-shrink: 0;">
            <div style="padding: 0 25px 15px 25px;">
                <?php renderStandardInvoiceFooter($company, $q, '#e5ad45'); ?>
            </div>
            <!-- Bottom Accent Bar -->
            <div class="bottom-bar">
                <div class="bottom-bar-dark"></div>
                <div class="bottom-bar-gold"></div>
            </div>
        </div>
    </div>

    <?php require_once dirname(dirname(__DIR__)) . '/includes/invoice-pdf-script.php'; ?>
</body>

</html>