<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('plan')->default('pro')->after('slug');
            $table->integer('max_users')->default(10)->after('plan');
            $table->integer('max_properties')->default(50)->after('max_users');
            $table->json('feature_flags')->nullable()->after('max_properties');
        });

        Schema::table('api_keys', function (Blueprint $table) {
            $table->timestamp('revoked_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn('revoked_at');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['plan', 'max_users', 'max_properties', 'feature_flags']);
        });
    }
};
