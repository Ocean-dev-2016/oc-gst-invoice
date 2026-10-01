<?php

// Central sidebar configuration used by sidebar + role permissions.
// Add new menu items here to automatically appear in permissions.
$sidebarMenu = [
    [
        'type' => 'item',
        'label' => 'Dashboard',
        'icon' => 'ti ti-smart-home',
        'url' => 'dashboard.php',
        'key' => 'dashboard'
    ],
    [
        'type' => 'item',
        'label' => 'Manage Company',
        'icon' => 'ti ti-building',
        'url' => 'manage-company-list.php',
        'alt_urls' => ['manage-company-form.php'],
        'key' => 'manage_company'
    ],
    [
        'type' => 'item',
        'label' => 'Manage Party Details',
        'icon' => 'ti ti-users',
        'url' => 'manage-party-list.php',
        'alt_urls' => ['manage-party-form.php'],
        'key' => 'manage_party'
    ],
    [
        'type' => 'item',
        'label' => 'Manage Product',
        'icon' => 'ti ti-box',
        'url' => 'manage-product-list.php',
        'alt_urls' => ['manage-product-form.php'],
        'key' => 'manage_product'
    ],
    [
        'type' => 'item',
        'label' => 'Manage Quotation',
        'icon' => 'ti ti-file-invoice',
        'url' => 'manage-quotation-list.php',
        'alt_urls' => ['manage-quotation-form.php'],
        'key' => 'manage_quotation'
    ],
    [
        'type' => 'group',
        'label' => 'Manage Regions',
        'icon' => 'ti ti-map-pin',
        'children' => [
            [
                'type' => 'item',
                'label' => 'Manage State',
                'icon' => 'ti ti-location-pin',
                'url' => 'manage-state-list.php',
                'alt_urls' => ['manage-state-form.php'],
                'key' => 'manage_state'
            ],
            [
                'type' => 'item',
                'label' => 'Manage City',
                'icon' => 'ti ti-building-community',
                'url' => 'manage-city-list.php',
                'alt_urls' => ['manage-city-form.php'],
                'key' => 'manage_city'
            ],
        ]
    ],
];
