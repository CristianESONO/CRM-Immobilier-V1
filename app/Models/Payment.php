<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'reservation_id',
        'payment_schedule_id',
        'reference',
        'amount',
        'payment_date',
        'payment_method',
        'proof_reference',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function schedule()
    {
        return $this->belongsTo(PaymentSchedule::class, 'payment_schedule_id');
    }
}
