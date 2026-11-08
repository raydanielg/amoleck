<?php

namespace Modules\Foundation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Support\Audit;
use Modules\Foundation\Support\SuperAdminGuard;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('admin/users/index', [
            'users' => User::with('roles:id,name', 'branches:id,name')
                ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")->orWhere('employee_number', 'like', "%$s%")))
                ->when($request->status, fn ($q, $s) => $q->where('status', $s))
                ->when($request->branch_id, fn ($q, $b) => $q->whereHas('branches', fn ($q) => $q->where('branches.id', $b)))
                ->when($request->role, fn ($q, $r) => $q->role($r))
                ->latest()->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'status', 'branch_id', 'role']),
            'branches' => Branch::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'roles' => Role::orderBy('name')->get(['name']),
        ]);
    }

    public function create()
    {
        return $this->form();
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        if (empty($data['employee_number'])) {
            $data['employee_number'] = 'EMP-'.str_pad((string) (User::withTrashed()->max('id') + 1), 4, '0', STR_PAD_LEFT);
        }
        $plain = $request->input('password');
        $data['password'] = Hash::make($plain ?: \Illuminate\Support\Str::random(16));
        $data['created_by'] = $request->user()->id;

        $user = DB::transaction(function () use ($data, $request) {
            if ($request->hasFile('profile_photo')) {
                $data['profile_photo'] = $request->file('profile_photo')->store('staff', 'public');
            }
            $user = User::create($data);
            $user->syncRoles($request->input('roles', []));
            $this->syncBranches($user, $request);
            return $user;
        });

        if (! $plain) {
            \Illuminate\Support\Facades\Password::sendResetLink(['email' => $user->email]);
        }
        Audit::record('user.created', $user, null, $user->only(['name', 'email', 'status']));

        return redirect()->route('admin.users.index')->with('toast', ['type' => 'success', 'message' => 'Staff account created.']);
    }

    public function edit(User $user)
    {
        $this->scopeToManagedBranches(request()->user(), $user);
        return $this->form($user);
    }

    public function update(Request $request, User $user)
    {
        $this->scopeToManagedBranches($request->user(), $user);
        $data = $this->validateData($request, $user->id);
        $before = $user->toArray();
        $data['updated_by'] = $request->user()->id;

        DB::transaction(function () use ($user, $data, $request) {
            if ($request->hasFile('profile_photo')) {
                $data['profile_photo'] = $request->file('profile_photo')->store('staff', 'public');
            }
            $user->update($data);
            $user->syncRoles($request->input('roles', []));
            $this->syncBranches($user, $request);
        });

        Audit::record('user.updated', $user, $before, $user->fresh()->only(['name', 'email', 'status']));

        return redirect()->route('admin.users.index')->with('toast', ['type' => 'success', 'message' => 'Staff account updated.']);
    }

    public function updateStatus(Request $request, User $user)
    {
        $this->scopeToManagedBranches($request->user(), $user);
        $data = $request->validate(['status' => ['required', Rule::in(User::STATUSES)]]);

        if ($user->hasRole('Super Admin') && $data['status'] !== 'active') {
            SuperAdminGuard::ensureNotLastSuperAdmin($user);
        }

        $before = $user->status;
        $user->update(['status' => $data['status'], 'updated_by' => $request->user()->id]);
        Audit::record("user.{$data['status']}", $user, ['status' => $before], ['status' => $data['status']]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Account status updated.']);
    }

    public function resetPassword(Request $request, User $user)
    {
        $this->scopeToManagedBranches($request->user(), $user);
        $user->update(['password' => Hash::make($request->validate(['password' => ['required', Password::defaults()]])['password'])]);
        Audit::record('user.password_reset', $user);

        return back()->with('toast', ['type' => 'success', 'message' => 'Password reset.']);
    }

    private function form(?User $user = null)
    {
        return Inertia::render('admin/users/form', [
            'user' => $user?->load('roles:id', 'branches:id'),
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'branches' => Branch::where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']),
            'statuses' => User::STATUSES,
        ]);
    }

    public function show(User $user)
    {
        $this->scopeToManagedBranches(request()->user(), $user);

        return Inertia::render('admin/users/show', [
            'user' => $user->load('roles:id,name', 'branches:id,name', 'primaryBranch:id,name'),
            'loginHistory' => \Modules\Foundation\Models\LoginHistory::where('user_id', $user->id)->latest('created_at')->limit(10)->get(),
            'activity' => \Modules\Foundation\Models\AuditLog::where('user_id', $user->id)->latest('created_at')->limit(10)->get(['id', 'action', 'created_at']),
        ]);
    }

    public function bulkStatus(Request $request)
    {
        $data = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'status' => ['required', Rule::in(User::STATUSES)],
        ]);

        $users = User::whereIn('id', $data['user_ids'])->get();
        foreach ($users as $user) {
            $this->scopeToManagedBranches($request->user(), $user);
            if ($user->hasRole('Super Admin') && $data['status'] !== 'active') {
                SuperAdminGuard::ensureNotLastSuperAdmin($user);
            }
            $before = $user->status;
            $user->update(['status' => $data['status'], 'updated_by' => $request->user()->id]);
            Audit::record("user.{$data['status']}", $user, ['status' => $before], ['status' => $data['status']]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => count($users).' account(s) updated.']);
    }

    private function scopeToManagedBranches(User $actor, User $target): void
    {
        if ($actor->hasRole('Super Admin') || $actor->hasRole('Hospital Administrator')) {
            return;
        }
        $allowed = \Modules\Foundation\Support\BranchContext::allowed($actor)->pluck('id');
        abort_unless($target->branches->pluck('id')->intersect($allowed)->isNotEmpty(), 403, 'You can only manage staff within your branches.');
    }

    public function permissions(User $user)
    {
        $this->scopeToManagedBranches(request()->user(), $user);

        return Inertia::render('admin/users/permissions', [
            'user' => $user->load('roles:id,name'),
            'rolePermissions' => $user->getPermissionsViaRoles()->pluck('name'),
            'directPermissions' => $user->getDirectPermissions()->pluck('name'),
            'permissionGroups' => \Modules\Foundation\Support\PermissionCatalog::grouped(),
        ]);
    }

    public function syncPermissions(Request $request, User $user)
    {
        $this->scopeToManagedBranches($request->user(), $user);
        abort_if($user->hasRole('Super Admin'), 403, 'Super Admin permissions cannot be modified directly.');

        $data = $request->validate([
            'permissions' => 'array',
            'permissions.*' => [Rule::in(\Modules\Foundation\Support\PermissionCatalog::flat())],
        ]);

        $before = $user->getDirectPermissions()->pluck('name')->all();
        $user->syncPermissions($request->input('permissions', []));
        \Modules\Foundation\Support\Audit::record('user.permissions_synced', $user, ['permissions' => $before], ['permissions' => $request->input('permissions', [])]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Direct permissions updated.']);
    }

    private function syncBranches(User $user, Request $request): void
    {
        $ids = collect($request->input('branch_ids', []));
        $primary = $request->input('primary_branch_id');
        $user->branches()->sync($ids->mapWithKeys(fn ($id) => [$id => ['is_primary' => $id == $primary]])->all());
        $user->update(['primary_branch_id' => $primary && $ids->contains($primary) ? $primary : $ids->first()]);
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'employee_number' => ['nullable', 'string', 'max:50', Rule::unique('users', 'employee_number')->ignore($ignoreId)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreId)],
            'username' => ['nullable', 'string', 'max:50', Rule::unique('users', 'username')->ignore($ignoreId)],
            'phone' => 'nullable|string|max:50',
            'gender' => 'nullable|string|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'employment_type' => 'nullable|string|max:50',
            'employment_start_date' => 'nullable|date',
            'profile_photo' => 'nullable|image|max:2048',
            'job_title' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'primary_branch_id' => 'nullable|exists:branches,id',
            'branch_ids' => 'array',
            'branch_ids.*' => 'exists:branches,id',
            'roles' => 'array',
            'roles.*' => 'exists:roles,name',
        ]);
    }
}
