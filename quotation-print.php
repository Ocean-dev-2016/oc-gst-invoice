<?php
require_once __DIR__ . '/root/config.php';

// Check login
if (empty($_SESSION['aid'])) {
    header('Location: index.php');
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("Invalid Quotation ID!");
}

// Fetch Quotation
$quotationRow = $ai_db->aiGetQueryObj("SELECT * FROM tbl_quotation WHERE id='$id' LIMIT 1");
if (empty($quotationRow)) {
    die("Quotation not found!");
}
$q = $quotationRow[0];

// Fetch Quotation Items joined with product for SKU if available
$items = $ai_db->aiGetQueryObj("
    SELECT qi.*, p.sku, p.product_name 
    FROM tbl_quotation_items qi
    LEFT JOIN tbl_product p ON p.id = qi.product_id
    WHERE qi.quotation_id='$id'
    ORDER BY qi.id ASC
");

// Fetch Company Details
$company = null;
if (!empty($q->company_id)) {
    $compRow = $ai_db->aiGetQueryObj("SELECT * FROM tbl_company WHERE id='{$q->company_id}' LIMIT 1");
    if (!empty($compRow)) {
        $company = $compRow[0];
    }
}

// Fetch Party Details
$party = null;
if (!empty($q->party_id)) {
    $partyRow = $ai_db->aiGetQueryObj("SELECT * FROM tbl_party WHERE id='{$q->party_id}' LIMIT 1");
    if (!empty($partyRow)) {
        $party = $partyRow[0];
    }
}

// Helper: Convert numbers to Indian Rupees in words
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
    $paise = ($decimal > 0) ? " and " . ($words[$decimal / 10] . " " . $words[$decimal % 10]) . ' Paise' : '';
    return ($Rupees ? $Rupees . 'Rupees ' : '') . $paise . " Only";
}

$amountInWords = getIndianCurrencyWords(floatval($q->grand_total));
$isGst = ($q->gst_type === 'with_gst');
$hasCgstSgst = ($q->cgst_amount > 0 || $q->sgst_amount > 0);
$hasIgst = ($q->igst_amount > 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation - <?= htmlspecialchars($q->quotation_no) ?></title>
    <link rel="icon" type="image/x-icon" href="<?= ADMIN_URL ?>assets/img/favicon/favicon.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ADMIN_URL ?>assets/vendor/fonts/tabler-icons.css" />
    
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Public Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f3f4f6;
            color: #2b3445;
            font-size: 13px;
            line-height: 1.5;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        /* Floating Top Bar (Hidden during Print) */
        .top-action-bar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
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
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background-color: #7367f0;
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: #5e50ee;
        }
        .btn-success {
            background-color: #28c76f;
            color: #ffffff;
        }
        .btn-success:hover {
            background-color: #1f9d57;
        }
        .btn-secondary {
            background-color: #f1f5f9;
            color: #475569;
            border-color: #cbd5e1;
        }
        .btn-secondary:hover {
            background-color: #e2e8f0;
        }

        /* Invoice Container */
        .invoice-wrapper {
            max-width: 860px;
            margin: 24px auto 40px;
            padding: 0 15px;
        }
        .invoice-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 36px 40px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }

        /* Header Layout */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid #7367f0;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        .company-brand h2 {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
            letter-spacing: -0.5px;
        }
        .company-brand p {
            color: #64748b;
            font-size: 12.5px;
            margin-bottom: 2px;
        }
        .invoice-title-block {
            text-align: right;
        }
        .invoice-badge-title {
            font-size: 26px;
            font-weight: 800;
            color: #7367f0;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .meta-line {
            font-size: 13px;
            color: #475569;
            margin-bottom: 3px;
        }
        .meta-line strong {
            color: #1e293b;
        }

        /* Parties Grid */
        .parties-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }
        .party-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
        }
        .party-box-title {
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 700;
            color: #7367f0;
            margin-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }
        .party-name {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .party-desc {
            font-size: 12.5px;
            color: #475569;
            line-height: 1.45;
        }

        /* Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 700;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #cbd5e1;
            padding: 10px 8px;
            text-align: center;
        }
        .items-table td {
            border: 1px solid #e2e8f0;
            padding: 10px 8px;
            font-size: 12.5px;
            color: #1e293b;
            vertical-align: middle;
        }
        .items-table tbody tr:nth-child(even) {
            background-color: #fafbfd;
        }
        .text-start { text-align: left !important; }
        .text-center { text-align: center !important; }
        .text-end { text-align: right !important; }

        /* Summary Section */
        .summary-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-top: 15px;
            gap: 20px;
        }
        .amount-words-box {
            flex: 1;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px 16px;
        }
        .amount-words-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 4px;
        }
        .amount-words-text {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
        }

        .calculation-table {
            width: 330px;
            border-collapse: collapse;
        }
        .calculation-table td {
            padding: 6px 12px;
            font-size: 13px;
        }
        .calc-label {
            color: #64748b;
            font-weight: 500;
            text-align: left;
        }
        .calc-val {
            text-align: right;
            font-weight: 600;
            color: #1e293b;
        }
        .calc-total-row td {
            border-top: 2px solid #cbd5e1;
            border-bottom: 2px solid #cbd5e1;
            padding: 10px 12px;
            font-size: 15px;
            font-weight: 700;
            color: #7367f0;
            background-color: #f5f3ff;
        }

        /* Footer & Signatory */
        .invoice-footer {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-top: 20px;
            border-top: 1px dashed #cbd5e1;
        }
        .terms-box {
            max-width: 480px;
            font-size: 11.5px;
            color: #64748b;
        }
        .terms-box h4 {
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .signature-box {
            text-align: center;
            width: 220px;
        }
        .sign-line {
            height: 55px;
        }
        .sign-name {
            font-size: 12.5px;
            font-weight: 700;
            color: #1e293b;
            border-top: 1px solid #94a3b8;
            padding-top: 6px;
        }
        .sign-title {
            font-size: 11px;
            color: #64748b;
        }

        /* Print Specific Styling */
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
            }
            .no-print, .top-action-bar {
                display: none !important;
            }
            .invoice-wrapper {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .invoice-card {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
            }
            @page {
                size: A4 portrait;
                margin: 10mm 12mm 10mm 12mm;
            }
        }
    </style>
</head>
<body>

    <!-- Floating Top Action Bar -->
    <div class="top-action-bar no-print">
        <div style="font-size: 16px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px;">
            <i class="ti ti-file-invoice fs-4 text-primary"></i>
            Quotation Preview &mdash; <?= htmlspecialchars($q->quotation_no) ?>
        </div>
        <div class="action-btns">
            <button onclick="downloadPDF()" id="btnDownloadPdf" class="btn btn-success">
                <i class="ti ti-download"></i> Download PDF
            </button>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="ti ti-printer"></i> Print / Save as PDF
            </button>
            <a href="manage-quotation-form.php?mode=edit&id=<?= $q->id ?>" class="btn btn-secondary">
                <i class="ti ti-pencil"></i> Edit Quotation
            </a>
            <a href="manage-quotation-list.php" class="btn btn-secondary">
                <i class="ti ti-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <!-- Invoice Content Area -->
    <div class="invoice-wrapper">
        <div class="invoice-card">
            
            <!-- Invoice Header -->
            <div class="invoice-header">
                <div class="company-brand">
                    <h2><?= !empty($company->company_name) ? htmlspecialchars($company->company_name) : 'Company Name' ?></h2>
                    <?php if (!empty($company->address)) { ?>
                        <p><?= nl2br(htmlspecialchars($company->address)) ?></p>
                    <?php } ?>
                    <?php if (!empty($company->mobile_no) || !empty($company->email)) { ?>
                        <p>
                            <?= !empty($company->mobile_no) ? '<strong>Phone:</strong> ' . htmlspecialchars($company->mobile_no) . ' &nbsp;|&nbsp; ' : '' ?>
                            <?= !empty($company->email) ? '<strong>Email:</strong> ' . htmlspecialchars($company->email) : '' ?>
                        </p>
                    <?php } ?>
                    <?php if (!empty($company->gst_no)) { ?>
                        <p><strong>GSTIN:</strong> <?= htmlspecialchars($company->gst_no) ?></p>
                    <?php } ?>
                </div>

                <div class="invoice-title-block">
                    <div class="invoice-badge-title">QUOTATION</div>
                    <div class="meta-line"><strong>Quotation No:</strong> <?= htmlspecialchars($q->quotation_no) ?></div>
                    <div class="meta-line"><strong>Date:</strong> <?= !empty($q->quotation_date) ? date('d-m-Y', strtotime($q->quotation_date)) : '-' ?></div>
                    <?php if (!empty($q->due_date)) { 
                        $isOverdue = ($q->payment_status === 'Pending' && $q->due_date < date('Y-m-d'));
                    ?>
                        <div class="meta-line">
                            <strong>Due Date:</strong> <?= date('d-m-Y', strtotime($q->due_date)) ?>
                            <?php if ($isOverdue) { ?>
                                <span style="background-color: #fee2e2; color: #dc2626; font-size: 11px; padding: 2px 6px; border-radius: 4px; font-weight: 700; margin-left: 4px;">Overdue</span>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <div class="meta-line">
                        <strong>Status:</strong> 
                        <span style="font-weight: 700; color: <?= ($q->payment_status === 'Paid') ? '#10b981' : '#f59e0b' ?>">
                            <?= htmlspecialchars($q->payment_status ?? 'Pending') ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Party Details (Bill To) -->
            <div class="parties-grid">
                <div class="party-box">
                    <div class="party-box-title">QUOTATION TO (CUSTOMER)</div>
                    <div class="party-name"><?= htmlspecialchars($q->party_name) ?></div>
                    <div class="party-desc">
                        <?php if (!empty($q->address)) { ?>
                            <div><?= nl2br(htmlspecialchars($q->address)) ?></div>
                        <?php } ?>
                        <?php if (!empty($q->city_name) || !empty($q->state_name)) { ?>
                            <div><?= htmlspecialchars(trim(($q->city_name ? $q->city_name . ', ' : '') . $q->state_name)) ?></div>
                        <?php } ?>
                        <?php if (!empty($q->gst_no)) { ?>
                            <div style="margin-top: 4px;"><strong>GSTIN:</strong> <?= htmlspecialchars($q->gst_no) ?></div>
                        <?php } ?>
                        <?php if (!empty($party->mobile_no)) { ?>
                            <div><strong>Phone:</strong> <?= htmlspecialchars($party->mobile_no) ?></div>
                        <?php } ?>
                        <?php if (!empty($party->email)) { ?>
                            <div><strong>Email:</strong> <?= htmlspecialchars($party->email) ?></div>
                        <?php } ?>
                    </div>
                </div>

                <div class="party-box">
                    <div class="party-box-title">QUOTATION DETAILS</div>
                    <div class="party-desc" style="line-height: 1.8;">
                        <div><strong>GST Type:</strong> <?= ($q->gst_type === 'with_gst') ? 'With GST' : 'Without GST' ?></div>
                        <div><strong>Reverse Charge (RCM):</strong> <?= htmlspecialchars($q->rca ?? 'No') ?></div>
                        <div><strong>Place of Supply:</strong> <?= !empty($q->state_name) ? htmlspecialchars($q->state_name) : 'Gujarat' ?></div>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 30%;" class="text-start">Item Description</th>
                        <th style="width: 10%;">HSN/SAC</th>
                        <th style="width: 10%;">Rate</th>
                        <th style="width: 8%;">Qty</th>
                        <th style="width: 12%;">Discount</th>
                        <th style="width: 11%;">Taxable Amt</th>
                        <?php if ($isGst) { ?>
                            <th style="width: 8%;">GST</th>
                        <?php } ?>
                        <th style="width: 12%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sr = 1;
                    if (!empty($items)) {
                        foreach ($items as $item) {
                            $rate = floatval($item->rate);
                            $qty = floatval($item->qty);
                            $discVal = floatval($item->discount_value);
                            $discType = $item->discount_type;
                            $discAmt = floatval($item->discount_amount);
                            $netAmt = floatval($item->net_amount);
                            $gstPct = floatval($item->gst_percent);
                            $totalAmt = floatval($item->total_amount);

                            $discLabel = '-';
                            if ($discVal > 0) {
                                $discLabel = ($discType === 'fixed') 
                                    ? '₹' . number_format($discVal, 2) 
                                    : $discVal . '% (₹' . number_format($discAmt, 2) . ')';
                            }
                    ?>
                        <tr>
                            <td class="text-center"><?= $sr++ ?></td>
                            <td class="text-start">
                                <strong><?= htmlspecialchars($item->description) ?></strong>
                                <?php if (!empty($item->sku)) { ?>
                                    <br><small style="color: #64748b;">SKU: <?= htmlspecialchars($item->sku) ?></small>
                                <?php } ?>
                            </td>
                            <td class="text-center"><?= !empty($item->hsn_code) ? htmlspecialchars($item->hsn_code) : '-' ?></td>
                            <td class="text-end">₹<?= number_format($rate, 2) ?></td>
                            <td class="text-center"><?= number_format($qty, 2) ?></td>
                            <td class="text-center"><?= $discLabel ?></td>
                            <td class="text-end">₹<?= number_format($netAmt, 2) ?></td>
                            <?php if ($isGst) { ?>
                                <td class="text-center"><?= $gstPct ?>%</td>
                            <?php } ?>
                            <td class="text-end"><strong>₹<?= number_format($totalAmt, 2) ?></strong></td>
                        </tr>
                    <?php 
                        }
                    } else { ?>
                        <tr>
                            <td colspan="<?= $isGst ? '9' : '8' ?>" class="text-center" style="padding: 24px; color: #94a3b8;">
                                No items found in this quotation.
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <!-- Summary & Amount in Words -->
            <div class="summary-wrapper">
                <div class="amount-words-box">
                    <div class="amount-words-title">Amount in Words:</div>
                    <div class="amount-words-text"><?= htmlspecialchars($amountInWords) ?></div>
                </div>

                <table class="calculation-table">
                    <tr>
                        <td class="calc-label">Sub Total (Taxable):</td>
                        <td class="calc-val">₹<?= number_format(floatval($q->total_amount), 2) ?></td>
                    </tr>
                    <?php if ($isGst) { ?>
                        <?php if ($hasCgstSgst) { ?>
                            <tr>
                                <td class="calc-label">CGST Amount:</td>
                                <td class="calc-val">₹<?= number_format(floatval($q->cgst_amount), 2) ?></td>
                            </tr>
                            <tr>
                                <td class="calc-label">SGST Amount:</td>
                                <td class="calc-val">₹<?= number_format(floatval($q->sgst_amount), 2) ?></td>
                            </tr>
                        <?php } ?>
                        <?php if ($hasIgst) { ?>
                            <tr>
                                <td class="calc-label">IGST Amount:</td>
                                <td class="calc-val">₹<?= number_format(floatval($q->igst_amount), 2) ?></td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                    <tr class="calc-total-row">
                        <td class="calc-label" style="color: #7367f0;">Grand Total:</td>
                        <td class="calc-val" style="color: #7367f0;">₹<?= number_format(floatval($q->grand_total), 2) ?></td>
                    </tr>
                </table>
            </div>

            <!-- Footer & Signatures -->
            <div class="invoice-footer">
                <div class="terms-box">
                    <h4>Terms & Conditions</h4>
                    <div>1. Quotation is valid for 30 days from the date of issue.</div>
                    <div>2. Goods once sold will not be taken back without prior consent.</div>
                    <div>3. Payment is due as per agreed terms.</div>
                </div>

                <div class="signature-box">
                    <div class="sign-line"></div>
                    <div class="sign-name"><?= !empty($company->company_name) ? htmlspecialchars($company->company_name) : 'Authorized Signatory' ?></div>
                    <div class="sign-title">Authorized Signatory</div>
                </div>
            </div>

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
                btn.innerHTML = '<i class="ti ti-loader"></i> Generating PDF...';
            }

            var element = document.querySelector('.invoice-card');
            var fileName = <?= json_encode(preg_replace('/[^a-zA-Z0-9_\-]/', '_', $q->quotation_no) . '.pdf') ?>;

            var opt = {
                margin:       [10, 10, 10, 10], // top, left, bottom, right in mm
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
                // Fallback to print
                window.print();
            });
        }

        window.addEventListener('load', function() {
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('download') === '1') {
                setTimeout(downloadPDF, 500);
            } else if (urlParams.get('print') === '1') {
                window.print();
            }
        });
    </script>
</body>
</html>
