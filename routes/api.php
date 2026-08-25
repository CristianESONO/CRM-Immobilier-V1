<?php

use App\Http\Controllers\Api\LeadController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/leads', [LeadController::class, 'store']);
