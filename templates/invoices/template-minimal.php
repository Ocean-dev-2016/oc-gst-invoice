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
    <title><?= ucfirst($docType) ?> - <?= htmlspecialchars($q->invoice_no) ?> (Clean Minimalist)</title>
    <link rel="icon" type="image/x-icon" href="<?= ADMIN_URL ?>assets/img/favicon/favicon.png" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ADMIN_URL ?>assets/vendor/fonts/tabler-icons.css" />
    
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background-color: #fafafa;
            color: #171717;
            font-size: 13px;
            line-height: 1.6;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .top-action-bar {
            position: sticky; top: 0; z-index: 1000;
            background: #ffffff; border-bottom: 1px solid #eaeaea;
            padding: 12px 24px; display: flex; justify-content: flex-end; align-items: center;
        }
        .action-btns { display: flex; gap: 10px; }
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 18px; font-size: 13px; font-weight: 600;
            border-radius: 4px; text-decoration: none; cursor: pointer; border: 1px solid #171717;
        }
        .btn-primary { background-color: #171717; color: #fff; }
        .btn-success { background-color: #0f766e; color: #fff; border-color: #0f766e; }
        .btn-secondary { background-color: #fff; color: #171717; }

        .invoice-card {
            max-width: 840px; margin: 30px auto; background: #ffffff;
            padding: 45px 50px; border: 1px solid #e5e5e5;
        }
        .minimal-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            padding-bottom: 24px; border-bottom: 2px solid #171717; margin-bottom: 30px;
        }
        .company-name { font-size: 22px; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 4px; }
        .company-sub { font-size: 12px; color: #737373; line-height: 1.4; }
        
        .inv-right-title { font-size: 26px; font-weight: 300; letter-spacing: 2px; text-align: right; text-transform: uppercase; }
        .inv-meta-list { text-align: right; margin-top: 8px; font-size: 12px; color: #737373; }
        .inv-meta-list strong { color: #171717; }

        .parties-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 35px; }
        .party-col-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #a3a3a3; margin-bottom: 6px; }
        .party-col-name { font-size: 15px; font-weight: 700; color: #171717; margin-bottom: 4px; }
        .party-col-desc { font-size: 12px; color: #525252; line-height: 1.5; }

        .minimal-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .minimal-table th {
            border-bottom: 1.5px solid #171717; border-top: 1.5px solid #171717;
            padding: 10px 8px; font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;
        }
        .minimal-table td { padding: 12px 8px; border-bottom: 1px solid #f5f5f5; font-size: 12.5px; }

        .totals-section { display: flex; justify-content: space-between; margin-top: 20px; align-items: flex-start; }
        .bank-minimal { width: 50%; font-size: 12px; color: #525252; }
        .bank-minimal strong { color: #171717; }

        .calc-minimal { width: 40%; }
        .calc-row { display: flex; justify-content: space-between; font-size: 12.5px; padding: 4px 0; color: #737373; }
        .calc-row strong { color: #171717; }
        .calc-total {
            display: flex; justify-content: space-between; font-size: 16px; font-weight: 800;
            border-top: 1.5px solid #171717; border-bottom: 1.5px solid #171717; padding: 10px 0; margin-top: 10px;
        }

        .auth-sign-minimal { text-align: right; margin-top: 50px; font-size: 11px; color: #737373; }
        .auth-line { width: 160px; margin-left: auto; border-top: 1px solid #171717; padding-top: 4px; font-weight: 600; color: #171717; }

        @media print {
            @page {
                size: A4 portrait;
                margin: 8mm 10mm;
            }
            .top-action-bar { display: none !important; }
            body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
            .invoice-card {
                border: none !important; margin: 0 auto !important;
                width: 100% !important; max-width: 100% !important; padding: 20px 25px !important;
            }
        }
        @media screen and (max-width: 768px) {
            .top-action-bar { padding: 10px 15px; justify-content: center; }
            .action-btns { width: 100%; justify-content: center; flex-wrap: wrap; }
            .invoice-card { margin: 10px; padding: 20px 15px; }
            .minimal-header { flex-direction: column; gap: 15px; }
            .inv-right-title, .inv-meta-list { text-align: left; }
            .parties-grid { grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px; }
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
        <div class="minimal-header">
            <div>
                <?php if (!empty($company->logo)): ?>
                    <div style="margin-bottom: 8px;">
                        <img src="<?= SITE_URL . $company->logo ?>" alt="Logo" style="max-height: 48px; max-width: 150px; object-fit: contain;">
                    </div>
                <?php endif; ?>
                <div class="company-name"><?= !empty($company->company_name) ? htmlspecialchars($company->company_name) : 'YOUR COMPANY NAME' ?></div>
                <div class="company-sub">
                    <?= htmlspecialchars($company->address ?? 'Address Line') ?><br>
                    <?= htmlspecialchars($company->mobile_no ?? '') ?> | <?= htmlspecialchars($company->email ?? '') ?><br>
                    <?php if (!empty($company->gst_no)): ?>GSTIN: <strong><?= htmlspecialchars($company->gst_no) ?></strong><?php endif; ?>
                </div>
            </div>
            <div>
                <div class="inv-right-title">Tax Invoice</div>
                <div class="inv-meta-list">
                    Invoice: <strong><?= htmlspecialchars($q->invoice_no) ?></strong><br>
                    Date: <strong><?= date('d-m-Y', strtotime($q->invoice_date)) ?></strong><br>
                    <?php if (!empty($q->due_date)): ?>Due: <strong><?= date('d-m-Y', strtotime($q->due_date)) ?></strong><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="parties-grid">
            <div>
                <div class="party-col-label">Billed To</div>
                <div class="party-col-name"><?= htmlspecialchars($q->party_name) ?></div>
                <div class="party-col-desc">
                    <?= nl2br(htmlspecialchars($q->address ?? '')) ?><br>
                    <?php if (!empty($party->gst_no)): ?>GSTIN: <strong><?= htmlspecialchars($party->gst_no) ?></strong><br><?php endif; ?>
                    <?= htmlspecialchars($q->state_name ?? '') ?>
                </div>
            </div>
            <div>
                <div class="party-col-label">Shipped To</div>
                <div class="party-col-name"><?= htmlspecialchars($q->party_name) ?></div>
                <div class="party-col-desc">
                    <?= !empty($party->shipping_address) ? nl2br(htmlspecialchars($party->shipping_address)) : nl2br(htmlspecialchars($q->address ?? '')) ?><br>
                    <?= htmlspecialchars($q->state_name ?? '') ?>
                </div>
            </div>
        </div>

        <?php renderInvoiceProductTableAndTotals($items, $q, $isGst, $hasCgstSgst, $hasIgst, $amountInWords, '#171717'); ?>

        <?php renderStandardInvoiceFooter($company, $q, '#171717'); ?>
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
