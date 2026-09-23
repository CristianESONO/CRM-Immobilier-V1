<?php

use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\V1\ApiContractController;
use App\Http\Controllers\Api\V1\ApiPropertyController;
use App\Http\Controllers\Api\V1\ApiReservationController;
use App\Http\Controllers\Api\V1\MetricsController;
use App\Http\Controllers\SignatureWebhookController;
use App\Http\Middleware\ApiScopeMiddleware;
use App\Http\Middleware\TenantApiMiddleware;
use Illuminate\Support\Facades\Route;

Route::post('/v1/leads', [LeadController::class, 'store']);
Route::post('/webhooks/signature/{provider}', [SignatureWebhookController::class, 'handle'])
    ->name('api.webhooks.signature');

// V6.4 / V8.1 API Platform
Route::prefix('v1')->middleware([TenantApiMiddleware::class])->group(function () {
    Route::get('/properties', [ApiPropertyController::class, 'properties']);
    Route::get('/units', [ApiPropertyController::class, 'units']);
    Route::get('/reservations', [ApiReservationController::class, 'index']);
    Route::get('/contracts', [ApiContractController::class, 'index']);
    Route::get('/metrics', [MetricsController::class, 'index']);
});

// V8.1 Scoped Protected API
Route::prefix('v1/scoped')->middleware([ApiScopeMiddleware::class . ':properties:read'])->group(function () {
    Route::get('/properties', [ApiPropertyController::class, 'properties']);
});
