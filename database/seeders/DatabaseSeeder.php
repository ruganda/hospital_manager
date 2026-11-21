<?php

namespace Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * php artisan migrate:fresh --seed
     *
     * Every account uses the password: password
     */
    public function run(): void
    {
        Model::unguard(); // allow back-dated created_at values

        $this->call([
            StaffSeeder::class,       // admin, receptionists, cashiers
            DepartmentSeeder::class,  // 6 departments
            DoctorSeeder::class,      // 12 doctors + weekly schedules
            PatientSeeder::class,     // 80 patients (+ demo patient)
            ClinicActivitySeeder::class, // ~6 months of appointments, consultations, invoices, payments
        ]);

        Model::reguard();
    }
}
