<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\BusinessRegistrationController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('landing.index');

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

// Admin / Software Owner Routes
Route::middleware(['auth', 'check.user.active'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/businesses', [App\Http\Controllers\Admin\BusinessController::class, 'index'])->name('businesses.index');
    Route::get('/businesses/create', [App\Http\Controllers\Admin\BusinessController::class, 'create'])->name('businesses.create');
    Route::post('/businesses', [App\Http\Controllers\Admin\BusinessController::class, 'store'])->name('businesses.store');
    Route::get('/businesses/{business}/edit', [App\Http\Controllers\Admin\BusinessController::class, 'edit'])->name('businesses.edit');
    Route::put('/businesses/{business}', [App\Http\Controllers\Admin\BusinessController::class, 'update'])->name('businesses.update');
    Route::post('/businesses/{business}/toggle-status', [App\Http\Controllers\Admin\BusinessController::class, 'toggleStatus'])->name('businesses.toggle-status');
    Route::post('/businesses/{business}/approve', [App\Http\Controllers\Admin\BusinessController::class, 'approveRegistration'])->name('businesses.approve');
    Route::post('/businesses/{business}/reject', [App\Http\Controllers\Admin\BusinessController::class, 'rejectRegistration'])->name('businesses.reject');
    
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
    Route::get('/support', [App\Http\Controllers\SupportTicketController::class, 'index'])->name('tickets.index');
    Route::get('/support/create', [App\Http\Controllers\SupportTicketController::class, 'create'])->name('tickets.create');
    Route::post('/support', [App\Http\Controllers\SupportTicketController::class, 'store'])->name('tickets.store');
    Route::get('/support/{ticket}', [App\Http\Controllers\SupportTicketController::class, 'show'])->name('tickets.show_tenant');

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

    // Notes & reminders
    Route::get('/notes', [App\Http\Controllers\BusinessNoteController::class, 'index'])->name('notes.index');
    Route::post('/notes', [App\Http\Controllers\BusinessNoteController::class, 'store'])->name('notes.store');
    Route::put('/notes/{note}', [App\Http\Controllers\BusinessNoteController::class, 'update'])->name('notes.update');
    Route::delete('/notes/{note}', [App\Http\Controllers\BusinessNoteController::class, 'destroy'])->name('notes.destroy');
    Route::post('/notes/{note}/complete', [App\Http\Controllers\BusinessNoteController::class, 'complete'])->name('notes.complete');

    // Sales / POS Routes
    Route::get('/debts', [App\Http\Controllers\DebtController::class, 'index'])->name('debts.index');
    Route::get('/invoices', [App\Http\Controllers\InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [App\Http\Controllers\InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [App\Http\Controllers\InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{invoice}', [App\Http\Controllers\InvoiceController::class, 'show'])->name('invoices.show');
    Route::resource('/sales', App\Http\Controllers\SaleController::class);
    Route::post('/sales/{sale}/pay', [App\Http\Controllers\SaleController::class, 'pay'])->name('sales.pay');
    Route::post('/sales/{sale}/cancel', [App\Http\Controllers\SaleController::class, 'cancel'])->name('sales.cancel');

    // Sales Shifts
    Route::get('/shifts', [App\Http\Controllers\ShiftController::class, 'index'])->name('shifts.index');
    Route::get('/shifts/stock-shortages', [App\Http\Controllers\ShiftController::class, 'variances'])->name('stock-shortages.index');
    Route::post('/shifts/stock-shortages/{check}/verify', [App\Http\Controllers\ShiftController::class, 'verifyShortage'])->name('stock-shortages.verify');
    Route::get('/shifts/open', [App\Http\Controllers\ShiftController::class, 'create'])->name('shifts.create');
    Route::post('/shifts', [App\Http\Controllers\ShiftController::class, 'store'])->name('shifts.store');
    Route::get('/shifts/{shift}', [App\Http\Controllers\ShiftController::class, 'show'])->name('shifts.show');
    Route::get('/shifts/{shift}/close', [App\Http\Controllers\ShiftController::class, 'closeForm'])->name('shifts.close');
    Route::post('/shifts/{shift}/close', [App\Http\Controllers\ShiftController::class, 'close'])->name('shifts.close.store');

    // Day Closing
    Route::get('/day-closing/history', [App\Http\Controllers\DayClosingController::class, 'history'])->name('day-closing.history');
    Route::get('/day-closing/{dayClosing}', [App\Http\Controllers\DayClosingController::class, 'show'])->name('day-closing.show');
    Route::get('/day-closing', [App\Http\Controllers\DayClosingController::class, 'index'])->name('day-closing.index');
    Route::post('/day-closing', [App\Http\Controllers\DayClosingController::class, 'store'])->name('day-closing.store');
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

    // Staff Management Routes
    Route::post('/employees/{employee}/reset-password', [App\Http\Controllers\StaffController::class, 'resetPassword'])->name('employees.reset-password');
    Route::post('/employees/{employee}/toggle-status', [App\Http\Controllers\StaffController::class, 'toggleStatus'])->name('employees.toggle-status');
    Route::resource('/employees', App\Http\Controllers\StaffController::class);
    Route::resource('/roles', App\Http\Controllers\RoleController::class);
});

Route::post('/stop-impersonating', [App\Http\Controllers\Admin\ImpersonationController::class, 'stopImpersonating'])->name('stop-impersonating')->middleware(['auth', 'check.user.active']);

Route::get('/subscription-expired', [App\Http\Controllers\SubscriptionController::class, 'expired'])
    ->name('subscription.expired')
    ->middleware('auth');
