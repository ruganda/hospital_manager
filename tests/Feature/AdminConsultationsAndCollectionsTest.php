<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\MobileMoneyRequest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admins writing consultations for a chosen doctor; Mobile Money from the Payments section and its overview page. */
class AdminConsultationsAndCollectionsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mobile_money.driver' => 'fake']);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function visit(Doctor $doctor, string $status = 'checked_in'): Appointment
    {
        $patient = Patient::whereHas('user', fn ($q) => $q->where('email', 'patient@citycare.test'))->firstOrFail();

        return Appointment::create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'appointment_date' => today()->toDateString(),
            'start_time' => '23:00:00', 'end_time' => '23:30:00', 'status' => $status, 'reason' => 'Admin test '.uniqid(),
        ]);
    }

    /** Two doctors with different fees, so re-pricing is visible. */
    private function twoDoctors(): array
    {
        $doctors = Doctor::with('user')->get();
        $a = $doctors->first();
        $b = $doctors->first(fn ($d) => (float) $d->consultation_fee !== (float) $a->consultation_fee);
        $this->assertNotNull($b, 'seed data has doctors with different fees');

        return [$a, $b];
    }

    public function test_admin_picker_lists_every_doctors_open_visits_and_filters_by_doctor(): void
    {
        [$a, $b] = $this->twoDoctors();
        $va = $this->visit($a);
        $vb = $this->visit($b);
        $admin = $this->user('admin@citycare.test');

        $this->actingAs($admin)->get(route('consultations.index'))->assertSee('New consultation');
        $this->actingAs($admin)->get(route('consultations.create'))->assertOk()
            ->assertSee(route('consultations.create', ['appointment_id' => $va->id]), false)
            ->assertSee(route('consultations.create', ['appointment_id' => $vb->id]), false)
            ->assertSee('All doctors');

        $this->actingAs($admin)->get(route('consultations.create', ['doctor_id' => $a->id]))->assertOk()
            ->assertSee(route('consultations.create', ['appointment_id' => $va->id]), false)
            ->assertDontSee(route('consultations.create', ['appointment_id' => $vb->id]), false);
    }

    public function test_admin_records_notes_under_a_chosen_doctor_and_the_invoice_follows(): void
    {
        [$booked, $seenBy] = $this->twoDoctors();
        $visit = $this->visit($booked);
        $admin = $this->user('admin@citycare.test');

        $this->actingAs($admin)->get(route('consultations.create', ['appointment_id' => $visit->id]))
            ->assertOk()->assertSee('Doctor who saw the patient')->assertSee('name="doctor_id"', false);

        $this->actingAs($admin)->post(route('consultations.store'), [
            'appointment_id' => $visit->id, 'doctor_id' => $seenBy->id, 'diagnosis' => 'Acute gastritis',
        ])->assertSessionHasNoErrors();

        $consultation = Consultation::where('appointment_id', $visit->id)->firstOrFail();
        $this->assertSame($seenBy->id, $consultation->doctor_id);
        $this->assertSame($admin->id, $consultation->recorded_by);
        $this->assertSame($seenBy->id, $visit->fresh()->doctor_id);
        $this->assertSame('completed', $visit->fresh()->status->value);
        $this->assertEquals((float) $seenBy->consultation_fee, (float) $visit->fresh()->invoice->amount);

        $this->actingAs($admin)->get(route('consultations.show', $consultation))->assertOk()
            ->assertSee('Entered by')->assertSee($admin->name);

        // the chosen doctor now owns these notes
        $this->actingAs($seenBy->user)->get(route('consultations.show', $consultation))->assertOk();
    }

    public function test_admin_can_change_the_doctor_until_the_visit_is_paid(): void
    {
        [$a, $b] = $this->twoDoctors();
        $visit = $this->visit($a);
        $admin = $this->user('admin@citycare.test');
        $this->actingAs($admin)->post(route('consultations.store'), ['appointment_id' => $visit->id, 'doctor_id' => $a->id, 'diagnosis' => 'Flu']);
        $consultation = Consultation::where('appointment_id', $visit->id)->firstOrFail();
        $invoice = $visit->fresh()->invoice;

        $this->actingAs($admin)->put(route('consultations.update', $consultation), ['doctor_id' => $b->id, 'diagnosis' => 'Flu'])->assertSessionHasNoErrors();
        $this->assertSame($b->id, $consultation->fresh()->doctor_id);
        $this->assertEquals((float) $b->consultation_fee, (float) $invoice->fresh()->amount);

        Payment::create(['invoice_id' => $invoice->id, 'amount' => 1000, 'method' => 'cash', 'paid_at' => now()]);
        $this->actingAs($admin)->put(route('consultations.update', $consultation), ['doctor_id' => $a->id, 'diagnosis' => 'Flu'])->assertSessionHasErrors('doctor_id');
        $this->assertSame($b->id, $consultation->fresh()->doctor_id);
    }

    public function test_doctors_cannot_reassign_notes_to_someone_else(): void
    {
        [$a, $b] = $this->twoDoctors();
        $visit = $this->visit($a);

        $this->actingAs($a->user)->get(route('consultations.create', ['appointment_id' => $visit->id]))->assertDontSee('Doctor who saw the patient');
        $this->actingAs($a->user)->post(route('consultations.store'), ['appointment_id' => $visit->id, 'doctor_id' => $b->id, 'diagnosis' => 'Cold'])->assertSessionHasNoErrors();

        $this->assertSame($a->id, Consultation::where('appointment_id', $visit->id)->value('doctor_id'));
        $this->assertSame($a->id, $visit->fresh()->doctor_id);
    }

    public function test_collect_by_mobile_money_from_record_payment(): void
    {
        $invoice = Invoice::where('status', '!=', 'paid')->with('patient.user')->firstOrFail();

        foreach (['admin@citycare.test', 'cashier@citycare.test'] as $email) {
            $this->actingAs($this->user($email))->get(route('payments.create'))->assertOk()
                ->assertSee('Collect by MTN Mobile Money or Airtel Money')
                ->assertSee('data-momo-invoice', false)
                ->assertSee('Or record a payment already received');
        }

        $this->actingAs($this->user('admin@citycare.test'))->post(route('mobile-money.collect'), [
            'invoice_id' => $invoice->id, 'network' => 'AIRTEL', 'phone' => '0752 400 500', 'amount' => (int) round($invoice->balance),
        ])->assertRedirect(route('invoices.show', $invoice))->assertSessionHasNoErrors();

        $mm = MobileMoneyRequest::firstOrFail();
        $this->assertSame('AIRTEL', $mm->network->value);
        $this->assertSame($this->user('admin@citycare.test')->id, $mm->requested_by);

        // editing an existing payment does not offer it
        $payment = Payment::firstOrFail();
        $this->actingAs($this->user('cashier@citycare.test'))->get(route('payments.edit', $payment))->assertDontSee('Collect by MTN');

        // paid invoices and missing invoices are refused; receptionists can't reach it
        $paid = Invoice::where('status', 'paid')->firstOrFail();
        $this->actingAs($this->user('cashier@citycare.test'))->post(route('mobile-money.collect'), ['invoice_id' => $paid->id, 'network' => 'MTN', 'phone' => '0772 400 500', 'amount' => 1000])
            ->assertSessionHasErrors('invoice_id');
        $this->actingAs($this->user('cashier@citycare.test'))->post(route('mobile-money.collect'), ['network' => 'MTN', 'phone' => '0772 400 500', 'amount' => 1000])
            ->assertSessionHasErrors('invoice_id');
        $this->actingAs($this->user('reception@citycare.test'))->post(route('mobile-money.collect'), ['invoice_id' => $invoice->id, 'network' => 'MTN', 'phone' => '0772 400 500', 'amount' => 1000])
            ->assertForbidden();
    }

    public function test_mobile_money_overview_page(): void
    {
        $invoice = Invoice::where('status', '!=', 'paid')->firstOrFail();
        $this->actingAs($this->user('cashier@citycare.test'))->post(route('mobile-money.collect'), [
            'invoice_id' => $invoice->id, 'network' => 'MTN', 'phone' => '0772 400 500', 'amount' => (int) round($invoice->balance),
        ]);

        foreach (['admin@citycare.test', 'cashier@citycare.test'] as $email) {
            $this->actingAs($this->user($email))->get(route('mobile-money.index'))->assertOk()
                ->assertSee('Waiting for approval')->assertSee($invoice->invoice_no)->assertSee('+256 772 400 500')
                ->assertSee('data-momo-poll', false);
        }
        $this->actingAs($this->user('admin@citycare.test'))->get(route('dashboard'))->assertSee(route('mobile-money.index'), false);

        $this->actingAs($this->user('reception@citycare.test'))->get(route('mobile-money.index'))->assertForbidden();
        $this->actingAs($this->user('doctor@citycare.test'))->get(route('mobile-money.index'))->assertForbidden();
    }
}
