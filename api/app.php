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
    $businessName = trim($input['business_name'] ?? ($input['company_name'] ?? ''));
    $email = trim($input['email'] ?? '');
    $mobileNo = trim($input['mobile_no'] ?? ($input['mobile'] ?? ''));
    $password = $input['password'] ?? '';

    // Validation
    if ($name === '') {
        apiResponse(400, 'Name is required.');
    }

    if ($businessName === '') {
        apiResponse(400, 'Business name is required.');
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
    $safeCompanyName = mysqli_real_escape_string($ai_conn, $businessName);
    $safeEmail = mysqli_real_escape_string($ai_conn, $email);
    $safeMobile = mysqli_real_escape_string($ai_conn, $mobileNo);
    $hashedPassword = md5($password);
    $apiToken = bin2hex(random_bytes(32));
    $safeToken = mysqli_real_escape_string($ai_conn, $apiToken);

    // Duplicate check in tbl_company
    $dupCheckQry = "SELECT id, company_name, email, mobile_no, username FROM tbl_company 
                    WHERE LOWER(company_name) = '" . strtolower($safeCompanyName) . "' 
                       OR LOWER(email) = '" . strtolower($safeEmail) . "' 
                       OR mobile_no = '" . $safeMobile . "' 
                       OR LOWER(username) = '" . strtolower($safeUsername) . "' 
                    LIMIT 1";

    $existing = $ai_db->aiGetQueryObj($dupCheckQry);
    if (!empty($existing)) {
        $row = $existing[0];
        if (strtolower($row->company_name) === strtolower($businessName)) {
            apiResponse(409, 'Business name is already registered.');
        } elseif (strtolower($row->username) === strtolower($name)) {
            apiResponse(409, 'Name/Username is already registered.');
        } elseif (strtolower($row->email) === strtolower($email)) {
            apiResponse(409, 'Email address is already registered.');
        } elseif ($row->mobile_no === $mobileNo) {
            apiResponse(409, 'Mobile number is already registered.');
        } else {
            apiResponse(409, 'Account already exists with provided details.');
        }
    }

    // Insert into tbl_company
    $insertQry = "INSERT INTO tbl_company (company_name, owner_name, username, email, mobile_no, password, status, created_at) 
                  VALUES ('{$safeCompanyName}', '{$safeUsername}', '{$safeUsername}', '{$safeEmail}', '{$safeMobile}', '{$hashedPassword}', 'active', NOW())";

    $result = mysqli_query($ai_conn, $insertQry);
    if (!$result) {
        apiResponse(500, 'Failed to register company. ' . mysqli_error($ai_conn));
    }

    $companyId = mysqli_insert_id($ai_conn);

    apiResponse(200, 'Registration successful.', [
        'id'            => $companyId,
        'company_name'  => $businessName,
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




