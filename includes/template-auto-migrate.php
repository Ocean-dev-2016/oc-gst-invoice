<?php
// Auto-migrate invoice template schema
if (isset($ai_db)) {
    // 1. Ensure tbl_invoice_templates exists
    @$ai_db->aiQuery("CREATE TABLE IF NOT EXISTS `tbl_invoice_templates` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `template_name` varchar(150) NOT NULL,
        `template_slug` varchar(100) NOT NULL UNIQUE,
        `preview_image` varchar(255) NULL,
        `file_path` varchar(255) NOT NULL,
        `status` enum('active','deactive') NOT NULL DEFAULT 'active',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `modified_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    // 2. Ensure invoice_template_id exists in tbl_company
    $checkCompCol = @$ai_db->aiGetQueryObj("SHOW COLUMNS FROM `tbl_company` LIKE 'invoice_template_id'");
    if (empty($checkCompCol)) {
        @$ai_db->aiQuery("ALTER TABLE `tbl_company` ADD COLUMN `invoice_template_id` INT(11) NOT NULL DEFAULT 1 AFTER `status`");
    }
    // Ensure all existing companies have default 1st template selected
    @$ai_db->aiQuery("UPDATE `tbl_company` SET `invoice_template_id` = 1 WHERE `invoice_template_id` IS NULL OR `invoice_template_id` = 0");

    // Drop template_id from tbl_invoice if exists
    $checkInvCol = @$ai_db->aiGetQueryObj("SHOW COLUMNS FROM `tbl_invoice` LIKE 'template_id'");
    if (!empty($checkInvCol)) {
        @$ai_db->aiQuery("ALTER TABLE `tbl_invoice` DROP COLUMN `template_id`");
    }

    // Drop template_id from tbl_quotation if exists
    $checkQuoCol = @$ai_db->aiGetQueryObj("SHOW COLUMNS FROM `tbl_quotation` LIKE 'template_id'");
    if (!empty($checkQuoCol)) {
        @$ai_db->aiQuery("ALTER TABLE `tbl_quotation` DROP COLUMN `template_id`");
    }

    // Drop template_id from tbl_purchase if exists
    $checkPurCol = @$ai_db->aiGetQueryObj("SHOW COLUMNS FROM `tbl_purchase` LIKE 'template_id'");
    if (!empty($checkPurCol)) {
        @$ai_db->aiQuery("ALTER TABLE `tbl_purchase` DROP COLUMN `template_id`");
    }

    // Ensure is_premium exists in tbl_invoice_templates
    $checkPremCol = @$ai_db->aiGetQueryObj("SHOW COLUMNS FROM `tbl_invoice_templates` LIKE 'is_premium'");
    if (empty($checkPremCol)) {
        @$ai_db->aiQuery("ALTER TABLE `tbl_invoice_templates` ADD COLUMN `is_premium` TINYINT(1) NOT NULL DEFAULT 0 AFTER `preview_image`");
    }

    // Ensure tbl_purchase payment_status includes 'Partially Paid'
    $chkPurPs = @$ai_db->aiGetQueryObj("SHOW COLUMNS FROM `tbl_purchase` LIKE 'payment_status'");
    if (!empty($chkPurPs) && strpos($chkPurPs[0]->Type ?? '', 'Partially Paid') === false) {
        @$ai_db->aiQuery("ALTER TABLE `tbl_purchase` MODIFY COLUMN `payment_status` ENUM('Pending','Partially Paid','Paid') NOT NULL DEFAULT 'Pending'");
    }

    // Ensure Golden Executive Contrast exists in tbl_invoice_templates with valid file_path
    $chkGolden = @$ai_db->aiGetQueryObj("SELECT id, file_path FROM `tbl_invoice_templates` WHERE `id`=4 OR `template_slug`='golden-executive-contrast' OR `template_slug`='template-golden-executive' LIMIT 1");
    if (empty($chkGolden)) {
        @$ai_db->aiQuery("INSERT INTO `tbl_invoice_templates` (`id`, `template_name`, `template_slug`, `preview_image`, `file_path`, `status`) VALUES
        (4, 'Golden Executive Contrast', 'template-golden-executive', 'assets/img/templates/template-golden-executive.jpg', 'templates/invoices/template-golden-executive.php', 'active')
        ON DUPLICATE KEY UPDATE `file_path`='templates/invoices/template-golden-executive.php', `status`='active'");
    } else {
        // Fix file_path and slug if it points to non-existent file
        $curTpl = $chkGolden[0];
        $fullPath = dirname(__DIR__) . '/' . ltrim($curTpl->file_path, '/');
        if (!file_exists($fullPath) || strpos($curTpl->file_path, 'golden-executive-contrast.php') !== false) {
            @$ai_db->aiQuery("UPDATE `tbl_invoice_templates` SET `file_path`='templates/invoices/template-golden-executive.php', `template_slug`='template-golden-executive' WHERE `id`='{$curTpl->id}'");
        }
    }

    // Ensure Emerald Corporate exists in tbl_invoice_templates
    $chkEmerald = @$ai_db->aiGetQueryObj("SELECT id FROM `tbl_invoice_templates` WHERE `id`=5 OR `template_slug`='template-emerald' LIMIT 1");
    if (empty($chkEmerald)) {
        @$ai_db->aiQuery("INSERT INTO `tbl_invoice_templates` (`id`, `template_name`, `template_slug`, `preview_image`, `file_path`, `status`) VALUES
        (5, 'Emerald Corporate GST', 'template-emerald', 'assets/img/templates/template-emerald.svg', 'templates/invoices/template-emerald.php', 'active')
        ON DUPLICATE KEY UPDATE `file_path`='templates/invoices/template-emerald.php', `status`='active'");
    }

    // Ensure Royal Crimson exists in tbl_invoice_templates
    $chkRuby = @$ai_db->aiGetQueryObj("SELECT id FROM `tbl_invoice_templates` WHERE `id`=6 OR `template_slug`='template-ruby' LIMIT 1");
    if (empty($chkRuby)) {
        @$ai_db->aiQuery("INSERT INTO `tbl_invoice_templates` (`id`, `template_name`, `template_slug`, `preview_image`, `file_path`, `status`) VALUES
        (6, 'Royal Crimson Luxury GST', 'template-ruby', 'assets/img/templates/template-ruby.svg', 'templates/invoices/template-ruby.php', 'active')
        ON DUPLICATE KEY UPDATE `file_path`='templates/invoices/template-ruby.php', `status`='active'");
    }

    // 4. Seed default templates if empty
    $chkCount = @$ai_db->aiGetQueryObj("SELECT COUNT(*) as cnt FROM `tbl_invoice_templates`");
    if (!empty($chkCount) && (int)$chkCount[0]->cnt === 0) {
        @$ai_db->aiQuery("INSERT INTO `tbl_invoice_templates` (`id`, `template_name`, `template_slug`, `preview_image`, `file_path`, `status`) VALUES
        (1, 'Classic Professional GST', 'template-classic', 'assets/img/templates/template-classic.svg', 'templates/invoices/template-classic.php', 'active'),
        (2, 'Modern Navy & Slate GST', 'template-modern', 'assets/img/templates/template-modern.svg', 'templates/invoices/template-modern.php', 'active'),
        (3, 'Clean Minimalist GST', 'template-minimal', 'assets/img/templates/template-minimal.svg', 'templates/invoices/template-minimal.php', 'active')");
    }
}
