<?php

namespace Modules\Foundation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Foundation\Models\HospitalProfile;
use Modules\Foundation\Support\Audit;

class HospitalController extends Controller
{
    public function edit()
    {
        return Inertia::render('admin/hospital', ['profile' => HospitalProfile::current()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'slogan' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:100',
            'license_number' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'secondary_phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'timezone' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
            'logo' => 'nullable|image|max:2048',
            'stamp' => 'nullable|image|max:2048',
        ]);

        app(\Modules\Foundation\Services\HospitalProfileService::class)->update($data, $request);

        return back()->with('toast', ['type' => 'success', 'message' => 'Hospital profile updated.']);
    }
}
