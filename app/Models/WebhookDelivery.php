<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'event_type',
        'payload',
        'status_code',
        'response_body',
        'status',
        'attempts',
        'next_retry_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'status_code' => 'integer',
        'next_retry_at' => 'datetime',
    ];

    public function subscription()
    {
        return $this->belongsTo(WebhookSubscription::class, 'subscription_id');
    }
}
