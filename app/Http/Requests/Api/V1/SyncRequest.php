<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * EXT-05: batch upsert of customers with their accounts and devices.
 */
class SyncRequest extends FormRequest
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
            'customers' => ['required', 'array', 'min:1', 'max:500'],
            'customers.*.name' => ['required', 'string', 'max:150'],
            'customers.*.email' => ['nullable', 'email', 'max:190', 'required_without:customers.*.phone'],
            'customers.*.phone' => ['nullable', 'string', 'max:30'],
            'customers.*.avg_balance' => ['nullable', 'numeric', 'min:0'],
            'customers.*.usage_pattern' => ['nullable', 'array'],
            'customers.*.accounts' => ['nullable', 'array', 'max:50'],
            'customers.*.accounts.*.account_number' => ['required', 'string', 'max:64'],
            'customers.*.accounts.*.type' => ['nullable', 'string', 'max:30'],
            'customers.*.accounts.*.status' => ['nullable', 'in:active,frozen,closed'],
            'customers.*.accounts.*.balance' => ['nullable', 'numeric'],
            'customers.*.accounts.*.transfer_limit' => ['nullable', 'numeric', 'min:0'],
            'customers.*.devices' => ['nullable', 'array', 'max:50'],
            'customers.*.devices.*.device_fingerprint' => ['required', 'string', 'max:255'],
            'customers.*.devices.*.device_type' => ['nullable', 'string', 'max:50'],
            'customers.*.devices.*.is_trusted' => ['nullable', 'boolean'],
        ];
    }
}
