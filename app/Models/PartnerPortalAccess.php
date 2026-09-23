<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerPortalAccess extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'referrer_id',
        'email',
        'password_hash',
        'portal_token',
        'portal_token_hash',
        'commission_rate',
        'last_login_at',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'last_login_at' => 'datetime',
    ];

    public function referrer()
    {
        return $this->belongsTo(Referrer::class);
    }

    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}
