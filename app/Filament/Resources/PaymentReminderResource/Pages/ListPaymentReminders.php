<?php

namespace App\Filament\Resources\PaymentReminderResource\Pages;

use App\Filament\Resources\PaymentReminderResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentReminders extends ListRecords
{
    protected static string $resource = PaymentReminderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
