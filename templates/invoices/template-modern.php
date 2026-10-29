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

$hasDiscount = false;
if (!empty($items)) {
    foreach ($items as $it) {
        if (floatval($it->discount_value ?? 0) > 0 || floatval($it->discount_amount ?? 0) > 0) {
            $hasDiscount = true;
            break;
        }
    }
}

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
    <title><?= ucfirst($docType) ?> - <?= htmlspecialchars($q->invoice_no) ?> (Modern Navy)</title>
    <link rel="icon" type="image/x-icon" href="<?= ADMIN_URL ?>assets/img/favicon/favicon.png" />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ADMIN_URL ?>assets/vendor/fonts/tabler-icons.css" />
    
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            background-color: #f1f5f9;
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
        .btn-primary { background-color: #0284c7; color: #fff; }
        .btn-success { background-color: #0f172a; color: #fff; }
        .btn-secondary { background-color: #e2e8f0; color: #334155; }

        .invoice-card {
            max-width: 860px; margin: 28px auto; background: #ffffff;
            border-radius: 12px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            overflow: hidden; border: 1px solid #e2e8f0;
        }
        .modern-banner {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff; padding: 36px 40px; display: flex; justify-content: space-between; align-items: flex-start;
        }
        .modern-banner h2 { font-size: 26px; font-weight: 800; color: #38bdf8; margin-bottom: 4px; }
        .modern-banner p { font-size: 12.5px; color: #94a3b8; line-height: 1.4; }
        .inv-badge {
            background: rgba(56, 189, 248, 0.15); border: 1px solid #38bdf8;
            color: #38bdf8; padding: 6px 16px; border-radius: 6px;
            font-size: 14px; font-weight: 800; letter-spacing: 1px; text-align: right;
        }
        .inv-meta-right { text-align: right; margin-top: 10px; color: #cbd5e1; font-size: 12px; }
        .inv-meta-right strong { color: #ffffff; }

        .modern-content { padding: 32px 40px; }
        .parties-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
        .party-card {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px;
            border-left: 4px solid #0284c7;
        }
        .party-card.shipping { border-left-color: #64748b; }
        .party-card-title {
            font-size: 11px; text-transform: uppercase; font-weight: 700; color: #0284c7;
            letter-spacing: 0.5px; margin-bottom: 8px;
        }
        .party-card.shipping .party-card-title { color: #64748b; }
        .party-name { font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
        .party-details { font-size: 12px; color: #475569; line-height: 1.5; }

        .modern-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 24px; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; }
        .modern-table th {
            background: #0f172a; color: #ffffff; padding: 12px 14px;
            font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .modern-table td { padding: 12px 14px; font-size: 12.5px; border-bottom: 1px solid #f1f5f9; color: #334155; }
        .modern-table tbody tr:nth-child(even) { background-color: #f8fafc; }
        .modern-table tbody tr:last-child td { border-bottom: none; }

        .summary-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px; align-items: start; margin-bottom: 24px; }
        .bank-card {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px;
        }
        .bank-card-title { font-size: 12px; font-weight: 700; color: #0284c7; margin-bottom: 8px; }
        .bank-row { font-size: 12px; color: #475569; margin-bottom: 3px; }

        .totals-card {
            background: #0f172a; color: #ffffff; border-radius: 8px; padding: 20px;
        }
        .tot-row { display: flex; justify-content: space-between; font-size: 12.5px; color: #94a3b8; margin-bottom: 8px; }
        .tot-row strong { color: #ffffff; }
        .tot-divider { height: 1px; background: #334155; margin: 12px 0; }
        .tot-grand { display: flex; justify-content: space-between; font-size: 17px; font-weight: 800; color: #38bdf8; }

        .auth-sign-box { text-align: right; margin-top: 30px; }
        .auth-sign-line { width: 180px; margin-left: auto; border-top: 1px solid #0f172a; padding-top: 4px; font-size: 11px; font-weight: 700; color: #0f172a; }

        @media print {
            @page {
                size: A4 portrait;
                margin: 8mm 10mm;
            }
            .top-action-bar { display: none !important; }
            body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
            .invoice-card {
                border: none !important; box-shadow: none !important;
                margin: 0 auto !important; width: 100% !important; max-width: 100% !important;
                border-radius: 0 !important;
            }
            .modern-banner { padding: 24px 28px !important; }
            .modern-content { padding: 20px 28px !important; }
            .parties-row { gap: 15px !important; margin-bottom: 16px !important; }
            .party-card { padding: 12px 15px !important; }
        }
        @media screen and (max-width: 768px) {
            .top-action-bar { padding: 10px 15px; justify-content: center; }
            .action-btns { width: 100%; justify-content: center; flex-wrap: wrap; }
            .invoice-card { margin: 10px; border-radius: 8px; }
            .modern-banner { flex-direction: column; gap: 15px; padding: 20px; }
            .inv-meta-right { text-align: left; margin-top: 8px; }
            .parties-row { grid-template-columns: 1fr; gap: 15px; }
            .modern-content { padding: 15px; }
        }
    </style>
</head>
<body>

    <div class="top-action-bar">
        <div class="action-btns">
            <button onclick="window.print()" class="btn btn-primary"><i class="ti ti-printer"></i> Print</button>
            <button onclick="downloadPDF()" id="btnDownloadPdf" class="btn btn-success"><i class="ti ti-download"></i> Save as PDF</button>
            <button onclick="window.close()" class="btn btn-secondary"><i class="ti ti-x"></i> Cancel</button>
        </div>
    </div>

    <div class="invoice-card">
        <!-- Banner Header -->
        <div class="modern-banner">
            <div>
                <?php if (!empty($company->logo)): ?>
                    <div style="margin-bottom: 8px;">
                        <img src="<?= SITE_URL . $company->logo ?>" alt="Logo" style="max-height: 50px; max-width: 160px; object-fit: contain; background: #fff; padding: 4px; border-radius: 4px;">
                    </div>
                <?php endif; ?>
                <h2><?= !empty($company->company_name) ? htmlspecialchars($company->company_name) : 'YOUR COMPANY NAME' ?></h2>
                <p><?= !empty($company->address) ? htmlspecialchars($company->address) : 'Company Address Line' ?></p>
                <p>Phone: <?= htmlspecialchars($company->mobile_no ?? '') ?> | Email: <?= htmlspecialchars($company->email ?? '') ?></p>
                <?php if (!empty($company->gst_no)): ?>
                    <p style="margin-top:4px;"><strong style="color:#38bdf8;">GSTIN:</strong> <?= htmlspecialchars($company->gst_no) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <div class="inv-badge"><?= $docTitle ?></div>
                <div class="inv-meta-right">
                    <div><?= $docNumberLabel ?>: <strong><?= htmlspecialchars($q->invoice_no) ?></strong></div>
                    <div>Date: <strong><?= date('d M, Y', strtotime($q->invoice_date)) ?></strong></div>
                    <?php if (!empty($q->due_date)): ?>
                        <div>Due Date: <strong><?= date('d M, Y', strtotime($q->due_date)) ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="modern-content">
            <!-- Parties Row -->
            <div class="parties-row">
                <div class="party-card">
                    <div class="party-card-title">Billed To</div>
                    <div class="party-name"><?= htmlspecialchars($q->party_name) ?></div>
                    <div class="party-details">
                        <?= nl2br(htmlspecialchars($q->address ?? '')) ?><br>
                        <?php if (!empty($party->mobile_no)): ?>Phone: <?= htmlspecialchars($party->mobile_no) ?><br><?php endif; ?>
                        <?php if (!empty($party->gst_no)): ?><strong>GSTIN:</strong> <?= htmlspecialchars($party->gst_no) ?><br><?php endif; ?>
                        <?php if (!empty($q->state_name)): ?>State: <?= htmlspecialchars($q->state_name) ?><?php endif; ?>
                    </div>
                </div>

                <div class="party-card shipping">
                    <div class="party-card-title">Shipped To</div>
                    <div class="party-name"><?= htmlspecialchars(!empty($party->shipping_address) ? $q->party_name : $q->party_name) ?></div>
                    <div class="party-details">
                        <?= !empty($party->shipping_address) ? nl2br(htmlspecialchars($party->shipping_address)) : nl2br(htmlspecialchars($q->address ?? '')) ?><br>
                        <?php if (!empty($party->shipping_pincode)): ?>Pincode: <?= htmlspecialchars($party->shipping_pincode) ?><br><?php endif; ?>
                        State: <?= htmlspecialchars($q->state_name ?? '') ?>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <?php renderInvoiceProductTableAndTotals($items, $q, $isGst, $hasCgstSgst, $hasIgst, $amountInWords, '#0284c7'); ?>

            <?php renderStandardInvoiceFooter($company, $q, '#0284c7'); ?>
        </div>
    </div>

    <!-- html2pdf.js for direct client-side PDF download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadPDF() {
            var btn = document.getElementById('btnDownloadPdf');
            var originalText = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="ti ti-loader"></i> Downloading...';
            }

            var element = document.querySelector('.invoice-card');
            var fileName = <?= json_encode(preg_replace('/[^a-zA-Z0-9_\-]/', '_', $q->invoice_no ?? 'invoice') . '.pdf') ?>;

            var opt = {
                margin: [5, 8, 5, 8], // top, left, bottom, right in mm
                filename: fileName,
                image: {
                    type: 'jpeg',
                    quality: 0.98
                },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    logging: false
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: 'portrait'
                }
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
                setTimeout(downloadPDF, 600);
            }
        });
    </script>
</body>
</html>
