<?php
require_once __DIR__ . '/root/config.php';

// Accept doc_type ('invoice', 'quotation', 'purchase') and document ID
$docType = strtolower(trim($_GET['type'] ?? ($_GET['doc_type'] ?? 'invoice')));
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    die('Invalid document ID.');
}

if (!in_array($docType, ['invoice', 'quotation', 'purchase'], true)) {
    $docType = 'invoice';
}

global $ai_db;
$doc = null;
$folder = 'invoices';
$redirectPrint = 'invoice-print.php?id=' . $id . '&store_and_show=1';
$filePrefix = 'invoice_';

if ($docType === 'quotation') {
    $folder = 'quotation';
    $redirectPrint = 'quotation-print.php?id=' . $id . '&store_and_show=1';
    $row = $ai_db->aiGetQueryObj("SELECT id, quotation_no as doc_no, pdf_url FROM tbl_quotation WHERE id='$id' LIMIT 1");
    if (!empty($row)) {
        $doc = $row[0];
        $formattedId = str_pad($doc->id, 2, '0', STR_PAD_LEFT);
        $cleanNo = preg_replace('/[^a-zA-Z0-9_]/', '_', $doc->doc_no ?? $doc->id);
        $expectedFile = 'quotation_' . $formattedId . '_' . $cleanNo . '.pdf';
    }
} elseif ($docType === 'purchase') {
    $folder = 'purchase';
    $redirectPrint = 'purchase-print.php?id=' . $id . '&store_and_show=1';
    $row = $ai_db->aiGetQueryObj("SELECT id, purchase_no as doc_no, pdf_url FROM tbl_purchase WHERE id='$id' LIMIT 1");
    if (!empty($row)) {
        $doc = $row[0];
        $formattedId = str_pad($doc->id, 2, '0', STR_PAD_LEFT);
        $cleanNo = preg_replace('/[^a-zA-Z0-9_]/', '_', $doc->doc_no ?? $doc->id);
        $expectedFile = !empty($doc->pdf_url) ? basename($doc->pdf_url) : ('purchase_' . $formattedId . '_' . $cleanNo . '.pdf');
    }
} else {
    $folder = 'invoices';
    $redirectPrint = 'invoice-print.php?id=' . $id . '&store_and_show=1';
    $row = $ai_db->aiGetQueryObj("SELECT id, invoice_no as doc_no, pdf_url FROM tbl_invoice WHERE id='$id' LIMIT 1");
    if (!empty($row)) {
        $doc = $row[0];
        $cleanNo = preg_replace('/[^a-zA-Z0-9_]/', '_', $doc->doc_no ?? $doc->id);
        $expectedFile = 'invoice_' . $doc->id . '_' . $cleanNo . '.pdf';
    }
}

if (empty($doc)) {
    http_response_code(404);
    die('Document not found.');
}

// 1. Check if physical PDF already exists on disk
$pdfRel = !empty($doc->pdf_url) ? $doc->pdf_url : ('uploads/' . $folder . '/' . ($expectedFile ?? 'doc.pdf'));
$diskPath = __DIR__ . '/' . ltrim($pdfRel, '/');

if (file_exists($diskPath) && filesize($diskPath) > 500) {
    // Serve the PDF file directly to the browser
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($diskPath) . '"');
    header('Content-Length: ' . filesize($diskPath));
    header('Cache-Control: private, max-age=3600, must-revalidate');
    readfile($diskPath);
    exit;
}

// 2. Try server-side generation if possible
require_once __DIR__ . '/includes/server-pdf-generator.php';
$genRel = generateDocumentPdfServerSide($id, $docType);
if ($genRel) {
    $genDisk = __DIR__ . '/' . ltrim($genRel, '/');
    if (file_exists($genDisk) && filesize($genDisk) > 500) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($genDisk) . '"');
        header('Content-Length: ' . filesize($genDisk));
        readfile($genDisk);
        exit;
    }
}

// 3. Fallback: If no server-side binary exists (e.g. standard Linux cPanel),
// redirect directly to the HTML print template which will automatically generate & save the PDF!
header('Location: ' . $redirectPrint);
exit;

