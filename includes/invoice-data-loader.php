<?php
require_once __DIR__ . '/../root/config.php';

// Check login
if (empty($_SESSION['aid'])) {
    header('Location: index.php');
    exit;
}

// Check doc_type: 'invoice' (default), 'quotation', or 'purchase'
$docType = isset($docType) ? $docType : (isset($_GET['doc_type']) ? $_GET['doc_type'] : 'invoice');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isSamplePreview = (isset($_GET['sample_preview']) && (int)$_GET['sample_preview'] === 1);

if ($id <= 0 && !$isSamplePreview) {
    die("Invalid " . ucfirst($docType) . " ID!");
}

if ($isSamplePreview) {
    // Generate clean Dummy / Sample Data for Template Preview
    $company_id = isset($_GET['company_id']) ? intval($_GET['company_id']) : 0;
    if ($company_id > 0) {
        $compRow = $ai_db->aiGetQueryObj("SELECT * FROM tbl_company WHERE id='$company_id' LIMIT 1");
        $company = !empty($compRow) ? $compRow[0] : null;
    } else {
        $compRow = $ai_db->aiGetQueryObj("SELECT * FROM tbl_company LIMIT 1");
        $company = !empty($compRow) ? $compRow[0] : null;
    }

    $q = (object)[
        'id' => 0,
        'invoice_no' => 'INV-SAMPLE-001',
        'quotation_no' => 'QUO-SAMPLE-001',
        'bill_no' => 'PUR-SAMPLE-001',
        'invoice_date' => date('Y-m-d'),
        'quotation_date' => date('Y-m-d'),
        'bill_date' => date('Y-m-d'),
        'due_date' => date('Y-m-d', strtotime('+15 days')),
        'gst_type' => 'with_gst',
        'rca' => 'No',
        'party_name' => 'Demo Customer / Business Name',
        'address' => 'Shop 12, Business Commercial Hub, M.G. Road',
        'state_name' => 'Gujarat',
        'city_name' => 'Ahmedabad',
        'pincode' => '380001',
        'total_amount' => 10000.00,
        'tax_amount' => 1800.00,
        'cgst_amount' => 900.00,
        'sgst_amount' => 900.00,
        'igst_amount' => 0.00,
        'grand_total' => 11800.00,
        'paid_amount' => 0.00,
        'payment_status' => 'Pending',
        'terms_condition' => '1. Goods once sold will not be taken back.<br>2. Subject to local jurisdiction.'
    ];

    $party = (object)[
        'party_name' => 'Demo Customer / Business Name',
        'address' => 'Shop 12, Business Commercial Hub, M.G. Road',
        'mobile_no' => '+91 98765 43210',
        'email' => 'sample.customer@example.com',
        'gst_no' => '24ABCDE1234F1Z5',
        'state_name' => 'Gujarat',
        'city_name' => 'Ahmedabad',
        'pincode' => '380001',
        'shipping_address' => 'Shop 12, Business Commercial Hub, M.G. Road',
        'shipping_pincode' => '380001'
    ];

    $items = [
        (object)[
            'product_name' => 'Sample Product / Service Item 1',
            'sku' => 'PRD-001',
            'description' => 'Standard high quality business goods or service',
            'hsn_code' => '8471',
            'qty' => 2,
            'rate' => 3000.00,
            'discount_type' => 'percentage',
            'discount_value' => 0,
            'discount_amount' => 0,
            'net_amount' => 6000.00,
            'gst_percent' => 18,
            'tax_amount' => 1080.00,
            'total_amount' => 7080.00
        ],
        (object)[
            'product_name' => 'Sample Product / Service Item 2',
            'sku' => 'PRD-002',
            'description' => 'Professional installation and consulting service',
            'hsn_code' => '9983',
            'qty' => 1,
            'rate' => 4000.00,
            'discount_type' => 'percentage',
            'discount_value' => 0,
            'discount_amount' => 0,
            'net_amount' => 4000.00,
            'gst_percent' => 18,
            'tax_amount' => 720.00,
            'total_amount' => 4720.00
        ]
    ];
} else {
    // Normal Real Dynamic Data based on $docType
    if ($docType === 'quotation') {
        $quotationRow = $ai_db->aiGetQueryObj("
            SELECT q.*, s.state_name, ct.city_name 
            FROM tbl_quotation q 
            LEFT JOIN tbl_state s ON q.state_id = s.id 
            LEFT JOIN tbl_city ct ON q.city_id = ct.id 
            WHERE q.id='$id' 
            LIMIT 1
        ");
        if (empty($quotationRow)) {
            die("Quotation not found!");
        }
        $q = $quotationRow[0];
        // Standardize invoice_no and invoice_date for unified templates
        $q->invoice_no = $q->quotation_no ?? ('QUO-' . $q->id);
        $q->invoice_date = $q->quotation_date ?? ($q->created_at ?? date('Y-m-d'));

        $items = $ai_db->aiGetQueryObj("
            SELECT qi.*, p.sku, p.product_name 
            FROM tbl_quotation_items qi
            LEFT JOIN tbl_product p ON p.id = qi.product_id
            WHERE qi.quotation_id='$id'
            ORDER BY qi.id ASC
        ");
    } elseif ($docType === 'purchase') {
        $purchaseRow = $ai_db->aiGetQueryObj("
            SELECT q.*, s.state_name, ct.city_name 
            FROM tbl_purchase q 
            LEFT JOIN tbl_state s ON q.state_id = s.id 
            LEFT JOIN tbl_city ct ON q.city_id = ct.id 
            WHERE q.id='$id' 
            LIMIT 1
        ");
        if (empty($purchaseRow)) {
            die("Purchase Bill not found!");
        }
        $q = $purchaseRow[0];
        // Standardize invoice_no and invoice_date for unified templates
        $q->invoice_no = $q->bill_no ?? ('PUR-' . $q->id);
        $q->invoice_date = $q->bill_date ?? ($q->created_at ?? date('Y-m-d'));

        $items = $ai_db->aiGetQueryObj("
            SELECT qi.*, p.sku, p.product_name 
            FROM tbl_purchase_items qi
            LEFT JOIN tbl_product p ON p.id = qi.product_id
            WHERE qi.purchase_id='$id'
            ORDER BY qi.id ASC
        ");
    } else {
        $invoiceRow = $ai_db->aiGetQueryObj("
            SELECT q.*, s.state_name, ct.city_name 
            FROM tbl_invoice q 
            LEFT JOIN tbl_state s ON q.state_id = s.id 
            LEFT JOIN tbl_city ct ON q.city_id = ct.id 
            WHERE q.id='$id' 
            LIMIT 1
        ");
        if (empty($invoiceRow)) {
            die("Invoice not found!");
        }
        $q = $invoiceRow[0];

        $items = $ai_db->aiGetQueryObj("
            SELECT qi.*, p.sku, p.product_name 
            FROM tbl_invoice_items qi
            LEFT JOIN tbl_product p ON p.id = qi.product_id
            WHERE qi.invoice_id='$id'
            ORDER BY qi.id ASC
        ");
    }

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
}
