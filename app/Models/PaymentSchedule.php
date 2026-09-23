<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentSchedule extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'reservation_id',
        'label',
        'due_date',
        'percentage',
        'expected_amount',
        'paid_amount',
        'status',
    ];

    protected $casts = [
        'due_date' => 'date',
        'percentage' => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function reminders()
    {
        return $this->hasMany(PaymentReminder::class);
    }

    /**
     * Remaining due after amounts already recorded on the schedule line.
     * Source of truth for analytics: never the gross expected_amount.
     */
    public function remainingAmount(): float
    {
        return max(0.0, (float) $this->expected_amount - (float) $this->paid_amount);
    }

    /**
     * Recalculate status based on paid vs expected amounts.
     */
    public function refreshStatus(): void
    {
        $paid = (float) $this->payments()->where('status', 'validated')->sum('amount');
        $this->paid_amount = $paid;

        if ($paid >= (float) $this->expected_amount) {
            $this->status = 'paid';
        } elseif ($paid > 0) {
            $this->status = 'partial';
        } else {
            $this->status = ($this->due_date && $this->due_date->isPast()) ? 'overdue' : 'pending';
        }

        $this->save();
    }
}
