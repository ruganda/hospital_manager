<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasRole('admin', 'cashier', 'receptionist')
            || ($user->hasRole('patient') && $invoice->patient_id === $user->patient?->id);
    }

    /** Send a Mobile Money prompt: billing staff, the patient for their own bill, or the doctor who saw the patient. */
    public function payMobileMoney(User $user, Invoice $invoice): bool
    {
        return $invoice->balance > 0 && (
            $user->hasRole('admin', 'cashier')
            || ($user->hasRole('patient') && $invoice->patient_id === $user->patient?->id)
            || $this->isTreatingDoctor($user, $invoice)
        );
    }

    /** See the progress of Mobile Money requests for this invoice. */
    public function followMobileMoney(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice) || $this->isTreatingDoctor($user, $invoice);
    }

    private function isTreatingDoctor(User $user, Invoice $invoice): bool
    {
        return $user->hasRole('doctor')
            && $invoice->appointment_id !== null
            && $invoice->appointment?->doctor_id === $user->doctor?->id;
    }

    public function manage(User $user): bool
    {
        return $user->hasRole('admin', 'cashier');
    }
}
