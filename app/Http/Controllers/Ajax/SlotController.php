<?php

namespace App\Http\Controllers\Ajax;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** GET /ajax/doctors/{doctor}/slots?date=YYYY-MM-DD[&ignore=appointmentId] */
class SlotController extends Controller
{
    public function __invoke(Request $request, Doctor $doctor, AppointmentService $service): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'ignore' => ['nullable', 'integer'],
        ]);

        $doctor->load('user', 'department', 'schedules');
        $slots = $service->slotsFor($doctor, $data['date'], $data['ignore'] ?? null);
        $date = Carbon::parse($data['date']);

        return response()->json([
            'doctor' => [
                'id' => $doctor->id,
                'name' => $doctor->name,
                'department' => $doctor->department->name,
                'fee' => money($doctor->consultation_fee),
                'working_days' => $doctor->schedules->where('is_active', true)->map(fn ($s) => $s->day_name.' '.$s->time_range)->values(),
            ],
            'date' => $date->toDateString(),
            'day' => $date->format('l, d M Y'),
            'works_today' => $slots->isNotEmpty(),
            'available_count' => $slots->where('available', true)->count(),
            'slots' => $slots,
        ]);
    }
}
