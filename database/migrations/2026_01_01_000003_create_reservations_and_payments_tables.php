<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Table reservations
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('reference');
            $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->default('option'); // option, confirmed, cancelled, completed
            $table->decimal('total_amount', 15, 2);
            $table->decimal('deposit_amount', 15, 2)->default(0);
            $table->timestamp('reserved_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'unit_id']);
            $table->index(['tenant_id', 'contact_id']);
        });

        // 2. Table payment_schedules (Échéancier prévisionnel VEFA)
        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->string('label'); // Ex: Dépôt de garantie, Fondations, Hors d'eau, Finitions, Remise des clés
            $table->date('due_date')->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->decimal('expected_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('status')->default('pending'); // pending, partial, paid, overdue
            $table->timestamps();

            $table->index(['tenant_id', 'reservation_id']);
        });

        // 3. Table payments (Règlements reçus)
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('payment_schedule_id')->nullable()->constrained('payment_schedules')->nullOnDelete();
            $table->string('reference');
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('payment_method')->default('bank_transfer'); // bank_transfer, check, cash, wave_om
            $table->string('proof_reference')->nullable();
            $table->string('status')->default('validated'); // validated, pending, rejected
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'reservation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_schedules');
        Schema::dropIfExists('reservations');
    }
};
