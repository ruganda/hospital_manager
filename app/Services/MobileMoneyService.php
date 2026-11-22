<?php

namespace App\Services;

use App\Enums\MobileMoneyNetwork;
use App\Enums\MobileMoneyStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\MobileMoneyRequest;
use App\Models\Payment;
use App\Models\User;
use App\Payments\GatewayException;
use App\Payments\MobileMoneyGateway;
use App\Payments\VerifyResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Mobile Money (MTN / Airtel) collections:
 *   request()  → sends the "approve on your phone" prompt through the gateway
 *   refresh()  → asks the gateway what happened and, once, records the Payment
 *
 * refresh() is safe to call any number of times (webhook, polling, "check now" button):
 * the request row is locked and only a pending request can be completed.
 */
class MobileMoneyService
{
    /** After this long without an answer a pending request is given up. */
    public const EXPIRE_AFTER_MINUTES = 30;

    public function __construct(private MobileMoneyGateway $gateway) {}

    public function request(Invoice $invoice, MobileMoneyNetwork $network, string $phone, float $amount, ?User $by = null): MobileMoneyRequest
    {
        $amount = round($amount);
        if ($amount < 1) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount to charge.']);
        }
        if ($amount > $invoice->balance + 0.001) {
            throw ValidationException::withMessages(['amount' => 'Amount exceeds the outstanding balance of '.money($invoice->balance).'.']);
        }
        if ($invoice->mobileMoneyRequests()->where('status', MobileMoneyStatus::Pending)->where('created_at', '>', now()->subMinutes(self::EXPIRE_AFTER_MINUTES))->exists()) {
            throw ValidationException::withMessages(['phone' => 'A Mobile Money request for this invoice is still waiting for approval. Check it or wait for it to finish first.']);
        }

        $request = MobileMoneyRequest::create([
            'invoice_id' => $invoice->id,
            'requested_by' => $by?->id,
            'provider' => $this->gateway->name(),
            'tx_ref' => 'CC-'.$invoice->id.'-'.Str::upper(Str::random(12)),
            'network' => $network,
            'phone' => self::normalisePhone($phone),
            'amount' => $amount,
            'currency' => config('clinic.currency', 'UGX'),
            'status' => MobileMoneyStatus::Pending,
        ]);

        $invoice->loadMissing('patient.user');
        try {
            $result = $this->gateway->charge($request, [
                'email' => $invoice->patient->user->email,
                'name' => $invoice->patient->user->name,
            ]);
        } catch (GatewayException $e) {
            $request->update(['status' => MobileMoneyStatus::Failed, 'message' => Str::limit($e->getMessage(), 250), 'completed_at' => now()]);
            throw ValidationException::withMessages(['phone' => $e->getMessage()]);
        }

        $request->update(['message' => Str::limit($result->message, 250), 'redirect_url' => $result->redirectUrl]);

        return $request;
    }

    public function refresh(MobileMoneyRequest $request): MobileMoneyRequest
    {
        if (! $request->isPending()) {
            return $request;
        }

        $result = $this->gateway->verify($request->tx_ref);

        return DB::transaction(function () use ($request, $result) {
            /** @var MobileMoneyRequest $locked */
            $locked = MobileMoneyRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isPending()) {
                return $locked; // another process (webhook / poll) finished it first
            }

            $expired = $locked->created_at->lt(now()->subMinutes(self::EXPIRE_AFTER_MINUTES));

            return match ($result->status) {
                VerifyResult::SUCCESSFUL => $this->complete($locked, $result),
                VerifyResult::FAILED => $this->finish($locked, MobileMoneyStatus::Failed, $result->message ?: 'The payment was declined or cancelled.', $result),
                default => $expired
                    ? $this->finish($locked, MobileMoneyStatus::Failed, 'No approval within '.self::EXPIRE_AFTER_MINUTES.' minutes.', $result)
                    : tap($locked)->update(['message' => Str::limit($result->message ?: 'Waiting for the customer to approve on their phone.', 250)]),
            };
        });
    }

    /** Validate what the provider says was paid, then record the Payment (inside the caller's transaction). */
    private function complete(MobileMoneyRequest $request, VerifyResult $result): MobileMoneyRequest
    {
        if (strtoupper((string) $result->currency) !== strtoupper($request->currency) || (float) $result->amount + 0.001 < (float) $request->amount) {
            return $this->finish($request, MobileMoneyStatus::NeedsReview,
                'Provider reported '.($result->currency ?? '?').' '.number_format((float) $result->amount).' instead of '.$request->currency.' '.number_format((float) $request->amount).'. Check the Flutterwave dashboard before recording it.', $result);
        }

        $invoice = Invoice::whereKey($request->invoice_id)->lockForUpdate()->firstOrFail();
        if ((float) $request->amount > $invoice->balance + 0.001) {
            return $this->finish($request, MobileMoneyStatus::NeedsReview,
                'Money was received but the invoice balance is now '.money($invoice->balance).' (paid another way meanwhile). Refund or apply it manually.', $result);
        }

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            // staff who sent the request "received" it; a patient paying from the portal leaves it empty
            'received_by' => $request->requester?->hasRole('admin', 'cashier') ? $request->requested_by : null,
            'amount' => $request->amount,
            'method' => PaymentMethod::MobileMoney,
            'reference' => $result->providerRef ?: $result->providerId ?: $request->tx_ref,
            'paid_at' => now(),
            'notes' => Str::limit($request->network->label().' via '.ucfirst($request->provider).' · '.$request->tx_ref, 250),
        ]);

        $request->payment_id = $payment->id;

        return $this->finish($request, MobileMoneyStatus::Successful, 'Payment received.', $result);
    }

    private function finish(MobileMoneyRequest $request, MobileMoneyStatus $status, string $message, VerifyResult $result): MobileMoneyRequest
    {
        $request->fill([
            'status' => $status,
            'message' => Str::limit($message, 250),
            'provider_id' => $result->providerId ?? $request->provider_id,
            'provider_ref' => $result->providerRef ?? $request->provider_ref,
            'completed_at' => now(),
        ])->save();

        return $request;
    }

    /** "0772 418 093", "+256772418093", "772418093" → "256772418093". */
    public static function normalisePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (str_starts_with($digits, '256')) {
            return $digits;
        }
        if (str_starts_with($digits, '0')) {
            return '256'.substr($digits, 1);
        }

        return '256'.$digits;
    }

    public static function isValidUgandanMobile(string $phone): bool
    {
        return (bool) preg_match('/^2567\d{8}$/', self::normalisePhone($phone));
    }
}
