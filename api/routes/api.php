<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AdministratorController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankTransferController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PayPalWebhookController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
| Login, and the two payment webhooks. Webhooks are intentionally NOT behind
| Sanctum — Stripe/PayPal can't send a bearer token, they send a signature
| instead, which each controller verifies itself before trusting the payload.
| These two routes must also be excluded from CSRF verification in
| bootstrap/app.php (see the README's "Wiring notes" section).
*/
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);
Route::post('/webhooks/paypal', [PayPalWebhookController::class, 'handle']);

// Storefront-facing: a customer submitting their bank transfer reference isn't
// an admin action, so it sits outside the admin auth:sanctum group below.
Route::post('/payments/{payment}/bank-transfer/submit', [BankTransferController::class, 'submit']);
Route::post('/orders/{order}/payments/initiate', [PaymentController::class, 'initiate']);

/*
|--------------------------------------------------------------------------
| Admin dashboard routes — require a valid Sanctum token AND, per-route,
| the specific granular permission the Roles & Permissions screen manages.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'locale'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/sales-series', [DashboardController::class, 'salesSeries']);

    // Products
    Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.view');
    Route::post('/products', [ProductController::class, 'store'])->middleware('permission:products.create');
    Route::get('/products/{product}', [ProductController::class, 'show'])->middleware('permission:products.view');
    Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('permission:products.edit');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete');
    Route::post('/products/{product}/adjust-stock', [ProductController::class, 'adjustStock'])->middleware('permission:products.edit');

    // Categories
    Route::get('/categories', [CategoryController::class, 'index'])->middleware('permission:products.view');
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:products.create');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('permission:products.edit');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:products.delete');

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->middleware('permission:orders.view');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('permission:orders.view');
    Route::get('/orders/{order}/activity', [OrderController::class, 'activity'])->middleware('permission:orders.view');
    Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus'])->middleware('permission:orders.edit');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->middleware('permission:orders.cancel');

    // Customers
    Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:customers.view');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:customers.create');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.view');
    Route::get('/customers/{customer}/orders', [CustomerController::class, 'orders'])->middleware('permission:customers.view');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.edit');
    Route::post('/customers/{customer}/status', [CustomerController::class, 'setStatus'])->middleware('permission:customers.edit');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:customers.delete');

    // Payments
    Route::get('/payments', [PaymentController::class, 'index'])->middleware('permission:payments.view');
    Route::get('/payments/summary', [PaymentController::class, 'summary'])->middleware('permission:payments.view');
    Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund'])->middleware('permission:payments.refund');

    // Bank transfer manual verification queue
    Route::get('/bank-transfers/pending', [BankTransferController::class, 'pending'])->middleware('permission:payments.verify');
    Route::post('/bank-transfers/{submission}/confirm', [BankTransferController::class, 'confirm'])->middleware('permission:payments.verify');
    Route::post('/bank-transfers/{submission}/reject', [BankTransferController::class, 'reject'])->middleware('permission:payments.verify');

    // Administrators & roles
    Route::get('/administrators', [AdministratorController::class, 'index'])->middleware('permission:administrators.manage');
    Route::post('/administrators', [AdministratorController::class, 'store'])->middleware('permission:administrators.manage');
    Route::post('/administrators/{admin}/status', [AdministratorController::class, 'setStatus'])->middleware('permission:administrators.manage');
    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:administrators.manage');
    Route::get('/permissions', [RoleController::class, 'permissions'])->middleware('permission:administrators.manage');
    Route::put('/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->middleware('permission:administrators.manage');

    // Activity log & settings
    Route::get('/activity-log', [ActivityLogController::class, 'index'])->middleware('permission:activity_log.view');
    Route::get('/settings', [SettingController::class, 'index']);
    Route::put('/settings', [SettingController::class, 'update'])->middleware('permission:settings.manage');
});
