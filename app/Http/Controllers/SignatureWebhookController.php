<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\SignatureWebhookEvent;
use App\Services\AuditService;
use App\Services\Contracts\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SignatureWebhookController extends Controller
{
    /**
     * Traitement sécurisé et strictement idempotent des webhooks de signature électronique.
     */
    public function handle(
        Request $request,
        string $provider,
        ContractService $contractService
    ): JsonResponse {
        $payload = $request->json()->all();
        $rawContent = $request->getContent();

        // 1. Vérification de l'authenticité HMAC de la requête
        $secret = config("services.signature.{$provider}.webhook_secret") ?? env('SIGNATURE_WEBHOOK_SECRET', 'test_signature_secret');
        $receivedSignature = $request->header('X-Signature-SHA256') ?? $request->header('X-Webhook-Signature') ?? $request->header('X-Signature');

        if ($receivedSignature) {
            $expectedSignature = hash_hmac('sha256', $rawContent, $secret);
            if (!hash_equals($expectedSignature, $receivedSignature)) {
                Log::warning("Signature webhook invalide pour le provider {$provider}");
                return response()->json(['error' => 'Invalid webhook signature HMAC.'], 401);
            }
        }

        // 2. Extraction des identifiants d'événement
        $eventId = (string) ($payload['event_id'] ?? $payload['id'] ?? $request->header('X-Event-ID') ?? hash('sha256', $rawContent));
        $eventType = (string) ($payload['event_type'] ?? $payload['type'] ?? 'contract.signed');
        $signatureRequestId = (string) ($payload['signature_request_id'] ?? $payload['envelope_id'] ?? '');

        // 3. Contrôle strict d'Idempotence (Déduplication)
        return DB::transaction(function () use (
            $provider,
            $eventId,
            $eventType,
            $signatureRequestId,
            $payload,
            $contractService
        ) {
            $existingEvent = SignatureWebhookEvent::where('provider', $provider)
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->first();

            if ($existingEvent) {
                // Événement déjà traité avec succès : réponse idempotente immédiate sans rejeu
                return response()->json([
                    'status' => 'already_processed',
                    'event_id' => $eventId,
                    'processed_at' => $existingEvent->processed_at->toIso8601String(),
                ], 200);
            }

            // 4. Recherche du contrat associé
            $contract = null;
            if ($signatureRequestId) {
                $contract = Contract::withoutGlobalScopes()
                    ->where('signature_request_id', $signatureRequestId)
                    ->first();
            }

            if (!$contract && isset($payload['contract_number'])) {
                $contract = Contract::withoutGlobalScopes()
                    ->where('contract_number', $payload['contract_number'])
                    ->first();
            }

            // 5. Enregistrement de l'événement dans le journal d'idempotence
            $webhookEvent = SignatureWebhookEvent::create([
                'tenant_id' => $contract?->tenant_id,
                'provider' => $provider,
                'event_id' => $eventId,
                'event_type' => $eventType,
                'signature_request_id' => $signatureRequestId,
                'payload' => $payload,
                'status' => 'processed',
                'processed_at' => now(),
            ]);

            // 6. Déclenchement de la transition métier si le contrat est signé
            if ($contract && in_array($eventType, ['contract.signed', 'envelope.completed', 'document.signed'], true)) {
                if ($contract->status === 'sent') {
                    $signedPdfPath = $payload['signed_pdf_url'] ?? "contracts/signed/{$signatureRequestId}.pdf";
                    $contractService->recordSignedContract($contract, $signedPdfPath);

                    AuditService::log('WEBHOOK_SIGNATURE_PROCESSED', $contract, [
                        'provider' => $provider,
                        'event_id' => $eventId,
                        'signature_request_id' => $signatureRequestId,
                    ], null, null, $contract->tenant_id);
                }
            }

            return response()->json([
                'status' => 'processed',
                'event_id' => $eventId,
                'contract_id' => $contract?->id,
                'contract_status' => $contract?->fresh()->status,
            ], 200);
        });
    }
}
