<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Patient;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $base = Invoice::query()->visibleTo($request->user());

        $totals = (clone $base)->selectRaw('COALESCE(SUM(amount),0) billed, COALESCE(SUM(amount_paid),0) paid')->first();

        $invoices = $base->with('patient.user', 'appointment.doctor.user')
            ->latest()
            ->limit($this->perPage())
            ->get();

        return view('invoices.index', compact('invoices', 'totals'));
    }

    public function create(Request $request): View
    {
        $this->authorize('manage', Invoice::class);

        return view('invoices.form', [
            'invoice' => new Invoice(['patient_id' => $request->input('patient_id'), 'due_date' => today()->addDays(14)]),
            'selectedPatient' => $request->input('patient_id') ? Patient::with('user')->find($request->input('patient_id')) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage', Invoice::class);
        $invoice = Invoice::create($this->validated($request) + ['status' => InvoiceStatus::Unpaid, 'amount_paid' => 0]);

        return redirect()->route('invoices.show', $invoice)->with('success', "Invoice {$invoice->invoice_no} created.");
    }

    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);
        $invoice->load('patient.user', 'appointment.doctor.user', 'payments.receiver', 'mobileMoneyRequests.payment');

        return view('invoices.show', ['invoice' => $invoice, 'methods' => PaymentMethod::cases()]);
    }

    public function edit(Invoice $invoice): View
    {
        $this->authorize('manage', Invoice::class);
        $invoice->load('patient.user');

        return view('invoices.form', ['invoice' => $invoice, 'selectedPatient' => $invoice->patient]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('manage', Invoice::class);
        $data = $this->validated($request);

        if ((float) $data['amount'] < (float) $invoice->amount_paid) {
            return back()->withInput()->withErrors(['amount' => 'Amount cannot be lower than what has already been paid ('.money($invoice->amount_paid).').']);
        }

        $invoice->update($data);
        $invoice->refreshTotals();

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice updated.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->authorize('manage', Invoice::class);
        $invoice->delete();

        return redirect()->route('invoices.index')->with('success', 'Invoice deleted.');
    }

    public function pdf(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);
        $invoice->load('patient.user', 'appointment.doctor.user', 'payments.receiver', 'mobileMoneyRequests.payment');

        return Pdf::loadView('invoices.pdf', compact('invoice'))->download($invoice->invoice_no.'.pdf');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'due_date' => ['nullable', 'date'],
        ]);
    }
}
