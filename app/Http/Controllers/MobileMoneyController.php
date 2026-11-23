<?php

namespace App\Http\Controllers;

use App\Enums\MobileMoneyNetwork;
use App\Enums\MobileMoneyStatus;
use App\Models\Invoice;
use App\Models\MobileMoneyRequest;
use App\Services\MobileMoneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Staff (cashier/admin) or the patient start a Mobile Money payment for an invoice and follow its progress. */
class MobileMoneyController extends Controller
{
    public function __construct(private MobileMoneyService $service) {}

    /** All Mobile Money requests (admin / cashier), newest first. */
    public function index(): View
    {
        $requests = MobileMoneyRequest::query()
            ->with('invoice.patient.user', 'requester', 'payment')
            ->latest()
            ->limit(100)
            ->get();

        $today = MobileMoneyRequest::where('status', MobileMoneyStatus::Successful)->whereDate('completed_at', today());

        return view('mobile-money.index', [
            'requests' => $requests,
            'stats' => [
                'pending' => MobileMoneyRequest::where('status', MobileMoneyStatus::Pending)->count(),
                'review' => MobileMoneyRequest::where('status', MobileMoneyStatus::NeedsReview)->count(),
                'today_count' => (clone $today)->count(),
                'today_amount' => (clone $today)->sum('amount'),
            ],
        ]);
    }

    /** From an invoice page or a consultation page: goes back there. */
    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $mm = $this->requestFor($request, $invoice);

        // back to wherever it was sent from (invoice page, consultation page)
        return redirect()->back(fallback: route('invoices.show', $invoice))
            ->with('success', 'Payment request sent to '.$mm->display_phone.'. Ask the patient to approve it on their phone.');
    }

    /** From Payments → Record payment: the invoice is chosen in the form; follow progress on the invoice page. */
    public function collect(Request $request): RedirectResponse
    {
        $invoiceId = $request->validate(['invoice_id' => ['required', 'integer', 'exists:invoices,id']], [], ['invoice_id' => 'invoice'])['invoice_id'];
        $invoice = Invoice::findOrFail($invoiceId);
        if ($invoice->balance <= 0) {
            throw ValidationException::withMessages(['invoice_id' => $invoice->invoice_no.' is already paid.']);
        }

        $mm = $this->requestFor($request, $invoice);

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Payment request sent to '.$mm->display_phone.'. This page updates by itself once the patient approves.');
    }

    private function requestFor(Request $request, Invoice $invoice): MobileMoneyRequest
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

        return $this->service->request(
            $invoice,
            MobileMoneyNetwork::from($data['network']),
            $data['phone'],
            (float) $data['amount'],
            $request->user(),
        );
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
