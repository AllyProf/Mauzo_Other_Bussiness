<?php

/**
 * First-time usage guide — step order, routes, and menu diagram targets.
 */
return [
    'steps' => [
        [
            'key' => 'business_types',
            'route' => 'categories.index',
            'permission' => 'manage_categories',
            'retail' => true,
            'diagram' => ['group' => 'group_inventory', 'item' => 'categories'],
        ],
        [
            'key' => 'packaging',
            'route' => 'packagings.index',
            'permission' => 'manage_packaging',
            'retail' => true,
            'diagram' => ['group' => 'group_inventory', 'item' => 'packaging_units'],
        ],
        [
            'key' => 'roles',
            'route' => 'roles.index',
            'permission' => 'manage_staff',
            'diagram' => ['tree' => 'staff_management', 'item' => 'roles'],
        ],
        [
            'key' => 'employees',
            'route' => 'employees.index',
            'permission' => 'manage_staff',
            'diagram' => ['tree' => 'staff_management', 'item' => 'employees'],
        ],
        [
            'key' => 'settings',
            'route' => 'settings.index',
            'permission' => 'manage_business_settings',
            'diagram' => ['group' => 'group_my_business', 'item' => 'business_settings'],
        ],
        [
            'key' => 'suppliers',
            'route' => 'suppliers.index',
            'permission' => 'manage_suppliers',
            'retail' => true,
            'optional' => true,
            'diagram' => ['group' => 'group_inventory', 'item' => 'suppliers'],
        ],
        [
            'key' => 'items',
            'route' => 'items.index',
            'permission' => 'add_items',
            'retail' => true,
            'diagram' => ['group' => 'group_inventory', 'item' => 'items'],
        ],
        [
            'key' => 'receiving',
            'route' => 'receivings.index',
            'permission' => 'receive_stock',
            'retail' => true,
            'diagram' => ['group' => 'group_inventory', 'item' => 'stock_in'],
        ],
        [
            'key' => 'shifts',
            'route' => 'shifts.index',
            'permission' => 'open_shift',
            'diagram' => ['group' => 'group_sales', 'item' => 'sales_shifts'],
        ],
        [
            'key' => 'sales',
            'route' => 'sales.create',
            'permission' => 'process_sales',
            'diagram' => ['group' => 'group_sales', 'item' => 'store_pos'],
        ],
        [
            'key' => 'reports',
            'route' => 'reports.index',
            'permission' => null,
            'any_report' => true,
            'diagram' => ['group' => 'group_reports', 'item' => 'reports'],
        ],
    ],
];
