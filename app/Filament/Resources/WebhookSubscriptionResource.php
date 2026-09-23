<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WebhookSubscriptionResource\Pages;
use App\Models\WebhookSubscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WebhookSubscriptionResource extends Resource
{
    protected static ?string $model = WebhookSubscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationGroup = 'Intégrations & Webhooks';
    protected static ?string $navigationLabel = 'Subscriptions Webhooks';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nom de l\'intégration')
                    ->required(),
                Forms\Components\TextInput::make('url')
                    ->label('URL de Destination Webhook')
                    ->url()
                    ->required(),
                Forms\Components\TextInput::make('secret')
                    ->label('Clé Secret HMAC SHA-256')
                    ->required(),
                Forms\Components\TagsInput::make('events')
                    ->label('Événements Subscrits (ex: ReservationCreated, *')
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Actif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nom')->searchable(),
                Tables\Columns\TextColumn::make('url')->label('URL Endpoint')->limit(30),
                Tables\Columns\TagsColumn::make('events')->label('Événements'),
                Tables\Columns\IconColumn::make('is_active')->label('Actif')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('Créé le')->dateTime(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebhookSubscriptions::route('/'),
            'create' => Pages\CreateWebhookSubscription::route('/create'),
            'edit' => Pages\EditWebhookSubscription::route('/{record}/edit'),
        ];
    }
}
