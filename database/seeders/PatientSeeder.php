<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\Support\Uganda;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PatientSeeder extends Seeder
{
    public const COUNT = 10;

    public function run(): void
    {
        $faker = fake();
        $password = Hash::make('password');
        $allergies = [null, null, null, null, null, 'Penicillin', 'Sulphur drugs (Septrin)', 'Groundnuts', 'Chloroquine', 'Aspirin', 'Fish'];

        // Demo patient with a known login.
        $this->makePatient([
            'name' => 'Peter Ssebunya',
            'email' => 'patient@citycare.test',
            'phone' => '+256 772 418 093',
            'created_at' => now()->subMonths(7),
        ], [
            'date_of_birth' => '1988-04-12',
            'gender' => 'male',
            'national_id' => 'CM88012345ABCD',
            'address' => 'Plot 14, Ntinda Road, Ntinda, Kampala',
            'blood_group' => 'O+',
            'allergies' => 'Penicillin',
            'emergency_contact_name' => 'Mary Nakato (wife)',
            'emergency_contact_phone' => '+256 701 552 310',
        ], $password);

        $usedEmails = [];
        for ($i = 0; $i < self::COUNT - 1; $i++) {
            $gender = $faker->randomElement(['male', 'female']);
            $name = Uganda::name($gender);
            $dob = $faker->dateTimeBetween('-85 years', '-6 months')->format('Y-m-d');
            do {
                $email = Uganda::email($name);
            } while (isset($usedEmails[$email]));
            $usedEmails[$email] = true;

            // Registration dates skew recent so "new patients" grows month on month.
            $registered = now()->subDays((int) round(pow($faker->randomFloat(4, 0, 1), 1.6) * 330));
            $relation = $faker->randomElement(['spouse', 'parent', 'sibling', 'son', 'daughter']);

            $this->makePatient([
                'name' => $name,
                'email' => $email,
                'phone' => Uganda::phone(),
                'created_at' => $registered,
            ], [
                'date_of_birth' => $dob,
                'gender' => $gender,
                // adults usually have a NIN; young children usually don't
                'national_id' => ((int) substr($dob, 0, 4) <= now()->year - 18 && $faker->boolean(85)) ? Uganda::nin($gender, $dob) : null,
                'address' => Uganda::address(),
                'blood_group' => $faker->boolean(80) ? $faker->randomElement(Patient::BLOOD_GROUPS) : null,
                'allergies' => $faker->randomElement($allergies),
                'emergency_contact_name' => Uganda::name($faker->randomElement(['male', 'female'])).' ('.$relation.')',
                'emergency_contact_phone' => Uganda::phone(),
                'created_at' => $registered,
            ], $password);
        }
    }

    private function makePatient(array $user, array $patient, string $password): void
    {
        $u = User::create($user + [
            'role' => Role::Patient,
            'password' => $password,
            'email_verified_at' => now(),
        ]);

        $u->patient()->create($patient);
    }
}
