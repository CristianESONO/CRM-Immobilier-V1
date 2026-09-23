<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReservationResource\Pages;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\Reservation;
use App\Models\Unit;
use App\Services\Contracts\ContractService;
use App\Services\PaymentReminderService;
use App\Services\ReservationService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'CRM & Prospects';

    protected static ?string $navigationLabel = 'Réservations & Paiements';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Dossier de Réservation')
                    ->schema([
                        Select::make('contact_id')
                            ->label('Client / Acheteur')
                            ->relationship('contact')
                            ->getOptionLabelFromRecordUsing(
                                fn ($record) => "{$record->first_name} {$record->last_name}" .
                                    ($record->phone_e164 ? " ({$record->phone_e164})" : '')
                            )
                            ->searchable(['first_name', 'last_name', 'phone_e164'])
                            ->preload()
                            ->required(),

                        Select::make('opportunity_id')
                            ->label('Opportunité liée (facultatif)')
                            ->relationship('opportunity', 'title')
                            ->searchable()
                            ->preload(),

                        Select::make('property_id')
                            ->label('Programme Immobilier')
                            ->relationship('property', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('unit_id', null)),

                        Select::make('unit_id')
                            ->label('Lot Réservé')
                            ->options(function (Get $get) {
                                $propertyId = $get('property_id');
                                if (!$propertyId) {
                                    return Unit::where('status', 'available')
                                        ->pluck('reference', 'id');
                                }
                                return Unit::where('property_id', $propertyId)
                                    ->where('status', 'available')
                                    ->pluck('reference', 'id');
                            })
                            ->live()
                            ->required()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $unit = Unit::find($state);
                                    if ($unit && $unit->price) {
                                        $set('total_amount', $unit->price);
                                        $set('deposit_amount', round($unit->price * 0.05, 2));
                                    }
                                }
                            })
                            ->helperText('Seuls les lots disponibles sont listés. La réservation verrouille immédiatement le lot.'),

                        Select::make('assigned_to')
                            ->label('Commercial Responsable')
                            ->relationship('assignedTo', 'name')
                            ->searchable()
                            ->preload(),

                        DatePicker::make('expires_at')
                            ->label('Expiration de l\'option')
                            ->helperText('Si non confirmée avant cette date, la réservation sera annulée automatiquement.'),
                    ])->columns(2),

                Section::make('Conditions Financières')
                    ->schema([
                        TextInput::make('total_amount')
                            ->label('Prix Total de Vente (FCFA)')
                            ->numeric()
                            ->required()
                            ->live(debounce: 500)
                            ->suffix('FCFA'),

                        TextInput::make('deposit_amount')
                            ->label('Dépôt de Garantie (FCFA)')
                            ->numeric()
                            ->suffix('FCFA'),

                        Textarea::make('notes')
                            ->label('Notes & Conditions particulières')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Échéancier VEFA')
                    ->description('Définissez les jalons de paiement. Par défaut, le barème VEFA standard sera appliqué à la création (5% / 30% / 35% / 25% / 5%).')
                    ->schema([
                        Repeater::make('schedules')
                            ->label('Jalons de paiement')
                            ->schema([
                                TextInput::make('label')
                                    ->label('Libellé du jalon')
                                    ->placeholder('Ex : Achèvement des fondations')
                                    ->required(),
                                DatePicker::make('due_date')
                                    ->label('Date d\'appel prévue'),
                                TextInput::make('percentage')
                                    ->label('% du prix total')
                                    ->numeric()
                                    ->suffix('%'),
                                TextInput::make('expected_amount')
                                    ->label('Montant attendu (FCFA)')
                                    ->numeric()
                                    ->suffix('FCFA'),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel('Ajouter un jalon')
                            ->helperText('Laissez vide pour utiliser le barème VEFA standard (5%/30%/35%/25%/5%).'),
                    ])->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Référence')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('contact.full_name')
                    ->label('Acheteur')
                    ->state(fn ($record) => trim("{$record->contact?->first_name} {$record->contact?->last_name}"))
                    ->searchable(),

                Tables\Columns\TextColumn::make('property.name')
                    ->label('Programme')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('unit.reference')
                    ->label('Lot')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Prix Total')
                    ->money('XOF')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_paid')
                    ->label('Encaissé')
                    ->state(fn (Reservation $record): string => number_format($record->total_paid, 0, ',', ' ') . ' FCFA')
                    ->color('success'),

                Tables\Columns\TextColumn::make('remaining_balance')
                    ->label('Solde Restant')
                    ->state(fn (Reservation $record): string => number_format($record->remaining_balance, 0, ',', ' ') . ' FCFA')
                    ->color(fn (Reservation $record): string => $record->remaining_balance > 0 ? 'warning' : 'success'),

                Tables\Columns\TextColumn::make('payment_progress_percentage')
                    ->label('Avancement Paiement')
                    ->state(fn (Reservation $record): string => $record->payment_progress_percentage . '%')
                    ->badge()
                    ->color(fn (Reservation $record): string => match (true) {
                        $record->payment_progress_percentage >= 100 => 'success',
                        $record->payment_progress_percentage >= 50 => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'option' => 'info',
                        'confirmed' => 'success',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'option' => 'Option en cours',
                        'confirmed' => 'Confirmée',
                        'completed' => 'Soldée',
                        'cancelled' => 'Annulée',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('reserved_at')
                    ->label('Date de réservation')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'option' => 'Option en cours',
                        'confirmed' => 'Confirmée',
                        'completed' => 'Soldée',
                        'cancelled' => 'Annulée',
                    ]),

                Tables\Filters\SelectFilter::make('property_id')
                    ->label('Programme')
                    ->relationship('property', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('record_payment')
                    ->label('Encaisser')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Reservation $record) => !in_array($record->status, ['cancelled', 'completed']))
                    ->form(function (Reservation $record) {
                        $scheduleOptions = $record->schedules()
                            ->whereIn('status', ['pending', 'partial', 'overdue'])
                            ->pluck('label', 'id')
                            ->toArray();

                        return [
                            Select::make('payment_schedule_id')
                                ->label('Appel de fonds / Jalon concerné')
                                ->options($scheduleOptions)
                                ->placeholder('Sélectionner un jalon (facultatif)'),

                            TextInput::make('amount')
                                ->label('Montant Encaissé (FCFA)')
                                ->numeric()
                                ->required()
                                ->suffix('FCFA'),

                            DatePicker::make('payment_date')
                                ->label('Date d\'encaissement')
                                ->default(now())
                                ->required(),

                            Select::make('payment_method')
                                ->label('Mode de Paiement')
                                ->options([
                                    'bank_transfer' => 'Virement Bancaire',
                                    'check' => 'Chèque',
                                    'cash' => 'Espèces',
                                    'wave_om' => 'Wave / Orange Money',
                                ])
                                ->required(),

                            TextInput::make('proof_reference')
                                ->label('Référence du justificatif')
                                ->placeholder('N° de virement, chèque, reçu…'),

                            Textarea::make('notes')
                                ->label('Notes (facultatif)'),
                        ];
                    })
                    ->action(function (Reservation $record, array $data) {
                        $service = new ReservationService();
                        $service->recordPayment($record, $data);

                        Notification::make()
                            ->title('Paiement enregistré !')
                            ->body('Solde restant : ' . number_format($record->fresh()->remaining_balance, 0, ',', ' ') . ' FCFA')
                            ->success()
                            ->send();
                    })
                    ->modalHeading('Enregistrer un Encaissement')
                    ->modalSubmitActionLabel('Valider l\'encaissement'),

                Tables\Actions\Action::make('send_reminder')
                    ->label('Relancer')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn (Reservation $record) => in_array($record->status, ['option', 'confirmed']))
                    ->form(function (Reservation $record) {
                        $schedules = $record->schedules()
                            ->whereIn('status', ['pending', 'partial', 'overdue'])
                            ->get();

                        $options = $schedules->mapWithKeys(function ($s) {
                            $due = $s->due_date ? ' (Échéance : ' . $s->due_date->format('d/m/Y') . ')' : '';
                            $rest = number_format($s->expected_amount - $s->paid_amount, 0, ',', ' ') . ' FCFA';
                            return [$s->id => "{$s->label} — Reste : {$rest}{$due}"];
                        })->toArray();

                        return [
                            Select::make('payment_schedule_id')
                                ->label('Échéance / Jalon à relancer')
                                ->options($options)
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(function ($state, Set $set, Reservation $record) {
                                    if ($state) {
                                        $schedule = PaymentSchedule::find($state);
                                        if ($schedule) {
                                            $service = app(PaymentReminderService::class);
                                            $trigger = ($schedule->due_date && $schedule->due_date->isPast()) ? 'overdue_j7' : 'preventive_j7';
                                            $set('trigger_type', $trigger);
                                            $set('message_content', $service->generateMessageText($schedule, $trigger, 'whatsapp'));
                                        }
                                    }
                                }),

                            Select::make('channel')
                                ->label('Canal')
                                ->options([
                                    'whatsapp' => 'WhatsApp',
                                    'email' => 'Email',
                                    'sms' => 'SMS',
                                ])
                                ->default('whatsapp')
                                ->required(),

                            Select::make('trigger_type')
                                ->label('Type de relance')
                                ->options([
                                    'preventive_j7' => 'J-7 Préventive',
                                    'due_today' => 'Jour J',
                                    'overdue_j7' => 'J+7 1ère relance amiable',
                                    'overdue_j15' => 'J+15 Mise en demeure',
                                    'manual' => 'Personnalisée',
                                ])
                                ->default('manual')
                                ->required(),

                            TextInput::make('recipient')
                                ->label('Destinataire')
                                ->default($record->contact?->phone ?? $record->contact?->email)
                                ->required(),

                            Textarea::make('message_content')
                                ->label('Message')
                                ->rows(6)
                                ->required(),
                        ];
                    })
                    ->action(function (Reservation $record, array $data) {
                        $schedule = PaymentSchedule::findOrFail($data['payment_schedule_id']);
                        $service = app(PaymentReminderService::class);
                        $service->sendReminder(
                            schedule: $schedule,
                            triggerType: $data['trigger_type'],
                            channel: $data['channel'],
                            customMessage: $data['message_content'],
                            userId: auth()->id()
                        );

                        Notification::make()
                            ->title('Relance client effectuée')
                            ->body("La relance pour le jalon {$schedule->label} a été consignée avec succès.")
                            ->success()
                            ->send();
                    })
                    ->modalHeading('Relancer l\'acquéreur')
                    ->modalSubmitActionLabel('Envoyer la relance'),

                Tables\Actions\Action::make('contract')
                    ->label(fn (Reservation $record) => $record->contract ? 'Dossier Contrat' : 'Générer Contrat VEFA')
                    ->icon(fn (Reservation $record) => $record->contract ? 'heroicon-o-document-text' : 'heroicon-o-document-plus')
                    ->color(fn (Reservation $record) => $record->contract ? 'info' : 'primary')
                    ->visible(fn (Reservation $record) => in_array($record->status, ['option', 'confirmed']))
                    ->action(function (Reservation $record, ContractService $service) {
                        if ($record->contract) {
                            Notification::make()
                                ->title('Contrat existant')
                                ->body("Réf: {$record->contract->reference} · Statut: {$record->contract->status}")
                                ->info()
                                ->send();
                            return;
                        }

                        $contract = $service->createContract($record, auth()->user());
                        Notification::make()
                            ->title('Contrat VEFA généré')
                            ->body("Contrat {$contract->reference} créé avec succès (v1).")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label('Annuler')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Reservation $record) => !in_array($record->status, ['cancelled', 'completed']))
                    ->form([
                        TextInput::make('cancellation_reason')
                            ->label('Motif d\'annulation')
                            ->required(),
                    ])
                    ->action(function (Reservation $record, array $data) {
                        $service = new ReservationService();
                        $service->cancelReservation($record, $data['cancellation_reason']);

                        Notification::make()
                            ->title('Réservation annulée')
                            ->body("Le lot {$record->unit?->reference} est à nouveau disponible.")
                            ->warning()
                            ->send();
                    })
                    ->modalHeading('Annuler la Réservation'),

                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReservations::route('/'),
            'create' => Pages\CreateReservation::route('/create'),
            'edit' => Pages\EditReservation::route('/{record}/edit'),
        ];
    }
}
