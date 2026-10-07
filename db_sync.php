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

// 2. tbl_party -> Missing columns check (email, pincode, pan_no, party_status)
if (tableExists('tbl_party')) {
    if (!columnExists('tbl_party', 'email')) {
        runMigrationQuery(
            "ALTER TABLE `tbl_party` ADD COLUMN `email` VARCHAR(150) NULL DEFAULT '' AFTER `mobile_no`",
            "Added `email` column to `tbl_party` table successfully.",
            "Failed to add `email` column to `tbl_party`"
        );
    } else {
        echo '<div class="log-item log-info">ℹ `tbl_party`.`email` column already exists.</div>';
    }

    if (!columnExists('tbl_party', 'pincode')) {
        runMigrationQuery(
            "ALTER TABLE `tbl_party` ADD COLUMN `pincode` VARCHAR(20) NULL DEFAULT '' AFTER `city_id`",
            "Added `pincode` column to `tbl_party` table successfully.",
            "Failed to add `pincode` column to `tbl_party`"
        );
    } else {
        echo '<div class="log-item log-info">ℹ `tbl_party`.`pincode` column already exists.</div>';
    }

    if (!columnExists('tbl_party', 'shipping_pincode')) {
        runMigrationQuery(
            "ALTER TABLE `tbl_party` ADD COLUMN `shipping_pincode` VARCHAR(20) NULL DEFAULT '' AFTER `pincode`",
            "Added `shipping_pincode` column to `tbl_party` table successfully.",
            "Failed to add `shipping_pincode` column to `tbl_party`"
        );
    } else {
        echo '<div class="log-item log-info">ℹ `tbl_party`.`shipping_pincode` column already exists.</div>';
    }

    if (!columnExists('tbl_party', 'shipping_address')) {
        runMigrationQuery(
            "ALTER TABLE `tbl_party` ADD COLUMN `shipping_address` TEXT NULL DEFAULT NULL AFTER `shipping_pincode`",
            "Added `shipping_address` column to `tbl_party` table successfully.",
            "Failed to add `shipping_address` column to `tbl_party`"
        );
    } else {
        echo '<div class="log-item log-info">ℹ `tbl_party`.`shipping_address` column already exists.</div>';
    }

    if (!columnExists('tbl_party', 'shipping_state_id')) {
        runMigrationQuery(
            "ALTER TABLE `tbl_party` ADD COLUMN `shipping_state_id` INT(11) NOT NULL DEFAULT 0 AFTER `shipping_address`",
            "Added `shipping_state_id` column to `tbl_party` table successfully.",
            "Failed to add `shipping_state_id` column to `tbl_party`"
        );
    } else {
        echo '<div class="log-item log-info">ℹ `tbl_party`.`shipping_state_id` column already exists.</div>';
    }

    if (!columnExists('tbl_party', 'shipping_city_id')) {
        runMigrationQuery(
            "ALTER TABLE `tbl_party` ADD COLUMN `shipping_city_id` INT(11) NOT NULL DEFAULT 0 AFTER `shipping_state_id`",
            "Added `shipping_city_id` column to `tbl_party` table successfully.",
            "Failed to add `shipping_city_id` column to `tbl_party`"
        );
    } else {
        echo '<div class="log-item log-info">ℹ `tbl_party`.`shipping_city_id` column already exists.</div>';
    }

    if (!columnExists('tbl_party', 'pan_no')) {
        runMigrationQuery(
            "ALTER TABLE `tbl_party` ADD COLUMN `pan_no` VARCHAR(20) NULL DEFAULT '' AFTER `gst_no`",
            "Added `pan_no` column to `tbl_party` table successfully.",
            "Failed to add `pan_no` column to `tbl_party`"
        );
    } else {
        echo '<div class="log-item log-info">ℹ `tbl_party`.`pan_no` column already exists.</div>';
    }

    if (!columnExists('tbl_party', 'party_status')) {
        runMigrationQuery(
            "ALTER TABLE `tbl_party` ADD COLUMN `party_status` VARCHAR(50) NULL DEFAULT 'Sales' AFTER `pan_no`",
            "Added `party_status` column to `tbl_party` table successfully.",
            "Failed to add `party_status` column to `tbl_party`"
        );
    } else {
        echo '<div class="log-item log-info">ℹ `tbl_party`.`party_status` column already exists.</div>';
    }

    if (!columnExists('tbl_party', 'business_type')) {
        runMigrationQuery(
            "ALTER TABLE `tbl_party` ADD COLUMN `business_type` ENUM('Individual','Business') NOT NULL DEFAULT 'Business' AFTER `party_status`",
            "Added `business_type` column to `tbl_party` table successfully.",
            "Failed to add `business_type` column to `tbl_party`"
        );
    }

    if (!columnExists('tbl_party', 'opening_balance')) {
        runMigrationQuery(
            "ALTER TABLE `tbl_party` ADD COLUMN `opening_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `business_type`",
            "Added `opening_balance` column to `tbl_party` table successfully.",
            "Failed to add `opening_balance` column to `tbl_party`"
        );
    }

    if (!columnExists('tbl_party', 'balance_type')) {
        runMigrationQuery(
            "ALTER TABLE `tbl_party` ADD COLUMN `balance_type` ENUM('Credit','Debit') NOT NULL DEFAULT 'Debit' AFTER `opening_balance`",
            "Added `balance_type` column to `tbl_party` table successfully.",
            "Failed to add `balance_type` column to `tbl_party`"
        );
    }

    if (!columnExists('tbl_party', 'credit_limit')) {
        runMigrationQuery(
            "ALTER TABLE `tbl_party` ADD COLUMN `credit_limit` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `balance_type`",
            "Added `credit_limit` column to `tbl_party` table successfully.",
            "Failed to add `credit_limit` column to `tbl_party`"
        );
    }

    if (!columnExists('tbl_party', 'outstanding')) {
        runMigrationQuery(
            "ALTER TABLE `tbl_party` ADD COLUMN `outstanding` DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `credit_limit`",
            "Added `outstanding` column to `tbl_party` table successfully.",
            "Failed to add `outstanding` column to `tbl_party`"
        );
    }

    if (!columnExists('tbl_party', 'remark')) {
        runMigrationQuery(
            "ALTER TABLE `tbl_party` ADD COLUMN `remark` TEXT NULL AFTER `outstanding`",
            "Added `remark` column to `tbl_party` table successfully.",
            "Failed to add `remark` column to `tbl_party`"
        );
    }
} else {
    echo '<div class="log-item log-warn">⚠ Table `tbl_party` does not exist!</div>';
}

// 2. tbl_quotation -> payment_status & due_date columns
if (tableExists('tbl_quotation')) {
    if (!columnExists('tbl_quotation', 'payment_status')) {
        runMigrationQuery(
            "ALTER TABLE `tbl_quotation` ADD COLUMN `payment_status` ENUM('Pending','Paid') NOT NULL DEFAULT 'Pending' AFTER `grand_total`",
            "Added `payment_status` column to `tbl_quotation` table successfully.",
            "Failed to add `payment_status` column to `tbl_quotation`"
        );
    } else {
        echo '<div class="log-item log-info">ℹ `tbl_quotation`.`payment_status` column already exists.</div>';
    }

    if (!columnExists('tbl_quotation', 'due_date')) {
        runMigrationQuery(
            "ALTER TABLE `tbl_quotation` ADD COLUMN `due_date` DATE NULL DEFAULT NULL AFTER `quotation_date`",
            "Added `due_date` column to `tbl_quotation` table successfully.",
            "Failed to add `due_date` column to `tbl_quotation`"
        );
    } else {
        echo '<div class="log-item log-info">ℹ `tbl_quotation`.`due_date` column already exists.</div>';
    }
}

// 2.1 tbl_product -> sku column
if (tableExists('tbl_product')) {
    if (!columnExists('tbl_product', 'sku')) {
        runMigrationQuery(
            "ALTER TABLE `tbl_product` ADD COLUMN `sku` VARCHAR(100) NULL DEFAULT '' AFTER `product_name`",
            "Added `sku` column to `tbl_product` table successfully.",
            "Failed to add `sku` column to `tbl_product`"
        );
    } else {
        echo '<div class="log-item log-info">ℹ `tbl_product`.`sku` column already exists.</div>';
    }
}

// 2.2 tbl_quotation_items -> discount columns
if (tableExists('tbl_quotation_items')) {
    if (!columnExists('tbl_quotation_items', 'discount_type')) {
        runMigrationQuery(
            "ALTER TABLE `tbl_quotation_items` ADD COLUMN `discount_type` ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage' AFTER `gst_percent`",
            "Added `discount_type` column to `tbl_quotation_items` successfully.",
            "Failed to add `discount_type` to `tbl_quotation_items`"
        );
    } else {
        echo '<div class="log-item log-info">ℹ `tbl_quotation_items`.`discount_type` column already exists.</div>';
    }

    if (!columnExists('tbl_quotation_items', 'discount_value')) {
        runMigrationQuery(
            "ALTER TABLE `tbl_quotation_items` ADD COLUMN `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `discount_type`",
            "Added `discount_value` column to `tbl_quotation_items` successfully.",
            "Failed to add `discount_value` to `tbl_quotation_items`"
        );
    } else {
        echo '<div class="log-item log-info">ℹ `tbl_quotation_items`.`discount_value` column already exists.</div>';
    }

    if (!columnExists('tbl_quotation_items', 'discount_amount')) {
        runMigrationQuery(
            "ALTER TABLE `tbl_quotation_items` ADD COLUMN `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `discount_value`",
            "Added `discount_amount` column to `tbl_quotation_items` successfully.",
            "Failed to add `discount_amount` to `tbl_quotation_items`"
        );
    } else {
        echo '<div class="log-item log-info">ℹ `tbl_quotation_items`.`discount_amount` column already exists.</div>';
    }
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

// 4. tbl_product -> Index check for company-wise fast lookup (product_name, hsn_code)
if (tableExists('tbl_product')) {
    $idxProduct = mysqli_query($conn, "SHOW INDEX FROM `tbl_product` WHERE Key_name = 'idx_comp_hsn_product'");
    if ($idxProduct && mysqli_num_rows($idxProduct) === 0) {
        @mysqli_query($conn, "ALTER TABLE `tbl_product` ADD INDEX `idx_comp_hsn_product` (`company_id`, `hsn_code`, `product_name`)");
        echo '<div class="log-item log-success">✓ Added `idx_comp_hsn_product` index to `tbl_product`.</div>';
    } else {
        echo '<div class="log-item log-info">ℹ `tbl_product` company & HSN code index already exists.</div>';
    }
}

// 5. Ensure upload directories exist
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
