<?php

/**
 * In-page manual modal: route → screen key, wireframe layout, and pin positions (%).
 */
return [
    'routes' => [
        'categories.index' => [
            'key' => 'business_types',
            'wireframe' => 'categories_screenshot',
            'screenshots' => [
                'gp-assets/img/bussiness_category_01.png',
                'gp-assets/img/bussiness_category_image_02.png',
                'gp-assets/img/business_category_03.png',
            ],
        ],
        'packagings.index' => [
            'key' => 'packaging',
            'wireframe' => 'packaging_screenshot',
            'screenshot' => 'gp-assets/img/packaging.png',
        ],
        'roles.index' => ['key' => 'roles', 'wireframe' => 'list'],
        'employees.index' => ['key' => 'employees', 'wireframe' => 'list'],
        'settings.index' => ['key' => 'settings', 'wireframe' => 'settings'],
        'suppliers.index' => ['key' => 'suppliers', 'wireframe' => 'list'],
        'items.index' => ['key' => 'items', 'wireframe' => 'list'],
        'items.stock' => ['key' => 'items', 'wireframe' => 'list'],
        'items.create' => [
            'key' => 'items_create',
            'wireframe' => 'item_register_screenshot',
            'screenshot' => 'gp-assets/img/item register.png',
        ],
        'items.edit' => ['key' => 'items', 'wireframe' => 'form'],
        'receivings.index' => ['key' => 'receiving', 'wireframe' => 'list'],
        'receivings.create' => [
            'key' => 'receiving_create',
            'wireframe' => 'receiving_form',
            'screenshot' => 'gp-assets/img/stock-in.png',
        ],
        'shifts.index' => ['key' => 'shifts', 'wireframe' => 'list'],
        'sales.create' => [
            'key' => 'sales_pos_create',
            'wireframe' => 'pos_screenshot',
            'screenshot' => 'gp-assets/img/sales.png',
        ],
        'sales.index' => ['key' => 'sales', 'wireframe' => 'list'],
        'invoices.create' => [
            'key' => 'invoices_create',
            'wireframe' => 'invoice_create_screenshot',
            'screenshot' => 'gp-assets/img/invoice.png',
        ],
        'reports.index' => ['key' => 'reports', 'wireframe' => 'report'],
        'reports.daily-report' => [
            'key' => 'daily_report',
            'wireframe' => 'daily_report_screenshot',
            'screenshot' => 'gp-assets/img/report.png',
        ],
        'home' => ['key' => 'dashboard', 'wireframe' => 'dashboard'],
        'customers.index' => ['key' => 'customers', 'wireframe' => 'list'],
        'debts.index' => ['key' => 'debts', 'wireframe' => 'list'],
        'branches.index' => ['key' => 'branches', 'wireframe' => 'list'],
        'day-closing.index' => ['key' => 'day_closing', 'wireframe' => 'report'],
        'price-list.index' => ['key' => 'price_list', 'wireframe' => 'list'],
        'subscription.upgrade' => ['key' => 'subscription', 'wireframe' => 'settings'],
        'help.getting-started' => ['key' => 'system_guide', 'wireframe' => 'dashboard'],
    ],

    'prefix_routes' => [
        'reports.' => ['key' => 'reports', 'wireframe' => 'report'],
        'day-closing.' => ['key' => 'day_closing', 'wireframe' => 'report'],
        'customers.' => ['key' => 'customers', 'wireframe' => 'list'],
        'debts.' => ['key' => 'debts', 'wireframe' => 'list'],
        'invoices.' => ['key' => 'sales', 'wireframe' => 'list'],
    ],

    'wireframes' => [
        'categories' => [
            ['n' => 1, 'x' => 22, 'y' => 32],
            ['n' => 2, 'x' => 72, 'y' => 28],
            ['n' => 3, 'x' => 50, 'y' => 55],
            ['n' => 4, 'x' => 78, 'y' => 82],
        ],
        'categories_screenshot' => [
            ['n' => 1, 'x' => 48, 'y' => 38],
            ['n' => 2, 'x' => 22, 'y' => 88],
            ['n' => 3, 'x' => 18, 'y' => 72],
            ['n' => 4, 'x' => 62, 'y' => 48],
        ],
        'list' => [
            ['n' => 1, 'x' => 78, 'y' => 24],
            ['n' => 2, 'x' => 92, 'y' => 48],
            ['n' => 3, 'x' => 22, 'y' => 94],
            ['n' => 4, 'x' => 78, 'y' => 94],
        ],
        'form' => [
            ['n' => 1, 'x' => 50, 'y' => 24],
            ['n' => 2, 'x' => 50, 'y' => 44],
            ['n' => 3, 'x' => 50, 'y' => 64],
            ['n' => 4, 'x' => 78, 'y' => 88],
        ],
        'receiving_form' => [
            ['n' => 1, 'x' => 24, 'y' => 20],
            ['n' => 2, 'x' => 68, 'y' => 19],
            ['n' => 3, 'x' => 45, 'y' => 58],
            ['n' => 4, 'x' => 83, 'y' => 84],
        ],
        'settings' => [
            ['n' => 1, 'x' => 42, 'y' => 14],
            ['n' => 2, 'x' => 50, 'y' => 40],
            ['n' => 3, 'x' => 50, 'y' => 62],
            ['n' => 4, 'x' => 72, 'y' => 88],
        ],
        'report' => [
            ['n' => 1, 'x' => 28, 'y' => 24],
            ['n' => 2, 'x' => 58, 'y' => 24],
            ['n' => 3, 'x' => 50, 'y' => 55],
            ['n' => 4, 'x' => 82, 'y' => 24],
        ],
        'pos' => [
            ['n' => 1, 'x' => 35, 'y' => 20],
            ['n' => 2, 'x' => 35, 'y' => 50],
            ['n' => 3, 'x' => 78, 'y' => 55],
            ['n' => 4, 'x' => 78, 'y' => 82],
        ],
        'pos_screenshot' => [
            ['n' => 1, 'x' => 38, 'y' => 11],
            ['n' => 2, 'x' => 52, 'y' => 50],
            ['n' => 3, 'x' => 86, 'y' => 40],
            ['n' => 4, 'x' => 86, 'y' => 91],
        ],
        'item_register_screenshot' => [
            ['n' => 1, 'x' => 26, 'y' => 28],
            ['n' => 2, 'x' => 26, 'y' => 40],
            ['n' => 3, 'x' => 72, 'y' => 34],
            ['n' => 4, 'x' => 20, 'y' => 91],
        ],
        'dashboard' => [
            ['n' => 1, 'x' => 28, 'y' => 32],
            ['n' => 2, 'x' => 72, 'y' => 32],
            ['n' => 3, 'x' => 88, 'y' => 12],
            ['n' => 4, 'x' => 12, 'y' => 12],
        ],
        'daily_report_screenshot' => [
            ['n' => 1, 'x' => 14, 'y' => 11],
            ['n' => 2, 'x' => 34, 'y' => 11],
            ['n' => 3, 'x' => 50, 'y' => 52],
            ['n' => 4, 'x' => 88, 'y' => 11],
        ],
        'invoice_create_screenshot' => [
            ['n' => 1, 'x' => 22, 'y' => 14],
            ['n' => 2, 'x' => 68, 'y' => 22],
            ['n' => 3, 'x' => 48, 'y' => 48],
            ['n' => 4, 'x' => 82, 'y' => 91],
        ],
        'packaging_screenshot' => [
            ['n' => 1, 'x' => 16, 'y' => 28],
            ['n' => 2, 'x' => 28, 'y' => 38],
            ['n' => 3, 'x' => 20, 'y' => 68],
            ['n' => 4, 'x' => 72, 'y' => 52],
        ],
    ],
];
