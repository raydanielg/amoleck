<?php

namespace Modules\HospitalOperations\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Support\Audit;
use Modules\HospitalOperations\Models\MedicationAdministration;
use Modules\HospitalOperations\Models\MedicationOrder;
use Modules\HospitalOperations\Models\MedicationScheduledDose;

class MedicationService
{
    public const TRANSITIONS = [
        'draft' => ['pending_review', 'approved', 'cancelled'],
        'pending_review' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['active', 'on_hold', 'discontinued'],
        'active' => ['on_hold', 'discontinued', 'completed'],
        'on_hold' => ['active', 'discontinued'],
        'rejected' => [], 'discontinued' => [], 'completed' => [], 'cancelled' => [],
    ];
    public const NON_ADMIN = ['missed', 'refused', 'withheld', 'omitted', 'delayed', 'partially_administered'];

    public function nextNumber(): string
    {
        return DB::transaction(function () {
            $row = DB::table('settings')->where('key', 'ops.med_order_sequence')->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = MedicationOrder::where('order_number', 'like', 'MED-'.date('Y').'-%')->orderByDesc('order_number')->value('order_number');
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) { $seq = $existing + 1; }
            DB::table('settings')->updateOrInsert(['key' => 'ops.med_order_sequence'], ['group' => 'ops', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]);
            return sprintf('MED-%s-%06d', date('Y'), $seq);
        });
    }

    public function createOrder(array $data, int $userId): MedicationOrder
    {
        return DB::transaction(function () use ($data, $userId) {
            $o = MedicationOrder::create($data + ['order_number' => $this->nextNumber(), 'status' => 'draft', 'created_by' => $userId]);
            $o->versions()->create(['version' => 1, 'snapshot' => $o->toArray(), 'action' => 'created', 'acted_by' => $userId]);
            Audit::record('med.order_created', $o->patient, null, ['order' => $o->order_number], $o->branch_id);
            return $o;
        });
    }

    private function snapshot(MedicationOrder $o, string $action, int $userId, ?string $reason = null): void
    {
        $o->versions()->create(['version' => ($o->versions()->max('version') ?? 0) + 1, 'snapshot' => $o->toArray(), 'action' => $action, 'reason' => $reason, 'acted_by' => $userId]);
    }

    public function transition(MedicationOrder $o, string $to, int $userId, ?string $reason = null): MedicationOrder
    {
        abort_unless(in_array($to, static::TRANSITIONS[$o->status] ?? []), 422, "Cannot move {$o->status}→{$to}.");
        abort_if($to === 'discontinued' && ! $reason, 422, 'Discontinuation reason required.');
        DB::transaction(function () use ($o, $to, $userId, $reason) {
            $this->snapshot($o, $to, $userId, $reason);
            $o->update(['status' => $to] + match ($to) {
                'approved' => ['approved_by' => $userId],
                'discontinued' => ['discontinued_at' => now(), 'discontinue_reason' => $reason],
                default => [],
            });
            if (in_array($to, ['discontinued', 'completed', 'cancelled'])) {
                $o->scheduledDoses()->whereIn('status', ['scheduled', 'due'])->update(['status' => 'cancelled']);
            }
        });
        Audit::record("med.order_{$to}", $o->patient, null, ['status' => $to], $o->branch_id);
        return $o->fresh();
    }

    /** Generate scheduled doses for a regular order across $days from schedule_times ['HH:MM',...]. */
    public function generateDoses(MedicationOrder $o, int $days = 3): int
    {
        abort_unless(in_array($o->status, ['approved', 'active']) && $o->type === 'regular' && $o->schedule_times, 422);
        $created = 0;
        foreach (range(0, $days - 1) as $d) {
            foreach ($o->schedule_times as $hm) {
                $at = now()->addDays($d)->setTimeFromTimeString($hm)->setTimezone(config('app.timezone'));
                $o->scheduledDoses()->create(['patient_id' => $o->patient_id, 'admission_id' => $o->admission_id, 'scheduled_at' => $at, 'status' => 'scheduled']);
                $created++;
            }
        }
        return $created;
    }

    /** Administer a scheduled dose — claim-safe, never auto-administered. */
    public function administer(MedicationScheduledDose $dose, array $data, int $userId, ?int $verifiedBy = null): MedicationAdministration
    {
        abort_unless(in_array($dose->order->status, ['active', 'approved']), 422, 'Order not administrable.');
        $admin = DB::transaction(function () use ($dose, $data, $userId, $verifiedBy) {
            $claimed = MedicationScheduledDose::where('id', $dose->id)->whereIn('status', ['scheduled', 'due'])->update(['status' => 'administered']);
            abort_unless($claimed, 422, 'Dose already recorded.');
            return MedicationAdministration::create($data + [
                'medication_scheduled_dose_id' => $dose->id, 'medication_order_id' => $dose->medication_order_id,
                'patient_id' => $dose->patient_id, 'admission_id' => $dose->admission_id,
                'administered_at' => now(), 'administered_by' => $userId, 'verified_by' => $verifiedBy, 'status' => 'administered',
            ]);
        });
        Audit::record('med.administered', $dose->patient, null, ['dose' => $dose->id], $dose->order->branch_id);
        return $admin;
    }

    /** Record non-administration (missed/refused/withheld/omitted/delayed/partial) with reason. */
    public function recordNonAdmin(MedicationScheduledDose $dose, string $status, string $reason, int $userId): void
    {
        abort_unless(in_array($status, static::NON_ADMIN), 422);
        DB::transaction(function () use ($dose, $status, $reason, $userId) {
            $claimed = MedicationScheduledDose::where('id', $dose->id)->whereIn('status', ['scheduled', 'due'])->update(['status' => $status]);
            abort_unless($claimed, 422, 'Dose already recorded.');
            MedicationAdministration::create([
                'medication_scheduled_dose_id' => $dose->id, 'medication_order_id' => $dose->medication_order_id,
                'patient_id' => $dose->patient_id, 'admission_id' => $dose->admission_id,
                'actual_dose' => '0', 'actual_route' => $dose->order->route, 'administered_at' => now(),
                'administered_by' => $userId, 'status' => $status, 'reason' => $reason,
            ]);
        });
        Audit::record("med.dose_{$status}", $dose->patient, null, ['reason' => $reason], $dose->order->branch_id);
    }

    /** PRN administration — enforces min interval against last PRN admin on the order. */
    public function administerPrn(MedicationOrder $o, array $data, int $userId, string $reason): MedicationAdministration
    {
        abort_unless($o->type === 'prn' && $o->status === 'active', 422, 'Not an active PRN order.');
        if ($o->prn_min_interval_minutes) {
            $last = $o->administrations()->where('status', 'administered')->latest('administered_at')->first();
            abort_if($last && $last->administered_at->diffInMinutes(now()) < $o->prn_min_interval_minutes, 422, 'Minimum interval not met.');
        }
        $admin = MedicationAdministration::create($data + [
            'medication_order_id' => $o->id, 'patient_id' => $o->patient_id, 'admission_id' => $o->admission_id,
            'administered_at' => now(), 'administered_by' => $userId, 'status' => 'administered', 'reason' => $reason,
        ]);
        Audit::record('med.prn_administered', $o->patient, null, null, $o->branch_id);
        return $admin;
    }

    /** eMAR dashboard buckets for a branch+ward. */
    public function emarDashboard(int $branchId, ?int $wardId = null): array
    {
        $base = MedicationScheduledDose::whereHas('order', fn ($q) => $q->where('branch_id', $branchId)->when($wardId, fn ($q) => $q->where('ward_id', $wardId)));
        return [
            'due' => (clone $base)->where('scheduled_at', '<=', now())->where('status', 'scheduled')->count(),
            'upcoming' => (clone $base)->where('scheduled_at', '>', now())->where('status', 'scheduled')->count(),
            'missed' => (clone $base)->where('status', 'missed')->count(),
            'refused' => (clone $base)->where('status', 'refused')->count(),
            'withheld' => (clone $base)->where('status', 'withheld')->count(),
            'pending_review' => MedicationOrder::where('branch_id', $branchId)->where('status', 'pending_review')->count(),
        ];
    }
}
