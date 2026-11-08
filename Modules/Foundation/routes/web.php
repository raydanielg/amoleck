<?php

use Illuminate\Support\Facades\Route;
use Modules\Foundation\Http\Controllers;

Route::middleware(['auth', 'verified', 'account.active', 'module.enabled:Foundation'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [Controllers\DashboardController::class, 'index'])->name('dashboard')->middleware('can:dashboard.view');

    Route::get('hospital', [Controllers\HospitalController::class, 'edit'])->name('hospital.edit')->middleware('can:hospital.view');
    Route::put('hospital', [Controllers\HospitalController::class, 'update'])->name('hospital.update')->middleware('can:hospital.edit');

    Route::get('branches', [Controllers\BranchController::class, 'index'])->name('branches.index')->middleware('can:branches.view');
    Route::get('branches/create', [Controllers\BranchController::class, 'create'])->name('branches.create')->middleware('can:branches.create');
    Route::post('branches', [Controllers\BranchController::class, 'store'])->name('branches.store')->middleware('can:branches.create');
    Route::get('branches/{branch}', [Controllers\BranchController::class, 'show'])->name('branches.show')->middleware('can:branches.view');
    Route::get('branches/{branch}/edit', [Controllers\BranchController::class, 'edit'])->name('branches.edit')->middleware('can:branches.edit');
    Route::put('branches/{branch}', [Controllers\BranchController::class, 'update'])->name('branches.update')->middleware('can:branches.edit');
    Route::post('branches/{branch}/status', [Controllers\BranchController::class, 'updateStatus'])->name('branches.status')->middleware('can:branches.edit');

    Route::get('users', [Controllers\UserController::class, 'index'])->name('users.index')->middleware('can:users.view');
    Route::get('users/create', [Controllers\UserController::class, 'create'])->name('users.create')->middleware('can:users.create');
    Route::post('users', [Controllers\UserController::class, 'store'])->name('users.store')->middleware('can:users.create');
    Route::get('users/{user}', [Controllers\UserController::class, 'show'])->name('users.show')->middleware('can:users.view');
    Route::get('users/{user}/edit', [Controllers\UserController::class, 'edit'])->name('users.edit')->middleware('can:users.edit');
    Route::put('users/{user}', [Controllers\UserController::class, 'update'])->name('users.update')->middleware('can:users.edit');
    Route::post('users/bulk-status', [Controllers\UserController::class, 'bulkStatus'])->name('users.bulk-status')->middleware('can:users.suspend');
    Route::post('users/{user}/status', [Controllers\UserController::class, 'updateStatus'])->name('users.status')->middleware('can:users.suspend');
    Route::post('users/{user}/reset-password', [Controllers\UserController::class, 'resetPassword'])->name('users.reset-password')->middleware('can:users.reset_password');

    Route::get('roles', [Controllers\RoleController::class, 'index'])->name('roles.index')->middleware('can:roles.view');
    Route::get('roles/create', [Controllers\RoleController::class, 'create'])->name('roles.create')->middleware('can:roles.create');
    Route::post('roles', [Controllers\RoleController::class, 'store'])->name('roles.store')->middleware('can:roles.create');
    Route::get('roles/{role}', [Controllers\RoleController::class, 'show'])->name('roles.show')->middleware('can:roles.view');
    Route::post('roles/{role}/status', [Controllers\RoleController::class, 'updateStatus'])->name('roles.status')->middleware('can:roles.edit');
    Route::get('roles/{role}/edit', [Controllers\RoleController::class, 'edit'])->name('roles.edit')->middleware('can:roles.assign_permissions');
    Route::put('roles/{role}', [Controllers\RoleController::class, 'update'])->name('roles.update')->middleware('can:roles.assign_permissions');
    Route::delete('roles/{role}', [Controllers\RoleController::class, 'destroy'])->name('roles.destroy')->middleware('can:roles.delete');
    Route::post('roles/{role}/duplicate', [Controllers\RoleController::class, 'duplicate'])->name('roles.duplicate')->middleware('can:roles.create');

    Route::get('permissions', [Controllers\PermissionController::class, 'index'])->name('permissions.index')->middleware('can:permissions.view');
    Route::get('users/{user}/permissions', [Controllers\UserController::class, 'permissions'])->name('users.permissions')->middleware('can:users.permissions');
    Route::put('users/{user}/permissions', [Controllers\UserController::class, 'syncPermissions'])->name('users.permissions.update')->middleware('can:users.permissions');

    Route::get('modules', [Controllers\ModuleController::class, 'index'])->name('modules.index')->middleware('can:modules.view');
    Route::post('modules/{module}/toggle', [Controllers\ModuleController::class, 'toggle'])->name('modules.toggle')->middleware('can:modules.manage');
    Route::get('modules/{module}', [Controllers\ModuleController::class, 'show'])->name('modules.show')->middleware('can:modules.view');
    Route::post('modules/{module}/maintenance', [Controllers\ModuleController::class, 'maintenance'])->name('modules.maintenance')->middleware('can:modules.manage');
    Route::put('modules/{module}/access', [Controllers\ModuleController::class, 'updateAccess'])->name('modules.access')->middleware('can:modules.manage');

    Route::post('branch-context', function (\Illuminate\Http\Request $request) {
        $data = $request->validate(['branch_id' => 'required|integer|exists:branches,id']);
        abort_unless(\Modules\Foundation\Support\BranchContext::canAccess($request->user(), (int) $data['branch_id']), 403, 'You cannot access this branch.');
        $request->session()->put('current_branch_id', (int) $data['branch_id']);
        \Modules\Foundation\Support\Audit::record('branch.context_switched', null, null, ['branch_id' => (int) $data['branch_id']]);

        return back();
    })->name('branch-context.update');

    Route::get('settings', [Controllers\SettingController::class, 'edit'])->name('settings.edit')->middleware('can:foundation.settings.view');
    Route::put('settings', [Controllers\SettingController::class, 'update'])->name('settings.update')->middleware('can:foundation.settings.update');
    Route::delete('settings/secret', [Controllers\SettingController::class, 'deleteSecret'])->name('settings.secret.delete')->middleware('can:foundation.settings.manage_secrets');
    Route::delete('settings/override', [Controllers\SettingController::class, 'removeOverride'])->name('settings.override.delete')->middleware('can:foundation.settings.manage_branch_overrides');
    Route::get('settings/history', [Controllers\SettingController::class, 'history'])->name('settings.history')->middleware('can:foundation.settings.view_history');

    Route::get('audit-logs', [Controllers\AuditLogController::class, 'index'])->name('audit-logs.index')->middleware('can:audit_logs.view');
    Route::get('login-history', [Controllers\AuditLogController::class, 'logins'])->name('login-history.index')->middleware('can:login_history.view');
});
