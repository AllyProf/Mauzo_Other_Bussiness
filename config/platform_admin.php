<?php

return [
    'permission_groups' => [
        'Overview' => [
            'dashboard' => 'Platform Dashboard',
        ],
        'Business Management' => [
            'businesses' => 'Businesses, Plans & Broadcasts',
            'free-trials' => 'Free Trials',
            'onboarding' => 'Onboarding Checklists',
        ],
        'Billing & Analytics' => [
            'payments' => 'Payments & Invoices',
            'reports' => 'Platform Reports',
            'regional' => 'Regional Reports',
            'funnel' => 'Registration Funnel',
            'monitor' => 'Usage Monitor',
        ],
        'Support & Leads' => [
            'tickets' => 'Support Tickets',
            'leads' => 'Demo Leads',
            'audit-logs' => 'Activity Logs',
        ],
        'Security & Administration' => [
            'security' => 'Failed Logins & Security',
            'staff' => 'Manage Platform Staff',
            'platform_roles' => 'Manage Admin Roles',
            'sessions' => 'Admin Sessions',
            'settings' => 'System Settings',
        ],
    ],

    'route_permissions' => [
        'admin.dashboard' => 'dashboard',
        'admin.businesses.*' => 'businesses',
        'admin.plans.*' => 'businesses',
        'admin.broadcasts.*' => 'businesses',
        'admin.tickets.*' => 'tickets',
        'admin.payments.*' => 'payments',
        'admin.reports.*' => 'reports',
        'admin.audit-logs.*' => 'audit-logs',
        'admin.free-trials.*' => 'free-trials',
        'admin.settings.*' => 'settings',
        'admin.monitor.*' => 'monitor',
        'admin.regional.*' => 'regional',
        'admin.funnel.*' => 'funnel',
        'admin.leads.*' => 'leads',
        'admin.onboarding.*' => 'onboarding',
        'admin.security.*' => 'security',
        'admin.staff.*' => 'staff',
        'admin.platform-roles.*' => 'platform_roles',
        'admin.sessions.*' => 'sessions',
        'admin.impersonate' => 'businesses',
    ],

    // Legacy map used only when migrating old string roles on users.
    'roles' => [
        'full' => ['*'],
        'billing' => ['dashboard', 'businesses', 'payments', 'reports', 'free-trials', 'monitor', 'regional'],
        'support' => ['dashboard', 'tickets', 'businesses', 'audit-logs', 'leads', 'onboarding', 'monitor', 'security'],
        'readonly' => ['dashboard', 'reports', 'audit-logs', 'businesses', 'monitor', 'funnel', 'regional', 'leads'],
    ],
];
