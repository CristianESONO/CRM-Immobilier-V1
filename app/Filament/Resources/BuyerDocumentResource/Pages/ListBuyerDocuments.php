<?php

namespace App\Filament\Resources\BuyerDocumentResource\Pages;

use App\Filament\Resources\BuyerDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBuyerDocuments extends ListRecords
{
    protected static string $resource = BuyerDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
