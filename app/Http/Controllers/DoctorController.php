<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class DoctorController extends Controller
{
    public function index(): View
    {
        $doctors = Doctor::query()
            ->with('user', 'department')
            ->withCount(['appointments as upcoming_count' => fn ($q) => $q->upcoming()])
            ->latest()
            ->limit($this->perPage())
            ->get();

        return view('doctors.index', compact('doctors'));
    }

    public function create(): View
    {
        return view('doctors.form', ['doctor' => new Doctor, 'departments' => Department::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $doctor = DB::transaction(function () use ($data, $request) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'role' => Role::Doctor,
                'email_verified_at' => now(),
            ]);

            $doctor = $user->doctor()->create(collect($data)->only(['department_id', 'specialization', 'license_no', 'consultation_fee', 'bio'])->all());

            if ($request->boolean('default_schedule')) {
                foreach (range(1, 5) as $day) {
                    $doctor->schedules()->create(['day_of_week' => $day, 'start_time' => '08:00', 'end_time' => '17:00', 'slot_minutes' => 30]);
                }
            }

            return $doctor;
        });

        return redirect()->route('doctors.show', $doctor)->with('success', 'Doctor profile created.');
    }

    public function show(Doctor $doctor): View
    {
        $doctor->load('user', 'department', 'schedules');

        $stats = [
            'total' => $doctor->appointments()->count(),
            'upcoming' => $doctor->appointments()->upcoming()->count(),
            'completed' => $doctor->appointments()->where('status', 'completed')->count(),
            'patients' => $doctor->appointments()->distinct('patient_id')->count('patient_id'),
        ];

        $upcoming = $doctor->appointments()->upcoming()->with('patient.user')
            ->orderBy('appointment_date')->orderBy('start_time')->limit(8)->get();

        return view('doctors.show', compact('doctor', 'stats', 'upcoming'));
    }

    public function edit(Doctor $doctor): View
    {
        $doctor->load('user');

        return view('doctors.form', ['doctor' => $doctor, 'departments' => Department::orderBy('name')->get()]);
    }

    public function update(Request $request, Doctor $doctor): RedirectResponse
    {
        $data = $request->validate($this->rules($doctor));

        DB::transaction(function () use ($doctor, $data) {
            $userData = ['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null];
            if (! empty($data['password'])) {
                $userData['password'] = $data['password'];
            }
            $doctor->user->update($userData);
            $doctor->update(collect($data)->only(['department_id', 'specialization', 'license_no', 'consultation_fee', 'bio'])->all());
        });

        return redirect()->route('doctors.show', $doctor)->with('success', 'Doctor profile updated.');
    }

    public function destroy(Doctor $doctor): RedirectResponse
    {
        if ($doctor->appointments()->upcoming()->exists()) {
            return back()->with('error', 'This doctor has upcoming appointments. Cancel or reassign them first.');
        }

        if ($doctor->user->photo_path) {
            Storage::disk('public')->delete($doctor->user->photo_path);
        }
        $doctor->user->delete(); // cascades to doctor, schedules, appointments

        return redirect()->route('doctors.index')->with('success', 'Doctor removed.');
    }

    private function rules(?Doctor $doctor = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($doctor?->user_id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$doctor ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'department_id' => ['required', 'exists:departments,id'],
            'specialization' => ['required', 'string', 'max:255'],
            'license_no' => ['required', 'string', 'max:50', Rule::unique('doctors')->ignore($doctor?->id)],
            'consultation_fee' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'bio' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
