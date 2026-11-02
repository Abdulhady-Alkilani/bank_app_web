<?php

namespace App\Livewire\Profile;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Component;

class Security extends Component
{
    public string $code = '';

    public bool $showRecoveryCodes = false;

    public function render(): View
    {
        $user = Auth::user()->fresh();

        return view('livewire.profile.security', [
            'user' => $user,
            'hasSecret' => filled($user->two_factor_secret),
            'confirmed' => filled($user->two_factor_confirmed_at),
            'qrSvg' => filled($user->two_factor_secret) && blank($user->two_factor_confirmed_at) ? $user->twoFactorQrCodeSvg() : null,
            'setupKey' => filled($user->two_factor_secret) && blank($user->two_factor_confirmed_at)
                ? decrypt($user->two_factor_secret)
                : null,
            'recoveryCodes' => $this->showRecoveryCodes && filled($user->two_factor_secret) ? $user->recoveryCodes() : [],
        ])->title(__('common.security'));
    }

    public function enable(EnableTwoFactorAuthentication $enable): void
    {
        $enable(Auth::user());
        $this->code = '';
    }

    public function confirmSetup(ConfirmTwoFactorAuthentication $confirm): void
    {
        $this->resetErrorBag();
        $confirm(Auth::user(), $this->code);

        $this->code = '';
        $this->showRecoveryCodes = true;
        session()->flash('status', __('common.tfa.enabled'));
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        $generate(Auth::user());
        $this->showRecoveryCodes = true;
    }

    public function toggleRecoveryCodes(): void
    {
        $this->showRecoveryCodes = ! $this->showRecoveryCodes;
    }

    public function disable(DisableTwoFactorAuthentication $disable): void
    {
        $disable(Auth::user());
        $this->showRecoveryCodes = false;
        $this->code = '';
    }
}
