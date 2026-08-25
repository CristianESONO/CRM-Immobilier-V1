<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PropertyResource\Pages;
use App\Models\Property;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PropertyResource extends Resource
{
    protected static ?string $model = Property::class;

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationGroup = 'Référentiel Immobilier';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nom du Programme / Projet')
                    ->required()
                    ->maxLength(255),
                TextInput::make('location')
                    ->label('Emplacement / Localisation')
                    ->maxLength(255),
                Select::make('property_type')
                    ->label('Type de Bien')
                    ->options([
                        'apartment' => 'Appartement',
                        'villa' => 'Villa',
                        'land' => 'Terrain',
                        'commercial' => 'Local Commercial',
                    ]),
                TextInput::make('price_min')
                    ->label('Prix Min (FCFA)')
                    ->numeric(),
                TextInput::make('price_max')
                    ->label('Prix Max (FCFA)')
                    ->numeric(),
                DatePicker::make('delivery_date')
                    ->label('Date de Livraison Prévue'),
                Select::make('status')
                    ->label('Statut du Programme')
                    ->options([
                        'available' => 'En Commercialisation',
                        'sold_out' => 'Épuisé',
                        'upcoming' => 'À Venir',
                    ])
                    ->default('available'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Programme')->searchable(),
                Tables\Columns\TextColumn::make('location')->label('Localisation')->searchable(),
                Tables\Columns\TextColumn::make('price_min')->label('Prix Min')->money('XOF'),
                Tables\Columns\TextColumn::make('price_max')->label('Prix Max')->money('XOF'),
                Tables\Columns\TextColumn::make('status')->label('Statut')->badge(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProperties::route('/'),
            'create' => Pages\CreateProperty::route('/create'),
            'edit' => Pages\EditProperty::route('/{record}/edit'),
        ];
    }
}
