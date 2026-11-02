<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ModelRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'model_name' => ['required', 'string', 'max:150'],
            'algorithm' => ['nullable', 'string', 'max:100'],
            'version_number' => ['required', 'string', 'max:50'],
            'metrics' => ['required', 'array'],
            'metrics.drift_detected' => ['nullable', 'boolean'],
            'trained_at' => ['nullable', 'date'],
        ];
    }
}
