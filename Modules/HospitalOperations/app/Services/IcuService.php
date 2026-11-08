<?php
namespace Modules\HospitalOperations\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Foundation\Support\Audit;

class IcuService
{
    private function model(string $t): \Illuminate\Database\Eloquent\Builder { return DB::table($t)->toBase() ? DB::table($t) : DB::table($t); }
    private function num(string $p, string $table, string $col): string
    {
        return DB::transaction(function () use ($p, $table, $col) {
            $key = "icu.{$p}_seq";
            $row = DB::table('settings')->where('key', $key)->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            $latest = DB::table($table)->where($col, 'like', "{$p}-".date('Y')."-%")->orderByDesc($col)->value($col);
            if ($latest && ($e = (int) substr($latest, -6)) >= $seq) { $seq = $e + 1; }
            DB::table('settings')->updateOrInsert(['key' => $key], ['group' => 'icu', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]);
            return sprintf("%s-%s-%06d", $p, date('Y'), $seq);
        });
    }
    public function requestAdmission(int $patientId, int $unitId, string $priority, int $userId): object
    {
        $id = DB::table('icu_admission_requests')->insertGetId(['request_number' => $this->num('ICU', 'icu_admission_requests', 'request_number'), 'patient_id' => $patientId, 'icu_unit_id' => $unitId, 'priority' => $priority, 'status' => 'submitted', 'requested_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
        Audit::record('icu.requested', null, null, ['icu' => "ICU#$id"]);
        return DB::table('icu_admission_requests')->find($id);
    }
    public function approve(object $req, int $userId): object
    {
        abort_unless($req->status === 'submitted', 422); abort_if($req->requested_by === $userId, 422);
        DB::table('icu_admission_requests')->where('id', $req->id)->update(['status' => 'approved', 'reviewed_by' => $userId]);
        return DB::table('icu_admission_requests')->find($req->id);
    }
    /** Admit — allocates a bed with locking, blocks over-capacity + occupied. */
    public function admit(object $req, int $bedId, int $userId): object
    {
        return DB::transaction(function () use ($req, $bedId, $userId) {
            $bed = DB::table('icu_beds')->where('id', $bedId)->lockForUpdate()->first();
            abort_unless($bed && $bed->status === 'available', 422, 'Bed unavailable.');
            $unit = DB::table('icu_units')->find($bed->icu_unit_id);
            $occupied = DB::table('icu_stays')->join('icu_beds', 'icu_stays.icu_bed_id', '=', 'icu_beds.id')->where('icu_beds.icu_unit_id', $unit->id)->where('icu_stays.status', 'active')->count();
            abort_if($unit->capacity && $occupied >= $unit->capacity, 422, 'Unit at capacity.');
            DB::table('icu_beds')->where('id', $bedId)->update(['status' => 'occupied']);
            $id = DB::table('icu_stays')->insertGetId(['stay_number' => $this->num('STY', 'icu_stays', 'stay_number'), 'icu_admission_request_id' => $req->id, 'icu_bed_id' => $bedId, 'status' => 'active', 'admitted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('icu_admission_requests')->where('id', $req->id)->update(['status' => 'admitted']);
            Audit::record('icu.admitted', null, null, ['sty' => "STY#$id"]);
            return DB::table('icu_stays')->find($id);
        });
    }
    public function observe(int $stayId, string $type, ?float $value, ?string $unit, int $userId): object
    {
        $id = DB::table('icu_observations')->insertGetId(['icu_stay_id' => $stayId, 'type' => $type, 'value' => $value, 'unit' => $unit, 'measured_at' => now(), 'recorded_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('icu_observations')->find($id);
    }
    public function fluid(int $stayId, string $direction, string $category, float $amount, int $userId): object
    {
        $id = DB::table('icu_fluid_entries')->insertGetId(['icu_stay_id' => $stayId, 'direction' => $direction, 'category' => $category, 'amount' => $amount, 'recorded_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
        return DB::table('icu_fluid_entries')->find($id);
    }
    public function balance(int $stayId): float
    {
        $in = DB::table('icu_fluid_entries')->where('icu_stay_id', $stayId)->where('direction', 'in')->sum('amount');
        $out = DB::table('icu_fluid_entries')->where('icu_stay_id', $stayId)->where('direction', 'out')->sum('amount');
        return $in - $out;
    }
    public function discharge(int $stayId, int $userId): void
    {
        $stay = DB::table('icu_stays')->find($stayId); abort_unless($stay->status === 'active', 422);
        DB::table('icu_stays')->where('id', $stayId)->update(['status' => 'discharged', 'discharged_at' => now()]);
        DB::table('icu_beds')->where('id', $stay->icu_bed_id)->update(['status' => 'cleaning']);
        Audit::record('icu.discharged', null, null, ['sty' => $stay->stay_number]);
    }
}
