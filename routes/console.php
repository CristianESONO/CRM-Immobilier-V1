<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Planification quotidienne des relances d'échéances VEFA.
 * S'exécute chaque matin à 08:00 pour notifier les acquéreurs des appels de fonds à venir ou en retard.
 */
Schedule::command('crm:send-payment-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/payment-reminders.log'));

Schedule::command('sequences:process')->hourly();
Schedule::command('contacts:check-alerts')->everyFifteenMinutes();

