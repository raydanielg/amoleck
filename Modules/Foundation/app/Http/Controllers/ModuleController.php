<?php

namespace Modules\Foundation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Spatie\Permission\Models\Role;

class ModuleController extends Controller
{
    public function index(ModuleRegistryService $svc)
    {
        $svc->sync();
        $modules = $svc->all();

        return Inertia::render('admin/modules/index', [
            'modules' => $modules->map(fn ($m) => $this->describe($m)),
            'stats' => [
                'total' => $modules->count(),
                'active' => $modules->where('status', 'active')->count(),
                'disabled' => $modules->where('status', 'disabled')->count(),
                'maintenance' => $modules->where('status', 'maintenance')->count(),
                'unavailable' => $modules->whereIn('status', ['unavailable', 'pending_installation'])->count(),
            ],
        ]);
    }

    public function show(SystemModule $module, ModuleRegistryService $svc)
    {
        return Inertia::render('admin/modules/show', [
            'module' => $this->describe($module->load('roles:id,name')),
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'dependencies' => collect($module->dependencies ?? [])->map(fn ($d) => ['name' => $d, 'status' => $svc->statusOf($d)]),
            'activity' => AuditLog::where('entity_type', SystemModule::class)->where('entity_id', $module->id)->latest('created_at')->limit(10)->get(['id', 'action', 'created_at']),
        ]);
    }

    public function toggle(string $module, ModuleRegistryService $svc)
    {
        $svc->sync();
        $mod = $svc->findByName($module);
        abort_unless($mod, 404);
        $svc->setStatus($module, $mod->status === 'active' ? 'disabled' : 'active');

        return back()->with('toast', ['type' => 'success', 'message' => 'Module updated.']);
    }

    public function maintenance(SystemModule $module, ModuleRegistryService $svc)
    {
        $svc->setStatus($module->name, $module->status === 'maintenance' ? 'active' : 'maintenance');

        return back()->with('toast', ['type' => 'success', 'message' => 'Module updated.']);
    }

    public function updateAccess(Request $request, SystemModule $module, ModuleRegistryService $svc)
    {
        $data = $request->validate(['role_ids' => 'array', 'role_ids.*' => 'exists:roles,id']);
        $svc->syncRoleAccess($module->name, $data['role_ids'] ?? []);

        return back()->with('toast', ['type' => 'success', 'message' => 'Module access updated.']);
    }

    private function describe(SystemModule $m): array
    {
        return [
            'id' => $m->id,
            'identifier' => $m->identifier,
            'name' => $m->name,
            'description' => $m->description,
            'version' => $m->version,
            'category' => $m->category,
            'icon' => $m->icon,
            'status' => $m->status,
            'protected' => $m->is_protected,
            'dependencies' => $m->dependencies ?? [],
            'features' => $m->features ?? [],
            'roles' => $m->roles->pluck('name'),
            'role_ids' => $m->roles->pluck('id'),
            'updated_at' => $m->updated_at?->toDateTimeString(),
        ];
    }
}
