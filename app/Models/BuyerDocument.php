<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuyerDocument extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'reservation_id',
        'document_type',
        'title',
        'file_path',
        'file_size',
        'mime_type',
        'status',
        'issued_at',
        'expires_at',
        'verified_by_user_id',
        'verified_at',
        'rejection_reason',
        'notes',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'issued_at' => 'date',
        'expires_at' => 'date',
        'verified_at' => 'datetime',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
