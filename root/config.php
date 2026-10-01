<?php

/*
 * File = config.php
 * Date = 03-11-2025
 */
// session start
session_start();
error_reporting(E_ALL);
// website full url
define('APP_NAME', 'OC-GST Invice');

define('SITE_LOCAL_URL', 'http://localhost/OC-GST-invoice/');
// define('SITE_NAME', 'Site Name');
define('SITE_LIVE_URL', 'https://oceaninfotech.co.in/');

// site running in live server or locaL
define('SITE_MODE', '0');
define('DB_PREFIX', 'tbl_');

// dynamic site url detection
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$subfolder = '';
if (strpos($script_name, '/OC-GST-invoice/') !== false) {
    $subfolder = 'OC-GST-invoice/';
}
$dynamic_site_url = $protocol . $host . '/' . $subfolder;

// other configuration
if (SITE_MODE == 0) {
    define('SITE_URL', $dynamic_site_url);
    define('ADMIN_URL', SITE_URL);
    // db configuration
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_DATABASE', 'oc-gst-invoice');
} else {
    define('SITE_URL', $dynamic_site_url);
    define('ADMIN_URL', SITE_URL);
    // db configuration
    define('DB_HOST', 'localhost');
    define('DB_USER', 'oc-gst-invoice');
    define('DB_PASS', 'oc-gst-invoice');
    define('DB_DATABASE', 'oc-gst-invoice');
}

require_once ('define.php');

// class call function
date_default_timezone_set('Asia/Calcutta');
require_once ('ai_core/class.core.php');
require_once ('ai_core/security.php');
require_once ('include/class.phpmailer.php');


