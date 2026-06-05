<?php

return [
    'title' => 'Item Stock',
    'subtitle' => 'View current inventory in detail',
    'breadcrumb_stock' => 'Stock',

    'stats' => [
        'total_items' => 'Total Items',
        'low_stock_items' => 'Low Stock Items',
        'from_settings' => '(≤ :count from settings)',
        'total_stock_value' => 'Total Stock Value',
    ],

    'all' => 'All',
    'new_stock_in' => 'New Stock-In',
    'back' => 'Back',
    'record_stock_in' => 'Record Stock-In',

    'branch' => [
        'showing_for' => 'Showing stock for :branch — items in categories assigned to this branch. Business type tabs reflect this branch only.',
        'viewing_all' => 'Viewing all branches. Switch branch in the header to filter by branch.',
    ],

    'search' => [
        'label' => 'Search Items',
        'placeholder' => 'Search by name, SKU, brand...',
    ],

    'filters' => [
        'quick_categories' => 'Quick Filters (Categories)',
        'all_items' => 'ALL ITEMS',
        'low_stock' => 'LOW STOCK',
    ],

    'card' => [
        'low_stock' => 'LOW STOCK',
        'available' => 'Available',
        'pieces' => 'Pieces',
        'pcs' => 'pcs',
        'pcs_each' => 'pcs each',
        'received_at_branch' => 'Received at branch:',
        'selling_prices' => 'Selling Prices',
        'selling_price' => 'Selling Price',
        'not_set' => 'Not set',
        'holding_value' => 'Holding Value:',
        'view_history' => 'View History',
    ],

    'status' => [
        'low_stock' => 'Low Stock',
        'in_stock' => 'In Stock',
    ],

    'summary' => [
        'total_value' => 'Total Inventory Value',
        'total_value_hint' => 'Estimated value based on current selling prices',
    ],

    'empty' => [
        'branch_title' => 'No in-stock items for :branch.',
        'branch_text' => 'No items with stock belong to categories assigned to this branch yet. Switch branch in the header, assign categories to this branch, or record a stock-in.',
        'general_title' => 'No in-stock items to display.',
        'general_text' => 'Items with zero stock or no category assigned are hidden here. Assign categories on the Items page, or record stock-in for empty items.',
    ],
];
