<?php

namespace App\Filament\Resources\DocumentRequirementResource\Pages;

use App\Filament\Resources\DocumentRequirementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocumentRequirements extends ListRecords
{
    protected static string $resource = DocumentRequirementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
