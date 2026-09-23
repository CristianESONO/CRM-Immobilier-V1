<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Referrer extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'organisation',
        'phone',
        'email',
        'status',
        'agreement_number',
        'agreement_signed_at',
        'bank_details',
    ];

    protected $casts = [
        'agreement_signed_at' => 'date',
        'bank_details' => 'array',
    ];

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class);
    }

    public function portalAccess()
    {
        return $this->hasOne(PartnerPortalAccess::class);
    }
}
