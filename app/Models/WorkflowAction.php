<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'action_type',
        'config',
        'delay_seconds',
        'order',
    ];

    protected $casts = [
        'config' => 'array',
        'delay_seconds' => 'integer',
        'order' => 'integer',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }
}
