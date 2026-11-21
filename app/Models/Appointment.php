<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id', 'doctor_id', 'booked_by', 'appointment_date', 'start_time', 'end_time',
        'status', 'reason', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'status' => AppointmentStatus::class,
        ];
    }

    /** Always store times as HH:MM:SS so comparisons behave the same on MySQL and SQLite. */
    public function setStartTimeAttribute($value): void
    {
        $this->attributes['start_time'] = self::normalizeTime($value);
    }

    public function setEndTimeAttribute($value): void
    {
        $this->attributes['end_time'] = self::normalizeTime($value);
    }

    public static function normalizeTime(?string $value): ?string
    {
        return $value === null ? null : (strlen($value) === 5 ? $value.':00' : $value);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    public function consultation(): HasOne
    {
        return $this->hasOne(Consultation::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function getTimeRangeAttribute(): string
    {
        return substr($this->start_time, 0, 5).' – '.substr($this->end_time, 0, 5);
    }

    public function getStartsAtAttribute(): Carbon
    {
        return Carbon::parse($this->appointment_date->toDateString().' '.$this->start_time);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [AppointmentStatus::Scheduled, AppointmentStatus::CheckedIn], true);
    }

    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('status', AppointmentStatus::blocking());
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('appointment_date', '>=', today())
            ->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::CheckedIn->value]);
    }

    /** Restrict results to what the given user is allowed to see. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role->value) {
            'doctor' => $query->where('doctor_id', $user->doctor?->id ?? 0),
            'patient' => $query->where('patient_id', $user->patient?->id ?? 0),
            default => $query,
        };
    }
}
