<?php

namespace App\Payments;

final readonly class VerifyResult
{
    public const SUCCESSFUL = 'successful';
    public const FAILED = 'failed';
    public const PENDING = 'pending';
    public const NOT_FOUND = 'not_found';

    public function __construct(
        public string $status,
        public ?float $amount = null,
        public ?string $currency = null,
        public ?string $providerId = null,
        public ?string $providerRef = null,
        public ?string $message = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === self::SUCCESSFUL;
    }
}
