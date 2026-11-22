<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\MobileMoneyRequest;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** "New consultation" picker, and doctors requesting Mobile Money payment from a consultation. */
class ConsultationPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private bool $refuseCharge = false;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.mobile_money.driver' => 'flutterwave',
            'services.flutterwave.secret_key' => 'FLWSECK_TEST-unit',
            'services.flutterwave.base_url' => 'https://api.flutterwave.com/v3',
        ]);
        Http::fake(function (HttpRequest $request) {
            if (str_contains($request->url(), '/charges')) {
                return $this->refuseCharge
                    ? Http::response(['status' => 'error', 'message' => 'Invalid phone number'], 400)
                    : Http::response(['status' => 'success', 'message' => 'Charge initiated']);
            }

            return Http::response(['status' => 'success', 'data' => ['status' => 'pending']]);
        });
    }

    private function doctorUser(): User
    {
        return User::where('email', 'doctor@citycare.test')->firstOrFail();
    }

    /** A visit with the demo doctor today that has no notes yet. */
    private function openVisit(string $status = 'checked_in', ?string $date = null, ?Doctor $doctor = null): Appointment
    {
        $doctor ??= $this->doctorUser()->doctor;
        $patient = Patient::with('user')->whereHas('user', fn ($q) => $q->where('email', 'patient@citycare.test'))->firstOrFail();

        return Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => $date ?? today()->toDateString(),
            'start_time' => '23:00:00',
            'end_time' => '23:30:00',
            'status' => $status,
            'reason' => 'Test visit '.uniqid(),
        ]);
    }

    private function notes(array $extra = []): array
    {
        return array_merge(['diagnosis' => 'Uncomplicated malaria', 'prescription' => 'Coartem 80/480 BD x3 days'], $extra);
    }

    public function test_doctor_gets_new_consultation_button_and_a_picker_of_open_visits(): void
    {
        $doctor = $this->doctorUser();
        $today = $this->openVisit();
        $overdue = $this->openVisit('scheduled', today()->subDays(2)->toDateString());
        $future = $this->openVisit('scheduled', today()->addDays(3)->toDateString());
        $someoneElses = $this->openVisit('checked_in', null, Doctor::where('id', '!=', $doctor->doctor->id)->firstOrFail());

        $this->actingAs($doctor)->get(route('consultations.index'))->assertOk()->assertSee('New consultation');

        $page = $this->actingAs($doctor)->get(route('consultations.create'))->assertOk()
            ->assertSee('Write notes')
            ->assertSee(route('consultations.create', ['appointment_id' => $today->id]), false)
            ->assertSee(route('consultations.create', ['appointment_id' => $overdue->id]), false);
        $page->assertDontSee(route('consultations.create', ['appointment_id' => $future->id]), false);
        $page->assertDontSee(route('consultations.create', ['appointment_id' => $someoneElses->id]), false);

        // admins and patients don't write notes
        $this->actingAs(User::where('email', 'admin@citycare.test')->first())->get(route('consultations.create'))->assertForbidden();
        $this->actingAs(User::where('email', 'admin@citycare.test')->first())->get(route('consultations.index'))->assertDontSee('New consultation');
    }

    public function test_saving_notes_can_send_a_mobile_money_request_for_the_new_invoice(): void
    {
        $doctor = $this->doctorUser();
        $visit = $this->openVisit();
        $fee = (int) round((float) $doctor->doctor->consultation_fee);

        $this->actingAs($doctor)->get(route('consultations.create', ['appointment_id' => $visit->id]))
            ->assertOk()->assertSee('Send a Mobile Money request')->assertSee('name="momo_phone"', false);

        $response = $this->actingAs($doctor)->post(route('consultations.store'), $this->notes([
            'appointment_id' => $visit->id,
            'request_payment' => '1',
            'momo_network' => 'AIRTEL',
            'momo_phone' => '0752 111 222',
        ]));

        $consultation = Consultation::where('appointment_id', $visit->id)->firstOrFail();
        $response->assertRedirect(route('consultations.show', $consultation))->assertSessionHasNoErrors();

        $invoice = $visit->fresh()->invoice;
        $this->assertNotNull($invoice, 'completing the visit raises the invoice');
        $mm = MobileMoneyRequest::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('256752111222', $mm->phone);
        $this->assertSame('AIRTEL', $mm->network->value);
        $this->assertSame($doctor->id, $mm->requested_by);
        $this->assertEquals($fee, (float) $mm->amount);

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/charges') && $r['amount'] === $fee && $r['network'] === 'AIRTEL');

        // the consultation page shows the payment panel and lets the doctor follow the request
        $this->actingAs($doctor)->get(route('consultations.show', $consultation))
            ->assertOk()->assertSee('Payment')->assertSee('Waiting for approval');
        $this->actingAs($doctor)->getJson(route('mobile-money.status', $mm))->assertOk()->assertJson(['pending' => true]);
    }

    public function test_saving_without_the_switch_sends_nothing(): void
    {
        $visit = $this->openVisit();
        $this->actingAs($this->doctorUser())->post(route('consultations.store'), $this->notes(['appointment_id' => $visit->id, 'momo_phone' => '0752 111 222']))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, MobileMoneyRequest::count());
        Http::assertNothingSent();
    }

    public function test_bad_number_is_rejected_before_anything_is_saved(): void
    {
        $visit = $this->openVisit();
        $this->actingAs($this->doctorUser())->post(route('consultations.store'), $this->notes([
            'appointment_id' => $visit->id, 'request_payment' => '1', 'momo_network' => 'MTN', 'momo_phone' => '12345',
        ]))->assertSessionHasErrors('momo_phone');

        $this->assertNull(Consultation::where('appointment_id', $visit->id)->first());
        $this->assertSame('checked_in', $visit->fresh()->status->value);
    }

    public function test_provider_problem_keeps_the_notes_and_warns(): void
    {
        $this->refuseCharge = true;
        $visit = $this->openVisit();

        $this->actingAs($this->doctorUser())->post(route('consultations.store'), $this->notes([
            'appointment_id' => $visit->id, 'request_payment' => '1', 'momo_network' => 'MTN', 'momo_phone' => '0772 111 222',
        ]))->assertSessionHas('warning', fn ($w) => str_contains($w, 'Notes saved, but the payment request was not sent'));

        $this->assertNotNull(Consultation::where('appointment_id', $visit->id)->first());
        $this->assertSame('failed', MobileMoneyRequest::firstOrFail()->status->value);
    }

    public function test_doctor_can_request_payment_when_editing_and_from_the_consultation_page(): void
    {
        $doctor = $this->doctorUser();
        $visit = $this->openVisit();
        $this->actingAs($doctor)->post(route('consultations.store'), $this->notes(['appointment_id' => $visit->id]));
        $consultation = Consultation::where('appointment_id', $visit->id)->firstOrFail();
        $invoice = $visit->fresh()->invoice;

        // editing: switch on the request
        $this->actingAs($doctor)->get(route('consultations.edit', $consultation))->assertOk()->assertSee('Send a Mobile Money request');
        $this->actingAs($doctor)->put(route('consultations.update', $consultation), $this->notes([
            'request_payment' => '1', 'momo_network' => 'MTN', 'momo_phone' => '0772 333 444',
        ]))->assertSessionHasNoErrors();
        $this->assertSame(1, MobileMoneyRequest::where('invoice_id', $invoice->id)->count());

        // while one is pending the form offers no second request
        $this->actingAs($doctor)->get(route('consultations.edit', $consultation))->assertSee('waiting for the patient to approve');

        // the consultation page's own form posts to the shared endpoint and comes back to the consultation
        MobileMoneyRequest::query()->update(['status' => 'failed']);
        $this->actingAs($doctor)->from(route('consultations.show', $consultation))
            ->post(route('mobile-money.store', $invoice), ['network' => 'MTN', 'phone' => '0772 333 444', 'amount' => (int) round($invoice->balance)])
            ->assertRedirect(route('consultations.show', $consultation));
        $this->assertSame(2, MobileMoneyRequest::where('invoice_id', $invoice->id)->count());
    }

    public function test_other_doctors_cannot_charge_or_follow_the_visit(): void
    {
        $visit = $this->openVisit();
        $this->actingAs($this->doctorUser())->post(route('consultations.store'), $this->notes([
            'appointment_id' => $visit->id, 'request_payment' => '1', 'momo_network' => 'MTN', 'momo_phone' => '0772 111 222',
        ]));
        $invoice = $visit->fresh()->invoice;
        $mm = MobileMoneyRequest::firstOrFail();

        $other = Doctor::with('user')->where('id', '!=', $this->doctorUser()->doctor->id)->firstOrFail()->user;
        $this->actingAs($other)->post(route('mobile-money.store', $invoice), ['network' => 'MTN', 'phone' => '0772 111 222', 'amount' => 1000])->assertForbidden();
        $this->actingAs($other)->getJson(route('mobile-money.status', $mm))->assertForbidden();

        // doctors still can't open billing pages directly
        $this->actingAs($this->doctorUser())->get(route('invoices.show', $invoice))->assertForbidden();
    }
}
