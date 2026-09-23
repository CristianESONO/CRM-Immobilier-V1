<?php

namespace App\Filament\Resources\OperationalAlertResource\Pages;

use App\Filament\Resources\OperationalAlertResource;
use App\Services\Alerts\OperationalAlertService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListOperationalAlerts extends ListRecords
{
    protected static string $resource = OperationalAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scan')
                ->label('Lancer le balayage')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(function () {
                    $user = auth()->user();
                    if ($user && $user->tenant) {
                        $res = app(OperationalAlertService::class)->scanTenant($user->tenant);
                        Notification::make()
                            ->title('Balayage effectué')
                            ->body("{$res['created']} nouvelles alertes, {$res['auto_resolved']} résolues automatiquement.")
                            ->success()
                            ->send();
                    }
                }),
        ];
    }
}
