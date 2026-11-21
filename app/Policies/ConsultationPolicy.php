<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\User;

class ConsultationPolicy
{
    public function view(User $user, Consultation $consultation): bool
    {
        return match ($user->role->value) {
            'admin' => true,
            'doctor' => $consultation->doctor_id === $user->doctor?->id,
            'patient' => $consultation->patient_id === $user->patient?->id,
            default => false,
        };
    }

    /** Only the doctor who owns the appointment can write its notes. */
    public function create(User $user, Appointment $appointment): bool
    {
        return $user->hasRole('doctor') && $appointment->doctor_id === $user->doctor?->id;
    }

    public function update(User $user, Consultation $consultation): bool
    {
        return $user->hasRole('doctor') && $consultation->doctor_id === $user->doctor?->id;
    }

    public function delete(User $user, Consultation $consultation): bool
    {
        return $user->isAdmin() || $this->update($user, $consultation);
    }
}
