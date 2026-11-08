<!DOCTYPE html><html><head><meta charset="utf-8"><title>{{ $plan->plan_number }}</title>
<style>body{font-family:sans-serif;font-size:13px;color:#111}h1{font-size:18px}h2{font-size:14px;border-bottom:1px solid #ccc;margin-top:24px;padding-bottom:4px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ddd;padding:4px 8px;font-size:12px;text-align:left}.meta{color:#555}@media print{button{display:none}}</style></head><body>
<button onclick="window.print()">Print</button>
<h1>{{ $hospital->name ?? 'Hospital' }} — Physiotherapy Treatment Plan</h1>
<p class="meta">Branch: {{ $plan->branch->name ?? '' }}</p>
<table><tr><th>MRN</th><td>{{ $plan->patient->mrn }}</td><th>Patient</th><td>{{ $plan->patient->full_name }}</td></tr>
<tr><th>Plan</th><td>{{ $plan->plan_number }}</td><th>Assessment</th><td>{{ $plan->encounter->encounter_number ?? '—' }}</td></tr>
<tr><th>Physiotherapist</th><td>{{ $plan->physiotherapist->name ?? '—' }}</td><th>Approved by</th><td>{{ $plan->approvedBy->name ?? '—' }}{{ $plan->approved_at ? ' · '.$plan->approved_at->format('d/m/Y') : '' }}</td></tr>
<tr><th>Status</th><td>{{ $plan->status }}</td><th>Review</th><td>{{ $plan->review_at?->format('d/m/Y') }}</td></tr></table>
<h2>Clinical indication</h2><p>{{ $plan->clinical_indication }}</p>
<h2>Problems</h2><table><tr><th>Problem</th><th>Region</th><th>Baseline</th><th>Priority</th></tr>
@foreach($plan->problems as $p)<tr><td>{{ $p->description }}</td><td>{{ $p->body_region }}</td><td>{{ $p->baseline }}</td><td>{{ $p->priority }}</td></tr>@endforeach</table>
<h2>Goals</h2><table><tr><th>Goal</th><th>Type</th><th>Baseline</th><th>Target</th><th>By</th></tr>
@foreach($plan->goals as $g)<tr><td>{{ $g->description }}</td><td>{{ str_replace('_',' ',$g->type) }}</td><td>{{ $g->baseline }}</td><td>{{ $g->target }} {{ $g->unit }}</td><td>{{ $g->target_date?->format('d/m/Y') }}</td></tr>@endforeach</table>
<h2>Planned interventions</h2><table><tr><th>Intervention</th><th>Region</th><th>Sets×Reps</th><th>Frequency</th><th>Precautions</th></tr>
@foreach($plan->plannedInterventions as $i)<tr><td>{{ $i->intervention->name ?? 'Custom' }}</td><td>{{ $i->body_region }}</td><td>{{ $i->sets }}×{{ $i->reps }}</td><td>{{ $i->frequency }}</td><td>{{ $i->precautions }}</td></tr>@endforeach</table>
@if($plan->precautionRecords->count())<h2>Precautions</h2><table><tr><th>Type</th><th>Description</th></tr>
@foreach($plan->precautionRecords as $p)<tr><td>{{ $p->type }}</td><td>{{ $p->description }}</td></tr>@endforeach</table>@endif
<p class="meta">Frequency: {{ $plan->sessions_per_week }}/week · {{ $plan->expected_sessions }} sessions · Printed {{ now()->format('d/m/Y H:i') }}</p>
</body></html>
