<?php

function getQuotationStatuses()
{
    return ['Draft', 'Send', 'Accepted', 'Rejected'];
}

function ensureQuotationStatusSchema($connection)
{
    $result = mysqli_query($connection, "SHOW COLUMNS FROM `tbl_quotation` LIKE 'quotation_status'");
    if (!$result) {
        return false;
    }

    $column = mysqli_fetch_assoc($result);
    if (!$column) {
        return mysqli_query(
            $connection,
            "ALTER TABLE `tbl_quotation` ADD COLUMN `quotation_status` ENUM('Draft','Send','Accepted','Rejected') NOT NULL DEFAULT 'Draft' AFTER `converted_invoice_id`"
        ) !== false;
    }

    if (stripos($column['Type'] ?? '', "'Send'") === false) {
        return mysqli_query(
            $connection,
            "ALTER TABLE `tbl_quotation` MODIFY COLUMN `quotation_status` ENUM('Draft','Send','Accepted','Rejected') NOT NULL DEFAULT 'Draft'"
        ) !== false;
    }

    return true;
}
