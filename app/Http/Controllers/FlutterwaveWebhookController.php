<?php

namespace App\Http\Controllers;

use App\Models\MobileMoneyRequest;
use App\Services\MobileMoneyService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * POST /webhooks/flutterwave
 *
 * Flutterwave sends the "secret hash" set in its dashboard in the verif-hash header.
 * The payload is only a hint: the outcome is always re-checked with the API (MobileMoneyService::refresh)
 * before any payment is recorded. Always answers 200 for authentic calls, as Flutterwave requires.
 */
class FlutterwaveWebhookController extends Controller
{
    public function __invoke(Request $request, MobileMoneyService $service): Response
    {
        $expected = (string) config('services.flutterwave.secret_hash');
        if ($expected === '' || ! hash_equals($expected, (string) $request->header('verif-hash', ''))) {
            Log::warning('Flutterwave webhook rejected: bad or missing verif-hash', ['ip' => $request->ip()]);

            return response('', 401);
        }

        $txRef = (string) ($request->input('data.tx_ref') ?? $request->input('txRef') ?? '');
        $mm = $txRef !== '' ? MobileMoneyRequest::where('tx_ref', $txRef)->first() : null;

        if ($mm) {
            $service->refresh($mm);
        } else {
            Log::info('Flutterwave webhook for unknown tx_ref', ['tx_ref' => $txRef, 'event' => $request->input('event')]);
        }

        return response('', 200);
    }
}
