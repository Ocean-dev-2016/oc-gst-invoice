<?php
// Function to generate and save PDF for quotation, invoice, or purchase
function generateDocumentPdfServerSide($docId, $docType = 'quotation') {
    $docId = intval($docId);
    if ($docId <= 0) return false;

    // Path to Chrome or Edge
    $chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
    if (!file_exists($chromePath)) {
        $chromePath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
    }
    if (!file_exists($chromePath)) {
        error_log("PDF Generator: No Chrome/Edge binary found.");
        return false;
    }

    global $ai_db;
    $targetFile = '';
    $targetFolder = 'invoices';
    if ($docType === 'quotation') {
        $targetFolder = 'quotation';
        $row = $ai_db->aiGetQueryObj("SELECT id, quotation_no as doc_no, company_id FROM tbl_quotation WHERE id='$docId' LIMIT 1");
        if (empty($row)) return false;
        $doc = $row[0];
        $formattedId = str_pad($doc->id, 2, '0', STR_PAD_LEFT);
        $cleanNo = preg_replace('/[^a-zA-Z0-9_]/', '_', $doc->doc_no ?? $doc->id);
        $fileName = 'quotation_' . $formattedId . '_' . $cleanNo . '.pdf';
        $printUrl = 'http://localhost/OC-GST-invoice/quotation-print.php?id=' . $docId . '&token=system_auto_pdf';
    } elseif ($docType === 'purchase') {
        $targetFolder = 'purchase';
        $row = $ai_db->aiGetQueryObj("SELECT id, purchase_no as doc_no, company_id FROM tbl_purchase WHERE id='$docId' LIMIT 1");
        if (empty($row)) return false;
        $doc = $row[0];
        $cleanNo = preg_replace('/[^a-zA-Z0-9_]/', '_', $doc->doc_no ?? $doc->id);
        $fileName = 'purchase_' . $doc->id . '_' . $cleanNo . '.pdf';
        $printUrl = 'http://localhost/OC-GST-invoice/purchase-print.php?id=' . $docId . '&token=system_auto_pdf';
    } else {
        $targetFolder = 'invoices';
        $row = $ai_db->aiGetQueryObj("SELECT id, invoice_no as doc_no, company_id FROM tbl_invoice WHERE id='$docId' LIMIT 1");
        if (empty($row)) return false;
        $doc = $row[0];
        $cleanNo = preg_replace('/[^a-zA-Z0-9_]/', '_', $doc->doc_no ?? $doc->id);
        $fileName = 'invoice_' . $doc->id . '_' . $cleanNo . '.pdf';
        $printUrl = 'http://localhost/OC-GST-invoice/invoice-print.php?id=' . $docId . '&token=system_auto_pdf';
    }

    $uploadDir = __DIR__ . '/../uploads/' . $targetFolder . '/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }
    $targetFile = $uploadDir . $fileName;

    // Run Headless Chrome to generate the PDF directly from the template
    $cmd = '"' . $chromePath . '" --headless=new --disable-gpu --no-sandbox --print-to-pdf-no-header --run-all-compositor-stages-before-draw --virtual-time-budget=3000 --print-to-pdf="' . $targetFile . '" "' . $printUrl . '"';
    
    // Execute command
    @exec($cmd, $output, $returnVar);

    if (file_exists($targetFile) && filesize($targetFile) > 1000) {
        $relPath = 'uploads/' . $targetFolder . '/' . $fileName;
        $safeRel = addslashes($relPath);
        if ($docType === 'quotation') {
            @$ai_db->aiQuery("UPDATE tbl_quotation SET pdf_url='$safeRel' WHERE id='$docId'");
        } elseif ($docType === 'purchase') {
            @$ai_db->aiQuery("UPDATE tbl_purchase SET pdf_url='$safeRel' WHERE id='$docId'");
        } else {
            @$ai_db->aiQuery("UPDATE tbl_invoice SET pdf_url='$safeRel' WHERE id='$docId'");
        }
        return $relPath;
    }

    return false;
}

