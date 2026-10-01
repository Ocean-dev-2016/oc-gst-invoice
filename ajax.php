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

    if (!empty($company_name)) {
        $check = $ai_db->aiGetQueryObj("SELECT id FROM tbl_company WHERE LOWER(company_name) = '" . strtolower($company_name) . "' $id_cond LIMIT 1");
        if (!empty($check)) {
            echo json_encode(['status' => 'duplicate', 'field' => 'company_name', 'message' => 'Company Name already exists!']);
            exit;
        }
    }

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




