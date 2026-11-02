<?php

namespace App\Notifications;

use App\Models\ModelRun;
use Illuminate\Notifications\Notification;

class ModelDriftNotification extends Notification
{
    public function __construct(public readonly ModelRun $run) {}

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
        $model = $this->run->model?->model_name;

        return [
            'model_run_id' => $this->run->id,
            'model_id' => $this->run->model_id,
            'model_name' => $model,
            'version' => $this->run->version_number,
            'message' => __('scoring.notification.drift', ['model' => $model, 'version' => $this->run->version_number], 'ar'),
        ];
    }
}
