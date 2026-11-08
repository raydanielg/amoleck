<?php

namespace Modules\HospitalOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Foundation\Support\BranchContext;
use Modules\HospitalOperations\Models\Department;
use Modules\HospitalOperations\Models\OperatingTheatre;
use Modules\HospitalOperations\Models\SurgicalChecklistTemplate;
use Modules\HospitalOperations\Models\SurgicalProcedure;
use Modules\HospitalOperations\Models\SurgicalSpecialty;
use Modules\HospitalOperations\Services\SurgicalCatalogueService;
use Modules\HospitalOperations\Services\SurgicalIntegrationService;
use Modules\HospitalOperations\Services\TheatreDashboardService;

class SurgeryController extends Controller
{
    public function dashboard(Request $request, TheatreDashboardService $svc)
    {
        $user = $request->user();
        $branchId = $request->integer('branch_id') ?: BranchContext::current($user)?->id;
        abort_unless($branchId, 403);
        return Inertia::render('admin/surgery/dashboard', [
            'metrics' => $svc->dashboard($branchId, $request->get('date')),
            'analytics' => $svc->analytics($branchId, $request->get('from', now()->subDays(30)->toDateString()), $request->get('to', today()->toDateString())),
            'branches' => BranchContext::allowed($user)->values(['id', 'name']),
            'branchId' => $branchId,
        ]);
    }

    public function config(Request $request)
    {
        $user = $request->user();
        $branchId = $request->integer('branch_id') ?: BranchContext::current($user)?->id;
        return Inertia::render('admin/surgery/config', [
            'specialties' => SurgicalSpecialty::with(['department:id,name', 'head:id,name', 'branches'])->orderBy('name')->get(),
            'procedures' => SurgicalProcedure::with(['specialty:id,name', 'branches'])->latest()->get(),
            'templates' => SurgicalChecklistTemplate::orderBy('stage')->orderByDesc('version')->get(),
            'departments' => Department::where('status', 'active')->get(['id', 'name', 'code']),
            'branches' => BranchContext::allowed($user)->values(['id', 'name']),
            'branchId' => $branchId,
            'categories' => SurgicalCatalogueService::PROCEDURE_CATEGORIES,
            'urgencies' => SurgicalCatalogueService::URGENCY_CLASSES,
            'techniques' => SurgicalCatalogueService::ANESTHESIA_TECHNIQUES,
            'theatres' => OperatingTheatre::when($branchId, fn ($q) => $q->where('branch_id', $branchId))->get(['id', 'code', 'name', 'category', 'status']),
        ]);
    }

    public function storeSpecialty(Request $request, SurgicalCatalogueService $svc)
    {
        abort_unless($request->user()->can('surgery.catalogue.manage'), 403);
        $data = $request->validate([
            'code' => 'required|string|unique:surgical_specialties,code', 'name' => 'required|string',
            'description' => 'nullable|string', 'department_id' => 'nullable|exists:departments,id',
            'head_user_id' => 'nullable|exists:users,id', 'branch_ids' => 'nullable|array', 'branch_ids.*' => 'exists:branches,id',
        ]);
        $s = $svc->createSpecialty($data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Specialty {$s->name} created."]);
    }

    public function updateSpecialty(Request $request, SurgicalSpecialty $specialty, SurgicalCatalogueService $svc)
    {
        abort_unless($request->user()->can('surgery.catalogue.manage'), 403);
        $data = $request->validate([
            'name' => 'sometimes|string', 'description' => 'nullable|string',
            'department_id' => 'nullable|exists:departments,id', 'head_user_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:active,inactive', 'branch_ids' => 'nullable|array', 'branch_ids.*' => 'exists:branches,id',
        ]);
        $svc->updateSpecialty($specialty, $data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => 'Specialty updated.']);
    }

    public function storeProcedure(Request $request, SurgicalCatalogueService $svc)
    {
        abort_unless($request->user()->can('surgery.catalogue.manage'), 403);
        $data = $request->validate([
            'code' => 'required|string|unique:surgical_procedures,code', 'name' => 'required|string',
            'surgical_specialty_id' => 'required|exists:surgical_specialties,id', 'description' => 'nullable|string',
            'category' => 'required|string', 'urgency_classification' => 'required|string',
            'expected_duration_minutes' => 'nullable|integer|min:1', 'theatre_category' => 'nullable|string',
            'anesthesia_options' => 'nullable|array', 'consent_required' => 'nullable|boolean',
            'branch_ids' => 'nullable|array', 'branch_ids.*' => 'exists:branches,id',
        ]);
        $p = $svc->createProcedure($data, $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Procedure {$p->name} created."]);
    }

    public function transitionProcedure(Request $request, SurgicalProcedure $procedure, SurgicalCatalogueService $svc)
    {
        abort_unless($request->user()->can('surgery.catalogue.manage'), 403);
        $data = $request->validate(['status' => 'required|in:draft,active,inactive']);
        $svc->transitionProcedure($procedure, $data['status'], $request->user()->id);
        return back()->with('toast', ['type' => 'success', 'message' => "Procedure {$data['status']}."]);
    }

    public function storeTemplate(Request $request)
    {
        abort_unless($request->user()->can('surgery.catalogue.manage'), 403);
        $data = $request->validate([
            'code' => 'required|string|unique:surgical_checklist_templates,code', 'name' => 'required|string',
            'stage' => 'required|in:preparation,sign_in,time_out,sign_out',
            'items' => 'required|array|min:1', 'items.*.key' => 'required|string', 'items.*.label' => 'required|string',
            'items.*.mandatory' => 'nullable|boolean', 'items.*.role' => 'nullable|string',
        ]);
        $latest = SurgicalChecklistTemplate::where('code', $data['code'])->max('version') ?: 0;
        SurgicalChecklistTemplate::create($data + ['version' => $latest + 1, 'approved_by' => $request->user()->id, 'effective_from' => now()->toDateString()]);
        return back()->with('toast', ['type' => 'success', 'message' => 'Checklist template saved.']);
    }

    public function reports(Request $request, TheatreDashboardService $svc, SurgicalIntegrationService $int)
    {
        $user = $request->user();
        $branchId = $request->integer('branch_id') ?: BranchContext::current($user)?->id;
        $from = $request->get('from', now()->subDays(30)->toDateString());
        $to = $request->get('to', today()->toDateString());
        return Inertia::render('admin/surgery/reports', [
            'analytics' => $svc->analytics($branchId, $from, $to),
            'filters' => compact('from', 'to', 'branchId'),
            'branches' => BranchContext::allowed($user)->values(['id', 'name']),
            'specialties' => SurgicalSpecialty::where('status', 'active')->get(['id', 'name']),
        ]);
    }
}
