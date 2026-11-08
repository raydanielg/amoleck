<!DOCTYPE html><html><head><meta charset="utf-8"><title>{{ $payment->payment_number }}</title>
<style>body{font-family:monospace;font-size:13px;max-width:320px;margin:20px auto;text-align:center}h2{margin:8px 0}@media print{button{display:none}}</style></head><body>
<button onclick="window.print()">Print</button>
<h2>{{ $hospital->name ?? 'Hospital' }}</h2>
<p>{{ $payment->branch->name }}</p>
<p><strong>OFFICIAL RECEIPT</strong></p>
<p>{{ $payment->payment_number }} · {{ $payment->confirmed_at?->format('d/m/Y H:i') }}</p>
<p>{{ $payment->patient->first_name }} {{ $payment->patient->last_name }} · {{ $payment->patient->mrn }}</p>
@if($payment->invoice)<p>Invoice: {{ $payment->invoice->invoice_number }}</p>@endif
<p>{{ $payment->currency }} {{ number_format($payment->amount, 2) }} · {{ ucfirst(str_replace('_',' ',$payment->method)) }}</p>
@if($payment->reference)<p>Ref: {{ $payment->reference }}</p>@endif
<p>Cashier: {{ $payment->recordedBy->name }}</p>
<p>Received with thanks.</p>
</body></html>
