<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommissionResource\Pages;
use App\Models\Commission;
use App\Services\Partners\CommissionEngineService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CommissionResource extends Resource
{
    protected static ?string $model = Commission::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Réseau Apporteurs';

    protected static ?string $navigationLabel = 'Gestion des Commissions';

    protected static ?string $modelLabel = 'Commission';

    protected static ?string $pluralModelLabel = 'Commissions Apporteurs';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('referrer.name')
                    ->label('Apporteur')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('reservation.reference')
                    ->label('Réservation')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('sales_amount')
                    ->label('Montant Vente')
                    ->state(fn (Commission $record) => number_format((float) $record->sales_amount, 0, ',', ' ') . ' FCFA'),

                Tables\Columns\TextColumn::make('commission_amount')
                    ->label('Commission Due')
                    ->state(fn (Commission $record) => number_format((float) $record->commission_amount, 0, ',', ' ') . ' FCFA')
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'calculated' => 'info',
                        'validated' => 'warning',
                        'payable' => 'primary',
                        'paid' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('payment_reference')
                    ->label('Réf. Règlement')
                    ->placeholder('Non réglée'),

                Tables\Columns\TextColumn::make('calculated_at')
                    ->label('Calculée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('calculated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'calculated' => 'Calculée (En attente)',
                        'validated' => 'Validée',
                        'payable' => 'Payable (Acompte encaisse)',
                        'paid' => 'Payée / Réglée',
                        'cancelled' => 'Annulée',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('validate_commission')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')
                    ->color('warning')
                    ->visible(fn (Commission $record): bool => $record->status === 'calculated')
                    ->action(function (Commission $record) {
                        app(CommissionEngineService::class)->validateCommission($record, auth()->user());
                        Notification::make()->title('Commission validée')->success()->send();
                    }),

                Tables\Actions\Action::make('mark_payable')
                    ->label('Passer Payable')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('primary')
                    ->visible(fn (Commission $record): bool => in_array($record->status, ['calculated', 'validated'], true))
                    ->action(function (Commission $record) {
                        app(CommissionEngineService::class)->markPayable($record, auth()->user());
                        Notification::make()->title('Commission marquée payable')->success()->send();
                    }),

                Tables\Actions\Action::make('mark_paid')
                    ->label('Marquer Réglée')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Commission $record): bool => in_array($record->status, ['validated', 'payable'], true))
                    ->form([
                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Référence de paiement (Virement / Chèque)')
                            ->required(),
                    ])
                    ->action(function (Commission $record, array $data) {
                        app(CommissionEngineService::class)->markPaid($record, $data['payment_reference'], auth()->user());
                        Notification::make()->title('Commission marquée payée')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommissions::route('/'),
        ];
    }
}
