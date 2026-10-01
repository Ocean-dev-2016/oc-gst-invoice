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


$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($action === 'login') {
    if ($method !== 'POST') {
        apiResponse(405, 'Only POST method is allowed.');
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $login = trim($input['username'] ?? ($input['login'] ?? ($input['email'] ?? '')));
    $password = $input['password'] ?? '';

    if ($login === '' || $password === '') {
        apiResponse(400, 'Username/Email and password are required.');
    }

    $loginUname = mysqli_real_escape_string($ai_conn, $login);
    $loginPassword = md5($password);

    // ===== Admin Login Query (username OR email) =====
    $qry = "SELECT * FROM " . DB_PREFIX . "admin WHERE username='" . $loginUname . "' OR email='" . $loginUname . "'";
    $row = $ai_db->aiGetQuery($qry);

    if (empty($row) || !is_array($row) || count($row) === 0) {
        apiResponse(401, 'Wrong Username OR Password!');
    }

    $admin = $row[0];

    if ($loginPassword !== $admin['password']) {
        apiResponse(401, 'Wrong Username OR Password!');
    }

    if ($admin['is_active'] != '1') {
        apiResponse(403, 'Your account is not active. Please contact admin for that.');
    }

    // Generate Bearer / API token
    $token = bin2hex(random_bytes(32));
    $safeToken = mysqli_real_escape_string($ai_conn, $token);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
    $adminId = (int)$admin['id'];

    mysqli_query($ai_conn, "INSERT INTO `" . DB_PREFIX . "api_tokens` (`user_id`, `user_type`, `token`, `expires_at`, `created_at`) 
        VALUES ({$adminId}, 'admin', '{$safeToken}', '{$expiresAt}', NOW())");

    unset($admin['password']);

    apiResponse(200, 'Login successful.', [
        'token_type' => 'Bearer',
        'token'      => $token,
        'expires_at' => $expiresAt,
        'user'       => $admin
    ]);
}

