<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentScheduleResource\Pages;
use App\Models\PaymentSchedule;
use Carbon\Carbon;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentScheduleResource extends Resource
{
    protected static ?string $model = PaymentSchedule::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Ventes & Réservations';

    protected static ?string $navigationLabel = 'Échéances';

    protected static ?string $modelLabel = 'Échéance';

    protected static ?string $pluralModelLabel = 'Échéances';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reservation.reference')
                    ->label('Réservation')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('reservation.contact.first_name')
                    ->label('Acquéreur')
                    ->state(fn (PaymentSchedule $record) => trim(
                        ($record->reservation?->contact?->first_name ?? '') . ' ' .
                        ($record->reservation?->contact?->last_name ?? '')
                    )),

                Tables\Columns\TextColumn::make('label')
                    ->label('Jalon')
                    ->searchable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Échéance')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expected_amount')
                    ->label('Attendu')
                    ->state(fn (PaymentSchedule $record) => number_format((float) $record->expected_amount, 0, ',', ' ') . ' FCFA'),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Encaissé')
                    ->state(fn (PaymentSchedule $record) => number_format((float) $record->paid_amount, 0, ',', ' ') . ' FCFA')
                    ->color('success'),

                Tables\Columns\TextColumn::make('remaining')
                    ->label('Reste dû')
                    ->state(fn (PaymentSchedule $record) => number_format($record->remainingAmount(), 0, ',', ' ') . ' FCFA')
                    ->color('warning')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'partial' => 'info',
                        'overdue' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('due_date')
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'pending' => 'À venir',
                        'partial' => 'Partiel',
                        'overdue' => 'Échu',
                        'paid' => 'Soldé',
                    ]),

                SelectFilter::make('drilldown')
                    ->label('Vue analytique')
                    ->options([
                        'overdue' => 'Échéances échues',
                        'overdue_30_plus' => 'Créances > 30 jours',
                        'due_within_30_days' => 'Échéance ≤ 30 jours',
                        'current' => 'À jour',
                        'aging_1_7' => 'Retard 1–7 jours',
                        'aging_8_30' => 'Retard 8–30 jours',
                        'aging_31_60' => 'Retard 31–60 jours',
                        'aging_61_90' => 'Retard 61–90 jours',
                        'aging_over_90' => 'Retard > 90 jours',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return self::applyDrilldown($query, $data['value'] ?? null);
                    }),
            ])
            ->actions([]);
    }

    public static function applyDrilldown(Builder $query, ?string $value): Builder
    {
        if (blank($value)) {
            return $query;
        }

        $today = Carbon::today()->toDateString();
        $in30 = Carbon::today()->addDays(30)->toDateString();
        $open = fn (Builder $q) => $q->whereColumn('paid_amount', '<', 'expected_amount')->whereNotNull('due_date');

        return match ($value) {
            'overdue' => $open($query)->whereDate('due_date', '<', $today),
            'overdue_30_plus' => $open($query)->whereDate('due_date', '<=', Carbon::today()->subDays(31)->toDateString()),
            'due_within_30_days' => $open($query)->whereDate('due_date', '<=', $in30),
            'current' => $open($query)->whereDate('due_date', '>=', $today),
            'aging_1_7' => $open($query)
                ->whereDate('due_date', '>=', Carbon::today()->subDays(7)->toDateString())
                ->whereDate('due_date', '<', $today),
            'aging_8_30' => $open($query)
                ->whereDate('due_date', '>=', Carbon::today()->subDays(30)->toDateString())
                ->whereDate('due_date', '<=', Carbon::today()->subDays(8)->toDateString()),
            'aging_31_60' => $open($query)
                ->whereDate('due_date', '>=', Carbon::today()->subDays(60)->toDateString())
                ->whereDate('due_date', '<=', Carbon::today()->subDays(31)->toDateString()),
            'aging_61_90' => $open($query)
                ->whereDate('due_date', '>=', Carbon::today()->subDays(90)->toDateString())
                ->whereDate('due_date', '<=', Carbon::today()->subDays(61)->toDateString()),
            'aging_over_90' => $open($query)->whereDate('due_date', '<=', Carbon::today()->subDays(91)->toDateString()),
            default => $query,
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentSchedules::route('/'),
        ];
    }
}
