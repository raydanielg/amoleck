<!DOCTYPE html><html><head><meta charset="utf-8"><title>{{ $discharge->discharge_number }}</title>
<style>body{font-family:sans-serif;font-size:13px}h1{font-size:18px}h2{font-size:14px;border-bottom:1px solid #ccc;margin-top:24px;padding-bottom:4px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ddd;padding:4px 8px;font-size:12px;text-align:left}.meta{color:#555}@media print{button{display:none}}</style></head><body>
<button onclick="window.print()">Print</button>
<h1>{{ $hospital->name ?? 'Hospital' }} — Physiotherapy Discharge Summary</h1>
<table><tr><th>MRN</th><td>{{ $discharge->patient->mrn }}</td><th>Patient</th><td>{{ $discharge->patient->full_name }}</td></tr>
<tr><th>Discharge</th><td>{{ $discharge->discharge_number }}</td><th>Type</th><td>{{ str_replace('_',' ',$discharge->type) }}</td></tr>
<tr><th>Physiotherapist</th><td>{{ $discharge->physiotherapist->name ?? '—' }}</td><th>Approved by</th><td>{{ $discharge->approvedBy->name ?? '—' }}</td></tr>
<tr><th>Treatment plan</th><td>{{ $discharge->plan->plan_number ?? '—' }}</td><th>Discharge date</th><td>{{ $discharge->discharge_on?->format('d/m/Y') ?? '—' }}</td></tr>
<tr><th>Sessions</th><td>{{ $sessions }}</td><th>Status</th><td>{{ $discharge->status }}</td></tr></table>
<h2>Initial status</h2><p>{{ $discharge->initial_status ?? '—' }}</p>
<h2>Final status</h2><p>{{ $discharge->final_status ?? '—' }}</p>
<p><strong>Pain:</strong> {{ $discharge->baseline_pain ?? '—' }} → {{ $discharge->final_pain ?? '—' }} /10</p>
<h2>Goal achievement</h2><p>{{ $discharge->goal_achievement ?? '—' }}</p>
@if($discharge->outstanding_concerns)<h2>Outstanding concerns</h2><p>{{ $discharge->outstanding_concerns }}</p>@endif
@if($discharge->hep_recommendation)<h2>Home program</h2><p>{{ $discharge->hep_recommendation }}</p>@endif
@if($discharge->follow_up)<h2>Follow-up</h2><p>{{ $discharge->follow_up }}</p>@endif
<p class="meta">Printed {{ now()->format('d/m/Y H:i') }}</p>
</body></html>
