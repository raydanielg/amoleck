<?php

namespace Modules\Foundation\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Modules\Foundation\Support\PermissionCatalog;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        $counts = Permission::withCount('roles')->get()->keyBy('name');
        $registry = collect(PermissionCatalog::all())->map(function ($perms, $module) use ($counts) {
            return ['module' => $module, 'permissions' => collect($perms)->map(fn ($meta, $name) => ['name' => $name, 'description' => $meta[0], 'risk_level' => $meta[1], 'roles_count' => $counts[$name]->roles_count ?? 0])->values()];
        })->values();

        return Inertia::render('admin/permissions/index', ['registry' => $registry]);
    }
}
