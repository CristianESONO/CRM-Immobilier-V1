<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'property_id',
        'unit_id',
        'assigned_to',
        'title',
        'stage',
        'amount',
        'expected_close_at',
        'lost_reason',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expected_close_at' => 'date',
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

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
