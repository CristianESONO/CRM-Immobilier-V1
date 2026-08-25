<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactResource\Pages;
use App\Models\Contact;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'CRM & Prospects';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informations Personnelles')
                    ->schema([
                        TextInput::make('first_name')
                            ->label('Prénom')
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->label('Nom')
                            ->maxLength(255),
                        TextInput::make('phone_e164')
                            ->label('Téléphone (E.164)')
                            ->tel()
                            ->maxLength(50),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        Select::make('preferred_channel')
                            ->label('Canal Préféré')
                            ->options([
                                'whatsapp' => 'WhatsApp',
                                'phone' => 'Téléphone',
                                'email' => 'Email',
                                'sms' => 'SMS',
                            ])
                            ->default('whatsapp'),
                        Toggle::make('is_diaspora')
                            ->label('Client Diaspora'),
                    ])->columns(2),

                Section::make('Source Traçable (Obligatoire)')
                    ->schema([
                        Select::make('source_id')
                            ->label('Source Obligatoire')
                            ->relationship('source', 'label')
                            ->required(),
                        TextInput::make('sub_source')
                            ->label('Sous-source (Détail)'),
                        TextInput::make('utm_source')
                            ->label('UTM Source'),
                        TextInput::make('utm_campaign')
                            ->label('UTM Campaign'),
                    ])->columns(2),

                Section::make('Critères de Recherche')
                    ->schema([
                        TextInput::make('property_type')
                            ->label('Type de Bien'),
                        TextInput::make('district')
                            ->label('Quartier / Zone'),
                        TextInput::make('budget_min')
                            ->label('Budget Min (FCFA)')
                            ->numeric(),
                        TextInput::make('budget_max')
                            ->label('Budget Max (FCFA)')
                            ->numeric(),
                    ])->columns(2),

                Section::make('Statut Pipeline & Suivi Commercial')
                    ->schema([
                        Select::make('status')
                            ->label('Statut du Prospect (Pipeline)')
                            ->options([
                                'nouveau' => '1. Nouveau',
                                'contacte' => '2. Contacté',
                                'qualifie' => '3. Qualifié',
                                'rdv_planifie' => '4. RDV Planifié',
                                'visite_planifiee' => '5. Visite Planifiée',
                                'visite_realisee' => '6. Visite Réalisée',
                                'proposition' => '7. Proposition (Offre / Devis)',
                                'gagne' => '8. Gagné (Vente Signée)',
                                'perdu' => '9. Perdu / Annulé',
                            ])
                            ->default('nouveau')
                            ->required(),
                        Select::make('assigned_to')
                            ->label('Commercial Assigné')
                            ->relationship('assignedTo', 'name'),
                        DateTimePicker::make('next_action_at')
                            ->label('Date Prochaine Action / Relance'),
                    ])->columns(3),

                Section::make('Qualification Derivée (Lecture seule)')
                    ->schema([
                        DateTimePicker::make('q_replied_at')
                            ->label('1. A répondu'),
                        DateTimePicker::make('q_project_at')
                            ->label('2. Projet confirmé'),
                        DateTimePicker::make('q_budget_at')
                            ->label('3. Budget confirmé'),
                        DateTimePicker::make('q_source_at')
                            ->label('4. Source vérifiée'),
                        DateTimePicker::make('qualified_at')
                            ->label('Statut Qualifié (Calculé)')
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('first_name')->label('Prénom')->searchable(),
                Tables\Columns\TextColumn::make('last_name')->label('Nom')->searchable(),
                Tables\Columns\TextColumn::make('phone_e164')->label('Téléphone')->searchable(),
                Tables\Columns\TextColumn::make('source.label')->label('Source'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Statut Pipeline')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'nouveau' => 'info',
                        'qualifie' => 'success',
                        'gagne' => 'success',
                        'perdu' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\IconColumn::make('qualified_at')
                    ->label('Qualifié ?')
                    ->boolean(fn ($record) => !is_null($record->qualified_at)),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'nouveau' => 'Nouveau',
                        'contacte' => 'Contacté',
                        'qualifie' => 'Qualifié',
                        'gagne' => 'Gagné',
                        'perdu' => 'Perdu',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContacts::route('/'),
            'create' => Pages\CreateContact::route('/create'),
            'edit' => Pages\EditContact::route('/{record}/edit'),
        ];
    }
}
