<?php

namespace Modules\Foundation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Foundation\Models\AuditLog;
use Modules\Foundation\Models\LoginHistory;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('admin/audit-logs', [
            'logs' => AuditLog::with('user:id,name')
                ->when($request->search, fn ($q, $s) => $q->where('action', 'like', "%$s%"))
                ->latest('created_at')->paginate(20)->withQueryString(),
            'filters' => $request->only('search'),
        ]);
    }

    public function logins(Request $request)
    {
        return Inertia::render('admin/login-history', [
            'logs' => LoginHistory::with('user:id,name')
                ->latest('created_at')->paginate(20)->withQueryString(),
        ]);
    }
}
