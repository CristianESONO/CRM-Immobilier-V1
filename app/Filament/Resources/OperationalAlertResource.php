<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OperationalAlertResource\Pages;
use App\Models\BuyerDocument;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\OperationalAlert;
use App\Models\PaymentReminder;
use App\Models\PaymentSchedule;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\SignatureWebhookEvent;
use App\Models\Unit;
use App\Services\Alerts\OperationalAlertService;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

class OperationalAlertResource extends Resource
{
    protected static ?string $model = OperationalAlert::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Gouvernance & Conformité';

    protected static ?string $navigationLabel = 'Alertes Opérationnelles';

    protected static ?string $modelLabel = 'Alerte Opérationnelle';

    protected static ?string $pluralModelLabel = 'Alertes Opérationnelles';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('severity')
                    ->label('Sévérité')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        'info' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),

                Tables\Columns\TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->weight('bold')
                    ->url(fn (OperationalAlert $record): ?string => self::getDrilldownUrl($record)),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'danger',
                        'acknowledged' => 'warning',
                        'resolved' => 'success',
                        'dismissed' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('assignedToUser.name')
                    ->label('Assigné à')
                    ->placeholder('Non assigné'),

                Tables\Columns\TextColumn::make('detected_at')
                    ->label('Détectée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('resolved_at')
                    ->label('Résolue le')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Non résolue'),
            ])
            ->defaultSort('detected_at', 'desc')
            ->filters([
                SelectFilter::make('severity')
                    ->label('Sévérité')
                    ->options([
                        'critical' => 'Critique',
                        'warning' => 'Avertissement',
                        'info' => 'Information',
                    ]),

                SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'open' => 'Ouverte',
                        'acknowledged' => 'Acquittée',
                        'resolved' => 'Résolue',
                        'dismissed' => 'Ignorée',
                    ]),

                SelectFilter::make('type')
                    ->label('Type d\'alerte')
                    ->options([
                        'lead_no_response_sla' => 'Lead sans réponse',
                        'reservation_without_contract' => 'Réservation sans contrat',
                        'kyc_incomplete_folder' => 'Dossier KYC incomplet',
                        'kyc_document_expired' => 'Document KYC expiré',
                        'contract_pending_approval' => 'Contrat en attente de validation',
                        'signature_sent_unsigned' => 'Contrat non signé',
                        'finance_schedule_upcoming' => 'Échéance proche',
                        'finance_schedule_overdue' => 'Échéance en retard',
                        'finance_refund_blocked' => 'Remboursement bloqué',
                        'stock_option_expired' => 'Option expirée',
                        'technical_webhook_error' => 'Webhook en erreur',
                        'operational_task_overdue' => 'Rappel échu',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('acknowledge')
                    ->label('Acquitter')
                    ->icon('heroicon-o-check-circle')
                    ->color('warning')
                    ->visible(fn (OperationalAlert $record): bool => $record->status === 'open')
                    ->action(function (OperationalAlert $record) {
                        app(OperationalAlertService::class)->acknowledge($record);
                        Notification::make()->title('Alerte acquittée')->success()->send();
                    }),

                Tables\Actions\Action::make('resolve')
                    ->label('Résoudre')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (OperationalAlert $record): bool => $record->isOpen())
                    ->action(function (OperationalAlert $record) {
                        app(OperationalAlertService::class)->resolve($record);
                        Notification::make()->title('Alerte marquée résolue')->success()->send();
                    }),

                Tables\Actions\Action::make('dismiss')
                    ->label('Ignorer')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (OperationalAlert $record): bool => $record->isOpen())
                    ->action(function (OperationalAlert $record) {
                        app(OperationalAlertService::class)->dismiss($record);
                        Notification::make()->title('Alerte ignorée')->info()->send();
                    }),
            ]);
    }

    public static function getDrilldownUrl(OperationalAlert $record): ?string
    {
        try {
            return match ($record->entity_type) {
                Reservation::class => ReservationResource::getUrl('index'),
                Contract::class => ContractResource::getUrl('index'),
                PaymentSchedule::class => PaymentScheduleResource::getUrl('index', [
                    'tableFilters' => ['drilldown' => ['value' => 'overdue']],
                ]),
                Refund::class => RefundResource::getUrl('index'),
                BuyerDocument::class => BuyerDocumentResource::getUrl('index'),
                Contact::class => ContactResource::getUrl('index'),
                Unit::class => UnitResource::getUrl('index'),
                PaymentReminder::class => PaymentReminderResource::getUrl('index'),
                SignatureWebhookEvent::class => AuditLogResource::getUrl('index'),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOperationalAlerts::route('/'),
        ];
    }
}
