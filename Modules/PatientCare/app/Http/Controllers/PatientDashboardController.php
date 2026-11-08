<?php

namespace Modules\PatientCare\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\PatientCare\Models\Patient;
use Modules\PatientCare\Services\PatientAccessService;

class PatientDashboardController extends Controller
{
    public function index(Request $request, PatientAccessService $access)
    {
        $scoped = $access->scopeFor($request->user(), Patient::query());

        return Inertia::render('admin/patients/dashboard', [
            'stats' => [
                'total' => (clone $scoped)->count(),
                'active' => (clone $scoped)->where('status', 'active')->count(),
                'newThisMonth' => (clone $scoped)->where('created_at', '>=', now()->startOfMonth())->count(),
                'inactive' => (clone $scoped)->where('status', 'inactive')->count(),
                'archived' => (clone $scoped)->where('status', 'archived')->count(),
            ],
            'byBranch' => (clone $scoped)->selectRaw('primary_branch_id, count(*) as total')->groupBy('primary_branch_id')->with('primaryBranch:id,name')->get()->map(fn ($r) => ['branch' => $r->primaryBranch?->name ?? 'Unassigned', 'total' => $r->total]),
            'recent' => (clone $scoped)->latest()->limit(8)->get(['id', 'mrn', 'first_name', 'last_name', 'status', 'created_at']),
        ]);
    }
}
