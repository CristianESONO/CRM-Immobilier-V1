<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentReminderResource\Pages;
use App\Models\PaymentReminder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentReminderResource extends Resource
{
    protected static ?string $model = PaymentReminder::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Ventes & Réservations';

    protected static ?string $modelLabel = 'Relance d’Échéance';

    protected static ?string $pluralModelLabel = 'Relances d’Échéances';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations de la Relance')
                    ->schema([
                        Forms\Components\TextInput::make('recipient')
                            ->label('Destinataire')
                            ->disabled(),

                        Forms\Components\TextInput::make('channel')
                            ->label('Canal')
                            ->disabled(),

                        Forms\Components\TextInput::make('trigger_type')
                            ->label('Déclencheur')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('sent_at')
                            ->label('Date & Heure d’envoi')
                            ->disabled(),

                        Forms\Components\TextInput::make('subject')
                            ->label('Objet du message')
                            ->columnSpanFull()
                            ->disabled(),

                        Forms\Components\Textarea::make('message_content')
                            ->label('Contenu du message envoyé')
                            ->rows(8)
                            ->columnSpanFull()
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Envoyé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('channel')
                    ->label('Canal')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'whatsapp' => 'success',
                        'email' => 'info',
                        'sms' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => strtoupper($state)),

                Tables\Columns\TextColumn::make('trigger_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'preventive_j7' => 'info',
                        'due_today' => 'warning',
                        'overdue_j7' => 'danger',
                        'overdue_j15' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'preventive_j7' => 'J-7 Préventive',
                        'due_today' => 'Jour J Échéance',
                        'overdue_j7' => 'J+7 Relance 1',
                        'overdue_j15' => 'J+15 Mise en demeure',
                        default => 'Manuelle',
                    }),

                Tables\Columns\TextColumn::make('contact.first_name')
                    ->label('Acquéreur')
                    ->state(fn ($record) => trim(
                        ($record->contact?->first_name ?? '') . ' ' .
                        ($record->contact?->last_name ?? '')
                    ))
                    ->searchable(),

                Tables\Columns\TextColumn::make('reservation.property.name')
                    ->label('Programme')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('reservation.unit.reference')
                    ->label('Lot')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('paymentSchedule.label')
                    ->label('Jalon VEFA')
                    ->limit(25),

                Tables\Columns\TextColumn::make('metadata.remaining_due')
                    ->label('Montant Restant')
                    ->state(fn ($record) => isset($record->metadata['remaining_due'])
                        ? number_format($record->metadata['remaining_due'], 0, ',', ' ') . ' FCFA'
                        : '—')
                    ->color('danger'),

                Tables\Columns\TextColumn::make('sentBy.name')
                    ->label('Initié par')
                    ->state(fn ($record) => $record->sentBy?->name ?? 'Système Automatique')
                    ->badge()
                    ->color(fn ($record) => $record->sent_by_user_id ? 'primary' : 'gray'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('sent_at', 'desc')
            ->filters([
                SelectFilter::make('channel')
                    ->label('Canal')
                    ->options([
                        'whatsapp' => 'WhatsApp',
                        'email' => 'Email',
                        'sms' => 'SMS',
                    ]),

                SelectFilter::make('trigger_type')
                    ->label('Type de relance')
                    ->options([
                        'preventive_j7' => 'J-7 Préventive',
                        'due_today' => 'Jour J',
                        'overdue_j7' => 'J+7 Relance 1',
                        'overdue_j15' => 'J+15 Critique',
                        'manual' => 'Manuelle',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Détail de la Relance Transmise'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentReminders::route('/'),
        ];
    }
}
