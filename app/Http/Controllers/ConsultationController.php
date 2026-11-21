<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Services\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Consultation notes / treatment records written by doctors. */
class ConsultationController extends Controller
{
    public function __construct(private AppointmentService $appointments) {}

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
        $appointment = Appointment::with('patient.user', 'doctor.user')->findOrFail($request->input('appointment_id'));
        $this->authorize('create', [Consultation::class, $appointment]);

        if ($appointment->consultation) {
            return redirect()->route('consultations.edit', $appointment->consultation);
        }

        return view('consultations.form', [
            'consultation' => new Consultation(['appointment_id' => $appointment->id]),
            'appointment' => $appointment,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $appointment = Appointment::findOrFail($request->input('appointment_id'));
        $this->authorize('create', [Consultation::class, $appointment]);
        abort_if($appointment->consultation()->exists(), 422, 'Notes already exist for this appointment.');

        $consultation = DB::transaction(function () use ($request, $appointment) {
            $consultation = Consultation::create($this->validated($request) + [
                'appointment_id' => $appointment->id,
                'patient_id' => $appointment->patient_id,
                'doctor_id' => $appointment->doctor_id,
            ]);

            // Writing notes completes the visit and raises the invoice for the cashier.
            $this->appointments->changeStatus($appointment, AppointmentStatus::Completed);

            return $consultation;
        });

        return redirect()->route('consultations.show', $consultation)->with('success', 'Consultation saved and visit marked as completed.');
    }

    public function show(Consultation $consultation): View
    {
        $this->authorize('view', $consultation);
        $consultation->load('patient.user', 'doctor.user', 'doctor.department', 'appointment');

        return view('consultations.show', compact('consultation'));
    }

    public function edit(Consultation $consultation): View
    {
        $this->authorize('update', $consultation);
        $consultation->load('appointment.patient.user', 'appointment.doctor.user');

        return view('consultations.form', ['consultation' => $consultation, 'appointment' => $consultation->appointment]);
    }

    public function update(Request $request, Consultation $consultation): RedirectResponse
    {
        $this->authorize('update', $consultation);
        $consultation->update($this->validated($request));

        return redirect()->route('consultations.show', $consultation)->with('success', 'Consultation notes updated.');
    }

    public function destroy(Consultation $consultation): RedirectResponse
    {
        $this->authorize('delete', $consultation);
        $consultation->delete();

        return redirect()->route('consultations.index')->with('success', 'Consultation notes deleted.');
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
