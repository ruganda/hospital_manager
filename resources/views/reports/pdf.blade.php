<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $title }}</title>
<style>
    @page { margin: 28px 30px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #222; }
    .head { border-bottom: 3px solid #0b6e6e; padding-bottom: 8px; margin-bottom: 14px; }
    .brand { font-size: 17px; font-weight: bold; color: #0b6e6e; }
    h1 { font-size: 15px; margin: 4px 0 2px; }
    .muted { color: #6c757d; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th { background: #0f2a44; color: #fff; text-align: left; padding: 6px; font-size: 9.5px; }
    table.data td { padding: 5px 6px; border-bottom: 1px solid #e3e6ea; vertical-align: top; }
    table.data tr:nth-child(even) td { background: #f6f8fb; }
    .summary { margin-top: 14px; width: 45%; border-collapse: collapse; }
    .summary td { padding: 4px 6px; border-bottom: 1px solid #e3e6ea; }
    .total td { font-weight: bold; background: #e7f1ff !important; }
    .footer { position: fixed; bottom: -10px; left: 0; right: 0; font-size: 9px; color: #6c757d; text-align: center; }
</style></head>
<body>
<div class="head">
    <div class="brand">{{ config('clinic.name') }}</div>
    <h1>{{ $title }}</h1>
    <div class="muted">{{ $subtitle }} · generated {{ now()->format('d M Y H:i') }} by {{ auth()->user()->name }}</div>
</div>

<table class="data">
    <thead><tr>@foreach($headings as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse($rows as $row)
        <tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
    @empty
        <tr><td colspan="{{ count($headings) }}" class="muted" style="text-align:center;padding:20px">No records for the selected filters.</td></tr>
    @endforelse
    @if(!empty($totals))
        <tr class="total">@foreach($totals as $cell)<td>{{ $cell }}</td>@endforeach</tr>
    @endif
    </tbody>
</table>

@if(!empty($summary))
    <table class="summary">
        @foreach($summary as $label => $value)
            <tr><td>{{ $label }}</td><td style="text-align:right"><strong>{{ $value }}</strong></td></tr>
        @endforeach
    </table>
@endif

<div class="footer">{{ config('clinic.name') }} · {{ config('clinic.address') }} · Confidential</div>
</body></html>
