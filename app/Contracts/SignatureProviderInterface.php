<?php

namespace App\Contracts;

use App\Models\ContractVersion;

interface SignatureProviderInterface
{
    /**
     * Crée une demande de signature électronique pour une version de contrat.
     *
     * @return string Identifiant de la demande externe (DocuSign, Yousign, etc.)
     */
    public function createSignatureRequest(ContractVersion $version): string;

    /**
     * Récupère le statut actuel de signature auprès du fournisseur.
     *
     * @return string 'pending' | 'signed' | 'rejected'
     */
    public function getSignatureStatus(string $signatureRequestId): string;

    /**
     * Télécharge le document signé certifié.
     */
    public function downloadSignedDocument(string $signatureRequestId): ?string;
}
