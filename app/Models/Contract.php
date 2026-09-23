<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'reservation_id',
        'contract_number',
        'contract_type',
        'status',
        'current_version',
        'created_by_user_id',
        'approved_by_user_id',
        'approved_at',
        'signed_at',
        'cancelled_at',
        'cancellation_reason',
        'signature_request_id',
    ];

    protected $casts = [
        'current_version' => 'integer',
        'approved_at' => 'datetime',
        'signed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function versions()
    {
        return $this->hasMany(ContractVersion::class)->orderByDesc('version_number');
    }

    public function latestVersion()
    {
        return $this->hasOne(ContractVersion::class)->latestOfMany('version_number');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
