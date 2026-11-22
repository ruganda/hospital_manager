<?php

namespace App\Http\Controllers;

use App\Enums\MobileMoneyNetwork;
use App\Models\Invoice;
use App\Models\MobileMoneyRequest;
use App\Services\MobileMoneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Staff (cashier/admin) or the patient start a Mobile Money payment for an invoice and follow its progress. */
class MobileMoneyController extends Controller
{
    public function __construct(private MobileMoneyService $service) {}

    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('payMobileMoney', $invoice);

        $data = $request->validate([
            'network' => ['required', Rule::in(MobileMoneyNetwork::values())],
            'phone' => ['required', 'string', 'max:20', function ($attr, $value, $fail) {
                if (! MobileMoneyService::isValidUgandanMobile($value)) {
                    $fail('Enter a Ugandan mobile number, e.g. 0772 123 456.');
                }
            }],
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $mm = $this->service->request(
            $invoice,
            MobileMoneyNetwork::from($data['network']),
            $data['phone'],
            (float) $data['amount'],
            $request->user(),
        );

        // back to wherever it was sent from (invoice page, consultation page)
        return redirect()->back(fallback: route('invoices.show', $invoice))
            ->with('success', 'Payment request sent to '.$mm->display_phone.'. Ask the patient to approve it on their phone.');
    }

    /** Polled by the invoice page while a request is pending; also refreshes from the provider. */
    public function status(MobileMoneyRequest $mobileMoney): JsonResponse
    {
        $this->authorize('followMobileMoney', $mobileMoney->invoice);
        $mobileMoney = $this->service->refresh($mobileMoney);

        return response()->json([
            'status' => $mobileMoney->status->value,
            'label' => $mobileMoney->status->label(),
            'message' => $mobileMoney->message,
            'pending' => $mobileMoney->isPending(),
        ]);
    }

    /** "Check now" button (works without JavaScript). */
    public function refresh(MobileMoneyRequest $mobileMoney): RedirectResponse
    {
        $this->authorize('followMobileMoney', $mobileMoney->invoice);
        $mobileMoney = $this->service->refresh($mobileMoney);

        return redirect()->back(fallback: route('invoices.show', $mobileMoney->invoice_id))
            ->with($mobileMoney->isPending() ? 'info' : ($mobileMoney->status->value === 'successful' ? 'success' : 'warning'),
                $mobileMoney->status->label().': '.$mobileMoney->message);
    }
}
