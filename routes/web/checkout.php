<?php

use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('/basket', [CheckoutController::class, 'basket'])->name('basket.index');
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::get('/checkout/subscriptions/{subscription}/renew', [CheckoutController::class, 'renewSubscription'])
    ->middleware('auth')
    ->name('checkout.subscriptions.renew');
Route::post('/checkout/start', [CheckoutController::class, 'start'])
    ->middleware('auth')
    ->name('checkout.start');
Route::get('/checkout/orders/{order}', [CheckoutController::class, 'show'])
    ->middleware('auth')
    ->name('checkout.show');
Route::post('/checkout/orders/{order}/payfast/initiate', [CheckoutController::class, 'payfastInitiate'])
    ->middleware('auth')
    ->name('checkout.payfast.initiate');
Route::post('/checkout/orders/{order}/payfast/retry', [CheckoutController::class, 'retryPayment'])
    ->middleware('auth')
    ->name('checkout.payfast.retry');
Route::post('/checkout/orders/{order}/invoice/send', [CheckoutController::class, 'sendInvoice'])
    ->middleware('auth')
    ->name('checkout.invoice.send');
Route::post('/checkout/payfast/callback', [CheckoutController::class, 'payfastCallback'])
    ->middleware('throttle:payfast-callback')
    ->name('checkout.payfast.callback');
