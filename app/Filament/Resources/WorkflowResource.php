<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkflowResource\Pages;
use App\Models\Workflow;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WorkflowResource extends Resource
{
    protected static ?string $model = Workflow::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'Gouvernance & Conformité';

    protected static ?string $navigationLabel = 'Workflows & Automatisation';

    protected static ?string $modelLabel = 'Workflow';

    protected static ?string $pluralModelLabel = 'Workflows';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nom du Workflow')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('trigger_event')
                    ->label('Événement Déclencheur')
                    ->required()
                    ->options([
                        'ReservationCreated' => 'Réservation Créée (ReservationCreated)',
                        'ReservationCancelled' => 'Réservation Annulée (ReservationCancelled)',
                        'PaymentRecorded' => 'Paiement Encaissé (PaymentRecorded)',
                        'ContractSigned' => 'Contrat Signé (ContractSigned)',
                        'RefundCompleted' => 'Remboursement Exécuté (RefundCompleted)',
                        'KycDocumentVerified' => 'Document KYC Validé (KycDocumentVerified)',
                        'OperationalAlertCreated' => 'Alerte Opérationnelle Créée (OperationalAlertCreated)',
                        'ContactCreated' => 'Prospect/Contact Créé (ContactCreated)',
                    ]),

                Forms\Components\Toggle::make('is_active')
                    ->label('Actif')
                    ->default(true),

                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('trigger_event')
                    ->label('Déclencheur')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Statut')
                    ->boolean(),

                Tables\Columns\TextColumn::make('conditions_count')
                    ->label('Conditions')
                    ->counts('conditions'),

                Tables\Columns\TextColumn::make('actions_count')
                    ->label('Actions')
                    ->counts('actions'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkflows::route('/'),
        ];
    }
}
