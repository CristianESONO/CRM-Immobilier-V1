<?php

namespace App\Services\Signature;

use App\Contracts\SignatureProviderInterface;
use App\Models\ContractVersion;
use Illuminate\Support\Str;

class MockSignatureProvider implements SignatureProviderInterface
{
    public function createSignatureRequest(ContractVersion $version): string
    {
        return 'SIG_REQ_' . Str::upper(Str::random(12));
    }

    public function getSignatureStatus(string $signatureRequestId): string
    {
        return 'signed';
    }

    public function downloadSignedDocument(string $signatureRequestId): ?string
    {
        return "contracts/signed/{$signatureRequestId}.pdf";
    }
}
