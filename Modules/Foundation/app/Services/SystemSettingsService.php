<?php

namespace Modules\Foundation\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\Setting;
use Modules\Foundation\Support\SettingsRegistry;

class SystemSettingsService
{
    public function get(string $key, ?int $branchId = null): mixed
    {
        $meta = SettingsRegistry::find($key);
        abort_unless($meta, 404, "Unknown setting: {$key}");

        if ($branchId && ($meta['scope'] ?? 'global') === 'branch') {
            $row = $this->row($key, $branchId);
            if ($row) {
                return $this->cast($meta['type'], $this->decode($row));
            }
        }

        $row = $this->row($key, null);

        return $row ? $this->cast($meta['type'], $this->decode($row)) : $meta['default'] ?? null;
    }

    public function set(string $key, mixed $value, ?int $branchId = null, ?User $actor = null): void
    {
        $meta = SettingsRegistry::find($key);
        abort_unless($meta, 422, "Setting {$key} is not registered.");
        abort_if($meta['readonly'] ?? false, 422, "Setting {$key} is deployment-managed and cannot be changed here.");
        abort_if($branchId && ($meta['scope'] ?? 'global') !== 'branch', 422, "Setting {$key} does not support branch overrides.");

        $actor ??= auth()->user();
        abort_unless($actor?->can($meta['perm']), 403, 'You cannot modify this setting.');

        $isSecret = $meta['type'] === 'secret';
        // Empty input must never overwrite a stored secret
        if ($isSecret && ($value === null || $value === '')) {
            return;
        }

        Validator::make(['value' => $value], ['value' => $meta['rules']])->validate();

        DB::transaction(function () use ($key, $value, $branchId, $meta, $isSecret, $actor) {
            $row = Setting::firstOrNew(['key' => $key, 'branch_id' => $branchId]);
            $before = $row->exists ? $this->decode($row) : null;

            $row->group = $this->categoryOf($key);
            $row->is_secret = $isSecret;
            $row->value = $isSecret ? Crypt::encryptString((string) $value) : json_encode($value);
            $row->save();

            AuditLog::create([
                'user_id' => $actor?->id,
                'action' => 'setting.updated',
                'entity_type' => Setting::class,
                'entity_id' => $row->id,
                'before' => $isSecret ? null : ['value' => $before],
                'after' => $isSecret ? ['value' => '********'] : ['value' => $value],
                'ip_address' => request()->ip(),
                'branch_id' => $branchId,
                'created_at' => now(),
            ]);
        });

        $this->invalidateCache($key, $branchId);
    }

    public function deleteSecret(string $key, ?int $branchId = null, ?User $actor = null): void
    {
        $meta = SettingsRegistry::find($key);
        abort_unless($meta && $meta['type'] === 'secret', 404);
        abort_unless(($actor ?? auth()->user())?->can($meta['perm']), 403);

        Setting::where('key', $key)->where('branch_id', $branchId)->delete();
        AuditLog::create([
            'user_id' => ($actor ?? auth()->user())?->id,
            'action' => 'setting.secret_removed',
            'after' => ['key' => $key],
            'branch_id' => $branchId,
            'created_at' => now(),
        ]);
        $this->invalidateCache($key, $branchId);
    }

    /** Grouped registry + resolved values; secrets masked. */
    public function groupedValues(?int $branchId = null): array
    {
        return collect(SettingsRegistry::all())->map(fn ($keys, $category) => [
            'category' => $category,
            'settings' => collect($keys)->map(fn ($meta, $key) => [
                'key' => $key,
                'label' => $meta['label'],
                'type' => $meta['type'],
                'options' => $meta['options'] ?? null,
                'scope' => $meta['scope'] ?? 'global',
                'readonly' => $meta['readonly'] ?? false,
                'restart' => $meta['restart'] ?? false,
                'perm' => $meta['perm'],
                'value' => $meta['type'] === 'secret'
                    ? ($this->row($key, $branchId) ? '********' : '')
                    : $this->get($key, $branchId),
                'has_value' => (bool) $this->row($key, $branchId) || ($meta['default'] ?? null) !== null,
            ])->values(),
        ])->values()->all();
    }

    public function removeBranchOverride(string $key, int $branchId, ?User $actor = null): void
    {
        $meta = SettingsRegistry::find($key);
        abort_unless($meta && ($meta['scope'] ?? '') === 'branch', 404);
        abort_unless(($actor ?? auth()->user())?->can('foundation.settings.manage_branch_overrides'), 403);
        Setting::where('key', $key)->where('branch_id', $branchId)->delete();
        $this->invalidateCache($key, $branchId);
    }

    private function row(string $key, ?int $branchId): ?Setting
    {
        return Cache::rememberForever("setting:{$key}:".($branchId ?? 0), fn () => Setting::where('key', $key)->where('branch_id', $branchId)->first());
    }

    private function decode(Setting $row): mixed
    {
        return $row->is_secret ? Crypt::decryptString($row->value) : (json_decode($row->value, true) ?? $row->value);
    }

    private function cast(string $type, mixed $value): mixed
    {
        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value,
            'int' => (int) $value,
            'decimal' => (float) $value,
            'json' => is_string($value) ? (json_decode($value, true) ?? $value) : $value,
            default => $value,
        };
    }

    private function categoryOf(string $key): string
    {
        foreach (SettingsRegistry::all() as $category => $keys) {
            if (array_key_exists($key, $keys)) {
                return $category;
            }
        }
        return 'general';
    }

    private function invalidateCache(string $key, ?int $branchId = null): void
    {
        Cache::forget("setting:{$key}:".($branchId ?? 0));
        Cache::forget("setting:{$key}");
    }
}
