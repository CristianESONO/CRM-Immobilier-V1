<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\MessageLog;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;
use App\Models\Source;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SequenceEngineTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function process_sequences_command_executes_due_steps_and_advances_enrollment()
    {
        $tenant = Tenant::create(['slug' => 'tenant-seq', 'name' => 'Tenant Seq']);
        session(['tenant_id' => $tenant->id]);

        $source = Source::create(['tenant_id' => $tenant->id, 'channel' => 'web', 'label' => 'Web LP']);

        $contact = Contact::create([
            'tenant_id' => $tenant->id,
            'source_id' => $source->id,
            'first_name' => 'Abdoulaye',
            'last_name' => 'Baye',
            'phone_e164' => '+221771112233',
        ]);

        $sequence = Sequence::create([
            'tenant_id' => $tenant->id,
            'key' => 'nouveau_contact',
            'name' => 'Relance J+2',
            'trigger' => 'new_contact',
            'steps' => [
                ['delay_hours' => 2, 'channel' => 'whatsapp', 'template' => 'relance_j2'],
                ['delay_hours' => 5, 'channel' => 'whatsapp', 'template' => 'relance_j5'],
            ],
        ]);

        $enrollment = SequenceEnrollment::create([
            'tenant_id' => $tenant->id,
            'contact_id' => $contact->id,
            'sequence_id' => $sequence->id,
            'current_step' => 0,
            'status' => 'active',
            'enrolled_at' => now()->subHours(3), // Enrolled 3 hours ago -> step 1 (delay 2h) is due!
        ]);

        $this->artisan('sequences:process')->assertExitCode(0);

        $enrollment->refresh();
        $this->assertEquals(1, $enrollment->current_step);

        $messageLogged = MessageLog::withoutGlobalScopes()
            ->where('contact_id', $contact->id)
            ->where('template', 'relance_j2')
            ->exists();
        $this->assertTrue($messageLogged);
    }

    /** @test */
    public function check_alerts_command_creates_sla_alert_for_unresponded_contacts_exceeding_2_hours()
    {
        $tenant = Tenant::create(['slug' => 'tenant-sla', 'name' => 'Tenant SLA']);
        session(['tenant_id' => $tenant->id]);

        $source = Source::create(['tenant_id' => $tenant->id, 'channel' => 'web', 'label' => 'Web LP']);

        $createdTime = Carbon::create(2026, 8, 19, 10, 0, 0, 'Africa/Dakar');
        Carbon::setTestNow($createdTime);

        $contact = Contact::create([
            'tenant_id' => $tenant->id,
            'source_id' => $source->id,
            'first_name' => 'Khadija',
            'last_name' => 'Fall',
            'phone_e164' => '+221773334455',
            'first_response_at' => null,
        ]);

        Carbon::setTestNow(Carbon::create(2026, 8, 19, 14, 0, 0, 'Africa/Dakar'));

        $this->artisan('contacts:check-alerts')->assertExitCode(0);

        $alertExists = Activity::withoutGlobalScopes()
            ->where('contact_id', $contact->id)
            ->where('type', 'alert')
            ->exists();

        $this->assertTrue($alertExists);

        Carbon::setTestNow(null);
    }
}
