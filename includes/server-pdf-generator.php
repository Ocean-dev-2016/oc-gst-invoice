<?php
// Function to generate and save PDF for quotation, invoice, or purchase
function generateDocumentPdfServerSide($docId, $docType = 'quotation')
{
    $docId = intval($docId);
    if ($docId <= 0) return false;

    // Check if shell execution is disabled in PHP configuration (common on shared cPanel hosting)
    $disabledFunctions = explode(',', (string)ini_get('disable_functions'));
    $disabledFunctions = array_map('trim', $disabledFunctions);
    if (in_array('exec', $disabledFunctions, true) || !function_exists('exec')) {
        // exec is disabled on this server
        return false;
    }

    // Detect Chrome/Edge binary depending on OS (Windows or Linux server)
    $chromePath = '';
    if (PHP_OS_FAMILY === 'Windows') {
        $winCandidates = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe'
        ];
        foreach ($winCandidates as $cand) {
            if (file_exists($cand)) {
                $chromePath = $cand;
                break;
            }
        }
    } else {
        // Linux / Unix server candidates
        $linuxCandidates = [
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/snap/bin/chromium',
            '/usr/local/bin/chrome',
            '/usr/local/bin/chromium'
        ];
        foreach ($linuxCandidates as $cand) {
            if (file_exists($cand) && is_executable($cand)) {
                $chromePath = $cand;
                break;
            }
        }
        if (empty($chromePath) && function_exists('shell_exec') && !in_array('shell_exec', $disabledFunctions, true)) {
            // Try 'which' command on Linux only if shell_exec is allowed
            $whichOutput = @shell_exec('which google-chrome 2>/dev/null || which chromium 2>/dev/null || which chromium-browser 2>/dev/null');
            if (!empty($whichOutput)) {
                $trimmed = trim($whichOutput);
                if (file_exists($trimmed)) {
                    $chromePath = $trimmed;
                }
            }
        }
    }

    if (empty($chromePath)) {
        // No Chrome/Chromium/Edge binary found on server (e.g. shared cPanel)
        return false;
    }

    global $ai_db;
    $targetFile = '';
    $targetFolder = 'invoices';
    $baseUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') . '/' : 'http://localhost/OC-GST-invoice/';

    if ($docType === 'quotation') {
        $targetFolder = 'quotation';
        $row = $ai_db->aiGetQueryObj("SELECT id, quotation_no as doc_no, company_id FROM tbl_quotation WHERE id='$docId' LIMIT 1");
        if (empty($row)) return false;
        $doc = $row[0];
        $formattedId = str_pad($doc->id, 2, '0', STR_PAD_LEFT);
        $cleanNo = preg_replace('/[^a-zA-Z0-9_]/', '_', $doc->doc_no ?? $doc->id);
        $fileName = 'quotation_' . $formattedId . '_' . $cleanNo . '.pdf';
        $printUrl = $baseUrl . 'quotation-print.php?id=' . $docId . '&token=system_auto_pdf';
    } elseif ($docType === 'purchase') {
        $targetFolder = 'purchase';
        $row = $ai_db->aiGetQueryObj("SELECT id, purchase_no as doc_no, company_id FROM tbl_purchase WHERE id='$docId' LIMIT 1");
        if (empty($row)) return false;
        $doc = $row[0];
        $cleanNo = preg_replace('/[^a-zA-Z0-9_]/', '_', $doc->doc_no ?? $doc->id);
        $fileName = 'purchase_' . $doc->id . '_' . $cleanNo . '.pdf';
        $printUrl = $baseUrl . 'purchase-print.php?id=' . $docId . '&token=system_auto_pdf';
    } else {
        $targetFolder = 'invoices';
        $row = $ai_db->aiGetQueryObj("SELECT id, invoice_no as doc_no, company_id FROM tbl_invoice WHERE id='$docId' LIMIT 1");
        if (empty($row)) return false;
        $doc = $row[0];
        $cleanNo = preg_replace('/[^a-zA-Z0-9_]/', '_', $doc->doc_no ?? $doc->id);
        $fileName = 'invoice_' . $doc->id . '_' . $cleanNo . '.pdf';
        $printUrl = $baseUrl . 'invoice-print.php?id=' . $docId . '&token=system_auto_pdf';
    }

    $uploadDir = __DIR__ . '/../uploads/' . $targetFolder . '/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }
    $targetFile = $uploadDir . $fileName;

    // Run Headless Chrome to generate the PDF directly from the template
    $cmd = escapeshellarg($chromePath) . ' --headless=new --disable-gpu --no-sandbox --disable-dev-shm-usage --print-to-pdf-no-header --run-all-compositor-stages-before-draw --virtual-time-budget=3000 --print-to-pdf=' . escapeshellarg($targetFile) . ' ' . escapeshellarg($printUrl);

    // Execute command safely
    $output = [];
    $returnVar = 0;
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
