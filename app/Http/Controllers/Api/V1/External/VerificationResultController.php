<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Enums\LabelSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\VerificationResultRequest;
use App\Models\AccountEvent;
use App\Models\OtpChallenge;
use App\Models\Transaction;
use App\Services\Labels\LabelService;
use Illuminate\Http\JsonResponse;

class VerificationResultController extends Controller
{
    /**
     * POST /verification-results (EXT-04) — final outcome from the bank becomes a training label.
     * An OTP that was verified labels the subject legitimate; an OTP that failed (max attempts) labels it fraud.
     */
    public function store(VerificationResultRequest $request, LabelService $labels): JsonResponse
    {
        $type = $request->string('type')->toString();
        $id = $request->integer('reference_id');
        $outcome = $request->string('outcome')->toString();

        $transaction = null;
        $event = null;

        if ($type === 'transaction') {
            $transaction = Transaction::findOrFail($id);
        } elseif ($type === 'event') {
            $event = AccountEvent::findOrFail($id);
        } else {
            $challenge = OtpChallenge::findOrFail($id);
            $transaction = $challenge->transaction;
            $event = $challenge->event;
        }

        $isFraud = in_array($outcome, ['fraud', 'failed'], true);
        $default = $type === 'otp' ? LabelSource::Customer : LabelSource::Bank;
        $source = $request->filled('source') ? LabelSource::from($request->string('source')->toString()) : $default;

        $label = $labels->record($transaction, $event, null, $isFraud, $source);

        return response()->json(['label_id' => $label->id, 'is_fraud' => $isFraud, 'source' => $source->value], 201);
    }
}
