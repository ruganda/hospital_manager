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

    public function manage(User $user): bool
    {
        return $user->hasRole('admin', 'cashier');
    }
}
