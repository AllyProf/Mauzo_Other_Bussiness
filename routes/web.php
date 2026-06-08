<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\BusinessRegistrationController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], '/locale/{locale}', [App\Http\Controllers\LocaleController::class, 'switch'])->name('locale.switch');

Route::get('/', [LandingController::class, 'index'])->name('landing.index');
Route::post('/request-demo', [App\Http\Controllers\LandingLeadController::class, 'store'])->name('landing.lead.store');

// Authentication Routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Business Registration
Route::get('/register-business', [BusinessRegistrationController::class, 'showRegistrationForm'])->name('register.business');
Route::post('/register-business/send-code', [BusinessRegistrationController::class, 'sendVerificationCode'])->name('register.business.send-code');
Route::post('/register-business', [BusinessRegistrationController::class, 'register'])->name('register.business.store');
Route::get('/register', fn () => redirect()->route('register.business'))->name('landing.register');

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->middleware(['auth', 'check.user.active']);

Route::middleware(['auth', 'check.user.active'])->group(function () {
    Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile', [App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/password', [App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.update-password');

    Route::post('/support/quick', [App\Http\Controllers\SupportTicketController::class, 'quickStore'])->name('tickets.quick-store');
    Route::get('/activity-log', [App\Http\Controllers\BusinessAuditLogController::class, 'index'])->name('business.activity-log');
});

// Admin / Software Owner Routes
Route::middleware(['auth', 'check.user.active', 'check.platform.admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [App\Http\Controllers\Admin\AdminDashboardController::class, 'index']);

    Route::get('/businesses', [App\Http\Controllers\Admin\BusinessController::class, 'index'])->name('businesses.index');
    Route::get('/businesses/create', [App\Http\Controllers\Admin\BusinessController::class, 'create'])->name('businesses.create');
    Route::post('/businesses', [App\Http\Controllers\Admin\BusinessController::class, 'store'])->name('businesses.store');
    Route::get('/businesses/{business}/edit', [App\Http\Controllers\Admin\BusinessController::class, 'edit'])->name('businesses.edit');
    Route::put('/businesses/{business}', [App\Http\Controllers\Admin\BusinessController::class, 'update'])->name('businesses.update');
    Route::post('/businesses/{business}/toggle-status', [App\Http\Controllers\Admin\BusinessController::class, 'toggleStatus'])->name('businesses.toggle-status');
    Route::post('/businesses/{business}/approve', [App\Http\Controllers\Admin\BusinessController::class, 'approveRegistration'])->name('businesses.approve');
    Route::post('/businesses/{business}/reject', [App\Http\Controllers\Admin\BusinessController::class, 'rejectRegistration'])->name('businesses.reject');
    Route::post('/businesses/{business}/reset-owner-password', [App\Http\Controllers\Admin\BusinessController::class, 'resetOwnerPassword'])->name('businesses.reset-owner-password');
    Route::post('/businesses/{business}/purge-data', [App\Http\Controllers\Admin\BusinessController::class, 'purgeData'])->name('businesses.purge-data');
    Route::delete('/businesses/{business}', [App\Http\Controllers\Admin\BusinessController::class, 'destroy'])->name('businesses.destroy');
    
    Route::get('/plans', [App\Http\Controllers\Admin\PlanController::class, 'index'])->name('plans.index');
    Route::get('/plans/{plan}/edit', [App\Http\Controllers\Admin\PlanController::class, 'edit'])->name('plans.edit');
    Route::put('/plans/{plan}', [App\Http\Controllers\Admin\PlanController::class, 'update'])->name('plans.update');
    Route::get('/plans/create', [App\Http\Controllers\Admin\PlanController::class, 'create'])->name('plans.create');
    Route::post('/plans', [App\Http\Controllers\Admin\PlanController::class, 'store'])->name('plans.store');

    Route::get('/broadcasts', [App\Http\Controllers\Admin\BroadcastController::class, 'index'])->name('broadcasts.index');
    Route::post('/broadcasts', [App\Http\Controllers\Admin\BroadcastController::class, 'store'])->name('broadcasts.store');
    Route::delete('/broadcasts/{broadcast}', [App\Http\Controllers\Admin\BroadcastController::class, 'destroy'])->name('broadcasts.destroy');

    Route::get('/tickets', [App\Http\Controllers\Admin\AdminTicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/{ticket}', [App\Http\Controllers\Admin\AdminTicketController::class, 'show'])->name('tickets.show');
    Route::put('/tickets/{ticket}', [App\Http\Controllers\Admin\AdminTicketController::class, 'update'])->name('tickets.update');

    Route::get('/audit-logs', [App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-logs/feed', [App\Http\Controllers\Admin\AuditLogController::class, 'feed'])->name('audit-logs.feed');
    Route::get('/audit-logs/export', [App\Http\Controllers\Admin\AuditLogController::class, 'export'])->name('audit-logs.export');
    Route::get('/audit-logs/{auditLog}', [App\Http\Controllers\Admin\AuditLogController::class, 'show'])->name('audit-logs.show');

    Route::get('/payments', [App\Http\Controllers\Admin\PaymentReportController::class, 'index'])->name('payments.index');
    Route::post('/payments/generate', [App\Http\Controllers\Admin\PaymentReportController::class, 'generateInvoices'])->name('payments.generate');
    Route::post('/payments/{invoice}/mark-paid', [App\Http\Controllers\Admin\PaymentReportController::class, 'markPaid'])->name('payments.mark-paid');
    Route::get('/payments/{invoice}/pdf', [App\Http\Controllers\Admin\PaymentReportController::class, 'downloadPdf'])->name('payments.pdf');
    Route::post('/payments/{invoice}/resend', [App\Http\Controllers\Admin\PaymentReportController::class, 'resendInvoice'])->name('payments.resend');

    Route::get('/reports', [App\Http\Controllers\Admin\ReportController::class, 'index'])->name('reports.index');
    Route::get('/regional', [App\Http\Controllers\Admin\RegionalReportController::class, 'index'])->name('regional.index');
    Route::get('/monitor', [App\Http\Controllers\Admin\PlatformMonitorController::class, 'index'])->name('monitor.index');
    Route::get('/funnel', [App\Http\Controllers\Admin\RegistrationFunnelController::class, 'index'])->name('funnel.index');
    Route::get('/leads', [App\Http\Controllers\Admin\PlatformLeadController::class, 'index'])->name('leads.index');
    Route::put('/leads/{lead}', [App\Http\Controllers\Admin\PlatformLeadController::class, 'update'])->name('leads.update');
    Route::get('/businesses/{business}/onboarding', [App\Http\Controllers\Admin\BusinessOnboardingController::class, 'show'])->name('onboarding.show');

    Route::get('/security/failed-logins', [App\Http\Controllers\Admin\FailedLoginController::class, 'index'])->name('security.failed-logins');
    Route::get('/staff', [App\Http\Controllers\Admin\PlatformStaffController::class, 'index'])->name('staff.index');
    Route::post('/staff', [App\Http\Controllers\Admin\PlatformStaffController::class, 'store'])->name('staff.store');
    Route::put('/staff/{user}', [App\Http\Controllers\Admin\PlatformStaffController::class, 'update'])->name('staff.update');
    Route::get('/platform-roles', [App\Http\Controllers\Admin\PlatformAdminRoleController::class, 'index'])->name('platform-roles.index');
    Route::get('/platform-roles/create', [App\Http\Controllers\Admin\PlatformAdminRoleController::class, 'create'])->name('platform-roles.create');
    Route::post('/platform-roles', [App\Http\Controllers\Admin\PlatformAdminRoleController::class, 'store'])->name('platform-roles.store');
    Route::get('/platform-roles/{platformRole}/edit', [App\Http\Controllers\Admin\PlatformAdminRoleController::class, 'edit'])->name('platform-roles.edit');
    Route::put('/platform-roles/{platformRole}', [App\Http\Controllers\Admin\PlatformAdminRoleController::class, 'update'])->name('platform-roles.update');
    Route::delete('/platform-roles/{platformRole}', [App\Http\Controllers\Admin\PlatformAdminRoleController::class, 'destroy'])->name('platform-roles.destroy');
    Route::get('/sessions', [App\Http\Controllers\Admin\AdminSessionController::class, 'index'])->name('sessions.index');
    Route::delete('/sessions/{sessionId}', [App\Http\Controllers\Admin\AdminSessionController::class, 'destroy'])->name('sessions.destroy');

    Route::get('/free-trials', [App\Http\Controllers\Admin\FreeTrialController::class, 'index'])->name('free-trials.index');
    Route::post('/free-trials/{business}/extend', [App\Http\Controllers\Admin\FreeTrialController::class, 'extendTrial'])->name('free-trials.extend');
    Route::post('/free-trials/{business}/convert', [App\Http\Controllers\Admin\FreeTrialController::class, 'convertToPaid'])->name('free-trials.convert');

    Route::post('/impersonate/{business}', [App\Http\Controllers\Admin\ImpersonationController::class, 'impersonate'])->name('impersonate');

    Route::get('/settings', [App\Http\Controllers\Admin\SystemSettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/profile', [App\Http\Controllers\Admin\SystemSettingsController::class, 'updateProfile'])->name('settings.profile.update');
    Route::put('/settings/registration', [App\Http\Controllers\Admin\SystemSettingsController::class, 'updateRegistration'])->name('settings.registration.update');
    Route::put('/settings/subscription', [App\Http\Controllers\Admin\SystemSettingsController::class, 'updateSubscription'])->name('settings.subscription.update');
    Route::put('/settings/mail', [App\Http\Controllers\Admin\SystemSettingsController::class, 'updateMail'])->name('settings.mail.update');
    Route::put('/settings/security', [App\Http\Controllers\Admin\SystemSettingsController::class, 'updateSecurity'])->name('settings.security.update');
    Route::put('/settings/password', [App\Http\Controllers\Admin\SystemSettingsController::class, 'updatePassword'])->name('settings.password.update');
});

// Tenant Routes (Authenticated & Subscribed)
Route::middleware(['auth', 'check.user.active', 'check.subscription'])->group(function () {
    Route::get('/subscription/upgrade', [App\Http\Controllers\SubscriptionController::class, 'upgrade'])->name('subscription.upgrade');

    Route::middleware('check.plan.feature')->group(function () {
    Route::get('/support', [App\Http\Controllers\SupportTicketController::class, 'index'])->name('tickets.index');
    Route::get('/support/create', [App\Http\Controllers\SupportTicketController::class, 'create'])->name('tickets.create');
    Route::post('/support', [App\Http\Controllers\SupportTicketController::class, 'store'])->name('tickets.store');
    Route::get('/support/{ticket}', [App\Http\Controllers\SupportTicketController::class, 'show'])->name('tickets.show_tenant');

    Route::middleware('check.business.retail')->group(function () {
    // Items Management
    Route::get('/items/stock', [App\Http\Controllers\ItemController::class, 'stock'])->name('items.stock');
    Route::get('/items/{item}/history', [App\Http\Controllers\ItemController::class, 'history'])->name('items.history');
    Route::resource('/items', App\Http\Controllers\ItemController::class);
    
    // Category & Packaging Registration
    Route::get('/categories', [App\Http\Controllers\CategoryController::class, 'index'])->name('categories.index');
    Route::delete('/categories/clear-all', [App\Http\Controllers\CategoryController::class, 'clearAll'])->name('categories.clear-all');
    Route::post('/categories', [App\Http\Controllers\CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [App\Http\Controllers\CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [App\Http\Controllers\CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('/categories/import-templates', [App\Http\Controllers\CategoryController::class, 'importTemplates'])->name('categories.import-templates');

    Route::get('/packagings', [App\Http\Controllers\PackagingController::class, 'index'])->name('packagings.index');
    Route::delete('/packagings/clear-all', [App\Http\Controllers\PackagingController::class, 'clearAll'])->name('packagings.clear-all');
    Route::post('/packagings', [App\Http\Controllers\PackagingController::class, 'store'])->name('packagings.store');
    Route::put('/packagings/{packaging}', [App\Http\Controllers\PackagingController::class, 'update'])->name('packagings.update');
    Route::delete('/packagings/{packaging}', [App\Http\Controllers\PackagingController::class, 'destroy'])->name('packagings.destroy');
    Route::post('/packagings/import-templates', [App\Http\Controllers\PackagingController::class, 'importTemplates'])->name('packagings.import-templates');

    // Receiving Routes
    Route::get('/receivings', [App\Http\Controllers\ReceivingController::class, 'index'])->name('receivings.index');
    Route::get('/receivings/create', [App\Http\Controllers\ReceivingController::class, 'create'])->name('receivings.create');
    Route::post('/receivings', [App\Http\Controllers\ReceivingController::class, 'store'])->name('receivings.store');
    Route::get('/receivings/{receiving}', [App\Http\Controllers\ReceivingController::class, 'show'])->name('receivings.show');
    Route::post('/receivings/{receiving}/cancel', [App\Http\Controllers\ReceivingController::class, 'cancel'])->name('receivings.cancel');

    // Stock losses (lost / damaged / destroyed)
    Route::get('/stock-losses', [App\Http\Controllers\StockLossController::class, 'index'])->name('stock-losses.index');
    Route::get('/stock-losses/create', [App\Http\Controllers\StockLossController::class, 'create'])->name('stock-losses.create');
    Route::post('/stock-losses', [App\Http\Controllers\StockLossController::class, 'store'])->name('stock-losses.store');
    Route::get('/stock-losses/{stockLoss}', [App\Http\Controllers\StockLossController::class, 'show'])->name('stock-losses.show');
    Route::post('/stock-losses/{stockLoss}/cancel', [App\Http\Controllers\StockLossController::class, 'cancel'])->name('stock-losses.cancel');
    });

    Route::middleware('check.business.services')->group(function () {
    Route::get('/services', [App\Http\Controllers\ServiceCatalogController::class, 'index'])->name('services.index');
    Route::get('/services/register', [App\Http\Controllers\ServiceCatalogController::class, 'register'])->name('services.register');
    Route::get('/services/categories', [App\Http\Controllers\ServiceCatalogController::class, 'categories'])->name('services.categories');
    Route::post('/services/categories', [App\Http\Controllers\ServiceCatalogController::class, 'storeCategory'])->name('services.categories.store');
    Route::get('/services/sales', [App\Http\Controllers\ServiceSaleController::class, 'index'])->name('services.sales.index');
    Route::get('/services/handover', [App\Http\Controllers\DayClosingController::class, 'index'])->name('services.handover');
    Route::post('/services/import-templates', [App\Http\Controllers\ServiceCatalogController::class, 'importTemplates'])->name('services.import-templates');
    Route::post('/services/catalog', [App\Http\Controllers\ServiceCatalogController::class, 'storeService'])->name('services.store');
    Route::put('/services/{service}', [App\Http\Controllers\ServiceCatalogController::class, 'updateService'])->name('services.update');
    Route::delete('/services/{service}', [App\Http\Controllers\ServiceCatalogController::class, 'destroyService'])->name('services.destroy');
    Route::get('/service-pos', [App\Http\Controllers\ServiceSaleController::class, 'create'])->name('service-pos.create');
    Route::post('/service-pos', [App\Http\Controllers\ServiceSaleController::class, 'store'])->name('service-pos.store');
    Route::get('/service-invoices', [App\Http\Controllers\ServiceInvoiceController::class, 'index'])->name('service-invoices.index');
    Route::get('/service-invoices/create', [App\Http\Controllers\ServiceInvoiceController::class, 'create'])->name('service-invoices.create');
    Route::post('/service-invoices', [App\Http\Controllers\ServiceInvoiceController::class, 'store'])->name('service-invoices.store');
    Route::get('/service-invoices/{serviceInvoice}', [App\Http\Controllers\ServiceInvoiceController::class, 'show'])->name('service-invoices.show');
    });

    // Notes & reminders
    Route::get('/notes', [App\Http\Controllers\BusinessNoteController::class, 'index'])->name('notes.index');
    Route::post('/notes', [App\Http\Controllers\BusinessNoteController::class, 'store'])->name('notes.store');
    Route::put('/notes/{note}', [App\Http\Controllers\BusinessNoteController::class, 'update'])->name('notes.update');
    Route::delete('/notes/{note}', [App\Http\Controllers\BusinessNoteController::class, 'destroy'])->name('notes.destroy');
    Route::post('/notes/{note}/complete', [App\Http\Controllers\BusinessNoteController::class, 'complete'])->name('notes.complete');

    // Sales / POS Routes
    Route::get('/debts/history', [App\Http\Controllers\DebtController::class, 'history'])->name('debts.history');
    Route::get('/debts', [App\Http\Controllers\DebtController::class, 'index'])->name('debts.index');
    Route::get('/invoices', [App\Http\Controllers\InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [App\Http\Controllers\InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [App\Http\Controllers\InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{invoice}', [App\Http\Controllers\InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/live-sales', [App\Http\Controllers\LiveSalesController::class, 'index'])->name('live-sales.index');

    Route::middleware('check.business.retail')->group(function () {
    Route::resource('/sales', App\Http\Controllers\SaleController::class);
    Route::post('/sales/{sale}/pay', [App\Http\Controllers\SaleController::class, 'pay'])->name('sales.pay');
    Route::post('/sales/{sale}/cancel', [App\Http\Controllers\SaleController::class, 'cancel'])->name('sales.cancel');
    });

    // Sales Shifts
    Route::get('/shifts', [App\Http\Controllers\ShiftController::class, 'index'])->name('shifts.index');
    Route::get('/shifts/stock-shortages', [App\Http\Controllers\ShiftController::class, 'variances'])->name('stock-shortages.index');
    Route::post('/shifts/stock-shortages/{check}/verify', [App\Http\Controllers\ShiftController::class, 'verifyShortage'])->name('stock-shortages.verify');
    Route::post('/shifts/stock-shortages/{check}/revert', [App\Http\Controllers\ShiftController::class, 'revertShortageDecision'])->name('stock-shortages.revert');
    Route::get('/shifts/open', [App\Http\Controllers\ShiftController::class, 'create'])->name('shifts.create');
    Route::post('/shifts', [App\Http\Controllers\ShiftController::class, 'store'])->name('shifts.store');
    Route::get('/shifts/{shift}', [App\Http\Controllers\ShiftController::class, 'show'])->name('shifts.show');
    Route::get('/shifts/{shift}/close', [App\Http\Controllers\ShiftController::class, 'closeForm'])->name('shifts.close');
    Route::post('/shifts/{shift}/close', [App\Http\Controllers\ShiftController::class, 'close'])->name('shifts.close.store');

    // Day Closing
    Route::get('/money-shorts', [App\Http\Controllers\MoneyShortController::class, 'index'])->name('money-shorts.index');
    Route::post('/money-shorts/{dayClosing}/pay', [App\Http\Controllers\MoneyShortController::class, 'recordPayment'])->name('money-shorts.pay');
    Route::post('/money-shorts/{dayClosing}/salary-deduction', [App\Http\Controllers\MoneyShortController::class, 'recordSalaryDeduction'])->name('money-shorts.salary-deduction');
    Route::delete('/money-shorts/settlements/{settlement}', [App\Http\Controllers\MoneyShortController::class, 'undoSettlement'])->name('money-shorts.undo');
    Route::get('/day-closing/history', [App\Http\Controllers\DayClosingController::class, 'history'])->name('day-closing.history');
    Route::get('/day-closing/{dayClosing}', [App\Http\Controllers\DayClosingController::class, 'show'])->name('day-closing.show');
    Route::get('/day-closing', [App\Http\Controllers\DayClosingController::class, 'index'])->name('day-closing.index');
    Route::post('/day-closing', [App\Http\Controllers\DayClosingController::class, 'store'])->name('day-closing.store');
    Route::post('/day-closing/post-owner-sales', [App\Http\Controllers\DayClosingController::class, 'postOwnerDirectSales'])->name('day-closing.post-owner-sales');
    Route::post('/day-closing/{dayClosing}/verify', [App\Http\Controllers\DayClosingController::class, 'verify'])->name('day-closing.verify');

    // Business Settings (Owner)
    Route::get('/settings', [App\Http\Controllers\BusinessSettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/profile', [App\Http\Controllers\BusinessSettingsController::class, 'updateProfile'])->name('settings.profile.update');
    Route::put('/settings/finance', [App\Http\Controllers\BusinessSettingsController::class, 'updateFinance'])->name('settings.finance.update');
    Route::put('/settings/automation', [App\Http\Controllers\BusinessSettingsController::class, 'updateAutomation'])->name('settings.automation.update');
    Route::put('/settings/shift-rules', [App\Http\Controllers\BusinessSettingsController::class, 'updateShiftRules'])->name('settings.shift-rules.update');
    Route::put('/settings/payment-methods', [App\Http\Controllers\BusinessSettingsController::class, 'updatePaymentMethods'])->name('settings.payment-methods.update');

    // Sales Targets (Owner)
    Route::get('/sales-targets', [App\Http\Controllers\SalesTargetController::class, 'index'])->name('sales-targets.index');
    Route::post('/sales-targets', [App\Http\Controllers\SalesTargetController::class, 'store'])->name('sales-targets.store');
    Route::put('/sales-targets/{salesTarget}', [App\Http\Controllers\SalesTargetController::class, 'update'])->name('sales-targets.update');
    Route::delete('/sales-targets/{salesTarget}', [App\Http\Controllers\SalesTargetController::class, 'destroy'])->name('sales-targets.destroy');

    // Branches (Owner)
    Route::post('/businesses/switch', [App\Http\Controllers\BusinessSwitchController::class, 'switch'])->name('businesses.switch');
    Route::get('/branches', [App\Http\Controllers\BranchController::class, 'index'])->name('branches.index');
    Route::post('/branches/switch', [App\Http\Controllers\BranchController::class, 'switch'])->name('branches.switch');
    Route::post('/branches', [App\Http\Controllers\BranchController::class, 'store'])->name('branches.store');
    Route::put('/branches/{branch}', [App\Http\Controllers\BranchController::class, 'update'])->name('branches.update');
    Route::delete('/branches/{branch}', [App\Http\Controllers\BranchController::class, 'destroy'])->name('branches.destroy');

    // Owner Daily Business Report
    Route::get('/owner-reports', [App\Http\Controllers\OwnerDailyReportController::class, 'index'])->name('owner-reports.index');

    // Business Reports & Analytics
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::redirect('/', '/reports/circulation-profit');
        Route::get('/circulation-profit', [App\Http\Controllers\ReportController::class, 'circulationProfit'])->name('circulation-profit');
        Route::get('/daily-sales', [App\Http\Controllers\ReportController::class, 'dailySales'])->name('daily-sales');
        Route::get('/expenses', [App\Http\Controllers\ReportController::class, 'expenses'])->name('expenses');
        Route::get('/profit', [App\Http\Controllers\ReportController::class, 'profit'])->name('profit');
        Route::get('/sales-analytics', [App\Http\Controllers\ReportController::class, 'salesAnalytics'])->name('sales-analytics');
        Route::get('/products', [App\Http\Controllers\ReportController::class, 'products'])->name('products');
        Route::get('/debts', [App\Http\Controllers\ReportController::class, 'debts'])->name('debts');
    });

    Route::get('/owner-reports/{date}', [App\Http\Controllers\OwnerDailyReportController::class, 'show'])->name('owner-reports.show')->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}');
    Route::post('/owner-reports/{date}/expenses', [App\Http\Controllers\OwnerDailyReportController::class, 'storeExpense'])->name('owner-reports.expenses.store')->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}');
    Route::delete('/owner-reports/{date}/expenses/{expense}', [App\Http\Controllers\OwnerDailyReportController::class, 'destroyExpense'])->name('owner-reports.expenses.destroy')->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}');
    Route::post('/owner-reports/{date}/finalize', [App\Http\Controllers\OwnerDailyReportController::class, 'finalize'])->name('owner-reports.finalize')->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}');

    // Petty Cash (Owner)
    Route::get('/petty-cash', [App\Http\Controllers\PettyCashController::class, 'index'])->name('petty-cash.index');
    Route::get('/petty-cash/balances', [App\Http\Controllers\PettyCashController::class, 'balances'])->name('petty-cash.balances');
    Route::post('/petty-cash', [App\Http\Controllers\PettyCashController::class, 'store'])->name('petty-cash.store');
    Route::delete('/petty-cash/{expense}', [App\Http\Controllers\PettyCashController::class, 'destroy'])->name('petty-cash.destroy');

    // Supplier Management
    Route::resource('/suppliers', App\Http\Controllers\SupplierController::class);

    // Customer Management
    Route::resource('/customers', App\Http\Controllers\CustomerController::class);
    Route::get('/customer-communications', [App\Http\Controllers\CustomerCommunicationController::class, 'index'])->name('customer-communications.index');
    Route::post('/customer-communications/send', [App\Http\Controllers\CustomerCommunicationController::class, 'send'])->name('customer-communications.send');
    Route::delete('/customer-communications/campaigns/{campaign}', [App\Http\Controllers\CustomerCommunicationController::class, 'cancelCampaign'])->name('customer-communications.cancel');

    // Staff Management Routes
    Route::post('/employees/{employee}/reset-password', [App\Http\Controllers\StaffController::class, 'resetPassword'])->name('employees.reset-password');
    Route::post('/employees/{employee}/toggle-status', [App\Http\Controllers\StaffController::class, 'toggleStatus'])->name('employees.toggle-status');
    Route::resource('/employees', App\Http\Controllers\StaffController::class);
    Route::resource('/roles', App\Http\Controllers\RoleController::class);
    });
});

Route::post('/stop-impersonating', [App\Http\Controllers\Admin\ImpersonationController::class, 'stopImpersonating'])->name('stop-impersonating')->middleware(['auth', 'check.user.active']);

Route::get('/subscription-expired', [App\Http\Controllers\SubscriptionController::class, 'expired'])
    ->name('subscription.expired')
    ->middleware('auth');
