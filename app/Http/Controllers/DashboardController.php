<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Appointment;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load('doctor', 'patient');

        return match ($user->role->value) {
            'admin' => view('dashboard.admin', $this->adminData()),
            'receptionist' => view('dashboard.receptionist', $this->receptionData()),
            'doctor' => view('dashboard.doctor', $this->doctorData($user)),
            'cashier' => view('dashboard.cashier', $this->cashierData()),
            'patient' => view('dashboard.patient', $this->patientData($user)),
        };
    }

    /** JSON consumed by Chart.js on the dashboards (AJAX). */
    public function charts(Request $request): JsonResponse
    {
        $user = $request->user();

        $doctorId = $user->doctor?->id ?? 0;
        $days = in_array((int) $request->query('days'), [7, 30, 90], true) ? (int) $request->query('days') : 30;

        // Builders are closures so a range switch (?only=key) only computes the chart it needs.
        $builders = match ($user->role->value) {
            'admin' => [
                'appointmentsTrend' => fn () => $this->appointmentsTrend(Appointment::query(), $days),
                'byDepartment' => fn () => $this->byDepartment(),
                'revenueMonthly' => fn () => $this->revenueMonthly(6),
                'doctorWorkload' => fn () => $this->doctorWorkload(),
                'statusBreakdown' => fn () => $this->statusBreakdown(Appointment::query()->whereDate('appointment_date', '>=', today()->subDays(30))),
                'attendance' => fn () => $this->attendance(Appointment::query(), 6),
            ],
            'receptionist' => [
                'upcomingWeek' => fn () => $this->upcomingWeek(),
                'byDepartment' => fn () => $this->byDepartment(),
                'attendance' => fn () => $this->attendance(Appointment::query(), 6),
            ],
            'doctor' => [
                'patientsWeekly' => fn () => $this->patientsWeekly($doctorId),
                'statusBreakdown' => fn () => $this->statusBreakdown(Appointment::query()->where('doctor_id', $doctorId)),
            ],
            'cashier' => [
                'revenueDaily' => fn () => $this->revenueDaily(30),
                'paymentMethods' => fn () => $this->paymentMethods(),
                'invoiceStatus' => fn () => $this->invoiceStatus(),
            ],
            default => [],
        };

        if (is_string($only = $request->query('only')) && $only !== '') {
            $builders = array_intersect_key($builders, [$only => true]);
        }

        $charts = array_map(fn ($build) => $build(), $builders);

        return response()->json($charts);
    }

    // ----------------------------------------------------------------- page data

    private function adminData(): array
    {
        $monthStart = today()->startOfMonth();

        return [
            'stats' => [
                'patients' => Patient::count(),
                'new_patients' => Patient::where('created_at', '>=', $monthStart)->count(),
                'doctors' => Doctor::count(),
                'departments' => Department::count(),
                'today' => Appointment::whereDate('appointment_date', today())->blocking()->count(),
                'upcoming' => Appointment::upcoming()->count(),
                'revenue_month' => Payment::where('paid_at', '>=', $monthStart)->sum('amount'),
                'billed_month' => Invoice::where('created_at', '>=', $monthStart)->sum('amount'),
                'today_checked_in' => Appointment::whereDate('appointment_date', today())->where('status', AppointmentStatus::CheckedIn)->count(),
                'today_completed' => Appointment::whereDate('appointment_date', today())->where('status', AppointmentStatus::Completed)->count(),
                'outstanding' => Invoice::where('status', '!=', 'paid')->selectRaw('COALESCE(SUM(amount - amount_paid),0) s')->value('s'),
                'staff' => User::whereIn('role', ['admin', 'receptionist', 'cashier'])->count(),
            ],
            'todayAppointments' => $this->todayList(Appointment::query(), 8),
            'recentPayments' => Payment::with('invoice.patient.user', 'receiver')->latest('paid_at')->limit(6)->get(),
        ];
    }

    private function receptionData(): array
    {
        $today = Appointment::whereDate('appointment_date', today());

        return [
            'stats' => [
                'today' => (clone $today)->blocking()->count(),
                'waiting' => (clone $today)->where('status', AppointmentStatus::Scheduled)->count(),
                'checked_in' => (clone $today)->where('status', AppointmentStatus::CheckedIn)->count(),
                'completed' => (clone $today)->where('status', AppointmentStatus::Completed)->count(),
                'cancelled' => (clone $today)->where('status', AppointmentStatus::Cancelled)->count(),
                'patients' => Patient::count(),
            ],
            'todayAppointments' => $this->todayList(Appointment::query(), 15),
            'doctorsToday' => Doctor::with('user', 'department')
                ->whereHas('schedules', fn ($q) => $q->where('day_of_week', today()->dayOfWeek)->where('is_active', true))
                ->withCount(['appointments as today_count' => fn ($q) => $q->whereDate('appointment_date', today())->blocking()])
                ->get(),
        ];
    }

    private function doctorData(User $user): array
    {
        $doctorId = $user->doctor?->id ?? 0;
        $mine = Appointment::where('doctor_id', $doctorId);

        return [
            'stats' => [
                'today' => (clone $mine)->whereDate('appointment_date', today())->blocking()->count(),
                'pending_today' => (clone $mine)->whereDate('appointment_date', today())->whereIn('status', ['scheduled', 'checked_in'])->count(),
                'upcoming' => (clone $mine)->upcoming()->count(),
                'month_completed' => (clone $mine)->where('status', 'completed')->whereDate('appointment_date', '>=', today()->startOfMonth())->count(),
                'patients' => (clone $mine)->distinct('patient_id')->count('patient_id'),
            ],
            'todayAppointments' => $this->todayList(Appointment::where('doctor_id', $doctorId), 20),
            'nextAppointments' => Appointment::where('doctor_id', $doctorId)->whereDate('appointment_date', '>', today())->upcoming()
                ->with('patient.user')->orderBy('appointment_date')->orderBy('start_time')->limit(6)->get(),
        ];
    }

    private function cashierData(): array
    {
        return [
            'stats' => [
                'today' => Payment::whereDate('paid_at', today())->sum('amount'),
                'today_count' => Payment::whereDate('paid_at', today())->count(),
                'month' => Payment::where('paid_at', '>=', today()->startOfMonth())->sum('amount'),
                'unpaid' => Invoice::where('status', 'unpaid')->count(),
                'partial' => Invoice::where('status', 'partial')->count(),
                'outstanding' => Invoice::where('status', '!=', 'paid')->selectRaw('COALESCE(SUM(amount - amount_paid),0) s')->value('s'),
            ],
            'openInvoices' => Invoice::with('patient.user')->where('status', '!=', 'paid')->oldest()->limit(8)->get(),
            'recentPayments' => Payment::with('invoice.patient.user')->latest('paid_at')->limit(8)->get(),
        ];
    }

    private function patientData(User $user): array
    {
        $patient = $user->patient;
        $pid = $patient?->id ?? 0;

        return [
            'patient' => $patient,
            'stats' => [
                'upcoming' => Appointment::where('patient_id', $pid)->upcoming()->count(),
                'visits' => Appointment::where('patient_id', $pid)->where('status', 'completed')->count(),
                'balance' => $patient?->outstanding_balance ?? 0,
                'invoices_open' => Invoice::where('patient_id', $pid)->where('status', '!=', 'paid')->count(),
            ],
            'upcoming' => Appointment::where('patient_id', $pid)->upcoming()->with('doctor.user', 'doctor.department')
                ->orderBy('appointment_date')->orderBy('start_time')->limit(5)->get(),
            'history' => Appointment::where('patient_id', $pid)->whereDate('appointment_date', '<', today())
                ->with('doctor.user', 'consultation')->orderByDesc('appointment_date')->limit(5)->get(),
            'invoices' => Invoice::where('patient_id', $pid)->latest()->limit(5)->get(),
        ];
    }

    private function todayList($query, int $limit): Collection
    {
        return $query->whereDate('appointment_date', today())
            ->with('patient.user', 'doctor.user', 'doctor.department')
            ->orderBy('start_time')->limit($limit)->get();
    }

    // ----------------------------------------------------------------- chart builders

    private function appointmentsTrend($query, int $days): array
    {
        $from = today()->subDays($days - 1);
        $rows = $query->whereDate('appointment_date', '>=', $from)->whereDate('appointment_date', '<=', today())
            ->selectRaw('appointment_date as d, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed', ['completed'])
            ->groupBy('appointment_date')->get()
            ->keyBy(fn ($r) => Carbon::parse($r->d)->toDateString());

        $labels = $total = $completed = [];
        for ($d = $from->copy(); $d->lte(today()); $d->addDay()) {
            $labels[] = $d->format('d M');
            $total[] = (int) ($rows[$d->toDateString()]->total ?? 0);
            $completed[] = (int) ($rows[$d->toDateString()]->completed ?? 0);
        }

        return $this->chart('line', $labels, [
            ['label' => 'Booked', 'data' => $total],
            ['label' => 'Completed', 'data' => $completed],
        ]);
    }

    private function upcomingWeek(): array
    {
        $labels = $data = [];
        $rows = Appointment::blocking()->whereBetween('appointment_date', [today(), today()->addDays(6)])
            ->selectRaw('appointment_date as d, COUNT(*) c')->groupBy('appointment_date')->get()
            ->keyBy(fn ($r) => Carbon::parse($r->d)->toDateString());

        for ($d = today(); $d->lte(today()->addDays(6)); $d->addDay()) {
            $labels[] = $d->format('D d');
            $data[] = (int) ($rows[$d->toDateString()]->c ?? 0);
        }

        return $this->chart('bar', $labels, [['label' => 'Appointments booked', 'data' => $data]]);
    }

    private function byDepartment(): array
    {
        $rows = Department::query()
            ->withCount(['appointments' => fn ($q) => $q->whereDate('appointment_date', '>=', today()->subDays(90))])
            ->orderByDesc('appointments_count')->get();

        return $this->chart('bar', $rows->pluck('name'), [['label' => 'Appointments (90 days)', 'data' => $rows->pluck('appointments_count')]], ['horizontal' => true]);
    }

    private function revenueMonthly(int $months): array
    {
        $from = today()->startOfMonth()->subMonths($months - 1);
        // aggregate in SQL (no model hydration) — fast even with thousands of rows
        $paidRows = DB::table('payments')->where('paid_at', '>=', $from)
            ->selectRaw($this->monthSql('paid_at').' as m, SUM(amount) as total')->groupBy('m')->pluck('total', 'm');
        $billedRows = DB::table('invoices')->where('created_at', '>=', $from)
            ->selectRaw($this->monthSql('created_at').' as m, SUM(amount) as total')->groupBy('m')->pluck('total', 'm');

        $labels = $paid = $inv = [];
        for ($m = $from->copy(); $m->lte(today()); $m->addMonth()) {
            $labels[] = $m->format('M Y');
            $paid[] = round((float) ($paidRows[$m->format('Y-m')] ?? 0), 2);
            $inv[] = round((float) ($billedRows[$m->format('Y-m')] ?? 0), 2);
        }

        return $this->chart('bar', $labels, [
            ['label' => 'Billed', 'data' => $inv],
            ['label' => 'Collected', 'data' => $paid],
        ], ['currency' => config('clinic.currency'), 'colors' => ['soft', 'primary']]);
    }

    private function revenueDaily(int $days): array
    {
        $from = today()->subDays($days - 1);
        $rows = DB::table('payments')->where('paid_at', '>=', $from)
            ->selectRaw('DATE(paid_at) as d, SUM(amount) as total')->groupBy('d')->pluck('total', 'd');

        $labels = $data = [];
        for ($d = $from->copy(); $d->lte(today()); $d->addDay()) {
            $labels[] = $d->format('d M');
            $data[] = round((float) ($rows[$d->toDateString()] ?? 0), 2);
        }

        return $this->chart('line', $labels, [['label' => 'Collected', 'data' => $data]], ['currency' => config('clinic.currency')]);
    }

    private function doctorWorkload(): array
    {
        $rows = Doctor::with('user')
            ->withCount(['appointments' => fn ($q) => $q->blocking()->whereDate('appointment_date', '>=', today()->startOfMonth())])
            ->orderByDesc('appointments_count')->limit(10)->get();

        return $this->chart('bar', $rows->map(fn ($d) => $d->name), [['label' => 'Appointments this month', 'data' => $rows->pluck('appointments_count')]], ['horizontal' => true]);
    }

    private function statusBreakdown($query): array
    {
        $counts = $query->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');
        $cases = AppointmentStatus::cases();

        return $this->chart('pie', array_map(fn ($s) => $s->label(), $cases), [[
            'label' => 'Appointments',
            'data' => array_map(fn ($s) => (int) ($counts[$s->value] ?? 0), $cases),
        ]], ['colors' => array_map(fn ($s) => $s->color(), $cases)]);
    }

    private function attendance($query, int $months): array
    {
        $from = today()->startOfMonth()->subMonths($months - 1);
        $rows = $query->toBase()
            ->whereDate('appointment_date', '>=', $from)->whereDate('appointment_date', '<', today())
            ->selectRaw($this->monthSql('appointment_date').' as m, status, COUNT(*) as c')
            ->groupBy('m', 'status')->get()
            ->groupBy('m');

        $labels = $attended = $missed = $cancelled = [];
        for ($m = $from->copy(); $m->lte(today()); $m->addMonth()) {
            $group = ($rows[$m->format('Y-m')] ?? collect())->pluck('c', 'status');
            $labels[] = $m->format('M Y');
            $attended[] = (int) ($group['completed'] ?? 0);
            $missed[] = (int) ($group['no_show'] ?? 0);
            $cancelled[] = (int) ($group['cancelled'] ?? 0);
        }

        return $this->chart('bar', $labels, [
            ['label' => 'Attended', 'data' => $attended],
            ['label' => 'No show', 'data' => $missed],
            ['label' => 'Cancelled', 'data' => $cancelled],
        ], ['stacked' => true, 'colors' => ['success', 'danger', 'secondary']]);
    }

    private function patientsWeekly(int $doctorId): array
    {
        $from = today()->startOfWeek()->subWeeks(7);
        $perDay = DB::table('appointments')->where('doctor_id', $doctorId)->where('status', 'completed')
            ->whereDate('appointment_date', '>=', $from)
            ->selectRaw('appointment_date as d, COUNT(*) as c')->groupBy('appointment_date')->get();

        $weeks = [];
        foreach ($perDay as $row) {
            $key = Carbon::parse($row->d)->startOfWeek()->toDateString();
            $weeks[$key] = ($weeks[$key] ?? 0) + (int) $row->c;
        }

        $labels = $data = [];
        for ($w = $from->copy(); $w->lte(today()); $w->addWeek()) {
            $labels[] = 'Wk '.$w->format('d M');
            $data[] = $weeks[$w->toDateString()] ?? 0;
        }

        return $this->chart('line', $labels, [['label' => 'Patients seen', 'data' => $data]]);
    }

    private function paymentMethods(): array
    {
        $rows = Payment::where('paid_at', '>=', today()->subDays(90))->selectRaw('method, SUM(amount) s')->groupBy('method')->pluck('s', 'method');
        $cases = PaymentMethod::cases();

        return $this->chart('doughnut', array_map(fn ($m) => $m->label(), $cases), [[
            'label' => 'Collected (90 days)',
            'data' => array_map(fn ($m) => round((float) ($rows[$m->value] ?? 0), 2), $cases),
        ]], ['currency' => config('clinic.currency')]);
    }

    private function invoiceStatus(): array
    {
        $rows = Invoice::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');
        $cases = InvoiceStatus::cases();

        return $this->chart('doughnut', array_map(fn ($s) => $s->label(), $cases), [[
            'label' => 'Invoices',
            'data' => array_map(fn ($s) => (int) ($rows[$s->value] ?? 0), $cases),
        ]], ['colors' => array_map(fn ($s) => $s->color(), $cases)]);
    }

    /** "YYYY-MM" grouping expression that works on MySQL/MariaDB and SQLite (tests). */
    private function monthSql(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    private function chart(string $type, iterable $labels, array $datasets, array $options = []): array
    {
        return [
            'type' => $type,
            'labels' => collect($labels)->values(),
            'datasets' => $datasets,
            'options' => $options,
        ];
    }
}
