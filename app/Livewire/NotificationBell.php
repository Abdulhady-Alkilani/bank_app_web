<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

/**
 * Header bell: polls unread database notifications. Generic - any notification whose data carries
 * `message` (and optionally `url` or `alert_id` + `score`) is rendered.
 */
class NotificationBell extends Component
{
    public function markRead(string $id): void
    {
        $notification = $this->notificationFor($id);
        $notification?->markAsRead();
    }

    public function openNotification(string $id): mixed
    {
        $notification = $this->notificationFor($id);
        if (! $notification) {
            return null;
        }

        $notification->markAsRead();
        $url = $this->urlFor($notification);

        return $url ? $this->redirect($url, navigate: true) : null;
    }

    public function markAllRead(): void
    {
        auth()->user()?->unreadNotifications()->update(['read_at' => now()]);
    }

    public function render(): View
    {
        $user = auth()->user();

        $notifications = $user
            ? $user->unreadNotifications()->latest()->limit(10)->get()->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'message' => $this->messageFor($n),
                'score' => $n->data['score'] ?? null,
                'url' => $this->urlFor($n),
                'time' => $n->created_at?->diffForHumans(),
            ])
            : collect();

        return view('livewire.notification-bell', [
            'notifications' => $notifications,
            'unread' => $user ? $user->unreadNotifications()->count() : 0,
        ]);
    }

    private function notificationFor(string $id): ?DatabaseNotification
    {
        return auth()->user()?->notifications()->whereKey($id)->first();
    }

    private function messageFor(DatabaseNotification $notification): string
    {
        $data = $notification->data;

        if (isset($data['message'])) {
            return (string) $data['message'];
        }

        if (isset($data['alert_id'])) {
            return __('analyst.notifications.new_alert', ['id' => $data['alert_id']]);
        }

        return class_basename($notification->type);
    }

    private function urlFor(DatabaseNotification $notification): ?string
    {
        $data = $notification->data;

        if (! empty($data['url']) && is_string($data['url'])) {
            return $data['url'];
        }

        if (isset($data['alert_id']) && Route::has('analyst.alerts.show')) {
            return route('analyst.alerts.show', $data['alert_id']);
        }

        return null;
    }
}
