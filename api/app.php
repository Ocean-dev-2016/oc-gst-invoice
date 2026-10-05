<?php
/**
 * Single API Handler File (api/app.php)
 * Supports Bearer Token Authentication and Action-based Routing
 */

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


$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];


// Ensure api_token column exists in tbl_company
static $tokenColChecked = false;
if (!$tokenColChecked) {
    $colRes = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_company LIKE 'api_token'");
    if ($colRes && mysqli_num_rows($colRes) === 0) {
        @mysqli_query($ai_conn, "ALTER TABLE tbl_company ADD COLUMN `api_token` VARCHAR(255) NULL DEFAULT NULL AFTER `password`");
    }
    $tokenColChecked = true;
}

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

if ($action === 'change-password') {
    if ($method !== 'POST') {
        apiResponse(405, 'Only POST method is allowed.');
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    // Extract Bearer token from Authorization header if available
    $token = '';
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = trim($matches[1]);
    }
    // Also accept token from input body
    if (empty($token)) {
        $token = trim($input['api_token'] ?? ($input['token'] ?? ''));
    }

    $oldPassword = $input['old_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? ($input['new_password_confirmation'] ?? '');
    $companyId = (int)($input['company_id'] ?? ($input['id'] ?? 0));

    // Validation
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

    // Identify Company: via api_token OR company_id
    $company = null;
    if (!empty($token)) {
        $safeToken = mysqli_real_escape_string($ai_conn, $token);
        $res = $ai_db->aiGetQuery("SELECT * FROM tbl_company WHERE api_token='{$safeToken}' LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    } elseif ($companyId > 0) {
        $res = $ai_db->aiGetQuery("SELECT * FROM tbl_company WHERE id={$companyId} LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    }

    if (!$company) {
        apiResponse(401, 'Unauthorized or Company not found. Please provide valid Bearer token or company_id.');
    }

    // Check old password
    if (md5($oldPassword) !== $company['password']) {
        apiResponse(400, 'Old password is incorrect.');
    }

    // Check if new password is same as old
    if (md5($newPassword) === $company['password']) {
        apiResponse(400, 'New password cannot be the same as old password.');
    }

    // Update password
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
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    // Extract Bearer token from Authorization header if available
    $token = '';
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = trim($matches[1]);
    }
    // Also accept token / company_id from query parameters or input body
    if (empty($token)) {
        $token = trim($input['api_token'] ?? ($input['token'] ?? ($_GET['api_token'] ?? ($_GET['token'] ?? ''))));
    }
    $companyId = (int)($input['company_id'] ?? ($input['id'] ?? ($_GET['company_id'] ?? ($_GET['id'] ?? 0))));

    // Identify Company: via api_token OR company_id
    $company = null;
    if (!empty($token)) {
        $safeToken = mysqli_real_escape_string($ai_conn, $token);
        $res = $ai_db->aiGetQuery("SELECT c.*, s.state_name, ct.city_name 
                                   FROM tbl_company c 
                                   LEFT JOIN tbl_state s ON c.state_id = s.id 
                                   LEFT JOIN tbl_city ct ON c.city_id = ct.id 
                                   WHERE c.api_token='{$safeToken}' LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    } elseif ($companyId > 0) {
        $res = $ai_db->aiGetQuery("SELECT c.*, s.state_name, ct.city_name 
                                   FROM tbl_company c 
                                   LEFT JOIN tbl_state s ON c.state_id = s.id 
                                   LEFT JOIN tbl_city ct ON c.city_id = ct.id 
                                   WHERE c.id={$companyId} LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    }

    if (!$company) {
        apiResponse(401, 'Unauthorized or Company not found. Please provide valid Bearer token or company_id.');
    }

    $cId = (int)$company['id'];

    // ==========================================
    // 1. GET PROFILE
    // ==========================================
    if ($method === 'GET') {
        apiResponse(200, 'Profile retrieved successfully.', [
            'id'           => (int)$company['id'],
            'company_name' => $company['company_name'] ?? '',
            'owner_name'   => $company['owner_name'] ?? '',
            'mobile_no'    => $company['mobile_no'] ?? '',
            'email'        => $company['email'] ?? '',
            'username'     => $company['username'] ?? '',
            'gst_no'       => $company['gst_no'] ?? '',
            'state_id'     => (int)($company['state_id'] ?? 0),
            'city_id'      => (int)($company['city_id'] ?? 0),
            'address'      => $company['address'] ?? '',
            'status'       => $company['status'] ?? 'active'
        ]);
    }

    // ==========================================
    // 2. UPDATE PROFILE (POST / PUT)
    // ==========================================
    if ($method === 'POST' || $method === 'PUT') {
        $company_name = trim($input['company_name'] ?? ($company['company_name'] ?? ''));
        $owner_name   = trim($input['owner_name'] ?? ($company['owner_name'] ?? ''));
        $mobile_no    = trim($input['mobile_no'] ?? ($company['mobile_no'] ?? ''));
        $email        = trim($input['email'] ?? ($company['email'] ?? ''));
        $username     = trim($input['username'] ?? ($company['username'] ?? ''));
        $gst_no       = trim($input['gst_no'] ?? ($company['gst_no'] ?? ''));
        $state_id     = isset($input['state_id']) ? (int)$input['state_id'] : (int)($company['state_id'] ?? 0);
        $city_id      = isset($input['city_id']) ? (int)$input['city_id'] : (int)($company['city_id'] ?? 0);
        $address      = trim($input['address'] ?? ($company['address'] ?? ''));

        // Validation
        if ($company_name === '') {
            apiResponse(400, 'Business name is required.');
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

        // Duplicate checks (excluding current company)
        $safeCompany = mysqli_real_escape_string($ai_conn, $company_name);
        $safeOwner   = mysqli_real_escape_string($ai_conn, $owner_name);
        $safeMobile  = mysqli_real_escape_string($ai_conn, $mobile_no);
        $safeEmail   = mysqli_real_escape_string($ai_conn, $email);
        $safeUname   = mysqli_real_escape_string($ai_conn, $username);
        $safeGst     = mysqli_real_escape_string($ai_conn, $gst_no);
        $safeAddress = mysqli_real_escape_string($ai_conn, $address);

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
            } else {
                apiResponse(409, 'Another company with these details already exists.');
            }
        }

        // Update company profile
        $updateQry = "UPDATE tbl_company SET 
                      company_name = '{$safeCompany}',
                      owner_name   = '{$safeOwner}',
                      mobile_no    = '{$safeMobile}',
                      email        = '{$safeEmail}',
                      username     = '{$safeUname}',
                      gst_no       = '{$safeGst}',
                      state_id     = {$state_id},
                      city_id      = {$city_id},
                      address      = '{$safeAddress}',
                      modified_at  = NOW() 
                      WHERE id = {$cId}";

        if (!mysqli_query($ai_conn, $updateQry)) {
            apiResponse(500, 'Failed to update profile: ' . mysqli_error($ai_conn));
        }

        // Fetch state and city names for updated response
        $stateRow = $state_id > 0 ? $ai_db->aiGetQueryObj("SELECT state_name FROM tbl_state WHERE id={$state_id} LIMIT 1") : [];
        $cityRow  = $city_id > 0 ? $ai_db->aiGetQueryObj("SELECT city_name FROM tbl_city WHERE id={$city_id} LIMIT 1") : [];

        apiResponse(200, 'Profile updated successfully.', [
            'id'           => $cId,
            'company_name' => $company_name,
            'owner_name'   => $owner_name,
            'mobile_no'    => $mobile_no,
            'email'        => $email,
            'username'     => $username,
            'gst_no'       => $gst_no,
            'state_id'     => $state_id,
            'city_id'      => $city_id,
            'address'      => $address,
            'status'       => $company['status'] ?? 'active'
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

    // Verify if state exists
    $stateCheck = $ai_db->aiGetQueryObj("SELECT id, state_name FROM tbl_state WHERE id = {$stateId} LIMIT 1");
    if (empty($stateCheck)) {
        apiResponse(404, 'State not found.');
    }

    $stateInfo = $stateCheck[0];

    // Fetch active cities of this state
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

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    // Extract Bearer token from Authorization header if available
    $token = '';
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = trim($matches[1]);
    }
    // Also accept token / company_id from query parameters or body
    if (empty($token)) {
        $token = trim($input['api_token'] ?? ($input['token'] ?? ($_GET['api_token'] ?? ($_GET['token'] ?? ''))));
    }
    $companyId = (int)($input['company_id'] ?? ($input['id'] ?? ($_GET['company_id'] ?? ($_GET['id'] ?? 0))));

    // Authenticate Company
    $company = null;
    if (!empty($token)) {
        $safeToken = mysqli_real_escape_string($ai_conn, $token);
        $res = $ai_db->aiGetQuery("SELECT * FROM tbl_company WHERE api_token='{$safeToken}' LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    } elseif ($companyId > 0) {
        $res = $ai_db->aiGetQuery("SELECT * FROM tbl_company WHERE id={$companyId} LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    }

    if (!$company) {
        apiResponse(401, 'Unauthorized or Company not found. Please provide valid Bearer token or company_id.');
    }

    $cId = (int)$company['id'];

    // Optional status filter (default: active, or 'all')
    $filterStatus = trim($input['status'] ?? ($_GET['status'] ?? ''));
    $statusCond = "";
    if ($filterStatus === 'active' || $filterStatus === 'deactive') {
        $statusCond = " AND p.status = '{$filterStatus}'";
    } elseif ($filterStatus === 'all') {
        $statusCond = "";
    } else {
        $statusCond = " AND p.status = 'active'";
    }

    // Optional search filter
    $search = trim($input['search'] ?? ($_GET['search'] ?? ''));
    $searchCond = "";
    if ($search !== '') {
        $safeSearch = mysqli_real_escape_string($ai_conn, $search);
        $searchCond = " AND (p.party_name LIKE '%{$safeSearch}%' OR p.mobile_no LIKE '%{$safeSearch}%' OR p.email LIKE '%{$safeSearch}%' OR p.gst_no LIKE '%{$safeSearch}%')";
    }

    // Fetch customers (parties) for this company only
    $partyQry = "SELECT p.id, p.company_id, p.party_name, p.address, 
                        p.state_id, s.state_name, 
                        p.city_id, ct.city_name, 
                        p.pincode, p.mobile_no, p.email, p.gst_no, p.pan_no, 
                        p.party_status, p.status, p.created_at
                 FROM tbl_party p
                 LEFT JOIN tbl_state s ON p.state_id = s.id
                 LEFT JOIN tbl_city ct ON p.city_id = ct.id
                 WHERE p.company_id = {$cId} {$statusCond} {$searchCond}
                 ORDER BY p.id DESC";

    $parties = $ai_db->aiGetQueryObj($partyQry);

    $list = [];
    if (!empty($parties)) {
        foreach ($parties as $pty) {
            $list[] = [
                'id'           => (int)$pty->id,
                'company_id'   => (int)$pty->company_id,
                'customer_name'   => $pty->party_name ?? '',
                'mobile_no'    => $pty->mobile_no ?? '',
                'email'        => $pty->email ?? '',
                'gst_no'       => $pty->gst_no ?? '',
                'pan_no'       => $pty->pan_no ?? '',
                'address'      => $pty->address ?? '',
                'state_id'     => (int)($pty->state_id ?? 0),
                'state_name'   => $pty->state_name ?? '',
                'city_id'      => (int)($pty->city_id ?? 0),
                'city_name'    => $pty->city_name ?? '',
                'pincode'      => $pty->pincode ?? '',
                'party_status' => $pty->party_status ?? 'Sales',
                'status'       => $pty->status ?? 'active',
                'created_at'   => $pty->created_at ?? ''
            ];
        }
    }

    apiResponse(200, 'Customer list retrieved successfully.', [
        'company_id'   => $cId,
        'company_name' => $company['company_name'] ?? '',
        'total'        => count($list),
        'customers'    => $list
    ]);
}

if ($action === 'customer-add') {
    if ($method !== 'POST') {
        apiResponse(405, 'Only POST method is allowed.');
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    // Extract Bearer token from Authorization header if available
    $token = '';
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = trim($matches[1]);
    }
    if (empty($token)) {
        $token = trim($input['api_token'] ?? ($input['token'] ?? ($_GET['api_token'] ?? ($_GET['token'] ?? ''))));
    }
    $reqCompanyId = (int)($input['company_id'] ?? ($input['id'] ?? ($_GET['company_id'] ?? ($_GET['id'] ?? 0))));

    // Authenticate Company
    $company = null;
    if (!empty($token)) {
        $safeToken = mysqli_real_escape_string($ai_conn, $token);
        $res = $ai_db->aiGetQuery("SELECT * FROM tbl_company WHERE api_token='{$safeToken}' LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    } elseif ($reqCompanyId > 0) {
        $res = $ai_db->aiGetQuery("SELECT * FROM tbl_company WHERE id={$reqCompanyId} LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    }

    if (!$company) {
        apiResponse(401, 'Unauthorized or Company not found. Please provide valid Bearer token or company_id.');
    }

    $company_id = (int)$company['id'];

    // Input fields mapping (as seen in Party Details Form image)
    $party_name   = trim($input['customer_name'] ?? '');
    $address      = trim($input['address'] ?? '');
    $state_id     = (int)($input['state_id'] ?? 0);
    $city_id      = (int)($input['city_id'] ?? 0);
    $pincode      = trim($input['pincode'] ?? '');
    $mobile_no    = trim($input['mobile_no'] ?? ($input['mobile'] ?? ''));
    $email        = trim($input['email'] ?? ($input['email_address'] ?? ''));
    $gst_no       = trim($input['gst_no'] ?? ($input['gst_in_no'] ?? ''));
    $pan_no       = strtoupper(trim($input['pan_no'] ?? ''));
    $party_status = trim($input['party_status'] ?? 'Sales');
    $status       = trim($input['status'] ?? 'active');

    // Default status if not provided or valid
    if (!in_array(strtolower($status), ['active', 'deactive'])) {
        $status = 'active';
    }
    if (!in_array($party_status, ['Sales', 'Purchase'])) {
        $party_status = 'Sales';
    }

    // Required Field Validation (Image specifies Party Name * as required)
    if ($party_name === '') {
        apiResponse(400, 'Customer Name is required.');
    }

    // Optional field format validations
    if ($mobile_no !== '' && !preg_match('/^[0-9]{10}$/', $mobile_no)) {
        apiResponse(400, 'Mobile Number must be exactly 10 digits.');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        apiResponse(400, 'Please enter a valid Email Address.');
    }

    if ($pan_no !== '' && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan_no)) {
        apiResponse(400, 'PAN Number must be valid 10-character alphanumeric (e.g. ABCDE1234F).');
    }

    if ($pincode !== '' && !preg_match('/^[0-9]{6}$/', $pincode)) {
        apiResponse(400, 'Pincode must be exactly 6 digits.');
    }

    // Check State & City if provided
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

    // Duplicate Check under this company (Party Name unique per company)
    $safePartyName = mysqli_real_escape_string($ai_conn, $party_name);
    $checkDup = $ai_db->aiGetQueryObj("SELECT id FROM tbl_party WHERE company_id = {$company_id} AND LOWER(party_name) = '" . strtolower($safePartyName) . "' LIMIT 1");
    if (!empty($checkDup)) {
        apiResponse(409, "Customer Name '{$party_name}' already exists for your company.");
    }

    // Escape remaining fields
    $safeAddress     = mysqli_real_escape_string($ai_conn, $address);
    $safePincode     = mysqli_real_escape_string($ai_conn, $pincode);
    $safeMobileNo    = mysqli_real_escape_string($ai_conn, $mobile_no);
    $safeEmail       = mysqli_real_escape_string($ai_conn, $email);
    $safeGstNo       = mysqli_real_escape_string($ai_conn, $gst_no);
    $safePanNo       = mysqli_real_escape_string($ai_conn, $pan_no);
    $safePartyStatus = mysqli_real_escape_string($ai_conn, $party_status);
    $safeStatus      = mysqli_real_escape_string($ai_conn, $status);

    $insertQry = "INSERT INTO tbl_party SET 
                  company_id   = {$company_id},
                  party_name   = '{$safePartyName}',
                  address      = '{$safeAddress}',
                  state_id     = {$state_id},
                  city_id      = {$city_id},
                  pincode      = '{$safePincode}',
                  mobile_no    = '{$safeMobileNo}',
                  email        = '{$safeEmail}',
                  gst_no       = '{$safeGstNo}',
                  pan_no       = '{$safePanNo}',
                  party_status = '{$safePartyStatus}',
                  status       = '{$safeStatus}',
                  created_at   = NOW()";

    $res = mysqli_query($ai_conn, $insertQry);
    if (!$res) {
        apiResponse(500, 'Failed to add party: ' . mysqli_error($ai_conn));
    }

    $newPartyId = mysqli_insert_id($ai_conn);

    // Fetch state and city names for response
    $stateRow = $state_id > 0 ? $ai_db->aiGetQueryObj("SELECT state_name FROM tbl_state WHERE id = {$state_id} LIMIT 1") : [];
    $cityRow  = $city_id > 0 ? $ai_db->aiGetQueryObj("SELECT city_name FROM tbl_city WHERE id = {$city_id} LIMIT 1") : [];

    apiResponse(200, 'Customer added successfully.', [
        'id'           => $newPartyId,
        'company_id'   => $company_id,
        'company_name' => $company['company_name'] ?? '',
        'customer_name'=> $party_name,
        'address'      => $address,
        'state_id'     => $state_id,
        'state_name'   => !empty($stateRow) ? $stateRow[0]->state_name : '',
        'city_id'      => $city_id,
        'city_name'    => !empty($cityRow) ? $cityRow[0]->city_name : '',
        'pincode'      => $pincode,
        'mobile_no'    => $mobile_no,
        'email'        => $email,
        'gst_no'       => $gst_no,
        'pan_no'       => $pan_no,
        'party_status' => $party_status,
        'status'       => $status
    ]);
}

// ==========================================
// CUSTOMER EDIT / DETAIL (GET)
// ==========================================
if ($action === 'customer-edit') {
    if ($method !== 'GET') {
        apiResponse(405, 'Only GET method is allowed.');
    }

    // Extract Bearer token from Authorization header if available
    $token = '';
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = trim($matches[1]);
    }
    if (empty($token)) {
        $token = trim($_GET['api_token'] ?? ($_GET['token'] ?? ''));
    }
    $reqCompanyId = (int)($_GET['company_id'] ?? 0);

    // Authenticate Company
    $company = null;
    if (!empty($token)) {
        $safeToken = mysqli_real_escape_string($ai_conn, $token);
        $res = $ai_db->aiGetQuery("SELECT * FROM tbl_company WHERE api_token='{$safeToken}' LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    } elseif ($reqCompanyId > 0) {
        $res = $ai_db->aiGetQuery("SELECT * FROM tbl_company WHERE id={$reqCompanyId} LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    }

    if (!$company) {
        apiResponse(401, 'Unauthorized or Company not found. Please provide valid Bearer token or company_id.');
    }

    $company_id = (int)$company['id'];
    $customerId = (int)($_GET['id'] ?? ($_GET['customer_id'] ?? 0));

    if ($customerId <= 0) {
        apiResponse(400, 'Customer id is required.');
    }

    $customerQry = "SELECT p.*, s.state_name, ct.city_name 
                    FROM tbl_party p 
                    LEFT JOIN tbl_state s ON p.state_id = s.id 
                    LEFT JOIN tbl_city ct ON p.city_id = ct.id 
                    WHERE p.id = {$customerId} AND p.company_id = {$company_id} 
                    LIMIT 1";

    $customerRes = $ai_db->aiGetQueryObj($customerQry);
    if (empty($customerRes)) {
        apiResponse(404, 'Customer not found.');
    }

    $cust = $customerRes[0];

    apiResponse(200, 'Customer details retrieved successfully.', [
        'id'            => (int)$cust->id,
        'company_id'    => (int)$cust->company_id,
        'customer_name' => $cust->party_name ?? '',
        'address'       => $cust->address ?? '',
        'state_id'      => (int)($cust->state_id ?? 0),
        'state_name'    => $cust->state_name ?? '',
        'city_id'       => (int)($cust->city_id ?? 0),
        'city_name'     => $cust->city_name ?? '',
        'pincode'       => $cust->pincode ?? '',
        'mobile_no'     => $cust->mobile_no ?? '',
        'email'         => $cust->email ?? '',
        'gst_no'        => $cust->gst_no ?? '',
        'pan_no'        => $cust->pan_no ?? '',
        'party_status'  => $cust->party_status ?? 'Sales',
        'status'        => $cust->status ?? 'active',
        'created_at'    => $cust->created_at ?? ''
    ]);
}

// ==========================================
// CUSTOMER UPDATE (POST / PUT)
// ==========================================
if ($action === 'customer-update') {
    if ($method !== 'POST' && $method !== 'PUT') {
        apiResponse(405, 'Method not allowed. Use POST or PUT.');
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST;
    }
    // Extract Bearer token from Authorization header if available
    $token = '';
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = trim($matches[1]);
    }
    if (empty($token)) {
        $token = trim($input['api_token'] ?? ($input['token'] ?? ($_GET['api_token'] ?? ($_GET['token'] ?? ''))));
    }
    $reqCompanyId = (int)($input['company_id'] ?? ($input['id'] ?? ($_GET['company_id'] ?? ($_GET['id'] ?? 0))));

    // Authenticate Company
    $company = null;
    if (!empty($token)) {
        $safeToken = mysqli_real_escape_string($ai_conn, $token);
        $res = $ai_db->aiGetQuery("SELECT * FROM tbl_company WHERE api_token='{$safeToken}' LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    } elseif ($reqCompanyId > 0) {
        $res = $ai_db->aiGetQuery("SELECT * FROM tbl_company WHERE id={$reqCompanyId} LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    }

    if (!$company) {
        apiResponse(401, 'Unauthorized or Company not found. Please provide valid Bearer token or company_id.');
    }

    $company_id = (int)$company['id'];
    $customerId = (int)($input['id'] ?? ($input['customer_id'] ?? ($_GET['id'] ?? 0)));

    if ($customerId <= 0) {
        apiResponse(400, 'Customer id is required.');
    }

    // Check if customer exists and belongs to this company
    $existing = $ai_db->aiGetQueryObj("SELECT * FROM tbl_party WHERE id = {$customerId} AND company_id = {$company_id} LIMIT 1");
    if (empty($existing)) {
        apiResponse(404, 'Customer not found.');
    }
    $currentCust = $existing[0];

    // Fields mapping
    $party_name   = trim($input['customer_name'] ?? ($input['party_name'] ?? ($currentCust->party_name ?? '')));
    $address      = trim($input['address'] ?? ($currentCust->address ?? ''));
    $state_id     = isset($input['state_id']) ? (int)$input['state_id'] : (int)($currentCust->state_id ?? 0);
    $city_id      = isset($input['city_id']) ? (int)$input['city_id'] : (int)($currentCust->city_id ?? 0);
    $pincode      = trim($input['pincode'] ?? ($currentCust->pincode ?? ''));
    $mobile_no    = trim($input['mobile_no'] ?? ($input['mobile'] ?? ($currentCust->mobile_no ?? '')));
    $email        = trim($input['email'] ?? ($input['email_address'] ?? ($currentCust->email ?? '')));
    $gst_no       = trim($input['gst_no'] ?? ($input['gst_in_no'] ?? ($currentCust->gst_no ?? '')));
    $pan_no       = strtoupper(trim($input['pan_no'] ?? ($currentCust->pan_no ?? '')));
    $party_status = trim($input['party_status'] ?? ($currentCust->party_status ?? 'Sales'));
    $status       = trim($input['status'] ?? ($currentCust->status ?? 'active'));

    // Status validation
    if (!in_array(strtolower($status), ['active', 'deactive'])) {
        $status = $currentCust->status ?? 'active';
    }
    if (!in_array($party_status, ['Sales', 'Purchase'])) {
        $party_status = $currentCust->party_status ?? 'Sales';
    }

    // Required Field Validation
    if ($party_name === '') {
        apiResponse(400, 'Customer Name is required.');
    }

    // Format validations
    if ($mobile_no !== '' && !preg_match('/^[0-9]{10}$/', $mobile_no)) {
        apiResponse(400, 'Mobile Number must be exactly 10 digits.');
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        apiResponse(400, 'Please enter a valid Email Address.');
    }

    if ($pan_no !== '' && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan_no)) {
        apiResponse(400, 'PAN Number must be valid 10-character alphanumeric (e.g. ABCDE1234F).');
    }

    if ($pincode !== '' && !preg_match('/^[0-9]{6}$/', $pincode)) {
        apiResponse(400, 'Pincode must be exactly 6 digits.');
    }

    // Check State & City if provided
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

    // Duplicate Check excluding current customer ID
    $safePartyName = mysqli_real_escape_string($ai_conn, $party_name);
    $checkDup = $ai_db->aiGetQueryObj("SELECT id FROM tbl_party WHERE company_id = {$company_id} AND LOWER(party_name) = '" . strtolower($safePartyName) . "' AND id != {$customerId} LIMIT 1");
    if (!empty($checkDup)) {
        apiResponse(409, "Customer Name '{$party_name}' already exists for your company.");
    }

    // Escape values
    $safeAddress     = mysqli_real_escape_string($ai_conn, $address);
    $safePincode     = mysqli_real_escape_string($ai_conn, $pincode);
    $safeMobileNo    = mysqli_real_escape_string($ai_conn, $mobile_no);
    $safeEmail       = mysqli_real_escape_string($ai_conn, $email);
    $safeGstNo       = mysqli_real_escape_string($ai_conn, $gst_no);
    $safePanNo       = mysqli_real_escape_string($ai_conn, $pan_no);
    $safePartyStatus = mysqli_real_escape_string($ai_conn, $party_status);
    $safeStatus      = mysqli_real_escape_string($ai_conn, $status);

    $updateQry = "UPDATE tbl_party SET 
                  party_name   = '{$safePartyName}',
                  address      = '{$safeAddress}',
                  state_id     = {$state_id},
                  city_id      = {$city_id},
                  pincode      = '{$safePincode}',
                  mobile_no    = '{$safeMobileNo}',
                  email        = '{$safeEmail}',
                  gst_no       = '{$safeGstNo}',
                  pan_no       = '{$safePanNo}',
                  party_status = '{$safePartyStatus}',
                  status       = '{$safeStatus}'
                  WHERE id = {$customerId} AND company_id = {$company_id}";

    $res = mysqli_query($ai_conn, $updateQry);
    if (!$res) {
        apiResponse(500, 'Failed to update customer: ' . mysqli_error($ai_conn));
    }

    // Fetch state and city names for response
    $stateRow = $state_id > 0 ? $ai_db->aiGetQueryObj("SELECT state_name FROM tbl_state WHERE id = {$state_id} LIMIT 1") : [];
    $cityRow  = $city_id > 0 ? $ai_db->aiGetQueryObj("SELECT city_name FROM tbl_city WHERE id = {$city_id} LIMIT 1") : [];

    apiResponse(200, 'Customer updated successfully.', [
        'id'            => $customerId,
        'company_id'    => $company_id,
        'customer_name' => $party_name,
        'address'       => $address,
        'state_id'      => $state_id,
        'state_name'    => !empty($stateRow) ? $stateRow[0]->state_name : '',
        'city_id'       => $city_id,
        'city_name'     => !empty($cityRow) ? $cityRow[0]->city_name : '',
        'pincode'       => $pincode,
        'mobile_no'     => $mobile_no,
        'email'         => $email,
        'gst_no'        => $gst_no,
        'pan_no'        => $pan_no,
        'party_status'  => $party_status,
        'status'        => $status
    ]);
}

// ==========================================
// CUSTOMER DELETE (DELETE / POST)
// ==========================================
if ($action === 'customer-delete') {
    if ($method !== 'DELETE' && $method !== 'POST') {
        apiResponse(405, 'Method not allowed. Use DELETE or POST.');
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    // Extract Bearer token from Authorization header if available
    $token = '';
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = trim($matches[1]);
    }
    if (empty($token)) {
        $token = trim($input['api_token'] ?? ($input['token'] ?? ($_GET['api_token'] ?? ($_GET['token'] ?? ''))));
    }
    $reqCompanyId = (int)($input['company_id'] ?? ($input['id'] ?? ($_GET['company_id'] ?? ($_GET['id'] ?? 0))));

    // Authenticate Company
    $company = null;
    if (!empty($token)) {
        $safeToken = mysqli_real_escape_string($ai_conn, $token);
        $res = $ai_db->aiGetQuery("SELECT * FROM tbl_company WHERE api_token='{$safeToken}' LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    } elseif ($reqCompanyId > 0) {
        $res = $ai_db->aiGetQuery("SELECT * FROM tbl_company WHERE id={$reqCompanyId} LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    }

    if (!$company) {
        apiResponse(401, 'Unauthorized or Company not found. Please provide valid Bearer token or company_id.');
    }

    $company_id = (int)$company['id'];
    $customerId = (int)($input['id'] ?? ($input['customer_id'] ?? ($_GET['id'] ?? 0)));

    if ($customerId <= 0) {
        apiResponse(400, 'Customer id is required.');
    }

    // Check if customer exists and belongs to this company
    $existing = $ai_db->aiGetQueryObj("SELECT id, party_name FROM tbl_party WHERE id = {$customerId} AND company_id = {$company_id} LIMIT 1");
    if (empty($existing)) {
        apiResponse(404, 'Customer not found.');
    }

    $custName = $existing[0]->party_name;

    // Delete record from tbl_party
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

apiResponse(404, 'Invalid API action.');






