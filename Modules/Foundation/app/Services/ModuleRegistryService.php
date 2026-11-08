<?php

namespace Modules\Foundation\Services;

use Illuminate\Support\Collection;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\SystemModule;
use Nwidart\Modules\Facades\Module;

class ModuleRegistryService
{
    /** Static metadata for known modules; installed modules self-register on sync. */
    public const META = [
        'Foundation' => ['category' => 'core', 'icon' => 'shield', 'dependencies' => [], 'features' => ['rbac', 'branches', 'staff', 'settings', 'audit', 'modules'], 'protected' => true],
        'PatientCare' => ['category' => 'clinical', 'icon' => 'stethoscope', 'dependencies' => ['Foundation'], 'features' => ['patient_registration', 'clinical_assessment', 'treatment_plans', 'therapy_sessions'], 'protected' => false],
        'HospitalOperations' => ['category' => 'operations', 'icon' => 'calendar', 'dependencies' => ['Foundation'], 'features' => ['appointments', 'queues', 'scheduling'], 'protected' => false],
        'Finance' => ['category' => 'finance', 'icon' => 'banknote', 'dependencies' => ['Foundation'], 'features' => ['billing', 'payments', 'invoices', 'expenses'], 'protected' => false],
        'Administration' => ['category' => 'admin', 'icon' => 'briefcase', 'dependencies' => ['Foundation'], 'features' => ['governance'], 'protected' => false],
        'Reporting' => ['category' => 'analytics', 'icon' => 'chart', 'dependencies' => ['Foundation'], 'features' => ['analytics', 'exports'], 'protected' => false],
    ];

    /** Reconcile DB registry with installed modules. */
    public function sync(): void
    {
        $installed = collect(Module::all())->keyBy(fn ($m) => $m->getName());

        foreach (self::META as $name => $meta) {
            $mod = SystemModule::firstOrCreate(
                ['identifier' => strtolower($name)],
                [
                    'name' => $name,
                    'description' => ($installed->get($name)?->getDescription()) ?? ($meta['category'].' module'),
                    'version' => '1.0.0',
                    'category' => $meta['category'],
                    'icon' => $meta['icon'],
                    'dependencies' => $meta['dependencies'],
                    'features' => $meta['features'],
                    'is_protected' => $meta['protected'],
                    'status' => 'pending_installation',
                    'registered_by' => auth()->id(),
                ]
            );

            if ($installed->has($name)) {
                $nw = $installed[$name];
                if (in_array($mod->status, ['pending_installation', 'unavailable'])) {
                    $mod->update(['status' => $nw->isEnabled() ? 'active' : 'disabled']);
                }
                // nwidart enable-bit = installed; DB status = operational state
                if (! $nw->isEnabled()) { $nw->enable(); }
            } else {
                $mod->update(['status' => 'unavailable']);
            }
        }
    }

    public function all(): Collection
    {
        return SystemModule::with('roles:id,name')->orderBy('id')->get();
    }

    public function findByName(string $name): ?SystemModule
    {
        return SystemModule::where('identifier', strtolower($name))->first();
    }

    public function statusOf(string $name): string
    {
        $mod = $this->findByName($name);
        if ($mod) {
            return $mod->status;
        }
        // Not yet registered: fall back to the installed module's enable state
        $nw = Module::find($name);
        return $nw ? ($nw->isEnabled() ? 'active' : 'disabled') : 'unavailable';
    }

    public function isAvailable(string $name): bool
    {
        return $this->statusOf($name) === 'active';
    }

    /** Module availability AND role binding check (Super Admin always allowed). */
    public function isAccessibleBy(string $name, $user): bool
    {
        $mod = $this->findByName($name);
        if (! $mod) {
            // Unregistered module: accessible when enabled, no role bindings
            return $this->statusOf($name) === 'active';
        }
        if (! in_array($mod->status, ['active', 'maintenance'])) {
            return false;
        }
        if ($user->hasRole('Super Admin')) {
            return true;
        }
        $bound = $mod->roles()->pluck('roles.name');
        if ($bound->isEmpty()) {
            return true; // no bindings = open to all authorized users
        }

        return $user->hasAnyRole($bound->all());
    }

    public function setStatus(string $name, string $status): SystemModule
    {
        $mod = $this->findByName($name);
        abort_unless($mod, 404);
        abort_unless(in_array($status, SystemModule::STATUSES), 422, 'Invalid status.');
        abort_if($mod->is_protected && $status !== 'active', 403, 'This module is required and cannot be '.$status.'.');
        abort_if($mod->status === 'unavailable' && $status === 'active', 422, 'Module is not installed.');

        if ($status === 'active') {
            foreach ($mod->dependencies ?? [] as $dep) {
                abort_unless($this->statusOf($dep) === 'active', 422, "Module {$dep} must be active first.");
            }
        }
        if (in_array($status, ['disabled', 'maintenance', 'unavailable'])) {
            $dependents = SystemModule::where('status', 'active')->get()
                ->filter(fn ($m) => in_array($mod->name, $m->dependencies ?? []));
            abort_if($dependents->isNotEmpty(), 422, 'Active module(s) depend on this: '.$dependents->pluck('name')->join(', '));
        }

        $before = $mod->status;
        $mod->update(['status' => $status]);

        $nw = Module::find($mod->name);
        if ($nw && ! $nw->isEnabled()) { $nw->enable(); }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => "module.{$status}",
            'entity_type' => SystemModule::class,
            'entity_id' => $mod->id,
            'before' => ['status' => $before],
            'after' => ['status' => $status],
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return $mod->fresh();
    }

    public function syncRoleAccess(string $name, array $roleIds): void
    {
        $mod = $this->findByName($name);
        abort_unless($mod, 404);
        $before = $mod->roles()->pluck('roles.id')->all();
        $mod->roles()->sync($roleIds);
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'module.access_updated',
            'entity_type' => SystemModule::class,
            'entity_id' => $mod->id,
            'before' => ['role_ids' => $before],
            'after' => ['role_ids' => $roleIds],
            'created_at' => now(),
        ]);
    }

    /** Detect dependency cycles before persisting dependency changes. */
    public function hasCircularDependency(string $name, array $deps): bool
    {
        $visit = function ($n, $chain) use (&$visit) {
            if ($n === $chain[0] && count($chain) > 1) {
                return true;
            }
            $mod = $this->findByName($n);
            foreach ($mod->dependencies ?? [] as $d) {
                if ($visit($d, [...$chain, $d])) {
                    return true;
                }
            }
            return false;
        };

        return in_array($name, $deps) || $visit($name, [$name]);
    }

    public function setEnabled(string $moduleName, bool $enabled, ?int $actorId = null): void
    {
        $this->setStatus($moduleName, $enabled ? 'active' : 'disabled');
    }
}
