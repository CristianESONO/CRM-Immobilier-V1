<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('payment_schedule_id')->constrained('payment_schedules')->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('trigger_type'); // preventive_j7, due_today, overdue_j7, overdue_j15, manual
            $table->string('channel')->default('whatsapp'); // whatsapp, email, sms, call
            $table->string('status')->default('sent'); // sent, pending, failed
            $table->string('recipient'); // phone number or email
            $table->string('subject')->nullable();
            $table->text('message_content');
            $table->timestamp('sent_at')->useCurrent();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'payment_schedule_id']);
            $table->index(['tenant_id', 'trigger_type']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reminders');
    }
};
