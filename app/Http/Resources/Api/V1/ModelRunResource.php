<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ModelRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ModelRun $resource
 */
class ModelRunResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'model_id' => $this->resource->model_id,
            'model_name' => $this->resource->model?->model_name,
            'model_status' => $this->resource->model?->status->value,
            'version_number' => $this->resource->version_number,
            'metrics' => $this->resource->metrics,
            'trained_at' => $this->resource->trained_at?->toIso8601String(),
            'drift_detected' => (bool) data_get($this->resource->metrics, 'drift_detected', false),
        ];
    }
}
