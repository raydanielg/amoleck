<!DOCTYPE html><html><head><meta charset="utf-8"><title>{{ $encounter->encounter_number }}</title>
<style>body{font-family:sans-serif;font-size:13px;color:#111}h1{font-size:18px}h2{font-size:14px;border-bottom:1px solid #ccc;margin-top:24px;padding-bottom:4px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ddd;padding:4px 8px;text-align:left;font-size:12px}.meta{color:#555}@media print{button{display:none}}</style>
</head><body>
<button onclick="window.print()">Print</button>
<h1>{{ $hospital->name ?? 'Hospital' }} — Physiotherapy Assessment</h1>
<p class="meta">{{ $hospital->address ?? '' }} · Branch: {{ $encounter->branch->name ?? '' }}</p>
<table><tr><th>MRN</th><td>{{ $encounter->patient->mrn }}</td><th>Name</th><td>{{ $encounter->patient->full_name }}</td></tr>
<tr><th>Encounter</th><td>{{ $encounter->encounter_number }}</td><th>Type</th><td>{{ str_replace('_',' ',$encounter->type) }}</td></tr>
<tr><th>Date</th><td>{{ $encounter->encounter_at->format('d/m/Y H:i') }}</td><th>Physiotherapist</th><td>{{ $encounter->physiotherapist->name ?? '—' }}</td></tr>
<tr><th>Status</th><td colspan="3">{{ $encounter->status }}</td></tr></table>

@if($encounter->assessment)
<h2>History of Present Illness</h2>
<p><strong>Mechanism:</strong> {{ $encounter->assessment->mechanism }}</p>
<p><strong>Onset:</strong> {{ $encounter->assessment->onset_type }} · <strong>Progression:</strong> {{ $encounter->assessment->progression }}</p>
<p><strong>Expectations:</strong> {{ $encounter->assessment->patient_expectations }}</p>
<h2>Summary</h2>
<p><strong>Subjective:</strong> {{ $encounter->assessment->subjective_summary }}</p>
<p><strong>Objective:</strong> {{ $encounter->assessment->objective_summary }}</p>
<p><strong>Interpretation:</strong> {{ $encounter->assessment->interpretation }}</p>
<p><strong>Recommendations:</strong> {{ $encounter->assessment->recommendations }}</p>
@endif

@if($encounter->complaints->count())<h2>Presenting Complaints</h2><table><tr><th>Complaint</th><th>Onset</th><th>Aggravating</th><th>Relieving</th></tr>
@foreach($encounter->complaints as $c)<tr><td>{{ $c->complaint }}</td><td>{{ $c->onset_date?->format('d/m/Y') }}</td><td>{{ $c->aggravating }}</td><td>{{ $c->relieving }}</td></tr>@endforeach</table>@endif

@if($encounter->painAssessments->count())<h2>Pain (NPRS)</h2><table><tr><th>Location</th><th>Current</th><th>Best</th><th>Worst</th><th>Type</th></tr>
@foreach($encounter->painAssessments as $p)<tr><td>{{ $p->location }}</td><td>{{ $p->not_assessable ? 'N/A' : $p->nprs_current }}</td><td>{{ $p->nprs_best }}</td><td>{{ $p->nprs_worst }}</td><td>{{ $p->pain_type }}</td></tr>@endforeach</table>@endif

@if($encounter->functionalEntries->count())<h2>Functional</h2><table><tr><th>Activity</th><th>Level</th></tr>
@foreach($encounter->functionalEntries as $f)<tr><td>{{ $f->activity }}</td><td>{{ str_replace('_',' ',$f->level) }}</td></tr>@endforeach</table>@endif

@if($encounter->objectiveFindings->count())<h2>Objective Findings</h2><table><tr><th>Category</th><th>Finding</th></tr>
@foreach($encounter->objectiveFindings as $o)<tr><td>{{ $o->category }}</td><td>{{ $o->finding }}</td></tr>@endforeach</table>@endif

@if($encounter->romMeasurements->count())<h2>ROM</h2><table><tr><th>Joint</th><th>Movement</th><th>Side</th><th>Active</th><th>Passive</th><th>Pain</th></tr>
@foreach($encounter->romMeasurements as $r)<tr><td>{{ $r->joint }}</td><td>{{ $r->movement }}</td><td>{{ $r->side }}</td><td>{{ $r->active_rom }}{{ $r->unit==='degrees'?'°':$r->unit }}</td><td>{{ $r->passive_rom }}{{ $r->unit==='degrees'?'°':$r->unit }}</td><td>{{ $r->pain_during?'Y':'' }}</td></tr>@endforeach</table>@endif

@if($encounter->strengthMeasurements->count())<h2>Strength (MMT)</h2><table><tr><th>Muscle</th><th>Side</th><th>Grade</th></tr>
@foreach($encounter->strengthMeasurements as $m)<tr><td>{{ $m->muscle_group }}</td><td>{{ $m->side }}</td><td>{{ $m->grade }}/5</td></tr>@endforeach</table>@endif

@if($encounter->redFlags->count())<h2>Red Flags / Precautions</h2><table><tr><th>Category</th><th>Description</th><th>Action</th></tr>
@foreach($encounter->redFlags as $r)<tr><td>{{ $r->category }}</td><td>{{ $r->description }}</td><td>{{ $r->action_taken }}</td></tr>@endforeach</table>@endif

@if($encounter->problems->count())<h2>Problems</h2><table><tr><th>Description</th><th>Region</th><th>Status</th></tr>
@foreach($encounter->problems as $p)<tr><td>{{ $p->description }}</td><td>{{ $p->body_region }}</td><td>{{ $p->status }}</td></tr>@endforeach</table>@endif

<p class="meta">Printed {{ now()->format('d/m/Y H:i') }} · {{ $encounter->encounter_number }}</p>
</body></html>
