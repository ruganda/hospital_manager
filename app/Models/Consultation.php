<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consultation extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id', 'patient_id', 'doctor_id', 'recorded_by', 'symptoms', 'diagnosis', 'treatment',
        'prescription', 'notes', 'follow_up_date',
    ];

    protected function casts(): array
    {
        return ['follow_up_date' => 'date'];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /** Who typed the notes; differs from the doctor when an admin entered them on the doctor's behalf. */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role->value) {
            'doctor' => $query->where('doctor_id', $user->doctor?->id ?? 0),
            'patient' => $query->where('patient_id', $user->patient?->id ?? 0),
            default => $query,
        };
    }
}
