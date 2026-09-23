<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractTemplate extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'contract_type',
        'version',
        'content',
        'available_variables',
        'is_active',
    ];

    protected $casts = [
        'version' => 'integer',
        'available_variables' => 'array',
        'is_active' => 'boolean',
    ];

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function versions()
    {
        return $this->hasMany(ContractVersion::class, 'template_id');
    }
}
