<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(): View
    {
        $total = Payment::sum('amount');

        $payments = Payment::with('invoice.patient.user', 'receiver')
            ->latest('paid_at')
            ->limit($this->perPage())
            ->get();

        return view('payments.index', compact('payments', 'total'));
    }

    public function create(Request $request): View
    {
        $invoice = $request->input('invoice_id') ? Invoice::with('patient.user')->findOrFail($request->input('invoice_id')) : null;

        return view('payments.form', [
            'payment' => new Payment(['invoice_id' => $invoice?->id, 'amount' => $invoice?->balance, 'paid_at' => now(), 'method' => PaymentMethod::Cash]),
            'invoice' => $invoice,
            'openInvoices' => Invoice::with('patient.user')->where('status', '!=', 'paid')->latest()->limit(200)->get(),
            'methods' => PaymentMethod::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $payment = DB::transaction(function () use ($data, $request) {
            $invoice = Invoice::whereKey($data['invoice_id'])->lockForUpdate()->firstOrFail();
            $this->guardOverpayment($invoice, (float) $data['amount']);

            return Payment::create($data + ['received_by' => $request->user()->id]);
        });

        return redirect()->route('invoices.show', $payment->invoice_id)->with('success', 'Payment of '.money($payment->amount).' recorded.');
    }

    public function edit(Payment $payment): View
    {
        $payment->load('invoice.patient.user');

        return view('payments.form', [
            'payment' => $payment,
            'invoice' => $payment->invoice,
            'openInvoices' => collect(),
            'methods' => PaymentMethod::cases(),
        ]);
    }

    public function update(Request $request, Payment $payment): RedirectResponse
    {
        $data = $this->validated($request);
        unset($data['invoice_id']); // a payment stays on its invoice

        DB::transaction(function () use ($payment, $data) {
            $invoice = Invoice::whereKey($payment->invoice_id)->lockForUpdate()->firstOrFail();
            $this->guardOverpayment($invoice, (float) $data['amount'], (float) $payment->amount);
            $payment->update($data);
        });

        return redirect()->route('invoices.show', $payment->invoice_id)->with('success', 'Payment updated.');
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $invoiceId = $payment->invoice_id;
        $payment->delete();

        return redirect()->route('invoices.show', $invoiceId)->with('success', 'Payment removed and invoice balance recalculated.');
    }

    private function guardOverpayment(Invoice $invoice, float $amount, float $existing = 0): void
    {
        $balance = $invoice->balance + $existing;
        if ($amount > $balance + 0.001) {
            throw ValidationException::withMessages(['amount' => 'Amount exceeds the outstanding balance of '.money($balance).'.']);
        }
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(PaymentMethod::values())],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
