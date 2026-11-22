<?php

namespace App\Payments;

use App\Models\MobileMoneyRequest;
use Illuminate\Support\Facades\Cache;

/**
 * Demo gateway: no network calls, no keys. Simulates the customer approving on their phone.
 *
 *   - approves the payment a few seconds after the request;
 *   - numbers ending in 000 are declined (to demo the failure path).
 */
class FakeGateway implements MobileMoneyGateway
{
    public function __construct(private int $approveAfterSeconds = 6) {}

    public function name(): string
    {
        return 'fake';
    }

    public function charge(MobileMoneyRequest $request, array $customer): ChargeResult
    {
        Cache::put($this->key($request->tx_ref), [
            'at' => now()->timestamp,
            'amount' => (float) $request->amount,
            'currency' => $request->currency,
            'decline' => str_ends_with($request->phone, '000'),
        ], now()->addDay());

        return new ChargeResult('Demo charge started: it will be approved automatically in a few seconds.');
    }

    public function verify(string $txRef): VerifyResult
    {
        $tx = Cache::get($this->key($txRef));
        if (! $tx) {
            return new VerifyResult(VerifyResult::NOT_FOUND, message: 'No transaction was found for this reference.');
        }

        if (now()->timestamp - $tx['at'] < $this->approveAfterSeconds) {
            return new VerifyResult(VerifyResult::PENDING, message: 'Waiting for the customer to approve.');
        }

        if ($tx['decline']) {
            return new VerifyResult(VerifyResult::FAILED, $tx['amount'], $tx['currency'], message: 'Declined by the customer (demo).');
        }

        return new VerifyResult(
            VerifyResult::SUCCESSFUL,
            $tx['amount'],
            $tx['currency'],
            providerId: 'DEMO-'.substr(md5($txRef), 0, 8),
            providerRef: 'DEMO-'.strtoupper(substr(md5($txRef.'ref'), 0, 10)),
            message: 'Approved (demo).',
        );
    }

    private function key(string $txRef): string
    {
        return 'fake-momo:'.$txRef;
    }
}
