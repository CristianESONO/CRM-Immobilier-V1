<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentRequirement extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'document_type',
        'target_buyer_type',
        'financing_type',
        'residence_type',
        'is_mandatory',
        'description',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
    ];

    /**
     * Vérifie si cette exigence s'applique au profil d'un contact et d'une réservation donnés.
     */
    public function matches(string $buyerType = 'individual', string $financing = 'cash', string $residence = 'resident'): bool
    {
        $buyerMatch = in_array($this->target_buyer_type, ['all', $buyerType], true);
        $financingMatch = in_array($this->financing_type, ['all', $financing], true);
        $residenceMatch = in_array($this->residence_type, ['all', $residence], true);

        return $buyerMatch && $financingMatch && $residenceMatch;
    }
}
