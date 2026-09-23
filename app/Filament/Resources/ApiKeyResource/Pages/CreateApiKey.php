<?php

namespace App\Filament\Resources\ApiKeyResource\Pages;

use App\Filament\Resources\ApiKeyResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateApiKey extends CreateRecord
{
    protected static string $resource = ApiKeyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $rawToken = 'crm_live_' . Str::random(32);
        $data['key_hash'] = hash('sha256', $rawToken);

        // Store raw token in session flash or notification if needed
        session()->flash('generated_api_token', $rawToken);

        return $data;
    }
}
