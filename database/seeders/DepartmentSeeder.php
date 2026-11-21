<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['General Medicine', 'fa-stethoscope', 'First point of care for adults: acute illness, chronic disease follow-up and referrals.'],
            ['Paediatrics', 'fa-baby', 'Child health from newborn to adolescence, immunisation and growth monitoring.'],
            ['Cardiology', 'fa-heart-pulse', 'Heart and blood-pressure clinic, ECG and cardiovascular risk management.'],
            ['Obstetrics & Gynaecology', 'fa-person-pregnant', 'Antenatal care, family planning and women\'s health.'],
            ['Dermatology', 'fa-hand-dots', 'Skin, hair and nail conditions.'],
            ['Dental', 'fa-tooth', 'Check-ups, fillings, extractions and oral hygiene.'],
        ];

        foreach ($departments as [$name, $icon, $description]) {
            Department::create(compact('name', 'icon', 'description'));
        }
    }
}
