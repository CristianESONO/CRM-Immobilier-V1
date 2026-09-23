<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiKeyResource\Pages;
use App\Models\ApiKey;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationGroup = 'Intégrations & Webhooks';
    protected static ?string $navigationLabel = 'Clés d\'API';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nom du partenaire / système')
                    ->required(),
                Forms\Components\TagsInput::make('scopes')
                    ->label('Scopes (ex: leads:read, properties:read, *)')
                    ->required(),
                Forms\Components\DateTimePicker::make('expires_at')
                    ->label('Date d\'expiration'),
                Forms\Components\DateTimePicker::make('revoked_at')
                    ->label('Date de révocation (Révokation immédiate)'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nom')->searchable(),
                Tables\Columns\TagsColumn::make('scopes')->label('Scopes'),
                Tables\Columns\TextColumn::make('last_used_at')->label('Dernière utilisation')->dateTime(),
                Tables\Columns\TextColumn::make('expires_at')->label('Expire le')->dateTime(),
                Tables\Columns\TextColumn::make('revoked_at')->label('Révoquée le')->dateTime(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApiKeys::route('/'),
            'create' => Pages\CreateApiKey::route('/create'),
            'edit' => Pages\EditApiKey::route('/{record}/edit'),
        ];
    }
}
