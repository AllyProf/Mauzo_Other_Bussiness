<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\BusinessRegistrationController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerCommunicationController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DayClosingController;
use App\Http\Controllers\Api\V1\DebtController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\ItemController;
use App\Http\Controllers\Api\V1\LiveSalesController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OwnerReportController;
use App\Http\Controllers\Api\V1\PackagingController;
use App\Http\Controllers\Api\V1\ReceivingController;
use App\Http\Controllers\Api\V1\ReferenceController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\PettyCashController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SalesTargetController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Controllers\Api\V1\SupplierController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::get('/register-business', [BusinessRegistrationController::class, 'options']);
    Route::post('/register-business/send-code', [BusinessRegistrationController::class, 'sendCode']);
    Route::post('/register-business', [BusinessRegistrationController::class, 'register']);

    Route::middleware([
        'auth:sanctum',
        'api.user.active',
        'api.tenant',
        'api.subscription',
    ])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/switch-business', [AuthController::class, 'switchBusiness']);
        Route::post('/auth/switch-branch', [AuthController::class, 'switchBranch']);

        // In-app notifications (poll-based — no Firebase)
        Route::post('/devices', [DeviceController::class, 'store']);
        Route::delete('/devices/{token}', [DeviceController::class, 'destroy'])->where('token', '.*');
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/preferences', [NotificationController::class, 'preferences']);
        Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

        Route::get('/dashboard/today', [DashboardController::class, 'today']);

        Route::get('/payment-methods', [ReferenceController::class, 'paymentMethods']);
        Route::get('/branches', [BranchController::class, 'index']);
        Route::post('/branches', [BranchController::class, 'store']);
        Route::put('/branches/{branch}', [BranchController::class, 'update']);
        Route::delete('/branches/{branch}', [BranchController::class, 'destroy']);

        // Business settings (same as web /settings)
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::put('/settings/profile', [SettingsController::class, 'updateProfile']);
        Route::put('/settings/finance', [SettingsController::class, 'updateFinance']);
        Route::put('/settings/automation', [SettingsController::class, 'updateAutomation']);
        Route::put('/settings/shift-rules', [SettingsController::class, 'updateShiftRules']);
        Route::put('/settings/payment-methods', [SettingsController::class, 'updatePaymentMethods']);

        Route::get('/shifts', [ShiftController::class, 'index']);
        Route::get('/shifts/current', [ShiftController::class, 'current']);
        Route::get('/shifts/open-form', [ShiftController::class, 'openForm']);
        Route::post('/shifts/open', [ShiftController::class, 'open']);
        Route::get('/shifts/{shift}', [ShiftController::class, 'show']);
        Route::post('/shifts/{shift}/close', [ShiftController::class, 'close']);

        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::post('/categories/import-templates', [CategoryController::class, 'importTemplates']);
        Route::delete('/categories/clear-all', [CategoryController::class, 'clearAll']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        Route::get('/packagings', [PackagingController::class, 'index']);
        Route::post('/packagings', [PackagingController::class, 'store']);
        Route::post('/packagings/import-templates', [PackagingController::class, 'importTemplates']);
        Route::delete('/packagings/clear-all', [PackagingController::class, 'clearAll']);
        Route::put('/packagings/{packaging}', [PackagingController::class, 'update']);
        Route::delete('/packagings/{packaging}', [PackagingController::class, 'destroy']);

        Route::get('/suppliers', [SupplierController::class, 'index']);
        Route::get('/suppliers/create-form', [SupplierController::class, 'createForm']);
        Route::post('/suppliers', [SupplierController::class, 'store']);
        Route::post('/suppliers/migrate-from-branch', [SupplierController::class, 'migrateFromBranch']);
        Route::get('/suppliers/branch/{branch}/list', [SupplierController::class, 'listForBranch']);
        Route::get('/suppliers/{supplier}', [SupplierController::class, 'show']);
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update']);
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy']);

        Route::get('/items', [ItemController::class, 'index']);
        Route::get('/items/stock', [ItemController::class, 'stock']);
        Route::get('/items/create-form', [ItemController::class, 'createForm']);
        Route::get('/items/check-name', [ItemController::class, 'checkName']);
        Route::get('/items/search', [ItemController::class, 'search']);
        Route::get('/items/lookup-barcode', [ItemController::class, 'lookupBarcode']);
        Route::post('/items', [ItemController::class, 'store']);
        Route::get('/items/{item}/barcodes', [ItemController::class, 'barcodes']);
        Route::get('/items/{item}/barcodes/print', [ItemController::class, 'printBarcodesData']);

        Route::get('/receivings', [ReceivingController::class, 'index']);
        Route::get('/receivings/create-form', [ReceivingController::class, 'createForm']);
        Route::post('/receivings', [ReceivingController::class, 'store']);
        Route::get('/receivings/{receiving}', [ReceivingController::class, 'show']);
        Route::get('/receivings/{receiving}/cancel-preview', [ReceivingController::class, 'cancelPreview']);
        Route::post('/receivings/{receiving}/cancel', [ReceivingController::class, 'cancel']);

        Route::get('/items/{item}/history', [ItemController::class, 'history']);
        Route::get('/items/{item}', [ItemController::class, 'show']);
        Route::put('/items/{item}', [ItemController::class, 'update']);
        Route::delete('/items/{item}', [ItemController::class, 'destroy']);

        Route::get('/sales', [SaleController::class, 'index']);
        Route::post('/sales', [SaleController::class, 'store']);
        Route::get('/sales/{sale}', [SaleController::class, 'show']);
        Route::post('/sales/{sale}/pay', [SaleController::class, 'pay']);
        Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel']);

        // Live Sales Pulse (same as web /live-sales)
        Route::get('/live-sales', [LiveSalesController::class, 'index']);

        // Invoices (product invoices — same as web /invoices)
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::get('/invoices/create-form', [InvoiceController::class, 'createForm']);
        Route::post('/invoices', [InvoiceController::class, 'store']);
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);

        Route::get('/debts', [DebtController::class, 'index']);
        Route::post('/debts/{sale}/collect', [DebtController::class, 'collect']);

        Route::get('/day-closing/preview', [DayClosingController::class, 'preview']);
        Route::get('/day-closing/pending', [DayClosingController::class, 'pending']);
        Route::get('/day-closing/review', [DayClosingController::class, 'review']);
        Route::get('/day-closing/owner-direct', [DayClosingController::class, 'ownerDirectPreview']);
        Route::post('/day-closing/owner-direct', [DayClosingController::class, 'postOwnerDirectSales']);
        Route::get('/day-closing', [DayClosingController::class, 'index']);
        Route::post('/day-closing', [DayClosingController::class, 'store']);
        Route::get('/day-closing/{dayClosing}', [DayClosingController::class, 'show']);
        Route::post('/day-closing/{dayClosing}/verify', [DayClosingController::class, 'verify']);

        Route::get('/owner-reports', [OwnerReportController::class, 'index']);
        Route::get('/owner-reports/{date}', [OwnerReportController::class, 'show'])->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}');
        Route::post('/owner-reports/{date}/expenses', [OwnerReportController::class, 'storeExpense'])->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}');
        Route::delete('/owner-reports/{date}/expenses/{expense}', [OwnerReportController::class, 'destroyExpense'])->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}');
        Route::post('/owner-reports/{date}/finalize', [OwnerReportController::class, 'finalize'])->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}');

        // Business reports (same as web /reports/*)
        Route::get('/reports', [ReportController::class, 'index']);
        Route::get('/reports/circulation-profit', [ReportController::class, 'circulationProfit']);
        Route::get('/reports/daily-sales', [ReportController::class, 'dailySales']);
        Route::get('/reports/expenses', [ReportController::class, 'expenses']);
        Route::get('/reports/profit', [ReportController::class, 'profit']);
        Route::get('/reports/sales-analytics', [ReportController::class, 'salesAnalytics']);
        Route::get('/reports/products', [ReportController::class, 'products']);
        Route::get('/reports/debts', [ReportController::class, 'debts']);

        Route::get('/customers', [CustomerController::class, 'index']);
        Route::get('/customers/create-form', [CustomerController::class, 'createForm']);
        Route::get('/customers/search', [CustomerController::class, 'search']);
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::get('/customers/{customer}', [CustomerController::class, 'show']);
        Route::put('/customers/{customer}', [CustomerController::class, 'update']);
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy']);

        Route::get('/customer-communications', [CustomerCommunicationController::class, 'index']);
        Route::post('/customer-communications/send', [CustomerCommunicationController::class, 'send']);
        Route::delete('/customer-communications/campaigns/{campaign}', [CustomerCommunicationController::class, 'cancelCampaign']);

        Route::get('/sales-targets', [SalesTargetController::class, 'index']);
        Route::post('/sales-targets', [SalesTargetController::class, 'store']);
        Route::get('/sales-targets/{salesTarget}', [SalesTargetController::class, 'show']);
        Route::put('/sales-targets/{salesTarget}', [SalesTargetController::class, 'update']);
        Route::delete('/sales-targets/{salesTarget}', [SalesTargetController::class, 'destroy']);

        Route::get('/roles', [RoleController::class, 'index']);
        Route::get('/roles/create-form', [RoleController::class, 'createForm']);
        Route::post('/roles', [RoleController::class, 'store']);

        Route::get('/employees', [EmployeeController::class, 'index']);
        Route::get('/employees/create-form', [EmployeeController::class, 'createForm']);
        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::put('/employees/{employee}', [EmployeeController::class, 'update']);
        Route::post('/employees/{employee}/reset-password', [EmployeeController::class, 'resetPassword']);
        Route::post('/employees/{employee}/toggle-status', [EmployeeController::class, 'toggleStatus']);
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy']);

        // Petty Cash
        Route::get('/petty-cash', [PettyCashController::class, 'index']);
        Route::get('/petty-cash/balances', [PettyCashController::class, 'balances']);
        Route::post('/petty-cash', [PettyCashController::class, 'store']);
        Route::delete('/petty-cash/{expense}', [PettyCashController::class, 'destroy']);
    });
});
