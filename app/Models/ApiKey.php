<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'key_hash',
        'scopes',
        'expires_at',
        'revoked_at',
        'last_used_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null && $this->revoked_at->isPast();
    }

    public function hasScope(string $scope): bool
    {
        if (empty($this->scopes)) {
            return false;
        }

        if (in_array('*', $this->scopes, true) || in_array($scope, $this->scopes, true)) {
            return true;
        }

        return false;
    }
}
