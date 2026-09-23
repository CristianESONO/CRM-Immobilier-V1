<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'trigger_event',
        'description',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function conditions()
    {
        return $this->hasMany(WorkflowCondition::class)->orderBy('order');
    }

    public function actions()
    {
        return $this->hasMany(WorkflowAction::class)->orderBy('order');
    }

    public function executions()
    {
        return $this->hasMany(WorkflowExecution::class);
    }
}
