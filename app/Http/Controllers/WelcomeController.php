<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Public home page: clinic facts plus live free-slot counts per department for the coming days. */
class WelcomeController extends Controller
{
    private const DAYS = 6;

    public function __invoke(AppointmentService $slots): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        $data = Cache::remember('welcome.v1', now()->addMinutes(5), fn () => $this->build($slots));

        return view('welcome', $data);
    }

    private function build(AppointmentService $slots): array
    {
        $departments = Department::with(['doctors.user'])->orderBy('name')->get();
        $days = collect(range(0, self::DAYS - 1))->map(fn ($i) => today()->addDays($i));

        $availability = $departments
            ->filter(fn ($d) => $d->doctors->isNotEmpty())
            ->map(function (Department $department) use ($days, $slots) {
                $perDay = [];
                $next = null;

                foreach ($days as $day) {
                    $free = 0;
                    foreach ($department->doctors as $doctor) {
                        $open = $slots->availableSlots($doctor, $day);
                        $free += $open->count();
                        $first = $open->first();
                        if ($first && (! $next || $day->toDateString().$first['start'] < $next['sort'])) {
                            $next = [
                                'sort' => $day->toDateString().$first['start'],
                                'when' => ($day->isToday() ? 'Today' : ($day->isTomorrow() ? 'Tomorrow' : $day->format('D j M'))).', '.$first['start'],
                                'doctor' => $doctor->name,
                            ];
                        }
                    }
                    $perDay[] = $free;
                }

                return [
                    'name' => $department->name,
                    'fee' => money($department->doctors->min('consultation_fee')),
                    'free' => $perDay,
                    'total' => array_sum($perDay),
                    'next' => $next ? ['when' => $next['when'], 'doctor' => $next['doctor']] : null,
                ];
            })
            ->values()
            ->all();

        return [
            'figures' => [
                'departments' => $departments->count(),
                'doctors' => Doctor::count(),
                'patients' => Patient::count(),
            ],
            'departments' => $departments->map(fn ($d) => [
                'name' => $d->name,
                'icon' => $d->icon ?: 'fa-stethoscope',
                'description' => $d->description,
                'fee' => $d->doctors->isNotEmpty() ? money($d->doctors->min('consultation_fee')) : null,
            ])->all(),
            'days' => $days->map(fn ($d) => $d->isToday() ? 'Today' : $d->format('D j'))->all(),
            'availability' => $availability,
            'maxFree' => max(1, collect($availability)->flatMap(fn ($a) => $a['free'])->max() ?? 1),
        ];
    }
}
