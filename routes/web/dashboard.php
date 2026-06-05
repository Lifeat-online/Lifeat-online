<?php

use App\Http\Controllers\ClassifiedSubmissionController;
use App\Http\Controllers\CivicFaultReportController;
use App\Http\Controllers\Councillor\CivicFaultReportController as CouncillorCivicFaultReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StaffAdvertisingDashboardController;
use App\Http\Controllers\StaffVoucherRedemptionController;
use App\Http\Controllers\Writer\ArticleController as WriterArticleController;
use App\Http\Controllers\Writer\EarningsController as WriterEarningsController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', DashboardController::class)->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/my-classifieds', [ClassifiedSubmissionController::class, 'index'])->name('classifieds.manage.index');
    Route::get('/my-classifieds/create', [ClassifiedSubmissionController::class, 'create'])->name('classifieds.manage.create');
    Route::post('/my-classifieds', [ClassifiedSubmissionController::class, 'store'])
        ->middleware('throttle:public-form')
        ->name('classifieds.manage.store');
    Route::get('/my-classifieds/{classified}/edit', [ClassifiedSubmissionController::class, 'edit'])->name('classifieds.manage.edit');
    Route::put('/my-classifieds/{classified}', [ClassifiedSubmissionController::class, 'update'])->name('classifieds.manage.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/faults/report', [CivicFaultReportController::class, 'create'])->name('faults.report.create');
    Route::post('/faults/report/categorize', [CivicFaultReportController::class, 'categorize'])
        ->middleware('throttle:12,1')
        ->name('faults.report.categorize');
    Route::post('/faults/report', [CivicFaultReportController::class, 'store'])
        ->middleware('throttle:public-form')
        ->name('faults.report.store');
});

Route::middleware(['auth', 'role:admin,editor,staff,writer'])->group(function () {
    Route::get('/staff/dashboard', \App\Http\Controllers\StaffDashboardController::class)->name('staff.dashboard');
});

Route::middleware(['auth', 'role:staff,admin'])->group(function () {
    Route::get('/staff/advertising', [StaffAdvertisingDashboardController::class, 'index'])->name('staff.advertising.index');
});

Route::middleware('auth')->group(function () {
    Route::get('/staff/vouchers/redeem', [StaffVoucherRedemptionController::class, 'show'])->name('staff.vouchers.redeem');
    Route::post('/staff/vouchers/consume', [StaffVoucherRedemptionController::class, 'consume'])
        ->middleware('throttle:voucher-redemption')
        ->name('staff.vouchers.consume');
});

Route::middleware(['auth', 'role:writer'])->prefix('writer')->name('writer.')->group(function () {
    Route::resource('articles', WriterArticleController::class)->except('show', 'destroy');
    Route::get('earnings', [WriterEarningsController::class, 'index'])->name('earnings.index');
});

Route::middleware(['auth', 'verified', 'role:councillor'])->prefix('councillor')->name('councillor.')->group(function () {
    Route::get('/faults', [CouncillorCivicFaultReportController::class, 'index'])->name('faults.index');
    Route::post('/faults/{faultReport}/status', [CouncillorCivicFaultReportController::class, 'updateStatus'])->name('faults.status');
});
