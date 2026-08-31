<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Filament\Resources\ContactResource;
use App\Models\Contact;
use App\Models\Source;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListContacts extends ListRecords
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('import_csv')
                ->label('Importer Contacts CSV')
                ->icon('heroicon-o-document-arrow-up')
                ->color('info')
                ->form([
                    Select::make('source_id')
                        ->label('Source Obligatoire pour cet Import')
                        ->options(fn () => Source::pluck('label', 'id'))
                        ->required(),
                    FileUpload::make('csv_file')
                        ->label('Fichier CSV')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->required()
                        ->storeFiles(false),
                ])
                ->action(function (array $data) {
                    $file = $data['csv_file'];
                    $sourceId = $data['source_id'];

                    if (!file_exists($file->getRealPath())) {
                        Notification::make()->title('Erreur fichier CSV')->danger()->send();
                        return;
                    }

                    $handle = fopen($file->getRealPath(), 'r');
                    $header = fgetcsv($handle, 1000, ',');
                    
                    $imported = 0;
                    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                        if (count($row) < 2) continue;

                        $firstName = $row[0] ?? null;
                        $lastName = $row[1] ?? null;
                        $phone = $row[2] ?? null;
                        $email = $row[3] ?? null;

                        Contact::create([
                            'source_id' => $sourceId,
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'phone_e164' => $phone ? '+' . ltrim($phone, '+') : null,
                            'email' => $email,
                            'q_source_at' => now(),
                            'consent_at' => now(),
                            'consent_source' => 'import_csv',
                        ]);
                        $imported++;
                    }
                    fclose($handle);

                    Notification::make()
                        ->title("Import réussi : {$imported} contacts créés.")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('export_tenant_data')
                ->label('Export Intégral (Conformité 2008-12)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): StreamedResponse {
                    $headers = [
                        'Content-Type' => 'text/csv',
                        'Content-Disposition' => 'attachment; filename="tenant_contacts_export_' . date('Y-m-d') . '.csv"',
                    ];

                    $callback = function () {
                        $file = fopen('php://output', 'w');
                        fputcsv($file, ['ID', 'Prénom', 'Nom', 'Téléphone', 'Email', 'Source', 'Statut', 'Qualifié Le', 'Créé Le']);

                        Contact::with('source')->chunk(100, function ($contacts) use ($file) {
                            foreach ($contacts as $c) {
                                fputcsv($file, [
                                    $c->id,
                                    $c->first_name,
                                    $c->last_name,
                                    $c->phone_e164,
                                    $c->email,
                                    $c->source?->label ?? '',
                                    $c->status,
                                    $c->qualified_at?->format('Y-m-d H:i') ?? 'Non',
                                    $c->created_at->format('Y-m-d H:i'),
                                ]);
                            }
                        });
                        fclose($file);
                    };

                    return response()->stream($callback, 200, $headers);
                }),

            Actions\Action::make('export_committee_report')
                ->label('Rapport de Comité (PDF)')
                ->icon('heroicon-o-printer')
                ->color('warning')
                ->url(fn () => route('reports.committee-pdf'))
                ->openUrlInNewTab(),
        ];
    }
}
