<?php

namespace App\Filament\Widgets;

use App\Models\PaymentSchedule;
use App\Models\Reservation;
use App\Services\PaymentReminderService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OverdueSchedulesWidget extends BaseWidget
{
    protected static ?string $heading = 'Échéances en Retard — Action & Relances Requises';

    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PaymentSchedule::query()
                    ->with(['reservation.contact', 'reservation.property', 'reservation.unit', 'reminders'])
                    ->where(function (Builder $query) {
                        $query->where('status', 'overdue')
                            ->orWhere(function (Builder $q) {
                                $q->whereIn('status', ['pending', 'partial'])
                                    ->where('due_date', '<', now()->toDateString());
                            });
                    })
                    ->orderBy('due_date')
            )
            ->columns([
                Tables\Columns\TextColumn::make('reservation.reference')
                    ->label('Réservation')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('reservation.contact.first_name')
                    ->label('Acheteur')
                    ->state(fn ($record) => trim(
                        ($record->reservation?->contact?->first_name ?? '') . ' ' .
                        ($record->reservation?->contact?->last_name ?? '')
                    )),

                Tables\Columns\TextColumn::make('reservation.property.name')
                    ->label('Programme')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('reservation.unit.reference')
                    ->label('Lot')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('label')
                    ->label('Jalon'),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Date prévue')
                    ->date('d/m/Y')
                    ->color('danger'),

                Tables\Columns\TextColumn::make('expected_amount')
                    ->label('Montant attendu')
                    ->state(fn ($record) => number_format($record->expected_amount, 0, ',', ' ') . ' FCFA')
                    ->color('danger'),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Déjà encaissé')
                    ->state(fn ($record) => number_format($record->paid_amount, 0, ',', ' ') . ' FCFA')
                    ->color('success'),

                Tables\Columns\TextColumn::make('days_overdue')
                    ->label('Retard')
                    ->state(fn ($record) => $record->due_date
                        ? now()->diffInDays($record->due_date) . ' j'
                        : '—')
                    ->badge()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('reminders_count')
                    ->label('Relances')
                    ->counts('reminders')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
            ])
            ->actions([
                Action::make('relancer')
                    ->label('Relancer')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->button()
                    ->modalHeading(fn ($record) => "Relancer l'acquéreur — " . ($record->reservation?->contact?->first_name ?? 'Client'))
                    ->modalDescription(fn ($record) => "Échéance « {$record->label} » d'un montant de " . number_format($record->expected_amount - $record->paid_amount, 0, ',', ' ') . " FCFA")
                    ->form([
                        Forms\Components\Select::make('channel')
                            ->label('Canal d’envoi')
                            ->options([
                                'whatsapp' => 'WhatsApp (Recommandé)',
                                'email' => 'Email',
                                'sms' => 'SMS',
                                'call' => 'Appel téléphonique consigné',
                            ])
                            ->default('whatsapp')
                            ->required(),

                        Forms\Components\Select::make('trigger_type')
                            ->label('Niveau de relance')
                            ->options([
                                'overdue_j7' => '1ère relance amiable (J+7)',
                                'overdue_j15' => 'Mise en demeure / Relance urgente (J+15)',
                                'manual' => 'Relance personnalisée / Point dossier',
                            ])
                            ->default('overdue_j7')
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set, $record) {
                                if ($record) {
                                    $service = app(PaymentReminderService::class);
                                    $set('message_content', $service->generateMessageText($record, $state, 'whatsapp'));
                                }
                            }),

                        Forms\Components\TextInput::make('recipient')
                            ->label('Destinataire')
                            ->default(fn ($record) => $record->reservation?->contact?->phone ?? $record->reservation?->contact?->email)
                            ->required(),

                        Forms\Components\Textarea::make('message_content')
                            ->label('Message préparé')
                            ->rows(6)
                            ->default(function ($record) {
                                $service = app(PaymentReminderService::class);
                                return $service->generateMessageText($record, 'overdue_j7', 'whatsapp');
                            })
                            ->required(),
                    ])
                    ->action(function (PaymentSchedule $record, array $data): void {
                        $service = app(PaymentReminderService::class);
                        $service->sendReminder(
                            schedule: $record,
                            triggerType: $data['trigger_type'],
                            channel: $data['channel'],
                            customMessage: $data['message_content'],
                            userId: Auth::id()
                        );

                        Notification::make()
                            ->title('Relance enregistrée et transmise avec succès !')
                            ->body("La relance via " . strtoupper($data['channel']) . " pour {$record->reservation?->contact?->first_name} a été consignée.")
                            ->success()
                            ->send();
                    }),
            ])
            ->paginated([10, 25])
            ->emptyStateHeading('Aucune échéance en retard')
            ->emptyStateDescription('Toutes les échéances sont à jour.')
            ->emptyStateIcon('heroicon-o-check-badge');
    }
}
