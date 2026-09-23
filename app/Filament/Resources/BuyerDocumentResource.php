<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BuyerDocumentResource\Pages;
use App\Models\BuyerDocument;
use App\Services\Documents\BuyerDocumentService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class BuyerDocumentResource extends Resource
{
    protected static ?string $model = BuyerDocument::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Contrats & Juridique';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Pièce KYC Acquéreur';

    protected static ?string $pluralModelLabel = 'Dossiers KYC Acquéreurs';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)->schema([
                    Select::make('contact_id')
                        ->label('Acquéreur / Contact')
                        ->relationship('contact', 'first_name')
                        ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->first_name} {$record->last_name} ({$record->email})")
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('reservation_id')
                        ->label('Réservation associée (optionnel)')
                        ->relationship('reservation', 'reference_code')
                        ->searchable()
                        ->preload(),

                    Select::make('document_type')
                        ->label('Type de pièce')
                        ->options([
                            'cni' => 'Pièce d\'identité (CNI / Passeport)',
                            'proof_of_address' => 'Justificatif de domicile (< 3 mois)',
                            'tax_notice' => 'Avis d\'imposition',
                            'bank_statement' => 'Relevés bancaires',
                            'loan_agreement' => 'Accord de principe bancaire / Attestation de prêt',
                            'other' => 'Autre justificatif',
                        ])
                        ->required(),

                    TextInput::make('title')
                        ->label('Intitulé de la pièce')
                        ->placeholder('ex: CNI Recto-Verso M. Dupont')
                        ->required(),

                    FileUpload::make('file_path')
                        ->label('Fichier')
                        ->disk('public')
                        ->directory('buyer_documents')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(10240)
                        ->required(),

                    Select::make('status')
                        ->label('Statut de conformité')
                        ->options([
                            'pending' => 'En attente de vérification',
                            'verified' => 'Validé conforme',
                            'rejected' => 'Rejeté / Invalide',
                        ])
                        ->default('pending')
                        ->required(),

                    Textarea::make('rejection_reason')
                        ->label('Motif de rejet (si rejeté)')
                        ->columnSpanFull()
                        ->visible(fn ($get) => $get('status') === 'rejected'),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('contact.full_name')
                    ->label('Acquéreur')
                    ->getStateUsing(fn ($record) => $record->contact ? "{$record->contact->first_name} {$record->contact->last_name}" : '-')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('document_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cni' => 'Identité (CNI)',
                        'proof_of_address' => 'Domicile',
                        'tax_notice' => 'Avis Impôts',
                        'bank_statement' => 'Relevés banque',
                        'loan_agreement' => 'Accord Prêt',
                        default => 'Autre',
                    }),

                Tables\Columns\TextColumn::make('title')
                    ->label('Intitulé')
                    ->limit(25),

                Tables\Columns\TextColumn::make('reservation.reference_code')
                    ->label('Réservation')
                    ->placeholder('-')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'À vérifier',
                        'verified' => 'Validé',
                        'rejected' => 'Rejeté',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'verified' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('verifiedBy.name')
                    ->label('Vérifié par')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Déposé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'pending' => 'En attente',
                        'verified' => 'Validé',
                        'rejected' => 'Rejeté',
                    ]),
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Type de pièce')
                    ->options([
                        'cni' => 'Identité',
                        'proof_of_address' => 'Domicile',
                        'tax_notice' => 'Avis Impôts',
                        'bank_statement' => 'Banque',
                        'loan_agreement' => 'Accord Prêt',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Télécharger')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->url(fn (BuyerDocument $record) => route('documents.kyc.download', ['buyerDocument' => $record->id]))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('verify')
                    ->label('Valider')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (BuyerDocument $record) => $record->status !== 'verified' && Auth::user()?->role !== 'observer')
                    ->requiresConfirmation()
                    ->action(function (BuyerDocument $record, BuyerDocumentService $service) {
                        $service->verifyDocument($record, Auth::user());
                        Notification::make()
                            ->title('Pièce validée avec succès')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Rejeter')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (BuyerDocument $record) => $record->status !== 'rejected' && Auth::user()?->role !== 'observer')
                    ->form([
                        Textarea::make('reason')
                            ->label('Motif de rejet (ex: document illisible, date expirée)')
                            ->required(),
                    ])
                    ->action(function (BuyerDocument $record, array $data, BuyerDocumentService $service) {
                        $service->rejectDocument($record, $data['reason'], Auth::user());
                        Notification::make()
                            ->title('Pièce rejetée')
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBuyerDocuments::route('/'),
        ];
    }
}
