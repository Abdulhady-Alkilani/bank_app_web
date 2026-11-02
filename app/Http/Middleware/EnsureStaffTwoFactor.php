<?php

namespace App\Http\Middleware;

use App\Services\Settings\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffTwoFactor
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * When `security.require_staff_2fa` is on, staff must enable two-factor authentication before continuing.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && $user->isStaff()
            && $this->settings->get('security.require_staff_2fa', false)
            && ! $user->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('profile.security')->with('warning', __('common.two_factor_required'));
        }

        return $next($request);
    }
}
