<!DOCTYPE html><html><head><meta charset="utf-8"><title>Statement</title>
<style>body{font-family:sans-serif;font-size:13px;max-width:700px;margin:20px auto}table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid #ddd;padding:6px;text-align:left}@media print{button{display:none}}</style></head><body>
<button onclick="window.print()">Print</button>
<h2>{{ $hospital->name ?? 'Hospital' }} — {{ $branch->name }}</h2>
<p><strong>Patient:</strong> {{ $patient->first_name }} {{ $patient->last_name }} · {{ $patient->mrn }} · <strong>Date:</strong> {{ now()->format('d/m/Y') }}</p>
<table><thead><tr><th>Invoice</th><th>Date</th><th>Total</th><th>Credited</th><th>Outstanding</th><th>Status</th></tr></thead>
<tbody>@foreach($invoices as $i)<tr><td>{{ $i->invoice_number }}</td><td>{{ $i->issue_date?->format('d/m/Y') }}</td><td>{{ number_format($i->total, 2) }}</td><td>{{ number_format($i->creditNotes->where('status','issued')->sum('amount'), 2) }}</td><td>{{ number_format(max(0, $i->total - $i->creditNotes->where('status','issued')->sum('amount')), 2) }}</td><td>{{ str_replace('_', ' ', $i->status) }}</td></tr>@endforeach</tbody></table>
<p style="text-align:right"><strong>Total outstanding: TZS {{ number_format($outstanding, 2) }}</strong></p>
<p><em>Payment records not yet integrated (M3).</em></p>
</body></html>
