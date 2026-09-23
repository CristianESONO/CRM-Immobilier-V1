<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->decimal('rate_snapshot', 8, 2)->nullable()->after('rule_id');
            $table->string('rule_name_snapshot')->nullable()->after('rate_snapshot');
            $table->foreignId('paid_by_user_id')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
            $table->string('payment_method')->nullable()->after('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropForeign(['paid_by_user_id']);
            $table->dropColumn(['rate_snapshot', 'rule_name_snapshot', 'paid_by_user_id', 'payment_method']);
        });
    }
};
