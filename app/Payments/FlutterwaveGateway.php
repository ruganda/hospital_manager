<?php

namespace App\Payments;

use App\Models\MobileMoneyRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Flutterwave v3 API: Uganda mobile money (MTN / Airtel) in UGX.
 *
 *   POST {base}/charges?type=mobile_money_uganda            start a charge (customer approves on their phone)
 *   GET  {base}/transactions/verify_by_reference?tx_ref=…    check the outcome
 *
 * Authentication: "Authorization: Bearer <secret key>" (test keys start with FLWSECK_TEST-).
 */
class FlutterwaveGateway implements MobileMoneyGateway
{
    public function __construct(
        private string $secretKey,
        private string $baseUrl = 'https://api.flutterwave.com/v3',
        private int $timeout = 30,
    ) {}

    public function name(): string
    {
        return 'flutterwave';
    }

    public function charge(MobileMoneyRequest $request, array $customer): ChargeResult
    {
        $payload = [
            'tx_ref' => $request->tx_ref,
            'amount' => (int) round((float) $request->amount), // UGX has no minor unit
            'currency' => $request->currency,
            'network' => $request->network->value,
            'phone_number' => $request->phone,
            'email' => $customer['email'],
            'fullname' => $customer['name'],
        ];

        try {
            $response = $this->http()->post('/charges?type=mobile_money_uganda', $payload);
        } catch (ConnectionException $e) {
            throw new GatewayException('Could not reach Flutterwave. Check the internet connection and try again.', previous: $e);
        }

        $body = $response->json() ?? [];
        if (! $response->successful() || ($body['status'] ?? null) !== 'success') {
            Log::warning('Flutterwave charge refused', ['tx_ref' => $request->tx_ref, 'http' => $response->status(), 'body' => $body]);
            throw new GatewayException('Flutterwave refused the request: '.($body['message'] ?? 'HTTP '.$response->status()));
        }

        return new ChargeResult(
            message: $body['message'] ?? 'Charge initiated',
            redirectUrl: data_get($body, 'meta.authorization.redirect'),
        );
    }

    public function verify(string $txRef): VerifyResult
    {
        try {
            $response = $this->http()->get('/transactions/verify_by_reference', ['tx_ref' => $txRef]);
        } catch (ConnectionException) {
            return new VerifyResult(VerifyResult::PENDING, message: 'Could not reach Flutterwave; will try again.');
        }

        $body = $response->json() ?? [];
        $data = $body['data'] ?? null;

        if (! $response->successful() || ! is_array($data)) {
            // Flutterwave answers "No transaction was found for this id" until the customer acts.
            return new VerifyResult(VerifyResult::NOT_FOUND, message: $body['message'] ?? null);
        }

        $status = match (strtolower((string) ($data['status'] ?? ''))) {
            'successful' => VerifyResult::SUCCESSFUL,
            'failed', 'cancelled' => VerifyResult::FAILED,
            default => VerifyResult::PENDING,
        };

        return new VerifyResult(
            status: $status,
            amount: isset($data['amount']) ? (float) $data['amount'] : null,
            currency: $data['currency'] ?? null,
            providerId: isset($data['id']) ? (string) $data['id'] : null,
            providerRef: $data['flw_ref'] ?? null,
            message: $data['processor_response'] ?? ($body['message'] ?? null),
        );
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withToken($this->secretKey)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout);
    }
}
