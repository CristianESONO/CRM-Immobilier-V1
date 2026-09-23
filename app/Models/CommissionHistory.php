<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'commission_id',
        'from_status',
        'to_status',
        'user_id',
        'reason',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function commission()
    {
        return $this->belongsTo(Commission::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
