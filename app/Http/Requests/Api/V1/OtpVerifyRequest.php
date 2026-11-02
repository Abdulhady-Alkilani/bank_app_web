<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class OtpVerifyRequest extends FormRequest
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
            'otp_challenge_id' => ['required', 'integer', 'exists:otp_challenges,id'],
            'code' => ['required', 'string', 'digits:6'],
        ];
    }
}
