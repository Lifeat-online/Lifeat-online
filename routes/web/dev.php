<?php

use App\Http\Controllers\Admin\AiSettingsController as AdminAiSettingsController;
use App\Http\Controllers\Admin\DevUpdateController as AdminDevUpdateController;
use App\Http\Controllers\Admin\MapIntegrationController as AdminMapIntegrationController;
use App\Http\Controllers\Admin\TranslationController as AdminTranslationController;
use App\Http\Controllers\Transport\Admin\SetupController as TransportAdminSetupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin,dev'])->prefix('dev')->name('dev.')->group(function () {
    Route::post('/tests/run', [AdminDevUpdateController::class, 'runTests'])
        ->middleware('throttle:2,1')
        ->name('tests.run');
    Route::post('/webpush/vapid/enable', [AdminDevUpdateController::class, 'enableVapidKeys'])
        ->middleware('throttle:3,1')
        ->name('webpush.vapid.enable');
    Route::post('/translations/key', [AdminTranslationController::class, 'saveKey'])
        ->middleware('throttle:6,1')
        ->name('translations.key.store');
    Route::post('/translations/preview', [AdminTranslationController::class, 'preview'])
        ->middleware('throttle:12,1')
        ->name('translations.preview');
    Route::post('/translations/batch', [AdminTranslationController::class, 'batch'])
        ->middleware('throttle:60,1')
        ->name('translations.batch');
    Route::post('/translations/articles/{article:slug}', [AdminTranslationController::class, 'translateArticle'])
        ->middleware('throttle:12,1')
        ->name('translations.articles.translate');
    Route::post('/ai/settings', [AdminAiSettingsController::class, 'save'])
        ->middleware('throttle:6,1')
        ->name('ai.settings.store');
    Route::post('/ai/test', [AdminAiSettingsController::class, 'test'])
        ->middleware('throttle:12,1')
        ->name('ai.test');
    Route::post('/ai/writer-process', [AdminAiSettingsController::class, 'writerProcess'])
        ->middleware('throttle:12,1')
        ->name('ai.writer.process');
    Route::post('/maps/key', [AdminMapIntegrationController::class, 'saveKey'])
        ->middleware('throttle:6,1')
        ->name('maps.key.store');
    Route::get('/transport', [TransportAdminSetupController::class, 'index'])->name('transport.setup');
    Route::post('/transport/managers', [TransportAdminSetupController::class, 'storeManager'])->name('transport.managers.store');
    Route::put('/transport/settings', [TransportAdminSetupController::class, 'updateSettings'])->name('transport.settings.update');
});
