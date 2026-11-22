<?php

namespace App\Payments;

use App\Models\MobileMoneyRequest;

/**
 * A provider that can push a "approve this payment" prompt to a customer's phone
 * and report back what happened. Implementations: Flutterwave (real) and Fake (demo / tests).
 */
interface MobileMoneyGateway
{
    /** Short machine name stored on each request (e.g. "flutterwave"). */
    public function name(): string;

    /**
     * Ask the provider to charge the customer's wallet.
     *
     * @param  array{email: string, name: string}  $customer
     *
     * @throws GatewayException when the provider refuses the request
     */
    public function charge(MobileMoneyRequest $request, array $customer): ChargeResult;

    /** Look a transaction up by our own reference. Never throws for "not found". */
    public function verify(string $txRef): VerifyResult;
}
