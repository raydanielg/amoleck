<?php

namespace Modules\Foundation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Branch;
use Modules\Foundation\Models\LoginHistory;
use Modules\Foundation\Models\SystemModule;
use Modules\Foundation\Services\ModuleRegistryService;
use Modules\Foundation\Support\BranchContext;

class DashboardController extends Controller
{
    public function index(Request $request, ModuleRegistryService $svc)
    {
        $user = $request->user();
        $can = fn (string $perm) => $user->can($perm);
        $svc->sync();

        $stats = array_filter([
            'totalStaff' => $can('users.view') ? User::count() : null,
            'activeStaff' => $can('users.view') ? User::where('status', 'active')->count() : null,
            'inactiveAccounts' => $can('users.view') ? User::where('status', '!=', 'active')->count() : null,
            'suspendedUsers' => $can('users.view') ? User::where('status', 'suspended')->count() : null,
            'totalBranches' => $can('branches.view') ? Branch::count() : null,
            'activeBranches' => $can('branches.view') ? Branch::where('status', 'active')->count() : null,
            'activeModules' => $can('modules.view') ? SystemModule::where('status', 'active')->count() : null,
            'disabledModules' => $can('modules.view') ? SystemModule::where('status', 'disabled')->count() : null,
        ], fn ($v) => $v !== null);

        $branches = $can('branches.view')
            ? BranchContext::allowed($user)->loadCount('users')->map(fn ($b) => [
                'id' => $b->id, 'name' => $b->name, 'code' => $b->code,
                'manager_name' => $b->manager_name, 'users_count' => $b->users_count, 'status' => $b->status,
            ])
            : [];

        $security = $can('login_history.view') || $can('audit_logs.view') ? [
            'recentLogins' => LoginHistory::with('user:id,name')->where('event', 'login')->latest('created_at')->limit(6)->get(['id', 'user_id', 'email', 'ip_address', 'created_at']),
            'failedLogins' => LoginHistory::where('event', 'failed')->latest('created_at')->limit(6)->get(['id', 'email', 'ip_address', 'created_at']),
            'lockedAccounts' => User::where('status', 'locked')->orWhere('locked_until', '>', now())->count(),
            'rbacChanges' => AuditLog::with('user:id,name')->where('action', 'like', 'role.%')->orWhere('action', 'like', 'user.permissions%')->latest('created_at')->limit(6)->get(['id', 'user_id', 'action', 'created_at']),
        ] : null;

        $activity = AuditLog::with('user:id,name')
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->branch_id, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($request->action, fn ($q, $v) => $q->where('action', 'like', "{$v}%"))
            ->when($request->from, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('created_at')->limit(10)->get(['id', 'user_id', 'action', 'entity_type', 'branch_id', 'created_at']);

        return Inertia::render('admin/dashboard', [
            'stats' => $stats,
            'branches' => $branches,
            'security' => $security,
            'activity' => $activity,
            'filters' => $request->only(['user_id', 'branch_id', 'action', 'from', 'to']),
        ]);
    }
}
