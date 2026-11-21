<?php

return [
    'name' => env('APP_NAME', 'CityCare Medical Centre'),
    'short_name' => 'CityCare',
    'tagline' => 'Quality care for every family in Kampala',

    // Uganda Shillings have no minor unit in everyday use, so amounts show without decimals.
    'currency' => env('CLINIC_CURRENCY', 'UGX'),
    'currency_decimals' => (int) env('CLINIC_CURRENCY_DECIMALS', 0),

    'phone' => '+256 414 530 120',
    'mobile' => '+256 772 530 120',
    'email' => 'info@citycare.test',
    'address' => 'Plot 24, Kampala Road, Kampala, Uganda',
    'po_box' => 'P.O. Box 7051, Kampala',
    'hours' => 'Mon–Sat 8:00am – 5:00pm · Emergencies 24/7',

    'default_slot_minutes' => 30,
    'per_page' => 10, // lists show at most this many (latest) records
];
