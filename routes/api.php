<?php

use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/leads', [LeadController::class, 'store']);

Route::get('/v1/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/v1/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle']);
