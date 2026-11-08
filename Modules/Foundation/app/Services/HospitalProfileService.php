<?php

namespace Modules\Foundation\Services;

use Illuminate\Http\Request;
use Modules\Foundation\Models\HospitalProfile;
use Modules\Foundation\Support\Audit;

class HospitalProfileService
{
    public function current(): HospitalProfile
    {
        return HospitalProfile::current();
    }

    public function update(array $data, Request $request): HospitalProfile
    {
        $profile = $this->current();
        $before = $profile->toArray();

        foreach (['logo', 'stamp'] as $file) {
            if ($request->hasFile($file)) {
                $data[$file] = $request->file($file)->store('hospital', 'public');
            }
        }

        $profile->update($data + ['updated_by' => $request->user()->id]);
        Audit::record('hospital.updated', $profile, $before, $profile->fresh()->toArray());

        return $profile->fresh();
    }
}
