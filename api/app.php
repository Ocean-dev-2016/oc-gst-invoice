<?php

require_once __DIR__ . '/../root/config.php';

function apiResponse($status, $message, $data = [])
{
    http_response_code($status);
    echo json_encode([
        'status'  => $status === 200,
        'message' => $message,
        'data'    => $data
    ]);
    exit;
}

function validatePasswordPolicy($password)
{
    if (empty($password)) {
        return 'Password is required.';
    }
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least 1 number.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must contain at least 1 uppercase letter.';
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        return 'Password must contain at least 1 special character.';
    }
    return '';
}

function getApiInput()
{
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST;
    }
    return is_array($input) ? $input : [];
}


function authenticateCompany($input = [], $includeLocation = false)
{
    global $ai_conn, $ai_db;

    // 1. Extract Bearer token from Authorization header if available
    $token = '';
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = trim($matches[1]);
    }

    // 2. Also accept token from body or query parameters
    if (empty($token)) {
        $token = trim($input['api_token'] ?? ($input['token'] ?? ($_GET['api_token'] ?? ($_GET['token'] ?? ''))));
    }

    $companyId = (int)($input['company_id'] ?? ($input['id'] ?? ($_GET['company_id'] ?? ($_GET['id'] ?? 0))));
    $companyName = trim($input['company_name'] ?? ($_GET['company_name'] ?? ''));

    $company = null;
    $compSelect = $includeLocation 
        ? "SELECT c.*, s.state_name, ct.city_name FROM tbl_company c LEFT JOIN tbl_state s ON c.state_id = s.id LEFT JOIN tbl_city ct ON c.city_id = ct.id" 
        : "SELECT * FROM tbl_company";

    $wherePrefix = $includeLocation ? "c." : "";

    if (!empty($token)) {
        $safeToken = mysqli_real_escape_string($ai_conn, $token);
        $res = $ai_db->aiGetQuery("{$compSelect} WHERE {$wherePrefix}api_token='{$safeToken}' LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    } elseif ($companyId > 0) {
        $res = $ai_db->aiGetQuery("{$compSelect} WHERE {$wherePrefix}id={$companyId} LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    } elseif (!empty($companyName)) {
        $safeCompName = mysqli_real_escape_string($ai_conn, $companyName);
        $res = $ai_db->aiGetQuery("{$compSelect} WHERE LOWER({$wherePrefix}company_name) = '" . strtolower($safeCompName) . "' LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    }

    if (!$company) {
        apiResponse(401, 'Unauthorized or Company not found.');
    }

    return $company;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($action === 'register') {
    if ($method !== 'POST') {
        apiResponse(405, 'Only POST method is allowed.');
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    // Input fields mapping
    $name = trim($input['name'] ?? ($input['username'] ?? ''));
    $email = trim($input['email'] ?? '');
    $mobileNo = trim($input['mobile_no'] ?? ($input['mobile'] ?? ''));
    $password = $input['password'] ?? '';

    // Validation
    if ($name === '') {
        apiResponse(400, 'Name is required.');
    }

    if ($email === '') {
        apiResponse(400, 'Email is required.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        apiResponse(400, 'Please enter a valid email address.');
    }

    if ($mobileNo === '') {
        apiResponse(400, 'Mobile number is required.');
    } elseif (!preg_match('/^[0-9]{10}$/', $mobileNo)) {
        apiResponse(400, 'Mobile number must be exactly 10 digits.');
    }

    $passErr = validatePasswordPolicy($password);
    if (!empty($passErr)) {
        apiResponse(400, $passErr);
    }

    // Escape values
    $safeUsername = mysqli_real_escape_string($ai_conn, $name);
    $safeEmail = mysqli_real_escape_string($ai_conn, $email);
    $safeMobile = mysqli_real_escape_string($ai_conn, $mobileNo);
    $hashedPassword = md5($password);
    $apiToken = bin2hex(random_bytes(32));
    $safeToken = mysqli_real_escape_string($ai_conn, $apiToken);

    // Duplicate check in tbl_company
    $dupCheckQry = "SELECT id, email, mobile_no, username FROM tbl_company 
                    WHERE LOWER(email) = '" . strtolower($safeEmail) . "' 
                       OR mobile_no = '" . $safeMobile . "' 
                       OR LOWER(username) = '" . strtolower($safeUsername) . "' 
                    LIMIT 1";

    $existing = $ai_db->aiGetQueryObj($dupCheckQry);
    if (!empty($existing)) {
        $row = $existing[0];
        if (strtolower($row->username) === strtolower($name)) {
            apiResponse(409, 'Name/Username is already registered.');
        } elseif (strtolower($row->email) === strtolower($email)) {
            apiResponse(409, 'Email address is already registered.');
        } elseif ($row->mobile_no === $mobileNo) {
            apiResponse(409, 'Mobile number is already registered.');
        } else {
            apiResponse(409, 'Account already exists with provided details.');
        }
    }

    // Insert into tbl_company with api_token
    $insertQry = "INSERT INTO tbl_company (username, email, mobile_no, password, api_token, status, created_at) 
                  VALUES ('{$safeUsername}', '{$safeEmail}', '{$safeMobile}', '{$hashedPassword}', '{$safeToken}', 'active', NOW())";

    $result = mysqli_query($ai_conn, $insertQry);
    if (!$result) {
        apiResponse(500, 'Failed to register company. ' . mysqli_error($ai_conn));
    }

    $companyId = mysqli_insert_id($ai_conn);

    apiResponse(200, 'Registration successful.', [
        'api_token'     => $apiToken,
        'id'            => $companyId,
        'username'      => $name,
        'email'         => $email,
        'mobile_no'     => $mobileNo,
        'status'        => 'active'
    ]);
}

if ($action === 'login') {
    if ($method !== 'POST') {
        apiResponse(405, 'Only POST method is allowed.');
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $login = trim($input['username'] ?? ($input['login'] ?? ($input['email'] ?? ($input['mobile_no'] ?? ''))));
    $password = $input['password'] ?? '';

    if ($login === '' || $password === '') {
        apiResponse(400, 'Username/Email and password are required.');
    }

    $loginUname = mysqli_real_escape_string($ai_conn, $login);
    $loginPassword = md5($password);

    // ===== Company Login Query (username OR email OR mobile_no) =====
    $qry = "SELECT * FROM tbl_company 
            WHERE username='" . $loginUname . "' 
               OR email='" . $loginUname . "'";
    $row = $ai_db->aiGetQuery($qry);

    if (empty($row) || !is_array($row) || count($row) === 0) {
        apiResponse(401, 'Wrong Username/Email');
    }

    $company = null;
    foreach ($row as $comp) {
        if ($loginPassword === $comp['password']) {
            $company = $comp;
            break;
        }
    }

    if (!$company) {
        apiResponse(401, 'Wrong Password!');
    }

    if (isset($company['status']) && $company['status'] !== 'active') {
        apiResponse(403, 'Your company account is not active. Please contact administrator.');
    }

    // Generate API token and update in tbl_company directly
    $token = bin2hex(random_bytes(32));
    $safeToken = mysqli_real_escape_string($ai_conn, $token);
    $companyId = (int)$company['id'];

    mysqli_query($ai_conn, "UPDATE tbl_company SET api_token='{$safeToken}' WHERE id={$companyId}");

    unset($company['password']);
    $company['api_token'] = $token;

    apiResponse(200, 'Login successful.', [
        'company'    => $company
    ]);
}

if ($action === 'logout') {
    if ($method !== 'POST') {
        apiResponse(405, 'Only POST method is allowed.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);

    $cId = (int)$company['id'];
    // Invalidate the token in database
    mysqli_query($ai_conn, "UPDATE tbl_company SET api_token=NULL WHERE id={$cId}");

    apiResponse(200, 'Logged out successfully.');
}

if ($action === 'change-password') {
    if ($method !== 'POST') {
        apiResponse(405, 'Only POST method is allowed.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);

    $oldPassword = $input['old_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? ($input['new_password_confirmation'] ?? '');

    if ($oldPassword === '') {
        apiResponse(400, 'Old password is required.');
    }

    if ($newPassword === '') {
        apiResponse(400, 'New password is required.');
    }

    $passErr = validatePasswordPolicy($newPassword);
    if (!empty($passErr)) {
        apiResponse(400, $passErr);
    }

    if (!empty($confirmPassword) && $newPassword !== $confirmPassword) {
        apiResponse(400, 'New password and confirm password do not match.');
    }

    if (md5($oldPassword) !== $company['password']) {
        apiResponse(400, 'Old password is incorrect.');
    }
    if (md5($newPassword) === $company['password']) {
        apiResponse(400, 'New password cannot be the same as old password.');
    }

    $newHashedPassword = md5($newPassword);
    $cId = (int)$company['id'];
    $updateQry = "UPDATE tbl_company SET password='{$newHashedPassword}', modified_at=NOW() WHERE id={$cId}";

    if (mysqli_query($ai_conn, $updateQry)) {
        apiResponse(200, 'Password changed successfully.');
    } else {
        apiResponse(500, 'Failed to update password. ' . mysqli_error($ai_conn));
    }
}

if ($action === 'profile') {
    $input = getApiInput();
    $company = authenticateCompany($input, true);
    $cId = (int)$company['id'];

    if ($method === 'GET') {
        apiResponse(200, 'Profile retrieved successfully.', [
            'id'                   => (int)$company['id'],
            'company_name'         => $company['company_name'] ?? '',
            'gst_applicable'       => (int)($company['gst_applicable'] ?? 0),
            'owner_name'           => $company['owner_name'] ?? '',
            'mobile_no'            => $company['mobile_no'] ?? '',
            'email'                => $company['email'] ?? '',
            'username'             => $company['username'] ?? '',
            'gst_no'               => $company['gst_no'] ?? '',
            'state_id'             => (int)($company['state_id'] ?? 0),
            'state_name'           => $company['state_name'] ?? '',
            'city_id'              => (int)($company['city_id'] ?? 0),
            'city_name'            => $company['city_name'] ?? '',
            'pincode'              => $company['pincode'] ?? '',
            'address'              => $company['address'] ?? '',
            'status'               => $company['status'] ?? 'active'
        ]);
    }

    if ($method === 'POST' || $method === 'PUT') {
        $company_name       = trim($input['company_name'] ?? ($company['company_name'] ?? ''));
        $owner_name         = trim($input['owner_name'] ?? ($company['owner_name'] ?? ''));
        $mobile_no          = trim($input['mobile_no'] ?? ($company['mobile_no'] ?? ''));
        $email              = trim($input['email'] ?? ($company['email'] ?? ''));
        $username           = trim($input['username'] ?? ($company['username'] ?? ''));
        $gst_applicable     = isset($input['gst_applicable']) ? (int)$input['gst_applicable'] : (int)($company['gst_applicable'] ?? 0);
        $gst_no             = ($gst_applicable === 1) ? trim($input['gst_no'] ?? ($company['gst_no'] ?? '')) : '';
        $state_id           = isset($input['state_id']) ? (int)$input['state_id'] : (int)($company['state_id'] ?? 0);
        $city_id            = isset($input['city_id']) ? (int)$input['city_id'] : (int)($company['city_id'] ?? 0);
        $pincode            = trim($input['pincode'] ?? ($company['pincode'] ?? ''));
        $address            = trim($input['address'] ?? ($company['address'] ?? ''));

        if ($company_name === '') {
            apiResponse(400, 'Business name is required.');
        }

        if ($gst_applicable === 1) {
            if ($gst_no === '') {
                apiResponse(400, 'GST Number is required when GST is Applicable.');
            } elseif (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i', $gst_no)) {
                apiResponse(400, 'Please enter a valid 15-digit GST Number (e.g. 24AAAAA0000A1Z5).');
            }
        }

        if ($pincode !== '' && !preg_match('/^[0-9]{6}$/', $pincode)) {
            apiResponse(400, 'Pincode must be exactly 6 digits.');
        }

        if ($email === '') {
            apiResponse(400, 'Email is required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            apiResponse(400, 'Please enter a valid email address.');
        }

        if ($mobile_no === '') {
            apiResponse(400, 'Mobile number is required.');
        } elseif (!preg_match('/^[0-9]{10}$/', $mobile_no)) {
            apiResponse(400, 'Mobile number must be exactly 10 digits.');
        }

        $safeCompany       = mysqli_real_escape_string($ai_conn, $company_name);
        $safeOwner         = mysqli_real_escape_string($ai_conn, $owner_name);
        $safeMobile        = mysqli_real_escape_string($ai_conn, $mobile_no);
        $safeEmail         = mysqli_real_escape_string($ai_conn, $email);
        $safeUname         = mysqli_real_escape_string($ai_conn, $username);
        $safeGst           = mysqli_real_escape_string($ai_conn, $gst_no);
        $safePincode       = mysqli_real_escape_string($ai_conn, $pincode);
        $safeAddress       = mysqli_real_escape_string($ai_conn, $address);

        $dupQry = "SELECT id, email, mobile_no, username FROM tbl_company 
                   WHERE id != {$cId} AND (
                       LOWER(email) = '" . strtolower($safeEmail) . "' 
                       OR mobile_no = '{$safeMobile}' 
                       " . (!empty($safeUname) ? "OR LOWER(username) = '" . strtolower($safeUname) . "'" : "") . "
                   ) LIMIT 1";

        $dupRes = $ai_db->aiGetQueryObj($dupQry);
        if (!empty($dupRes)) {
            $row = $dupRes[0];
            if (!empty($safeUname) && strtolower($row->username) === strtolower($username)) {
                apiResponse(409, 'Username is already taken by another company.');
            } elseif (strtolower($row->email) === strtolower($email)) {
                apiResponse(409, 'Email address is already in use by another company.');
            } elseif ($row->mobile_no === $mobile_no) {
                apiResponse(409, 'Mobile number is already in use by another company.');
            } elseif ($row->gst_no === $gst_no) {
                apiResponse(409, 'GST number is already in use by another company.');
            } else {
                apiResponse(409, 'Another company with these details already exists.');
            }
        }

        $updateQry = "UPDATE tbl_company SET 
                      company_name       = '{$safeCompany}',
                      gst_applicable     = {$gst_applicable},
                      owner_name         = '{$safeOwner}',
                      mobile_no          = '{$safeMobile}',
                      email              = '{$safeEmail}',
                      username           = '{$safeUname}',
                      gst_no             = '{$safeGst}',
                      state_id           = {$state_id},
                      city_id            = {$city_id},
                      pincode            = '{$safePincode}',
                      address            = '{$safeAddress}',
                      modified_at        = NOW() 
                      WHERE id = {$cId}";

        if (!mysqli_query($ai_conn, $updateQry)) {
            apiResponse(500, 'Failed to update profile: ' . mysqli_error($ai_conn));
        }

        $stateRow        = $state_id > 0 ? $ai_db->aiGetQueryObj("SELECT state_name FROM tbl_state WHERE id={$state_id} LIMIT 1") : [];
        $cityRow         = $city_id > 0 ? $ai_db->aiGetQueryObj("SELECT city_name FROM tbl_city WHERE id={$city_id} LIMIT 1") : [];

        apiResponse(200, 'Profile updated successfully.', [
            'id'                   => $cId,
            'company_name'         => $company_name,
            'gst_applicable'       => $gst_applicable,
            'owner_name'           => $owner_name,
            'mobile_no'            => $mobile_no,
            'email'                => $email,
            'username'             => $username,
            'gst_no'               => $gst_no,
            'state_id'             => $state_id,
            'state_name'           => !empty($stateRow) ? $stateRow[0]->state_name : '',
            'city_id'              => $city_id,
            'city_name'            => !empty($cityRow) ? $cityRow[0]->city_name : '',
            'pincode'              => $pincode,
            'address'              => $address,
            'status'               => $company['status'] ?? 'active'
        ]);
    }

    apiResponse(405, 'Method not allowed. Use GET or POST.');
}

if ($action === 'state-list') {
    if ($method !== 'GET') {
        apiResponse(405, 'Only GET method is allowed.');
    }

    $states = $ai_db->aiGetQueryObj("SELECT id, state_name, state_code, status FROM tbl_state WHERE status='active' ORDER BY order_no ASC, state_name ASC");

    $list = [];
    if (!empty($states)) {
        foreach ($states as $st) {
            $list[] = [
                'id'         => (int)$st->id,
                'state_name' => $st->state_name,
                'state_code' => $st->state_code,
                'status'     => $st->status
            ];
        }
    }

    apiResponse(200, 'States retrieved successfully.', [
        'total'  => count($list),
        'states' => $list
    ]);
}

if ($action === 'city-list') {
    if ($method !== 'GET' && $method !== 'POST') {
        apiResponse(405, 'Method not allowed. Use GET or POST.');
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $stateId = intval($input['state_id'] ?? ($_GET['state_id'] ?? 0));

    if ($stateId <= 0) {
        apiResponse(400, 'state_id is required.');
    }

    $stateCheck = $ai_db->aiGetQueryObj("SELECT id, state_name FROM tbl_state WHERE id = {$stateId} LIMIT 1");
    if (empty($stateCheck)) {
        apiResponse(404, 'State not found.');
    }

    $stateInfo = $stateCheck[0];

    $cities = $ai_db->aiGetQueryObj("SELECT id, state_id, city_name, status 
                                     FROM tbl_city 
                                     WHERE state_id = {$stateId} AND status = 'active' 
                                     ORDER BY order_no ASC, city_name ASC");

    $list = [];
    if (!empty($cities)) {
        foreach ($cities as $ct) {
            $list[] = [
                'id'        => (int)$ct->id,
                'state_id'  => (int)$ct->state_id,
                'city_name' => $ct->city_name,
                'status'    => $ct->status
            ];
        }
    }

    apiResponse(200, 'Cities retrieved successfully.', [
        'state_id'   => (int)$stateInfo->id,
        'state_name' => $stateInfo->state_name,
        'total'      => count($list),
        'cities'     => $list
    ]);
}

if ($action === 'customer-list') {
    if ($method !== 'GET' && $method !== 'POST') {
        apiResponse(405, 'Method not allowed. Use GET or POST.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $cId = (int)$company['id'];

    $filterStatus = trim($input['status'] ?? ($_GET['status'] ?? ''));
    $statusCond = "";
    if ($filterStatus === 'active' || $filterStatus === 'deactive') {
        $statusCond = " AND p.status = '{$filterStatus}'";
    } elseif ($filterStatus === 'all') {
        $statusCond = "";
    } else {
        $statusCond = " AND p.status = 'active'";
    }

    // Filter by Party Status / Type: 'Sales' (Customer) or 'Purchase' (Supplier/Vendor)
    $filterPartyStatus = trim($input['party_status'] ?? ($input['type'] ?? ($_GET['party_status'] ?? ($_GET['type'] ?? ''))));
    $partyStatusCond = "";
    if (strcasecmp($filterPartyStatus, 'Sales') === 0) {
        $partyStatusCond = " AND p.party_status = 'Sales'";
    } elseif (strcasecmp($filterPartyStatus, 'Purchase') === 0) {
        $partyStatusCond = " AND p.party_status = 'Purchase'";
    }

    $search = trim($input['search'] ?? ($_GET['search'] ?? ''));
    $searchCond = "";
    if ($search !== '') {
        $safeSearch = mysqli_real_escape_string($ai_conn, $search);
        $searchCond = " AND (p.party_name LIKE '%{$safeSearch}%' OR p.mobile_no LIKE '%{$safeSearch}%' OR p.email LIKE '%{$safeSearch}%' OR p.gst_no LIKE '%{$safeSearch}%')";
    }

    $orderBy = "ORDER BY p.id DESC";

    $todayDate = date('Y-m-d');

    $partyQry = "SELECT p.id, p.company_id, p.party_name, p.address, 
                        p.state_id, s.state_name, 
                        p.city_id, ct.city_name, 
                        p.pincode, p.shipping_pincode, 
                        p.shipping_address, p.shipping_state_id, ss.state_name AS shipping_state_name,
                        p.shipping_city_id, sc.city_name AS shipping_city_name,
                        p.mobile_no, p.email, p.gst_no, p.pan_no, 
                        p.party_status, p.business_type, p.opening_balance, 
                        p.balance_type, p.outstanding, p.remark,
                        p.status, p.created_at,
                        COALESCE(inv_overdue.overdue_amount, 0) AS overdue_amount,
                        COALESCE(inv_overdue.overdue_invoices_count, 0) AS overdue_invoices_count
                 FROM tbl_party p
                 LEFT JOIN tbl_state s ON p.state_id = s.id
                 LEFT JOIN tbl_city ct ON p.city_id = ct.id
                 LEFT JOIN tbl_state ss ON p.shipping_state_id = ss.id
                 LEFT JOIN tbl_city sc ON p.shipping_city_id = sc.id
                 LEFT JOIN (
                     SELECT party_id, 
                            SUM(grand_total - paid_amount) AS overdue_amount,
                            COUNT(id) AS overdue_invoices_count
                     FROM tbl_invoice 
                     WHERE company_id = {$cId}
                       AND status = 'active' 
                       AND payment_status != 'Paid' 
                       AND due_date IS NOT NULL 
                       AND due_date < '{$todayDate}'
                     GROUP BY party_id
                 ) inv_overdue ON inv_overdue.party_id = p.id
                 WHERE p.company_id = {$cId} {$statusCond} {$partyStatusCond} {$searchCond}
                 {$orderBy}";

    $parties = $ai_db->aiGetQueryObj($partyQry);

    $partyStatusCondSummary = "";
    if (strcasecmp($filterPartyStatus, 'Sales') === 0) {
        $partyStatusCondSummary = " AND party_status = 'Sales'";
    } elseif (strcasecmp($filterPartyStatus, 'Purchase') === 0) {
        $partyStatusCondSummary = " AND party_status = 'Purchase'";
    }

    $summaryQry = "SELECT 
                        COUNT(id) AS total_customers,
                        COALESCE(SUM(CASE WHEN outstanding > 0 THEN outstanding ELSE 0 END), 0) AS total_receivable,
                        COUNT(CASE WHEN outstanding > 0 THEN 1 END) AS receivable_clients_count
                   FROM tbl_party 
                   WHERE company_id = {$cId} AND status = 'active' {$partyStatusCondSummary}";
    $summaryRes = $ai_db->aiGetQueryObj($summaryQry);
    $sumData = !empty($summaryRes) ? $summaryRes[0] : null;

    $totalCustomers = (int)($sumData->total_customers ?? count($parties ?? []));
    $receivableAmt = (float)($sumData->total_receivable ?? 0);
    $receivableClients = (int)($sumData->receivable_clients_count ?? 0);

    // Compute company-level total overdue amount and count of parties with overdue invoices
    $overduePartyJoin = ($partyStatusCondSummary !== "") ? " AND party_id IN (SELECT id FROM tbl_party WHERE company_id = {$cId} {$partyStatusCondSummary})" : "";
    $overdueSummaryQry = "SELECT 
                            COALESCE(SUM(grand_total - paid_amount), 0) AS total_overdue_amt,
                            COUNT(DISTINCT party_id) AS overdue_parties_count
                          FROM tbl_invoice 
                          WHERE company_id = {$cId} 
                            AND status = 'active' 
                            AND payment_status != 'Paid' 
                            AND due_date IS NOT NULL 
                            AND due_date < '{$todayDate}'
                            {$overduePartyJoin}";
    $overdueSummaryRes = $ai_db->aiGetQueryObj($overdueSummaryQry);
    $totalOverdueAmt = (float)($overdueSummaryRes[0]->total_overdue_amt ?? 0);
    $overduePartiesCount = (int)($overdueSummaryRes[0]->overdue_parties_count ?? 0);

    $list = [];
    if (!empty($parties)) {
        foreach ($parties as $pty) {
            $name = trim($pty->party_name ?? '');            
            $words = preg_split('/\s+/', $name);
            $initials = '';
            if (count($words) >= 2) {
                $initials = strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
            } elseif (count($words) === 1 && mb_strlen($words[0]) > 0) {
                $initials = strtoupper(mb_substr($words[0], 0, 1));
            }

            $outstandingVal = (float)($pty->outstanding ?? 0);
            $overdueVal     = (float)($pty->overdue_amount ?? 0);
            $overdueCount   = (int)($pty->overdue_invoices_count ?? 0);

            $list[] = [
                'id'                     => (int)$pty->id,
                'customer_code'          => 'CUST-' . str_pad($pty->id, 3, '0', STR_PAD_LEFT),
                'company_id'             => (int)$pty->company_id,
                'customer_name'          => $name,
                'initials'               => $initials,
                'mobile_no'              => $pty->mobile_no ?? '',
                'email'                  => $pty->email ?? '',
                'gst_no'                 => $pty->gst_no ?? '',
                'pan_no'                 => $pty->pan_no ?? '',
                'address'                => $pty->address ?? '',
                'state_id'               => (int)($pty->state_id ?? 0),
                'state_name'             => $pty->state_name ?? '',
                'city_id'                => (int)($pty->city_id ?? 0),
                'city_name'              => $pty->city_name ?? '',
                'pincode'                => $pty->pincode ?? '',
                'shipping_address'       => $pty->shipping_address ?? '',
                'shipping_state_id'      => (int)($pty->shipping_state_id ?? 0),
                'shipping_state_name'    => $pty->shipping_state_name ?? '',
                'shipping_city_id'       => (int)($pty->shipping_city_id ?? 0),
                'shipping_city_name'     => $pty->shipping_city_name ?? '',
                'shipping_pincode'       => $pty->shipping_pincode ?? '',
                'party_status'           => $pty->party_status ?? 'Sales',
                'business_type'          => $pty->business_type ?? 'Individual',
                'opening_balance'        => (float)($pty->opening_balance ?? 0),
                'balance_type'           => $pty->balance_type ?? 'Debit',
                'outstanding'            => $outstandingVal,
                'outstanding_formatted'  => '₹' . number_format($outstandingVal, 2),
                'overdue_amount'         => $overdueVal,
                'overdue_formatted'      => '₹' . number_format($overdueVal, 2),
                'overdue_invoices_count' => $overdueCount,
                'has_overdue'            => ($overdueVal > 0),
                'remark'                 => $pty->remark ?? '',
                'status'                 => $pty->status ?? 'active',
                'created_at'             => $pty->created_at ?? ''
            ];
        }
    }

    apiResponse(200, 'Customer list retrieved successfully.', [
        'company_id'   => $cId,
        'company_name' => $company['company_name'] ?? '',
        'total'        => count($list),
        'summary'      => [
            'total_customers'             => $totalCustomers,
            'total_customers_formatted'   => (string)$totalCustomers,
            'total_customers_sub_text'    => '+12 month',
            'receivable_amount'           => $receivableAmt,
            'receivable_amount_formatted' => '₹' . number_format($receivableAmt, 2),
            'receivable_clients_count'    => $receivableClients,
            'receivable_sub_text'         => $receivableClients . ' clients',
            'overdue_amount'              => $totalOverdueAmt,
            'overdue_amount_formatted'    => '₹' . number_format($totalOverdueAmt, 2),
            'overdue_clients_count'       => $overduePartiesCount,
            'overdue_sub_text'            => $overduePartiesCount . ' clients overdue'
        ],
        'receivable'   => $receivableAmt,
        'overdue'      => $totalOverdueAmt,
        'customers'    => $list
    ]);
}

if ($action === 'customer-add') {
    if ($method !== 'POST') {
        apiResponse(405, 'Only POST method is allowed.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];

    $party_name         = trim($input['customer_name'] ?? ($input['party_name'] ?? ''));
    $mobile_no          = trim($input['mobile_no'] ?? ($input['mobile'] ?? ''));
    $email              = trim($input['email'] ?? ($input['email_address'] ?? ''));
    $gst_no             = trim($input['gst_no'] ?? ($input['gst_in_no'] ?? ''));
    $pan_no             = strtoupper(trim($input['pan_no'] ?? ''));
    $party_status       = trim($input['party_status'] ?? 'Sales');
    $business_type      = in_array($input['business_type'] ?? '', ['Individual', 'Business']) ? $input['business_type'] : 'Business';
    $opening_balance    = (float)($input['opening_balance'] ?? 0);
    $balance_type       = in_array($input['balance_type'] ?? '', ['Credit', 'Debit']) ? $input['balance_type'] : 'Debit';
    $outstanding        = (float)($input['outstanding'] ?? 0);
    $remark             = trim($input['remark'] ?? '');
    $status             = trim($input['status'] ?? 'active');

    $address            = trim($input['billing_address'] ?? ($input['address'] ?? ''));
    $state_id           = (int)($input['billing_state_id'] ?? ($input['state_id'] ?? 0));
    $city_id            = (int)($input['billing_city_id'] ?? ($input['city_id'] ?? 0));
    $pincode            = trim($input['billing_pincode'] ?? ($input['pincode'] ?? ''));

    $sameAsBilling = !empty($input['same_as_billing']) && ($input['same_as_billing'] === true || $input['same_as_billing'] === 'true' || $input['same_as_billing'] === 1 || $input['same_as_billing'] === '1');
    if ($sameAsBilling) {
        $shipping_address   = $address;
        $shipping_state_id  = $state_id;
        $shipping_city_id   = $city_id;
        $shipping_pincode   = $pincode;
    } else {
        $shipping_address   = trim($input['shipping_address'] ?? '');
        $shipping_state_id  = (int)($input['shipping_state_id'] ?? 0);
        $shipping_city_id   = (int)($input['shipping_city_id'] ?? 0);
        $shipping_pincode   = trim($input['shipping_pincode'] ?? '');
    }

    if (!in_array(strtolower($status), ['active', 'deactive'])) {
        $status = 'active';
    }
    if (!in_array($party_status, ['Sales', 'Purchase'])) {
        $party_status = 'Sales';
    }

    if ($party_name === '') {
        apiResponse(400, 'Customer Name is required.');
    }
    if ($mobile_no !== '' && !preg_match('/^[0-9]{10}$/', $mobile_no)) {
        apiResponse(400, 'Mobile Number must be exactly 10 digits.');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        apiResponse(400, 'Please enter a valid Email Address.');
    }

    if ($pan_no !== '' && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan_no)) {
        apiResponse(400, 'PAN Number must be valid 10-character alphanumeric (e.g. ABCDE1234F).');
    }

    if ($gst_no !== '' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i', $gst_no)) {
        apiResponse(400, 'Please enter a valid 15-digit GST Number (e.g. 24AAAAA0000A1Z5).');
    }

    if ($pincode !== '' && !preg_match('/^[0-9]{6}$/', $pincode)) {
        apiResponse(400, 'Pincode must be exactly 6 digits.');
    }

    if ($shipping_pincode !== '' && !preg_match('/^[0-9]{6}$/', $shipping_pincode)) {
        apiResponse(400, 'Shipping Pincode must be exactly 6 digits.');
    }

    if ($state_id > 0) {
        $stateCheck = $ai_db->aiGetQueryObj("SELECT id, state_name FROM tbl_state WHERE id = {$state_id} LIMIT 1");
        if (empty($stateCheck)) {
            apiResponse(400, 'Invalid State ID provided.');
        }
    }
    if ($city_id > 0) {
        $cityCheck = $ai_db->aiGetQueryObj("SELECT id, city_name FROM tbl_city WHERE id = {$city_id} LIMIT 1");
        if (empty($cityCheck)) {
            apiResponse(400, 'Invalid City ID provided.');
        }
    }

    $safePartyName = mysqli_real_escape_string($ai_conn, $party_name);
    $checkDup = $ai_db->aiGetQueryObj("SELECT id FROM tbl_party WHERE company_id = {$company_id} AND LOWER(party_name) = '" . strtolower($safePartyName) . "' LIMIT 1");
    if (!empty($checkDup)) {
        apiResponse(409, "Customer Name '{$party_name}' already exists for your company.");
    }

    $safeAddress        = mysqli_real_escape_string($ai_conn, $address);
    $safePincode        = mysqli_real_escape_string($ai_conn, $pincode);
    $safeShippingAddress= mysqli_real_escape_string($ai_conn, $shipping_address);
    $safeShippingPincode= mysqli_real_escape_string($ai_conn, $shipping_pincode);
    $safeMobileNo       = mysqli_real_escape_string($ai_conn, $mobile_no);
    $safeEmail          = mysqli_real_escape_string($ai_conn, $email);
    $safeGstNo          = mysqli_real_escape_string($ai_conn, $gst_no);
    $safePanNo          = mysqli_real_escape_string($ai_conn, $pan_no);
    $safePartyStatus    = mysqli_real_escape_string($ai_conn, $party_status);
    $safeBusinessType   = mysqli_real_escape_string($ai_conn, $business_type);
    $safeBalanceType    = mysqli_real_escape_string($ai_conn, $balance_type);
    $safeRemark         = mysqli_real_escape_string($ai_conn, $remark);
    $safeStatus         = mysqli_real_escape_string($ai_conn, $status);

    $insertQry = "INSERT INTO tbl_party SET 
                  company_id        = {$company_id},
                  party_name        = '{$safePartyName}',
                  address           = '{$safeAddress}',
                  state_id          = {$state_id},
                  city_id           = {$city_id},
                  pincode           = '{$safePincode}',
                  shipping_address  = '{$safeShippingAddress}',
                  shipping_state_id = {$shipping_state_id},
                  shipping_city_id  = {$shipping_city_id},
                  shipping_pincode  = '{$safeShippingPincode}',
                  mobile_no         = '{$safeMobileNo}',
                  email             = '{$safeEmail}',
                  gst_no            = '{$safeGstNo}',
                  pan_no            = '{$safePanNo}',
                  party_status      = '{$safePartyStatus}',
                  business_type     = '{$safeBusinessType}',
                  opening_balance   = {$opening_balance},
                  balance_type      = '{$safeBalanceType}',
                  outstanding       = {$outstanding},
                  remark            = '{$safeRemark}',
                  status            = '{$safeStatus}',
                  created_at        = NOW()";

    $res = mysqli_query($ai_conn, $insertQry);
    if (!$res) {
        apiResponse(500, 'Failed to add party: ' . mysqli_error($ai_conn));
    }

    $newPartyId = mysqli_insert_id($ai_conn);

    $stateRow     = $state_id > 0 ? $ai_db->aiGetQueryObj("SELECT state_name FROM tbl_state WHERE id = {$state_id} LIMIT 1") : [];
    $cityRow      = $city_id > 0 ? $ai_db->aiGetQueryObj("SELECT city_name FROM tbl_city WHERE id = {$city_id} LIMIT 1") : [];
    $shipStateRow = $shipping_state_id > 0 ? $ai_db->aiGetQueryObj("SELECT state_name FROM tbl_state WHERE id = {$shipping_state_id} LIMIT 1") : [];
    $shipCityRow  = $shipping_city_id > 0 ? $ai_db->aiGetQueryObj("SELECT city_name FROM tbl_city WHERE id = {$shipping_city_id} LIMIT 1") : [];

    apiResponse(200, 'Customer added successfully.', [
        'id'                  => $newPartyId,
        'company_id'          => $company_id,
        'company_name'        => $company['company_name'] ?? '',
        'customer_name'       => $party_name,
        'address'             => $address,
        'state_id'            => $state_id,
        'state_name'          => !empty($stateRow) ? $stateRow[0]->state_name : '',
        'city_id'             => $city_id,
        'city_name'           => !empty($cityRow) ? $cityRow[0]->city_name : '',
        'pincode'             => $pincode,
        'shipping_address'    => $shipping_address,
        'shipping_state_id'   => $shipping_state_id,
        'shipping_state_name' => !empty($shipStateRow) ? $shipStateRow[0]->state_name : '',
        'shipping_city_id'    => $shipping_city_id,
        'shipping_city_name'  => !empty($shipCityRow) ? $shipCityRow[0]->city_name : '',
        'shipping_pincode'    => $shipping_pincode,
        'mobile_no'           => $mobile_no,
        'email'               => $email,
        'gst_no'              => $gst_no,
        'pan_no'              => $pan_no,
        'party_status'        => $party_status,
        'business_type'       => $business_type,
        'opening_balance'     => $opening_balance,
        'balance_type'        => $balance_type,
        'outstanding'         => $outstanding,
        'remark'              => $remark,
        'status'              => $status
    ]);
}

if ($action === 'customer-edit') {
    if ($method !== 'GET') {
        apiResponse(405, 'Only GET method is allowed.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];
    $customerId = (int)($_GET['id'] ?? ($_GET['customer_id'] ?? 0));

    if ($customerId <= 0) {
        apiResponse(400, 'Customer id is required.');
    }

    $customerQry = "SELECT p.*, s.state_name, ct.city_name,
                           ss.state_name AS shipping_state_name, sc.city_name AS shipping_city_name 
                    FROM tbl_party p 
                    LEFT JOIN tbl_state s ON p.state_id = s.id 
                    LEFT JOIN tbl_city ct ON p.city_id = ct.id 
                    LEFT JOIN tbl_state ss ON p.shipping_state_id = ss.id
                    LEFT JOIN tbl_city sc ON p.shipping_city_id = sc.id
                    WHERE p.id = {$customerId} AND p.company_id = {$company_id} 
                    LIMIT 1";

    $customerRes = $ai_db->aiGetQueryObj($customerQry);
    if (empty($customerRes)) {
        apiResponse(404, 'Customer not found.');
    }

    $cust = $customerRes[0];

    // Compute customer's overdue from unpaid invoices past due date
    $todayDate = date('Y-m-d');
    $custOverdueRes = $ai_db->aiGetQueryObj("SELECT 
        COALESCE(SUM(grand_total - paid_amount), 0) AS overdue_amount,
        COUNT(id) AS overdue_invoices_count
    FROM tbl_invoice 
    WHERE company_id = {$company_id} 
      AND party_id = {$customerId} 
      AND status = 'active' 
      AND payment_status != 'Paid' 
      AND due_date IS NOT NULL 
      AND due_date < '{$todayDate}'");
    $custOverdueAmt = (float)($custOverdueRes[0]->overdue_amount ?? 0);
    $custOverdueCount = (int)($custOverdueRes[0]->overdue_invoices_count ?? 0);

    $sameAsBilling = (!empty($cust->address) && $cust->address === $cust->shipping_address)
        && ((int)$cust->state_id === (int)$cust->shipping_state_id)
        && ((int)$cust->city_id === (int)$cust->shipping_city_id)
        && (!empty($cust->pincode) && $cust->pincode === $cust->shipping_pincode);

    apiResponse(200, 'Customer details retrieved successfully.', [
        'id'                  => (int)$cust->id,
        'company_id'          => (int)$cust->company_id,
        'company_name'        => $company['company_name'] ?? '',
        'party_name'          => $cust->party_name ?? '',
        'customer_name'       => $cust->party_name ?? '',
        'mobile_no'           => $cust->mobile_no ?? '',
        'email'               => $cust->email ?? '',
        'gst_no'              => $cust->gst_no ?? '',
        'pan_no'              => $cust->pan_no ?? '',
        'party_status'        => $cust->party_status ?? 'Sales',
        'business_type'       => $cust->business_type ?? 'Business',
        'opening_balance'     => (float)($cust->opening_balance ?? 0),
        'balance_type'        => $cust->balance_type ?? 'Debit',
        'outstanding'         => (float)($cust->outstanding ?? 0),
        'outstanding_formatted' => '₹' . number_format((float)($cust->outstanding ?? 0), 2),
        'overdue_amount'      => $custOverdueAmt,
        'overdue_formatted'   => '₹' . number_format($custOverdueAmt, 2),
        'overdue_invoices_count' => $custOverdueCount,
        'has_overdue'         => ($custOverdueAmt > 0),
        'status'              => $cust->status ?? 'active',
        'remark'              => $cust->remark ?? '',

        'address'             => $cust->address ?? '',
        'billing_address'     => $cust->address ?? '',
        'state_id'            => (int)($cust->state_id ?? 0),
        'billing_state_id'    => (int)($cust->state_id ?? 0),
        'state_name'          => $cust->state_name ?? '',
        'billing_state_name'  => $cust->state_name ?? '',
        'city_id'             => (int)($cust->city_id ?? 0),
        'billing_city_id'     => (int)($cust->city_id ?? 0),
        'city_name'           => $cust->city_name ?? '',
        'billing_city_name'   => $cust->city_name ?? '',
        'pincode'             => $cust->pincode ?? '',
        'billing_pincode'     => $cust->pincode ?? '',

        'same_as_billing'     => $sameAsBilling,
        'shipping_address'    => $cust->shipping_address ?? '',
        'shipping_state_id'   => (int)($cust->shipping_state_id ?? 0),
        'shipping_state_name' => $cust->shipping_state_name ?? '',
        'shipping_city_id'    => (int)($cust->shipping_city_id ?? 0),
        'shipping_city_name'  => $cust->shipping_city_name ?? '',
        'shipping_pincode'    => $cust->shipping_pincode ?? '',
        'created_at'          => $cust->created_at ?? ''
    ]);
}

if ($action === 'customer-update') {
    if ($method !== 'POST' && $method !== 'PUT') {
        apiResponse(405, 'Method not allowed. Use POST or PUT.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];
    $customerId = (int)($input['id'] ?? ($input['customer_id'] ?? ($_GET['id'] ?? 0)));

    if ($customerId <= 0) {
        apiResponse(400, 'Customer id is required.');
    }

    $existing = $ai_db->aiGetQueryObj("SELECT * FROM tbl_party WHERE id = {$customerId} AND company_id = {$company_id} LIMIT 1");
    if (empty($existing)) {
        apiResponse(404, 'Customer not found.');
    }
    $currentCust = $existing[0];

    $party_name   = trim($input['customer_name'] ?? ($input['party_name'] ?? ($currentCust->party_name ?? '')));
    $mobile_no          = trim($input['mobile_no'] ?? ($input['mobile'] ?? ($currentCust->mobile_no ?? '')));
    $email              = trim($input['email'] ?? ($input['email_address'] ?? ($currentCust->email ?? '')));
    $gst_no             = trim($input['gst_no'] ?? ($input['gst_in_no'] ?? ($currentCust->gst_no ?? '')));
    $pan_no             = strtoupper(trim($input['pan_no'] ?? ($currentCust->pan_no ?? '')));
    $party_status       = trim($input['party_status'] ?? ($currentCust->party_status ?? 'Sales'));
    $business_type      = in_array($input['business_type'] ?? ($currentCust->business_type ?? ''), ['Individual', 'Business']) ? ($input['business_type'] ?? ($currentCust->business_type ?? 'Business')) : 'Business';
    $opening_balance    = isset($input['opening_balance']) ? (float)$input['opening_balance'] : (float)($currentCust->opening_balance ?? 0);
    $balance_type       = in_array($input['balance_type'] ?? ($currentCust->balance_type ?? ''), ['Credit', 'Debit']) ? ($input['balance_type'] ?? ($currentCust->balance_type ?? 'Debit')) : 'Debit';
    $outstanding        = isset($input['outstanding']) ? (float)$input['outstanding'] : (float)($currentCust->outstanding ?? 0);
    $remark             = trim($input['remark'] ?? ($currentCust->remark ?? ''));
    $status             = trim($input['status'] ?? ($currentCust->status ?? 'active'));

    $address            = trim($input['billing_address'] ?? ($input['address'] ?? ($currentCust->address ?? '')));
    $state_id           = isset($input['billing_state_id']) ? (int)$input['billing_state_id'] : (isset($input['state_id']) ? (int)$input['state_id'] : (int)($currentCust->state_id ?? 0));
    $city_id            = isset($input['billing_city_id']) ? (int)$input['billing_city_id'] : (isset($input['city_id']) ? (int)$input['city_id'] : (int)($currentCust->city_id ?? 0));
    $pincode            = trim($input['billing_pincode'] ?? ($input['pincode'] ?? ($currentCust->pincode ?? '')));

    $sameAsBilling = !empty($input['same_as_billing']) && ($input['same_as_billing'] === true || $input['same_as_billing'] === 'true' || $input['same_as_billing'] === 1 || $input['same_as_billing'] === '1');
    if ($sameAsBilling) {
        $shipping_address   = $address;
        $shipping_state_id  = $state_id;
        $shipping_city_id   = $city_id;
        $shipping_pincode   = $pincode;
    } else {
        $shipping_address   = trim($input['shipping_address'] ?? ($currentCust->shipping_address ?? ''));
        $shipping_state_id  = isset($input['shipping_state_id']) ? (int)$input['shipping_state_id'] : (int)($currentCust->shipping_state_id ?? 0);
        $shipping_city_id   = isset($input['shipping_city_id']) ? (int)$input['shipping_city_id'] : (int)($currentCust->shipping_city_id ?? 0);
        $shipping_pincode   = trim($input['shipping_pincode'] ?? ($currentCust->shipping_pincode ?? ''));
    }

    if (!in_array(strtolower($status), ['active', 'deactive'])) {
        $status = $currentCust->status ?? 'active';
    }
    if (!in_array($party_status, ['Sales', 'Purchase'])) {
        $party_status = $currentCust->party_status ?? 'Sales';
    }

    if ($party_name === '') {
        apiResponse(400, 'Customer Name is required.');
    }

    if ($mobile_no !== '' && !preg_match('/^[0-9]{10}$/', $mobile_no)) {
        apiResponse(400, 'Mobile Number must be exactly 10 digits.');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        apiResponse(400, 'Please enter a valid Email Address.');
    }

    if ($pan_no !== '' && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan_no)) {
        apiResponse(400, 'PAN Number must be valid 10-character alphanumeric (e.g. ABCDE1234F).');
    }

    if ($gst_no !== '' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i', $gst_no)) {
        apiResponse(400, 'Please enter a valid 15-digit GST Number (e.g. 24AAAAA0000A1Z5).');
    }

    if ($pincode !== '' && !preg_match('/^[0-9]{6}$/', $pincode)) {
        apiResponse(400, 'Pincode must be exactly 6 digits.');
    }

    if ($shipping_pincode !== '' && !preg_match('/^[0-9]{6}$/', $shipping_pincode)) {
        apiResponse(400, 'Shipping Pincode must be exactly 6 digits.');
    }

    if ($state_id > 0) {
        $stateCheck = $ai_db->aiGetQueryObj("SELECT id FROM tbl_state WHERE id = {$state_id} LIMIT 1");
        if (empty($stateCheck)) {
            apiResponse(400, 'Invalid State ID provided.');
        }
    }
    if ($city_id > 0) {
        $cityCheck = $ai_db->aiGetQueryObj("SELECT id FROM tbl_city WHERE id = {$city_id} LIMIT 1");
        if (empty($cityCheck)) {
            apiResponse(400, 'Invalid City ID provided.');
        }
    }

    $safePartyName = mysqli_real_escape_string($ai_conn, $party_name);
    $checkDup = $ai_db->aiGetQueryObj("SELECT id FROM tbl_party WHERE company_id = {$company_id} AND LOWER(party_name) = '" . strtolower($safePartyName) . "' AND id != {$customerId} LIMIT 1");
    if (!empty($checkDup)) {
        apiResponse(409, "Customer Name '{$party_name}' already exists for your company.");
    }

    $safeAddress        = mysqli_real_escape_string($ai_conn, $address);
    $safePincode        = mysqli_real_escape_string($ai_conn, $pincode);
    $safeShippingAddress= mysqli_real_escape_string($ai_conn, $shipping_address);
    $safeShippingPincode= mysqli_real_escape_string($ai_conn, $shipping_pincode);
    $safeMobileNo       = mysqli_real_escape_string($ai_conn, $mobile_no);
    $safeEmail          = mysqli_real_escape_string($ai_conn, $email);
    $safeGstNo          = mysqli_real_escape_string($ai_conn, $gst_no);
    $safePanNo          = mysqli_real_escape_string($ai_conn, $pan_no);
    $safePartyStatus    = mysqli_real_escape_string($ai_conn, $party_status);
    $safeBusinessType   = mysqli_real_escape_string($ai_conn, $business_type);
    $safeBalanceType    = mysqli_real_escape_string($ai_conn, $balance_type);
    $safeRemark         = mysqli_real_escape_string($ai_conn, $remark);
    $safeStatus         = mysqli_real_escape_string($ai_conn, $status);

    $updateQry = "UPDATE tbl_party SET 
                  party_name        = '{$safePartyName}',
                  address           = '{$safeAddress}',
                  state_id          = {$state_id},
                  city_id           = {$city_id},
                  pincode           = '{$safePincode}',
                  shipping_address  = '{$safeShippingAddress}',
                  shipping_state_id = {$shipping_state_id},
                  shipping_city_id  = {$shipping_city_id},
                  shipping_pincode  = '{$safeShippingPincode}',
                  mobile_no         = '{$safeMobileNo}',
                  email             = '{$safeEmail}',
                  gst_no            = '{$safeGstNo}',
                  pan_no            = '{$safePanNo}',
                  party_status      = '{$safePartyStatus}',
                  business_type     = '{$safeBusinessType}',
                  opening_balance   = {$opening_balance},
                  balance_type      = '{$safeBalanceType}',
                  outstanding       = {$outstanding},
                  remark            = '{$safeRemark}',
                  status            = '{$safeStatus}'
                  WHERE id = {$customerId} AND company_id = {$company_id}";

    $res = mysqli_query($ai_conn, $updateQry);
    if (!$res) {
        apiResponse(500, 'Failed to update customer: ' . mysqli_error($ai_conn));
    }

    $stateRow     = $state_id > 0 ? $ai_db->aiGetQueryObj("SELECT state_name FROM tbl_state WHERE id = {$state_id} LIMIT 1") : [];
    $cityRow      = $city_id > 0 ? $ai_db->aiGetQueryObj("SELECT city_name FROM tbl_city WHERE id = {$city_id} LIMIT 1") : [];
    $shipStateRow = $shipping_state_id > 0 ? $ai_db->aiGetQueryObj("SELECT state_name FROM tbl_state WHERE id = {$shipping_state_id} LIMIT 1") : [];
    $shipCityRow  = $shipping_city_id > 0 ? $ai_db->aiGetQueryObj("SELECT city_name FROM tbl_city WHERE id = {$shipping_city_id} LIMIT 1") : [];

    apiResponse(200, 'Customer updated successfully.', [
        'id'                  => $customerId,
        'company_id'          => $company_id,
        'customer_name'       => $party_name,
        'address'             => $address,
        'state_id'            => $state_id,
        'state_name'          => !empty($stateRow) ? $stateRow[0]->state_name : '',
        'city_id'             => $city_id,
        'city_name'           => !empty($cityRow) ? $cityRow[0]->city_name : '',
        'pincode'             => $pincode,
        'shipping_address'    => $shipping_address,
        'shipping_state_id'   => $shipping_state_id,
        'shipping_state_name' => !empty($shipStateRow) ? $shipStateRow[0]->state_name : '',
        'shipping_city_id'    => $shipping_city_id,
        'shipping_city_name'  => !empty($shipCityRow) ? $shipCityRow[0]->city_name : '',
        'shipping_pincode'    => $shipping_pincode,
        'mobile_no'           => $mobile_no,
        'email'               => $email,
        'gst_no'              => $gst_no,
        'pan_no'              => $pan_no,
        'party_status'        => $party_status,
        'business_type'       => $business_type,
        'opening_balance'     => $opening_balance,
        'balance_type'        => $balance_type,
        'outstanding'         => $outstanding,
        'remark'              => $remark,
        'status'              => $status
    ]);
}

if ($action === 'customer-delete') {
    if ($method !== 'DELETE' && $method !== 'POST') {
        apiResponse(405, 'Method not allowed. Use DELETE or POST.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];
    $customerId = (int)($input['id'] ?? ($input['customer_id'] ?? ($_GET['id'] ?? 0)));

    if ($customerId <= 0) {
        apiResponse(400, 'Customer id is required.');
    }

    $existing = $ai_db->aiGetQueryObj("SELECT id, party_name FROM tbl_party WHERE id = {$customerId} AND company_id = {$company_id} LIMIT 1");
    if (empty($existing)) {
        apiResponse(404, 'Customer not found.');
    }

    $custName = $existing[0]->party_name;

    $delQry = "DELETE FROM tbl_party WHERE id = {$customerId} AND company_id = {$company_id}";
    $res = mysqli_query($ai_conn, $delQry);

    if (!$res) {
        apiResponse(500, 'Failed to delete customer: ' . mysqli_error($ai_conn));
    }

    apiResponse(200, 'Customer deleted successfully.', [
        'id'            => $customerId,
        'customer_name' => $custName,
        'deleted'       => true
    ]);
}

if ($action === 'quotation-list') {
    if ($method !== 'GET' && $method !== 'POST') {
        apiResponse(405, 'Method not allowed. Use GET or POST.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $cId = (int)$company['id'];

    $filterPayment = trim($input['payment_status'] ?? ($_GET['payment_status'] ?? ''));
    $payCond = "";
    if (in_array($filterPayment, ['Pending', 'Paid'], true)) {
        $payCond = " AND q.payment_status = '{$filterPayment}'";
    } elseif ($filterPayment === 'Overdue') {
        $todayStr = date('Y-m-d');
        $payCond = " AND q.payment_status = 'Pending' AND q.due_date IS NOT NULL AND q.due_date < '{$todayStr}'";
    }

    $filterStatus = trim($input['status'] ?? ($_GET['status'] ?? ''));
    $statusCond = "";
    if ($filterStatus === 'active' || $filterStatus === 'deactive') {
        $statusCond = " AND q.status = '{$filterStatus}'";
    }

    $partyId = (int)($input['party_id'] ?? ($_GET['party_id'] ?? 0));
    $partyCond = "";
    if ($partyId > 0) {
        $partyCond = " AND q.party_id = {$partyId}";
    }

    $search = trim($input['search'] ?? ($_GET['search'] ?? ''));
    $searchCond = "";
    if ($search !== '') {
        $safeSearch = mysqli_real_escape_string($ai_conn, $search);
        $searchCond = " AND (q.quotation_no LIKE '%{$safeSearch}%' OR q.party_name LIKE '%{$safeSearch}%' OR q.gst_no LIKE '%{$safeSearch}%')";
    }

    $orderBy = "ORDER BY q.id DESC";

    $quotationQry = "SELECT q.*, c.company_name, p.mobile_no AS party_mobile, p.email AS party_email,
                            s.state_name, ct.city_name
                     FROM tbl_quotation q
                     LEFT JOIN tbl_company c ON q.company_id = c.id
                     LEFT JOIN tbl_party p ON q.party_id = p.id
                     LEFT JOIN tbl_state s ON q.state_id = s.id
                     LEFT JOIN tbl_city ct ON q.city_id = ct.id
                     WHERE q.company_id = {$cId} {$payCond} {$statusCond} {$partyCond} {$searchCond}
                     {$orderBy}";
    $quotations = $ai_db->aiGetQueryObj($quotationQry);

    $summaryQry = "SELECT 
                        COUNT(id) AS total_quotations,
                        COUNT(CASE WHEN payment_status = 'Pending' AND status = 'active' THEN 1 END) AS pending_count,
                        COALESCE(SUM(CASE WHEN payment_status = 'Pending' AND status = 'active' THEN grand_total ELSE 0 END), 0) AS pending_amount,
                        COUNT(CASE WHEN payment_status = 'Paid' THEN 1 END) AS paid_count,
                        COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN grand_total ELSE 0 END), 0) AS paid_amount,
                        COALESCE(SUM(grand_total), 0) AS total_amount
                   FROM tbl_quotation
                   WHERE company_id = {$cId}";
    $sumRes = $ai_db->aiGetQueryObj($summaryQry);
    $sumData = !empty($sumRes) ? $sumRes[0] : null;

    $totalCount = (int)($sumData->total_quotations ?? 0);
    $pendingCount = (int)($sumData->pending_count ?? 0);
    $pendingAmount = (float)($sumData->pending_amount ?? 0);
    $paidCount = (int)($sumData->paid_count ?? 0);
    $paidAmount = (float)($sumData->paid_amount ?? 0);
    $allTotalAmount = (float)($sumData->total_amount ?? 0);

    $includeItems = !empty($input['include_items']) || !empty($_GET['include_items']);

    $list = [];
    if (!empty($quotations)) {
        $itemsByQuotation = [];
        if ($includeItems) {
            $qIds = array_map(function($item) { return (int)$item->id; }, $quotations);
            if (!empty($qIds)) {
                $qIdsStr = implode(',', $qIds);
                $itemsRows = $ai_db->aiGetQueryObj("
                    SELECT qi.*, p.sku 
                    FROM tbl_quotation_items qi
                    LEFT JOIN tbl_product p ON qi.product_id = p.id
                    WHERE qi.quotation_id IN ({$qIdsStr})
                    ORDER BY qi.id ASC
                ");
                if (!empty($itemsRows)) {
                    foreach ($itemsRows as $iRow) {
                        $qid = (int)$iRow->quotation_id;
                        if (!isset($itemsByQuotation[$qid])) {
                            $itemsByQuotation[$qid] = [];
                        }
                        $itemsByQuotation[$qid][] = [
                            'id'              => (int)$iRow->id,
                            'product_id'      => (int)$iRow->product_id,
                            'description'     => $iRow->description ?? '',
                            'sku'             => $iRow->sku ?? '',
                            'hsn_code'        => $iRow->hsn_code ?? '',
                            'rate'            => (float)$iRow->rate,
                            'rate_formatted'  => '₹' . number_format((float)$iRow->rate, 2),
                            'gst_percent'     => (float)$iRow->gst_percent,
                            'discount_type'   => $iRow->discount_type ?? 'percentage',
                            'discount_value'  => (float)$iRow->discount_value,
                            'discount_amount' => (float)$iRow->discount_amount,
                            'qty'             => (float)$iRow->qty,
                            'net_amount'      => (float)$iRow->net_amount,
                            'tax_amount'      => (float)$iRow->tax_amount,
                            'total_amount'    => (float)$iRow->total_amount,
                            'total_formatted' => '₹' . number_format((float)$iRow->total_amount, 2)
                        ];
                    }
                }
            }
        }

        foreach ($quotations as $row) {
            $qid = (int)$row->id;
            $grandTotal = (float)$row->grand_total;
            $totalTaxable = (float)$row->total_amount;
            $taxAmount = (float)$row->tax_amount;
            $cgst = (float)$row->cgst_amount;
            $sgst = (float)$row->sgst_amount;
            $igst = (float)$row->igst_amount;
            $payStatus = $row->payment_status ?? 'Pending';
            $isActive = ($row->status === 'active');
            $isPaid = ($payStatus === 'Paid');

            $printUrl = rtrim(ADMIN_URL, '/') . '/quotation-print.php?id=' . $qid;

            $itemData = [
                'id'                     => $qid,
                'quotation_no'           => $row->quotation_no ?? '',
                'company_id'             => (int)$row->company_id,
                'company_name'           => $row->company_name ?? ($company['company_name'] ?? ''),
                'party_id'               => (int)$row->party_id,
                'party_name'             => $row->party_name ?? '',
                'party_mobile'           => $row->party_mobile ?? '',
                'party_email'            => $row->party_email ?? '',
                'gst_no'                 => $row->gst_no ?? '',
                'rca'                    => $row->rca ?? 'No',
                'address'                => $row->address ?? '',
                'state_id'               => (int)($row->state_id ?? 0),
                'state_name'             => $row->state_name ?? '',
                'city_id'                => (int)($row->city_id ?? 0),
                'city_name'              => $row->city_name ?? '',
                'quotation_date'         => $row->quotation_date ?? '',
                'quotation_date_formatted' => !empty($row->quotation_date) ? date('d-m-Y', strtotime($row->quotation_date)) : '',
                'due_date'               => $row->due_date ?? null,
                'due_date_formatted'     => !empty($row->due_date) ? date('d-m-Y', strtotime($row->due_date)) : '',
                'gst_type'               => $row->gst_type ?? 'with_gst',
                'taxable_amount'         => $totalTaxable,
                'taxable_formatted'      => '₹' . number_format($totalTaxable, 2),
                'cgst_amount'            => $cgst,
                'sgst_amount'            => $sgst,
                'igst_amount'            => $igst,
                'tax_amount'             => $taxAmount,
                'tax_amount_formatted'   => '₹' . number_format($taxAmount, 2),
                'grand_total'            => $grandTotal,
                'grand_total_formatted'  => '₹' . number_format($grandTotal, 2),
                'payment_status'         => $payStatus,
                'status'                 => $row->status ?? 'active',
                'is_overdue'             => ($payStatus === 'Pending' && !empty($row->due_date) && $row->due_date < date('Y-m-d')),
                'converted_invoice_id'   => (int)($row->converted_invoice_id ?? 0),
                'is_converted'           => (!empty($row->converted_invoice_id) && (int)$row->converted_invoice_id > 0),
                'is_locked'              => ($isPaid || (!empty($row->converted_invoice_id) && (int)$row->converted_invoice_id > 0)), 
                'print_url'              => $printUrl,
                'download_url'           => $printUrl . '&download=1',
                'created_at'             => $row->created_at ?? ''
            ];

            if ($includeItems) {
                $itemData['items'] = $itemsByQuotation[$qid] ?? [];
            }

            $list[] = $itemData;
        }
    }

    apiResponse(200, 'Quotation list retrieved successfully.', [
        'company_id'   => $cId,
        'company_name' => $company['company_name'] ?? '',
        'total'        => count($list),
        'summary'      => [
            'total_quotations'        => $totalCount,
            'pending_count'           => $pendingCount,
            'pending_amount'          => $pendingAmount,
            'pending_amount_formatted'=> '₹' . number_format($pendingAmount, 2),
            'paid_count'              => $paidCount,
            'paid_amount'             => $paidAmount,
            'paid_amount_formatted'   => '₹' . number_format($paidAmount, 2),
            'total_amount'            => $allTotalAmount,
            'total_amount_formatted'  => '₹' . number_format($allTotalAmount, 2)
        ],
        'quotations'   => $list
    ]);
}

if ($action === 'quotation-generate-no') {
    if ($method !== 'GET' && $method !== 'POST') {
        apiResponse(405, 'Method not allowed. Use GET or POST.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];

    $nextQuotationNo = generateQuotationNo($ai_db, $company_id);

    apiResponse(200, 'Next quotation number generated successfully.', [
        'company_id'   => $company_id,
        'quotation_no' => $nextQuotationNo
    ]);
}

if ($action === 'quotation-add') {
    if ($method !== 'POST') {
        apiResponse(405, 'Only POST method is allowed.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];
    $company_has_gst = (int)($company['gst_applicable'] ?? 0) === 1;

    $party_id = (int)($input['party_id'] ?? 0);
    $party_name = trim($input['party_name'] ?? '');
    $gst_no = trim($input['gst_no'] ?? '');
    $address = trim($input['address'] ?? '');
    $state_id = (int)($input['state_id'] ?? 0);
    $city_id = (int)($input['city_id'] ?? 0);
    $city_name = trim($input['city_name'] ?? '');

    if ($party_id > 0) {
        $partyRow = $ai_db->aiGetQueryObj("SELECT * FROM tbl_party WHERE id={$party_id} AND company_id={$company_id} LIMIT 1");
        if (empty($partyRow)) {
            apiResponse(404, 'Selected Customer/Party not found for this company.');
        }
        $pty = $partyRow[0];
        if (empty($party_name)) $party_name = $pty->party_name ?? '';
        if (empty($gst_no)) $gst_no = $pty->gst_no ?? '';
        if (empty($address)) $address = $pty->address ?? '';
        if ($state_id <= 0) $state_id = (int)($pty->state_id ?? 0);
        if ($city_id <= 0) $city_id = (int)($pty->city_id ?? 0);
    }

    if (empty($party_name)) {
        apiResponse(400, 'Party Name is required.');
    }

    if ($state_id <= 0) {
        apiResponse(400, 'State is required.');
    }

    $state_name = '';
    if ($state_id > 0) {
        $stObj = $ai_db->aiGetQueryObj("SELECT state_name FROM tbl_state WHERE id={$state_id} LIMIT 1");
        if (!empty($stObj)) {
            $state_name = $stObj[0]->state_name ?? '';
        }
    }

    if (empty($city_name) && $city_id > 0) {
        $ctObj = $ai_db->aiGetQueryObj("SELECT city_name FROM tbl_city WHERE id={$city_id} LIMIT 1");
        if (!empty($ctObj)) {
            $city_name = $ctObj[0]->city_name ?? '';
        }
    }

    $quotation_no = trim($input['quotation_no'] ?? '');
    if (empty($quotation_no)) {
        $quotation_no = generateQuotationNo($ai_db, $company_id);
    } else {
        $safeQNo = mysqli_real_escape_string($ai_conn, $quotation_no);
        $checkDupQ = $ai_db->aiGetQueryObj("SELECT id FROM tbl_quotation WHERE quotation_no='{$safeQNo}' AND company_id={$company_id} LIMIT 1");
        if (!empty($checkDupQ)) {
            apiResponse(400, "Quotation Number '{$quotation_no}' already exists for this company.");
        }
    }

    $rca = (isset($input['rca']) && strtolower(trim($input['rca'])) === 'yes') ? 'Yes' : 'No';
    $quotation_date = !empty($input['quotation_date']) ? date('Y-m-d', strtotime($input['quotation_date'])) : (!empty($input['date']) ? date('Y-m-d', strtotime($input['date'])) : date('Y-m-d'));
    
    $due_date = !empty($input['due_date']) ? date('Y-m-d', strtotime($input['due_date'])) : $quotation_date;
    if ($due_date < $quotation_date) {
        apiResponse(400, "Due Date cannot be earlier than Quotation Date ({$quotation_date}).");
    }

    if (!$company_has_gst) {
        $gst_no = '';
    }

    if ($company_has_gst && !empty($gst_no) && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i', $gst_no)) {
        apiResponse(400, 'Please enter a valid 15-digit GST Number (e.g. 24AAAAA0000A1Z5).');
    }

    $payment_status = (isset($input['payment_status']) && strtolower(trim($input['payment_status'])) === 'paid') ? 'Paid' : 'Pending';
    $status = (isset($input['status']) && strtolower(trim($input['status'])) === 'deactive') ? 'deactive' : 'active';

    $rawItems = $input['items'] ?? ($input['products'] ?? []);
    if (!is_array($rawItems) || count($rawItems) === 0) {
        apiResponse(400, 'At least one product item is required in items array.');
    }

    $processedItems = [];
    $totalTaxable = 0.00;
    $totalTax = 0.00;

    foreach ($rawItems as $idx => $it) {
        $desc = trim($it['description'] ?? ($it['product_name'] ?? ''));
        $prodId = (int)($it['product_id'] ?? 0);
        $hsn = trim($it['hsn_code'] ?? ($it['hsn'] ?? ''));

        if ($prodId <= 0) {
            $nameHint = !empty($desc) ? " for '{$desc}'" : "";
            apiResponse(400, "Item{$nameHint} (index {$idx}) must have a valid product_id.");
        }

        // Validate product exists, belongs to company, and is active
        $pFind = $ai_db->aiGetQueryObj("SELECT id, product_name, hsn_code, sales_price, gst_rate, status FROM tbl_product WHERE id={$prodId} AND company_id={$company_id} LIMIT 1");
        if (empty($pFind)) {
            $nameText = !empty($desc) ? " '{$desc}'" : "";
            apiResponse(404, "Product{$nameText} with ID {$prodId} (index {$idx}) does not exist for your company.");
        }
        if (strtolower($pFind[0]->status ?? '') !== 'active') {
            apiResponse(400, "Product '{$pFind[0]->product_name}' (ID {$prodId}) is deactivated. Only active products can be added to a quotation.");
        }

        if (empty($desc)) $desc = $pFind[0]->product_name ?? '';
        if (empty($hsn))  $hsn  = $pFind[0]->hsn_code ?? '';

        if (empty($desc)) {
            apiResponse(400, "Item at index {$idx} must have a valid description or product name.");
        }

        $rate = floatval($it['rate'] ?? ($it['price'] ?? 0));
        $qty = floatval($it['qty'] ?? ($it['quantity'] ?? 1));
        if ($qty <= 0) $qty = 1;
        $prodGstRate = floatval($pFind[0]->gst_rate ?? 18);
        $gstPct = $company_has_gst ? (isset($it['gst_percent']) ? floatval($it['gst_percent']) : (isset($it['gst_pct']) ? floatval($it['gst_pct']) : $prodGstRate)) : 0.00;

        $discType = in_array(strtolower(trim($it['discount_type'] ?? '')), ['fixed', 'percentage'], true) ? strtolower(trim($it['discount_type'])) : 'percentage';
        $discVal = floatval($it['discount_value'] ?? ($it['discount_val'] ?? 0));

        $baseAmt = $rate * $qty;
        $discAmt = 0.00;
        if ($discType === 'percentage') {
            $discAmt = ($baseAmt * $discVal) / 100.00;
        } else {
            $discAmt = min($baseAmt, $discVal);
        }
        if ($discAmt > $baseAmt) $discAmt = $baseAmt;

        $netAmt = $baseAmt - $discAmt;
        $itemTax = $company_has_gst ? (($netAmt * $gstPct) / 100.00) : 0.00;
        $itemTotal = $netAmt + $itemTax;

        $totalTaxable += $netAmt;
        $totalTax += $itemTax;

        $processedItems[] = [
            'product_id'      => $prodId,
            'description'     => $desc,
            'hsn_code'        => $hsn,
            'rate'            => $rate,
            'qty'             => $qty,
            'gst_percent'     => $gstPct,
            'discount_type'   => $discType,
            'discount_value'  => $discVal,
            'discount_amount' => $discAmt,
            'net_amount'      => $netAmt,
            'tax_amount'      => $itemTax,
            'total_amount'    => $itemTotal
        ];
    }

    $isGujarat = false;
    if (!empty($state_name) && stripos($state_name, 'gujarat') !== false) {
        $isGujarat = true;
    } elseif ($state_id === 7) { 
        $isGujarat = true;
    }

    $cgst_amount = 0.00;
    $sgst_amount = 0.00;
    $igst_amount = 0.00;

    if ($company_has_gst) {
        if ($isGujarat) {
            $cgst_amount = $totalTax / 2.00;
            $sgst_amount = $totalTax / 2.00;
            $igst_amount = 0.00;
        } else {
            $cgst_amount = 0.00;
            $sgst_amount = 0.00;
            $igst_amount = $totalTax;
        }
    }

    $grand_total = $totalTaxable + $totalTax;

    $safePartyName = mysqli_real_escape_string($ai_conn, $party_name);
    $safeGstNo = mysqli_real_escape_string($ai_conn, $gst_no);
    $safeQNo = mysqli_real_escape_string($ai_conn, $quotation_no);
    $safeAddress = mysqli_real_escape_string($ai_conn, $address);
    $safeStateName = mysqli_real_escape_string($ai_conn, $state_name);
    $safeCityName = mysqli_real_escape_string($ai_conn, $city_name);
    $dueSql = $due_date ? "'{$due_date}'" : "NULL";
    $dbGstType = $company_has_gst ? 'with_gst' : 'without_gst';

    $insertQuotationQry = "INSERT INTO tbl_quotation SET
        company_id = {$company_id},
        party_id = {$party_id},
        party_name = '{$safePartyName}',
        gst_no = '{$safeGstNo}',
        quotation_no = '{$safeQNo}',
        rca = '{$rca}',
        address = '{$safeAddress}',
        state_id = {$state_id},
        city_id = {$city_id},
        quotation_date = '{$quotation_date}',
        due_date = {$dueSql},
        gst_type = '{$dbGstType}',
        total_amount = {$totalTaxable},
        cgst_amount = {$cgst_amount},
        sgst_amount = {$sgst_amount},
        igst_amount = {$igst_amount},
        tax_amount = {$totalTax},
        grand_total = {$grand_total},
        payment_status = '{$payment_status}',
        status = '{$status}'";

    $insRes = mysqli_query($ai_conn, $insertQuotationQry);
    if (!$insRes) {
        apiResponse(500, 'Failed to save quotation: ' . mysqli_error($ai_conn));
    }

    $newQuotationId = (int)mysqli_insert_id($ai_conn);

    foreach ($processedItems as $pItem) {
        $pDesc = mysqli_real_escape_string($ai_conn, $pItem['description']);
        $pHsn = mysqli_real_escape_string($ai_conn, $pItem['hsn_code']);

        $itemQry = "INSERT INTO tbl_quotation_items SET
            quotation_id = {$newQuotationId},
            product_id = {$pItem['product_id']},
            description = '{$pDesc}',
            hsn_code = '{$pHsn}',
            rate = {$pItem['rate']},
            gst_percent = {$pItem['gst_percent']},
            discount_type = '{$pItem['discount_type']}',
            discount_value = {$pItem['discount_value']},
            discount_amount = {$pItem['discount_amount']},
            qty = {$pItem['qty']},
            net_amount = {$pItem['net_amount']},
            tax_amount = {$pItem['tax_amount']},
            total_amount = {$pItem['total_amount']}";
        mysqli_query($ai_conn, $itemQry);
    }

    if ($party_id > 0 && function_exists('recalculatePartyOutstanding')) {
        recalculatePartyOutstanding($party_id);
    }
    $printUrl = rtrim(ADMIN_URL, '/') . '/quotation-print.php?id=' . $newQuotationId;

    apiResponse(200, 'Quotation created successfully.', [
        'id'                     => $newQuotationId,
        'quotation_no'           => $quotation_no,
        'company_id'             => $company_id,
        'party_id'               => $party_id,
        'party_name'             => $party_name,
        'gst_no'                 => $gst_no,
        'quotation_date'         => $quotation_date,
        'quotation_date_formatted' => date('d-m-Y', strtotime($quotation_date)),
        'due_date'               => $due_date,
        'due_date_formatted'     => date('d-m-Y', strtotime($due_date)),
        'taxable_amount'         => round($totalTaxable, 2),
        'taxable_formatted'      => '₹' . number_format($totalTaxable, 2),
        'cgst_amount'            => round($cgst_amount, 2),
        'sgst_amount'            => round($sgst_amount, 2),
        'igst_amount'            => round($igst_amount, 2),
        'tax_amount'             => round($totalTax, 2),
        'tax_amount_formatted'   => '₹' . number_format($totalTax, 2),
        'grand_total'            => round($grand_total, 2),
        'grand_total_formatted'  => '₹' . number_format($grand_total, 2),
        'payment_status'         => $payment_status,
        'status'                 => $status,
        'items_count'            => count($processedItems),
        'print_url'              => $printUrl,
        'download_url'           => $printUrl . '&download=1'
    ]);
}

if ($action === 'quotation-edit') {
    if ($method !== 'GET' && $method !== 'POST') {
        apiResponse(405, 'Method not allowed. Use GET or POST.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];
    $quotationId = (int)($input['id'] ?? ($input['quotation_id'] ?? ($_GET['id'] ?? ($_GET['quotation_id'] ?? 0))));

    if ($quotationId <= 0) {
        apiResponse(400, 'Quotation id is required.');
    }

    $quotationRes = $ai_db->aiGetQueryObj("
        SELECT q.*, c.company_name, 
               p.mobile_no AS party_mobile, p.email AS party_email,
               s.state_name, ct.city_name
        FROM tbl_quotation q
        LEFT JOIN tbl_company c ON q.company_id = c.id
        LEFT JOIN tbl_party p ON q.party_id = p.id
        LEFT JOIN tbl_state s ON q.state_id = s.id
        LEFT JOIN tbl_city ct ON q.city_id = ct.id
        WHERE q.id = {$quotationId} AND q.company_id = {$company_id}
        LIMIT 1
    ");

    if (empty($quotationRes)) {
        apiResponse(404, 'Quotation not found.');
    }

    $q = $quotationRes[0];

    // Fetch items with product information
    $itemsRows = $ai_db->aiGetQueryObj("
        SELECT qi.*, p.sku, p.product_name, p.status AS product_status
        FROM tbl_quotation_items qi
        LEFT JOIN tbl_product p ON qi.product_id = p.id
        WHERE qi.quotation_id = {$quotationId}
        ORDER BY qi.id ASC
    ");

    $items = [];
    if (!empty($itemsRows)) {
        foreach ($itemsRows as $iRow) {
            $items[] = [
                'id'              => (int)$iRow->id,
                'product_id'      => (int)$iRow->product_id,
                'product_name'    => $iRow->product_name ?? '',
                'product_status'  => $iRow->product_status ?? 'active',
                'description'     => $iRow->description ?? '',
                'sku'             => $iRow->sku ?? '',
                'hsn_code'        => $iRow->hsn_code ?? '',
                'rate'            => (float)$iRow->rate,
                'rate_formatted'  => '₹' . number_format((float)$iRow->rate, 2),
                'gst_percent'     => (float)$iRow->gst_percent,
                'discount_type'   => $iRow->discount_type ?? 'percentage',
                'discount_value'  => (float)$iRow->discount_value,
                'discount_amount' => (float)$iRow->discount_amount,
                'qty'             => (float)$iRow->qty,
                'net_amount'      => (float)$iRow->net_amount,
                'tax_amount'      => (float)$iRow->tax_amount,
                'total_amount'    => (float)$iRow->total_amount,
                'total_formatted' => '₹' . number_format((float)$iRow->total_amount, 2)
            ];
        }
    }

    $printUrl = rtrim(ADMIN_URL, '/') . '/quotation-print.php?id=' . $quotationId;
    $payStatus = $q->payment_status ?? 'Pending';
    $isConverted = (!empty($q->converted_invoice_id) && (int)$q->converted_invoice_id > 0);

    apiResponse(200, 'Quotation details retrieved successfully.', [
        'id'                       => (int)$q->id,
        'quotation_no'             => $q->quotation_no ?? '',
        'company_id'               => (int)$q->company_id,
        'company_name'             => $q->company_name ?? ($company['company_name'] ?? ''),
        'party_id'                 => (int)$q->party_id,
        'party_name'               => $q->party_name ?? '',
        'party_mobile'             => $q->party_mobile ?? '',
        'party_email'              => $q->party_email ?? '',
        'gst_no'                   => $q->gst_no ?? '',
        'rca'                      => $q->rca ?? 'No',
        'address'                  => $q->address ?? '',
        'state_id'                 => (int)($q->state_id ?? 0),
        'state_name'               => $q->state_name ?? '',
        'city_id'                  => (int)($q->city_id ?? 0),
        'city_name'                => $q->city_name ?? '',
        'quotation_date'           => $q->quotation_date ?? '',
        'quotation_date_formatted' => !empty($q->quotation_date) ? date('d-m-Y', strtotime($q->quotation_date)) : '',
        'due_date'                 => $q->due_date ?? null,
        'due_date_formatted'       => !empty($q->due_date) ? date('d-m-Y', strtotime($q->due_date)) : '',
        'gst_type'                 => $q->gst_type ?? 'with_gst',
        'taxable_amount'           => (float)($q->total_amount ?? 0),
        'taxable_formatted'        => '₹' . number_format((float)($q->total_amount ?? 0), 2),
        'cgst_amount'              => (float)($q->cgst_amount ?? 0),
        'sgst_amount'              => (float)($q->sgst_amount ?? 0),
        'igst_amount'              => (float)($q->igst_amount ?? 0),
        'tax_amount'               => (float)($q->tax_amount ?? 0),
        'tax_amount_formatted'     => '₹' . number_format((float)($q->tax_amount ?? 0), 2),
        'grand_total'              => (float)($q->grand_total ?? 0),
        'grand_total_formatted'    => '₹' . number_format((float)($q->grand_total ?? 0), 2),
        'payment_status'           => $payStatus,
        'status'                   => $q->status ?? 'active',
        'is_converted'             => $isConverted,
        'converted_invoice_id'     => (int)($q->converted_invoice_id ?? 0),
        'is_locked'                => ($payStatus === 'Paid' || $isConverted),
        'print_url'                => $printUrl,
        'download_url'             => $printUrl . '&download=1',
        'items'                    => $items,
        'created_at'               => $q->created_at ?? ''
    ]);
}

if ($action === 'quotation-update') {
    if ($method !== 'POST' && $method !== 'PUT') {
        apiResponse(405, 'Method not allowed. Use POST or PUT.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];
    $company_has_gst = ((int)($company['gst_applicable'] ?? 0) === 1);

    $quotationId = (int)($input['id'] ?? ($input['quotation_id'] ?? ($_GET['id'] ?? ($_GET['quotation_id'] ?? 0))));
    if ($quotationId <= 0) {
        apiResponse(400, 'Quotation id is required.');
    }

    $existingQ = $ai_db->aiGetQueryObj("SELECT * FROM tbl_quotation WHERE id = {$quotationId} AND company_id = {$company_id} LIMIT 1");
    if (empty($existingQ)) {
        apiResponse(404, 'Quotation not found.');
    }
    $currQ = $existingQ[0];
    $prevPartyId = (int)($currQ->party_id ?? 0);

    // If already paid or converted to invoice, prevent modifying critical values
    if (($currQ->payment_status ?? '') === 'Paid') {
        apiResponse(400, 'This quotation is marked as Paid and cannot be modified.');
    }
    if (!empty($currQ->converted_invoice_id) && (int)$currQ->converted_invoice_id > 0) {
        apiResponse(400, 'This quotation has already been converted to an invoice and cannot be modified.');
    }

    $party_id = isset($input['party_id']) ? (int)$input['party_id'] : (int)($currQ->party_id ?? 0);
    $party_name = trim($input['party_name'] ?? ($currQ->party_name ?? ''));
    $gst_no = trim($input['gst_no'] ?? ($currQ->gst_no ?? ''));
    $address = trim($input['address'] ?? ($currQ->address ?? ''));
    $state_id = isset($input['state_id']) ? (int)$input['state_id'] : (int)($currQ->state_id ?? 0);
    $city_id = isset($input['city_id']) ? (int)$input['city_id'] : (int)($currQ->city_id ?? 0);

    if ($party_id > 0) {
        $partyRow = $ai_db->aiGetQueryObj("SELECT * FROM tbl_party WHERE id={$party_id} AND company_id={$company_id} LIMIT 1");
        if (empty($partyRow)) {
            apiResponse(404, 'Selected Customer/Party not found for this company.');
        }
        $pty = $partyRow[0];
        if (empty($party_name)) $party_name = $pty->party_name ?? '';
        if (empty($gst_no)) $gst_no = $pty->gst_no ?? '';
        if (empty($address)) $address = $pty->address ?? '';
        if ($state_id <= 0) $state_id = (int)($pty->state_id ?? 0);
        if ($city_id <= 0) $city_id = (int)($pty->city_id ?? 0);
    }

    if (empty($party_name)) {
        apiResponse(400, 'Party Name is required.');
    }
    if ($state_id <= 0) {
        apiResponse(400, 'State is required.');
    }

    $state_name = '';
    if ($state_id > 0) {
        $stObj = $ai_db->aiGetQueryObj("SELECT state_name FROM tbl_state WHERE id={$state_id} LIMIT 1");
        if (!empty($stObj)) {
            $state_name = $stObj[0]->state_name ?? '';
        }
    }

    $quotation_no = trim($input['quotation_no'] ?? ($currQ->quotation_no ?? ''));
    if (empty($quotation_no)) {
        $quotation_no = $currQ->quotation_no;
    } else {
        $safeQNo = mysqli_real_escape_string($ai_conn, $quotation_no);
        $checkDupQ = $ai_db->aiGetQueryObj("SELECT id FROM tbl_quotation WHERE quotation_no='{$safeQNo}' AND company_id={$company_id} AND id != {$quotationId} LIMIT 1");
        if (!empty($checkDupQ)) {
            apiResponse(400, "Quotation Number '{$quotation_no}' is already used by another quotation.");
        }
    }

    $rca = isset($input['rca']) ? ((strtolower(trim($input['rca'])) === 'yes') ? 'Yes' : 'No') : ($currQ->rca ?? 'No');
    $quotation_date = !empty($input['quotation_date']) ? date('Y-m-d', strtotime($input['quotation_date'])) : ($currQ->quotation_date ?? date('Y-m-d'));
    $due_date = !empty($input['due_date']) ? date('Y-m-d', strtotime($input['due_date'])) : (!empty($currQ->due_date) ? $currQ->due_date : $quotation_date);
    if ($due_date < $quotation_date) {
        apiResponse(400, "Due Date cannot be earlier than Quotation Date ({$quotation_date}).");
    }

    if (!$company_has_gst) {
        $gst_no = '';
    }
    if ($company_has_gst && !empty($gst_no) && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i', $gst_no)) {
        apiResponse(400, 'Please enter a valid 15-digit GST Number (e.g. 24AAAAA0000A1Z5).');
    }

    $payment_status = isset($input['payment_status']) ? ((strtolower(trim($input['payment_status'])) === 'paid') ? 'Paid' : 'Pending') : ($currQ->payment_status ?? 'Pending');
    $status = isset($input['status']) ? ((strtolower(trim($input['status'])) === 'deactive') ? 'deactive' : 'active') : ($currQ->status ?? 'active');

    $rawItems = $input['items'] ?? ($input['products'] ?? null);
    if ($rawItems !== null && (!is_array($rawItems) || count($rawItems) === 0)) {
        apiResponse(400, 'At least one product item is required.');
    }

    // Process items if provided; otherwise reuse existing items to calculate totals
    $processedItems = [];
    $totalTaxable = 0.00;
    $totalTax = 0.00;

    if ($rawItems !== null) {
        foreach ($rawItems as $idx => $it) {
            $desc = trim($it['description'] ?? ($it['product_name'] ?? ''));
            $prodId = (int)($it['product_id'] ?? 0);
            $hsn = trim($it['hsn_code'] ?? ($it['hsn'] ?? ''));

            if ($prodId <= 0) {
                $nameHint = !empty($desc) ? " for '{$desc}'" : "";
                apiResponse(400, "Item{$nameHint} (index {$idx}) must have a valid product_id.");
            }

            // Validate product exists, belongs to company, and is active
            $pFind = $ai_db->aiGetQueryObj("SELECT id, product_name, hsn_code, sales_price, gst_rate, status FROM tbl_product WHERE id={$prodId} AND company_id={$company_id} LIMIT 1");
            if (empty($pFind)) {
                $nameText = !empty($desc) ? " '{$desc}'" : "";
                apiResponse(404, "Product{$nameText} with ID {$prodId} (index {$idx}) does not exist for your company.");
            }
            if (strtolower($pFind[0]->status ?? '') !== 'active') {
                apiResponse(400, "Product '{$pFind[0]->product_name}' (ID {$prodId}) is deactivated. Only active products can be added to a quotation.");
            }

            if (empty($desc)) $desc = $pFind[0]->product_name ?? '';
            if (empty($hsn))  $hsn  = $pFind[0]->hsn_code ?? '';

            if (empty($desc)) {
                apiResponse(400, "Item at index {$idx} must have a valid description or product name.");
            }

            $rate = floatval($it['rate'] ?? ($it['price'] ?? 0));
            $qty = floatval($it['qty'] ?? ($it['quantity'] ?? 1));
            if ($qty <= 0) $qty = 1;
            $prodGstRate = floatval($pFind[0]->gst_rate ?? 18);
            $gstPct = $company_has_gst ? (isset($it['gst_percent']) ? floatval($it['gst_percent']) : (isset($it['gst_pct']) ? floatval($it['gst_pct']) : $prodGstRate)) : 0.00;

            $discType = in_array(strtolower(trim($it['discount_type'] ?? '')), ['fixed', 'percentage'], true) ? strtolower(trim($it['discount_type'])) : 'percentage';
            $discVal = floatval($it['discount_value'] ?? ($it['discount_val'] ?? 0));

            $baseAmt = $rate * $qty;
            $discAmt = 0.00;
            if ($discType === 'percentage') {
                $discAmt = ($baseAmt * $discVal) / 100.00;
            } else {
                $discAmt = min($baseAmt, $discVal);
            }
            if ($discAmt > $baseAmt) $discAmt = $baseAmt;

            $netAmt = $baseAmt - $discAmt;
            $itemTax = $company_has_gst ? (($netAmt * $gstPct) / 100.00) : 0.00;
            $itemTotal = $netAmt + $itemTax;

            $totalTaxable += $netAmt;
            $totalTax += $itemTax;

            $processedItems[] = [
                'product_id'      => $prodId,
                'description'     => $desc,
                'hsn_code'        => $hsn,
                'rate'            => $rate,
                'qty'             => $qty,
                'gst_percent'     => $gstPct,
                'discount_type'   => $discType,
                'discount_value'  => $discVal,
                'discount_amount' => $discAmt,
                'net_amount'      => $netAmt,
                'tax_amount'      => $itemTax,
                'total_amount'    => $itemTotal
            ];
        }
    } else {
        // Items array not provided in update payload: retain existing totals
        $totalTaxable = (float)($currQ->total_amount ?? 0);
        $totalTax = (float)($currQ->tax_amount ?? 0);
    }

    $isGujarat = false;
    if (!empty($state_name) && stripos($state_name, 'gujarat') !== false) {
        $isGujarat = true;
    } elseif ($state_id === 7) { 
        $isGujarat = true;
    }

    $cgst_amount = 0.00;
    $sgst_amount = 0.00;
    $igst_amount = 0.00;

    if ($company_has_gst) {
        if ($isGujarat) {
            $cgst_amount = $totalTax / 2.00;
            $sgst_amount = $totalTax / 2.00;
            $igst_amount = 0.00;
        } else {
            $cgst_amount = 0.00;
            $sgst_amount = 0.00;
            $igst_amount = $totalTax;
        }
    }

    $grand_total = $totalTaxable + $totalTax;

    $safePartyName = mysqli_real_escape_string($ai_conn, $party_name);
    $safeGstNo = mysqli_real_escape_string($ai_conn, $gst_no);
    $safeQNo = mysqli_real_escape_string($ai_conn, $quotation_no);
    $safeAddress = mysqli_real_escape_string($ai_conn, $address);
    $dueSql = $due_date ? "'{$due_date}'" : "NULL";
    $dbGstType = $company_has_gst ? 'with_gst' : 'without_gst';

    $updateQuotationQry = "UPDATE tbl_quotation SET
        party_id = {$party_id},
        party_name = '{$safePartyName}',
        gst_no = '{$safeGstNo}',
        quotation_no = '{$safeQNo}',
        rca = '{$rca}',
        address = '{$safeAddress}',
        state_id = {$state_id},
        city_id = {$city_id},
        quotation_date = '{$quotation_date}',
        due_date = {$dueSql},
        gst_type = '{$dbGstType}',
        total_amount = {$totalTaxable},
        cgst_amount = {$cgst_amount},
        sgst_amount = {$sgst_amount},
        igst_amount = {$igst_amount},
        tax_amount = {$totalTax},
        grand_total = {$grand_total},
        payment_status = '{$payment_status}',
        status = '{$status}'
        WHERE id = {$quotationId} AND company_id = {$company_id}";

    $updRes = mysqli_query($ai_conn, $updateQuotationQry);
    if (!$updRes) {
        apiResponse(500, 'Failed to update quotation: ' . mysqli_error($ai_conn));
    }

    // Re-insert items if new items array was supplied
    if ($rawItems !== null) {
        mysqli_query($ai_conn, "DELETE FROM tbl_quotation_items WHERE quotation_id = {$quotationId}");
        foreach ($processedItems as $pItem) {
            $pDesc = mysqli_real_escape_string($ai_conn, $pItem['description']);
            $pHsn = mysqli_real_escape_string($ai_conn, $pItem['hsn_code']);

            $itemQry = "INSERT INTO tbl_quotation_items SET
                quotation_id = {$quotationId},
                product_id = {$pItem['product_id']},
                description = '{$pDesc}',
                hsn_code = '{$pHsn}',
                rate = {$pItem['rate']},
                gst_percent = {$pItem['gst_percent']},
                discount_type = '{$pItem['discount_type']}',
                discount_value = {$pItem['discount_value']},
                discount_amount = {$pItem['discount_amount']},
                qty = {$pItem['qty']},
                net_amount = {$pItem['net_amount']},
                tax_amount = {$pItem['tax_amount']},
                total_amount = {$pItem['total_amount']}";
            mysqli_query($ai_conn, $itemQry);
        }
    }

    // Recalculate outstanding for parties
    if (function_exists('recalculatePartyOutstanding')) {
        if ($party_id > 0) recalculatePartyOutstanding($party_id);
        if ($prevPartyId > 0 && $prevPartyId !== $party_id) recalculatePartyOutstanding($prevPartyId);
    }

    $printUrl = rtrim(ADMIN_URL, '/') . '/quotation-print.php?id=' . $quotationId;

    apiResponse(200, 'Quotation updated successfully.', [
        'id'                       => $quotationId,
        'quotation_no'             => $quotation_no,
        'company_id'               => $company_id,
        'party_id'                 => $party_id,
        'party_name'               => $party_name,
        'gst_no'                   => $gst_no,
        'quotation_date'           => $quotation_date,
        'quotation_date_formatted' => date('d-m-Y', strtotime($quotation_date)),
        'due_date'                 => $due_date,
        'due_date_formatted'       => date('d-m-Y', strtotime($due_date)),
        'taxable_amount'           => round($totalTaxable, 2),
        'taxable_formatted'        => '₹' . number_format($totalTaxable, 2),
        'cgst_amount'              => round($cgst_amount, 2),
        'sgst_amount'              => round($sgst_amount, 2),
        'igst_amount'              => round($igst_amount, 2),
        'tax_amount'               => round($totalTax, 2),
        'tax_amount_formatted'     => '₹' . number_format($totalTax, 2),
        'grand_total'              => round($grand_total, 2),
        'grand_total_formatted'    => '₹' . number_format($grand_total, 2),
        'payment_status'           => $payment_status,
        'status'                   => $status,
        'items_count'              => ($rawItems !== null) ? count($processedItems) : count($ai_db->aiGetQueryObj("SELECT id FROM tbl_quotation_items WHERE quotation_id = {$quotationId}")),
        'print_url'                => $printUrl,
        'download_url'             => $printUrl . '&download=1'
    ]);
}

if ($action === 'quotation-delete') {
    if ($method !== 'DELETE' && $method !== 'POST') {
        apiResponse(405, 'Method not allowed. Use DELETE or POST.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];
    $quotationId = (int)($input['id'] ?? ($input['quotation_id'] ?? ($_GET['id'] ?? ($_GET['quotation_id'] ?? 0))));

    if ($quotationId <= 0) {
        apiResponse(400, 'Quotation id is required.');
    }

    $existing = $ai_db->aiGetQueryObj("SELECT id, quotation_no, party_id, payment_status, converted_invoice_id FROM tbl_quotation WHERE id = {$quotationId} AND company_id = {$company_id} LIMIT 1");
    if (empty($existing)) {
        apiResponse(404, 'Quotation not found.');
    }

    $qRow = $existing[0];
    if (!empty($qRow->converted_invoice_id) && (int)$qRow->converted_invoice_id > 0) {
        apiResponse(400, "Quotation '{$qRow->quotation_no}' has already been converted to an invoice and cannot be deleted.");
    }

    $partyId = (int)($qRow->party_id ?? 0);
    $quotNo  = $qRow->quotation_no ?? '';

    // Delete quotation and associated items
    $delQry = "DELETE FROM tbl_quotation WHERE id = {$quotationId} AND company_id = {$company_id}";
    $res = mysqli_query($ai_conn, $delQry);

    if (!$res) {
        apiResponse(500, 'Failed to delete quotation: ' . mysqli_error($ai_conn));
    }

    mysqli_query($ai_conn, "DELETE FROM tbl_quotation_items WHERE quotation_id = {$quotationId}");

    if ($partyId > 0 && function_exists('recalculatePartyOutstanding')) {
        recalculatePartyOutstanding($partyId);
    }

    apiResponse(200, 'Quotation deleted successfully.', [
        'id'           => $quotationId,
        'quotation_no' => $quotNo,
        'deleted'      => true
    ]);
}

if ($action === 'quotation-convert-to-invoice') {
    if ($method !== 'POST') {
        apiResponse(405, 'Only POST method is allowed.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];
    $quotationId = (int)($input['quotation_id'] ?? ($input['id'] ?? ($_GET['quotation_id'] ?? ($_GET['id'] ?? 0))));

    if ($quotationId <= 0) {
        apiResponse(400, 'Quotation id is required.');
    }

    $existing = $ai_db->aiGetQueryObj("SELECT * FROM tbl_quotation WHERE id = {$quotationId} AND company_id = {$company_id} LIMIT 1");
    if (empty($existing)) {
        apiResponse(404, 'Quotation not found.');
    }

    $quot = $existing[0];

    // Check if already converted
    if (!empty($quot->converted_invoice_id) && (int)$quot->converted_invoice_id > 0) {
        $convInvId = (int)$quot->converted_invoice_id;
        $invCheck = $ai_db->aiGetQueryObj("SELECT invoice_no FROM tbl_invoice WHERE id = {$convInvId} LIMIT 1");
        $invNoStr = !empty($invCheck) ? $invCheck[0]->invoice_no : "#{$convInvId}";
        apiResponse(400, "Quotation '{$quot->quotation_no}' has already been converted to invoice {$invNoStr}.", [
            'already_converted'    => true,
            'quotation_id'         => $quotationId,
            'converted_invoice_id' => $convInvId,
            'invoice_no'           => !empty($invCheck) ? $invCheck[0]->invoice_no : ''
        ]);
    }

    // Fetch items
    $qItems = $ai_db->aiGetQueryObj("SELECT * FROM tbl_quotation_items WHERE quotation_id = {$quotationId} ORDER BY id ASC");
    if (empty($qItems)) {
        apiResponse(400, 'Quotation has no items to convert.');
    }

    // Check stock availability for all products in this quotation BEFORE creating invoice
    $outOfStockErrors = [];
    foreach ($qItems as $chkItem) {
        $pId = (int)($chkItem->product_id ?? 0);
        $reqQty = floatval($chkItem->qty ?? 0);
        $itemDesc = trim($chkItem->description ?? '');

        // Fallback product_id lookup by product_name if missing
        if ($pId <= 0 && !empty($itemDesc)) {
            $escDesc = mysqli_real_escape_string($ai_conn, $itemDesc);
            $pFind = $ai_db->aiGetQueryObj("SELECT id FROM tbl_product WHERE LOWER(TRIM(product_name)) = LOWER(TRIM('{$escDesc}')) AND company_id = {$company_id} LIMIT 1");
            if (!empty($pFind)) {
                $pId = (int)$pFind[0]->id;
            }
        }

        if ($pId > 0 && function_exists('checkProductStockAvailability')) {
            $avail = checkProductStockAvailability($pId, $reqQty);
            if (!empty($avail['track']) && !$avail['available']) {
                $pTitle = !empty($avail['product_name']) ? $avail['product_name'] : $itemDesc;
                $outOfStockErrors[] = [
                    'product_id'      => $pId,
                    'product_name'    => $pTitle,
                    'required_qty'    => $reqQty,
                    'available_stock' => floatval($avail['stock'] ?? 0)
                ];
            }
        }
    }

    if (!empty($outOfStockErrors)) {
        $errStrings = [];
        foreach ($outOfStockErrors as $err) {
            $errStrings[] = "• " . $err['product_name'] . " (Required: " . number_format($err['required_qty'], 2) . ", Available: " . number_format($err['available_stock'], 2) . ")";
        }
        apiResponse(400, "Cannot convert quotation to invoice due to insufficient stock:\n" . implode("\n", $errStrings), [
            'insufficient_stock' => true,
            'details'            => $outOfStockErrors
        ]);
    }

    // Generate Invoice Number (or accept custom invoice_no if passed in payload)
    $invNo = trim($input['invoice_no'] ?? '');
    if (empty($invNo)) {
        $invNo = generateInvoiceNo($ai_db, $company_id);
    }

    $safeInvNo = mysqli_real_escape_string($ai_conn, $invNo);
    $checkDup = $ai_db->aiGetQueryObj("SELECT id FROM tbl_invoice WHERE invoice_no = '{$safeInvNo}' AND company_id = {$company_id} LIMIT 1");
    if (!empty($checkDup)) {
        apiResponse(400, "Invoice Number '{$invNo}' already exists for this company.");
    }

    $today = date('Y-m-d');
    $invoiceDate = !empty($input['invoice_date']) ? date('Y-m-d', strtotime($input['invoice_date'])) : $today;
    $dueDate = !empty($input['due_date']) ? date('Y-m-d', strtotime($input['due_date'])) : (!empty($quot->due_date) ? $quot->due_date : $invoiceDate);
    $safePartyName = mysqli_real_escape_string($ai_conn, $quot->party_name ?? '');
    $company_has_gst = (int)($company['gst_applicable'] ?? 0) === 1;
    $safeGstNo = $company_has_gst ? mysqli_real_escape_string($ai_conn, $quot->gst_no ?? '') : '';
    $safeRca = mysqli_real_escape_string($ai_conn, $quot->rca ?? 'No');
    $safeAddress = mysqli_real_escape_string($ai_conn, $quot->address ?? '');
    $dueSql = $dueDate ? "'{$dueDate}'" : "NULL";

    $invTotalAmount = floatval($quot->total_amount ?? 0);
    $invCgst = $company_has_gst ? floatval($quot->cgst_amount ?? 0) : 0.00;
    $invSgst = $company_has_gst ? floatval($quot->sgst_amount ?? 0) : 0.00;
    $invIgst = $company_has_gst ? floatval($quot->igst_amount ?? 0) : 0.00;
    $invTax = $company_has_gst ? floatval($quot->tax_amount ?? 0) : 0.00;
    $invGrandTotal = $company_has_gst ? floatval($quot->grand_total ?? 0) : $invTotalAmount;

    $insInvSql = "INSERT INTO tbl_invoice (
        quotation_id, company_id, party_id, party_name, gst_no, invoice_no, rca, address,
        state_id, city_id, invoice_date, due_date,
        total_amount, cgst_amount, sgst_amount, igst_amount, tax_amount, grand_total,
        payment_status, status, created_at
    ) VALUES (
        {$quotationId},
        {$company_id},
        " . intval($quot->party_id ?? 0) . ",
        '{$safePartyName}',
        '{$safeGstNo}',
        '{$safeInvNo}',
        '{$safeRca}',
        '{$safeAddress}',
        " . intval($quot->state_id ?? 0) . ",
        " . intval($quot->city_id ?? 0) . ",
        '{$invoiceDate}',
        {$dueSql},
        {$invTotalAmount},
        {$invCgst},
        {$invSgst},
        {$invIgst},
        {$invTax},
        {$invGrandTotal},
        '" . mysqli_real_escape_string($ai_conn, $quot->payment_status ?? 'Pending') . "',
        'active',
        NOW()
    )";

    $res = mysqli_query($ai_conn, $insInvSql);
    if (!$res) {
        apiResponse(500, 'Failed to create invoice from quotation: ' . mysqli_error($ai_conn));
    }

    $newInvoiceId = mysqli_insert_id($ai_conn);

    // Copy items to tbl_invoice_items and deduct product stock
    $copiedItems = [];
    foreach ($qItems as $item) {
        $pId = (int)($item->product_id ?? 0);
        $desc = mysqli_real_escape_string($ai_conn, $item->description ?? '');
        $hsn = mysqli_real_escape_string($ai_conn, $item->hsn_code ?? '');
        $rate = floatval($item->rate ?? 0);
        $gstP = $company_has_gst ? floatval($item->gst_percent ?? 0) : 0.00;
        $discType = in_array(strtolower(trim($item->discount_type ?? '')), ['fixed', 'percentage'], true) ? strtolower(trim($item->discount_type)) : 'percentage';
        $discVal = floatval($item->discount_value ?? 0);
        $discAmt = floatval($item->discount_amount ?? 0);
        $qty = floatval($item->qty ?? 1);
        $netAmt = floatval($item->net_amount ?? 0);
        $taxAmt = $company_has_gst ? floatval($item->tax_amount ?? 0) : 0.00;
        $totAmt = $company_has_gst ? floatval($item->total_amount ?? 0) : $netAmt;

        // Fallback product_id lookup if missing
        if ($pId <= 0 && !empty($item->description)) {
            $pFind = $ai_db->aiGetQueryObj("SELECT id FROM tbl_product WHERE LOWER(TRIM(product_name)) = LOWER(TRIM('{$desc}')) AND company_id = {$company_id} LIMIT 1");
            if (!empty($pFind)) {
                $pId = (int)$pFind[0]->id;
            }
        }

        $insItemSql = "INSERT INTO tbl_invoice_items (
            invoice_id, product_id, description, hsn_code, rate, gst_percent,
            discount_type, discount_value, discount_amount, qty, net_amount, tax_amount, total_amount
        ) VALUES (
            {$newInvoiceId}, {$pId}, '{$desc}', '{$hsn}', {$rate}, {$gstP},
            '{$discType}', {$discVal}, {$discAmt}, {$qty}, {$netAmt}, {$taxAmt}, {$totAmt}
        )";
        mysqli_query($ai_conn, $insItemSql);

        // Deduct stock for converted invoice item
        if ($pId > 0 && function_exists('deductProductStock')) {
            deductProductStock($pId, $qty);
        }

        $copiedItems[] = [
            'product_id'   => $pId,
            'description'  => $item->description ?? '',
            'qty'          => $qty,
            'rate'         => $rate,
            'total_amount' => $totAmt
        ];
    }

    // Mark quotation as converted
    mysqli_query($ai_conn, "UPDATE tbl_quotation SET converted_invoice_id = {$newInvoiceId} WHERE id = {$quotationId}");

    // Recalculate party outstanding
    $partyId = (int)($quot->party_id ?? 0);
    if ($partyId > 0 && function_exists('recalculatePartyOutstanding')) {
        recalculatePartyOutstanding($partyId);
    }

    $printUrl = rtrim(ADMIN_URL, '/') . '/invoice-print.php?id=' . $newInvoiceId;

    apiResponse(200, "Quotation '{$quot->quotation_no}' successfully converted to Invoice '{$invNo}'.", [
        'quotation_id'          => $quotationId,
        'quotation_no'          => $quot->quotation_no,
        'invoice_id'            => $newInvoiceId,
        'invoice_no'            => $invNo,
        'company_id'            => $company_id,
        'party_id'              => $partyId,
        'party_name'            => $quot->party_name,
        'invoice_date'          => $invoiceDate,
        'invoice_date_formatted'=> date('d-m-Y', strtotime($invoiceDate)),
        'due_date'              => $dueDate,
        'due_date_formatted'    => date('d-m-Y', strtotime($dueDate)),
        'grand_total'           => round($invGrandTotal, 2),
        'grand_total_formatted' => '₹' . number_format($invGrandTotal, 2),
        'items_count'           => count($copiedItems),
        'print_url'             => $printUrl,
        'download_url'          => $printUrl . '&download=1'
    ]);
}

if ($action === 'product-list') {
    if ($method !== 'GET' && $method !== 'POST') {
        apiResponse(405, 'Method not allowed. Use GET or POST.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $cId = (int)$company['id'];
    $filterStatus = trim($input['status'] ?? ($_GET['status'] ?? ''));
    if ($filterStatus === 'active' || $filterStatus === 'deactive') {
        $statusCond = " AND status = '{$filterStatus}'";
    } elseif ($filterStatus === 'all') {
        $statusCond = '';
    } else {
        $statusCond = " AND status = 'active'";
    }

    $search = trim($input['search'] ?? ($_GET['search'] ?? ''));
    $searchCond = '';
    if ($search !== '') {
        $safeSearch = mysqli_real_escape_string($ai_conn, $search);
        $searchCond = " AND (product_name LIKE '%{$safeSearch}%' OR sku LIKE '%{$safeSearch}%' OR hsn_code LIKE '%{$safeSearch}%')";
    }

    $orderBy = "ORDER BY p.id DESC";

    // Per-product sold quantity and sold revenue aggregated from tbl_invoice_items (active invoices)
    $productsQry = "
        SELECT p.id, p.company_id, p.product_name, p.sku, p.hsn_code, p.gst_rate, 
               p.purchase_price, p.sales_price, p.stock, p.min_stock_alert, p.track_stock, 
               p.status, p.created_at,
               COALESCE(inv_sales.sold_qty, 0) AS sold_qty,
               COALESCE(inv_sales.sold_amount, 0) AS sold_amount
        FROM tbl_product p
        LEFT JOIN (
            SELECT ii.product_id, 
                   SUM(ii.qty) AS sold_qty, 
                   SUM(ii.total_amount) AS sold_amount
            FROM tbl_invoice_items ii
            INNER JOIN tbl_invoice i ON ii.invoice_id = i.id
            WHERE i.company_id = {$cId} AND i.status = 'active'
            GROUP BY ii.product_id
        ) inv_sales ON inv_sales.product_id = p.id
        WHERE p.company_id = {$cId}{$statusCond}{$searchCond}
        {$orderBy}
    ";
    $products = $ai_db->aiGetQueryObj($productsQry);

    // Summary calculations across all company products
    $summaryQry = "
        SELECT 
            COUNT(id) AS total_products,
            COUNT(CASE WHEN status = 'active' THEN 1 END) AS total_active_products,
            COUNT(CASE WHEN status = 'deactive' THEN 1 END) AS total_deactive_products,
            COUNT(CASE WHEN (track_stock = 1 OR track_stock IS NULL) AND stock <= min_stock_alert THEN 1 END) AS restock_needed_count,
            COUNT(CASE WHEN (track_stock = 1 OR track_stock IS NULL) AND stock <= 0 THEN 1 END) AS out_of_stock_count,
            COUNT(CASE WHEN (track_stock = 1 OR track_stock IS NULL) AND stock > 0 AND stock <= min_stock_alert THEN 1 END) AS low_stock_count
        FROM tbl_product
        WHERE company_id = {$cId}
    ";
    $summaryRes = $ai_db->aiGetQueryObj($summaryQry);
    $sumRow = !empty($summaryRes) ? $summaryRes[0] : null;

    $totalProducts        = (int)($sumRow->total_products ?? 0);
    $totalActiveProducts  = (int)($sumRow->total_active_products ?? 0);
    $totalDeactiveProducts= (int)($sumRow->total_deactive_products ?? 0);
    $restockNeededCount   = (int)($sumRow->restock_needed_count ?? 0);
    $outOfStockCount      = (int)($sumRow->out_of_stock_count ?? 0);
    $lowStockCount        = (int)($sumRow->low_stock_count ?? 0);

    // Sales metrics for company products from active invoices
    $salesSummaryQry = "
        SELECT 
            COALESCE(SUM(ii.qty), 0) AS total_sold_qty,
            COALESCE(SUM(ii.total_amount), 0) AS total_sold_amount
        FROM tbl_invoice_items ii
        INNER JOIN tbl_invoice i ON ii.invoice_id = i.id
        WHERE i.company_id = {$cId} AND i.status = 'active'
    ";
    $salesSummaryRes = $ai_db->aiGetQueryObj($salesSummaryQry);
    $salesRow = !empty($salesSummaryRes) ? $salesSummaryRes[0] : null;

    $totalSoldQty    = (float)($salesRow->total_sold_qty ?? 0);
    $totalSoldAmount = (float)($salesRow->total_sold_amount ?? 0);

    $list = [];
    if (!empty($products)) {
        foreach ($products as $product) {
            $curStock  = (float)($product->stock ?? 0);
            $minAlert  = (float)($product->min_stock_alert ?? 5.0);
            $isTracked = ((int)($product->track_stock ?? 1) === 1);
            $soldQty   = (float)($product->sold_qty ?? 0);
            $soldAmt   = (float)($product->sold_amount ?? 0);

            $needsRestock = false;
            $stockStatus = 'not_tracked';
            if ($isTracked) {
                if ($curStock <= 0) {
                    $stockStatus  = 'out_of_stock';
                    $needsRestock = true;
                } elseif ($curStock <= $minAlert) {
                    $stockStatus  = 'low_stock';
                    $needsRestock = true;
                } else {
                    $stockStatus  = 'in_stock';
                    $needsRestock = false;
                }
            }

            $list[] = [
                'id'                  => (int)$product->id,
                'company_id'          => (int)$product->company_id,
                'product_name'        => $product->product_name ?? '',
                'sku'                 => $product->sku ?? '',
                'hsn_code'            => $product->hsn_code ?? '',
                'gst_rate'            => ((int)($company['gst_applicable'] ?? 0) === 1) ? (float)($product->gst_rate ?? 18) : false,
                'purchase_price'      => (float)$product->purchase_price,
                'purchase_formatted'  => '₹' . number_format((float)$product->purchase_price, 2),
                'sales_price'         => (float)$product->sales_price,
                'sales_formatted'     => '₹' . number_format((float)$product->sales_price, 2),
                'stock'               => $curStock,
                'min_stock_alert'     => $minAlert,
                'track_stock'         => $isTracked ? 1 : 0,
                'stock_status'        => $stockStatus,
                'needs_restock'       => $needsRestock,
                'sold_count'          => $soldQty,
                'sold_amount'         => $soldAmt,
                'sold_amount_formatted' => '₹' . number_format($soldAmt, 2),
                'status'              => $product->status ?? 'active',
                'created_at'          => $product->created_at ?? ''
            ];
        }
    }

    apiResponse(200, 'Product list retrieved successfully.', [
        'company_id'   => $cId,
        'company_name' => $company['company_name'] ?? '',
        'total'        => count($list),
        'summary'      => [
            'total_products'               => $totalProducts,
            'total_active_products'        => $totalActiveProducts,
            'total_deactive_products'      => $totalDeactiveProducts,
            'total_sold_count'             => $totalSoldQty,
            'total_sold_amount'            => $totalSoldAmount,
            'total_sold_amount_formatted'  => '₹' . number_format($totalSoldAmount, 2),
            'restock_needed_count'         => $restockNeededCount,
            'out_of_stock_count'           => $outOfStockCount,
            'low_stock_count'              => $lowStockCount
        ],
        'products'     => $list
    ]);
}

if ($action === 'product-add') {
    if ($method !== 'POST') {
        apiResponse(405, 'Only POST method is allowed.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];
    $gst_applicable = (int)($company['gst_applicable'] ?? 0);

    $product_name    = trim($input['product_name'] ?? ($input['name'] ?? ''));
    $sku             = trim($input['sku'] ?? '');
    $hsn_code        = trim($input['hsn_code'] ?? ($input['hsn'] ?? ''));
    $purchase_price  = (float)($input['purchase_price'] ?? 0);
    $sales_price     = (float)($input['sales_price'] ?? ($input['price'] ?? 0));
    $stock           = (float)($input['stock'] ?? 0);
    $min_stock_alert = isset($input['min_stock_alert']) ? (float)$input['min_stock_alert'] : 5.0;
    $track_stock     = 1;
    $status          = trim($input['status'] ?? 'active');

    $gst_rate = 0;
    if ($gst_applicable === 1) {
        $allowedRates = [18, 12, 5, 28, 0];
        $passedRate = isset($input['gst_rate']) ? (int)$input['gst_rate'] : 18;
        $gst_rate = in_array($passedRate, $allowedRates, true) ? $passedRate : 18;
    }

    if (!in_array(strtolower($status), ['active', 'deactive'])) {
        $status = 'active';
    }
    if ($product_name === '') {
        apiResponse(400, 'Product Name is required.');
    }

    $safeProdName = mysqli_real_escape_string($ai_conn, $product_name);
    $dup_conds = ["LOWER(product_name) = '" . strtolower($safeProdName) . "'"];
    if (!empty($sku)) {
        $safeSku = mysqli_real_escape_string($ai_conn, $sku);
        $dup_conds[] = "LOWER(sku) = '" . strtolower($safeSku) . "'";
    }

    $checkDup = $ai_db->aiGetQueryObj("SELECT id, product_name, sku FROM tbl_product WHERE company_id = {$company_id} AND (" . implode(" OR ", $dup_conds) . ") LIMIT 1");
    if (!empty($checkDup)) {
        if (strtolower($checkDup[0]->product_name) === strtolower($product_name)) {
            apiResponse(409, "Product Name '{$product_name}' already exists for your company.");
        } elseif (!empty($sku) && strtolower($checkDup[0]->sku) === strtolower($sku)) {
            apiResponse(409, "SKU '{$sku}' already exists for your company.");
        } else {
            apiResponse(409, "Product already exists with given details.");
        }
    }

    $safeSku        = mysqli_real_escape_string($ai_conn, $sku);
    $safeHsn        = mysqli_real_escape_string($ai_conn, $hsn_code);
    $safeStatus     = mysqli_real_escape_string($ai_conn, $status);

    $insertQry = "INSERT INTO tbl_product SET 
                  company_id      = {$company_id},
                  product_name    = '{$safeProdName}',
                  sku             = '{$safeSku}',
                  hsn_code        = '{$safeHsn}',
                  gst_rate        = {$gst_rate},
                  purchase_price  = {$purchase_price},
                  sales_price     = {$sales_price},
                  stock           = {$stock},
                  min_stock_alert = {$min_stock_alert},
                  track_stock     = {$track_stock},
                  status          = '{$safeStatus}',
                  created_at      = NOW()";

    $res = mysqli_query($ai_conn, $insertQry);
    if (!$res) {
        apiResponse(500, 'Failed to add product: ' . mysqli_error($ai_conn));
    }

    $newProductId = mysqli_insert_id($ai_conn);

    apiResponse(200, 'Product added successfully.', [
        'id'              => (int)$newProductId,
        'company_id'      => $company_id,
        'company_name'    => $company['company_name'] ?? '',
        'product_name'    => $product_name,
        'sku'             => $sku,
        'hsn_code'        => $hsn_code,
        'gst_rate'        => (float)$gst_rate,
        'purchase_price'  => (float)$purchase_price,
        'sales_price'     => (float)$sales_price,
        'stock'           => (float)$stock,
        'min_stock_alert' => (float)$min_stock_alert,
        'track_stock'     => (int)$track_stock,
        'status'          => $status
    ]);
}

if ($action === 'product-edit') {
    if ($method !== 'GET' && $method !== 'POST') {
        apiResponse(405, 'Method not allowed. Use GET or POST.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];
    $productId = (int)($input['id'] ?? ($input['product_id'] ?? ($_GET['id'] ?? ($_GET['product_id'] ?? 0))));

    if ($productId <= 0) {
        apiResponse(400, 'Product id is required.');
    }

    $productRes = $ai_db->aiGetQueryObj("SELECT * FROM tbl_product WHERE id = {$productId} AND company_id = {$company_id} LIMIT 1");
    if (empty($productRes)) {
        apiResponse(404, 'Product not found.');
    }

    $prod = $productRes[0];

    apiResponse(200, 'Product details retrieved successfully.', [
        'id'              => (int)$prod->id,
        'company_id'      => (int)$prod->company_id,
        'company_name'    => $company['company_name'] ?? '',
        'product_name'    => $prod->product_name ?? '',
        'sku'             => $prod->sku ?? '',
        'hsn_code'        => $prod->hsn_code ?? '',
        'gst_rate'        => (float)($prod->gst_rate ?? 0),
        'purchase_price'  => (float)($prod->purchase_price ?? 0),
        'sales_price'     => (float)($prod->sales_price ?? 0),
        'stock'           => (float)($prod->stock ?? 0),
        'min_stock_alert' => (float)($prod->min_stock_alert ?? 5),
        'track_stock'     => (int)($prod->track_stock ?? 1),
        'status'          => $prod->status ?? 'active',
        'created_at'      => $prod->created_at ?? ''
    ]);
}

if ($action === 'product-update') {
    if ($method !== 'POST' && $method !== 'PUT') {
        apiResponse(405, 'Method not allowed. Use POST or PUT.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];
    $gst_applicable = (int)($company['gst_applicable'] ?? 0);
    $productId = (int)($input['id'] ?? ($input['product_id'] ?? ($_GET['id'] ?? ($_GET['product_id'] ?? 0))));

    if ($productId <= 0) {
        apiResponse(400, 'Product id is required.');
    }

    $existing = $ai_db->aiGetQueryObj("SELECT * FROM tbl_product WHERE id = {$productId} AND company_id = {$company_id} LIMIT 1");
    if (empty($existing)) {
        apiResponse(404, 'Product not found.');
    }

    $currentProd = $existing[0];

    $product_name    = trim($input['product_name'] ?? ($input['name'] ?? ($currentProd->product_name ?? '')));
    $sku             = trim($input['sku'] ?? ($currentProd->sku ?? ''));
    $hsn_code        = trim($input['hsn_code'] ?? ($input['hsn'] ?? ($currentProd->hsn_code ?? '')));
    $purchase_price  = isset($input['purchase_price']) ? (float)$input['purchase_price'] : (float)($currentProd->purchase_price ?? 0);
    $sales_price     = isset($input['sales_price']) ? (float)$input['sales_price'] : (isset($input['price']) ? (float)$input['price'] : (float)($currentProd->sales_price ?? 0));
    $stock           = isset($input['stock']) ? (float)$input['stock'] : (float)($currentProd->stock ?? 0);
    $min_stock_alert = isset($input['min_stock_alert']) ? (float)$input['min_stock_alert'] : (float)($currentProd->min_stock_alert ?? 5);
    $track_stock     = 1;
    $status          = trim($input['status'] ?? ($currentProd->status ?? 'active'));

    $gst_rate = 0;
    if ($gst_applicable === 1) {
        $allowedRates = [18, 12, 5, 28, 0];
        $passedRate = isset($input['gst_rate']) ? (int)$input['gst_rate'] : (int)($currentProd->gst_rate ?? 18);
        $gst_rate = in_array($passedRate, $allowedRates, true) ? $passedRate : 18;
    }

    if (!in_array(strtolower($status), ['active', 'deactive'])) {
        $status = $currentProd->status ?? 'active';
    }

    if ($product_name === '') {
        apiResponse(400, 'Product Name is required.');
    }

    $safeProdName = mysqli_real_escape_string($ai_conn, $product_name);
    $dup_conds = ["LOWER(product_name) = '" . strtolower($safeProdName) . "'"];
    if (!empty($sku)) {
        $safeSku = mysqli_real_escape_string($ai_conn, $sku);
        $dup_conds[] = "LOWER(sku) = '" . strtolower($safeSku) . "'";
    }

    $checkDup = $ai_db->aiGetQueryObj("SELECT id, product_name, sku FROM tbl_product WHERE company_id = {$company_id} AND (" . implode(" OR ", $dup_conds) . ") AND id != {$productId} LIMIT 1");
    if (!empty($checkDup)) {
        if (strtolower($checkDup[0]->product_name) === strtolower($product_name)) {
            apiResponse(409, "Product Name '{$product_name}' already exists for your company.");
        } elseif (!empty($sku) && strtolower($checkDup[0]->sku) === strtolower($sku)) {
            apiResponse(409, "SKU '{$sku}' already exists for your company.");
        } else {
            apiResponse(409, "Product already exists with given details.");
        }
    }

    $safeSku        = mysqli_real_escape_string($ai_conn, $sku);
    $safeHsn        = mysqli_real_escape_string($ai_conn, $hsn_code);
    $safeStatus     = mysqli_real_escape_string($ai_conn, $status);

    $updateQry = "UPDATE tbl_product SET 
                  product_name    = '{$safeProdName}',
                  sku             = '{$safeSku}',
                  hsn_code        = '{$safeHsn}',
                  gst_rate        = {$gst_rate},
                  purchase_price  = {$purchase_price},
                  sales_price     = {$sales_price},
                  stock           = {$stock},
                  min_stock_alert = {$min_stock_alert},
                  track_stock     = {$track_stock},
                  status          = '{$safeStatus}'
                  WHERE id = {$productId} AND company_id = {$company_id}";

    $res = mysqli_query($ai_conn, $updateQry);
    if (!$res) {
        apiResponse(500, 'Failed to update product: ' . mysqli_error($ai_conn));
    }

    apiResponse(200, 'Product updated successfully.', [
        'id'              => $productId,
        'company_id'      => $company_id,
        'product_name'    => $product_name,
        'sku'             => $sku,
        'hsn_code'        => $hsn_code,
        'gst_rate'        => (float)$gst_rate,
        'purchase_price'  => (float)$purchase_price,
        'sales_price'     => (float)$sales_price,
        'stock'           => (float)$stock,
        'min_stock_alert' => (float)$min_stock_alert,
        'track_stock'     => (int)$track_stock,
        'status'          => $status
    ]);
}

if ($action === 'product-delete') {
    if ($method !== 'DELETE' && $method !== 'POST') {
        apiResponse(405, 'Method not allowed. Use DELETE or POST.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];
    $productId = (int)($input['id'] ?? ($input['product_id'] ?? ($_GET['id'] ?? ($_GET['product_id'] ?? 0))));

    if ($productId <= 0) {
        apiResponse(400, 'Product id is required.');
    }

    $existing = $ai_db->aiGetQueryObj("SELECT id, product_name FROM tbl_product WHERE id = {$productId} AND company_id = {$company_id} LIMIT 1");
    if (empty($existing)) {
        apiResponse(404, 'Product not found.');
    }

    $prodName = $existing[0]->product_name;

    $delQry = "DELETE FROM tbl_product WHERE id = {$productId} AND company_id = {$company_id}";
    $res = mysqli_query($ai_conn, $delQry);

    if (!$res) {
        apiResponse(500, 'Failed to delete product: ' . mysqli_error($ai_conn));
    }

    apiResponse(200, 'Product deleted successfully.', [
        'id'           => $productId,
        'product_name' => $prodName,
        'deleted'      => true
    ]);
}

if ($action === 'invoice-generate-no') {
    if ($method !== 'GET' && $method !== 'POST') {
        apiResponse(405, 'Method not allowed. Use GET or POST.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];

    $nextInvoiceNo = generateInvoiceNo($ai_db, $company_id);

    apiResponse(200, 'Next invoice number generated successfully.', [
        'company_id' => $company_id,
        'invoice_no' => $nextInvoiceNo
    ]);
}

if ($action === 'invoice-list') {
    if ($method !== 'GET' && $method !== 'POST') {
        apiResponse(405, 'Method not allowed. Use GET or POST.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $cId = (int)$company['id'];

    $todayDate = date('Y-m-d');
    $filterPayment = trim($input['payment_status'] ?? ($_GET['payment_status'] ?? ''));
    $payCond = "";
    if (in_array($filterPayment, ['Pending', 'Partially Paid', 'Paid'], true)) {
        $payCond = " AND q.payment_status = '{$filterPayment}'";
    } elseif ($filterPayment === 'Overdue') {
        $payCond = " AND q.payment_status IN ('Pending','Partially Paid') AND q.due_date IS NOT NULL AND q.due_date < '{$todayDate}'";
    }

    $filterStatus = trim($input['status'] ?? ($_GET['status'] ?? ''));
    $statusCond = "";
    if ($filterStatus === 'active' || $filterStatus === 'deactive') {
        $statusCond = " AND q.status = '{$filterStatus}'";
    }

    $partyId = (int)($input['party_id'] ?? ($_GET['party_id'] ?? 0));
    $partyCond = ($partyId > 0) ? " AND q.party_id = {$partyId}" : "";

    $search = trim($input['search'] ?? ($_GET['search'] ?? ''));
    $searchCond = "";
    if ($search !== '') {
        $safeSearch = mysqli_real_escape_string($ai_conn, $search);
        $searchCond = " AND (q.invoice_no LIKE '%{$safeSearch}%' OR q.party_name LIKE '%{$safeSearch}%' OR q.gst_no LIKE '%{$safeSearch}%')";
    }

    $orderBy = "ORDER BY q.id DESC";

    $invoiceQry = "SELECT q.*, c.company_name, p.mobile_no AS party_mobile, p.email AS party_email,
                          s.state_name, ct.city_name
                   FROM tbl_invoice q
                   LEFT JOIN tbl_company c ON q.company_id = c.id
                   LEFT JOIN tbl_party p ON q.party_id = p.id
                   LEFT JOIN tbl_state s ON q.state_id = s.id
                   LEFT JOIN tbl_city ct ON q.city_id = ct.id
                   WHERE q.company_id = {$cId} {$payCond} {$statusCond} {$partyCond} {$searchCond}
                   {$orderBy}";
    $invoices = $ai_db->aiGetQueryObj($invoiceQry);

    $summaryQry = "SELECT 
                        COUNT(id) AS total_count,
                        COUNT(CASE WHEN payment_status = 'Paid' THEN 1 END) AS paid_count,
                        COUNT(CASE WHEN payment_status != 'Paid' THEN 1 END) AS unpaid_count
                   FROM tbl_invoice
                   WHERE company_id = {$cId}";
    $sumRes = $ai_db->aiGetQueryObj($summaryQry);
    $m = !empty($sumRes) ? $sumRes[0] : null;

    $statTotalCount = (int)($m->total_count ?? 0);
    $statPaidCount = (int)($m->paid_count ?? 0);
    $statUnpaidCount = (int)($m->unpaid_count ?? 0);

    $includeItems = !empty($input['include_items']) || !empty($_GET['include_items']);

    $list = [];
    if (!empty($invoices)) {
        $itemsByInvoice = [];
        if ($includeItems) {
            $invIds = array_map(function($item) { return (int)$item->id; }, $invoices);
            if (!empty($invIds)) {
                $invIdsStr = implode(',', $invIds);
                $itemsRows = $ai_db->aiGetQueryObj("
                    SELECT ii.*, p.sku, p.stock AS current_stock
                    FROM tbl_invoice_items ii
                    LEFT JOIN tbl_product p ON ii.product_id = p.id
                    WHERE ii.invoice_id IN ({$invIdsStr})
                    ORDER BY ii.id ASC
                ");
                if (!empty($itemsRows)) {
                    foreach ($itemsRows as $iRow) {
                        $iid = (int)$iRow->invoice_id;
                        if (!isset($itemsByInvoice[$iid])) {
                            $itemsByInvoice[$iid] = [];
                        }
                        $itemsByInvoice[$iid][] = [
                            'id'              => (int)$iRow->id,
                            'product_id'      => (int)$iRow->product_id,
                            'description'     => $iRow->description ?? '',
                            'sku'             => $iRow->sku ?? '',
                            'hsn_code'        => $iRow->hsn_code ?? '',
                            'rate'            => (float)$iRow->rate,
                            'rate_formatted'  => '₹' . number_format((float)$iRow->rate, 2),
                            'gst_percent'     => (float)$iRow->gst_percent,
                            'discount_type'   => $iRow->discount_type ?? 'percentage',
                            'discount_value'  => (float)$iRow->discount_value,
                            'discount_amount' => (float)$iRow->discount_amount,
                            'qty'             => (float)$iRow->qty,
                            'net_amount'      => (float)$iRow->net_amount,
                            'tax_amount'      => (float)$iRow->tax_amount,
                            'total_amount'    => (float)$iRow->total_amount,
                            'total_formatted' => '₹' . number_format((float)$iRow->total_amount, 2)
                        ];
                    }
                }
            }
        }

        foreach ($invoices as $row) {
            $iid = (int)$row->id;
            $grandTotal = (float)$row->grand_total;
            $paidAmt = (float)($row->paid_amount ?? 0);
            $pendingAmt = max(0, $grandTotal - $paidAmt);
            $totalTaxable = (float)$row->total_amount;
            $taxAmount = (float)$row->tax_amount;
            $cgst = (float)$row->cgst_amount;
            $sgst = (float)$row->sgst_amount;
            $igst = (float)$row->igst_amount;
            $payStatus = $row->payment_status ?? 'Pending';
            $isPaid = ($payStatus === 'Paid');

            $printUrl = rtrim(ADMIN_URL, '/') . '/invoice-print.php?id=' . $iid;

            $itemData = [
                'id'                     => $iid,
                'quotation_id'           => (int)($row->quotation_id ?? 0),
                'invoice_no'             => $row->invoice_no ?? '',
                'company_id'             => (int)$row->company_id,
                'company_name'           => $row->company_name ?? ($company['company_name'] ?? ''),
                'party_id'               => (int)$row->party_id,
                'party_name'             => $row->party_name ?? '',
                'party_mobile'           => $row->party_mobile ?? '',
                'party_email'            => $row->party_email ?? '',
                'gst_no'                 => $row->gst_no ?? '',
                'rca'                    => $row->rca ?? 'No',
                'address'                => $row->address ?? '',
                'state_id'               => (int)($row->state_id ?? 0),
                'state_name'             => $row->state_name ?? '',
                'city_id'                => (int)($row->city_id ?? 0),
                'city_name'              => $row->city_name ?? '',
                'invoice_date'           => $row->invoice_date ?? '',
                'invoice_date_formatted' => !empty($row->invoice_date) ? date('d-m-Y', strtotime($row->invoice_date)) : '',
                'due_date'               => $row->due_date ?? null,
                'due_date_formatted'     => !empty($row->due_date) ? date('d-m-Y', strtotime($row->due_date)) : '',
                'taxable_amount'         => $totalTaxable,
                'taxable_formatted'      => '₹' . number_format($totalTaxable, 2),
                'cgst_amount'            => $cgst,
                'sgst_amount'            => $sgst,
                'igst_amount'            => $igst,
                'tax_amount'             => $taxAmount,
                'tax_amount_formatted'   => '₹' . number_format($taxAmount, 2),
                'grand_total'            => $grandTotal,
                'grand_total_formatted'  => '₹' . number_format($grandTotal, 2),
                'paid_amount'            => $paidAmt,
                'paid_amount_formatted'  => '₹' . number_format($paidAmt, 2),
                'pending_amount'         => $pendingAmt,
                'pending_amount_formatted' => '₹' . number_format($pendingAmt, 2),
                'payment_status'         => $payStatus,
                'status'                 => $row->status ?? 'active',
                'is_overdue'             => ($payStatus !== 'Paid' && !empty($row->due_date) && $row->due_date < $todayDate),
                'is_locked'              => $isPaid,
                'print_url'              => $printUrl,
                'download_url'           => $printUrl . '&download=1',
                'created_at'             => $row->created_at ?? ''
            ];

            if ($includeItems) {
                $itemData['items'] = $itemsByInvoice[$iid] ?? [];
            }

            $list[] = $itemData;
        }
    }

    apiResponse(200, 'Invoice list retrieved successfully.', [
        'company_id'      => $cId,
        'company_name'    => $company['company_name'] ?? '',
        'total'           => count($list),
        'total_invoices'  => $statTotalCount,
        'paid_invoices'   => $statPaidCount,
        'unpaid_invoices' => $statUnpaidCount,
        'invoices'        => $list
    ]);
}

if ($action === 'invoice-add') {
    if ($method !== 'POST') {
        apiResponse(405, 'Only POST method is allowed.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];
    $company_has_gst = (int)($company['gst_applicable'] ?? 0) === 1;

    $party_id = (int)($input['party_id'] ?? 0);
    $party_name = trim($input['party_name'] ?? '');
    $gst_no = $company_has_gst ? trim($input['gst_no'] ?? '') : '';
    $address = trim($input['address'] ?? '');
    $state_id = (int)($input['state_id'] ?? 0);
    $city_id = (int)($input['city_id'] ?? 0);
    $rca = trim($input['rca'] ?? 'No');

    if ($party_id > 0) {
        $pRow = $ai_db->aiGetQueryObj("SELECT party_name, gst_no, address, state_id, city_id FROM tbl_party WHERE id={$party_id} AND company_id={$company_id} LIMIT 1");
        if (!empty($pRow)) {
            if (empty($party_name)) $party_name = $pRow[0]->party_name ?? '';
            if ($company_has_gst && empty($gst_no)) $gst_no = $pRow[0]->gst_no ?? '';
            if (empty($address)) $address = $pRow[0]->address ?? '';
            if ($state_id <= 0) $state_id = (int)($pRow[0]->state_id ?? 0);
            if ($city_id <= 0) $city_id = (int)($pRow[0]->city_id ?? 0);
        }
    }

    if (empty($party_id) && empty($party_name)) {
        apiResponse(400, 'Party Name is required.');
    }

    if ($company_has_gst && !empty($gst_no) && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i', $gst_no)) {
        apiResponse(400, 'Please enter a valid 15-digit GST Number (e.g. 24AAAAA0000A1Z5).');
    }

    $invoice_no = trim($input['invoice_no'] ?? '');
    if (empty($invoice_no)) {
        $invoice_no = generateInvoiceNo($ai_db, $company_id);
    } else {
        $safeInvNo = mysqli_real_escape_string($ai_conn, $invoice_no);
        $checkDup = $ai_db->aiGetQueryObj("SELECT id FROM tbl_invoice WHERE invoice_no='{$safeInvNo}' AND company_id={$company_id} LIMIT 1");
        if (!empty($checkDup)) {
            apiResponse(400, "Invoice Number '{$invoice_no}' already exists for this company.");
        }
    }

    $invoice_date = !empty($input['invoice_date']) ? date('Y-m-d', strtotime($input['invoice_date'])) : date('Y-m-d');
    $due_date = !empty($input['due_date']) ? date('Y-m-d', strtotime($input['due_date'])) : $invoice_date;
    if ($due_date < $invoice_date) {
        apiResponse(400, 'Due date cannot be earlier than Invoice date.');
    }

    $reqPaymentStatus = isset($input['payment_status']) ? trim((string)$input['payment_status']) : 'Pending';
    $payment_status = ($reqPaymentStatus === 'Paid') ? 'Paid' : 'Pending';
    $rawStatus = isset($input['status']) && $input['status'] !== null ? strtolower(trim((string)$input['status'])) : 'active';
    $status = in_array($rawStatus, ['active', 'deactive'], true) ? $rawStatus : 'active';

    $isGujarat = false;
    if ($state_id > 0) {
        $stObj = $ai_db->aiGetQueryObj("SELECT state_name FROM tbl_state WHERE id={$state_id} LIMIT 1");
        if (!empty($stObj)) {
            $isGujarat = (stripos($stObj[0]->state_name, 'Gujarat') !== false);
        }
    }

    $rawItems = $input['items'] ?? [];
    if (!is_array($rawItems) || empty($rawItems)) {
        apiResponse(400, 'Invoice must contain at least one item.');
    }

    // First validate products, stock, and calculate totals
    $processedItems = [];
    $totalTaxable = 0.00;
    $totalTax = 0.00;
    $outOfStockErrors = [];

    foreach ($rawItems as $idx => $it) {
        $prodId = (int)($it['product_id'] ?? ($it['id'] ?? 0));
        $desc = trim($it['description'] ?? ($it['product_name'] ?? ''));
        $hsn = trim($it['hsn_code'] ?? ($it['hsn'] ?? ''));

        if ($prodId <= 0 && !empty($desc)) {
            $escDesc = mysqli_real_escape_string($ai_conn, $desc);
            $pFind = $ai_db->aiGetQueryObj("SELECT id, product_name, hsn_code, sales_price, gst_rate, status, stock, min_stock_alert, track_stock FROM tbl_product WHERE LOWER(TRIM(product_name)) = LOWER(TRIM('{$escDesc}')) AND company_id={$company_id} LIMIT 1");
            if (!empty($pFind)) {
                $prodId = (int)$pFind[0]->id;
            }
        }

        if ($prodId <= 0) {
            $nameHint = !empty($desc) ? " for '{$desc}'" : "";
            apiResponse(400, "Item{$nameHint} (index {$idx}) must have a valid product_id.");
        }

        $pFind = $ai_db->aiGetQueryObj("SELECT id, product_name, hsn_code, sales_price, gst_rate, status, stock, min_stock_alert, track_stock FROM tbl_product WHERE id={$prodId} AND company_id={$company_id} LIMIT 1");
        if (empty($pFind)) {
            $nameText = !empty($desc) ? " '{$desc}'" : "";
            apiResponse(404, "Product{$nameText} with ID {$prodId} (index {$idx}) does not exist for your company.");
        }
        if (strtolower($pFind[0]->status ?? '') !== 'active') {
            apiResponse(400, "Product '{$pFind[0]->product_name}' (ID {$prodId}) is deactivated. Only active products can be added to an invoice.");
        }

        if (empty($desc)) $desc = $pFind[0]->product_name ?? '';
        if (empty($hsn))  $hsn  = $pFind[0]->hsn_code ?? '';

        $rate = floatval($it['rate'] ?? ($it['price'] ?? ($pFind[0]->sales_price ?? 0)));
        $qty = floatval($it['qty'] ?? ($it['quantity'] ?? 1));
        if ($qty <= 0) $qty = 1;

        // Stock availability check
        if (function_exists('checkProductStockAvailability')) {
            $avail = checkProductStockAvailability($prodId, $qty);
            if (!empty($avail['track']) && !$avail['available']) {
                $pTitle = !empty($avail['product_name']) ? $avail['product_name'] : $desc;
                $outOfStockErrors[] = [
                    'product_id'      => $prodId,
                    'product_name'    => $pTitle,
                    'required_qty'    => $qty,
                    'available_stock' => floatval($avail['stock'] ?? 0)
                ];
            }
        }

        $prodGstRate = floatval($pFind[0]->gst_rate ?? 18);
        $gstPct = $company_has_gst ? (isset($it['gst_percent']) ? floatval($it['gst_percent']) : (isset($it['gst_pct']) ? floatval($it['gst_pct']) : $prodGstRate)) : 0.00;

        $discType = in_array(strtolower(trim($it['discount_type'] ?? '')), ['fixed', 'percentage'], true) ? strtolower(trim($it['discount_type'])) : 'percentage';
        $discVal = floatval($it['discount_value'] ?? ($it['discount_val'] ?? 0));

        $baseAmt = $rate * $qty;
        $discAmt = 0.00;
        if ($discType === 'percentage') {
            $discAmt = ($baseAmt * $discVal) / 100.00;
        } else {
            $discAmt = min($baseAmt, $discVal);
        }
        if ($discAmt > $baseAmt) $discAmt = $baseAmt;

        $netAmt = $baseAmt - $discAmt;
        $itemTax = $company_has_gst ? (($netAmt * $gstPct) / 100.00) : 0.00;
        $itemTotal = $netAmt + $itemTax;

        $totalTaxable += $netAmt;
        $totalTax += $itemTax;

        $processedItems[] = [
            'product_id'      => $prodId,
            'description'     => $desc,
            'hsn_code'        => $hsn,
            'rate'            => $rate,
            'gst_percent'     => $gstPct,
            'discount_type'   => $discType,
            'discount_value'  => $discVal,
            'discount_amount' => $discAmt,
            'qty'             => $qty,
            'net_amount'      => $netAmt,
            'tax_amount'      => $itemTax,
            'total_amount'    => $itemTotal
        ];
    }

    if (!empty($outOfStockErrors)) {
        $errStrings = [];
        foreach ($outOfStockErrors as $err) {
            $errStrings[] = "• " . $err['product_name'] . " (Required: " . number_format($err['required_qty'], 2) . ", Available: " . number_format($err['available_stock'], 2) . ")";
        }
        apiResponse(400, "Cannot create invoice due to insufficient stock:\n" . implode("\n", $errStrings), [
            'insufficient_stock' => true,
            'details'            => $outOfStockErrors
        ]);
    }

    $cgst_amount = 0.00;
    $sgst_amount = 0.00;
    $igst_amount = 0.00;

    if ($company_has_gst) {
        if ($isGujarat) {
            $cgst_amount = $totalTax / 2.00;
            $sgst_amount = $totalTax / 2.00;
            $igst_amount = 0.00;
        } else {
            $cgst_amount = 0.00;
            $sgst_amount = 0.00;
            $igst_amount = $totalTax;
        }
    }

    $grand_total = $totalTaxable + $totalTax;
    $paid_amount = ($payment_status === 'Paid') ? $grand_total : 0.00;

    $safePartyName = mysqli_real_escape_string($ai_conn, $party_name);
    $safeGstNo = mysqli_real_escape_string($ai_conn, $gst_no);
    $safeInvNo = mysqli_real_escape_string($ai_conn, $invoice_no);
    $safeAddress = mysqli_real_escape_string($ai_conn, $address);
    $dueSql = $due_date ? "'{$due_date}'" : "NULL";

    $insertInvoiceQry = "INSERT INTO tbl_invoice SET
        company_id = {$company_id},
        party_id = {$party_id},
        party_name = '{$safePartyName}',
        gst_no = '{$safeGstNo}',
        invoice_no = '{$safeInvNo}',
        rca = '{$rca}',
        address = '{$safeAddress}',
        state_id = {$state_id},
        city_id = {$city_id},
        invoice_date = '{$invoice_date}',
        due_date = {$dueSql},
        total_amount = {$totalTaxable},
        cgst_amount = {$cgst_amount},
        sgst_amount = {$sgst_amount},
        igst_amount = {$igst_amount},
        tax_amount = {$totalTax},
        grand_total = {$grand_total},
        paid_amount = {$paid_amount},
        payment_status = '{$payment_status}',
        status = '{$status}'";

    $res = mysqli_query($ai_conn, $insertInvoiceQry);
    if (!$res) {
        apiResponse(500, 'Failed to create invoice: ' . mysqli_error($ai_conn));
    }

    $newInvoiceId = mysqli_insert_id($ai_conn);

    foreach ($processedItems as $pItem) {
        $safeDesc = mysqli_real_escape_string($ai_conn, $pItem['description']);
        $safeHsn = mysqli_real_escape_string($ai_conn, $pItem['hsn_code']);
        $itemQry = "INSERT INTO tbl_invoice_items SET
            invoice_id = {$newInvoiceId},
            product_id = {$pItem['product_id']},
            description = '{$safeDesc}',
            hsn_code = '{$safeHsn}',
            rate = {$pItem['rate']},
            gst_percent = {$pItem['gst_percent']},
            discount_type = '{$pItem['discount_type']}',
            discount_value = {$pItem['discount_value']},
            discount_amount = {$pItem['discount_amount']},
            qty = {$pItem['qty']},
            net_amount = {$pItem['net_amount']},
            tax_amount = {$pItem['tax_amount']},
            total_amount = {$pItem['total_amount']}";
        mysqli_query($ai_conn, $itemQry);

        // Deduct stock for new invoice item
        if ($pItem['product_id'] > 0 && function_exists('deductProductStock')) {
            deductProductStock($pItem['product_id'], $pItem['qty']);
        }
    }

    if ($party_id > 0 && function_exists('recalculatePartyOutstanding')) {
        recalculatePartyOutstanding($party_id);
    }
    $printUrl = rtrim(ADMIN_URL, '/') . '/invoice-print.php?id=' . $newInvoiceId;

    apiResponse(200, 'Invoice created successfully.', [
        'id'                     => $newInvoiceId,
        'invoice_no'             => $invoice_no,
        'company_id'             => $company_id,
        'party_id'               => $party_id,
        'party_name'             => $party_name,
        'gst_no'                 => $gst_no,
        'invoice_date'           => $invoice_date,
        'invoice_date_formatted' => date('d-m-Y', strtotime($invoice_date)),
        'due_date'               => $due_date,
        'due_date_formatted'     => date('d-m-Y', strtotime($due_date)),
        'taxable_amount'         => round($totalTaxable, 2),
        'taxable_formatted'      => '₹' . number_format($totalTaxable, 2),
        'cgst_amount'            => round($cgst_amount, 2),
        'sgst_amount'            => round($sgst_amount, 2),
        'igst_amount'            => round($igst_amount, 2),
        'tax_amount'             => round($totalTax, 2),
        'tax_amount_formatted'   => '₹' . number_format($totalTax, 2),
        'grand_total'            => round($grand_total, 2),
        'grand_total_formatted'  => '₹' . number_format($grand_total, 2),
        'paid_amount'            => round($paid_amount, 2),
        'paid_amount_formatted'  => '₹' . number_format($paid_amount, 2),
        'payment_status'         => $payment_status,
        'status'                 => $status,
        'items_count'            => count($processedItems),
        'print_url'              => $printUrl,
        'download_url'           => $printUrl . '&download=1'
    ]);
}

if ($action === 'invoice-edit') {
    if ($method !== 'GET' && $method !== 'POST') {
        apiResponse(405, 'Method not allowed. Use GET or POST.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];
    $invoiceId = (int)($input['id'] ?? ($input['invoice_id'] ?? ($_GET['id'] ?? ($_GET['invoice_id'] ?? 0))));

    if ($invoiceId <= 0) {
        apiResponse(400, 'Invoice id is required.');
    }

    $invoiceQry = "SELECT q.*, c.company_name, p.mobile_no AS party_mobile, p.email AS party_email,
                          s.state_name, ct.city_name
                   FROM tbl_invoice q
                   LEFT JOIN tbl_company c ON q.company_id = c.id
                   LEFT JOIN tbl_party p ON q.party_id = p.id
                   LEFT JOIN tbl_state s ON q.state_id = s.id
                   LEFT JOIN tbl_city ct ON q.city_id = ct.id
                   WHERE q.id = {$invoiceId} AND q.company_id = {$company_id}
                   LIMIT 1";
    $res = $ai_db->aiGetQueryObj($invoiceQry);

    if (empty($res)) {
        apiResponse(404, 'Invoice not found.');
    }

    $q = $res[0];
    $todayDate = date('Y-m-d');
    $grandTotal = (float)$q->grand_total;
    $paidAmt = (float)($q->paid_amount ?? 0);
    $pendingAmt = max(0, $grandTotal - $paidAmt);
    $totalTaxable = (float)$q->total_amount;
    $taxAmount = (float)$q->tax_amount;
    $cgst = (float)$q->cgst_amount;
    $sgst = (float)$q->sgst_amount;
    $igst = (float)$q->igst_amount;
    $payStatus = $q->payment_status ?? 'Pending';
    $isPaid = ($payStatus === 'Paid');

    $printUrl = rtrim(ADMIN_URL, '/') . '/invoice-print.php?id=' . $invoiceId;

    // Fetch items with live stock info
    $itemsRows = $ai_db->aiGetQueryObj("
        SELECT ii.*, p.sku, p.stock AS current_stock, p.min_stock_alert, p.track_stock
        FROM tbl_invoice_items ii
        LEFT JOIN tbl_product p ON ii.product_id = p.id
        WHERE ii.invoice_id = {$invoiceId}
        ORDER BY ii.id ASC
    ");

    $itemsList = [];
    if (!empty($itemsRows)) {
        foreach ($itemsRows as $iRow) {
            $itemQty = (float)$iRow->qty;
            $curStock = (float)($iRow->current_stock ?? 0);
            $effectiveStock = $curStock + $itemQty; // Current stock plus stock held in this invoice
            $itemsList[] = [
                'id'              => (int)$iRow->id,
                'product_id'      => (int)$iRow->product_id,
                'description'     => $iRow->description ?? '',
                'sku'             => $iRow->sku ?? '',
                'hsn_code'        => $iRow->hsn_code ?? '',
                'rate'            => (float)$iRow->rate,
                'rate_formatted'  => '₹' . number_format((float)$iRow->rate, 2),
                'gst_percent'     => (float)$iRow->gst_percent,
                'discount_type'   => $iRow->discount_type ?? 'percentage',
                'discount_value'  => (float)$iRow->discount_value,
                'discount_amount' => (float)$iRow->discount_amount,
                'qty'             => $itemQty,
                'net_amount'      => (float)$iRow->net_amount,
                'tax_amount'      => (float)$iRow->tax_amount,
                'total_amount'    => (float)$iRow->total_amount,
                'total_formatted' => '₹' . number_format((float)$iRow->total_amount, 2),
                'available_stock' => $effectiveStock,
                'track_stock'     => (int)($iRow->track_stock ?? 1) === 1
            ];
        }
    }

    // Payment history
    $paymentRows = $ai_db->aiGetQueryObj("SELECT * FROM tbl_invoice_payments WHERE invoice_id = {$invoiceId} ORDER BY payment_date DESC, id DESC");
    $paymentsList = [];
    if (!empty($paymentRows)) {
        foreach ($paymentRows as $pRow) {
            $paymentsList[] = [
                'id'           => (int)$pRow->id,
                'amount'       => (float)$pRow->amount,
                'payment_date' => $pRow->payment_date,
                'payment_mode' => $pRow->payment_mode,
                'reference_no' => $pRow->reference_no,
                'notes'        => $pRow->notes
            ];
        }
    }

    apiResponse(200, 'Invoice retrieved successfully.', [
        'id'                     => $invoiceId,
        'quotation_id'           => (int)($q->quotation_id ?? 0),
        'invoice_no'             => $q->invoice_no ?? '',
        'company_id'             => (int)$q->company_id,
        'company_name'           => $q->company_name ?? ($company['company_name'] ?? ''),
        'party_id'               => (int)$q->party_id,
        'party_name'             => $q->party_name ?? '',
        'party_mobile'           => $q->party_mobile ?? '',
        'party_email'            => $q->party_email ?? '',
        'gst_no'                 => $q->gst_no ?? '',
        'rca'                    => $q->rca ?? 'No',
        'address'                => $q->address ?? '',
        'state_id'               => (int)($q->state_id ?? 0),
        'state_name'             => $q->state_name ?? '',
        'city_id'                => (int)($q->city_id ?? 0),
        'city_name'              => $q->city_name ?? '',
        'invoice_date'           => $q->invoice_date ?? '',
        'invoice_date_formatted' => !empty($q->invoice_date) ? date('d-m-Y', strtotime($q->invoice_date)) : '',
        'due_date'               => $q->due_date ?? null,
        'due_date_formatted'     => !empty($q->due_date) ? date('d-m-Y', strtotime($q->due_date)) : '',
        'taxable_amount'         => $totalTaxable,
        'taxable_formatted'      => '₹' . number_format($totalTaxable, 2),
        'cgst_amount'            => $cgst,
        'sgst_amount'            => $sgst,
        'igst_amount'            => $igst,
        'tax_amount'             => $taxAmount,
        'tax_amount_formatted'   => '₹' . number_format($taxAmount, 2),
        'grand_total'            => $grandTotal,
        'grand_total_formatted'  => '₹' . number_format($grandTotal, 2),
        'paid_amount'            => $paidAmt,
        'paid_amount_formatted'  => '₹' . number_format($paidAmt, 2),
        'pending_amount'         => $pendingAmt,
        'pending_amount_formatted' => '₹' . number_format($pendingAmt, 2),
        'payment_status'         => $payStatus,
        'status'                 => $q->status ?? 'active',
        'is_overdue'             => ($payStatus !== 'Paid' && !empty($q->due_date) && $q->due_date < $todayDate),
        'is_locked'              => $isPaid,
        'print_url'              => $printUrl,
        'download_url'           => $printUrl . '&download=1',
        'items'                  => $itemsList,
        'payments'               => $paymentsList
    ]);
}

if ($action === 'invoice-update') {
    if ($method !== 'POST' && $method !== 'PUT') {
        apiResponse(405, 'Method not allowed. Use POST or PUT.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];
    $company_has_gst = (int)($company['gst_applicable'] ?? 0) === 1;

    $invoiceId = (int)($input['id'] ?? ($input['invoice_id'] ?? ($_GET['id'] ?? ($_GET['invoice_id'] ?? 0))));
    if ($invoiceId <= 0) {
        apiResponse(400, 'Invoice id is required.');
    }

    $existing = $ai_db->aiGetQueryObj("SELECT * FROM tbl_invoice WHERE id = {$invoiceId} AND company_id = {$company_id} LIMIT 1");
    if (empty($existing)) {
        apiResponse(404, 'Invoice not found.');
    }

    $currInv = $existing[0];
    if (($currInv->payment_status ?? '') === 'Paid') {
        apiResponse(400, "Invoice '{$currInv->invoice_no}' is marked as Paid and locked. Paid invoices cannot be modified.");
    }

    $prevPartyId = (int)($currInv->party_id ?? 0);

    $party_id = isset($input['party_id']) ? (int)$input['party_id'] : $prevPartyId;
    $party_name = trim($input['party_name'] ?? ($currInv->party_name ?? ''));
    $gst_no = $company_has_gst ? trim($input['gst_no'] ?? ($currInv->gst_no ?? '')) : '';
    $address = trim($input['address'] ?? ($currInv->address ?? ''));
    $state_id = isset($input['state_id']) ? (int)$input['state_id'] : (int)($currInv->state_id ?? 0);
    $city_id = isset($input['city_id']) ? (int)$input['city_id'] : (int)($currInv->city_id ?? 0);
    $rca = trim($input['rca'] ?? ($currInv->rca ?? 'No'));

    if ($party_id > 0 && empty($input['party_name'])) {
        $pRow = $ai_db->aiGetQueryObj("SELECT party_name, gst_no, address, state_id, city_id FROM tbl_party WHERE id={$party_id} AND company_id={$company_id} LIMIT 1");
        if (!empty($pRow)) {
            $party_name = $pRow[0]->party_name;
            if ($company_has_gst && empty($input['gst_no'])) $gst_no = $pRow[0]->gst_no ?? '';
            if (empty($input['address'])) $address = $pRow[0]->address ?? '';
            if ($state_id <= 0) $state_id = (int)($pRow[0]->state_id ?? 0);
            if ($city_id <= 0) $city_id = (int)($pRow[0]->city_id ?? 0);
        }
    }

    if (empty($party_id) && empty($party_name)) {
        apiResponse(400, 'Party Name is required.');
    }

    if ($company_has_gst && !empty($gst_no) && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i', $gst_no)) {
        apiResponse(400, 'Please enter a valid 15-digit GST Number (e.g. 24AAAAA0000A1Z5).');
    }

    $invoice_no = trim($input['invoice_no'] ?? ($currInv->invoice_no ?? ''));
    if (empty($invoice_no)) {
        $invoice_no = $currInv->invoice_no;
    } else {
        $safeInvNo = mysqli_real_escape_string($ai_conn, $invoice_no);
        $checkDup = $ai_db->aiGetQueryObj("SELECT id FROM tbl_invoice WHERE invoice_no='{$safeInvNo}' AND company_id={$company_id} AND id != {$invoiceId} LIMIT 1");
        if (!empty($checkDup)) {
            apiResponse(400, "Invoice Number '{$invoice_no}' is already used by another invoice.");
        }
    }

    $invoice_date = !empty($input['invoice_date']) ? date('Y-m-d', strtotime($input['invoice_date'])) : ($currInv->invoice_date ?? date('Y-m-d'));
    $due_date = !empty($input['due_date']) ? date('Y-m-d', strtotime($input['due_date'])) : ($currInv->due_date ?? $invoice_date);
    if ($due_date < $invoice_date) {
        apiResponse(400, 'Due date cannot be earlier than Invoice date.');
    }

    $reqPaymentStatus = isset($input['payment_status']) ? trim((string)$input['payment_status']) : ($currInv->payment_status ?? 'Pending');
    $payment_status = in_array($reqPaymentStatus, ['Pending', 'Partially Paid', 'Paid'], true) ? $reqPaymentStatus : 'Pending';
    $rawStatus = isset($input['status']) && $input['status'] !== null ? strtolower(trim((string)$input['status'])) : ($currInv->status ?? 'active');
    $status = in_array($rawStatus, ['active', 'deactive'], true) ? $rawStatus : 'active';

    $isGujarat = false;
    if ($state_id > 0) {
        $stObj = $ai_db->aiGetQueryObj("SELECT state_name FROM tbl_state WHERE id={$state_id} LIMIT 1");
        if (!empty($stObj)) {
            $isGujarat = (stripos($stObj[0]->state_name, 'Gujarat') !== false);
        }
    }

    $rawItems = $input['items'] ?? null;
    $processedItems = [];
    $totalTaxable = 0.00;
    $totalTax = 0.00;

    // Load existing items with current quantities for effective stock check
    $oldItems = $ai_db->aiGetQueryObj("SELECT product_id, qty FROM tbl_invoice_items WHERE invoice_id={$invoiceId}");
    $oldQtyMap = [];
    if (!empty($oldItems)) {
        foreach ($oldItems as $oIt) {
            $oldPId = (int)$oIt->product_id;
            $oldQtyMap[$oldPId] = ($oldQtyMap[$oldPId] ?? 0.00) + floatval($oIt->qty ?? 0);
        }
    }

    if ($rawItems !== null) {
        if (!is_array($rawItems) || empty($rawItems)) {
            apiResponse(400, 'Items must be a non-empty array.');
        }

        $outOfStockErrors = [];
        foreach ($rawItems as $idx => $it) {
            $prodId = (int)($it['product_id'] ?? ($it['id'] ?? 0));
            $desc = trim($it['description'] ?? ($it['product_name'] ?? ''));
            $hsn = trim($it['hsn_code'] ?? ($it['hsn'] ?? ''));

            if ($prodId <= 0 && !empty($desc)) {
                $escDesc = mysqli_real_escape_string($ai_conn, $desc);
                $pFind = $ai_db->aiGetQueryObj("SELECT id, product_name, hsn_code, sales_price, gst_rate, status, stock, min_stock_alert, track_stock FROM tbl_product WHERE LOWER(TRIM(product_name)) = LOWER(TRIM('{$escDesc}')) AND company_id={$company_id} LIMIT 1");
                if (!empty($pFind)) {
                    $prodId = (int)$pFind[0]->id;
                }
            }

            if ($prodId <= 0) {
                $nameHint = !empty($desc) ? " for '{$desc}'" : "";
                apiResponse(400, "Item{$nameHint} (index {$idx}) must have a valid product_id.");
            }

            $pFind = $ai_db->aiGetQueryObj("SELECT id, product_name, hsn_code, sales_price, gst_rate, status, stock, min_stock_alert, track_stock FROM tbl_product WHERE id={$prodId} AND company_id={$company_id} LIMIT 1");
            if (empty($pFind)) {
                $nameText = !empty($desc) ? " '{$desc}'" : "";
                apiResponse(404, "Product{$nameText} with ID {$prodId} (index {$idx}) does not exist for your company.");
            }
            if (strtolower($pFind[0]->status ?? '') !== 'active') {
                apiResponse(400, "Product '{$pFind[0]->product_name}' (ID {$prodId}) is deactivated. Only active products can be added to an invoice.");
            }

            if (empty($desc)) $desc = $pFind[0]->product_name ?? '';
            if (empty($hsn))  $hsn  = $pFind[0]->hsn_code ?? '';

            $rate = floatval($it['rate'] ?? ($it['price'] ?? ($pFind[0]->sales_price ?? 0)));
            $qty = floatval($it['qty'] ?? ($it['quantity'] ?? 1));
            if ($qty <= 0) $qty = 1;

            // Effective stock availability check (current DB stock + existing invoice qty)
            $track = (isset($pFind[0]->track_stock) && (int)$pFind[0]->track_stock === 0) ? false : true;
            if ($track) {
                $curDbStock = floatval($pFind[0]->stock ?? 0);
                $effectiveAvailStock = $curDbStock + ($oldQtyMap[$prodId] ?? 0.00);
                if ($effectiveAvailStock < $qty) {
                    $outOfStockErrors[] = [
                        'product_id'      => $prodId,
                        'product_name'    => $pFind[0]->product_name ?? $desc,
                        'required_qty'    => $qty,
                        'available_stock' => $effectiveAvailStock
                    ];
                }
            }

            $prodGstRate = floatval($pFind[0]->gst_rate ?? 18);
            $gstPct = $company_has_gst ? (isset($it['gst_percent']) ? floatval($it['gst_percent']) : (isset($it['gst_pct']) ? floatval($it['gst_pct']) : $prodGstRate)) : 0.00;

            $discType = in_array(strtolower(trim($it['discount_type'] ?? '')), ['fixed', 'percentage'], true) ? strtolower(trim($it['discount_type'])) : 'percentage';
            $discVal = floatval($it['discount_value'] ?? ($it['discount_val'] ?? 0));

            $baseAmt = $rate * $qty;
            $discAmt = 0.00;
            if ($discType === 'percentage') {
                $discAmt = ($baseAmt * $discVal) / 100.00;
            } else {
                $discAmt = min($baseAmt, $discVal);
            }
            if ($discAmt > $baseAmt) $discAmt = $baseAmt;

            $netAmt = $baseAmt - $discAmt;
            $itemTax = $company_has_gst ? (($netAmt * $gstPct) / 100.00) : 0.00;
            $itemTotal = $netAmt + $itemTax;

            $totalTaxable += $netAmt;
            $totalTax += $itemTax;

            $processedItems[] = [
                'product_id'      => $prodId,
                'description'     => $desc,
                'hsn_code'        => $hsn,
                'rate'            => $rate,
                'gst_percent'     => $gstPct,
                'discount_type'   => $discType,
                'discount_value'  => $discVal,
                'discount_amount' => $discAmt,
                'qty'             => $qty,
                'net_amount'      => $netAmt,
                'tax_amount'      => $itemTax,
                'total_amount'    => $itemTotal
            ];
        }

        if (!empty($outOfStockErrors)) {
            $errStrings = [];
            foreach ($outOfStockErrors as $err) {
                $errStrings[] = "• " . $err['product_name'] . " (Required: " . number_format($err['required_qty'], 2) . ", Available: " . number_format($err['available_stock'], 2) . ")";
            }
            apiResponse(400, "Cannot update invoice due to insufficient stock:\n" . implode("\n", $errStrings), [
                'insufficient_stock' => true,
                'details'            => $outOfStockErrors
            ]);
        }
    } else {
        $totalTaxable = floatval($currInv->total_amount ?? 0);
        $totalTax = floatval($currInv->tax_amount ?? 0);
    }

    $cgst_amount = 0.00;
    $sgst_amount = 0.00;
    $igst_amount = 0.00;

    if ($company_has_gst) {
        if ($isGujarat) {
            $cgst_amount = $totalTax / 2.00;
            $sgst_amount = $totalTax / 2.00;
            $igst_amount = 0.00;
        } else {
            $cgst_amount = 0.00;
            $sgst_amount = 0.00;
            $igst_amount = $totalTax;
        }
    }

    $grand_total = $totalTaxable + $totalTax;
    $curPaidAmount = floatval($currInv->paid_amount ?? 0);
    $paid_amount = ($payment_status === 'Paid') ? $grand_total : $curPaidAmount;

    $safePartyName = mysqli_real_escape_string($ai_conn, $party_name);
    $safeGstNo = mysqli_real_escape_string($ai_conn, $gst_no);
    $safeInvNo = mysqli_real_escape_string($ai_conn, $invoice_no);
    $safeAddress = mysqli_real_escape_string($ai_conn, $address);
    $dueSql = $due_date ? "'{$due_date}'" : "NULL";

    $updateInvoiceQry = "UPDATE tbl_invoice SET
        party_id = {$party_id},
        party_name = '{$safePartyName}',
        gst_no = '{$safeGstNo}',
        invoice_no = '{$safeInvNo}',
        rca = '{$rca}',
        address = '{$safeAddress}',
        state_id = {$state_id},
        city_id = {$city_id},
        invoice_date = '{$invoice_date}',
        due_date = {$dueSql},
        total_amount = {$totalTaxable},
        cgst_amount = {$cgst_amount},
        sgst_amount = {$sgst_amount},
        igst_amount = {$igst_amount},
        tax_amount = {$totalTax},
        grand_total = {$grand_total},
        paid_amount = {$paid_amount},
        payment_status = '{$payment_status}',
        status = '{$status}'
        WHERE id = {$invoiceId} AND company_id = {$company_id}";

    $updRes = mysqli_query($ai_conn, $updateInvoiceQry);
    if (!$updRes) {
        apiResponse(500, 'Failed to update invoice: ' . mysqli_error($ai_conn));
    }

    if ($rawItems !== null) {
        // Restore stock from old items before re-inserting
        if (function_exists('restoreInvoiceProductStock')) {
            restoreInvoiceProductStock($invoiceId);
        }

        mysqli_query($ai_conn, "DELETE FROM tbl_invoice_items WHERE invoice_id = {$invoiceId}");

        foreach ($processedItems as $pItem) {
            $safeDesc = mysqli_real_escape_string($ai_conn, $pItem['description']);
            $safeHsn = mysqli_real_escape_string($ai_conn, $pItem['hsn_code']);
            $itemQry = "INSERT INTO tbl_invoice_items SET
                invoice_id = {$invoiceId},
                product_id = {$pItem['product_id']},
                description = '{$safeDesc}',
                hsn_code = '{$safeHsn}',
                rate = {$pItem['rate']},
                gst_percent = {$pItem['gst_percent']},
                discount_type = '{$pItem['discount_type']}',
                discount_value = {$pItem['discount_value']},
                discount_amount = {$pItem['discount_amount']},
                qty = {$pItem['qty']},
                net_amount = {$pItem['net_amount']},
                tax_amount = {$pItem['tax_amount']},
                total_amount = {$pItem['total_amount']}";
            mysqli_query($ai_conn, $itemQry);

            // Deduct stock for new items
            if ($pItem['product_id'] > 0 && function_exists('deductProductStock')) {
                deductProductStock($pItem['product_id'], $pItem['qty']);
            }
        }
    }

    if (function_exists('recalculatePartyOutstanding')) {
        if ($party_id > 0) recalculatePartyOutstanding($party_id);
        if ($prevPartyId > 0 && $prevPartyId !== $party_id) recalculatePartyOutstanding($prevPartyId);
    }

    $printUrl = rtrim(ADMIN_URL, '/') . '/invoice-print.php?id=' . $invoiceId;

    apiResponse(200, 'Invoice updated successfully.', [
        'id'                     => $invoiceId,
        'invoice_no'             => $invoice_no,
        'company_id'             => $company_id,
        'party_id'               => $party_id,
        'party_name'             => $party_name,
        'gst_no'                 => $gst_no,
        'invoice_date'           => $invoice_date,
        'invoice_date_formatted' => date('d-m-Y', strtotime($invoice_date)),
        'due_date'               => $due_date,
        'due_date_formatted'     => date('d-m-Y', strtotime($due_date)),
        'taxable_amount'         => round($totalTaxable, 2),
        'taxable_formatted'      => '₹' . number_format($totalTaxable, 2),
        'cgst_amount'            => round($cgst_amount, 2),
        'sgst_amount'            => round($sgst_amount, 2),
        'igst_amount'            => round($igst_amount, 2),
        'tax_amount'             => round($totalTax, 2),
        'tax_amount_formatted'   => '₹' . number_format($totalTax, 2),
        'grand_total'            => round($grand_total, 2),
        'grand_total_formatted'  => '₹' . number_format($grand_total, 2),
        'paid_amount'            => round($paid_amount, 2),
        'paid_amount_formatted'  => '₹' . number_format($paid_amount, 2),
        'payment_status'         => $payment_status,
        'status'                 => $status,
        'items_count'            => ($rawItems !== null) ? count($processedItems) : count($ai_db->aiGetQueryObj("SELECT id FROM tbl_invoice_items WHERE invoice_id = {$invoiceId}")),
        'print_url'              => $printUrl,
        'download_url'           => $printUrl . '&download=1'
    ]);
}

if ($action === 'invoice-delete') {
    if ($method !== 'DELETE' && $method !== 'POST') {
        apiResponse(405, 'Method not allowed. Use DELETE or POST.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];
    $invoiceId = (int)($input['id'] ?? ($input['invoice_id'] ?? ($_GET['id'] ?? ($_GET['invoice_id'] ?? 0))));

    if ($invoiceId <= 0) {
        apiResponse(400, 'Invoice id is required.');
    }

    $existing = $ai_db->aiGetQueryObj("SELECT id, invoice_no, party_id, payment_status, quotation_id FROM tbl_invoice WHERE id = {$invoiceId} AND company_id = {$company_id} LIMIT 1");
    if (empty($existing)) {
        apiResponse(404, 'Invoice not found.');
    }

    $iRow = $existing[0];
    if (($iRow->payment_status ?? '') === 'Paid') {
        apiResponse(400, "Invoice '{$iRow->invoice_no}' is marked as Paid and cannot be deleted.");
    }

    $partyId = (int)($iRow->party_id ?? 0);
    $invNo   = $iRow->invoice_no ?? '';
    $quotId  = (int)($iRow->quotation_id ?? 0);

    // 1. Restore product stock before deleting invoice items
    if (function_exists('restoreInvoiceProductStock')) {
        restoreInvoiceProductStock($invoiceId);
    }

    // 2. Delete invoice records
    $delQry = "DELETE FROM tbl_invoice WHERE id = {$invoiceId} AND company_id = {$company_id}";
    $res = mysqli_query($ai_conn, $delQry);

    if (!$res) {
        apiResponse(500, 'Failed to delete invoice: ' . mysqli_error($ai_conn));
    }

    mysqli_query($ai_conn, "DELETE FROM tbl_invoice_items WHERE invoice_id = {$invoiceId}");
    mysqli_query($ai_conn, "DELETE FROM tbl_invoice_payments WHERE invoice_id = {$invoiceId}");

    // 3. Reset quotation converted_invoice_id if it originated from a quotation
    if ($quotId > 0) {
        mysqli_query($ai_conn, "UPDATE tbl_quotation SET converted_invoice_id = 0 WHERE id = {$quotId} AND converted_invoice_id = {$invoiceId}");
    }

    // 4. Recalculate party outstanding
    if ($partyId > 0 && function_exists('recalculatePartyOutstanding')) {
        recalculatePartyOutstanding($partyId);
    }

    apiResponse(200, 'Invoice deleted successfully.', [
        'id'           => $invoiceId,
        'invoice_no'   => $invNo,
        'deleted'      => true
    ]);
}

if ($action === 'purchase-generate-no') {
    if ($method !== 'GET' && $method !== 'POST') {
        apiResponse(405, 'Method not allowed. Use GET or POST.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];

    $nextPurchaseNo = generatePurchaseNo($ai_db, $company_id);

    apiResponse(200, 'Next purchase bill number generated successfully.', [
        'company_id'  => $company_id,
        'purchase_no' => $nextPurchaseNo
    ]);
}

if ($action === 'purchase-list') {
    if ($method !== 'GET' && $method !== 'POST') {
        apiResponse(405, 'Method not allowed. Use GET or POST.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $cId = (int)$company['id'];

    $todayDate = date('Y-m-d');
    $filterPayment = trim($input['payment_status'] ?? ($_GET['payment_status'] ?? ''));
    $payCond = "";
    if (in_array($filterPayment, ['Pending', 'Partially Paid', 'Paid'], true)) {
        $payCond = " AND q.payment_status = '{$filterPayment}'";
    } elseif ($filterPayment === 'Overdue') {
        $payCond = " AND q.payment_status IN ('Pending','Partially Paid') AND q.due_date IS NOT NULL AND q.due_date < '{$todayDate}'";
    }

    $filterStatus = trim($input['status'] ?? ($_GET['status'] ?? ''));
    $statusCond = "";
    if ($filterStatus === 'active' || $filterStatus === 'deactive') {
        $statusCond = " AND q.status = '{$filterStatus}'";
    }

    $partyId = (int)($input['party_id'] ?? ($_GET['party_id'] ?? 0));
    $partyCond = ($partyId > 0) ? " AND q.party_id = {$partyId}" : "";

    $search = trim($input['search'] ?? ($_GET['search'] ?? ''));
    $searchCond = "";
    if ($search !== '') {
        $safeSearch = mysqli_real_escape_string($ai_conn, $search);
        $searchCond = " AND (q.purchase_no LIKE '%{$safeSearch}%' OR q.party_name LIKE '%{$safeSearch}%' OR q.gst_no LIKE '%{$safeSearch}%')";
    }

    $orderBy = "ORDER BY q.id DESC";

    $purchaseQry = "SELECT q.*, c.company_name, p.mobile_no AS party_mobile, p.email AS party_email,
                           s.state_name, ct.city_name
                    FROM tbl_purchase q
                    LEFT JOIN tbl_company c ON q.company_id = c.id
                    LEFT JOIN tbl_party p ON q.party_id = p.id
                    LEFT JOIN tbl_state s ON q.state_id = s.id
                    LEFT JOIN tbl_city ct ON q.city_id = ct.id
                    WHERE q.company_id = {$cId} {$payCond} {$statusCond} {$partyCond} {$searchCond}
                    {$orderBy}";
    $purchases = $ai_db->aiGetQueryObj($purchaseQry);

    $summaryQry = "SELECT 
                        COUNT(id) AS total_count,
                        COUNT(CASE WHEN payment_status = 'Paid' THEN 1 END) AS paid_count,
                        COUNT(CASE WHEN payment_status != 'Paid' THEN 1 END) AS unpaid_count
                   FROM tbl_purchase
                   WHERE company_id = {$cId}";
    $sumRes = $ai_db->aiGetQueryObj($summaryQry);
    $m = !empty($sumRes) ? $sumRes[0] : null;

    $statTotalCount = (int)($m->total_count ?? 0);
    $statPaidCount = (int)($m->paid_count ?? 0);
    $statUnpaidCount = (int)($m->unpaid_count ?? 0);

    $includeItems = !empty($input['include_items']) || !empty($_GET['include_items']);

    $list = [];
    if (!empty($purchases)) {
        $itemsByPurchase = [];
        if ($includeItems) {
            $purIds = array_map(function($item) { return (int)$item->id; }, $purchases);
            if (!empty($purIds)) {
                $purIdsStr = implode(',', $purIds);
                $itemsRows = $ai_db->aiGetQueryObj("
                    SELECT pi.*, p.sku, p.stock AS current_stock
                    FROM tbl_purchase_items pi
                    LEFT JOIN tbl_product p ON pi.product_id = p.id
                    WHERE pi.purchase_id IN ({$purIdsStr})
                    ORDER BY pi.id ASC
                ");
                if (!empty($itemsRows)) {
                    foreach ($itemsRows as $iRow) {
                        $pid = (int)$iRow->purchase_id;
                        if (!isset($itemsByPurchase[$pid])) {
                            $itemsByPurchase[$pid] = [];
                        }
                        $itemsByPurchase[$pid][] = [
                            'id'              => (int)$iRow->id,
                            'product_id'      => (int)$iRow->product_id,
                            'description'     => $iRow->description ?? '',
                            'sku'             => $iRow->sku ?? '',
                            'hsn_code'        => $iRow->hsn_code ?? '',
                            'rate'            => (float)$iRow->rate,
                            'rate_formatted'  => '₹' . number_format((float)$iRow->rate, 2),
                            'gst_percent'     => (float)$iRow->gst_percent,
                            'discount_type'   => $iRow->discount_type ?? 'percentage',
                            'discount_value'  => (float)$iRow->discount_value,
                            'discount_amount' => (float)$iRow->discount_amount,
                            'qty'             => (float)$iRow->qty,
                            'net_amount'      => (float)$iRow->net_amount,
                            'tax_amount'      => (float)$iRow->tax_amount,
                            'total_amount'    => (float)$iRow->total_amount,
                            'total_formatted' => '₹' . number_format((float)$iRow->total_amount, 2)
                        ];
                    }
                }
            }
        }

        foreach ($purchases as $row) {
            $pid = (int)$row->id;
            $grandTotal = (float)$row->grand_total;
            $paidAmt = (float)($row->paid_amount ?? 0);
            $pendingAmt = max(0, $grandTotal - $paidAmt);
            $totalTaxable = (float)$row->total_amount;
            $taxAmount = (float)$row->tax_amount;
            $cgst = (float)$row->cgst_amount;
            $sgst = (float)$row->sgst_amount;
            $igst = (float)$row->igst_amount;
            $payStatus = $row->payment_status ?? 'Pending';
            $isPaid = ($payStatus === 'Paid');

            $printUrl = rtrim(ADMIN_URL, '/') . '/purchase-print.php?id=' . $pid;

            $itemData = [
                'id'                      => $pid,
                'purchase_no'             => $row->purchase_no ?? '',
                'company_id'              => (int)$row->company_id,
                'company_name'            => $row->company_name ?? ($company['company_name'] ?? ''),
                'party_id'                => (int)$row->party_id,
                'party_name'              => $row->party_name ?? '',
                'party_mobile'            => $row->party_mobile ?? '',
                'party_email'             => $row->party_email ?? '',
                'gst_no'                  => $row->gst_no ?? '',
                'rca'                     => $row->rca ?? 'No',
                'address'                 => $row->address ?? '',
                'state_id'                => (int)($row->state_id ?? 0),
                'state_name'              => $row->state_name ?? '',
                'city_id'                 => (int)($row->city_id ?? 0),
                'city_name'               => $row->city_name ?? '',
                'purchase_date'           => $row->purchase_date ?? '',
                'purchase_date_formatted' => !empty($row->purchase_date) ? date('d-m-Y', strtotime($row->purchase_date)) : '',
                'due_date'                => $row->due_date ?? null,
                'due_date_formatted'      => !empty($row->due_date) ? date('d-m-Y', strtotime($row->due_date)) : '',
                'taxable_amount'          => $totalTaxable,
                'taxable_formatted'       => '₹' . number_format($totalTaxable, 2),
                'cgst_amount'             => $cgst,
                'sgst_amount'             => $sgst,
                'igst_amount'             => $igst,
                'tax_amount'              => $taxAmount,
                'tax_amount_formatted'    => '₹' . number_format($taxAmount, 2),
                'grand_total'             => $grandTotal,
                'grand_total_formatted'   => '₹' . number_format($grandTotal, 2),
                'paid_amount'             => $paidAmt,
                'paid_amount_formatted'   => '₹' . number_format($paidAmt, 2),
                'pending_amount'          => $pendingAmt,
                'pending_amount_formatted'=> '₹' . number_format($pendingAmt, 2),
                'payment_status'          => $payStatus,
                'status'                  => $row->status ?? 'active',
                'is_overdue'              => ($payStatus !== 'Paid' && !empty($row->due_date) && $row->due_date < $todayDate),
                'is_locked'               => $isPaid,
                'print_url'               => $printUrl,
                'download_url'            => $printUrl . '&download=1',
                'created_at'              => $row->created_at ?? ''
            ];

            if ($includeItems) {
                $itemData['items'] = $itemsByPurchase[$pid] ?? [];
            }

            $list[] = $itemData;
        }
    }

    apiResponse(200, 'Purchase list retrieved successfully.', [
        'company_id'      => $cId,
        'company_name'    => $company['company_name'] ?? '',
        'total'           => count($list),
        'total_purchases' => $statTotalCount,
        'paid_purchases'  => $statPaidCount,
        'unpaid_purchases'=> $statUnpaidCount,
        'purchases'       => $list
    ]);
}

if ($action === 'purchase-add') {
    if ($method !== 'POST') {
        apiResponse(405, 'Only POST method is allowed.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];
    $company_has_gst = (int)($company['gst_applicable'] ?? 0) === 1;

    $party_id = (int)($input['party_id'] ?? 0);
    $party_name = trim($input['party_name'] ?? '');
    $gst_no = $company_has_gst ? trim($input['gst_no'] ?? '') : '';
    $address = trim($input['address'] ?? '');
    $state_id = (int)($input['state_id'] ?? 0);
    $city_id = (int)($input['city_id'] ?? 0);
    $rca = trim($input['rca'] ?? 'No');
    if ($party_id > 0) {
        $pRow = $ai_db->aiGetQueryObj("SELECT party_name, gst_no, address, state_id, city_id FROM tbl_party WHERE id={$party_id} AND company_id={$company_id} LIMIT 1");
        if (!empty($pRow)) {
            if (empty($party_name)) $party_name = $pRow[0]->party_name ?? '';
            if ($company_has_gst && empty($gst_no)) $gst_no = $pRow[0]->gst_no ?? '';
            if (empty($address)) $address = $pRow[0]->address ?? '';
            if ($state_id <= 0) $state_id = (int)($pRow[0]->state_id ?? 0);
            if ($city_id <= 0) $city_id = (int)($pRow[0]->city_id ?? 0);
        }
    }

    if (empty($party_id) && empty($party_name)) {
        apiResponse(400, 'Party Name is required.');
    }

    if ($company_has_gst && !empty($gst_no) && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i', $gst_no)) {
        apiResponse(400, 'Please enter a valid 15-digit GST Number (e.g. 24AAAAA0000A1Z5).');
    }

    $purchase_no = trim($input['purchase_no'] ?? '');
    if (empty($purchase_no)) {
        $purchase_no = generateCompanyPurchaseNo($ai_db, $company_id);
    } else {
        $safePurNo = mysqli_real_escape_string($ai_conn, $purchase_no);
        $checkDup = $ai_db->aiGetQueryObj("SELECT id FROM tbl_purchase WHERE purchase_no='{$safePurNo}' AND company_id={$company_id} LIMIT 1");
        if (!empty($checkDup)) {
            apiResponse(400, "Purchase Bill Number '{$purchase_no}' already exists for this company.");
        }
    }

    $purchase_date = !empty($input['purchase_date']) ? date('Y-m-d', strtotime($input['purchase_date'])) : date('Y-m-d');
    $due_date = !empty($input['due_date']) ? date('Y-m-d', strtotime($input['due_date'])) : $purchase_date;
    if ($due_date < $purchase_date) {
        apiResponse(400, 'Due date cannot be earlier than Purchase date.');
    }

    $reqPaymentStatus = isset($input['payment_status']) ? trim((string)$input['payment_status']) : 'Pending';
    $payment_status = ($reqPaymentStatus === 'Paid') ? 'Paid' : 'Pending';
    $rawStatus = isset($input['status']) && $input['status'] !== null ? strtolower(trim((string)$input['status'])) : 'active';
    $status = in_array($rawStatus, ['active', 'deactive'], true) ? $rawStatus : 'active';

    $isGujarat = false;
    if ($state_id > 0) {
        $stObj = $ai_db->aiGetQueryObj("SELECT state_name FROM tbl_state WHERE id={$state_id} LIMIT 1");
        if (!empty($stObj)) {
            $isGujarat = (stripos($stObj[0]->state_name, 'Gujarat') !== false);
        }
    }

    $rawItems = $input['items'] ?? [];
    if (!is_array($rawItems) || empty($rawItems)) {
        apiResponse(400, 'Purchase bill must contain at least one item.');
    }

    $processedItems = [];
    $totalTaxable = 0.00;
    $totalTax = 0.00;

    foreach ($rawItems as $idx => $it) {
        $prodId = (int)($it['product_id'] ?? ($it['id'] ?? 0));
        $desc = trim($it['description'] ?? ($it['product_name'] ?? ''));
        $hsn = trim($it['hsn_code'] ?? ($it['hsn'] ?? ''));

        if ($prodId <= 0 && !empty($desc)) {
            $escDesc = mysqli_real_escape_string($ai_conn, $desc);
            $pFind = $ai_db->aiGetQueryObj("SELECT id, product_name, hsn_code, purchase_price, gst_rate, status FROM tbl_product WHERE LOWER(TRIM(product_name)) = LOWER(TRIM('{$escDesc}')) AND company_id={$company_id} LIMIT 1");
            if (!empty($pFind)) {
                $prodId = (int)$pFind[0]->id;
            }
        }

        if ($prodId <= 0) {
            $nameHint = !empty($desc) ? " for '{$desc}'" : "";
            apiResponse(400, "Item{$nameHint} (index {$idx}) must have a valid product_id.");
        }

        $pFind = $ai_db->aiGetQueryObj("SELECT id, product_name, hsn_code, purchase_price, gst_rate, status FROM tbl_product WHERE id={$prodId} AND company_id={$company_id} LIMIT 1");
        if (empty($pFind)) {
            $nameText = !empty($desc) ? " '{$desc}'" : "";
            apiResponse(404, "Product{$nameText} with ID {$prodId} (index {$idx}) does not exist for your company.");
        }
        if (strtolower($pFind[0]->status ?? '') !== 'active') {
            apiResponse(400, "Product '{$pFind[0]->product_name}' (ID {$prodId}) is deactivated. Only active products can be added to a purchase bill.");
        }

        if (empty($desc)) $desc = $pFind[0]->product_name ?? '';
        if (empty($hsn))  $hsn  = $pFind[0]->hsn_code ?? '';

        $rate = floatval($it['rate'] ?? ($it['price'] ?? ($pFind[0]->purchase_price ?? 0)));
        $qty = floatval($it['qty'] ?? ($it['quantity'] ?? 1));
        if ($qty <= 0) $qty = 1;

        $prodGstRate = floatval($pFind[0]->gst_rate ?? 18);
        $gstPct = $company_has_gst ? (isset($it['gst_percent']) ? floatval($it['gst_percent']) : (isset($it['gst_pct']) ? floatval($it['gst_pct']) : $prodGstRate)) : 0.00;

        $discType = in_array(strtolower(trim($it['discount_type'] ?? '')), ['fixed', 'percentage'], true) ? strtolower(trim($it['discount_type'])) : 'percentage';
        $discVal = floatval($it['discount_value'] ?? ($it['discount_val'] ?? 0));

        $baseAmt = $rate * $qty;
        $discAmt = 0.00;
        if ($discType === 'percentage') {
            $discAmt = ($baseAmt * $discVal) / 100.00;
        } else {
            $discAmt = min($baseAmt, $discVal);
        }
        if ($discAmt > $baseAmt) $discAmt = $baseAmt;

        $netAmt = $baseAmt - $discAmt;
        $itemTax = $company_has_gst ? (($netAmt * $gstPct) / 100.00) : 0.00;
        $itemTotal = $netAmt + $itemTax;

        $totalTaxable += $netAmt;
        $totalTax += $itemTax;

        $processedItems[] = [
            'product_id'      => $prodId,
            'description'     => $desc,
            'hsn_code'        => $hsn,
            'rate'            => $rate,
            'gst_percent'     => $gstPct,
            'discount_type'   => $discType,
            'discount_value'  => $discVal,
            'discount_amount' => $discAmt,
            'qty'             => $qty,
            'net_amount'      => $netAmt,
            'tax_amount'      => $itemTax,
            'total_amount'    => $itemTotal
        ];
    }

    $cgst_amount = 0.00;
    $sgst_amount = 0.00;
    $igst_amount = 0.00;

    if ($company_has_gst) {
        if ($isGujarat) {
            $cgst_amount = $totalTax / 2.00;
            $sgst_amount = $totalTax / 2.00;
            $igst_amount = 0.00;
        } else {
            $cgst_amount = 0.00;
            $sgst_amount = 0.00;
            $igst_amount = $totalTax;
        }
    }

    $grand_total = $totalTaxable + $totalTax;
    $paid_amount = ($payment_status === 'Paid') ? $grand_total : 0.00;

    $safePartyName = mysqli_real_escape_string($ai_conn, $party_name);
    $safeGstNo = mysqli_real_escape_string($ai_conn, $gst_no);
    $safePurNo = mysqli_real_escape_string($ai_conn, $purchase_no);
    $safeAddress = mysqli_real_escape_string($ai_conn, $address);
    $dueSql = $due_date ? "'{$due_date}'" : "NULL";

    $insertPurchaseQry = "INSERT INTO tbl_purchase SET
        company_id = {$company_id},
        party_id = {$party_id},
        party_name = '{$safePartyName}',
        gst_no = '{$safeGstNo}',
        purchase_no = '{$safePurNo}',
        rca = '{$rca}',
        address = '{$safeAddress}',
        state_id = {$state_id},
        city_id = {$city_id},
        purchase_date = '{$purchase_date}',
        due_date = {$dueSql},
        total_amount = {$totalTaxable},
        cgst_amount = {$cgst_amount},
        sgst_amount = {$sgst_amount},
        igst_amount = {$igst_amount},
        tax_amount = {$totalTax},
        grand_total = {$grand_total},
        paid_amount = {$paid_amount},
        payment_status = '{$payment_status}',
        status = '{$status}'";

    $res = mysqli_query($ai_conn, $insertPurchaseQry);
    if (!$res) {
        apiResponse(500, 'Failed to create purchase bill: ' . mysqli_error($ai_conn));
    }

    $newPurchaseId = mysqli_insert_id($ai_conn);

    foreach ($processedItems as $pItem) {
        $safeDesc = mysqli_real_escape_string($ai_conn, $pItem['description']);
        $safeHsn = mysqli_real_escape_string($ai_conn, $pItem['hsn_code']);
        $itemQry = "INSERT INTO tbl_purchase_items SET
            purchase_id = {$newPurchaseId},
            product_id = {$pItem['product_id']},
            description = '{$safeDesc}',
            hsn_code = '{$safeHsn}',
            rate = {$pItem['rate']},
            gst_percent = {$pItem['gst_percent']},
            discount_type = '{$pItem['discount_type']}',
            discount_value = {$pItem['discount_value']},
            discount_amount = {$pItem['discount_amount']},
            qty = {$pItem['qty']},
            net_amount = {$pItem['net_amount']},
            tax_amount = {$pItem['tax_amount']},
            total_amount = {$pItem['total_amount']}";
        mysqli_query($ai_conn, $itemQry);

        // Increase product stock upon purchase (+)
        if ($pItem['product_id'] > 0 && function_exists('addProductStock')) {
            addProductStock($pItem['product_id'], $pItem['qty']);
        }
    }

    if ($party_id > 0 && function_exists('recalculatePartyOutstanding')) {
        recalculatePartyOutstanding($party_id);
    }
    $printUrl = rtrim(ADMIN_URL, '/') . '/purchase-print.php?id=' . $newPurchaseId;

    apiResponse(200, 'Purchase bill created successfully.', [
        'id'                      => $newPurchaseId,
        'purchase_no'             => $purchase_no,
        'company_id'              => $company_id,
        'party_id'                => $party_id,
        'party_name'              => $party_name,
        'gst_no'                  => $gst_no,
        'purchase_date'           => $purchase_date,
        'purchase_date_formatted' => date('d-m-Y', strtotime($purchase_date)),
        'due_date'                => $due_date,
        'due_date_formatted'      => date('d-m-Y', strtotime($due_date)),
        'taxable_amount'          => round($totalTaxable, 2),
        'taxable_formatted'       => '₹' . number_format($totalTaxable, 2),
        'cgst_amount'             => round($cgst_amount, 2),
        'sgst_amount'             => round($sgst_amount, 2),
        'igst_amount'             => round($igst_amount, 2),
        'tax_amount'              => round($totalTax, 2),
        'tax_amount_formatted'    => '₹' . number_format($totalTax, 2),
        'grand_total'             => round($grand_total, 2),
        'grand_total_formatted'   => '₹' . number_format($grand_total, 2),
        'paid_amount'             => round($paid_amount, 2),
        'paid_amount_formatted'   => '₹' . number_format($paid_amount, 2),
        'payment_status'          => $payment_status,
        'status'                  => $status,
        'items_count'             => count($processedItems),
        'print_url'               => $printUrl,
        'download_url'            => $printUrl . '&download=1'
    ]);
}

if ($action === 'purchase-edit') {
    if ($method !== 'GET' && $method !== 'POST') {
        apiResponse(405, 'Method not allowed. Use GET or POST.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];
    $purchaseId = (int)($input['id'] ?? ($input['purchase_id'] ?? ($_GET['id'] ?? ($_GET['purchase_id'] ?? 0))));

    if ($purchaseId <= 0) {
        apiResponse(400, 'Purchase ID is required.');
    }

    $purchaseQry = "SELECT q.*, c.company_name, p.mobile_no AS party_mobile, p.email AS party_email,
                           s.state_name, ct.city_name
                    FROM tbl_purchase q
                    LEFT JOIN tbl_company c ON q.company_id = c.id
                    LEFT JOIN tbl_party p ON q.party_id = p.id
                    LEFT JOIN tbl_state s ON q.state_id = s.id
                    LEFT JOIN tbl_city ct ON q.city_id = ct.id
                    WHERE q.id = {$purchaseId} AND q.company_id = {$company_id}
                    LIMIT 1";
    $res = $ai_db->aiGetQueryObj($purchaseQry);

    if (empty($res)) {
        apiResponse(404, 'Purchase bill not found.');
    }

    $q = $res[0];
    $todayDate = date('Y-m-d');
    $grandTotal = (float)$q->grand_total;
    $paidAmt = (float)($q->paid_amount ?? 0);
    $pendingAmt = max(0, $grandTotal - $paidAmt);
    $totalTaxable = (float)$q->total_amount;
    $taxAmount = (float)$q->tax_amount;
    $cgst = (float)$q->cgst_amount;
    $sgst = (float)$q->sgst_amount;
    $igst = (float)$q->igst_amount;
    $payStatus = $q->payment_status ?? 'Pending';
    $isPaid = ($payStatus === 'Paid');

    $printUrl = rtrim(ADMIN_URL, '/') . '/purchase-print.php?id=' . $purchaseId;

    // Fetch items with product information
    $itemsRows = $ai_db->aiGetQueryObj("
        SELECT pi.*, p.sku, p.stock AS current_stock, p.min_stock_alert, p.track_stock
        FROM tbl_purchase_items pi
        LEFT JOIN tbl_product p ON pi.product_id = p.id
        WHERE pi.purchase_id = {$purchaseId}
        ORDER BY pi.id ASC
    ");

    $itemsList = [];
    if (!empty($itemsRows)) {
        foreach ($itemsRows as $iRow) {
            $itemQty = (float)$iRow->qty;
            $itemsList[] = [
                'id'              => (int)$iRow->id,
                'product_id'      => (int)$iRow->product_id,
                'description'     => $iRow->description ?? '',
                'sku'             => $iRow->sku ?? '',
                'hsn_code'        => $iRow->hsn_code ?? '',
                'rate'            => (float)$iRow->rate,
                'rate_formatted'  => '₹' . number_format((float)$iRow->rate, 2),
                'gst_percent'     => (float)$iRow->gst_percent,
                'discount_type'   => $iRow->discount_type ?? 'percentage',
                'discount_value'  => (float)$iRow->discount_value,
                'discount_amount' => (float)$iRow->discount_amount,
                'qty'             => $itemQty,
                'net_amount'      => (float)$iRow->net_amount,
                'tax_amount'      => (float)$iRow->tax_amount,
                'total_amount'    => (float)$iRow->total_amount,
                'total_formatted' => '₹' . number_format((float)$iRow->total_amount, 2),
                'current_stock'   => (float)($iRow->current_stock ?? 0),
                'track_stock'     => (int)($iRow->track_stock ?? 1) === 1
            ];
        }
    }

    // Payment history
    $paymentRows = $ai_db->aiGetQueryObj("SELECT * FROM tbl_purchase_payments WHERE purchase_id = {$purchaseId} ORDER BY payment_date DESC, id DESC");
    $paymentsList = [];
    if (!empty($paymentRows)) {
        foreach ($paymentRows as $pRow) {
            $paymentsList[] = [
                'id'           => (int)$pRow->id,
                'amount'       => (float)$pRow->amount,
                'payment_date' => $pRow->payment_date,
                'payment_mode' => $pRow->payment_mode,
                'reference_no' => $pRow->reference_no,
                'notes'        => $pRow->notes
            ];
        }
    }

    apiResponse(200, 'Purchase bill retrieved successfully.', [
        'id'                      => $purchaseId,
        'purchase_no'             => $q->purchase_no ?? '',
        'company_id'              => (int)$q->company_id,
        'company_name'            => $q->company_name ?? ($company['company_name'] ?? ''),
        'party_id'                => (int)$q->party_id,
        'party_name'              => $q->party_name ?? '',
        'party_mobile'            => $q->party_mobile ?? '',
        'party_email'             => $q->party_email ?? '',
        'gst_no'                  => $q->gst_no ?? '',
        'rca'                     => $q->rca ?? 'No',
        'address'                 => $q->address ?? '',
        'state_id'                => (int)($q->state_id ?? 0),
        'state_name'              => $q->state_name ?? '',
        'city_id'                 => (int)($q->city_id ?? 0),
        'city_name'               => $q->city_name ?? '',
        'purchase_date'           => $q->purchase_date ?? '',
        'purchase_date_formatted' => !empty($q->purchase_date) ? date('d-m-Y', strtotime($q->purchase_date)) : '',
        'due_date'                => $q->due_date ?? null,
        'due_date_formatted'      => !empty($q->due_date) ? date('d-m-Y', strtotime($q->due_date)) : '',
        'taxable_amount'          => $totalTaxable,
        'taxable_formatted'       => '₹' . number_format($totalTaxable, 2),
        'cgst_amount'             => $cgst,
        'sgst_amount'             => $sgst,
        'igst_amount'             => $igst,
        'tax_amount'              => $taxAmount,
        'tax_amount_formatted'    => '₹' . number_format($taxAmount, 2),
        'grand_total'             => $grandTotal,
        'grand_total_formatted'   => '₹' . number_format($grandTotal, 2),
        'paid_amount'             => $paidAmt,
        'paid_amount_formatted'   => '₹' . number_format($paidAmt, 2),
        'pending_amount'          => $pendingAmt,
        'pending_amount_formatted'=> '₹' . number_format($pendingAmt, 2),
        'payment_status'          => $payStatus,
        'status'                  => $q->status ?? 'active',
        'is_overdue'              => ($payStatus !== 'Paid' && !empty($q->due_date) && $q->due_date < $todayDate),
        'is_locked'               => $isPaid,
        'print_url'               => $printUrl,
        'download_url'            => $printUrl . '&download=1',
        'items'                   => $itemsList,
        'payments'                => $paymentsList
    ]);
}

if ($action === 'purchase-update') {
    if ($method !== 'POST' && $method !== 'PUT') {
        apiResponse(405, 'Method not allowed. Use POST or PUT.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];
    $company_has_gst = (int)($company['gst_applicable'] ?? 0) === 1;

    $purchaseId = (int)($input['id'] ?? ($input['purchase_id'] ?? ($_GET['id'] ?? ($_GET['purchase_id'] ?? 0))));
    if ($purchaseId <= 0) {
        apiResponse(400, 'Purchase ID is required.');
    }

    $currRes = $ai_db->aiGetQueryObj("SELECT * FROM tbl_purchase WHERE id = {$purchaseId} AND company_id = {$company_id} LIMIT 1");
    if (empty($currRes)) {
        apiResponse(404, 'Purchase bill not found.');
    }

    $currPur = $currRes[0];
    $prevPartyId = (int)($currPur->party_id ?? 0);

    $party_id = isset($input['party_id']) ? (int)$input['party_id'] : $prevPartyId;
    $party_name = trim($input['party_name'] ?? ($currPur->party_name ?? ''));
    $gst_no = $company_has_gst ? trim($input['gst_no'] ?? ($currPur->gst_no ?? '')) : '';
    $address = trim($input['address'] ?? ($currPur->address ?? ''));
    $state_id = isset($input['state_id']) ? (int)$input['state_id'] : (int)($currPur->state_id ?? 0);
    $city_id = isset($input['city_id']) ? (int)$input['city_id'] : (int)($currPur->city_id ?? 0);
    $rca = trim($input['rca'] ?? ($currPur->rca ?? 'No'));

    if ($party_id > 0 && empty($input['party_name'])) {
        $pRow = $ai_db->aiGetQueryObj("SELECT party_name, gst_no, address, state_id, city_id FROM tbl_party WHERE id={$party_id} AND company_id={$company_id} LIMIT 1");
        if (!empty($pRow)) {
            $party_name = $pRow[0]->party_name;
            if ($company_has_gst && empty($input['gst_no'])) $gst_no = $pRow[0]->gst_no ?? '';
            if (empty($input['address'])) $address = $pRow[0]->address ?? '';
            if ($state_id <= 0) $state_id = (int)($pRow[0]->state_id ?? 0);
            if ($city_id <= 0) $city_id = (int)($pRow[0]->city_id ?? 0);
        }
    }

    if (empty($party_id) && empty($party_name)) {
        apiResponse(400, 'Party Name is required.');
    }

    if ($company_has_gst && !empty($gst_no) && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i', $gst_no)) {
        apiResponse(400, 'Please enter a valid 15-digit GST Number (e.g. 24AAAAA0000A1Z5).');
    }

    $purchase_no = trim($input['purchase_no'] ?? ($currPur->purchase_no ?? ''));
    if (empty($purchase_no)) {
        $purchase_no = $currPur->purchase_no;
    } else {
        $safePurNo = mysqli_real_escape_string($ai_conn, $purchase_no);
        $checkDup = $ai_db->aiGetQueryObj("SELECT id FROM tbl_purchase WHERE purchase_no='{$safePurNo}' AND company_id={$company_id} AND id != {$purchaseId} LIMIT 1");
        if (!empty($checkDup)) {
            apiResponse(400, "Purchase Bill Number '{$purchase_no}' is already used by another purchase bill.");
        }
    }

    $purchase_date = !empty($input['purchase_date']) ? date('Y-m-d', strtotime($input['purchase_date'])) : ($currPur->purchase_date ?? date('Y-m-d'));
    $due_date = !empty($input['due_date']) ? date('Y-m-d', strtotime($input['due_date'])) : ($currPur->due_date ?? $purchase_date);
    if ($due_date < $purchase_date) {
        apiResponse(400, 'Due date cannot be earlier than Purchase date.');
    }

    $reqPaymentStatus = isset($input['payment_status']) ? trim((string)$input['payment_status']) : ($currPur->payment_status ?? 'Pending');
    $payment_status = in_array($reqPaymentStatus, ['Pending', 'Partially Paid', 'Paid'], true) ? $reqPaymentStatus : 'Pending';
    $rawStatus = isset($input['status']) && $input['status'] !== null ? strtolower(trim((string)$input['status'])) : ($currPur->status ?? 'active');
    $status = in_array($rawStatus, ['active', 'deactive'], true) ? $rawStatus : 'active';

    $isGujarat = false;
    if ($state_id > 0) {
        $stObj = $ai_db->aiGetQueryObj("SELECT state_name FROM tbl_state WHERE id={$state_id} LIMIT 1");
        if (!empty($stObj)) {
            $isGujarat = (stripos($stObj[0]->state_name, 'Gujarat') !== false);
        }
    }

    $rawItems = $input['items'] ?? null;
    $processedItems = [];
    $totalTaxable = 0.00;
    $totalTax = 0.00;

    if ($rawItems !== null) {
        if (!is_array($rawItems) || empty($rawItems)) {
            apiResponse(400, 'Items must be a non-empty array.');
        }

        foreach ($rawItems as $idx => $it) {
            $prodId = (int)($it['product_id'] ?? ($it['id'] ?? 0));
            $desc = trim($it['description'] ?? ($it['product_name'] ?? ''));
            $hsn = trim($it['hsn_code'] ?? ($it['hsn'] ?? ''));

            if ($prodId <= 0 && !empty($desc)) {
                $escDesc = mysqli_real_escape_string($ai_conn, $desc);
                $pFind = $ai_db->aiGetQueryObj("SELECT id, product_name, hsn_code, purchase_price, gst_rate, status FROM tbl_product WHERE LOWER(TRIM(product_name)) = LOWER(TRIM('{$escDesc}')) AND company_id={$company_id} LIMIT 1");
                if (!empty($pFind)) {
                    $prodId = (int)$pFind[0]->id;
                }
            }

            if ($prodId <= 0) {
                $nameHint = !empty($desc) ? " for '{$desc}'" : "";
                apiResponse(400, "Item{$nameHint} (index {$idx}) must have a valid product_id.");
            }

            $pFind = $ai_db->aiGetQueryObj("SELECT id, product_name, hsn_code, purchase_price, gst_rate, status FROM tbl_product WHERE id={$prodId} AND company_id={$company_id} LIMIT 1");
            if (empty($pFind)) {
                $nameText = !empty($desc) ? " '{$desc}'" : "";
                apiResponse(404, "Product{$nameText} with ID {$prodId} (index {$idx}) does not exist for your company.");
            }
            if (strtolower($pFind[0]->status ?? '') !== 'active') {
                apiResponse(400, "Product '{$pFind[0]->product_name}' (ID {$prodId}) is deactivated. Only active products can be added to a purchase bill.");
            }

            if (empty($desc)) $desc = $pFind[0]->product_name ?? '';
            if (empty($hsn))  $hsn  = $pFind[0]->hsn_code ?? '';

            $rate = floatval($it['rate'] ?? ($it['price'] ?? ($pFind[0]->purchase_price ?? 0)));
            $qty = floatval($it['qty'] ?? ($it['quantity'] ?? 1));
            if ($qty <= 0) $qty = 1;

            $prodGstRate = floatval($pFind[0]->gst_rate ?? 18);
            $gstPct = $company_has_gst ? (isset($it['gst_percent']) ? floatval($it['gst_percent']) : (isset($it['gst_pct']) ? floatval($it['gst_pct']) : $prodGstRate)) : 0.00;

            $discType = in_array(strtolower(trim($it['discount_type'] ?? '')), ['fixed', 'percentage'], true) ? strtolower(trim($it['discount_type'])) : 'percentage';
            $discVal = floatval($it['discount_value'] ?? ($it['discount_val'] ?? 0));

            $baseAmt = $rate * $qty;
            $discAmt = 0.00;
            if ($discType === 'percentage') {
                $discAmt = ($baseAmt * $discVal) / 100.00;
            } else {
                $discAmt = min($baseAmt, $discVal);
            }
            if ($discAmt > $baseAmt) $discAmt = $baseAmt;

            $netAmt = $baseAmt - $discAmt;
            $itemTax = $company_has_gst ? (($netAmt * $gstPct) / 100.00) : 0.00;
            $itemTotal = $netAmt + $itemTax;

            $totalTaxable += $netAmt;
            $totalTax += $itemTax;

            $processedItems[] = [
                'product_id'      => $prodId,
                'description'     => $desc,
                'hsn_code'        => $hsn,
                'rate'            => $rate,
                'gst_percent'     => $gstPct,
                'discount_type'   => $discType,
                'discount_value'  => $discVal,
                'discount_amount' => $discAmt,
                'qty'             => $qty,
                'net_amount'      => $netAmt,
                'tax_amount'      => $itemTax,
                'total_amount'    => $itemTotal
            ];
        }

        $cgst_amount = 0.00;
        $sgst_amount = 0.00;
        $igst_amount = 0.00;

        if ($company_has_gst) {
            if ($isGujarat) {
                $cgst_amount = $totalTax / 2.00;
                $sgst_amount = $totalTax / 2.00;
                $igst_amount = 0.00;
            } else {
                $cgst_amount = 0.00;
                $sgst_amount = 0.00;
                $igst_amount = $totalTax;
            }
        }
    } else {
        // Items not provided, keep current totals
        $totalTaxable = (float)$currPur->total_amount;
        $cgst_amount = (float)$currPur->cgst_amount;
        $sgst_amount = (float)$currPur->sgst_amount;
        $igst_amount = (float)$currPur->igst_amount;
        $totalTax = (float)$currPur->tax_amount;
    }

    $grand_total = $totalTaxable + $totalTax;
    $curPaidAmount = floatval($currPur->paid_amount ?? 0);
    $paid_amount = ($payment_status === 'Paid') ? $grand_total : $curPaidAmount;

    $safePartyName = mysqli_real_escape_string($ai_conn, $party_name);
    $safeGstNo = mysqli_real_escape_string($ai_conn, $gst_no);
    $safePurNo = mysqli_real_escape_string($ai_conn, $purchase_no);
    $safeAddress = mysqli_real_escape_string($ai_conn, $address);
    $dueSql = $due_date ? "'{$due_date}'" : "NULL";

    $updatePurchaseQry = "UPDATE tbl_purchase SET
        party_id = {$party_id},
        party_name = '{$safePartyName}',
        gst_no = '{$safeGstNo}',
        purchase_no = '{$safePurNo}',
        rca = '{$rca}',
        address = '{$safeAddress}',
        state_id = {$state_id},
        city_id = {$city_id},
        purchase_date = '{$purchase_date}',
        due_date = {$dueSql},
        total_amount = {$totalTaxable},
        cgst_amount = {$cgst_amount},
        sgst_amount = {$sgst_amount},
        igst_amount = {$igst_amount},
        tax_amount = {$totalTax},
        grand_total = {$grand_total},
        paid_amount = {$paid_amount},
        payment_status = '{$payment_status}',
        status = '{$status}'
        WHERE id = {$purchaseId} AND company_id = {$company_id}";

    $updRes = mysqli_query($ai_conn, $updatePurchaseQry);
    if (!$updRes) {
        apiResponse(500, 'Failed to update purchase bill: ' . mysqli_error($ai_conn));
    }

    if ($rawItems !== null) {
        // Revert previous stock addition before re-inserting
        if (function_exists('restorePurchaseProductStock')) {
            restorePurchaseProductStock($purchaseId);
        }

        mysqli_query($ai_conn, "DELETE FROM tbl_purchase_items WHERE purchase_id = {$purchaseId}");

        foreach ($processedItems as $pItem) {
            $safeDesc = mysqli_real_escape_string($ai_conn, $pItem['description']);
            $safeHsn = mysqli_real_escape_string($ai_conn, $pItem['hsn_code']);
            $itemQry = "INSERT INTO tbl_purchase_items SET
                purchase_id = {$purchaseId},
                product_id = {$pItem['product_id']},
                description = '{$safeDesc}',
                hsn_code = '{$safeHsn}',
                rate = {$pItem['rate']},
                gst_percent = {$pItem['gst_percent']},
                discount_type = '{$pItem['discount_type']}',
                discount_value = {$pItem['discount_value']},
                discount_amount = {$pItem['discount_amount']},
                qty = {$pItem['qty']},
                net_amount = {$pItem['net_amount']},
                tax_amount = {$pItem['tax_amount']},
                total_amount = {$pItem['total_amount']}";
            mysqli_query($ai_conn, $itemQry);

            // Add stock for updated items
            if ($pItem['product_id'] > 0 && function_exists('addProductStock')) {
                addProductStock($pItem['product_id'], $pItem['qty']);
            }
        }
    }

    if (function_exists('recalculatePartyOutstanding')) {
        if ($party_id > 0) recalculatePartyOutstanding($party_id);
        if ($prevPartyId > 0 && $prevPartyId !== $party_id) recalculatePartyOutstanding($prevPartyId);
    }

    $printUrl = rtrim(ADMIN_URL, '/') . '/purchase-print.php?id=' . $purchaseId;

    apiResponse(200, 'Purchase bill updated successfully.', [
        'id'                      => $purchaseId,
        'purchase_no'             => $purchase_no,
        'company_id'              => $company_id,
        'party_id'                => $party_id,
        'party_name'              => $party_name,
        'gst_no'                  => $gst_no,
        'purchase_date'           => $purchase_date,
        'purchase_date_formatted' => date('d-m-Y', strtotime($purchase_date)),
        'due_date'                => $due_date,
        'due_date_formatted'      => date('d-m-Y', strtotime($due_date)),
        'taxable_amount'          => round($totalTaxable, 2),
        'taxable_formatted'       => '₹' . number_format($totalTaxable, 2),
        'cgst_amount'             => round($cgst_amount, 2),
        'sgst_amount'             => round($sgst_amount, 2),
        'igst_amount'             => round($igst_amount, 2),
        'tax_amount'              => round($totalTax, 2),
        'tax_amount_formatted'    => '₹' . number_format($totalTax, 2),
        'grand_total'             => round($grand_total, 2),
        'grand_total_formatted'   => '₹' . number_format($grand_total, 2),
        'paid_amount'             => round($paid_amount, 2),
        'paid_amount_formatted'   => '₹' . number_format($paid_amount, 2),
        'payment_status'          => $payment_status,
        'status'                  => $status,
        'items_count'             => ($rawItems !== null) ? count($processedItems) : count($ai_db->aiGetQueryObj("SELECT id FROM tbl_purchase_items WHERE purchase_id = {$purchaseId}")),
        'print_url'               => $printUrl,
        'download_url'            => $printUrl . '&download=1'
    ]);
}

if ($action === 'purchase-delete') {
    if ($method !== 'DELETE' && $method !== 'POST') {
        apiResponse(405, 'Method not allowed. Use DELETE or POST.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];
    $purchaseId = (int)($input['id'] ?? ($input['purchase_id'] ?? ($_GET['id'] ?? ($_GET['purchase_id'] ?? 0))));

    if ($purchaseId <= 0) {
        apiResponse(400, 'Purchase ID is required.');
    }

    $existing = $ai_db->aiGetQueryObj("SELECT id, purchase_no, party_id, payment_status, paid_amount FROM tbl_purchase WHERE id = {$purchaseId} AND company_id = {$company_id} LIMIT 1");
    if (empty($existing)) {
        apiResponse(404, 'Purchase bill not found.');
    }

    $currPur = $existing[0];
    $partyId = (int)($currPur->party_id ?? 0);
    $purNo = $currPur->purchase_no ?? "#{$purchaseId}";

    // Revert added product stock before deleting purchase items
    if (function_exists('restorePurchaseProductStock')) {
        restorePurchaseProductStock($purchaseId);
    }

    // Delete purchase, items, and payments
    $delQry = "DELETE FROM tbl_purchase WHERE id = {$purchaseId} AND company_id = {$company_id}";
    $res = mysqli_query($ai_conn, $delQry);

    if (!$res) {
        apiResponse(500, 'Failed to delete purchase bill: ' . mysqli_error($ai_conn));
    }

    mysqli_query($ai_conn, "DELETE FROM tbl_purchase_items WHERE purchase_id = {$purchaseId}");
    mysqli_query($ai_conn, "DELETE FROM tbl_purchase_payments WHERE purchase_id = {$purchaseId}");

    // Recalculate party outstanding
    if ($partyId > 0 && function_exists('recalculatePartyOutstanding')) {
        recalculatePartyOutstanding($partyId);
    }

    apiResponse(200, 'Purchase bill deleted successfully.', [
        'id'           => $purchaseId,
        'purchase_no'  => $purNo,
        'deleted'      => true
    ]);
}

/* =========================================================================
   LEDGER API (action=ledger)
   ========================================================================= */

if ($action === 'ledger') {
    if ($method !== 'GET' && $method !== 'POST') {
        apiResponse(405, 'Method not allowed. Use GET or POST.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];

    $partyId = (int)($input['party_id'] ?? ($_GET['party_id'] ?? 0));
    if ($partyId <= 0) {
        apiResponse(400, 'Party ID is required.');
    }

    $pRow = $ai_db->aiGetQueryObj("SELECT p.*, c.company_name, s.state_name, ct.city_name 
        FROM tbl_party p 
        LEFT JOIN tbl_company c ON p.company_id = c.id 
        LEFT JOIN tbl_state s ON p.state_id = s.id 
        LEFT JOIN tbl_city ct ON p.city_id = ct.id 
        WHERE p.id = {$partyId} AND p.company_id = {$company_id} 
        LIMIT 1");

    if (empty($pRow)) {
        apiResponse(404, 'Party not found for your company.');
    }

    $party = $pRow[0];
    $partyStatus = $party->party_status ?? 'Sales';
    $openingBalance = floatval($party->opening_balance ?? 0);
    $openingType = $party->balance_type ?? 'Debit';

    $startDate = trim($input['start_date'] ?? ($_GET['start_date'] ?? ''));
    $endDate = trim($input['end_date'] ?? ($_GET['end_date'] ?? ''));

    // Date filters sanitization
    $dateWhereInvoice = "";
    $dateWherePayment = "";
    $dateWherePurchase = "";

    if (!empty($startDate)) {
        $sDate = date('Y-m-d', strtotime($startDate));
        $dateWhereInvoice .= " AND invoice_date >= '{$sDate}'";
        $dateWherePayment .= " AND payment_date >= '{$sDate}'";
        $dateWherePurchase .= " AND purchase_date >= '{$sDate}'";
    }
    if (!empty($endDate)) {
        $eDate = date('Y-m-d', strtotime($endDate));
        $dateWhereInvoice .= " AND invoice_date <= '{$eDate}'";
        $dateWherePayment .= " AND payment_date <= '{$eDate}'";
        $dateWherePurchase .= " AND purchase_date <= '{$eDate}'";
    }

    $rawList = [];
    $totalDebit = 0.00;
    $totalCredit = 0.00;

    if ($partyStatus === 'Purchase') {
        // Purchase Party: Opening Credit = (+ payable), Debit = (- advance)
        $currentSign = ($openingType === 'Credit') ? $openingBalance : -$openingBalance;

        // 1. Purchase Bills
        $purchases = $ai_db->aiGetQueryObj("SELECT id, purchase_no AS ref_no, purchase_date AS tx_date, 
            grand_total AS debit_or_credit, 'Purchase Bill' AS tx_type 
            FROM tbl_purchase 
            WHERE party_id = {$partyId} AND company_id = {$company_id} AND status = 'active' {$dateWherePurchase}");

        // 2. Purchase Payments
        $payments = $ai_db->aiGetQueryObj("SELECT id, reference_no AS ref_no, payment_date AS tx_date, 
            amount AS debit_or_credit, CONCAT('Payment (', payment_mode, ')') AS tx_type, notes 
            FROM tbl_purchase_payments 
            WHERE party_id = {$partyId} AND company_id = {$company_id} {$dateWherePayment}");

        if (!empty($purchases)) {
            foreach ($purchases as $pur) {
                $amt = floatval($pur->debit_or_credit);
                $rawList[] = [
                    'id'         => (int)$pur->id,
                    'date'       => $pur->tx_date,
                    'type'       => 'Purchase Bill',
                    'ref_no'     => $pur->ref_no ?? ('PUR-' . $pur->id),
                    'debit'      => 0.00,
                    'credit'     => $amt,
                    'notes'      => 'Bill Total',
                    'sort_order' => 1
                ];
            }
        }

        if (!empty($payments)) {
            foreach ($payments as $pay) {
                $amt = floatval($pay->debit_or_credit);
                $rawList[] = [
                    'id'         => (int)$pay->id,
                    'date'       => $pay->tx_date,
                    'type'       => $pay->tx_type,
                    'ref_no'     => !empty($pay->ref_no) ? $pay->ref_no : ('PAY-' . $pay->id),
                    'debit'      => $amt,
                    'credit'     => 0.00,
                    'notes'      => $pay->notes ?? 'Paid to Supplier',
                    'sort_order' => 2
                ];
            }
        }

        usort($rawList, function($a, $b) {
            $t1 = strtotime($a['date']);
            $t2 = strtotime($b['date']);
            if ($t1 === $t2) {
                return $a['sort_order'] - $b['sort_order'];
            }
            return $t1 - $t2;
        });

        $running = $currentSign;
        $transactions = [];
        foreach ($rawList as $item) {
            $deb = $item['debit'];
            $crd = $item['credit'];
            $totalDebit += $deb;
            $totalCredit += $crd;

            // In Purchase: Credit increases payable (+), Debit payment decreases payable (-)
            $running += ($crd - $deb);
            $balType = ($running >= 0) ? 'Credit' : 'Debit';
            $balVal = abs($running);

            $transactions[] = [
                'id'                => $item['id'],
                'date'              => $item['date'],
                'date_formatted'    => !empty($item['date']) ? date('d-m-Y', strtotime($item['date'])) : '',
                'type'              => $item['type'],
                'ref_no'            => $item['ref_no'],
                'debit'             => $deb,
                'debit_formatted'   => '₹' . number_format($deb, 2),
                'credit'            => $crd,
                'credit_formatted'  => '₹' . number_format($crd, 2),
                'notes'             => $item['notes'],
                'balance'           => round($balVal, 2),
                'balance_type'      => $balType,
                'balance_formatted' => '₹' . number_format($balVal, 2) . ' ' . ($balType === 'Credit' ? 'Cr' : 'Dr')
            ];
        }

        $finalBalance = abs($running);
        $finalType = ($running >= 0) ? 'Credit' : 'Debit';

    } else {
        // Sales Party: Opening Debit = (+ receivable), Credit = (- advance)
        $currentSign = ($openingType === 'Debit') ? $openingBalance : -$openingBalance;

        // 1. Invoices (Debit to customer)
        $invoices = $ai_db->aiGetQueryObj("SELECT id, invoice_no AS ref_no, invoice_date AS tx_date, 
            grand_total AS amount, 'Invoice' AS tx_type 
            FROM tbl_invoice 
            WHERE party_id = {$partyId} AND company_id = {$company_id} AND status = 'active' {$dateWhereInvoice}");

        // 2. Payments (Credit / payment received from customer)
        $payments = $ai_db->aiGetQueryObj("SELECT id, reference_no AS ref_no, payment_date AS tx_date, 
            amount, CONCAT('Receipt (', payment_mode, ')') AS tx_type, notes 
            FROM tbl_invoice_payments 
            WHERE party_id = {$partyId} AND company_id = {$company_id} {$dateWherePayment}");

        if (!empty($invoices)) {
            foreach ($invoices as $inv) {
                $amt = floatval($inv->amount);
                $rawList[] = [
                    'id'         => (int)$inv->id,
                    'date'       => $inv->tx_date,
                    'type'       => 'Invoice',
                    'ref_no'     => $inv->ref_no ?? ('INV-' . $inv->id),
                    'debit'      => $amt,
                    'credit'     => 0.00,
                    'notes'      => 'Invoice Generated',
                    'sort_order' => 1
                ];
            }
        }

        if (!empty($payments)) {
            foreach ($payments as $pay) {
                $amt = floatval($pay->amount);
                $rawList[] = [
                    'id'         => (int)$pay->id,
                    'date'       => $pay->tx_date,
                    'type'       => $pay->tx_type,
                    'ref_no'     => !empty($pay->ref_no) ? $pay->ref_no : ('REC-' . $pay->id),
                    'debit'      => 0.00,
                    'credit'     => $amt,
                    'notes'      => $pay->notes ?? 'Payment Received',
                    'sort_order' => 2
                ];
            }
        }

        usort($rawList, function($a, $b) {
            $t1 = strtotime($a['date']);
            $t2 = strtotime($b['date']);
            if ($t1 === $t2) {
                return $a['sort_order'] - $b['sort_order'];
            }
            return $t1 - $t2;
        });

        $running = $currentSign;
        $transactions = [];
        foreach ($rawList as $item) {
            $deb = $item['debit'];
            $crd = $item['credit'];
            $totalDebit += $deb;
            $totalCredit += $crd;

            // In Sales: Debit invoice increases receivable (+), Credit payment decreases receivable (-)
            $running += ($deb - $crd);
            $balType = ($running >= 0) ? 'Debit' : 'Credit';
            $balVal = abs($running);

            $transactions[] = [
                'id'                => $item['id'],
                'date'              => $item['date'],
                'date_formatted'    => !empty($item['date']) ? date('d-m-Y', strtotime($item['date'])) : '',
                'type'              => $item['type'],
                'ref_no'            => $item['ref_no'],
                'debit'             => $deb,
                'debit_formatted'   => '₹' . number_format($deb, 2),
                'credit'            => $crd,
                'credit_formatted'  => '₹' . number_format($crd, 2),
                'notes'             => $item['notes'],
                'balance'           => round($balVal, 2),
                'balance_type'      => $balType,
                'balance_formatted' => '₹' . number_format($balVal, 2) . ' ' . ($balType === 'Debit' ? 'Dr' : 'Cr')
            ];
        }

        $finalBalance = abs($running);
        $finalType = ($running >= 0) ? 'Debit' : 'Credit';
    }

    $printUrl = rtrim(ADMIN_URL, '/') . '/ledger-print.php?party_id=' . $partyId;
    if (!empty($startDate)) $printUrl .= '&start_date=' . urlencode($startDate);
    if (!empty($endDate)) $printUrl .= '&end_date=' . urlencode($endDate);

    apiResponse(200, 'Party ledger retrieved successfully.', [
        'company_id'      => $company_id,
        'company_name'    => $company['company_name'] ?? '',
        'party'           => [
            'id'                        => (int)$party->id,
            'party_name'                => $party->party_name ?? '',
            'party_status'              => $partyStatus,
            'mobile_no'                 => $party->mobile_no ?? '',
            'email'                     => $party->email ?? '',
            'gst_no'                    => $party->gst_no ?? '',
            'pan_no'                    => $party->pan_no ?? '',
            'address'                   => $party->address ?? '',
            'state_id'                  => (int)($party->state_id ?? 0),
            'state_name'                => $party->state_name ?? '',
            'city_id'                   => (int)($party->city_id ?? 0),
            'city_name'                 => $party->city_name ?? '',
            'pincode'                   => $party->pincode ?? '',
            'opening_balance'           => $openingBalance,
            'opening_balance_type'      => $openingType,
            'opening_balance_formatted' => '₹' . number_format($openingBalance, 2) . ' ' . ($openingType === 'Debit' ? 'Dr' : 'Cr')
        ],
        'date_filter'     => [
            'start_date' => !empty($startDate) ? $startDate : null,
            'end_date'   => !empty($endDate) ? $endDate : null
        ],
        'transactions'    => $transactions,
        'summary'         => [
            'total_transactions'     => count($transactions),
            'total_debit'            => round($totalDebit, 2),
            'total_debit_formatted'  => '₹' . number_format($totalDebit, 2),
            'total_credit'           => round($totalCredit, 2),
            'total_credit_formatted' => '₹' . number_format($totalCredit, 2),
            'closing_balance'        => round($finalBalance, 2),
            'closing_balance_type'   => $finalType,
            'closing_balance_formatted' => '₹' . number_format($finalBalance, 2) . ' ' . ($finalType === 'Debit' ? 'Dr' : 'Cr')
        ],
        'print_url'       => $printUrl,
        'download_url'    => $printUrl . '&download=1'
    ]);
}

/* =========================================================================
   DASHBOARD API (action=dashboard)
   ========================================================================= */

if ($action === 'dashboard') {
    if ($method !== 'GET' && $method !== 'POST') {
        apiResponse(405, 'Method not allowed. Use GET or POST.');
    }

    $input = getApiInput();
    $company = authenticateCompany($input);
    $company_id = (int)$company['id'];


    // 1. Overall Summary / Metric Cards
    // Total Sales & Payment Received
    $salesRow = $ai_db->aiGetQueryObj("SELECT 
        COALESCE(SUM(grand_total), 0) AS total_sales,
        COALESCE(SUM(paid_amount), 0) AS total_sales_received,
        COALESCE(SUM(cgst_amount), 0) AS total_sales_cgst,
        COALESCE(SUM(sgst_amount), 0) AS total_sales_sgst,
        COUNT(id) AS total_invoices
        FROM tbl_invoice 
        WHERE status = 'active' AND company_id = {$company_id}");

    $totalSales         = !empty($salesRow) ? floatval($salesRow[0]->total_sales) : 0.00;
    $totalSalesReceived = !empty($salesRow) ? floatval($salesRow[0]->total_sales_received) : 0.00;
    $totalSalesPending  = max(0, $totalSales - $totalSalesReceived);
    $totalInvoices      = !empty($salesRow) ? (int)$salesRow[0]->total_invoices : 0;

    // Total Purchase & Payment Payable
    $purRow = $ai_db->aiGetQueryObj("SELECT 
        COALESCE(SUM(grand_total), 0) AS total_purchase,
        COALESCE(SUM(paid_amount), 0) AS total_purchase_paid,
        COALESCE(SUM(cgst_amount), 0) AS total_pur_cgst,
        COALESCE(SUM(sgst_amount), 0) AS total_pur_sgst,
        COUNT(id) AS total_purchases
        FROM tbl_purchase 
        WHERE status = 'active' AND company_id = {$company_id}");

    $totalPurchase        = !empty($purRow) ? floatval($purRow[0]->total_purchase) : 0.00;
    $totalPurchasePaid    = !empty($purRow) ? floatval($purRow[0]->total_purchase_paid) : 0.00;
    $totalPurchasePayable = max(0, $totalPurchase - $totalPurchasePaid);
    $totalPurchases       = !empty($purRow) ? (int)$purRow[0]->total_purchases : 0;

    // 2. Customer Counts (Current Year customers for tbl_party where party_status = 'Sales' or general party)
    $currentYear = date('Y');
    $custRow = $ai_db->aiGetQueryObj("SELECT 
        SUM(CASE WHEN YEAR(created_at) = '{$currentYear}' THEN 1 ELSE 0 END) AS current_year_customers
        FROM tbl_party 
        WHERE company_id = {$company_id} AND (party_status = 'Sales' OR party_status IS NULL OR party_status = '')");

    $currentYearCustomers = !empty($custRow) ? (int)$custRow[0]->current_year_customers : 0;

    // Suppliers Count (tbl_party where party_status = 'Purchase')
    $suppRow = $ai_db->aiGetQueryObj("SELECT 
        COUNT(id) AS total_suppliers,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_suppliers
        FROM tbl_party 
        WHERE company_id = {$company_id} AND party_status = 'Purchase'");

    $totalSuppliers  = !empty($suppRow) ? (int)$suppRow[0]->total_suppliers : 0;
    $activeSuppliers = !empty($suppRow) ? (int)$suppRow[0]->active_suppliers : 0;

    // Total Parties Count (all parties of this company)
    $partyTotalRow = $ai_db->aiGetQueryObj("SELECT COUNT(id) AS total_parties FROM tbl_party WHERE company_id = {$company_id}");
    $totalParties = !empty($partyTotalRow) ? (int)$partyTotalRow[0]->total_parties : 0;

    // 3. Product Counts & Low Stock Metrics
    $prodRow = $ai_db->aiGetQueryObj("SELECT 
        COUNT(id) AS total_products,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_products,
        SUM(CASE WHEN (track_stock = 1 OR track_stock IS NULL) AND stock <= min_stock_alert THEN 1 ELSE 0 END) AS restock_needed_products,
        SUM(CASE WHEN (track_stock = 1 OR track_stock IS NULL) AND stock > 0 AND stock <= min_stock_alert THEN 1 ELSE 0 END) AS low_stock_products,
        SUM(CASE WHEN (track_stock = 1 OR track_stock IS NULL) AND stock <= 0 THEN 1 ELSE 0 END) AS out_of_stock_products,
        COALESCE(SUM(stock), 0) AS total_stock_quantity
        FROM tbl_product 
        WHERE company_id = {$company_id}");

    $totalProducts         = !empty($prodRow) ? (int)$prodRow[0]->total_products : 0;
    $activeProducts        = !empty($prodRow) ? (int)$prodRow[0]->active_products : 0;
    $lowStockProducts      = !empty($prodRow) ? (int)$prodRow[0]->low_stock_products : 0;
    $outOfStockProducts    = !empty($prodRow) ? (int)$prodRow[0]->out_of_stock_products : 0;
    $restockNeededProducts = !empty($prodRow) ? (int)$prodRow[0]->restock_needed_products : 0;
    $totalStockQuantity    = !empty($prodRow) ? floatval($prodRow[0]->total_stock_quantity) : 0.00;

    // 4. Sales & Purchase Outstanding Section (Filtered by sales/purchase dates or today's date by default)
    $todayDate = date('Y-m-d');

    // Sales specific date filter
    $salesStartDateRaw = trim($input['sales_start_date'] ?? ($_GET['sales_start_date'] ?? ''));
    $salesEndDateRaw   = trim($input['sales_end_date'] ?? ($_GET['sales_end_date'] ?? ''));

    $filterSalesStartDate = !empty($salesStartDateRaw) ? date('Y-m-d', strtotime($salesStartDateRaw)) : $todayDate;
    $filterSalesEndDate   = !empty($salesEndDateRaw) ? date('Y-m-d', strtotime($salesEndDateRaw)) : $todayDate;

    // Purchase specific date filter
    $purStartDateRaw = trim($input['purchase_start_date'] ?? ($_GET['purchase_start_date'] ?? ''));
    $purEndDateRaw   = trim($input['purchase_end_date'] ?? ($_GET['purchase_end_date'] ?? ''));

    $filterPurStartDate = !empty($purStartDateRaw) ? date('Y-m-d', strtotime($purStartDateRaw)) : $todayDate;
    $filterPurEndDate   = !empty($purEndDateRaw) ? date('Y-m-d', strtotime($purEndDateRaw)) : $todayDate;

    // Sales Outstanding query
    $filteredInvRow = $ai_db->aiGetQueryObj("SELECT 
        COALESCE(SUM(grand_total), 0) AS total_payment,
        COALESCE(SUM(paid_amount), 0) AS total_received,
        COALESCE(SUM(cgst_amount), 0) AS total_cgst,
        COALESCE(SUM(sgst_amount), 0) AS total_sgst,
        COUNT(id) AS total_invoices
        FROM tbl_invoice 
        WHERE status = 'active' AND company_id = {$company_id} 
          AND invoice_date >= '{$filterSalesStartDate}' AND invoice_date <= '{$filterSalesEndDate}'");

    $sTotPayment  = !empty($filteredInvRow) ? floatval($filteredInvRow[0]->total_payment) : 0.00;
    $sTotReceived = !empty($filteredInvRow) ? floatval($filteredInvRow[0]->total_received) : 0.00;
    $sTotPending  = max(0, $sTotPayment - $sTotReceived);
    $sTotCgst     = !empty($filteredInvRow) ? floatval($filteredInvRow[0]->total_cgst) : 0.00;
    $sTotSgst     = !empty($filteredInvRow) ? floatval($filteredInvRow[0]->total_sgst) : 0.00;

    // Purchase Outstanding query
    $filteredPurRow = $ai_db->aiGetQueryObj("SELECT 
        COALESCE(SUM(grand_total), 0) AS total_payment,
        COALESCE(SUM(paid_amount), 0) AS total_payable,
        COALESCE(SUM(cgst_amount), 0) AS total_cgst,
        COALESCE(SUM(sgst_amount), 0) AS total_sgst,
        COUNT(id) AS total_purchases
        FROM tbl_purchase 
        WHERE status = 'active' AND company_id = {$company_id} 
          AND purchase_date >= '{$filterPurStartDate}' AND purchase_date <= '{$filterPurEndDate}'");

    $pTotPayment = !empty($filteredPurRow) ? floatval($filteredPurRow[0]->total_payment) : 0.00;
    $pTotPayable = !empty($filteredPurRow) ? floatval($filteredPurRow[0]->total_payable) : 0.00;
    $pTotPending = max(0, $pTotPayment - $pTotPayable);
    $pTotCgst    = !empty($filteredPurRow) ? floatval($filteredPurRow[0]->total_cgst) : 0.00;
    $pTotSgst    = !empty($filteredPurRow) ? floatval($filteredPurRow[0]->total_sgst) : 0.00;

    $salesOutstanding = [
        'title'                   => 'Sales Outstanding',
        'sub_title'               => 'Customer Overview',
        'from_date'               => $filterSalesStartDate,
        'to_date'                 => $filterSalesEndDate,
        'from_date_formatted'     => date('d/m/Y', strtotime($filterSalesStartDate)),
        'to_date_formatted'       => date('d/m/Y', strtotime($filterSalesEndDate)),
        'range_text'              => 'From Date :- ' . date('d/m/Y', strtotime($filterSalesStartDate)) . ' To Date :- ' . date('d/m/Y', strtotime($filterSalesEndDate)),
        'total_amount'            => round($sTotPayment, 2),
        'total_amount_formatted'  => '₹' . number_format($sTotPayment, 2),
        'total_received'          => round($sTotReceived, 2),
        'total_received_formatted'=> '₹' . number_format($sTotReceived, 2),
        'total_pending'           => round($sTotPending, 2),
        'total_pending_formatted' => '₹' . number_format($sTotPending, 2),
        'total_sgst'              => round($sTotSgst, 2),
        'total_sgst_formatted'    => '₹' . number_format($sTotSgst, 2),
        'total_cgst'              => round($sTotCgst, 2),
        'total_cgst_formatted'    => '₹' . number_format($sTotCgst, 2),
        'total_invoices'          => !empty($filteredInvRow) ? (int)$filteredInvRow[0]->total_invoices : 0
    ];

    $purchaseOutstanding = [
        'title'                   => 'Purchase Outstanding',
        'sub_title'               => 'Supplier Overview',
        'from_date'               => $filterPurStartDate,
        'to_date'                 => $filterPurEndDate,
        'from_date_formatted'     => date('d/m/Y', strtotime($filterPurStartDate)),
        'to_date_formatted'       => date('d/m/Y', strtotime($filterPurEndDate)),
        'range_text'              => 'From Date :- ' . date('d/m/Y', strtotime($filterPurStartDate)) . ' To Date :- ' . date('d/m/Y', strtotime($filterPurEndDate)),
        'total_bill'              => round($pTotPayment, 2),
        'total_bill_formatted'    => '₹' . number_format($pTotPayment, 2),
        'total_payable'           => round($pTotPayable, 2),
        'total_payable_formatted' => '₹' . number_format($pTotPayable, 2),
        'total_pending'           => round($pTotPending, 2),
        'total_pending_formatted' => '₹' . number_format($pTotPending, 2),
        'total_sgst'              => round($pTotSgst, 2),
        'total_sgst_formatted'    => '₹' . number_format($pTotSgst, 2),
        'total_cgst'              => round($pTotCgst, 2),
        'total_cgst_formatted'    => '₹' . number_format($pTotCgst, 2),
        'total_purchases'         => !empty($filteredPurRow) ? (int)$filteredPurRow[0]->total_purchases : 0
    ];

    apiResponse(200, 'Dashboard data retrieved successfully.', [
        'company_id'   => $company_id,
        'company_name' => $company['company_name'] ?? '',

        // Metrics (Overview)
        'metrics' => [
            'total_sales' => [
                'amount'           => round($totalSales, 2),
                'amount_formatted' => '₹' . number_format($totalSales, 2),
                'label'            => 'Total Sales',
                'sub_label'        => 'All Invoices'
            ],
            'total_purchase' => [
                'amount'           => round($totalPurchase, 2),
                'amount_formatted' => '₹' . number_format($totalPurchase, 2),
                'label'            => 'Total Purchase',
                'sub_label'        => 'All Bills'
            ],
            'payment_received' => [
                'amount'           => round($totalSalesReceived, 2),
                'amount_formatted' => '₹' . number_format($totalSalesReceived, 2),
                'label'            => 'Payment Received',
                'sub_label'        => 'From Customers'
            ],
            'payment_payable' => [
                'amount'           => round($totalPurchasePayable, 2),
                'amount_formatted' => '₹' . number_format($totalPurchasePayable, 2),
                'label'            => 'Payment Payable',
                'sub_label'        => 'To Suppliers'
            ],
            'sales_pending' => [
                'amount'           => round($totalSalesPending, 2),
                'amount_formatted' => '₹' . number_format($totalSalesPending, 2),
                'label'            => 'Sales Pending'
            ],
            'total_customers' => [
                'count' => $currentYearCustomers,
                'label' => '+12 month'
            ],
            'total_products' => [
                'count'                  => $totalProducts,
                'active_count'           => $activeProducts,
                'low_stock_count'        => $lowStockProducts,
                'out_of_stock_count'     => $outOfStockProducts,
                'restock_needed_count'   => $restockNeededProducts,
                'stock_quantity'         => round($totalStockQuantity, 2),
                'label'                  => 'Total Products'
            ]
        ],

        'sales_outstanding'    => $salesOutstanding,
        'purchase_outstanding' => $purchaseOutstanding
    ]);
}

apiResponse(404, 'Invalid API action.');






