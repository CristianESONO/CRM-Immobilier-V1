<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('typology')->default('T3')->after('reference');
            $table->timestamp('marketed_at')->nullable()->after('status');

            $table->index(['tenant_id', 'property_id', 'status']);
            $table->index(['tenant_id', 'typology']);
            $table->index(['tenant_id', 'status', 'marketed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'property_id', 'status']);
            $table->dropIndex(['tenant_id', 'typology']);
            $table->dropIndex(['tenant_id', 'status', 'marketed_at']);
            $table->dropColumn(['typology', 'marketed_at']);
        });
    }
};
