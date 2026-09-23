<?php

use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\Portal\BuyerPortalController;
use App\Http\Controllers\Portal\PartnerPortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/secure-documents/contracts/{contractVersion}/download', [DocumentDownloadController::class, 'downloadContractVersion'])
        ->name('documents.contracts.download');

    Route::get('/secure-documents/kyc/{buyerDocument}/download', [DocumentDownloadController::class, 'downloadBuyerDocument'])
        ->name('documents.kyc.download');
});

// Portail Acquéreur VEFA (V6.1)
Route::post('/portal/buyer/login', [BuyerPortalController::class, 'login']);
Route::get('/portal/buyer/dashboard', [BuyerPortalController::class, 'dashboard']);

// Portail Apporteur / Prescripteur (V6.2)
Route::post('/portal/partner/login', [PartnerPortalController::class, 'login']);
Route::get('/portal/partner/dashboard', [PartnerPortalController::class, 'dashboard']);
Route::post('/portal/partner/leads', [PartnerPortalController::class, 'submitLead']);

Route::get('/health', function (\App\Services\Health\HealthCheckService $healthService) {
    $report = $healthService->runAllChecks();
    $statusCode = $report['status'] === 'healthy' ? 200 : 503;
    return response()->json($report, $statusCode);
})->name('health');
