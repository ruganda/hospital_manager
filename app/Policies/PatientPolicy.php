<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function view(User $user, Patient $patient): bool
    {
        return match ($user->role->value) {
            'admin', 'receptionist', 'cashier' => true,
            // a doctor may open any patient record they have (or had) an appointment with
            'doctor' => $patient->appointments()->where('doctor_id', $user->doctor?->id)->exists(),
            'patient' => $patient->id === $user->patient?->id,
            default => false,
        };
    }

    public function manage(User $user): bool
    {
        return $user->hasRole('admin', 'receptionist');
    }

    public function delete(User $user, Patient $patient): bool
    {
        return $user->isAdmin();
    }
}
