<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentRequirementResource\Pages;
use App\Models\DocumentRequirement;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentRequirementResource extends Resource
{
    protected static ?string $model = DocumentRequirement::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Contrats & Juridique';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Exigence Documentaire KYC';

    protected static ?string $pluralModelLabel = 'Matrice KYC (Pièces Requises)';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label('Libellé de la pièce exigée')
                        ->required()
                        ->placeholder('ex: CNI / Passeport en cours de validité')
                        ->maxLength(255),

                    Select::make('document_type')
                        ->label('Type normalisé de pièce')
                        ->options([
                            'identity_card' => 'Pièce d\'identité (CNI / Passeport)',
                            'proof_of_residence' => 'Justificatif de domicile (< 3 mois)',
                            'company_statutes' => 'Statuts de la société / Extrait Kbis / RCCM',
                            'bank_statement' => 'Relevés bancaires / RIB',
                            'loan_agreement' => 'Accord de principe bancaire / Attestation de prêt',
                            'tax_notice' => 'Avis d\'imposition',
                            'other' => 'Autre pièce spécifique',
                        ])
                        ->required(),

                    Select::make('target_buyer_type')
                        ->label('Type d\'acquéreur ciblé')
                        ->options([
                            'all' => 'Tous les acquéreurs',
                            'individual' => 'Personnes Physiques (Particuliers)',
                            'company' => 'Personnes Morales (Sociétés / SCI)',
                        ])
                        ->default('all')
                        ->required(),

                    Select::make('financing_type')
                        ->label('Mode de financement')
                        ->options([
                            'all' => 'Tous modes',
                            'bank_loan' => 'Crédit / Prêt bancaire',
                            'cash' => 'Fonds propres / Comptant',
                        ])
                        ->default('all')
                        ->required(),

                    Select::make('residence_type')
                        ->label('Zone de résidence')
                        ->options([
                            'all' => 'Toutes zones',
                            'resident' => 'Résidents locaux',
                            'diaspora' => 'Diaspora / Non-résidents',
                        ])
                        ->default('all')
                        ->required(),

                    Toggle::make('is_mandatory')
                        ->label('Pièce obligatoire pour valider le dossier')
                        ->default(true),
                ]),

                Textarea::make('description')
                    ->label('Consignes & Instructions de conformité pour le client')
                    ->columnSpanFull()
                    ->placeholder('ex: Fournir un document en couleur, lisible et non tronqué.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Exigence')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('document_type')
                    ->label('Type de pièce')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('target_buyer_type')
                    ->label('Cible')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'individual' => 'Particulier',
                        'company' => 'Société',
                        default => 'Tous',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'company' => 'warning',
                        'individual' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('financing_type')
                    ->label('Financement')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'bank_loan' => 'Crédit',
                        'cash' => 'Comptant',
                        default => 'Tous',
                    }),

                Tables\Columns\IconColumn::make('is_mandatory')
                    ->label('Obligatoire')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentRequirements::route('/'),
            'create' => Pages\CreateDocumentRequirement::route('/create'),
            'edit' => Pages\EditDocumentRequirement::route('/{record}/edit'),
        ];
    }
}
