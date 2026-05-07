<?php

use App\Http\Controllers\Api\BusinessVoucherApiController;
use App\Http\Controllers\Api\VoucherApiController;
use App\Http\Controllers\Api\VoucherRedemptionApiController;
use Illuminate\Support\Facades\Route;

Route::get('/vouchers', [VoucherApiController::class, 'index']);

Route::scopeBindings()->group(function () {
    Route::get('/vouchers/{listing:slug}/{voucher:slug}', [VoucherApiController::class, 'show']);
    Route::post('/vouchers/{listing:slug}/{voucher:slug}/redeem', [VoucherRedemptionApiController::class, 'redeem'])->middleware('auth');
});

Route::middleware('auth')->group(function () {
    Route::get('/me/vouchers', [VoucherRedemptionApiController::class, 'mine']);
    Route::post('/voucher-redemptions/{code}/consume', [VoucherRedemptionApiController::class, 'consume']);
});

Route::middleware('auth')->scopeBindings()->group(function () {
    Route::get('/listings/{listing:slug}/vouchers', [BusinessVoucherApiController::class, 'index']);
    Route::post('/listings/{listing:slug}/vouchers', [BusinessVoucherApiController::class, 'store']);
    Route::get('/listings/{listing:slug}/vouchers/stats', [BusinessVoucherApiController::class, 'stats']);
    Route::put('/listings/{listing:slug}/vouchers/{voucher}', [BusinessVoucherApiController::class, 'update']);
    Route::delete('/listings/{listing:slug}/vouchers/{voucher}', [BusinessVoucherApiController::class, 'destroy']);
});

