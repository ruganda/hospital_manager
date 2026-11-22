<?php

namespace Tests\Feature;

use App\Enums\MobileMoneyStatus;
use App\Models\Invoice;
use App\Models\MobileMoneyRequest;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Mobile Money via Flutterwave (faked HTTP) and the demo "fake" driver. */
class MobileMoneyTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    /** What the faked verify endpoint currently answers. */
    private array $verify = ['status' => 'pending'];

    private function useFlutterwave(bool $fakeHttp = true): void
    {
        config([
            'services.mobile_money.driver' => 'flutterwave',
            'services.flutterwave.secret_key' => 'FLWSECK_TEST-unit',
            'services.flutterwave.secret_hash' => 'whsec-unit',
            'services.flutterwave.base_url' => 'https://api.flutterwave.com/v3',
        ]);

        if (! $fakeHttp) {
            return;
        }

        Http::fake(function (HttpRequest $request) {
            if (str_contains($request->url(), '/charges')) {
                return Http::response(['status' => 'success', 'message' => 'Charge initiated',
                    'meta' => ['authorization' => ['redirect' => 'https://checkout.example/authorize/abc', 'mode' => 'redirect']]]);
            }
            if (str_contains($request->url(), '/transactions/verify_by_reference')) {
                if (($this->verify['status'] ?? null) === 'missing') {
                    return Http::response(['status' => 'error', 'message' => 'No transaction was found for this id', 'data' => null], 404);
                }
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);

                return Http::response(['status' => 'success', 'message' => 'Transaction fetched successfully', 'data' => array_merge([
                    'id' => 4242, 'tx_ref' => $q['tx_ref'] ?? '', 'flw_ref' => 'FLW-MOCK-123', 'currency' => 'UGX', 'payment_type' => 'mobilemoneyuganda',
                ], $this->verify)]);
            }

            return Http::response([], 404);
        });
    }

    private function openInvoice(): Invoice
    {
        $invoice = Invoice::where('status', '!=', 'paid')->with('patient.user')->firstOrFail();
        $this->assertGreaterThan(0, $invoice->balance);

        return $invoice;
    }

    private function cashier(): User
    {
        return User::where('email', 'cashier@citycare.test')->firstOrFail();
    }

    private function requestPayment(Invoice $invoice, ?User $as = null, array $overrides = [])
    {
        return $this->actingAs($as ?? $this->cashier())->post(route('mobile-money.store', $invoice), array_merge([
            'network' => 'MTN', 'phone' => '0772 123 456', 'amount' => (int) round($invoice->balance),
        ], $overrides));
    }

    public function test_cashier_request_is_sent_to_flutterwave_with_the_documented_payload(): void
    {
        $this->useFlutterwave();
        $invoice = $this->openInvoice();

        $this->requestPayment($invoice)->assertRedirect(route('invoices.show', $invoice))->assertSessionHasNoErrors();

        $mm = MobileMoneyRequest::firstOrFail();
        $this->assertSame(MobileMoneyStatus::Pending, $mm->status);
        $this->assertSame('256772123456', $mm->phone);
        $this->assertSame('https://checkout.example/authorize/abc', $mm->redirect_url);

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST'
            && $r->url() === 'https://api.flutterwave.com/v3/charges?type=mobile_money_uganda'
            && $r->hasHeader('Authorization', 'Bearer FLWSECK_TEST-unit')
            && $r['tx_ref'] === $mm->tx_ref
            && $r['currency'] === 'UGX'
            && $r['network'] === 'MTN'
            && $r['phone_number'] === '256772123456'
            && $r['amount'] === (int) round($invoice->balance)
            && $r['email'] === $invoice->patient->user->email);

        // the invoice page shows the pending request with the test-mode link
        $this->actingAs($this->cashier())->get(route('invoices.show', $invoice))
            ->assertOk()->assertSee('Waiting for approval')->assertSee('Complete test payment')->assertSee('data-momo-poll', false);
    }

    public function test_successful_verification_records_one_payment_and_settles_the_invoice(): void
    {
        $this->useFlutterwave();
        $invoice = $this->openInvoice();
        $due = (int) round($invoice->balance);
        $this->requestPayment($invoice);
        $mm = MobileMoneyRequest::firstOrFail();

        $this->verify = ['status' => 'successful', 'amount' => $due];
        $this->actingAs($this->cashier())->getJson(route('mobile-money.status', $mm))
            ->assertOk()->assertJson(['status' => 'successful', 'pending' => false]);

        $mm->refresh();
        $this->assertSame(MobileMoneyStatus::Successful, $mm->status);
        $payment = Payment::findOrFail($mm->payment_id);
        $this->assertSame('mobile_money', $payment->method->value);
        $this->assertSame('FLW-MOCK-123', $payment->reference);
        $this->assertSame($this->cashier()->id, $payment->received_by);
        $this->assertSame('paid', $invoice->fresh()->status->value);

        // polling again, a webhook, or "check now" never records it twice
        $this->actingAs($this->cashier())->getJson(route('mobile-money.status', $mm))->assertOk();
        $this->post(route('webhooks.flutterwave'), ['event' => 'charge.completed', 'data' => ['tx_ref' => $mm->tx_ref]], ['verif-hash' => 'whsec-unit'])->assertOk();
        $this->assertSame(1, Payment::where('reference', 'FLW-MOCK-123')->count());
    }

    public function test_webhook_requires_the_secret_hash_and_reverifies_with_the_api(): void
    {
        $this->useFlutterwave();
        $invoice = $this->openInvoice();
        $this->requestPayment($invoice);
        $mm = MobileMoneyRequest::firstOrFail();
        $payload = ['event' => 'charge.completed', 'data' => ['tx_ref' => $mm->tx_ref, 'status' => 'successful', 'amount' => 999999999]];

        $this->post(route('webhooks.flutterwave'), $payload)->assertStatus(401);
        $this->post(route('webhooks.flutterwave'), $payload, ['verif-hash' => 'wrong'])->assertStatus(401);
        $this->assertTrue($mm->fresh()->isPending());

        // the payload claims success, but the API still says pending: nothing is recorded
        $this->post(route('webhooks.flutterwave'), $payload, ['verif-hash' => 'whsec-unit'])->assertOk();
        $this->assertTrue($mm->fresh()->isPending());

        $this->verify = ['status' => 'successful', 'amount' => (int) round($invoice->balance)];
        $this->post(route('webhooks.flutterwave'), $payload, ['verif-hash' => 'whsec-unit'])->assertOk();
        $this->assertSame(MobileMoneyStatus::Successful, $mm->fresh()->status);

        // unknown references are acknowledged and ignored
        $this->post(route('webhooks.flutterwave'), ['data' => ['tx_ref' => 'nope']], ['verif-hash' => 'whsec-unit'])->assertOk();
    }

    public function test_declined_payment_is_marked_failed_without_a_payment(): void
    {
        $this->useFlutterwave();
        $invoice = $this->openInvoice();
        $before = $invoice->balance;
        $this->requestPayment($invoice);
        $mm = MobileMoneyRequest::firstOrFail();

        $this->verify = ['status' => 'failed', 'amount' => (int) round($before), 'processor_response' => 'Insufficient funds'];
        $this->actingAs($this->cashier())->post(route('mobile-money.refresh', $mm))->assertRedirect();

        $mm->refresh();
        $this->assertSame(MobileMoneyStatus::Failed, $mm->status);
        $this->assertSame('Insufficient funds', $mm->message);
        $this->assertNull($mm->payment_id);
        $this->assertEquals($before, $invoice->fresh()->balance);
    }

    public function test_short_payment_or_already_settled_invoice_goes_to_review(): void
    {
        $this->useFlutterwave();
        $invoice = $this->openInvoice();
        $due = (int) round($invoice->balance);

        // provider reports less than requested
        $this->requestPayment($invoice);
        $mm = MobileMoneyRequest::firstOrFail();
        $this->verify = ['status' => 'successful', 'amount' => $due - 1];
        $this->actingAs($this->cashier())->getJson(route('mobile-money.status', $mm));
        $this->assertSame(MobileMoneyStatus::NeedsReview, $mm->fresh()->status);
        $this->assertNull($mm->fresh()->payment_id);

        // invoice paid in cash while the patient was approving
        $this->requestPayment($invoice);
        $second = MobileMoneyRequest::latest('id')->firstOrFail();
        Payment::create(['invoice_id' => $invoice->id, 'amount' => $invoice->fresh()->balance, 'method' => 'cash', 'paid_at' => now()]);
        $this->verify = ['status' => 'successful', 'amount' => $due];
        $this->actingAs($this->cashier())->getJson(route('mobile-money.status', $second));
        $this->assertSame(MobileMoneyStatus::NeedsReview, $second->fresh()->status);
        $this->assertNull($second->fresh()->payment_id);
        $this->assertSame(0, Payment::where('invoice_id', $invoice->id)->where('reference', 'FLW-MOCK-123')->count());
    }

    public function test_provider_refusal_is_shown_to_staff(): void
    {
        $this->useFlutterwave(fakeHttp: false);
        Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Invalid authorization key'], 401)]);
        $invoice = $this->openInvoice();

        $this->requestPayment($invoice)->assertSessionHasErrors('phone');
        $this->assertSame(MobileMoneyStatus::Failed, MobileMoneyRequest::firstOrFail()->status);
    }

    public function test_validation_and_permissions(): void
    {
        $this->useFlutterwave();
        $invoice = $this->openInvoice();

        $this->requestPayment($invoice, overrides: ['phone' => '12345'])->assertSessionHasErrors('phone');
        $this->requestPayment($invoice, overrides: ['amount' => (int) $invoice->balance + 1000])->assertSessionHasErrors('amount');
        $this->requestPayment($invoice, overrides: ['network' => 'MPESA'])->assertSessionHasErrors('network');

        // receptionists can view invoices but not take money
        $this->requestPayment($invoice, User::where('email', 'reception@citycare.test')->first())->assertForbidden();

        // a patient can pay their own bill, not someone else's
        $patientUser = $invoice->patient->user;
        $this->requestPayment($invoice, $patientUser)->assertRedirect()->assertSessionHasNoErrors();
        $mm = MobileMoneyRequest::latest('id')->firstOrFail();
        $this->assertSame($patientUser->id, $mm->requested_by);

        // when it succeeds, a self-service payment has no cashier as "received by"
        $this->verify = ['status' => 'successful', 'amount' => (int) round($invoice->balance)];
        $this->actingAs($patientUser)->getJson(route('mobile-money.status', $mm))->assertJson(['status' => 'successful']);
        $this->assertNull(Payment::findOrFail($mm->fresh()->payment_id)->received_by);
        $invoice = Invoice::where('status', '!=', 'paid')->where('patient_id', '!=', $invoice->patient_id)->firstOrFail();
        $this->verify = ['status' => 'pending'];

        $this->requestPayment($invoice, $patientUser)->assertForbidden(); // someone else's bill

        // only one pending request per invoice at a time
        $this->requestPayment($invoice)->assertSessionHasNoErrors();
        $this->requestPayment($invoice)->assertSessionHasErrors('phone');

        // paid invoices cannot be charged
        $paid = Invoice::where('status', 'paid')->first();
        if ($paid) {
            $this->requestPayment($paid)->assertForbidden();
        }
    }

    public function test_fake_driver_simulates_approval_and_decline(): void
    {
        config(['services.mobile_money.driver' => 'fake']);
        $invoice = $this->openInvoice();

        $this->requestPayment($invoice, overrides: ['phone' => '0772 000 000'])->assertSessionHasNoErrors();
        $declined = MobileMoneyRequest::firstOrFail();
        $this->assertSame('fake', $declined->provider);
        $this->travel(10)->seconds();
        $this->actingAs($this->cashier())->getJson(route('mobile-money.status', $declined))->assertJson(['status' => 'failed']);

        $this->requestPayment($invoice)->assertSessionHasNoErrors();
        $approved = MobileMoneyRequest::latest('id')->firstOrFail();
        $this->actingAs($this->cashier())->getJson(route('mobile-money.status', $approved))->assertJson(['pending' => true]);
        $this->travel(10)->seconds();
        $this->actingAs($this->cashier())->getJson(route('mobile-money.status', $approved))->assertJson(['status' => 'successful']);
        $this->assertSame('paid', $invoice->fresh()->status->value);
    }

    public function test_stale_pending_request_expires(): void
    {
        $this->useFlutterwave();
        $invoice = $this->openInvoice();
        $this->requestPayment($invoice);
        $mm = MobileMoneyRequest::firstOrFail();

        $this->verify = ['status' => 'missing'];
        $this->travel(31)->minutes();
        $this->actingAs($this->cashier())->getJson(route('mobile-money.status', $mm))->assertJson(['status' => 'failed']);
    }
}
