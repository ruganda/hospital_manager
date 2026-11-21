<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DoctorSeeder extends Seeder
{
    /** Weekly patterns: [day_of_week => [[start, end], ...]] */
    private const PATTERNS = [
        'full' => [1 => [['08:00', '12:30'], ['14:00', '17:00']], 2 => [['08:00', '12:30'], ['14:00', '17:00']], 3 => [['08:00', '12:30'], ['14:00', '17:00']], 4 => [['08:00', '12:30'], ['14:00', '17:00']], 5 => [['08:00', '12:30'], ['14:00', '17:00']]],
        'mornings' => [1 => [['08:00', '13:00']], 2 => [['08:00', '13:00']], 3 => [['08:00', '13:00']], 4 => [['08:00', '13:00']], 5 => [['08:00', '13:00']], 6 => [['09:00', '12:00']]],
        'mwf' => [1 => [['09:00', '17:00']], 3 => [['09:00', '17:00']], 5 => [['09:00', '17:00']]],
        'tts' => [2 => [['08:00', '16:00']], 4 => [['08:00', '16:00']], 6 => [['09:00', '13:00']]],
    ];

    public function run(): void
    {
        $password = Hash::make('password');
        $dept = Department::pluck('id', 'name');

        // [name, email, department, specialization, fee, pattern, slot minutes]
        $doctors = [
            ['Sarah Nambi', 'doctor@citycare.test', 'General Medicine', 'Medical Officer (General Practice)', 30000, 'demo', 30],
            ['Daniel Mugisha', 'daniel.mugisha@citycare.test', 'Paediatrics', 'Paediatrician', 50000, 'full', 30],
            ['Robert Ssemwogerere', 'robert.ssemwogerere@citycare.test', 'Cardiology', 'Cardiologist', 100000, 'mwf', 45],
            ['Annet Namubiru', 'annet.namubiru@citycare.test', 'Obstetrics & Gynaecology', 'Obstetrician', 60000, 'full', 30],
            ['Brian Tumusiime', 'brian.tumusiime@citycare.test', 'Dermatology', 'Dermatologist', 50000, 'mornings', 20],
            ['Fiona Nabirye', 'fiona.nabirye@citycare.test', 'Dental', 'Dental Surgeon', 40000, 'full', 30],
        ];

        foreach ($doctors as $i => [$name, $email, $department, $spec, $fee, $pattern, $slot]) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'phone' => sprintf('+256 %s 530 %03d', ['772', '701', '782', '752', '776', '704'][$i % 6], 201 + $i),
                'role' => Role::Doctor,
                'password' => $password,
                'email_verified_at' => now(),
                'created_at' => now()->subMonths(8),
            ]);

            $doctor = Doctor::create([
                'user_id' => $user->id,
                'department_id' => $dept[$department],
                'specialization' => $spec,
                'license_no' => sprintf('UMDPC/%05d', 10234 + $i * 17), // Uganda Medical & Dental Practitioners Council reg. no.
                'consultation_fee' => $fee,
                'bio' => "{$spec} with ".rand(4, 22).' years of clinical experience. Trained at '
                    .['Makerere University College of Health Sciences', 'Mbarara University of Science & Technology', 'Gulu University Faculty of Medicine', 'Kampala International University'][$i % 4]
                    .'. Joined CityCare in '.(2014 + $i % 9).'.',
            ]);

            // The demo doctor consults every day so "today" always has data whatever day you run the seeder.
            $weekly = $pattern === 'demo'
                ? array_fill_keys(range(0, 6), [['08:00', '17:00']])
                : self::PATTERNS[$pattern];

            foreach ($weekly as $day => $blocks) {
                foreach ($blocks as [$start, $end]) {
                    $doctor->schedules()->create([
                        'day_of_week' => $day,
                        'start_time' => $start,
                        'end_time' => $end,
                        'slot_minutes' => $slot,
                        'is_active' => true,
                    ]);
                }
            }
        }
    }
}
