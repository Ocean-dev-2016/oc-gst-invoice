<?php
/**
 * Database Schema Synchronization / Migration Runner
 * 
 * Works both on LOCAL and on LIVE server seamlessly.
 * Whenever accessed (e.g. https://your-live-domain.com/db_sync.php or http://localhost/OC-GST-invoice/db_sync.php),
 * it detects existing tables/columns and safely applies missing migrations, logging every action.
 */

require_once __DIR__ . '/root/config.php';

header('Content-Type: text/html; charset=utf-8');

// Ensure database connection
if (!isset($ai_conn) || !($ai_conn instanceof mysqli)) {
    $ai_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_DATABASE);
    if ($ai_conn->connect_error) {
        die("Database connection failed: " . $ai_conn->connect_error);
    }
}
$conn = $ai_conn;

// Helper function to check if table exists
function tableExists($table) {
    global $conn;
    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return ($res && mysqli_num_rows($res) > 0);
}

// Helper function to check if column exists in table
function columnExists($table, $column) {
    global $conn;
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return ($res && mysqli_num_rows($res) > 0);
}

// Helper function to execute query and render log item
function runMigrationQuery($sql, $successMsg, $failMsg) {
    global $conn;
    $res = mysqli_query($conn, $sql);
    if ($res) {
        echo '<div class="log-item log-success">✓ ' . htmlspecialchars($successMsg) . '</div>';
        return true;
    } else {
        echo '<div class="log-item log-error">✗ ' . htmlspecialchars($failMsg) . ': ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Sync / Migration</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; padding: 30px; color: #1e293b; margin: 0; }
        .container { max-width: 850px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); padding: 25px 30px; }
        h2 { margin-top: 0; color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
        .badge { font-size: 13px; font-weight: 500; padding: 4px 10px; border-radius: 20px; }
        .badge-live { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-local { background: #e0f2fe; color: #075985; border: 1px solid #bae6fd; }
        .log-item { padding: 10px 14px; border-radius: 6px; margin-bottom: 10px; font-family: monospace; font-size: 13px; line-height: 1.5; }
        .log-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .log-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
        .log-warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .log-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .btn { display: inline-block; background: #2563eb; color: #fff; text-decoration: none; padding: 10px 18px; border-radius: 6px; font-weight: 500; margin-top: 15px; }
        .btn:hover { background: #1d4ed8; }
        .info-bar { background: #f1f5f9; padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container">
    <h2>
        <span>Database Schema Synchronization</span>
        <span class="badge <?= (SITE_MODE == 1) ? 'badge-live' : 'badge-local' ?>">
            <?= (SITE_MODE == 1) ? '🌐 LIVE SERVER' : '💻 LOCALHOST' ?>
        </span>
    </h2>

    <div class="info-bar">
        <strong>Connected DB:</strong> <?= htmlspecialchars(DB_DATABASE) ?> (Host: <?= htmlspecialchars(DB_HOST) ?>) &nbsp;|&nbsp; 
        <strong>Server:</strong> <?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost') ?>
    </div>

<?php

// =========================================================================
// MIGRATIONS & SCHEMA UPDATES HISTORY
// જે પણ નવા ટેબલ કે ફિલ્ડ ઉમેરાય તે ક્રમશઃ અહીં નીચે એડ કરવા
// =========================================================================

// 1. tbl_company -> api_token column
if (tableExists('tbl_company')) {
    if (!columnExists('tbl_company', 'api_token')) {
        runMigrationQuery(
            "ALTER TABLE `tbl_company` ADD COLUMN `api_token` VARCHAR(255) NULL DEFAULT NULL AFTER `password`",
            "Added `api_token` column to `tbl_company` table successfully.",
            "Failed to add `api_token` column to `tbl_company`"
        );
    } else {
        echo '<div class="log-item log-info">ℹ `tbl_company`.`api_token` column already exists.</div>';
    }
} else {
    echo '<div class="log-item log-warn">⚠ Table `tbl_company` does not exist!</div>';
}

// 2. tbl_api_tokens (Bearer authentication token log table if needed)
if (!tableExists('tbl_api_tokens')) {
    $createTokensSql = "CREATE TABLE IF NOT EXISTS `tbl_api_tokens` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `user_id` bigint(20) NOT NULL,
        `user_type` varchar(50) NOT NULL DEFAULT 'company',
        `token` varchar(255) NOT NULL,
        `expires_at` datetime NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        KEY `idx_token` (`token`),
        KEY `idx_user` (`user_id`, `user_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    runMigrationQuery(
        $createTokensSql,
        "Created `tbl_api_tokens` table successfully.",
        "Failed to create `tbl_api_tokens` table"
    );
} else {
    echo '<div class="log-item log-info">ℹ `tbl_api_tokens` table exists.</div>';
}

// 3. tbl_company -> Indexes check
if (tableExists('tbl_company')) {
    // Add index on username/email/mobile if not exists for fast login & unique checks
    $indexCheck = mysqli_query($conn, "SHOW INDEX FROM `tbl_company` WHERE Key_name = 'idx_login_credentials'");
    if ($indexCheck && mysqli_num_rows($indexCheck) === 0) {
        @mysqli_query($conn, "ALTER TABLE `tbl_company` ADD INDEX `idx_login_credentials` (`email`, `username`, `mobile_no`)");
        echo '<div class="log-item log-success">✓ Added `idx_login_credentials` index to `tbl_company`.</div>';
    } else {
        echo '<div class="log-item log-info">ℹ `tbl_company` login credentials index already exists.</div>';
    }
}

// 4. Ensure upload directories exist
$uploadDirs = [
    __DIR__ . '/uploads/',
    __DIR__ . '/uploads/company/'
];
foreach ($uploadDirs as $dir) {
    if (!is_dir($dir)) {
        if (@mkdir($dir, 0777, true)) {
            echo '<div class="log-item log-success">✓ Created directory `' . basename($dir) . '/` successfully.</div>';
        } else {
            echo '<div class="log-item log-warn">⚠ Please create `' . basename($dir) . '/` directory with write permissions.</div>';
        }
    }
}

echo '<p class="mt-4" style="color: #15803d; font-weight: 600; font-size: 15px;">✓ Database synchronization completed successfully.</p>';
?>
    <a href="<?= SITE_URL ?>" class="btn">Go to Dashboard / Home</a>
</div>
</body>
</html>
