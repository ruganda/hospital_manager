<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Receptionist = 'receptionist';
    case Doctor = 'doctor';
    case Cashier = 'cashier';
    case Patient = 'patient';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Admin => 'fa-user-shield',
            self::Receptionist => 'fa-headset',
            self::Doctor => 'fa-user-doctor',
            self::Cashier => 'fa-cash-register',
            self::Patient => 'fa-hospital-user',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Receptionist => 'info',
            self::Doctor => 'primary',
            self::Cashier => 'warning',
            self::Patient => 'success',
        };
    }

    /** Roles whose accounts are managed from the Users module (doctors & patients have their own modules). */
    public static function staff(): array
    {
        return [self::Admin, self::Receptionist, self::Cashier];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
