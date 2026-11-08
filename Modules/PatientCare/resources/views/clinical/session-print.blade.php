<!DOCTYPE html><html><head><meta charset="utf-8"><title>{{ $session->session_number }}</title>
<style>body{font-family:sans-serif;font-size:13px;color:#111}h1{font-size:18px}h2{font-size:14px;border-bottom:1px solid #ccc;margin-top:24px;padding-bottom:4px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ddd;padding:4px 8px;font-size:12px;text-align:left}.meta{color:#555}@media print{button{display:none}}</style></head><body>
<button onclick="window.print()">Print</button>
<h1>{{ $hospital->name ?? 'Hospital' }} — Treatment Session</h1>
<p class="meta">Branch: {{ $session->branch->name ?? '' }}</p>
<table><tr><th>MRN</th><td>{{ $session->patient->mrn }}</td><th>Patient</th><td>{{ $session->patient->full_name }}</td></tr>
<tr><th>Session</th><td>{{ $session->session_number }}</td><th>Plan</th><td>{{ $session->plan->plan_number ?? '—' }}</td></tr>
<tr><th>Date</th><td>{{ $session->session_at->format('d/m/Y H:i') }}</td><th>Physiotherapist</th><td>{{ $session->treatingPhysiotherapist->name ?? '—' }}</td></tr>
<tr><th>Type</th><td>{{ str_replace('_',' ',$session->type) }}</td><th>Status</th><td>{{ $session->status }}</td></tr></table>
<h2>Pre-treatment</h2>
<p><strong>Condition:</strong> {{ $session->pre_not_assessed ? 'Not assessed'.($session->pre_not_assessed_reason ? ' ('.$session->pre_not_assessed_reason.')' : '') : ($session->pre_condition ?? '—') }}</p>
<p><strong>Pain:</strong> {{ $session->pre_pain !== null ? $session->pre_pain.'/10' : '—' }} · <strong>Changes:</strong> {{ $session->pre_changes ?? '—' }}</p>
<h2>Interventions</h2><table><tr><th>Intervention</th><th>Status</th><th>Sets×Reps</th><th>Tolerance</th><th>Observations</th></tr>
@foreach($session->interventions as $i)<tr><td>{{ $i->name }}</td><td>{{ str_replace('_',' ',$i->status) }}{{ $i->not_performed_reason ? ' ('.str_replace('_',' ',$i->not_performed_reason).')' : '' }}</td><td>{{ $i->sets ? $i->sets.'×'.$i->reps : '—' }}</td><td>{{ $i->tolerance ? str_replace('_',' ',$i->tolerance) : '—' }}</td><td>{{ $i->observations }}</td></tr>@endforeach</table>
<h2>Post-treatment</h2>
<p><strong>Pain after:</strong> {{ $session->post_pain !== null ? $session->post_pain.'/10' : '—' }} · <strong>Tolerance:</strong> {{ $session->overall_tolerance ? str_replace('_',' ',$session->overall_tolerance) : '—' }}</p>
<p><strong>Feedback:</strong> {{ $session->post_feedback ?? '—' }} · <strong>Next:</strong> {{ $session->post_next_steps ?? '—' }}</p>
@if($session->notes)<h2>Progress notes</h2>
<p><strong>S:</strong> {{ $session->notes->subjective }}</p><p><strong>O:</strong> {{ $session->notes->objective }}</p>
<p><strong>A:</strong> {{ $session->notes->assessment }}</p><p><strong>P:</strong> {{ $session->notes->plan }}</p>@endif
@if($session->adverseEvents->count())<h2>Adverse events</h2><table><tr><th>Description</th><th>Action</th><th>Escalation</th></tr>
@foreach($session->adverseEvents as $a)<tr><td>{{ $a->description }}</td><td>{{ $a->action_taken }}</td><td>{{ $a->escalation }}</td></tr>@endforeach</table>@endif
<p class="meta">Printed {{ now()->format('d/m/Y H:i') }} · {{ $session->session_number }}</p>
</body></html>
