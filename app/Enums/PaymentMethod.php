<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case MobileMoney = 'mobile_money';
    case Card = 'card';
    case Insurance = 'insurance';

    public function label(): string
    {
        return match ($this) {
            self::MobileMoney => 'Mobile Money (MTN/Airtel)',
            default => ucfirst($this->value),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Cash => 'fa-money-bill-wave',
            self::MobileMoney => 'fa-mobile-screen',
            self::Card => 'fa-credit-card',
            self::Insurance => 'fa-shield-heart',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
