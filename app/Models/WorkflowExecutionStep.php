<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowExecutionStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'execution_id',
        'workflow_action_id',
        'action_type',
        'status',
        'output',
        'error',
        'executed_at',
    ];

    protected $casts = [
        'output' => 'array',
        'executed_at' => 'datetime',
    ];

    public function execution()
    {
        return $this->belongsTo(WorkflowExecution::class, 'execution_id');
    }

    public function action()
    {
        return $this->belongsTo(WorkflowAction::class, 'workflow_action_id');
    }
}
