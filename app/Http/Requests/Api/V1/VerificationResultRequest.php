<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * EXT-04: final outcome reported by the bank.
 *  type=transaction, reference_id=<transactions.id>, outcome=fraud|legitimate
 *  type=event,       reference_id=<account_events.id>, outcome=fraud|legitimate
 *  type=otp,         reference_id=<otp_challenges.id>, outcome=verified|failed
 */
class VerificationResultRequest extends FormRequest
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
            'type' => ['required', Rule::in(['transaction', 'event', 'otp'])],
            'reference_id' => ['required', 'integer', 'min:1'],
            'outcome' => ['required', Rule::in($this->input('type') === 'otp' ? ['verified', 'failed'] : ['fraud', 'legitimate'])],
            'source' => ['nullable', Rule::in(['bank', 'customer'])],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
