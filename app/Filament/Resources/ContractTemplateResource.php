<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractTemplateResource\Pages;
use App\Models\ContractTemplate;
use App\Models\Property;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContractTemplateResource extends Resource
{
    protected static ?string $model = ContractTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Contrats & Juridique';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Modèle de contrat';

    protected static ?string $pluralModelLabel = 'Modèles de contrat';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label('Nom du modèle')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('ex: Contrat de Réservation VEFA Standard'),

                    Select::make('contract_type')
                        ->label('Type de contrat')
                        ->options([
                            'reservation_vefa' => 'Réservation VEFA',
                            'promesse_vente' => 'Promesse de Vente',
                            'cession_droits' => 'Cession de Droits',
                            'annex' => 'Annexe / Notice Descriptive',
                        ])
                        ->default('reservation_vefa')
                        ->required(),

                    TextInput::make('version')
                        ->label('Version du modèle')
                        ->numeric()
                        ->default(1)
                        ->required(),

                    Toggle::make('is_active')
                        ->label('Actif')
                        ->default(true),
                ]),

                Textarea::make('content')
                    ->label('Corps du modèle (Variables: {{buyer.full_name}}, {{unit.reference}}, {{financials.total_amount}}, {{payment_schedule_table}}...)')
                    ->required()
                    ->rows(18)
                    ->columnSpanFull()
                    ->helperText('Balises disponibles: {{contract.number}}, {{reservation.reference}}, {{generated_at}}, {{promoter.name}}, {{buyer.full_name}}, {{buyer.phone}}, {{buyer.email}}, {{program.name}}, {{program.location}}, {{unit.reference}}, {{unit.area}}, {{financials.total_amount}}, {{financials.deposit_amount}}, {{payment_schedule_table}}'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('contract_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'reservation_vefa' => 'Réservation VEFA',
                        'promesse_vente' => 'Promesse de Vente',
                        'cession_droits' => 'Cession de Droits',
                        'annex' => 'Annexe',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'reservation_vefa' => 'info',
                        'promesse_vente' => 'primary',
                        'cession_droits' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('version')
                    ->label('Version')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContractTemplates::route('/'),
            'create' => Pages\CreateContractTemplate::route('/create'),
            'edit' => Pages\EditContractTemplate::route('/{record}/edit'),
        ];
    }
}
