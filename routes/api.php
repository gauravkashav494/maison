<?php

use App\Http\Controllers\Api\SyncAgentController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Middleware\AuthenticateSyncAgent;
use Illuminate\Support\Facades\Route;

Route::prefix('sync/v1')->middleware(['throttle:600,1', AuthenticateSyncAgent::class])->group(function () {
    Route::get('ping', [SyncController::class, 'ping']);

    Route::get('changes/{resource}', [SyncController::class, 'changes'])->whereIn('resource', ['products', 'orders', 'customers']);
    Route::post('changes/{resource}/ack', [SyncController::class, 'acknowledge'])->whereIn('resource', ['products', 'orders', 'customers']);

    Route::post('products', [SyncController::class, 'products']);
    Route::post('stock', [SyncController::class, 'stock']);
    Route::post('customers', [SyncController::class, 'customers']);
    Route::post('orders/status', [SyncController::class, 'orderStatuses']);

    Route::get('agent/config', [SyncAgentController::class, 'config']);
    Route::get('agent/package', [SyncAgentController::class, 'package']);
    Route::get('agent/package/binary', [SyncAgentController::class, 'binary']);
    Route::post('agent/schema', [SyncAgentController::class, 'schema']);
    Route::post('agent/report', [SyncAgentController::class, 'report']);
});
