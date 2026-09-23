<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowExecution extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'workflow_id',
        'trigger_event',
        'entity_type',
        'entity_id',
        'status',
        'context',
        'error_message',
        'started_at',
        'finished_at',
        'retry_count',
    ];

    protected $casts = [
        'context' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function steps()
    {
        return $this->hasMany(WorkflowExecutionStep::class, 'execution_id');
    }
}
