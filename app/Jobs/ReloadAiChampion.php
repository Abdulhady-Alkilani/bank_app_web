<?php

namespace App\Jobs;

use App\Models\MlModel;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * L5: tells the AI service to load the model version of the champion that was just promoted in the admin UI.
 * A failure is logged and audited but never undoes the promotion.
 */
class ReloadAiChampion implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $mlModelId) {}

    public function handle(AuditLogger $audit): void
    {
        $model = MlModel::with(['runs' => fn ($q) => $q->latest('trained_at')->latest('id')])->find($this->mlModelId);
        $run = $model?->runs->first();

        if (! $model || ! $run) {
            return;
        }

        $body = ['model_name' => $model->model_name, 'model_version' => $run->version_number];

        try {
            $response = Http::baseUrl(rtrim((string) config('fraud.ai.url'), '/'))
                ->acceptJson()
                ->asJson()
                ->withToken((string) config('fraud.ai.admin_token'))
                ->timeout(10)
                ->post('/admin/reload', $body);

            if ($response->successful()) {
                $audit->log('model.reload_requested', $model, [], $body + ['status' => $response->status()], user: null);

                return;
            }

            $error = 'HTTP '.$response->status();
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        Log::warning('AI service reload after promotion failed', $body + ['error' => $error]);
        $audit->log('model.reload_failed', $model, [], $body + ['error' => $error], user: null);
    }
}
