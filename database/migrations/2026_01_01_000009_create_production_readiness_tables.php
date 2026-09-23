<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Colonne pdf_size si non présente
        if (!Schema::hasColumn('contract_versions', 'pdf_size')) {
            Schema::table('contract_versions', function (Blueprint $table) {
                $table->unsignedBigInteger('pdf_size')->nullable()->after('pdf_path');
            });
        }

        // 2. Traçabilité et Idempotence des Webhooks de signature
        if (!Schema::hasTable('signature_webhook_events')) {
            Schema::create('signature_webhook_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->string('provider'); // yousign, docusign, universign, mock
                $table->string('event_id'); // Identifiant unique émis par le provider
                $table->string('event_type'); // contract.signed, contract.declined, contract.expired
                $table->string('signature_request_id')->nullable();
                $table->json('payload')->nullable();
                $table->string('status')->default('processed'); // processed, ignored, failed
                $table->timestamp('processed_at')->useCurrent();
                $table->timestamps();

                $table->unique(['provider', 'event_id']);
                $table->index(['tenant_id', 'provider']);
                $table->index('signature_request_id');
            });
        }

        // 3. Matrice des exigences documentaires KYC contextuelles
        if (!Schema::hasTable('document_requirements')) {
            Schema::create('document_requirements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('name'); // ex: "Pièce d'identité officielle (CNI/Passeport)"
                $table->string('document_type'); // identity_card, proof_of_residence, company_statutes, bank_statement, loan_agreement, etc.
                $table->string('target_buyer_type')->default('all'); // all, individual, company
                $table->string('financing_type')->default('all'); // all, bank_loan, cash
                $table->string('residence_type')->default('all'); // all, resident, diaspora
                $table->boolean('is_mandatory')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'target_buyer_type']);
                $table->index(['tenant_id', 'financing_type']);
            });
        }

        // 4. Distinction Acquéreur Particulier vs Société sur contacts
        if (!Schema::hasColumn('contacts', 'buyer_type')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->string('buyer_type')->default('individual')->after('status');
                $table->string('company_name')->nullable()->after('buyer_type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requirements');
        Schema::dropIfExists('signature_webhook_events');

        if (Schema::hasColumn('contract_versions', 'pdf_size')) {
            Schema::table('contract_versions', function (Blueprint $table) {
                $table->dropColumn('pdf_size');
            });
        }
    }
};
