<?php

namespace App\Enums;

enum MobileMoneyStatus: string
{
    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';
    case NeedsReview = 'needs_review';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for approval',
            self::Successful => 'Paid',
            self::Failed => 'Failed',
            self::NeedsReview => 'Needs review',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Successful => 'success',
            self::Failed => 'danger',
            self::NeedsReview => 'warning',
        };
    }
}
