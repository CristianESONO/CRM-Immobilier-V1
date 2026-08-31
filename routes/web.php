<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/reports/committee-pdf', [ReportController::class, 'exportCommitteePdf'])->name('reports.committee-pdf');
