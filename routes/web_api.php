<?php

use App\Http\Controllers\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Admin\CampaignController as AdminCampaignController;
use App\Http\Controllers\Admin\CivicFaultReportController as AdminCivicFaultReportController;
use App\Http\Controllers\Admin\CouncillorController as AdminCouncillorController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\ListingController as AdminListingController;
use App\Http\Controllers\Admin\MarketingIntegrationController as AdminMarketingIntegrationController;
use App\Http\Controllers\Admin\MetricsController as AdminMetricsController;
use App\Http\Controllers\Admin\VoucherController as AdminVoucherController;
use App\Http\Controllers\Api\ClientAdvertisingApiController;
use App\Http\Controllers\Api\StaffAdvertisingApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Session-backed dashboard JSON routes
|--------------------------------------------------------------------------
|
| These endpoints intentionally live on the web middleware stack because the
| Blade dashboards call them with the user's browser session. They keep the
| existing /api URI and api.* route names, but are not the decoupled external
| API described in the production-readiness roadmap.
|
*/

Route::middleware('auth')->prefix('api')->name('api.')->group(function () {
    Route::prefix('client/advertising')->name('client.advertising.')->group(function () {
        Route::get('/listings', [ClientAdvertisingApiController::class, 'listings'])->name('listings');
        Route::get('/listings/{listing}', [ClientAdvertisingApiController::class, 'summary'])->name('summary');
        Route::put('/listings/{listing}/integrations/{type}', [ClientAdvertisingApiController::class, 'updateIntegration'])->name('integrations.update');
    });

    Route::middleware('role:staff,admin')->prefix('staff/advertising')->name('staff.advertising.')->group(function () {
        Route::get('/businesses', [StaffAdvertisingApiController::class, 'businesses'])->name('businesses');
        Route::get('/businesses/{listing}', [StaffAdvertisingApiController::class, 'summary'])->name('summary');
        Route::put('/ad-campaigns/{adCampaign}', [StaffAdvertisingApiController::class, 'updateAdCampaign'])->name('ad-campaigns.update');
        Route::put('/push-campaigns/{pushCampaign}', [StaffAdvertisingApiController::class, 'updatePushCampaign'])->name('push-campaigns.update');
        Route::put('/businesses/{listing}/integrations/{type}', [StaffAdvertisingApiController::class, 'updateIntegration'])->name('integrations.update');
    });

    Route::middleware('role:admin,editor,staff,support')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/metrics', AdminMetricsController::class)->name('metrics');
        Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->middleware('role:admin,editor,support')->name('audit-logs.index');

        Route::get('/listings', [AdminListingController::class, 'index'])->name('listings.index');
        Route::post('/listings', [AdminListingController::class, 'store'])->name('listings.store');
        Route::get('/listings/{listing:slug}', [AdminListingController::class, 'show'])->name('listings.show');
        Route::put('/listings/{listing:slug}', [AdminListingController::class, 'update'])->name('listings.update');
        Route::delete('/listings/{listing:slug}', [AdminListingController::class, 'destroy'])->name('listings.destroy');
        Route::post('/listings/bulk', [AdminListingController::class, 'bulk'])->name('listings.bulk');

        Route::get('/events', [AdminEventController::class, 'index'])->name('events.index');
        Route::post('/events', [AdminEventController::class, 'store'])->name('events.store');
        Route::get('/events/{event:slug}', [AdminEventController::class, 'show'])->name('events.show');
        Route::put('/events/{event:slug}', [AdminEventController::class, 'update'])->name('events.update');
        Route::delete('/events/{event:slug}', [AdminEventController::class, 'destroy'])->name('events.destroy');
        Route::post('/events/bulk', [AdminEventController::class, 'bulk'])->name('events.bulk');

        Route::get('/articles', [AdminArticleController::class, 'index'])->name('articles.index');
        Route::post('/articles', [AdminArticleController::class, 'store'])->middleware('role:admin,editor')->name('articles.store');
        Route::get('/articles/{article:slug}', [AdminArticleController::class, 'show'])->name('articles.show');
        Route::put('/articles/{article:slug}', [AdminArticleController::class, 'update'])->middleware('role:admin,editor')->name('articles.update');
        Route::delete('/articles/{article:slug}', [AdminArticleController::class, 'destroy'])->middleware('role:admin,editor')->name('articles.destroy');
        Route::post('/articles/bulk', [AdminArticleController::class, 'bulk'])->middleware('role:admin,editor')->name('articles.bulk');

        Route::get('/vouchers', [AdminVoucherController::class, 'index'])->middleware('role:admin,editor,staff')->name('vouchers.index');
        Route::post('/vouchers', [AdminVoucherController::class, 'store'])->middleware('role:admin,editor,staff')->name('vouchers.store');
        Route::get('/vouchers/{voucher:id}', [AdminVoucherController::class, 'show'])->middleware('role:admin,editor,staff')->name('vouchers.show');
        Route::put('/vouchers/{voucher:id}', [AdminVoucherController::class, 'update'])->middleware('role:admin,editor,staff')->name('vouchers.update');
        Route::delete('/vouchers/{voucher:id}', [AdminVoucherController::class, 'destroy'])->middleware('role:admin,editor,staff')->name('vouchers.destroy');
        Route::post('/vouchers/bulk', [AdminVoucherController::class, 'bulk'])->middleware('role:admin,editor,staff')->name('vouchers.bulk');

        Route::get('/integrations', [AdminMarketingIntegrationController::class, 'index'])->middleware('role:admin,editor,staff')->name('integrations.index');
        Route::post('/integrations', [AdminMarketingIntegrationController::class, 'store'])->middleware('role:admin,editor,staff')->name('integrations.store');
        Route::get('/integrations/{integration}', [AdminMarketingIntegrationController::class, 'show'])->middleware('role:admin,editor,staff')->name('integrations.show');
        Route::put('/integrations/{integration}', [AdminMarketingIntegrationController::class, 'update'])->middleware('role:admin,editor,staff')->name('integrations.update');
        Route::delete('/integrations/{integration}', [AdminMarketingIntegrationController::class, 'destroy'])->middleware('role:admin,editor,staff')->name('integrations.destroy');
        Route::post('/integrations/bulk', [AdminMarketingIntegrationController::class, 'bulk'])->middleware('role:admin,editor,staff')->name('integrations.bulk');

        Route::get('/campaigns/ads', [AdminCampaignController::class, 'adIndex'])->name('campaigns.ads.index');
        Route::get('/campaigns/ads/{adCampaign}', [AdminCampaignController::class, 'adShow'])->name('campaigns.ads.show');
        Route::post('/campaigns/ads/{adCampaign}/approve', [AdminCampaignController::class, 'adApprove'])->middleware('role:admin,editor')->name('campaigns.ads.approve');
        Route::post('/campaigns/ads/{adCampaign}/pause', [AdminCampaignController::class, 'adPause'])->middleware('role:admin,editor')->name('campaigns.ads.pause');
        Route::post('/campaigns/ads/{adCampaign}/resume', [AdminCampaignController::class, 'adResume'])->middleware('role:admin,editor')->name('campaigns.ads.resume');
        Route::post('/campaigns/ads/bulk', [AdminCampaignController::class, 'adBulk'])->middleware('role:admin,editor')->name('campaigns.ads.bulk');

        Route::get('/campaigns/push', [AdminCampaignController::class, 'pushIndex'])->name('campaigns.push.index');
        Route::get('/campaigns/push/{pushCampaign}', [AdminCampaignController::class, 'pushShow'])->name('campaigns.push.show');
        Route::post('/campaigns/push/{pushCampaign}/dispatch', [AdminCampaignController::class, 'pushDispatch'])->middleware('role:admin,editor')->name('campaigns.push.dispatch');
        Route::post('/campaigns/push/bulk', [AdminCampaignController::class, 'pushBulk'])->middleware('role:admin,editor')->name('campaigns.push.bulk');

        Route::get('/councillors', [AdminCouncillorController::class, 'index'])->middleware('role:admin')->name('councillors.index');
        Route::post('/councillors', [AdminCouncillorController::class, 'store'])->middleware('role:admin')->name('councillors.store');
        Route::get('/councillors/{councillor}', [AdminCouncillorController::class, 'show'])->middleware('role:admin')->name('councillors.show');
        Route::put('/councillors/{councillor}', [AdminCouncillorController::class, 'update'])->middleware('role:admin')->name('councillors.update');
        Route::delete('/councillors/{councillor}', [AdminCouncillorController::class, 'destroy'])->middleware('role:admin')->name('councillors.destroy');
        Route::post('/councillors/bulk', [AdminCouncillorController::class, 'bulk'])->middleware('role:admin')->name('councillors.bulk');

        Route::get('/fault-reports', [AdminCivicFaultReportController::class, 'index'])->middleware('role:admin,editor')->name('fault-reports.index');
        Route::get('/fault-reports/{faultReport}', [AdminCivicFaultReportController::class, 'show'])->middleware('role:admin,editor')->name('fault-reports.show');
        Route::post('/fault-reports/{faultReport}/moderate', [AdminCivicFaultReportController::class, 'moderate'])->middleware('role:admin,editor')->name('fault-reports.moderate');
        Route::put('/fault-reports/{faultReport}', [AdminCivicFaultReportController::class, 'update'])->middleware('role:admin,editor')->name('fault-reports.update');
        Route::post('/fault-reports/bulk', [AdminCivicFaultReportController::class, 'bulk'])->middleware('role:admin,editor')->name('fault-reports.bulk');
    });
});
