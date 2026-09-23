<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SequenceEnrollment extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'sequence_id',
        'current_step',
        'next_run_at',
        'status',
        'enrolled_at',
        'stopped_at',
        'stop_reason',
    ];

    protected $casts = [
        'next_run_at' => 'datetime',
        'enrolled_at' => 'datetime',
        'stopped_at' => 'datetime',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function sequence()
    {
        return $this->belongsTo(Sequence::class);
    }
}
