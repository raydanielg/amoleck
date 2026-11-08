<?php

namespace Modules\Foundation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Support\Audit;

class BranchController extends Controller
{
    public function index(Request $request)
    {

        return Inertia::render('admin/branches/index', [
            'branches' => Branch::withCount('users')
                ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%"))
                ->latest()->paginate(15)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/branches/form', ['branch' => null]);
    }

    public function store(Request $request)
    {
        $branch = Branch::create($this->validateData($request) + ['created_by' => $request->user()->id]);
        Audit::record('branch.created', $branch, null, $branch->toArray());

        return redirect()->route('admin.branches.index')->with('toast', ['type' => 'success', 'message' => 'Branch created.']);
    }

    public function show(Branch $branch)
    {
        return Inertia::render('admin/branches/show', [
            'branch' => $branch->load(['users' => fn ($q) => $q->select('users.id', 'name', 'email', 'job_title', 'status')]),
            'activity' => \Modules\Foundation\Models\AuditLog::where('entity_type', Branch::class)->where('entity_id', $branch->id)->latest('created_at')->limit(10)->get(),
        ]);
    }

    public function edit(Branch $branch)
    {
        return Inertia::render('admin/branches/form', ['branch' => $branch]);
    }

    public function update(Request $request, Branch $branch)
    {
        $before = $branch->toArray();
        $branch->update($this->validateData($request, $branch->id) + ['updated_by' => $request->user()->id]);
        Audit::record('branch.updated', $branch, $before, $branch->fresh()->toArray());

        return redirect()->route('admin.branches.index')->with('toast', ['type' => 'success', 'message' => 'Branch updated.']);
    }

    public function updateStatus(Request $request, Branch $branch)
    {
        $data = $request->validate(['status' => ['required', Rule::in(Branch::STATUSES)]]);

        $before = $branch->status;
        $branch->update(['status' => $data['status']]);
        Audit::record("branch.{$data['status']}", $branch, ['status' => $before], ['status' => $data['status']]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Branch status updated.']);
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:20', Rule::unique('branches', 'code')->ignore($ignoreId)],
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'ward' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'manager_name' => 'nullable|string|max:255',
            'opening_date' => 'nullable|date',
        ]);
    }
}
