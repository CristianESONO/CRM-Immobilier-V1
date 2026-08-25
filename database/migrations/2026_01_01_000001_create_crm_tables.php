<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tenants
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('domain')->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestamps();
        });

        // 2. Users extension
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('role')->default('commercial'); // super_admin, admin, commercial, observer
            $table->boolean('is_active')->default(true);
        });

        // 3. Sources
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('channel'); // web, whatsapp, phone, event, referrer
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        // 4. Referrers
        Schema::create('referrers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->nullable(); // agency, individual, partner
            $table->string('organisation')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        // 5. Contacts
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone_e164')->nullable();
            $table->string('email')->nullable();
            $table->string('preferred_channel')->default('whatsapp'); // whatsapp, phone, email, sms
            $table->string('language')->default('fr');
            
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->boolean('is_diaspora')->default(false);
            
            $table->string('property_type')->nullable(); // apartment, villa, land, commercial
            $table->string('district')->nullable();
            $table->decimal('budget_min', 15, 2)->nullable();
            $table->decimal('budget_max', 15, 2)->nullable();
            $table->string('decision_horizon')->nullable(); // immediate, 3_months, 6_months, 1_year
            $table->string('purpose')->nullable(); // residence, investment
            
            // Traçage obligatoire de la source (contrainte DB + NOT NULL)
            $table->foreignId('source_id')->constrained('sources');
            $table->string('sub_source')->nullable();
            $table->foreignId('referrer_id')->nullable()->constrained('referrers')->nullOnDelete();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('landing_page')->nullable();
            
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('nouveau');
            $table->integer('potential_score')->default(0);
            
            // Qualification calculée (4 conditions horodatées)
            $table->timestamp('q_replied_at')->nullable();
            $table->timestamp('q_project_at')->nullable();
            $table->timestamp('q_budget_at')->nullable();
            $table->timestamp('q_source_at')->nullable();
            $table->timestamp('qualified_at')->nullable(); // Calculé, jamais saisi
            
            // Compteur première réponse 2h ouvrées
            $table->timestamp('first_response_at')->nullable();
            $table->integer('first_response_minutes')->nullable();
            
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('next_action_at')->nullable();
            
            // Consentement (Conformité)
            $table->timestamp('consent_at')->nullable();
            $table->string('consent_source')->nullable();
            
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'assigned_to', 'next_action_at']);
        });

        // 6. Status History
        Schema::create('status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();
            
            $table->index(['tenant_id', 'contact_id']);
        });

        // 7. Activities
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); // call, whatsapp, email, meeting, note, status_change
            $table->string('channel')->nullable();
            $table->text('body')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            
            $table->index(['tenant_id', 'contact_id', 'occurred_at']);
        });

        // 8. Properties & Units
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('location')->nullable();
            $table->string('property_type')->nullable();
            $table->decimal('price_min', 15, 2)->nullable();
            $table->decimal('price_max', 15, 2)->nullable();
            $table->date('delivery_date')->nullable();
            $table->string('status')->default('available');
            $table->string('landing_page_url')->nullable();
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->string('reference');
            $table->decimal('area', 10, 2)->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->string('status')->default('available'); // available, reserved, sold
            $table->timestamps();
        });

        Schema::create('contact_property', function (Blueprint $table) {
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->string('interest_level')->default('medium'); // low, medium, high
            $table->primary(['contact_id', 'property_id']);
        });

        // 9. Sequences & Enrollment
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->string('trigger'); // new_contact, status_change, inactivity
            $table->jsonb('steps');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sequence_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('sequence_id')->constrained('sequences')->cascadeOnDelete();
            $table->integer('current_step')->default(0);
            $table->timestamp('next_run_at')->nullable();
            $table->string('status')->default('active'); // active, completed, stopped
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('stopped_at')->nullable();
            $table->string('stop_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('message_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('channel'); // whatsapp, email, sms
            $table->string('template')->nullable();
            $table->string('provider_id')->nullable();
            $table->string('status')->default('sent'); // sent, delivered, read, failed
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamp('delivered_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_log');
        Schema::dropIfExists('sequence_enrollments');
        Schema::dropIfExists('sequences');
        Schema::dropIfExists('contact_property');
        Schema::dropIfExists('units');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('status_history');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('referrers');
        Schema::dropIfExists('sources');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['tenant_id', 'role', 'is_active']);
        });
        Schema::dropIfExists('tenants');
    }
};
