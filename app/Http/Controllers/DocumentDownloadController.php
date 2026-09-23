<?php

namespace App\Http\Controllers;

use App\Models\BuyerDocument;
use App\Models\ContractVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    /**
     * Téléchargement sécurisé d'une version de contrat archivée.
     */
    public function downloadContractVersion(Request $request, int|string $contractVersion): StreamedResponse
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Authentification requise.');
        }

        $version = ContractVersion::withoutGlobalScopes()->find($contractVersion);
        if (!$version) {
            abort(404, 'Version de contrat introuvable.');
        }

        // Vérification de politique d'accès tenant et profil via ContractPolicy
        if (!Gate::forUser($user)->allows('view', $version->contract)) {
            abort(403, 'Accès interdit à ce document contractuel.');
        }

        $pdfPath = $version->pdf_path;
        if (!$pdfPath || !Storage::disk('local')->exists($pdfPath)) {
            abort(404, 'Fichier PDF contractuel introuvable.');
        }

        $filename = sprintf(
            'Contrat_%s_v%d.pdf',
            $version->contract->contract_number,
            $version->version_number
        );

        return Storage::disk('local')->download($pdfPath, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Téléchargement sécurisé d'une pièce justificative acquéreur (KYC).
     */
    public function downloadBuyerDocument(Request $request, int|string $buyerDocument): StreamedResponse
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Authentification requise.');
        }

        $doc = BuyerDocument::withoutGlobalScopes()->find($buyerDocument);
        if (!$doc) {
            abort(404, 'Pièce justificative introuvable.');
        }

        // Vérification de politique d'accès tenant et profil via BuyerDocumentPolicy
        if (!Gate::forUser($user)->allows('view', $doc)) {
            abort(403, 'Accès interdit à ce document justificatif.');
        }

        $filePath = $doc->file_path;
        if (!$filePath || !Storage::disk('local')->exists($filePath)) {
            abort(404, 'Pièce justificative introuvable sur le stockage sécurisé.');
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'pdf';
        $safeTitle = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $doc->title);
        $filename = "{$safeTitle}.{$extension}";

        return Storage::disk('local')->download($filePath, $filename);
    }
}
