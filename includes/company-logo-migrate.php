<?php
// Auto-migrate logo & signature columns in tbl_company
if (isset($ai_db)) {
    // 1. Add logo column to tbl_company
    $chkLogo = @$ai_db->aiGetQueryObj("SHOW COLUMNS FROM `tbl_company` LIKE 'logo'");
    if (empty($chkLogo)) {
        @$ai_db->aiQuery("ALTER TABLE `tbl_company` ADD COLUMN `logo` VARCHAR(255) NULL DEFAULT '' AFTER `email`");
    }

    // 2. Add signature column to tbl_company
    $chkSign = @$ai_db->aiGetQueryObj("SHOW COLUMNS FROM `tbl_company` LIKE 'signature'");
    if (empty($chkSign)) {
        @$ai_db->aiQuery("ALTER TABLE `tbl_company` ADD COLUMN `signature` VARCHAR(255) NULL DEFAULT '' AFTER `logo`");
    }
}

