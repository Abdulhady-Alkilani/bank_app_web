<?php

namespace App\Notifications;

use App\Models\Alert;
use App\Support\Masker;
use Illuminate\Notifications\Notification;

class HighRiskAlertNotification extends Notification
{
    public function __construct(
        public readonly Alert $alert,
        public readonly string $accountNumber,
        public readonly int $score,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $account = Masker::maskAccount($this->accountNumber);

        return [
            'alert_id' => $this->alert->id,
            'score' => $this->score,
            'priority' => $this->alert->priority->value,
            'account' => $account,
            'message' => __('scoring.notification.high_risk', [
                'priority' => $this->alert->priority->label(),
                'score' => $this->score,
                'account' => $account,
            ], 'ar'),
        ];
    }
}
