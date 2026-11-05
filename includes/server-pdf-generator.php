<?php
// Unified Server-Side mPDF Generator for Quotation, Invoice, and Purchase
require_once dirname(__DIR__) . '/root/config.php';
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

if (!function_exists('getIndianCurrencyWordsForMpdf')) {
    function getIndianCurrencyWordsForMpdf(float $number) {
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
        $paise = "";
        if ((int)$decimal > 0) {
            $d = (int)$decimal;
            $paise = " and " . ($d < 21 ? ($words[$d] ?? "") : (($words[(int)floor($d / 10) * 10] ?? "") . " " . ($words[$d % 10] ?? ""))) . " Paise";
        }
        return ($Rupees ? $Rupees . 'Rupees ' : '') . $paise . " Only";
    }
}

/**
 * Common mPDF Document Generator for Quotation, Invoice, and Purchase
 * - For quotation & invoice: Uses the company's selected dynamic template (1 to 6)
 * - For purchase: Uses a dedicated clean, simple, standard layout (no fancy/dynamic template)
 *
 * @param int $docId Document ID
 * @param string $docType 'quotation', 'invoice', or 'purchase'
 * @param int|null $forceTemplateId Override company template
 * @return string|false Relative file path on success, false on failure
 */
function generateDocumentPdfMpdf($docId, $docType = 'invoice', $forceTemplateId = null) {
    global $ai_db;
    $docId = intval($docId);
    if ($docId <= 0) return false;

    $docType = strtolower(trim($docType));
    if (!in_array($docType, ['invoice', 'quotation', 'purchase'], true)) {
        $docType = 'invoice';
    }

    // Config per document type
    if ($docType === 'quotation') {
        $mainTable   = 'tbl_quotation';
        $itemsTable  = 'tbl_quotation_items';
        $itemFk      = 'quotation_id';
        $folderName  = 'quotation';
        $filePrefix  = 'quotation_';
        $docTitle    = 'QUOTATION';
        $noLabel     = 'Quotation No';
        $dateLabel   = 'Quotation Date';
    } elseif ($docType === 'purchase') {
        $mainTable   = 'tbl_purchase';
        $itemsTable  = 'tbl_purchase_items';
        $itemFk      = 'purchase_id';
        $folderName  = 'purchase';
        $filePrefix  = 'purchase_';
        $docTitle    = 'PURCHASE BILL';
        $noLabel     = 'Bill No';
        $dateLabel   = 'Date';
    } else {
        $mainTable   = 'tbl_invoice';
        $itemsTable  = 'tbl_invoice_items';
        $itemFk      = 'invoice_id';
        $folderName  = 'invoices';
        $filePrefix  = 'invoice_';
        $docTitle    = 'TAX INVOICE';
        $noLabel     = 'Invoice No';
        $dateLabel   = 'Invoice Date';
    }

    // Fetch Main Document Record
    $docRow = $ai_db->aiGetQueryObj("SELECT * FROM {$mainTable} WHERE id='{$docId}' LIMIT 1");
    if (empty($docRow)) return false;
    $q = $docRow[0];

    // Standardize Number and Date properties
    if ($docType === 'quotation') {
        $docNumber = $q->quotation_no ?? ('QUO-' . $q->id);
        $rawDate   = $q->quotation_date ?? ($q->created_at ?? date('Y-m-d'));
    } elseif ($docType === 'purchase') {
        $docNumber = $q->purchase_no ?? ('PUR-' . $q->id);
        $rawDate   = $q->purchase_date ?? ($q->created_at ?? date('Y-m-d'));
    } else {
        $docNumber = $q->invoice_no ?? ('INV-' . $q->id);
        $rawDate   = $q->invoice_date ?? ($q->created_at ?? date('Y-m-d'));
    }
    $docDate = !empty($rawDate) ? date('d M Y', strtotime($rawDate)) : date('d M Y');
    $dueDate = !empty($q->due_date) ? date('d M Y', strtotime($q->due_date)) : '';

    // Fetch Items
    $items = $ai_db->aiGetQueryObj("
        SELECT qi.*, p.sku, p.product_name 
        FROM {$itemsTable} qi
        LEFT JOIN tbl_product p ON p.id = qi.product_id
        WHERE qi.{$itemFk}='{$docId}'
        ORDER BY qi.id ASC
    ");

    // Fetch Company Details
    $company = null;
    if (!empty($q->company_id)) {
        $cRow = $ai_db->aiGetQueryObj("SELECT * FROM tbl_company WHERE id='{$q->company_id}' LIMIT 1");
        if (!empty($cRow)) $company = $cRow[0];
    }

    // Fetch Party Details
    $party = null;
    if (!empty($q->party_id)) {
        $pRow = $ai_db->aiGetQueryObj("SELECT * FROM tbl_party WHERE id='{$q->party_id}' LIMIT 1");
        if (!empty($pRow)) $party = $pRow[0];
    }

    // For Purchase: ALWAYS use clean simple standard theme (no dynamic templates)
    // For Quotation/Invoice: Use company's configured template (1 to 6)
    $tplId = ($docType === 'purchase') ? 'simple_purchase' : ($forceTemplateId ?: (!empty($company->invoice_template_id) ? intval($company->invoice_template_id) : 1));

    // Theme palette mappings
    $themes = [
        'simple_purchase' => [
            'name' => 'Simple Purchase Standard',
            'is_split_header' => false,
            'header_dark' => false,
            'header_bg' => '#ffffff',
            'header_border' => '2px solid #0284c7',
            'primary' => '#0284c7',
            'text_dark' => '#0f172a',
            'light' => '#f8fafc',
            'border' => '#e2e8f0',
            'th_bg' => '#f1f5f9',
            'th_text' => '#334155',
            'th_border' => '1px solid #cbd5e1',
            'td_border' => '1px solid #e2e8f0',
            'grand_bg' => '#f0f9ff',
            'grand_border' => '1.5px solid #0284c7',
            'grand_text' => '#0284c7',
            'sign_line' => '1.5px solid #94a3b8',
            'terms_color' => '#334155'
        ],
        1 => [
            'name' => 'Classic Professional',
            'is_split_header' => false,
            'header_dark' => false,
            'header_bg' => '#ffffff',
            'header_border' => '3px solid #7367f0',
            'primary' => '#7367f0',
            'text_dark' => '#333333',
            'light' => '#f4f3ff',
            'border' => '#dcd7fe',
            'th_bg' => '#f4f3ff',
            'th_text' => '#7367f0',
            'th_border' => '1px solid #dcd7fe',
            'td_border' => '1px solid #dcd7fe',
            'grand_bg' => '#f4f3ff',
            'grand_border' => '2px solid #7367f0',
            'grand_text' => '#7367f0',
            'sign_line' => '2px solid #7367f0',
            'terms_color' => '#7367f0'
        ],
        2 => [
            'name' => 'Modern Navy',
            'is_split_header' => false,
            'header_dark' => true,
            'header_bg' => '#0f172a',
            'header_border' => '3px solid #0284c7',
            'primary' => '#0284c7',
            'text_dark' => '#0f172a',
            'light' => '#f0f9ff',
            'border' => '#bae6fd',
            'th_bg' => '#0f172a',
            'th_text' => '#ffffff',
            'th_border' => '1px solid #0f172a',
            'td_border' => '1px solid #bae6fd',
            'grand_bg' => '#f0f9ff',
            'grand_border' => '2px solid #0284c7',
            'grand_text' => '#0284c7',
            'sign_line' => '2px solid #0284c7',
            'terms_color' => '#0284c7'
        ],
        3 => [
            'name' => 'Clean Minimalist',
            'is_split_header' => false,
            'header_dark' => false,
            'header_bg' => '#ffffff',
            'header_border' => '3px solid #171717',
            'primary' => '#171717',
            'text_dark' => '#171717',
            'light' => '#f8fafc',
            'border' => '#e2e8f0',
            'th_bg' => '#ffffff',
            'th_text' => '#171717',
            'th_border' => '1.5px solid #171717',
            'td_border' => '1px solid #e5e5e5',
            'grand_bg' => '#f8fafc',
            'grand_border' => '2px solid #171717',
            'grand_text' => '#171717',
            'sign_line' => '2px solid #171717',
            'terms_color' => '#171717'
        ],
        4 => [
            'name' => 'Golden Executive',
            'is_split_header' => true,
            'header_dark' => true,
            'header_bg' => '#1a1a1a',
            'header_border' => 'none',
            'primary' => '#e5ad45',
            'text_dark' => '#1a1a1a',
            'light' => '#ffffff',
            'border' => '#e5ad45',
            'th_bg' => '#e5ad45',
            'th_text' => '#1a1a1a',
            'th_border' => '1.5px solid #e5ad45',
            'td_border' => '1px solid #e5ad45',
            'grand_bg' => '#e5ad45',
            'grand_border' => '1.5px solid #e5ad45',
            'grand_text' => '#1a1a1a',
            'sign_line' => '2.5px solid #e5ad45',
            'terms_color' => '#b45309'
        ],
        5 => [
            'name' => 'Emerald Corporate',
            'is_split_header' => false,
            'header_dark' => true,
            'header_bg' => '#064e3b',
            'header_border' => '3px solid #059669',
            'primary' => '#059669',
            'text_dark' => '#064e3b',
            'light' => '#f0fdf4',
            'border' => '#bbf7d0',
            'th_bg' => '#064e3b',
            'th_text' => '#ffffff',
            'th_border' => '1px solid #064e3b',
            'td_border' => '1px solid #bbf7d0',
            'grand_bg' => '#f0fdf4',
            'grand_border' => '2px solid #059669',
            'grand_text' => '#064e3b',
            'sign_line' => '2px solid #059669',
            'terms_color' => '#059669'
        ],
        6 => [
            'name' => 'Royal Crimson',
            'is_split_header' => false,
            'header_dark' => true,
            'header_bg' => '#881337',
            'header_border' => '3px solid #be123c',
            'primary' => '#be123c',
            'text_dark' => '#881337',
            'light' => '#fff1f2',
            'border' => '#fecdd3',
            'th_bg' => '#fff1f2',
            'th_text' => '#881337',
            'th_border' => '1px solid #fecdd3',
            'td_border' => '1px solid #fecdd3',
            'grand_bg' => '#fff1f2',
            'grand_border' => '2px solid #be123c',
            'grand_text' => '#881337',
            'sign_line' => '2px solid #be123c',
            'terms_color' => '#be123c'
        ]
    ];
    $th = $themes[$tplId] ?? $themes[1];

    $isCompanyGst = (!empty($company) && isset($company->gst_applicable)) ? ((int)$company->gst_applicable === 1) : true;
    $isGst = ($isCompanyGst && ($q->gst_type ?? 'with_gst') === 'with_gst');
    $hasCgstSgst = ($isGst && (($q->cgst_amount ?? 0) > 0 || ($q->sgst_amount ?? 0) > 0));
    $hasIgst = ($isGst && ($q->igst_amount ?? 0) > 0);
    $amountInWords = getIndianCurrencyWordsForMpdf(floatval($q->grand_total ?? 0));

    // Logo
    $logoHtml = '';
    if (!empty($company->logo)) {
        $logoPath = dirname(__DIR__) . '/' . ltrim($company->logo, '/');
        if (file_exists($logoPath)) {
            $logoHtml = '<img src="' . str_replace('\\', '/', $logoPath) . '" style="max-height: 52px; max-width: 190px; margin-bottom: 6px;" /><br/>';
        }
    }

    // Signature
    $signHtml = '';
    if (!empty($company->signature)) {
        $signPath = dirname(__DIR__) . '/' . ltrim($company->signature, '/');
        if (file_exists($signPath)) {
            $signHtml = '<img src="' . str_replace('\\', '/', $signPath) . '" style="max-height: 48px; max-width: 150px; margin-bottom: 4px;" /><br/>';
        }
    }

    $terms = !empty($q->terms_condition) ? nl2br(htmlspecialchars($q->terms_condition)) : "1. Goods once sold will not be taken back.<br>2. Interest @ 18% p.a. will be charged if payment is not made within due date.<br>3. Subject to local jurisdiction.";

    $headerTextTitle = $th['header_dark'] ? '#ffffff' : $th['primary'];
    $headerTextSub = $th['header_dark'] ? $th['light'] : '#475569';
    $headerMetaVal = $th['header_dark'] ? '#ffffff' : '#0f172a';

    $partySectionLabel1 = ($docType === 'purchase') ? 'PURCHASED BY / BUYER' : 'BILLED BY / SUPPLIER';
    $partySectionLabel2 = ($docType === 'purchase') ? 'SUPPLIER / VENDOR' : 'BILLED TO / CUSTOMER';

    ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($docTitle) ?> - <?= htmlspecialchars($docNumber) ?></title>
<style>
    @page {
        margin-top: 10mm;
        margin-bottom: 44mm;
        margin-left: 10mm;
        margin-right: 10mm;
        footer: html_docFooter;
    }
    body {
        font-family: 'Helvetica', 'Arial', sans-serif;
        color: #1e293b;
        font-size: 13px;
        line-height: 1.45;
        margin: 0;
        padding: 0;
    }

    /* Standard Header for Templates 1, 2, 3, 5, 6 and Simple Purchase */
    .header-table {
        width: 100%;
        border-collapse: collapse;
        background-color: <?= $th['header_bg'] ?>;
        border-bottom: <?= $th['header_border'] ?>;
        padding: 16px 20px;
        margin-bottom: 16px;
    }
    .header-table td {
        vertical-align: top;
        padding: 18px 22px;
    }
    .comp-title {
        font-size: 23px;
        font-weight: 800;
        color: <?= $headerTextTitle ?>;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .comp-sub {
        font-size: 12px;
        color: <?= $headerTextSub ?>;
        line-height: 1.4;
    }
    .doc-title {
        font-size: 30px;
        font-weight: 900;
        color: <?= $headerTextTitle ?>;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        margin-bottom: 6px;
    }
    .doc-meta {
        font-size: 12.5px;
        color: <?= $headerTextSub ?>;
        line-height: 1.5;
    }
    .doc-meta strong {
        color: <?= $headerMetaVal ?>;
        font-weight: bold;
    }

    /* Distinct Split Header for Template 4 (Golden Executive - for Quotation/Invoice) */
    .golden-header-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 16px;
    }
    .golden-header-left {
        width: 58%;
        background-color: #1a1a1a;
        color: #ffffff;
        padding: 18px 22px;
        vertical-align: middle;
    }
    .golden-header-right {
        width: 42%;
        background-color: #e5ad45;
        color: #1a1a1a;
        padding: 18px 22px;
        text-align: right;
        vertical-align: middle;
    }
    .golden-title {
        font-size: 34px;
        font-weight: 900;
        letter-spacing: 2px;
        color: #ffffff;
        text-transform: uppercase;
        margin: 0;
        line-height: 1.1;
    }
    .golden-title-line {
        width: 140px;
        height: 2px;
        background-color: #ffffff;
        margin: 6px 0 10px 0;
    }
    .golden-meta-table {
        border-collapse: collapse;
    }
    .golden-meta-table td {
        padding: 2px 0;
        font-size: 12.5px;
        color: #d1d5db;
        vertical-align: middle;
    }
    .golden-meta-label {
        width: 110px;
        color: #9ca3af;
    }
    .golden-meta-colon {
        width: 12px;
        text-align: center;
        color: #9ca3af;
    }
    .golden-meta-val {
        color: #ffffff;
        font-weight: bold;
    }
    .golden-company-name {
        font-size: 24px;
        font-weight: 900;
        color: #1a1a1a;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        line-height: 1.2;
    }
    .golden-company-detail {
        font-size: 12px;
        color: #27272a;
        line-height: 1.35;
        margin-top: 3px;
    }

    /* Parties Section */
    .parties-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 12px 0;
        margin-bottom: 16px;
    }
    .party-card {
        width: 50%;
        border: 1.5px solid <?= $th['border'] ?>;
        background-color: <?= $th['light'] ?>;
        padding: 12px 14px;
        vertical-align: top;
        border-radius: 4px;
        font-size: 12px;
        line-height: 1.45;
    }
    .party-label {
        font-size: 11.5px;
        font-weight: bold;
        color: <?= $th['primary'] ?>;
        text-transform: uppercase;
        margin-bottom: 6px;
        letter-spacing: 0.5px;
    }
    .party-name {
        font-size: 15px;
        font-weight: 800;
        color: <?= $th['text_dark'] ?>;
        margin-bottom: 3px;
    }

    /* Products Table */
    .items-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
    }
    .items-table th {
        background-color: <?= $th['th_bg'] ?>;
        color: <?= $th['th_text'] ?>;
        border: <?= $th['th_border'] ?>;
        padding: 9px 8px;
        font-size: 11.5px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        text-align: center;
    }
    .items-table td {
        border: <?= $th['td_border'] ?>;
        padding: 7px 8px;
        font-size: 12px;
        vertical-align: middle;
    }

    /* Calculations & Summary Section */
    .calc-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 6px;
    }
    .words-box {
        border: none !important;
        background: transparent !important;
        padding: 0 !important;
    }
    .words-label {
        font-size: 12px;
        font-weight: 900;
        color: <?= $th['primary'] ?>;
        margin-bottom: 3px;
        letter-spacing: 0.5px;
        border: none !important;
    }
    .words-val {
        font-size: 13.5px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.45;
        border: none !important;
    }
    .totals-table {
        width: 100%;
        border-collapse: collapse;
    }
    .totals-table td {
        padding: 3.5px 6px;
        font-size: 12.5px;
    }
    .totals-table .lbl {
        color: #475569;
        text-align: left;
    }
    .totals-table .val {
        text-align: right;
        font-weight: bold;
        color: #0f172a;
    }
    .grand-strip {
        background-color: <?= $th['grand_bg'] ?>;
        border: <?= $th['grand_border'] ?>;
        padding: 8px 16px;
        margin-top: 6px;
        border-radius: 4px;
    }
    .grand-strip td {
        padding: 4px 0;
    }
    .grand-lbl {
        font-size: 15px;
        font-weight: 900;
        color: <?= $th['grand_text'] ?>;
    }
    .grand-val {
        font-size: 17.5px;
        font-weight: 900;
        color: <?= $th['grand_text'] ?>;
        text-align: right;
    }

    /* Fixed Last Footer */
    .footer-wrapper {
        width: 100%;
        border-top: 1.5px dashed #cbd5e1;
        padding-top: 8px;
    }
    .footer-table {
        width: 100%;
        border-collapse: collapse;
    }
    .footer-table td {
        vertical-align: bottom;
    }
    .terms-box {
        width: 60%;
        font-size: 11px;
        color: #64748b;
        line-height: 1.45;
        padding-right: 20px;
    }
    .terms-lbl {
        font-size: 11.5px;
        font-weight: bold;
        color: <?= $th['terms_color'] ?>;
        text-transform: uppercase;
        margin-bottom: 5px;
        letter-spacing: 0.5px;
    }
    .sign-box {
        width: 40%;
        text-align: center;
    }
    .sign-line {
        border-top: <?= $th['sign_line'] ?>;
        padding-top: 5px;
        font-size: 13.5px;
        font-weight: bold;
        color: #0f172a;
        margin-top: 2px;
    }
    .sign-sub {
        font-size: 11.5px;
        color: #64748b;
    }
</style>
</head>
<body>

<?php if ($th['is_split_header']): ?>
<!-- Split Header for Golden Executive (Template 4) -->
<table class="golden-header-table">
    <tr>
        <td class="golden-header-left">
            <div class="golden-title"><?= htmlspecialchars($docTitle) ?></div>
            <div class="golden-title-line"></div>
            <table class="golden-meta-table">
                <tr>
                    <td class="golden-meta-label"><?= $noLabel ?></td>
                    <td class="golden-meta-colon">:</td>
                    <td class="golden-meta-val"><?= htmlspecialchars($docNumber) ?></td>
                </tr>
                <tr>
                    <td class="golden-meta-label"><?= $dateLabel ?></td>
                    <td class="golden-meta-colon">:</td>
                    <td class="golden-meta-val"><?= $docDate ?></td>
                </tr>
                <?php if (!empty($dueDate)): ?>
                <tr>
                    <td class="golden-meta-label">Due Date</td>
                    <td class="golden-meta-colon">:</td>
                    <td class="golden-meta-val"><?= $dueDate ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </td>
        <td class="golden-header-right">
            <?= $logoHtml ?>
            <div class="golden-company-name"><?= htmlspecialchars($company->company_name ?? 'COMPANY NAME') ?></div>
            <div class="golden-company-detail"><?= htmlspecialchars($company->address ?? '') ?></div>
            <?php if (!empty($company->gst_no)): ?>
                <div class="golden-company-detail" style="font-weight: bold; margin-top: 2px;">GSTIN: <?= htmlspecialchars($company->gst_no) ?></div>
            <?php endif; ?>
        </td>
    </tr>
</table>
<?php else: ?>
<!-- Standard Header for Simple Purchase & Templates 1, 2, 3, 5, 6 -->
<table class="header-table">
    <tr>
        <td style="width: 55%;">
            <?= $logoHtml ?>
            <div class="comp-title"><?= htmlspecialchars($company->company_name ?? 'COMPANY NAME') ?></div>
            <div class="comp-sub"><?= htmlspecialchars($company->address ?? '') ?></div>
            <?php if (!empty($company->gst_no)): ?>
                <div class="comp-sub"><strong>GSTIN:</strong> <?= htmlspecialchars($company->gst_no) ?></div>
            <?php endif; ?>
        </td>
        <td style="width: 45%; text-align: right;">
            <div class="doc-title"><?= htmlspecialchars($docTitle) ?></div>
            <div class="doc-meta"><?= $noLabel ?>: <strong><?= htmlspecialchars($docNumber) ?></strong></div>
            <div class="doc-meta"><?= $dateLabel ?>: <strong><?= $docDate ?></strong></div>
            <?php if (!empty($dueDate)): ?>
                <div class="doc-meta">Due Date: <strong><?= $dueDate ?></strong></div>
            <?php endif; ?>
        </td>
    </tr>
</table>
<?php endif; ?>

<!-- Parties Section -->
<table class="parties-table">
    <tr>
        <td class="party-card">
            <div class="party-label"><?= $partySectionLabel1 ?></div>
            <div class="party-name"><?= htmlspecialchars($company->company_name ?? '') ?></div>
            <div><?= htmlspecialchars($company->address ?? '') ?></div>
            <div>Phone: <?= htmlspecialchars($company->mobile_no ?? 'N/A') ?></div>
            <?php if (!empty($company->email)): ?>
                <div>Email: <?= htmlspecialchars($company->email) ?></div>
            <?php endif; ?>
        </td>
        <td class="party-card">
            <div class="party-label"><?= $partySectionLabel2 ?></div>
            <div class="party-name"><?= htmlspecialchars($q->party_name ?? '') ?></div>
            <div><?= nl2br(htmlspecialchars($q->address ?? '')) ?></div>
            <div>Phone: <?= htmlspecialchars($party->mobile_no ?? 'N/A') ?></div>
            <?php if (!empty($party->gst_no)): ?>
                <div><strong>GSTIN:</strong> <?= htmlspecialchars($party->gst_no) ?></div>
            <?php endif; ?>
        </td>
    </tr>
</table>

<!-- Products Table -->
<table class="items-table">
    <thead>
        <tr>
            <th style="width: 4%;">#</th>
            <th style="width: 32%; text-align: left;">Item Description</th>
            <th style="width: 10%;">HSN/SAC</th>
            <th style="width: 10%; text-align: right;">Rate</th>
            <th style="width: 7%; text-align: right;">Qty</th>
            <th style="width: 9%; text-align: right;">Discount</th>
            <th style="width: 13%; text-align: right;">Taxable Amt</th>
            <?php if ($isGst): ?>
                <th style="width: 6%; text-align: center;">GST</th>
            <?php endif; ?>
            <th style="width: 13%; text-align: right;">Total</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $sr = 1;
        $totalTaxableCalc = 0;
        if (!empty($items)) {
            foreach ($items as $it): 
                $rate = floatval($it->rate ?? 0);
                $qty = floatval($it->qty ?? 1);
                $gross = $rate * $qty;
                $discVal = floatval($it->discount_value ?? 0);
                $discAmt = floatval($it->discount_amount ?? 0);
                $discType = $it->discount_type ?? 'percentage';
                $discText = '-';
                if ($discAmt > 0 || $discVal > 0) {
                    $discText = ($discType === 'fixed') ? '₹' . number_format($discVal, 2) : ($discVal . '%');
                }
                $net = floatval($it->net_amount ?? ($gross - $discAmt));
                $totalTaxableCalc += $net;
                $gstPct = floatval($it->gst_percent ?? 0);
                $tot = floatval($it->total_amount ?? ($net + floatval($it->tax_amount ?? 0)));
        ?>
        <tr>
            <td style="text-align: center;"><?= $sr++ ?></td>
            <td>
                <span style="font-size: 13.5px; font-weight: bold;"><?= htmlspecialchars($it->product_name ?? ($it->description ?? '')) ?></span>
                <?php if (!empty($it->sku)): ?>
                    <div style="font-size: 11px; color: #64748b; margin-top: 2px;">SKU: <?= htmlspecialchars($it->sku) ?></div>
                <?php endif; ?>
                <?php if (!empty($it->description) && $it->description !== ($it->product_name ?? '')): ?>
                    <div style="font-size: 11px; color: #64748b; margin-top: 2px;"><?= htmlspecialchars($it->description) ?></div>
                <?php endif; ?>
            </td>
            <td style="text-align: center;"><?= htmlspecialchars($it->hsn_code ?? '-') ?></td>
            <td style="text-align: right;">₹<?= number_format($rate, 2) ?></td>
            <td style="text-align: right;"><?= number_format($qty, 2) ?></td>
            <td style="text-align: right;"><?= $discText ?></td>
            <td style="text-align: right;">₹<?= number_format($net, 2) ?></td>
            <?php if ($isGst): ?>
                <td style="text-align: center;"><?= $gstPct ?>%</td>
            <?php endif; ?>
            <td style="text-align: right; font-weight: bold;">₹<?= number_format($tot, 2) ?></td>
        </tr>
        <?php endforeach; 
        } ?>
    </tbody>
</table>

<!-- Calculation & Totals -->
<table class="calc-table">
    <tr>
        <td style="width: 55%; padding-right: 14px; vertical-align: top;">
            <div class="words-box" style="border: none !important; background: transparent !important; padding: 0 !important;">
                <div class="words-label" style="border: none !important;">AMOUNT IN WORDS:</div>
                <div class="words-val" style="border: none !important;"><?= htmlspecialchars($amountInWords) ?></div>
            </div>
        </td>
        <td style="width: 45%;">
            <table class="totals-table">
                <tr>
                    <td class="lbl">Sub Total/Taxable:</td>
                    <td class="val">₹<?= number_format($totalTaxableCalc, 2) ?></td>
                </tr>
                <?php if ($hasCgstSgst): ?>
                <tr>
                    <td class="lbl">CGST Amount:</td>
                    <td class="val">₹<?= number_format(floatval($q->cgst_amount ?? 0), 2) ?></td>
                </tr>
                <tr>
                    <td class="lbl">SGST Amount:</td>
                    <td class="val">₹<?= number_format(floatval($q->sgst_amount ?? 0), 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($hasIgst): ?>
                <tr>
                    <td class="lbl">IGST Amount:</td>
                    <td class="val">₹<?= number_format(floatval($q->igst_amount ?? 0), 2) ?></td>
                </tr>
                <?php endif; ?>
            </table>
            <table class="totals-table grand-strip">
                <tr>
                    <td class="grand-lbl">Grand Total:</td>
                    <td class="grand-val">₹<?= number_format(floatval($q->grand_total ?? 0), 2) ?></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Pinned HTML Page Footer at the very bottom of A4 -->
<htmlpagefooter name="docFooter">
    <div class="footer-wrapper">
        <table class="footer-table">
            <tr>
                <td class="terms-box">
                    <div class="terms-lbl">TERMS & CONDITIONS</div>
                    <div style="font-size: 11px; color: #64748b; line-height: 1.45;"><?= $terms ?></div>
                </td>
                <td class="sign-box">
                    <?= $signHtml ?>
                    <div class="sign-line">For <?= htmlspecialchars($company->company_name ?? 'Company') ?></div>
                    <div class="sign-sub">Authorized Signatory</div>
                </td>
            </tr>
        </table>
    </div>
</htmlpagefooter>

</body>
</html>
<?php
    $html = ob_get_clean();

    $uploadDir = dirname(__DIR__) . '/uploads/' . $folderName . '/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }
    $tempDir = dirname(__DIR__) . '/uploads/temp/';
    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0777, true);
    }

    $formattedId = str_pad($q->id, 2, '0', STR_PAD_LEFT);
    $cleanNo = preg_replace('/[^a-zA-Z0-9_]/', '_', $docNumber);
    $fileName = $filePrefix . $formattedId . '_' . $cleanNo . '.pdf';
    $targetFile = $uploadDir . $fileName;

    try {
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 44,
            'margin_footer' => 8,
            'tempDir' => $tempDir
        ]);
        $mpdf->WriteHTML($html);
        $mpdf->Output($targetFile, \Mpdf\Output\Destination::FILE);

        if (file_exists($targetFile) && filesize($targetFile) > 1000) {
            $relPath = 'uploads/' . $folderName . '/' . $fileName;
            $safeRel = addslashes($relPath);
            @$ai_db->aiQuery("UPDATE {$mainTable} SET pdf_url='$safeRel' WHERE id='$docId'");
            return $relPath;
        }
    } catch (\Exception $e) {
        error_log("mPDF {$docType} Error: " . $e->getMessage());
        return false;
    }

    return false;
}

/**
 * Backward compatibility wrapper for Quotation module
 */
function generateQuotationPdfMpdf($quotationId, $forceTemplateId = null) {
    return generateDocumentPdfMpdf($quotationId, 'quotation', $forceTemplateId);
}

/**
 * Backward compatibility wrapper for Invoice module
 */
function generateInvoicePdfMpdf($invoiceId, $forceTemplateId = null) {
    return generateDocumentPdfMpdf($invoiceId, 'invoice', $forceTemplateId);
}

/**
 * Universal Server-Side PDF Generator
 * Quotation & Invoice: Uses company's dynamic template (1 to 6)
 * Purchase: Uses clean simple standard format (no dynamic template)
 */
function generateDocumentPdfServerSide($docId, $docType = 'quotation')
{
    $docId = intval($docId);
    if ($docId <= 0) return false;

    return generateDocumentPdfMpdf($docId, $docType);
}
