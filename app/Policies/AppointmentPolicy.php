<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        return match ($user->role->value) {
            'admin', 'receptionist', 'cashier' => true,
            'doctor' => $appointment->doctor_id === $user->doctor?->id,
            'patient' => $appointment->patient_id === $user->patient?->id,
            default => false,
        };
    }

    public function manage(User $user): bool
    {
        return $user->hasRole('admin', 'receptionist');
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->manage($user) && $appointment->isEditable();
    }

    public function changeStatus(User $user, Appointment $appointment): bool
    {
        return $this->manage($user) || ($user->hasRole('doctor') && $appointment->doctor_id === $user->doctor?->id);
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $user->isAdmin();
    }
}
