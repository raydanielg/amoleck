<?php

namespace Modules\Foundation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Foundation\Support\Audit;
use Modules\Foundation\Support\PermissionCatalog;
use Modules\Foundation\Support\SuperAdminGuard;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        return Inertia::render('admin/roles/index', [
            'roles' => Role::withCount('users', 'permissions')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/roles/form', ['role' => null, 'permissionGroups' => PermissionCatalog::grouped()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => [Rule::in(PermissionCatalog::flat())],
            'description' => 'nullable|string|max:500',
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
            'slug' => \Illuminate\Support\Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'created_by' => $request->user()->id,
        ]);
        $role->syncPermissions($request->input('permissions', []));
        Audit::record('role.created', $role, null, ['permissions' => $request->input('permissions', [])]);

        return redirect()->route('admin.roles.index')->with('toast', ['type' => 'success', 'message' => 'Role created.']);
    }

    public function show(Role $role)
    {
        return Inertia::render('admin/roles/show', [
            'role' => $role->load('permissions:id,name'),
            'users' => $role->users()->get(['users.id', 'name', 'email', 'status']),
            'activity' => \Modules\Foundation\Models\AuditLog::where('entity_type', Role::class)->where('entity_id', $role->id)->latest('created_at')->limit(10)->get(['id', 'action', 'created_at']),
            'protected' => $role->is_protected,
        ]);
    }

    public function updateStatus(Request $request, Role $role)
    {
        $data = $request->validate(['status' => 'required|in:active,inactive']);
        abort_if($role->is_protected && $data['status'] !== 'active', 403, 'Protected role cannot be deactivated.');
        $before = $role->status;
        $role->update(['status' => $data['status'], 'updated_by' => $request->user()->id]);
        Audit::record('role.'.$data['status'], $role, ['status' => $before], ['status' => $data['status']]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Role updated.']);
    }

    public function edit(Role $role)
    {
        return Inertia::render('admin/roles/form', [
            'role' => $role->load('permissions:id,name'),
            'permissionGroups' => PermissionCatalog::grouped(),
            'protected' => in_array($role->name, SuperAdminGuard::PROTECTED_ROLES),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        abort_if(in_array($role->name, SuperAdminGuard::PROTECTED_ROLES) && $request->name !== $role->name, 403, 'Protected role cannot be renamed.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions' => 'array',
            'permissions.*' => [Rule::in(PermissionCatalog::flat())],
            'description' => 'nullable|string|max:500',
        ]);

        $before = ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->all()];
        $role->update(['name' => $data['name'], 'description' => $data['description'] ?? $role->description, 'updated_by' => $request->user()->id]);
        $role->syncPermissions($request->input('permissions', []));
        Audit::record('role.updated', $role, $before, ['name' => $role->name, 'permissions' => $request->input('permissions', [])]);

        return redirect()->route('admin.roles.index')->with('toast', ['type' => 'success', 'message' => 'Role updated.']);
    }

    public function destroy(Role $role)
    {
        abort_if(in_array($role->name, SuperAdminGuard::PROTECTED_ROLES), 403, 'Protected role cannot be deleted.');
        abort_if($role->users()->exists(), 403, 'Role is assigned to users and cannot be deleted.');

        Audit::record('role.deleted', $role, $role->toArray());
        $role->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Role deleted.']);
    }

    public function duplicate(Role $role)
    {
        $copy = $role->replicate();
        $copy->name = $role->name.' (Copy)';
        $copy->save();
        $copy->syncPermissions($role->permissions);
        Audit::record('role.duplicated', $copy);

        return back()->with('toast', ['type' => 'success', 'message' => 'Role duplicated.']);
    }
}
