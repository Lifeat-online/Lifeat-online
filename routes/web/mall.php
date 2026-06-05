<?php

use App\Http\Controllers\Mall\AccountOrderController as MallAccountOrderController;
use App\Http\Controllers\Mall\Admin\MallCommissionController;
use App\Http\Controllers\Mall\Admin\MallOrderController as MallAdminOrderController;
use App\Http\Controllers\Mall\Admin\MallProductCategoryController as MallAdminProductCategoryController;
use App\Http\Controllers\Mall\Admin\MallProductController as MallAdminProductController;
use App\Http\Controllers\Mall\Admin\MallStoreController as MallAdminStoreController;
use App\Http\Controllers\Mall\CartController as MallCartController;
use App\Http\Controllers\Mall\CheckoutController as MallCheckoutController;
use App\Http\Controllers\Mall\MallController;
use App\Http\Controllers\Mall\PaymentController as MallPaymentController;
use App\Http\Controllers\Mall\StoreController as MallStoreController;
use App\Http\Controllers\Mall\Vendor\VendorDashboardController as MallVendorDashboardController;
use App\Http\Controllers\Mall\Vendor\VendorEarningsController as MallVendorEarningsController;
use App\Http\Controllers\Mall\Vendor\VendorOrderController as MallVendorOrderController;
use App\Http\Controllers\Mall\Vendor\VendorProductCategoryController as MallVendorProductCategoryController;
use App\Http\Controllers\Mall\Vendor\VendorProductController as MallVendorProductController;
use App\Http\Controllers\Mall\Vendor\VendorRegistrationController as MallVendorRegistrationController;
use App\Http\Controllers\Mall\Vendor\VendorStoreController as MallVendorStoreController;
use Illuminate\Support\Facades\Route;

Route::prefix('mall')->name('mall.')->group(function () {
    Route::get('/', [MallController::class, 'index'])->name('index');

    Route::get('/stores/{store:slug}', [MallStoreController::class, 'window'])->name('stores.window');
    Route::get('/stores/{store:slug}/inside', [MallStoreController::class, 'index'])->name('stores.index');

    Route::scopeBindings()->group(function () {
        Route::get('/stores/{store:slug}/products/{product:slug}', [MallStoreController::class, 'product'])->name('stores.products.show');
        Route::post('/stores/{store:slug}/cart/items/{product:slug}', [MallCartController::class, 'store'])->name('cart.items.store');
    });

    Route::get('/stores/{store:slug}/cart', [MallCartController::class, 'show'])->name('cart.show');
    Route::patch('/stores/{store:slug}/cart/items/{item}', [MallCartController::class, 'update'])->name('cart.items.update');
    Route::delete('/stores/{store:slug}/cart/items/{item}', [MallCartController::class, 'destroy'])->name('cart.items.destroy');

    Route::middleware('auth')->group(function () {
        Route::get('/stores/{store:slug}/checkout', [MallCheckoutController::class, 'show'])->name('checkout.show');
        Route::post('/stores/{store:slug}/checkout', [MallCheckoutController::class, 'initiate'])->name('checkout.initiate');
        Route::get('/pudo/lockers', [MallCheckoutController::class, 'pudoLockers'])->middleware('throttle:30,1')->name('pudo.lockers');
        Route::post('/stores/{store:slug}/checkout/pudo/quote', [MallCheckoutController::class, 'pudoQuote'])
            ->middleware('throttle:30,1')
            ->name('checkout.pudo.quote');
        Route::get('/stores/{store:slug}/checkout/return', [MallCheckoutController::class, 'return'])->name('checkout.return');
        Route::get('/stores/{store:slug}/checkout/cancel', [MallCheckoutController::class, 'cancel'])->name('checkout.cancel');
    });
});

Route::post('/mall/payment/itn', [MallPaymentController::class, 'itn'])
    ->middleware('throttle:payfast-callback')
    ->name('mall.payment.itn');

Route::middleware('auth')->prefix('mall/vendor')->name('mall.vendor.')->group(function () {
    Route::get('/register', [MallVendorRegistrationController::class, 'create'])->name('register');
    Route::post('/register', [MallVendorRegistrationController::class, 'store'])->name('register.store');

    Route::middleware('mall.vendor')->group(function () {
        Route::get('/', [MallVendorDashboardController::class, 'index'])->name('dashboard');
        Route::get('/store', [MallVendorStoreController::class, 'edit'])->name('store.edit');
        Route::put('/store', [MallVendorStoreController::class, 'update'])->name('store.update');
        Route::get('/product-categories', [MallVendorProductCategoryController::class, 'index'])->name('product-categories.index');
        Route::get('/product-categories/create', [MallVendorProductCategoryController::class, 'create'])->name('product-categories.create');
        Route::post('/product-categories', [MallVendorProductCategoryController::class, 'store'])->name('product-categories.store');
        Route::get('/product-categories/{productCategory:id}/edit', [MallVendorProductCategoryController::class, 'edit'])->name('product-categories.edit');
        Route::put('/product-categories/{productCategory:id}', [MallVendorProductCategoryController::class, 'update'])->name('product-categories.update');
        Route::delete('/product-categories/{productCategory:id}', [MallVendorProductCategoryController::class, 'destroy'])->name('product-categories.destroy');
        Route::resource('products', MallVendorProductController::class)->except('show');
        Route::get('/orders', [MallVendorOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [MallVendorOrderController::class, 'show'])->name('orders.show');
        Route::put('/orders/{order}/status', [MallVendorOrderController::class, 'updateStatus'])->name('orders.status');
        Route::get('/earnings', [MallVendorEarningsController::class, 'index'])->name('earnings.index');
    });
});

Route::middleware('auth')->prefix('mall/account')->name('mall.account.')->group(function () {
    Route::get('/orders', [MallAccountOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [MallAccountOrderController::class, 'show'])->name('orders.show');
});

Route::middleware(['auth', 'mall.admin'])->prefix('mall/admin')->name('mall.admin.')->group(function () {
    Route::get('/stores', [MallAdminStoreController::class, 'index'])->name('stores.index');
    Route::get('/stores/{store:slug}', [MallAdminStoreController::class, 'show'])->name('stores.show');
    Route::get('/stores/{store:slug}/edit', [MallAdminStoreController::class, 'edit'])->name('stores.edit');
    Route::put('/stores/{store:slug}', [MallAdminStoreController::class, 'update'])->name('stores.update');
    Route::post('/stores/{store:slug}/approve', [MallAdminStoreController::class, 'approve'])->name('stores.approve');
    Route::post('/stores/{store:slug}/suspend', [MallAdminStoreController::class, 'suspend'])->name('stores.suspend');
    Route::get('/product-categories', [MallAdminProductCategoryController::class, 'index'])->name('product-categories.index');
    Route::get('/product-categories/create', [MallAdminProductCategoryController::class, 'create'])->name('product-categories.create');
    Route::post('/product-categories', [MallAdminProductCategoryController::class, 'store'])->name('product-categories.store');
    Route::get('/product-categories/{productCategory:id}/edit', [MallAdminProductCategoryController::class, 'edit'])->name('product-categories.edit');
    Route::put('/product-categories/{productCategory:id}', [MallAdminProductCategoryController::class, 'update'])->name('product-categories.update');
    Route::delete('/product-categories/{productCategory:id}', [MallAdminProductCategoryController::class, 'destroy'])->name('product-categories.destroy');
    Route::get('/products', [MallAdminProductController::class, 'index'])->name('products.index');
    Route::get('/products/{product:id}/edit', [MallAdminProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product:id}', [MallAdminProductController::class, 'update'])->name('products.update');
    Route::get('/orders', [MallAdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [MallAdminOrderController::class, 'show'])->name('orders.show');
    Route::get('/commissions', [MallCommissionController::class, 'index'])->name('commissions.index');
});
