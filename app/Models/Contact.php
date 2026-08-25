<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'first_name',
        'last_name',
        'phone_e164',
        'email',
        'preferred_channel',
        'language',
        'country',
        'city',
        'is_diaspora',
        'property_type',
        'district',
        'budget_min',
        'budget_max',
        'decision_horizon',
        'purpose',
        'source_id',
        'sub_source',
        'referrer_id',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'landing_page',
        'assigned_to',
        'status',
        'potential_score',
        'q_replied_at',
        'q_project_at',
        'q_budget_at',
        'q_source_at',
        'qualified_at',
        'first_response_at',
        'first_response_minutes',
        'last_activity_at',
        'next_action_at',
        'consent_at',
        'consent_source',
    ];

    protected $casts = [
        'is_diaspora' => 'boolean',
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
        'q_replied_at' => 'datetime',
        'q_project_at' => 'datetime',
        'q_budget_at' => 'datetime',
        'q_source_at' => 'datetime',
        'qualified_at' => 'datetime',
        'first_response_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'next_action_at' => 'datetime',
        'consent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Contact $contact) {
            // Qualification calculée (4 conditions, chacune horodatée)
            if ($contact->q_replied_at && $contact->q_project_at && $contact->q_budget_at && $contact->q_source_at) {
                $contact->qualified_at = $contact->qualified_at ?? now();
            } else {
                $contact->qualified_at = null;
            }
        });
    }

    public function source()
    {
        return $this->belongsTo(Source::class);
    }

    public function referrer()
    {
        return $this->belongsTo(Referrer::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function statusHistories()
    {
        return $this->hasMany(StatusHistory::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }
}
