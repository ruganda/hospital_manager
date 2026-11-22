<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\MobileMoneyNetwork;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Invoice;
use App\Services\AppointmentService;
use App\Services\MobileMoneyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Consultation notes / treatment records written by doctors. */
class ConsultationController extends Controller
{
    public function __construct(
        private AppointmentService $appointments,
        private MobileMoneyService $mobileMoney,
    ) {}

    public function index(Request $request): View
    {
        $consultations = Consultation::query()
            ->with('patient.user', 'doctor.user', 'appointment')
            ->visibleTo($request->user())
            ->latest()
            ->limit($this->perPage())
            ->get();

        return view('consultations.index', compact('consultations'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->filled('appointment_id')) {
            return $this->pickAppointment($request);
        }

        $appointment = Appointment::with('patient.user', 'doctor.user')->findOrFail($request->input('appointment_id'));
        $this->authorize('create', [Consultation::class, $appointment]);

        if ($appointment->consultation) {
            return redirect()->route('consultations.edit', $appointment->consultation);
        }

        return view('consultations.form', [
            'consultation' => new Consultation(['appointment_id' => $appointment->id]),
            'appointment' => $appointment,
            'invoice' => null,
        ]);
    }

    /** "New consultation": the doctor picks one of their visits that still needs notes (today first, then overdue). */
    private function pickAppointment(Request $request): View
    {
        abort_unless($request->user()->hasRole('doctor'), 403, 'Consultation notes are written by the doctor who saw the patient.');

        $open = Appointment::query()
            ->with('patient.user')
            ->where('doctor_id', $request->user()->doctor?->id ?? 0)
            ->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::CheckedIn])
            ->whereDate('appointment_date', '<=', today())
            ->doesntHave('consultation')
            ->orderByDesc('appointment_date')->orderBy('start_time')
            ->limit(50)
            ->get();

        return view('consultations.pick', [
            'today' => $open->filter(fn ($a) => $a->appointment_date->isToday())->sortBy('start_time'),
            'earlier' => $open->reject(fn ($a) => $a->appointment_date->isToday()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $appointment = Appointment::with('patient.user')->findOrFail($request->input('appointment_id'));
        $this->authorize('create', [Consultation::class, $appointment]);
        abort_if($appointment->consultation()->exists(), 422, 'Notes already exist for this appointment.');

        $notes = $this->validated($request);
        $payment = $this->validatedPaymentRequest($request);

        $consultation = DB::transaction(function () use ($notes, $appointment) {
            $consultation = Consultation::create($notes + [
                'appointment_id' => $appointment->id,
                'patient_id' => $appointment->patient_id,
                'doctor_id' => $appointment->doctor_id,
            ]);

            // Writing notes completes the visit and raises the invoice for the cashier.
            $this->appointments->changeStatus($appointment, AppointmentStatus::Completed);

            return $consultation;
        });

        $redirect = redirect()->route('consultations.show', $consultation)->with('success', 'Consultation saved and visit marked as completed.');

        return $this->sendPaymentRequest($redirect, $payment, $appointment->invoice()->first(), $request);
    }

    public function show(Consultation $consultation): View
    {
        $this->authorize('view', $consultation);
        $consultation->load('patient.user', 'doctor.user', 'doctor.department', 'appointment.invoice.mobileMoneyRequests.payment');

        return view('consultations.show', ['consultation' => $consultation, 'invoice' => $consultation->appointment?->invoice]);
    }

    public function edit(Consultation $consultation): View
    {
        $this->authorize('update', $consultation);
        $consultation->load('appointment.patient.user', 'appointment.doctor.user', 'appointment.invoice.mobileMoneyRequests');

        return view('consultations.form', [
            'consultation' => $consultation,
            'appointment' => $consultation->appointment,
            'invoice' => $consultation->appointment?->invoice,
        ]);
    }

    public function update(Request $request, Consultation $consultation): RedirectResponse
    {
        $this->authorize('update', $consultation);
        $notes = $this->validated($request);
        $payment = $this->validatedPaymentRequest($request);
        $consultation->update($notes);

        $redirect = redirect()->route('consultations.show', $consultation)->with('success', 'Consultation notes updated.');

        return $this->sendPaymentRequest($redirect, $payment, $consultation->appointment?->invoice()->first(), $request);
    }

    public function destroy(Consultation $consultation): RedirectResponse
    {
        $this->authorize('delete', $consultation);
        $consultation->delete();

        return redirect()->route('consultations.index')->with('success', 'Consultation notes deleted.');
    }

    /** Optional "request payment on save" fields. Validated before anything is saved. */
    private function validatedPaymentRequest(Request $request): ?array
    {
        if (! $request->boolean('request_payment')) {
            return null;
        }

        return $request->validate([
            'momo_network' => ['required', Rule::in(MobileMoneyNetwork::values())],
            'momo_phone' => ['required', 'string', 'max:20', function ($attr, $value, $fail) {
                if (! MobileMoneyService::isValidUgandanMobile($value)) {
                    $fail('Enter the patient\'s Ugandan mobile number, e.g. 0772 123 456.');
                }
            }],
        ], [], ['momo_network' => 'network', 'momo_phone' => 'Mobile Money number']);
    }

    /**
     * Sends the Mobile Money prompt for the visit's invoice. The notes are already saved, so a provider
     * problem only adds a warning; it never loses the doctor's work.
     */
    private function sendPaymentRequest(RedirectResponse $redirect, ?array $payment, ?Invoice $invoice, Request $request): RedirectResponse
    {
        if (! $payment) {
            return $redirect;
        }
        if (! $invoice || ! Gate::allows('payMobileMoney', $invoice)) {
            return $redirect->with('warning', 'Notes saved. No payment request was sent: there is no unpaid invoice for this visit.');
        }

        try {
            $mm = $this->mobileMoney->request(
                $invoice,
                MobileMoneyNetwork::from($payment['momo_network']),
                $payment['momo_phone'],
                $invoice->balance,
                $request->user(),
            );
        } catch (ValidationException $e) {
            return $redirect->with('warning', 'Notes saved, but the payment request was not sent: '.collect($e->errors())->flatten()->first());
        }

        return $redirect->with('success', 'Notes saved. Payment request for '.money($mm->amount).' sent to '.$mm->display_phone.'.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'symptoms' => ['nullable', 'string', 'max:2000'],
            'diagnosis' => ['required', 'string', 'max:2000'],
            'treatment' => ['nullable', 'string', 'max:2000'],
            'prescription' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'follow_up_date' => ['nullable', 'date', 'after:today'],
        ]);
    }
}
