<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Usage: permission:alerts.view,alerts.decide — passes when the user has ANY of the listed permissions.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        $allowed = $user && collect($permissions)->contains(fn (string $permission) => $user->hasPermission($permission));

        abort_unless($allowed, 403, __('common.forbidden'));

        return $next($request);
    }
}
