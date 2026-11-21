<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $invoice->invoice_no }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
    .head { border-bottom: 3px solid #0b6e6e; padding-bottom: 10px; margin-bottom: 20px; }
    .brand { font-size: 20px; font-weight: bold; color: #0b6e6e; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 7px; border-bottom: 1px solid #e3e6ea; text-align: left; }
    .right { text-align: right; }
    .muted { color: #6c757d; }
    .badge { padding: 3px 8px; border-radius: 10px; color: #fff; font-size: 11px; }
    .paid { background: #198754; } .partial { background: #e0a800; } .unpaid { background: #dc3545; }
</style></head>
<body>
<div class="head">
    <table><tr>
        <td style="border:0"><div class="brand">{{ config('clinic.name') }}</div><div class="muted">{{ config('clinic.address') }} · {{ config('clinic.phone') }} · {{ config('clinic.email') }}</div></td>
        <td class="right" style="border:0"><h2 style="margin:0">INVOICE</h2>{{ $invoice->invoice_no }}<br><span class="badge {{ $invoice->status->value }}">{{ strtoupper($invoice->status->label()) }}</span></td>
    </tr></table>
</div>
<table style="margin-bottom:20px"><tr>
    <td style="border:0"><strong>Billed to</strong><br>{{ $invoice->patient->name }}<br>{{ $invoice->patient->patient_no }}<br>{{ $invoice->patient->user->phone }}</td>
    <td class="right" style="border:0">Issued: {{ $invoice->created_at->format('d M Y') }}<br>@if($invoice->due_date)Due: {{ $invoice->due_date->format('d M Y') }}@endif</td>
</tr></table>
<table>
    <thead><tr><th>Description</th><th class="right">Amount</th></tr></thead>
    <tr><td>{{ $invoice->description }}@if($invoice->appointment)<br><span class="muted">{{ $invoice->appointment->appointment_date->format('d M Y') }} · {{ $invoice->appointment->doctor->name }}</span>@endif</td><td class="right">{{ money($invoice->amount) }}</td></tr>
    <tr><th>Total</th><th class="right">{{ money($invoice->amount) }}</th></tr>
    <tr><td>Paid</td><td class="right">{{ money($invoice->amount_paid) }}</td></tr>
    <tr><th>Balance due</th><th class="right">{{ money($invoice->balance) }}</th></tr>
</table>
@if($invoice->payments->isNotEmpty())
    <h4 style="margin-top:25px">Payments received</h4>
    <table>
        <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th>Received by</th><th class="right">Amount</th></tr></thead>
        @foreach($invoice->payments as $p)
            <tr><td>{{ $p->paid_at->format('d M Y H:i') }}</td><td>{{ $p->method->label() }}</td><td>{{ $p->reference }}</td><td>{{ $p->receiver?->name }}</td><td class="right">{{ money($p->amount) }}</td></tr>
        @endforeach
    </table>
@endif
<p class="muted" style="margin-top:40px">Thank you for choosing {{ config('clinic.name') }}. Generated {{ now()->format('d M Y H:i') }}.</p>
</body></html>
