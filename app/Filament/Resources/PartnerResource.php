<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartnerResource\Pages;
use App\Models\Referrer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PartnerResource extends Resource
{
    protected static ?string $model = Referrer::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationGroup = 'Réseau Apporteurs';

    protected static ?string $navigationLabel = 'Partenaires & Apporteurs';

    protected static ?string $modelLabel = 'Partenaire Apporteur';

    protected static ?string $pluralModelLabel = 'Partenaires & Apporteurs';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nom du Partenaire / Agence')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('type')
                    ->label('Type de Partenaire')
                    ->options([
                        'agency' => 'Agence Immobilière',
                        'individual' => 'Apporteur Indépendant',
                        'partner' => 'Partenaire Stratégique',
                    ])
                    ->default('agency'),

                Forms\Components\Select::make('status')
                    ->label('Statut d\'Agrément')
                    ->options([
                        'active' => 'Actif (Agréé)',
                        'pending_approval' => 'En attente de validation',
                        'suspended' => 'Suspendu',
                    ])
                    ->default('active'),

                Forms\Components\TextInput::make('organisation')
                    ->label('Organisation / Raison Sociale'),

                Forms\Components\TextInput::make('agreement_number')
                    ->label('N° Convention de Partenariat'),

                Forms\Components\DatePicker::make('agreement_signed_at')
                    ->label('Date de Signature Convention'),

                Forms\Components\TextInput::make('email')
                    ->label('Email de Contact')
                    ->email(),

                Forms\Components\TextInput::make('phone')
                    ->label('Téléphone'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Partenaire')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Agrément')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending_approval' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('agreement_number')
                    ->label('Convention')
                    ->placeholder('Aucune'),

                Tables\Columns\TextColumn::make('contacts_count')
                    ->label('Leads Apportés')
                    ->counts('contacts'),

                Tables\Columns\TextColumn::make('commissions_count')
                    ->label('Commissions')
                    ->counts('commissions'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Inscrit le')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartners::route('/'),
        ];
    }
}
