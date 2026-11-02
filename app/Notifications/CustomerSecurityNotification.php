<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * In-app (database) security notice for a customer: a held/blocked transaction or a frozen account.
 * Rendered by the shared notification bell through data.message / data.url.
 */
class CustomerSecurityNotification extends Notification
{
    public function __construct(
        public readonly string $message,
        public readonly ?string $url = null,
        public readonly string $type = 'security',
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
        return [
            'type' => $this->type,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}
