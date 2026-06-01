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
    <li><a class="app-menu__item {{ Request::is('home') ? 'active' : '' }}" href="{{ url('/home') }}"><i class="app-menu__icon fa fa-dashboard"></i><span class="app-menu__label">Dashboard</span></a></li>
    
    @if(Auth::user()->role != 'super_admin')
        <li><a class="app-menu__item {{ Request::is('notes*') ? 'active' : '' }}" href="{{ route('notes.index') }}"><i class="app-menu__icon fa fa-sticky-note"></i><span class="app-menu__label">Notes & Reminders</span></a></li>
    @endif

    @if(Auth::user()->role == 'super_admin')
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
        <li><a class="app-menu__item {{ Request::is('admin/broadcasts*') ? 'active' : '' }}" href="{{ route('admin.broadcasts.index') }}"><i class="app-menu__icon fa fa-bullhorn"></i><span class="app-menu__label">System Broadcasts</span></a></li>
        <li><a class="app-menu__item {{ Request::is('admin/tickets*') ? 'active' : '' }}" href="{{ route('admin.tickets.index') }}"><i class="app-menu__icon fa fa-ticket"></i><span class="app-menu__label">Support Tickets</span></a></li>
        <li><a class="app-menu__item {{ Request::is('admin/audit-logs*') ? 'active' : '' }}" href="{{ route('admin.audit-logs.index') }}"><i class="app-menu__icon fa fa-history"></i><span class="app-menu__label">Audit Logs</span></a></li>
        <li><a class="app-menu__item {{ Request::is('admin/free-trials*') ? 'active' : '' }}" href="{{ route('admin.free-trials.index') }}"><i class="app-menu__icon fa fa-hourglass-half"></i><span class="app-menu__label">Free Trials</span></a></li>
        <li><a class="app-menu__item {{ Request::is('admin/settings*') ? 'active' : '' }}" href="{{ route('admin.settings.index') }}"><i class="app-menu__icon fa fa-gears"></i><span class="app-menu__label">System Settings</span></a></li>
    @else
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

        @can('receive_stock')
        <li><a class="app-menu__item {{ Request::is('receivings*') ? 'active' : '' }}" href="{{ route('receivings.index') }}"><i class="app-menu__icon fa fa-truck"></i><span class="app-menu__label">Receiving</span></a></li>
        @endcan
        @canany(['record_stock_loss', 'view_stock_history'])
        <li><a class="app-menu__item {{ Request::is('stock-losses*') ? 'active' : '' }}" href="{{ route('stock-losses.index') }}"><i class="app-menu__icon fa fa-minus-circle"></i><span class="app-menu__label">Stock Losses</span></a></li>
        @endcanany

        @canany(['open_shift', 'process_sales', 'view_all_shifts'])
        <li><a class="app-menu__item {{ Request::is('shifts*') ? 'active' : '' }}" href="{{ route('shifts.index') }}"><i class="app-menu__icon fa fa-clock-o"></i><span class="app-menu__label">Sales Shifts</span></a></li>
        @endcanany
        
        @canany(['process_sales', 'view_sales_history'])
        <li><a class="app-menu__item {{ Request::is('sales*') ? 'active' : '' }}" href="{{ route('sales.index') }}"><i class="app-menu__icon fa fa-shopping-cart"></i><span class="app-menu__label">Store / POS</span></a></li>
        @endcanany
        @canany(['view_invoices', 'create_invoices', 'collect_invoice_payments', 'process_sales', 'view_sales_history'])
        <li><a class="app-menu__item {{ Request::is('invoices*') ? 'active' : '' }}" href="{{ route('invoices.index') }}"><i class="app-menu__icon fa fa-file-text-o"></i><span class="app-menu__label">Invoices</span></a></li>
        @endcanany
        @canany(['manage_debts', 'process_sales', 'collect_payments'])
        <li><a class="app-menu__item {{ Request::is('debts*') ? 'active' : '' }}" href="{{ route('debts.index') }}"><i class="app-menu__icon fa fa-credit-card"></i><span class="app-menu__label">Debt Management</span></a></li>
        @endcanany
        @can('manage_customers')
        <li><a class="app-menu__item {{ Request::is('customers*') ? 'active' : '' }}" href="{{ route('customers.index') }}"><i class="app-menu__icon fa fa-address-book"></i><span class="app-menu__label">Customers</span></a></li>
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
        @endcanany

        @canany(['verify_stock_shortages', 'view_reports'])
        <li><a class="app-menu__item {{ Request::is('shifts/stock-shortages*') ? 'active' : '' }}" href="{{ route('stock-shortages.index') }}"><i class="app-menu__icon fa fa-warning"></i><span class="app-menu__label">Stock Shortages</span></a></li>
        @endcanany
        @canany(['view_reports', 'verify_day_closing', 'finalize_reports'])
        <li class="treeview {{ Request::is('reports*') || Request::is('owner-reports*') ? 'is-expanded' : '' }}">
            <a class="app-menu__item" href="#" data-toggle="treeview">
                <i class="app-menu__icon fa fa-bar-chart"></i>
                <span class="app-menu__label">Reports</span>
                <i class="treeview-indicator fa fa-angle-right"></i>
            </a>
            <ul class="treeview-menu" style="padding-left: 20px;">
                <li><a class="treeview-item {{ Request::is('reports/circulation-profit') ? 'active' : '' }}" href="{{ route('reports.circulation-profit') }}"><i class="icon fa fa-exchange"></i> Circulation vs Profit</a></li>
                <li><a class="treeview-item {{ Request::is('reports/daily-sales') ? 'active' : '' }}" href="{{ route('reports.daily-sales') }}"><i class="icon fa fa-calendar"></i> Daily Sales</a></li>
                <li><a class="treeview-item {{ Request::is('reports/expenses') ? 'active' : '' }}" href="{{ route('reports.expenses') }}"><i class="icon fa fa-minus-circle"></i> Expenses</a></li>
                <li><a class="treeview-item {{ Request::is('reports/profit') ? 'active' : '' }}" href="{{ route('reports.profit') }}"><i class="icon fa fa-line-chart"></i> Profit</a></li>
                <li><a class="treeview-item {{ Request::is('reports/sales-analytics') ? 'active' : '' }}" href="{{ route('reports.sales-analytics') }}"><i class="icon fa fa-bar-chart"></i> Sales Analytics</a></li>
                <li><a class="treeview-item {{ Request::is('reports/products') ? 'active' : '' }}" href="{{ route('reports.products') }}"><i class="icon fa fa-cubes"></i> Products</a></li>
                <li><a class="treeview-item {{ Request::is('reports/debts') ? 'active' : '' }}" href="{{ route('reports.debts') }}"><i class="icon fa fa-credit-card"></i> Debt Report</a></li>
                <li><a class="treeview-item {{ Request::is('owner-reports*') ? 'active' : '' }}" href="{{ route('owner-reports.index') }}"><i class="icon fa fa-list-alt"></i> Master Sheet</a></li>
            </ul>
        </li>
        @endcanany
        @can('manage_petty_cash')
        <li><a class="app-menu__item {{ Request::is('petty-cash*') ? 'active' : '' }}" href="{{ route('petty-cash.index') }}"><i class="app-menu__icon fa fa-money"></i><span class="app-menu__label">Petty Cash</span></a></li>
        @endcan
        @canany(['view_closing_history', 'view_reports', 'verify_day_closing'])
        <li><a class="app-menu__item {{ Request::is('day-closing/history') ? 'active' : '' }}" href="{{ route('day-closing.history') }}"><i class="app-menu__icon fa fa-file-text"></i><span class="app-menu__label">Closing History</span></a></li>
        @endcanany
        @can('manage_branches')
        <li><a class="app-menu__item {{ Request::is('branches*') ? 'active' : '' }}" href="{{ route('branches.index') }}"><i class="app-menu__icon fa fa-building"></i><span class="app-menu__label">Branches</span></a></li>
        @endcan
        @can('manage_business_settings')
        <li><a class="app-menu__item {{ Request::is('sales-targets*') ? 'active' : '' }}" href="{{ route('sales-targets.index') }}"><i class="app-menu__icon fa fa-bullseye"></i><span class="app-menu__label">Sales Targets</span></a></li>
        @endcan
        @canany(['manage_business_settings', 'manage_payment_methods'])
        <li><a class="app-menu__item {{ Request::is('settings*') ? 'active' : '' }}" href="{{ route('settings.index') }}"><i class="app-menu__icon fa fa-gears"></i><span class="app-menu__label">Business Settings</span></a></li>
        @endcanany
        <hr style="border-top: 1px solid rgba(255,255,255,0.1);">
        @can('manage_support')
        <li><a class="app-menu__item {{ Request::is('support*') ? 'active' : '' }}" href="{{ route('tickets.index') }}"><i class="app-menu__icon fa fa-question-circle"></i><span class="app-menu__label">Support Center</span></a></li>
        @endcan
    @endif
  </ul>
</aside>
