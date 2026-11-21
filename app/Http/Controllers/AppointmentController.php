<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Department;
use App\Models\Patient;
use App\Services\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function __construct(private AppointmentService $service) {}

    public function index(Request $request): View
    {
        $appointments = Appointment::query()
            ->with('patient.user', 'doctor.user', 'doctor.department', 'invoice')
            ->visibleTo($request->user()) // doctors & patients only see their own
            ->orderByDesc('appointment_date')->orderByDesc('start_time')
            ->limit($this->perPage())
            ->get();

        return view('appointments.index', compact('appointments'));
    }

    public function create(Request $request): View
    {
        $this->authorize('manage', Appointment::class);

        $appointment = new Appointment([
            'patient_id' => $request->input('patient_id'),
            'doctor_id' => $request->input('doctor_id'),
            'appointment_date' => $request->input('date', today()->toDateString()),
        ]);

        return view('appointments.form', $this->formData($appointment));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage', Appointment::class);
        $appointment = $this->service->book($this->validated($request), $request->user());

        return redirect()->route('appointments.show', $appointment)->with('success', 'Appointment booked for '.$appointment->appointment_date->format('D, d M Y').' at '.$appointment->time_range.'.');
    }

    public function show(Appointment $appointment): View
    {
        $this->authorize('view', $appointment);
        $appointment->load('patient.user', 'doctor.user', 'doctor.department', 'consultation', 'invoice.payments', 'bookedBy');

        $history = Consultation::query()
            ->where('patient_id', $appointment->patient_id)
            ->where('appointment_id', '!=', $appointment->id)
            ->with('doctor.user')->latest()->limit(5)->get();

        return view('appointments.show', compact('appointment', 'history'));
    }

    public function edit(Appointment $appointment): View
    {
        $this->authorize('update', $appointment);
        $appointment->load('patient.user');

        return view('appointments.form', $this->formData($appointment));
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorize('update', $appointment);
        $this->service->update($appointment, $this->validated($request));

        return redirect()->route('appointments.show', $appointment)->with('success', 'Appointment rescheduled.');
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorize('update', $appointment);
        $data = $request->validate(['cancellation_reason' => ['required', 'string', 'max:255']]);
        $this->service->cancel($appointment, $data['cancellation_reason']);

        return back()->with('success', 'Appointment cancelled – the slot is free again.');
    }

    public function status(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorize('changeStatus', $appointment);
        $data = $request->validate([
            'status' => ['required', Rule::in([AppointmentStatus::CheckedIn->value, AppointmentStatus::Completed->value, AppointmentStatus::NoShow->value, AppointmentStatus::Scheduled->value])],
        ]);

        $this->service->changeStatus($appointment, AppointmentStatus::from($data['status']));

        return back()->with('success', 'Status changed to '.AppointmentStatus::from($data['status'])->label().'.');
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        $this->authorize('delete', $appointment);
        $appointment->delete();

        return redirect()->route('appointments.index')->with('success', 'Appointment deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'doctor_id' => ['required', 'exists:doctors,id'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [
            'patient_id.required' => 'Search for and select a patient.',
            'start_time.required' => 'Pick one of the available time slots.',
        ]);
    }

    private function formData(Appointment $appointment): array
    {
        return [
            'appointment' => $appointment,
            'departments' => Department::with(['doctors.user'])->orderBy('name')->get(),
            'selectedPatient' => $appointment->patient_id ? Patient::with('user')->find(old('patient_id', $appointment->patient_id)) : (old('patient_id') ? Patient::with('user')->find(old('patient_id')) : null),
        ];
    }
}
