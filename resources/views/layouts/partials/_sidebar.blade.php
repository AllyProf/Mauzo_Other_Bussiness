<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
  <div class="app-sidebar__user" data-tour="sidebar-profile">
    <div class="app-sidebar__user-avatar-container">
        @if(Auth::user()->profileImageUrl())
        <img class="app-sidebar__user-avatar" src="{{ Auth::user()->profileImageUrl() }}" alt="{{ Auth::user()->name }}">
        @else
        <span class="app-sidebar__user-initial">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(Auth::user()->name, 0, 1)) }}</span>
        @endif
    </div>
    <div>
      <p class="app-sidebar__user-name">{{ Auth::user()->name }}</p>
      <p class="app-sidebar__user-designation">{{ Auth::user()->sidebarDesignation() }}</p>
    </div>
  </div>
  <ul class="app-menu">
    <li><a class="app-menu__item {{ Request::is('home') || Request::is('admin') || Request::is('admin/dashboard*') ? 'active' : '' }}" href="{{ Auth::user()->isPlatformAdmin() ? route('admin.dashboard') : url('/home') }}" data-tour="menu-dashboard"><i class="app-menu__icon fa fa-dashboard"></i><span class="app-menu__label">{{ __('menu.dashboard') }}</span></a></li>

    @if(Auth::user()->isPlatformAdmin())
        @if(platform_admin_can('businesses'))
        <li class="treeview {{ Request::is('admin/businesses*') || Request::is('admin/plans*') ? 'is-expanded' : '' }}">
            <a class="app-menu__item" href="#" data-toggle="treeview">
                <i class="app-menu__icon fa fa-building"></i>
                <span class="app-menu__label">{{ __('menu.businesses') }}</span>
                <i class="treeview-indicator fa fa-angle-right"></i>
            </a>
            <ul class="treeview-menu">
                <li><a class="treeview-item" href="{{ route('admin.businesses.index') }}"><i class="icon fa fa-list"></i> {{ __('menu.all_businesses') }}</a></li>
                <li><a class="treeview-item" href="{{ route('admin.plans.index') }}"><i class="icon fa fa-credit-card"></i> {{ __('menu.subscriptions') }}</a></li>
            </ul>
        </li>
        @endif
        @if(platform_admin_can('tickets'))
        <li><a class="app-menu__item {{ Request::is('admin/tickets*') ? 'active' : '' }}" href="{{ route('admin.tickets.index') }}">
            <i class="app-menu__icon fa fa-ticket"></i><span class="app-menu__label">{{ __('menu.support_tickets') }}</span>
            @if(!empty($unreadAdminTickets) && $unreadAdminTickets > 0)
            <span class="badge badge-danger ml-1">{{ $unreadAdminTickets }}</span>
            @endif
        </a></li>
        @endif
        @if(platform_admin_can('payments'))
        <li><a class="app-menu__item {{ Request::is('admin/payments*') ? 'active' : '' }}" href="{{ route('admin.payments.index') }}"><i class="app-menu__icon fa fa-money"></i><span class="app-menu__label">{{ __('menu.payments') }}</span></a></li>
        @endif
        @if(platform_admin_can('monitor'))
        <li><a class="app-menu__item {{ Request::is('admin/monitor*') ? 'active' : '' }}" href="{{ route('admin.monitor.index') }}"><i class="app-menu__icon fa fa-heartbeat"></i><span class="app-menu__label">{{ __('menu.usage_monitor') }}</span></a></li>
        @endif
        @if(platform_admin_can('reports'))
        <li><a class="app-menu__item {{ Request::is('admin/reports*') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}"><i class="app-menu__icon fa fa-bar-chart"></i><span class="app-menu__label">{{ __('menu.reports') }}</span></a></li>
        @endif
        @if(platform_admin_can('regional'))
        <li><a class="app-menu__item {{ Request::is('admin/regional*') ? 'active' : '' }}" href="{{ route('admin.regional.index') }}"><i class="app-menu__icon fa fa-map-marker"></i><span class="app-menu__label">{{ __('menu.regional_report') }}</span></a></li>
        @endif
        @if(platform_admin_can('funnel'))
        <li><a class="app-menu__item {{ Request::is('admin/funnel*') ? 'active' : '' }}" href="{{ route('admin.funnel.index') }}"><i class="app-menu__icon fa fa-filter"></i><span class="app-menu__label">{{ __('menu.registration_funnel') }}</span></a></li>
        @endif
        @if(platform_admin_can('leads'))
        <li><a class="app-menu__item {{ Request::is('admin/leads*') ? 'active' : '' }}" href="{{ route('admin.leads.index') }}"><i class="app-menu__icon fa fa-envelope"></i><span class="app-menu__label">{{ __('menu.demo_leads') }}</span></a></li>
        @endif
        @if(platform_admin_can('audit-logs'))
        <li><a class="app-menu__item {{ Request::is('admin/audit-logs*') ? 'active' : '' }}" href="{{ route('admin.audit-logs.index') }}"><i class="app-menu__icon fa fa-history"></i><span class="app-menu__label">{{ __('menu.activity_logs') }}</span></a></li>
        @endif
        @if(platform_admin_can('free-trials'))
        <li><a class="app-menu__item {{ Request::is('admin/free-trials*') ? 'active' : '' }}" href="{{ route('admin.free-trials.index') }}"><i class="app-menu__icon fa fa-hourglass-half"></i><span class="app-menu__label">{{ __('menu.free_trials') }}</span></a></li>
        @endif
        @if(platform_admin_can('businesses'))
        <li><a class="app-menu__item {{ Request::is('admin/broadcasts*') ? 'active' : '' }}" href="{{ route('admin.broadcasts.index') }}"><i class="app-menu__icon fa fa-bullhorn"></i><span class="app-menu__label">{{ __('menu.system_broadcasts') }}</span></a></li>
        @endif
        @if(platform_admin_can('settings'))
        <li><a class="app-menu__item {{ Request::is('admin/communication*') ? 'active' : '' }}" href="{{ route('admin.communication.index') }}"><i class="app-menu__icon fa fa-comments"></i><span class="app-menu__label">Communication Room</span></a></li>
        @endif
        @if(platform_admin_can('security'))
        <li class="treeview {{ Request::is('admin/security*') || Request::is('admin/staff*') || Request::is('admin/sessions*') ? 'is-expanded' : '' }}">
            <a class="app-menu__item" href="#" data-toggle="treeview">
                <i class="app-menu__icon fa fa-shield"></i>
                <span class="app-menu__label">{{ __('menu.security') }}</span>
                <i class="treeview-indicator fa fa-angle-right"></i>
            </a>
            <ul class="treeview-menu">
                <li><a class="treeview-item" href="{{ route('admin.security.failed-logins') }}"><i class="icon fa fa-exclamation-triangle"></i> {{ __('menu.failed_logins') }}</a></li>
                <li><a class="treeview-item" href="{{ route('admin.staff.index') }}"><i class="icon fa fa-users"></i> {{ __('menu.platform_staff') }}</a></li>
                @if(platform_admin_can('platform_roles'))
                <li><a class="treeview-item" href="{{ route('admin.platform-roles.index') }}"><i class="icon fa fa-shield"></i> {{ __('menu.admin_roles') }}</a></li>
                @endif
                <li><a class="treeview-item" href="{{ route('admin.sessions.index') }}"><i class="icon fa fa-desktop"></i> {{ __('menu.admin_sessions') }}</a></li>
            </ul>
        </li>
        @endif
        @if(platform_admin_can('settings'))
        <li><a class="app-menu__item {{ Request::is('admin/settings*') ? 'active' : '' }}" href="{{ route('admin.settings.index') }}"><i class="app-menu__icon fa fa-gears"></i><span class="app-menu__label">{{ __('menu.system_settings') }}</span></a></li>
        @endif
    @else
        {{-- Inventory --}}
        @php ob_start(); @endphp
        @if(business_retail_enabled())
            @can('view_inventory')
            <li><a class="treeview-item {{ Request::is('items') ? 'active' : '' }}" href="{{ route('items.index') }}" data-tour="menu-registration"><i class="icon fa fa-barcode"></i> {{ __('menu.items') }}</a></li>
            <li><a class="treeview-item {{ Request::is('items/stock') ? 'active' : '' }}" href="{{ route('items.stock') }}"><i class="icon fa fa-cubes"></i> {{ __('menu.item_stock') }}</a></li>
            <li><a class="treeview-item {{ Request::is('categories*') ? 'active' : '' }}" href="{{ route('categories.index') }}"><i class="icon fa fa-list"></i> {{ __('menu.categories') }}</a></li>
            <li><a class="treeview-item {{ Request::is('packagings*') ? 'active' : '' }}" href="{{ route('packagings.index') }}"><i class="icon fa fa-archive"></i> {{ __('menu.packaging_units') }}</a></li>
            <li><a class="treeview-item {{ Request::is('items/barcodes') ? 'active' : '' }}" href="{{ route('items.barcodes.index') }}"><i class="icon fa fa-qrcode"></i> {{ __('menu.qr_codes') }}</a></li>
            @endcan
            @canany(['view_inventory', 'view_price_list'])
            <li><a class="treeview-item {{ Request::is('price-list*') ? 'active' : '' }}" href="{{ route('price-list.index') }}"><i class="icon fa fa-tags"></i> {{ __('menu.price_list') }}</a></li>
            @endcanany
            @can('manage_suppliers')
            @can('view_inventory')
            <li><a class="treeview-item {{ Request::is('suppliers*') ? 'active' : '' }}" href="{{ route('suppliers.index') }}"><i class="icon fa fa-truck"></i> {{ __('menu.suppliers') }}</a></li>
            @endcan
            @endcan
            @can('receive_stock')
            <li><a class="treeview-item {{ Request::is('receivings*') ? 'active' : '' }}" href="{{ route('receivings.index') }}" data-tour="menu-receiving"><i class="icon fa fa-download"></i> {{ __('menu.stock_in') }}</a></li>
            @endcan
            @canany(['supply_to_branch', 'receive_branch_supply'])
            @if(app(\App\Services\BranchTransferService::class)->businessHasBranches((int) auth()->user()->business_id))
            <li><a class="treeview-item {{ Request::is('branch-transfers*') ? 'active' : '' }}" href="{{ route('branch-transfers.index') }}"><i class="icon fa fa-exchange"></i> {{ __('menu.supply_to_branch') }}</a></li>
            @endif
            @endcanany
            @canany(['record_stock_loss', 'view_stock_history', 'open_shift', 'process_sales'])
            <li><a class="treeview-item {{ Request::is('stock-losses*') ? 'active' : '' }}" href="{{ route('stock-losses.index') }}" data-tour="menu-stock-losses"><i class="icon fa fa-minus-circle"></i> {{ __('menu.stock_losses') }}</a></li>
            @endcanany
        @endif
        @canany(['verify_stock_shortages', 'view_reports'])
        <li><a class="treeview-item {{ Request::is('shifts/stock-shortages*') ? 'active' : '' }}" href="{{ route('stock-shortages.index') }}" data-tour="menu-stock-shortages"><i class="icon fa fa-warning"></i> {{ __('menu.stock_shortages') }}</a></li>
        @endcanany
        @php $menuInventory = trim(ob_get_clean()); @endphp
        @if($menuInventory !== '')
        <li class="treeview {{ str_contains($menuInventory, 'treeview-item active') ? 'is-expanded has-active' : '' }}">
            <a class="app-menu__item" href="#" data-toggle="treeview">
                <i class="app-menu__icon fa fa-cubes"></i>
                <span class="app-menu__label">{{ __('menu.group_inventory') }}</span>
                <i class="treeview-indicator fa fa-angle-right"></i>
            </a>
            <ul class="treeview-menu">{!! $menuInventory !!}</ul>
        </li>
        @endif

        {{-- Sales --}}
        @php ob_start(); @endphp
        @can('collect_payments')
        @if(Auth::user()->isPaymentCashier() || Auth::user()->seesBusinessWideData())
        <li><a class="treeview-item {{ Request::is('cashier*') ? 'active' : '' }}" href="{{ route('cashier.queue') }}"><i class="icon fa fa-money"></i> Payment Queue</a></li>
        @endif
        @endcan
        @canany(['process_sales', 'view_sales_history'])
        @if(business_retail_enabled() || business_services_menu_visible())
        <li><a class="treeview-item {{ Request::is('sales') || Request::is('sales/*') ? 'active' : '' }}" href="{{ route('sales.index') }}" data-tour="menu-pos"><i class="icon fa fa-shopping-cart"></i> {{ __('menu.store_pos') }}</a></li>
        @endif
        @endcanany
        @canany(['open_shift', 'process_sales', 'collect_payments', 'view_all_shifts'])
        <li><a class="treeview-item {{ Request::is('shifts') || (Request::is('shifts/*') && !Request::is('shifts/stock-shortages*')) ? 'active' : '' }}" href="{{ route('shifts.index') }}" data-tour="menu-shifts"><i class="icon fa fa-clock-o"></i> {{ __('menu.sales_shifts') }}</a></li>
        @endcanany
        @canany(['view_live_sales', 'view_reports', 'view_sales_history', 'process_sales'])
        @if(plan_feature('live_sales_pulse'))
        <li><a class="treeview-item {{ Request::is('live-sales*') ? 'active' : '' }}" href="{{ route('live-sales.index') }}" data-tour="menu-live-sales"><i class="icon fa fa-bolt"></i> {{ __('menu.live_sales_pulse') }}</a></li>
        @endif
        @endcanany
        @canany(['view_invoices', 'create_invoices', 'collect_invoice_payments', 'process_sales', 'view_sales_history'])
        @if(plan_feature('invoices'))
        <li><a class="treeview-item {{ Request::is('invoices*') ? 'active' : '' }}" href="{{ route('invoices.index') }}" data-tour="menu-invoices"><i class="icon fa fa-file-text-o"></i> {{ __('menu.invoices') }}</a></li>
        @endif
        @endcanany
        @canany(['manage_sales_targets', 'manage_business_settings'])
        @if(plan_feature('sales_targets'))
        <li><a class="treeview-item {{ Request::is('sales-targets*') ? 'active' : '' }}" href="{{ route('sales-targets.index') }}" data-tour="menu-sales-targets-nav"><i class="icon fa fa-bullseye"></i> {{ __('menu.sales_targets') }}</a></li>
        @endif
        @endcanany
        @php $menuSales = trim(ob_get_clean()); @endphp
        @if($menuSales !== '')
        <li class="treeview {{ str_contains($menuSales, 'treeview-item active') ? 'is-expanded has-active' : '' }}">
            <a class="app-menu__item" href="#" data-toggle="treeview">
                <i class="app-menu__icon fa fa-shopping-cart"></i>
                <span class="app-menu__label">{{ __('menu.group_sales') }}</span>
                <i class="treeview-indicator fa fa-angle-right"></i>
            </a>
            <ul class="treeview-menu">{!! $menuSales !!}</ul>
        </li>
        @endif

        {{-- Services --}}
        @canany(['process_sales', 'view_sales_history'])
        @if(business_services_menu_visible() && plan_feature('services'))
        <li class="treeview {{ Request::is('services*') || Request::is('service-invoices*') ? 'is-expanded has-active' : '' }}">
            <a class="app-menu__item" href="#" data-toggle="treeview" data-tour="menu-services">
                <i class="app-menu__icon fa fa-briefcase"></i>
                <span class="app-menu__label">{{ __('menu.services') }}</span>
                <i class="treeview-indicator fa fa-angle-right"></i>
            </a>
            <ul class="treeview-menu">
                @canany(['manage_services', 'manage_categories', 'view_inventory', 'process_sales'])
                <li><a class="treeview-item {{ Request::routeIs('services.categories', 'services.index') ? 'active' : '' }}" href="{{ route('services.categories') }}"><i class="icon fa fa-folder-open"></i> {{ __('menu.categories') }}</a></li>
                <li><a class="treeview-item {{ Request::routeIs('services.materials') ? 'active' : '' }}" href="{{ route('services.materials') }}"><i class="icon fa fa-cubes"></i> {{ __('menu.service_materials') }}</a></li>
                @endcanany
                @canany(['manage_services', 'manage_categories', 'view_inventory', 'add_items'])
                <li><a class="treeview-item {{ Request::is('services/register') ? 'active' : '' }}" href="{{ route('services.register') }}"><i class="icon fa fa-plus-circle"></i> {{ __('menu.register_business') }}</a></li>
                @endcanany
                @can('process_sales')
                <li><a class="treeview-item" href="{{ route('sales.create') }}"><i class="icon fa fa-desktop"></i> {{ __('menu.sales_pos') }}</a></li>
                @endcan
                @canany(['process_sales', 'view_sales_history'])
                <li><a class="treeview-item" href="{{ route('sales.index') }}"><i class="icon fa fa-list-alt"></i> {{ __('menu.sales_history') }}</a></li>
                @endcanany
                @canany(['submit_day_closing', 'verify_day_closing', 'process_sales'])
                <li><a class="treeview-item {{ Request::is('services/handover') ? 'active' : '' }}" href="{{ route('services.handover') }}"><i class="icon fa fa-exchange"></i> {{ __('menu.handover') }}</a></li>
                @endcanany
                @can('view_reports')
                @if(plan_feature('master_sheet'))
                <li><a class="treeview-item {{ Request::is('services/master-sheet') ? 'active' : '' }}" href="{{ route('services.master-sheet') }}"><i class="icon fa fa-list-alt"></i> {{ __('menu.master_sheet') }}</a></li>
                @endif
                @endcan
                @canany(['view_invoices', 'create_invoices'])
                @if(plan_feature('invoices'))
                <li><a class="treeview-item {{ Request::is('service-invoices*') ? 'active' : '' }}" href="{{ route('service-invoices.index') }}"><i class="icon fa fa-file-text-o"></i> {{ __('menu.invoices') }}</a></li>
                @endif
                @endcanany
            </ul>
        </li>
        @endif
        @endcanany

        {{-- Customers & Debts --}}
        @php ob_start(); @endphp
        @can('manage_customers')
        @if(plan_feature('customers'))
        <li><a class="treeview-item {{ Request::is('customers*') ? 'active' : '' }}" href="{{ route('customers.index') }}" data-tour="menu-customers"><i class="icon fa fa-address-book"></i> {{ __('menu.customers') }}</a></li>
        @endif
        @endcan
        @canany(['manage_debts', 'process_sales', 'collect_payments'])
        @if(plan_feature('debts'))
        <li><a class="treeview-item {{ Request::is('debts') || (Request::is('debts/*') && !Request::is('debts/history*')) ? 'active' : '' }}" href="{{ route('debts.index') }}" data-tour="menu-debts"><i class="icon fa fa-exclamation-circle"></i> {{ __('menu.debts') }} - {{ __('menu.outstanding') }}</a></li>
        <li><a class="treeview-item {{ Request::is('debts/history*') ? 'active' : '' }}" href="{{ route('debts.history') }}"><i class="icon fa fa-history"></i> {{ __('menu.debts') }} - {{ __('menu.history') }}</a></li>
        @endif
        @endcanany
        @canany(['manage_customer_communications', 'manage_customers'])
        @if(plan_feature('customer_communication'))
        <li><a class="treeview-item {{ Request::is('customer-communications*') ? 'active' : '' }}" href="{{ route('customer-communications.index') }}" data-tour="menu-customer-comms"><i class="icon fa fa-commenting"></i> {{ __('menu.customer_comms') }}</a></li>
        @endif
        @endcanany
        @php $menuCustomers = trim(ob_get_clean()); @endphp
        @if($menuCustomers !== '')
        <li class="treeview {{ str_contains($menuCustomers, 'treeview-item active') ? 'is-expanded has-active' : '' }}">
            <a class="app-menu__item" href="#" data-toggle="treeview">
                <i class="app-menu__icon fa fa-address-book"></i>
                <span class="app-menu__label">{{ __('menu.group_customers') }}</span>
                <i class="treeview-indicator fa fa-angle-right"></i>
            </a>
            <ul class="treeview-menu">{!! $menuCustomers !!}</ul>
        </li>
        @endif

        {{-- Cash & Closing --}}
        @php ob_start(); @endphp
        @canany(['submit_day_closing', 'verify_day_closing', 'process_sales'])
        @php
            $sidebarShift = null;
            if (Auth::user()->requiresOpenShift()) {
                $sidebarShift = \App\Models\Shift::latestClosedAwaitingHandover(Auth::id(), Auth::user()->business_id)
                    ?? \App\Models\Shift::openForUser(Auth::id(), Auth::user()->business_id);
            }
        @endphp
        <li><a class="treeview-item {{ Request::is('day-closing') ? 'active' : '' }}" href="{{ $sidebarShift ? route('day-closing.index', ['shift' => $sidebarShift->id]) : route('day-closing.index') }}" data-tour="menu-day-closing"><i class="icon fa fa-balance-scale"></i> {{ __('menu.daily_reconciliation') }}</a></li>
        @can('manage_money_shorts')
        <li><a class="treeview-item {{ Request::is('money-shorts*') ? 'active' : '' }}" href="{{ route('money-shorts.index') }}" data-tour="menu-money-shorts"><i class="icon fa fa-money"></i> {{ __('menu.money_shorts') }}</a></li>
        @endcan
        @endcanany
        @can('manage_petty_cash')
        @if(plan_feature('petty_cash'))
        <li><a class="treeview-item {{ Request::is('petty-cash*') ? 'active' : '' }}" href="{{ route('petty-cash.index') }}" data-tour="menu-petty-cash"><i class="icon fa fa-credit-card"></i> {{ __('menu.petty_cash') }}</a></li>
        @endif
        @endcan
        @canany(['view_closing_history', 'view_reports', 'verify_day_closing'])
        <li><a class="treeview-item {{ Request::is('day-closing/history*') ? 'active' : '' }}" href="{{ route('day-closing.history') }}" data-tour="menu-closing-history"><i class="icon fa fa-file-text"></i> {{ __('menu.closing_history') }}</a></li>
        @endcanany
        @php $menuFinance = trim(ob_get_clean()); @endphp
        @if($menuFinance !== '')
        <li class="treeview {{ str_contains($menuFinance, 'treeview-item active') ? 'is-expanded has-active' : '' }}">
            <a class="app-menu__item" href="#" data-toggle="treeview">
                <i class="app-menu__icon fa fa-balance-scale"></i>
                <span class="app-menu__label">{{ __('menu.group_finance') }}</span>
                <i class="treeview-indicator fa fa-angle-right"></i>
            </a>
            <ul class="treeview-menu">{!! $menuFinance !!}</ul>
        </li>
        @endif

        {{-- Reports & Analytics --}}
        @php ob_start(); @endphp
        @canany(['view_reports', 'verify_day_closing', 'finalize_reports'])
        @if(plan_feature_any(['reports_daily', 'reports_expenses', 'reports_sales', 'reports_products', 'reports_debts', 'reports_profit', 'reports_circulation']))
        <li><a class="treeview-item {{ Request::is('reports*') ? 'active' : '' }}" href="{{ route('reports.index') }}" data-tour="menu-reports"><i class="icon fa fa-bar-chart"></i> {{ __('menu.reports') }}</a></li>
        @endif
        @if(plan_feature('master_sheet'))
        <li><a class="treeview-item {{ Request::is('owner-reports*') ? 'active' : '' }}" href="{{ route('owner-reports.index') }}" data-tour="menu-master-sheet"><i class="icon fa fa-list-alt"></i> {{ __('menu.master_sheet') }}</a></li>
        @endif
        @endcanany
        @can('view_audit_logs')
        <li><a class="treeview-item {{ Request::is('activity-log*') ? 'active' : '' }}" href="{{ route('business.activity-log') }}" data-tour="menu-activity-log"><i class="icon fa fa-history"></i> {{ __('menu.activity_log') }}</a></li>
        @endcan
        @php $menuReports = trim(ob_get_clean()); @endphp
        @if($menuReports !== '')
        <li class="treeview {{ str_contains($menuReports, 'treeview-item active') ? 'is-expanded has-active' : '' }}">
            <a class="app-menu__item" href="#" data-toggle="treeview">
                <i class="app-menu__icon fa fa-line-chart"></i>
                <span class="app-menu__label">{{ __('menu.group_reports') }}</span>
                <i class="treeview-indicator fa fa-angle-right"></i>
            </a>
            <ul class="treeview-menu">{!! $menuReports !!}</ul>
        </li>
        @endif

        {{-- Staff --}}
        @can('manage_staff')
        <li class="treeview {{ Request::is('employees*') || Request::is('roles*') ? 'is-expanded has-active' : '' }}">
            <a class="app-menu__item" href="#" data-toggle="treeview" data-tour="menu-staff">
                <i class="app-menu__icon fa fa-users"></i>
                <span class="app-menu__label">{{ __('menu.staff_management') }}</span>
                <i class="treeview-indicator fa fa-angle-right"></i>
            </a>
            <ul class="treeview-menu">
                <li><a class="treeview-item {{ Request::is('employees*') ? 'active' : '' }}" href="{{ route('employees.index') }}"><i class="icon fa fa-user"></i> {{ __('menu.employees') }}</a></li>
                <li><a class="treeview-item {{ Request::is('roles*') ? 'active' : '' }}" href="{{ route('roles.index') }}"><i class="icon fa fa-shield"></i> {{ __('menu.roles') }}</a></li>
            </ul>
        </li>
        @endcan

        {{-- My Business --}}
        @php ob_start(); @endphp
        @canany(['manage_business_settings', 'manage_payment_methods'])
        <li><a class="treeview-item {{ Request::is('settings*') ? 'active' : '' }}" href="{{ route('settings.index') }}" data-tour="menu-settings"><i class="icon fa fa-gears"></i> {{ __('menu.business_settings') }}</a></li>
        @endcanany
        @can('manage_branches')
        @if(plan_feature('branches'))
        <li><a class="treeview-item {{ Request::is('branches*') ? 'active' : '' }}" href="{{ route('branches.index') }}" data-tour="menu-branches"><i class="icon fa fa-building"></i> {{ __('menu.branches') }}</a></li>
        @endif
        @endcan
        @if(plan_feature('notes_reminders'))
        @can('manage_notes')
        <li><a class="treeview-item {{ Request::is('notes*') ? 'active' : '' }}" href="{{ route('notes.index') }}" data-tour="menu-notes"><i class="icon fa fa-sticky-note"></i> {{ __('menu.notes_reminders') }}</a></li>
        @endcan
        @endif
        @if(in_array(Auth::user()->role, ['owner', 'staff'], true))
        <li><a class="treeview-item {{ Request::is('subscription/upgrade*') ? 'active' : '' }}" href="{{ route('subscription.upgrade') }}" data-tour="menu-upgrade"><i class="icon fa fa-level-up"></i> {{ __('menu.upgrade_plan') }}</a></li>
        @endif
        @can('manage_support')
        <li><a class="treeview-item {{ Request::is('support*') ? 'active' : '' }}" href="{{ route('tickets.index') }}" data-tour="menu-support"><i class="icon fa fa-life-ring"></i> {{ __('menu.my_support') }}</a></li>
        @endcan
        @php $menuMyBusiness = trim(ob_get_clean()); @endphp
        @if($menuMyBusiness !== '')
        <li class="treeview {{ str_contains($menuMyBusiness, 'treeview-item active') ? 'is-expanded has-active' : '' }}">
            <a class="app-menu__item" href="#" data-toggle="treeview">
                <i class="app-menu__icon fa fa-briefcase"></i>
                <span class="app-menu__label">{{ __('menu.group_my_business') }}</span>
                <i class="treeview-indicator fa fa-angle-right"></i>
            </a>
            <ul class="treeview-menu">{!! $menuMyBusiness !!}</ul>
        </li>
        @endif

        @canany(['adjust_stock', 'view_stock_adjustments'])
        @if(business_retail_enabled())
        <li class="treeview danger-zone-menu {{ Request::is('stock-adjustments*') ? 'is-expanded' : '' }}">
            <a class="app-menu__item danger-zone-menu__toggle" href="#" data-toggle="treeview" data-tour="menu-danger-zone">
                <i class="app-menu__icon fa fa-exclamation-triangle"></i>
                <span class="app-menu__label">{{ __('menu.danger_zone') }}</span>
                <i class="treeview-indicator fa fa-angle-right"></i>
            </a>
            <ul class="treeview-menu danger-zone-menu__items">
                <li><a class="treeview-item {{ Request::is('stock-adjustments*') ? 'active' : '' }}" href="{{ route('stock-adjustments.index') }}"><i class="icon fa fa-wrench"></i> {{ __('menu.stock_adjustments') }}</a></li>
            </ul>
        </li>
        @endif
        @endcanany
    @endif
  </ul>
</aside>
