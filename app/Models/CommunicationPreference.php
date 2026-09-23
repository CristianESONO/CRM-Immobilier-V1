<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunicationPreference extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'preferred_channel',
        'opt_in_transactional',
        'opt_in_marketing',
        'quiet_hours_start',
        'quiet_hours_end',
    ];

    protected $casts = [
        'opt_in_transactional' => 'boolean',
        'opt_in_marketing' => 'boolean',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
