<?php

namespace App\Payments;

final readonly class ChargeResult
{
    public function __construct(
        public string $message,
        /** Some providers (e.g. Flutterwave test mode) return a page where the payment is authorised. */
        public ?string $redirectUrl = null,
    ) {}
}
