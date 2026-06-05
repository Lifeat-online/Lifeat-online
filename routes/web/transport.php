<?php

use App\Http\Controllers\Transport\Admin\SetupController as TransportAdminSetupController;
use App\Http\Controllers\Transport\DriverDutyController as TransportDriverDutyController;
use App\Http\Controllers\Transport\DriverOfferController as TransportDriverOfferController;
use App\Http\Controllers\Transport\DriverWorkspaceController as TransportDriverWorkspaceController;
use App\Http\Controllers\Transport\Manager\DashboardController as TransportManagerDashboardController;
use App\Http\Controllers\Transport\Manager\DriverController as TransportManagerDriverController;
use App\Http\Controllers\Transport\Manager\VehicleController as TransportManagerVehicleController;
use App\Http\Controllers\Transport\RequestCancellationController as TransportRequestCancellationController;
use App\Http\Controllers\Transport\RequestController as TransportRequestController;
use App\Http\Controllers\Transport\RequestTrackingController as TransportRequestTrackingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('transport')->name('transport.')->group(function () {
    Route::get('/requests/create', [TransportRequestController::class, 'create'])->name('requests.create');
    Route::post('/requests', [TransportRequestController::class, 'store'])->name('requests.store');
    Route::get('/requests/{transportRequest}', [TransportRequestController::class, 'show'])->name('requests.show');
    Route::post('/requests/{transportRequest}/cancel', [TransportRequestCancellationController::class, 'store'])->name('requests.cancel');
    Route::get('/requests/{transportRequest}/tracking', [TransportRequestTrackingController::class, 'show'])->name('requests.tracking');
    Route::post('/requests/{transportRequest}/passenger-location', [TransportRequestTrackingController::class, 'updatePassenger'])->name('requests.passenger-location');
    Route::post('/requests/{transportRequest}/driver-location', [TransportRequestTrackingController::class, 'updateDriver'])
        ->middleware('transport.on_duty')
        ->name('requests.driver-location');

    Route::middleware('role:transport_manager,admin,dev')->prefix('manager')->name('manager.')->group(function () {
        Route::get('/', TransportManagerDashboardController::class)->name('dashboard');
        Route::get('/drivers', [TransportManagerDriverController::class, 'index'])->name('drivers.index');
        Route::post('/drivers', [TransportManagerDriverController::class, 'store'])->name('drivers.store');
        Route::get('/drivers/{driver}/edit', [TransportManagerDriverController::class, 'edit'])->name('drivers.edit');
        Route::put('/drivers/{driver}', [TransportManagerDriverController::class, 'update'])->name('drivers.update');
        Route::get('/vehicles', [TransportManagerVehicleController::class, 'index'])->name('vehicles.index');
        Route::post('/vehicles', [TransportManagerVehicleController::class, 'store'])->name('vehicles.store');
        Route::get('/vehicles/{vehicle}/edit', [TransportManagerVehicleController::class, 'edit'])->name('vehicles.edit');
        Route::put('/vehicles/{vehicle}', [TransportManagerVehicleController::class, 'update'])->name('vehicles.update');
    });

    Route::middleware('role:transport_driver,admin')->prefix('driver')->name('driver.')->group(function () {
        Route::get('/duty', [TransportDriverDutyController::class, 'show'])->name('duty');
        Route::post('/clock-in', [TransportDriverDutyController::class, 'clockIn'])->name('clock-in');
        Route::post('/clock-out', [TransportDriverDutyController::class, 'clockOut'])->name('clock-out');
        Route::post('/offers/{offer}/accept', [TransportDriverOfferController::class, 'accept'])
            ->middleware('transport.on_duty')
            ->name('offers.accept');
        Route::get('/', TransportDriverWorkspaceController::class)
            ->middleware('transport.on_duty')
            ->name('workspace');
    });
});
