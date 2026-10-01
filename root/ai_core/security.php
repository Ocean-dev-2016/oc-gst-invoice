<?php
/*
 * Security helper: CSRF, input sanitization, prepared statements
 * Include after config.php (so $ai_conn is available).
 */

if (!defined('DB_PREFIX')) {
    return;
}

/**
 * Generate CSRF token and store in session
 */
function csrf_token() {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Output hidden input with CSRF token for forms
 */
function csrf_field() {
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Validate CSRF token from POST
 */
function csrf_validate() {
    $token = $_POST['_csrf_token'] ?? '';
    return !empty($token) && hash_equals($_SESSION['_csrf_token'] ?? '', $token);
}

/**
 * Generate simple math (sum) captcha to avoid spam bots.
 * Stores answer in session. Use same $key when validating.
 * Returns the question string, e.g. "What is 7 + 4?"
 */
function sum_captcha_generate($key = 'default') {
    $a = random_int(1, 15);
    $b = random_int(1, 15);
    if (!isset($_SESSION['_captcha_sum'])) {
        $_SESSION['_captcha_sum'] = [];
    }
    $_SESSION['_captcha_sum'][$key] = $a + $b;
    return 'What is ' . $a . ' + ' . $b . '?';
}

/**
 * Validate user's answer for the sum captcha. Clears session after check.
 */
function sum_captcha_validate($key, $user_answer) {
    $stored = $_SESSION['_captcha_sum'][$key] ?? null;
    unset($_SESSION['_captcha_sum'][$key]);
    return $stored !== null && (int) trim($user_answer) === (int) $stored;
}

/**
 * Sanitize string for display and DB (strip tags, trim, length limit)
 */
function sanitize_string($str, $maxLength = 500) {
    if (!is_string($str)) return '';
    $str = trim(strip_tags($str));
    return mb_substr($str, 0, $maxLength);
}

/**
 * Sanitize email
 */
function sanitize_email($email) {
    $email = filter_var(trim($email ?? ''), FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

/**
 * Sanitize phone (digits and + only)
 */
function sanitize_phone($phone, $maxLength = 20) {
    $phone = preg_replace('/[^\d+\s\-]/', '', trim($phone ?? ''));
    return mb_substr($phone, 0, $maxLength);
}

/**
 * Run prepared statement (INSERT/UPDATE) and return true/false
 * Usage: ai_prepared_execute("INSERT INTO tbl (a,b) VALUES (?,?)", 'ss', $a, $b);
 */
function ai_prepared_execute($sql, $types, ...$params) {
    global $ai_conn;
    if (!$ai_conn instanceof mysqli) return false;
    $stmt = $ai_conn->prepare($sql);
    if (!$stmt) return false;
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Run prepared SELECT and return first row as object or null
 */
function ai_prepared_get_one($sql, $types, ...$params) {
    global $ai_conn;
    if (!$ai_conn instanceof mysqli) return null;
    $stmt = $ai_conn->prepare($sql);
    if (!$stmt) return null;
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_object() : null;
    $stmt->close();
    return $row;
}

/**
 * Output minimal HTML + script so browser does not show stuck loading state.
 * Use for form responses that only need to show alert and then history.back().
 */
function form_response_script_page($script_body) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Message</title>';
    echo '<style>body{margin:0;background:#f5f5f5;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:sans-serif;}</style>';
    echo '</head><body>';
    echo '<script>var el=document.getElementById("loading");if(el)el.style.display="none";</script>';
    echo $script_body;
    echo '</body></html>';
}

/**
 * Check if this email can submit (once per 24 hours per form).
 * $table = full table name e.g. tbl_inquiry or tbl_project_inquiry (use DB_PREFIX . 'inquiry').
 * Returns ['allowed' => true] or ['allowed' => false, 'next_after' => '10 Feb 2026, 14:30'].
 */
function email_submission_allowed_24h($email, $table) {
    if (empty($email)) {
        return ['allowed' => false, 'next_after' => null];
    }
    $tbl = preg_replace('/[^a-z0-9_]/i', '', $table);
    $row = ai_prepared_get_one(
        "SELECT created_at FROM `$tbl` WHERE email = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY created_at DESC LIMIT 1",
        's',
        $email
    );
    if (!$row || empty($row->created_at)) {
        return ['allowed' => true];
    }
    $next = date('d M Y, H:i', strtotime($row->created_at . ' +24 hours'));
    return ['allowed' => false, 'next_after' => $next];
}

/**
 * Run prepared SELECT and return array of objects
 */
function ai_prepared_get_all($sql, $types, ...$params) {
    global $ai_conn;
    if (!$ai_conn instanceof mysqli) return [];
    $stmt = $ai_conn->prepare($sql);
    if (!$stmt) return [];
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_object()) $rows[] = $row;
    $stmt->close();
    return $rows;
}
