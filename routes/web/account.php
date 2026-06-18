<?php

use App\Http\Controllers\AccountAdCampaignController;
use App\Http\Controllers\AccountAdvertisingDashboardController;
use App\Http\Controllers\AccountAiAssistController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountEventController;
use App\Http\Controllers\AccountInvoiceController;
use App\Http\Controllers\AccountListingController;
use App\Http\Controllers\AccountPushCampaignController;
use App\Http\Controllers\AccountSubmissionController;
use App\Http\Controllers\AccountVoucherController;
use App\Http\Controllers\AccountVoucherRedemptionController;
use App\Http\Controllers\AccountWalletController;
use Illuminate\Support\Facades\Route;

Route::get('/account', [AccountController::class, 'index'])->middleware('auth')->name('account.index');
Route::get('/account/advertising', [AccountAdvertisingDashboardController::class, 'index'])
    ->middleware('auth')
    ->name('account.advertising.index');

Route::get('/account/invoices', [AccountInvoiceController::class, 'index'])->middleware('auth')->name('account.invoices.index');
Route::get('/account/invoices/{invoice}', [AccountInvoiceController::class, 'show'])
    ->middleware('auth')
    ->name('account.invoices.show');

Route::get('/account/listings', [AccountListingController::class, 'index'])->middleware('auth')->name('account.listings.index');
Route::get('/account/listings/{listing}', [AccountListingController::class, 'show'])
    ->middleware('auth')
    ->name('account.listings.show');
Route::get('/account/listings/{listing}/edit', [AccountListingController::class, 'edit'])
    ->middleware('auth')
    ->name('account.listings.edit');
Route::put('/account/listings/{listing}', [AccountListingController::class, 'update'])
    ->middleware('auth')
    ->name('account.listings.update');
Route::delete('/account/listings/{listing}', [AccountListingController::class, 'destroy'])
    ->middleware('auth')
    ->name('account.listings.destroy');

Route::get('/account/wallet', [AccountWalletController::class, 'index'])
    ->middleware(['auth', 'role:staff'])
    ->name('account.wallet.index');
Route::post('/account/wallet/payout-requests', [AccountWalletController::class, 'requestPayout'])
    ->middleware(['auth', 'role:staff'])
    ->name('account.wallet.payout-requests.store');
Route::delete('/account/wallet/payout-requests/{payoutRequest}', [AccountWalletController::class, 'cancelPayout'])
    ->middleware(['auth', 'role:staff'])
    ->name('account.wallet.payout-requests.cancel');
Route::get('/account/wallet/statement.pdf', [AccountWalletController::class, 'statementPdf'])
    ->middleware(['auth', 'role:staff'])
    ->name('account.wallet.statement.pdf');

Route::get('/account/submissions', [AccountSubmissionController::class, 'index'])
    ->middleware('auth')
    ->name('account.submissions.index');
Route::get('/account/vouchers', [AccountVoucherRedemptionController::class, 'index'])
    ->middleware('auth')
    ->name('account.vouchers.index');

Route::middleware('auth')->scopeBindings()->group(function () {
    Route::get('/account/listings/{listing}/vouchers', [AccountVoucherController::class, 'index'])->name('account.listings.vouchers.index');
    Route::get('/account/listings/{listing}/vouchers/dashboard', [AccountVoucherController::class, 'dashboard'])->name('account.listings.vouchers.dashboard');
    Route::get('/account/listings/{listing}/vouchers/create', [AccountVoucherController::class, 'create'])->name('account.listings.vouchers.create');
    Route::post('/account/listings/{listing}/ai/voucher-copy', [AccountAiAssistController::class, 'voucherCopy'])
        ->middleware('throttle:12,1')
        ->name('account.listings.ai.voucher-copy');
    Route::post('/account/listings/{listing}/vouchers', [AccountVoucherController::class, 'store'])->name('account.listings.vouchers.store');
    Route::get('/account/listings/{listing}/vouchers/{voucher}/edit', [AccountVoucherController::class, 'edit'])->name('account.listings.vouchers.edit');
    Route::put('/account/listings/{listing}/vouchers/{voucher}', [AccountVoucherController::class, 'update'])->name('account.listings.vouchers.update');
    Route::delete('/account/listings/{listing}/vouchers/{voucher}', [AccountVoucherController::class, 'destroy'])->name('account.listings.vouchers.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/account/listings/{listing}/ad-campaigns', [AccountAdCampaignController::class, 'index'])->name('account.listings.ad-campaigns.index');
    Route::get('/account/listings/{listing}/ad-campaigns/create', [AccountAdCampaignController::class, 'create'])->name('account.listings.ad-campaigns.create');
    Route::post('/account/listings/{listing}/ad-campaigns', [AccountAdCampaignController::class, 'store'])->name('account.listings.ad-campaigns.store');
    Route::get('/account/listings/{listing}/ad-campaigns/{adCampaign}/edit', [AccountAdCampaignController::class, 'edit'])->name('account.listings.ad-campaigns.edit');
    Route::put('/account/listings/{listing}/ad-campaigns/{adCampaign}', [AccountAdCampaignController::class, 'update'])->name('account.listings.ad-campaigns.update');
    Route::delete('/account/listings/{listing}/ad-campaigns/{adCampaign}', [AccountAdCampaignController::class, 'destroy'])->name('account.listings.ad-campaigns.destroy');

    Route::get('/account/listings/{listing}/push-campaigns', [AccountPushCampaignController::class, 'index'])->name('account.listings.push-campaigns.index');
    Route::get('/account/listings/{listing}/push-campaigns/create', [AccountPushCampaignController::class, 'create'])->name('account.listings.push-campaigns.create');
    Route::post('/account/listings/{listing}/ai/push-copy', [AccountAiAssistController::class, 'pushCopy'])
        ->middleware('throttle:12,1')
        ->name('account.listings.ai.push-copy');
    Route::post('/account/listings/{listing}/push-campaigns', [AccountPushCampaignController::class, 'store'])->name('account.listings.push-campaigns.store');
    Route::get('/account/listings/{listing}/push-campaigns/{pushCampaign}/edit', [AccountPushCampaignController::class, 'edit'])->name('account.listings.push-campaigns.edit');
    Route::put('/account/listings/{listing}/push-campaigns/{pushCampaign}', [AccountPushCampaignController::class, 'update'])->name('account.listings.push-campaigns.update');
    Route::post('/account/listings/{listing}/push-campaigns/{pushCampaign}/dispatch', [AccountPushCampaignController::class, 'dispatch'])->name('account.listings.push-campaigns.dispatch');
    Route::delete('/account/listings/{listing}/push-campaigns/{pushCampaign}', [AccountPushCampaignController::class, 'destroy'])->name('account.listings.push-campaigns.destroy');

    Route::get('/account/listings/{listing}/events', [AccountEventController::class, 'index'])->name('account.listings.events.index');
    Route::get('/account/listings/{listing}/events/create', [AccountEventController::class, 'create'])->name('account.listings.events.create');
    Route::post('/account/listings/{listing}/ai/event-description', [AccountAiAssistController::class, 'eventDescription'])
        ->middleware('throttle:12,1')
        ->name('account.listings.ai.event-description');
    Route::post('/account/listings/{listing}/events', [AccountEventController::class, 'store'])->name('account.listings.events.store');
    Route::get('/account/listings/{listing}/events/{event}/edit', [AccountEventController::class, 'edit'])->name('account.listings.events.edit');
    Route::put('/account/listings/{listing}/events/{event}', [AccountEventController::class, 'update'])->name('account.listings.events.update');
    Route::delete('/account/listings/{listing}/events/{event}', [AccountEventController::class, 'destroy'])->name('account.listings.events.destroy');

    Route::post('/account/listings/{listing}/reviews/{review}/response', [AccountListingController::class, 'respondToReview'])->name('account.listings.reviews.respond');
    Route::post('/account/listings/{listing}/photos', [AccountListingController::class, 'storePhoto'])->name('account.listings.photos.store');
    Route::post('/account/listings/{listing}/photos/{photo}/primary', [AccountListingController::class, 'makePrimaryPhoto'])->name('account.listings.photos.primary');
    Route::delete('/account/listings/{listing}/photos/{photo}', [AccountListingController::class, 'destroyPhoto'])->name('account.listings.photos.destroy');
});
