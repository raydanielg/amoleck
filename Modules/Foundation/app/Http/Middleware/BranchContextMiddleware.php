<?php

namespace Modules\Foundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Foundation\Support\BranchContext;
use Symfony\Component\HttpFoundation\Response;

class BranchContextMiddleware
{
    public function handle(Request $request, Closure $next, ?string $param = null): Response
    {
        $user = $request->user();
        if ($user) {
            $branchId = $param ? $request->route($param) : $request->input('branch_id', $request->header('X-Branch'));
            if ($branchId && ! BranchContext::canAccess($user, (int) $branchId)) {
                abort(403, 'You do not have access to this branch.');
            }
            if ($branchId) {
                $request->session()->put('current_branch_id', (int) $branchId);
            }
        }

        return $next($request);
    }
}
