<?php

namespace App\Services\Workflows;

use App\Jobs\ExecuteWorkflowJob;
use App\Models\Workflow;
use App\Models\WorkflowAction;
use App\Models\WorkflowExecution;
use App\Models\WorkflowExecutionStep;
use Carbon\Carbon;
use Throwable;

class WorkflowEngine
{
    public function __construct(
        protected WorkflowConditionEvaluator $evaluator = new WorkflowConditionEvaluator(),
        protected WorkflowActionExecutor $executor = new WorkflowActionExecutor(),
    ) {}

    /**
     * Process an incoming domain event trigger.
     *
     * @return array<int, WorkflowExecution> Created executions
     */
    public function trigger(string $triggerEvent, mixed $target, int $tenantId, array $context = []): array
    {
        $workflows = Workflow::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('trigger_event', $triggerEvent)
            ->with(['conditions', 'actions'])
            ->get();

        $executions = [];

        foreach ($workflows as $workflow) {
            if ($this->evaluator->evaluate($workflow->conditions, $target)) {
                $executions[] = $this->runWorkflow($workflow, $triggerEvent, $target, $tenantId, $context);
            }
        }

        return $executions;
    }

    /**
     * Run an individual workflow for a target entity.
     */
    public function runWorkflow(
        Workflow $workflow,
        string $triggerEvent,
        mixed $target,
        int $tenantId,
        array $context = [],
    ): WorkflowExecution {
        $entityType = is_object($target) ? get_class($target) : 'ArrayTarget';
        $entityId = is_object($target) ? ($target->id ?? 0) : 0;

        $execution = WorkflowExecution::create([
            'tenant_id' => $tenantId,
            'workflow_id' => $workflow->id,
            'trigger_event' => $triggerEvent,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'status' => 'running',
            'context' => array_merge($context, [
                'target_class' => $entityType,
                'target_id' => $entityId,
            ]),
            'started_at' => Carbon::now(),
        ]);

        try {
            foreach ($workflow->actions as $action) {
                if ($action->delay_seconds > 0) {
                    // Dispatch delayed async execution job
                    ExecuteWorkflowJob::dispatch($execution->id, $action->id)
                        ->delay(now()->addSeconds($action->delay_seconds));

                    WorkflowExecutionStep::create([
                        'execution_id' => $execution->id,
                        'workflow_action_id' => $action->id,
                        'action_type' => $action->action_type,
                        'status' => 'pending',
                        'output' => ['scheduled_delay_seconds' => $action->delay_seconds],
                    ]);
                } else {
                    $this->executeActionStep($execution, $action, $target, $tenantId);
                }
            }

            $execution->update([
                'status' => 'completed',
                'finished_at' => Carbon::now(),
            ]);
        } catch (Throwable $e) {
            $execution->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => Carbon::now(),
            ]);
        }

        return $execution;
    }

    /**
     * Execute a specific action step within a workflow execution context.
     */
    public function executeActionStep(
        WorkflowExecution $execution,
        WorkflowAction $action,
        mixed $target,
        int $tenantId,
    ): WorkflowExecutionStep {
        try {
            $output = $this->executor->execute($action, $target, $tenantId);

            return WorkflowExecutionStep::create([
                'execution_id' => $execution->id,
                'workflow_action_id' => $action->id,
                'action_type' => $action->action_type,
                'status' => 'completed',
                'output' => $output,
                'executed_at' => Carbon::now(),
            ]);
        } catch (Throwable $e) {
            return WorkflowExecutionStep::create([
                'execution_id' => $execution->id,
                'workflow_action_id' => $action->id,
                'action_type' => $action->action_type,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'executed_at' => Carbon::now(),
            ]);
        }
    }
}
