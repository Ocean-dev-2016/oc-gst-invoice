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
        'type' => 'group',
        'label' => 'Sales',
        'icon' => 'ti ti-shopping-cart',
        'children' => [
            [
                'type' => 'item',
                'label' => 'Quotation',
                'icon' => 'ti ti-file-description',
                'url' => 'manage-quotation-list.php',
                'alt_urls' => ['manage-quotation-form.php'],
                'key' => 'manage_quotation'
            ],
            [
                'type' => 'item',
                'label' => 'Invoice',
                'icon' => 'ti ti-file-invoice',
                'url' => 'manage-invoice-list.php',
                'alt_urls' => ['manage-invoice-form.php'],
                'key' => 'manage_invoice'
            ],
        ]
    ],
    [
        'type' => 'group',
        'label' => 'Purchase',
        'icon' => 'ti ti-truck-delivery',
        'children' => [
            [
                'type' => 'item',
                'label' => 'Purchase Bill',
                'icon' => 'ti ti-file-dollar',
                'url' => 'manage-purchase-list.php',
                'alt_urls' => ['manage-purchase-form.php'],
                'key' => 'manage_purchase'
            ],
        ]
    ],
    [
        'type' => 'item',
        'label' => 'Manage Ledger',
        'icon' => 'ti ti-report-money',
        'url' => 'manage-ledger.php',
        'alt_urls' => ['ledger-print.php'],
        'key' => 'manage_ledger'
    ],
    [
        'type' => 'item',
        'label' => 'Templates',
        'icon' => 'ti ti-template',
        'url' => 'manage-invoice-template.php',
        'alt_urls' => ['manage-template-form.php'],
        'key' => 'manage_invoice_template'
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
