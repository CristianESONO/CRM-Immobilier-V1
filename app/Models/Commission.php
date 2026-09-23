<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'referrer_id',
        'reservation_id',
        'contact_id',
        'rule_id',
        'rate_snapshot',
        'rule_name_snapshot',
        'sales_amount',
        'commission_amount',
        'status',
        'calculated_at',
        'validated_at',
        'payable_at',
        'paid_at',
        'paid_by_user_id',
        'payment_reference',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'sales_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'rate_snapshot' => 'decimal:2',
        'calculated_at' => 'datetime',
        'validated_at' => 'datetime',
        'payable_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function referrer()
    {
        return $this->belongsTo(Referrer::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function rule()
    {
        return $this->belongsTo(CommissionRule::class, 'rule_id');
    }

    public function paidByUser()
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function histories()
    {
        return $this->hasMany(CommissionHistory::class)->orderByDesc('changed_at');
    }
}
