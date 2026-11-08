<?php

namespace Modules\Foundation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Services\SystemSettingsService;
use Modules\Foundation\Support\SettingsRegistry;

class SettingController extends Controller
{
    public function edit(Request $request, SystemSettingsService $svc)
    {
        $branchId = $request->integer('branch_id') ?: null;
        if ($branchId) {
            abort_unless(\Modules\Foundation\Support\BranchContext::canAccess($request->user(), $branchId), 403);
        }

        return Inertia::render('admin/settings', [
            'categories' => $svc->groupedValues($branchId),
            'branches' => Branch::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'selectedBranch' => $branchId,
            'permissions' => collect(SettingsRegistry::flat())->mapWithKeys(fn ($m, $k) => [$m['perm'] => $request->user()->can($m['perm'])])->unique()->all(),
        ]);
    }

    public function update(Request $request, SystemSettingsService $svc)
    {
        $branchId = $request->integer('branch_id') ?: null;
        if ($branchId) {
            abort_unless(\Modules\Foundation\Support\BranchContext::canAccess($request->user(), $branchId), 403);
            abort_unless($request->user()->can('foundation.settings.manage_branch_overrides'), 403);
        }

        $saved = 0;
        foreach ($request->except(['_method', '_token', 'branch_id']) as $key => $value) {
            $svc->set($key, $value, $branchId);
            $saved++;
        }

        return back()->with('toast', ['type' => 'success', 'message' => "{$saved} setting(s) saved."]);
    }

    public function deleteSecret(Request $request, SystemSettingsService $svc)
    {
        $data = $request->validate(['key' => ['required', Rule::in(SettingsRegistry::keys())]]);
        $svc->deleteSecret($data['key'], $request->integer('branch_id') ?: null);

        return back()->with('toast', ['type' => 'success', 'message' => 'Secret removed.']);
    }

    public function removeOverride(Request $request, SystemSettingsService $svc)
    {
        $data = $request->validate([
            'key' => ['required', Rule::in(SettingsRegistry::keys())],
            'branch_id' => 'required|exists:branches,id',
        ]);
        abort_unless(\Modules\Foundation\Support\BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403);
        $svc->removeBranchOverride($data['key'], (int) $data['branch_id']);

        return back()->with('toast', ['type' => 'success', 'message' => 'Override removed.']);
    }

    public function history(Request $request)
    {
        return Inertia::render('admin/settings-history', [
            'logs' => AuditLog::with('user:id,name')
                ->where('action', 'like', 'setting.%')
                ->when($request->search, fn ($q, $s) => $q->where('action', 'like', "%{$s}%"))
                ->latest('created_at')->paginate(20)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }
}
