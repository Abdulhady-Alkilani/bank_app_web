<?php

namespace App\Http\Requests\Api\V1;

use App\Support\EventPayload;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload of POST /events and POST /score (same shape as ScoringPipeline::process()).
 * The Idempotency-Key header is accepted as an alternative to the idempotency_key field.
 */
class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('idempotency_key') && $this->header('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = EventPayload::rules($this->input('event_type'));

        if ($this->input('event_type') !== 'open_account') {
            $rules['account_number'][] = 'exists:accounts,account_number';
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->validated();
    }
}
