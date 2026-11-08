<!DOCTYPE html><html><head><meta charset="utf-8"><title>{{ $program->hep_number }}</title>
<style>body{font-family:sans-serif;font-size:13px}h1{font-size:18px}h2{font-size:14px;border-bottom:1px solid #ccc;margin-top:24px;padding-bottom:4px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ddd;padding:4px 8px;font-size:12px;text-align:left}.meta{color:#555}@media print{button{display:none}}</style></head><body>
<button onclick="window.print()">Print</button>
<h1>{{ $hospital->name ?? 'Hospital' }} — Home Exercise Program / Programu ya Mazoezi</h1>
<table><tr><th>MRN</th><td>{{ $program->patient->mrn }}</td><th>Patient</th><td>{{ $program->patient->full_name }}</td></tr>
<tr><th>Program</th><td>{{ $program->hep_number }}</td><th>Physiotherapist</th><td>{{ $program->physiotherapist->name ?? '—' }}</td></tr>
<tr><th>Issued</th><td>{{ $program->issued_on->format('d/m/Y') }}</td><th>Review</th><td>{{ $program->review_on?->format('d/m/Y') ?? '—' }}</td></tr></table>
<h2>Exercises / Mazoezi</h2><table><tr><th>Exercise</th><th>Sets×Reps</th><th>Hold</th><th>Frequency</th><th>Instructions</th></tr>
@foreach($program->exercises as $e)<tr><td>{{ $e->exercise->name ?? $e->custom_name }}</td><td>{{ $e->sets ? $e->sets.'×'.$e->reps : '—' }}</td><td>{{ $e->hold_duration ?? '—' }}</td><td>{{ $e->frequency ?? '—' }}{{ $e->days_per_week ? ' · '.$e->days_per_week : '' }}</td><td>{{ $e->patient_instructions ?? '—' }}</td></tr>@endforeach</table>
@if($program->general_instructions)<h2>Instructions</h2><p>{{ $program->general_instructions }}</p>@endif
@if($program->emergency_instructions)<h2>Emergency</h2><p>{{ $program->emergency_instructions }}</p>@endif
<p class="meta">Printed {{ now()->format('d/m/Y H:i') }}</p>
</body></html>
