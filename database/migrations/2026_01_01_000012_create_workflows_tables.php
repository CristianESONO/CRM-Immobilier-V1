<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Workflows
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger_event'); // ReservationCreated, PaymentRecorded, etc.
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'trigger_event']);
        });

        // 2. Workflow Conditions
        Schema::create('workflow_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('field');
            $table->string('operator'); // equals, not_equals, greater_than, less_than, exists, is_empty, contains
            $table->string('value')->nullable();
            $table->string('logical_operator')->default('AND'); // AND, OR
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // 3. Workflow Actions
        Schema::create('workflow_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('action_type'); // create_task, send_notification, assign_user, change_status, create_alert
            $table->json('config')->nullable();
            $table->integer('delay_seconds')->default(0);
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // 4. Workflow Executions
        Schema::create('workflow_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('trigger_event');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->json('context')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['entity_type', 'entity_id']);
        });

        // 5. Workflow Execution Steps
        Schema::create('workflow_execution_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_id')->constrained('workflow_executions')->cascadeOnDelete();
            $table->foreignId('workflow_action_id')->nullable()->constrained('workflow_actions')->nullOnDelete();
            $table->string('action_type');
            $table->string('status')->default('pending'); // pending, completed, failed, skipped
            $table->json('output')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_execution_steps');
        Schema::dropIfExists('workflow_executions');
        Schema::dropIfExists('workflow_actions');
        Schema::dropIfExists('workflow_conditions');
        Schema::dropIfExists('workflows');
    }
};
