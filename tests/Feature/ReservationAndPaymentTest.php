<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\Unit;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function setupTenantWithLot(string $slug, string $unitRef = 'LOT-A01', float $price = 80000000): array
    {
        $tenant = Tenant::create(['slug' => $slug, 'name' => ucfirst($slug)]);
        session(['tenant_id' => $tenant->id]);

        $source = Source::create(['tenant_id' => $tenant->id, 'channel' => 'web', 'label' => 'Web']);
        $contact = Contact::create([
            'tenant_id' => $tenant->id,
            'source_id' => $source->id,
            'first_name' => 'Aminata',
            'last_name' => 'Traoré',
        ]);

        $property = Property::create([
            'tenant_id' => $tenant->id,
            'name' => 'Résidence La Corniche',
            'location' => 'Dakar',
            'property_type' => 'apartment',
        ]);

        $unit = Unit::create([
            'tenant_id' => $tenant->id,
            'property_id' => $property->id,
            'reference' => $unitRef,
            'area' => 95,
            'price' => $price,
            'status' => 'available',
        ]);

        return compact('tenant', 'contact', 'property', 'unit');
    }

    /** @test */
    public function reservation_creates_record_locks_unit_and_generates_vefa_schedule()
    {
        ['tenant' => $tenant, 'contact' => $contact, 'property' => $property, 'unit' => $unit] =
            $this->setupTenantWithLot('promoteur-vefa');

        $service = new ReservationService();
        $reservation = $service->createReservation([
            'tenant_id' => $tenant->id,
            'contact_id' => $contact->id,
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'total_amount' => 80000000,
            'deposit_amount' => 4000000,
        ]);

        // Reservation must be created with status 'option'
        $this->assertEquals('option', $reservation->status);
        $this->assertStringStartsWith('RES-', $reservation->reference);

        // Unit must be locked as 'reserved'
        $this->assertEquals('reserved', $unit->fresh()->status);

        // VEFA default schedule must have 5 lines totalling 100%
        $schedules = $reservation->schedules;
        $this->assertCount(5, $schedules);
        $this->assertEquals(100, $schedules->sum('percentage'));
        $this->assertEquals(80000000, $schedules->sum('expected_amount'));
    }

    /** @test */
    public function reservation_blocks_double_booking_of_same_unit()
    {
        ['tenant' => $tenant, 'contact' => $contact, 'property' => $property, 'unit' => $unit] =
            $this->setupTenantWithLot('promoteur-lock');

        $service = new ReservationService();

        // First reservation succeeds
        $service->createReservation([
            'tenant_id' => $tenant->id,
            'contact_id' => $contact->id,
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'total_amount' => 80000000,
            'deposit_amount' => 4000000,
        ]);

        // Second reservation on same lot must throw
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/n\'est plus disponible/');

        $service->createReservation([
            'tenant_id' => $tenant->id,
            'contact_id' => $contact->id,
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'total_amount' => 80000000,
            'deposit_amount' => 4000000,
        ]);
    }

    /** @test */
    public function cancellation_releases_unit_back_to_available()
    {
        ['tenant' => $tenant, 'contact' => $contact, 'property' => $property, 'unit' => $unit] =
            $this->setupTenantWithLot('promoteur-cancel');

        $service = new ReservationService();

        $reservation = $service->createReservation([
            'tenant_id' => $tenant->id,
            'contact_id' => $contact->id,
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'total_amount' => 80000000,
            'deposit_amount' => 4000000,
        ]);

        // Verify locked
        $this->assertEquals('reserved', $unit->fresh()->status);

        // Cancel
        $service->cancelReservation($reservation, 'Financement non obtenu');

        // Unit must be available again
        $this->assertEquals('available', $unit->fresh()->status);
        $this->assertEquals('cancelled', $reservation->fresh()->status);
        $this->assertEquals('Financement non obtenu', $reservation->fresh()->cancellation_reason);
    }

    /** @test */
    public function payments_are_recorded_and_balance_is_calculated_correctly()
    {
        ['tenant' => $tenant, 'contact' => $contact, 'property' => $property, 'unit' => $unit] =
            $this->setupTenantWithLot('promoteur-payment', 'LOT-B01', 100000000);

        $service = new ReservationService();

        $reservation = $service->createReservation([
            'tenant_id' => $tenant->id,
            'contact_id' => $contact->id,
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'total_amount' => 100000000, // 100M FCFA
            'deposit_amount' => 5000000,
        ]);

        // Initial state: nothing paid
        $this->assertEquals(0, $reservation->total_paid);
        $this->assertEquals(100000000, $reservation->remaining_balance);
        $this->assertEquals(0, $reservation->payment_progress_percentage);

        // Record first payment: deposit 5M
        $firstSchedule = $reservation->schedules()->first();
        $service->recordPayment($reservation, [
            'payment_schedule_id' => $firstSchedule->id,
            'amount' => 5000000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'proof_reference' => 'VIRT-001',
            'status' => 'validated',
        ]);

        $reservation->refresh();
        $this->assertEquals(5000000, $reservation->total_paid);
        $this->assertEquals(95000000, $reservation->remaining_balance);
        $this->assertEquals(5.0, $reservation->payment_progress_percentage);

        // First schedule line must be marked 'paid'
        $firstSchedule->refresh();
        $this->assertEquals('paid', $firstSchedule->status);

        // Record final full payment
        $service->recordPayment($reservation, [
            'amount' => 95000000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'proof_reference' => 'VIRT-FINAL',
            'status' => 'validated',
        ]);

        $reservation->refresh();
        $this->assertEquals(100000000, $reservation->total_paid);
        $this->assertEquals(0, $reservation->remaining_balance);
        $this->assertEquals(100.0, $reservation->payment_progress_percentage);

        // Reservation must be auto-completed and lot marked as sold
        $this->assertEquals('completed', $reservation->fresh()->status);
        $this->assertEquals('sold', $unit->fresh()->status);
    }
}
