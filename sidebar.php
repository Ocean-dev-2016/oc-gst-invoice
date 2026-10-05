<?php
$currentPage = basename($_SERVER['PHP_SELF']);
include(__DIR__ . '/includes/sidebar-menu.php');

if (!function_exists('ai_detect_user_type')) {
    function ai_detect_user_type($ai_db)
    {
        if (isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['user', 'admin', 'company'], true)) {
            return $_SESSION['user_type'];
        }

        $uname = addslashes($_SESSION['username'] ?? '');
        $email = addslashes($_SESSION['email'] ?? '');
        if ($uname === '' && $email === '') {
            $_SESSION['user_type'] = 'admin';
            return 'admin';
        }

        $adminRow = $ai_db->aiGetQueryObj("SELECT id FROM tbl_admin WHERE username='" . $uname . "' OR email='" . $email . "' LIMIT 1");
        $compRow = $ai_db->aiGetQueryObj("SELECT id FROM tbl_company WHERE username='" . $uname . "' OR email='" . $email . "' LIMIT 1");

        if (!empty($compRow) && empty($adminRow)) {
            $_SESSION['user_type'] = 'company';
            $_SESSION['company_id'] = (int)$compRow[0]->id;
            return 'company';
        }
        if (!empty($adminRow)) {
            $_SESSION['user_type'] = 'admin';
            return 'admin';
        }

        $_SESSION['user_type'] = 'admin';
        return 'admin';
    }
}

if (!function_exists('ai_get_role_permissions_map')) {
    function ai_get_role_permissions_map($ai_db)
    {
        $map = [];
        $roleId = 0;
        $roleName = $_SESSION['user_role'] ?? '';

        if (!empty($_SESSION['user_role_id'])) {
            $roleId = intval($_SESSION['user_role_id']);
        }

    if ($roleId === 0 && !empty($roleName)) {
        $roleRow = $ai_db->aiGetQueryObj("SELECT id, is_super FROM tbl_roles WHERE name='" . addslashes($roleName) . "' OR slug='" . addslashes($roleName) . "' LIMIT 1");
        if (!$roleRow) {
            return $map;
        }
        $roleId = intval($roleRow[0]->id);
        $_SESSION['user_role_id'] = $roleId;
        $_SESSION['user_role_is_super'] = $roleRow[0]->is_super ?? 0;
    }

    if ($roleId === 0) {
        return $map;
    }

    $isSuper = isset($_SESSION['user_role_is_super']) ? intval($_SESSION['user_role_is_super']) === 1 : false;
    if ($isSuper) {
        return ['__super__' => true];
    }

    $rows = $ai_db->aiGetQueryObj("SELECT module_key, can_view, can_add, can_edit, can_delete FROM tbl_role_permissions WHERE role_id='" . $roleId . "'");
    if ($rows) {
        foreach ($rows as $row) {
            $map[$row->module_key] = [
                'view' => intval($row->can_view),
                'add' => intval($row->can_add),
                'edit' => intval($row->can_edit),
                'delete' => intval($row->can_delete),
            ];
        }
    }
        return $map;
    }
}

if (!function_exists('ai_can_show_module')) {
    function ai_can_show_module($moduleKey, $permMap)
    {
        if (isset($permMap['__super__']) && $permMap['__super__'] === true) {
            return true;
        }
        if (empty($permMap) || empty($moduleKey)) {
            return false;
        }
        if (!isset($permMap[$moduleKey])) {
            return false;
        }
        $p = $permMap[$moduleKey];
        return !empty($p['view']) || !empty($p['add']) || !empty($p['edit']) || !empty($p['delete']);
    }
}

if (!function_exists('ai_is_menu_item_active')) {
    function ai_is_menu_item_active($item, $currentPage)
    {
        $pages = [];
        if (!empty($item['url'])) {
            $pages[] = $item['url'];
        }
        if (!empty($item['alt_urls']) && is_array($item['alt_urls'])) {
            $pages = array_merge($pages, $item['alt_urls']);
        }
        return in_array($currentPage, $pages, true);
    }
}

if (!function_exists('ai_is_group_active')) {
    function ai_is_group_active($group, $currentPage)
    {
        if (!isset($group['children']) || !is_array($group['children'])) {
            return false;
        }
        foreach ($group['children'] as $child) {
            if (ai_is_menu_item_active($child, $currentPage)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('ai_filter_sidebar_menu')) {
    function ai_filter_sidebar_menu($sidebarMenu, $permMap, $isSuperUser)
    {
        if ($isSuperUser) {
            return $sidebarMenu;
        }

        $filtered = [];
        foreach ($sidebarMenu as $entry) {
            $type = $entry['type'] ?? 'group';
            if ($type === 'item') {
                $key = $entry['key'] ?? '';
                if (ai_can_show_module($key, $permMap)) {
                    $filtered[] = $entry;
                }
                continue;
            }

            if (empty($entry['children']) || !is_array($entry['children'])) {
                continue;
            }
            $children = [];
            foreach ($entry['children'] as $child) {
                $key = $child['key'] ?? '';
                if (ai_can_show_module($key, $permMap)) {
                    $children[] = $child;
                }
            }
            if (!empty($children)) {
                $entry['children'] = $children;
                $filtered[] = $entry;
            }
        }
        return $filtered;
    }
}

$userType = isset($ai_db) ? ai_detect_user_type($ai_db) : ($_SESSION['user_type'] ?? 'admin');
$isUserLogin = $userType === 'user';
$isSuperUser = ($userType !== 'user');
$permMap = [];
if ($isUserLogin && isset($ai_db)) {
    $permMap = ai_get_role_permissions_map($ai_db);
    if (isset($permMap['__super__']) && $permMap['__super__'] === true) {
        $isSuperUser = true;
    }
}
$renderMenu = ai_filter_sidebar_menu($sidebarMenu, $permMap, $isSuperUser);

// Only hide Manage Company if current logged in user is a company user
if ($userType === 'company') {
    $renderMenu = array_values(array_filter($renderMenu, function($item) {
        return ($item['key'] ?? '') !== 'manage_company';
    }));
}
?>



<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand demo" style="padding: 20px 15px 10px; text-align: center;">
        <a href="dashboard.php" class="app-brand-link">
            <img src="<?= ADMIN_PANEL_LOGO ?>" alt="logo icon"
                style="width: 70px; display: block; margin: 0 auto 12px;">
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="ti menu-toggle-icon d-none d-xl-block ti-sm align-middle"></i>
            <i class="ti ti-x d-block d-xl-none ti-sm align-middle"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">
        <?php foreach ($renderMenu as $entry) { ?>
            <?php if (($entry['type'] ?? 'group') === 'item') { ?>
                <?php $isActive = ai_is_menu_item_active($entry, $currentPage); ?>
                <li class="menu-item <?= $isActive ? 'active' : '' ?>">
                    <a href="<?= $entry['url'] ?>" class="menu-link">
                        <i class="menu-icon tf-icons <?= $entry['icon'] ?? '' ?>"></i>
                        <?php if (!empty($entry['i18n'])) { ?>
                            <div data-i18n="<?= htmlspecialchars($entry['i18n']) ?>"><?= htmlspecialchars($entry['label']) ?></div>
                        <?php } else { ?>
                            <div><?= htmlspecialchars($entry['label']) ?></div>
                        <?php } ?>
                    </a>
                </li>
            <?php } else { ?>
                <?php $isGroupActive = ai_is_group_active($entry, $currentPage); ?>
                <li class="menu-item <?= $isGroupActive ? 'active open' : '' ?>">
                    <a href="javascript:void(0);" class="menu-link menu-toggle">
                        <i class="menu-icon tf-icons <?= $entry['icon'] ?? '' ?>"></i>
                        <div><?= htmlspecialchars($entry['label']) ?></div>
                    </a>

                    <ul class="menu-sub">
                        <?php foreach ($entry['children'] as $child) { ?>
                            <?php $isChildActive = ai_is_menu_item_active($child, $currentPage); ?>
                            <li class="menu-item <?= $isChildActive ? 'active' : '' ?>">
                                <a href="<?= $child['url'] ?>" class="menu-link">
                                    <?php if (!empty($child['icon'])) { ?>
                                        <i class="menu-icon tf-icons <?= $child['icon'] ?>"></i>
                                    <?php } ?>
                                    <?php if (!empty($child['i18n'])) { ?>
                                        <div data-i18n="<?= htmlspecialchars($child['i18n']) ?>"><?= htmlspecialchars($child['label']) ?></div>
                                    <?php } else { ?>
                                        <div><?= htmlspecialchars($child['label']) ?></div>
                                    <?php } ?>
                                </a>
                            </li>
                        <?php } ?>
                    </ul>
                </li>
            <?php } ?>
        <?php } ?>
    </ul>
</aside>
