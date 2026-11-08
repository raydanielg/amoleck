<!DOCTYPE html><html><head><meta charset="utf-8"><title>{{ $entry->ticket_number ?? '' }}</title>
<style>body{font-family:monospace;font-size:13px;text-align:center;max-width:300px;margin:20px auto}h2{font-size:24px}@media print{button{display:none}}</style></head><body>
<button onclick="window.print()">Print</button>
<p><strong>{{ $hospital->name ?? 'Hospital' }}</strong></p>
<p>{{ $visit->branch->name ?? '' }} · {{ $entry->queueType->name ?? '' }}</p>
<h2>{{ $entry->ticket_number ?? '—' }}</h2>
<p>{{ $visit->patient->first_name }} {{ $visit->patient->last_name }}</p>
<p>Visit: {{ $visit->visit_number }}</p>
<p>{{ $visit->created_at->format('d/m/Y H:i') }}</p>
<p>Please wait to be called.</p>
</body></html>
