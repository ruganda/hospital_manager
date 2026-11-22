<?php

namespace App\Enums;

enum MobileMoneyNetwork: string
{
    case Mtn = 'MTN';
    case Airtel = 'AIRTEL';

    public function label(): string
    {
        return match ($this) {
            self::Mtn => 'MTN Mobile Money',
            self::Airtel => 'Airtel Money',
        };
    }

    /** Best guess from a Ugandan number (07X…); only used to pre-select the network. */
    public static function guessFromPhone(?string $phone): ?self
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        $local = str_starts_with($digits, '256') ? '0'.substr($digits, 3) : $digits;

        return match (substr($local, 0, 3)) {
            '076', '077', '078', '039', '031' => self::Mtn,
            '070', '074', '075', '020' => self::Airtel,
            default => null,
        };
    }

    public static function values(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }
}
