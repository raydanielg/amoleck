<?php

namespace Modules\Foundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Foundation\Services\ModuleRegistryService;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $svc = app(ModuleRegistryService::class);
        $status = $svc->statusOf($module);
        $user = $request->user();

        // unavailable/disabled → always reject; maintenance → only module managers pass
        $allowed = $status === 'active'
            || ($status === 'maintenance' && $user?->can('modules.manage'));

        abort_unless($allowed, 403, 'This module is currently '.$status.'.');
        abort_unless($svc->isAccessibleBy($module, $user), 403, 'Your role does not have access to this module.');

        return $next($request);
    }
}
