<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractResource\Pages;
use App\Models\Contract;
use App\Models\ContractVersion;
use App\Services\Contracts\ContractService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Contrats & Documents';

    protected static ?string $modelLabel = 'Contrat VEFA';

    protected static ?string $pluralModelLabel = 'Contrats VEFA';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false; // Les contrats sont initiés depuis les réservations
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('contract_number')->disabled(),
                Forms\Components\TextInput::make('status')->disabled(),
                Forms\Components\TextInput::make('current_version')->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('contract_number')
                    ->label('N° Contrat')
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                Tables\Columns\TextColumn::make('reservation.reference')
                    ->label('Réservation')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('reservation.contact.first_name')
                    ->label('Acquéreur')
                    ->state(fn ($record) => trim(
                        ($record->reservation?->contact?->first_name ?? '') . ' ' .
                        ($record->reservation?->contact?->last_name ?? '')
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

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'generated' => 'info',
                        'pending_review' => 'warning',
                        'approved' => 'primary',
                        'sent' => 'secondary',
                        'signed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'draft' => 'Brouillon',
                        'generated' => 'Généré',
                        'pending_review' => 'En revue',
                        'approved' => 'Approuvé',
                        'sent' => 'Envoyé en signature',
                        'signed' => 'Signé',
                        'cancelled' => 'Annulé',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('current_version')
                    ->label('Version')
                    ->formatStateUsing(fn ($state) => "v{$state}")
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('signed_at')
                    ->label('Date Signature')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'generated' => 'Généré',
                        'pending_review' => 'En attente revue',
                        'approved' => 'Approuvé',
                        'sent' => 'En cours de signature',
                        'signed' => 'Signé',
                        'cancelled' => 'Annulé',
                    ]),
            ])
            ->actions([
                // 1. Consulter le document rendu
                Action::make('view_rendered')
                    ->label('Voir Contrat')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (Contract $record) => "Contrat {$record->contract_number} (v{$record->current_version})")
                    ->modalContent(function (Contract $record) {
                        $version = $record->latestVersion;
                        if (!$version) {
                            return new HtmlString('<p>Aucune version disponible.</p>');
                        }

                        $checksum = substr($version->checksum, 0, 16) . '…';
                        $header = "<div style='background:#f3f4f6; padding:10px; border-radius:6px; margin-bottom:15px; font-size:12px;'>
                            <strong>Version :</strong> v{$version->version_number} | 
                            <strong>Généré le :</strong> {$version->created_at->format('d/m/Y H:i')} | 
                            <strong>Empreinte SHA-256 :</strong> <code>{$checksum}</code>
                        </div>";

                        return new HtmlString($header . "<div style='border:1px solid #e5e7eb; padding:20px; border-radius:8px; background:#fff;'>" . $version->rendered_content . "</div>");
                    }),

                // Téléchargement sécurisé du PDF certifié
                Action::make('download_pdf')
                    ->label('Télécharger PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (Contract $record) => $record->latestVersion?->pdf_path !== null)
                    ->url(fn (Contract $record) => $record->latestVersion ? route('documents.contracts.download', ['contractVersion' => $record->latestVersion->id]) : '#')
                    ->openUrlInNewTab(),

                // 2. Générer une nouvelle version
                Action::make('generate_new_version')
                    ->label('Nouvelle Version')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (Contract $record) => !in_array($record->status, ['signed', 'cancelled'], true) && (!Auth::user()?->isObserver()))
                    ->form([
                        Forms\Components\TextInput::make('change_reason')
                            ->label('Motif de révision du contrat')
                            ->placeholder('Ex : Rectification orthographe acquéreur, ajustement échéancier')
                            ->required(),
                    ])
                    ->action(function (Contract $record, array $data) {
                        $service = new ContractService();
                        $version = $service->generateVersion($record, $data['change_reason'], null, Auth::id());

                        Notification::make()
                            ->title("Version v{$version->version_number} générée !")
                            ->body("Empreinte SHA-256 : " . substr($version->checksum, 0, 12) . "…")
                            ->success()
                            ->send();
                    }),

                // 3. Soumettre pour validation
                Action::make('submit_review')
                    ->label('Soumettre Revue')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn (Contract $record) => $record->status === 'generated' && (!Auth::user()?->isObserver()))
                    ->action(function (Contract $record) {
                        $service = new ContractService();
                        $service->submitForReview($record, Auth::id());

                        Notification::make()
                            ->title('Contrat soumis pour examen')
                            ->success()
                            ->send();
                    }),

                // 4. Approuver (Admin)
                Action::make('approve')
                    ->label('Approuver')
                    ->icon('heroicon-o-check-badge')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Approuver ce contrat pour signature')
                    ->visible(fn (Contract $record) => $record->status === 'pending_review' && (Auth::user()?->isAdmin() ?? true))
                    ->action(function (Contract $record) {
                        $service = new ContractService();
                        $service->approveContract($record, Auth::id());

                        Notification::make()
                            ->title('Contrat formellement approuvé !')
                            ->body('Le contrat est prêt pour envoi en signature.')
                            ->success()
                            ->send();
                    }),

                // 5. Envoyer en signature (Admin)
                Action::make('send_signature')
                    ->label('Envoyer Signature')
                    ->icon('heroicon-o-pencil-square')
                    ->color('secondary')
                    ->visible(fn (Contract $record) => $record->status === 'approved' && (Auth::user()?->isAdmin() ?? true))
                    ->action(function (Contract $record) {
                        $service = new ContractService();
                        $service->sendForSignature($record, null, Auth::id());

                        Notification::make()
                            ->title('Contrat transmis en signature électronique !')
                            ->success()
                            ->send();
                    }),

                // 6. Marquer Signé (Admin)
                Action::make('mark_signed')
                    ->label('Valider Signature')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Contract $record) => $record->status === 'sent' && (Auth::user()?->isAdmin() ?? true))
                    ->action(function (Contract $record) {
                        $service = new ContractService();
                        $service->recordSignedContract($record, null, Auth::id());

                        Notification::make()
                            ->title('Contrat validé comme SIGNÉ !')
                            ->body("La réservation {$record->reservation->reference} est passée à l'état confirmée.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContracts::route('/'),
        ];
    }
}
