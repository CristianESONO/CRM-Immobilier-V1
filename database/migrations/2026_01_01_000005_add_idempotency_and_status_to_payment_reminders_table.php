<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_reminders', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable()->after('tenant_id');
            $table->integer('retry_count')->default(0)->after('status');
            $table->timestamp('last_attempt_at')->nullable()->after('retry_count');
            $table->text('error_message')->nullable()->after('last_attempt_at');

            $table->index(['tenant_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_reminders', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'idempotency_key']);
            $table->dropColumn(['idempotency_key', 'retry_count', 'last_attempt_at', 'error_message']);
        });
    }
};
