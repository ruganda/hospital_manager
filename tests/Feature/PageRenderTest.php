<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test for the UI: every page each role can reach renders without errors,
 * against the full demo seed data.
 */
class PageRenderTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function assertPagesOk(User $user, array $urls): void
    {
        foreach ($urls as $url) {
            $response = $this->actingAs($user)->get($url);
            $this->assertSame(200, $response->status(), "GET {$url} as {$user->role->value} returned {$response->status()}");
        }
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_public_pages_render(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('See the right doctor, on time.')
            ->assertSee('data-availability', false)
            ->assertSee('data-countup', false);
        $this->get('/login')->assertOk()->assertSee('Welcome back');
        $this->get('/register')->assertOk()->assertSee('Create your patient account');
        $this->get('/forgot-password')->assertOk();
    }

    public function test_admin_pages_render(): void
    {
        $admin = $this->user('admin@citycare.test');
        $appointment = Appointment::first();
        $editable = Appointment::where('status', 'scheduled')->whereDate('appointment_date', '>=', today())->firstOrFail();
        $patient = Patient::first();
        $doctor = Doctor::first();
        $department = Department::first();
        $invoice = Invoice::where('status', '!=', 'paid')->first() ?? Invoice::first();
        $payment = Payment::first();
        $consultation = Consultation::first();

        $this->assertPagesOk($admin, [
            route('dashboard'),
            route('appointments.index'), route('appointments.create'), route('appointments.show', $appointment), route('appointments.show', $editable), route('appointments.edit', $editable),
            route('patients.index'), route('patients.create'), route('patients.show', $patient), route('patients.edit', $patient),
            route('consultations.index'), route('consultations.show', $consultation),
            route('schedules.index'), route('schedules.create'), route('schedules.edit', DoctorSchedule::first()),
            route('doctors.index'), route('doctors.create'), route('doctors.show', $doctor), route('doctors.edit', $doctor),
            route('departments.index'), route('departments.create'), route('departments.show', $department), route('departments.edit', $department),
            route('users.index'), route('users.create'), route('users.edit', $admin), route('users.edit', $this->user('reception@citycare.test')),
            route('invoices.index'), route('invoices.create'), route('invoices.show', $invoice), route('invoices.edit', $invoice),
            route('payments.index'), route('payments.create'), route('payments.create', ['invoice_id' => $invoice->id]), route('payments.edit', $payment),
            route('reports.index'), route('profile.edit'),
        ]);

        // dashboard has the KPI strip and the range switch
        $this->actingAs($admin)->get(route('dashboard'))
            ->assertSee('data-chart-range="appointmentsTrend"', false)
            ->assertSee('cc-kpis', false);
    }

    public function test_dashboard_chart_endpoint_and_range_switch(): void
    {
        $admin = $this->user('admin@citycare.test');

        $all = $this->actingAs($admin)->getJson(route('ajax.dashboard.charts'))->assertOk()->json();
        $this->assertEqualsCanonicalizing(
            ['appointmentsTrend', 'byDepartment', 'revenueMonthly', 'doctorWorkload', 'statusBreakdown', 'attendance'],
            array_keys($all)
        );
        $this->assertCount(30, $all['appointmentsTrend']['labels']);

        foreach ([7, 90] as $days) {
            $one = $this->actingAs($admin)->getJson(route('ajax.dashboard.charts', ['only' => 'appointmentsTrend', 'days' => $days]))->assertOk()->json();
            $this->assertSame(['appointmentsTrend'], array_keys($one));
            $this->assertCount($days, $one['appointmentsTrend']['labels']);
        }

        // invalid input falls back safely
        $this->actingAs($admin)->getJson(route('ajax.dashboard.charts', ['days' => 5, 'only' => ['x']]))->assertOk();

        foreach (['reception@citycare.test', 'doctor@citycare.test', 'cashier@citycare.test'] as $email) {
            $this->actingAs($this->user($email))->getJson(route('ajax.dashboard.charts'))->assertOk();
        }
    }

    public function test_receptionist_pages_render(): void
    {
        $user = $this->user('reception@citycare.test');
        $appointment = Appointment::first();

        $this->assertPagesOk($user, [
            route('dashboard'),
            route('appointments.index'), route('appointments.create'), route('appointments.show', $appointment),
            route('patients.index'), route('patients.create'), route('patients.show', Patient::first()),
            route('schedules.index'), route('doctors.index'), route('doctors.show', Doctor::first()),
            route('departments.index'), route('departments.show', Department::first()),
            route('invoices.index'), route('invoices.show', Invoice::first()),
            route('reports.index'), route('profile.edit'),
        ]);
    }

    public function test_doctor_pages_render(): void
    {
        $user = $this->user('doctor@citycare.test');
        $doctorId = $user->doctor->id;
        $mine = Appointment::where('doctor_id', $doctorId)->first();
        $consultation = Consultation::where('doctor_id', $doctorId)->first();
        $open = Appointment::where('doctor_id', $doctorId)->whereIn('status', ['scheduled', 'checked_in'])->first();

        $urls = [
            route('dashboard'),
            route('appointments.index'), route('appointments.show', $mine),
            route('patients.index'), route('patients.show', $mine->patient_id),
            route('consultations.index'), route('consultations.show', $consultation), route('consultations.edit', $consultation),
            route('schedules.index'), route('doctors.index'), route('departments.index'),
            route('reports.index'), route('profile.edit'),
        ];
        if ($open) {
            $urls[] = route('consultations.create', ['appointment_id' => $open->id]);
        }
        $this->assertPagesOk($user, $urls);
    }

    public function test_cashier_pages_render(): void
    {
        $user = $this->user('cashier@citycare.test');
        $invoice = Invoice::where('status', '!=', 'paid')->first() ?? Invoice::first();

        $this->assertPagesOk($user, [
            route('dashboard'),
            route('appointments.index'), route('patients.index'),
            route('invoices.index'), route('invoices.show', $invoice),
            route('payments.index'), route('payments.create'), route('payments.create', ['invoice_id' => $invoice->id]),
            route('reports.index'), route('profile.edit'),
        ]);

        $this->actingAs($user)->get(route('payments.create', ['invoice_id' => $invoice->id]))
            ->assertSee('name="method"', false)
            ->assertSee('value="mobile_money"', false);
    }

    public function test_patient_pages_render(): void
    {
        $user = $this->user('patient@citycare.test');
        $pid = $user->patient->id;
        $urls = [
            route('dashboard'),
            route('appointments.index'),
            route('consultations.index'),
            route('invoices.index'),
            route('doctors.index'), route('doctors.show', Doctor::first()),
            route('profile.edit'),
        ];
        if ($a = Appointment::where('patient_id', $pid)->first()) {
            $urls[] = route('appointments.show', $a);
        }
        if ($c = Consultation::where('patient_id', $pid)->first()) {
            $urls[] = route('consultations.show', $c);
        }
        if ($i = Invoice::where('patient_id', $pid)->first()) {
            $urls[] = route('invoices.show', $i);
        }
        $this->assertPagesOk($user, $urls);
    }

    public function test_slot_endpoint_still_serves_the_booking_form(): void
    {
        $admin = $this->user('admin@citycare.test');
        $doctor = Doctor::first();

        $this->actingAs($admin)->getJson(route('ajax.slots', ['doctor' => $doctor, 'date' => today()->addDay()->toDateString()]))
            ->assertOk()->assertJsonStructure(['doctor' => ['name', 'fee'], 'works_today', 'available_count', 'slots']);

        $this->actingAs($admin)->get(route('appointments.create'))
            ->assertSee('data-slot-picker', false)
            ->assertSee('data-slot-value', false);
    }

    public function test_schedules_page_lists_every_block(): void
    {
        $page = $this->actingAs($this->user('admin@citycare.test'))->get(route('schedules.index'))->assertOk();

        foreach (Doctor::with('user')->get() as $doctor) {
            if ($doctor->schedules()->exists()) {
                $page->assertSee(e($doctor->name), false);
            }
        }
    }

    public function test_forms_still_submit(): void
    {
        $cashier = $this->user('cashier@citycare.test');
        $invoice = Invoice::where('status', '!=', 'paid')->firstOrFail();
        $balance = (float) $invoice->balance;

        // record payment with the field names the new form posts
        $this->actingAs($cashier)->post(route('payments.store'), [
            'invoice_id' => $invoice->id,
            'method' => 'mobile_money',
            'amount' => $balance,
            'reference' => 'TEST123',
            'paid_at' => now()->format('Y-m-d\TH:i'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('paid', $invoice->fresh()->status->value);

        // check-in from the dashboard queue
        $reception = $this->user('reception@citycare.test');
        $scheduled = Appointment::where('status', 'scheduled')->whereDate('appointment_date', '>=', today())->firstOrFail();
        $this->actingAs($reception)->patch(route('appointments.status', $scheduled), ['status' => 'checked_in'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('checked_in', $scheduled->fresh()->status->value);

        // register a patient through the rebuilt form
        $this->actingAs($reception)->post(route('patients.store'), [
            'name' => 'Test Patient Okot',
            'date_of_birth' => '1990-05-04',
            'gender' => 'male',
            'phone' => '+256 700 000 999',
            'email' => 'okot.test@example.test',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'okot.test@example.test']);
    }
}
