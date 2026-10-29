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

    // 3. Ensure template_id exists in tbl_invoice
    $checkInvCol = @$ai_db->aiGetQueryObj("SHOW COLUMNS FROM `tbl_invoice` LIKE 'template_id'");
    if (empty($checkInvCol)) {
        @$ai_db->aiQuery("ALTER TABLE `tbl_invoice` ADD COLUMN `template_id` INT(11) NOT NULL DEFAULT 1 AFTER `company_id`");
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

