<?php
    require_once __DIR__ . '/../root/config.php';

    
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$isLoginPage = ($currentPage === 'index.php');
$isLoggedIn = !empty($_SESSION['aid']);

// Prevent cached admin pages from being visible after logout/back.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

if (!$isLoginPage && !$isLoggedIn) {
    header('Location: index.php');
    exit;
}

    if ($isLoginPage && $isLoggedIn) {
        header('Location: dashboard.php');
        exit;
    }

    $currentPage = basename($_SERVER['PHP_SELF'] ?? '');
    $ai_page_perm = null;
    $ai_hide_add_links = false;
    $permMenuPath = __DIR__ . '/sidebar-menu.php';
    $permHelperPath = __DIR__ . '/permissions.php';

    if (file_exists($permMenuPath) && file_exists($permHelperPath)) {
        include_once $permMenuPath;
        include_once $permHelperPath;

        if (isset($ai_db)) {
            $ai_page_perm = ai_perm_get_current_page_permissions($ai_db, $sidebarMenu ?? [], $currentPage);

            if (
                is_array($ai_page_perm)
                && ($ai_page_perm['user_type'] ?? '') === 'user'
                && $currentPage !== 'index.php'
                && $currentPage !== 'dashboard.php'
            ) {
                $mode = strtolower($_REQUEST['mode'] ?? '');
                if ($mode === '' && $currentPage === 'manage-role-permission.php') {
                    $mode = 'edit';
                }
                $hasAnyPermission = !empty($ai_page_perm['can_view'])
                    || !empty($ai_page_perm['can_add'])
                    || !empty($ai_page_perm['can_edit'])
                    || !empty($ai_page_perm['can_delete']);

                $allowed = true;
                if ($mode === 'add') {
                    $allowed = !empty($ai_page_perm['can_add']);
                } elseif ($mode === 'edit') {
                    $allowed = !empty($ai_page_perm['can_edit']);
                } elseif ($mode === 'delete') {
                    $allowed = !empty($ai_page_perm['can_delete']);
                } elseif (($ai_page_perm['module_key'] ?? '') !== '' && !$hasAnyPermission) {
                    $allowed = false;
                }

                if (!$allowed) {
                    $ai_core->aiGoPage('dashboard.php');
                }
            }
            $ai_hide_add_links = ($ai_page_perm['user_type'] ?? '') === 'user' && empty($ai_page_perm['can_add']);
        }
    }

    if (!function_exists('ai_handle_status_toggle')) {
        function ai_handle_status_toggle($table, $pageUrl, $activeValue = 'active', $inactiveValue = 'deactive', $statusField = 'status', $idField = 'id')
        {
            if (!isset($_GET['toggle'])) {
                return;
            }
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            $to = $_GET['to'] ?? '';
            if ($id <= 0 || $table === '') {
                return;
            }
            if (!in_array($to, [$activeValue, $inactiveValue], true)) {
                return;
            }
            if (!preg_match('/^tbl_[a-z0-9_]+$/i', $table)) {
                return;
            }
            global $ai_db, $ai_core;
            $ai_db->aiQuery("UPDATE `" . $table . "` SET `" . $statusField . "`='" . addslashes($to) . "' WHERE `" . $idField . "`='" . $id . "'");
            $ai_core->aiGoPage($pageUrl);
        }
    }

    if (!function_exists('ai_render_status_toggle')) {
        function ai_render_status_toggle($pageUrl, $id, $status, $activeValue = 'active', $inactiveValue = 'deactive')
        {
            $isActive = strtolower(trim((string)$status)) === strtolower($activeValue);
            $label = $isActive ? 'Active' : 'Deactive';
            $class = $isActive ? 'bg-label-success' : 'bg-label-danger';
            $to = $isActive ? $inactiveValue : $activeValue;
            $url = $pageUrl . '?toggle=1&id=' . intval($id) . '&to=' . urlencode($to);
            return '<a class="badge ' . $class . ' px-3 py-2 fs-6" href="' . $url . '">' . $label . '</a>';
        }
    }
    ?>
 <!doctype html>
 <html
     lang="en"
     class="light-style layout-wide customizer-hide"
     dir="ltr"
     data-theme="theme-default"
     data-assets-path="<?= ADMIN_URL ?>assets/"
     data-template="vertical-menu-template">

 <head>
     <meta charset="utf-8" />
     <meta
         name="viewport"
         content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

     <title><?= SITE_TITLE ?> </title>

     <meta name="description" content="" />

     <!-- Favicon -->
     <link rel="icon" type="image/x-icon" href="<?= ADMIN_URL; ?>assets/img/favicon/favicon.png" />

     <!-- Fonts -->
     <link rel="preconnect" href="https://fonts.googleapis.com" />
     <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
     <link
         href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&ampdisplay=swap"
         rel="stylesheet" />

     <!-- Icons -->
     <link rel="stylesheet" href="<?= ADMIN_URL; ?>assets/vendor/fonts/fontawesome.css" />
     <link rel="stylesheet" href="assets/vendor/fonts/tabler-icons.css" />
     <link rel="stylesheet" href="assets/vendor/fonts/flag-icons.css" />

     <!-- Core CSS -->
     <link rel="stylesheet" href="<?= ADMIN_URL; ?>assets/vendor/css/rtl/core.css" class="template-customizer-core-css" />
     <link rel="stylesheet" href="<?= ADMIN_URL; ?>assets/vendor/css/rtl/theme-default.css" class="template-customizer-theme-css" />
     <link rel="stylesheet" href="<?= ADMIN_URL; ?>assets/css/demo.css" />

     <!-- Vendors CSS -->
     <link rel="stylesheet" href="<?= ADMIN_URL; ?>assets/vendor/libs/node-waves/node-waves.css" />
     <link rel="stylesheet" href="<?= ADMIN_URL; ?>assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
     <link rel="stylesheet" href="<?= ADMIN_URL; ?>assets/vendor/libs/typeahead-js/typeahead.css" />
     <link rel="stylesheet" href="<?= ADMIN_URL; ?>assets/vendor/libs/quill/typography.css" />
     <link rel="stylesheet" href="<?= ADMIN_URL; ?>assets/vendor/libs/quill/katex.css" />
     <link rel="stylesheet" href="<?= ADMIN_URL; ?>assets/vendor/libs/quill/editor.css" />
     <link rel="stylesheet" href="<?= ADMIN_URL; ?>assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css" />
     <link rel="stylesheet" href="<?= ADMIN_URL; ?>assets/vendor/libs/sweetalert2/sweetalert2.css" />
     <link rel="stylesheet" href="<?= ADMIN_URL; ?>assets/vendor/libs/select2/select2.css" />
     <?php if ($currentPage === 'dashboard.php') { ?>
     <link rel="stylesheet" href="<?= ADMIN_URL; ?>assets/vendor/libs/apex-charts/apex-charts.css" />
     <?php } ?>
     <!-- Vendor -->
     <link rel="stylesheet" href="<?= ADMIN_URL; ?>assets/vendor/libs/@form-validation/form-validation.css" />

     <!-- Page CSS -->
     <!-- Page -->
     <link rel="stylesheet" href="<?= ADMIN_URL; ?>assets/vendor/css/pages/page-auth.css" />

     <!-- Custome -->
     <link rel="stylesheet" href="<?= ADMIN_URL; ?>assets/custom/custome_css.css" />

     <!-- Helpers -->
     <script src="<?= ADMIN_URL; ?>assets/vendor/js/helpers.js"></script>
     <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
     <!--? Template customizer: To hide customizer set displayCustomizer value false in config.js.  -->
     <script src="<?= ADMIN_URL; ?>assets/vendor/js/template-customizer.js"></script>
     <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
     <script src="<?= ADMIN_URL; ?>assets/js/config.js"></script>
     <style>
.remove-image, .remove-image2 {
    position:absolute;
    top:5px;
    right:8px;
    background:red;
    color:#fff;
    width:22px;
    height:22px;
    text-align:center;
    line-height:20px;
    border-radius:50%;
    cursor:pointer;
    font-weight:bold;
}
     </style>
     <?php if (!empty($ai_hide_add_links)) { ?>
     <style>
         a[href*="mode=add"],
         button[data-mode="add"] {
             display: none !important;
         }
     </style>
     <?php } ?>
 </head>

 <body>
     <!-- Content -->
