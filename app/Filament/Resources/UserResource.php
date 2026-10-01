<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Tenant;
use App\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Administration & Accès';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Utilisateur';

    protected static ?string $pluralModelLabel = 'Utilisateurs';

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return $user && in_array($user->role, ['super_admin', 'admin'], true);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        // Le super admin voit tout ; les admins de promoteur ne voient que leur tenant
        if ($user && $user->role !== 'super_admin' && $user->tenant_id) {
            $query->where('tenant_id', $user->tenant_id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Identité & Droits d’accès')
                    ->description('Renseignez les coordonnées et le niveau d’habilitation de l’utilisateur.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom complet')
                            ->placeholder('Ex: Amadou Diallo')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Adresse Email (Identifiant)')
                            ->placeholder('Ex: commercial@gretinvest.sn')
                            ->email()
                            ->required()
                            ->unique(User::class, 'email', ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('password')
                            ->label('Mot de passe')
                            ->password()
                            ->revealable()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(fn (string $context): string => $context === 'edit'
                                ? 'Laissez vide pour conserver le mot de passe actuel.'
                                : 'Mot de passe sécurisé pour l’accès au CRM.'),

                        Select::make('role')
                            ->label('Rôle & Permissions')
                            ->required()
                            ->options(function () {
                                $currentUser = auth()->user();
                                if ($currentUser?->role === 'super_admin') {
                                    return [
                                        'super_admin' => '👑 Super Admin LinkUp (Accès Total)',
                                        'admin' => '🏢 Admin Promoteur (Direction)',
                                        'commercial' => '💼 Commercial (Gestion Prospects)',
                                        'observer' => '👁️ Observateur (Lecture Seule)',
                                    ];
                                }

                                return [
                                    'admin' => '🏢 Admin Promoteur',
                                    'commercial' => '💼 Commercial',
                                    'observer' => '👁️ Observateur',
                                ];
                            })
                            ->default('commercial'),

                        Select::make('tenant_id')
                            ->label('Promoteur / Entité de rattachement')
                            ->options(fn () => Tenant::pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->visible(fn () => auth()->user()?->role === 'super_admin')
                            ->default(fn () => auth()->user()?->tenant_id)
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Compte Actif')
                            ->helperText('Désactiver ce compte empêchera immédiatement toute connexion au CRM.')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-m-envelope'),

                Tables\Columns\TextColumn::make('role')
                    ->label('Rôle')
                    ->badge()
                    ->colors([
                        'danger' => 'super_admin',
                        'warning' => 'admin',
                        'success' => 'commercial',
                        'gray' => 'observer',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'super_admin' => 'Super Admin',
                        'admin' => 'Admin Promoteur',
                        'commercial' => 'Commercial',
                        'observer' => 'Observateur',
                        default => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Promoteur')
                    ->badge()
                    ->color('info')
                    ->visible(fn () => auth()->user()?->role === 'super_admin')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Filtrer par rôle')
                    ->options([
                        'super_admin' => 'Super Admin',
                        'admin' => 'Admin Promoteur',
                        'commercial' => 'Commercial',
                        'observer' => 'Observateur',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Statut du compte')
                    ->placeholder('Tous')
                    ->trueLabel('Actifs uniquement')
                    ->falseLabel('Inactifs uniquement'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (User $record): bool => $record->id === auth()->id()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
