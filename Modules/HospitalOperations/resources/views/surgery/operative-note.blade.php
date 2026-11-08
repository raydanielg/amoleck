<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Operative Note — {{ $note->case->case_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .head { border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 12px; }
        .grid td { padding: 2px 10px 2px 0; font-size: 11px; }
        .sec { margin-top: 10px; }
        .sec h3 { font-size: 12px; margin: 0 0 4px; border-bottom: 1px solid #ccc; }
        .sec p { margin: 0; white-space: pre-wrap; }
        .meta { color: #555; font-size: 10px; margin-top: 14px; }
    </style>
</head>
<body>
    <div class="head">
        <strong>{{ $hospital->name ?? 'Hospital' }}</strong> — {{ $note->case->branch->name ?? '' }}<br>
        OPERATIVE NOTE — Case {{ $note->case->case_number }} · Operation {{ $note->operation->operation_number }}
    </div>
    <table class="grid">
        <tr><td><b>Patient</b></td><td>{{ $note->case->patient->first_name }} {{ $note->case->patient->last_name }} ({{ $note->case->patient->mrn }})</td></tr>
        <tr><td><b>Surgeon</b></td><td>{{ $note->operation->surgeon->name ?? '—' }}</td><td><b>Anesthetist</b></td><td>{{ $note->operation->anesthetist->name ?? '—' }}</td></tr>
        <tr><td><b>Procedure(s)</b></td><td colspan="3">{{ $note->operation->procedures->pluck('procedure_name')->join('; ') }}</td></tr>
        <tr><td><b>Times</b></td><td colspan="3">in {{ optional($note->operation->patient_in_at)->format('Y-m-d H:i') }} · start {{ optional($note->operation->procedure_start_at)->format('H:i') }} · end {{ optional($note->operation->procedure_end_at)->format('H:i') }} · out {{ optional($note->operation->patient_out_at)->format('H:i') }}</td></tr>
    </table>
    @foreach([['Pre-operative diagnosis','preop_diagnosis'],['Post-operative diagnosis','postop_diagnosis'],['Findings','findings'],['Technique','technique'],['Narrative','narrative'],['Post-op instructions','instructions']] as [$label,$f])
        <div class="sec"><h3>{{ $label }}</h3><p>{{ $note->{$f} ?? '—' }}</p></div>
    @endforeach
    <p class="meta">Status: {{ $note->status }} · version {{ $note->version }} · author {{ $note->author->name ?? '' }}
        · finalized {{ optional($note->finalized_at)->format('Y-m-d H:i') ?? 'not finalized' }} · printed {{ now()->format('Y-m-d H:i') }}</p>
</body>
</html>
