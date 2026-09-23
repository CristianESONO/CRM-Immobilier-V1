<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkflowExecutionResource\Pages;
use App\Models\WorkflowExecution;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WorkflowExecutionResource extends Resource
{
    protected static ?string $model = WorkflowExecution::class;

    protected static ?string $navigationIcon = 'heroicon-o-command-line';

    protected static ?string $navigationGroup = 'Gouvernance & Conformité';

    protected static ?string $navigationLabel = 'Journal des Exécutions Workflows';

    protected static ?string $modelLabel = 'Exécution Workflow';

    protected static ?string $pluralModelLabel = 'Exécutions Workflows';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('workflow.name')
                    ->label('Workflow')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('trigger_event')
                    ->label('Déclencheur')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'running' => 'warning',
                        'pending' => 'info',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('entity_type')
                    ->label('Entité')
                    ->state(fn (WorkflowExecution $record) => class_basename($record->entity_type) . " #{$record->entity_id}"),

                Tables\Columns\TextColumn::make('steps_count')
                    ->label('Étapes')
                    ->counts('steps'),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('Début')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('finished_at')
                    ->label('Fin')
                    ->dateTime('d/m/Y H:i:s')
                    ->placeholder('En cours'),
            ])
            ->defaultSort('started_at', 'desc')
            ->actions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkflowExecutions::route('/'),
        ];
    }
}
