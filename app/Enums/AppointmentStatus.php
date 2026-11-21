<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::CheckedIn => 'Checked in',
            self::NoShow => 'No show',
            default => ucfirst($this->value),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'primary',
            self::CheckedIn => 'info',
            self::Completed => 'success',
            self::Cancelled => 'secondary',
            self::NoShow => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Scheduled => 'fa-calendar',
            self::CheckedIn => 'fa-person-walking-arrow-right',
            self::Completed => 'fa-circle-check',
            self::Cancelled => 'fa-ban',
            self::NoShow => 'fa-user-xmark',
        };
    }

    /** Statuses that occupy a doctor's time slot (used for overlap checks). */
    public static function blocking(): array
    {
        return [self::Scheduled->value, self::CheckedIn->value, self::Completed->value];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
