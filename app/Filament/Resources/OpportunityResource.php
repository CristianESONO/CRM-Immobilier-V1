<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OpportunityResource\Pages;
use App\Models\Opportunity;
use App\Models\Unit;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OpportunityResource extends Resource
{
    protected static ?string $model = Opportunity::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationGroup = 'CRM & Prospects';

    protected static ?string $navigationLabel = 'Opportunités Immobilières';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Projet & Prospect')
                    ->schema([
                        TextInput::make('title')
                            ->label('Titre du projet / opportunité')
                            ->placeholder('Ex: Acquisition F4 - Résidence Les Almadies')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('contact_id')
                            ->label('Prospect / Client')
                            ->relationship('contact')
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->first_name} {$record->last_name} " . ($record->phone_e164 ? "({$record->phone_e164})" : ''))
                            ->searchable(['first_name', 'last_name', 'phone_e164'])
                            ->preload()
                            ->required(),

                        Select::make('property_id')
                            ->label('Programme Immobilier')
                            ->relationship('property', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('unit_id', null)),

                        Select::make('unit_id')
                            ->label('Lot / Unité Ciblée')
                            ->options(function (Get $get) {
                                $propertyId = $get('property_id');
                                if (!$propertyId) {
                                    return Unit::where('status', 'available')->pluck('reference', 'id');
                                }
                                return Unit::where('property_id', $propertyId)
                                    ->pluck('reference', 'id');
                            })
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $unit = Unit::find($state);
                                    if ($unit && $unit->price) {
                                        $set('amount', $unit->price);
                                    }
                                }
                            }),

                        Select::make('assigned_to')
                            ->label('Commercial Assigné')
                            ->relationship('assignedTo', 'name')
                            ->searchable()
                            ->preload(),
                    ])->columns(2),

                Section::make('Avancement & Négociation')
                    ->schema([
                        Select::make('stage')
                            ->label('Étape Commerciale')
                            ->options([
                                'decouverte' => 'Découverte / Qualification',
                                'visite_planifiee' => 'Visite Planifiée',
                                'visite_realisee' => 'Visite Réalisée',
                                'offre_en_cours' => 'Offre en cours',
                                'reservation' => 'Réservation signée',
                                'gagne' => 'Vente Gagnée',
                                'perdu' => 'Perdu / Abandonné',
                            ])
                            ->default('decouverte')
                            ->live()
                            ->required(),

                        TextInput::make('amount')
                            ->label('Montant / Valeur (FCFA)')
                            ->numeric()
                            ->suffix('FCFA'),

                        DatePicker::make('expected_close_at')
                            ->label('Date Prévisionnelle de Signature'),

                        TextInput::make('lost_reason')
                            ->label('Motif de Perte')
                            ->visible(fn (Get $get) => $get('stage') === 'perdu')
                            ->maxLength(255),

                        Textarea::make('notes')
                            ->label('Notes & Points de négociation')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Opportunité')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('contact.full_name')
                    ->label('Prospect')
                    ->state(fn ($record) => trim("{$record->contact?->first_name} {$record->contact?->last_name}"))
                    ->searchable(['first_name', 'last_name']),

                Tables\Columns\TextColumn::make('property.name')
                    ->label('Programme')
                    ->badge()
                    ->color('info')
                    ->placeholder('Non assigné'),

                Tables\Columns\TextColumn::make('unit.reference')
                    ->label('Lot')
                    ->badge()
                    ->color('gray')
                    ->placeholder('À définir'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Montant')
                    ->money('XOF')
                    ->sortable(),

                Tables\Columns\TextColumn::make('stage')
                    ->label('Étape')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'decouverte' => 'info',
                        'visite_planifiee', 'visite_realisee' => 'warning',
                        'offre_en_cours', 'reservation' => 'primary',
                        'gagne' => 'success',
                        'perdu' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'decouverte' => 'Découverte',
                        'visite_planifiee' => 'Visite Planifiée',
                        'visite_realisee' => 'Visite Réalisée',
                        'offre_en_cours' => 'Offre en cours',
                        'reservation' => 'Réservation',
                        'gagne' => 'Gagné',
                        'perdu' => 'Perdu',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('assignedTo.name')
                    ->label('Commercial')
                    ->placeholder('Non assigné'),

                Tables\Columns\TextColumn::make('expected_close_at')
                    ->label('Échéance')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('stage')
                    ->label('Étape')
                    ->options([
                        'decouverte' => 'Découverte',
                        'visite_planifiee' => 'Visite Planifiée',
                        'visite_realisee' => 'Visite Réalisée',
                        'offre_en_cours' => 'Offre en cours',
                        'reservation' => 'Réservation',
                        'gagne' => 'Gagné',
                        'perdu' => 'Perdu',
                    ]),

                Tables\Filters\SelectFilter::make('property_id')
                    ->label('Programme')
                    ->relationship('property', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOpportunities::route('/'),
            'create' => Pages\CreateOpportunity::route('/create'),
            'edit' => Pages\EditOpportunity::route('/{record}/edit'),
        ];
    }
}
