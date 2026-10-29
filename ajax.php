<?php

include __DIR__ . '/root/config.php';
if (!isset($_POST['action'])) {
    echo '';
    exit;
}
if ($_POST['action'] == 'login') {
    $loginUname = addslashes($_POST['loginUname'] ?? '');
    $loginPassword = md5($_POST['loginPassword'] ?? '');
    $data = "not found";

    // ===== Admin Login (username OR email) =====
    $qry = "SELECT * FROM " . DB_PREFIX . "admin WHERE username='" . $loginUname . "' OR email='" . $loginUname . "'";
    $row = $ai_db->aiGetQuery($qry);
    if (!empty($row) && is_array($row) && count($row)) {
        foreach ($row as $user) {
            if ($loginPassword == $user['password']) {
                if ($user['is_active'] == '1') {
                    $_SESSION['aid'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_type'] = 'admin';
                    unset($_SESSION['user_role'], $_SESSION['user_role_id'], $_SESSION['user_role_is_super']);
                    $data = "success";
                } else {
                    $data = "not active";
                }
                break;
            }
        }
        if ($data == "success" || $data == "not active") {
            echo $data;
            exit;
        }
    }

    // ===== User Login (username OR email) =====
    try {
        $qry = "SELECT * FROM " . DB_PREFIX . "user WHERE username='" . $loginUname . "' OR email='" . $loginUname . "'";
        $row = @$ai_db->aiQuery($qry);
        if ($row) {
            $user = $ai_db->aiFetchArray($row);
            if (!empty($user)) {
                if ($loginPassword == $user['password']) {
                    if ($user['status'] == 'active') {
                        $_SESSION['aid'] = $user['id'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_role'] = $user['role'] ?? '';
                        $_SESSION['user_type'] = 'user';
                        $_SESSION['user_role_id'] = '';
                        $_SESSION['user_role_is_super'] = 0;

                        // try to map role name/slug to role id
                        $roleName = addslashes($_SESSION['user_role']);
                        if (!empty($roleName)) {
                            $roleRow = $ai_db->aiGetQueryObj("SELECT id, is_super FROM " . DB_PREFIX . "roles WHERE name='" . $roleName . "' OR slug='" . $roleName . "' LIMIT 1");
                            if (!empty($roleRow)) {
                                $_SESSION['user_role_id'] = $roleRow[0]->id;
                                $_SESSION['user_role_is_super'] = $roleRow[0]->is_super ?? 0;
                            }
                        }
                        $data = "success";
                    } else {
                        $data = "not active";
                    }
                }
            }
            if ($data == "success" || $data == "not active") {
                echo $data;
                exit;
            }
        }
    } catch (Throwable $e) {
        // tbl_user table may not exist
    }

    // ===== Company Login (username OR email OR mobile_no) =====
    $qry = "SELECT * FROM tbl_company WHERE username='" . $loginUname . "' OR email='" . $loginUname . "' OR mobile_no='" . $loginUname . "'";
    $row = $ai_db->aiGetQueryObj($qry);
    if (!empty($row) && is_array($row) && count($row)) {
        foreach ($row as $comp) {
            if ($loginPassword == $comp->password) {
                if ($comp->status == 'active') {
                    $_SESSION['aid'] = $comp->id;
                    $_SESSION['company_id'] = $comp->id;
                    $_SESSION['company_name'] = $comp->company_name;
                    $_SESSION['email'] = $comp->email;
                    $_SESSION['username'] = $comp->username;
                    $_SESSION['user_type'] = 'company';
                    unset($_SESSION['user_role'], $_SESSION['user_role_id'], $_SESSION['user_role_is_super']);
                    $data = "success";
                } else {
                    $data = "not active";
                }
                break;
            }
        }
        if ($data == "success" || $data == "not active") {
            echo $data;
            exit;
        }
    }

    echo $data;
    exit;
}
if ($_POST['action'] == 'check-password') {
    $OldPassword = md5($_POST['old_pass'] ?? '');
    $qry = "SELECT * FROM " . DB_PREFIX . "admin WHERE password='" . $OldPassword . "' AND id=" . $_SESSION['aid'];
    $result = $ai_db->aiGetQueryObj($qry);
    if (empty($result)) {
        echo 'fail';
    } else {
        echo 'success';
    }
}
if ($_POST['action'] == 'get_products_by_category') {
    $category_id = intval($_POST['category_id'] ?? 0);
    $products = $ai_db->aiGetQueryObj("SELECT id, title FROM tbl_product WHERE category_id = '$category_id' AND status='active'");
    $html = '<option value="">-- Select Product --</option>';
    if ($products) {
        foreach ($products as $p) {
            $html .= '<option value="'.$p->id.'">'.$p->title.'</option>';
        }
    }
    echo $html;
    exit;
}
if ($_POST['action'] == 'get_product_details') {
    $product_id = intval($_POST['product_id'] ?? 0);
    $product = $ai_db->aiGetQueryObj("SELECT title, rupees, metal, product_id FROM tbl_product WHERE id = '$product_id'");
    if ($product) {
        echo json_encode($product[0]);
    } else {
        echo json_encode(['error' => 'not found']);
    }
    exit;
}
if ($_POST['action'] == 'generate_product_id') {
    $category_id = intval($_POST['category_id'] ?? 0);
    $prefix = "OT"; // Default Other
    
    $catRow = $ai_db->aiGetQueryObj("SELECT title FROM tbl_category WHERE id = '$category_id'");
    if ($catRow) {
        $title = strtolower($catRow[0]->title);
        if (strpos($title, 'men') !== false && strpos($title, 'women') === false) {
            $prefix = "MN";
        } elseif (strpos($title, 'women') !== false) {
            $prefix = "WN";
        } elseif (strpos($title, 'kid') !== false) {
            $prefix = "KD";
        }
    }
    
    // Find highest number for this prefix across all records
    $qry = "SELECT product_id FROM tbl_product WHERE product_id LIKE '{$prefix}%' AND product_id != ''";
    $allProds = $ai_db->aiGetQueryObj($qry);
    
    $maxNum = 0;
    if ($allProds) {
        foreach ($allProds as $p) {
            $numPart = preg_replace('/[^0-9]/', '', $p->product_id);
            if (!empty($numPart)) {
                $num = intval($numPart);
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }
    }
    $nextNum = $maxNum + 1;
    
    // Format according to user request (KD has 5 digits, others 4)
    if ($prefix === "KD") {
        echo $prefix . str_pad($nextNum, 5, '0', STR_PAD_LEFT); // KD00001
    } else {
        echo $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT); // MN0001, WN0001, OT0001
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'check_state_duplicate') {
    $state_name = addslashes(trim($_POST['state_name'] ?? ''));
    $state_code = addslashes(trim($_POST['state_code'] ?? ''));
    $id = intval($_POST['id'] ?? 0);

    // Check name duplicate
    if (!empty($state_name)) {
        $name_cond = "LOWER(state_name) = '" . strtolower($state_name) . "'";
        $id_cond = ($id > 0) ? " AND id != '$id'" : "";
        $check_name = $ai_db->aiGetQueryObj("SELECT id FROM tbl_state WHERE $name_cond $id_cond LIMIT 1");
        if (!empty($check_name)) {
            echo json_encode(['status' => 'duplicate', 'field' => 'state_name', 'message' => 'State Name already exists!']);
            exit;
        }
    }

    // Check code duplicate
    if (!empty($state_code)) {
        $code_cond = "state_code = '" . $state_code . "'";
        $id_cond = ($id > 0) ? " AND id != '$id'" : "";
        $check_code = $ai_db->aiGetQueryObj("SELECT id FROM tbl_state WHERE $code_cond $id_cond LIMIT 1");
        if (!empty($check_code)) {
            echo json_encode(['status' => 'duplicate', 'field' => 'state_code', 'message' => 'GST State Code already exists!']);
            exit;
        }
    }

    echo json_encode(['status' => 'clean']);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'check_city_duplicate') {
    $state_id = intval($_POST['state_id'] ?? 0);
    $city_name = addslashes(trim($_POST['city_name'] ?? ''));
    $id = intval($_POST['id'] ?? 0);

    if (!empty($city_name) && $state_id > 0) {
        $name_cond = "LOWER(city_name) = '" . strtolower($city_name) . "' AND state_id = '" . $state_id . "'";
        $id_cond = ($id > 0) ? " AND id != '$id'" : "";
        $check_name = $ai_db->aiGetQueryObj("SELECT id FROM tbl_city WHERE $name_cond $id_cond LIMIT 1");
        if (!empty($check_name)) {
            echo json_encode(['status' => 'duplicate', 'field' => 'city_name', 'message' => 'City Name already exists in selected State!']);
            exit;
        }
    }

    echo json_encode(['status' => 'clean']);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'get_cities_by_state') {
    $state_id = intval($_POST['state_id'] ?? 0);
    $cities = $ai_db->aiGetQueryObj("SELECT id, city_name FROM tbl_city WHERE state_id='" . $state_id . "' AND status='active' ORDER BY order_no ASC, city_name ASC");
    echo json_encode(['status' => 'success', 'cities' => $cities ?: []]);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'check_company_duplicate') {
    $company_name = addslashes(trim($_POST['company_name'] ?? ''));
    $gst_no = addslashes(trim($_POST['gst_no'] ?? ''));
    $mobile_no = addslashes(trim($_POST['mobile_no'] ?? ''));
    $email = addslashes(trim($_POST['email'] ?? ''));
    $username = addslashes(trim($_POST['username'] ?? ''));
    $id = intval($_POST['id'] ?? 0);

    $id_cond = ($id > 0) ? " AND id != '$id'" : "";

    if (!empty($gst_no)) {
        $check = $ai_db->aiGetQueryObj("SELECT id FROM tbl_company WHERE LOWER(gst_no) = '" . strtolower($gst_no) . "' $id_cond LIMIT 1");
        if (!empty($check)) {
            echo json_encode(['status' => 'duplicate', 'field' => 'gst_no', 'message' => 'GST Number already exists!']);
            exit;
        }
    }

    if (!empty($mobile_no)) {
        $check = $ai_db->aiGetQueryObj("SELECT id FROM tbl_company WHERE mobile_no = '" . $mobile_no . "' $id_cond LIMIT 1");
        if (!empty($check)) {
            echo json_encode(['status' => 'duplicate', 'field' => 'mobile_no', 'message' => 'Mobile Number already exists!']);
            exit;
        }
    }

    if (!empty($email)) {
        $check = $ai_db->aiGetQueryObj("SELECT id FROM tbl_company WHERE LOWER(email) = '" . strtolower($email) . "' $id_cond LIMIT 1");
        if (!empty($check)) {
            echo json_encode(['status' => 'duplicate', 'field' => 'email', 'message' => 'Email Address already exists!']);
            exit;
        }
    }

    if (!empty($username)) {
        $check = $ai_db->aiGetQueryObj("SELECT id FROM tbl_company WHERE LOWER(username) = '" . strtolower($username) . "' $id_cond LIMIT 1");
        if (!empty($check)) {
            echo json_encode(['status' => 'duplicate', 'field' => 'username', 'message' => 'Username already exists!']);
            exit;
        }
    }

    echo json_encode(['status' => 'clean']);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'get_party_details') {
    $party_id = intval($_POST['party_id'] ?? 0);
    $party = $ai_db->aiGetQueryObj("SELECT * FROM tbl_party WHERE id = '$party_id' LIMIT 1");
    if (!empty($party)) {
        echo json_encode(['status' => 'success', 'party' => $party[0]]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Party not found']);
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'check_product_duplicate') {
    $company_id   = intval($_POST['company_id'] ?? ($_SESSION['company_id'] ?? 0));
    $product_name = addslashes(trim($_POST['product_name'] ?? ''));
    $sku          = addslashes(trim($_POST['sku'] ?? ''));
    $id           = intval($_POST['id'] ?? 0);

    $id_cond = ($id > 0) ? " AND id != '$id'" : "";

    if (!empty($product_name)) {
        $check = $ai_db->aiGetQueryObj("SELECT id FROM tbl_product WHERE company_id = '$company_id' AND LOWER(product_name) = '" . strtolower($product_name) . "' $id_cond LIMIT 1");
        if (!empty($check)) {
            echo json_encode(['status' => 'duplicate', 'field' => 'product_name', 'message' => 'Product Name already exists for this company!']);
            exit;
        }
    }

    if (!empty($sku)) {
        $check = $ai_db->aiGetQueryObj("SELECT id FROM tbl_product WHERE company_id = '$company_id' AND LOWER(sku) = '" . strtolower($sku) . "' $id_cond LIMIT 1");
        if (!empty($check)) {
            echo json_encode(['status' => 'duplicate', 'field' => 'sku', 'message' => 'SKU already exists for this company!']);
            exit;
        }
    }

    echo json_encode(['status' => 'clean']);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'get_parties_and_quotation_no') {
    $company_id = intval($_POST['company_id'] ?? ($_SESSION['company_id'] ?? 0));

    // 1. Fetch parties for this company
    $parties = [];
    $products = [];
    if ($company_id > 0) {
        $parties = $ai_db->aiGetQueryObj("SELECT id, party_name, gst_no, address, state_id, city_id FROM tbl_party WHERE status='active' AND party_status='Sales' AND company_id='$company_id' ORDER BY party_name ASC");
        $products = $ai_db->aiGetQueryObj("SELECT id, product_name, hsn_code, sales_price, gst_rate FROM tbl_product WHERE status='active' AND company_id='$company_id' ORDER BY product_name ASC");
    }

    // 2. Generate Next Quotation No and fetch gst_applicable for this company
    $gst_applicable = 0;
    if ($company_id > 0) {
        $compRowGst = $ai_db->aiGetQueryObj("SELECT gst_applicable FROM tbl_company WHERE id='$company_id' LIMIT 1");
        if (!empty($compRowGst)) {
            $gst_applicable = (int)($compRowGst[0]->gst_applicable ?? 0);
        }
    }

    $quotation_no = generateQuotationNo($ai_db, $company_id);

    echo json_encode([
        'status'         => 'success',
        'parties'        => $parties,
        'products'       => $products,
        'quotation_no'   => $quotation_no,
        'gst_applicable' => $gst_applicable
    ]);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'get_parties_and_invoice_no') {
    $company_id = intval($_POST['company_id'] ?? ($_SESSION['company_id'] ?? 0));

    // 1. Fetch parties for this company
    $parties = [];
    $products = [];
    if ($company_id > 0) {
        $parties = $ai_db->aiGetQueryObj("SELECT id, party_name, gst_no, address, state_id, city_id FROM tbl_party WHERE status='active' AND party_status='Sales' AND company_id='$company_id' ORDER BY party_name ASC");
        $products = $ai_db->aiGetQueryObj("SELECT id, product_name, hsn_code, sales_price, gst_rate, stock, min_stock_alert, track_stock FROM tbl_product WHERE status='active' AND company_id='$company_id' ORDER BY product_name ASC");
    }

    // 2. Generate Next Invoice No and fetch gst_applicable for this company
    $gst_applicable = 0;
    if ($company_id > 0) {
        $compRowGst = $ai_db->aiGetQueryObj("SELECT gst_applicable FROM tbl_company WHERE id='$company_id' LIMIT 1");
        if (!empty($compRowGst)) {
            $gst_applicable = (int)($compRowGst[0]->gst_applicable ?? 0);
        }
    }

    $invoice_no = generateInvoiceNo($ai_db, $company_id);

    echo json_encode([
        'status'         => 'success',
        'parties'        => $parties,
        'products'       => $products,
        'invoice_no'     => $invoice_no,
        'gst_applicable' => $gst_applicable
    ]);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'get_parties_and_purchase_no') {
    $company_id = intval($_POST['company_id'] ?? ($_SESSION['company_id'] ?? 0));

    // 1. Fetch Purchase parties for this company
    $parties = [];
    $products = [];
    if ($company_id > 0) {
        $parties = $ai_db->aiGetQueryObj("SELECT id, party_name, gst_no, address, state_id, city_id FROM tbl_party WHERE status='active' AND party_status='Purchase' AND company_id='$company_id' ORDER BY party_name ASC");
        $products = $ai_db->aiGetQueryObj("SELECT id, product_name, hsn_code, purchase_price, sales_price, gst_rate, stock, min_stock_alert, track_stock FROM tbl_product WHERE status='active' AND company_id='$company_id' ORDER BY product_name ASC");
    }

    // 2. Generate Next Purchase No and fetch gst_applicable for this company
    $gst_applicable = 0;
    if ($company_id > 0) {
        $compRowGst = $ai_db->aiGetQueryObj("SELECT gst_applicable FROM tbl_company WHERE id='$company_id' LIMIT 1");
        if (!empty($compRowGst)) {
            $gst_applicable = (int)($compRowGst[0]->gst_applicable ?? 0);
        }
    }

    if (function_exists('generateCompanyPurchaseNo')) {
        $purchase_no = generateCompanyPurchaseNo($ai_db, $company_id);
    } else {
        $currentYear = date('y');
        $nextYear = date('y', strtotime('+1 year'));
        $fy = $currentYear . '-' . $nextYear;
        $prefix = 'PUR';

        $maxRow = $ai_db->aiGetQueryObj("SELECT purchase_no FROM tbl_purchase ORDER BY id DESC LIMIT 1");
        $nextNum = 1;
        if (!empty($maxRow)) {
            $pNo = $maxRow[0]->purchase_no;
            if (preg_match('~/(\d+)/~', $pNo, $matches)) {
                $nextNum = intval($matches[1]) + 1;
            } else {
                $nextNum = count($ai_db->aiGetQueryObj("SELECT id FROM tbl_purchase")) + 1;
            }
        }
        $purchase_no = $prefix . '/' . str_pad($nextNum, 3, '0', STR_PAD_LEFT) . '/' . $fy;
    }

    echo json_encode([
        'status'         => 'success',
        'parties'        => $parties,
        'products'       => $products,
        'purchase_no'    => $purchase_no,
        'gst_applicable' => $gst_applicable
    ]);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'convert_quotation_to_invoice') {
    $quotation_id = intval($_POST['quotation_id'] ?? 0);
    if ($quotation_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Quotation ID.']);
        exit;
    }

    $qRow = $ai_db->aiGetQueryObj("SELECT * FROM tbl_quotation WHERE id='$quotation_id' LIMIT 1");
    if (empty($qRow)) {
        echo json_encode(['status' => 'error', 'message' => 'Quotation not found.']);
        exit;
    }
    $quot = $qRow[0];

    // Check if already converted
    if (!empty($quot->converted_invoice_id) && intval($quot->converted_invoice_id) > 0) {
        $existingInvId = intval($quot->converted_invoice_id);
        echo json_encode([
            'status' => 'already_converted',
            'invoice_id' => $existingInvId,
            'message' => 'This quotation has already been converted to Invoice #' . $existingInvId
        ]);
        exit;
    }

    // Check stock availability for all products in this quotation BEFORE creating invoice
    $qItemsCheck = $ai_db->aiGetQueryObj("SELECT * FROM tbl_quotation_items WHERE quotation_id='$quotation_id'");
    $outOfStockErrors = [];
    if (!empty($qItemsCheck)) {
        foreach ($qItemsCheck as $chkItem) {
            $pId = intval($chkItem->product_id ?? 0);
            $reqQty = floatval($chkItem->qty ?? 0);
            $itemDesc = trim($chkItem->description ?? '');

            // Fallback product_id lookup by product_name if missing
            if ($pId <= 0 && !empty($itemDesc)) {
                $escDesc = addslashes($itemDesc);
                $pFind = $ai_db->aiGetQueryObj("SELECT id FROM tbl_product WHERE LOWER(TRIM(product_name)) = LOWER(TRIM('$escDesc')) AND company_id='{$quot->company_id}' LIMIT 1");
                if ($pFind && !empty($pFind[0]->id)) {
                    $pId = intval($pFind[0]->id);
                }
            }

            if ($pId > 0 && function_exists('checkProductStockAvailability')) {
                $avail = checkProductStockAvailability($pId, $reqQty);
                if (!empty($avail['track']) && !$avail['available']) {
                    $pTitle = !empty($avail['product_name']) ? $avail['product_name'] : $itemDesc;
                    $outOfStockErrors[] = "• " . htmlspecialchars($pTitle) . " (Required: " . number_format($reqQty, 2) . ", Available Stock: " . number_format($avail['stock'], 2) . ")";
                }
            }
        }
    }

    if (!empty($outOfStockErrors)) {
        echo json_encode([
            'status' => 'insufficient_stock',
            'message' => "Cannot convert quotation to invoice because of insufficient stock:\n\n" . implode("\n", $outOfStockErrors) . "\n\nPlease update product stock or adjust quotation quantity."
        ]);
        exit;
    }

    // Generate new Invoice Number
    $invNo = function_exists('generateCompanyInvoiceNo') 
        ? generateCompanyInvoiceNo($ai_db, $quot->company_id) 
        : 'INV/001/' . date('y') . '-' . date('y', strtotime('+1 year'));

    $today = date('Y-m-d');
    $dueDate = !empty($quot->due_date) ? $quot->due_date : $today;

    // Insert Invoice
    $insInvSql = "INSERT INTO tbl_invoice (
        quotation_id, company_id, party_id, party_name, gst_no, invoice_no, rca, address,
        state_id, city_id, invoice_date, due_date, gst_type,
        total_amount, cgst_amount, sgst_amount, igst_amount, tax_amount, grand_total,
        payment_status, status, created_at
    ) VALUES (
        '{$quot->id}',
        '{$quot->company_id}',
        '{$quot->party_id}',
        '" . addslashes($quot->party_name) . "',
        '" . addslashes($quot->gst_no) . "',
        '" . addslashes($invNo) . "',
        '" . addslashes($quot->rca) . "',
        '" . addslashes($quot->address) . "',
        '{$quot->state_id}',
        '{$quot->city_id}',
        '{$today}',
        '{$dueDate}',
        '{$quot->gst_type}',
        '{$quot->total_amount}',
        '{$quot->cgst_amount}',
        '{$quot->sgst_amount}',
        '{$quot->igst_amount}',
        '{$quot->tax_amount}',
        '{$quot->grand_total}',
        '{$quot->payment_status}',
        'active',
        NOW()
    )";

    $res = mysqli_query($ai_conn, $insInvSql);
    if (!$res) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create invoice: ' . mysqli_error($ai_conn)]);
        exit;
    }

    $newInvoiceId = mysqli_insert_id($ai_conn);

    // Copy items from quotation
    $qItems = $ai_db->aiGetQueryObj("SELECT * FROM tbl_quotation_items WHERE quotation_id='$quotation_id'");
    if (!empty($qItems)) {
        foreach ($qItems as $item) {
            $pId = intval($item->product_id ?? 0);
            $desc = addslashes($item->description ?? '');
            $hsn = addslashes($item->hsn_code ?? '');
            $rate = floatval($item->rate ?? 0);
            $gstP = floatval($item->gst_percent ?? 0);
            $discType = addslashes($item->discount_type ?? 'percentage');
            $discVal = floatval($item->discount_value ?? 0);
            $discAmt = floatval($item->discount_amount ?? 0);
            $qty = floatval($item->qty ?? 1);
            $netAmt = floatval($item->net_amount ?? 0);
            $taxAmt = floatval($item->tax_amount ?? 0);
            $totAmt = floatval($item->total_amount ?? 0);

            $insItemSql = "INSERT INTO tbl_invoice_items (
                invoice_id, product_id, description, hsn_code, rate, gst_percent,
                discount_type, discount_value, discount_amount, qty, net_amount, tax_amount, total_amount
            ) VALUES (
                '$newInvoiceId', '$pId', '$desc', '$hsn', '$rate', '$gstP',
                '$discType', '$discVal', '$discAmt', '$qty', '$netAmt', '$taxAmt', '$totAmt'
            )";
            mysqli_query($ai_conn, $insItemSql);

            // Deduct stock for converted invoice item
            if ($pId > 0 && function_exists('deductProductStock')) {
                deductProductStock($pId, $qty);
            }
        }
    }

    // Update Quotation converted_invoice_id
    mysqli_query($ai_conn, "UPDATE tbl_quotation SET converted_invoice_id='$newInvoiceId' WHERE id='$quotation_id'");

    // Recalculate party outstanding
    if (function_exists('recalculatePartyOutstanding') && !empty($quot->party_id)) {
        recalculatePartyOutstanding($quot->party_id);
    }

    echo json_encode([
        'status' => 'success',
        'invoice_id' => $newInvoiceId,
        'invoice_no' => $invNo,
        'message' => 'Quotation successfully converted to Invoice ' . $invNo
    ]);
    exit;
}

if ($_POST['action'] === 'get_dashboard_outstanding') {
    header('Content-Type: application/json');
    $type = trim($_POST['type'] ?? 'sales'); // 'sales' or 'purchase'
    $fromDate = !empty($_POST['from_date']) ? date('Y-m-d', strtotime($_POST['from_date'])) : '';
    $toDate = !empty($_POST['to_date']) ? date('Y-m-d', strtotime($_POST['to_date'])) : '';

    $userType = $_SESSION['user_type'] ?? 'admin';
    $companyFilter = "";
    if ($userType === 'company' && !empty($_SESSION['company_id'])) {
        $cid = intval($_SESSION['company_id']);
        $companyFilter = " AND company_id = '$cid'";
    }

    if ($type === 'sales') {
        $dateWhere = "";
        if (!empty($fromDate)) $dateWhere .= " AND invoice_date >= '$fromDate'";
        if (!empty($toDate)) $dateWhere .= " AND invoice_date <= '$toDate'";

        $invRow = $ai_db->aiGetQueryObj("SELECT 
            COALESCE(SUM(grand_total), 0) AS total_payment,
            COALESCE(SUM(paid_amount), 0) AS total_received,
            COALESCE(SUM(cgst_amount), 0) AS total_cgst,
            COALESCE(SUM(sgst_amount), 0) AS total_sgst
            FROM tbl_invoice 
            WHERE status = 'active' {$companyFilter} {$dateWhere}");

        $totPayment = !empty($invRow) ? floatval($invRow[0]->total_payment) : 0.00;
        $totReceived = !empty($invRow) ? floatval($invRow[0]->total_received) : 0.00;
        $totPending = max(0, $totPayment - $totReceived);
        $totCgst = !empty($invRow) ? floatval($invRow[0]->total_cgst) : 0.00;
        $totSgst = !empty($invRow) ? floatval($invRow[0]->total_sgst) : 0.00;

        echo json_encode([
            'status' => true,
            'total_payment' => number_format($totPayment, 2, '.', ''),
            'total_received' => number_format($totReceived, 2, '.', ''),
            'total_pending' => number_format($totPending, 2, '.', ''),
            'total_cgst' => number_format($totCgst, 2, '.', ''),
            'total_sgst' => number_format($totSgst, 2, '.', '')
        ]);
        exit;
    } else {
        $dateWhere = "";
        if (!empty($fromDate)) $dateWhere .= " AND purchase_date >= '$fromDate'";
        if (!empty($toDate)) $dateWhere .= " AND purchase_date <= '$toDate'";

        $purRow = $ai_db->aiGetQueryObj("SELECT 
            COALESCE(SUM(grand_total), 0) AS total_payment,
            COALESCE(SUM(paid_amount), 0) AS total_payable,
            COALESCE(SUM(cgst_amount), 0) AS total_cgst,
            COALESCE(SUM(sgst_amount), 0) AS total_sgst
            FROM tbl_purchase 
            WHERE status = 'active' {$companyFilter} {$dateWhere}");

        $totPayment = !empty($purRow) ? floatval($purRow[0]->total_payment) : 0.00;
        $totPaid = !empty($purRow) ? floatval($purRow[0]->total_payable) : 0.00;
        $totPending = max(0, $totPayment - $totPaid);
        $totCgst = !empty($purRow) ? floatval($purRow[0]->total_cgst) : 0.00;
        $totSgst = !empty($purRow) ? floatval($purRow[0]->total_sgst) : 0.00;

        echo json_encode([
            'status' => true,
            'total_payment' => number_format($totPayment, 2, '.', ''),
            'total_payable' => number_format($totPaid, 2, '.', ''),
            'total_pending' => number_format($totPending, 2, '.', ''),
            'total_cgst' => number_format($totCgst, 2, '.', ''),
            'total_sgst' => number_format($totSgst, 2, '.', '')
        ]);
        exit;
    }
}



if (isset($_POST['action']) && $_POST['action'] === 'set_company_invoice_template') {
    header('Content-Type: application/json');
    if (empty($_SESSION['aid'])) {
        echo json_encode(['status' => false, 'message' => 'Unauthorized access.']);
        exit;
    }

    $companyId = isset($_POST['company_id']) ? intval($_POST['company_id']) : 0;
    $templateId = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;

    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'company') {
        if (!empty($_SESSION['company_id']) && (int)$_SESSION['company_id'] !== $companyId) {
            echo json_encode(['status' => false, 'message' => 'You can only update your own company template.']);
            exit;
        }
    }

    if ($companyId <= 0 || $templateId <= 0) {
        echo json_encode(['status' => false, 'message' => 'Invalid Company or Template ID.']);
        exit;
    }

    $tplCheck = $ai_db->aiGetQueryObj("SELECT id, template_name FROM tbl_invoice_templates WHERE id='$templateId' AND status='active' LIMIT 1");
    if (empty($tplCheck)) {
        echo json_encode(['status' => false, 'message' => 'Selected template is not active or does not exist.']);
        exit;
    }

    $update = $ai_db->aiQuery("UPDATE tbl_company SET invoice_template_id='$templateId' WHERE id='$companyId'");
    if ($update) {
        echo json_encode([
            'status' => true,
            'message' => 'Default template updated to "' . $tplCheck[0]->template_name . '" successfully!',
            'template_id' => $templateId,
            'template_name' => $tplCheck[0]->template_name
        ]);
    } else {
        echo json_encode(['status' => false, 'message' => 'Database error while updating template.']);
    }
    exit;
}
