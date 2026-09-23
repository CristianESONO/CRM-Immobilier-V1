<?php

namespace Tests\Feature;

use App\Events\ContactCreated;
use App\Events\OperationalAlertCreated;
use App\Events\PaymentRecorded;
use App\Events\ReservationCreated;
use App\Jobs\ExecuteWorkflowJob;
use App\Models\Contact;
use App\Models\OperationalAlert;
use App\Models\Payment;
use App\Models\PaymentReminder;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowAction;
use App\Models\WorkflowCondition;
use App\Models\WorkflowExecution;
use App\Models\WorkflowExecutionStep;
use App\Services\Workflows\WorkflowEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private Source $sourceA;
    private Property $propertyA;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-20')->startOfDay());

        $this->tenantA = Tenant::create(['name' => 'Promotion Workflow A', 'slug' => 'wf-a']);
        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Manager Automation A',
            'email' => 'auto@a.test',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->sourceA = Source::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'web',
            'label' => 'Web A',
        ]);
        $this->propertyA = Property::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Résidence Automatisation',
            'location' => 'Dakar',
        ]);

        $this->tenantB = Tenant::create(['name' => 'Promotion Workflow B', 'slug' => 'wf-b']);
        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Manager Automation B',
            'email' => 'auto@b.test',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function workflow_engine_respects_tenant_isolation(): void
    {
        $workflowA = Workflow::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Workflow A',
            'trigger_event' => 'ReservationCreated',
            'is_active' => true,
        ]);
        WorkflowAction::create([
            'workflow_id' => $workflowA->id,
            'action_type' => 'send_notification',
            'config' => ['subject' => 'Alerte Tenant A'],
        ]);

        $workflowB = Workflow::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Workflow B',
            'trigger_event' => 'ReservationCreated',
            'is_active' => true,
        ]);
        WorkflowAction::create([
            'workflow_id' => $workflowB->id,
            'action_type' => 'send_notification',
            'config' => ['subject' => 'Alerte Tenant B'],
        ]);

        $resaA = $this->makeReservation($this->tenantA, $this->userA, 10_000_000);

        $engine = app(WorkflowEngine::class);
        $executions = $engine->trigger('ReservationCreated', $resaA, $this->tenantA->id);

        $this->assertCount(1, $executions);
        $this->assertEquals($workflowA->id, $executions[0]->workflow_id);
        $this->assertEquals(0, WorkflowExecution::where('tenant_id', $this->tenantB->id)->count());
    }

    #[Test]
    public function it_evaluates_complex_conditions_correctly(): void
    {
        $workflow = Workflow::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Réservation VIP > 20M',
            'trigger_event' => 'ReservationCreated',
            'is_active' => true,
        ]);

        // Condition 1: total_amount > 20,000,000
        WorkflowCondition::create([
            'workflow_id' => $workflow->id,
            'field' => 'total_amount',
            'operator' => 'greater_than',
            'value' => '20000000',
            'logical_operator' => 'AND',
            'order' => 1,
        ]);

        // Condition 2: status = confirmed
        WorkflowCondition::create([
            'workflow_id' => $workflow->id,
            'field' => 'status',
            'operator' => 'equals',
            'value' => 'confirmed',
            'logical_operator' => 'AND',
            'order' => 2,
        ]);

        WorkflowAction::create([
            'workflow_id' => $workflow->id,
            'action_type' => 'create_alert',
            'config' => [
                'type' => 'vip_reservation',
                'severity' => 'warning',
                'title' => 'Nouvelle Réservation VIP',
            ],
            'order' => 1,
        ]);

        $engine = app(WorkflowEngine::class);

        // 1. Ne doit pas se déclencher pour montant 10M
        $smallResa = $this->makeReservation($this->tenantA, $this->userA, 10_000_000, 'confirmed');
        $execsSmall = $engine->trigger('ReservationCreated', $smallResa, $this->tenantA->id);
        $this->assertCount(0, $execsSmall);

        // 2. Doit se déclencher pour 30M confirmée
        $vipResa = $this->makeReservation($this->tenantA, $this->userA, 30_000_000, 'confirmed');
        $execsVip = $engine->trigger('ReservationCreated', $vipResa, $this->tenantA->id);

        $this->assertCount(1, $execsVip);
        $this->assertEquals('completed', $execsVip[0]->status);
        $this->assertEquals(1, OperationalAlert::where('type', 'vip_reservation')->count());
    }

    #[Test]
    public function it_executes_actions_create_task_assign_user_and_change_status(): void
    {
        $contact = Contact::create([
            'tenant_id' => $this->tenantA->id,
            'source_id' => $this->sourceA->id,
            'first_name' => 'Auto',
            'last_name' => 'Workflow',
            'status' => 'prospect',
        ]);

        $workflow = Workflow::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Traitement Nouveau Lead',
            'trigger_event' => 'ContactCreated',
            'is_active' => true,
        ]);

        // Action 1: assigner au userA
        WorkflowAction::create([
            'workflow_id' => $workflow->id,
            'action_type' => 'assign_user',
            'config' => ['user_id' => $this->userA->id],
            'order' => 1,
        ]);

        // Action 2: changer statut en qualifié
        WorkflowAction::create([
            'workflow_id' => $workflow->id,
            'action_type' => 'change_status',
            'config' => ['status' => 'qualified'],
            'order' => 2,
        ]);

        $engine = app(WorkflowEngine::class);
        $engine->trigger('ContactCreated', $contact, $this->tenantA->id);

        $freshContact = $contact->fresh();
        $this->assertEquals($this->userA->id, $freshContact->assigned_to);
        $this->assertEquals('qualified', $freshContact->status);

        $execution = WorkflowExecution::where('workflow_id', $workflow->id)->first();
        $this->assertNotNull($execution);
        $this->assertEquals(2, WorkflowExecutionStep::where('execution_id', $execution->id)->count());
    }

    #[Test]
    public function it_dispatches_delayed_jobs_when_action_has_delay(): void
    {
        Queue::fake();

        $workflow = Workflow::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Relance différée J+1',
            'trigger_event' => 'ReservationCreated',
            'is_active' => true,
        ]);

        $action = WorkflowAction::create([
            'workflow_id' => $workflow->id,
            'action_type' => 'create_task',
            'config' => ['message' => 'Rappel différé'],
            'delay_seconds' => 86400, // 24h
            'order' => 1,
        ]);

        $resa = $this->makeReservation($this->tenantA, $this->userA, 15_000_000);

        $engine = app(WorkflowEngine::class);
        $engine->trigger('ReservationCreated', $resa, $this->tenantA->id);

        Queue::assertPushed(ExecuteWorkflowJob::class, function ($job) use ($action) {
            return $job->workflowActionId === $action->id;
        });

        $step = WorkflowExecutionStep::first();
        $this->assertEquals('pending', $step->status);
    }

    #[Test]
    public function event_listeners_automatically_trigger_workflows(): void
    {
        $workflow = Workflow::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alerte sur annulation',
            'trigger_event' => 'ReservationCancelled',
            'is_active' => true,
        ]);

        WorkflowAction::create([
            'workflow_id' => $workflow->id,
            'action_type' => 'create_alert',
            'config' => [
                'type' => 'cancellation_alert',
                'severity' => 'critical',
                'title' => 'Annulation Réservation',
            ],
            'order' => 1,
        ]);

        $resa = $this->makeReservation($this->tenantA, $this->userA, 10_000_000, 'confirmed');

        // Dispatch de l'événement de domaine Laravel
        event(new \App\Events\ReservationCancelled($resa, 'Client désisté', $this->userA));

        $this->assertEquals(1, WorkflowExecution::where('workflow_id', $workflow->id)->count());
        $this->assertEquals(1, OperationalAlert::where('type', 'cancellation_alert')->count());
    }

    private function makeReservation(Tenant $tenant, User $user, float $amount, string $status = 'confirmed'): Reservation
    {
        $this->seq++;

        $contact = Contact::create([
            'tenant_id' => $tenant->id,
            'source_id' => $this->sourceA->id,
            'first_name' => 'Acquéreur',
            'last_name' => 'WF' . $this->seq,
        ]);

        $unit = Unit::create([
            'tenant_id' => $tenant->id,
            'property_id' => $this->propertyA->id,
            'reference' => 'LOT-WF-' . $this->seq,
            'area' => 80,
            'price' => $amount,
            'status' => 'reserved',
        ]);

        return Reservation::create([
            'tenant_id' => $tenant->id,
            'reference' => 'RES-WF-' . $this->seq,
            'contact_id' => $contact->id,
            'property_id' => $this->propertyA->id,
            'unit_id' => $unit->id,
            'assigned_to' => $user->id,
            'status' => $status,
            'total_amount' => $amount,
            'deposit_amount' => 0,
            'reserved_at' => Carbon::now(),
        ]);
    }
}
