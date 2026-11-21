<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        $staff = [
            ['Andrew Mukasa', 'admin@citycare.test', Role::Admin, '+256 772 530 101'],
            ['Juliet Namutebi', 'reception@citycare.test', Role::Receptionist, '+256 701 530 102'],
            ['Ivan Kizza', 'reception2@citycare.test', Role::Receptionist, '+256 782 530 103'],
            ['Brenda Nalwoga', 'cashier@citycare.test', Role::Cashier, '+256 752 530 104'],
            ['Edward Ssali', 'cashier2@citycare.test', Role::Cashier, '+256 776 530 105'],
        ];

        foreach ($staff as [$name, $email, $role, $phone]) {
            User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'role' => $role,
                'password' => $password,
                'email_verified_at' => now(),
                'created_at' => now()->subMonths(8),
            ]);
        }

        // An inactive account to demonstrate that deactivated users cannot log in.
        User::create([
            'name' => 'Agnes Namata',
            'email' => 'inactive@citycare.test',
            'phone' => '+256 704 530 109',
            'role' => Role::Receptionist,
            'password' => $password,
            'is_active' => false,
            'email_verified_at' => now(),
        ]);
    }
}
