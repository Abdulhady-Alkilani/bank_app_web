<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\OtpVerifyRequest;
use App\Models\OtpChallenge;
use App\Services\Otp\OtpService;
use Illuminate\Http\JsonResponse;

class OtpController extends Controller
{
    /**
     * POST /otp/verify
     */
    public function verify(OtpVerifyRequest $request, OtpService $otp): JsonResponse
    {
        $challenge = OtpChallenge::findOrFail($request->integer('otp_challenge_id'));

        $verified = $otp->verify($challenge, $request->string('code')->toString());
        $challenge->refresh();

        return response()->json([
            'verified' => $verified,
            'status' => $challenge->status->value,
            'remaining_attempts' => $otp->remainingAttempts($challenge),
            'transaction_status' => $challenge->transaction?->status->value,
        ]);
    }
}
