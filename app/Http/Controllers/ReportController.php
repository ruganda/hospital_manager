<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Exports\ReportExport;
use App\Models\Appointment;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    /** Which roles may run which report. */
    public const ACCESS = [
        'daily-appointments' => ['admin', 'receptionist', 'doctor'],
        'doctor-schedule' => ['admin', 'receptionist', 'doctor'],
        'payments' => ['admin', 'cashier'],
        'patient-visits' => ['admin', 'receptionist', 'doctor'],
    ];

    public function index(Request $request)
    {
        $user = $request->user();

        return view('reports.index', [
            'reports' => collect(self::ACCESS)->filter(fn ($roles) => in_array($user->role->value, $roles))->keys(),
            'doctors' => $user->hasRole('doctor') ? Doctor::with('user')->whereKey($user->doctor->id)->get() : Doctor::with('user')->get()->sortBy('user.name'),
            'departments' => Department::orderBy('name')->get(),
            'cashiers' => User::whereIn('role', ['admin', 'cashier'])->orderBy('name')->get(),
            'methods' => PaymentMethod::cases(),
            'statuses' => AppointmentStatus::cases(),
            'selectedPatient' => $request->input('patient_id') ? Patient::with('user')->find($request->input('patient_id')) : null,
        ]);
    }

    public function export(Request $request, string $report): Response
    {
        abort_unless(isset(self::ACCESS[$report]), 404);
        abort_unless($request->user()->hasRole(...self::ACCESS[$report]), 403);

        $request->validate([
            'format' => ['required', 'in:pdf,xlsx,csv'],
            'date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'patient_id' => [$report === 'patient-visits' ? 'required' : 'nullable', 'exists:patients,id'],
        ], ['patient_id.required' => 'Select a patient for the visit report.']);

        $data = match ($report) {
            'daily-appointments' => $this->dailyAppointments($request),
            'doctor-schedule' => $this->doctorSchedule($request),
            'payments' => $this->payments($request),
            'patient-visits' => $this->patientVisits($request),
        };

        $filename = $report.'-'.now()->format('Ymd-His');

        return match ($request->input('format')) {
            'pdf' => Pdf::loadView('reports.pdf', $data)->setPaper('a4', count($data['headings']) > 6 ? 'landscape' : 'portrait')->download($filename.'.pdf'),
            'xlsx' => Excel::download(new ReportExport($data['title'], $data['headings'], $data['rows'], $data['totals'] ?? []), $filename.'.xlsx', ExcelFormat::XLSX),
            'csv' => Excel::download(new ReportExport($data['title'], $data['headings'], $data['rows'], $data['totals'] ?? []), $filename.'.csv', ExcelFormat::CSV),
        };
    }

    /** Doctors are always limited to their own data. */
    private function doctorScope(Request $request): ?int
    {
        return $request->user()->hasRole('doctor') ? $request->user()->doctor->id : ($request->input('doctor_id') ?: null);
    }

    private function dailyAppointments(Request $request): array
    {
        $date = Carbon::parse($request->input('date', today()));
        $doctorId = $this->doctorScope($request);

        $rows = Appointment::with('patient.user', 'doctor.user', 'doctor.department')
            ->whereDate('appointment_date', $date)
            ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId))
            ->when($request->input('department_id'), fn ($q, $d) => $q->whereHas('doctor', fn ($x) => $x->where('department_id', $d)))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('start_time')->get();

        return [
            'title' => 'Daily Appointment List',
            'subtitle' => $date->format('l, d F Y'),
            'headings' => ['Time', 'Patient No', 'Patient', 'Phone', 'Doctor', 'Department', 'Reason', 'Status'],
            'rows' => $rows->map(fn ($a) => [
                $a->time_range, $a->patient->patient_no, $a->patient->name, $a->patient->user->phone,
                $a->doctor->name, $a->doctor->department->name, $a->reason, $a->status->label(),
            ])->all(),
            'summary' => [
                'Total appointments' => $rows->count(),
                'Completed' => $rows->where('status', AppointmentStatus::Completed)->count(),
                'Cancelled' => $rows->where('status', AppointmentStatus::Cancelled)->count(),
                'No show' => $rows->where('status', AppointmentStatus::NoShow)->count(),
            ],
        ];
    }

    private function doctorSchedule(Request $request): array
    {
        $from = Carbon::parse($request->input('date_from', today()));
        $to = Carbon::parse($request->input('date_to', today()->addDays(6)));
        $doctorId = $this->doctorScope($request);

        $rows = Appointment::with('patient.user', 'doctor.user')
            ->blocking()
            ->whereBetween('appointment_date', [$from->toDateString(), $to->toDateString()])
            ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId))
            ->orderBy('doctor_id')->orderBy('appointment_date')->orderBy('start_time')->get();

        return [
            'title' => 'Doctor Schedule',
            'subtitle' => ($doctorId ? Doctor::with('user')->find($doctorId)->name.' · ' : 'All doctors · ').$from->format('d M Y').' – '.$to->format('d M Y'),
            'headings' => ['Doctor', 'Date', 'Day', 'Time', 'Patient No', 'Patient', 'Status'],
            'rows' => $rows->map(fn ($a) => [
                $a->doctor->name, $a->appointment_date->format('Y-m-d'), $a->appointment_date->format('l'), $a->time_range,
                $a->patient->patient_no, $a->patient->name, $a->status->label(),
            ])->all(),
            'summary' => $rows->groupBy(fn ($a) => $a->doctor->name)->map->count()->all() + ['Total' => $rows->count()],
        ];
    }

    private function payments(Request $request): array
    {
        $from = Carbon::parse($request->input('date_from', today()->startOfMonth()));
        $to = Carbon::parse($request->input('date_to', today()));

        $rows = Payment::with('invoice.patient.user', 'receiver')
            ->whereDate('paid_at', '>=', $from)->whereDate('paid_at', '<=', $to)
            ->when($request->input('method'), fn ($q, $m) => $q->where('method', $m))
            ->when($request->input('received_by'), fn ($q, $r) => $q->where('received_by', $r))
            ->orderBy('paid_at')->get();

        $total = $rows->sum('amount');

        return [
            'title' => 'Payment Report',
            'subtitle' => $from->format('d M Y').' – '.$to->format('d M Y'),
            'headings' => ['Date', 'Invoice', 'Patient No', 'Patient', 'Method', 'Reference', 'Received by', 'Amount ('.config('clinic.currency').')'],
            'rows' => $rows->map(fn ($p) => [
                $p->paid_at->format('Y-m-d H:i'), $p->invoice->invoice_no, $p->invoice->patient->patient_no, $p->invoice->patient->name,
                $p->method->label(), $p->reference, $p->receiver?->name, money_plain($p->amount),
            ])->all(),
            'totals' => ['', '', '', '', '', '', 'TOTAL', money_plain($total)],
            'summary' => $rows->groupBy(fn ($p) => $p->method->label())->map(fn ($g) => money($g->sum('amount')))->all()
                + ['Transactions' => $rows->count(), 'Total collected' => money($total)],
        ];
    }

    private function patientVisits(Request $request): array
    {
        $patient = Patient::with('user')->findOrFail($request->input('patient_id'));
        $doctorId = $this->doctorScope($request);

        $rows = Appointment::with('doctor.user', 'doctor.department', 'consultation', 'invoice')
            ->where('patient_id', $patient->id)
            ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId))
            ->when($request->input('date_from'), fn ($q, $d) => $q->whereDate('appointment_date', '>=', $d))
            ->when($request->input('date_to'), fn ($q, $d) => $q->whereDate('appointment_date', '<=', $d))
            ->orderByDesc('appointment_date')->get();

        return [
            'title' => 'Patient Visit Report',
            'subtitle' => "{$patient->name} ({$patient->patient_no})".($patient->age ? " · {$patient->age} yrs" : '').' · '.ucfirst((string) $patient->gender),
            'headings' => ['Date', 'Doctor', 'Department', 'Status', 'Diagnosis', 'Treatment', 'Prescription', 'Billing'],
            'rows' => $rows->map(fn ($a) => [
                $a->appointment_date->format('Y-m-d'), $a->doctor->name, $a->doctor->department->name, $a->status->label(),
                $a->consultation?->diagnosis, $a->consultation?->treatment, $a->consultation?->prescription,
                $a->invoice ? $a->invoice->status->label().' ('.money($a->invoice->amount).')' : '—',
            ])->all(),
            'summary' => [
                'Total visits' => $rows->count(),
                'Completed' => $rows->where('status', AppointmentStatus::Completed)->count(),
                'Missed' => $rows->where('status', AppointmentStatus::NoShow)->count(),
                'Outstanding balance' => money($patient->outstanding_balance),
            ],
        ];
    }
}
