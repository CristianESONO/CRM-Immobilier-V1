<?php

namespace App\Filament\Pages;

use App\Services\OpenWaService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageWhatsAppSession extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'Paramètres & Intégrations';
    protected static ?string $title = 'WhatsApp Gateway (OpenWA - YokAlma)';

    protected static string $view = 'filament.pages.manage-whats-app-session';

    public array $sessionInfo = [];
    public ?string $qrCode = null;
    public string $testPhone = '';
    public string $testMessage = 'Bonjour depuis CRM Immobilier GRET INVEST!';
    public string $newSessionName = 'gretinvest';

    public function mount(OpenWaService $openWaService): void
    {
        $this->refreshSessionStatus($openWaService);
    }

    public function refreshSessionStatus(OpenWaService $openWaService): void
    {
        $this->sessionInfo = $openWaService->getSessionStatus();
        $status = $this->sessionInfo['status'] ?? 'unknown';

        if ($status === 'qr_ready') {
            $this->qrCode = $openWaService->getQrCode();
        } else {
            $this->qrCode = null;
        }
    }

    public function startSession(OpenWaService $openWaService): void
    {
        $openWaService->startSession();
        $this->refreshSessionStatus($openWaService);

        Notification::make()
            ->title('Ordre de démarrage envoyé à la session OpenWA.')
            ->info()
            ->send();
    }

    public function createNewSession(OpenWaService $openWaService): void
    {
        if (empty($this->newSessionName)) {
            Notification::make()->title('Veuillez saisir un nom de session (ex: gretinvest).')->warning()->send();
            return;
        }

        $result = $openWaService->createSession($this->newSessionName);

        if ($result['success'] ?? false) {
            $sessionId = $result['data']['id'] ?? '';
            Notification::make()
                ->title("Session '{$this->newSessionName}' créée avec succès (ID: {$sessionId}). N'oubliez pas d'ajouter cet ID dans votre .env (OPENWA_SESSION_ID).")
                ->success()
                ->send();

            $this->refreshSessionStatus($openWaService);
        } else {
            $msg = $result['data']['message'] ?? $result['error'] ?? 'Échec création session.';
            Notification::make()
                ->title("Création échouée : {$msg}")
                ->danger()
                ->send();
        }
    }

    public function sendTestMessage(OpenWaService $openWaService): void
    {
        if (empty($this->testPhone)) {
            Notification::make()->title('Veuillez saisir un numéro de téléphone.')->warning()->send();
            return;
        }

        $success = $openWaService->sendTextMessage($this->testPhone, $this->testMessage);

        if ($success) {
            Notification::make()
                ->title('Message de test envoyé avec succès via YokAlma WhatsApp!')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title("Échec de l'envoi du message de test.")
                ->danger()
                ->send();
        }
    }
}
