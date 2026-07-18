<?php

use App\Http\Controllers\Admin\ActionStationController as AdminActionStationController;
use App\Http\Controllers\Admin\AiAssistController as AdminAiAssistController;
use App\Http\Controllers\Admin\AiManagerController as AdminAiManagerController;
use App\Http\Controllers\Admin\AiOperationsController as AdminAiOperationsController;
use App\Http\Controllers\Admin\AiSettingsController as AdminAiSettingsController;
use App\Http\Controllers\Admin\AiOperatorController as AdminAiOperatorController;
use App\Http\Controllers\Admin\ArticleBriefController as AdminArticleBriefController;
use App\Http\Controllers\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Admin\ArticleImageController as AdminArticleImageController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\CampaignController as AdminCampaignController;
use App\Http\Controllers\Admin\CivicFaultReportController as AdminCivicFaultReportController;
use App\Http\Controllers\Admin\ClassifiedController as AdminClassifiedController;
use App\Http\Controllers\Admin\CouncillorController as AdminCouncillorController;
use App\Http\Controllers\Admin\CustomerLookupController as AdminCustomerLookupController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\EditorialDossierController as AdminEditorialDossierController;
use App\Http\Controllers\Admin\FinanceController as AdminFinanceController;
use App\Http\Controllers\Admin\ListingController as AdminListingController;
use App\Http\Controllers\Admin\MapIntegrationController as AdminMapIntegrationController;
use App\Http\Controllers\Admin\MarketingIntegrationController as AdminMarketingIntegrationController;
use App\Http\Controllers\Admin\MetricsController as AdminMetricsController;
use App\Http\Controllers\Admin\PackageController as AdminPackageController;
use App\Http\Controllers\Admin\PayoutRequestController as AdminPayoutRequestController;
use App\Http\Controllers\Admin\PushNotificationTestController as AdminPushNotificationTestController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\VoucherController as AdminVoucherController;
use App\Http\Controllers\Admin\WalletController as AdminWalletController;
use App\Http\Controllers\Admin\WriterApplicationController as AdminWriterApplicationController;
use App\Http\Controllers\Admin\WriterPaymentController as AdminWriterPaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin,editor,staff,support,dev,developer'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('/ai-operator', [AdminAiOperatorController::class, 'index'])
        ->middleware('role:admin,editor,support,dev,developer')
        ->name('ai-operator.index');
    Route::post('/ai-operator/messages', [AdminAiOperatorController::class, 'storeMessage'])
        ->middleware('role:admin,editor,support,dev,developer')
        ->name('ai-operator.messages.store');
    Route::post('/ai-operator/tools/{tool}', [AdminAiOperatorController::class, 'execute'])
        ->middleware('role:admin,editor,support,dev,developer')
        ->where('tool', '[A-Za-z0-9._-]+')
        ->name('ai-operator.tools.execute');
    Route::post('/ai-operator/tools/{tool}/approve', [AdminAiOperatorController::class, 'approve'])
        ->middleware('role:admin,editor,dev,developer')
        ->where('tool', '[A-Za-z0-9._-]+')
        ->name('ai-operator.tools.approve');

    Route::get('/editorial-dossiers', [AdminEditorialDossierController::class, 'index'])
        ->middleware('role:admin,editor,dev,developer')
        ->name('editorial-dossiers.index');
    Route::get('/editorial-dossiers/{editorialDossier}', [AdminEditorialDossierController::class, 'show'])
        ->middleware('role:admin,editor,dev,developer')
        ->name('editorial-dossiers.show');
    Route::post('/editorial-dossiers/{editorialDossier}/approve', [AdminEditorialDossierController::class, 'approve'])
        ->middleware('role:admin,editor,dev,developer')
        ->name('editorial-dossiers.approve');
    Route::put('/editorial-dossiers/{editorialDossier}/claims/{claim}', [AdminEditorialDossierController::class, 'updateClaim'])
        ->middleware('role:admin,editor,dev,developer')
        ->name('editorial-dossiers.claims.update');
    Route::post('/editorial-dossiers/{editorialDossier}/claims/{claim}/evidence', [AdminEditorialDossierController::class, 'storeEvidence'])
        ->middleware('role:admin,editor,dev,developer')
        ->name('editorial-dossiers.evidence.store');

    Route::get('/action-station', [AdminActionStationController::class, 'index'])->name('action-station.index');
    Route::post('/action-station/settings', [AdminActionStationController::class, 'updateSettings'])
        ->middleware('role:admin,editor')
        ->name('action-station.settings.update');
    Route::post('/action-station/review', [AdminActionStationController::class, 'reviewContent'])
        ->middleware('role:admin,editor')
        ->name('action-station.review');
    Route::post('/action-station/review-all', [AdminActionStationController::class, 'reviewContentQueue'])
        ->middleware('role:admin,editor')
        ->name('action-station.review-all');

    Route::get('/ai-manager', [AdminAiManagerController::class, 'index'])
        ->middleware('role:admin,editor')
        ->name('ai-manager.index');
    Route::put('/ai-manager/policy', [AdminAiManagerController::class, 'updatePolicy'])
        ->middleware('role:admin')
        ->name('ai-manager.policy.update');
    Route::post('/ai-manager/recommendations', [AdminAiManagerController::class, 'generateRecommendations'])
        ->middleware('role:admin,editor')
        ->name('ai-manager.recommendations.store');
    Route::post('/ai-manager/actions/{aiManagerAction}', [AdminAiManagerController::class, 'updateAction'])
        ->middleware('role:admin,editor')
        ->name('ai-manager.actions.update');

    Route::get('/metrics', AdminMetricsController::class)->name('metrics');

    Route::get('/push-notifications', [AdminPushNotificationTestController::class, 'index'])
        ->middleware('role:admin,editor,dev,developer')
        ->name('push-notifications.index');
    Route::post('/push-notifications', [AdminPushNotificationTestController::class, 'store'])
        ->middleware('role:admin,editor,dev,developer')
        ->name('push-notifications.store');
    Route::get('/push-notifications/test', [AdminPushNotificationTestController::class, 'index'])
        ->middleware('role:admin,editor,dev,developer')
        ->name('push-notifications.test');
    Route::post('/push-notifications/test', [AdminPushNotificationTestController::class, 'store'])
        ->middleware('role:admin,editor,dev,developer')
        ->name('push-notifications.test.store');

    Route::get('/finance', [AdminFinanceController::class, 'index'])
        ->middleware('role:admin,editor,support')
        ->name('finance.index');
    Route::get('/finance/export/{dataset}', [AdminFinanceController::class, 'export'])
        ->middleware('role:admin,editor')
        ->name('finance.export');
    Route::post('/finance/payments/{payment}/mark-paid', [AdminFinanceController::class, 'markPaymentPaid'])
        ->middleware('role:admin,editor')
        ->name('finance.payments.mark-paid');
    Route::post('/finance/payments/{payment}/mark-failed', [AdminFinanceController::class, 'markPaymentFailed'])
        ->middleware('role:admin,editor')
        ->name('finance.payments.mark-failed');
    Route::post('/finance/payments/{payment}/refunds', [AdminFinanceController::class, 'refundPayment'])
        ->middleware('role:admin')
        ->name('finance.payments.refunds.store');
    Route::post('/finance/subscriptions/{subscription}/extend', [AdminFinanceController::class, 'extendSubscription'])
        ->middleware('role:admin,editor')
        ->name('finance.subscriptions.extend');
    Route::post('/finance/subscriptions/{subscription}/suspend', [AdminFinanceController::class, 'suspendSubscription'])
        ->middleware('role:admin')
        ->name('finance.subscriptions.suspend');
    Route::post('/finance/subscriptions/{subscription}/reminder', [AdminFinanceController::class, 'sendSubscriptionReminder'])
        ->middleware('role:admin,editor')
        ->name('finance.subscriptions.reminder');
    Route::get('/finance/orders', [AdminFinanceController::class, 'orders'])
        ->middleware('role:admin,editor,support')
        ->name('finance.orders.index');
    Route::get('/finance/orders/{order}', [AdminFinanceController::class, 'showOrder'])
        ->middleware('role:admin,editor,support')
        ->name('finance.orders.show');
    Route::post('/finance/orders/{order}/attribution', [AdminFinanceController::class, 'setOrderAttribution'])
        ->middleware('role:admin,editor')
        ->name('finance.orders.attribution');
    Route::get('/finance/notifications', [AdminFinanceController::class, 'notifications'])
        ->middleware('role:admin,editor,support')
        ->name('finance.notifications.index');
    Route::get('/finance/notifications/{notification}', [AdminFinanceController::class, 'showNotification'])
        ->middleware('role:admin,editor,support')
        ->name('finance.notifications.show');
    Route::post('/finance/notifications/{notification}/resend', [AdminFinanceController::class, 'resendNotification'])
        ->middleware('role:admin,editor')
        ->name('finance.notifications.resend');
    Route::get('/finance/payments', [AdminFinanceController::class, 'payments'])
        ->middleware('role:admin,editor,support')
        ->name('finance.payments.index');
    Route::get('/finance/payments/{payment}', [AdminFinanceController::class, 'showPayment'])
        ->middleware('role:admin,editor,support')
        ->name('finance.payments.show');
    Route::get('/finance/subscriptions', [AdminFinanceController::class, 'subscriptions'])
        ->middleware('role:admin,editor,support')
        ->name('finance.subscriptions.index');
    Route::get('/finance/subscriptions/{subscription}', [AdminFinanceController::class, 'showSubscription'])
        ->middleware('role:admin,editor,support')
        ->name('finance.subscriptions.show');

    Route::get('/settings', [AdminSettingsController::class, 'index'])
        ->middleware('role:admin')
        ->name('settings.index');
    Route::put('/settings', [AdminSettingsController::class, 'update'])
        ->middleware('role:admin')
        ->name('settings.update');

    Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])
        ->middleware('role:admin,editor,support')
        ->name('audit-logs.index');

    Route::get('/customers', [AdminCustomerLookupController::class, 'index'])->name('customers.index');
    Route::get('/customers/{user}', [AdminCustomerLookupController::class, 'show'])->name('customers.show');
    Route::post('/customers/{user}/notes', [AdminCustomerLookupController::class, 'storeNote'])->name('customers.notes.store');

    Route::get('/classifieds', [AdminClassifiedController::class, 'index'])
        ->middleware('role:admin,editor,staff')
        ->name('classifieds.index');
    Route::get('/classifieds/{classified}', [AdminClassifiedController::class, 'show'])
        ->middleware('role:admin,editor,staff')
        ->name('classifieds.show');
    Route::post('/classifieds/{classified}/review', [AdminClassifiedController::class, 'review'])
        ->middleware('role:admin,editor,staff')
        ->name('classifieds.review');

    Route::get('/writer-applications', [AdminWriterApplicationController::class, 'index'])
        ->middleware('role:admin,editor')
        ->name('writer-applications.index');
    Route::get('/writer-applications/{writerApplication}', [AdminWriterApplicationController::class, 'show'])
        ->middleware('role:admin,editor')
        ->name('writer-applications.show');
    Route::get('/writer-applications/{writerApplication}/documents/{document}', [AdminWriterApplicationController::class, 'document'])
        ->middleware('role:admin,editor')
        ->name('writer-applications.documents.show');
    Route::post('/writer-applications/{writerApplication}/review', [AdminWriterApplicationController::class, 'review'])
        ->middleware('role:admin,editor')
        ->name('writer-applications.review');
    Route::post('/writer-applications/{writerApplication}/resend-access', [AdminWriterApplicationController::class, 'resendAccess'])
        ->middleware('role:admin,editor')
        ->name('writer-applications.resend-access');

    Route::get('/writer-payments', [AdminWriterPaymentController::class, 'index'])
        ->middleware('role:admin,editor')
        ->name('writer-payments.index');
    Route::post('/writer-payments/batches', [AdminWriterPaymentController::class, 'storeBatch'])
        ->middleware('role:admin,editor')
        ->name('writer-payments.batches.store');
    Route::get('/writer-payments/batches/{batch}/export', [AdminWriterPaymentController::class, 'export'])
        ->middleware('role:admin,editor')
        ->name('writer-payments.batches.export');
    Route::post('/writer-payments/batches/{batch}/mark-paid', [AdminWriterPaymentController::class, 'markPaid'])
        ->middleware('role:admin')
        ->name('writer-payments.batches.mark-paid');

    Route::resource('packages', AdminPackageController::class)->except('show', 'destroy')->middleware('role:admin');

    Route::get('/ai-operations', [AdminAiOperationsController::class, 'index'])
        ->middleware('role:admin,editor,dev,developer')
        ->name('ai-operations.index');
    Route::put('/ai-operations/budget', [AdminAiOperationsController::class, 'updateBudget'])
        ->middleware('role:admin,dev,developer')
        ->name('ai-operations.budget.update');
    Route::put('/ai-operations/prompts/{featureKey}', [AdminAiOperationsController::class, 'updatePrompt'])
        ->middleware('role:admin,dev,developer')
        ->name('ai-operations.prompts.update');
    Route::delete('/ai-operations/prompts/{featureKey}', [AdminAiOperationsController::class, 'resetPrompt'])
        ->middleware('role:admin,dev,developer')
        ->name('ai-operations.prompts.reset');
    Route::post('/ai-operations/generations/{aiGeneration}/retry', [AdminAiOperationsController::class, 'retry'])
        ->middleware(['role:admin,dev,developer', 'throttle:6,1'])
        ->name('ai-operations.generations.retry');

    Route::post('/ai/listing-description', [AdminAiAssistController::class, 'listingDescription'])
        ->middleware('role:admin,editor,staff')
        ->name('ai.listing-description');
    Route::post('/ai/article-seo', [AdminAiAssistController::class, 'articleSeo'])
        ->middleware('role:admin,editor')
        ->name('ai.article-seo');
    Route::post('/ai/articles/{article:slug}/translation', [AdminAiAssistController::class, 'articleTranslation'])
        ->middleware('role:admin,editor')
        ->name('ai.article-translation');
    Route::post('/ai/event-description', [AdminAiAssistController::class, 'eventDescription'])
        ->middleware('role:admin,editor,staff')
        ->name('ai.event-description');
    Route::post('/ai/ad-copy', [AdminAiAssistController::class, 'adCopy'])
        ->middleware('role:admin,editor,staff')
        ->name('ai.ad-copy');
    Route::post('/ai/push-copy', [AdminAiAssistController::class, 'pushCopy'])
        ->middleware('role:admin,editor,staff')
        ->name('ai.push-copy');
    Route::post('/ai/voucher-copy', [AdminAiAssistController::class, 'voucherCopy'])
        ->middleware('role:admin,editor,staff')
        ->name('ai.voucher-copy');

    Route::get('/article-briefs', [AdminArticleBriefController::class, 'index'])
        ->middleware('role:admin,editor')
        ->name('article-briefs.index');
    Route::put('/article-briefs/{articleBrief}', [AdminArticleBriefController::class, 'update'])
        ->middleware('role:admin,editor')
        ->name('article-briefs.update');
    Route::post('/article-briefs/{articleBrief}/approve', [AdminArticleBriefController::class, 'approve'])
        ->middleware('role:admin,editor')
        ->name('article-briefs.approve');
    Route::post('/article-briefs/{articleBrief}/reject', [AdminArticleBriefController::class, 'reject'])
        ->middleware('role:admin,editor')
        ->name('article-briefs.reject');
    Route::post('/article-briefs/{articleBrief}/draft', [AdminArticleBriefController::class, 'draft'])
        ->middleware(['role:admin,editor', 'throttle:6,1'])
        ->name('article-briefs.draft');

    Route::post('/listings/bulk', [AdminListingController::class, 'bulk'])
        ->middleware('role:admin,editor,staff')
        ->name('listings.bulk');
    Route::resource('listings', AdminListingController::class)->except('show')
        ->middleware('role:admin,editor,staff');

    Route::post('/events/bulk', [AdminEventController::class, 'bulk'])
        ->middleware('role:admin,editor,staff')
        ->name('events.bulk');
    Route::resource('events', AdminEventController::class)->except('show')
        ->middleware('role:admin,editor,staff');

    Route::post('/articles/bulk', [AdminArticleController::class, 'bulk'])
        ->middleware('role:admin,editor')
        ->name('articles.bulk');
    Route::post('/articles/{article:slug}/ai-image', [AdminArticleImageController::class, 'store'])
        ->middleware(['role:admin,editor', 'throttle:6,1'])
        ->name('articles.ai-image');
    Route::resource('articles', AdminArticleController::class)->except('show')
        ->middleware('role:admin,editor');

    Route::get('/vouchers', [AdminVoucherController::class, 'index'])
        ->middleware('role:admin,editor,staff')
        ->name('vouchers.index');
    Route::get('/vouchers/create', [AdminVoucherController::class, 'create'])
        ->middleware('role:admin,editor,staff')
        ->name('vouchers.create');
    Route::post('/vouchers', [AdminVoucherController::class, 'store'])
        ->middleware('role:admin,editor,staff')
        ->name('vouchers.store');
    Route::get('/vouchers/{voucher:id}', [AdminVoucherController::class, 'show'])
        ->middleware('role:admin,editor,staff')
        ->name('vouchers.show');
    Route::get('/vouchers/{voucher:id}/edit', [AdminVoucherController::class, 'edit'])
        ->middleware('role:admin,editor,staff')
        ->name('vouchers.edit');
    Route::put('/vouchers/{voucher:id}', [AdminVoucherController::class, 'update'])
        ->middleware('role:admin,editor,staff')
        ->name('vouchers.update');
    Route::delete('/vouchers/{voucher:id}', [AdminVoucherController::class, 'destroy'])
        ->middleware('role:admin,editor,staff')
        ->name('vouchers.destroy');
    Route::post('/vouchers/bulk', [AdminVoucherController::class, 'bulk'])
        ->middleware('role:admin,editor,staff')
        ->name('vouchers.bulk');

    Route::post('/integrations/bulk', [AdminMarketingIntegrationController::class, 'bulk'])
        ->middleware('role:admin,editor,staff')
        ->name('integrations.bulk');
    Route::resource('integrations', AdminMarketingIntegrationController::class)
        ->middleware('role:admin,editor,staff');

    Route::post('/councillors/bulk', [AdminCouncillorController::class, 'bulk'])
        ->middleware('role:admin')
        ->name('councillors.bulk');
    Route::resource('councillors', AdminCouncillorController::class)->except('show')
        ->middleware('role:admin');

    Route::get('/fault-reports', [AdminCivicFaultReportController::class, 'index'])
        ->middleware('role:admin,editor')
        ->name('fault-reports.index');
    Route::get('/fault-reports/{faultReport}', [AdminCivicFaultReportController::class, 'show'])
        ->middleware('role:admin,editor')
        ->name('fault-reports.show');
    Route::post('/fault-reports/{faultReport}/moderate', [AdminCivicFaultReportController::class, 'moderate'])
        ->middleware('role:admin,editor')
        ->name('fault-reports.moderate');
    Route::put('/fault-reports/{faultReport}', [AdminCivicFaultReportController::class, 'update'])
        ->middleware('role:admin,editor')
        ->name('fault-reports.update');
    Route::post('/fault-reports/bulk', [AdminCivicFaultReportController::class, 'bulk'])
        ->middleware('role:admin,editor')
        ->name('fault-reports.bulk');

    Route::get('/wallet', [AdminWalletController::class, 'index'])->name('wallet.index');
    Route::get('/wallet/{staffWallet}', [AdminWalletController::class, 'show'])->name('wallet.show');
    Route::post('/wallet/{staffWallet}/adjustments', [AdminWalletController::class, 'adjust'])
        ->middleware('role:admin')
        ->name('wallet.adjustments.store');

    Route::get('/payout-requests', [AdminPayoutRequestController::class, 'index'])->name('payout-requests.index');
    Route::get('/payout-requests/export', [AdminPayoutRequestController::class, 'export'])
        ->middleware('role:admin,editor')
        ->name('payout-requests.export');
    Route::get('/payout-requests/{payoutRequest}', [AdminPayoutRequestController::class, 'show'])->name('payout-requests.show');
    Route::post('/payout-requests/{payoutRequest}/approve', [AdminPayoutRequestController::class, 'approve'])
        ->middleware('role:admin')
        ->name('payout-requests.approve');
    Route::post('/payout-requests/{payoutRequest}/reject', [AdminPayoutRequestController::class, 'reject'])
        ->middleware('role:admin')
        ->name('payout-requests.reject');
    Route::post('/payout-requests/{payoutRequest}/mark-paid', [AdminPayoutRequestController::class, 'markPaid'])
        ->middleware('role:admin')
        ->name('payout-requests.mark-paid');

    Route::get('/campaigns/report', [AdminCampaignController::class, 'report'])
        ->middleware('role:admin,editor')
        ->name('campaigns.report');
    Route::get('/campaigns/report/export/{dataset}', [AdminCampaignController::class, 'reportExport'])
        ->middleware('role:admin,editor')
        ->name('campaigns.report.export');
    Route::get('/campaigns/ads', [AdminCampaignController::class, 'adIndex'])->name('campaigns.ads.index');
    Route::get('/campaigns/ads/create', [AdminCampaignController::class, 'adCreate'])
        ->middleware('role:admin,editor,staff')
        ->name('campaigns.ads.create');
    Route::post('/campaigns/ads', [AdminCampaignController::class, 'adStore'])
        ->middleware('role:admin,editor,staff')
        ->name('campaigns.ads.store');
    Route::post('/campaigns/ads/bulk', [AdminCampaignController::class, 'adBulk'])
        ->middleware('role:admin,editor')
        ->name('campaigns.ads.bulk');
    Route::get('/campaigns/ads/{adCampaign}', [AdminCampaignController::class, 'adShow'])->name('campaigns.ads.show');
    Route::post('/campaigns/ads/{adCampaign}/approve', [AdminCampaignController::class, 'adApprove'])
        ->middleware('role:admin,editor')
        ->name('campaigns.ads.approve');
    Route::post('/campaigns/ads/{adCampaign}/pause', [AdminCampaignController::class, 'adPause'])
        ->middleware('role:admin,editor')
        ->name('campaigns.ads.pause');
    Route::post('/campaigns/ads/{adCampaign}/resume', [AdminCampaignController::class, 'adResume'])
        ->middleware('role:admin,editor')
        ->name('campaigns.ads.resume');
    Route::get('/campaigns/push', [AdminCampaignController::class, 'pushIndex'])->name('campaigns.push.index');
    Route::get('/campaigns/push/create', [AdminCampaignController::class, 'pushCreate'])
        ->middleware('role:admin,editor,staff,dev,developer')
        ->name('campaigns.push.create');
    Route::post('/campaigns/push', [AdminCampaignController::class, 'pushStore'])
        ->middleware('role:admin,editor,staff')
        ->name('campaigns.push.store');
    Route::post('/campaigns/push/bulk', [AdminCampaignController::class, 'pushBulk'])
        ->middleware('role:admin,editor')
        ->name('campaigns.push.bulk');
    Route::get('/campaigns/push/{pushCampaign}', [AdminCampaignController::class, 'pushShow'])->name('campaigns.push.show');
    Route::post('/campaigns/push/{pushCampaign}/dispatch', [AdminCampaignController::class, 'pushDispatch'])
        ->middleware('role:admin,editor')
        ->name('campaigns.push.dispatch');
});
