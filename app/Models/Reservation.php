<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'reference',
        'opportunity_id',
        'contact_id',
        'property_id',
        'unit_id',
        'assigned_to',
        'status',
        'total_amount',
        'deposit_amount',
        'reserved_at',
        'expires_at',
        'cancelled_at',
        'cancellation_reason',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'reserved_at' => 'datetime',
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function schedules()
    {
        return $this->hasMany(PaymentSchedule::class)->orderBy('due_date');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class)->orderByDesc('payment_date');
    }

    public function reminders()
    {
        return $this->hasMany(PaymentReminder::class)->orderByDesc('sent_at');
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class)->orderByDesc('requested_at');
    }

    /**
     * Total amount actually collected from validated payments.
     */
    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->where('status', 'validated')->sum('amount');
    }

    /**
     * Remaining amount to be collected.
     */
    public function getRemainingBalanceAttribute(): float
    {
        return max(0, (float) $this->total_amount - $this->total_paid);
    }

    /**
     * Percentage of the total amount already collected.
     */
    public function getPaymentProgressPercentageAttribute(): float
    {
        if ($this->total_amount <= 0) {
            return 0;
        }

        return round(($this->total_paid / (float) $this->total_amount) * 100, 1);
    }

    public function contract()
    {
        return $this->hasOne(Contract::class);
    }

    public function buyerDocuments()
    {
        return $this->hasMany(BuyerDocument::class);
    }
}

