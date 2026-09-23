<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionRule extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'rule_type',
        'rate',
        'min_volume',
        'max_volume',
        'is_active',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'min_volume' => 'decimal:2',
        'max_volume' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function commissions()
    {
        return $this->hasMany(Commission::class, 'rule_id');
    }
}
