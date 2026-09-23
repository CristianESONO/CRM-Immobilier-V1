<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Portail Acquéreur Client
        Schema::create('buyer_portal_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->string('portal_token', 80)->unique()->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'contact_id']);
        });

        // Portail Apporteur / Prescripteur
        Schema::create('partner_portal_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referrer_id')->constrained()->cascadeOnDelete();
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->string('portal_token', 80)->unique()->nullable();
            $table->decimal('commission_rate', 5, 2)->default(2.50); // % de commission par défaut
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'referrer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_portal_accesses');
        Schema::dropIfExists('buyer_portal_accesses');
    }
};
