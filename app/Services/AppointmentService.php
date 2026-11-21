<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Central place for every rule that prevents double-booking.
 * Controllers, the AJAX slot endpoint and the seeder all go through here.
 */
class AppointmentService
{
    public function __construct(private InvoiceService $invoices) {}

    /**
     * All slots for a doctor on a date, each flagged available or not.
     *
     * @return Collection<int, array{start:string,end:string,available:bool,reason:?string}>
     */
    public function slotsFor(Doctor $doctor, Carbon|string $date, ?int $ignoreAppointmentId = null): Collection
    {
        $date = Carbon::parse($date)->startOfDay();

        $schedules = $doctor->schedules()
            ->where('day_of_week', $date->dayOfWeek)
            ->where('is_active', true)
            ->get();

        $booked = $this->bookedFor($doctor->id, $date, $ignoreAppointmentId);
        $now = now();
        $slots = collect();

        foreach ($schedules as $schedule) {
            $cursor = $date->copy()->setTimeFromTimeString($schedule->start_time);
            $end = $date->copy()->setTimeFromTimeString($schedule->end_time);

            while ($cursor->copy()->addMinutes($schedule->slot_minutes)->lte($end)) {
                $slotStart = $cursor->format('H:i');
                $slotEnd = $cursor->copy()->addMinutes($schedule->slot_minutes)->format('H:i');

                $reason = null;
                if ($cursor->lt($now)) {
                    $reason = 'past';
                } elseif ($booked->contains(fn ($b) => $this->overlaps($slotStart, $slotEnd, $b['start'], $b['end']))) {
                    $reason = 'booked';
                }

                $slots->push(['start' => $slotStart, 'end' => $slotEnd, 'available' => $reason === null, 'reason' => $reason]);
                $cursor->addMinutes($schedule->slot_minutes);
            }
        }

        return $slots->sortBy('start')->values();
    }

    public function availableSlots(Doctor $doctor, Carbon|string $date, ?int $ignoreAppointmentId = null): Collection
    {
        return $this->slotsFor($doctor, $date, $ignoreAppointmentId)->where('available', true)->values();
    }

    /** Create a booking safely (locks the doctor row so concurrent bookings are serialised). */
    public function book(array $data, ?User $bookedBy = null): Appointment
    {
        return DB::transaction(function () use ($data, $bookedBy) {
            $doctor = Doctor::whereKey($data['doctor_id'])->lockForUpdate()->firstOrFail();
            [$start, $end] = $this->resolveTimes($doctor, $data['appointment_date'], $data['start_time']);
            $this->assertBookable($doctor, $data['patient_id'], $data['appointment_date'], $start, $end);

            return Appointment::create([
                'patient_id' => $data['patient_id'],
                'doctor_id' => $doctor->id,
                'booked_by' => $bookedBy?->id,
                'appointment_date' => $data['appointment_date'],
                'start_time' => $start,
                'end_time' => $end,
                'status' => AppointmentStatus::Scheduled,
                'reason' => $data['reason'] ?? null,
            ]);
        });
    }

    /** Reschedule / edit an existing booking with the same guarantees. */
    public function update(Appointment $appointment, array $data): Appointment
    {
        return DB::transaction(function () use ($appointment, $data) {
            $doctor = Doctor::whereKey($data['doctor_id'])->lockForUpdate()->firstOrFail();
            [$start, $end] = $this->resolveTimes($doctor, $data['appointment_date'], $data['start_time']);
            $this->assertBookable($doctor, $data['patient_id'], $data['appointment_date'], $start, $end, $appointment->id);

            $appointment->update([
                'patient_id' => $data['patient_id'],
                'doctor_id' => $doctor->id,
                'appointment_date' => $data['appointment_date'],
                'start_time' => $start,
                'end_time' => $end,
                'reason' => $data['reason'] ?? null,
            ]);

            return $appointment;
        });
    }

    public function cancel(Appointment $appointment, ?string $reason = null): void
    {
        $appointment->update([
            'status' => AppointmentStatus::Cancelled,
            'cancellation_reason' => $reason,
        ]);
    }

    public function changeStatus(Appointment $appointment, AppointmentStatus $status): void
    {
        $appointment->update(['status' => $status]);

        if ($status === AppointmentStatus::Completed) {
            $this->invoices->createForAppointment($appointment);
        }
    }

    /** @throws ValidationException */
    public function assertBookable(Doctor $doctor, int|string $patientId, string $date, string $start, string $end, ?int $ignoreId = null): void
    {
        $day = Carbon::parse($date);

        if ($day->copy()->setTimeFromTimeString($start)->isPast()) {
            throw ValidationException::withMessages(['start_time' => 'You cannot book an appointment in the past.']);
        }

        $withinSchedule = $doctor->schedules()
            ->where('day_of_week', $day->dayOfWeek)
            ->where('is_active', true)
            ->where('start_time', '<=', $start.':00')
            ->where('end_time', '>=', $end.':00')
            ->exists();

        if (! $withinSchedule) {
            throw ValidationException::withMessages([
                'start_time' => "{$doctor->name} is not available on {$day->format('l')} at {$start}. Please pick one of the listed slots.",
            ]);
        }

        if ($this->bookedFor($doctor->id, $day, $ignoreId)->contains(fn ($b) => $this->overlaps($start, $end, $b['start'], $b['end']))) {
            throw ValidationException::withMessages([
                'start_time' => "{$doctor->name} already has an appointment between {$start} and {$end}. Choose another slot.",
            ]);
        }

        $patientClash = Appointment::query()
            ->where('patient_id', $patientId)
            ->whereDate('appointment_date', $day)
            ->blocking()
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->get(['start_time', 'end_time'])
            ->contains(fn ($a) => $this->overlaps($start, $end, substr($a->start_time, 0, 5), substr($a->end_time, 0, 5)));

        if ($patientClash) {
            throw ValidationException::withMessages([
                'start_time' => 'This patient already has another appointment at that time.',
            ]);
        }
    }

    /** Half-open interval overlap: [aStart, aEnd) ∩ [bStart, bEnd) ≠ ∅ */
    public function overlaps(string $aStart, string $aEnd, string $bStart, string $bEnd): bool
    {
        return $aStart < $bEnd && $aEnd > $bStart;
    }

    /** @return array{0:string,1:string} start and end as H:i */
    private function resolveTimes(Doctor $doctor, string $date, string $start): array
    {
        $start = substr($start, 0, 5);
        $schedule = $doctor->schedules()
            ->where('day_of_week', Carbon::parse($date)->dayOfWeek)
            ->where('is_active', true)
            ->where('start_time', '<=', $start.':00')
            ->where('end_time', '>', $start.':00')
            ->first();

        $minutes = $schedule?->slot_minutes ?? config('clinic.default_slot_minutes');
        $end = Carbon::parse($date.' '.$start)->addMinutes($minutes)->format('H:i');

        return [$start, $end];
    }

    private function bookedFor(int $doctorId, Carbon $date, ?int $ignoreId): Collection
    {
        return Appointment::query()
            ->where('doctor_id', $doctorId)
            ->whereDate('appointment_date', $date)
            ->blocking()
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->get(['start_time', 'end_time'])
            ->map(fn ($a) => ['start' => substr($a->start_time, 0, 5), 'end' => substr($a->end_time, 0, 5)]);
    }
}
