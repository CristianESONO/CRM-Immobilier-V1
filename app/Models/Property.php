<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'location',
        'property_type',
        'price_min',
        'price_max',
        'delivery_date',
        'status',
        'landing_page_url',
    ];

    protected $casts = [
        'price_min' => 'decimal:2',
        'price_max' => 'decimal:2',
        'delivery_date' => 'date',
    ];

    public function units()
    {
        return $this->hasMany(Unit::class);
    }
}
