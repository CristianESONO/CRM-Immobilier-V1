<?php

namespace App\Contracts;

interface PdfGeneratorInterface
{
    /**
     * Compile un contenu HTML/texte en document binaire PDF standard.
     *
     * @param string $html Contenu HTML ou texte compilé du contrat
     * @param array $metadata Métadonnées du document (titre, auteur, sujet)
     * @return string Flux binaire du fichier PDF certifié
     */
    public function generatePdf(string $html, array $metadata = []): string;
}
