<?php

namespace App\Filament\Resources\ReservationResource\Pages;

use App\Filament\Resources\ReservationResource;
use App\Models\Unit;
use App\Services\ReservationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateReservation extends CreateRecord
{
    protected static string $resource = ReservationResource::class;

    /**
     * Override default creation to use ReservationService with pessimistic lock.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $scheduleItems = $data['schedules'] ?? [];
        unset($data['schedules']);

        $service = new ReservationService();
        return $service->createReservation($data, $scheduleItems);
    }
}
