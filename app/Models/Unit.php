<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'property_id',
        'reference',
        'typology',
        'area',
        'price',
        'status',
        'marketed_at',
    ];

    protected $casts = [
        'area' => 'decimal:2',
        'price' => 'decimal:2',
        'marketed_at' => 'datetime',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }

    public function reservation()
    {
        return $this->hasOne(Reservation::class);
    }

    /**
     * Prix au m² individuel du lot.
     */
    public function getPricePerSqmAttribute(): float
    {
        $area = (float) $this->area;
        if ($area <= 0) {
            return 0.0;
        }

        return round((float) $this->price / $area, 2);
    }

    /**
     * Ancienneté du lot en jours depuis commercialisation.
     */
    public function getAgeInDaysAttribute(): int
    {
        $date = $this->marketed_at ?? $this->created_at ?? now();
        return max(0, (int) now()->diffInDays($date));
    }
}
