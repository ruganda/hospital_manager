<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\MobileMoneyNetwork;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Doctor;
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
            'consultation' => new Consultation(['appointment_id' => $appointment->id, 'doctor_id' => $appointment->doctor_id]),
            'appointment' => $appointment,
            'invoice' => null,
            'doctors' => $this->doctorChoices($request),
        ]);
    }

    /**
     * "New consultation": pick a visit that still needs notes (today first, then overdue).
     * Doctors see their own visits; an admin sees every doctor's, optionally filtered by doctor.
     */
    private function pickAppointment(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->hasRole('doctor', 'admin'), 403, 'Consultation notes are written by the doctor who saw the patient.');

        $doctorFilter = $user->isAdmin() ? $request->integer('doctor_id') ?: null : ($user->doctor?->id ?? 0);

        $open = Appointment::query()
            ->with('patient.user', 'doctor.user')
            ->when($doctorFilter !== null, fn ($q) => $q->where('doctor_id', $doctorFilter))
            ->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::CheckedIn])
            ->whereDate('appointment_date', '<=', today())
            ->doesntHave('consultation')
            ->orderByDesc('appointment_date')->orderBy('start_time')
            ->limit(100)
            ->get();

        return view('consultations.pick', [
            'today' => $open->filter(fn ($a) => $a->appointment_date->isToday())->sortBy('start_time'),
            'earlier' => $open->reject(fn ($a) => $a->appointment_date->isToday()),
            'doctors' => $user->isAdmin() ? Doctor::with('user')->get()->sortBy('name') : collect(),
            'doctorFilter' => $doctorFilter,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $appointment = Appointment::with('patient.user')->findOrFail($request->input('appointment_id'));
        $this->authorize('create', [Consultation::class, $appointment]);
        abort_if($appointment->consultation()->exists(), 422, 'Notes already exist for this appointment.');

        $notes = $this->validated($request);
        $doctorId = $this->validatedDoctor($request, $appointment->doctor_id);
        $payment = $this->validatedPaymentRequest($request);

        $consultation = DB::transaction(function () use ($notes, $appointment, $doctorId, $request) {
            // An admin may record that a different doctor saw the patient: the visit moves to that
            // doctor before it is completed, so the invoice is raised at the right fee.
            if ($doctorId !== $appointment->doctor_id) {
                $appointment->update(['doctor_id' => $doctorId]);
                $appointment->unsetRelation('doctor');
            }

            $consultation = Consultation::create($notes + [
                'appointment_id' => $appointment->id,
                'patient_id' => $appointment->patient_id,
                'doctor_id' => $doctorId,
                'recorded_by' => $request->user()->id,
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
        $consultation->load('patient.user', 'doctor.user', 'doctor.department', 'recorder', 'appointment.invoice.mobileMoneyRequests.payment');

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
            'doctors' => $this->doctorChoices(request()),
        ]);
    }

    public function update(Request $request, Consultation $consultation): RedirectResponse
    {
        $this->authorize('update', $consultation);
        $notes = $this->validated($request);
        $doctorId = $this->validatedDoctor($request, $consultation->doctor_id);
        $payment = $this->validatedPaymentRequest($request);

        DB::transaction(function () use ($consultation, $notes, $doctorId) {
            if ($doctorId !== $consultation->doctor_id) {
                $this->moveToDoctor($consultation, $doctorId);
            }
            $consultation->update($notes);
        });

        $redirect = redirect()->route('consultations.show', $consultation)->with('success', 'Consultation notes updated.');

        return $this->sendPaymentRequest($redirect, $payment, $consultation->appointment?->invoice()->first(), $request);
    }

    public function destroy(Consultation $consultation): RedirectResponse
    {
        $this->authorize('delete', $consultation);
        $consultation->delete();

        return redirect()->route('consultations.index')->with('success', 'Consultation notes deleted.');
    }

    /** Doctors to choose from on the form: only offered to admins. */
    private function doctorChoices(Request $request)
    {
        return $request->user()->isAdmin() ? Doctor::with('user', 'department')->get()->sortBy('name') : collect();
    }

    /** The doctor the notes are recorded under. Doctors always record their own; admins choose. */
    private function validatedDoctor(Request $request, int $current): int
    {
        if (! $request->user()->isAdmin()) {
            return $current;
        }

        return (int) $request->validate(
            ['doctor_id' => ['required', 'integer', 'exists:doctors,id']], [], ['doctor_id' => 'doctor']
        )['doctor_id'];
    }

    /**
     * Admin corrects which doctor saw the patient after the notes exist. The visit moves with them and,
     * as long as nothing has been paid yet, the invoice is re-priced at the new doctor's fee.
     */
    private function moveToDoctor(Consultation $consultation, int $doctorId): void
    {
        $appointment = $consultation->appointment;
        $invoice = $appointment?->invoice;

        if ($invoice && (float) $invoice->amount_paid > 0) {
            throw ValidationException::withMessages(['doctor_id' => 'This visit has already been (partly) paid, so its doctor can no longer be changed.']);
        }
        if ($invoice?->mobileMoneyRequests()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['doctor_id' => 'A Mobile Money payment for this visit is waiting for approval. Change the doctor after it finishes.']);
        }

        $doctor = Doctor::with('user', 'department')->findOrFail($doctorId);
        $consultation->doctor_id = $doctorId;
        $appointment?->update(['doctor_id' => $doctorId]);

        if ($invoice) {
            $invoice->update([
                'amount' => $doctor->consultation_fee,
                'description' => 'Consultation – '.$doctor->name.' ('.$doctor->department->name.')',
            ]);
            $invoice->refreshTotals();
        }
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
