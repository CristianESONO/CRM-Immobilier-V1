<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Extension de la table referrers pour le partner management
        Schema::table('referrers', function (Blueprint $table) {
            $table->string('status')->default('active'); // active, pending_approval, suspended
            $table->string('agreement_number')->nullable();
            $table->date('agreement_signed_at')->nullable();
            $table->json('bank_details')->nullable();
        });

        // 2. Durcissement des jetons portail avec hachage SHA-256
        Schema::table('buyer_portal_accesses', function (Blueprint $table) {
            $table->string('portal_token_hash', 64)->nullable()->unique();
        });

        Schema::table('partner_portal_accesses', function (Blueprint $table) {
            $table->string('portal_token_hash', 64)->nullable()->unique();
        });

        // 3. Règles de commissionnement
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('rule_type')->default('percentage'); // percentage, fixed, tiered
            $table->decimal('rate', 8, 2); // % ou montant fixe
            $table->decimal('min_volume', 15, 2)->nullable();
            $table->decimal('max_volume', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        // 4. Commissions
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referrer_id')->constrained('referrers')->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('commission_rules')->nullOnDelete();

            $table->decimal('sales_amount', 15, 2);
            $table->decimal('commission_amount', 15, 2);
            $table->enum('status', ['calculated', 'validated', 'payable', 'paid', 'cancelled'])->default('calculated');

            $table->timestamp('calculated_at')->useCurrent();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('payable_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'referrer_id', 'status']);
        });

        // 5. Historique d'audit des commissions
        Schema::create('commission_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('changed_at')->useCurrent();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_histories');
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('commission_rules');

        Schema::table('partner_portal_accesses', function (Blueprint $table) {
            $table->dropColumn('portal_token_hash');
        });

        Schema::table('buyer_portal_accesses', function (Blueprint $table) {
            $table->dropColumn('portal_token_hash');
        });

        Schema::table('referrers', function (Blueprint $table) {
            $table->dropColumn(['status', 'agreement_number', 'agreement_signed_at', 'bank_details']);
        });
    }
};
