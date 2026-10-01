<?php

function ai_perm_find_module_key($sidebarMenu, $currentPage)
{
    foreach ($sidebarMenu as $entry) {
        $type = $entry['type'] ?? 'group';
        if ($type === 'item') {
            if (!empty($entry['url']) && $entry['url'] === $currentPage) {
                return $entry['key'] ?? '';
            }
            if (!empty($entry['alt_urls']) && in_array($currentPage, $entry['alt_urls'], true)) {
                return $entry['key'] ?? '';
            }
            continue;
        }

        if (!empty($entry['children']) && is_array($entry['children'])) {
            foreach ($entry['children'] as $child) {
                if (!empty($child['url']) && $child['url'] === $currentPage) {
                    return $child['key'] ?? '';
                }
                if (!empty($child['alt_urls']) && in_array($currentPage, $child['alt_urls'], true)) {
                    return $child['key'] ?? '';
                }
            }
        }
    }
    return '';
}

function ai_perm_detect_user_type($ai_db)
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

    $compRow = $ai_db->aiGetQueryObj("SELECT id FROM tbl_company WHERE username='" . $uname . "' OR email='" . $email . "' LIMIT 1");
    $adminRow = $ai_db->aiGetQueryObj("SELECT id FROM tbl_admin WHERE username='" . $uname . "' OR email='" . $email . "' LIMIT 1");

    if (!empty($compRow) && empty($adminRow)) {
        $_SESSION['user_type'] = 'company';
        return 'company';
    }
    if (!empty($adminRow)) {
        $_SESSION['user_type'] = 'admin';
        return 'admin';
    }

    $_SESSION['user_type'] = 'admin';
    return 'admin';
}

function ai_perm_get_role_info($ai_db, $roleName)
{
    if ($roleName === '') {
        return null;
    }
    $roleRow = $ai_db->aiGetQueryObj(
        "SELECT id, is_super FROM tbl_roles WHERE name='" . addslashes($roleName) . "' OR slug='" . addslashes($roleName) . "' LIMIT 1"
    );
    if (empty($roleRow)) {
        return null;
    }
    return [
        'id' => intval($roleRow[0]->id),
        'is_super' => intval($roleRow[0]->is_super) === 1
    ];
}

function ai_perm_get_permissions_map($ai_db, $roleId)
{
    $map = [];
    if ($roleId <= 0) {
        return $map;
    }
    $rows = $ai_db->aiGetQueryObj("SELECT module_key, can_view, can_add, can_edit, can_delete FROM tbl_role_permissions WHERE role_id='" . intval($roleId) . "'");
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

function ai_perm_get_current_page_permissions($ai_db, $sidebarMenu, $currentPage)
{
    $userType = ai_perm_detect_user_type($ai_db);
    $moduleKey = ai_perm_find_module_key($sidebarMenu, $currentPage);

    if ($userType !== 'user') {
        return [
            'user_type' => $userType,
            'module_key' => $moduleKey,
            'can_view' => 1,
            'can_add' => 1,
            'can_edit' => 1,
            'can_delete' => 1
        ];
    }

    $roleName = $_SESSION['user_role'] ?? '';
    if ($roleName === '') {
        $userRow = $ai_db->aiGetQueryObj("SELECT role FROM tbl_user WHERE username='" . addslashes($_SESSION['username'] ?? '') . "' OR email='" . addslashes($_SESSION['email'] ?? '') . "' LIMIT 1");
        if (!empty($userRow)) {
            $roleName = $userRow[0]->role ?? '';
            $_SESSION['user_role'] = $roleName;
        }
    }

    $roleInfo = ai_perm_get_role_info($ai_db, $roleName);
    if (empty($roleInfo)) {
        return [
            'user_type' => $userType,
            'module_key' => $moduleKey,
            'can_view' => 0,
            'can_add' => 0,
            'can_edit' => 0,
            'can_delete' => 0
        ];
    }

    if (!empty($roleInfo['is_super'])) {
        return [
            'user_type' => $userType,
            'module_key' => $moduleKey,
            'can_view' => 1,
            'can_add' => 1,
            'can_edit' => 1,
            'can_delete' => 1
        ];
    }

    $permMap = ai_perm_get_permissions_map($ai_db, $roleInfo['id']);
    if ($moduleKey === '') {
        return [
            'user_type' => $userType,
            'module_key' => $moduleKey,
            'can_view' => 1,
            'can_add' => 1,
            'can_edit' => 1,
            'can_delete' => 1
        ];
    }

    $perm = $permMap[$moduleKey] ?? ['view' => 0, 'add' => 0, 'edit' => 0, 'delete' => 0];
    return [
        'user_type' => $userType,
        'module_key' => $moduleKey,
        'can_view' => $perm['view'],
        'can_add' => $perm['add'],
        'can_edit' => $perm['edit'],
        'can_delete' => $perm['delete']
    ];
}
