<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Contracts\PdfGeneratorInterface::class,
            \App\Services\Pdf\SimplePdfGenerator::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::subscribe(\App\Listeners\AuditDomainEventListener::class);
        \Illuminate\Support\Facades\Event::subscribe(\App\Listeners\ReservationStatusSubscriber::class);
        \Illuminate\Support\Facades\Event::subscribe(\App\Listeners\OutboundWebhookListener::class);
    }
}
