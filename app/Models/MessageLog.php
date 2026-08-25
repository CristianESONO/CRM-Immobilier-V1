<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'message_log';

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'channel',
        'template',
        'provider_id',
        'status',
        'sent_at',
        'delivered_at',
        'error',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
