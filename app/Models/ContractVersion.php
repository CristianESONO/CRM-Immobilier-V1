<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractVersion extends Model
{
    use HasFactory, BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'contract_id',
        'version_number',
        'template_id',
        'snapshot_data',
        'rendered_content',
        'pdf_path',
        'pdf_size',
        'checksum',
        'change_reason',
        'created_by_user_id',
        'created_at',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'pdf_size' => 'integer',
        'snapshot_data' => 'array',
        'created_at' => 'datetime',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function template()
    {
        return $this->belongsTo(ContractTemplate::class, 'template_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
