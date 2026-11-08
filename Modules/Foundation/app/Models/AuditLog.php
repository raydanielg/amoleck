<?php

namespace Modules\Foundation\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'action', 'entity_type', 'entity_id', 'before', 'after', 'ip_address', 'user_agent', 'branch_id', 'created_at'])]
class AuditLog extends Model
{
    public $timestamps = false;
    protected $casts = ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
