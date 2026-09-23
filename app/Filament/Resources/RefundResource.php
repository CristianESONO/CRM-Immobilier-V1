<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RefundResource\Pages;
use App\Models\Refund;
use App\Services\RefundService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class RefundResource extends Resource
{
    protected static ?string $model = Refund::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationGroup = 'Ventes & Réservations';

    protected static ?string $modelLabel = 'Remboursement';

    protected static ?string $pluralModelLabel = 'Remboursements';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false; // Les remboursements sont initiés depuis les annulations ou services métier
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Détail du Remboursement')
                    ->schema([
                        Forms\Components\TextInput::make('reference')->label('Référence')->disabled(),
                        Forms\Components\TextInput::make('amount')->label('Montant')->suffix('FCFA')->disabled(),
                        Forms\Components\TextInput::make('status')->label('Statut')->disabled(),
                        Forms\Components\Textarea::make('reason')->label('Motif')->disabled()->columnSpanFull(),
                        Forms\Components\TextInput::make('payment_method')->label('Moyen de Paiement')->disabled(),
                        Forms\Components\TextInput::make('proof_reference')->label('Référence Virement')->disabled(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Réf. Remboursement')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('reservation.reference')
                    ->label('Réservation')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('reservation.contact.first_name')
                    ->label('Acquéreur')
                    ->state(fn ($record) => trim(
                        ($record->reservation?->contact?->first_name ?? '') . ' ' .
                        ($record->reservation?->contact?->last_name ?? '')
                    )),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Montant à Restituer')
                    ->state(fn ($record) => number_format($record->amount, 0, ',', ' ') . ' FCFA')
                    ->color('danger')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'processing' => 'warning',
                        'completed' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending' => 'En attente validation',
                        'approved' => 'Approuvé (À régler)',
                        'processing' => 'En traitement',
                        'completed' => 'Remboursé',
                        'rejected' => 'Rejeté',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('requested_at')
                    ->label('Demandé le')
                    ->dateTime('d/m/Y H:i'),

                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Remboursé le')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
            ])
            ->defaultSort('requested_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'pending' => 'En attente',
                        'approved' => 'Approuvé',
                        'processing' => 'En traitement',
                        'completed' => 'Remboursé',
                        'rejected' => 'Rejeté',
                    ]),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approuver')
                    ->icon('heroicon-o-check')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Approuver le remboursement')
                    ->modalDescription('Confirmez-vous l’autorisation de ce remboursement pour exécution par la comptabilité ?')
                    ->visible(fn (Refund $record) => $record->status === 'pending' && (Auth::user()?->isAdmin() ?? true))
                    ->action(function (Refund $record) {
                        $service = new RefundService();
                        $service->approveRefund($record, Auth::id());

                        Notification::make()
                            ->title('Remboursement approuvé !')
                            ->body("La demande {$record->reference} est prête pour règlement comptable.")
                            ->success()
                            ->send();
                    }),

                Action::make('process')
                    ->label('Exécuter')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Refund $record) => in_array($record->status, ['approved', 'processing']) && (Auth::user()?->isAdmin() ?? true))
                    ->form([
                        Forms\Components\Select::make('payment_method')
                            ->label('Mode de Restitution')
                            ->options([
                                'bank_transfer' => 'Virement bancaire',
                                'check' => 'Chèque',
                                'om_wave' => 'Orange Money / Wave',
                                'cash' => 'Espèces',
                            ])
                            ->default('bank_transfer')
                            ->required(),

                        Forms\Components\TextInput::make('proof_reference')
                            ->label('Référence du bordereau / chèque')
                            ->placeholder('Ex : VIR-REMB-2026-0044')
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes complémentaires'),
                    ])
                    ->action(function (Refund $record, array $data) {
                        $service = new RefundService();
                        $service->processRefund($record, $data, Auth::id());

                        Notification::make()
                            ->title('Remboursement exécuté !')
                            ->body("Le règlement de {$record->reference} a été consigné avec succès.")
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Rejeter')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Refund $record) => $record->status === 'pending' && (Auth::user()?->isAdmin() ?? true))
                    ->form([
                        Forms\Components\TextInput::make('reason')
                            ->label('Motif du rejet')
                            ->required(),
                    ])
                    ->action(function (Refund $record, array $data) {
                        $service = new RefundService();
                        $service->rejectRefund($record, $data['reason'], Auth::id());

                        Notification::make()
                            ->title('Remboursement rejeté')
                            ->warning()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRefunds::route('/'),
        ];
    }
}
