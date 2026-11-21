<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Invoice;

class InvoiceService
{
    /** Completed appointments are billed automatically at the doctor's consultation fee. */
    public function createForAppointment(Appointment $appointment): Invoice
    {
        $appointment->loadMissing('doctor.department', 'doctor.user');

        return Invoice::firstOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'patient_id' => $appointment->patient_id,
                'description' => 'Consultation – '.$appointment->doctor->name.' ('.$appointment->doctor->department->name.')',
                'amount' => $appointment->doctor->consultation_fee,
                'amount_paid' => 0,
                'status' => 'unpaid',
                'due_date' => $appointment->appointment_date->copy()->addDays(14),
            ]
        );
    }
}
