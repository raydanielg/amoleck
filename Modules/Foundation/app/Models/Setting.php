<?php

namespace Modules\Foundation\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

#[Fillable(['group', 'key', 'branch_id', 'value', 'is_secret'])]
class Setting extends Model
{
    protected $casts = ['is_secret' => 'boolean'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("setting:$key", function () use ($key, $default) {
            $row = static::where('key', $key)->whereNull('branch_id')->first();
            if (! $row) {
                return $default;
            }
            $value = json_decode($row->value, true) ?? $row->value;
            return $row->is_secret ? Crypt::decryptString($value) : $value;
        });
    }

    public static function set(string $group, string $key, mixed $value, bool $secret = false): void
    {
        $encoded = $secret ? Crypt::encryptString((string) $value) : json_encode($value);
        static::updateOrCreate(['key' => $key], ['group' => $group, 'value' => $encoded, 'is_secret' => $secret]);
        Cache::forget("setting:$key");
    }
}
