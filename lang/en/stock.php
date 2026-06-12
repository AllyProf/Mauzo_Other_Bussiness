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
        'expected_revenue' => 'Expected Revenue',
        'expected_profit' => 'Expected Profit',
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
        'total_on_hand' => ':count pcs on hand',
        'selling_prices' => 'Selling Prices',
        'selling_price' => 'Selling Price',
        'not_set' => 'Not set',
        'holding_value' => 'Holding Value:',
        'expected_revenue' => 'Expected Revenue:',
        'expected_profit' => 'Expected Profit:',
        'view_history' => 'View History',
    ],

    'status' => [
        'low_stock' => 'Low Stock',
        'in_stock' => 'In Stock',
    ],

    'summary' => [
        'total_value' => 'Total Inventory Value',
        'totals_title' => 'Inventory Financial Summary',
        'total_value_hint' => 'If all current stock is sold at retail prices (revenue minus cost = profit)',
    ],

    'empty' => [
        'branch_title' => 'No in-stock items for :branch.',
        'branch_text' => 'No items with stock belong to categories assigned to this branch yet. Switch branch in the header, assign categories to this branch, or record a stock-in.',
        'general_title' => 'No in-stock items to display.',
        'general_text' => 'Items with zero stock or no category assigned are hidden here. Assign categories on the Items page, or record stock-in for empty items.',
    ],

    'export' => [
        'pdf' => 'Export PDF',
        'excel' => 'Export Excel',
        'empty' => 'No in-stock items to export.',
        'sheet_title' => 'Stock Report',
        'report_title' => 'Stock Valuation & Selling Report',
        'inventory_snapshot' => 'Current Inventory Snapshot',
        'report_information' => 'Report Information',
        'selling_and_stock' => 'Selling Configuration & Stock',
        'branch_label' => 'Branch: :branch',
        'all_branches' => 'All Branches',
        'date_label' => 'Generated: :date',
        'prepared_by' => 'Prepared by: :name',
        'low_stock_threshold' => 'Low Stock Threshold',
        'total_cost_value' => 'Total Stock Cost',
        'total_margin' => 'Estimated Margin',
        'category_subtotal' => ':category — Subtotal',
        'grand_total' => 'Grand Total (All Items)',
        'amounts_in_tzs' => 'All amounts below are in TZS (Tanzanian Shillings).',
        'footer_note' => 'Expected revenue = stock × retail price per piece. Expected profit = revenue minus stock cost. Category subtotals and grand totals summarize all items if fully sold.',
        'col_item' => 'Item',
        'col_sku' => 'SKU',
        'col_brand' => 'Brand',
        'col_stock' => 'Stock',
        'col_unit' => 'Unit',
        'col_packaging' => 'Packaging',
        'col_pack_size' => 'Pack Size',
        'col_sell_price' => 'Selling Price',
        'col_price_per_piece' => 'Price / Piece',
        'col_stock_value' => 'Stock Value',
        'col_expected_revenue' => 'Expected Revenue',
        'col_expected_profit' => 'Expected Profit',
        'col_status' => 'Status',
        'col_cost_per_piece' => 'Cost / Piece',
        'col_stock_cost' => 'Stock Cost',
        'col_margin' => 'Margin',
        'col_margin_pct' => 'Margin %',
    ],
];
