<?php

namespace App\Services\Contracts;

use App\Contracts\PdfGeneratorInterface;
use App\Contracts\SignatureProviderInterface;
use App\Exceptions\StateTransitionException;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractVersion;
use App\Models\Reservation;
use App\Services\AuditService;
use App\Services\Pdf\SimplePdfGenerator;
use App\Services\Signature\MockSignatureProvider;
use App\Services\StateMachines\ContractStateMachine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContractService
{
    protected ContractDataBuilder $dataBuilder;
    protected TemplateRenderer $renderer;
    protected PdfGeneratorInterface $pdfGenerator;

    public function __construct(
        ?ContractDataBuilder $dataBuilder = null,
        ?TemplateRenderer $renderer = null,
        ?PdfGeneratorInterface $pdfGenerator = null
    ) {
        $this->dataBuilder = $dataBuilder ?? new ContractDataBuilder();
        $this->renderer = $renderer ?? new TemplateRenderer();
        $this->pdfGenerator = $pdfGenerator ?? new SimplePdfGenerator();
    }

    /**
     * Crée un contrat rattaché à une réservation et génère automatiquement sa première version v1.
     */
    public function createContract(
        Reservation $reservation,
        ?int $templateId = null,
        ?int $userId = null
    ): Contract {
        return DB::transaction(function () use ($reservation, $templateId, $userId) {
            $tenantId = $reservation->tenant_id;

            // Vérifier si un contrat existe déjà
            $existing = Contract::where('tenant_id', $tenantId)
                ->where('reservation_id', $reservation->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $count = Contract::where('tenant_id', $tenantId)->count() + 1;
            $contractNumber = sprintf('CTR-%s-%03d', now()->format('Y'), $count);

            $contract = Contract::create([
                'tenant_id' => $tenantId,
                'reservation_id' => $reservation->id,
                'contract_number' => $contractNumber,
                'contract_type' => 'reservation_vefa',
                'status' => ContractStateMachine::STATE_DRAFT,
                'current_version' => 1,
                'created_by_user_id' => $userId ?? Auth::id(),
            ]);

            // Génération immédiate de la version 1
            $this->generateVersion($contract, 'Version initiale du contrat', $templateId, $userId);

            AuditService::log('CONTRACT_CREATED', $contract, [
                'contract_number' => $contractNumber,
                'reservation_reference' => $reservation->reference,
            ], null, $userId, $tenantId);

            return $contract->fresh(['versions', 'reservation', 'latestVersion']);
        });
    }

    /**
     * Génère une nouvelle version immuable du contrat avec son snapshot et son checksum SHA-256.
     */
    public function generateVersion(
        Contract $contract,
        ?string $changeReason = null,
        ?int $templateId = null,
        ?int $userId = null
    ): ContractVersion {
        return DB::transaction(function () use ($contract, $changeReason, $templateId, $userId) {
            if (in_array($contract->status, [ContractStateMachine::STATE_SIGNED, ContractStateMachine::STATE_CANCELLED], true)) {
                throw new StateTransitionException("Impossible de générer une version pour un contrat dans l'état '{$contract->status}'.");
            }

            $reservation = $contract->reservation;
            $template = $templateId
                ? ContractTemplate::findOrFail($templateId)
                : $this->getDefaultTemplate($contract->tenant_id);

            // 1. Snapshot immuable
            $snapshot = $this->dataBuilder->buildSnapshot($reservation, $contract);

            // 2. Compilation du document final
            $rendered = $this->renderer->render($template->content, $snapshot);

            // 3. Numéro de version incrémenté
            $nextVersionNumber = ($contract->versions()->max('version_number') ?? 0) + 1;

            // 4. Génération du fichier binaire PDF certifié
            $pdfBinary = $this->pdfGenerator->generatePdf($rendered, [
                'title' => "Contrat {$contract->contract_number} v{$nextVersionNumber}",
                'author' => $contract->tenant?->name ?? 'CRM Immobilier Promoteur',
            ]);

            // 5. Stockage privé sécurisé avec UUID non prédictible
            $pdfUuid = Str::uuid()->toString();
            $pdfPath = "tenants/{$contract->tenant_id}/contracts/{$contract->id}/v{$nextVersionNumber}_{$pdfUuid}.pdf";
            Storage::disk('local')->put($pdfPath, $pdfBinary);

            // 6. Empreinte cryptographique d'intégrité calculée sur le fichier binaire archivé
            $checksum = hash('sha256', $pdfBinary);
            $pdfSize = strlen($pdfBinary);

            $version = ContractVersion::create([
                'tenant_id' => $contract->tenant_id,
                'contract_id' => $contract->id,
                'version_number' => $nextVersionNumber,
                'template_id' => $template->id,
                'snapshot_data' => $snapshot,
                'rendered_content' => $rendered,
                'pdf_path' => $pdfPath,
                'pdf_size' => $pdfSize,
                'checksum' => $checksum,
                'change_reason' => $changeReason ?? "Génération de la version {$nextVersionNumber}",
                'created_by_user_id' => $userId ?? Auth::id(),
                'created_at' => now(),
            ]);

            // Mise à jour de l'état du contrat
            ContractStateMachine::validateTransition($contract->status, ContractStateMachine::STATE_GENERATED);
            $contract->update([
                'current_version' => $nextVersionNumber,
                'status' => ContractStateMachine::STATE_GENERATED,
            ]);

            AuditService::log('CONTRACT_VERSION_GENERATED', $contract, [
                'version_number' => $nextVersionNumber,
                'checksum' => $checksum,
                'pdf_path' => $pdfPath,
                'change_reason' => $changeReason,
            ], null, $userId, $contract->tenant_id);

            return $version;
        });
    }

    /**
     * Soumet le contrat à l'examen de la direction.
     */
    public function submitForReview(Contract $contract, ?int $userId = null): Contract
    {
        return DB::transaction(function () use ($contract, $userId) {
            ContractStateMachine::validateTransition($contract->status, ContractStateMachine::STATE_PENDING_REVIEW);

            $contract->update(['status' => ContractStateMachine::STATE_PENDING_REVIEW]);

            AuditService::log('CONTRACT_SUBMITTED_FOR_REVIEW', $contract, [
                'version' => $contract->current_version,
            ], null, $userId);

            return $contract->fresh();
        });
    }

    /**
     * Approuve le contrat pour émission ou signature (Réservé admin / direction).
     */
    public function approveContract(Contract $contract, ?int $userId = null): Contract
    {
        return DB::transaction(function () use ($contract, $userId) {
            ContractStateMachine::validateTransition($contract->status, ContractStateMachine::STATE_APPROVED);

            $contract->update([
                'status' => ContractStateMachine::STATE_APPROVED,
                'approved_by_user_id' => $userId ?? Auth::id(),
                'approved_at' => now(),
            ]);

            AuditService::log('CONTRACT_APPROVED', $contract, [
                'approved_version' => $contract->current_version,
            ], null, $userId);

            return $contract->fresh();
        });
    }

    /**
     * Envoie le contrat vers la passerelle de signature électronique.
     */
    public function sendForSignature(
        Contract $contract,
        ?SignatureProviderInterface $provider = null,
        ?int $userId = null
    ): Contract {
        return DB::transaction(function () use ($contract, $provider, $userId) {
            ContractStateMachine::validateTransition($contract->status, ContractStateMachine::STATE_SENT);

            $provider = $provider ?? new MockSignatureProvider();
            $latestVersion = $contract->latestVersion;

            if (!$latestVersion) {
                throw new \Exception("Aucune version générée disponible pour signature.");
            }

            $sigReqId = $provider->createSignatureRequest($latestVersion);

            $contract->update([
                'status' => ContractStateMachine::STATE_SENT,
                'signature_request_id' => $sigReqId,
            ]);

            AuditService::log('CONTRACT_SENT_FOR_SIGNATURE', $contract, [
                'signature_request_id' => $sigReqId,
            ], null, $userId);

            return $contract->fresh();
        });
    }

    /**
     * Enregistre le contrat comme formellement signé et engageant.
     */
    public function recordSignedContract(
        Contract $contract,
        ?string $signedPdfPath = null,
        ?int $userId = null
    ): Contract {
        return DB::transaction(function () use ($contract, $signedPdfPath, $userId) {
            ContractStateMachine::validateTransition($contract->status, ContractStateMachine::STATE_SIGNED);

            $contract->update([
                'status' => ContractStateMachine::STATE_SIGNED,
                'signed_at' => now(),
            ]);

            // Si la réservation était en simple option, la signature contractuelle la confirme
            if ($contract->reservation->status === 'option') {
                $contract->reservation->update(['status' => 'confirmed']);
            }

            AuditService::log('CONTRACT_SIGNED', $contract, [
                'signed_version' => $contract->current_version,
                'signed_at' => now()->toDateTimeString(),
            ], null, $userId);

            \App\Events\ContractSigned::dispatch($contract->fresh(), $signedPdfPath, Auth::user());

            return $contract->fresh();
        });
    }

    /**
     * Annule le contrat avec justification.
     */
    public function cancelContract(Contract $contract, string $reason, ?int $userId = null): Contract
    {
        return DB::transaction(function () use ($contract, $reason, $userId) {
            ContractStateMachine::validateTransition($contract->status, ContractStateMachine::STATE_CANCELLED);

            $contract->update([
                'status' => ContractStateMachine::STATE_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            AuditService::log('CONTRACT_CANCELLED', $contract, [
                'reason' => $reason,
            ], null, $userId);

            return $contract->fresh();
        });
    }

    /**
     * Récupère ou instancie le modèle de contrat par défaut pour le promoteur.
     */
    public function getDefaultTemplate(int $tenantId): ContractTemplate
    {
        return ContractTemplate::firstOrCreate([
            'tenant_id' => $tenantId,
            'contract_type' => 'reservation_vefa',
        ], [
            'name' => 'Contrat de Réservation Préliminaire VEFA Standard',
            'version' => 1,
            'content' => <<<HTML
<h2>CONTRAT DE RÉSERVATION PRÉLIMINAIRE (VEFA)</h2>
<p><strong>N° de Contrat :</strong> {{contract.number}} | <strong>Réf. Réservation :</strong> {{reservation.reference}}</p>
<p><strong>Date d’établissement :</strong> {{generated_at}}</p>

<hr/>

<h3>ENTRE LES SOUSSIGNÉS :</h3>
<p><strong>Le Promoteur :</strong> {{promoter.name}}, ci-après désigné « Le Réservant ».</p>
<p><strong>ET :</strong></p>
<p><strong>L’Acquéreur :</strong> M./Mme {{buyer.full_name}}, demeurant à {{buyer.city}}, {{buyer.country}}.<br/>
Téléphone : {{buyer.phone}} | Email : {{buyer.email}}<br/>
ci-après désigné « Le Réservataire ».</p>

<hr/>

<h3>ARTICLE 1 — OBJET DU CONTRAT</h3>
<p>Le Réservant s’engage à réserver au profit du Réservataire le bien immobilier désigné ci-après :</p>
<ul>
    <li><strong>Programme :</strong> {{program.name}} situé à {{program.location}}</li>
    <li><strong>Lot n° :</strong> {{unit.reference}}</li>
    <li><strong>Surface indicative :</strong> {{unit.area}} m²</li>
</ul>

<h3>ARTICLE 2 — CONDITIONS FINANCIÈRES</h3>
<p>Le prix total de vente prévisionnel est fixé à la somme ferme et non révisable de <strong>{{financials.total_amount}}</strong>.</p>
<p>Le montant du dépôt de garantie s'élève à <strong>{{financials.deposit_amount}}</strong>.</p>

<h3>ARTICLE 3 — ÉCHÉANCIER DE PAIEMENT VEFA</h3>
<p>Les appels de fonds seront échelonnés selon le barème contractuel suivant :</p>
{{payment_schedule_table}}

<br/>
<table style="width:100%; margin-top:30px;">
    <tr>
        <td style="width:50%; text-align:center;">
            <strong>Pour le Promoteur</strong><br/><br/><br/>
            <em>Mention « Bon pour accord »</em>
        </td>
        <td style="width:50%; text-align:center;">
            <strong>Pour l’Acquéreur</strong><br/><br/><br/>
            <em>Mention « Lu et approuvé »</em>
        </td>
    </tr>
</table>
HTML,
            'available_variables' => [
                'contract.number', 'reservation.reference', 'generated_at',
                'promoter.name', 'buyer.full_name', 'buyer.phone', 'buyer.email',
                'program.name', 'program.location', 'unit.reference', 'unit.area',
                'financials.total_amount', 'financials.deposit_amount', 'payment_schedule_table',
            ],
            'is_active' => true,
        ]);
    }
}
