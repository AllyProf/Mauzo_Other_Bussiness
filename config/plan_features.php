<?php

return [
    'groups' => [
        'Sales & Customers' => [
            'customers' => 'Customers Management',
            'debts' => 'Debt Management',
            'invoices' => 'Invoices',
            'services' => 'Services (catalog, service POS & service invoices)',
            'live_sales_pulse' => 'Live Sales Pulse',
        ],
        'Finance & Targets' => [
            'petty_cash' => 'Petty Cash Management',
            'sales_targets' => 'Sales Targets',
        ],
        'Operations' => [
            'branches' => 'Branches',
            'notes_reminders' => 'Notes & Reminders',
            'automation_reminders' => 'Automated Reminders (Settings)',
        ],
        'Reports' => [
            'reports_daily' => 'Daily Reports',
            'reports_expenses' => 'Expenses Reports',
            'reports_sales' => 'Sales Reports',
            'reports_products' => 'Products Reports',
            'reports_debts' => 'Debt Reports',
            'reports_profit' => 'Profit Reports',
            'reports_circulation' => 'Circulation vs Profit',
            'master_sheet' => 'Master Sheet',
        ],
        'Messaging' => [
            'customer_communication' => 'Customer SMS Communication',
        ],
    ],

    'routes' => [
        'customers' => ['customers.*'],
        'debts' => ['debts.*'],
        'invoices' => ['invoices.*'],
        'services' => [
            'services.*',
            'service-pos.*',
            'service-invoices.*',
        ],
        'live_sales_pulse' => ['live-sales.*'],
        'notes_reminders' => ['notes.*'],
        'petty_cash' => ['petty-cash.*'],
        'sales_targets' => ['sales-targets.*'],
        'branches' => ['branches.*'],
        'reports_daily' => ['reports.daily-sales', 'owner-reports.show', 'owner-reports.expenses.*'],
        'reports_expenses' => ['reports.expenses'],
        'reports_sales' => ['reports.sales-analytics'],
        'reports_products' => ['reports.products'],
        'reports_debts' => ['reports.debts'],
        'reports_profit' => ['reports.profit'],
        'reports_circulation' => ['reports.circulation-profit'],
        'master_sheet' => ['owner-reports.index', 'owner-reports.finalize'],
        'automation_reminders' => ['settings.automation.update'],
        'customer_communication' => ['customer-communications.*'],
    ],

    'exempt_routes' => [
        'home',
        'tickets.*',
        'subscription.upgrade',
        'subscription.expired',
        'settings.index',
        'settings.profile.update',
        'settings.finance.update',
        'settings.payment-methods.update',
        'settings.shift-rules.update',
        'businesses.switch',
        'branches.switch',
        'business.activity-log',
    ],
];
