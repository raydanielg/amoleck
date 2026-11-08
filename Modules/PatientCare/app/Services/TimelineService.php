<?php

namespace Modules\PatientCare\Services;

use Illuminate\Support\Collection;
use Modules\Foundation\Models\AuditLog;
use Modules\PatientCare\Models\Patient;

class TimelineService
{
    /** Unified chronological timeline; 'kind' groups clinical/admin/referral/document. */
    public function for(Patient $patient): Collection
    {
        $events = collect();

        AuditLog::where('entity_type', Patient::class)->where('entity_id', $patient->id)->get()
            ->each(fn ($a) => $events->push([
                'at' => $a->created_at, 'kind' => $this->kindOf($a->action), 'title' => $a->action,
                'by' => $a->user?->name ?? 'system', 'meta' => $a->after,
            ]));

        $patient->referrals->each(fn ($r) => $events->push([
            'at' => $r->created_at, 'kind' => 'referral', 'title' => "Referral {$r->ref_number} ({$r->status})",
            'by' => $r->registeredBy?->name ?? '—', 'meta' => ['ref_number' => $r->ref_number],
        ]));

        $patient->documents->each(fn ($d) => $events->push([
            'at' => $d->created_at, 'kind' => 'document', 'title' => "Document uploaded: {$d->category}",
            'by' => $d->uploadedBy?->name ?? '—', 'meta' => ['title' => $d->title],
        ]));

        $patient->conditions->each(fn ($c) => $events->push([
            'at' => $c->created_at, 'kind' => 'clinical', 'title' => "Condition recorded: {$c->name}",
            'by' => $c->recordedBy?->name ?? '—', 'meta' => ['status' => $c->status, 'verified' => $c->verified],
        ]));

        $patient->statusHistory->each(fn ($s) => $events->push([
            'at' => $s->created_at, 'kind' => 'administrative', 'title' => "Status: {$s->from_status} → {$s->to_status}",
            'by' => $s->changedBy?->name ?? '—', 'meta' => ['reason' => $s->reason],
        ]));

        $patient->branchTransfers->each(fn ($t) => $events->push([
            'at' => $t->created_at, 'kind' => 'administrative', 'title' => "Branch transfer → {$t->toBranch?->name}",
            'by' => $t->transferredBy?->name ?? '—', 'meta' => ['reason' => $t->reason],
        ]));

        return $events->sortByDesc('at')->values();
    }

    private function kindOf(string $action): string
    {
        return match (true) {
            str_starts_with($action, 'referral.') => 'referral',
            str_contains($action, 'document') => 'document',
            str_contains($action, 'medical') || str_contains($action, 'allerg') || str_contains($action, 'consent') || str_contains($action, 'amend') => 'clinical',
            default => 'administrative',
        };
    }
}
