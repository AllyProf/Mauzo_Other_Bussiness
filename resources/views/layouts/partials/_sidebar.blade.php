<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
  <div class="app-sidebar__user">
    <div class="app-sidebar__user-avatar-container">
        <i class="fa fa-user-circle fa-3x text-white mr-3"></i>
    </div>
    <div>
      <p class="app-sidebar__user-name">{{ Auth::user()->name }}</p>
      <p class="app-sidebar__user-designation">{{ Auth::user()->sidebarDesignation() }}</p>
    </div>
  </div>
  <ul class="app-menu">
    <li><a class="app-menu__item {{ Request::is('home') || Request::is('admin') || Request::is('admin/dashboard*') ? 'active' : '' }}" href="{{ Auth::user()->isPlatformAdmin() ? route('admin.dashboard') : url('/home') }}"><i class="app-menu__icon fa fa-dashboard"></i><span class="app-menu__label">Dashboard</span></a></li>
    
    @if(!Auth::user()->isPlatformAdmin())
        <li><a class="app-menu__item {{ Request::is('notes*') ? 'active' : '' }}" href="{{ route('notes.index') }}"><i class="app-menu__icon fa fa-sticky-note"></i><span class="app-menu__label">Notes & Reminders</span></a></li>
    @endif

    @if(Auth::user()->isPlatformAdmin())
        @if(platform_admin_can('businesses'))
        <li class="treeview {{ Request::is('admin/businesses*') || Request::is('admin/plans*') ? 'is-expanded' : '' }}">
            <a class="app-menu__item" href="#" data-toggle="treeview">
                <i class="app-menu__icon fa fa-building"></i>
                <span class="app-menu__label">Businesses</span>
                <i class="treeview-indicator fa fa-angle-right"></i>
            </a>
            <ul class="treeview-menu" style="padding-left: 20px;">
                <li><a class="treeview-item" href="{{ route('admin.businesses.index') }}"><i class="icon fa fa-list"></i> All Businesses</a></li>
                <li><a class="treeview-item" href="{{ route('admin.plans.index') }}"><i class="icon fa fa-credit-card"></i> Subscriptions</a></li>
            </ul>
        </li>
        @endif
        @if(platform_admin_can('tickets'))
        <li><a class="app-menu__item {{ Request::is('admin/tickets*') ? 'active' : '' }}" href="{{ route('admin.tickets.index') }}">
            <i class="app-menu__icon fa fa-ticket"></i><span class="app-menu__label">Support Tickets</span>
            @if(!empty($unreadAdminTickets) && $unreadAdminTickets > 0)
            <span class="badge badge-danger ml-1">{{ $unreadAdminTickets }}</span>
            @endif
        </a></li>
        @endif
        @if(platform_admin_can('payments'))
        <li><a class="app-menu__item {{ Request::is('admin/payments*') ? 'active' : '' }}" href="{{ route('admin.payments.index') }}"><i class="app-menu__icon fa fa-money"></i><span class="app-menu__label">Payments</span></a></li>
        @endif
        @if(platform_admin_can('monitor'))
        <li><a class="app-menu__item {{ Request::is('admin/monitor*') ? 'active' : '' }}" href="{{ route('admin.monitor.index') }}"><i class="app-menu__icon fa fa-heartbeat"></i><span class="app-menu__label">Usage Monitor</span></a></li>
        @endif
        @if(platform_admin_can('reports'))
        <li><a class="app-menu__item {{ Request::is('admin/reports*') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}"><i class="app-menu__icon fa fa-bar-chart"></i><span class="app-menu__label">Reports</span></a></li>
        @endif
        @if(platform_admin_can('regional'))
        <li><a class="app-menu__item {{ Request::is('admin/regional*') ? 'active' : '' }}" href="{{ route('admin.regional.index') }}"><i class="app-menu__icon fa fa-map-marker"></i><span class="app-menu__label">Regional Report</span></a></li>
        @endif
        @if(platform_admin_can('funnel'))
        <li><a class="app-menu__item {{ Request::is('admin/funnel*') ? 'active' : '' }}" href="{{ route('admin.funnel.index') }}"><i class="app-menu__icon fa fa-filter"></i><span class="app-menu__label">Registration Funnel</span></a></li>
        @endif
        @if(platform_admin_can('leads'))
        <li><a class="app-menu__item {{ Request::is('admin/leads*') ? 'active' : '' }}" href="{{ route('admin.leads.index') }}"><i class="app-menu__icon fa fa-envelope"></i><span class="app-menu__label">Demo Leads</span></a></li>
        @endif
        @if(platform_admin_can('audit-logs'))
        <li><a class="app-menu__item {{ Request::is('admin/audit-logs*') ? 'active' : '' }}" href="{{ route('admin.audit-logs.index') }}"><i class="app-menu__icon fa fa-history"></i><span class="app-menu__label">Activity Logs</span></a></li>
        @endif
        @if(platform_admin_can('free-trials'))
        <li><a class="app-menu__item {{ Request::is('admin/free-trials*') ? 'active' : '' }}" href="{{ route('admin.free-trials.index') }}"><i class="app-menu__icon fa fa-hourglass-half"></i><span class="app-menu__label">Free Trials</span></a></li>
        @endif
        @if(platform_admin_can('businesses'))
        <li><a class="app-menu__item {{ Request::is('admin/broadcasts*') ? 'active' : '' }}" href="{{ route('admin.broadcasts.index') }}"><i class="app-menu__icon fa fa-bullhorn"></i><span class="app-menu__label">System Broadcasts</span></a></li>
        @endif
        @if(platform_admin_can('security'))
        <li class="treeview {{ Request::is('admin/security*') || Request::is('admin/staff*') || Request::is('admin/sessions*') ? 'is-expanded' : '' }}">
            <a class="app-menu__item" href="#" data-toggle="treeview">
                <i class="app-menu__icon fa fa-shield"></i>
                <span class="app-menu__label">Security</span>
                <i class="treeview-indicator fa fa-angle-right"></i>
            </a>
            <ul class="treeview-menu" style="padding-left: 20px;">
                <li><a class="treeview-item" href="{{ route('admin.security.failed-logins') }}"><i class="icon fa fa-exclamation-triangle"></i> Failed Logins</a></li>
                <li><a class="treeview-item" href="{{ route('admin.staff.index') }}"><i class="icon fa fa-users"></i> Platform Staff</a></li>
                @if(platform_admin_can('platform_roles'))
                <li><a class="treeview-item" href="{{ route('admin.platform-roles.index') }}"><i class="icon fa fa-shield"></i> Admin Roles</a></li>
                @endif
                <li><a class="treeview-item" href="{{ route('admin.sessions.index') }}"><i class="icon fa fa-desktop"></i> Admin Sessions</a></li>
            </ul>
        </li>
        @endif
        @if(platform_admin_can('settings'))
        <li><a class="app-menu__item {{ Request::is('admin/settings*') ? 'active' : '' }}" href="{{ route('admin.settings.index') }}"><i class="app-menu__icon fa fa-gears"></i><span class="app-menu__label">System Settings</span></a></li>
        @endif
    @else
        @if(business_retail_enabled())
        @can('view_inventory')
        <li class="treeview {{ Request::is('items*') ? 'is-expanded' : '' }}">
            <a class="app-menu__item" href="#" data-toggle="treeview">
                <i class="app-menu__icon fa fa-laptop"></i>
                <span class="app-menu__label">Registration</span>
                <i class="treeview-indicator fa fa-angle-right"></i>
            </a>
            <ul class="treeview-menu" style="padding-left: 20px;">
                <li><a class="treeview-item {{ Request::is('items') && !Request::is('items/stock') ? 'active' : '' }}" href="{{ route('items.index') }}"><i class="icon fa fa-barcode"></i> Items</a></li>
                <li><a class="treeview-item {{ Request::is('items/stock') ? 'active' : '' }}" href="{{ route('items.stock') }}"><i class="icon fa fa-cubes"></i> Item Stock</a></li>
                <li><a class="treeview-item {{ Request::is('categories*') ? 'active' : '' }}" href="{{ route('categories.index') }}"><i class="icon fa fa-list"></i> Categories</a></li>
                <li><a class="treeview-item {{ Request::is('packagings*') ? 'active' : '' }}" href="{{ route('packagings.index') }}"><i class="icon fa fa-archive"></i> Packaging & Units</a></li>
                @can('manage_suppliers')
                <li><a class="treeview-item {{ Request::is('suppliers*') ? 'active' : '' }}" href="{{ route('suppliers.index') }}"><i class="icon fa fa-truck"></i> Suppliers</a></li>
                @endcan
            </ul>
        </li>
        @endcan
        @endif

        @if(business_retail_enabled())
        @can('receive_stock')
        <li><a class="app-menu__item {{ Request::is('receivings*') ? 'active' : '' }}" href="{{ route('receivings.index') }}"><i class="app-menu__icon fa fa-truck"></i><span class="app-menu__label">Receiving</span></a></li>
        @endcan
        @canany(['record_stock_loss', 'view_stock_history', 'open_shift', 'process_sales'])
        <li><a class="app-menu__item {{ Request::is('stock-losses*') ? 'active' : '' }}" href="{{ route('stock-losses.index') }}"><i class="app-menu__icon fa fa-minus-circle"></i><span class="app-menu__label">Stock Losses</span></a></li>
        @endcanany
        @endif

        @canany(['open_shift', 'process_sales', 'view_all_shifts'])
        <li><a class="app-menu__item {{ Request::is('shifts*') ? 'active' : '' }}" href="{{ route('shifts.index') }}"><i class="app-menu__icon fa fa-clock-o"></i><span class="app-menu__label">Sales Shifts</span></a></li>
        @endcanany
        
        @canany(['process_sales', 'view_sales_history'])
        @if(business_retail_enabled())
        <li><a class="app-menu__item {{ Request::is('sales*') && !Request::is('service-pos*') ? 'active' : '' }}" href="{{ route('sales.index') }}"><i class="app-menu__icon fa fa-shopping-cart"></i><span class="app-menu__label">Store / POS</span></a></li>
        @endif
        @if(business_services_menu_visible())
        <li><a class="app-menu__item {{ Request::is('services*') || Request::is('service-pos*') || Request::is('service-invoices*') ? 'active' : '' }}" href="{{ route('services.index') }}"><i class="app-menu__icon fa fa-briefcase"></i><span class="app-menu__label">Services</span></a></li>
        @endif
        @endcanany
        @canany(['view_invoices', 'create_invoices', 'collect_invoice_payments', 'process_sales', 'view_sales_history'])
        @if(plan_feature('invoices'))
        <li><a class="app-menu__item {{ Request::is('invoices*') ? 'active' : '' }}" href="{{ route('invoices.index') }}"><i class="app-menu__icon fa fa-file-text-o"></i><span class="app-menu__label">Invoices</span></a></li>
        @endif
        @endcanany
        @canany(['manage_debts', 'process_sales', 'collect_payments'])
        @if(plan_feature('debts'))
        <li class="treeview {{ Request::is('debts*') ? 'is-expanded' : '' }}">
            <a class="app-menu__item" href="#" data-toggle="treeview">
                <i class="app-menu__icon fa fa-credit-card"></i>
                <span class="app-menu__label">Debts</span>
                <i class="treeview-indicator fa fa-angle-right"></i>
            </a>
            <ul class="treeview-menu" style="padding-left: 20px;">
                <li><a class="treeview-item {{ Request::is('debts') && !Request::is('debts/history*') ? 'active' : '' }}" href="{{ route('debts.index') }}"><i class="icon fa fa-exclamation-circle"></i> Outstanding</a></li>
                <li><a class="treeview-item {{ Request::is('debts/history*') ? 'active' : '' }}" href="{{ route('debts.history') }}"><i class="icon fa fa-history"></i> History</a></li>
            </ul>
        </li>
        @endif
        @endcanany
        @can('manage_customers')
        @if(plan_feature('customers'))
        <li><a class="app-menu__item {{ Request::is('customers*') ? 'active' : '' }}" href="{{ route('customers.index') }}"><i class="app-menu__icon fa fa-address-book"></i><span class="app-menu__label">Customers</span></a></li>
        @endif
        @if(plan_feature('customer_communication'))
        <li><a class="app-menu__item {{ Request::is('customer-communications*') ? 'active' : '' }}" href="{{ route('customer-communications.index') }}"><i class="app-menu__icon fa fa-commenting"></i><span class="app-menu__label">Customer Comms</span></a></li>
        @endif
        @endcan

        @can('manage_staff')
        <li class="treeview {{ Request::is('employees*') || Request::is('roles*') ? 'is-expanded' : '' }}">
            <a class="app-menu__item" href="#" data-toggle="treeview">
                <i class="app-menu__icon fa fa-users"></i>
                <span class="app-menu__label">Staff Management</span>
                <i class="treeview-indicator fa fa-angle-right"></i>
            </a>
            <ul class="treeview-menu" style="padding-left: 20px;">
                <li><a class="treeview-item {{ Request::is('employees*') ? 'active' : '' }}" href="{{ route('employees.index') }}"><i class="icon fa fa-user"></i> Employees</a></li>
                <li><a class="treeview-item {{ Request::is('roles*') ? 'active' : '' }}" href="{{ route('roles.index') }}"><i class="icon fa fa-shield"></i> Roles</a></li>
            </ul>
        </li>
        @endcan

        @canany(['submit_day_closing', 'verify_day_closing', 'process_sales'])
        @php
            $sidebarShift = null;
            if (Auth::user()->requiresOpenShift()) {
                $sidebarShift = \App\Models\Shift::latestClosedAwaitingHandover(Auth::id(), Auth::user()->business_id)
                    ?? \App\Models\Shift::openForUser(Auth::id(), Auth::user()->business_id);
            }
        @endphp
        <li><a class="app-menu__item {{ Request::is('day-closing') && !Request::is('day-closing/history*') ? 'active' : '' }}" href="{{ $sidebarShift ? route('day-closing.index', ['shift' => $sidebarShift->id]) : route('day-closing.index') }}"><i class="app-menu__icon fa fa-balance-scale"></i><span class="app-menu__label">Daily Reconciliation</span></a></li>
        @if(Auth::user()->role === 'owner')
        <li><a class="app-menu__item {{ Request::is('money-shorts*') ? 'active' : '' }}" href="{{ route('money-shorts.index') }}"><i class="app-menu__icon fa fa-money"></i><span class="app-menu__label">Money Shorts</span></a></li>
        @endif
        @endcanany

        @canany(['verify_stock_shortages', 'view_reports'])
        <li><a class="app-menu__item {{ Request::is('shifts/stock-shortages*') ? 'active' : '' }}" href="{{ route('stock-shortages.index') }}"><i class="app-menu__icon fa fa-warning"></i><span class="app-menu__label">Stock Shortages</span></a></li>
        @endcanany
        @canany(['view_reports', 'verify_day_closing', 'finalize_reports'])
        @if(plan_feature_any(['reports_daily', 'reports_expenses', 'reports_sales', 'reports_products', 'reports_debts', 'reports_profit', 'reports_circulation', 'master_sheet']))
        <li class="treeview {{ Request::is('reports*') || Request::is('owner-reports*') ? 'is-expanded' : '' }}">
            <a class="app-menu__item" href="#" data-toggle="treeview">
                <i class="app-menu__icon fa fa-bar-chart"></i>
                <span class="app-menu__label">Reports</span>
                <i class="treeview-indicator fa fa-angle-right"></i>
            </a>
            <ul class="treeview-menu" style="padding-left: 20px;">
                @if(plan_feature('reports_circulation'))
                <li><a class="treeview-item {{ Request::is('reports/circulation-profit') ? 'active' : '' }}" href="{{ route('reports.circulation-profit') }}"><i class="icon fa fa-exchange"></i> Circulation vs Profit</a></li>
                @endif
                @if(plan_feature('reports_daily'))
                <li><a class="treeview-item {{ Request::is('reports/daily-sales') ? 'active' : '' }}" href="{{ route('reports.daily-sales') }}"><i class="icon fa fa-calendar"></i> Daily Sales</a></li>
                @endif
                @if(plan_feature('reports_expenses'))
                <li><a class="treeview-item {{ Request::is('reports/expenses') ? 'active' : '' }}" href="{{ route('reports.expenses') }}"><i class="icon fa fa-minus-circle"></i> Expenses</a></li>
                @endif
                @if(plan_feature('reports_profit'))
                <li><a class="treeview-item {{ Request::is('reports/profit') ? 'active' : '' }}" href="{{ route('reports.profit') }}"><i class="icon fa fa-line-chart"></i> Profit</a></li>
                @endif
                @if(plan_feature('reports_sales'))
                <li><a class="treeview-item {{ Request::is('reports/sales-analytics') ? 'active' : '' }}" href="{{ route('reports.sales-analytics') }}"><i class="icon fa fa-bar-chart"></i> Sales Analytics</a></li>
                @endif
                @if(plan_feature('reports_products'))
                <li><a class="treeview-item {{ Request::is('reports/products') ? 'active' : '' }}" href="{{ route('reports.products') }}"><i class="icon fa fa-cubes"></i> Products</a></li>
                @endif
                @if(plan_feature('reports_debts'))
                <li><a class="treeview-item {{ Request::is('reports/debts') ? 'active' : '' }}" href="{{ route('reports.debts') }}"><i class="icon fa fa-credit-card"></i> Debt Report</a></li>
                @endif
                @if(plan_feature('master_sheet'))
                <li><a class="treeview-item {{ Request::is('owner-reports*') ? 'active' : '' }}" href="{{ route('owner-reports.index') }}"><i class="icon fa fa-list-alt"></i> Master Sheet</a></li>
                @endif
            </ul>
        </li>
        @endif
        @endcanany
        @can('manage_petty_cash')
        @if(plan_feature('petty_cash'))
        <li><a class="app-menu__item {{ Request::is('petty-cash*') ? 'active' : '' }}" href="{{ route('petty-cash.index') }}"><i class="app-menu__icon fa fa-money"></i><span class="app-menu__label">Petty Cash</span></a></li>
        @endif
        @endcan
        @canany(['view_closing_history', 'view_reports', 'verify_day_closing'])
        <li><a class="app-menu__item {{ Request::is('day-closing/history') ? 'active' : '' }}" href="{{ route('day-closing.history') }}"><i class="app-menu__icon fa fa-file-text"></i><span class="app-menu__label">Closing History</span></a></li>
        @endcanany
        @can('manage_branches')
        @if(plan_feature('branches'))
        <li><a class="app-menu__item {{ Request::is('branches*') ? 'active' : '' }}" href="{{ route('branches.index') }}"><i class="app-menu__icon fa fa-building"></i><span class="app-menu__label">Branches</span></a></li>
        @endif
        @endcan
        @can('manage_business_settings')
        @if(plan_feature('sales_targets'))
        <li><a class="app-menu__item {{ Request::is('sales-targets*') ? 'active' : '' }}" href="{{ route('sales-targets.index') }}"><i class="app-menu__icon fa fa-bullseye"></i><span class="app-menu__label">Sales Targets</span></a></li>
        @endif
        @endcan
        @canany(['manage_business_settings', 'manage_payment_methods'])
        <li><a class="app-menu__item {{ Request::is('settings*') ? 'active' : '' }}" href="{{ route('settings.index') }}"><i class="app-menu__icon fa fa-gears"></i><span class="app-menu__label">Business Settings</span></a></li>
        @endcanany
        <hr style="border-top: 1px solid rgba(255,255,255,0.1);">
        @if(in_array(Auth::user()->role, ['owner', 'staff'], true))
        <li><a class="app-menu__item {{ Request::is('subscription/upgrade*') ? 'active' : '' }}" href="{{ route('subscription.upgrade') }}"><i class="app-menu__icon fa fa-level-up"></i><span class="app-menu__label">Upgrade Plan</span></a></li>
        @endif
        @can('view_audit_logs')
        <li><a class="app-menu__item {{ Request::is('activity-log*') ? 'active' : '' }}" href="{{ route('business.activity-log') }}"><i class="app-menu__icon fa fa-history"></i><span class="app-menu__label">Activity Log</span></a></li>
        @endcan
        <li><a class="app-menu__item {{ Request::is('support*') ? 'active' : '' }}" href="{{ route('tickets.index') }}"><i class="app-menu__icon fa fa-life-ring"></i><span class="app-menu__label">My Support</span></a></li>
    @endif
  </ul>
</aside>
