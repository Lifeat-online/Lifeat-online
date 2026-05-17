<?php

use App\Http\Controllers\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Admin\CampaignController as AdminCampaignController;
use App\Http\Controllers\Admin\PayoutRequestController as AdminPayoutRequestController;
use App\Http\Controllers\Admin\WalletController as AdminWalletController;
use App\Http\Controllers\Admin\ClassifiedController as AdminClassifiedController;
use App\Http\Controllers\Admin\CustomerLookupController as AdminCustomerLookupController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\FinanceController as AdminFinanceController;
use App\Http\Controllers\Admin\ListingController as AdminListingController;
use App\Http\Controllers\Admin\MetricsController as AdminMetricsController;
use App\Http\Controllers\Admin\PackageController as AdminPackageController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\VoucherController as AdminVoucherController;
use App\Http\Controllers\Admin\MarketingIntegrationController as AdminMarketingIntegrationController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\PushNotificationTestController as AdminPushNotificationTestController;
use App\Http\Controllers\Admin\WriterApplicationController as AdminWriterApplicationController;
use App\Http\Controllers\Admin\WriterPaymentController as AdminWriterPaymentController;
use App\Http\Controllers\Admin\CouncillorController as AdminCouncillorController;
use App\Http\Controllers\Admin\CivicFaultReportController as AdminCivicFaultReportController;
use App\Http\Controllers\Admin\DevUpdateController as AdminDevUpdateController;
use App\Http\Controllers\Writer\EarningsController as WriterEarningsController;
use App\Http\Controllers\Writer\ArticleController as WriterArticleController;
use App\Http\Controllers\AboutController;
use App\Http\Controllers\AccountWalletController;
use App\Http\Controllers\AdTrackingController;
use App\Http\Controllers\AddListingController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AdvertiseController;
use App\Http\Controllers\ClassifiedController;
use App\Http\Controllers\ClassifiedSubmissionController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountAdCampaignController;
use App\Http\Controllers\AccountPushCampaignController;
use App\Http\Controllers\AccountInvoiceController;
use App\Http\Controllers\AccountListingController;
use App\Http\Controllers\AccountSubmissionController;
use App\Http\Controllers\AccountEventController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\VoucherRedemptionController;
use App\Http\Controllers\AccountVoucherController;
use App\Http\Controllers\AccountVoucherRedemptionController;
use App\Http\Controllers\StaffVoucherRedemptionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WriterApplicationController;
use App\Http\Controllers\Transport\DriverOfferController as TransportDriverOfferController;
use App\Http\Controllers\Transport\DriverDutyController as TransportDriverDutyController;
use App\Http\Controllers\Transport\DriverWorkspaceController as TransportDriverWorkspaceController;
use App\Http\Controllers\Transport\Manager\DashboardController as TransportManagerDashboardController;
use App\Http\Controllers\Transport\Manager\DriverController as TransportManagerDriverController;
use App\Http\Controllers\Transport\Manager\VehicleController as TransportManagerVehicleController;
use App\Http\Controllers\Transport\Admin\SetupController as TransportAdminSetupController;
use App\Http\Controllers\Transport\RequestController as TransportRequestController;
use App\Http\Controllers\Transport\RequestCancellationController as TransportRequestCancellationController;
use App\Http\Controllers\Transport\RequestTrackingController as TransportRequestTrackingController;
use App\Http\Controllers\AccountAdvertisingDashboardController;
use App\Http\Controllers\StaffAdvertisingDashboardController;
use App\Http\Controllers\CivicFaultMapController;
use App\Http\Controllers\CivicFaultDataController;
use App\Http\Controllers\CivicFaultReportController;
use App\Http\Controllers\Councillor\CivicFaultReportController as CouncillorCivicFaultReportController;
use App\Http\Controllers\Api\ClientAdvertisingApiController;
use App\Http\Controllers\Api\StaffAdvertisingApiController;
use App\Http\Controllers\Auth\AdminBootstrapController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::post('/__bootstrap/admin', [AdminBootstrapController::class, 'store'])->middleware(['throttle:6,1'])->name('bootstrap.admin');
Route::get('/directory', [DirectoryController::class, 'index'])->name('directory.index');
Route::get('/directory/{listing:slug}', [DirectoryController::class, 'show'])->name('directory.show');
Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::get('/events/{event:slug}', [EventController::class, 'show'])->name('events.show');
Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/articles/authors/{user:username}', [ArticleController::class, 'author'])->name('articles.authors.show');
Route::get('/articles/categories/{category:slug}', [ArticleController::class, 'category'])->name('articles.categories.show');
Route::get('/articles/tags/{tag:slug}', [ArticleController::class, 'tag'])->name('articles.tags.show');
Route::get('/articles/locations/{locationNode:slug}', [ArticleController::class, 'location'])->name('articles.locations.show');
Route::get('/articles/{article:slug}', [ArticleController::class, 'show'])->name('articles.show');
Route::get('/search', [SearchController::class, 'index'])->name('search.index');
Route::get('/classifieds', [ClassifiedController::class, 'index'])->name('classifieds.index');
Route::get('/classifieds/{classified:slug}', [ClassifiedController::class, 'show'])->name('classifieds.show');
Route::get('/vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
Route::scopeBindings()->group(function () {
    Route::get('/vouchers/{listing:slug}/{voucher:slug}', [VoucherController::class, 'show'])->name('vouchers.show');
    Route::post('/vouchers/{listing:slug}/{voucher:slug}/redeem', [VoucherRedemptionController::class, 'store'])->middleware(['auth', 'throttle:voucher-redemption'])->name('vouchers.redeem');
});
Route::get('/advertise', [AdvertiseController::class, 'index'])->name('advertise.index');
Route::post('/advertise/start', [AdvertiseController::class, 'start'])->middleware('auth')->name('advertise.start');
Route::get('/add-listing', [AddListingController::class, 'index'])->name('add-listing.index');
Route::post('/add-listing/start', [AddListingController::class, 'start'])->middleware('auth')->name('add-listing.start');
Route::view('/transport', 'transport.index')->name('transport.index');
Route::get('/about', [AboutController::class, 'index'])->name('about.index');

Route::get('/faults', [CivicFaultMapController::class, 'index'])->name('faults.index');
Route::get('/faults/data/faults', [CivicFaultDataController::class, 'faults'])->name('faults.data.faults');
Route::get('/faults/data/councillors', [CivicFaultDataController::class, 'councillors'])->name('faults.data.councillors');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/faults/report', [CivicFaultReportController::class, 'create'])->name('faults.report.create');
    Route::post('/faults/report', [CivicFaultReportController::class, 'store'])->middleware('throttle:public-form')->name('faults.report.store');
});

Route::middleware(['auth', 'role:admin'])->prefix('dev')->name('dev.')->group(function () {
    Route::post('/tests/run', [AdminDevUpdateController::class, 'runTests'])->middleware('throttle:2,1')->name('tests.run');
    Route::post('/webpush/vapid/enable', [AdminDevUpdateController::class, 'enableVapidKeys'])->middleware('throttle:3,1')->name('webpush.vapid.enable');
    Route::get('/transport', [TransportAdminSetupController::class, 'index'])->name('transport.setup');
    Route::post('/transport/managers', [TransportAdminSetupController::class, 'storeManager'])->name('transport.managers.store');
    Route::put('/transport/settings', [TransportAdminSetupController::class, 'updateSettings'])->name('transport.settings.update');
});

Route::middleware(['auth'])->prefix('transport')->name('transport.')->group(function () {
    Route::get('/requests/create', [TransportRequestController::class, 'create'])->name('requests.create');
    Route::post('/requests', [TransportRequestController::class, 'store'])->name('requests.store');
    Route::get('/requests/{transportRequest}', [TransportRequestController::class, 'show'])->name('requests.show');
    Route::post('/requests/{transportRequest}/cancel', [TransportRequestCancellationController::class, 'store'])->name('requests.cancel');
    Route::get('/requests/{transportRequest}/tracking', [TransportRequestTrackingController::class, 'show'])->name('requests.tracking');
    Route::post('/requests/{transportRequest}/passenger-location', [TransportRequestTrackingController::class, 'updatePassenger'])->name('requests.passenger-location');
    Route::post('/requests/{transportRequest}/driver-location', [TransportRequestTrackingController::class, 'updateDriver'])->middleware('transport.on_duty')->name('requests.driver-location');

    Route::middleware('role:transport_manager,admin')->prefix('manager')->name('manager.')->group(function () {
        Route::get('/', TransportManagerDashboardController::class)->name('dashboard');
        Route::post('/drivers', [TransportManagerDriverController::class, 'store'])->name('drivers.store');
        Route::post('/vehicles', [TransportManagerVehicleController::class, 'store'])->name('vehicles.store');
    });

    Route::middleware('role:transport_driver,admin')->prefix('driver')->name('driver.')->group(function () {
        Route::get('/duty', [TransportDriverDutyController::class, 'show'])->name('duty');
        Route::post('/clock-in', [TransportDriverDutyController::class, 'clockIn'])->name('clock-in');
        Route::post('/clock-out', [TransportDriverDutyController::class, 'clockOut'])->name('clock-out');
        Route::post('/offers/{offer}/accept', [TransportDriverOfferController::class, 'accept'])->middleware('transport.on_duty')->name('offers.accept');
        Route::get('/', TransportDriverWorkspaceController::class)->middleware('transport.on_duty')->name('workspace');
    });
});

require __DIR__.'/web_api.php';

// Ad & push tracking — public, no auth, no session
Route::get('/ads/{adCampaign}/i', [AdTrackingController::class, 'impression'])->middleware('throttle:public-tracking')->name('ad-tracking.impression');
Route::get('/ads/{adCampaign}/click', [AdTrackingController::class, 'click'])->middleware('throttle:public-tracking')->name('ad-tracking.click');
Route::get('/push/{pushCampaign}/open', [AdTrackingController::class, 'pushOpen'])->middleware('throttle:public-tracking')->name('ad-tracking.push-open');
Route::get('/contact-us', [ContactController::class, 'index'])->name('contact.index');
Route::get('/terms-and-conditions', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/privacy-policy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/staff-signup', [WriterApplicationController::class, 'create'])->name('staff-signup.create');
Route::post('/staff-signup', [WriterApplicationController::class, 'store'])->middleware('throttle:public-form')->name('staff-signup.store');
Route::get('/staff-signup/submitted', [WriterApplicationController::class, 'submitted'])->name('staff-signup.submitted');
Route::get('/account', [AccountController::class, 'index'])->middleware('auth')->name('account.index');
Route::get('/account/advertising', [AccountAdvertisingDashboardController::class, 'index'])->middleware('auth')->name('account.advertising.index');
Route::get('/account/invoices', [AccountInvoiceController::class, 'index'])->middleware('auth')->name('account.invoices.index');
Route::get('/account/invoices/{invoice}', [AccountInvoiceController::class, 'show'])->middleware('auth')->name('account.invoices.show');
Route::get('/account/listings', [AccountListingController::class, 'index'])->middleware('auth')->name('account.listings.index');
Route::get('/account/listings/{listing}', [AccountListingController::class, 'show'])->middleware('auth')->name('account.listings.show');
Route::get('/account/listings/{listing}/edit', [AccountListingController::class, 'edit'])->middleware('auth')->name('account.listings.edit');
Route::put('/account/listings/{listing}', [AccountListingController::class, 'update'])->middleware('auth')->name('account.listings.update');
Route::delete('/account/listings/{listing}', [AccountListingController::class, 'destroy'])->middleware('auth')->name('account.listings.destroy');
Route::get('/account/wallet', [AccountWalletController::class, 'index'])->middleware(['auth', 'role:staff'])->name('account.wallet.index');
Route::post('/account/wallet/payout-requests', [AccountWalletController::class, 'requestPayout'])->middleware(['auth', 'role:staff'])->name('account.wallet.payout-requests.store');
Route::delete('/account/wallet/payout-requests/{payoutRequest}', [AccountWalletController::class, 'cancelPayout'])->middleware(['auth', 'role:staff'])->name('account.wallet.payout-requests.cancel');
Route::get('/account/listings/{listing}/ad-campaigns', [AccountAdCampaignController::class, 'index'])->middleware('auth')->name('account.listings.ad-campaigns.index');
Route::get('/account/listings/{listing}/ad-campaigns/create', [AccountAdCampaignController::class, 'create'])->middleware('auth')->name('account.listings.ad-campaigns.create');
Route::post('/account/listings/{listing}/ad-campaigns', [AccountAdCampaignController::class, 'store'])->middleware('auth')->name('account.listings.ad-campaigns.store');
Route::get('/account/listings/{listing}/ad-campaigns/{adCampaign}/edit', [AccountAdCampaignController::class, 'edit'])->middleware('auth')->name('account.listings.ad-campaigns.edit');
Route::put('/account/listings/{listing}/ad-campaigns/{adCampaign}', [AccountAdCampaignController::class, 'update'])->middleware('auth')->name('account.listings.ad-campaigns.update');
Route::delete('/account/listings/{listing}/ad-campaigns/{adCampaign}', [AccountAdCampaignController::class, 'destroy'])->middleware('auth')->name('account.listings.ad-campaigns.destroy');
Route::get('/account/listings/{listing}/push-campaigns', [AccountPushCampaignController::class, 'index'])->middleware('auth')->name('account.listings.push-campaigns.index');
Route::get('/account/listings/{listing}/push-campaigns/create', [AccountPushCampaignController::class, 'create'])->middleware('auth')->name('account.listings.push-campaigns.create');
Route::post('/account/listings/{listing}/push-campaigns', [AccountPushCampaignController::class, 'store'])->middleware('auth')->name('account.listings.push-campaigns.store');
Route::get('/account/listings/{listing}/push-campaigns/{pushCampaign}/edit', [AccountPushCampaignController::class, 'edit'])->middleware('auth')->name('account.listings.push-campaigns.edit');
Route::put('/account/listings/{listing}/push-campaigns/{pushCampaign}', [AccountPushCampaignController::class, 'update'])->middleware('auth')->name('account.listings.push-campaigns.update');
Route::post('/account/listings/{listing}/push-campaigns/{pushCampaign}/dispatch', [AccountPushCampaignController::class, 'dispatch'])->middleware('auth')->name('account.listings.push-campaigns.dispatch');
Route::delete('/account/listings/{listing}/push-campaigns/{pushCampaign}', [AccountPushCampaignController::class, 'destroy'])->middleware('auth')->name('account.listings.push-campaigns.destroy');
Route::get('/account/listings/{listing}/events', [AccountEventController::class, 'index'])->middleware('auth')->name('account.listings.events.index');
Route::get('/account/listings/{listing}/events/create', [AccountEventController::class, 'create'])->middleware('auth')->name('account.listings.events.create');
Route::post('/account/listings/{listing}/events', [AccountEventController::class, 'store'])->middleware('auth')->name('account.listings.events.store');
Route::get('/account/listings/{listing}/events/{event}/edit', [AccountEventController::class, 'edit'])->middleware('auth')->name('account.listings.events.edit');
Route::put('/account/listings/{listing}/events/{event}', [AccountEventController::class, 'update'])->middleware('auth')->name('account.listings.events.update');
Route::delete('/account/listings/{listing}/events/{event}', [AccountEventController::class, 'destroy'])->middleware('auth')->name('account.listings.events.destroy');
Route::post('/account/listings/{listing}/reviews/{review}/response', [AccountListingController::class, 'respondToReview'])->middleware('auth')->name('account.listings.reviews.respond');
Route::post('/account/listings/{listing}/photos', [AccountListingController::class, 'storePhoto'])->middleware('auth')->name('account.listings.photos.store');
Route::post('/account/listings/{listing}/photos/{photo}/primary', [AccountListingController::class, 'makePrimaryPhoto'])->middleware('auth')->name('account.listings.photos.primary');
Route::delete('/account/listings/{listing}/photos/{photo}', [AccountListingController::class, 'destroyPhoto'])->middleware('auth')->name('account.listings.photos.destroy');
Route::get('/account/submissions', [AccountSubmissionController::class, 'index'])->middleware('auth')->name('account.submissions.index');
Route::get('/account/vouchers', [AccountVoucherRedemptionController::class, 'index'])->middleware('auth')->name('account.vouchers.index');
Route::get('/basket', [CheckoutController::class, 'basket'])->name('basket.index');
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::get('/checkout/subscriptions/{subscription}/renew', [CheckoutController::class, 'renewSubscription'])->middleware('auth')->name('checkout.subscriptions.renew');
Route::post('/checkout/start', [CheckoutController::class, 'start'])->middleware('auth')->name('checkout.start');
Route::get('/checkout/orders/{order}', [CheckoutController::class, 'show'])->middleware('auth')->name('checkout.show');
Route::post('/checkout/orders/{order}/payfast/initiate', [CheckoutController::class, 'payfastInitiate'])->middleware('auth')->name('checkout.payfast.initiate');
Route::post('/checkout/orders/{order}/payfast/retry', [CheckoutController::class, 'retryPayment'])->middleware('auth')->name('checkout.payfast.retry');
Route::post('/checkout/orders/{order}/invoice/send', [CheckoutController::class, 'sendInvoice'])->middleware('auth')->name('checkout.invoice.send');
Route::post('/checkout/payfast/callback', [CheckoutController::class, 'payfastCallback'])->middleware('throttle:payfast-callback')->name('checkout.payfast.callback');

use App\Http\Controllers\DashboardController;

Route::get('/dashboard', DashboardController::class)->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/my-classifieds', [ClassifiedSubmissionController::class, 'index'])->name('classifieds.manage.index');
    Route::get('/my-classifieds/create', [ClassifiedSubmissionController::class, 'create'])->name('classifieds.manage.create');
    Route::post('/my-classifieds', [ClassifiedSubmissionController::class, 'store'])->middleware('throttle:public-form')->name('classifieds.manage.store');
    Route::get('/my-classifieds/{classified}/edit', [ClassifiedSubmissionController::class, 'edit'])->name('classifieds.manage.edit');
    Route::put('/my-classifieds/{classified}', [ClassifiedSubmissionController::class, 'update'])->name('classifieds.manage.update');
});

Route::middleware('auth')->scopeBindings()->group(function () {
    Route::get('/account/listings/{listing}/vouchers', [AccountVoucherController::class, 'index'])->name('account.listings.vouchers.index');
    Route::get('/account/listings/{listing}/vouchers/dashboard', [AccountVoucherController::class, 'dashboard'])->name('account.listings.vouchers.dashboard');
    Route::get('/account/listings/{listing}/vouchers/create', [AccountVoucherController::class, 'create'])->name('account.listings.vouchers.create');
    Route::post('/account/listings/{listing}/vouchers', [AccountVoucherController::class, 'store'])->name('account.listings.vouchers.store');
    Route::get('/account/listings/{listing}/vouchers/{voucher}/edit', [AccountVoucherController::class, 'edit'])->name('account.listings.vouchers.edit');
    Route::put('/account/listings/{listing}/vouchers/{voucher}', [AccountVoucherController::class, 'update'])->name('account.listings.vouchers.update');
    Route::delete('/account/listings/{listing}/vouchers/{voucher}', [AccountVoucherController::class, 'destroy'])->name('account.listings.vouchers.destroy');
});

Route::middleware(['auth', 'role:admin,editor,staff,writer'])->group(function () {
    Route::get('/staff/dashboard', \App\Http\Controllers\StaffDashboardController::class)->name('staff.dashboard');
});

Route::middleware(['auth', 'role:staff,admin'])->group(function () {
    Route::get('/staff/advertising', [StaffAdvertisingDashboardController::class, 'index'])->name('staff.advertising.index');
});

Route::middleware('auth')->group(function () {
    Route::get('/staff/vouchers/redeem', [StaffVoucherRedemptionController::class, 'show'])->name('staff.vouchers.redeem');
    Route::post('/staff/vouchers/consume', [StaffVoucherRedemptionController::class, 'consume'])->middleware('throttle:voucher-redemption')->name('staff.vouchers.consume');
});

Route::middleware(['auth', 'role:writer'])->prefix('writer')->name('writer.')->group(function () {
    Route::resource('articles', WriterArticleController::class)->except('show', 'destroy');
    Route::get('earnings', [WriterEarningsController::class, 'index'])->name('earnings.index');
});

Route::middleware(['auth', 'verified', 'role:councillor'])->prefix('councillor')->name('councillor.')->group(function () {
    Route::get('/faults', [CouncillorCivicFaultReportController::class, 'index'])->name('faults.index');
    Route::post('/faults/{faultReport}/status', [CouncillorCivicFaultReportController::class, 'updateStatus'])->name('faults.status');
});

Route::middleware(['auth', 'role:admin,editor,staff,support'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('/metrics', AdminMetricsController::class)->name('metrics');
    Route::get('/push-notifications', [AdminPushNotificationTestController::class, 'index'])->middleware('role:admin,editor')->name('push-notifications.index');
    Route::post('/push-notifications', [AdminPushNotificationTestController::class, 'store'])->middleware('role:admin,editor')->name('push-notifications.store');
    Route::get('/push-notifications/test', [AdminPushNotificationTestController::class, 'index'])->middleware('role:admin,editor')->name('push-notifications.test');
    Route::post('/push-notifications/test', [AdminPushNotificationTestController::class, 'store'])->middleware('role:admin,editor')->name('push-notifications.test.store');
    Route::get('/finance', [AdminFinanceController::class, 'index'])->middleware('role:admin,editor,support')->name('finance.index');
    Route::get('/finance/export/{dataset}', [AdminFinanceController::class, 'export'])->middleware('role:admin,editor')->name('finance.export');
    Route::post('/finance/payments/{payment}/mark-paid', [AdminFinanceController::class, 'markPaymentPaid'])->middleware('role:admin,editor')->name('finance.payments.mark-paid');
    Route::post('/finance/payments/{payment}/mark-failed', [AdminFinanceController::class, 'markPaymentFailed'])->middleware('role:admin,editor')->name('finance.payments.mark-failed');
    Route::post('/finance/payments/{payment}/refunds', [AdminFinanceController::class, 'refundPayment'])->middleware('role:admin')->name('finance.payments.refunds.store');
    Route::post('/finance/subscriptions/{subscription}/extend', [AdminFinanceController::class, 'extendSubscription'])->middleware('role:admin,editor')->name('finance.subscriptions.extend');
    Route::post('/finance/subscriptions/{subscription}/suspend', [AdminFinanceController::class, 'suspendSubscription'])->middleware('role:admin')->name('finance.subscriptions.suspend');
    Route::post('/finance/subscriptions/{subscription}/reminder', [AdminFinanceController::class, 'sendSubscriptionReminder'])->middleware('role:admin,editor')->name('finance.subscriptions.reminder');
    Route::get('/finance/orders', [AdminFinanceController::class, 'orders'])->middleware('role:admin,editor,support')->name('finance.orders.index');
    Route::get('/finance/orders/{order}', [AdminFinanceController::class, 'showOrder'])->middleware('role:admin,editor,support')->name('finance.orders.show');
    Route::post('/finance/orders/{order}/attribution', [AdminFinanceController::class, 'setOrderAttribution'])->middleware('role:admin,editor')->name('finance.orders.attribution');
    Route::get('/finance/notifications', [AdminFinanceController::class, 'notifications'])->middleware('role:admin,editor,support')->name('finance.notifications.index');
    Route::get('/finance/notifications/{notification}', [AdminFinanceController::class, 'showNotification'])->middleware('role:admin,editor,support')->name('finance.notifications.show');
    Route::post('/finance/notifications/{notification}/resend', [AdminFinanceController::class, 'resendNotification'])->middleware('role:admin,editor')->name('finance.notifications.resend');
    Route::get('/finance/payments', [AdminFinanceController::class, 'payments'])->middleware('role:admin,editor,support')->name('finance.payments.index');
    Route::get('/finance/payments/{payment}', [AdminFinanceController::class, 'showPayment'])->middleware('role:admin,editor,support')->name('finance.payments.show');
    Route::get('/finance/subscriptions', [AdminFinanceController::class, 'subscriptions'])->middleware('role:admin,editor,support')->name('finance.subscriptions.index');
    Route::get('/finance/subscriptions/{subscription}', [AdminFinanceController::class, 'showSubscription'])->middleware('role:admin,editor,support')->name('finance.subscriptions.show');
    Route::get('/settings', [AdminSettingsController::class, 'index'])->middleware('role:admin')->name('settings.index');
    Route::put('/settings', [AdminSettingsController::class, 'update'])->middleware('role:admin')->name('settings.update');
    Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->middleware('role:admin,editor,support')->name('audit-logs.index');
    Route::get('/customers', [AdminCustomerLookupController::class, 'index'])->name('customers.index');
    Route::get('/customers/{user}', [AdminCustomerLookupController::class, 'show'])->name('customers.show');
    Route::post('/customers/{user}/notes', [AdminCustomerLookupController::class, 'storeNote'])->name('customers.notes.store');
    Route::get('/classifieds', [AdminClassifiedController::class, 'index'])->middleware('role:admin,editor,staff')->name('classifieds.index');
    Route::get('/classifieds/{classified}', [AdminClassifiedController::class, 'show'])->middleware('role:admin,editor,staff')->name('classifieds.show');
    Route::post('/classifieds/{classified}/review', [AdminClassifiedController::class, 'review'])->middleware('role:admin,editor,staff')->name('classifieds.review');
    Route::get('/writer-applications', [AdminWriterApplicationController::class, 'index'])->middleware('role:admin,editor')->name('writer-applications.index');
    Route::get('/writer-applications/{writerApplication}', [AdminWriterApplicationController::class, 'show'])->middleware('role:admin,editor')->name('writer-applications.show');
    Route::get('/writer-applications/{writerApplication}/documents/{document}', [AdminWriterApplicationController::class, 'document'])->middleware('role:admin,editor')->name('writer-applications.documents.show');
    Route::post('/writer-applications/{writerApplication}/review', [AdminWriterApplicationController::class, 'review'])->middleware('role:admin,editor')->name('writer-applications.review');
    Route::post('/writer-applications/{writerApplication}/resend-access', [AdminWriterApplicationController::class, 'resendAccess'])->middleware('role:admin,editor')->name('writer-applications.resend-access');
    Route::get('/writer-payments', [AdminWriterPaymentController::class, 'index'])->middleware('role:admin,editor')->name('writer-payments.index');
    Route::post('/writer-payments/batches', [AdminWriterPaymentController::class, 'storeBatch'])->middleware('role:admin,editor')->name('writer-payments.batches.store');
    Route::get('/writer-payments/batches/{batch}/export', [AdminWriterPaymentController::class, 'export'])->middleware('role:admin,editor')->name('writer-payments.batches.export');
    Route::post('/writer-payments/batches/{batch}/mark-paid', [AdminWriterPaymentController::class, 'markPaid'])->middleware('role:admin')->name('writer-payments.batches.mark-paid');
    Route::resource('packages', AdminPackageController::class)->except('show', 'destroy')->middleware('role:admin');
    Route::post('/listings/bulk', [AdminListingController::class, 'bulk'])->middleware('role:admin,editor,staff')->name('listings.bulk');
    Route::resource('listings', AdminListingController::class)->except('show')->middleware('role:admin,editor,staff');
    Route::post('/events/bulk', [AdminEventController::class, 'bulk'])->middleware('role:admin,editor,staff')->name('events.bulk');
    Route::resource('events', AdminEventController::class)->except('show')->middleware('role:admin,editor,staff');
    Route::post('/articles/bulk', [AdminArticleController::class, 'bulk'])->middleware('role:admin,editor')->name('articles.bulk');
    Route::resource('articles', AdminArticleController::class)->except('show')->middleware('role:admin,editor');
    Route::get('/vouchers', [AdminVoucherController::class, 'index'])->middleware('role:admin,editor,staff')->name('vouchers.index');
    Route::get('/vouchers/create', [AdminVoucherController::class, 'create'])->middleware('role:admin,editor,staff')->name('vouchers.create');
    Route::post('/vouchers', [AdminVoucherController::class, 'store'])->middleware('role:admin,editor,staff')->name('vouchers.store');
    Route::get('/vouchers/{voucher:id}', [AdminVoucherController::class, 'show'])->middleware('role:admin,editor,staff')->name('vouchers.show');
    Route::get('/vouchers/{voucher:id}/edit', [AdminVoucherController::class, 'edit'])->middleware('role:admin,editor,staff')->name('vouchers.edit');
    Route::put('/vouchers/{voucher:id}', [AdminVoucherController::class, 'update'])->middleware('role:admin,editor,staff')->name('vouchers.update');
    Route::delete('/vouchers/{voucher:id}', [AdminVoucherController::class, 'destroy'])->middleware('role:admin,editor,staff')->name('vouchers.destroy');
    Route::post('/vouchers/bulk', [AdminVoucherController::class, 'bulk'])->middleware('role:admin,editor,staff')->name('vouchers.bulk');
    Route::post('/integrations/bulk', [AdminMarketingIntegrationController::class, 'bulk'])->middleware('role:admin,editor,staff')->name('integrations.bulk');
    Route::resource('integrations', AdminMarketingIntegrationController::class)->middleware('role:admin,editor,staff');
    Route::post('/councillors/bulk', [AdminCouncillorController::class, 'bulk'])->middleware('role:admin')->name('councillors.bulk');
    Route::resource('councillors', AdminCouncillorController::class)->except('show')->middleware('role:admin');
    Route::get('/fault-reports', [AdminCivicFaultReportController::class, 'index'])->middleware('role:admin,editor')->name('fault-reports.index');
    Route::get('/fault-reports/{faultReport}', [AdminCivicFaultReportController::class, 'show'])->middleware('role:admin,editor')->name('fault-reports.show');
    Route::post('/fault-reports/{faultReport}/moderate', [AdminCivicFaultReportController::class, 'moderate'])->middleware('role:admin,editor')->name('fault-reports.moderate');
    Route::put('/fault-reports/{faultReport}', [AdminCivicFaultReportController::class, 'update'])->middleware('role:admin,editor')->name('fault-reports.update');
    Route::post('/fault-reports/bulk', [AdminCivicFaultReportController::class, 'bulk'])->middleware('role:admin,editor')->name('fault-reports.bulk');
    // Wallets & Payout Requests
    Route::get('/wallet', [AdminWalletController::class, 'index'])->name('wallet.index');
    Route::get('/wallet/{staffWallet}', [AdminWalletController::class, 'show'])->name('wallet.show');
    Route::get('/payout-requests', [AdminPayoutRequestController::class, 'index'])->name('payout-requests.index');
    Route::get('/payout-requests/{payoutRequest}', [AdminPayoutRequestController::class, 'show'])->name('payout-requests.show');
    Route::post('/payout-requests/{payoutRequest}/approve', [AdminPayoutRequestController::class, 'approve'])->middleware('role:admin')->name('payout-requests.approve');
    Route::post('/payout-requests/{payoutRequest}/reject', [AdminPayoutRequestController::class, 'reject'])->middleware('role:admin')->name('payout-requests.reject');
    Route::post('/payout-requests/{payoutRequest}/mark-paid', [AdminPayoutRequestController::class, 'markPaid'])->middleware('role:admin')->name('payout-requests.mark-paid');
    // Ad / Push Campaigns
    Route::get('/campaigns/ads', [AdminCampaignController::class, 'adIndex'])->name('campaigns.ads.index');
    Route::get('/campaigns/ads/create', [AdminCampaignController::class, 'adCreate'])->middleware('role:admin,editor,staff')->name('campaigns.ads.create');
    Route::post('/campaigns/ads', [AdminCampaignController::class, 'adStore'])->middleware('role:admin,editor,staff')->name('campaigns.ads.store');
    Route::post('/campaigns/ads/bulk', [AdminCampaignController::class, 'adBulk'])->middleware('role:admin,editor')->name('campaigns.ads.bulk');
    Route::get('/campaigns/ads/{adCampaign}', [AdminCampaignController::class, 'adShow'])->name('campaigns.ads.show');
    Route::post('/campaigns/ads/{adCampaign}/approve', [AdminCampaignController::class, 'adApprove'])->middleware('role:admin,editor')->name('campaigns.ads.approve');
    Route::post('/campaigns/ads/{adCampaign}/pause', [AdminCampaignController::class, 'adPause'])->middleware('role:admin,editor')->name('campaigns.ads.pause');
    Route::post('/campaigns/ads/{adCampaign}/resume', [AdminCampaignController::class, 'adResume'])->middleware('role:admin,editor')->name('campaigns.ads.resume');
    Route::get('/campaigns/push', [AdminCampaignController::class, 'pushIndex'])->name('campaigns.push.index');
    Route::get('/campaigns/push/create', [AdminCampaignController::class, 'pushCreate'])->middleware('role:admin,editor,staff')->name('campaigns.push.create');
    Route::post('/campaigns/push', [AdminCampaignController::class, 'pushStore'])->middleware('role:admin,editor,staff')->name('campaigns.push.store');
    Route::post('/campaigns/push/bulk', [AdminCampaignController::class, 'pushBulk'])->middleware('role:admin,editor')->name('campaigns.push.bulk');
    Route::get('/campaigns/push/{pushCampaign}', [AdminCampaignController::class, 'pushShow'])->name('campaigns.push.show');
    Route::post('/campaigns/push/{pushCampaign}/dispatch', [AdminCampaignController::class, 'pushDispatch'])->middleware('role:admin,editor')->name('campaigns.push.dispatch');
});

require __DIR__.'/auth.php';
