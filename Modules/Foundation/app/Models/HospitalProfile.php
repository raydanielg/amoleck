<?php

namespace Modules\Foundation\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'code', 'logo', 'country', 'description', 'slogan', 'registration_number', 'license_number', 'email', 'phone', 'secondary_phone', 'website', 'address', 'region', 'district', 'postal_code', 'working_hours', 'timezone', 'currency', 'report_header', 'report_footer', 'stamp', 'signatories', 'updated_by'])]
class HospitalProfile extends Model
{
    protected $casts = ['signatories' => 'array', 'working_hours' => 'array'];

    public static function current(): self
    {
        return static::firstOrCreate([], ['name' => config('app.name')]);
    }
}
