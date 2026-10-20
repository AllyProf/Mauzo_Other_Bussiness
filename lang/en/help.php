<?php

return [
    'center' => 'Help Center',
    'getting_started' => [
        'title' => 'Getting Started — System Usage Guide',
        'subtitle' => 'Optional walkthrough — use any step when you need it; nothing here is required to use the system.',
        'intro' => 'This guide walks you through setup in order: categories, packaging, staff, items, stock in, and sales. Nothing here is mandatory — skip any step you do not need.',
        'how_it_works_title' => 'How to use each step',
        'how_it_works_1' => 'Read where to find the screen in the menu (text path).',
        'how_it_works_2' => 'Click the highlighted item in the sidebar sketch — same place in your real sidebar.',
        'how_it_works_3' => 'On that page, follow the checklist. Use Open this screen to jump there in one click.',
        'recommended_order' => 'Suggested setup order',
        'step' => 'Step',
        'menu_path' => 'Where to find it',
        'what_to_do' => 'What to do',
        'open_screen' => 'Open this screen',
        'optional' => 'Optional',
        'menu_diagram_caption' => 'Sidebar sketch — click the yellow-bordered item (same in your app)',
        'diagram_opens_label' => 'This page opens',
        'diagram_mock_hint' => 'Your shop forms and lists appear here after you open the menu.',
        'then_on_that_page' => 'On that page, do this:',
        'no_access' => 'Your role does not include access to this screen. Ask the business owner to grant the permission or complete this step for you.',
        'retail_off' => 'This step applies to product / retail businesses. Service-only setups can skip it.',
        'also_see' => 'Also see',
        'help_articles' => 'Help articles',
        'support' => 'Contact support',
        'steps' => [
            'business_types' => [
                'title' => 'Choose business type & categories',
                'menu' => 'Sidebar → Inventory → Categories',
                'bullets' => [
                    'Open Categories and pick your business type (e.g. spare parts, pharmacy, general shop).',
                    'Use Import templates to load ready-made categories for that type, or create custom categories under a custom business type.',
                    'Add or edit categories before registering items so every product has a group.',
                ],
            ],
            'packaging' => [
                'title' => 'Create packaging & units',
                'menu' => 'Sidebar → Inventory → Packaging & Units',
                'bullets' => [
                    'Define how you sell and count stock: piece, box, carton, litre, etc.',
                    'Import common units from templates or add your own names and conversion rules.',
                    'You will choose these when registering items and when receiving stock.',
                ],
            ],
            'roles' => [
                'title' => 'Create roles & permissions',
                'menu' => 'Sidebar → Staff Management → Roles',
                'bullets' => [
                    'Create roles such as Cashier, Storekeeper, or Manager.',
                    'Tick only the permissions each role needs (sales, receiving, reports, settings, etc.).',
                    'Avoid giving every staff member full owner access.',
                ],
            ],
            'employees' => [
                'title' => 'Register employees',
                'menu' => 'Sidebar → Staff Management → Employees',
                'bullets' => [
                    'Add each staff member with login phone or email and assign a role.',
                    'Assign a branch if you use multiple branches.',
                    'Staff see only menus and actions allowed by their role.',
                ],
            ],
            'settings' => [
                'title' => 'Business settings & profile',
                'menu' => 'Sidebar → My Business → Business Settings',
                'bullets' => [
                    'Confirm shop name, contact details, currency, and receipt options.',
                    'Configure payment methods, shifts, and alerts your plan includes.',
                    'Review business types limit and plan features on the subscription tab.',
                ],
            ],
            'suppliers' => [
                'title' => 'Register suppliers (recommended)',
                'menu' => 'Sidebar → Inventory → Suppliers',
                'bullets' => [
                    'Add suppliers you buy stock from so receivings are linked to the right vendor.',
                    'Store phone and reference names for easier purchasing records.',
                ],
            ],
            'items' => [
                'title' => 'Register items (products)',
                'menu' => 'Sidebar → Inventory → Items',
                'bullets' => [
                    'Create each product with name, category, business type, selling price, and packaging.',
                    'Use SKU or barcode fields if you scan labels at POS.',
                    'Check Item Stock to see quantities after you receive goods.',
                ],
            ],
            'receiving' => [
                'title' => 'Receive stock (stock in)',
                'menu' => 'Sidebar → Inventory → Stock In',
                'bullets' => [
                    'Record goods arriving from suppliers; quantities increase in Item Stock.',
                    'Enter cost price when prompted so profit reports stay accurate.',
                    'You can receive against a supplier and branch where applicable.',
                ],
            ],
            'shifts' => [
                'title' => 'Open a sales shift (if your shop uses shifts)',
                'menu' => 'Sidebar → Sales → Sales Shifts',
                'bullets' => [
                    'Many businesses require opening a shift before POS sales.',
                    'Complete opening stock check when the system asks.',
                    'Close the shift at end of day and submit handover / reconciliation.',
                ],
            ],
            'sales' => [
                'title' => 'Sell at POS',
                'menu' => 'Sidebar → Sales → Store / POS (or Sales POS under Services)',
                'bullets' => [
                    'Search or scan items, set quantities, and take payment (cash, mobile, debt, etc.).',
                    'Print or share receipt if enabled in settings.',
                    'View sales history on the Store / Sales list page.',
                ],
            ],
            'reports' => [
                'title' => 'Reports & daily closing',
                'menu' => 'Sidebar → Reports & Analytics → Reports · Cash & Closing → Daily Reconciliation',
                'bullets' => [
                    'Use Reports for sales, profit, stock, and debts according to your permissions.',
                    'Run daily reconciliation / handover when staff finish a shift or day.',
                    'Owners use the dashboard for a quick summary before drilling into reports.',
                ],
            ],
        ],
    ],
];
