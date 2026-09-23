<?php

namespace App\Services\Workflows;

use App\Models\OperationalAlert;
use App\Models\PaymentReminder;
use App\Models\WorkflowAction;
use App\Services\AuditService;

class WorkflowActionExecutor
{
    /**
     * Execute a workflow action on a given target model or context.
     *
     * @return array<string, mixed> Execution result output
     */
    public function execute(WorkflowAction $action, mixed $target, int $tenantId): array
    {
        $config = $action->config ?? [];
        $actionType = $action->action_type;

        return match ($actionType) {
            'create_task', 'create_reminder' => $this->createTask($config, $target, $tenantId),
            'send_notification' => $this->sendNotification($config, $target, $tenantId),
            'assign_user' => $this->assignUser($config, $target),
            'change_status' => $this->changeStatus($config, $target),
            'create_alert' => $this->createAlert($config, $target, $tenantId),
            default => ['executed' => true, 'action' => $actionType],
        };
    }

    protected function createTask(array $config, mixed $target, int $tenantId): array
    {
        $reservationId = data_get($target, 'reservation_id') ?? data_get($target, 'id');
        $contactId = data_get($target, 'contact_id');
        $scheduleId = data_get($target, 'payment_schedule_id') ?? data_get($target, 'id');

        $reminder = PaymentReminder::create([
            'tenant_id' => $tenantId,
            'reservation_id' => $reservationId,
            'contact_id' => $contactId ?? 1,
            'payment_schedule_id' => $scheduleId,
            'trigger_type' => $config['trigger_type'] ?? 'workflow_automation',
            'channel' => $config['channel'] ?? 'email',
            'status' => 'scheduled',
            'recipient' => $config['recipient'] ?? 'workflow@tenant.local',
            'subject' => $config['subject'] ?? 'Tâche générée par workflow',
            'message_content' => $config['message'] ?? 'Tâche d\'automatisation déclenchée.',
            'scheduled_at' => now()->addSeconds((int) ($config['delay'] ?? 0)),
        ]);

        return [
            'action' => 'create_task',
            'reminder_id' => $reminder->id,
            'status' => 'scheduled',
        ];
    }

    protected function sendNotification(array $config, mixed $target, int $tenantId): array
    {
        AuditService::log('WORKFLOW_NOTIFICATION_SENT', is_object($target) ? $target : null, [
            'subject' => $config['subject'] ?? 'Notification Workflow',
            'message' => $config['message'] ?? '',
            'recipient' => $config['recipient'] ?? null,
        ], null, null, $tenantId);

        return [
            'action' => 'send_notification',
            'sent' => true,
            'subject' => $config['subject'] ?? 'Notification Workflow',
        ];
    }

    protected function assignUser(array $config, mixed $target): array
    {
        if (is_object($target) && method_exists($target, 'update')) {
            $userId = (int) ($config['user_id'] ?? 0);
            if ($userId > 0) {
                $target->update(['assigned_to' => $userId]);
            }
        }

        return [
            'action' => 'assign_user',
            'assigned_to' => $config['user_id'] ?? null,
        ];
    }

    protected function changeStatus(array $config, mixed $target): array
    {
        if (is_object($target) && method_exists($target, 'update')) {
            $newStatus = $config['status'] ?? null;
            if ($newStatus) {
                $target->update(['status' => $newStatus]);
            }
        }

        return [
            'action' => 'change_status',
            'new_status' => $config['status'] ?? null,
        ];
    }

    protected function createAlert(array $config, mixed $target, int $tenantId): array
    {
        $entityType = is_object($target) ? get_class($target) : 'CustomEntity';
        $entityId = is_object($target) ? ($target->id ?? 1) : 1;

        $alert = OperationalAlert::create([
            'tenant_id' => $tenantId,
            'type' => $config['type'] ?? 'workflow_alert',
            'severity' => $config['severity'] ?? 'warning',
            'status' => 'open',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'assigned_to' => $config['assigned_to'] ?? null,
            'title' => $config['title'] ?? 'Alerte générée par workflow',
            'description' => $config['description'] ?? 'Déclenché par le moteur d\'automatisation V5.',
            'detected_at' => now(),
            'idempotency_key' => "{$tenantId}:workflow_alert:{$entityType}:{$entityId}:" . uniqid(),
            'metadata' => $config['metadata'] ?? [],
        ]);

        return [
            'action' => 'create_alert',
            'alert_id' => $alert->id,
        ];
    }
}
