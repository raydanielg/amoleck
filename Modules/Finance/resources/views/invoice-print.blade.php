<!DOCTYPE html><html><head><meta charset="utf-8"><title>{{ $invoice->invoice_number }}</title>
<style>body{font-family:sans-serif;font-size:13px;max-width:700px;margin:20px auto}table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid #ddd;padding:6px;text-align:left}h2{margin:0}@media print{button{display:none}}</style></head><body>
<button onclick="window.print()">Print</button>
<h2>{{ $hospital->name ?? 'Hospital' }} — {{ $invoice->branch->name }}</h2>
<p><strong>Invoice:</strong> {{ $invoice->invoice_number }} · <strong>Date:</strong> {{ $invoice->issue_date?->format('d/m/Y') }} · <strong>Due:</strong> {{ $invoice->due_date?->format('d/m/Y') }}</p>
<p><strong>Patient:</strong> {{ $invoice->patient->first_name }} {{ $invoice->patient->last_name }} · {{ $invoice->patient->mrn }}</p>
<table><thead><tr><th>Service</th><th>Qty</th><th>Unit</th><th>Discount</th><th>Tax</th><th>Total</th></tr></thead>
<tbody>@foreach($invoice->items as $i)<tr><td>{{ $i->service_name }}</td><td>{{ $i->quantity }}</td><td>{{ number_format($i->unit_price, 2) }}</td><td>{{ number_format($i->discount, 2) }}</td><td>{{ number_format($i->tax, 2) }}</td><td>{{ number_format($i->line_total, 2) }}</td></tr>@endforeach</tbody></table>
<p style="text-align:right"><strong>Subtotal:</strong> {{ number_format($invoice->subtotal, 2) }} · <strong>Total: {{ $invoice->currency }} {{ number_format($invoice->total, 2) }}</strong></p>
@if($invoice->creditNotes->where('status','issued')->count())<p><strong>Credits applied:</strong> {{ number_format($invoice->creditNotes->where('status','issued')->sum('amount'), 2) }}</p>@endif
<p>Status: {{ str_replace('_', ' ', $invoice->status) }}</p>
</body></html>
