<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('preferred_channel')->default('email'); // email, whatsapp, sms, in_app
            $table->boolean('opt_in_transactional')->default(true);
            $table->boolean('opt_in_marketing')->default(false);
            $table->time('quiet_hours_start')->nullable(); // ex. 22:00:00
            $table->time('quiet_hours_end')->nullable();   // ex. 07:00:00
            $table->timestamps();

            $table->unique(['tenant_id', 'contact_id']);
        });

        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('channel'); // email, whatsapp, sms, in_app
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('status')->default('sent'); // sent, delivered, failed, blocked_quiet_hours, blocked_opt_out
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamps();

            $table->index(['tenant_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_logs');
        Schema::dropIfExists('communication_preferences');
    }
};
