<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Settings extends Component
{
    use AuthorizesPermission;

    /** form field => setting key */
    public const KEYS = [
        'allow_max' => 'decision.allow_max',
        'challenge_max' => 'decision.challenge_max',
        'rule_weight' => 'scoring.rule_weight',
        'model_weight' => 'scoring.model_weight',
        'otp_ttl' => 'otp.ttl_seconds',
        'otp_attempts' => 'otp.max_attempts',
        'transfer_limit' => 'transfer.default_limit',
        'high_value' => 'cases.high_value_threshold',
        'qa_rate' => 'qa.sample_rate',
        'capacity' => 'alerts.analyst_daily_capacity',
        'require_2fa' => 'security.require_staff_2fa',
    ];

    private const DEFAULTS = [
        'decision.allow_max' => 39, 'decision.challenge_max' => 74, 'scoring.rule_weight' => 0.4, 'scoring.model_weight' => 0.6,
        'otp.ttl_seconds' => 300, 'otp.max_attempts' => 3, 'transfer.default_limit' => 20000, 'cases.high_value_threshold' => 50000,
        'qa.sample_rate' => 0.1, 'alerts.analyst_daily_capacity' => 50, 'security.require_staff_2fa' => false,
    ];

    /** @var array<string, mixed> */
    public array $form = [];

    public ?string $flash = null;

    public function mount(SettingsService $settings): void
    {
        $this->authorizePermission('settings.manage');

        foreach (self::KEYS as $field => $key) {
            $this->form[$field] = $settings->get($key, self::DEFAULTS[$key]);
        }
        $this->form['require_2fa'] = (bool) $this->form['require_2fa'];
    }

    public function save(SettingsService $settings, AuditLogger $audit): void
    {
        $this->authorizePermission('settings.manage');
        $this->flash = null;

        $this->validate([
            'form.allow_max' => ['required', 'integer', 'min:0', 'max:99'],
            'form.challenge_max' => ['required', 'integer', 'gt:form.allow_max', 'max:100'],
            'form.rule_weight' => ['required', 'numeric', 'min:0', 'max:1'],
            'form.model_weight' => ['required', 'numeric', 'min:0', 'max:1'],
            'form.otp_ttl' => ['required', 'integer', 'min:30', 'max:3600'],
            'form.otp_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'form.transfer_limit' => ['required', 'numeric', 'min:0'],
            'form.high_value' => ['required', 'numeric', 'min:0'],
            'form.qa_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'form.capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'form.require_2fa' => ['boolean'],
        ]);

        if (abs((float) $this->form['rule_weight'] + (float) $this->form['model_weight'] - 1.0) > 0.0001) {
            $this->addError('form.model_weight', __('admin.settings.weights_sum'));

            return;
        }

        $old = [];
        $new = [];

        foreach (self::KEYS as $field => $key) {
            $value = $this->cast($field, $this->form[$field]);
            $current = $settings->get($key, self::DEFAULTS[$key]);

            if ($current !== $value && ! (is_numeric($current) && is_numeric($value) && (float) $current === (float) $value)) {
                $old[$key] = $current;
                $new[$key] = $value;
                $settings->set($key, $value);
            }
        }

        if ($new !== []) {
            $audit->log('settings.updated', null, $old, $new);
        }

        $this->flash = $new === [] ? __('admin.settings.no_changes') : __('admin.settings.saved');
    }

    private function cast(string $field, mixed $value): mixed
    {
        return match ($field) {
            'allow_max', 'challenge_max', 'otp_ttl', 'otp_attempts', 'capacity' => (int) $value,
            'rule_weight', 'model_weight', 'qa_rate' => round((float) $value, 4),
            'transfer_limit', 'high_value' => $this->number((float) $value),
            'require_2fa' => (bool) $value,
        };
    }

    private function number(float $value): int|float
    {
        return floor($value) === $value ? (int) $value : $value;
    }

    public function render(): View
    {
        return view('livewire.admin.settings')->title(__('admin.settings.title'));
    }
}
