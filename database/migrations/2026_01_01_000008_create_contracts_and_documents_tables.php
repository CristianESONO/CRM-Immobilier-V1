<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Modèles de contrats (Templates)
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('contract_type')->default('reservation_vefa'); // reservation_vefa, promesse_vente, cession_droits
            $table->integer('version')->default(1);
            $table->longText('content');
            $table->json('available_variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'contract_type']);
        });

        // 2. Contrats principaux
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->string('contract_number'); // ex: CTR-2026-001
            $table->string('contract_type')->default('reservation_vefa');
            $table->string('status')->default('draft'); // draft, generated, pending_review, approved, sent, signed, cancelled
            $table->integer('current_version')->default(1);

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('signature_request_id')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'contract_number']);
            $table->index(['tenant_id', 'reservation_id']);
            $table->index(['tenant_id', 'status']);
        });

        // 3. Versions immuables des contrats avec snapshot et checksum
        Schema::create('contract_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->integer('version_number');
            $table->foreignId('template_id')->nullable()->constrained('contract_templates')->nullOnDelete();

            $table->json('snapshot_data'); // Données client, lot, prix, échéancier figées
            $table->longText('rendered_content'); // Texte/HTML rendu final
            $table->string('pdf_path')->nullable();
            $table->string('checksum', 64); // SHA-256
            $table->string('change_reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['contract_id', 'version_number']);
        });

        // 4. Pièces justificatives et dossier acquéreur (KYC)
        Schema::create('buyer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();

            $table->string('document_type'); // identity_card, proof_of_residence, bank_statement, rib, kbis_corporate, other
            $table->string('title');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('status')->default('pending'); // pending, uploaded, under_review, verified, rejected, expired

            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'contact_id']);
            $table->index(['tenant_id', 'reservation_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_documents');
        Schema::dropIfExists('contract_versions');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('contract_templates');
    }
};
