<?php

namespace App\Jobs;

use App\Models\WorkflowAction;
use App\Models\WorkflowExecution;
use App\Services\Workflows\WorkflowEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $executionId,
        public int $workflowActionId,
    ) {}

    public function handle(WorkflowEngine $engine): void
    {
        $execution = WorkflowExecution::find($this->executionId);
        $action = WorkflowAction::find($this->workflowActionId);

        if (!$execution || !$action) {
            return;
        }

        $targetClass = data_get($execution->context, 'target_class');
        $targetId = data_get($execution->context, 'target_id');
        $target = null;

        if ($targetClass && class_exists($targetClass) && $targetId) {
            $target = $targetClass::find($targetId);
        }

        $engine->executeActionStep($execution, $action, $target, $execution->tenant_id);
    }
}
