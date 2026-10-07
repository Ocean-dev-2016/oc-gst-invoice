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


// Ensure api_token exists in tbl_company
static $tokenColChecked = false;
if (!$tokenColChecked) {
    $colRes = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_company LIKE 'api_token'");
    if ($colRes && mysqli_num_rows($colRes) === 0) {
        @mysqli_query($ai_conn, "ALTER TABLE tbl_company ADD COLUMN `api_token` VARCHAR(255) NULL DEFAULT NULL AFTER `password`");
    }

    // Ensure missing columns in tbl_party exist (especially on live server)
    $partyEmailRes = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_party LIKE 'email'");
    if ($partyEmailRes && mysqli_num_rows($partyEmailRes) === 0) {
        @mysqli_query($ai_conn, "ALTER TABLE `tbl_party` ADD COLUMN `email` VARCHAR(150) NULL DEFAULT '' AFTER `mobile_no`");
    }
    $partyPinRes = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_party LIKE 'pincode'");
    if ($partyPinRes && mysqli_num_rows($partyPinRes) === 0) {
        @mysqli_query($ai_conn, "ALTER TABLE `tbl_party` ADD COLUMN `pincode` VARCHAR(20) NULL DEFAULT '' AFTER `city_id`");
    }
    $partyShipPinRes = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_party LIKE 'shipping_pincode'");
    if ($partyShipPinRes && mysqli_num_rows($partyShipPinRes) === 0) {
        @mysqli_query($ai_conn, "ALTER TABLE `tbl_party` ADD COLUMN `shipping_pincode` VARCHAR(20) NULL DEFAULT '' AFTER `pincode`");
    }
    $partyShipAddrRes = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_party LIKE 'shipping_address'");
    if ($partyShipAddrRes && mysqli_num_rows($partyShipAddrRes) === 0) {
        @mysqli_query($ai_conn, "ALTER TABLE `tbl_party` ADD COLUMN `shipping_address` TEXT NULL DEFAULT NULL AFTER `shipping_pincode`");
    }
    $partyShipStateRes = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_party LIKE 'shipping_state_id'");
    if ($partyShipStateRes && mysqli_num_rows($partyShipStateRes) === 0) {
        @mysqli_query($ai_conn, "ALTER TABLE `tbl_party` ADD COLUMN `shipping_state_id` INT(11) NOT NULL DEFAULT 0 AFTER `shipping_address`");
    }
    $partyShipCityRes = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_party LIKE 'shipping_city_id'");
    if ($partyShipCityRes && mysqli_num_rows($partyShipCityRes) === 0) {
        @mysqli_query($ai_conn, "ALTER TABLE `tbl_party` ADD COLUMN `shipping_city_id` INT(11) NOT NULL DEFAULT 0 AFTER `shipping_state_id`");
    }
    $partyPanRes = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_party LIKE 'pan_no'");
    if ($partyPanRes && mysqli_num_rows($partyPanRes) === 0) {
        @mysqli_query($ai_conn, "ALTER TABLE `tbl_party` ADD COLUMN `pan_no` VARCHAR(20) NULL DEFAULT '' AFTER `gst_no`");
    }
    $partyStatusRes = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_party LIKE 'party_status'");
    if ($partyStatusRes && mysqli_num_rows($partyStatusRes) === 0) {
        @mysqli_query($ai_conn, "ALTER TABLE `tbl_party` ADD COLUMN `party_status` VARCHAR(50) NULL DEFAULT 'Sales' AFTER `pan_no`");
    }
    $partyBTypeRes = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_party LIKE 'business_type'");
    if ($partyBTypeRes && mysqli_num_rows($partyBTypeRes) === 0) {
        @mysqli_query($ai_conn, "ALTER TABLE `tbl_party` ADD COLUMN `business_type` ENUM('Individual','Business') NOT NULL DEFAULT 'Business' AFTER `party_status`");
    }
    $partyOpBalRes = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_party LIKE 'opening_balance'");
    if ($partyOpBalRes && mysqli_num_rows($partyOpBalRes) === 0) {
        @mysqli_query($ai_conn, "ALTER TABLE `tbl_party` ADD COLUMN `opening_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `business_type`");
    }
    $partyBalTypeRes = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_party LIKE 'balance_type'");
    if ($partyBalTypeRes && mysqli_num_rows($partyBalTypeRes) === 0) {
        @mysqli_query($ai_conn, "ALTER TABLE `tbl_party` ADD COLUMN `balance_type` ENUM('Credit','Debit') NOT NULL DEFAULT 'Debit' AFTER `opening_balance`");
    }
    $partyCredLimRes = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_party LIKE 'credit_limit'");
    if ($partyCredLimRes && mysqli_num_rows($partyCredLimRes) === 0) {
        @mysqli_query($ai_conn, "ALTER TABLE `tbl_party` ADD COLUMN `credit_limit` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `balance_type`");
    }
    $partyOutRes = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_party LIKE 'outstanding'");
    if ($partyOutRes && mysqli_num_rows($partyOutRes) === 0) {
        @mysqli_query($ai_conn, "ALTER TABLE `tbl_party` ADD COLUMN `outstanding` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `credit_limit`");
    }
    $partyRemRes = mysqli_query($ai_conn, "SHOW COLUMNS FROM tbl_party LIKE 'remark'");
    if ($partyRemRes && mysqli_num_rows($partyRemRes) === 0) {
        @mysqli_query($ai_conn, "ALTER TABLE `tbl_party` ADD COLUMN `remark` TEXT NULL AFTER `outstanding`");
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

if ($action === 'logout') {
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
    // Also accept token from body or query params
    if (empty($token)) {
        $token = trim($input['api_token'] ?? ($input['token'] ?? ($_GET['api_token'] ?? ($_GET['token'] ?? ''))));
    }

    $companyId = (int)($input['company_id'] ?? ($input['id'] ?? ($_GET['company_id'] ?? ($_GET['id'] ?? 0))));

    if (empty($token) && $companyId <= 0) {
        apiResponse(400, 'Bearer token or company_id/api_token is required to logout.');
    }

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
        apiResponse(401, 'Invalid token or session already expired.');
    }

    $cId = (int)$company['id'];
    // Invalidate the token in database
    mysqli_query($ai_conn, "UPDATE tbl_company SET api_token=NULL WHERE id={$cId}");

    apiResponse(200, 'Logged out successfully.');
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
    $compSelect = "SELECT c.*, 
                          s.state_name, ct.city_name 
                   FROM tbl_company c 
                   LEFT JOIN tbl_state s ON c.state_id = s.id 
                   LEFT JOIN tbl_city ct ON c.city_id = ct.id ";
    if (!empty($token)) {
        $safeToken = mysqli_real_escape_string($ai_conn, $token);
        $res = $ai_db->aiGetQuery("{$compSelect} WHERE c.api_token='{$safeToken}' LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    } elseif ($companyId > 0) {
        $res = $ai_db->aiGetQuery("{$compSelect} WHERE c.id={$companyId} LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    }

    if (!$company) {
        apiResponse(401, 'Unauthorized or Company not found. Please provide valid Bearer token or company_id.');
    }

    $cId = (int)$company['id'];

    // ==========================================
    // ==========================================
    // 1. GET PROFILE
    // ==========================================
    if ($method === 'GET') {
        apiResponse(200, 'Profile retrieved successfully.', [
            'id'                   => (int)$company['id'],
            'company_name'         => $company['company_name'] ?? '',
            'owner_name'           => $company['owner_name'] ?? '',
            'mobile_no'            => $company['mobile_no'] ?? '',
            'email'                => $company['email'] ?? '',
            'username'             => $company['username'] ?? '',
            'gst_no'               => $company['gst_no'] ?? '',
            'state_id'             => (int)($company['state_id'] ?? 0),
            'state_name'           => $company['state_name'] ?? '',
            'city_id'              => (int)($company['city_id'] ?? 0),
            'city_name'            => $company['city_name'] ?? '',
            'address'              => $company['address'] ?? '',
            'status'               => $company['status'] ?? 'active'
        ]);
    }

    // ==========================================
    // 2. UPDATE PROFILE (POST / PUT)
    // ==========================================
    if ($method === 'POST' || $method === 'PUT') {
        $company_name       = trim($input['company_name'] ?? ($company['company_name'] ?? ''));
        $owner_name         = trim($input['owner_name'] ?? ($company['owner_name'] ?? ''));
        $mobile_no          = trim($input['mobile_no'] ?? ($company['mobile_no'] ?? ''));
        $email              = trim($input['email'] ?? ($company['email'] ?? ''));
        $username           = trim($input['username'] ?? ($company['username'] ?? ''));
        $gst_no             = trim($input['gst_no'] ?? ($company['gst_no'] ?? ''));
        $state_id           = isset($input['state_id']) ? (int)$input['state_id'] : (int)($company['state_id'] ?? 0);
        $city_id            = isset($input['city_id']) ? (int)$input['city_id'] : (int)($company['city_id'] ?? 0);
        $address            = trim($input['address'] ?? ($company['address'] ?? ''));

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
        $safeCompany       = mysqli_real_escape_string($ai_conn, $company_name);
        $safeOwner         = mysqli_real_escape_string($ai_conn, $owner_name);
        $safeMobile        = mysqli_real_escape_string($ai_conn, $mobile_no);
        $safeEmail         = mysqli_real_escape_string($ai_conn, $email);
        $safeUname         = mysqli_real_escape_string($ai_conn, $username);
        $safeGst           = mysqli_real_escape_string($ai_conn, $gst_no);
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
            } else {
                apiResponse(409, 'Another company with these details already exists.');
            }
        }

        // Update company profile
        $updateQry = "UPDATE tbl_company SET 
                      company_name       = '{$safeCompany}',
                      owner_name         = '{$safeOwner}',
                      mobile_no          = '{$safeMobile}',
                      email              = '{$safeEmail}',
                      username           = '{$safeUname}',
                      gst_no             = '{$safeGst}',
                      state_id           = {$state_id},
                      city_id            = {$city_id},
                      address            = '{$safeAddress}',
                      modified_at        = NOW() 
                      WHERE id = {$cId}";

        if (!mysqli_query($ai_conn, $updateQry)) {
            apiResponse(500, 'Failed to update profile: ' . mysqli_error($ai_conn));
        }

        // Fetch state and city names for updated response
        $stateRow        = $state_id > 0 ? $ai_db->aiGetQueryObj("SELECT state_name FROM tbl_state WHERE id={$state_id} LIMIT 1") : [];
        $cityRow         = $city_id > 0 ? $ai_db->aiGetQueryObj("SELECT city_name FROM tbl_city WHERE id={$city_id} LIMIT 1") : [];

        apiResponse(200, 'Profile updated successfully.', [
            'id'                   => $cId,
            'company_name'         => $company_name,
            'owner_name'           => $owner_name,
            'mobile_no'            => $mobile_no,
            'email'                => $email,
            'username'             => $username,
            'gst_no'               => $gst_no,
            'state_id'             => $state_id,
            'state_name'           => !empty($stateRow) ? $stateRow[0]->state_name : '',
            'city_id'              => $city_id,
            'city_name'            => !empty($cityRow) ? $cityRow[0]->city_name : '',
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

    // Optional Sort By (Default: Latest added)
    $sort = trim($input['sort'] ?? ($_GET['sort'] ?? 'latest'));
    $orderBy = "ORDER BY p.id DESC";
    if ($sort === 'oldest') {
        $orderBy = "ORDER BY p.id ASC";
    } elseif ($sort === 'name_asc') {
        $orderBy = "ORDER BY p.party_name ASC";
    } elseif ($sort === 'name_desc') {
        $orderBy = "ORDER BY p.party_name DESC";
    } elseif ($sort === 'outstanding_desc' || $sort === 'outstanding_high') {
        $orderBy = "ORDER BY p.outstanding DESC";
    } elseif ($sort === 'outstanding_asc' || $sort === 'outstanding_low') {
        $orderBy = "ORDER BY p.outstanding ASC";
    }

    // Fetch customers (parties) for this company only
    $partyQry = "SELECT p.id, p.company_id, p.party_name, p.address, 
                        p.state_id, s.state_name, 
                        p.city_id, ct.city_name, 
                        p.pincode, p.shipping_pincode, 
                        p.shipping_address, p.shipping_state_id, ss.state_name AS shipping_state_name,
                        p.shipping_city_id, sc.city_name AS shipping_city_name,
                        p.mobile_no, p.email, p.gst_no, p.pan_no, 
                        p.party_status, p.business_type, p.opening_balance, 
                        p.balance_type, p.credit_limit, p.outstanding, p.remark,
                        p.status, p.created_at
                 FROM tbl_party p
                 LEFT JOIN tbl_state s ON p.state_id = s.id
                 LEFT JOIN tbl_city ct ON p.city_id = ct.id
                 LEFT JOIN tbl_state ss ON p.shipping_state_id = ss.id
                 LEFT JOIN tbl_city sc ON p.shipping_city_id = sc.id
                 WHERE p.company_id = {$cId} {$statusCond} {$searchCond}
                 {$orderBy}";

    $parties = $ai_db->aiGetQueryObj($partyQry);

    // Compute Summary Card Metrics across all active customers of this company
    $summaryQry = "SELECT 
                        COUNT(id) AS total_customers,
                        COALESCE(SUM(CASE WHEN outstanding > 0 THEN outstanding ELSE 0 END), 0) AS total_receivable,
                        COUNT(CASE WHEN outstanding > 0 THEN 1 END) AS receivable_clients_count,
                        COALESCE(SUM(CASE WHEN credit_limit > 0 AND outstanding > credit_limit THEN (outstanding - credit_limit) ELSE 0 END), 0) AS total_overdue,
                        COUNT(CASE WHEN credit_limit > 0 AND outstanding > credit_limit THEN 1 END) AS overdue_clients_count
                   FROM tbl_party 
                   WHERE company_id = {$cId} AND status = 'active'";
    $summaryRes = $ai_db->aiGetQueryObj($summaryQry);
    $sumData = !empty($summaryRes) ? $summaryRes[0] : null;

    $totalCustomers = (int)($sumData->total_customers ?? count($parties ?? []));
    $receivableAmt = (float)($sumData->total_receivable ?? 0);
    $receivableClients = (int)($sumData->receivable_clients_count ?? 0);
    $overdueAmt = (float)($sumData->total_overdue ?? 0);
    $overdueClients = (int)($sumData->overdue_clients_count ?? 0);

    $list = [];
    if (!empty($parties)) {
        foreach ($parties as $pty) {
            $name = trim($pty->party_name ?? '');
            
            // Generate initials (e.g. "Raj" -> "R", "Nexus Enterprises" -> "NE")
            $words = preg_split('/\s+/', $name);
            $initials = '';
            if (count($words) >= 2) {
                $initials = strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
            } elseif (count($words) === 1 && mb_strlen($words[0]) > 0) {
                $initials = strtoupper(mb_substr($words[0], 0, 1));
            }

            $outstandingVal = (float)($pty->outstanding ?? 0);
            $creditLimitVal = (float)($pty->credit_limit ?? 0);
            $isOverdue = ($creditLimitVal > 0 && $outstandingVal > $creditLimitVal);

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
                'credit_limit'           => $creditLimitVal,
                'credit_limit_formatted' => '₹' . number_format($creditLimitVal, 2),
                'outstanding'            => $outstandingVal,
                'outstanding_formatted'  => '₹' . number_format($outstandingVal, 2),
                'is_overdue'             => $isOverdue,
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
            'overdue_amount'              => $overdueAmt,
            'overdue_amount_formatted'    => '₹' . number_format($overdueAmt, 2),
            'overdue_clients_count'       => $overdueClients,
            'overdue_sub_text'            => ($overdueAmt > 0) ? 'Needs follow-up' : 'All clear'
        ],
        // Top-level direct keys for backward compatibility
        'receivable'   => $receivableAmt,
        'overdue'      => $overdueAmt,
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

    // Authenticate Company (via Token OR company_id OR company_name)
    $company = null;
    $reqCompanyName = trim($input['company_name'] ?? '');
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
    } elseif (!empty($reqCompanyName)) {
        $safeCompName = mysqli_real_escape_string($ai_conn, $reqCompanyName);
        $res = $ai_db->aiGetQuery("SELECT * FROM tbl_company WHERE LOWER(company_name) = '" . strtolower($safeCompName) . "' LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    }

    if (!$company) {
        apiResponse(401, 'Unauthorized or Company not found. Please provide valid Bearer token, company_id or company_name.');
    }

    $company_id = (int)$company['id'];

    // Input fields mapping (exact match with Add Party Details form image)
    $party_name         = trim($input['customer_name'] ?? ($input['party_name'] ?? ''));
    $mobile_no          = trim($input['mobile_no'] ?? ($input['mobile'] ?? ''));
    $email              = trim($input['email'] ?? ($input['email_address'] ?? ''));
    $gst_no             = trim($input['gst_no'] ?? ($input['gst_in_no'] ?? ''));
    $pan_no             = strtoupper(trim($input['pan_no'] ?? ''));
    $party_status       = trim($input['party_status'] ?? 'Sales');
    $business_type      = in_array($input['business_type'] ?? '', ['Individual', 'Business']) ? $input['business_type'] : 'Business';
    $opening_balance    = (float)($input['opening_balance'] ?? 0);
    $balance_type       = in_array($input['balance_type'] ?? '', ['Credit', 'Debit']) ? $input['balance_type'] : 'Debit';
    $credit_limit       = (float)($input['credit_limit'] ?? 0);
    $outstanding        = (float)($input['outstanding'] ?? 0);
    $remark             = trim($input['remark'] ?? '');
    $status             = trim($input['status'] ?? 'active');

    // Billing details
    $address            = trim($input['billing_address'] ?? ($input['address'] ?? ''));
    $state_id           = (int)($input['billing_state_id'] ?? ($input['state_id'] ?? 0));
    $city_id            = (int)($input['billing_city_id'] ?? ($input['city_id'] ?? 0));
    $pincode            = trim($input['billing_pincode'] ?? ($input['pincode'] ?? ''));

    // Shipping details (Support same_as_billing flag)
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

    if ($shipping_pincode !== '' && !preg_match('/^[0-9]{6}$/', $shipping_pincode)) {
        apiResponse(400, 'Shipping Pincode must be exactly 6 digits.');
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
                  credit_limit      = {$credit_limit},
                  outstanding       = {$outstanding},
                  remark            = '{$safeRemark}',
                  status            = '{$safeStatus}',
                  created_at        = NOW()";

    $res = mysqli_query($ai_conn, $insertQry);
    if (!$res) {
        apiResponse(500, 'Failed to add party: ' . mysqli_error($ai_conn));
    }

    $newPartyId = mysqli_insert_id($ai_conn);

    // Fetch state and city names for response
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
        'credit_limit'        => $credit_limit,
        'outstanding'         => $outstanding,
        'remark'              => $remark,
        'status'              => $status
    ]);
}

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
    $reqCompanyName = trim($_GET['company_name'] ?? '');

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
    } elseif (!empty($reqCompanyName)) {
        $safeCompName = mysqli_real_escape_string($ai_conn, $reqCompanyName);
        $res = $ai_db->aiGetQuery("SELECT * FROM tbl_company WHERE LOWER(company_name) = '" . strtolower($safeCompName) . "' LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    }

    if (!$company) {
        apiResponse(401, 'Unauthorized or Company not found. Please provide valid Bearer token, company_id or company_name.');
    }

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
        'credit_limit'        => (float)($cust->credit_limit ?? 0),
        'outstanding'         => (float)($cust->outstanding ?? 0),
        'status'              => $cust->status ?? 'active',
        'remark'              => $cust->remark ?? '',

        // Billing Details
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

        // Shipping Details
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

    // Authenticate Company (via Token OR company_id OR company_name)
    $company = null;
    $reqCompanyName = trim($input['company_name'] ?? '');
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
    } elseif (!empty($reqCompanyName)) {
        $safeCompName = mysqli_real_escape_string($ai_conn, $reqCompanyName);
        $res = $ai_db->aiGetQuery("SELECT * FROM tbl_company WHERE LOWER(company_name) = '" . strtolower($safeCompName) . "' LIMIT 1");
        if (!empty($res)) {
            $company = $res[0];
        }
    }

    if (!$company) {
        apiResponse(401, 'Unauthorized or Company not found. Please provide valid Bearer token, company_id or company_name.');
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

    // Fields mapping (supports form aliases)
    $party_name   = trim($input['customer_name'] ?? ($input['party_name'] ?? ($currentCust->party_name ?? '')));
    $mobile_no          = trim($input['mobile_no'] ?? ($input['mobile'] ?? ($currentCust->mobile_no ?? '')));
    $email              = trim($input['email'] ?? ($input['email_address'] ?? ($currentCust->email ?? '')));
    $gst_no             = trim($input['gst_no'] ?? ($input['gst_in_no'] ?? ($currentCust->gst_no ?? '')));
    $pan_no             = strtoupper(trim($input['pan_no'] ?? ($currentCust->pan_no ?? '')));
    $party_status       = trim($input['party_status'] ?? ($currentCust->party_status ?? 'Sales'));
    $business_type      = in_array($input['business_type'] ?? ($currentCust->business_type ?? ''), ['Individual', 'Business']) ? ($input['business_type'] ?? ($currentCust->business_type ?? 'Business')) : 'Business';
    $opening_balance    = isset($input['opening_balance']) ? (float)$input['opening_balance'] : (float)($currentCust->opening_balance ?? 0);
    $balance_type       = in_array($input['balance_type'] ?? ($currentCust->balance_type ?? ''), ['Credit', 'Debit']) ? ($input['balance_type'] ?? ($currentCust->balance_type ?? 'Debit')) : 'Debit';
    $credit_limit       = isset($input['credit_limit']) ? (float)$input['credit_limit'] : (float)($currentCust->credit_limit ?? 0);
    $outstanding        = isset($input['outstanding']) ? (float)$input['outstanding'] : (float)($currentCust->outstanding ?? 0);
    $remark             = trim($input['remark'] ?? ($currentCust->remark ?? ''));
    $status             = trim($input['status'] ?? ($currentCust->status ?? 'active'));

    // Billing details
    $address            = trim($input['billing_address'] ?? ($input['address'] ?? ($currentCust->address ?? '')));
    $state_id           = isset($input['billing_state_id']) ? (int)$input['billing_state_id'] : (isset($input['state_id']) ? (int)$input['state_id'] : (int)($currentCust->state_id ?? 0));
    $city_id            = isset($input['billing_city_id']) ? (int)$input['billing_city_id'] : (isset($input['city_id']) ? (int)$input['city_id'] : (int)($currentCust->city_id ?? 0));
    $pincode            = trim($input['billing_pincode'] ?? ($input['pincode'] ?? ($currentCust->pincode ?? '')));

    // Shipping details (Support same_as_billing flag)
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

    if ($shipping_pincode !== '' && !preg_match('/^[0-9]{6}$/', $shipping_pincode)) {
        apiResponse(400, 'Shipping Pincode must be exactly 6 digits.');
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
                  credit_limit      = {$credit_limit},
                  outstanding       = {$outstanding},
                  remark            = '{$safeRemark}',
                  status            = '{$safeStatus}'
                  WHERE id = {$customerId} AND company_id = {$company_id}";

    $res = mysqli_query($ai_conn, $updateQry);
    if (!$res) {
        apiResponse(500, 'Failed to update customer: ' . mysqli_error($ai_conn));
    }

    // Fetch state and city names for response
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
        'credit_limit'        => $credit_limit,
        'outstanding'         => $outstanding,
        'remark'              => $remark,
        'status'              => $status
    ]);
}

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

if ($action === 'invoice-list') {
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

    $cId = (int)$company['id'];

    // Optional Payment status filter (default: all, or 'Pending', 'Paid', 'Overdue')
    $filterPayment = trim($input['payment_status'] ?? ($_GET['payment_status'] ?? ''));
    $payCond = "";
    if (in_array($filterPayment, ['Pending', 'Paid'], true)) {
        $payCond = " AND q.payment_status = '{$filterPayment}'";
    } elseif ($filterPayment === 'Overdue') {
        $todayStr = date('Y-m-d');
        $payCond = " AND q.payment_status = 'Pending' AND q.due_date IS NOT NULL AND q.due_date < '{$todayStr}'";
    }

    // Optional status filter (active/deactive/all)
    $filterStatus = trim($input['status'] ?? ($_GET['status'] ?? ''));
    $statusCond = "";
    if ($filterStatus === 'active' || $filterStatus === 'deactive') {
        $statusCond = " AND q.status = '{$filterStatus}'";
    }

    // Optional party_id filter
    $partyId = (int)($input['party_id'] ?? ($_GET['party_id'] ?? 0));
    $partyCond = "";
    if ($partyId > 0) {
        $partyCond = " AND q.party_id = {$partyId}";
    }

    // Optional Search filter (quotation_no, party_name, gst_no)
    $search = trim($input['search'] ?? ($_GET['search'] ?? ''));
    $searchCond = "";
    if ($search !== '') {
        $safeSearch = mysqli_real_escape_string($ai_conn, $search);
        $searchCond = " AND (q.quotation_no LIKE '%{$safeSearch}%' OR q.party_name LIKE '%{$safeSearch}%' OR q.gst_no LIKE '%{$safeSearch}%')";
    }

    // Optional Sort (latest, oldest, amount_high, amount_low, date_desc, date_asc)
    $sort = trim($input['sort'] ?? ($_GET['sort'] ?? 'latest'));
    $orderBy = "ORDER BY q.id DESC";
    if ($sort === 'oldest') {
        $orderBy = "ORDER BY q.id ASC";
    } elseif ($sort === 'amount_high') {
        $orderBy = "ORDER BY q.grand_total DESC";
    } elseif ($sort === 'amount_low') {
        $orderBy = "ORDER BY q.grand_total ASC";
    } elseif ($sort === 'date_desc') {
        $orderBy = "ORDER BY q.quotation_date DESC, q.id DESC";
    } elseif ($sort === 'date_asc') {
        $orderBy = "ORDER BY q.quotation_date ASC, q.id ASC";
    }

    // Fetch quotations for this company
    $quotationQry = "SELECT q.*, c.company_name, p.mobile_no AS party_mobile, p.email AS party_email
                     FROM tbl_quotation q
                     LEFT JOIN tbl_company c ON q.company_id = c.id
                     LEFT JOIN tbl_party p ON q.party_id = p.id
                     WHERE q.company_id = {$cId} {$payCond} {$statusCond} {$partyCond} {$searchCond}
                     {$orderBy}";
    $quotations = $ai_db->aiGetQueryObj($quotationQry);

    // Summary calculation for the company
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

    // Option to include items (default: false unless include_items=1 is passed)
    $includeItems = !empty($input['include_items']) || !empty($_GET['include_items']);

    $list = [];
    if (!empty($quotations)) {
        // Pre-fetch items if requested
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

            // Generate print/pdf URL for convenient client app consumption
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
                'is_locked'              => $isPaid, // Cannot change quotation status if paid
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

if ($action === 'invoice-add') {
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

    // Party Information
    $party_id = (int)($input['party_id'] ?? 0);
    $party_name = trim($input['party_name'] ?? '');
    $gst_no = trim($input['gst_no'] ?? '');
    $address = trim($input['address'] ?? '');
    $state_id = (int)($input['state_id'] ?? 0);
    $city_id = (int)($input['city_id'] ?? 0);
    $city_name = trim($input['city_name'] ?? '');

    // If party_id passed, auto fill party fields if not explicitly provided
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

    // State Name lookup
    $state_name = '';
    if ($state_id > 0) {
        $stObj = $ai_db->aiGetQueryObj("SELECT state_name FROM tbl_state WHERE id={$state_id} LIMIT 1");
        if (!empty($stObj)) {
            $state_name = $stObj[0]->state_name ?? '';
        }
    }

    // Auto Quotation Number if not provided
    $quotation_no = trim($input['quotation_no'] ?? '');
    if (empty($quotation_no)) {
        if (function_exists('generateCompanyQuotationNo')) {
            $quotation_no = generateCompanyQuotationNo($ai_db, $company_id);
        } else {
            $quotation_no = generateQuotationNo($ai_db, $company_id);
        }
    } else {
        // Check duplicate quotation_no for this company
        $safeQNo = mysqli_real_escape_string($ai_conn, $quotation_no);
        $checkDupQ = $ai_db->aiGetQueryObj("SELECT id FROM tbl_quotation WHERE quotation_no='{$safeQNo}' AND company_id={$company_id} LIMIT 1");
        if (!empty($checkDupQ)) {
            apiResponse(400, "Quotation Number '{$quotation_no}' already exists for this company.");
        }
    }

    $rca = (isset($input['rca']) && strtolower(trim($input['rca'])) === 'yes') ? 'Yes' : 'No';
    $quotation_date = !empty($input['quotation_date']) ? date('Y-m-d', strtotime($input['quotation_date'])) : date('Y-m-d');
    
    // Due Date default to quotation_date if empty
    $due_date = !empty($input['due_date']) ? date('Y-m-d', strtotime($input['due_date'])) : $quotation_date;
    if ($due_date < $quotation_date) {
        apiResponse(400, "Due Date cannot be earlier than Quotation Date ({$quotation_date}).");
    }

    $gst_type = (isset($input['gst_type']) && strtolower(trim($input['gst_type'])) === 'without_gst') ? 'without_gst' : 'with_gst';
    $payment_status = (isset($input['payment_status']) && strtolower(trim($input['payment_status'])) === 'paid') ? 'Paid' : 'Pending';
    $status = (isset($input['status']) && strtolower(trim($input['status'])) === 'deactive') ? 'deactive' : 'active';

    // Validate Items array
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

        // If product_id given but no desc/hsn, lookup product
        if ($prodId > 0 && (empty($desc) || empty($hsn))) {
            $pFind = $ai_db->aiGetQueryObj("SELECT product_name, hsn_code, sales_price, gst_rate FROM tbl_product WHERE id={$prodId} LIMIT 1");
            if (!empty($pFind)) {
                if (empty($desc)) $desc = $pFind[0]->product_name ?? '';
                if (empty($hsn)) $hsn = $pFind[0]->hsn_code ?? '';
            }
        }

        if (empty($desc)) {
            apiResponse(400, "Item at index {$idx} must have a valid description or product_id.");
        }

        $rate = floatval($it['rate'] ?? ($it['price'] ?? 0));
        $qty = floatval($it['qty'] ?? ($it['quantity'] ?? 1));
        if ($qty <= 0) $qty = 1;
        $gstPct = ($gst_type === 'with_gst') ? floatval($it['gst_percent'] ?? ($it['gst_pct'] ?? 18)) : 0.00;

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
        $itemTax = ($gst_type === 'with_gst') ? (($netAmt * $gstPct) / 100.00) : 0.00;
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

    // Determine GST splitting (CGST/SGST vs IGST)
    $isGujarat = false;
    if (!empty($state_name) && stripos($state_name, 'gujarat') !== false) {
        $isGujarat = true;
    } elseif ($state_id === 7) { // 7 is Gujarat state_id in tbl_state
        $isGujarat = true;
    }

    $cgst_amount = 0.00;
    $sgst_amount = 0.00;
    $igst_amount = 0.00;

    if ($gst_type === 'with_gst') {
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

    // Safe SQL Strings
    $safePartyName = mysqli_real_escape_string($ai_conn, $party_name);
    $safeGstNo = mysqli_real_escape_string($ai_conn, $gst_no);
    $safeQNo = mysqli_real_escape_string($ai_conn, $quotation_no);
    $safeAddress = mysqli_real_escape_string($ai_conn, $address);
    $safeStateName = mysqli_real_escape_string($ai_conn, $state_name);
    $safeCityName = mysqli_real_escape_string($ai_conn, $city_name);
    $dueSql = $due_date ? "'{$due_date}'" : "NULL";

    // Insert into tbl_quotation
    $insertQuotationQry = "INSERT INTO tbl_quotation SET
        company_id = {$company_id},
        party_id = {$party_id},
        party_name = '{$safePartyName}',
        gst_no = '{$safeGstNo}',
        quotation_no = '{$safeQNo}',
        rca = '{$rca}',
        address = '{$safeAddress}',
        state_id = {$state_id},
        state_name = '{$safeStateName}',
        city_id = {$city_id},
        city_name = '{$safeCityName}',
        quotation_date = '{$quotation_date}',
        due_date = {$dueSql},
        gst_type = '{$gst_type}',
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

    // Insert Items into tbl_quotation_items
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

    // Automatically recalculate Party Outstanding
    if ($party_id > 0 && function_exists('recalculatePartyOutstanding')) {
        recalculatePartyOutstanding($party_id);
    }

    // Generate print_url for easy preview
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
        'gst_type'               => $gst_type,
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

apiResponse(404, 'Invalid API action.');








