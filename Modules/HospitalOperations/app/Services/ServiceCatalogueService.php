<?php

namespace Modules\HospitalOperations\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;
use Modules\HospitalOperations\Models\ClinicalService;
use Modules\HospitalOperations\Models\Department;

class ServiceCatalogueService
{
    public const SERVICE_TRANSITIONS = ['draft' => ['active', 'archived'], 'active' => ['inactive', 'archived'], 'inactive' => ['active', 'archived'], 'archived' => []];
    public const WORKFLOWS = ['initial_assessment', 'follow_up_assessment', 'treatment_session', 'reassessment', 'discharge'];

    public function createDepartment(array $data, int $userId): Department
    {
        $this->guardCircular($data['parent_id'] ?? null, null);
        $d = Department::create($data);
        Audit::record('department.created', null, null, ['dept' => $d->code]);
        return $d;
    }

    /** Reject parent that is self or a descendant (cycle). */
    public function guardCircular(?int $parentId, ?int $selfId): void
    {
        if (! $parentId || ! $selfId) return;
        $p = Department::find($parentId);
        while ($p) {
            abort_if($p->id === $selfId, 422, 'Circular department hierarchy.');
            $p = $p->parent;
        }
    }

    public function updateDepartment(Department $d, array $data, int $userId): Department
    {
        $this->guardCircular($data['parent_id'] ?? $d->parent_id, $d->id);
        $d->update($data);
        Audit::record('department.updated', null, null, ['dept' => $d->code]);
        return $d->fresh();
    }

    public function syncDepartmentBranches(Department $d, array $branchIds): void
    {
        $d->branches()->sync(collect($branchIds)->mapWithKeys(fn ($id) => [$id => ['active' => true]])->all());
        Audit::record('department.branches_synced', null, null, ['dept' => $d->code, 'branches' => $branchIds]);
    }

    public function createService(array $data, int $userId): ClinicalService
    {
        return DB::transaction(function () use ($data, $userId) {
            $s = ClinicalService::create($data + ['status' => 'draft']);
            $this->snapshot($s, 'created', $userId);
            Audit::record('service.created', null, null, ['service' => $s->code]);
            return $s;
        });
    }

    public function transitionService(ClinicalService $s, string $to, int $userId): ClinicalService
    {
        abort_unless(in_array($to, static::SERVICE_TRANSITIONS[$s->status] ?? []), 422, "Cannot move service {$s->status}→{$to}.");
        DB::transaction(function () use ($s, $to, $userId) {
            $this->snapshot($s, $to, $userId);
            $s->update(['status' => $to]);
        });
        Audit::record("service.{$to}", null, null, ['service' => $s->code]);
        return $s->fresh();
    }

    public function snapshot(ClinicalService $s, string $action, int $userId): void
    {
        $s->versions()->create(['version' => ($s->versions()->max('version') ?? 0) + 1, 'snapshot' => $s->load('branchConfigs', 'clinicians')->toArray(), 'action' => $action, 'acted_by' => $userId]);
    }

    /** Service bookable at a branch right now? Global active + branch config. */
    public function availableAt(ClinicalService $s, int $branchId): bool
    {
        if ($s->status !== 'active') return false;
        $cfg = $s->branchConfigs()->where('branch_id', $branchId)->first();
        return $cfg ? $cfg->available : true; // no config = available everywhere by default
    }

    public function setBranchAvailability(ClinicalService $s, int $branchId, bool $available, ?array $overrides = null): void
    {
        $s->branchConfigs()->updateOrCreate(['branch_id' => $branchId], ['available' => $available] + ($overrides ?? []));
        Audit::record('service.branch_availability', null, null, ['service' => $s->code, 'branch' => $branchId, 'available' => $available]);
    }

    /** Clinicians eligible for a service at a branch (assigned, active, not expired). */
    public function eligibleClinicians(ClinicalService $s, int $branchId): \Illuminate\Support\Collection
    {
        return $s->clinicians()->where('active', true)
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', today()))
            ->with('user:id,name')->get()->pluck('user');
    }
}
