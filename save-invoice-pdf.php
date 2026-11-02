<?php
require_once __DIR__ . '/root/config.php';

// Check login
if (empty($_SESSION['aid'])) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Method not allowed.']);
    exit;
}

$invoiceId = intval($_POST['invoice_id'] ?? 0);
if ($invoiceId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Invalid invoice ID.']);
    exit;
}

// Fetch document from tbl_invoice, tbl_quotation, or tbl_purchase
$docType = $_POST['doc_type'] ?? 'invoice';
$inv = null;
$cleanDocNo = '';

if ($docType === 'quotation') {
    $qRow = $ai_db->aiGetQueryObj("SELECT id, quotation_no as doc_no, company_id FROM tbl_quotation WHERE id='$invoiceId' LIMIT 1");
    if (!empty($qRow)) {
        $inv = $qRow[0];
        $formattedId = str_pad($inv->id, 2, '0', STR_PAD_LEFT);
        $cleanNo = preg_replace('/[^a-zA-Z0-9_]/', '_', $inv->doc_no ?? $inv->id);
        $cleanDocNo = 'quotation_' . $formattedId . '_' . $cleanNo;
    }
} elseif ($docType === 'purchase') {
    $pRow = $ai_db->aiGetQueryObj("SELECT id, purchase_no as doc_no, company_id FROM tbl_purchase WHERE id='$invoiceId' LIMIT 1");
    if (!empty($pRow)) {
        $inv = $pRow[0];
        $cleanDocNo = 'purchase_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $inv->doc_no ?? 'purchase_' . $inv->id);
    }
}

if (empty($inv)) {
    // Default to invoice check
    $invRow = $ai_db->aiGetQueryObj("SELECT id, invoice_no as doc_no, company_id FROM tbl_invoice WHERE id='$invoiceId' LIMIT 1");
    if (!empty($invRow)) {
        $inv = $invRow[0];
        $cleanDocNo = 'invoice_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $inv->doc_no ?? 'invoice_' . $inv->id);
    }
}

if (empty($inv)) {
    http_response_code(404);
    echo json_encode(['status' => false, 'message' => 'Document not found.']);
    exit;
}

// Verify company permissions if user is company
if (($_SESSION['user_type'] ?? '') === 'company' && !empty($_SESSION['company_id'])) {
    if (intval($inv->company_id) !== intval($_SESSION['company_id'])) {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Access denied to this invoice.']);
        exit;
    }
}

// Target folder: uploads/invoices/
$uploadDir = __DIR__ . '/uploads/invoices/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

// Prepare file name
$fileName = $cleanDocNo . '.pdf';
$targetFilePath = $uploadDir . $fileName;
$relativeUrl = 'uploads/invoices/' . $fileName;

$saved = false;

// Case 1: Uploaded via multipart/form-data $_FILES['pdf_blob']
if (isset($_FILES['pdf_blob']) && $_FILES['pdf_blob']['error'] === UPLOAD_ERR_OK) {
    if (move_uploaded_file($_FILES['pdf_blob']['tmp_name'], $targetFilePath)) {
        $saved = true;
    }
}
// Case 2: Uploaded via raw POST binary body or base64 string
elseif (!empty($_POST['pdf_base64'])) {
    $base64 = $_POST['pdf_base64'];
    if (strpos($base64, 'base64,') !== false) {
        $base64 = explode('base64,', $base64)[1];
    }
    $decoded = base64_decode($base64);
    if ($decoded !== false && strlen($decoded) > 0) {
        if (file_put_contents($targetFilePath, $decoded) !== false) {
            $saved = true;
        }
    }
} else {
    // Try raw input php://input
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput) && strpos($rawInput, '%PDF') === 0) {
        if (file_put_contents($targetFilePath, $rawInput) !== false) {
            $saved = true;
        }
    }
}

if (!$saved || !file_exists($targetFilePath)) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Failed to save PDF file on server.']);
    exit;
}

// Store PDF URL / path in corresponding table (tbl_invoice, tbl_quotation, or tbl_purchase)
$safeRel = addslashes($relativeUrl);

if ($docType === 'quotation') {
    // Auto-heal column if not exists
    $chkColQ = mysqli_query($ai_conn, "SHOW COLUMNS FROM `tbl_quotation` LIKE 'pdf_url'");
    if (!$chkColQ || mysqli_num_rows($chkColQ) === 0) {
        @mysqli_query($ai_conn, "ALTER TABLE `tbl_quotation` ADD COLUMN `pdf_url` VARCHAR(255) NULL DEFAULT '' AFTER `status`");
    }
    @$ai_db->aiQuery("UPDATE tbl_quotation SET pdf_url='$safeRel' WHERE id='$invoiceId'");
} elseif ($docType === 'purchase') {
    $chkColP = mysqli_query($ai_conn, "SHOW COLUMNS FROM `tbl_purchase` LIKE 'pdf_url'");
    if (!$chkColP || mysqli_num_rows($chkColP) === 0) {
        @mysqli_query($ai_conn, "ALTER TABLE `tbl_purchase` ADD COLUMN `pdf_url` VARCHAR(255) NULL DEFAULT '' AFTER `status`");
    }
    @$ai_db->aiQuery("UPDATE tbl_purchase SET pdf_url='$safeRel' WHERE id='$invoiceId'");
} else {
    // Default invoice
    $chkColI = mysqli_query($ai_conn, "SHOW COLUMNS FROM `tbl_invoice` LIKE 'pdf_url'");
    if (!$chkColI || mysqli_num_rows($chkColI) === 0) {
        @mysqli_query($ai_conn, "ALTER TABLE `tbl_invoice` ADD COLUMN `pdf_url` VARCHAR(255) NULL DEFAULT '' AFTER `status`");
    }
    @$ai_db->aiQuery("UPDATE tbl_invoice SET pdf_url='$safeRel' WHERE id='$invoiceId'");

    // Backward compatibility if pdf_file column exists
    $chkOldCol = @$ai_db->aiGetQueryObj("SHOW COLUMNS FROM tbl_invoice LIKE 'pdf_file'");
    if (!empty($chkOldCol)) {
        @$ai_db->aiQuery("UPDATE tbl_invoice SET pdf_file='$safeRel' WHERE id='$invoiceId'");
    }
}

$fullFileUrl = SITE_URL . $relativeUrl;

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => true,
    'message' => 'PDF generated and stored successfully.',
    'pdf_url' => $fullFileUrl,
    'relative_url' => $relativeUrl,
    'file_name' => $fileName
]);
exit;
