<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\AdTrackingController;
use App\Http\Controllers\AddListingController;
use App\Http\Controllers\AdvertiseController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AskLifeController;
use App\Http\Controllers\CivicFaultDataController;
use App\Http\Controllers\CivicFaultMapController;
use App\Http\Controllers\ClassifiedController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MapAddressController;
use App\Http\Controllers\PublicStorageController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Transport\PageController as TransportPageController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\VoucherRedemptionController;
use App\Http\Controllers\WriterApplicationController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/health', HealthController::class)->middleware('throttle:60,1')->name('health');
Route::get('/media/{path}', [PublicStorageController::class, 'show'])
    ->where('path', '.*')
    ->name('public-storage.show');
Route::post('/locale/{locale}', LocaleController::class)->whereIn('locale', array_keys((array) config('localization.supported', [])))->name('locale.switch');

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

Route::post('/ask-life', [AskLifeController::class, 'store'])->middleware('throttle:ask-life')->name('ask-life.store');
Route::post('/ask-life/stream', [AskLifeController::class, 'stream'])->middleware('throttle:ask-life')->name('ask-life.stream');
Route::post('/ask-life/feedback', [AskLifeController::class, 'feedback'])->middleware('throttle:ask-life')->name('ask-life.feedback');
Route::post('/ask-life/speak', [AskLifeController::class, 'speak'])->middleware('throttle:ask-life')->name('ask-life.speak');
Route::delete('/ask-life/sessions/{session}', [AskLifeController::class, 'destroySession'])->middleware('throttle:ask-life')->name('ask-life.sessions.destroy');

Route::get('/classifieds', [ClassifiedController::class, 'index'])->name('classifieds.index');
Route::get('/classifieds/{classified:slug}', [ClassifiedController::class, 'show'])->name('classifieds.show');

Route::get('/vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
Route::scopeBindings()->group(function () {
    Route::get('/vouchers/{listing:slug}/{voucher:slug}', [VoucherController::class, 'show'])->name('vouchers.show');
    Route::post('/vouchers/{listing:slug}/{voucher:slug}/redeem', [VoucherRedemptionController::class, 'store'])
        ->middleware(['auth', 'throttle:voucher-redemption'])
        ->name('vouchers.redeem');
});

Route::get('/advertise', [AdvertiseController::class, 'index'])->name('advertise.index');
Route::post('/advertise/start', [AdvertiseController::class, 'start'])->middleware('auth')->name('advertise.start');

Route::get('/add-listing', [AddListingController::class, 'index'])->name('add-listing.index');
Route::post('/add-listing/start', [AddListingController::class, 'start'])->middleware('auth')->name('add-listing.start');

Route::get('/transport', TransportPageController::class)->name('transport.index');
Route::get('/about', [AboutController::class, 'index'])->name('about.index');
Route::get('/contact-us', [ContactController::class, 'index'])->name('contact.index');
Route::get('/terms-and-conditions', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/privacy-policy', [LegalController::class, 'privacy'])->name('legal.privacy');

Route::get('/staff-signup', [WriterApplicationController::class, 'create'])->name('staff-signup.create');
Route::post('/staff-signup', [WriterApplicationController::class, 'store'])
    ->middleware('throttle:public-form')
    ->name('staff-signup.store');
Route::get('/staff-signup/submitted', [WriterApplicationController::class, 'submitted'])->name('staff-signup.submitted');

Route::get('/faults', [CivicFaultMapController::class, 'index'])->name('faults.index');
Route::get('/faults/data/faults', [CivicFaultDataController::class, 'faults'])->name('faults.data.faults');
Route::get('/faults/data/councillors', [CivicFaultDataController::class, 'councillors'])->name('faults.data.councillors');

Route::prefix('maps')->name('maps.')->middleware('throttle:60,1')->group(function () {
    Route::get('/places/autocomplete', [MapAddressController::class, 'autocomplete'])->name('places.autocomplete');
    Route::get('/places/details', [MapAddressController::class, 'place'])->name('places.details');
    Route::get('/places/reverse', [MapAddressController::class, 'reverse'])->name('places.reverse');
});

Route::get('/ads/{adCampaign}/i', [AdTrackingController::class, 'impression'])
    ->middleware('throttle:public-tracking')
    ->name('ad-tracking.impression');
Route::get('/ads/{adCampaign}/click', [AdTrackingController::class, 'click'])
    ->middleware('throttle:public-tracking')
    ->name('ad-tracking.click');
Route::get('/push/{pushCampaign}/open', [AdTrackingController::class, 'pushOpen'])
    ->middleware('throttle:public-tracking')
    ->name('ad-tracking.push-open');
