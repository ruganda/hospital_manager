<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $patients = Patient::query()
            ->with('user')
            ->withCount('appointments')
            // doctors only see patients they have treated or are booked to see
            ->when($user->hasRole('doctor'), fn ($q) => $q->whereHas('appointments', fn ($a) => $a->where('doctor_id', $user->doctor?->id)))
            ->latest('id')
            ->limit($this->perPage())
            ->get();

        return view('patients.index', compact('patients'));
    }

    public function create(): View
    {
        return view('patients.form', ['patient' => new Patient(['patient_no' => Patient::nextNumber()])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules(), $this->messages());
        $this->guardAgainstDuplicates($request);

        $patient = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'role' => Role::Patient,
                'password' => ($data['password'] ?? null) ?: Str::password(12),
                'email_verified_at' => now(),
            ]);

            return $user->patient()->create($this->patientFields($data));
        });

        return redirect()->route('patients.show', $patient)->with('success', "Patient {$patient->patient_no} registered.");
    }

    public function show(Request $request, Patient $patient): View
    {
        $this->authorize('view', $patient);
        $user = $request->user();

        $patient->load('user');

        $appointments = $patient->appointments()
            ->with('doctor.user', 'doctor.department', 'consultation')
            ->when($user->hasRole('doctor'), fn ($q) => $q->where('doctor_id', $user->doctor->id))
            ->orderByDesc('appointment_date')->orderByDesc('start_time')
            ->limit($this->perPage())
            ->get();

        // Treatment history: doctors see every consultation for their patient so care is continuous.
        $consultations = $patient->consultations()->with('doctor.user', 'doctor.department')->latest()->limit(20)->get();

        $invoices = $user->hasRole('doctor')
            ? collect()
            : $patient->invoices()->latest()->limit(10)->get();

        return view('patients.show', [
            'patient' => $patient,
            'appointments' => $appointments,
            'consultations' => $consultations,
            'invoices' => $invoices,
            'balance' => $patient->outstanding_balance,
        ]);
    }

    public function edit(Patient $patient): View
    {
        $patient->load('user');

        return view('patients.form', compact('patient'));
    }

    public function update(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate($this->rules($patient), $this->messages());
        $this->guardAgainstDuplicates($request, $patient);

        DB::transaction(function () use ($patient, $data) {
            $userData = ['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone']];
            if (! empty($data['password'])) {
                $userData['password'] = $data['password'];
            }
            $patient->user->update($userData);
            $patient->update($this->patientFields($data));
        });

        return redirect()->route('patients.show', $patient)->with('success', 'Patient record updated.');
    }

    public function destroy(Patient $patient): RedirectResponse
    {
        $this->authorize('delete', $patient);

        if ($patient->user->photo_path) {
            Storage::disk('public')->delete($patient->user->photo_path);
        }
        $patient->user->delete(); // cascades to patient record and history

        return redirect()->route('patients.index')->with('success', 'Patient deleted.');
    }

    /** Stops the "duplicated patient records" problem: same name + date of birth, or same phone number. */
    private function guardAgainstDuplicates(Request $request, ?Patient $current = null): void
    {
        if ($request->boolean('confirm_not_duplicate')) {
            return;
        }

        $duplicate = Patient::query()
            ->with('user')
            ->when($current, fn ($q) => $q->whereKeyNot($current->id))
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->whereDate('date_of_birth', $request->input('date_of_birth'))
                    ->whereHas('user', fn ($u) => $u->where('name', $request->input('name'))))
                ->orWhereHas('user', fn ($u) => $u->where('phone', $request->input('phone'))))
            ->first();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'duplicate' => "A similar patient already exists: {$duplicate->name} ({$duplicate->patient_no}, phone {$duplicate->user->phone}). Tick “This is a different person” to continue anyway.",
            ]);
        }
    }

    private function patientFields(array $data): array
    {
        return collect($data)->only([
            'date_of_birth', 'gender', 'national_id', 'address', 'blood_group', 'allergies',
            'emergency_contact_name', 'emergency_contact_phone',
        ])->all();
    }

    /** Friendlier wording for the most common mistakes. */
    private function messages(): array
    {
        return [
            'date_of_birth.before' => 'Date of birth must be in the past (before today). Please check the day, month and year.',
            'date_of_birth.after' => 'Please enter a realistic date of birth.',
        ];
    }

    private function rules(?Patient $patient = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($patient?->user_id)],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['nullable', 'confirmed', 'min:8'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'gender' => ['required', 'in:male,female,other'],
            'national_id' => ['nullable', 'string', 'max:50', Rule::unique('patients')->ignore($patient?->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'blood_group' => ['nullable', Rule::in(Patient::BLOOD_GROUPS)],
            'allergies' => ['nullable', 'string', 'max:1000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
