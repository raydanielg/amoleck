<?php

namespace Modules\PatientCare\Services;

use Illuminate\Support\Collection;
use Modules\PatientCare\Models\Patient;

class DuplicateDetectionService
{
    /** Return potential duplicates with the fields they matched on. */
    public function find(array $data): Collection
    {
        $candidates = Patient::query()
            ->where(fn ($q) => $q
                ->when($data['phone'] ?? null, fn ($q, $p) => $q->orWhere('phone', $p))
                ->when($data['date_of_birth'] ?? null, fn ($q, $d) => $q->orWhere('date_of_birth', $d))
                ->orWhere(fn ($q) => $q->where('first_name', $data['first_name'] ?? '')->where('last_name', $data['last_name'] ?? '')))
            ->when($data['identification_number'] ?? null, fn ($q, $n) => $q->orWhereHas('identifications', fn ($i) => $i->where('number', $n)))
            ->limit(10)
            ->get();

        return $candidates->map(fn ($p) => [
            'patient' => $p,
            'matched_on' => collect([
                ($data['phone'] ?? null) === $p->phone && $p->phone ? 'phone' : null,
                ($data['date_of_birth'] ?? null) === $p->date_of_birth?->toDateString() ? 'date_of_birth' : null,
                mb_strtolower($data['first_name'] ?? '') === mb_strtolower($p->first_name) && mb_strtolower($data['last_name'] ?? '') === mb_strtolower($p->last_name) ? 'name' : null,
                $p->identifications->contains('number', $data['identification_number'] ?? null) ? 'identification' : null,
            ])->filter()->values()->all(),
        ]);
    }
}
