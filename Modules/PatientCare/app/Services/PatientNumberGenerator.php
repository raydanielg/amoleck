<?php

namespace Modules\PatientCare\Services;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Models\Setting;

class PatientNumberGenerator
{
    /** Unique org-wide MRN; safe under concurrency via a locked counter row. */
    public function next(): string
    {
        $prefix = Setting::get('patientcare.mrn_prefix', 'PAT');

        return DB::transaction(function () use ($prefix) {
            $row = DB::table('settings')->where('key', 'patientcare.mrn_sequence')->lockForUpdate()->first();
            $seq = ($row ? (int) json_decode($row->value, true) : 0) + 1;
            // Never collide with existing MRNs (imports, manual entries, prior seeds)
            $latest = \Modules\PatientCare\Models\Patient::withTrashed()
                ->where('mrn', 'like', "{$prefix}-".date('Y')."-%")->orderByDesc('mrn')->value('mrn');
            if ($latest && ($existing = (int) substr($latest, -6)) >= $seq) {
                $seq = $existing + 1;
            }
            DB::table('settings')->updateOrInsert(
                ['key' => 'patientcare.mrn_sequence'],
                ['group' => 'patientcare', 'value' => json_encode($seq), 'is_secret' => false, 'updated_at' => now(), 'created_at' => now()]
            );

            return sprintf('%s-%s-%06d', $prefix, date('Y'), $seq);
        });
    }
}
