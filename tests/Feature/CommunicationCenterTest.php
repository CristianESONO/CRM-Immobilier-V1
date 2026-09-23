<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Property;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Communication\CommunicationCenterService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommunicationCenterTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Source $source;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-20 14:00:00'));

        $this->tenant = Tenant::create(['name' => 'Promotion Comm', 'slug' => 'comm']);
        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin Comm',
            'email' => 'admin@comm.test',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->source = Source::create([
            'tenant_id' => $this->tenant->id,
            'channel' => 'web',
            'label' => 'Web Comm',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function it_records_communication_preferences_and_respects_opt_out(): void
    {
        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'source_id' => $this->source->id,
            'first_name' => 'Awa',
            'last_name' => 'Sow',
        ]);

        $service = app(CommunicationCenterService::class);
        $service->setPreference($contact, [
            'preferred_channel' => 'whatsapp',
            'opt_in_transactional' => true,
            'opt_in_marketing' => false,
        ]);

        // Transactional message -> Sent
        $log1 = $service->send($contact, 'whatsapp', 'Votre reçu de paiement', 'Voici le reçu', true);
        $this->assertEquals('sent', $log1->status);

        // Marketing message -> Blocked
        $log2 = $service->send($contact, 'whatsapp', 'Offre promotionnelle', 'Découvrez nos villas', false);
        $this->assertEquals('blocked_opt_out', $log2->status);
    }

    #[Test]
    public function it_blocks_messages_during_quiet_hours(): void
    {
        $contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'source_id' => $this->source->id,
            'first_name' => 'Khadim',
            'last_name' => 'Faye',
        ]);

        $service = app(CommunicationCenterService::class);
        $service->setPreference($contact, [
            'preferred_channel' => 'sms',
            'opt_in_transactional' => true,
            'quiet_hours_start' => '22:00:00',
            'quiet_hours_end' => '07:00:00',
        ]);

        // Simulated time during quiet hours: 23:30:00
        Carbon::setTestNow(Carbon::parse('2026-09-20 23:30:00'));

        $log = $service->send($contact, 'sms', 'Rappel de rendez-vous', 'Demain à 10h', true);
        $this->assertEquals('blocked_quiet_hours', $log->status);
    }
}
