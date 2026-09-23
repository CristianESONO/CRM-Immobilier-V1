<?php

namespace Tests\Feature;

use App\DTOs\Analytics\AnalyticsFilterData;
use App\Filament\Resources\PaymentScheduleResource;
use App\Filament\Widgets\CashflowForecastWidget;
use App\Filament\Widgets\FinanceOverviewWidget;
use App\Filament\Widgets\OverdueAgingWidget;
use App\Filament\Widgets\RefundOverviewWidget;
use App\Models\Contact;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\Property;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\Source;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Analytics\FinanceAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinanceAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private Source $sourceA;
    private Source $sourceB;
    private Property $propertyA;
    private Property $propertyB;
    private int $unitSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-20')->startOfDay());

        $this->tenantA = Tenant::create(['name' => 'Promotion Finance A', 'slug' => 'fin-a']);
        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'DA Finance',
            'email' => 'da-finance@a.test',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->sourceA = Source::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'web',
            'label' => 'Web A',
        ]);
        $this->propertyA = Property::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Résidence Baie',
            'location' => 'Dakar',
        ]);

        $this->tenantB = Tenant::create(['name' => 'Promotion Finance B', 'slug' => 'fin-b']);
        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'DA Finance B',
            'email' => 'da-finance@b.test',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->sourceB = Source::create([
            'tenant_id' => $this->tenantB->id,
            'channel' => 'web',
            'label' => 'Web B',
        ]);
        $this->propertyB = Property::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Résidence Teranga',
            'location' => 'Saly',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function finance_analytics_respects_tenant_isolation(): void
    {
        $resaA = $this->makeReservation($this->tenantA, $this->userA, $this->sourceA, $this->propertyA, 10_000_000, 'confirmed');
        $this->makePayment($resaA, 3_000_000, 'validated');
        $this->makeSchedule($resaA, 10_000_000, 3_000_000, Carbon::today()->subDays(10));

        $resaB = $this->makeReservation($this->tenantB, $this->userB, $this->sourceB, $this->propertyB, 100_000_000, 'confirmed');
        $this->makePayment($resaB, 80_000_000, 'validated');
        $this->makeSchedule($resaB, 100_000_000, 80_000_000, Carbon::today()->subDays(40));

        $this->actingAs($this->userA);
        $overviewA = app(FinanceAnalyticsService::class)->overview();
        $agingA = app(FinanceAnalyticsService::class)->overdueAging();

        $this->assertEquals(10_000_000.0, $overviewA->confirmedRevenue);
        $this->assertEquals(3_000_000.0, $overviewA->collectedRevenue);
        $this->assertEquals(7_000_000.0, $overviewA->remainingBalance);
        $this->assertEquals(1, $agingA->overdueReservationCount);
        $this->assertEquals(7_000_000.0, $agingA->overdueAmount);

        $this->actingAs($this->userB);
        $overviewB = app(FinanceAnalyticsService::class)->overview();

        $this->assertEquals(100_000_000.0, $overviewB->confirmedRevenue);
        $this->assertEquals(80_000_000.0, $overviewB->collectedRevenue);
        $this->assertNotEquals($overviewA->collectedRevenue, $overviewB->collectedRevenue);
    }

    #[Test]
    public function tenant_a_cannot_see_tenant_b_payments_or_schedules(): void
    {
        $resaB = $this->makeReservation($this->tenantB, $this->userB, $this->sourceB, $this->propertyB, 50_000_000, 'confirmed');
        $this->makePayment($resaB, 12_000_000, 'validated');
        $this->makeSchedule($resaB, 50_000_000, 12_000_000, Carbon::today()->addDays(5));

        $this->actingAs($this->userA);
        $service = app(FinanceAnalyticsService::class);

        $this->assertEquals(0.0, $service->overview()->collectedRevenue);
        $this->assertEquals(0.0, $service->cashflowForecast()->next30Days);
        $this->assertEquals(0, $service->overdueAging()->openScheduleCount);
    }

    #[Test]
    public function it_calculates_contractual_engaged_and_confirmed_revenue(): void
    {
        $this->actingAs($this->userA);

        $this->makeReservation($this->tenantA, $this->userA, $this->sourceA, $this->propertyA, 8_000_000, 'option');
        $this->makeReservation($this->tenantA, $this->userA, $this->sourceA, $this->propertyA, 20_000_000, 'confirmed');
        $this->makeReservation($this->tenantA, $this->userA, $this->sourceA, $this->propertyA, 5_000_000, 'cancelled');

        $overview = app(FinanceAnalyticsService::class)->overview();

        $this->assertEquals(28_000_000.0, $overview->engagedRevenue);
        $this->assertEquals(20_000_000.0, $overview->confirmedRevenue);
    }

    #[Test]
    public function it_calculates_validated_collected_revenue_and_remaining_balance(): void
    {
        $this->actingAs($this->userA);

        $resa = $this->makeReservation($this->tenantA, $this->userA, $this->sourceA, $this->propertyA, 10_000_000, 'confirmed');
        $this->makePayment($resa, 4_000_000, 'validated');
        $this->makePayment($resa, 1_500_000, 'pending');
        $this->makePayment($resa, 2_000_000, 'rejected');

        $overview = app(FinanceAnalyticsService::class)->overview();

        $this->assertEquals(4_000_000.0, $overview->collectedRevenue);
        $this->assertEquals(6_000_000.0, $overview->remainingBalance);
        $this->assertEquals(40.0, $overview->collectionRate);
    }

    #[Test]
    public function it_ignores_invalid_and_non_validated_payments(): void
    {
        $this->actingAs($this->userA);

        $resa = $this->makeReservation($this->tenantA, $this->userA, $this->sourceA, $this->propertyA, 10_000_000, 'confirmed');
        $this->makePayment($resa, 9_000_000, 'pending');
        $this->makePayment($resa, 9_000_000, 'rejected');

        $overview = app(FinanceAnalyticsService::class)->overview();

        $this->assertEquals(0.0, $overview->collectedRevenue);
        $this->assertEquals(10_000_000.0, $overview->remainingBalance);
        $this->assertEquals(0.0, $overview->collectionRate);
    }

    #[Test]
    public function it_detects_overdue_schedules_and_partial_payment_balance(): void
    {
        $this->actingAs($this->userA);

        $resa = $this->makeReservation($this->tenantA, $this->userA, $this->sourceA, $this->propertyA, 10_000_000, 'confirmed');
        $this->makeSchedule($resa, 10_000_000, 4_000_000, Carbon::today()->subDays(12), 'partial');

        $aging = app(FinanceAnalyticsService::class)->overdueAging();

        $this->assertEquals(1, $aging->overdueScheduleCount);
        $this->assertEquals(1, $aging->overdueReservationCount);
        $this->assertEquals(6_000_000.0, $aging->overdueAmount);
        $this->assertEquals(6_000_000.0, $aging->bucket8to30);
        $this->assertEquals(0.0, $aging->currentAmount);
    }

    #[Test]
    public function it_classifies_aging_buckets_and_excludes_fully_paid_schedules(): void
    {
        $this->actingAs($this->userA);

        $resa = $this->makeReservation($this->tenantA, $this->userA, $this->sourceA, $this->propertyA, 60_000_000, 'confirmed');

        $this->makeSchedule($resa, 5_000_000, 0, Carbon::today()->addDays(10), 'pending'); // à jour
        $this->makeSchedule($resa, 5_000_000, 0, Carbon::today()->subDays(3), 'overdue'); // 1-7
        $this->makeSchedule($resa, 5_000_000, 0, Carbon::today()->subDays(15), 'overdue'); // 8-30
        $this->makeSchedule($resa, 5_000_000, 0, Carbon::today()->subDays(45), 'overdue'); // 31-60
        $this->makeSchedule($resa, 5_000_000, 0, Carbon::today()->subDays(75), 'overdue'); // 61-90
        $this->makeSchedule($resa, 5_000_000, 0, Carbon::today()->subDays(120), 'overdue'); // >90
        $this->makeSchedule($resa, 8_000_000, 8_000_000, Carbon::today()->subDays(200), 'paid'); // exclu

        $aging = app(FinanceAnalyticsService::class)->overdueAging();

        $this->assertEquals(5_000_000.0, $aging->currentAmount);
        $this->assertEquals(5_000_000.0, $aging->bucket1to7);
        $this->assertEquals(5_000_000.0, $aging->bucket8to30);
        $this->assertEquals(5_000_000.0, $aging->bucket31to60);
        $this->assertEquals(5_000_000.0, $aging->bucket61to90);
        $this->assertEquals(5_000_000.0, $aging->bucketOver90);
        $this->assertEquals(6, $aging->openScheduleCount);
        $this->assertEquals(5, $aging->overdueScheduleCount);
        $this->assertEquals(25_000_000.0, $aging->overdueAmount);
        $this->assertEquals(1, $aging->overdueReservationCount);

        // retards : 3, 15, 45, 75, 120 → moyenne 51.6, médiane 45
        $this->assertEquals(51.6, $aging->averageOverdueDays);
        $this->assertEquals(45.0, $aging->medianOverdueDays);
    }

    #[Test]
    public function it_calculates_cashflow_forecast_on_remaining_due_only(): void
    {
        $this->actingAs($this->userA);

        $resa = $this->makeReservation($this->tenantA, $this->userA, $this->sourceA, $this->propertyA, 40_000_000, 'confirmed');

        // Échu : 6M restant (10M - 4M) → aujourd'hui + toutes les fenêtres
        $this->makeSchedule($resa, 10_000_000, 4_000_000, Carbon::today()->subDays(5), 'partial');
        // J+20 : 2M
        $this->makeSchedule($resa, 2_000_000, 0, Carbon::today()->addDays(20), 'pending');
        // J+45 : 3M
        $this->makeSchedule($resa, 3_000_000, 0, Carbon::today()->addDays(45), 'pending');
        // J+80 : 4M
        $this->makeSchedule($resa, 4_000_000, 0, Carbon::today()->addDays(80), 'pending');
        // Soldé : ignoré
        $this->makeSchedule($resa, 7_000_000, 7_000_000, Carbon::today()->addDays(10), 'paid');

        $forecast = app(FinanceAnalyticsService::class)->cashflowForecast();

        $this->assertEquals(6_000_000.0, $forecast->today);
        $this->assertEquals(8_000_000.0, $forecast->next30Days); // 6 + 2
        $this->assertEquals(11_000_000.0, $forecast->next60Days); // 8 + 3
        $this->assertEquals(15_000_000.0, $forecast->next90Days); // 11 + 4
    }

    #[Test]
    public function it_separates_pending_refunds_from_executed_and_computes_net_collected(): void
    {
        $this->actingAs($this->userA);

        $resa = $this->makeReservation($this->tenantA, $this->userA, $this->sourceA, $this->propertyA, 10_000_000, 'confirmed');
        $this->makePayment($resa, 10_000_000, 'validated');

        $this->makeRefund($resa, 1_000_000, 'pending');
        $this->makeRefund($resa, 500_000, 'approved');
        $this->makeRefund($resa, 250_000, 'processing');
        $this->makeRefund($resa, 2_000_000, 'completed');
        $this->makeRefund($resa, 300_000, 'rejected');

        $refunds = app(FinanceAnalyticsService::class)->refunds();

        $this->assertEquals(1_000_000.0, $refunds->requestedAmount);
        $this->assertEquals(1, $refunds->requestedCount);
        $this->assertEquals(500_000.0, $refunds->approvedAmount);
        $this->assertEquals(250_000.0, $refunds->processingAmount);
        $this->assertEquals(2_000_000.0, $refunds->executedAmount);
        $this->assertEquals(300_000.0, $refunds->rejectedAmount);
        $this->assertEquals(10_000_000.0, $refunds->grossCollected);
        $this->assertEquals(8_000_000.0, $refunds->netCollected);
        $this->assertEquals(1_750_000.0, $refunds->pendingOperationalAmount());
    }

    #[Test]
    public function it_handles_empty_financial_dataset_and_avoids_division_by_zero(): void
    {
        $this->actingAs($this->userA);

        $service = app(FinanceAnalyticsService::class);
        $overview = $service->overview();
        $aging = $service->overdueAging();
        $forecast = $service->cashflowForecast();
        $refunds = $service->refunds();

        $this->assertEquals(0.0, $overview->engagedRevenue);
        $this->assertEquals(0.0, $overview->confirmedRevenue);
        $this->assertEquals(0.0, $overview->collectedRevenue);
        $this->assertEquals(0.0, $overview->remainingBalance);
        $this->assertEquals(0.0, $overview->collectionRate);
        $this->assertEquals(0, $overview->overdueReservationCount);
        $this->assertEquals(0.0, $aging->overdueAmount);
        $this->assertEquals(0.0, $aging->averageOverdueDays);
        $this->assertEquals(0.0, $aging->medianOverdueDays);
        $this->assertEquals(0.0, $forecast->next90Days);
        $this->assertEquals(0.0, $refunds->netCollected);
    }

    #[Test]
    public function it_respects_date_filters_without_mixing_cohorts(): void
    {
        $this->actingAs($this->userA);

        $old = $this->makeReservation(
            $this->tenantA,
            $this->userA,
            $this->sourceA,
            $this->propertyA,
            20_000_000,
            'confirmed',
            Carbon::parse('2026-01-01'),
        );
        $this->makePayment($old, 5_000_000, 'validated', Carbon::parse('2026-01-15'));

        $recent = $this->makeReservation(
            $this->tenantA,
            $this->userA,
            $this->sourceA,
            $this->propertyA,
            8_000_000,
            'confirmed',
            Carbon::parse('2026-09-10'),
        );
        $this->makePayment($recent, 3_000_000, 'validated', Carbon::parse('2026-09-18'));

        $filter = AnalyticsFilterData::fromArray(['period' => '30_days']);
        $service = app(FinanceAnalyticsService::class);

        $overview = $service->overview($filter);
        $period = $service->period($filter);

        // Portefeuille : tous temps, pas un ratio de période (8 / 28 = 28,6 %)
        $this->assertEquals(28_000_000.0, $overview->confirmedRevenue);
        $this->assertEquals(8_000_000.0, $overview->collectedRevenue);
        $this->assertEquals(28.6, $overview->collectionRate);

        // Flux de période : reserved_at vs payment_date (3 / 8 = 37,5 %)
        $this->assertEquals(8_000_000.0, $period->confirmedInPeriod);
        $this->assertEquals(3_000_000.0, $period->collectedInPeriod);
        $this->assertNotEquals(
            round(($period->collectedInPeriod / $period->confirmedInPeriod) * 100, 1),
            $overview->collectionRate,
            'Le taux portefeuille ne doit pas égaler encaissement de période / CA de période',
        );
    }

    #[Test]
    public function it_exposes_drilldown_filters_on_payment_schedules(): void
    {
        $this->actingAs($this->userA);

        $resa = $this->makeReservation($this->tenantA, $this->userA, $this->sourceA, $this->propertyA, 10_000_000, 'confirmed');
        $overdue = $this->makeSchedule($resa, 4_000_000, 0, Carbon::today()->subDays(40), 'overdue');
        $this->makeSchedule($resa, 3_000_000, 0, Carbon::today()->addDays(10), 'pending');
        $this->makeSchedule($resa, 3_000_000, 3_000_000, Carbon::today()->subDays(5), 'paid');

        $overdueIds = PaymentScheduleResource::applyDrilldown(
            PaymentSchedule::query(),
            'overdue',
        )->pluck('id');

        $this->assertTrue($overdueIds->contains($overdue->id));
        $this->assertCount(1, $overdueIds);

        $plus30 = PaymentScheduleResource::applyDrilldown(
            PaymentSchedule::query(),
            'overdue_30_plus',
        )->pluck('id');
        $this->assertTrue($plus30->contains($overdue->id));

        $within30 = PaymentScheduleResource::applyDrilldown(
            PaymentSchedule::query(),
            'due_within_30_days',
        )->count();
        $this->assertEquals(2, $within30);
    }

    #[Test]
    public function it_renders_finance_intelligence_widgets_without_errors(): void
    {
        $this->actingAs($this->userA);

        $resa = $this->makeReservation($this->tenantA, $this->userA, $this->sourceA, $this->propertyA, 10_000_000, 'confirmed');
        $this->makePayment($resa, 2_000_000, 'validated');
        $this->makeSchedule($resa, 10_000_000, 2_000_000, Carbon::today()->addDays(12));

        $stats = invade(new FinanceOverviewWidget())->getStats();
        $this->assertCount(6, $stats);

        $aging = invade(new OverdueAgingWidget())->getData();
        $this->assertArrayHasKey('labels', $aging);
        $this->assertCount(6, $aging['labels']);

        $cash = invade(new CashflowForecastWidget())->getData();
        $this->assertArrayHasKey('labels', $cash);
        $this->assertCount(4, $cash['labels']);

        $refundStats = invade(new RefundOverviewWidget())->getStats();
        $this->assertCount(6, $refundStats);
    }

    private function makeReservation(
        Tenant $tenant,
        User $user,
        Source $source,
        Property $property,
        float $amount,
        string $status,
        ?Carbon $reservedAt = null,
    ): Reservation {
        $this->unitSeq++;

        $contact = Contact::create([
            'tenant_id' => $tenant->id,
            'source_id' => $source->id,
            'first_name' => 'Acquereur',
            'last_name' => 'Test' . $this->unitSeq,
        ]);

        $unit = Unit::create([
            'tenant_id' => $tenant->id,
            'property_id' => $property->id,
            'reference' => 'LOT-F-' . $tenant->id . '-' . $this->unitSeq,
            'area' => 80,
            'price' => $amount,
            'status' => $status === 'cancelled' ? 'available' : 'reserved',
        ]);

        return Reservation::create([
            'tenant_id' => $tenant->id,
            'reference' => 'RES-F-' . $tenant->id . '-' . $this->unitSeq,
            'contact_id' => $contact->id,
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'assigned_to' => $user->id,
            'status' => $status,
            'total_amount' => $amount,
            'deposit_amount' => 0,
            'reserved_at' => $reservedAt ?? Carbon::now(),
        ]);
    }

    private function makeSchedule(
        Reservation $reservation,
        float $expected,
        float $paid,
        Carbon $dueDate,
        string $status = 'pending',
    ): PaymentSchedule {
        return PaymentSchedule::create([
            'tenant_id' => $reservation->tenant_id,
            'reservation_id' => $reservation->id,
            'label' => 'Jalon ' . $dueDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'percentage' => 0,
            'expected_amount' => $expected,
            'paid_amount' => $paid,
            'status' => $status,
        ]);
    }

    private function makePayment(
        Reservation $reservation,
        float $amount,
        string $status,
        ?Carbon $date = null,
    ): Payment {
        return Payment::create([
            'tenant_id' => $reservation->tenant_id,
            'reservation_id' => $reservation->id,
            'reference' => 'PAY-F-' . uniqid(),
            'amount' => $amount,
            'payment_date' => ($date ?? Carbon::today())->toDateString(),
            'payment_method' => 'bank_transfer',
            'status' => $status,
        ]);
    }

    private function makeRefund(Reservation $reservation, float $amount, string $status): Refund
    {
        return Refund::create([
            'tenant_id' => $reservation->tenant_id,
            'reservation_id' => $reservation->id,
            'reference' => 'REF-F-' . uniqid(),
            'amount' => $amount,
            'reason' => 'Test V4.3',
            'status' => $status,
            'requested_at' => Carbon::now(),
            'processed_at' => $status === 'completed' ? Carbon::now() : null,
        ]);
    }
}
