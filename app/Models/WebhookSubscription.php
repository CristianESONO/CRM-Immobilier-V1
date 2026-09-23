<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookSubscription extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'url',
        'secret',
        'events',
        'is_active',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
    ];

    public function deliveries()
    {
        return $this->hasMany(WebhookDelivery::class, 'subscription_id');
    }

    public function subscribesTo(string $eventType): bool
    {
        if (!$this->is_active || empty($this->events)) {
            return false;
        }

        return in_array('*', $this->events, true) || in_array($eventType, $this->events, true);
    }
}
