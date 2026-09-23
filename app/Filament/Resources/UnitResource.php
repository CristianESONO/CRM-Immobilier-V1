<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnitResource\Pages;
use App\Models\Property;
use App\Models\Unit;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationGroup = 'Référentiel Immobilier';

    protected static ?string $navigationLabel = 'Stock des Lots';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('reference')
                    ->label('Numéro / Référence du Lot')
                    ->required()
                    ->maxLength(255),

                Select::make('property_id')
                    ->label('Programme Immobilier')
                    ->relationship('property', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                Select::make('typology')
                    ->label('Typologie')
                    ->options([
                        'T1' => 'T1 / Studio',
                        'T2' => 'T2 (2 pièces)',
                        'T3' => 'T3 (3 pièces)',
                        'T4' => 'T4 (4 pièces)',
                        'T5' => 'T5 (5 pièces)',
                        'villa' => 'Villa',
                        'commercial' => 'Local Commercial',
                        'autre' => 'Autre',
                    ])
                    ->default('T3')
                    ->required(),

                TextInput::make('area')
                    ->label('Surface Habitable (m²)')
                    ->numeric()
                    ->required(),

                TextInput::make('price')
                    ->label('Prix de Vente (FCFA)')
                    ->numeric()
                    ->required(),

                Select::make('status')
                    ->label('Statut Commercial')
                    ->options([
                        'available' => 'Disponible',
                        'reserved' => 'Réservé',
                        'sold' => 'Vendu',
                    ])
                    ->default('available')
                    ->required(),

                DateTimePicker::make('marketed_at')
                    ->label('Date de Commercialisation')
                    ->default(now()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Lot')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('property.name')
                    ->label('Programme')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('typology')
                    ->label('Typologie')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('area')
                    ->label('Surface')
                    ->suffix(' m²')
                    ->sortable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Prix de Vente')
                    ->money('XOF')
                    ->sortable(),

                Tables\Columns\TextColumn::make('price_per_sqm')
                    ->label('Prix / m²')
                    ->money('XOF')
                    ->suffix(' / m²'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'reserved' => 'warning',
                        'sold' => 'danger',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'available' => 'Disponible',
                        'reserved' => 'Réservé',
                        'sold' => 'Vendu',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('age_in_days')
                    ->label('Âge Stock')
                    ->suffix(' j')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state <= 30 => 'success',
                        $state <= 90 => 'info',
                        $state <= 180 => 'warning',
                        default => 'danger',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'available' => 'Disponible',
                        'reserved' => 'Réservé',
                        'sold' => 'Vendu',
                    ]),

                SelectFilter::make('property_id')
                    ->label('Programme')
                    ->relationship('property', 'name'),

                SelectFilter::make('typology')
                    ->label('Typologie')
                    ->options([
                        'T1' => 'T1',
                        'T2' => 'T2',
                        'T3' => 'T3',
                        'T4' => 'T4',
                        'T5' => 'T5',
                        'villa' => 'Villa',
                        'commercial' => 'Commercial',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnits::route('/'),
            'create' => Pages\CreateUnit::route('/create'),
            'edit' => Pages\EditUnit::route('/{record}/edit'),
        ];
    }
}
