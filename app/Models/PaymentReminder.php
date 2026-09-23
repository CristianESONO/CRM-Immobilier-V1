<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentReminder extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'idempotency_key',
        'payment_schedule_id',
        'reservation_id',
        'contact_id',
        'sent_by_user_id',
        'trigger_type',
        'channel',
        'status',
        'retry_count',
        'last_attempt_at',
        'error_message',
        'recipient',
        'subject',
        'message_content',
        'sent_at',
        'metadata',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'retry_count' => 'integer',
        'metadata' => 'array',
    ];

    public function paymentSchedule()
    {
        return $this->belongsTo(PaymentSchedule::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }
}
