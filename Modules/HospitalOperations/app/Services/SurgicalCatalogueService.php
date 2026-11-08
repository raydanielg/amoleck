<?php

namespace Modules\HospitalOperations\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;
use Modules\HospitalOperations\Models\SurgicalProcedure;
use Modules\HospitalOperations\Models\SurgicalSpecialty;

class SurgicalCatalogueService
{
    public const PROCEDURE_CATEGORIES = ['major', 'minor', 'day', 'emergency', 'elective', 'diagnostic', 'therapeutic', 'other'];
    public const URGENCY_CLASSES = ['elective', 'emergency', 'both'];
    public const ANESTHESIA_TECHNIQUES = ['general', 'regional', 'local', 'sedation', 'other'];

    // ---- Specialties ----
    public function createSpecialty(array $data, int $userId): SurgicalSpecialty
    {
        $s = SurgicalSpecialty::create($data + ['status' => $data['status'] ?? 'active', 'created_by' => $userId]);
        $this->syncSpecialtyBranches($s, $data['branch_ids'] ?? []);
        Audit::record('surgery.specialty_created', $s, null, ['code' => $s->code]);
        return $s;
    }

    public function updateSpecialty(SurgicalSpecialty $s, array $data, int $userId): SurgicalSpecialty
    {
        $before = $s->only(['name', 'status', 'department_id', 'head_user_id']);
        $s->update($data);
        if (array_key_exists('branch_ids', $data)) {
            $this->syncSpecialtyBranches($s, $data['branch_ids']);
        }
        Audit::record('surgery.specialty_updated', $s, $before, $s->only(['name', 'status', 'department_id', 'head_user_id']));
        return $s->fresh();
    }

    private function syncSpecialtyBranches(SurgicalSpecialty $s, array $branchIds): void
    {
        foreach ($branchIds as $bid) {
            $s->branches()->updateOrCreate(['branch_id' => $bid], ['available' => true]);
        }
    }

    public function setSpecialtyBranchAvailability(SurgicalSpecialty $s, int $branchId, bool $available): void
    {
        $s->branches()->updateOrCreate(['branch_id' => $branchId], ['available' => $available]);
        Audit::record('surgery.specialty_branch_set', $s, null, ['branch' => $branchId, 'available' => $available], $branchId);
    }

    public function specialtyAvailableIn(SurgicalSpecialty $s, int $branchId): bool
    {
        $row = $s->branches()->where('branch_id', $branchId)->first();
        // no row => hospital-wide default is available unless specialty explicitly restricted
        return $s->status === 'active' && ($row ? (bool) $row->available : true);
    }

    // ---- Procedures ----
    public function createProcedure(array $data, int $userId): SurgicalProcedure
    {
        abort_unless(in_array($data['category'] ?? 'elective', self::PROCEDURE_CATEGORIES), 422, 'Invalid category.');
        abort_unless(in_array($data['urgency_classification'] ?? 'elective', self::URGENCY_CLASSES), 422, 'Invalid urgency.');
        $p = DB::transaction(function () use ($data, $userId) {
            $p = SurgicalProcedure::create($data + ['version' => 1, 'status' => $data['status'] ?? 'draft', 'created_by' => $userId]);
            $this->snapshot($p, 'created', $userId);
            foreach ($data['branch_ids'] ?? [] as $bid) {
                $p->branches()->updateOrCreate(['branch_id' => $bid], ['available' => true]);
            }
            return $p;
        });
        Audit::record('surgery.procedure_created', $p, null, ['code' => $p->code]);
        return $p;
    }

    public function updateProcedure(SurgicalProcedure $p, array $data, int $userId): SurgicalProcedure
    {
        abort_if($p->status === 'inactive' && ($data['status'] ?? 'inactive') === 'inactive', 422, 'Procedure is inactive.');
        return DB::transaction(function () use ($p, $data, $userId) {
            unset($data['branch_ids']);
            $p->update($data + ['version' => $p->version + 1]);
            $this->snapshot($p->fresh(), 'updated', $userId);
            Audit::record('surgery.procedure_updated', $p, null, ['version' => $p->version]);
            return $p->fresh();
        });
    }

    public function transitionProcedure(SurgicalProcedure $p, string $to, int $userId): SurgicalProcedure
    {
        abort_unless(in_array($to, ['draft', 'active', 'inactive']), 422);
        abort_if($to === 'active' && ! $p->surgical_specialty_id, 422, 'Procedure needs a specialty.');
        $p->update(['status' => $to, 'version' => $p->version + 1]);
        $this->snapshot($p->fresh(), "status_{$to}", $userId);
        Audit::record("surgery.procedure_{$to}", $p, null, null);
        return $p->fresh();
    }

    public function setProcedureBranchAvailability(SurgicalProcedure $p, int $branchId, bool $available): void
    {
        $p->branches()->updateOrCreate(['branch_id' => $branchId], ['available' => $available]);
        Audit::record('surgery.procedure_branch_set', $p, null, ['branch' => $branchId, 'available' => $available], $branchId);
    }

    public function procedureAvailableIn(SurgicalProcedure $p, int $branchId): bool
    {
        if ($p->status !== 'active') {
            return false;
        }
        $row = $p->branches()->where('branch_id', $branchId)->first();
        return $row ? (bool) $row->available : true;
    }

    private function snapshot(SurgicalProcedure $p, string $action, int $userId): void
    {
        $p->versions()->create(['version' => $p->version, 'snapshot' => $p->toArray(), 'action' => $action, 'acted_by' => $userId]);
    }
}
