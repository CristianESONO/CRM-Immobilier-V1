<?php

namespace App\Services\Contracts;

use Illuminate\Support\Arr;

class TemplateRenderer
{
    /**
     * Mappages directs pour rétrocompatibilité et syntaxe simplifiée.
     */
    protected const DIRECT_MAPPINGS = [
        'contract.number' => 'contract_number',
        'reservation.reference' => 'reservation_reference',
        'payment_schedule_table' => 'payment_schedule_table',
    ];

    /**
     * Rend le contenu final du contrat en substituant les balises {{variable}}.
     */
    public function render(string $templateContent, array $snapshot): string
    {
        return preg_replace_callback('/\{\{\s*([\w\.]+)\s*\}\}/', function ($matches) use ($snapshot) {
            $key = $matches[1];

            // 1. Chercher dans les mappings directs
            if (isset(self::DIRECT_MAPPINGS[$key])) {
                $targetKey = self::DIRECT_MAPPINGS[$key];
                return Arr::get($snapshot, $targetKey, '');
            }

            // 2. Recherche standard par notation pointée (ex: buyer.full_name, unit.price_formatted)
            $val = Arr::get($snapshot, $key);
            if ($val !== null && is_scalar($val)) {
                return (string) $val;
            }

            // 3. Essayer avec suffixe _formatted pour les montants
            $formattedVal = Arr::get($snapshot, $key . '_formatted');
            if ($formattedVal !== null && is_scalar($formattedVal)) {
                return (string) $formattedVal;
            }

            return $matches[0]; // Laisser intact si non trouvé
        }, $templateContent);
    }
}
