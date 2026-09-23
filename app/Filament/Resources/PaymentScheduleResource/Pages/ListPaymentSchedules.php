<?php

namespace App\Filament\Resources\PaymentScheduleResource\Pages;

use App\Filament\Resources\PaymentScheduleResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentSchedules extends ListRecords
{
    protected static string $resource = PaymentScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
