<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $currentUser = auth()->user();

        // Si l'utilisateur n'est pas super_admin, on assigne automatiquement son tenant
        if ($currentUser && $currentUser->role !== 'super_admin') {
            $data['tenant_id'] = $currentUser->tenant_id;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
