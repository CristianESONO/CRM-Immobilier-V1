<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Administration & Audit';

    protected static ?string $modelLabel = 'Journal d’Audit';

    protected static ?string $pluralModelLabel = 'Journal d’Audit';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false; // Journal immuable : aucune création manuelle
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('action')->disabled(),
                Forms\Components\TextInput::make('created_at')->disabled(),
                Forms\Components\TextInput::make('auditable_type')->disabled(),
                Forms\Components\TextInput::make('auditable_id')->disabled(),
                Forms\Components\KeyValue::make('old_values')->label('Anciennes Valeurs')->disabled(),
                Forms\Components\KeyValue::make('new_values')->label('Nouvelles Valeurs')->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Horodatage')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('action')
                    ->label('Événement')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'RESERVATION_CREATED' => 'info',
                        'PAYMENT_RECORDED', 'REFUND_COMPLETED' => 'success',
                        'RESERVATION_CANCELLED', 'REFUND_REJECTED' => 'danger',
                        'REFUND_REQUESTED' => 'warning',
                        'REFUND_APPROVED' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Auteur')
                    ->default('Système')
                    ->badge(),

                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Entité')
                    ->formatStateUsing(fn ($state) => class_basename($state)),

                Tables\Columns\TextColumn::make('auditable_id')
                    ->label('ID Cible'),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')
                    ->label('Type d’Événement')
                    ->options([
                        'RESERVATION_CREATED' => 'Réservation Créée',
                        'PAYMENT_RECORDED' => 'Paiement Encaissé',
                        'RESERVATION_CANCELLED' => 'Réservation Annulée',
                        'REFUND_REQUESTED' => 'Remboursement Demandé',
                        'REFUND_APPROVED' => 'Remboursement Approuvé',
                        'REFUND_COMPLETED' => 'Remboursement Exécuté',
                        'REFUND_REJECTED' => 'Remboursement Rejeté',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Détails de l’Entrée d’Audit'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
