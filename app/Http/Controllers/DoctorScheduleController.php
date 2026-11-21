<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DoctorScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $schedules = DoctorSchedule::query()
            ->with('doctor.user', 'doctor.department')
            ->when($user->hasRole('doctor'), fn ($q) => $q->where('doctor_id', $user->doctor?->id)) // doctors see their own
            ->orderBy('doctor_id')->orderBy('day_of_week')->orderBy('start_time')
            ->get(); // all blocks: the page is a weekly grid and a clinic has only a few dozen

        return view('schedules.index', compact('schedules'));
    }

    public function create(Request $request): View
    {
        return view('schedules.form', [
            'schedule' => new DoctorSchedule(['doctor_id' => $request->input('doctor_id'), 'slot_minutes' => 30, 'is_active' => true, 'start_time' => '08:00', 'end_time' => '17:00']),
            'doctors' => $this->doctors(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        DoctorSchedule::create($data);

        return redirect()->route('schedules.index', ['doctor_id' => $data['doctor_id']])->with('success', 'Consultation schedule added.');
    }

    public function edit(DoctorSchedule $schedule): View
    {
        return view('schedules.form', ['schedule' => $schedule, 'doctors' => $this->doctors()]);
    }

    public function update(Request $request, DoctorSchedule $schedule): RedirectResponse
    {
        $schedule->update($this->validated($request, $schedule));

        return redirect()->route('schedules.index', ['doctor_id' => $schedule->doctor_id])->with('success', 'Consultation schedule updated.');
    }

    public function destroy(DoctorSchedule $schedule): RedirectResponse
    {
        $schedule->delete();

        return back()->with('success', 'Schedule removed.');
    }

    private function validated(Request $request, ?DoctorSchedule $schedule = null): array
    {
        $data = $request->validate([
            'doctor_id' => ['required', 'exists:doctors,id'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_minutes' => ['required', 'integer', 'in:10,15,20,30,45,60'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        // Two schedule blocks for the same doctor & day must not overlap.
        $clash = DoctorSchedule::where('doctor_id', $data['doctor_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->when($schedule, fn ($q) => $q->whereKeyNot($schedule->id))
            ->where('start_time', '<', $data['end_time'].':00')
            ->where('end_time', '>', $data['start_time'].':00')
            ->exists();

        if ($clash) {
            throw ValidationException::withMessages(['start_time' => 'This block overlaps an existing schedule for the same doctor and day.']);
        }

        return $data;
    }

    private function doctors()
    {
        return Doctor::with('user')->get()->sortBy('user.name');
    }
}
