<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Generates ~6 months of realistic clinic activity so every dashboard chart has data.
 *
 * Appointments are created from each doctor's real schedule slots and tracked in memory
 * (doctor + patient busy maps), so the seed data never contains overlapping bookings.
 * Rows are built in memory and bulk-inserted in chunks to keep seeding fast.
 */
class ClinicActivitySeeder extends Seeder
{
    private const DAYS_BACK = 60;

    private const DAYS_AHEAD = 7;

    private const VISITS = [
        // [reason, symptoms, diagnosis, treatment, prescription]
        /* 0 */ ['Fever and headache', 'Fever 38.6°C, headache, joint pains and chills for 3 days', 'Malaria (uncomplicated) — mRDT positive', 'Blood smear positive for P. falciparum. Oral ACT started, plenty of fluids.', 'Artemether/Lumefantrine (Coartem) 80/480mg BD x 3 days; Paracetamol 1g TDS x 3 days'],
        /* 1 */ ['Cough and flu', 'Dry cough, runny nose, sore throat, mild fever', 'Upper respiratory tract infection', 'Supportive care, steam inhalation, warm fluids and rest.', 'Paracetamol 1g TDS x 5 days; Cetirizine 10mg OD x 5 days'],
        /* 2 */ ['Pressure (BP) review', 'Occasional headaches, no chest pain', 'Essential hypertension — controlled', 'BP 132/84 mmHg. Continue medication, reduce salt, regular exercise.', 'Nifedipine retard 20mg BD x 30 days'],
        /* 3 */ ['Sugar (diabetes) review', 'Frequent urination improved, no dizziness', 'Type 2 diabetes mellitus', 'RBS 8.1 mmol/L. Diet counselling (less posho and sugary drinks).', 'Metformin 500mg BD x 30 days'],
        /* 4 */ ['Stomach pain (ulcers)', 'Epigastric pain after meals, heartburn', 'Peptic ulcer disease', 'H. pylori stool antigen requested. Avoid NSAIDs, alcohol and spicy food.', 'Omeprazole 20mg BD x 14 days; Magnesium trisilicate PRN'],
        /* 5 */ ['Skin rash', 'Itchy circular patches on arms for 2 weeks', 'Tinea corporis (ringworm)', 'Keep skin dry, avoid sharing towels.', 'Clotrimazole 1% cream BD x 4 weeks'],
        /* 6 */ ['Child immunisation', 'Well child, routine visit (UNEPI schedule)', 'Routine immunisation — up to date', 'Vaccines given per UNEPI schedule, growth chart normal. Vitamin A given.', 'None'],
        /* 7 */ ['Child with diarrhoea', 'Loose stools x5/day for 2 days, some dehydration', 'Acute gastroenteritis with some dehydration', 'ORS demonstrated to mother, zinc supplementation, continue breastfeeding.', 'ORS sachets; Zinc 20mg OD x 10 days'],
        /* 8 */ ['Antenatal visit (ANC)', '24 weeks gestation, good fetal movements', 'Normal pregnancy — 2nd trimester', 'Fundal height appropriate, FHR 142 bpm. IPTp given, mosquito net advised.', 'Ferrous sulphate + Folic acid OD; Sulfadoxine-Pyrimethamine (IPTp) 3 tabs stat'],
        /* 9 */ ['Chest pain on exertion', 'Chest tightness when climbing stairs', 'Stable angina — for further evaluation', 'ECG done, lipid profile and echo requested. Lifestyle advice.', 'Aspirin 75mg OD; Atorvastatin 20mg nocte'],
        /* 10 */ ['Toothache', 'Pain lower left molar, sensitive to cold drinks', 'Dental caries with reversible pulpitis', 'Restoration done (composite filling).', 'Ibuprofen 400mg TDS x 3 days'],
        /* 11 */ ['Dental check-up', 'Routine check, bleeding gums when brushing', 'Gingivitis', 'Scaling and polishing; oral hygiene instruction.', 'Chlorhexidine mouthwash BD x 7 days'],
        /* 12 */ ['Back pain', 'Lower back pain after lifting, no radiation', 'Mechanical low back pain', 'Physiotherapy exercises, posture advice for boda boda riding.', 'Diclofenac 50mg BD x 5 days'],
        /* 13 */ ['Painful urination', 'Burning urination, frequency', 'Urinary tract infection', 'Urinalysis: nitrites positive. Increase fluid intake.', 'Nitrofurantoin 100mg BD x 5 days'],
        /* 14 */ ['Pimples on face', 'Facial papules and pustules', 'Acne vulgaris — moderate', 'Skin-care routine discussed, avoid bleaching creams.', 'Benzoyl peroxide 5% gel nocte; Doxycycline 100mg OD x 6 weeks'],
        /* 15 */ ['Family planning', 'Requests contraception counselling', 'Family planning counselling', 'Options discussed; injectable method chosen.', 'Depo-Provera (DMPA) 150mg IM, next dose in 3 months'],
        /* 16 */ ['Heart palpitations', 'Intermittent fast heartbeat, no fainting', 'Sinus tachycardia — anxiety related', 'ECG normal, thyroid function test requested, reassurance.', 'None — review with results'],
        /* 17 */ ['General check-up', 'No complaints, company medical', 'Healthy adult — routine screening', 'BMI 24, BP 118/76. HIV counselling & testing offered, routine labs done.', 'None'],
        /* 18 */ ['Prolonged fever', 'Fever for 1 week, abdominal pain, loss of appetite', 'Typhoid fever (suspected)', 'Blood culture taken, Widal & stool antigen done. Boil drinking water.', 'Ciprofloxacin 500mg BD x 7 days; Paracetamol 1g TDS'],
        /* 19 */ ['Child with fever', 'Fever and vomiting since yesterday, active child', 'Malaria in a child — mRDT positive', 'Weight-based ACT, tepid sponging, return if danger signs.', 'Artemether/Lumefantrine (Coartem) weight-based x 3 days; Paracetamol syrup'],
        /* 20 */ ['Itchy skin', 'Itchy skin worse at night, family members affected', 'Scabies', 'Treat all household contacts, wash bedding in hot water.', 'Benzyl benzoate 25% lotion x 2 nights; Cetirizine 10mg OD'],
    ];

    private const DEPARTMENT_VISITS = [
        'General Medicine' => [0, 0, 1, 2, 3, 4, 12, 13, 17, 18],
        'Paediatrics' => [19, 19, 1, 6, 7],
        'Cardiology' => [2, 9, 16],
        'Obstetrics & Gynaecology' => [8, 8, 13, 15],
        'Dermatology' => [5, 14, 20],
        'Dental' => [10, 11],
    ];

    /** Lab / procedure charges in Uganda Shillings. */
    private const EXTRA_CHARGES = [
        ['Malaria test (mRDT / blood smear)', 5000], ['Complete blood count (CBC)', 20000], ['Widal test (typhoid)', 10000],
        ['Urinalysis', 8000], ['Random blood sugar', 5000], ['Lipid profile', 45000], ['ECG', 60000],
        ['Abdominal ultrasound scan', 50000], ['Obstetric ultrasound scan', 40000], ['Dental X-ray', 30000], ['Wound dressing', 10000],
    ];

    /** in-memory row buffers */
    private array $appointments = [];

    private array $consultations = [];

    private array $invoices = [];

    private array $payments = [];

    /** busy maps: doctor "id|Y-m-d|H:i" => true, patient "id|Y-m-d" => true */
    private array $doctorBusy = [];

    private array $patientBusy = [];

    private int $appointmentId = 0;

    private int $invoiceId = 0;

    private $faker;

    private Carbon $now;

    private array $receptionists = [];

    private array $cashiers = [];

    public function run(): void
    {
        $this->faker = fake();
        $this->now = now();
        $this->receptionists = User::whereIn('role', [Role::Receptionist->value, Role::Admin->value])->where('is_active', true)->pluck('id')->all();
        $this->cashiers = User::where('role', Role::Cashier->value)->pluck('id')->all();
        $this->appointmentId = (int) DB::table('appointments')->max('id');
        $this->invoiceId = (int) DB::table('invoices')->max('id');

        $doctors = Doctor::with('schedules', 'department', 'user')->get();
        $registered = Patient::pluck('created_at', 'id')->map(fn ($c) => $c->toDateString()); // id => Y-m-d
        $patientIds = $registered->keys()->all();
        $today = today();

        foreach ($doctors as $doctor) {
            $isDemoDoctor = $doctor->user->email === 'doctor@citycare.test';

            for ($offset = -self::DAYS_BACK; $offset <= self::DAYS_AHEAD; $offset++) {
                $date = $today->copy()->addDays($offset);
                $slots = $this->slots($doctor, $date);
                if (! $slots) {
                    continue;
                }

                // Light, demo-sized volume: usually 0 bookings a day per doctor,
                // with the demo doctor always having a few patients today.
                $target = match (true) {
                    $offset === 0 && $isDemoDoctor => 3,
                    $offset === 0 => 1,
                    default => $this->faker->boolean(12) ? 1 : 0,
                };

                foreach (collect($slots)->shuffle()->take($target) as $slot) {
                    $patientId = $this->pickPatient($patientIds, $registered, $date, $slot['start']);
                    if (! $patientId) {
                        continue;
                    }
                    $startsAt = $date->copy()->setTimeFromTimeString($slot['start']);
                    $this->addAppointment($doctor, $patientId, $date, $slot, $this->statusFor($startsAt));
                }
            }
        }

        $demoPatient = Patient::whereHas('user', fn ($q) => $q->where('email', 'patient@citycare.test'))->first();
        if ($demoPatient) {
            $this->demoPatientHistory($demoPatient, $doctors);
        }

        $this->extraInvoices($patientIds);

        DB::transaction(function () {
            foreach (['appointments', 'consultations', 'invoices', 'payments'] as $table) {
                foreach (array_chunk($this->$table, 500) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }
        });
    }

    // ------------------------------------------------------------------ builders

    private function addAppointment(Doctor $doctor, int $patientId, Carbon $date, array $slot, AppointmentStatus $status, ?bool $settle = null): void
    {
        $id = ++$this->appointmentId;
        $startsAt = $date->copy()->setTimeFromTimeString($slot['start']);
        $visits = self::DEPARTMENT_VISITS[$doctor->department->name] ?? array_keys(self::VISITS);
        $visit = self::VISITS[$this->faker->randomElement($visits)];
        $booked = $startsAt->copy()->subDays(rand(1, 10))->min($this->now);

        $this->doctorBusy[$doctor->id.'|'.$date->toDateString().'|'.$slot['start']] = true;
        $this->patientBusy[$patientId.'|'.$date->toDateString()] = true; // one visit per patient per day

        $this->appointments[] = [
            'id' => $id,
            'patient_id' => $patientId,
            'doctor_id' => $doctor->id,
            'booked_by' => $this->faker->randomElement($this->receptionists),
            'appointment_date' => $date->toDateString(),
            'start_time' => $slot['start'].':00',
            'end_time' => $slot['end'].':00',
            'status' => $status->value,
            'reason' => $visit[0],
            'cancellation_reason' => $status === AppointmentStatus::Cancelled
                ? $this->faker->randomElement(['Patient requested by phone', 'Patient travelling', 'Doctor unavailable — rescheduled', 'Feeling better'])
                : null,
            'created_at' => $booked,
            'updated_at' => $startsAt->copy()->min($this->now),
        ];

        if ($status !== AppointmentStatus::Completed) {
            return;
        }

        $at = $startsAt->copy()->addMinutes(20)->min($this->now);
        $this->consultations[] = [
            'appointment_id' => $id,
            'patient_id' => $patientId,
            'doctor_id' => $doctor->id,
            'symptoms' => $visit[1],
            'diagnosis' => $visit[2],
            'treatment' => $visit[3],
            'prescription' => $visit[4],
            'notes' => $this->faker->boolean(30) ? $this->faker->randomElement(['Patient counselled on medication adherence.', 'Return if symptoms worsen.', 'Lab results to be reviewed at next visit.']) : null,
            'follow_up_date' => $this->faker->boolean(25) ? $startsAt->copy()->addDays(rand(7, 30))->toDateString() : null,
            'created_at' => $at,
            'updated_at' => $at,
        ];

        $this->addInvoice($patientId, $id, 'Consultation – '.$doctor->name.' ('.$doctor->department->name.')', (float) $doctor->consultation_fee, $at, $settle);
    }

    /**
     * @param  bool|null  $settle  true = fully paid, false = unpaid, null = random mix
     *                             (~75% paid, ~15% partial, ~10% unpaid; recent invoices more likely open)
     */
    private function addInvoice(int $patientId, ?int $appointmentId, string $description, float $amount, Carbon $issuedAt, ?bool $settle = null): void
    {
        $id = ++$this->invoiceId;

        $parts = match (true) {
            $settle === true => [$amount],
            $settle === false => [],
            default => $this->randomPaymentPlan($amount, $issuedAt),
        };

        $paid = 0.0;
        $paidAt = $issuedAt->copy()->addMinutes(rand(5, 40));
        foreach ($parts as $i => $part) {
            if ($i > 0) {
                $paidAt = $paidAt->copy()->addDays(rand(1, 10));
            }
            if ($paidAt->isAfter($this->now)) {
                break;
            }
            $method = $this->faker->randomElement([PaymentMethod::Cash, PaymentMethod::Cash, PaymentMethod::MobileMoney, PaymentMethod::MobileMoney, PaymentMethod::Card, PaymentMethod::Insurance]);
            $this->payments[] = [
                'invoice_id' => $id,
                'received_by' => $this->faker->randomElement($this->cashiers),
                'amount' => $part,
                'method' => $method->value,
                'reference' => $this->reference($method),
                'paid_at' => $paidAt->copy(),
                'notes' => null,
                'created_at' => $paidAt->copy(),
                'updated_at' => $paidAt->copy(),
            ];
            $paid += $part;
        }

        $this->invoices[] = [
            'id' => $id,
            'invoice_no' => 'INV-'.$issuedAt->format('Y').'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT),
            'patient_id' => $patientId,
            'appointment_id' => $appointmentId,
            'description' => $description,
            'amount' => $amount,
            'amount_paid' => $paid,
            'status' => $paid <= 0 ? 'unpaid' : ($paid + 0.001 >= $amount ? 'paid' : 'partial'),
            'due_date' => $issuedAt->copy()->addDays(14)->toDateString(),
            'created_at' => $issuedAt,
            'updated_at' => $issuedAt,
        ];
    }

    private function randomPaymentPlan(float $amount, Carbon $issuedAt): array
    {
        $r = $this->faker->numberBetween(1, 100) + ($issuedAt->diffInDays($this->now) < 7 ? 15 : 0);
        $roundUgx = fn (float $v) => max(500, round($v / 500) * 500);
        $half = $roundUgx($amount * 0.5);

        return match (true) {
            $r <= 75 => $this->faker->boolean(80) ? [$amount] : [$half, $amount - $half],
            $r <= 90 => [min($amount - 500, $roundUgx($amount * $this->faker->randomElement([0.3, 0.5, 0.6])))],
            default => [],
        };
    }

    // ------------------------------------------------------------------ helpers

    /** Payment references in the formats Ugandan cashiers actually record. */
    private function reference(PaymentMethod $method): ?string
    {
        return match ($method) {
            PaymentMethod::Cash => null,
            PaymentMethod::MobileMoney => $this->faker->randomElement(['MTN ', 'Airtel ']).$this->faker->numerify('###########'),
            PaymentMethod::Card => 'POS '.$this->faker->numerify('######').' (**** '.$this->faker->numerify('####').')',
            PaymentMethod::Insurance => $this->faker->randomElement(['Medical scheme', 'Employer scheme', 'Health insurance']).' claim '.$this->faker->numerify('CL-#####'),
        };
    }

    /** Free slots for a doctor on a date, from their weekly schedule. */
    private function slots(Doctor $doctor, Carbon $date): array
    {
        $prefix = $doctor->id.'|'.$date->toDateString().'|';

        return array_values(array_filter(
            $this->weeklySlots($doctor)[$date->dayOfWeek] ?? [],
            fn ($s) => ! isset($this->doctorBusy[$prefix.$s['start']])
        ));
    }

    /** Weekly slot grid per doctor, computed once with plain integer maths (fast). */
    private array $slotCache = [];

    private function weeklySlots(Doctor $doctor): array
    {
        if (isset($this->slotCache[$doctor->id])) {
            return $this->slotCache[$doctor->id];
        }

        $toMin = fn (string $t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
        $fmt = fn (int $m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        $grid = [];

        foreach ($doctor->schedules as $b) {
            if (! $b->is_active) {
                continue;
            }
            $len = $b->slot_minutes;
            for ($m = $toMin($b->start_time), $end = $toMin($b->end_time); $m + $len <= $end; $m += $len) {
                $grid[$b->day_of_week][] = ['start' => $fmt($m), 'end' => $fmt($m + $len)];
            }
        }

        return $this->slotCache[$doctor->id] = $grid;
    }

    private function pickPatient(array $ids, $registered, Carbon $date, string $start): ?int
    {
        for ($try = 0; $try < 8; $try++) {
            $id = $ids[array_rand($ids)];
            // patients can't attend before they registered, nor be in two rooms at once
            if ($registered[$id] > $date->toDateString()) {
                continue;
            }
            if (! isset($this->patientBusy[$id.'|'.$date->toDateString()])) {
                return $id;
            }
        }

        return null;
    }

    private function statusFor(Carbon $startsAt): AppointmentStatus
    {
        if ($startsAt->isAfter($this->now)) {
            return $this->faker->boolean(93) ? AppointmentStatus::Scheduled : AppointmentStatus::Cancelled;
        }

        if ($startsAt->isToday()) {
            // earlier today: mostly done; the last hour: patients in the waiting room
            return $startsAt->diffInMinutes($this->now) < 60
                ? AppointmentStatus::CheckedIn
                : $this->faker->randomElement([AppointmentStatus::Completed, AppointmentStatus::Completed, AppointmentStatus::Completed, AppointmentStatus::NoShow]);
        }

        $r = $this->faker->numberBetween(1, 100);

        return match (true) {
            $r <= 78 => AppointmentStatus::Completed,
            $r <= 87 => AppointmentStatus::NoShow,
            default => AppointmentStatus::Cancelled,
        };
    }

    /** Guarantees the demo patient has visit history, an unpaid bill and upcoming bookings. */
    private function demoPatientHistory(Patient $patient, $doctors): void
    {
        $demoDoctor = $doctors->first(fn ($d) => $d->user->email === 'doctor@citycare.test');
        $dental = $doctors->first(fn ($d) => $d->department->name === 'Dental');
        $cardio = $doctors->first(fn ($d) => $d->department->name === 'Cardiology');
        $other = $doctors->first(fn ($d) => $d->id !== $demoDoctor->id);

        // [doctor, days from today, status, fully paid?]
        $plan = [
            [$other, -50, AppointmentStatus::Completed, true],
            [$dental, -30, AppointmentStatus::Completed, true],
            [$demoDoctor, -20, AppointmentStatus::NoShow, null],
            [$demoDoctor, -9, AppointmentStatus::Completed, false], // left unpaid on purpose
            [$demoDoctor, 2, AppointmentStatus::Scheduled, null],
            [$cardio, 6, AppointmentStatus::Scheduled, null],
        ];

        foreach ($plan as [$doctor, $days, $status, $settle]) {
            for ($shift = 0; $shift < 10; $shift++) {
                $date = today()->addDays($days + $shift);
                $slot = collect($this->slots($doctor, $date))->first(function ($s) use ($patient, $date, $status) {
                    $startsAt = $date->copy()->setTimeFromTimeString($s['start']);
                    $timingOk = $status === AppointmentStatus::Scheduled ? $startsAt->isFuture() : $startsAt->isPast();

                    return $timingOk && ! isset($this->patientBusy[$patient->id.'|'.$date->toDateString()]);
                });
                if ($slot) {
                    $this->addAppointment($doctor, $patient->id, $date, $slot, $status, $settle);
                    break;
                }
            }
        }
    }

    /** Non-consultation charges (lab tests, procedures). */
    private function extraInvoices(array $patientIds): void
    {
        for ($i = 0; $i < 4; $i++) {
            [$desc, $amount] = $this->faker->randomElement(self::EXTRA_CHARGES);
            $at = $this->now->copy()->subDays(rand(0, self::DAYS_BACK))->setTime(rand(8, 16), rand(0, 59));
            if ($at->isFuture()) {
                $at = $this->now->copy()->subHour();
            }
            $this->addInvoice($this->faker->randomElement($patientIds), null, $desc, (float) $amount, $at);
        }
    }
}
